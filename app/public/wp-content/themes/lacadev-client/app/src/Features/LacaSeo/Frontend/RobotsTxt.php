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
        if (!is_admin() && !empty(trim((string) Settings::get('general.robots_txt_content', '')))) {
            add_filter('robots_txt', [$this, 'filterRobotsTxt'], 10, 2);
        }
    }

    /**
     * Replace robots.txt content matching Rank Math 1:1.
     * When blog_public is 0, Rank Math leaves content untouched.
     * When public, Rank Math returns the setting content without auto-injecting anything.
     */
    public function filterRobotsTxt(string $content, mixed $is_public): string
    {
        return 0 === absint($is_public) ? $content : (string) Settings::get('general.robots_txt_content', '');
    }
}
