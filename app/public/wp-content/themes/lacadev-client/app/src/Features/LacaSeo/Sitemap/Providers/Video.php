<?php

namespace App\Features\LacaSeo\Sitemap\Providers;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\Db;
use App\Features\LacaSeo\Sitemap\Router;
use App\Features\LacaSeo\Sitemap\Sitemap;

defined('ABSPATH') || exit;

class Video implements Provider
{
    public function handlesType(string $type): bool
    {
        return $type === 'video';
    }

    public function getIndexLinks(int $maxEntries): array
    {
        if (Settings::get('sitemap.video_sitemap', 'off') !== 'on') {
            return [];
        }

        $postTypes = (array) Settings::get('sitemap.video_sitemap_post_type', ['post', 'page']);
        $posts = $this->getVideoPosts($postTypes, 1, 0);
        if (empty($posts)) {
            return [];
        }

        return [[
            'loc'     => Router::getBaseUrl('video-sitemap.xml'),
            'lastmod' => $posts[0]->post_modified_gmt ?? get_lastpostdate('gmt'),
        ]];
    }

    public function getSitemapLinks(string $type, int $maxEntries, int $currentPage): array
    {
        if ($type !== 'video') {
            return [];
        }

        $postTypes = (array) Settings::get('sitemap.video_sitemap_post_type', ['post', 'page']);
        $posts = $this->getVideoPosts($postTypes, $maxEntries, ($currentPage - 1) * $maxEntries);
        $links = [];

        foreach ($posts as $post) {
            if (!Sitemap::isObjectIndexable($post->ID, 'post')) {
                continue;
            }

            $schemas = Db::getSchemas($post->ID);
            $videoSchemas = [];

            foreach ($schemas as $s) {
                if (($s['@type'] ?? '') === 'VideoObject') {
                    $videoSchemas[] = $s;
                }
            }

            if (empty($videoSchemas)) {
                // Also check rank_math_schema_VideoObject
                $rmVideos = get_post_meta($post->ID, 'rank_math_schema_VideoObject');
                if (!empty($rmVideos)) {
                    $videoSchemas = $rmVideos;
                }
            }

            if (empty($videoSchemas)) {
                continue;
            }

            $videos = [];
            foreach ($videoSchemas as $v) {
                $videos[] = [
                    'title'            => $v['name'] ?? $post->post_title,
                    'thumbnail_loc'    => $v['thumbnailUrl'] ?? '',
                    'description'      => $v['description'] ?? ($post->post_excerpt ?: $post->post_title),
                    'publication_date' => $v['uploadDate'] ?? $post->post_date_gmt,
                    'content_loc'      => $v['contentUrl'] ?? '',
                    'player_loc'       => $v['embedUrl'] ?? '',
                    'duration'         => $v['duration'] ?? '',
                    'tags'             => $v['metadata']['tags'] ?? '',
                    'family_friendly'  => !empty($v['isFamilyFriendly']) ? 'yes' : 'no',
                ];
            }

            $links[] = [
                'loc'    => get_permalink($post),
                'author' => $post->post_author,
                'videos' => $videos,
            ];
        }

        return $links;
    }

    public function getVideoPosts(array $postTypes, int $count, int $offset): array
    {
        global $wpdb;

        if (empty($postTypes)) {
            return [];
        }

        $escapedTypes = implode("', '", array_map('esc_sql', $postTypes));

        $sql = "
            SELECT p.*
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id)
            WHERE (
                pm.meta_key = 'rank_math_schema_VideoObject'
                OR (pm.meta_key LIKE '_laca_seo_schema_%' AND pm.meta_value LIKE '%%VideoObject%%')
            )
            AND p.post_type IN ('{$escapedTypes}')
            AND p.post_status IN ('publish', 'inherit')
            AND p.post_password = ''
            GROUP BY p.ID
            ORDER BY p.post_modified_gmt DESC
            LIMIT %d OFFSET %d
        ";

        return (array) $wpdb->get_results($wpdb->prepare($sql, $count, $offset));
    }
}
