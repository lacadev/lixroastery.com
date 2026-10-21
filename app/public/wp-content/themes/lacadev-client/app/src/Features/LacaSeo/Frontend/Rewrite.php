<?php

namespace App\Features\LacaSeo\Frontend;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Handles author & date archive disablement and URL rewrite modifications.
 */
class Rewrite
{
    public function register(): void
    {
        // 1. Author and Date archives redirect
        add_action('template_redirect', [$this, 'handleArchiveRedirects']);

        // 2. Strip category base rewrite rules
        if (Settings::get('general.strip_category_base', 'off') === 'on') {
            add_filter('category_rewrite_rules', [$this, 'filterCategoryRules']);
            add_filter('category_link', [$this, 'filterCategoryLink'], 1000);
        }

        // 3. Custom author base
        add_action('init', [$this, 'changeAuthorBase']);
    }

    /**
     * Redirect disabled author and date archives to homepage.
     */
    public function handleArchiveRedirects(): void
    {
        if (is_author() && Settings::get('titles.disable_author_archives', 'off') === 'on') {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }

        if (is_date() && Settings::get('titles.disable_date_archives', 'on') === 'on') {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }

    /**
     * Strip /category/ from category links.
     */
    public function filterCategoryLink(string $link): string
    {
        $cat_base = get_option('category_base') ?: 'category';
        $cat_base = trim($cat_base, '/');

        return preg_replace('`/' . preg_quote($cat_base, '`') . '(/|$)`', '/', $link, 1);
    }

    /**
     * Remove category base from rewrite rules.
     *
     * @param array<string, string> $category_rewrite
     * @return array<string, string>
     */
    public function filterCategoryRules(array $category_rewrite): array
    {
        $cat_base = get_option('category_base') ?: 'category';
        $cat_base = trim($cat_base, '/');

        $new_rules = [];
        foreach ($category_rewrite as $pattern => $query) {
            $new_pattern = preg_replace('`^' . preg_quote($cat_base, '`') . '/`', '', $pattern);
            $new_rules[$new_pattern] = $query;
        }

        return $new_rules;
    }

    /**
     * Modify author base slug if configured.
     */
    public function changeAuthorBase(): void
    {
        global $wp_rewrite;
        $custom_base = trim((string) Settings::get('titles.url_author_base', 'author'));
        if (!empty($custom_base) && $custom_base !== 'author') {
            $wp_rewrite->author_base = $custom_base;
        }
    }
}
