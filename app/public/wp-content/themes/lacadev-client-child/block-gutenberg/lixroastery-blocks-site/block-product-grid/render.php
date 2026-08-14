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
    }
}

$loop = new WP_Query($query_args);
$products = $loop->posts;
wp_reset_postdata();

if (empty($products)) {
    return;
}

$get_term_name = static function (int $post_id, string $taxonomy): string {
    $terms = get_the_terms($post_id, $taxonomy);
    return ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';
};

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
            <?php foreach ($products as $post):
                $post_id = $post->ID;
                $tag_left = esc_html($get_term_name($post_id, 'product_cat'));
                $tag_right = esc_html($get_term_name($post_id, 'variety_cat'));
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
            <?php endforeach; ?>
        </div>
    </div>
</section>