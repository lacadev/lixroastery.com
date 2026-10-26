<?php

use App\Features\LacaSeo\Modules\SearchConsole\GscOAuth;
use App\Features\LacaSeo\Modules\SearchConsole\GscClient;

defined('ABSPATH') || exit;

$creds = GscOAuth::getCredentials();
$isConnected = !empty($creds['access_token']);
$redirectUri = GscOAuth::getRedirectUri();

$siteUrl = GscClient::getSiteUrl();
$perf = $isConnected ? GscClient::getPerformanceSummary($siteUrl) : null;
$sitemaps = $isConnected ? GscClient::getSitemaps($siteUrl) : null;

?>
<div class="laca-seo-view-search-console">
    <?php if (isset($_GET['connected'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Đã kết nối Google Search Console thành công!', 'lacadev'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['disconnected'])): ?>
        <div class="notice notice-info is-dismissible"><p><?php esc_html_e('Đã ngắt kết nối Google Search Console.', 'lacadev'); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html(urldecode((string) $_GET['error'])); ?></p></div>
    <?php endif; ?>

    <?php if (!$isConnected): ?>
        <!-- Not Connected: Setup Prompt -->
        <div class="laca-seo-card">
            <div class="laca-seo-card__header">
                <h2 class="laca-seo-card__title"><?php esc_html_e('Google Search Console (OAuth 2.0)', 'lacadev'); ?></h2>
                <p class="laca-seo-card__subtitle"><?php esc_html_e('Chưa kết nối tài khoản Google Search Console để xem báo cáo hiệu suất tìm kiếm.', 'lacadev'); ?></p>
            </div>
            <div class="laca-seo-fields-container" style="padding: 24px;">
                <p style="font-size: 14px; color: var(--laca-fg-muted, #71717a); margin-bottom: 16px;">
                    <?php esc_html_e('Cấu hình kết nối Google OAuth và chọn property Search Console đã được chuyển sang trang quản lý tập trung:', 'lacadev'); ?>
                </p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;" aria-hidden="true"></span>
                    <?php esc_html_e('Cài đặt kết nối Search Console', 'lacadev'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Connected: Dashboard -->
        <div class="laca-seo-card">
            <div class="laca-seo-card__header laca-seo-header-flex">
                <div>
                    <h2 class="laca-seo-card__title"><?php esc_html_e('Hiệu suất tìm kiếm (28 ngày qua)', 'lacadev'); ?></h2>
                    <p class="laca-seo-card__subtitle"><?php printf(esc_html__('Dữ liệu từ tài khoản Google Search Console cho trang web %s', 'lacadev'), esc_html($siteUrl)); ?></p>
                </div>
                <div class="laca-seo-header-actions" style="display: flex; gap: 8px; align-items: center;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;" aria-hidden="true"></span>
                        <?php esc_html_e('Cài đặt kết nối & Property', 'lacadev'); ?>
                    </a>
                    <form method="post" class="laca-seo-form-inline">
                        <?php wp_nonce_field('laca_gsc_disconnect_action'); ?>
                        <button type="submit" name="laca_gsc_disconnect" class="button button-secondary" onclick="return confirm('Bạn có chắc muốn ngắt kết nối Google Search Console không?');">
                            <?php esc_html_e('Ngắt kết nối', 'lacadev'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="laca-seo-stats-grid">
                <div class="laca-seo-stat-card">
                    <div class="laca-seo-stat-card__icon is-info"><span class="dashicons dashicons-chart-area"></span></div>
                    <div class="laca-seo-stat-card__info">
                        <span class="laca-seo-stat-card__label"><?php esc_html_e('Tổng lượt click', 'lacadev'); ?></span>
                        <strong class="laca-seo-stat-card__value"><?php echo esc_html(number_format_i18n($perf['totalClicks'] ?? 0)); ?></strong>
                    </div>
                </div>
                <div class="laca-seo-stat-card">
                    <div class="laca-seo-stat-card__icon is-info"><span class="dashicons dashicons-visibility"></span></div>
                    <div class="laca-seo-stat-card__info">
                        <span class="laca-seo-stat-card__label"><?php esc_html_e('Tổng lượt hiển thị', 'lacadev'); ?></span>
                        <strong class="laca-seo-stat-card__value"><?php echo esc_html(number_format_i18n($perf['totalImpressions'] ?? 0)); ?></strong>
                    </div>
                </div>
                <div class="laca-seo-stat-card">
                    <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-percent"></span></div>
                    <div class="laca-seo-stat-card__info">
                        <span class="laca-seo-stat-card__label"><?php esc_html_e('CTR trung bình', 'lacadev'); ?></span>
                        <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) ($perf['avgCtr'] ?? 0)); ?>%</strong>
                    </div>
                </div>
                <div class="laca-seo-stat-card">
                    <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-performance"></span></div>
                    <div class="laca-seo-stat-card__info">
                        <span class="laca-seo-stat-card__label"><?php esc_html_e('Vị trí trung bình', 'lacadev'); ?></span>
                        <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) ($perf['avgPosition'] ?? 0)); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Top Queries Table -->
            <?php if (!empty($perf['rows'])): ?>
                <h3 class="laca-seo-card__title laca-seo-title-spaced"><?php esc_html_e('Top từ khóa tìm kiếm hàng đầu', 'lacadev'); ?></h3>
                <div class="laca-seo-table-responsive">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Từ khóa truy vấn', 'lacadev'); ?></th>
                            <th class="col-click"><?php esc_html_e('Clicks', 'lacadev'); ?></th>
                            <th class="col-imp"><?php esc_html_e('Hiển thị', 'lacadev'); ?></th>
                            <th class="col-ctr"><?php esc_html_e('CTR', 'lacadev'); ?></th>
                            <th class="col-pos"><?php esc_html_e('Vị trí', 'lacadev'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($perf['rows'], 0, 15) as $row): ?>
                            <tr>
                                <td><strong><?php echo esc_html(implode(', ', $row['keys'] ?? [])); ?></strong></td>
                                <td><?php echo esc_html(number_format_i18n($row['clicks'] ?? 0)); ?></td>
                                <td><?php echo esc_html(number_format_i18n($row['impressions'] ?? 0)); ?></td>
                                <td><?php echo esc_html(round(($row['ctr'] ?? 0) * 100, 2)); ?>%</td>
                                <td><?php echo esc_html(round($row['position'] ?? 0, 1)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
