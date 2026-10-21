<?php

namespace App\Features\LacaSeo\Frontend;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use WP_Post;
use WP_Term;

defined('ABSPATH') || exit;

/**
 * Breadcrumbs generator conforming 1:1 to Rank Math logic.
 */
class Breadcrumbs
{
    /** @var array<int, array{0: string, 1: string, hide_in_schema?: bool}> */
    private array $crumbs = [];
    /** @var array<string, mixed> */
    private array $settings = [];
    /** @var array<string, string> */
    private array $strings = [];

    public function __construct()
    {
        if (Helper::isRankMathActive() && function_exists('rank_math')) {
            $this->settings = [
                'home'            => (bool) \RankMath\Helper::get_settings('general.breadcrumbs_home'),
                'separator'       => (string) \RankMath\Helper::get_settings('general.breadcrumbs_separator', '-'),
                'remove_title'    => (bool) \RankMath\Helper::get_settings('general.breadcrumbs_remove_post_title'),
                'hide_tax_name'   => (bool) \RankMath\Helper::get_settings('general.breadcrumbs_hide_taxonomy_name'),
                'show_ancestors'  => (bool) \RankMath\Helper::get_settings('general.breadcrumbs_ancestor_categories'),
                'show_blog'       => (bool) \RankMath\Helper::get_settings('general.breadcrumbs_blog_page'),
                'show_pagination' => true,
            ];

            $this->strings = [
                'prefix'         => (string) \RankMath\Helper::get_settings('general.breadcrumbs_prefix', ''),
                'home'           => (string) \RankMath\Helper::get_settings('general.breadcrumbs_home_label', 'Home'),
                'home_link'      => (string) \RankMath\Helper::get_settings('general.breadcrumbs_home_link', home_url()),
                'error404'       => (string) \RankMath\Helper::get_settings('general.breadcrumbs_404_label', '404 Error: page not found'),
                'search_format'  => (string) \RankMath\Helper::get_settings('general.breadcrumbs_search_format', ''),
                'archive_format' => (string) \RankMath\Helper::get_settings('general.breadcrumbs_archive_format', ''),
            ];
        } else {
            $this->settings = [
                'home'            => Settings::get('general.breadcrumbs_home', 'on') === 'on',
                'separator'       => Settings::get('general.breadcrumbs_separator', '-'),
                'remove_title'    => Settings::get('general.breadcrumbs_remove_post_title', 'off') === 'on',
                'hide_tax_name'   => Settings::get('general.breadcrumbs_hide_taxonomy_name', 'off') === 'on',
                'show_ancestors'  => Settings::get('general.breadcrumbs_ancestor_categories', 'off') === 'on',
                'show_blog'       => Settings::get('general.breadcrumbs_blog_page', 'off') === 'on',
                'show_pagination' => true,
            ];

            $this->strings = [
                'prefix'         => (string) Settings::get('general.breadcrumbs_prefix', ''),
                'home'           => (string) Settings::get('general.breadcrumbs_home_label', 'Home'),
                'home_link'      => (string) Settings::get('general.breadcrumbs_home_link', home_url()),
                'error404'       => (string) Settings::get('general.breadcrumbs_404_label', '404 Error: page not found'),
                'search_format'  => (string) Settings::get('general.breadcrumbs_search_format', 'Results for %s'),
                'archive_format' => (string) Settings::get('general.breadcrumbs_archive_format', 'Archives for %s'),
            ];
        }
    }

    public function register(): void
    {
        add_shortcode('laca_seo_breadcrumb', [$this, 'renderShortcode']);

        // When Rank Math is inactive, define helper functions and alias shortcode
        if (!Helper::isRankMathActive()) {
            add_shortcode('rank_math_breadcrumb', [$this, 'renderShortcode']);
        }
    }

    public function renderShortcode(): string
    {
        return $this->getBreadcrumb();
    }

