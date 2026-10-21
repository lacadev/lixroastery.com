<?php

namespace App\Features\LacaSeo\Sitemap\Html;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\Sitemap as SitemapBase;
use WP_Term;

defined('ABSPATH') || exit;

/**
 * HTML sitemap generator for terms.
 */
class Terms
{
    /**
     * @param string $taxonomy
     * @param bool $show_dates
     * @param array<string, mixed> $args
     * @return string
     */
    public function generateSitemap(string $taxonomy, bool $show_dates, array $args = []): string
    {
        $tax_obj = get_taxonomy($taxonomy);
        if (!$tax_obj) {
            return '';
        }

        $sort_setting = Settings::get('sitemap.html_sitemap_sort', 'published');
        $orderby = match ($sort_setting) {
            'alphabetical' => 'name',
            default        => 'term_id',
        };
        $order = ($sort_setting === 'alphabetical') ? 'ASC' : 'DESC';

        $include_empty = Settings::get("sitemap.tax_{$taxonomy}_include_empty", 'off') === 'on';
        $exclude = array_filter(wp_parse_id_list(Settings::get('sitemap.exclude_terms', '')));

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => !$include_empty,
            'orderby'    => $orderby,
            'order'      => $order,
            'exclude'    => $exclude,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $title = $tax_obj->labels->name ?: $taxonomy;
        $output = [];
        $output[] = '<div class="laca-seo-html-sitemap__section laca-seo-html-sitemap__section--taxonomy laca-seo-html-sitemap__section--' . esc_attr($taxonomy) . '">';
        $output[] = '<h2 class="laca-seo-html-sitemap__title">' . esc_html($title) . '</h2>';
        $output[] = '<ul class="laca-seo-html-sitemap__list">';

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            if (!SitemapBase::isObjectIndexable($term->term_id, 'term')) {
                continue;
            }

            $link = get_term_link($term, $taxonomy);
            if (is_wp_error($link) || empty($link)) {
                continue;
            }

            $output[] = '<li class="laca-seo-html-sitemap__item">'
                . '<a href="' . esc_url($link) . '" class="laca-seo-html-sitemap__link">'
                . esc_html($term->name)
                . '</a>'
                . '</li>';
        }

        $output[] = '</ul>';
        $output[] = '</div>';

        return implode('', $output);
    }
}
