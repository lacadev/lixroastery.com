<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

class Singular implements IPaper
{
    protected ?\WP_Post $object = null;

    public function set_object(?\WP_Post $post): void
    {
        $this->object = $post;
    }

    protected function getPost(): ?\WP_Post
    {
        if ($this->object instanceof \WP_Post) {
            return $this->object;
        }
        $post = get_post();
        return ($post instanceof \WP_Post) ? $post : null;
    }

    public function title(): string
    {
        $post = $this->getPost();
        if (!$post) {
            return Paper::get_from_options('404_title', [], 'Page not found');
        }

        $meta = (string) get_post_meta($post->ID, '_laca_seo_title', true);
        if ($meta !== '') {
            return Variables::replace($meta, $post);
        }

        $pt = $post->post_type;
        return Paper::get_from_options("pt_{$pt}_title", $post, '%title% %sep% %sitename%');
    }

    public function description(): string
    {
        $post = $this->getPost();
        if (!$post) {
            return '';
        }

        // 1. Per-post meta
        $meta = (string) get_post_meta($post->ID, '_laca_seo_description', true);
        if ($meta !== '') {
            return Variables::replace($meta, $post);
        }

        // 2. Excerpt
        if (!empty($post->post_excerpt) && !post_password_required($post->ID)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        // 3. Option template
        $pt = $post->post_type;
        $desc = Paper::get_from_options("pt_{$pt}_description", $post, '');
        return $desc;
    }

    public function robots(): array
    {
        $post = $this->getPost();
        if (!$post) {
            return [];
        }

        $robots = Paper::robots_combine(get_post_meta($post->ID, '_laca_seo_robots', true));
        $pt = $post->post_type;

        if (empty($robots) && Settings::get("titles.pt_{$pt}_custom_robots", 'off') === 'on') {
            $robots = Paper::robots_combine((array) Settings::get("titles.pt_{$pt}_robots", []), true);
        }

        $isPrivate = ('private' === $post->post_status);
        $isSubpage = is_paged() && (Settings::get('titles.noindex_paginated_pages', 'off') === 'on');
        $isPass = !empty($post->post_password) && (Settings::get('titles.noindex_password_protected', 'off') === 'on');

        if ($isPrivate || $isSubpage || $isPass) {
            $robots['index'] = 'noindex';
        }

        return $robots;
    }

    public function advanced_robots(): array
    {
        $post = $this->getPost();
        if (!$post) {
            return [];
        }

        $adv = Paper::advanced_robots_combine(get_post_meta($post->ID, '_laca_seo_advanced_robots', true));
        $pt = $post->post_type;

        if (empty($adv) && Settings::get("titles.pt_{$pt}_custom_robots", 'off') === 'on') {
            $adv = Paper::advanced_robots_combine((array) Settings::get("titles.pt_{$pt}_advanced_robots", []));
        }

        return $adv;
    }

    public function canonical(): array
    {
        $post = $this->getPost();
        $postId = $post ? $post->ID : get_the_ID();
        $canonical = get_permalink($postId);
        $override = $post ? (string) get_post_meta($post->ID, '_laca_seo_canonical_url', true) : '';

        // Paginated single post
        $page = (int) get_query_var('page');
        if (is_singular() && $page > 1 && $post && !empty($post->post_content)) {
            $numPages = substr_count($post->post_content, '<!--nextpage-->') + 1;
            if ($numPages >= $page) {
                global $wp_rewrite;
                if (!$wp_rewrite || !$wp_rewrite->using_permalinks()) {
                    $canonical = add_query_arg('page', $page, $canonical);
                } else {
                    $canonical = user_trailingslashit(trailingslashit($canonical) . $page);
                }
            }
        }

        return [
            'canonical'          => $canonical,
            'canonical_unpaged'  => get_permalink($postId),
            'canonical_override' => $override,
        ];
    }

    public function keywords(): string
    {
        $post = $this->getPost();
        return $post ? (string) get_post_meta($post->ID, '_laca_seo_focus_keyword', true) : '';
    }
}
