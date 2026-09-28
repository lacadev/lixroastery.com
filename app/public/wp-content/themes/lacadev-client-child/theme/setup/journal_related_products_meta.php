<?php
/**
 * Carbon Fields meta box "Sản phẩm liên quan" cho post type "journal" —
 * điều khiển section tự động hiển thị ở cuối single.php (xem
 * app/helpers/journal-related-products-render.php).
 *
 * 2 chế độ:
 *   - Tự động: lấy ngẫu nhiên/mới nhất/... sản phẩm CÙNG BRAND với bài viết
 *     (Brand chọn ở khung "Brands" riêng — taxonomy product_brand, xem
 *     theme/setup/journal_brand_taxonomy.php).
 *   - Thủ công: tìm & chọn tay từng sản phẩm cho đúng bài viết này.
 *
 * @package LacaDevClientChild
 */

use Carbon_Fields\Container;
use Carbon_Fields\Field;

if (!defined('ABSPATH')) {
    exit;
}

add_action('carbon_fields_register_fields', function () {
    Container::make('post_meta', __('Sản phẩm liên quan', 'laca'))
        ->set_context('normal')
        ->set_priority('default')
        ->where('post_type', '=', 'journal')
        ->add_fields([
            Field::make('radio', 'related_products_mode', __('Chế độ', 'laca'))
                ->set_options([
                    'auto' => __('Tự động (theo Brand)', 'laca'),
                    'manual' => __('Thủ công (chọn tay)', 'laca'),
                ])
                ->set_width(33.33)
                ->set_default_value('auto'),

            Field::make('text', 'related_products_count', __('Số lượng sản phẩm', 'laca'))
                ->set_default_value(4)
                ->set_width(33.33)
                ->set_attribute('min', 1)
                ->set_attribute('max', 20)
                ->set_help_text(__('Bài viết cần chọn sẵn 1 Brand ở khung "Brands" để chế độ này có sản phẩm hiển thị.', 'laca'))
                ->set_conditional_logic([
                    ['field' => 'related_products_mode', 'value' => 'auto'],
                ]),

            Field::make('select', 'related_products_order', __('Sắp xếp theo', 'laca'))
                ->set_options([
                    'newest' => __('Mới nhất', 'laca'),
                    'oldest' => __('Cũ nhất', 'laca'),
                    'best_selling' => __('Bán chạy nhất', 'laca'),
                    'random' => __('Ngẫu nhiên', 'laca'),
                    'on_sale' => __('Đang giảm giá', 'laca'),
                    'featured' => __('Nổi bật (Featured)', 'laca'),
                ])
                ->set_width(33.33)
                ->set_default_value('newest')
                ->set_conditional_logic([
                    ['field' => 'related_products_mode', 'value' => 'auto'],
                ]),

            Field::make('association', 'related_products_manual', __('Chọn sản phẩm', 'laca'))
                ->set_types([
                    ['type' => 'post', 'post_type' => 'product'],
                ])
                ->set_max(20)
                ->set_width(60)
                ->set_conditional_logic([
                    ['field' => 'related_products_mode', 'value' => 'manual'],
                ]),
        ]);
});
