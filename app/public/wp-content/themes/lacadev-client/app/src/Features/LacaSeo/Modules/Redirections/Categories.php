<?php

namespace App\Features\LacaSeo\Modules\Redirections;

defined('ABSPATH') || exit;

/**
 * Pro feature: Redirection Categories taxonomy.
 */
class Categories
{
    public const TAXONOMY = 'laca_seo_redirection_category';

    public static function register(): void
    {
        register_taxonomy(
            self::TAXONOMY,
            null,
            [
                'labels' => [
                    'name'          => __('Danh mục chuyển hướng', 'lacadev'),
                    'singular_name' => __('Danh mục chuyển hướng', 'lacadev'),
                ],
                'public'            => false,
                'show_ui'           => false,
                'show_in_menu'      => false,
                'show_in_nav_menus' => false,
                'hierarchical'      => true,
                'rewrite'           => false,
                'query_var'         => false,
            ]
        );
    }

    /**
     * Get terms.
     */
    public static function getTerms(): array
    {
        $terms = get_terms([
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
        ]);
        return is_array($terms) ? $terms : [];
    }
}
