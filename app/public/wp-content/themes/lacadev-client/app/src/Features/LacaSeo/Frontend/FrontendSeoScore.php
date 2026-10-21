<?php

namespace App\Features\LacaSeo\Frontend;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Frontend SEO Score badge output.
 * Fully compatible with Rank Math class-frontend-seo-score.php
 * Handles shortcode [laca_seo_score] and the_content auto-insertion.
 */
class FrontendSeoScore
{
    /**
     * Register hooks.
     */
    public function register(): void
    {
        // 1. Shortcode [laca_seo_score]
        add_shortcode('laca_seo_score', [$this, 'renderShortcode']);
        // Also support rank_math_rich_snippet or rank_math_seo_score if RM is inactive
        if (!shortcode_exists('rank_math_seo_score')) {
            add_shortcode('rank_math_seo_score', [$this, 'renderShortcode']);
        }

        // 2. the_content filter for auto position
        add_filter('the_content', [$this, 'filterContent'], 20);
    }

    /**
     * Auto insert SEO score badge into the_content if enabled.
     */
    public function filterContent(string $content): string
    {
        if (!is_singular() || is_feed() || is_admin()) {
            return $content;
        }

        $enabled = (string) Settings::get('general.frontend_seo_score', 'off');
        if ($enabled !== 'on') {
            return $content;
        }

        $post = get_post();
        if (!$post) {
            return $content;
        }

        // Check if author opted out for this post
        $dontShow = get_post_meta($post->ID, '_laca_seo_dont_show_seo_score', true);
        if ($dontShow === 'on' || $dontShow === 'yes' || $dontShow === '1') {
            return $content;
        }

        // Check post type whitelist
        $postTypes = (array) Settings::get('general.frontend_seo_score_post_types', ['post']);
        if (!in_array($post->post_type, $postTypes, true)) {
            return $content;
        }

        $position = (string) Settings::get('general.frontend_seo_score_position', 'bottom');
        if ($position === 'custom') {
            return $content;
        }

        $badge = $this->renderBadge($post->ID);
        if ($badge === '') {
            return $content;
        }

        return match ($position) {
            'top'    => $badge . $content,
            'both'   => $badge . $content . $badge,
            default  => $content . $badge, // bottom
        };
    }

    /**
     * Shortcode handler [laca_seo_score id="" template=""].
     *
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode($atts = []): string
    {
        $post = get_post();
        $defaultId = $post ? $post->ID : 0;

        $atts = shortcode_atts([
            'id'       => $defaultId,
            'template' => '',
            'post_id'  => '',
        ], (array) $atts, 'laca_seo_score');

        $postId = !empty($atts['post_id']) ? (int) $atts['post_id'] : (int) $atts['id'];
        if ($postId <= 0) {
            return '';
        }

        $dontShow = get_post_meta($postId, '_laca_seo_dont_show_seo_score', true);
        if ($dontShow === 'on' || $dontShow === 'yes' || $dontShow === '1') {
            return '';
        }

        $template = !empty($atts['template']) ? (string) $atts['template'] : '';
        return $this->renderBadge($postId, $template);
    }

    /**
     * Render the HTML badge for a given post.
     */
    public function renderBadge(int $postId, string $template = ''): string
    {
        $score = (int) get_post_meta($postId, '_laca_seo_seo_score', true);
        if ($score <= 0) {
            return '';
        }

        if ($template === '') {
            $template = (string) Settings::get('general.frontend_seo_score_template', 'circle');
        }

        $classRating = match (true) {
            $score >= 80 => 'good',
            $score >= 50 => 'ok',
            default      => 'bad',
        };

        $color = match (true) {
            $score >= 80 => '#10b981',
            $score >= 50 => '#f59e0b',
            default      => '#ef4444',
        };

        if ($template === 'square') {
            return sprintf(
                '<div class="laca-seo-score-badge laca-seo-score--square laca-seo-score--%s" style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border:1px solid %s;border-radius:6px;font-family:system-ui,-apple-system,sans-serif;font-size:14px;margin:12px 0;">' .
                '<span class="laca-seo-score-label" style="font-weight:600;color:#374151;">%s</span>' .
                '<span class="laca-seo-score-val" style="font-weight:700;color:%s;background:%s15;padding:2px 8px;border-radius:4px;">%d/100</span>' .
                '</div>',
                esc_attr($classRating),
                esc_attr($color),
                esc_html__('Điểm SEO:', 'lacadev'),
                esc_attr($color),
                esc_attr($color),
                $score
            );
        }

        // Circle template (default)
        return sprintf(
            '<div class="laca-seo-score-badge laca-seo-score--circle laca-seo-score--%s" style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:50%%;border:3px solid %s;color:%s;font-weight:700;font-size:15px;font-family:system-ui,-apple-system,sans-serif;margin:12px 0;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.08);" title="%s">' .
            '<span>%d</span>' .
            '</div>',
            esc_attr($classRating),
            esc_attr($color),
            esc_attr($color),
            esc_attr(sprintf(__('Điểm SEO: %d/100', 'lacadev'), $score)),
            $score
        );
    }
}
