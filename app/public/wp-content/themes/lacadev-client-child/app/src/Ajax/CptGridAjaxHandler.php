<?php

/**
 * CPT Grid AJAX Handler
 *
 * Xử lý AJAX cho block "CPT Grid" (block-cpt-grid): lọc theo taxonomy term
 * (tab) + 2 chế độ phân trang — "numbered" (thay thế danh sách) và
 * "load-more" (nối thêm vào cuối, dùng cho infinite scroll).
 * Action: laca_cpt_grid_load. Dùng CHUNG cho MỌI post type (khác với
 * GalleryAjaxHandler/ProjectAjaxHandler — mỗi handler đó chỉ phục vụ 1 CPT
 * cố định), vì block CPT Grid cho phép admin chọn CPT bất kỳ.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class CptGridAjaxHandler
{
    public function __construct()
    {
        add_action('wp_ajax_laca_cpt_grid_load', [$this, 'handle']);
        add_action('wp_ajax_nopriv_laca_cpt_grid_load', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!check_ajax_referer('theme_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
        if (!$post_type || !post_type_exists($post_type)) {
            wp_send_json_error(['message' => 'Invalid post type'], 400);
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
        if ($taxonomy && (!taxonomy_exists($taxonomy) || !is_object_in_taxonomy($post_type, $taxonomy))) {
            $taxonomy = '';
        }

        $term_slug      = isset($_POST['term_slug']) ? sanitize_title(wp_unslash($_POST['term_slug'])) : '';
        $paged          = max(1, (int) ($_POST['paged'] ?? 1));
        $posts_per_page = max(1, min(48, (int) ($_POST['posts_per_page'] ?? 9)));
        $mode           = ($_POST['mode'] ?? 'replace') === 'append' ? 'append' : 'replace';

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'no_found_rows'  => false,
        ];

        if ($taxonomy && $term_slug) {
            // Tab lọc chỉ hiện danh mục cấp 1 (xem render.php) — bấm 1 tab
            // cha phải tự gộp cả bài của danh mục con bên trong, nên khai
            // báo tường minh include_children thay vì phụ thuộc ngầm vào
            // default của WP_Query (mặc định đang là true nhưng có thể đổi).
            $args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
                [
                    'taxonomy'         => $taxonomy,
                    'field'            => 'slug',
                    'terms'            => $term_slug,
                    'include_children' => true,
                ],
            ];
        }

        $query = new WP_Query($args);

        ob_start();
        laca_cpt_grid_render_cards($query, $taxonomy);
        $cards_html = ob_get_clean();

        $max_pages = (int) $query->max_num_pages;

        // Pagination "URL" chỉ để paginate_links() sinh markup — JS luôn
        // preventDefault() rồi tự đọc số trang từ href, không bao giờ điều
        // hướng thật, nên base dùng query var giả (#cgpage-%#%) là đủ.
        $pagination_html = $mode === 'replace'
            ? lacadev_child_pagination_markup([
                'base'    => '#cgpage-%#%',
                'format'  => '',
                'current' => $paged,
                'total'   => $max_pages,
            ])
            : '';

        wp_reset_postdata();

        wp_send_json_success([
            'html'       => $cards_html,
            'pagination' => $pagination_html,
            'max_pages'  => $max_pages,
            'has_more'   => $paged < $max_pages,
        ]);
    }
}

new CptGridAjaxHandler();
