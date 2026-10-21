<?php

namespace App\Features\LacaSeo\Modules\DatabaseTools;

defined('ABSPATH') || exit;

/**
 * Database tools: Sitemap cache flush, SEO analysis data reset,
 * and block converters (Yoast/AIOSEO FAQ/HowTo -> Laca SEO / Rank Math compatible blocks).
 */
class Tools
{
    /**
     * Clear SEO Analysis results and timestamp.
     */
    public static function clearSeoAnalysis(): bool
    {
        delete_option('laca_seo_analysis_results');
        delete_option('laca_seo_analysis_date');
        delete_option('rank_math_seo_analysis_results');
        delete_option('rank_math_seo_analysis_date');
        return true;
    }

    /**
     * Clear all sitemap transients and cache.
     */
    public static function clearSitemapCache(): bool
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%laca_seo_sitemap_%' OR option_name LIKE '%_transient_rank_math_sitemap_%'");
        return true;
    }

    /**
     * Convert Yoast FAQ Block parsed structure to Laca / Rank Math FAQ Block format.
     *
     * @param array $block Parsed Gutenberg block array ['blockName' => 'yoast/faq-block', 'attrs' => [...]]
     * @return array
     */
    public static function convertYoastFaqBlock(array $block): array
    {
        $questions = [];
        if (!empty($block['attrs']['questions']) && is_array($block['attrs']['questions'])) {
            foreach ($block['attrs']['questions'] as $q) {
                $questions[] = [
                    'id'      => 'faq-question-' . uniqid(),
                    'visible' => true,
                    'title'   => $q['jsonQuestion'] ?? '',
                    'content' => $q['jsonAnswer'] ?? '',
                ];
            }
        }

        $newBlock = [
            'blockName' => 'rank-math/faq-block',
            'attrs'     => [
                'listStyle'         => '',
                'textAlign'         => 'left',
                'titleWrapper'      => 'h3',
                'listCssClasses'    => '',
                'titleCssClasses'   => '',
                'contentCssClasses' => '',
                'questions'         => $questions,
                'className'         => $block['attrs']['className'] ?? '',
            ],
            'innerBlocks'    => [],
            'innerHTML'      => '',
            'innerContent'   => [],
        ];

        // Format HTML
        $out = ['<div class="wp-block-rank-math-faq-block">'];
        foreach ($questions as $question) {
            if (empty($question['title']) || empty($question['content'])) {
                continue;
            }
            $out[] = '<div class="rank-math-faq-item">';
            $out[] = sprintf('<h3 class="rank-math-question">%s</h3>', esc_html($question['title']));
            $out[] = sprintf('<div class="rank-math-answer">%s</div>', wp_kses_post($question['content']));
            $out[] = '</div>';
        }
        $out[] = '</div>';

        $html = implode('', $out);
        $newBlock['innerHTML'] = $html;
        $newBlock['innerContent'][] = $html;

        return $newBlock;
    }

    /**
     * Replace Yoast FAQ blocks in post content with converted Rank Math / Laca FAQ blocks.
     */
    public static function replaceYoastFaqInContent(string $content): string
    {
        preg_match_all('/<!-- wp:yoast\/faq-block\s*(\{.*?\})?\s*-->.*?<!-- \/wp:yoast\/faq-block -->/is', $content, $matches);

        if (empty($matches[0])) {
            return $content;
        }

        foreach ($matches[0] as $match) {
            $parsed = parse_blocks($match);
            if (!empty($parsed[0])) {
                $converted = self::convertYoastFaqBlock($parsed[0]);
                $rendered = render_block($converted);
                $content = str_replace($match, $rendered, $content);
            }
        }

        return $content;
    }
}
