<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

class Archive implements IPaper
{
    private function getQueriedPostType(): string
    {
        $pt = get_query_var('post_type');
        if (is_array($pt)) {
            return (string) reset($pt);
        }
        if (is_string($pt) && $pt !== '') {
            return $pt;
        }
        return 'post';
    }

    public function title(): string
    {
        $pt = $this->getQueriedPostType();
        return Paper::get_from_options("pt_{$pt}_archive_title", [], '%pt_plural% Archive %page% %sep% %sitename%');
    }

    public function description(): string
    {
        $pt = $this->getQueriedPostType();
        return Paper::get_from_options("pt_{$pt}_archive_description", [], '%pt_plural% Archive %page% %sep% %sitename%');
    }

    public function robots(): array
    {
        $pt = $this->getQueriedPostType();
        $robots = [];

        if (Settings::get("titles.pt_{$pt}_custom_robots", 'off') === 'on') {
            $robots = Paper::robots_combine((array) Settings::get("titles.pt_{$pt}_robots", []));
        }

        if (is_paged() && Settings::get('titles.noindex_archive_subpages', 'off') === 'on') {
            $robots['index'] = 'noindex';
        }

        return $robots;
    }

    public function advanced_robots(): array
    {
        $pt = $this->getQueriedPostType();
        if (Settings::get("titles.pt_{$pt}_custom_robots", 'off') === 'on') {
            return Paper::advanced_robots_combine((array) Settings::get("titles.pt_{$pt}_advanced_robots", []));
        }
        return [];
    }

    public function canonical(): array
    {
        $pt = $this->getQueriedPostType();
        $link = get_post_type_archive_link($pt);
        return [
            'canonical'         => !empty($link) ? $link : home_url(),
            'canonical_unpaged' => !empty($link) ? $link : home_url(),
        ];
    }

    public function keywords(): string
    {
        return '';
    }
}
