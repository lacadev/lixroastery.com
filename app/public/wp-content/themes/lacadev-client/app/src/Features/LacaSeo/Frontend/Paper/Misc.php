<?php

namespace App\Features\LacaSeo\Frontend\Paper;

defined('ABSPATH') || exit;

class Misc implements IPaper
{
    public function title(): string
    {
        return '';
    }

    public function description(): string
    {
        return '';
    }

    public function robots(): array
    {
        return [];
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
