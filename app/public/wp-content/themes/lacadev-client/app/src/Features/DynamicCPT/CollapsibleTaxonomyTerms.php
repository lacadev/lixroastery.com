<?php

namespace App\Features\DynamicCPT;

/**
 * CollapsibleTaxonomyTerms
 *
 * Thêm nút thu gọn/mở rộng danh mục con trên màn hình
 * edit-tags.php?taxonomy=X của BẤT KỲ taxonomy phân cấp nào (không riêng
 * Dynamic CPT — áp dụng luôn cho category/product_cat... nếu admin mở màn
 * đó) — chỉ là lớp UI client-side (localStorage), KHÔNG đổi dữ liệu/thứ tự
 * term. Mục đích: danh mục nhiều cấp con (vd taxonomy Journal, 43 items)
 * dễ nhìn/dễ kéo-thả sắp xếp hơn (sắp xếp thủ công dùng plugin riêng,
 * không thuộc phạm vi class này).
 */
class CollapsibleTaxonomyTerms
{
    public function register(): void
    {
        if (!is_admin()) {
            return;
        }
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== 'edit-tags.php') {
            return;
        }

        $taxonomy = sanitize_key($_GET['taxonomy'] ?? '');
        if (!$taxonomy || !taxonomy_exists($taxonomy) || !is_taxonomy_hierarchical($taxonomy)) {
            return;
        }

        // lacadev-client là theme độc lập (không phải child theme) — xem
        // DashboardWidgets::enqueueDashboardScripts() cho cùng 1 pattern.
        $base = dirname(get_template_directory_uri());
        $ver  = wp_get_theme()->get('Version') ?: '1.0.0';

        wp_enqueue_script(
            'laca-taxonomy-collapsible-terms',
            $base . '/resources/scripts/admin/taxonomy-collapsible-terms.js',
            [],
            $ver,
            true
        );
        wp_add_inline_script(
            'laca-taxonomy-collapsible-terms',
            'window.lacaTaxSlug = ' . wp_json_encode($taxonomy) . ';',
            'before'
        );
    }
}
