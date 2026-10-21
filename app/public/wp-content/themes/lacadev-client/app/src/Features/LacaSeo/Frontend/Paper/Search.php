<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

class Search implements IPaper
{
    public function title(): string
    {
        return Paper::get_from_options('search_title', [], 'Searched for %search_query% %page% %sep% %sitename%');
    }

    public function description(): string
    {
        return '';
    }

    public function robots(): array
    {
        return Settings::get('titles.noindex_search', 'on') === 'on' ? ['index' => 'noindex'] : [];
    }

    public function advanced_robots(): array
    {
        return [];
    }

    public function canonical(): array
    {
        return ['canonical' => get_search_link()];
    }

    public function keywords(): string
    {
        return '';
    }
}
