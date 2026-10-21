<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

class Blog implements IPaper
{
    public function title(): string
    {
        return Paper::get_from_options('homepage_title', [], '%sitename% %page% %sep% %sitedesc%');
    }

    public function description(): string
    {
        return Paper::get_from_options('homepage_description', [], get_bloginfo('description'));
    }

    public function robots(): array
    {
        $robots = [];

        if (Settings::get('titles.homepage_custom_robots', 'off') === 'on') {
            $robots = Paper::robots_combine((array) Settings::get('titles.homepage_robots', []));
        }

        if (is_paged() && Settings::get('titles.noindex_paginated_pages', 'off') === 'on') {
            $robots['index'] = 'noindex';
        }

        return $robots;
    }

    public function advanced_robots(): array
    {
        if (Settings::get('titles.homepage_custom_robots', 'off') !== 'on') {
            return [];
        }

        return Paper::advanced_robots_combine((array) Settings::get('titles.homepage_advanced_robots', []));
    }

    public function canonical(): array
    {
        $canonical = home_url();
        $postsPageId = (int) get_option('page_for_posts');
        if (is_home() && 'page' === get_option('show_on_front') && $postsPageId > 0) {
            $override = (string) get_post_meta($postsPageId, '_laca_seo_canonical_url', true);
            $canonical = $override !== '' ? $override : get_permalink($postsPageId);
        }

        return ['canonical' => $canonical];
    }

    public function keywords(): string
    {
        $postsPageId = (int) get_option('page_for_posts');
        if (is_home() && 'page' === get_option('show_on_front') && $postsPageId > 0) {
            return (string) get_post_meta($postsPageId, '_laca_seo_focus_keyword', true);
        }
        return '';
    }
}
