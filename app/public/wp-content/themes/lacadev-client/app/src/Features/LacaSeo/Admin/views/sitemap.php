<?php

use App\Features\LacaSeo\Admin\Renderer;
use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\SitemapFields;

defined('ABSPATH') || exit;

// Build tabs list: Chung, Sitemap HTML, Tác giả, post types, taxonomies
$static_tabs = [
    'general'      => 'Cài đặt chung (General)',
    'html-sitemap' => 'Sitemap HTML',
    'authors'      => 'Tác giả (Authors)',
];

$post_types = Helper::getAccessiblePostTypes();
$taxonomies = Helper::getAccessibleTaxonomies();

$tabs = $static_tabs;
foreach ($post_types as $pt_slug => $pt_obj) {
    $pt_label = $pt_obj->labels->singular_name ?: ($pt_obj->labels->name ?: $pt_slug);
    $tabs['pt_' . $pt_slug] = 'Loại bài: ' . $pt_label . ' (' . $pt_slug . ')';
}
foreach ($taxonomies as $tax_slug => $tax_obj) {
    $tax_label = $tax_obj->labels->singular_name ?: ($tax_obj->labels->name ?: $tax_slug);
    $linked_pts = !empty($tax_obj->object_type) ? ' - ' . implode(', ', (array) $tax_obj->object_type) : '';
    $tabs['tax_' . $tax_slug] = 'Phân loại: ' . $tax_label . ' (' . $tax_slug . $linked_pts . ')';
}

$active_tab = sanitize_key($_GET['tab'] ?? 'general');
if (!array_key_exists($active_tab, $tabs)) {
    $active_tab = 'general';
}

$all_fields = SitemapFields::get();
$tab_fields = $all_fields[$active_tab] ?? [];
$current_options = Settings::getGroup('sitemap');

$sitemap_url = home_url('/sitemap_index.xml');

?>
<div class="laca-seo-settings-layout">
    <!-- Notice box pointing to XML sitemap index -->
    <div class="laca-seo-sitemap-banner" style="background:#fff;border-left:4px solid #2271b1;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 2px rgba(0,0,0,.05);">
        <div>
            <strong>Sơ đồ trang XML của bạn:</strong>
            <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" style="margin-left:8px;font-family:monospace;font-size:13px;"><?php echo esc_html($sitemap_url); ?></a>
        </div>
        <div>
            <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" class="button button-secondary">
                <span class="dashicons dashicons-external" style="vertical-align:middle;margin-top:-2px;"></span> Xem Sitemap XML
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="laca-seo-search-bar">
        <span class="dashicons dashicons-search" aria-hidden="true"></span>
        <input type="text" id="laca-seo-search-input" placeholder="Tìm kiếm cài đặt sitemap..." class="laca-seo-search-input">
    </div>

    <div class="laca-seo-settings-body">
        <!-- Vertical Tab Navigation -->
        <aside class="laca-seo-tabs-sidebar" aria-label="Tabs Sơ đồ trang Sitemap">
            <ul class="laca-seo-tabs-list">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <li class="laca-seo-tabs-list__item">
                        <a
                            href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'sitemap', 'tab' => $tab_key], admin_url('admin.php'))); ?>"
                            class="laca-seo-tabs-list__link<?php echo ($active_tab === $tab_key) ? ' is-active' : ''; ?>"
                        >
                            <?php echo esc_html($tab_label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Form Content Card -->
        <section class="laca-seo-tabs-content">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-seo-form">
                <?php wp_nonce_field('laca_seo_save_action', 'laca_seo_nonce'); ?>
                <input type="hidden" name="action" value="laca_seo_save">
                <input type="hidden" name="option_group" value="sitemap">
                <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>">
                <input type="hidden" name="view" value="sitemap">

                <div class="laca-seo-card">
                    <div class="laca-seo-card__header">
                        <h2 class="laca-seo-card__title"><?php echo esc_html($tabs[$active_tab]); ?></h2>
                    </div>

                    <div class="laca-seo-fields-container">
                        <?php if (empty($tab_fields)): ?>
                            <p>Không có trường cài đặt nào trong mục này.</p>
                        <?php else: ?>
                            <?php foreach ($tab_fields as $field): ?>
                                <?php
                                $val = $current_options[$field->getId()] ?? $field->getDefault();
                                Renderer::renderFieldRow($field, $val);
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sticky bottom actions -->
                <div class="laca-seo-sticky-footer">
                    <div class="laca-seo-sticky-footer__inner">
                        <button type="submit" class="button button-primary button-hero laca-seo-save-btn">
                            <span class="dashicons dashicons-saved"></span> Lưu thay đổi
                        </button>

                        <button
                            type="submit"
                            formaction="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                            class="button button-secondary laca-seo-reset-btn"
                            onclick="return confirm(lacaSeoData.i18n.confirmReset);"
                            name="action"
                            value="laca_seo_reset_tab"
                        >
                            Khôi phục mặc định tab này
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>
