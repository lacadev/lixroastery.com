<?php

namespace App\Settings\LacaTools;

/**
 * AITranslationManager Class
 * Orchestrates the entire translation process including SEO meta and UI integration.
 */
class AITranslationManager
{
    private $handler;
    private $parser;

    public function __construct()
    {
        $this->handler = new AITranslationHandler();
        $this->parser  = new AITranslationParser($this->handler);

        // Processing action (full post)
        add_action('admin_post_lacadev_ai_translate', [$this, 'handleAITranslateRequest']);

        // AJAX: translate single block from Gutenberg Editor
        add_action('wp_ajax_lacadev_ai_translate_block', [$this, 'handleAjaxTranslateBlock']);

        // Enqueue AI translate data into Gutenberg editor script
        add_action('enqueue_block_editor_assets', [$this, 'localizeBlockEditorScript']);

        // Admin Notices
        add_action('admin_notices', [$this, 'renderAdminNotices']);
    }

    /**
     * Renders success notices after translation.
     */
    public function renderAdminNotices()
    {
        if (isset($_GET['ai_translated']) && $_GET['ai_translated'] == 1) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>✨ **Tuyệt vời!** Nội dung bài viết và các thẻ SEO đã được dịch tự động bằng AI thành công.</p>
            </div>
            <?php
        }
    }



    /**
     * Handles the AJAX/POST request to translate a post.
     */
    public function handleAITranslateRequest()
    {
        if (!isset($_GET['post']) || !current_user_can('edit_posts')) {
            wp_die('Lỗi quyền truy cập!');
        }

        check_admin_referer('lacadev_ai_translate_nonce');

        $post_id = absint($_GET['post']);
        
        // Detect target language from query or Polylang
        $target_lang = $this->detectTargetLanguage($post_id);
        
        if (is_wp_error($target_lang)) {
            wp_die($target_lang->get_error_message());
        }

        // Execute Translation
        $result = $this->parser->translatePost($post_id, $target_lang);

        if (is_wp_error($result)) {
            wp_die('Lỗi AI: ' . $result->get_error_message());
        }

        // Update Post
        wp_update_post([
            'ID'           => $post_id,
            'post_title'   => $result['post_title'],
            'post_content' => $result['post_content'],
            'post_excerpt' => $result['post_excerpt'],
        ]);

        // Translate SEO Metadata
        $this->translateSEOMeta($post_id, $target_lang);

        // Redirect back with success notice
        wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit&ai_translated=1'));
        exit;
    }

    /**
     * Localizes AI translation data into the existing Gutenberg block editor script.
     * Runs on enqueue_block_editor_assets so the data is available when index.js loads.
     */
    public function localizeBlockEditorScript()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Ensure the handle is registered/enqueued before localizing
        if (!wp_script_is('lacadev-gutenberg-blocks', 'registered') &&
            !wp_script_is('lacadev-gutenberg-blocks', 'enqueued')) {
            wp_enqueue_script('lacadev-gutenberg-blocks');
        }

        wp_localize_script('lacadev-gutenberg-blocks', 'lacaAITranslate', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('lacadev_ai_translate_block_nonce'),
            'langs'   => [
                ['value' => 'auto', 'label' => '🔍 Tự động nhận dạng'],
                ['value' => 'vi',   'label' => '🇻🇳 Tiếng Việt'],
                ['value' => 'en',   'label' => '🇺🇸 English'],
                ['value' => 'ja',   'label' => '🇯🇵 日本語'],
                ['value' => 'ko',   'label' => '🇰🇷 한국어'],
                ['value' => 'fr',   'label' => '🇫🇷 Français'],
                ['value' => 'zh',   'label' => '🇨🇳 中文'],
                ['value' => 'de',   'label' => '🇩🇪 Deutsch'],
                ['value' => 'es',   'label' => '🇪🇸 Español'],
            ],
        ]);
    }

    /**
     * AJAX handler: translate a single Gutenberg block.
     * Called from the per-block toolbar button in the editor.
     */
    public function handleAjaxTranslateBlock()
    {
        check_ajax_referer('lacadev_ai_translate_block_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Không có quyền thực hiện thao tác này.');
        }

        $source_lang = sanitize_text_field($_POST['source_lang'] ?? 'auto');
        $target_lang = sanitize_text_field($_POST['target_lang'] ?? 'en');
        $block_data  = json_decode(stripslashes($_POST['block_data'] ?? '{}'), true);

        if (empty($block_data) || empty($block_data['blockName'])) {
            wp_send_json_error('Dữ liệu block không hợp lệ.');
        }

        $result = $this->parser->translateSingleBlock($block_data, $source_lang, $target_lang);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Translates SEO meta fields for RankMath and Yoast.
     */
    private function translateSEOMeta($post_id, $target_lang)
    {
        $meta_keys = [
            // RankMath
            'rank_math_title',
            'rank_math_description',
            'rank_math_focus_keyword',
            'rank_math_facebook_title',
            'rank_math_facebook_description',
            // Yoast
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
        ];

        foreach ($meta_keys as $key) {
            $val = get_post_meta($post_id, $key, true);
            if (!empty($val)) {
                $translated = $this->handler->translateText($val, $target_lang, "SEO Metadata: $key");
                if (!is_wp_error($translated)) {
                    update_post_meta($post_id, $key, $translated);
                }
            }
        }
    }

    /**
     * Detects what language this post SHOULD be translated to.
     * Logic: If Polylang is present, get the language. 
     * Usually, users create a translation draft first, then they want to translate its content.
     */
    private function detectTargetLanguage($post_id)
    {
        if (function_exists('pll_get_post_language')) {
            $lang = pll_get_post_language($post_id);
            return $lang ?: 'en';
        }

        // Simplified fallback for site default
        return 'en';
    }
}
