<?php
/**
 * Related Products — override giao diện mặc định của WooCommerce, dùng lại
 * đúng markup/class CSS của block Product Grid
 * (block-gutenberg/lixroastery-blocks-site/block-product-grid) qua helper
 * dùng chung app/helpers/product-grid-render.php, để 2 nơi hiện "sản phẩm
 * liên quan" (single Product ở đây, single Journal ở
 * app/helpers/journal-related-products-render.php) trông giống hệt nhau.
 *
 * Bản gốc: woocommerce/templates/single-product/related.php.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($related_products)) {
    return;
}

/**
 * Ensure all images of related products are lazy loaded by increasing the
 * current media count to WordPress's lazy loading threshold if needed.
 * Because wp_increase_content_media_count() is a private function, we
 * check for its existence before use.
 */
if (function_exists('wp_increase_content_media_count')) {
    $content_media_count = wp_increase_content_media_count(0);
    if ($content_media_count < wp_omit_loading_attr_threshold()) {
        wp_increase_content_media_count(wp_omit_loading_attr_threshold() - $content_media_count);
    }
}

// $related_products là mảng WC_Product — laca_render_product_grid_cards()
// (dùng chung với block Product Grid) cần WP_Post nên convert qua get_post().
$related_posts = array_values(array_filter(array_map(
    static fn($related_product) => get_post($related_product->get_id()),
    $related_products
)));

if (empty($related_posts)) {
    return;
}

// "Coffee Beans" hiện cứng tiếng Anh trước đây — sản phẩm không phải cà
// phê hạt (dụng cụ pha chế, quà tặng...) vẫn hiện sai tiêu đề này. File
// tương đương app/helpers/journal-related-products-render.php đã dùng
// đúng "Sản phẩm liên quan" — đổi lại cho nhất quán.
$heading = apply_filters('woocommerce_product_related_products_heading', __('Sản phẩm liên quan', 'laca'));
$shop_url = function_exists('wc_get_page_permalink') ? esc_url(wc_get_page_permalink('shop')) : '';
?>
<section class="block-product-grid">
    <div class="container-fluid">
        <div class="block-product-grid__header">
            <?php if ($heading): ?>
                <h2 class="block-product-grid__title"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($shop_url): ?>
                <a class="block-product-grid__view-all"
                    href="<?php echo $shop_url; ?>"><?php esc_html_e('View all', 'laca'); ?></a>
            <?php endif; ?>
        </div>
        <hr class="block-product-grid__rule" />
        <div class="block-product-grid__grid" style="--bpg-columns: 4;">
            <?php laca_render_product_grid_cards($related_posts); ?>
        </div>
    </div>
</section>
<?php
wp_reset_postdata();
