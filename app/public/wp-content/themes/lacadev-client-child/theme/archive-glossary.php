<?php
/**
 * App Layout: layouts/app.php
 *
 * Trang danh sách "Glossary" — hiển thị TẤT CẢ thuật ngữ trên 1 trang, sắp
 * xếp theo bảng chữ cái A-Z. Cột A-Z bên trái, click vào 1 chữ cái sẽ
 * scroll tới thuật ngữ đầu tiên bắt đầu bằng chữ cái đó (xem
 * resources/scripts/theme/components/glossary-nav.js).
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

$alphabet = range('A', 'Z');

$query = new WP_Query([
    'post_type'           => 'glossary',
    'posts_per_page'      => -1,
    'post_status'         => 'publish',
    'orderby'             => 'title',
    'order'               => 'ASC',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
]);

$terms = $query->posts;
wp_reset_postdata();

// Nhóm theo chữ cái đầu (viết hoa) — chỉ để biết chữ nào có/không có thuật
// ngữ, phục vụ làm mờ chữ cái rỗng ở cột A-Z bên trái.
$letters_with_entries = [];
foreach ($terms as $term) {
    $first_char = mb_strtoupper(mb_substr($term->post_title, 0, 1));
    $letters_with_entries[$first_char] = true;
}

// "related_articles" là ID bài viết lấy từ Carbon Fields, KHÔNG nằm trong
// batch query ở trên — nếu không prime trước, mỗi bài liên quan (của MỌI
// thuật ngữ) sẽ kích hoạt get_post_status()/get_permalink()/get_the_title()/
// get_the_excerpt() riêng lẻ ở vòng lặp render bên dưới (N+1 query), chậm
// dần khi danh sách Glossary tăng lên. Gom hết ID lại, prime cache 1 lần
// duy nhất trước khi render.
$manual_ids_by_term = [];
$all_related_ids = [];
foreach ($terms as $term) {
    $manual_ids_by_term[$term->ID] = [];
    foreach ((array) carbon_get_post_meta($term->ID, 'related_articles') as $related) {
        $related_id = (int) ($related['id'] ?? 0);
        if ($related_id) {
            $manual_ids_by_term[$term->ID][] = $related_id;
            $all_related_ids[] = $related_id;
        }
    }
}
if (!empty($all_related_ids)) {
    _prime_post_caches(array_unique($all_related_ids), true, true);
}

// Trang danh sách chỉ hiện tối đa 3 bài liên quan đã publish (trang chi tiết
// hiện đủ). Chọn thủ công thì chỉ dùng bài đã chọn; chưa chọn thì tự lấy bài
// tin tức/journal có chứa thuật ngữ (GlossaryAutoLink::autoRelatedIds()).
$related_by_term = [];
$auto_ids = [];
foreach ($terms as $term) {
    $ids = array_slice(array_values(array_filter(
        $manual_ids_by_term[$term->ID],
        static fn($id) => 'publish' === get_post_status($id)
    )), 0, 3);
    if (empty($ids)) {
        $ids = GlossaryAutoLink::autoRelatedIds($term, 3);
        $auto_ids = array_merge($auto_ids, $ids);
    }
    $related_by_term[$term->ID] = $ids;
}
if (!empty($auto_ids)) {
    _prime_post_caches(array_unique($auto_ids), true, true);
}
?>

<section class="glossary-page">
    <div class="container-fluid">
        <h1 class="glossary-page__title"><?php esc_html_e('Thuật ngữ', 'laca'); ?></h1>

        <?php if (!empty($terms)) : ?>
            <div class="glossary-page__body">
                <nav class="glossary-page__nav" aria-label="<?php esc_attr_e('Bảng chữ cái', 'laca'); ?>">
                    <?php foreach ($alphabet as $letter) :
                        $has_entries = !empty($letters_with_entries[$letter]);
                    ?>
                        <a
                            href="#glossary-letter-<?php echo esc_attr($letter); ?>"
                            data-target="glossary-letter-<?php echo esc_attr($letter); ?>"
                            class="glossary-page__nav-link<?php echo $has_entries ? '' : ' glossary-page__nav-link--disabled'; ?>"
                            <?php echo $has_entries ? '' : 'aria-disabled="true" tabindex="-1"'; ?>
                        ><?php echo esc_html($letter); ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="glossary-page__list">
                    <?php
                    $prev_letter = null;
                    foreach ($terms as $term) :
                        $first_char = mb_strtoupper(mb_substr($term->post_title, 0, 1));
                        $is_first_of_letter = $first_char !== $prev_letter;
                        $prev_letter = $first_char;

                        $related_ids = $related_by_term[$term->ID];

                        // id của item = slug, để link từ bài viết (GlossaryAutoLink)
                        // trỏ tới /glossary/#<slug> — nên neo chữ cái A-Z phải tách
                        // ra phần tử riêng (1 phần tử không có 2 id).
                        $term_slug = urldecode($term->post_name);
                    ?>
                        <?php if ($is_first_of_letter) : ?>
                            <span class="glossary-page__anchor" id="glossary-letter-<?php echo esc_attr($first_char); ?>"></span>
                        <?php endif; ?>
                        <div
                            class="glossary-page__item"
                            id="<?php echo esc_attr($term_slug); ?>"
                            data-slug="<?php echo esc_attr($term_slug); ?>"
                        >
                            <h2 class="glossary-page__term">
                                <a href="<?php echo esc_url(get_permalink($term)); ?>"><?php echo esc_html($term->post_title); ?></a>
                            </h2>
                            <div class="glossary-page__content">
                                <?php
                                // Chỉ hiện tóm tắt (excerpt hoặc đoạn đầu) — chi tiết
                                // thuật ngữ có thể chứa ảnh/block khác, xem ở trang chi tiết.
                                $summary = GlossaryAutoLink::summary($term);
                                ?>
                                <div class="glossary-page__definition">
                                    <?php echo $summary; // Đã wp_kses_post trong summary(). ?>
                                    <a class="glossary-page__more" href="<?php echo esc_url(get_permalink($term)); ?>"><?php esc_html_e('Đọc thêm', 'laca'); ?></a>
                                </div>

                                <?php if (!empty($related_ids)) : ?>
                                    <div class="glossary-page__related">
                                        <p class="glossary-page__related-label"><?php esc_html_e('Bài viết liên quan:', 'laca'); ?></p>
                                        <ul class="glossary-page__related-list">
                                            <?php foreach ($related_ids as $related_id) : ?>
                                                <li>
                                                    <a href="<?php echo esc_url(get_permalink($related_id)); ?>"><?php echo esc_html(get_the_title($related_id)); ?></a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else : ?>
            <p class="glossary-page__empty"><?php esc_html_e('Chưa có thuật ngữ nào.', 'laca'); ?></p>
        <?php endif; ?>
    </div>
</section>
