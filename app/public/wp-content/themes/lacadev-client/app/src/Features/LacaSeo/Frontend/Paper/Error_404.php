<?php

namespace App\Features\LacaSeo\Frontend\Paper;

defined('ABSPATH') || exit;

class Error_404 implements IPaper
{
    public function title(): string
    {
        return Paper::get_from_options('404_title', [], 'Page not found %sep% %sitename%');
    }

    public function description(): string
    {
        return '';
    }

    public function robots(): array
    {
        return ['index' => 'noindex'];
    }

    public function advanced_robots(): array
    {
        return [];
    }

    public function canonical(): array
    {
        return [];
    }

    public function keywords(): string
    {
        return '';
    }
}
