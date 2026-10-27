<?php

use App\Features\GoogleServices\GoogleDashboardService;
use App\Features\GoogleServices\GscClient;
use App\Features\GoogleServices\GscOAuth;

defined('ABSPATH') || exit;

$range = sanitize_key($_GET['range'] ?? '28d');
if (!in_array($range, ['7d', '28d', '90d'], true)) {
    $range = '28d';
}
$isForceRefresh = !empty($_GET['refresh']);

$gsc = GoogleDashboardService::getGscData($range, $isForceRefresh);
$isConnected = !empty($gsc['connected']);
$siteUrl = $gsc['siteUrl'] ?? GscClient::getSiteUrl();
$sitemaps = ($isConnected && empty($gsc['error'])) ? GscClient::getSitemaps($siteUrl, $isForceRefresh) : null;
if (is_wp_error($sitemaps)) {
    $sitemaps = null;
}

$rangeLabels = [
    '7d'  => __('7 ngày qua', 'lacadev'),
    '28d' => __('28 ngày qua', 'lacadev'),
    '90d' => __('90 ngày qua', 'lacadev'),
];
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
                <p style="font-size: 14px; color: var(--laca-fg-muted); margin-bottom: 16px;">
                    <?php esc_html_e('Cấu hình kết nối Google OAuth và chọn property Search Console đã được chuyển sang trang quản lý tập trung:', 'lacadev'); ?>
                </p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;" aria-hidden="true"></span>
                    <?php esc_html_e('Cài đặt kết nối Search Console', 'lacadev'); ?>
                </a>
            </div>
        </div>
    <?php elseif (!empty($gsc['error'])): ?>
        <!-- Error State: Alert with error message and action button to choose property -->
        <div class="laca-seo-card">
            <div class="laca-seo-card__header laca-seo-header-flex">
                <div>
                    <h2 class="laca-seo-card__title"><?php esc_html_e('Hiệu suất tìm kiếm', 'lacadev'); ?></h2>
                    <p class="laca-seo-card__subtitle"><?php printf(esc_html__('Property: %s', 'lacadev'), '<code>' . esc_html($siteUrl) . '</code>'); ?></p>
                </div>
                <div class="laca-seo-header-actions" style="display: flex; gap: 8px; align-items: center;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;" aria-hidden="true"></span>
                        <?php esc_html_e('Chọn property khác', 'lacadev'); ?>
                    </a>
                </div>
            </div>
            <div style="padding: 24px;">
                <div class="laca-alert laca-alert--error" role="alert" style="margin: 0; padding: 16px 20px; border: 1px solid var(--laca-border-danger); border-radius: 6px; background: var(--laca-bg-danger-subtle);">
                    <div style="display: flex; gap: 12px; align-items: flex-start;">
                        <span class="dashicons dashicons-no-alt" style="color: var(--laca-danger); font-size: 22px; line-height: 1.2;"></span>
                        <div>
                            <h3 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 600; color: var(--laca-fg);">
                                <?php
                                if (!empty($gsc['status'])) {
                                    printf(esc_html__('Lỗi kết nối Google Search Console (HTTP %d)', 'lacadev'), (int) $gsc['status']);
                                } else {
                                    esc_html_e('Không thể tải dữ liệu Search Console', 'lacadev');
                                }
                                ?>
                            </h3>
                            <p style="margin: 0 0 12px 0; font-size: 13.5px; color: var(--laca-fg-muted); line-height: 1.5;">
                                <?php echo esc_html($gsc['error']); ?>
                            </p>
                            <a href="<?php echo esc_url($gsc['setup_url'] ?? admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;"></span>
                                <?php esc_html_e('Chọn property khác', 'lacadev'); ?> &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Connected & Success: Dashboard -->
        <div class="laca-seo-card">
            <div class="laca-seo-card__header laca-seo-header-flex">
                <div>
                    <h2 class="laca-seo-card__title"><?php printf(esc_html__('Hiệu suất tìm kiếm (%s)', 'lacadev'), esc_html($rangeLabels[$range] ?? '28 ngày qua')); ?></h2>
                    <p class="laca-seo-card__subtitle"><?php printf(esc_html__('Dữ liệu từ tài khoản Google Search Console cho trang web %s', 'lacadev'), esc_html($siteUrl)); ?></p>
                </div>
                <div class="laca-seo-header-actions" style="display: flex; gap: 8px; align-items: center;">
                    <div class="button-group" role="group" aria-label="<?php esc_attr_e('Khoảng thời gian', 'lacadev'); ?>">
                        <?php foreach ($rangeLabels as $rKey => $rLabel): ?>
                            <a
                                href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'search-console', 'range' => $rKey], admin_url('admin.php'))); ?>"
                                class="button <?php echo $range === $rKey ? 'button-primary' : 'button-secondary'; ?>"
                                style="font-size: 12px; height: 30px; line-height: 28px;"
                            >
                                <?php echo esc_html($rLabel); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a
                        href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'search-console', 'range' => $range, 'refresh' => '1'], admin_url('admin.php'))); ?>"
                        class="button button-secondary"
                        title="<?php esc_attr_e('Làm mới dữ liệu từ Google (bỏ qua bộ nhớ tạm)', 'lacadev'); ?>"
                        style="font-size: 12px; height: 30px; line-height: 28px;"
                    >
                        <span class="dashicons dashicons-update" style="margin-top: 3px; font-size: 15px; width: 15px; height: 15px;"></span>
                        <?php esc_html_e('Làm mới', 'lacadev'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;" aria-hidden="true"></span>
                        <?php esc_html_e('Cài đặt & Property', 'lacadev'); ?>
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
                        <strong class="laca-seo-stat-card__value"><?php echo esc_html(number_format_i18n((int) ($gsc['clicks'] ?? 0))); ?></strong>
                        <small style="color: var(--laca-fg-muted);"><?php echo esc_html((string) ($gsc['clicks_delta'] ?? '—')); ?> so với kỳ trước</small>
                    </div>
                </div>
                <div class="laca-seo-stat-card">
                    <div class="laca-seo-stat-card__icon is-info"><span class="dashicons dashicons-visibility"></span></div>
                    <div class="laca-seo-stat-card__info">
                        <span class="laca-seo-stat-card__label"><?php esc_html_e('Tổng lượt hiển thị', 'lacadev'); ?></span>
                        <strong class="laca-seo-stat-card__value"><?php echo esc_html(number_format_i18n((int) ($gsc['impressions'] ?? 0))); ?></strong>
                        <small style="color: var(--laca-fg-muted);"><?php echo esc_html((string) ($gsc['impressions_delta'] ?? '—')); ?> so với kỳ trước</small>
                    </div>
                </div>
                <div class="laca-seo-stat-card">
                    <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-percent"></span></div>
                    <div class="laca-seo-stat-card__info">
                        <span class="laca-seo-stat-card__label"><?php esc_html_e('CTR trung bình', 'lacadev'); ?></span>
                        <strong class="laca-seo-stat-card__value"><?php echo esc_html((string) ($gsc['ctr'] ?? 0)); ?>%</strong>
                        <small style="color: var(--laca-fg-muted);"><?php echo esc_html((string) ($gsc['ctr_delta'] ?? '—')); ?> so với kỳ trước</small>
                    </div>
                </div>
                <div class="laca-seo-stat-card">
                    <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-performance"></span></div>
                    <div class="laca-seo-stat-card__info">
                        <span class="laca-seo-stat-card__label"><?php esc_html_e('Vị trí trung bình', 'lacadev'); ?></span>
                        <strong class="laca-seo-stat-card__value">#<?php echo esc_html((string) ($gsc['position'] ?? 0)); ?></strong>
                        <small style="color: var(--laca-fg-muted);"><?php echo esc_html((string) ($gsc['position_delta'] ?? '—')); ?> so với kỳ trước</small>
                    </div>
                </div>
            </div>

            <!-- Top Queries Table -->
            <?php
            $displayQueries = $gsc['queries'] ?? ($gsc['rows'] ?? []);
            if (!empty($displayQueries)):
            ?>
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
                        <?php foreach (array_slice($displayQueries, 0, 15) as $row): ?>
                            <?php
                            $qText = $row['query'] ?? (is_array($row['keys'] ?? null) ? implode(', ', $row['keys']) : '');
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($qText); ?></strong></td>
                                <td><?php echo esc_html(number_format_i18n((int) ($row['clicks'] ?? 0))); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) ($row['impressions'] ?? 0))); ?></td>
                                <td><?php echo esc_html((string) ($row['ctr'] ?? 0)); ?>%</td>
                                <td><?php echo esc_html((string) ($row['position'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <!-- Sitemaps Table -->
            <?php if (!empty($sitemaps['sitemaps'])): ?>
                <h3 class="laca-seo-card__title laca-seo-title-spaced"><?php esc_html_e('Sitemaps đã gửi', 'lacadev'); ?></h3>
                <div class="laca-seo-table-responsive">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('URL Sitemap', 'lacadev'); ?></th>
                            <th><?php esc_html_e('Loại', 'lacadev'); ?></th>
                            <th><?php esc_html_e('Gửi lần cuối', 'lacadev'); ?></th>
                            <th><?php esc_html_e('Đã gửi', 'lacadev'); ?></th>
                            <th><?php esc_html_e('Đã index', 'lacadev'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sitemaps['sitemaps'] as $sm): ?>
                            <tr>
                                <td><code><?php echo esc_html($sm['path'] ?? ''); ?></code></td>
                                <td><?php echo esc_html($sm['type'] ?? 'sitemap'); ?></td>
                                <td><?php echo esc_html(!empty($sm['lastSubmitted']) ? date_i18n(get_option('date_format'), strtotime($sm['lastSubmitted'])) : '—'); ?></td>
                                <td><?php echo number_format_i18n((int) ($sm['contents'][0]['submitted'] ?? 0)); ?></td>
                                <td><?php echo number_format_i18n((int) ($sm['contents'][0]['indexed'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
