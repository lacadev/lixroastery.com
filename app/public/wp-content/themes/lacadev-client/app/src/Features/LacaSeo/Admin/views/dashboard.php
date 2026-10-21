<?php

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Guide\GuideData;

defined('ABSPATH') || exit;

$checklist = GuideData::getChecklist();
$blog_public = (bool) get_option('blog_public');
$rm_active = Helper::isRankMathActive();
$active_modules = Modules::getActive();

// Missing meta description count
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
    <!-- Stat boxes -->
    <div class="laca-seo-stats-grid">
        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo $blog_public ? 'is-success' : 'is-danger'; ?>">
                <span class="dashicons <?php echo $blog_public ? 'dashicons-visibility' : 'dashicons-hidden'; ?>"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label">Hiển thị tìm kiếm</span>
                <strong class="laca-seo-stat-card__value"><?php echo $blog_public ? 'Công khai' : 'Bị chặn'; ?></strong>
                <small><?php echo $blog_public ? 'Googlebot được phép crawl' : 'Đang bật noindex toàn site'; ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo $rm_active ? 'is-warning' : 'is-success'; ?>">
                <span class="dashicons dashicons-shield"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label">Rank Math SEO</span>
                <strong class="laca-seo-stat-card__value"><?php echo $rm_active ? 'Đang chạy' : 'Đã thay thế'; ?></strong>
                <small><?php echo $rm_active ? 'Laca SEO tắt frontend' : 'Laca SEO điều khiển 100%'; ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon is-info">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label">Sơ đồ Sitemap XML</span>
                <strong class="laca-seo-stat-card__value"><?php echo $rm_active ? 'Rank Math' : 'Sắp có (Phase 3)'; ?></strong>
                <small><?php echo $rm_active ? '<a href="' . esc_url(home_url('/sitemap_index.xml')) . '" target="_blank">Đang dùng sitemap Rank Math &rarr;</a>' : 'Laca Sitemap XML chuyên sâu (Phase 3)'; ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo $missing_count > 0 ? 'is-amber' : 'is-success'; ?>">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label">Bài thiếu Meta Desc</span>
                <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) $missing_count); ?> bài</strong>
                <small><?php echo $missing_count > 0 ? 'Cần bổ sung mô tả SEO' : 'Tất cả bài viết đều đủ'; ?></small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon is-info">
                <span class="dashicons dashicons-randomize"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label">Chuyển hướng hoạt động</span>
                <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) ($redirStats->total ?? 0)); ?> quy tắc</strong>
                <small><?php echo esc_html((string) ($redirStats->hits ?? 0)); ?> lượt chuyển hướng</small>
            </div>
        </div>

        <div class="laca-seo-stat-card">
            <div class="laca-seo-stat-card__icon <?php echo ($notFoundStats->total ?? 0) > 0 ? 'is-amber' : 'is-success'; ?>">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div class="laca-seo-stat-card__info">
                <span class="laca-seo-stat-card__label">Lỗi 404 ghi nhận</span>
                <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) ($notFoundStats->total ?? 0)); ?> URL</strong>
                <small><?php echo esc_html((string) ($notFoundStats->hits ?? 0)); ?> lượt truy cập lỗi</small>
            </div>
        </div>
    </div>

    <!-- Modules Grid -->
    <div class="laca-seo-card" style="margin-top: 24px;">
        <div class="laca-seo-card__header">
            <h2 class="laca-seo-card__title">Các tính năng & Module SEO</h2>
            <p class="laca-seo-card__subtitle">Bật tắt các module tính năng phù hợp với nhu cầu tối ưu của website.</p>
        </div>

        <div class="laca-seo-modules-grid">
            <?php foreach (Modules::DEFINITIONS as $mod_id => $mod): ?>
                <?php
                $is_active = Modules::isActive($mod_id);
                $is_core = !empty($mod['core']);
                $phase = $mod['phase'] ?? 1;
                $is_future = ($phase > 1);
                ?>
                <div class="laca-seo-module-card<?php echo $is_active ? ' is-active' : ''; ?><?php echo $is_future ? ' is-future' : ''; ?>">
                    <div class="laca-seo-module-card__header">
                        <h3 class="laca-seo-module-card__title"><?php echo esc_html($mod['title']); ?></h3>
                        <?php if ($is_future): ?>
                            <span class="laca-seo-badge laca-seo-badge--subtle">Sắp có (Phase <?php echo esc_html((string) $phase); ?>)</span>
                        <?php elseif ($is_core): ?>
                            <span class="laca-seo-badge laca-seo-badge--core">Cốt lõi</span>
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
