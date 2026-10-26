<?php

use App\Features\LacaSeo\Admin\GoogleDashboardService;
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

// 2. Google Services Report (GSC + GA4)
$range = sanitize_key($_GET['range'] ?? '28d');
if (!in_array($range, ['7d', '28d', '90d'], true)) {
    $range = '28d';
}

$isForceRefresh = !empty($_GET['refresh']);
$report = GoogleDashboardService::getDashboardReport($range, $isForceRefresh);
$gsc = $report['gsc'];
$ga  = $report['ga4'];

$rangeLabels = [
    '7d'  => __('7 ngày qua', 'lacadev'),
    '28d' => __('28 ngày qua', 'lacadev'),
    '90d' => __('90 ngày qua', 'lacadev'),
];

?>
<div class="laca-seo-dashboard">

    <!-- Thanh điều khiển khoảng thời gian báo cáo Google -->
    <div class="laca-seo-card laca-google-toolbar" style="margin-bottom: 24px; padding: 14px 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-chart-pie" style="font-size: 20px; width: 20px; height: 20px; color: #18181b;"></span>
                <div>
                    <strong style="font-size: 14px; color: #18181b;"><?php esc_html_e('Báo cáo Hiệu quả Tìm kiếm & Lưu lượng Truy cập', 'lacadev'); ?></strong>
                    <div style="font-size: 12px; color: #71717a;">
                        <?php if (!empty($gsc['isMock'])): ?>
                            <span class="laca-badge"><?php esc_html_e('Dữ liệu mẫu', 'lacadev'); ?></span>
                        <?php else: ?>
                            <span><?php echo esc_html($rangeLabels[$range] ?? '28 ngày qua'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
                <div class="button-group" role="group" aria-label="<?php esc_attr_e('Khoảng thời gian', 'lacadev'); ?>">
                    <?php foreach ($rangeLabels as $rKey => $rLabel): ?>
                        <a
                            href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'range' => $rKey], admin_url('admin.php'))); ?>"
                            class="button <?php echo $range === $rKey ? 'button-primary' : 'button-secondary'; ?>"
                            style="font-size: 12px; height: 30px; line-height: 28px;"
                        >
                            <?php echo esc_html($rLabel); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <a
                    href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'range' => $range, 'refresh' => '1'], admin_url('admin.php'))); ?>"
                    class="button button-secondary"
                    title="<?php esc_attr_e('Làm mới dữ liệu từ Google (bỏ qua bộ nhớ tạm)', 'lacadev'); ?>"
                    style="font-size: 12px; height: 30px; line-height: 28px;"
                >
                    <span class="dashicons dashicons-update" style="margin-top: 3px; font-size: 15px; width: 15px; height: 15px;"></span>
                    <?php esc_html_e('Làm mới', 'lacadev'); ?>
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($gsc['isMock']) || !empty($ga['isMock'])): ?>
        <div class="laca-alert laca-alert--info" role="alert" style="margin-bottom: 20px;">
            <span class="dashicons dashicons-info laca-alert__icon" aria-hidden="true"></span>
            <div class="laca-alert__content">
                <h3 class="laca-alert__title"><?php esc_html_e('DỮ LIỆU MẪU — không phải số liệu thật', 'lacadev'); ?></h3>
                <p class="laca-alert__description"><?php esc_html_e('Đang hiển thị dữ liệu mô phỏng do có tham số ?laca_mock_google=1 trên môi trường phát triển cục bộ.', 'lacadev'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== BỐ CỤC 2 CỘT SONG SONG (PHƯƠNG ÁN B) ==================== -->
    <div class="laca-google-dual-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 24px; align-items: start; margin-bottom: 24px;">

        <!-- CỘT TRÁI: GOOGLE SEARCH CONSOLE -->
        <div class="laca-seo-card laca-google-card" style="margin-bottom: 0;">
            <div class="laca-seo-card__header laca-seo-header-flex" style="padding: 16px 20px; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                <div>
                    <h2 class="laca-seo-card__title" style="font-size: 15.5px; display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-search" style="font-size: 18px; width: 18px; height: 18px;"></span>
                        <?php esc_html_e('Google Search Console', 'lacadev'); ?>
                    </h2>
                    <p class="laca-seo-card__subtitle" style="font-size: 12.5px; margin-top: 2px;">
                        <?php esc_html_e('Bên ngoài trang — Hành vi tìm kiếm tự nhiên', 'lacadev'); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <span class="laca-badge" style="background: #f4f4f5; color: #18181b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; border: 1px solid var(--laca-border-emphasized, #d4d4d8);">
                        Organic Search
                    </span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-small" style="font-size: 11px; height: 24px; line-height: 22px;">
                        <?php esc_html_e('Cài đặt', 'lacadev'); ?>
                    </a>
                </div>
            </div>

            <?php if (empty($gsc['connected'])): ?>
                <!-- Trạng thái chưa kết nối GSC -->
                <div style="padding: 32px 24px; text-align: center; background: #fafafa;">
                    <span class="dashicons dashicons-google" style="font-size: 36px; width: 36px; height: 36px; color: #a1a1aa; margin-bottom: 12px;"></span>
                    <h3 style="font-size: 15px; margin: 0 0 6px 0; color: #18181b;"><?php esc_html_e('Chưa kết nối Google Search Console', 'lacadev'); ?></h3>
                    <p style="margin: 0 auto 16px auto; max-width: 380px; color: #71717a; font-size: 13px;">
                        <?php esc_html_e('Kết nối tài khoản Google để theo dõi thứ hạng từ khóa, lượt hiển thị và số lượt click tự nhiên từ Google.', 'lacadev'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-primary">
                        <?php esc_html_e('Thiết lập Search Console', 'lacadev'); ?> &rarr;
                    </a>
                </div>
            <?php elseif (!empty($gsc['error'])): ?>
                <!-- Lỗi trả về từ Google Search Console -->
                <div style="padding: 20px;">
                    <div class="notice notice-error" style="margin: 0; padding: 12px 16px;">
                        <p style="margin: 0 0 6px 0; font-weight: 600; color: #b91c1c;">
                            <?php echo esc_html($gsc['error']); ?>
                        </p>
                        <p style="margin: 0; font-size: 12px; color: #71717a;">
                            <?php esc_html_e('Vui lòng kiểm tra quyền tài khoản hoặc cấu hình lại trong trang Dịch vụ Google.', 'lacadev'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>"><?php esc_html_e('Xem cài đặt Search Console', 'lacadev'); ?> &rarr;</a>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- 4 thẻ KPI có % tăng trưởng vs kỳ trước -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px 20px;">
                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('Tổng lượt Clicks', 'lacadev'); ?></div>
                        <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                            <?php echo number_format_i18n($gsc['clicks'] ?? 0); ?>
                        </div>
                        <?php
                        $cDelta = (string) ($gsc['clicks_delta'] ?? '—');
                        $cColor = str_starts_with($cDelta, '+') ? '#10b981' : (str_starts_with($cDelta, '-') ? '#ef4444' : '#71717a');
                        ?>
                        <div style="font-size: 11.5px; margin-top: 4px;">
                            <span style="color: <?php echo esc_attr($cColor); ?>; font-weight: 600;"><?php echo esc_html($cDelta); ?></span>
                            <?php if ($cDelta !== '—'): ?>
                                <span style="color: #71717a;"><?php esc_html_e('vs kỳ trước', 'lacadev'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('Tổng lượt Hiển thị', 'lacadev'); ?></div>
                        <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                            <?php echo number_format_i18n($gsc['impressions'] ?? 0); ?>
                        </div>
                        <?php
                        $iDelta = (string) ($gsc['impressions_delta'] ?? '—');
                        $iColor = str_starts_with($iDelta, '+') ? '#10b981' : (str_starts_with($iDelta, '-') ? '#ef4444' : '#71717a');
                        ?>
                        <div style="font-size: 11.5px; margin-top: 4px;">
                            <span style="color: <?php echo esc_attr($iColor); ?>; font-weight: 600;"><?php echo esc_html($iDelta); ?></span>
                            <?php if ($iDelta !== '—'): ?>
                                <span style="color: #71717a;"><?php esc_html_e('vs kỳ trước', 'lacadev'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('CTR trung bình', 'lacadev'); ?></div>
                        <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                            <?php echo esc_html((string) ($gsc['ctr'] ?? 0)); ?>%
                        </div>
                        <?php
                        $ctrDelta = (string) ($gsc['ctr_delta'] ?? '—');
                        $ctrColor = str_starts_with($ctrDelta, '+') ? '#10b981' : (str_starts_with($ctrDelta, '-') ? '#ef4444' : '#71717a');
                        ?>
                        <div style="font-size: 11.5px; margin-top: 4px;">
                            <span style="color: <?php echo esc_attr($ctrColor); ?>; font-weight: 600;"><?php echo esc_html($ctrDelta); ?></span>
                            <?php if ($ctrDelta !== '—'): ?>
                                <span style="color: #71717a;"><?php esc_html_e('vs kỳ trước', 'lacadev'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('Vị trí trung bình', 'lacadev'); ?></div>
                        <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                            <?php echo esc_html((string) ($gsc['position'] ?? 0)); ?>
                        </div>
                        <?php
                        $posDelta = (string) ($gsc['position_delta'] ?? '—');
                        $posColor = str_starts_with($posDelta, '+') ? '#10b981' : (str_starts_with($posDelta, '-') ? '#ef4444' : '#71717a');
                        ?>
                        <div style="font-size: 11.5px; margin-top: 4px;">
                            <span style="color: <?php echo esc_attr($posColor); ?>; font-weight: 600;"><?php echo esc_html($posDelta); ?></span>
                            <?php if ($posDelta !== '—'): ?>
                                <span style="color: #71717a;"><?php esc_html_e('thứ hạng', 'lacadev'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bảng 1: Top từ khóa tìm kiếm kèm CTR, Vị trí TB và Badge Cơ hội -->
                <div style="padding: 0 20px 16px 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h4 style="margin: 0; font-size: 13.5px; font-weight: 600; color: #18181b;">
                            <?php esc_html_e('Top từ khóa tìm kiếm', 'lacadev'); ?>
                        </h4>
                        <span style="font-size: 11px; color: #71717a;">
                            <?php esc_html_e('Vùng cơ hội: Top 4 – 10', 'lacadev'); ?>
                        </span>
                    </div>

                    <div class="laca-seo-table-responsive" style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; overflow-x: auto;">
                        <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Từ khóa truy vấn', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 60px;">Clicks</th>
                                    <th style="text-align: right; width: 60px;">CTR</th>
                                    <th style="text-align: right; width: 85px;"><?php esc_html_e('Vị trí TB', 'lacadev'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($gsc['queries'])): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #71717a; padding: 14px;">
                                            <?php esc_html_e('Chưa có dữ liệu từ khóa trong khoảng thời gian này.', 'lacadev'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($gsc['queries'] as $q): ?>
                                        <?php
                                        $pos = (float) ($q['position'] ?? 0);
                                        $isOpportunity = ($pos >= 4.0 && $pos <= 10.0);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($q['query'] ?? ''); ?></strong>
                                                <?php if ($isOpportunity): ?>
                                                    <span style="background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 600; padding: 1px 5px; border-radius: 3px; margin-left: 4px;">
                                                        <?php esc_html_e('Cơ hội', 'lacadev'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right; font-weight: 600;"><?php echo number_format_i18n((int) ($q['clicks'] ?? 0)); ?></td>
                                            <td style="text-align: right; color: #52525b;"><?php echo esc_html((string) ($q['ctr'] ?? 0)); ?>%</td>
                                            <td style="text-align: right;">
                                                <span style="display: inline-block; padding: 1px 6px; border-radius: 4px; font-weight: 600; font-size: 11.5px; background: <?php echo $pos <= 3.0 ? '#dcfce7; color: #166534;' : ($isOpportunity ? '#fef3c7; color: #92400e;' : '#f4f4f5; color: #71717a;'); ?>">
                                                    #<?php echo esc_html((string) $pos); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bảng 2: Top trang đích nhận click từ Google (Organic Landing Pages) -->
                <div style="padding: 0 20px 16px 20px;">
                    <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600; color: #18181b;">
                        <?php esc_html_e('Top trang đích nhận click từ Google (Organic Landing Pages)', 'lacadev'); ?>
                    </h4>
                    <div class="laca-seo-table-responsive" style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; overflow-x: auto;">
                        <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Trang đích', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 75px;">Clicks</th>
                                    <th style="text-align: right; width: 85px;"><?php esc_html_e('Hiển thị', 'lacadev'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($gsc['pages'])): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #71717a; padding: 14px;">
                                            <?php esc_html_e('Chưa có dữ liệu trang đích trong khoảng thời gian này.', 'lacadev'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($gsc['pages'] as $p): ?>
                                        <tr>
                                            <td><code style="font-size: 12px;"><?php echo esc_html($p['url'] ?? ''); ?></code></td>
                                            <td style="text-align: right; font-weight: 600;"><?php echo number_format_i18n((int) ($p['clicks'] ?? 0)); ?></td>
                                            <td style="text-align: right; color: #52525b;"><?php echo number_format_i18n((int) ($p['impressions'] ?? 0)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Chú thích độ trễ GSC -->
                <div style="padding: 0 20px 16px 20px;">
                    <small style="color: #71717a; font-size: 11.5px; display: block;">
                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                        <?php echo esc_html($gsc['lagNotice'] ?? 'Google Search Console có độ trễ 2–3 ngày'); ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <!-- CỘT PHẢI: GOOGLE ANALYTICS 4 -->
        <div class="laca-seo-card laca-google-card" style="margin-bottom: 0;">
            <div class="laca-seo-card__header laca-seo-header-flex" style="padding: 16px 20px; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                <div>
                    <h2 class="laca-seo-card__title" style="font-size: 15.5px; display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-chart-area" style="font-size: 18px; width: 18px; height: 18px;"></span>
                        <?php esc_html_e('Google Analytics 4', 'lacadev'); ?>
                    </h2>
                    <p class="laca-seo-card__subtitle" style="font-size: 12.5px; margin-top: 2px;">
                        <?php esc_html_e('Bên trong trang — Trải nghiệm & Chuyển đổi', 'lacadev'); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <span class="laca-badge" style="background: #f4f4f5; color: #18181b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; border: 1px solid var(--laca-border-emphasized, #d4d4d8);">
                        Traffic & Events
                    </span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-small" style="font-size: 11px; height: 24px; line-height: 22px;">
                        <?php esc_html_e('Cài đặt', 'lacadev'); ?>
                    </a>
                </div>
            </div>

            <?php if (empty($ga['connected'])): ?>
                <!-- Trạng thái chưa kết nối GA4 -->
                <div style="padding: 32px 24px; text-align: center; background: #fafafa;">
                    <span class="dashicons dashicons-chart-line" style="font-size: 36px; width: 36px; height: 36px; color: #a1a1aa; margin-bottom: 12px;"></span>
                    <h3 style="font-size: 15px; margin: 0 0 6px 0; color: #18181b;"><?php esc_html_e('Chưa kết nối Google Analytics 4', 'lacadev'); ?></h3>
                    <p style="margin: 0 auto 16px auto; max-width: 380px; color: #71717a; font-size: 13px;">
                        <?php esc_html_e('Kết nối Google Analytics 4 để theo dõi người dùng, số phiên truy cập, thời gian đọc sâu và tỷ lệ chuyển đổi đơn hàng / workshop.', 'lacadev'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-primary">
                        <?php esc_html_e('Thiết lập Google Analytics 4', 'lacadev'); ?> &rarr;
                    </a>
                </div>
            <?php elseif (!empty($ga['error'])): ?>
                <!-- Lỗi trả về từ GA4 Data API -->
                <div style="padding: 20px;">
                    <div class="notice notice-error" style="margin: 0; padding: 12px 16px;">
                        <p style="margin: 0 0 6px 0; font-weight: 600; color: #b91c1c;">
                            <?php echo esc_html($ga['error']); ?>
                        </p>
                        <p style="margin: 0; font-size: 12px; color: #71717a;">
                            <?php esc_html_e('Vui lòng kiểm tra quyền tài khoản hoặc cấu hình lại trong trang Dịch vụ Google.', 'lacadev'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>"><?php esc_html_e('Xem cài đặt Google Analytics', 'lacadev'); ?> &rarr;</a>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- 4 thẻ KPI có Thời gian tương tác TB và Tỷ lệ chuyển đổi chung -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px 20px;">
                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('Người dùng hoạt động', 'lacadev'); ?></div>
                        <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                            <?php echo number_format_i18n($ga['users'] ?? 0); ?>
                        </div>
                        <?php
                        $uDelta = (string) ($ga['users_delta'] ?? '—');
                        $uColor = str_starts_with($uDelta, '+') ? '#10b981' : (str_starts_with($uDelta, '-') ? '#ef4444' : '#71717a');
                        ?>
                        <div style="font-size: 11.5px; margin-top: 4px;">
                            <span style="color: <?php echo esc_attr($uColor); ?>; font-weight: 600;"><?php echo esc_html($uDelta); ?></span>
                            <?php if ($uDelta !== '—'): ?>
                                <span style="color: #71717a;"><?php esc_html_e('vs kỳ trước', 'lacadev'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('Số phiên truy cập', 'lacadev'); ?></div>
                        <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                            <?php echo number_format_i18n($ga['sessions'] ?? 0); ?>
                        </div>
                        <?php
                        $sDelta = (string) ($ga['sessions_delta'] ?? '—');
                        $sColor = str_starts_with($sDelta, '+') ? '#10b981' : (str_starts_with($sDelta, '-') ? '#ef4444' : '#71717a');
                        ?>
                        <div style="font-size: 11.5px; margin-top: 4px;">
                            <span style="color: <?php echo esc_attr($sColor); ?>; font-weight: 600;"><?php echo esc_html($sDelta); ?></span>
                            <?php if ($sDelta !== '—'): ?>
                                <span style="color: #71717a;"><?php esc_html_e('vs kỳ trước', 'lacadev'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('Thời gian tương tác TB', 'lacadev'); ?></div>
                        <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                            <?php echo esc_html($ga['avgEngagement'] ?? '0s'); ?>
                        </div>
                        <?php
                        $eDelta = (string) ($ga['avgEngagement_delta'] ?? '—');
                        $eColor = str_starts_with($eDelta, '+') ? '#10b981' : (str_starts_with($eDelta, '-') ? '#ef4444' : '#71717a');
                        ?>
                        <div style="font-size: 11.5px; margin-top: 4px;">
                            <span style="color: <?php echo esc_attr($eColor); ?>; font-weight: 600;"><?php echo esc_html($eDelta); ?></span>
                            <?php if ($eDelta !== '—'): ?>
                                <span style="color: #71717a;"><?php esc_html_e('đọc sâu hơn', 'lacadev'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                        <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;"><?php esc_html_e('Tỷ lệ chuyển đổi chung', 'lacadev'); ?></div>
                        <?php if (!empty($ga['conversionRate'])): ?>
                            <div style="font-size: 22px; font-weight: 700; color: #18181b; line-height: 1.2;">
                                <?php echo esc_html($ga['conversionRate']); ?>
                            </div>
                            <?php
                            $crDelta = (string) ($ga['conversionRate_delta'] ?? '—');
                            $crColor = str_starts_with($crDelta, '+') ? '#10b981' : (str_starts_with($crDelta, '-') ? '#ef4444' : '#71717a');
                            ?>
                            <div style="font-size: 11.5px; margin-top: 4px;">
                                <span style="color: <?php echo esc_attr($crColor); ?>; font-weight: 600;"><?php echo esc_html($crDelta); ?></span>
                                <span style="color: #71717a;">(Key Events)</span>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 12px; font-weight: 500; color: #71717a; line-height: 1.4; margin: 4px 0 2px 0;">
                                <?php esc_html_e('Chưa cấu hình sự kiện chính trong GA4', 'lacadev'); ?>
                            </div>
                            <div style="font-size: 11px;">
                                <a href="https://support.google.com/analytics/answer/12844695" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: none;">
                                    <?php esc_html_e('Hướng dẫn Key Events', 'lacadev'); ?> &rarr;
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kênh thu hút lượng truy cập (Session Default Channel Grouping) -->
                <div style="padding: 0 20px 16px 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <h4 style="margin: 0; font-size: 13.5px; font-weight: 600; color: #18181b;">
                            <?php esc_html_e('Kênh thu hút truy cập (Channel Grouping)', 'lacadev'); ?>
                        </h4>
                        <span style="font-size: 11px; color: #71717a;">
                            <?php echo number_format_i18n($ga['sessions'] ?? 0); ?> <?php esc_html_e('phiên', 'lacadev'); ?>
                        </span>
                    </div>

                    <!-- Thanh phân bổ kênh dạng Stacked Segmented Bar -->
                    <div style="height: 12px; border-radius: 6px; overflow: hidden; display: flex; width: 100%; margin-bottom: 10px; background: #e4e4e7;">
                        <?php if (!empty($ga['channels'])): ?>
                            <?php foreach ($ga['channels'] as $ch): ?>
                                <div style="width: <?php echo esc_attr((string) $ch['share']); ?>%; background: <?php echo esc_attr($ch['color']); ?>;" title="<?php echo esc_attr($ch['name'] . ': ' . $ch['share'] . '% (' . number_format_i18n($ch['sessions']) . ')'); ?>"></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Chú thích chi tiết kênh -->
                    <div style="display: flex; gap: 14px; flex-wrap: wrap; font-size: 11.5px;">
                        <?php if (!empty($ga['channels'])): ?>
                            <?php foreach ($ga['channels'] as $ch): ?>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 9px; height: 9px; border-radius: 2px; background: <?php echo esc_attr($ch['color']); ?>; display: inline-block;"></span>
                                    <strong><?php echo esc_html($ch['name']); ?>: <?php echo esc_html((string) $ch['share']); ?>%</strong>
                                    <span style="color: #71717a;">(<?php echo number_format_i18n((int) $ch['sessions']); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Khối Hành động mục tiêu / Chuyển đổi (Business KPI / Key Events) -->
                <?php if (!empty($ga['businessKpis'])): ?>
                    <div style="padding: 0 20px 16px 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600; color: #18181b;">
                            <?php esc_html_e('Hành động mục tiêu giá trị (Key Events / Conversions)', 'lacadev'); ?>
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <?php foreach ($ga['businessKpis'] as $kpi): ?>
                                <?php
                                $kDelta = (string) ($kpi['delta'] ?? '—');
                                $kColor = str_starts_with($kDelta, '+') ? '#10b981' : (str_starts_with($kDelta, '-') ? '#ef4444' : '#71717a');
                                ?>
                                <div style="background: #fafafa; border: 1px solid var(--laca-border, #e4e4e7); border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                                    <div style="font-size: 11.5px; color: #52525b;"><?php echo esc_html($kpi['title']); ?></div>
                                    <div style="font-size: 18px; font-weight: 700; color: #18181b; margin-top: 2px;">
                                        <?php echo number_format_i18n((int) $kpi['value']); ?> <span style="font-size: 12px; font-weight: normal; color: #71717a;"><?php esc_html_e('lượt', 'lacadev'); ?></span>
                                    </div>
                                    <small style="color: <?php echo esc_attr($kColor); ?>; font-weight: 600; font-size: 11px;"><?php echo esc_html($kDelta); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Bảng Top trang có lượt xem cao & Thời gian tương tác TB -->
                <div style="padding: 0 20px 16px 20px;">
                    <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600; color: #18181b;">
                        <?php esc_html_e('Top trang có lượt xem cao & Thời gian tương tác', 'lacadev'); ?>
                    </h4>
                    <div class="laca-seo-table-responsive" style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; overflow-x: auto;">
                        <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Trang nội dung', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 75px;"><?php esc_html_e('Lượt xem', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 95px;"><?php esc_html_e('Tương tác TB', 'lacadev'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ga['topPages'])): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #71717a; padding: 14px;">
                                            <?php esc_html_e('Chưa có dữ liệu trang trong khoảng thời gian này.', 'lacadev'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ga['topPages'] as $tp): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($tp['title'] ?? $tp['pageTitle'] ?? 'Trang'); ?></strong><br>
                                                <small style="color: #71717a;"><?php echo esc_html($tp['path'] ?? $tp['pagePath'] ?? '/'); ?></small>
                                            </td>
                                            <td style="text-align: right; font-weight: 600;">
                                                <?php echo number_format_i18n((int) ($tp['views'] ?? $tp['screenPageViews'] ?? 0)); ?>
                                            </td>
                                            <td style="text-align: right; color: <?php echo !empty($tp['highlight']) ? '#10b981; font-weight: 600;' : '#52525b;'; ?>">
                                                <?php echo esc_html($tp['duration'] ?? '1m 30s'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
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
