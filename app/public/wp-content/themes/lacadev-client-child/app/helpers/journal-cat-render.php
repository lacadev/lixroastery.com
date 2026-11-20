<?php
/**
 * Render "lưới danh mục con" cho 1 danh mục cha journal-cat — dùng ở
 * theme/archive-journal.php (cấp gốc /journal). Mọi tab được render sẵn
 * thành panel ẩn/hiện nên đổi tab là tức thì, không cần AJAX.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * In ra phần nội dung "đang xem danh mục cha nào" — tiêu đề + mô tả của
 * danh mục cha, kèm lưới các danh mục CON (chỉ tên + mô tả, không phải bài
 * viết — bấm vào 1 thẻ con sẽ chuyển sang xem bài viết của danh mục đó qua
 * URL taxonomy thật /journal-cat/{con}/).
 *
 * @param WP_Term        $term     Danh mục cha (journal-cat).
 * @param WP_Term[]|null $children Danh mục con đã query sẵn (tránh N+1 khi
 *                                 render nhiều panel); null = tự query.
 */
function laca_journal_render_category_directory(WP_Term $term, ?array $children = null): void
{
    if (null === $children) {
        $children = get_terms([
            'taxonomy'   => $term->taxonomy,
            'parent'     => $term->term_id,
            'hide_empty' => false,
        ]);
        if (is_wp_error($children)) {
            $children = [];
        }
    }
    ?>
    <div class="journal-directory__header">
        <h2 class="journal-directory__title"><?php echo esc_html($term->name); ?></h2>
        <?php if ($term->description) : ?>
            <div class="journal-directory__desc"><?php echo wp_kses_post(wpautop($term->description)); ?></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($children)) : ?>
        <div class="journal-directory__grid">
            <?php foreach ($children as $child) :
                $term_link = get_term_link($child);
            ?>
                <a
                    class="journal-directory__card"
                    href="<?php echo !is_wp_error($term_link) ? esc_url($term_link) : '#'; ?>"
                    data-term-slug="<?php echo esc_attr($child->slug); ?>"
                >
                    <h3 class="journal-directory__card-title"><?php echo esc_html($child->name); ?></h3>
                    <?php if ($child->description) : ?>
                        <p class="journal-directory__card-desc"><?php echo esc_html($child->description); ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else :
        // Danh mục không có con (vd partnership-cat chỉ 1 cấp) → hiện luôn
        // lưới bài viết của danh mục đó (card của block CPT Grid).
        $tax_object = get_taxonomy($term->taxonomy);
        $post_type  = ($tax_object && !empty($tax_object->object_type)) ? (string) reset($tax_object->object_type) : 'journal';
        $query = new WP_Query([
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => 60,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
            'tax_query'              => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                [
                    'taxonomy' => $term->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ],
            ],
        ]);
    ?>
        <div class="block-cpt-grid__list block-cpt-grid__list--cols-3">
            <?php laca_cpt_grid_render_cards($query, $term->taxonomy); ?>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * Chế độ điều hướng thanh chủ đề trên trang taxonomy journal-cat.
 *
 * @return string 'full' (mặc định) hoặc 'compact' (PREV | TẤT CẢ CHỦ ĐỀ | NEXT).
 */
function laca_journal_topic_nav_mode(): string
{
    return (string) apply_filters('laca_journal_topic_nav_mode', 'full');
}
