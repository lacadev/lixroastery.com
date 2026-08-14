<?php
/**
 * Custom taxonomy cho post type "product" (WooCommerce).
 * Tham khảo từ xliiicoffee (site cà phê khác) — Phân loại Giống (variety_cat).
 *
 * @link    https://developer.wordpress.org/reference/functions/register_taxonomy/
 * @hook    init
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    register_taxonomy(
        'variety_cat',
        ['product'],
        [
            'labels' => [
                'name'              => __('Phân loại Giống', 'laca'),
                'singular_name'     => __('Phân loại Giống', 'laca'),
                'search_items'      => __('Tìm kiếm Danh mục', 'laca'),
                'all_items'         => __('Tất cả Danh mục', 'laca'),
                'parent_item'       => __('Danh mục cha', 'laca'),
                'parent_item_colon' => __('Danh mục cha:', 'laca'),
                'view_item'         => __('Hiển thị Danh mục', 'laca'),
                'edit_item'         => __('Chỉnh sửa Danh mục', 'laca'),
                'update_item'       => __('Cập nhật Danh mục', 'laca'),
                'add_new_item'      => __('Thêm mới Danh mục', 'laca'),
                'new_item_name'     => __('Tên mới Danh mục', 'laca'),
                'menu_name'         => __('Phân loại Giống', 'laca'),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'variety-cat'],
        ]
    );
});
