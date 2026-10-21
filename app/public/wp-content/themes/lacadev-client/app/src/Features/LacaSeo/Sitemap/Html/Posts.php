<?php

namespace App\Features\LacaSeo\Sitemap\Html;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\Sitemap as SitemapBase;

defined('ABSPATH') || exit;

/**
 * HTML sitemap generator for posts.
 */
class Posts
{
    /**
     * @param string $post_type
     * @param bool $show_dates
     * @return string
     */
    public function generateSitemap(string $post_type, bool $show_dates): string
    {
        $posts = $this->getPosts($post_type);
        if (empty($posts)) {
            return '';
        }

        $obj = get_post_type_object($post_type);
        $title = $obj ? ($obj->labels->name ?: $post_type) : $post_type;

        $output = [];
        $output[] = '<div class="laca-seo-html-sitemap__section laca-seo-html-sitemap__section--post-type laca-seo-html-sitemap__section--' . esc_attr($post_type) . '">';
        $output[] = '<h2 class="laca-seo-html-sitemap__title">' . esc_html($title) . '</h2>';
        $output[] = '<ul class="laca-seo-html-sitemap__list">';

        foreach ($posts as $post) {
            if (!SitemapBase::isObjectIndexable($post->ID, 'post')) {
                continue;
            }

            $permalink = get_permalink($post);
            if (!$permalink) {
                continue;
            }

            $item_title = $this->getPostTitle($post);
            $date_str = $show_dates ? ' <span class="laca-seo-html-sitemap__date">(' . esc_html(mysql2date(get_option('date_format'), $post->post_date)) . ')</span>' : '';

            $output[] = '<li class="laca-seo-html-sitemap__item">'
                . '<a href="' . esc_url($permalink) . '" class="laca-seo-html-sitemap__link">'
                . esc_html($item_title)
                . '</a>'
                . $date_str
                . '</li>';
        }

        $output[] = '</ul>';
        $output[] = '</div>';

        return implode('', $output);
    }

    /**
     * @return \WP_Post[]
     */
    private function getPosts(string $post_type): array
    {
        $sort_setting = Settings::get('sitemap.html_sitemap_sort', 'published');
        $orderby = match ($sort_setting) {
            'modified'     => 'post_modified',
            'alphabetical' => 'post_title',
            'post_id'      => 'ID',
            default        => 'post_date',
        };
        $order = ($sort_setting === 'alphabetical') ? 'ASC' : 'DESC';

        $exclude = array_filter(wp_parse_id_list(Settings::get('sitemap.exclude_posts', '')));

        $args = [
            'post_type'      => $post_type,
            'post_status'    => ($post_type === 'attachment') ? 'inherit' : 'publish',
            'posts_per_page' => 500,
            'orderby'        => $orderby,
            'order'          => $order,
            'no_found_rows'  => true,
        ];
        if (!empty($exclude)) {
            $args['post__not_in'] = $exclude;
        }

        return get_posts($args);
    }

    private function getPostTitle(\WP_Post $post): string
    {
        if (Settings::get('sitemap.html_sitemap_seo_titles', 'titles') === 'seo_titles') {
            $custom_title = get_post_meta($post->ID, '_laca_seo_title', true);
            if (!empty($custom_title)) {
                return \App\Features\LacaSeo\Core\Variables::replace($custom_title, $post);
            }
        }
        return $post->post_title;
    }
}
