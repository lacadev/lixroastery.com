<?php
/**
 * Gắn taxonomy "Brands" có sẵn của WooCommerce (product_brand — bật mặc định
 * từ WC 9.6, không cần cài thêm plugin) cho post type "journal" (Dynamic CPT).
 *
 * Mục đích: khi viết bài Journal, admin chọn 1 Brand giống như chọn Category
 * cho post thường — sau đó block Product Grid (autoQuery="same_brand") tự
 * lấy đúng Brand này của bài đang xem để hiện ngẫu nhiên sản phẩm cùng brand,
 * không cần thêm field/CSDL riêng nào.
 *
 * Priority 20 (sau init mặc định của WooCommerce/DynamicCPT đều chạy ở
 * priority 5) để chắc chắn cả taxonomy "product_brand" lẫn post type
 * "journal" đã tồn tại trước khi gắn.
 *
 * @hook init
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!taxonomy_exists('product_brand') || !post_type_exists('journal')) {
        return;
    }
    register_taxonomy_for_object_type('product_brand', 'journal');
}, 20);
