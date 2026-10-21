<?php

namespace App\Features\LacaSeo\Sitemap\Providers;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\Router;
use WP_User_Query;

defined('ABSPATH') || exit;

/**
 * Author archive sitemap provider.
 */
class Author implements Provider
{
    public function handlesType(string $type): bool
    {
        if ($type !== 'author') {
            return false;
        }

        $enabled = Settings::get('sitemap.authors_sitemap', 'on');
        if ($enabled !== 'on') {
            return false;
        }

        $disable_author_archives = Settings::get('titles.disable_author_archives', 'off');
        if ($disable_author_archives === 'on') {
            return false;
        }

        return !apply_filters('laca_seo/sitemap/exclude_author', false);
    }

    public function getIndexLinks(int $maxEntries): array
    {
        if (!$this->handlesType('author')) {
            return [];
        }

        $users = $this->getAuthors();
        if (empty($users)) {
            return [];
        }

        $total_count = count($users);
        $max_pages = 1;
        if ($total_count > $maxEntries) {
            $max_pages = (int) ceil($total_count / $maxEntries);
        }

        $index = [];
        for ($page = 1; $page <= $max_pages; $page++) {
            $n = ($max_pages > 1) ? (string) $page : '';
            $item = [
                'loc'     => Router::getBaseUrl("author-sitemap{$n}.xml"),
                'lastmod' => !empty($users[0]->last_update) ? '@' . $users[0]->last_update : '',
            ];

            $item = apply_filters('laca_seo/sitemap/index/entry', $item, 'author', 'author');
            if ($item) {
                $index[] = $item;
            }
        }

        return $index;
    }

    public function getSitemapLinks(string $type, int $maxEntries, int $currentPage): array
    {
        if ($type !== 'author') {
            return [];
        }

        $users = $this->getAuthors();
        if (empty($users)) {
            return [];
        }

        $offset = ($currentPage > 1) ? (($currentPage - 1) * $maxEntries) : 0;
        $sliced = array_slice($users, $offset, $maxEntries);
        $links = [];

        foreach ($sliced as $user) {
            $link = get_author_posts_url($user->ID, $user->user_nicename);
            if (!$link) {
                continue;
            }

            $url = [
                'loc'    => $link,
                'mod'    => !empty($user->last_update) ? '@' . $user->last_update : '',
                'images' => [],
            ];

            $url = apply_filters('laca_seo/sitemap/entry', $url, 'author', $user);
            if (!empty($url)) {
                $links[] = $url;
            }
        }

        return $links;
    }

    /**
     * @return object[]
     */
    private function getAuthors(): array
    {
        global $wpdb;

        $exclude_roles = (array) Settings::get('sitemap.exclude_roles', ['contributor', 'subscriber', 'customer', 'shop_manager']);
        $exclude_users = array_filter(wp_parse_id_list(Settings::get('sitemap.exclude_users', '')));
        $include_without_posts = Settings::get('sitemap.include_authors_without_posts', 'off') === 'on';

        $args = [
            'role__not_in' => $exclude_roles,
            'exclude'      => $exclude_users,
            'has_published_posts' => !$include_without_posts ? ['post', 'page', 'product'] : false,
        ];

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        if (empty($users)) {
            return [];
        }

        $filtered = [];
        foreach ($users as $user) {
            $robots = get_user_meta($user->ID, '_laca_seo_robots', true);
            if (is_array($robots) && in_array('noindex', $robots, true)) {
                continue;
            }

            $last_update = get_user_meta($user->ID, 'last_update', true);
            if (empty($last_update)) {
                $last_post_date = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_author = %d AND post_status = 'publish'",
                    $user->ID
                ));
                $last_update = $last_post_date ? strtotime($last_post_date) : strtotime($user->user_registered);
            }

            $user->last_update = $last_update;
            $filtered[] = $user;
        }

        usort($filtered, fn($a, $b) => ($b->last_update <=> $a->last_update));
        return $filtered;
    }
}
