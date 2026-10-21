<?php

namespace App\Features\LacaSeo\Frontend\OpenGraph;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

/**
 * Emits Facebook OpenGraph meta tags.
 */
class Facebook
{
    public function output(): void
    {
        $site_name = (string) Settings::get('titles.website_name', get_bloginfo('name'));
        $title = $this->getTitle();
        $desc = $this->getDescription();
        $url = $this->getUrl();
        $image = Image::get();

        echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($this->getType()) . '" />' . "\n";
        if (!empty($title)) {
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        }

        if (!empty($desc)) {
            echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        }

        if (!empty($url)) {
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        }

        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";

        if (is_singular()) {
            $post = get_post();
            if ($post) {
                $pt = get_post_type($post);
                $snippet = Settings::get("titles.pt_{$pt}_default_rich_snippet", false);
                if (in_array($snippet, ['article', 'Article', 'NewsArticle', 'BlogPosting'], true)) {
                    $published = get_the_date('c', $post);
                    $modified  = get_the_modified_date('c', $post);
                    if ($published) {
                        echo '<meta property="article:published_time" content="' . esc_attr($published) . '" />' . "\n";
                    }
                    if ($modified && $modified !== $published) {
                        echo '<meta property="article:modified_time" content="' . esc_attr($modified) . '" />' . "\n";
                    }
                }
            }
        }

        // App ID / Admin ID
        $app_id = (string) Settings::get('titles.facebook_app_id', '');
        if (!empty($app_id)) {
            echo '<meta property="fb:app_id" content="' . esc_attr($app_id) . '" />' . "\n";
        }

        $admin_id = (string) Settings::get('titles.facebook_admin_id', '');
        if (!empty($admin_id)) {
            echo '<meta property="fb:admins" content="' . esc_attr($admin_id) . '" />' . "\n";
        }

        // Image tags
        $imageDetails = Image::getDetails();
        if (!empty($imageDetails['url'])) {
            echo '<meta property="og:image" content="' . esc_url($imageDetails['url']) . '" />' . "\n";
            if (!empty($imageDetails['width'])) {
                echo '<meta property="og:image:width" content="' . esc_attr((string) $imageDetails['width']) . '" />' . "\n";
            }
            if (!empty($imageDetails['height'])) {
                echo '<meta property="og:image:height" content="' . esc_attr((string) $imageDetails['height']) . '" />' . "\n";
            }
            if (!empty($imageDetails['type'])) {
                echo '<meta property="og:image:type" content="' . esc_attr($imageDetails['type']) . '" />' . "\n";
            }
        }
    }

    private function getTitle(): string
    {
        $post = is_singular() ? get_post() : null;
        if ($post) {
            $custom = get_post_meta($post->ID, '_laca_seo_facebook_title', true);
            if (!empty($custom)) {
                $t = Variables::replace($custom, $post);
                return Settings::get('titles.capitalize_titles') === 'on' ? mb_convert_case($t, MB_CASE_TITLE, 'UTF-8') : $t;
            }
        } elseif (is_front_page()) {
            $custom = (string) Settings::get('titles.homepage_facebook_title', '');
            if (!empty($custom)) {
                return Variables::replace($custom);
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $custom = get_term_meta($term->term_id, '_laca_seo_facebook_title', true);
                if (!empty($custom)) {
                    $t = Variables::replace($custom, $term);
                    return Settings::get('titles.capitalize_titles') === 'on' ? mb_convert_case($t, MB_CASE_TITLE, 'UTF-8') : $t;
                }
            }
        }

        return \App\Features\LacaSeo\Frontend\Paper\Paper::get()->get_title();
    }

    private function getDescription(): string
    {
        $post = is_singular() ? get_post() : null;
        if ($post) {
            $custom = get_post_meta($post->ID, '_laca_seo_facebook_description', true);
            if (!empty($custom)) {
                return wp_strip_all_tags(Variables::replace($custom, $post));
            }
            if (has_excerpt($post)) {
                return wp_strip_all_tags(get_the_excerpt($post));
            }
        } elseif (is_front_page()) {
            $custom = (string) Settings::get('titles.homepage_facebook_description', '');
            if (!empty($custom)) {
                return wp_strip_all_tags(Variables::replace($custom));
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $custom = get_term_meta($term->term_id, '_laca_seo_facebook_description', true);
                if (!empty($custom)) {
                    return wp_strip_all_tags(Variables::replace($custom, $term));
                }
                $paperDesc = \App\Features\LacaSeo\Frontend\Paper\Paper::get()->get_description();
                if (!empty($paperDesc)) {
                    return wp_strip_all_tags($paperDesc);
                }
                $td = term_description($term->term_id, $term->taxonomy);
                if (!empty($td)) {
                    return wp_strip_all_tags($td);
                }
            }
        }

        return wp_strip_all_tags(\App\Features\LacaSeo\Frontend\Paper\Paper::get()->get_description());
    }

    private function getUrl(): string
    {
        if (is_404()) {
            return '';
        }

        if (is_search()) {
            return (string) get_search_link();
        }

        $canonical = \App\Features\LacaSeo\Frontend\Paper\Paper::get()->get_canonical();
        if (!empty($canonical)) {
            return $canonical;
        }

        if (is_singular()) {
            return (string) get_permalink();
        }
        if (is_front_page()) {
            return home_url('/');
        }
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            return ($term && !is_wp_error($term)) ? (string) get_term_link($term) : '';
        }
        return home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
    }

    private function getType(): string
    {
        if (is_front_page() || is_home()) {
            return 'website';
        }
        if (is_author()) {
            return 'profile';
        }
        if (function_exists('is_product') && is_product()) {
            return 'product';
        }
        return 'article';
    }
}

