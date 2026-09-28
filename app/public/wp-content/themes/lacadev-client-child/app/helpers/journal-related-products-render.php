<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Sản phẩm liên quan" tự động render cuối mỗi bài Journal (gọi từ
 * theme/single.php) — điều khiển qua meta box "Sản phẩm liên quan"
 * (theme/setup/journal_related_products_meta.php). Tái dùng markup thẻ +
 * class CSS của block Product Grid (helpers/product-grid-render.php) để
 * đồng bộ giao diện, không tạo CSS/markup riêng.
 */
if (!function_exists('laca_render_journal_related_products')) {
    function laca_render_journal_related_products(int $post_id): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $mode = carbon_get_post_meta($post_id, 'related_products_mode') ?: 'auto';

        if ($mode === 'manual') {
            $manual = (array) carbon_get_post_meta($post_id, 'related_products_manual');
            $ids = array_values(array_filter(array_map(
                static fn($item) => (int) ($item['id'] ?? 0),
                $manual
            )));
            if (empty($ids)) {
                return;
            }
            $query_args = [
                'post_type' => 'product',
                'post__in' => $ids,
                'orderby' => 'post__in',
                'posts_per_page' => count($ids),
                'post_status' => 'publish',
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
            ];
        } else {
            $brand_terms = get_the_terms($post_id, 'product_brand');
            if (empty($brand_terms) || is_wp_error($brand_terms)) {
                // Bài viết chưa gán Brand nào — không có gì để "liên quan tự
                // động", hiện gợi ý riêng cho admin, khách không thấy gì cả.
                if (current_user_can('edit_posts')) {
                    echo '<div class="container-fluid"><div class="block-product-grid__admin-notice">'
                        . esc_html__('Sản phẩm liên quan (Tự động): bài viết này chưa chọn Brand nào — vào khung "Brands" bên phải để chọn.', 'laca')
                        . '</div></div>';
                }
                return;
            }

            $count = max(1, min(20, (int) (carbon_get_post_meta($post_id, 'related_products_count') ?: 4)));
            $order = carbon_get_post_meta($post_id, 'related_products_order') ?: 'newest';

            $query_args = [
                'post_type' => 'product',
                'posts_per_page' => $count,
                'post_status' => 'publish',
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'tax_query' => [
                    [
                        'taxonomy' => 'product_brand',
                        'field' => 'term_id',
                        'terms' => wp_list_pluck($brand_terms, 'term_id'),
                    ],
                ],
            ];

            switch ($order) {
                case 'oldest':
                    $query_args['orderby'] = 'date';
                    $query_args['order'] = 'ASC';
                    break;
                case 'best_selling':
                    $query_args['meta_key'] = 'total_sales';
                    $query_args['orderby'] = 'meta_value_num';
                    $query_args['order'] = 'DESC';
                    break;
                case 'random':
                    $query_args['orderby'] = 'rand';
                    break;
                case 'on_sale':
                    $on_sale_ids = function_exists('wc_get_product_ids_on_sale') ? wc_get_product_ids_on_sale() : [];
                    // [0] ép về rỗng thay vì bỏ qua điều kiện — post__in rỗng
                    // nghĩa là "không lọc gì" đối với WP_Query, sẽ trả về mọi
                    // sản phẩm cùng Brand thay vì đúng nghĩa "đang giảm giá".
                    $query_args['post__in'] = $on_sale_ids ?: [0];
                    break;
                case 'featured':
                    $query_args['tax_query'][] = [
                        'taxonomy' => 'product_visibility',
                        'field' => 'name',
                        'terms' => 'featured',
                    ];
                    break;
                case 'newest':
                default:
                    $query_args['orderby'] = 'date';
                    $query_args['order'] = 'DESC';
            }
        }

        $loop = new WP_Query($query_args);
        $products = $loop->posts;
        wp_reset_postdata();

        if (empty($products)) {
            return;
        }
        ?>
        <section class="block-product-grid">
            <div class="container-fluid">
                <div class="block-product-grid__header">
                    <h2 class="block-product-grid__title"><?php esc_html_e('RELATED COFFEE BEANS', 'laca'); ?></h2>
                </div>
                <hr class="block-product-grid__rule" />
                <div class="block-product-grid__grid" style="--bpg-columns: 4;">
                    <?php laca_render_product_grid_cards($products); ?>
                </div>
            </div>
        </section>
        <?php
    }
}
