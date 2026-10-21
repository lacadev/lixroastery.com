<?php

namespace App\Features\LacaSeo\Modules\Llms;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\Sitemap;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * Serves /llms.txt for AI/LLM crawlers.
 */
class LlmsTxt
{
    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRule']);
        add_action('template_redirect', [$this, 'maybeServeLlmsTxt']);
    }

    public function addRewriteRule(): void
    {
        add_rewrite_rule('^llms\\.txt$', 'index.php?laca_llms_txt=1', 'top');
        global $wp;
        $wp->add_query_var('laca_llms_txt');
    }

    public function maybeServeLlmsTxt(): void
    {
        $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);

        if ($path !== '/llms.txt' && (int) get_query_var('laca_llms_txt') !== 1) {
            return;
        }

        // Module can be toggled on/off in Dashboard
        if (!Modules::isActive('llms-txt')) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        status_header(200);

        echo $this->generateContent();
        exit;
    }

    public function generateContent(): string
    {
        $limit = (int) Settings::get('general.llms_limit', 50);
        if ($limit < 1) {
            $limit = 50;
        }

        $post_types = (array) Settings::get('general.llms_post_types', ['post', 'page']);
        $taxonomies = (array) Settings::get('general.llms_taxonomies', ['category']);
        $extra = (string) Settings::get('general.llms_extra_content', '');

        $lines = [];
        $site_name = get_bloginfo('name');
        $site_desc = get_bloginfo('description');

        $lines[] = "# {$site_name}";
        if (!empty($site_desc)) {
            $lines[] = "> {$site_desc}";
        }
        $lines[] = '';

        // 1. Post Types
        foreach ($post_types as $pt) {
            $pt_obj = get_post_type_object($pt);
            if (!$pt_obj) {
                continue;
            }

            $posts = get_posts([
                'post_type'      => $pt,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'no_found_rows'  => true,
            ]);

            if (empty($posts)) {
                continue;
            }

            $lines[] = "## " . ($pt_obj->labels->name ?: $pt);
            foreach ($posts as $post) {
                if (!Sitemap::isObjectIndexable($post->ID, 'post')) {
                    continue;
                }
                $link = get_permalink($post);
                if ($link) {
                    $desc = wp_strip_all_tags(get_the_excerpt($post));
                    $suffix = !empty($desc) ? ": {$desc}" : '';
                    $lines[] = "- [{$post->post_title}]({$link}){$suffix}";
                }
            }
            $lines[] = '';
        }

        // 2. Taxonomies
        foreach ($taxonomies as $tax) {
            $tax_obj = get_taxonomy($tax);
            if (!$tax_obj) {
                continue;
            }

            $terms = get_terms([
                'taxonomy'   => $tax,
                'hide_empty' => true,
                'number'     => $limit,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $lines[] = "## " . ($tax_obj->labels->name ?: $tax);
            foreach ($terms as $term) {
                if (!Sitemap::isObjectIndexable($term->term_id, 'term')) {
                    continue;
                }
                $link = get_term_link($term);
                if (!is_wp_error($link) && !empty($link)) {
                    $lines[] = "- [{$term->name}]({$link})";
                }
            }
            $lines[] = '';
        }

        // 3. Extra Content
        if (!empty(trim($extra))) {
            $lines[] = "## Additional Content";
            $lines[] = trim($extra);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
