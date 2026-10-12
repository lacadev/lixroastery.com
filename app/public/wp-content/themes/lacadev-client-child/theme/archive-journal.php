<?php
/**
 * App Layout: layouts/app.php
 *
 * Template cho archive CPT "journal" (/journal) — hiện tab danh mục CẤP 1
 * (taxonomy journal-cat, xem laca_get_top_level_terms_with_content()).
 * Nội dung bên dưới KHÔNG phải danh sách bài viết mà là "lưới danh mục con"
 * của tab đang active — mọi tab render sẵn thành panel, bấm tab chỉ ẩn/hiện
 * tức thì (xem resources/scripts/theme/components/journal-directory.js).
 * Muốn xem bài viết thật, bấm vào 1
 * thẻ danh mục con để sang /journal-cat/{con}/ (taxonomy-journal-cat.php).
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

$taxonomy = 'journal-cat';
$terms = taxonomy_exists($taxonomy) ? laca_get_top_level_terms_with_content($taxonomy) : [];
$unique_id = wp_unique_id('lix-journal-archive-');
?>

<section class="journal-archive">
    <div class="container-fluid">
        <h1 class="journal-archive__title"><?php post_type_archive_title(); ?></h1>
        <?php laca_render_dynamic_cpt_archive_intro('journal'); ?>

        <?php if (!empty($terms)): ?>
            <?php
            // 1 query lấy toàn bộ danh mục rồi gom theo parent, thay vì 1 query/tab.
            $all_terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]);
            $children_by_parent = [];
            if (!is_wp_error($all_terms)) {
                foreach ($all_terms as $t) {
                    $children_by_parent[$t->parent][] = $t;
                }
            }
            ?>
            <div class="block-cpt-grid__inner journal-archive__body" id="<?php echo esc_attr($unique_id); ?>"
                data-journal-directory>
                <div class="block-cpt-grid__tabs-wrap">
                    <div class="block-cpt-grid__tabs" role="tablist">
                        <?php foreach ($terms as $i => $term): ?>
                            <button type="button" role="tab" class="block-cpt-grid__tab<?php echo 0 === $i ? ' is-active' : ''; ?>"
                                id="<?php echo esc_attr($unique_id . '-tab-' . $term->slug); ?>"
                                aria-controls="<?php echo esc_attr($unique_id . '-panel-' . $term->slug); ?>"
                                aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
                                data-term-slug="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php foreach ($terms as $i => $term): ?>
                    <div class="journal-directory" role="tabpanel"
                        id="<?php echo esc_attr($unique_id . '-panel-' . $term->slug); ?>"
                        aria-labelledby="<?php echo esc_attr($unique_id . '-tab-' . $term->slug); ?>"
                        data-term-slug="<?php echo esc_attr($term->slug); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
                        <?php laca_journal_render_category_directory($term, $children_by_parent[$term->term_id] ?? []); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="journal-archive__empty"><?php esc_html_e('Chưa có danh mục nào.', 'laca'); ?></p>
        <?php endif; ?>
    </div>
</section>