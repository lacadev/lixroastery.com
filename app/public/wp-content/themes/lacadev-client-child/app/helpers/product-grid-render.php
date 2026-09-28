<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Card sản phẩm — dùng chung giữa block Product Grid
 * (block-gutenberg/lixroastery-blocks-site/block-product-grid/render.php)
 * và section "Sản phẩm liên quan" tự động cuối mỗi bài Journal
 * (app/helpers/journal-related-products-render.php), để đồng bộ giao diện
 * và tránh trùng lặp markup.
 */

if (!function_exists('laca_product_grid_term_name')) {
    function laca_product_grid_term_name(int $post_id, string $taxonomy): string
    {
        $terms = get_the_terms($post_id, $taxonomy);
        return ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';
    }
}

if (!function_exists('laca_render_product_grid_cards')) {
    /**
     * @param WP_Post[] $products
     */
    function laca_render_product_grid_cards(array $products): void
    {
        foreach ($products as $post) {
            $post_id = $post->ID;
            $tag_left = esc_html(laca_product_grid_term_name($post_id, 'product_cat'));
            $tag_right = esc_html(laca_product_grid_term_name($post_id, 'variety_cat'));
            $title = esc_html(get_the_title($post_id));
            $origin = getPostMeta('origin', $post_id);
            $region = getPostMeta('region', $post_id);
            $subtitle = esc_html(trim(implode(', ', array_filter([$origin, $region])), ', '));
            $link = esc_url(get_permalink($post_id));
            ?>
            <a href="<?php echo $link; ?>" class="block-product-grid__card">
                <div class="block-product-grid__image">
                    <img src="<?php echo esc_url(getPostThumbnailUrl($post_id)); ?>" alt="<?php echo $title; ?>" loading="lazy" />
                </div>
                <?php if ($tag_left || $tag_right): ?>
                    <div class="block-product-grid__tags">
                        <?php if ($tag_left): ?><span><?php echo $tag_left; ?></span><?php endif; ?>
                        <?php if ($tag_left && $tag_right): ?><span
                                class="block-product-grid__tags-sep">|</span><?php endif; ?>
                        <?php if ($tag_right): ?><span><?php echo $tag_right; ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($title): ?>
                    <h3 class="block-product-grid__card-title"><?php echo $title; ?></h3>
                <?php endif; ?>
                <?php if ($subtitle): ?>
                    <p class="block-product-grid__card-subtitle"><?php echo $subtitle; ?></p>
                <?php endif; ?>
            </a>
            <?php
        }
    }
}
