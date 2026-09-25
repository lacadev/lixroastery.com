<?php
/**
 * Render "lưới danh mục con" cho 1 danh mục cha journal-cat — dùng ở
 * theme/archive-journal.php (cấp gốc /journal) và AJAX handler
 * JournalCatDirectoryAjaxHandler (khi bấm đổi tab cấp cha).
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
 * @param WP_Term $term Danh mục cha (journal-cat).
 */
function laca_journal_render_category_directory(WP_Term $term): void
{
    $children = get_terms([
        'taxonomy'   => $term->taxonomy,
        'parent'     => $term->term_id,
        'hide_empty' => false,
    ]);
    if (is_wp_error($children)) {
        $children = [];
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
    <?php else : ?>
        <p class="journal-directory__empty"><?php esc_html_e('Chưa có danh mục con nào.', 'laca'); ?></p>
    <?php endif; ?>
    <?php
}
