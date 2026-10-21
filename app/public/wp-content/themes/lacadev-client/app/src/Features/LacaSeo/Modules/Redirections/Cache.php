<?php

namespace App\Features\LacaSeo\Modules\Redirections;

defined('ABSPATH') || exit;

/**
 * Cache manager for redirections (1:1 with Rank Math Cache).
 */
class Cache
{
    /**
     * Get table name.
     */
    public static function getTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_seo_redirections_cache';
    }

    /**
     * Get redirection by object ID.
     */
    public static function getByObjectId(int $objectId, string $objectType): ?object
    {
        global $wpdb;
        $table = self::getTable();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE object_id = %d AND object_type = %s LIMIT 1",
            $objectId,
            $objectType
        ));
        return $row ?: null;
    }

    /**
     * Get redirection by URL.
     */
    public static function getByUrl(string $url): ?object
    {
        if ($url === '') {
            return null;
        }
        global $wpdb;
        $table = self::getTable();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE BINARY from_url = %s LIMIT 1",
            $url
        ));
        return $row ?: null;
    }

    /**
     * Get redirections by object ID or URL.
     */
    public static function getByObjectIdOrUrl(int $objectId, string $objectType, string $url): array
    {
        global $wpdb;
        $table = self::getTable();

        if ($url !== '') {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE (object_id = %d AND object_type = %s) OR BINARY from_url = %s ORDER BY object_id DESC",
                $objectId,
                $objectType,
                $url
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_id = %d AND object_type = %s ORDER BY object_id DESC",
                $objectId,
                $objectType
            );
        }

        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Add a cache record.
     */
    public static function add(array $args): bool
    {
        if (empty($args)) {
            return false;
        }

        global $wpdb;
        $table = self::getTable();

        $args = wp_parse_args($args, [
            'from_url'       => '',
            'redirection_id' => 0,
            'object_id'      => 0,
            'object_type'    => 'post',
            'is_redirected'  => 1,
        ]);

        // Check if exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE from_url = %s AND redirection_id = %d AND object_id = %d AND object_type = %s AND is_redirected = %d LIMIT 1",
            $args['from_url'],
            (int) $args['redirection_id'],
            (int) $args['object_id'],
            $args['object_type'],
            (int) $args['is_redirected']
        ));

        if ($exists) {
            return false;
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'from_url'       => $args['from_url'],
                'redirection_id' => (int) $args['redirection_id'],
                'object_id'      => (int) $args['object_id'],
                'object_type'    => $args['object_type'],
                'is_redirected'  => (int) $args['is_redirected'],
            ],
            ['%s', '%d', '%d', '%s', '%d']
        );

        return $inserted !== false;
    }

    /**
     * Purge cache by redirection ID(s).
     *
     * @param int|int[] $ids
     */
    public static function purge(int|array $ids): int
    {
        global $wpdb;
        $table = self::getTable();
        $ids = array_map('absint', (array) $ids);
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE redirection_id IN ({$placeholders})",
            ...$ids
        ));

        return $deleted !== false ? (int) $deleted : 0;
    }

    /**
     * Purge cache by object ID(s).
     *
     * @param int|int[] $ids
     */
    public static function purgeByObjectId(int|array $ids, string $objectType): int
    {
        global $wpdb;
        $table = self::getTable();
        $ids = array_map('absint', (array) $ids);
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge($ids, [$objectType]);
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE object_id IN ({$placeholders}) AND object_type = %s",
            ...$params
        ));

        return $deleted !== false ? (int) $deleted : 0;
    }
}
