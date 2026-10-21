<?php

namespace App\Features\LacaSeo\Sitemap;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Main coordinator for Laca SEO XML Sitemaps.
 */
class Sitemap
{
    public function register(): void
    {
        (new Router())->register();
        (new CacheWatcher())->register();
        (new Html\Sitemap())->register();

        if (!Helper::isRankMathActive() || Helper::isForceFrontend()) {
            add_filter('wp_sitemaps_enabled', '__return_false');
            add_filter('robots_txt', [$this, 'addSitemapDirective'], 0, 2);
        }
    }

    /**
     * Adds the sitemap index to robots.txt matching Rank Math 1:1.
     */
    public function addSitemapDirective(string $output, mixed $is_public): string
    {
        if (
            '0' === (string) $is_public ||
            str_contains($output, 'Sitemap:') ||
            str_contains($output, 'sitemap:')
        ) {
            return $output;
        }

        $sitemap_url = esc_url(Router::getBaseUrl('sitemap_index.xml'));
        return $output . "\nSitemap: {$sitemap_url}\n";
    }

    /**
     * Get GMT modification date for post types.
     *
     * @param string|string[] $post_types
     * @param bool $return_all
     * @return string|array<string, string>
     */
    public static function getLastModifiedGmt(string|array $post_types, bool $return_all = false): string|array
    {
        global $wpdb;

        if (empty($post_types)) {
            return $return_all ? [] : '';
        }

        if (!is_array($post_types)) {
            $post_types = [$post_types];
        }

        $all_public = get_post_types(['public' => true]);
        if (empty($all_public)) {
            return $return_all ? [] : '';
        }

        $placeholders = implode("','", array_map('esc_sql', $all_public));
        $sql = "
            SELECT post_type, MAX(GREATEST(p.post_modified_gmt, p.post_date_gmt)) AS date
            FROM {$wpdb->posts} as p
            LEFT JOIN {$wpdb->postmeta} AS pm_laca ON (p.ID = pm_laca.post_id AND pm_laca.meta_key = '_laca_seo_robots')
            WHERE (pm_laca.meta_value IS NULL OR pm_laca.meta_value NOT LIKE '%%noindex%%')
            AND p.post_status IN ('publish', 'inherit')
            AND p.post_type IN ('{$placeholders}')
            GROUP BY p.post_type
            ORDER BY p.post_modified_gmt DESC
        ";

        $results = (array) $wpdb->get_results($sql);
        $dates = [];
        foreach ($results as $row) {
            $dates[$row->post_type] = $row->date;
        }

        $filtered = array_intersect_key($dates, array_flip($post_types));
        if (!empty($filtered)) {
            return $return_all ? $filtered : max($filtered);
        }

        return $return_all ? [] : '';
    }

    /**
     * Check if post or term is indexable.
     */
    public static function isObjectIndexable(int $objectId, string $type = 'post'): bool
    {
        if (apply_filters('laca_seo/sitemap/include_noindex', false, $type)) {
            return true;
        }

        if ($type === 'post') {
            $robots = get_post_meta($objectId, '_laca_seo_robots', true);
            if (is_array($robots) && in_array('noindex', $robots, true)) {
                return false;
            }

            // Check post type global setting
            $pt = get_post_type($objectId);
            if ($pt) {
                $pt_robots = Settings::get("titles.pt_{$pt}_custom_robots", 'off');
                if ($pt_robots === 'on') {
                    $pt_robots_tags = (array) Settings::get("titles.pt_{$pt}_robots", ['index']);
                    if (in_array('noindex', $pt_robots_tags, true)) {
                        return false;
                    }
                }
            }

            return true;
        }

        if ($type === 'term') {
            $robots = get_term_meta($objectId, '_laca_seo_robots', true);
            if (is_array($robots) && in_array('noindex', $robots, true)) {
                return false;
            }

            return true;
        }

        return true;
    }
}
