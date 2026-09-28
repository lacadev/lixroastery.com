<?php
if (!defined('ABSPATH')) {
    exit;
}

$section_title = esc_html($attributes['sectionTitle'] ?? '');
$view_all_text = esc_html($attributes['viewAllText'] ?? '');
$view_all_link = esc_url($attributes['viewAllLink'] ?? '') ?: (function_exists('wc_get_page_permalink') ? esc_url(wc_get_page_permalink('shop')) : '');
$columns = max(2, min(4, intval($attributes['columns'] ?? 4)));
$mode = ($attributes['mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
$auto_query = sanitize_key($attributes['autoQuery'] ?? 'newest');
$auto_count = max(1, min(20, intval($attributes['autoCount'] ?? 8)));
$selected_products = array_map('absint', (array) ($attributes['selectedProducts'] ?? []));

if (!class_exists('WooCommerce')) {
    return;
}

if ($mode === 'manual') {
    if (empty($selected_products)) {
        return;
    }
    $query_args = [
        'post_type' => 'product',
        'post__in' => $selected_products,
        'orderby' => 'post__in',
        'posts_per_page' => count($selected_products),
        'post_status' => 'publish',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ];
} else {
    $query_args = [
        'post_type' => 'product',
        'posts_per_page' => $auto_count,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ];

    switch ($auto_query) {
        case 'best_selling':
            $query_args['meta_key'] = 'total_sales';
            $query_args['orderby'] = 'meta_value_num';
            break;
        case 'random':
            $query_args['orderby'] = 'rand';
            break;
        case 'featured':
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'featured',
                ],
            ];
            break;
        case 'on_sale':
            $on_sale_ids = function_exists('wc_get_product_ids_on_sale') ? wc_get_product_ids_on_sale() : [];
            if (empty($on_sale_ids)) {
                return;
            }
            $query_args['post__in'] = $on_sale_ids;
            break;

        case 'same_brand':
            // "Sản phẩm liên quan" tự động theo Brand (taxonomy product_brand
            // của WooCommerce, gắn thêm cho post type "journal" — xem
            // theme/setup/journal_brand_taxonomy.php) của CHÍNH bài viết
            // đang xem, KHÔNG phải của block — lấy qua context "postId"
            // (usesContext trong block.json) vì block này thường được chèn
            // trực tiếp vào nội dung 1 bài Journal.
            $current_post_id = $block->context['postId'] ?? get_the_ID();
            $brand_terms = $current_post_id ? get_the_terms($current_post_id, 'product_brand') : [];
            if (empty($brand_terms) || is_wp_error($brand_terms)) {
                // Bài viết chưa gán Brand nào — không có gì để "liên quan",
                // hiện gợi ý riêng cho admin, khách thường không thấy gì cả.
                if (current_user_can('edit_posts')) {
                    echo '<div class="block-product-grid__admin-notice">'
                        . esc_html__('Product Grid (Cùng Brand): bài viết này chưa chọn Brand nào — vào Journal > Brands (bên phải khung soạn thảo) để chọn.', 'laca')
                        . '</div>';
                }
                return;
            }
            $query_args['orderby'] = 'rand';
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'product_brand',
                    'field' => 'term_id',
                    'terms' => wp_list_pluck($brand_terms, 'term_id'),
                ],
            ];
            break;
    }
}

$loop = new WP_Query($query_args);
$products = $loop->posts;
wp_reset_postdata();

if (empty($products)) {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-product-grid']);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="container-fluid">
        <div class="block-product-grid__header">
            <?php if ($section_title): ?>
                <h2 class="block-product-grid__title"><?php echo $section_title; ?></h2>
            <?php endif; ?>
            <?php if ($view_all_text): ?>
                <a class="block-product-grid__view-all"
                    href="<?php echo $view_all_link ?: '#'; ?>"><?php echo $view_all_text; ?></a>
            <?php endif; ?>
        </div>
        <hr class="block-product-grid__rule" />

        <div class="block-product-grid__grid" style="--bpg-columns: <?php echo esc_attr($columns); ?>;">
            <?php laca_render_product_grid_cards($products); ?>
        </div>
    </div>
</section>