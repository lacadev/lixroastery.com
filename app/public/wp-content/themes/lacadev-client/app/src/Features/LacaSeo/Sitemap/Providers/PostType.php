<?php

namespace App\Features\LacaSeo\Sitemap\Providers;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\ImageParser;
use App\Features\LacaSeo\Sitemap\Router;
use App\Features\LacaSeo\Sitemap\Sitemap;

defined('ABSPATH') || exit;

/**
 * Post type sitemap provider.
 */
class PostType implements Provider
{
    protected ?ImageParser $imageParser = null;
    protected ?int $pageOnFrontId = null;
    protected ?int $pageForPostsId = null;

    public function __construct()
    {
        $this->pageOnFrontId = (int) get_option('page_on_front');
        $this->pageForPostsId = (int) get_option('page_for_posts');
    }

    public function handlesType(string $type): bool
    {
        if (!post_type_exists($type)) {
            return false;
        }

        // Chỉ đọc cấu hình Laca để sitemap trước và sau cutover giống nhau.
        $enabled = Settings::get("sitemap.pt_{$type}_sitemap", ($type === 'attachment' ? 'off' : 'on'));
        if ($enabled !== 'on') {
            return false;
        }

        if ($type === 'attachment' && Settings::get('general.attachment_redirect_urls', 'on') === 'on') {
            return false;
        }

        return !apply_filters('laca_seo/sitemap/exclude_post_type', false, $type);
    }

    public function getIndexLinks(int $maxEntries): array
    {
        $post_types = Helper::getAccessiblePostTypes();
        $post_types = array_keys(array_filter($post_types, fn($obj, $pt) => $this->handlesType($pt), ARRAY_FILTER_USE_BOTH));
        $last_modified_times = Sitemap::getLastModifiedGmt($post_types, true);
        $index = [];

        foreach ($post_types as $post_type) {
            $total_count = $this->getPostTypeCount($post_type);
            if ($total_count === 0) {
                continue;
            }

            $max_pages = 1;
            if ($total_count > $maxEntries) {
                $max_pages = (int) ceil($total_count / $maxEntries);
            }

            for ($page = 1; $page <= $max_pages; $page++) {
                $n = ($max_pages > 1) ? (string) $page : '';
                $item = [
                    'loc'     => Router::getBaseUrl("{$post_type}-sitemap{$n}.xml"),
                    'lastmod' => $last_modified_times[$post_type] ?? '',
                ];

                $item = apply_filters('laca_seo/sitemap/index/entry', $item, 'post', $post_type);
                if ($item) {
                    $index[] = $item;
                }
            }
        }

        return $index;
    }

    public function getSitemapLinks(string $type, int $maxEntries, int $currentPage): array
    {
        global $wpdb;

        $links = [];
        if ($currentPage === 1) {
            $links = array_merge($links, $this->getFirstLinks($type));
        }

        $offset = ($currentPage > 1) ? (($currentPage - 1) * $maxEntries) : 0;

        $posts = $this->getPosts($type, $maxEntries, $offset);
        if (empty($posts)) {
            return $links;
        }

        $parser = $this->getImageParser();

        foreach ($posts as $post) {
            if (!Sitemap::isObjectIndexable($post->ID, 'post')) {
                continue;
            }

            // Exclude hidden products in WooCommerce
            if ($type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);
                if ($product && !$product->is_visible()) {
                    continue;
                }
            }

            $permalink = get_permalink($post);
            if (!$permalink) {
                continue;
            }

            // Apply Rank Math and Laca SEO xml_post_url filter (e.g. Polylang home URL rewriting)
            $permalink = apply_filters('rank_math/sitemap/xml_post_url', $permalink, $post);
            $permalink = apply_filters('laca_seo/sitemap/xml_post_url', $permalink, $post);

            // Check canonical URL meta
            $canonical = get_post_meta($post->ID, '_laca_seo_canonical_url', true);
            if (!empty($canonical) && $canonical !== $permalink) {
                continue;
            }

            $url = [
                'loc'    => $permalink,
                'mod'    => $post->post_modified_gmt,
                'images' => $parser->getImages($post) ?: [],
            ];

            $url = apply_filters('laca_seo/sitemap/entry', $url, 'post', $post);
            if (!empty($url)) {
                if ($post->ID === $this->pageOnFrontId || $post->ID === $this->pageForPostsId) {
                    array_unshift($links, $url);
                } else {
                    $links[] = $url;
                }
            }
        }

