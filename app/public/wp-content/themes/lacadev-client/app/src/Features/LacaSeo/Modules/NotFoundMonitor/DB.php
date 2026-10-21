<?php

namespace App\Features\LacaSeo\Modules\NotFoundMonitor;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Database operations for 404 Monitor.
 */
class DB
{
    public static function getTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_seo_404_logs';
    }

    public static function getLogs(array $args = []): array
    {
        global $wpdb;
        $table = self::getTable();

        $args = wp_parse_args($args, [
            'orderby' => 'id',
            'order'   => 'DESC',
            'limit'   => 10,
            'paged'   => 1,
            'search'  => '',
            'ids'     => [],
            'uri'     => '',
        ]);

        $where = [];
        $params = [];

        if (!empty($args['search'])) {
            $where[] = "uri LIKE %s";
            $params[] = '%' . $wpdb->esc_like(rawurlencode($args['search'])) . '%';
        }

        if (!empty($args['ids'])) {
            $ids = array_map('absint', (array) $args['ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $where[] = "id IN ({$placeholders})";
            $params = array_merge($params, $ids);
        }

        if (!empty($args['uri'])) {
            $where[] = "uri = %s";
            $params[] = $args['uri'];
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowedOrderby = ['id', 'uri', 'accessed', 'times_accessed'];
        $orderby = in_array($args['orderby'], $allowedOrderby, true) ? $args['orderby'] : 'id';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit = max(1, (int) $args['limit']);
        $offset = max(0, ((int) $args['paged'] - 1) * $limit);

        $countSql = "SELECT COUNT(*) FROM {$table} {$whereSql}";
        $total = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($countSql, ...$params)) : (int) $wpdb->get_var($countSql);

        $querySql = "SELECT * FROM {$table} {$whereSql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $queryParams = array_merge($params, [$limit, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($querySql, ...$queryParams), ARRAY_A);

        return [
            'logs'  => $rows ?: [],
            'count' => $total,
        ];
    }

    public static function add(array $args): int|false
    {
        global $wpdb;
        $table = self::getTable();

        $args = wp_parse_args($args, [
            'uri'            => '',
            'accessed'       => current_time('mysql'),
            'times_accessed' => 1,
            'referer'        => '',
            'user_agent'     => '',
        ]);

        // Maybe trim logs if limit reached
        $limit = absint(Settings::get('general.404_monitor_limit', 100));
        if ($limit > 0 && self::getCount() >= $limit) {
            self::trimLogs($limit - 1);
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'uri'            => $args['uri'],
                'accessed'       => $args['accessed'],
                'times_accessed' => (int) $args['times_accessed'],
                'referer'        => $args['referer'],
                'user_agent'     => $args['user_agent'],
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        return $inserted !== false ? (int) $wpdb->insert_id : false;
    }

    public static function update(array $args): int|false
    {
        global $wpdb;
        $table = self::getTable();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE uri = %s LIMIT 1",
            $args['uri']
        ), ARRAY_A);

        if ($row) {
            $updated = $wpdb->update(
                $table,
                [
                    'accessed'       => current_time('mysql'),
                    'times_accessed' => absint($row['times_accessed']) + 1,
                ],
                ['id' => (int) $row['id']],
                ['%s', '%d'],
                ['%d']
            );
            return $updated !== false ? (int) $row['id'] : false;
        }

        return self::add($args);
    }

    public static function deleteLog(int|array $ids): int
    {
        global $wpdb;
        $table = self::getTable();
        $ids = array_map('absint', (array) $ids);
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE id IN ({$placeholders})",
            ...$ids
        ));

        return $deleted !== false ? (int) $deleted : 0;
    }

    public static function getCount(): int
    {
        global $wpdb;
        $table = self::getTable();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public static function clearLogs(): bool
    {
        global $wpdb;
        $table = self::getTable();
        return $wpdb->query("DELETE FROM {$table}") !== false;
    }

    public static function trimLogs(int $keepCount): void
    {
        global $wpdb;
        $table = self::getTable();
        $keepCount = max(0, $keepCount);

        if ($keepCount === 0) {
            $wpdb->query("DELETE FROM {$table}");
            return;
        }

        // Delete all rows older than the top $keepCount newest rows
        $cutoffId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d",
            $keepCount
        ));

        if ($cutoffId) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id <= %d", $cutoffId));
        }
    }

    public static function getStats(): object
    {
        global $wpdb;
        $table = self::getTable();

        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$tableExists) {
            return (object) ['total' => 0, 'hits' => 0];
        }

        $row = $wpdb->get_row("SELECT COUNT(*) as total, COALESCE(SUM(times_accessed), 0) as hits FROM {$table}");
        return $row ?: (object) ['total' => 0, 'hits' => 0];
    }
}
