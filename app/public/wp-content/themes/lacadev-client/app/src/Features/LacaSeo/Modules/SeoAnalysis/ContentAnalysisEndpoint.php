<?php

namespace App\Features\LacaSeo\Modules\SeoAnalysis;

use App\Features\LacaSeo\Core\Settings;
use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container\Post_Meta_Container;

defined('ABSPATH') || exit;

/**
 * Endpoint and logic for complete content analysis:
 * - Renders dynamic Gutenberg blocks via do_blocks()
 * - Extracts text from Carbon Fields post_meta containers
 * - Supports caching and fallback toggle
 */
class ContentAnalysisEndpoint
{
    public const ACTION = 'laca_seo_analysis_content';

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handleAjax']);
    }

    /**
     * AJAX handler for rendering full content for SEO analysis.
     */
    public function handleAjax(): void
    {
        check_ajax_referer('laca_seo_admin_nonce', 'nonce');

        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId <= 0 || !current_user_can('edit_post', $postId)) {
            wp_send_json_error(['message' => 'Unauthorized or invalid post_id'], 403);
            return;
        }

        $rawContent = wp_unslash($_POST['content'] ?? '');
        $cfInputs   = isset($_POST['carbon_fields']) && is_array($_POST['carbon_fields']) ? wp_unslash($_POST['carbon_fields']) : [];

        $result = self::renderContentForAnalysis($postId, $rawContent, $cfInputs);

        wp_send_json_success($result);
    }

    /**
     * Core renderer: processes raw content and Carbon Fields into full analysis HTML.
     *
     * @param int $postId
     * @param string $rawContent
     * @param array $cfInputs
     * @return array{html: string, dynamic_blocks_count: int, carbon_fields_count: int, from_cache: bool, warning?: string}
     */
    public static function renderContentForAnalysis(int $postId, string $rawContent, array $cfInputs = []): array
    {
        $enabled = Settings::get('general.analyze_blocks_and_carbon', 'on') === 'on';
        if (!$enabled) {
            return [
                'html'                 => $rawContent,
                'dynamic_blocks_count' => 0,
                'carbon_fields_count'  => 0,
                'from_cache'           => false,
            ];
        }

        // Limit size: if content > 300KB HTML, warn and fallback
        if (strlen($rawContent) > 300 * 1024) {
            return [
                'html'                 => $rawContent,
                'dynamic_blocks_count' => 0,
                'carbon_fields_count'  => 0,
                'from_cache'           => false,
                'warning'              => 'Nội dung vượt quá 300KB, bỏ qua render block động.',
            ];
        }

        // Check cache (transient 10 minutes)
        $cacheKey = 'laca_ca_' . md5($postId . '_' . md5($rawContent) . '_' . serialize($cfInputs));
        $cached = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['html'])) {
            $cached['from_cache'] = true;
            return $cached;
        }

        // 1. Render dynamic blocks using do_blocks
        $parsedBlocks = parse_blocks($rawContent);
        $dynamicBlocksCount = 0;
        foreach ($parsedBlocks as $b) {
            if (!empty($b['blockName'])) {
                // If it is a registered block type
                $blockType = \WP_Block_Type_Registry::get_instance()->get_registered($b['blockName']);
                if ($blockType && $blockType->is_dynamic()) {
                    $dynamicBlocksCount++;
                }
            }
        }

        // Run do_blocks on content (suppress unwanted output buffering errors)
        $renderedBlocksHtml = do_blocks($rawContent);

        // 2. Extract Carbon Fields text
        $carbonHtml = '';
        $carbonFieldsCount = 0;

        $cfData = self::extractCarbonFields($postId, $cfInputs);
        foreach ($cfData as $fieldName => $val) {
            if ($val !== '') {
                $carbonFieldsCount++;
                $carbonHtml .= "\n<div data-laca-source=\"cf:" . esc_attr($fieldName) . "\">" . $val . "</div>";
            }
        }

        $fullHtml = $renderedBlocksHtml . $carbonHtml;

        // Apply external filter
        $fullHtml = apply_filters('laca_seo/analysis/extra_content', $fullHtml, $postId);

        $response = [
            'html'                 => $fullHtml,
            'dynamic_blocks_count' => $dynamicBlocksCount,
            'carbon_fields_count'  => $carbonFieldsCount,
            'from_cache'           => false,
        ];

        set_transient($cacheKey, $response, 10 * MINUTE_IN_SECONDS);

        return $response;
    }

    /**
     * Extract text fields from Carbon Fields post_meta containers.
     *
     * @param int $postId
     * @param array $cfInputs Form inputs sent by client
     * @return array<string, string> Map of field_name => text/html
     */
    public static function extractCarbonFields(int $postId, array $cfInputs = []): array
    {
        $allowedTypes = apply_filters('laca_seo/analysis/carbon_field_types', [
            'text',
            'textarea',
            'rich_text',
            'complex',
        ]);

        $results = [];

        if (!class_exists('Carbon_Fields\Carbon_Fields')) {
            return $results;
        }

        try {
            $repo = Carbon_Fields::resolve('container_repository');
            if (!$repo) {
                return $results;
            }

            foreach ($repo->get_containers() as $container) {
                if (!($container instanceof Post_Meta_Container)) {
                    continue;
                }

                if (!$container->is_valid_attach_for_object($postId)) {
                    continue;
                }

                foreach ($container->get_fields() as $field) {
                    $type = $field->get_type();
                    if (!in_array($type, $allowedTypes, true)) {
                        continue;
                    }

                    $name = $field->get_base_name();

                    if ($type === 'complex') {
                        // Extract complex sub-fields
                        $complexValues = $cfInputs[$name] ?? (function_exists('carbon_get_post_meta') ? carbon_get_post_meta($postId, $name) : []);
                        if (is_array($complexValues)) {
                            $subHtml = self::flattenComplexField($complexValues, $field);
                            if ($subHtml !== '') {
                                $results[$name] = $subHtml;
                            }
                        }
                    } else {
                        // Single text / textarea / rich_text
                        $val = $cfInputs[$name] ?? (function_exists('carbon_get_post_meta') ? (string) carbon_get_post_meta($postId, $name) : '');
                        if (is_string($val) && trim($val) !== '') {
                            $results[$name] = trim($val);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[laca-seo] Carbon fields extraction error: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Recursively extract text from complex sub-fields.
     */
    private static function flattenComplexField(array $items, $complexField): string
    {
        $textParts = [];
        $allowedSubTypes = ['text', 'textarea', 'rich_text'];

        // Get sub field definitions
        $subFieldTypes = [];
        if (method_exists($complexField, 'get_fields')) {
            foreach ($complexField->get_fields() as $subF) {
                $subFieldTypes[$subF->get_base_name()] = $subF->get_type();
            }
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach ($item as $subKey => $subVal) {
                if (isset($subFieldTypes[$subKey]) && !in_array($subFieldTypes[$subKey], $allowedSubTypes, true)) {
                    continue;
                }
                if (is_string($subVal) && trim($subVal) !== '') {
                    $textParts[] = trim($subVal);
                }
            }
        }

        return implode("\n", $textParts);
    }
}