        return $links;
    }

    /**
     * Produces set of links to prepend at start of first sitemap page (e.g. archive link).
     */
    protected function getFirstLinks(string $post_type): array
    {
        $links = [];
        $needs_archive = true;

        if (!$this->pageOnFrontId && ($post_type === 'post' || $post_type === 'page')) {
            $needs_archive = false;
            $links[] = [
                'loc' => home_url('/'),
                'mod' => Sitemap::getLastModifiedGmt($post_type),
            ];
        } elseif ($this->pageOnFrontId && $post_type === 'post' && $this->pageForPostsId) {
            $needs_archive = false;
        }

        if (!$needs_archive) {
            return array_filter($links);
        }

        $archive_url = $this->getPostTypeArchiveLink($post_type);
        $archive_url = apply_filters('rank_math/sitemap/post_type_archive_link', $archive_url, $post_type);
        $archive_url = apply_filters('laca_seo/sitemap/post_type_archive_link', $archive_url, $post_type);

        if ($archive_url) {
            $links[] = [
                'loc' => $archive_url,
                'mod' => Sitemap::getLastModifiedGmt($post_type),
            ];
        }

        return $links;
    }

    protected function getPostTypeArchiveLink(string $post_type): string|false
    {
        if ($post_type === 'post' && get_option('show_on_front') !== 'posts' && !$this->pageForPostsId) {
            return false;
        }

        return get_post_type_archive_link($post_type) ?: false;
    }

    /**
     * @return \WP_Post[]
     */
    private function getPosts(string $post_type, int $limit, int $offset): array
    {
        global $wpdb;

        $statuses = ['publish'];
        if ($post_type === 'attachment') {
            $statuses = ['inherit'];
        }

        $exclude_ids = array_filter(wp_parse_id_list(Settings::get('sitemap.exclude_posts', '')));
        $exclude_clause = '';
        if (!empty($exclude_ids)) {
            $exclude_clause = " AND p.ID NOT IN (" . implode(',', $exclude_ids) . ") ";
        }

        $status_placeholders = implode("','", array_map('esc_sql', $statuses));

        $sql = "
            SELECT p.*
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_laca ON (p.ID = pm_laca.post_id AND pm_laca.meta_key = '_laca_seo_robots')
            WHERE p.post_type = %s
              AND p.post_status IN ('{$status_placeholders}')
              AND (pm_laca.meta_value IS NULL OR pm_laca.meta_value NOT LIKE '%%noindex%%')
              {$exclude_clause}
            ORDER BY p.post_modified_gmt DESC
            LIMIT %d OFFSET %d
        ";

        return (array) $wpdb->get_results($wpdb->prepare($sql, $post_type, $limit, $offset));
    }

    public function getPostTypeCount(string $post_type): int
    {
        global $wpdb;

        $statuses = ['publish'];
        if ($post_type === 'attachment') {
            $statuses = ['inherit'];
        }

        $exclude_ids = array_filter(wp_parse_id_list(Settings::get('sitemap.exclude_posts', '')));
        $exclude_clause = '';
        if (!empty($exclude_ids)) {
            $exclude_clause = " AND p.ID NOT IN (" . implode(',', $exclude_ids) . ") ";
        }

        $status_placeholders = implode("','", array_map('esc_sql', $statuses));

        $sql = "
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_laca ON (p.ID = pm_laca.post_id AND pm_laca.meta_key = '_laca_seo_robots')
            WHERE p.post_type = %s
              AND p.post_status IN ('{$status_placeholders}')
              AND (pm_laca.meta_value IS NULL OR pm_laca.meta_value NOT LIKE '%%noindex%%')
              {$exclude_clause}
        ";

        return (int) $wpdb->get_var($wpdb->prepare($sql, $post_type));
    }

    private function getImageParser(): ImageParser
    {
        if ($this->imageParser === null) {
            $this->imageParser = new ImageParser();
        }
        return $this->imageParser;
    }
}
