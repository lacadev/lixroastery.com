<?php

namespace App\Features\LacaSeo\Frontend;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Filters the virtual robots.txt output.
 */
class RobotsTxt
{
    public function register(): void
    {
        add_filter('robots_txt', [$this, 'filterRobotsTxt'], 10, 2);
    }

    /**
     * Filter robots.txt content if site is public.
     */
    public function filterRobotsTxt(string $output, bool $public): string
    {
        if (!$public) {
            return "User-agent: *\nDisallow: /\n";
        }

        $custom = (string) Settings::get('general.robots_txt_content', '');
        if (!empty(trim($custom))) {
            return trim($custom) . "\n";
        }

        return $output;
    }
}
