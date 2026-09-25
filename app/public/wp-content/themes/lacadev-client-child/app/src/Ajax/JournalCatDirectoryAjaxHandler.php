<?php

/**
 * Journal Category Directory AJAX Handler
 *
 * Xử lý AJAX khi bấm đổi tab danh mục CẤP 1 tại /journal — trả về lại
 * tiêu đề + mô tả + lưới danh mục con của danh mục cha vừa chọn (KHÔNG
 * phải bài viết — xem taxonomy-journal-cat.php cho phần lọc bài viết thật
 * theo danh mục con qua CptGridAjaxHandler).
 * Action: laca_journal_cat_directory_load.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class JournalCatDirectoryAjaxHandler
{
    public function __construct()
    {
        add_action('wp_ajax_laca_journal_cat_directory_load', [$this, 'handle']);
        add_action('wp_ajax_nopriv_laca_journal_cat_directory_load', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!check_ajax_referer('theme_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $taxonomy  = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : 'journal-cat';
        $term_slug = isset($_POST['term_slug']) ? sanitize_title(wp_unslash($_POST['term_slug'])) : '';

        if (!$taxonomy || !taxonomy_exists($taxonomy) || !$term_slug) {
            wp_send_json_error(['message' => 'Invalid params'], 400);
        }

        $term = get_term_by('slug', $term_slug, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(['message' => 'Term not found'], 404);
        }

        ob_start();
        laca_journal_render_category_directory($term);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }
}

new JournalCatDirectoryAjaxHandler();
