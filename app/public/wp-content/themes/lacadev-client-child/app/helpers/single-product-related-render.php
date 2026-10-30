<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render section "SẢN PHẨM LIÊN QUAN" cuối trang chi tiết sản phẩm.
 *
 * @param WC_Product $product
 * @return void
 */
function laca_render_single_product_related(WC_Product $product): void
{
    $product_id = $product->get_id();
    $target_count = 4;

    // 1. Lấy related từ WooCommerce (tự cache transient)
    $related_ids = wc_get_related_products($product_id, $target_count);

    // 2. Nếu thiếu thì bù thêm sản phẩm mới nhất
    if (count($related_ids) < $target_count) {
        $exclude_ids = array_merge([$product_id], $related_ids);
        $needed = $target_count - count($related_ids);

        $fallback_query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $needed,
            'post__not_in' => $exclude_ids,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if (!empty($fallback_query->posts)) {
            $related_ids = array_merge($related_ids, $fallback_query->posts);
        }
    }

    if (empty($related_ids)) {
        return;
    }

    // Preload post data, meta cache, term cache để tránh N+1 khi gọi getPostMeta / get_the_terms
    if (function_exists('_prime_post_caches')) {
        _prime_post_caches($related_ids, true, true);
    }

    // Link View all: danh mục đầu tiên của sản phẩm hoặc trang shop
    $view_all_url = '';
    $terms = get_the_terms($product_id, 'product_cat');
    if ($terms && !is_wp_error($terms) && !empty($terms[0])) {
        $term_link = get_term_link($terms[0]);
        if (!is_wp_error($term_link)) {
            $view_all_url = $term_link;
        }
    }
    if (!$view_all_url && function_exists('wc_get_page_permalink')) {
        $view_all_url = wc_get_page_permalink('shop');
    }
    ?>
    <aside class="single-product-related">
        <div class="container-fluid">
            <div class="single-product-related__header">
                <h2 class="single-product-related__title"><?php esc_html_e('SẢN PHẨM LIÊN QUAN', 'laca'); ?></h2>
                <?php if ($view_all_url): ?>
                    <a href="<?php echo esc_url($view_all_url); ?>" class="single-product-related__view-all">
                        <?php echo esc_html__('Xem tất cả', 'laca'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="single-product-related__grid">
                <?php foreach ($related_ids as $r_id): ?>
                    <div class="single-product-related__item">
                        <?php
                        if (function_exists('laca_render_product_grid_card')) {
                            laca_render_product_grid_card((int) $r_id);
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
    <?php
}
