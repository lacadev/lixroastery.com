<?php
/**
 * Helper taxonomy dùng chung cho block CPT Grid và các template
 * archive-journal.php / taxonomy-journal-cat.php.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lấy danh sách danh mục CẤP 1 (top-level) của 1 taxonomy — CHỈ những danh
 * mục có bài viết, TÍNH CẢ bài viết nằm ở danh mục con bên trong (hide_empty
 * của get_terms() chỉ tính bài gán trực tiếp, không đệ quy xuống con, nên
 * phải tự dò ngược lên tổ tiên cấp 1 cho từng term có bài).
 *
 * @param string $taxonomy Taxonomy slug.
 * @return WP_Term[]
 */
function laca_get_top_level_terms_with_content(string $taxonomy): array
{
    $terms_with_posts = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
    if (is_wp_error($terms_with_posts)) {
        return [];
    }

    $top_level_ids = [];
    foreach ($terms_with_posts as $t) {
        if ((int) $t->parent === 0) {
            $top_level_ids[$t->term_id] = true;
            continue;
        }
        $ancestors = get_ancestors($t->term_id, $taxonomy, 'taxonomy');
        if (!empty($ancestors)) {
            $top_level_ids[end($ancestors)] = true;
        }
    }

    if (empty($top_level_ids)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'include'  => array_keys($top_level_ids),
        'hide_empty' => false,
    ]);

    return is_wp_error($terms) ? [] : $terms;
}