    /**
     * @param array<string, mixed> $args
     */
    public function getBreadcrumb(array $args = []): string
    {
        $args = wp_parse_args($args, [
            'separator'   => $this->settings['separator'],
            'wrap_before' => '<nav aria-label="breadcrumbs" class="laca-seo-breadcrumb rank-math-breadcrumb"><p>',
            'wrap_after'  => '</p></nav>',
            'before'      => '',
            'after'       => '',
        ]);

        $html   = '';
        $crumbs = $this->getCrumbs();

        $remove_title = (is_single() || is_page()) && $this->settings['remove_title'];
        if ($remove_title) {
            array_pop($crumbs);
        }
        $size = count($crumbs);

        if (!empty($this->strings['prefix'])) {
            $html .= sprintf('<span class="label">%s</span> ', esc_html($this->strings['prefix']));
        }

        foreach ($crumbs as $key => $crumb) {
            $link = !empty($crumb[1]) && ($remove_title || $size !== $key + 1);
            $link = $link ? '<a href="' . esc_url($crumb[1]) . '">' . esc_html($crumb[0]) . '</a>' :
                '<span class="last">' . esc_html($crumb[0]) . '</span>';

            $html .= wp_kses_post($args['before']) . $link . wp_kses_post($args['after']);

            if ($size !== $key + 1) {
                $html .= '<span class="separator"> ' . wp_kses_post($args['separator']) . ' </span>';
            }
        }

        $html = wp_kses_post($args['wrap_before']) . $html . wp_kses_post($args['wrap_after']);
        return apply_filters('laca_seo/frontend/breadcrumb/html', $html, $crumbs, $this);
    }

    /**
     * @return array<int, array{0: string, 1: string, hide_in_schema?: bool}>
     */
    public function getCrumbs(): array
    {
        if (empty($this->crumbs)) {
            $this->generate();
        }

        return apply_filters('laca_seo/frontend/breadcrumb/items', $this->crumbs, $this);
    }

    private function addCrumb(string $name, string $link = '', bool $hide_in_schema = false): void
    {
        $this->crumbs[] = [
            wp_strip_all_tags($name),
            $link,
            'hide_in_schema' => $hide_in_schema,
        ];
    }

    private function generate(): void
    {
        $conditionals = [
            'is_home',
            'is_404',
            'is_search',
            'is_attachment',
            'is_shop',
            'is_product',
            'is_singular',
            'is_product_category',
            'is_product_tag',
            'is_post_type_archive',
            'is_category',
            'is_tag',
            'is_tax',
            'is_date',
            'is_author',
        ];

        $this->maybeAddHomeCrumb();

        if (!$this->canGenerate()) {
            return;
        }

        foreach ($conditionals as $conditional) {
            if (function_exists($conditional) && call_user_func($conditional)) {
                $method = 'addCrumbs' . str_replace('_', '', substr($conditional, 3));
                if (method_exists($this, $method)) {
                    $this->$method();
                    break;
                }
            }
        }

        $this->maybeAddPageCrumb();
    }

    private function canGenerate(): bool
    {
        return (
            !is_front_page() &&
            !(
                is_post_type_archive() &&
                function_exists('wc_get_page_id') &&
                (int) get_option('page_on_front') === wc_get_page_id('shop')
            )
        ) || is_paged();
    }

    private function addCrumbsHome(): void
    {
        $this->addCrumb(single_post_title('', false));
    }

    private function addCrumbs404(): void
    {
        $this->addCrumb($this->strings['error404']);
    }

    private function addCrumbsSearch(): void
    {
        $this->addCrumb($this->replacePlaceholder($this->strings['search_format'], get_search_query()), remove_query_arg('paged'));
    }

    private function addCrumbsAttachment(): void
    {
        global $post;
        if ($post && $post->post_parent) {
            $this->addCrumbsSingular($post->post_parent, get_permalink($post->post_parent));
        }
        $this->addCrumb($this->getBreadcrumbTitle('post', get_the_ID(), get_the_title()), get_permalink());
    }

    private function addCrumbsProduct(): void
    {
        global $post;

        $this->prependShopPage();
        $main_tax = Settings::get('titles.pt_product_primary_taxonomy', 'product_cat');
        if ($main_tax && $post) {
            $this->maybeAddPrimaryTerm(get_the_terms($post->ID, $main_tax));
        }

        if (isset($post->ID)) {
            $this->addCrumb($this->getBreadcrumbTitle('post', $post->ID, get_the_title($post)), get_permalink($post));
        }
    }

    private function addCrumbsSingular(int $post_id = 0, string $permalink = ''): void
    {
        $post      = !$post_id ? $GLOBALS['post'] : get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        $post_type = get_post_type($post);
        $permalink = $permalink ? $permalink : get_permalink($post);

        $this->addCrumbsPostTypeArchive($post_type);

        if (!isset($post->ID) || empty($post->ID)) {
            return;
        }

        $this->maybeAddBlog();

        $main_tax = Settings::get("titles.pt_{$post_type}_primary_taxonomy", '');
        if (isset($post->post_parent) && 0 === (int) $post->post_parent && $main_tax) {
            $this->maybeAddPrimaryTerm(get_the_terms($post, $main_tax));
        }

        if (isset($post->post_parent) && 0 !== (int) $post->post_parent) {
            $this->addPostAncestors($post);
        }

        $this->addCrumb($this->getBreadcrumbTitle('post', $post->ID, get_the_title($post)), $permalink);
    }

