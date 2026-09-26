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
// sanitize_text_field thay vì sanitize_key — chuỗi định dạng ngày PHP dùng
// cả chữ hoa, dấu phẩy, dấu gạch chéo (vd "d/m/Y", "d F, Y") mà sanitize_key
// sẽ xoá mất.
$date_format = sanitize_text_field($attributes['dateFormat'] ?? '') ?: 'M d';

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

// Trích text thuần từ 1 bài viết — dùng chung cho excerpt fallback lẫn tính
// thời gian đọc.
//
// KHÔNG dùng apply_filters('the_content', ...) — đã thử và verify bị 1
// plugin nào đó trong chuỗi filter (Rank Math/WooCommerce/Polylang đều có
// hook vào 'the_content') cắt bớt nội dung khi gọi NGOÀI vòng lặp chính
// (in_the_loop() = false ở đây) — 1 bài thật 1774 từ chỉ còn lại ra vài
// chục từ sau khi qua filter, khiến thời gian đọc luôn hiện sai "1 min".
// Đọc thẳng cấu trúc block đã parse (parse_blocks()) thay vì render qua
// filter — không phụ thuộc bất kỳ plugin nào, lấy đúng 100% nội dung thật.
$extract_block_text = static function (array $blocks) use (&$extract_block_text): string {
    $parts = [];
    foreach ($blocks as $block) {
        // Block thường (paragraph/heading/list/quote...) lưu HTML hiển thị
        // thật trong innerHTML — lấy trực tiếp, không cần render gì cả.
        if (!empty($block['innerHTML'])) {
            $parts[] = wp_strip_all_tags($block['innerHTML']);
        }
        // Block động/tuỳ chỉnh (save() rỗng, như các block lacadev/* trong
        // theme này) không có innerHTML — text nằm trong JSON attribute,
        // dò các key phổ biến (content/text/description/desc) + items[].
        if (!empty($block['attrs']) && is_array($block['attrs'])) {
            foreach (['content', 'text', 'description', 'desc'] as $key) {
                if (!empty($block['attrs'][$key]) && is_string($block['attrs'][$key])) {
                    $parts[] = wp_strip_all_tags($block['attrs'][$key]);
                }
            }
            if (!empty($block['attrs']['items']) && is_array($block['attrs']['items'])) {
                foreach ($block['attrs']['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    foreach ($item as $item_value) {
                        if (is_string($item_value)) {
                            $parts[] = wp_strip_all_tags($item_value);
                        }
                    }
                }
            }
        }
        if (!empty($block['innerBlocks'])) {
            $parts[] = $extract_block_text($block['innerBlocks']);
        }
    }
    return implode(' ', array_filter($parts));
};

$get_plain_text = static function (string $post_content) use ($extract_block_text): string {
    // Bài viết cũ/không dùng block editor — post_content vốn đã là HTML
    // hiển thị thật, không cần parse block.
    if (!has_blocks($post_content)) {
        return trim(wp_strip_all_tags(strip_shortcodes($post_content)));
    }
    $text = trim($extract_block_text(parse_blocks($post_content)));
    // Phòng hờ: nếu vì lý do gì đó parse ra rỗng (block lạ không khớp quy
    // ước key ở trên), fallback về strip tag thô còn hơn hiện trống trơn.
    return $text !== '' ? $text : trim(wp_strip_all_tags(strip_shortcodes($post_content)));
};

// Ước lượng thời gian đọc theo số từ trong nội dung (chuẩn ~200 từ/phút),
// WordPress không có API sẵn cho việc này.
//
// KHÔNG dùng str_word_count() — hàm này không nhận diện ký tự có dấu tiếng
// Việt (multi-byte UTF-8) là "word character", nên mỗi từ có dấu (được,
// triển, phát...) bị tách vụn thành nhiều "từ" giả, đếm dư ~60-70% so với
// số từ thật (đã verify: bài ~558 từ thật bị đếm thành 924, hiện "5 min"
// thay vì đúng "3 min"). Tách theo khoảng trắng với modifier /u (UTF-8-aware)
// để đếm đúng số từ tiếng Việt.
$get_read_time_from_text = static function (string $text): int {
    $text = trim($text);
    if ($text === '') {
        return 1;
    }
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $word_count = is_array($words) ? count($words) : 0;
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
                // setup_postdata() để các block tuỳ chỉnh (nếu có dùng
                // get_the_ID()/context bài viết hiện tại khi tự render) nhận
                // đúng bài đang xử lý, không phải bài còn sót từ query khác.
                setup_postdata($post);

                $post_url = esc_url(get_permalink($post));
                $cat_name = $get_cat($post, $taxonomy);
                $date = esc_html(get_the_date($date_format, $post));
                $title = esc_html(get_the_title($post));

                $plain_content = $get_plain_text($post->post_content);

                $raw_excerpt = $post->post_excerpt !== '' ? $post->post_excerpt : $plain_content;
                $excerpt = esc_html(wp_trim_words(wp_strip_all_tags(strip_shortcodes($raw_excerpt)), 40));
                $read_time = $get_read_time_from_text($plain_content);
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
            <?php endforeach;
            wp_reset_postdata(); // trả lại đúng post/query chính sau setup_postdata() ở trên
            ?>
        </div>
    </div>
</section>