<?php

namespace App\Features\LacaSeo\Schema;

defined('ABSPATH') || exit;

class Db
{
    /**
     * Get all schemas saved for a given post/term/user object ID.
     * Reads `_laca_seo_schema_*`.
     *
     * @param int $objectId
     * @param string $table 'postmeta', 'termmeta', 'usermeta'
     * @return array<string, array>
     */
    public static function getSchemas(int $objectId, string $table = 'postmeta'): array
    {
        $pre = apply_filters('laca_seo/schema/get_schemas', null, $objectId, $table);
        if (is_array($pre)) {
            return $pre;
        }

        static $cache = [];
        $cacheKey = "{$table}_{$objectId}";
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        global $wpdb;
        $tableName = $wpdb->{$table} ?? $wpdb->postmeta;
        $idCol = 'post_id';
        if ($table === 'termmeta') {
            $idCol = 'term_id';
        } elseif ($table === 'usermeta') {
            $idCol = 'user_id';
        }

        // Check Laca SEO schema meta
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id, meta_key, meta_value FROM {$tableName} WHERE {$idCol} = %d AND meta_key LIKE %s",
            $objectId,
            '_laca_seo_schema_%'
        ));

        $schemas = [];
        foreach ($rows as $row) {
            $val = maybe_unserialize($row->meta_value);
            if (!empty($val) && is_array($val)) {
                $schemas['schema-' . $row->meta_id] = $val;
            }
        }

        $cache[$cacheKey] = $schemas;
        return $schemas;
    }

    /**
     * Get default schema types for display/metabox.
     */
    public static function getDefaultSchemaType(int $postId): string|false
    {
        $pt = get_post_type($postId);
        if (!$pt) {
            return false;
        }

        $type = \App\Features\LacaSeo\Core\Settings::get("titles.pt_{$pt}_default_rich_snippet", false);
        if (!$type || $type === 'off') {
            return false;
        }

        if ($type === 'article') {
            $artType = \App\Features\LacaSeo\Core\Settings::get("titles.pt_{$pt}_default_article_type", 'Article');
            return $artType ?: 'Article';
        }

        if ($type === 'product' && $pt === 'product' && class_exists('WooCommerce')) {
            return 'WooCommerceProduct';
        }

        return ucfirst($type);
    }
}