    private function addCrumbsProductCategory(): void
    {
        $term = $GLOBALS['wp_query']->get_queried_object();
        if (!$term instanceof WP_Term) {
            return;
        }
        $this->prependShopPage();
        $this->maybeAddTermAncestors($term);
        $this->addCrumb($this->getBreadcrumbTitle('term', $term->term_id, $term->name), get_term_link($term));
    }

    private function addCrumbsProductTag(): void
    {
        $term = $GLOBALS['wp_query']->get_queried_object();
        if (!$term instanceof WP_Term) {
            return;
        }
        $this->prependShopPage();
        $label = sprintf(__('Products tagged &ldquo;%s&rdquo;', 'lacadev'), $this->getBreadcrumbTitle('term', $term->term_id, $term->name));
        $this->addCrumb($label, get_term_link($term));
    }

    private function addCrumbsShop(): void
    {
        $shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : false;
        if ((int) get_option('page_on_front') === $shop_page_id) {
            return;
        }

        $name = $shop_page_id ? $this->getBreadcrumbTitle('post', $shop_page_id, get_the_title($shop_page_id)) : '';
        if (!$name) {
            $post_type = get_post_type_object('product');
            $name      = $post_type ? $post_type->labels->singular_name : 'Shop';
        }
        $this->addCrumb($name, get_post_type_archive_link('product') ?: home_url('/shop/'));
    }

    private function addCrumbsPostTypeArchive(?string $post_type = null): void
    {
        if (!$post_type) {
            $post_type = $GLOBALS['wp_query']->get('post_type');
        }

        if ('post' === $post_type || empty($post_type)) {
            return;
        }

        $type_object = get_post_type_object($post_type);
        if (!empty($type_object->has_archive)) {
            $this->addCrumb($type_object->labels->name, get_post_type_archive_link($post_type));
        }
    }

    private function addCrumbsCategory(): void
    {
        $this->maybeAddBlog();
        $term = $GLOBALS['wp_query']->get_queried_object();
        if (!$term instanceof WP_Term) {
            return;
        }

        $this->maybeAddTermAncestors($term);
        $this->addCrumb($this->getBreadcrumbTitle('term', $term->term_id, $term->name), get_term_link($term));
    }

    private function addCrumbsTag(): void
    {
        $this->maybeAddBlog();
        $term = $GLOBALS['wp_query']->get_queried_object();
        if (!$term instanceof WP_Term) {
            return;
        }

        $this->addCrumb($this->getBreadcrumbTitle('term', $term->term_id, $term->name), get_term_link($term));
    }

    private function addCrumbsTax(): void
    {
        $term = $GLOBALS['wp_query']->get_queried_object();
        if (!$term instanceof WP_Term) {
            return;
        }

        if (!$this->settings['hide_tax_name']) {
            $taxonomy = get_taxonomy($term->taxonomy);
            if ($taxonomy) {
                $this->addCrumb($taxonomy->labels->name);
            }
        }

        $this->maybeAddTermAncestors($term);
        $this->addCrumb($this->getBreadcrumbTitle('term', $term->term_id, $term->name), get_term_link($term));
    }

    private function addCrumbsDate(): void
    {
        if (is_year() || is_month() || is_day()) {
            $this->addCrumb($this->replacePlaceholder($this->strings['archive_format'], get_the_time('Y')), get_year_link((int) get_the_time('Y')));
        }
        if (is_month() || is_day()) {
            $this->addCrumb($this->replacePlaceholder($this->strings['archive_format'], get_the_time('F')), get_month_link((int) get_the_time('Y'), (int) get_the_time('m')));
        }
        if (is_day()) {
            $this->addCrumb($this->replacePlaceholder($this->strings['archive_format'], get_the_time('d')));
        }
    }

    private function addCrumbsAuthor(): void
    {
        global $author;
        $userdata = get_userdata((int) $author);
        if (!$userdata) {
            return;
        }

        $this->addCrumb($this->replacePlaceholder($this->strings['archive_format'], $this->getBreadcrumbTitle('user', $userdata->ID, $userdata->display_name)));
    }

