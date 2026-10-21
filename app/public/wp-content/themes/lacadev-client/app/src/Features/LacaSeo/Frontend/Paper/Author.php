<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

class Author implements IPaper
{
    private function getAuthor(): ?\WP_User
    {
        $obj = get_queried_object();
        return ($obj instanceof \WP_User) ? $obj : null;
    }

    public function title(): string
    {
        $user = $this->getAuthor();
        if ($user) {
            $meta = (string) get_user_meta($user->ID, '_laca_seo_title', true);
            if ($meta !== '') {
                return Variables::replace($meta, $user);
            }
        }

        return Paper::get_from_options('author_archive_title', $user, '%name% %sep% %sitename% %page%');
    }

    public function description(): string
    {
        $user = $this->getAuthor();
        if ($user) {
            $meta = (string) get_user_meta($user->ID, '_laca_seo_description', true);
            if ($meta !== '') {
                return Variables::replace($meta, $user);
            }
        }

        return Paper::get_from_options('author_archive_description', $user, '');
    }

    public function robots(): array
    {
        $user = $this->getAuthor();
        $robots = [];

        if ($user) {
            $robots = Paper::robots_combine(get_user_meta($user->ID, '_laca_seo_robots', true));
        }

        if (empty($robots) && Settings::get('titles.author_custom_robots', 'off') === 'on') {
            $robots = Paper::robots_combine((array) Settings::get('titles.author_robots', []), true);
        }

        if (Settings::get('titles.disable_author_archives', 'off') === 'on') {
            $robots['index'] = 'noindex';
        }

        if (is_paged() && Settings::get('titles.noindex_archive_subpages', 'off') === 'on') {
            $robots['index'] = 'noindex';
        }

        return $robots;
    }

    public function advanced_robots(): array
    {
        $user = $this->getAuthor();
        $adv = [];

        if ($user) {
            $adv = Paper::advanced_robots_combine(get_user_meta($user->ID, '_laca_seo_advanced_robots', true));
        }

        if (empty($adv) && Settings::get('titles.author_custom_robots', 'off') === 'on') {
            $adv = Paper::advanced_robots_combine((array) Settings::get('titles.author_advanced_robots', []));
        }

        return $adv;
    }

    public function canonical(): array
    {
        $user = $this->getAuthor();
        $canonical = $user ? get_author_posts_url($user->ID) : home_url();
        $override = $user ? (string) get_user_meta($user->ID, '_laca_seo_canonical_url', true) : '';

        return [
            'canonical'          => $canonical,
            'canonical_unpaged'  => $canonical,
            'canonical_override' => $override,
        ];
    }

    public function keywords(): string
    {
        $user = $this->getAuthor();
        return $user ? (string) get_user_meta($user->ID, '_laca_seo_focus_keyword', true) : '';
    }
}
