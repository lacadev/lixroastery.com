<?php

namespace App\Features\LacaSeo\Frontend\OpenGraph;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

/**
 * Emits Twitter Card meta tags.
 */
class Twitter
{
    public function output(): void
    {
        $card = '';
        if (is_singular()) {
            $card = (string) get_post_meta((int) get_the_ID(), '_laca_seo_twitter_card_type', true);
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $card = (string) get_term_meta((int) $term->term_id, '_laca_seo_twitter_card_type', true);
            }
        }

        if (empty($card)) {
            $card = (string) Settings::get('titles.twitter_card_type', 'summary_large_image');
        }

        // Map summary_card to standard Twitter card value 'summary'
        if ($card === 'summary_card') {
            $card = 'summary';
        }

        $creator = (string) Settings::get('titles.twitter_author_names', '');

        $title = $this->getTitle();
        $desc = $this->getDescription();
        $image = Image::get();

        echo '<meta name="twitter:card" content="' . esc_attr($card) . '" />' . "\n";
        if (!empty($title)) {
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        }

        if (!empty($desc)) {
            echo '<meta name="twitter:description" content="' . esc_attr($desc) . '" />' . "\n";
        }

        if (!empty($image)) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }

        if (!empty($creator)) {
            $creator = '@' . ltrim($creator, '@');
            echo '<meta name="twitter:creator" content="' . esc_attr($creator) . '" />' . "\n";
            echo '<meta name="twitter:site" content="' . esc_attr($creator) . '" />' . "\n";
        }
    }

    private function getTitle(): string
    {
        $post = is_singular() ? get_post() : null;
        if ($post) {
            $useFb = get_post_meta($post->ID, '_laca_seo_twitter_use_facebook', true);
            if ($useFb === 'on' || $useFb === '') {
                $custom = get_post_meta($post->ID, '_laca_seo_facebook_title', true)
                    ?: get_post_meta($post->ID, '_laca_seo_twitter_title', true);
            } else {
                $custom = get_post_meta($post->ID, '_laca_seo_twitter_title', true);
            }
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
                $useFb = get_term_meta($term->term_id, '_laca_seo_twitter_use_facebook', true);
                if ($useFb === 'on' || $useFb === '') {
                    $custom = get_term_meta($term->term_id, '_laca_seo_facebook_title', true)
                        ?: get_term_meta($term->term_id, '_laca_seo_twitter_title', true);
                } else {
                    $custom = get_term_meta($term->term_id, '_laca_seo_twitter_title', true);
                }
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
            $useFb = get_post_meta($post->ID, '_laca_seo_twitter_use_facebook', true);
            if ($useFb === 'on' || $useFb === '') {
                $custom = get_post_meta($post->ID, '_laca_seo_facebook_description', true)
                    ?: get_post_meta($post->ID, '_laca_seo_twitter_description', true);
            } else {
                $custom = get_post_meta($post->ID, '_laca_seo_twitter_description', true);
            }
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
                $useFb = get_term_meta($term->term_id, '_laca_seo_twitter_use_facebook', true);
                if ($useFb === 'on' || $useFb === '') {
                    $custom = get_term_meta($term->term_id, '_laca_seo_facebook_description', true)
                        ?: get_term_meta($term->term_id, '_laca_seo_twitter_description', true);
                } else {
                    $custom = get_term_meta($term->term_id, '_laca_seo_twitter_description', true);
                }
                if (!empty($custom)) {
                    return wp_strip_all_tags(Variables::replace($custom, $term));
                }
                $td = term_description($term->term_id, $term->taxonomy);
                if (!empty($td)) {
                    return wp_strip_all_tags($td);
                }
            }
        }

        return wp_strip_all_tags(\App\Features\LacaSeo\Frontend\Paper\Paper::get()->get_description());
    }
}
