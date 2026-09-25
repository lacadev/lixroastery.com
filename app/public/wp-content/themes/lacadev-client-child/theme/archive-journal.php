<?php
/**
 * App Layout: layouts/app.php
 *
 * Template cho archive CPT "journal" (/journal) — hiện tab danh mục CẤP 1
 * (taxonomy journal-cat, xem laca_get_top_level_terms_with_content()).
 * Nội dung bên dưới KHÔNG phải danh sách bài viết mà là "lưới danh mục con"
 * của tab đang active — bấm sang tab khác đổi qua AJAX tại chỗ (xem
 * resources/scripts/theme/components/journal-directory.js +
 * JournalCatDirectoryAjaxHandler.php). Muốn xem bài viết thật, bấm vào 1
 * thẻ danh mục con để sang /journal-cat/{con}/ (taxonomy-journal-cat.php).
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

$taxonomy  = 'journal-cat';
$terms     = taxonomy_exists($taxonomy) ? laca_get_top_level_terms_with_content($taxonomy) : [];
$unique_id = wp_unique_id('lix-journal-archive-');
?>

<section class="journal-archive">
    <div class="container">
        <h1 class="journal-archive__title"><?php post_type_archive_title(); ?></h1>
        <?php laca_render_dynamic_cpt_archive_intro('journal'); ?>

        <?php if (!empty($terms)) : ?>
            <div
                class="block-cpt-grid__inner journal-archive__body"
                id="<?php echo esc_attr($unique_id); ?>"
                data-journal-directory-config='<?php echo esc_attr(wp_json_encode([
                    'action'   => 'laca_journal_cat_directory_load',
                    'nonce'    => wp_create_nonce('theme_nonce'),
                    'ajaxurl'  => admin_url('admin-ajax.php'),
                    'taxonomy' => $taxonomy,
                ])); ?>'
            >
                <div class="block-cpt-grid__tabs-wrap">
                    <button type="button" class="block-cpt-grid__tabs-nav block-cpt-grid__tabs-nav--prev"><?php esc_html_e('Prev', 'laca'); ?></button>
                    <div class="block-cpt-grid__tabs">
                        <?php foreach ($terms as $i => $term) : ?>
                            <button
                                type="button"
                                class="block-cpt-grid__tab<?php echo 0 === $i ? ' is-active' : ''; ?>"
                                data-term-slug="<?php echo esc_attr($term->slug); ?>"
                            ><?php echo esc_html($term->name); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="block-cpt-grid__tabs-nav block-cpt-grid__tabs-nav--next"><?php esc_html_e('Next', 'laca'); ?></button>
                </div>

                <div class="journal-directory">
                    <?php laca_journal_render_category_directory($terms[0]); ?>
                </div>
            </div>
        <?php else : ?>
            <p class="journal-archive__empty"><?php esc_html_e('Chưa có danh mục nào.', 'laca'); ?></p>
        <?php endif; ?>
    </div>
</section>
