<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

class Taxonomy implements IPaper
{
    private function getTerm(): ?\WP_Term
    {
        $obj = get_queried_object();
        return ($obj instanceof \WP_Term) ? $obj : null;
    }

    public function title(): string
    {
        $term = $this->getTerm();
        if (!$term) {
            return Paper::get_from_options('404_title', [], 'Page not found');
        }

        $meta = (string) get_term_meta($term->term_id, '_laca_seo_title', true);
        if ($meta !== '') {
            return Variables::replace($meta, $term);
        }

        return Paper::get_from_options("tax_{$term->taxonomy}_title", $term, '');
    }

    public function description(): string
    {
        $term = $this->getTerm();
        if (!$term) {
            return '';
        }

        $meta = (string) get_term_meta($term->term_id, '_laca_seo_description', true);
        if ($meta !== '') {
            return Variables::replace($meta, $term);
        }

        return Paper::get_from_options("tax_{$term->taxonomy}_description", $term, '');
    }

    public function robots(): array
    {
        $term = $this->getTerm();
        if (!$term) {
            return [];
        }

        $robots = Paper::robots_combine(get_term_meta($term->term_id, '_laca_seo_robots', true));
        $tax = $term->taxonomy;

        if (empty($robots) && Settings::get("titles.tax_{$tax}_custom_robots", 'off') === 'on') {
            $robots = Paper::robots_combine((array) Settings::get("titles.tax_{$tax}_robots", []), true);
        }

        // Empty taxonomy check
        if (Settings::get('titles.noindex_empty_taxonomies', 'on') === 'on' && $term->count === 0) {
            $robots['index'] = 'noindex';
        }

        // Subpages check
        if (is_paged() && Settings::get('titles.noindex_archive_subpages', 'off') === 'on') {
            $robots['index'] = 'noindex';
        }

        return $robots;
    }

    public function advanced_robots(): ?array
    {
        $term = $this->getTerm();
        if (!$term) {
            return [];
        }

        $adv = Paper::advanced_robots_combine(get_term_meta($term->term_id, '_laca_seo_advanced_robots', true));
        $tax = $term->taxonomy;

        if (empty($adv) && Settings::get("titles.tax_{$tax}_custom_robots", 'off') === 'on') {
            $adv = Paper::advanced_robots_combine((array) Settings::get("titles.tax_{$tax}_advanced_robots", []));
        }

        return !empty($adv) ? $adv : null;
    }

    public function canonical(): array
    {
        $term = $this->getTerm();
        $canonical = $term ? get_term_link($term) : home_url();
        $override = $term ? (string) get_term_meta($term->term_id, '_laca_seo_canonical_url', true) : '';

        return [
            'canonical'          => !is_wp_error($canonical) ? $canonical : home_url(),
            'canonical_unpaged'  => !is_wp_error($canonical) ? $canonical : home_url(),
            'canonical_override' => $override,
        ];
    }

    public function keywords(): string
    {
        $term = $this->getTerm();
        return $term ? (string) get_term_meta($term->term_id, '_laca_seo_focus_keyword', true) : '';
    }
}
