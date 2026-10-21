<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

class Date implements IPaper
{
    public function title(): string
    {
        return Paper::get_from_options('date_archive_title', [], '%date% %page% %sep% %sitename%');
    }

    public function description(): string
    {
        return Paper::get_from_options('date_archive_description', [], '');
    }

    public function robots(): array
    {
        $robots = Paper::robots_combine((array) Settings::get('titles.date_archive_robots', []));
        if (Settings::get('titles.disable_date_archives', 'off') === 'on') {
            $robots['index'] = 'noindex';
        }
        if (is_paged() && Settings::get('titles.noindex_archive_subpages', 'off') === 'on') {
            $robots['index'] = 'noindex';
        }
        return $robots;
    }

    public function advanced_robots(): array
    {
        return [];
    }

    public function canonical(): array
    {
        $canonical = '';
        if (is_day()) {
            $canonical = get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
        } elseif (is_month()) {
            $canonical = get_month_link(get_query_var('year'), get_query_var('monthnum'));
        } elseif (is_year()) {
            $canonical = get_year_link(get_query_var('year'));
        }

        return ['canonical' => $canonical ?: home_url()];
    }

    public function keywords(): string
    {
        return '';
    }
}
