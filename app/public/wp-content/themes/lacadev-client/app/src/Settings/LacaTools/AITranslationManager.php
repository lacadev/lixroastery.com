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

        // Nút "Dịch cả bài bằng AI" trong khung Publish — trước đây action
        // này được đăng ký nhưng KHÔNG có nút/link nào ở bất kỳ đâu gọi tới,
        // tính năng chết hoàn toàn dù code xử lý đã viết xong.
        add_action('post_submitbox_misc_actions', [$this, 'renderTranslateWholePostButton']);

        // AJAX: translate single block from Gutenberg Editor
        add_action('wp_ajax_lacadev_ai_translate_block', [$this, 'handleAjaxTranslateBlock']);

        // Enqueue AI translate data into Gutenberg editor script — priority
        // 20 để CHẮC CHẮN chạy sau app_action_editor_enqueue_assets() (mục
        // app/hooks.php, priority mặc định 10) — nơi đó mới thật sự
        // wp_register_script('theme-editor-js-bundle', ...), còn hàm này chỉ
        // wp_localize_script() lên handle đó. Thứ tự add_action() giữa 2
        // file không đảm bảo chạy trước/sau nhau, nên phải ghim priority rõ
        // ràng thay vì dựa vào thứ tự include tình cờ.
        add_action('enqueue_block_editor_assets', [$this, 'localizeBlockEditorScript'], 20);

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
     * In nút "Dịch cả bài bằng AI" trong khung Publish của màn hình edit
     * post — link thật kèm nonce, trước đây hoàn toàn không tồn tại.
     */
    public function renderTranslateWholePostButton()
    {
        global $post;
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=lacadev_ai_translate&post=' . $post->ID),
            'lacadev_ai_translate_nonce'
        );
        ?>
        <div class="misc-pub-section">
            <a href="<?php echo esc_url($url); ?>"
                class="button button-secondary"
                style="width:100%;text-align:center;box-sizing:border-box;"
                onclick="return confirm('<?php echo esc_js(__('Dịch toàn bộ tiêu đề/nội dung/SEO của bài viết này bằng AI? Nội dung hiện tại sẽ bị GHI ĐÈ.', 'laca')); ?>');">
                ✨ <?php esc_html_e('Dịch cả bài bằng AI', 'laca'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Handles the AJAX/POST request to translate a post.
     */
    public function handleAITranslateRequest()
    {
        $post_id = absint($_GET['post'] ?? 0);
        // "edit_post" (kiểm tra ĐÚNG bài viết này) thay vì "edit_posts"
        // (quyền chung chung) — bug thật đã gặp: 1 Author chỉ được sửa bài
        // của chính mình vẫn gọi được action này với ?post=<id bất kỳ> để
        // ghi đè nội dung bài viết của NGƯỜI KHÁC, vì "edit_posts" chỉ kiểm
        // tra "có được sửa bài NÀO ĐÓ không", không kiểm tra bài CỤ THỂ này.
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Lỗi quyền truy cập!');
        }

        check_admin_referer('lacadev_ai_translate_nonce');
        
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
     * Localizes AI translation data vào 'theme-editor-js-bundle' — bundle
     * CHUNG luôn được enqueue trên mọi màn hình block editor (xem
     * app_action_editor_enqueue_assets() trong app/hooks.php), khác với
     * 'lacadev-gutenberg-blocks' (bundle LEGACY chỉ còn dùng cho vài block
     * cũ chưa có build riêng) — dùng handle chung này để panel "Dịch bằng
     * AI" (resources/scripts/editor/index.js) CHẮC CHẮN có mặt bất kể block
     * nào đang được soạn thảo.
     */
    public function localizeBlockEditorScript()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Ngôn ngữ dịch ĐẾN lấy thật từ Polylang (bỏ ngôn ngữ mặc định của
        // site ra khỏi danh sách đích — dịch sang chính nó vô nghĩa). Site
        // chỉ có 1 ngôn ngữ (hoặc tắt Polylang) → mảng rỗng, JS tự ẩn toàn
        // bộ panel dịch.
        $languages = \App\Helpers\PolylangLanguages::getActive();
        $targetLangs = array_values(array_filter($languages, static fn($l) => empty($l['is_default'])));

        wp_localize_script('theme-editor-js-bundle', 'lacaAITranslate', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('lacadev_ai_translate_block_nonce'),
            'langs'   => array_map(
                static fn($l) => ['value' => $l['slug'], 'label' => $l['name']],
                $targetLangs
            ),
            // Chỉ các block CUSTOM có ít nhất 1 attribute "translatable": true
            // trong block.json — core text block (paragraph/heading...) đã
            // dịch tốt qua nút "Dịch cả bài bằng AI" (xử lý cả innerHTML),
            // panel theo từng block này chỉ cập nhật attributes nên cố ý
            // không bật cho core block.
            'translatableBlocks' => function_exists('lacadev_get_translatable_block_attrs')
                ? array_keys(lacadev_get_translatable_block_attrs())
                : [],
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
