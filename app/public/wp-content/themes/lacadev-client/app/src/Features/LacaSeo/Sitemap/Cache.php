<?php

namespace App\Features\LacaSeo\Sitemap;

defined('ABSPATH') || exit;

/**
 * Handle XML sitemap caching and invalidation using laca_seo_sitemap_ prefix.
 */
class Cache
{
    public const STORAGE_KEY_PREFIX = 'laca_seo_sitemap_';

    /**
     * Get the sitemap that is cached.
     *
     * @param string $type
     * @param int $page
     * @param bool $html
     * @return string|false
     */
    public function getSitemap(string $type, int $page, bool $html = false): string|false
    {
        $transient_name = $this->getTransientKey($type, $page, $html);
        $sitemap = get_transient($transient_name);
        return is_string($sitemap) ? $sitemap : false;
    }

    /**
     * Store the sitemap in cache.
     *
     * @param string $type
     * @param int $page
     * @param string $sitemap
     * @param bool $html
     * @return bool
     */
    public function storeSitemap(string $type, int $page, string $sitemap, bool $html = false): bool
    {
        $transient_name = $this->getTransientKey($type, $page, $html);
        return set_transient($transient_name, $sitemap, DAY_IN_SECONDS * 100);
    }

    /**
     * Generate transient key with laca_seo_sitemap_ prefix.
     */
    public function getTransientKey(string $type, int $page, bool $html = false): string
    {
        $suffix = $html ? 'html' : 'xml';
        // Transient name length in WP is max 172 chars
        $hash = md5("{$type}_{$page}_" . home_url() . "_{$suffix}");
        return self::STORAGE_KEY_PREFIX . "{$type}_{$page}_{$hash}";
    }

    /**
     * Invalidate sitemap cache.
     *
     * @param string|null $type Null for all caches, or specific type (post, page, product, etc.)
     */
    public static function invalidateStorage(?string $type = null): void
    {
        global $wpdb;

        if ($type === null) {
            $prefix = '_transient_' . self::STORAGE_KEY_PREFIX;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like($prefix) . '%',
                    $wpdb->esc_like('_transient_timeout_' . self::STORAGE_KEY_PREFIX) . '%'
                )
            );
        } else {
            $prefix = '_transient_' . self::STORAGE_KEY_PREFIX . $type . '_';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like($prefix) . '%',
                    $wpdb->esc_like('_transient_timeout_' . self::STORAGE_KEY_PREFIX . $type . '_') . '%'
                )
            );
        }

        do_action('laca_seo/sitemap/invalidated_storage', $type);
    }
}
