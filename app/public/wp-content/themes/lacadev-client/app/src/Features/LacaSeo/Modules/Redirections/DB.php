<?php

namespace App\Features\LacaSeo\Modules\Redirections;

defined('ABSPATH') || exit;

/**
 * Database operations for Redirections (1:1 with Rank Math Redirections DB).
 */
class DB
{
    /**
     * Get table name.
     */
    public static function getTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_seo_redirections';
    }

    /**
     * Clean pattern for regex/exact comparison.
     */
    public static function getCleanPattern(string $pattern, string $comparison): string
    {
        if ('exact' === $comparison) {
            $pattern = trim($pattern, '/');
        }

        $cleaned = 'regex' === $comparison ? ('@' . stripslashes($pattern) . '@') : $pattern;

        return apply_filters('laca_seo/redirection/get_clean_pattern', $cleaned, $pattern, $comparison);
    }

    /**
     * Compare sources array against given URI.
     */
    public static function compareSources(array $sources, string $uri): bool
    {
        if (empty($sources)) {
            return false;
        }

        foreach ($sources as $source) {
            $compareUri = $uri;
            $comparison = $source['comparison'] ?? 'exact';

            if ('exact' === $comparison) {
                $compareUri = untrailingslashit($compareUri);
            }

            if (in_array($comparison, ['contains', 'start', 'end'], true)) {
                $source['pattern'] = untrailingslashit($source['pattern']);
            }

            if ('exact' === $comparison && isset($source['ignore']) && 'case' === $source['ignore'] && strtolower($source['pattern']) === strtolower($compareUri)) {
                return true;
            }

            $pattern = self::getCleanPattern($source['pattern'], $comparison);
            if (self::stringComparison($pattern, $compareUri, $comparison)) {
                return true;
            }
        }

        return false;
    }

    /**
     * String comparison matching Rank Math Str::comparison.
     */
    public static function stringComparison(string $needle, string $haystack, string $comparison = ''): bool
    {
        if ($comparison === 'regex') {
            return (bool) @preg_match($needle, $haystack);
        }

        if ($comparison === 'start') {
            return '' === $needle || substr($haystack, 0, strlen($needle)) === $needle;
        }

        if ($comparison === 'end') {
            return '' === $needle || substr($haystack, -strlen($needle)) === $needle;
        }

        if ($comparison === 'contains') {
            return '' !== $needle && strpos($haystack, $needle) !== false;
        }

        return $needle === $haystack;
    }

    /**
     * Unserialize and decode sources.
     */
    public static function getSources(mixed $sources): array
    {
        $sources = maybe_unserialize($sources);
        if (!is_array($sources) || empty($sources)) {
            return is_array($sources) ? $sources : [];
        }

        foreach ($sources as $key => $source) {
            if (isset($source['pattern'])) {
                $sources[$key]['pattern'] = wp_specialchars_decode($source['pattern']);
            }
        }

        return $sources;
    }

    /**
     * Compare a list of redirection rows against URI.
     */
    private static function compareRedirections(array $redirections, string $uri): array|false
    {
        foreach ($redirections as $redirection) {
            $sources = self::getSources($redirection['sources'] ?? []);
            if (!empty($sources) && self::compareSources($sources, $uri)) {
                $redirection['sources'] = $sources;
                return $redirection;
            }
        }

        return false;
    }

    /**
     * Match redirections for a URI.
     */
    public static function matchRedirections(string $uri, bool $all = false): array|false
    {
        if (empty($uri)) {
            return false;
        }

        global $wpdb;
        $table = self::getTable();

        if ($all) {
            $rows = $wpdb->get_results(
                "SELECT * FROM {$table} WHERE status = 'active' ORDER BY updated DESC",
                ARRAY_A
            );
            return self::compareRedirections($rows ?: [], $uri);
        }

        // Generate words
        $words = str_replace('/', '-', $uri);
        $words = str_replace('.', '-', $words);
        $wordList = array_filter(explode('-', $words));

        $whereParts = [];
        $params = [];

        // Exact serialized match attempt
        $sourceExact = serialize([
            'pattern'    => $uri,
            'comparison' => 'exact',
        ]);
        $whereParts[] = "sources LIKE %s";
        $params[] = '%' . $wpdb->esc_like($sourceExact) . '%';

        foreach ($wordList as $word) {
            if ($word !== '') {
                $whereParts[] = "sources LIKE %s";
                $params[] = '%' . $wpdb->esc_like($word) . '%';
            }
        }

        $whereSql = implode(' OR ', $whereParts);
        $sql = "SELECT * FROM {$table} WHERE status = 'active' AND ({$whereSql}) ORDER BY updated DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $matched = self::compareRedirections($rows ?: [], $uri);
        if ($matched === false) {
            return self::matchRedirections($uri, true);
        }

        return $matched;
    }

    /**
     * Get redirection by ID.
     */
    public static function getRedirectionById(int $id, string $status = 'all'): array|false
    {
        global $wpdb;
        $table = self::getTable();

        if ($status !== 'all') {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND status = %s LIMIT 1",
                $id,
                $status
            ), ARRAY_A);
        } else {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $id
            ), ARRAY_A);
        }

        if (!$row) {
            return false;
        }

        $row['sources'] = self::getSources($row['sources']);
        return $row;
    }

    /**
     * Update access timestamp and hits.
     */
    public static function updateAccess(array $redirection): void
    {
        if (empty($redirection['id'])) {
            return;
        }

        global $wpdb;
        $table = self::getTable();
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET hits = hits + 1, last_accessed = %s WHERE id = %d",
            current_time('mysql'),
            (int) $redirection['id']
        ));
    }

    /**
     * Add new redirection record.
     */
    public static function add(array $args): int|false
    {
        if (empty($args)) {
            return false;
        }

        unset($args['id']);

        $args = wp_parse_args($args, [
            'sources'       => '',
            'url_to'        => '',
            'header_code'   => 301,
            'hits'          => 0,
            'status'        => 'active',
            'created'       => current_time('mysql'),
            'updated'       => current_time('mysql'),
            'last_accessed' => '0000-00-00 00:00:00',
        ]);

        if (in_array((int) $args['header_code'], [410, 451], true)) {
            $args['url_to'] = '';
        }

        $args['sources'] = maybe_serialize($args['sources']);

        global $wpdb;
        $table = self::getTable();

        $inserted = $wpdb->insert(
            $table,
            [
                'sources'       => $args['sources'],
                'url_to'        => $args['url_to'],
                'header_code'   => (int) $args['header_code'],
                'hits'          => (int) $args['hits'],
                'status'        => $args['status'],
                'created'       => $args['created'],
                'updated'       => $args['updated'],
                'last_accessed' => $args['last_accessed'],
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $inserted !== false ? (int) $wpdb->insert_id : false;
    }

    /**
     * Update existing record.
     */
    public static function update(array $args): int|false
    {
        if (empty($args['id'])) {
            return false;
        }

        $id = absint($args['id']);
        unset($args['id']);

        $args = wp_parse_args($args, [
            'sources'     => '',
            'url_to'      => '',
            'header_code' => 301,
            'status'      => 'active',
            'updated'     => current_time('mysql'),
        ]);

        if (in_array((int) $args['header_code'], [410, 451], true)) {
            $args['url_to'] = '';
        }

        $args['sources'] = maybe_serialize($args['sources']);

        global $wpdb;
        $table = self::getTable();

        Cache::purge($id);

        $updated = $wpdb->update(
            $table,
            [
                'sources'     => $args['sources'],
                'url_to'      => $args['url_to'],
                'header_code' => (int) $args['header_code'],
                'status'      => $args['status'],
                'updated'     => $args['updated'],
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%s', '%s'],
            ['%d']
        );

        return $updated !== false ? $id : false;
    }

    /**
     * Add or update redirection.
     */
    public static function updateIff(array $redirection): int
    {
        if (!empty($redirection['id'])) {
            self::update($redirection);
            return (int) $redirection['id'];
        }

        $id = self::add($redirection);
        return $id ? (int) $id : 0;
    }

    /**
     * Delete redirections permanently.
     *
     * @param int|int[] $ids
     */
    public static function delete(int|array $ids): int
    {
        global $wpdb;
        $table = self::getTable();
        $ids = array_map('absint', (array) $ids);
        if (empty($ids)) {
            return 0;
        }

        Cache::purge($ids);

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE id IN ({$placeholders})",
            ...$ids
        ));

        return $deleted !== false ? (int) $deleted : 0;
    }

    /**
     * Change status of redirections.
     *
     * @param int|int[] $ids
     */
    public static function changeStatus(int|array $ids, string $status): int
    {
        global $wpdb;
        $table = self::getTable();
        $ids = array_map('absint', (array) $ids);
        if (empty($ids)) {
            return 0;
        }

        Cache::purge($ids);

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge([$status, current_time('mysql')], $ids);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, updated = %s WHERE id IN ({$placeholders})",
            ...$params
        ));

        return $updated !== false ? (int) $updated : 0;
    }

    /**
     * Get counts by status.
     */
    public static function getCounts(): array
    {
        global $wpdb;
        $table = self::getTable();

        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
            ARRAY_A
        );

        $counts = ['active' => 0, 'inactive' => 0, 'trashed' => 0, 'all' => 0];
        foreach ($rows as $row) {
            $st = $row['status'];
            if (isset($counts[$st])) {
                $counts[$st] = (int) $row['count'];
            }
        }

        $counts['all'] = $counts['active'] + $counts['inactive'];
        return $counts;
    }

    /**
     * Get list of redirections with pagination and filters.
     */
    public static function getRedirections(array $args = []): array
    {
        global $wpdb;
        $table = self::getTable();

        $args = wp_parse_args($args, [
            'orderby'  => 'id',
            'order'    => 'DESC',
            'limit'    => 10,
            'paged'    => 1,
            'search'   => '',
            'status'   => 'any',
            'category' => 0,
        ]);

        $joins = [];
        $where = [];
        $params = [];

        if (in_array($args['status'], ['active', 'inactive', 'trashed'], true)) {
            $where[] = "{$table}.status = %s";
            $params[] = $args['status'];
        } else {
            $where[] = "{$table}.status != 'trashed'";
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "({$table}.sources LIKE %s OR {$table}.url_to LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($args['category'])) {
            $catIds = array_map('absint', (array) $args['category']);
            if (!empty($catIds)) {
                $joins[] = "LEFT JOIN {$wpdb->term_relationships} tr ON ({$table}.id = tr.object_id)";
                $joins[] = "LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)";
                $placeholders = implode(',', array_fill(0, count($catIds), '%d'));
                $where[] = "tt.term_id IN ({$placeholders})";
                $params = array_merge($params, $catIds);
            }
        }

        $joinSql = !empty($joins) ? implode(' ', $joins) : '';
        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowedOrderby = ['id', 'url_to', 'header_code', 'hits', 'created', 'updated', 'last_accessed'];
        $orderby = in_array($args['orderby'], $allowedOrderby, true) ? $args['orderby'] : 'id';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit = max(1, (int) $args['limit']);
        $offset = max(0, ((int) $args['paged'] - 1) * $limit);

        // Count total
        $countSql = "SELECT COUNT(DISTINCT {$table}.id) FROM {$table} {$joinSql} {$whereSql}";
        $total = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($countSql, ...$params)) : (int) $wpdb->get_var($countSql);

        // Query rows
        $querySql = "SELECT {$table}.* FROM {$table} {$joinSql} {$whereSql} GROUP BY {$table}.id ORDER BY {$table}.{$orderby} {$order} LIMIT %d OFFSET %d";
        $queryParams = array_merge($params, [$limit, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($querySql, ...$queryParams), ARRAY_A);

        if ($rows) {
            foreach ($rows as $k => $row) {
                $rows[$k]['sources'] = self::getSources($row['sources']);
            }
        } else {
            $rows = [];
        }

        return [
            'redirections' => $rows,
            'count'        => $total,
        ];
    }

    /**
     * Get stats for dashboard widget.
     */
    public static function getStats(): object
    {
        global $wpdb;
        $table = self::getTable();

        // Check if table exists
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$tableExists) {
            return (object) ['total' => 0, 'hits' => 0];
        }

        $row = $wpdb->get_row("SELECT COUNT(*) as total, COALESCE(SUM(hits), 0) as hits FROM {$table} WHERE status = 'active'");
        return $row ?: (object) ['total' => 0, 'hits' => 0];
    }
}
