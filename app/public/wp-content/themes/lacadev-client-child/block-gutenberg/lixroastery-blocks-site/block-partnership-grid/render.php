<?php
if (!defined('ABSPATH')) {
    exit;
}

$section_title  = esc_html($attributes['sectionTitle'] ?? '');
$view_all_text  = esc_html($attributes['viewAllText'] ?? '');
$view_all_link  = esc_url($attributes['viewAllLink'] ?? '');
$post_type      = sanitize_key($attributes['postType'] ?? 'partnership');
$taxonomy       = sanitize_key($attributes['taxonomy'] ?? '');
$selected_terms = array_map('absint', (array) ($attributes['selectedTerms'] ?? []));
$mode           = in_array($attributes['mode'] ?? 'auto', ['auto', 'manual'], true) ? $attributes['mode'] : 'auto';
$orderby        = sanitize_key($attributes['orderBy'] ?? 'date');
$order          = strtoupper($attributes['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$posts_count    = max(1, min(20, intval($attributes['postsCount'] ?? 3)));
$selected_posts = array_map('absint', (array) ($attributes['selectedPosts'] ?? []));
$columns        = max(2, min(4, intval($attributes['columns'] ?? 3)));

$safe_orderby = in_array($orderby, ['date', 'title', 'menu_order'], true) ? $orderby : 'date';

if ($mode === 'manual' && !empty($selected_posts)) {
    $query_args = [
        'post_type'           => $post_type,
        'post__in'            => $selected_posts,
        'orderby'             => 'post__in',
        'posts_per_page'      => count($selected_posts),
        'post_status'         => 'publish',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ];
} else {
    $query_args = [
        'post_type'           => $post_type,
        'posts_per_page'      => $posts_count,
        'post_status'         => 'publish',
        'orderby'             => $safe_orderby,
        'order'               => $order,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ];

    if ($taxonomy && !empty($selected_terms)) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $selected_terms,
            ],
        ];
    }
}

$loop  = new WP_Query($query_args);
$posts = $loop->posts;
wp_reset_postdata();

if (empty($posts)) {
    return;
}

// Không chọn taxonomy filter (mặc định, hoặc chế độ thủ công không có ô
// chọn taxonomy) thì tự nhận diện taxonomy "danh mục" của chính post type
// đang hiển thị — get_the_category() chỉ đúng cho post type 'post', các CPT
// khác dùng taxonomy riêng (vd 'partnership' → 'partnership-cat').
$get_cat = static function (WP_Post $post, string $taxonomy): string {
    if (!$taxonomy) {
        foreach (get_object_taxonomies($post->post_type) as $tax) {
            if ($tax === 'category' || str_contains($tax, 'cat')) {
                $taxonomy = $tax;
                break;
            }
        }
    }
    if (!$taxonomy) {
        return '';
    }
    $terms = get_the_terms($post, $taxonomy);
    return ($terms && !is_wp_error($terms)) ? esc_html($terms[0]->name) : '';
};

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-partnership-grid']);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="container-fluid">
        <div class="block-partnership-grid__header">
            <?php if ($section_title) : ?>
                <h2 class="block-partnership-grid__title"><?php echo $section_title; ?></h2>
            <?php endif; ?>
            <?php if ($view_all_text) : ?>
                <a class="block-partnership-grid__view-all" href="<?php echo $view_all_link ?: '#'; ?>"><?php echo $view_all_text; ?></a>
            <?php endif; ?>
        </div>
        <hr class="block-partnership-grid__rule" />

        <div class="block-partnership-grid__grid" style="--bpg-columns: <?php echo esc_attr($columns); ?>;">
            <?php foreach ($posts as $post) :
                $post_id  = $post->ID;
                $post_url = esc_url(get_permalink($post));
                $cat_name = $get_cat($post, $taxonomy);
                $date     = esc_html(get_the_date('M d', $post));
                $title    = esc_html(get_the_title($post));
                $excerpt  = esc_html(wp_trim_words(get_the_excerpt($post), 20));
            ?>
                <a href="<?php echo $post_url; ?>" class="block-partnership-grid__card">
                    <div class="block-partnership-grid__image">
                        <img src="<?php echo esc_url(getPostThumbnailUrl($post_id)); ?>" alt="<?php echo $title; ?>" loading="lazy" />
                    </div>
                    <?php if ($cat_name || $date) : ?>
                        <div class="block-partnership-grid__tags">
                            <?php if ($cat_name) : ?><span><?php echo $cat_name; ?></span><?php endif; ?>
                            <?php if ($cat_name && $date) : ?><span class="block-partnership-grid__tags-sep">|</span><?php endif; ?>
                            <?php if ($date) : ?><span><?php echo $date; ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($title) : ?>
                        <h3 class="block-partnership-grid__card-title"><?php echo $title; ?></h3>
                    <?php endif; ?>
                    <?php if ($excerpt) : ?>
                        <p class="block-partnership-grid__card-excerpt"><?php echo $excerpt; ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
