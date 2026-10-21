<?php

namespace App\Features\LacaSeo\Sitemap\Providers;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\ImageParser;
use App\Features\LacaSeo\Sitemap\Router;
use App\Features\LacaSeo\Sitemap\Sitemap;
use WP_Query;
use WP_Term;

defined('ABSPATH') || exit;

/**
 * Taxonomy sitemap provider.
 */
class Taxonomy implements Provider
{
    protected ?ImageParser $imageParser = null;

    public function handlesType(string $type): bool
    {
        if (!taxonomy_exists($type) || !is_taxonomy_viewable($type)) {
            return false;
        }

        if (in_array($type, ['link_category', 'nav_menu', 'post_format'], true)) {
            return false;
        }

        if (Helper::isRankMathActive() && function_exists('rank_math')) {
            $rm_enabled = \RankMath\Helper::get_settings("sitemap.tax_{$type}_sitemap");
            if (!$rm_enabled) {
                return false;
            }
        } else {
            $default = ($type === 'category') ? 'on' : 'off';
            $enabled = Settings::get("sitemap.tax_{$type}_sitemap", $default);
            if ($enabled !== 'on') {
                return false;
            }
        }

        return !apply_filters('laca_seo/sitemap/exclude_taxonomy', false, $type);
    }

    public function getIndexLinks(int $maxEntries): array
    {
        $taxonomies = Helper::getAccessibleTaxonomies();
        $taxonomies = array_filter($taxonomies, fn($obj, $tax) => $this->handlesType($tax), ARRAY_FILTER_USE_BOTH);
        if (empty($taxonomies)) {
            return [];
        }

        $index = [];

        foreach ($taxonomies as $tax_name => $tax_obj) {
            $include_empty = Settings::get("sitemap.tax_{$tax_name}_include_empty", 'off') === 'on';
            $exclude_ids = array_filter(wp_parse_id_list(Settings::get('sitemap.exclude_terms', '')));

            $terms = get_terms([
                'taxonomy'   => $tax_name,
                'hide_empty' => !$include_empty,
                'fields'     => 'ids',
                'exclude'    => $exclude_ids,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $total_count = count($terms);
            $max_pages = 1;
            if ($total_count > $maxEntries) {
                $max_pages = (int) ceil($total_count / $maxEntries);
            }

            $lastmod = Sitemap::getLastModifiedGmt((array) $tax_obj->object_type);

            for ($page = 1; $page <= $max_pages; $page++) {
                $n = ($max_pages > 1) ? (string) $page : '';
                $item = [
                    'loc'     => Router::getBaseUrl("{$tax_name}-sitemap{$n}.xml"),
                    'lastmod' => $lastmod,
                ];

                $item = apply_filters('laca_seo/sitemap/index/entry', $item, 'term', $tax_name);
                if ($item) {
                    $index[] = $item;
                }
            }
        }

        return $index;
    }

    public function getSitemapLinks(string $type, int $maxEntries, int $currentPage): array
    {
        $taxonomy = get_taxonomy($type);
        if (!$taxonomy) {
            return [];
        }

        $offset = ($currentPage > 1) ? (($currentPage - 1) * $maxEntries) : 0;
        $include_empty = Settings::get("sitemap.tax_{$type}_include_empty", 'off') === 'on';
        $exclude_ids = array_filter(wp_parse_id_list(Settings::get('sitemap.exclude_terms', '')));

        $terms = get_terms([
            'taxonomy'     => $type,
            'hide_empty'   => !$include_empty,
            'offset'       => $offset,
            'number'       => $maxEntries,
            'exclude'      => $exclude_ids,
            'hierarchical' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $parser = $this->getImageParser();
        $links = [];

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            if (!Sitemap::isObjectIndexable($term->term_id, 'term')) {
                continue;
            }

            $link = get_term_link($term, $type);
            if (is_wp_error($link) || empty($link)) {
                continue;
            }

            // Check canonical
            $canonical = get_term_meta($term->term_id, '_laca_seo_canonical_url', true);
            if (!empty($canonical) && $canonical !== $link) {
                continue;
            }

            $url = [
                'loc'    => $link,
                'mod'    => $this->getLastmod($term),
                'images' => $parser->getTermImages($term) ?: [],
            ];

            $url = apply_filters('laca_seo/sitemap/entry', $url, 'term', $term);
            if (!empty($url)) {
                $links[] = $url;
            }
        }

        return $links;
    }

    public function getLastmod(WP_Term $term): string
    {
        global $wpdb;

        $sql = "
            SELECT MAX(p.post_modified_gmt) AS lastmod
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->term_relationships} AS tr ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = %s
              AND tt.term_id = %d
              AND p.post_status IN ('publish', 'inherit')
              AND p.post_password = ''
        ";

        $res = $wpdb->get_var($wpdb->prepare($sql, $term->taxonomy, $term->term_id));
        return is_string($res) ? $res : '';
    }

    private function getImageParser(): ImageParser
    {
        if ($this->imageParser === null) {
            $this->imageParser = new ImageParser();
        }
        return $this->imageParser;
    }
}
