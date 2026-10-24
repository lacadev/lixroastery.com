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
            'origin' => [
                'label' => __('Origin', 'laca'),
                'type'  => 'meta',
            ],
            'region' => [
                'label' => __('Region', 'laca'),
                'type'  => 'meta',
            ],
            'farm' => [
                'label' => __('Farm', 'laca'),
                'type'  => 'meta',
            ],
            'variety' => [
                'label' => __('Variety', 'laca'),
                'type'  => 'meta',
            ],
            'crop_year' => [
                'label' => __('Crop year', 'laca'),
                'type'  => 'meta',
            ],
            'altitude' => [
                'label' => __('Altitude', 'laca'),
                'type'  => 'meta',
            ],
            'process' => [
                'label' => __('Process', 'laca'),
                'type'  => 'meta',
            ],
            'net_weight' => [
                'label' => __('Net weight', 'laca'),
                'type'  => 'meta',
            ],
            'flavor_profile' => [
                'label' => __('Flavor profile', 'laca'),
                'type'  => 'meta',
            ],
            'sourcing' => [
                'label' => __('Sourcing', 'laca'),
                'type'  => 'meta',
            ],
            'paid_for_producer' => [
                'label' => __('Paid producer', 'laca'),
                'type'  => 'meta',
            ],
            'paid_for_exporter' => [
                'label' => __('Paid exporter', 'laca'),
                'type'  => 'meta',
            ],
            'flavor_aroma' => [
                'label' => __('Aroma', 'laca'),
                'type'  => 'meta',
            ],
            'flavor_hot' => [
                'label' => __('Hot', 'laca'),
                'type'  => 'meta',
            ],
            'flavor_warm' => [
                'label' => __('Warm', 'laca'),
                'type'  => 'meta',
            ],
            'flavor_cool' => [
                'label' => __('Cool', 'laca'),
                'type'  => 'meta',
            ],
            'flavor_aftertaste' => [
                'label' => __('Aftertaste', 'laca'),
                'type'  => 'meta',
            ],
            'flavor_acidity' => [
                'label' => __('Acidity', 'laca'),
                'type'  => 'meta',
            ],
        ];

        // Giữ vị trí sau stock_status và product_cat nếu có
        $merged = [];
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
            'journal_hide_toc' => [
                'label'   => __('Mục lục', 'laca'),
                'type'    => 'select',
                'options' => [
                    ''    => __('Hiện (Mặc định)', 'laca'),
                    'no'  => __('Hiện', 'laca'),
                    'yes' => __('Ẩn mục lục', 'laca'),
                ],
            ],
            'related_products_mode' => [
                'label'   => __('Chế độ SP liên quan', 'laca'),
                'type'    => 'select',
                'options' => [
                    'auto'   => __('Tự động (theo Brand)', 'laca'),
                    'manual' => __('Thủ công (chọn tay)', 'laca'),
                ],
            ],
            'related_products_count' => [
                'label' => __('Số SP liên quan', 'laca'),
                'type'  => 'meta',
            ],
            'related_products_order' => [
                'label'   => __('Sắp xếp SP', 'laca'),
                'type'    => 'select',
                'options' => [
                    'newest'       => __('Mới nhất', 'laca'),
                    'oldest'       => __('Cũ nhất', 'laca'),
                    'best_selling' => __('Bán chạy nhất', 'laca'),
                    'random'       => __('Ngẫu nhiên', 'laca'),
                    'on_sale'      => __('Đang giảm giá', 'laca'),
                    'featured'     => __('Nổi bật', 'laca'),
                ],
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