    private function addPostAncestors(WP_Post $post): void
    {
        $ancestors = [];
        if (isset($post->ancestors)) {
            $ancestors = is_array($post->ancestors) ? array_values($post->ancestors) : [$post->ancestors];
        } elseif (isset($post->post_parent)) {
            $ancestors = [$post->post_parent];
        }

        $ancestors = array_reverse($ancestors);
        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_post($ancestor_id);
            if ($ancestor) {
                $this->addCrumb($this->getBreadcrumbTitle('post', $ancestor->ID, get_the_title($ancestor)), get_permalink($ancestor));
            }
        }
    }

    private function prependShopPage(): void
    {
        $shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : false;
        $shop_page    = get_post($shop_page_id);

        if ($shop_page_id && $shop_page && $this->isUsingShopBase($shop_page) && (int) get_option('page_on_front') !== $shop_page_id) {
            $this->addCrumb($this->getBreadcrumbTitle('post', $shop_page_id, get_the_title($shop_page)), get_permalink($shop_page));
        }
    }

    private function isUsingShopBase(mixed $shop_page): bool
    {
        if (!function_exists('wc_get_permalink_structure')) {
            return false;
        }

        $permalinks = wc_get_permalink_structure();
        $is_using_shop_base = isset($permalinks['product_base']) && strstr($permalinks['product_base'], '/' . ($shop_page->post_name ?? ''));

        return (bool) apply_filters('rank_math/frontend/breadcrumb/is_using_shop_base', $is_using_shop_base);
    }

    private function maybeAddPrimaryTerm(mixed $terms): void
    {
        if (is_wp_error($terms) || empty($terms) || !is_array($terms)) {
            return;
        }

        // Check if primary term is saved in post meta
        $post = $GLOBALS['post'] ?? null;
        $primary_term_id = 0;
        if ($post instanceof WP_Post) {
            $first_tax = $terms[0]->taxonomy ?? '';
            $primary_term_id = (int) get_post_meta($post->ID, "_laca_seo_primary_{$first_tax}", true);
        }

        $chosen_term = $terms[0];
        if ($primary_term_id > 0) {
            foreach ($terms as $t) {
                if ($t->term_id === $primary_term_id) {
                    $chosen_term = $t;
                    break;
                }
            }
        }

        $this->maybeAddTermAncestors($chosen_term);
        $this->addCrumb($this->getBreadcrumbTitle('term', $chosen_term->term_id, $chosen_term->name), get_term_link($chosen_term));
    }

    private function maybeAddTermAncestors(WP_Term $term): void
    {
        if (empty($term) || 0 === (int) $term->parent || false === $this->settings['show_ancestors'] || !is_taxonomy_hierarchical($term->taxonomy)) {
            return;
        }

        $ancestors = get_ancestors($term->term_id, $term->taxonomy);
        $ancestors = array_reverse($ancestors);

        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_term($ancestor_id, $term->taxonomy);
            if ($ancestor instanceof WP_Term) {
                $this->addCrumb($this->getBreadcrumbTitle('term', $ancestor->term_id, $ancestor->name), get_term_link($ancestor));
            }
        }
    }

    private function maybeAddPageCrumb(): void
    {
        if (empty($this->settings['show_pagination']) || !is_paged()) {
            return;
        }

        $current_page = get_query_var('paged', 1);
        if ($current_page <= 1) {
            return;
        }

        $this->addCrumb(sprintf(__('Page %s', 'lacadev'), $current_page), '', true);
    }

    private function maybeAddHomeCrumb(): void
    {
        if (!empty($this->settings['home'])) {
            $this->addCrumb($this->strings['home'], $this->strings['home_link']);
        }
    }

    private function maybeAddBlog(): void
    {
        $blog_id = (int) get_option('page_for_posts');
        if (!$blog_id || empty($this->settings['show_blog']) || 'page' !== get_option('show_on_front')) {
            return;
        }

        if (!is_singular('post') && !is_category() && !is_tag()) {
            return;
        }

        $this->addCrumb($this->getBreadcrumbTitle('post', $blog_id, get_the_title($blog_id)), get_permalink($blog_id));
    }

    private function getBreadcrumbTitle(string $object_type, int $object_id, string $default_value): string
    {
        $title = '';
        if ('post' === $object_type) {
            $title = (string) get_post_meta($object_id, '_laca_seo_breadcrumb_title', true);
        } elseif ('term' === $object_type) {
            $title = (string) get_term_meta($object_id, '_laca_seo_breadcrumb_title', true);
        } elseif ('user' === $object_type) {
            $title = (string) get_user_meta($object_id, '_laca_seo_breadcrumb_title', true);
        }

        return !empty($title) ? $title : $default_value;
    }

    private function replacePlaceholder(string $text, string $query): string
    {
        return (string) preg_replace('/%s(?=\s|%|$)/', $query, $text);
    }
}
