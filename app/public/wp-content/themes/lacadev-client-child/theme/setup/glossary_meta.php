<?php
/**
 * Carbon Fields custom fields cho post type "glossary".
 *
 * @package LacaDevClientChild
 */

use Carbon_Fields\Container;
use Carbon_Fields\Field;

if (!defined('ABSPATH')) {
    exit;
}

add_action('carbon_fields_register_fields', function () {
    // Không check post_type_exists('glossary') ở đây — Carbon Fields tự
    // kích hoạt 'carbon_fields_register_fields' trên hook 'init' priority 0,
    // TRƯỚC khi Dynamic CPT đăng ký post type (init priority sau đó). Xem
    // bug tương tự đã gặp ở product_meta.php.
    // LƯU Ý: KHÔNG dùng set_context('carbon_fields_after_title') — context
    // này dựa vào hook 'edit_form_after_title', hook đó CHỈ chạy ở Classic
    // Editor (wp-admin/edit-form-advanced.php), Block Editor
    // (wp-admin/edit-form-blocks.php) không bao giờ gọi nó nên field sẽ ẩn
    // hoàn toàn. Product không bị ảnh hưởng vì product dùng Classic Editor,
    // còn glossary dùng Block Editor. Dùng 'normal' (meta box thật của WP,
    // hoạt động ở cả 2 editor) thay thế.
    Container::make('post_meta', __('Bài viết liên quan', 'laca'))
        ->set_context('normal')
        ->set_priority('high')
        ->where('post_type', 'IN', ['glossary'])
        ->add_fields([
            Field::make('html', 'related_articles_intro', '')
                ->set_html('<p style="font-size:13px;color:#666;margin:0 0 12px;">Chọn các bài viết liên quan — có thể chọn từ NHIỀU loại nội dung khác nhau (Glossary, Journal, sản phẩm...), không giới hạn 1 loại.</p>'),

            Field::make('association', 'related_articles', __('Related Articles', 'laca'))
                ->set_types(laca_glossary_related_article_types()),
        ]);
});

/**
 * Danh sách post type cho phép chọn ở field "related_articles" — tự động
 * lấy TẤT CẢ Dynamic CPT hiện có (kể cả tạo sau này) + 'post'/'product',
 * không hardcode tay từng loại để khỏi phải sửa code mỗi khi tạo thêm
 * Dynamic CPT mới.
 *
 * KHÔNG dùng get_post_types() ở đây — Carbon Fields kích hoạt
 * 'carbon_fields_register_fields' trên hook 'init' priority 0, TRƯỚC khi
 * Dynamic CPT (init priority 5) và WooCommerce (init priority 5) đăng ký
 * post type của chúng, nên get_post_types() lúc này sẽ trả về danh sách
 * thiếu (y hệt bug post_type_exists() đã gặp ở product_meta.php). Đọc
 * thẳng option 'laca_dynamic_cpts' (dữ liệu thô, không phụ thuộc thời điểm
 * init) để tránh phụ thuộc thứ tự đăng ký.
 *
 * @return array
 */
function laca_glossary_related_article_types(): array
{
    $slugs = ['post'];

    if (class_exists('WooCommerce')) {
        $slugs[] = 'product';
    }

    // Option được lưu dạng JSON string (không phải mảng PHP) — phải
    // json_decode() trước khi duyệt.
    $dynamic_cpts = get_option('laca_dynamic_cpts', []);
    if (is_string($dynamic_cpts)) {
        $dynamic_cpts = json_decode($dynamic_cpts, true) ?: [];
    }
    if (is_array($dynamic_cpts)) {
        foreach ($dynamic_cpts as $cpt) {
            if (!empty($cpt['slug'])) {
                $slugs[] = (string) $cpt['slug'];
            }
        }
    }

    $types = [];
    foreach (array_unique($slugs) as $slug) {
        $types[] = [
            'type'      => 'post',
            'post_type' => $slug,
        ];
    }

    return $types;
}
