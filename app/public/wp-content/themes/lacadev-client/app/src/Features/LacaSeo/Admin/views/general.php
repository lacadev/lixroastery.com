<?php

use App\Features\LacaSeo\Admin\Renderer;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\GeneralFields;

defined('ABSPATH') || exit;

$tabs = [
    'links'       => 'Đường dẫn (Links)',
    'images'      => 'Tối ưu hình ảnh (Images)',
    'breadcrumbs' => 'Breadcrumbs',
    'podcast'     => 'Podcast',
    'webmaster'   => 'Webmaster Tools',
    'analytics'   => 'Google Analytics / GTM',
    'robots'      => 'Chỉnh sửa robots.txt',
    'htaccess'    => 'Chỉnh sửa .htaccess',
    'woocommerce' => 'WooCommerce SEO',
    'redirections'=> 'Chuyển hướng (Redirections)',
    '404-monitor' => 'Theo dõi lỗi 404 (404 Monitor)',
    'blocks'      => 'Khối Block Mục lục',
    'others'      => 'Khác (Others)',
];

$is_super_user = apply_filters('lacadev_is_super_user', false, wp_get_current_user());
if (!$is_super_user) {
    unset($tabs['htaccess']);
}

$active_tab = sanitize_key($_GET['tab'] ?? 'links');
if (!array_key_exists($active_tab, $tabs)) {
    $active_tab = 'links';
}

$all_fields = GeneralFields::get();
$tab_fields = $all_fields[$active_tab] ?? [];
$current_options = Settings::getGroup('general');

?>
<div class="laca-seo-settings-layout">
    <!-- Search Bar -->
    <div class="laca-seo-search-bar">
        <span class="dashicons dashicons-search" aria-hidden="true"></span>
        <input type="text" id="laca-seo-search-input" placeholder="Tìm kiếm cài đặt nhanh..." class="laca-seo-search-input">
    </div>

    <div class="laca-seo-settings-body">
        <!-- Vertical Tab Navigation -->
        <aside class="laca-seo-tabs-sidebar" aria-label="Tabs Cài đặt chung">
            <ul class="laca-seo-tabs-list">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <li class="laca-seo-tabs-list__item">
                        <a
                            href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'general', 'tab' => $tab_key], admin_url('admin.php'))); ?>"
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
                <input type="hidden" name="option_group" value="general">
                <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>">
                <input type="hidden" name="view" value="general">

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
