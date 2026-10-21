<?php

namespace App\Features\LacaSeo\Sitemap\Providers;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\Router;
use App\Features\LacaSeo\Sitemap\Sitemap;

defined('ABSPATH') || exit;

class News implements Provider
{
    public function handlesType(string $type): bool
    {
        return $type === 'news';
    }

    public function getIndexLinks(int $maxEntries): array
    {
        if (Settings::get('sitemap.news_sitemap', 'off') !== 'on') {
            return [];
        }

        $postTypes = (array) Settings::get('sitemap.news_sitemap_post_type', ['post']);
        $posts = $this->getNewsPosts($postTypes, 1, 0);
        if (empty($posts)) {
            return [];
        }

        return [[
            'loc'     => Router::getBaseUrl('news-sitemap.xml'),
            'lastmod' => get_lastpostdate('gmt'),
        ]];
    }

    public function getSitemapLinks(string $type, int $maxEntries, int $currentPage): array
    {
        if ($type !== 'news') {
            return [];
        }

        $postTypes = (array) Settings::get('sitemap.news_sitemap_post_type', ['post']);
        $posts = $this->getNewsPosts($postTypes, 1000, 0);
        $links = [];

        foreach ($posts as $post) {
            if (!Sitemap::isObjectIndexable($post->ID, 'post')) {
                continue;
            }

            $newsRobots = get_post_meta($post->ID, '_laca_seo_news_sitemap_robots', true)
                ?: get_post_meta($post->ID, 'rank_math_news_sitemap_robots', true);
            if ($newsRobots === 'noindex') {
                continue;
            }

            $title = get_post_meta($post->ID, '_laca_seo_title', true)
                ?: get_post_meta($post->ID, 'rank_math_title', true)
                ?: $post->post_title;

            $publicationName = Settings::get('sitemap.news_sitemap_publication_name', get_bloginfo('name'));
            $publicationLang = Settings::get('sitemap.news_sitemap_publication_language', substr(get_bloginfo('language'), 0, 2));

            $links[] = [
                'loc'              => get_permalink($post),
                'publication_name' => $publicationName,
                'language'         => $publicationLang,
                'publication_date' => $post->post_date_gmt,
                'title'            => $title,
            ];
        }

        return $links;
    }

    public function getNewsPosts(array $postTypes, int $count, int $offset): array
    {
        global $wpdb;

        if (empty($postTypes)) {
            return [];
        }

        $escapedTypes = implode("', '", array_map('esc_sql', $postTypes));

        $termsQuery = '';
        foreach ($postTypes as $postType) {
            $excludeTerms = Settings::get("sitemap.news_sitemap_exclude_{$postType}_terms", []);
            if (!empty($excludeTerms) && is_array($excludeTerms)) {
                $termIds = [];
                foreach ($excludeTerms as $tax => $ids) {
                    if (is_array($ids)) {
                        $termIds = array_merge($termIds, array_map('absint', $ids));
                    }
                }
                if (!empty($termIds)) {
                    $placeholders = implode(',', $termIds);
                    $termsQuery .= " AND p.ID NOT IN (
                        SELECT object_id FROM {$wpdb->term_relationships}
                        WHERE term_taxonomy_id IN ({$placeholders})
                    )";
                }
            }
        }

        $sql = "
            SELECT p.*
            FROM {$wpdb->posts} p
            WHERE p.post_status = 'publish'
            AND (TIMESTAMPDIFF(MINUTE, p.post_date_gmt, UTC_TIMESTAMP()) <= (48 * 60))
            AND p.post_type IN ('{$escapedTypes}')
            AND p.post_password = ''
            {$termsQuery}
            ORDER BY p.post_date_gmt DESC
            LIMIT %d OFFSET %d
        ";

        return (array) $wpdb->get_results($wpdb->prepare($sql, $count, $offset));
    }
}
