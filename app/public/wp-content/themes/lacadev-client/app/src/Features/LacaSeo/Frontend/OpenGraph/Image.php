<?php

namespace App\Features\LacaSeo\Frontend\OpenGraph;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

/**
 * OpenGraph Image resolver: handles 4-level fallback
 * User Defined (Meta) -> Featured Image -> First Content Image -> Global Fallback
 */
class Image
{
    /**
     * Resolve best image URL for current context.
     */
    public static function get(): ?string
    {
        $details = self::getDetails();
        return $details['url'] ?? null;
    }

    /**
     * Resolve best image details (url, width, height, type) for current context.
     *
     * @return array{url: string, width?: int, height?: int, type?: string}|null
     */
    public static function getDetails(): ?array
    {
        $post = is_singular() ? get_post() : null;

        // Level 1: Per-post / per-term meta
        if ($post) {
            $meta_id = (int) get_post_meta($post->ID, '_laca_seo_facebook_image_id', true);
            if ($meta_id > 0) {
                $img = wp_get_attachment_image_src($meta_id, 'full');
                if ($img) {
                    return [
                        'url'    => $img[0],
                        'width'  => (int) $img[1],
                        'height' => (int) $img[2],
                        'type'   => (string) get_post_mime_type($meta_id) ?: 'image/jpeg',
                    ];
                }
            }

            $meta_img = get_post_meta($post->ID, '_laca_seo_facebook_image', true);
            if (!empty($meta_img)) {
                return ['url' => (string) $meta_img];
            }

            // Level 2: Featured image
            if (has_post_thumbnail($post->ID)) {
                $thumb_id = get_post_thumbnail_id($post->ID);
                $img = wp_get_attachment_image_src($thumb_id, 'full');
                if ($img) {
                    return [
                        'url'    => $img[0],
                        'width'  => (int) $img[1],
                        'height' => (int) $img[2],
                        'type'   => (string) get_post_mime_type($thumb_id) ?: 'image/jpeg',
                    ];
                }
            }

            // Level 3: First image in post content
            $content_img = self::extractFirstImage($post->post_content);
            if (!empty($content_img)) {
                return ['url' => $content_img];
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $meta_id = (int) get_term_meta($term->term_id, '_laca_seo_facebook_image_id', true);
                if ($meta_id > 0) {
                    $img = wp_get_attachment_image_src($meta_id, 'full');
                    if ($img) {
                        return [
                            'url'    => $img[0],
                            'width'  => (int) $img[1],
                            'height' => (int) $img[2],
                            'type'   => (string) get_post_mime_type($meta_id) ?: 'image/jpeg',
                        ];
                    }
                }
                $meta_img = get_term_meta($term->term_id, '_laca_seo_facebook_image', true);
                if (!empty($meta_img)) {
                    return ['url' => (string) $meta_img];
                }
            }
        }

        // Level 4: Global OpenGraph Fallback
        $global_id = (int) Settings::get('titles.open_graph_image_id', 0);
        if ($global_id > 0) {
            $img = wp_get_attachment_image_src($global_id, 'full');
            if ($img) {
                return [
                    'url'    => $img[0],
                    'width'  => (int) $img[1],
                    'height' => (int) $img[2],
                    'type'   => (string) get_post_mime_type($global_id) ?: 'image/jpeg',
                ];
            }
        }

        $global = Settings::get('titles.open_graph_image', '');
        if (is_array($global)) {
            $global = $global['url'] ?? '';
        }

        return !empty($global) ? ['url' => (string) $global] : null;
    }

    /**
     * Extract first <img> src from HTML string.
     */
    public static function extractFirstImage(string $html): ?string
    {
        if (empty($html) || !str_contains($html, '<img')) {
            return null;
        }

        if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
            $src = $matches[1];
            // Ignore tiny base64 or tracking pixels
            if (!str_starts_with($src, 'data:') && !str_ends_with($src, '.svg')) {
                return $src;
            }
        }

        return null;
    }
}
