<?php

namespace App\Features\LacaSeo\Sitemap\Html;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use WP_User_Query;

defined('ABSPATH') || exit;

/**
 * HTML sitemap generator for authors.
 */
class Authors
{
    public function generateSitemap(): string
    {
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
            return '';
        }

        $output = [];
        $output[] = '<div class="laca-seo-html-sitemap__section laca-seo-html-sitemap__section--authors">';
        $output[] = '<h2 class="laca-seo-html-sitemap__title">' . esc_html__('Tác giả', 'lacadev') . '</h2>';
        $output[] = '<ul class="laca-seo-html-sitemap__list">';

        foreach ($users as $user) {
            $link = get_author_posts_url($user->ID, $user->user_nicename);
            if (!$link) {
                continue;
            }

            $output[] = '<li class="laca-seo-html-sitemap__item">'
                . '<a href="' . esc_url($link) . '" class="laca-seo-html-sitemap__link">'
                . esc_html($user->display_name)
                . '</a>'
                . '</li>';
        }

        $output[] = '</ul>';
        $output[] = '</div>';

        return implode('', $output);
    }
}
