<?php
if (!defined('ABSPATH')) {
    exit;
}

$section_title = esc_html($attributes['sectionTitle'] ?? '');
$view_all_text = esc_html($attributes['viewAllText'] ?? '');
$view_all_link = esc_url($attributes['viewAllLink'] ?? '');
$post_type = sanitize_key($attributes['postType'] ?? 'post');
$taxonomy = sanitize_key($attributes['taxonomy'] ?? '');
$selected_terms = array_map('absint', (array) ($attributes['selectedTerms'] ?? []));
$mode = in_array($attributes['mode'] ?? 'auto', ['auto', 'manual'], true) ? $attributes['mode'] : 'auto';
$orderby = sanitize_key($attributes['orderBy'] ?? 'date');
$order = strtoupper($attributes['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$posts_count = max(1, min(20, intval($attributes['postsCount'] ?? 6)));
$selected_posts = array_map('absint', (array) ($attributes['selectedPosts'] ?? []));
$columns = max(2, min(4, intval($attributes['columns'] ?? 3)));

$safe_orderby = in_array($orderby, ['date', 'title', 'menu_order'], true) ? $orderby : 'date';

if ($mode === 'manual' && !empty($selected_posts)) {
    $query_args = [
        'post_type' => $post_type,
        'post__in' => $selected_posts,
        'orderby' => 'post__in',
        'posts_per_page' => count($selected_posts),
        'post_status' => 'publish',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ];
} else {
    $query_args = [
        'post_type' => $post_type,
        'posts_per_page' => $posts_count,
        'post_status' => 'publish',
        'orderby' => $safe_orderby,
        'order' => $order,
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ];

    if ($taxonomy && !empty($selected_terms)) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $selected_terms,
            ],
        ];
    }
}

$loop = new WP_Query($query_args);
$posts = $loop->posts;
wp_reset_postdata();

if (empty($posts)) {
    return;
}

// Không chọn taxonomy filter (mặc định, hoặc chế độ thủ công không có ô
// chọn taxonomy) thì tự nhận diện taxonomy "danh mục" của chính post type
// đang hiển thị — get_the_category() chỉ đúng cho post type 'post', các CPT
// khác dùng taxonomy riêng (vd 'journal' → 'journal-cat').
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

// Ước lượng thời gian đọc theo số từ trong nội dung (chuẩn ~200 từ/phút),
// WordPress không có API sẵn cho việc này.
$get_read_time = static function (WP_Post $post): int {
    $word_count = str_word_count(wp_strip_all_tags(strip_shortcodes($post->post_content)));
    return max(1, (int) ceil($word_count / 200));
};

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-journal-grid']);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="container-fluid">
        <div class="block-journal-grid__header">
            <?php if ($section_title): ?>
                <h2 class="block-journal-grid__title"><?php echo $section_title; ?></h2>
            <?php endif; ?>
            <?php if ($view_all_text): ?>
                <a class="block-journal-grid__view-all"
                    href="<?php echo $view_all_link ?: '#'; ?>"><?php echo $view_all_text; ?></a>
            <?php endif; ?>
        </div>
        <hr class="block-journal-grid__rule" />

        <div class="block-journal-grid__grid" style="--bjg-columns: <?php echo esc_attr($columns); ?>;">
            <?php foreach ($posts as $post):
                $post_url = esc_url(get_permalink($post));
                $cat_name = $get_cat($post, $taxonomy);
                $date = esc_html(get_the_date('M d', $post));
                $title = esc_html(get_the_title($post));
                $excerpt = esc_html(wp_trim_words(get_the_excerpt($post), 40));
                $read_time = $get_read_time($post);
                ?>
                <a href="<?php echo $post_url; ?>" class="block-journal-grid__card">
                    <div class="block-journal-grid__meta">
                        <div class="block-journal-grid__eyebrow">
                            <?php if ($cat_name): ?><span><?php echo $cat_name; ?></span><span
                                    class="block-journal-grid__eyebrow-sep">|</span><?php endif; ?>
                            <span><?php echo $date; ?></span>
                        </div>
                        <h3 class="block-journal-grid__card-title"><?php echo $title; ?></h3>
                        <span class="block-journal-grid__read-time"><?php echo esc_html($read_time); ?> min read</span>
                    </div>
                    <?php if ($excerpt): ?>
                        <p class="block-journal-grid__card-excerpt"><?php echo $excerpt; ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>