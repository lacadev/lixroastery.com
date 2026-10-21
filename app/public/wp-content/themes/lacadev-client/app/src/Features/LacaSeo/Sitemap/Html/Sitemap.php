<?php

namespace App\Features\LacaSeo\Sitemap\Html;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\Cache;

defined('ABSPATH') || exit;

/**
 * Main coordinator for HTML Sitemap.
 */
class Sitemap
{
    private Posts $posts;
    private Terms $terms;
    private Authors $authors;
    private Cache $cache;

    public function __construct()
    {
        $this->posts   = new Posts();
        $this->terms   = new Terms();
        $this->authors = new Authors();
        $this->cache   = new Cache();
    }

    public function register(): void
    {
        add_shortcode('laca_seo_html_sitemap', [$this, 'renderShortcode']);

        // When Rank Math is inactive, also register alias [rank_math_html_sitemap]
        if (!Helper::isRankMathActive()) {
            add_shortcode('rank_math_html_sitemap', [$this, 'renderShortcode']);
        }

        // Display on page if configured
        if (Settings::get('sitemap.html_sitemap_display', 'shortcode') === 'page') {
            add_filter('the_content', [$this, 'appendPageContent']);
        }
    }

    public function renderShortcode(): string
    {
        return $this->getOutput();
    }

    public function appendPageContent(string $content): string
    {
        if (!is_page() || !is_main_query() || !in_the_loop()) {
            return $content;
        }

        $page_id = (int) Settings::get('sitemap.html_sitemap_page', 0);
        if ($page_id > 0 && get_the_ID() === $page_id) {
            return $content . $this->getOutput();
        }

        return $content;
    }

    public function getOutput(): string
    {
        if (Settings::get('sitemap.html_sitemap', 'off') !== 'on') {
            return '';
        }

        $show_dates = Settings::get('sitemap.html_sitemap_show_dates', 'on') === 'on';
        $output = [];
        $output[] = '<div class="laca-seo-html-sitemap">';

        // 1. Post types
        $post_types = Helper::getAccessiblePostTypes();
        foreach ($post_types as $pt_slug => $pt_obj) {
            if (Helper::isRankMathActive() && function_exists('rank_math')) {
                $enabled = \RankMath\Helper::get_settings("sitemap.pt_{$pt_slug}_html_sitemap") ? 'on' : 'off';
            } else {
                $enabled = Settings::get("sitemap.pt_{$pt_slug}_html_sitemap", ($pt_slug === 'attachment' ? 'off' : 'on'));
            }
            if ($enabled !== 'on') {
                continue;
            }

            $cached = $this->cache->getSitemap($pt_slug, 1, true);
            if (!empty($cached)) {
                $output[] = $cached;
                continue;
            }

            $html = $this->posts->generateSitemap($pt_slug, $show_dates);
            if (!empty($html)) {
                $this->cache->storeSitemap($pt_slug, 1, $html, true);
                $output[] = $html;
            }
        }

        // 2. Taxonomies
        $taxonomies = Helper::getAccessibleTaxonomies();
        foreach ($taxonomies as $tax_slug => $tax_obj) {
            if (Helper::isRankMathActive() && function_exists('rank_math')) {
                $enabled = \RankMath\Helper::get_settings("sitemap.tax_{$tax_slug}_html_sitemap") ? 'on' : 'off';
            } else {
                $default = ($tax_slug === 'category') ? 'on' : 'off';
                $enabled = Settings::get("sitemap.tax_{$tax_slug}_html_sitemap", $default);
            }
            if ($enabled !== 'on') {
                continue;
            }

            $cached = $this->cache->getSitemap($tax_slug, 1, true);
            if (!empty($cached)) {
                $output[] = $cached;
                continue;
            }

            $html = $this->terms->generateSitemap($tax_slug, $show_dates);
            if (!empty($html)) {
                $this->cache->storeSitemap($tax_slug, 1, $html, true);
                $output[] = $html;
            }
        }

        // 3. Authors
        if ($this->shouldShowAuthorSitemap()) {
            $cached = $this->cache->getSitemap('author', 1, true);
            if (!empty($cached)) {
                $output[] = $cached;
            } else {
                $html = $this->authors->generateSitemap();
                if (!empty($html)) {
                    $this->cache->storeSitemap('author', 1, $html, true);
                    $output[] = $html;
                }
            }
        }

        $output[] = '</div>';

        return implode("\n", $output);
    }

    private function shouldShowAuthorSitemap(): bool
    {
        if (Settings::get('sitemap.authors_html_sitemap', 'on') !== 'on') {
            return false;
        }

        if (Settings::get('titles.disable_author_archives', 'off') === 'on') {
            return false;
        }

        $robots = (array) Settings::get('titles.author_robots', ['index']);
        if (in_array('noindex', $robots, true)) {
            return false;
        }

        return true;
    }
}
