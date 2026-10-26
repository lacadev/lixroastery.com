<?php

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Guide\GuideData;

defined('ABSPATH') || exit;

// 1. Core SEO system stats
$checklist = GuideData::getChecklist();
$blog_public = (bool) get_option('blog_public');
$rm_active = Helper::isRankMathActive();
$active_modules = Modules::getActive();

global $wpdb;
$missing_count = (int) $wpdb->get_var("
    SELECT COUNT(p.ID)
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_laca ON (p.ID = pm_laca.post_id AND pm_laca.meta_key = '_laca_seo_description')
    WHERE p.post_type IN ('post', 'page', 'product')
      AND p.post_status = 'publish'
      AND (pm_laca.meta_value IS NULL OR pm_laca.meta_value = '')
");

$redirStats = \App\Features\LacaSeo\Modules\Redirections\DB::getStats();
$notFoundStats = \App\Features\LacaSeo\Modules\NotFoundMonitor\DB::getStats();

?>
<div class="laca-seo-dashboard">
    <div class="laca-seo-card" style="margin-bottom: 24px;">
        <div class="laca-seo-card__header laca-seo-header-flex">
            <div>
                <h2 class="laca-seo-card__title"><?php esc_html_e('Báo cáo Google Search Console & Google Analytics 4', 'lacadev'); ?></h2>
                <p class="laca-seo-card__subtitle"><?php esc_html_e('Báo cáo chi tiết GSC & GA4 hiện hiển thị độc lập tại Bảng điều khiển WordPress và cài đặt tại menu Dịch vụ Google.', 'lacadev'); ?></p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="<?php echo esc_url(admin_url('index.php#laca-google-report')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-chart-area" style="margin-top: 3px;"></span> <?php esc_html_e('Xem Báo cáo trên Bảng điều khiển', 'lacadev'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-google" style="margin-top: 3px;"></span> <?php esc_html_e('Cấu hình Dịch vụ Google', 'lacadev'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Khối Stat boxes cốt lõi của website -->
    <div class="laca-seo-stats-grid" style="margin-bottom: 24px;">
        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo $blog_public ? 'is-success' : 'is-danger'; ?>">
                <span class="dashicons <?php echo $blog_public ? 'dashicons-visibility' : 'dashicons-hidden'; ?>"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label"><?php esc_html_e('Hiển thị tìm kiếm', 'lacadev'); ?></span>
                <strong class="laca-seo-stat-card__value"><?php echo $blog_public ? esc_html__('Công khai', 'lacadev') : esc_html__('Bị chặn', 'lacadev'); ?></strong>
                <small><?php echo $blog_public ? esc_html__('Googlebot được phép crawl', 'lacadev') : esc_html__('Đang bật noindex toàn site', 'lacadev'); ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo $rm_active ? 'is-warning' : 'is-success'; ?>">
                <span class="dashicons dashicons-shield"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label">Rank Math SEO</span>
                <strong class="laca-seo-stat-card__value"><?php echo $rm_active ? esc_html__('Đang chạy', 'lacadev') : esc_html__('Đã thay thế', 'lacadev'); ?></strong>
                <small><?php echo $rm_active ? esc_html__('Laca SEO tắt frontend', 'lacadev') : esc_html__('Laca SEO điều khiển 100%', 'lacadev'); ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon is-info">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label"><?php esc_html_e('Sơ đồ Sitemap XML', 'lacadev'); ?></span>
                <?php $laca_sitemap_on = Modules::isActive('sitemap'); ?>
                <strong class="laca-seo-stat-card__value"><?php echo esc_html($rm_active ? 'Rank Math' : ($laca_sitemap_on ? 'Đang hoạt động' : 'Đang tắt')); ?></strong>
                <small>
                    <?php if ($rm_active || $laca_sitemap_on): ?>
                        <a href="<?php echo esc_url(home_url('/sitemap_index.xml')); ?>" target="_blank" rel="noopener"><?php echo esc_html($rm_active ? 'Đang dùng sitemap Rank Math' : 'Xem sitemap_index.xml'); ?> &rarr;</a>
                    <?php else: ?>
                        <?php esc_html_e('Bật module Sơ đồ trang XML bên dưới', 'lacadev'); ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo $missing_count > 0 ? 'is-amber' : 'is-success'; ?>">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label"><?php esc_html_e('Bài thiếu Meta Desc', 'lacadev'); ?></span>
                <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) $missing_count); ?> <?php esc_html_e('bài', 'lacadev'); ?></strong>
                <small><?php echo $missing_count > 0 ? esc_html__('Cần bổ sung mô tả SEO', 'lacadev') : esc_html__('Tất cả bài viết đều đủ', 'lacadev'); ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon is-info">
                <span class="dashicons dashicons-randomize"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label"><?php esc_html_e('Chuyển hướng hoạt động', 'lacadev'); ?></span>
                <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) ($redirStats->total ?? 0)); ?> <?php esc_html_e('quy tắc', 'lacadev'); ?></strong>
                <small><?php echo esc_html((string) ($redirStats->hits ?? 0)); ?> <?php esc_html_e('lượt chuyển hướng', 'lacadev'); ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo ($notFoundStats->total ?? 0) > 0 ? 'is-amber' : 'is-success'; ?>">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label"><?php esc_html_e('Lỗi 404 ghi nhận', 'lacadev'); ?></span>
                <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) ($notFoundStats->total ?? 0)); ?> URL</strong>
                <small><?php echo esc_html((string) ($notFoundStats->hits ?? 0)); ?> <?php esc_html_e('lượt truy cập lỗi', 'lacadev'); ?></small>
            </div>
        </div>
    </div>

    <!-- Khối Modules & Tính năng Laca SEO -->
    <div class="laca-seo-card">
        <div class="laca-seo-card__header">
            <h2 class="laca-seo-card__title"><?php esc_html_e('Các tính năng & Module SEO', 'lacadev'); ?></h2>
            <p class="laca-seo-card__subtitle"><?php esc_html_e('Bật tắt các module tính năng phù hợp với nhu cầu tối ưu của website.', 'lacadev'); ?></p>
        </div>

        <div class="laca-seo-modules-grid">
            <?php foreach (Modules::DEFINITIONS as $mod_id => $mod): ?>
                <?php
                $is_active = Modules::isActive($mod_id);
                $is_core = !empty($mod['core']);
                ?>
                <div class="laca-seo-module-card<?php echo $is_active ? ' is-active' : ''; ?>">
                    <div class="laca-seo-module-card__header">
                        <h3 class="laca-seo-module-card__title"><?php echo esc_html($mod['title']); ?></h3>
                        <?php if ($is_core): ?>
                            <span class="laca-seo-badge laca-seo-badge--core"><?php esc_html_e('Cốt lõi', 'lacadev'); ?></span>
                        <?php else: ?>
                            <label class="laca-seo-switch">
                                <input
                                    type="checkbox"
                                    class="laca-seo-module-toggle"
                                    data-module="<?php echo esc_attr($mod_id); ?>"
                                    role="switch"
                                    <?php echo $is_active ? 'checked' : ''; ?>
                                >
                                <span class="laca-seo-switch__slider"></span>
                            </label>
                        <?php endif; ?>
                    </div>
                    <p class="laca-seo-module-card__desc"><?php echo esc_html($mod['desc']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
