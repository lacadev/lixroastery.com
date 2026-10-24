<?php
/**
 * Custom fields registration for Admin List Table Inline Editor (LIX Roastery).
 *
 * Cung cấp cấu hình các trường tùy biến đặc thù của site (cà phê đặc sản, journal, page)
 * thông qua filter 'laca/list_inline/fields'.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('laca/list_inline/fields', function (array $fields, string $post_type): array {
    if ($post_type === 'product') {
        $lix_product_fields = [
            'subname' => [
                'label' => __('Tên phụ', 'laca'),
                'type'  => 'meta',
            ],
            'variety_cat' => [
                'label'    => __('Phân loại giống', 'laca'),
                'type'     => 'taxonomy',
                'taxonomy' => 'variety_cat',
            ],
        ];

        // Đặt Tên phụ ngay sau Tên sản phẩm, rồi đến Hàng tồn kho và Danh mục
        $merged = [];
        if (isset($lix_product_fields['subname'])) {
            $merged['subname'] = $lix_product_fields['subname'];
            unset($lix_product_fields['subname']);
        }
        if (isset($fields['stock_status'])) {
            $merged['stock_status'] = $fields['stock_status'];
            unset($fields['stock_status']);
        }
        if (isset($fields['product_cat'])) {
            $merged['product_cat'] = $fields['product_cat'];
            unset($fields['product_cat']);
        }

        $fields = array_merge($merged, $lix_product_fields, $fields);
    } elseif ($post_type === 'journal') {
        $lix_journal_fields = [
            'product_brand' => [
                'label'    => __('Brand liên kết', 'laca'),
                'type'     => 'taxonomy',
                'taxonomy' => 'product_brand',
            ],
        ];

        $fields = array_merge($fields, $lix_journal_fields);
    } elseif ($post_type === 'page') {
        $lix_page_fields = [
            'hide_breadcrumb' => [
                'label'   => __('Ẩn breadcrumb', 'laca'),
                'type'    => 'select',
                'options' => [
                    ''    => __('Hiện (Mặc định)', 'laca'),
                    'no'  => __('Hiện', 'laca'),
                    'yes' => __('Ẩn breadcrumb', 'laca'),
                ],
            ],
        ];

        $fields = array_merge($fields, $lix_page_fields);
    }

    return $fields;
}, 10, 2);
