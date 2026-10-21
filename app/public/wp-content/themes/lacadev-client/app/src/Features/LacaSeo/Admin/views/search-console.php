<?php

use App\Features\LacaSeo\Modules\SearchConsole\GscOAuth;
use App\Features\LacaSeo\Modules\SearchConsole\GscClient;

defined('ABSPATH') || exit;

$creds = GscOAuth::getCredentials();
$isConnected = !empty($creds['access_token']);
$redirectUri = GscOAuth::getRedirectUri();

// Handle disconnect action
if (isset($_POST['laca_gsc_disconnect'])) {
    check_admin_referer('laca_gsc_disconnect_action');
    if (current_user_can('manage_options')) {
        GscOAuth::disconnect();
        wp_safe_redirect(admin_url('admin.php?page=laca-seo&view=search-console&disconnected=1'));
        exit;
    }
}

// Handle save credentials & initiate OAuth
$connectError = '';
if (isset($_POST['laca_gsc_connect_init'])) {
    check_admin_referer('laca_gsc_connect_action');
    $clientId = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
    $clientSecret = sanitize_text_field(wp_unslash($_POST['client_secret'] ?? ''));

    if ($clientId === '' || $clientSecret === '') {
        $connectError = __('Vui lòng nhập đầy đủ Client ID và Client Secret.', 'lacadev');
    } else {
        update_option('laca_seo_gsc_pending', [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ], false);

        $authUrl = GscOAuth::buildAuthUrl($clientId);
        wp_redirect($authUrl);
        exit;
    }
}

// Handle OAuth callback
if (isset($_GET['laca_gsc_oauth']) && isset($_GET['code'])) {
    $code = sanitize_text_field(wp_unslash($_GET['code']));
    $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));

    if (!GscOAuth::verifyState($state)) {
        $connectError = __('Mã trạng thái bảo mật (State Nonce) không hợp lệ.', 'lacadev');
    } else {
        $pending = get_option('laca_seo_gsc_pending', []);
        delete_option('laca_seo_gsc_pending');

        if (!empty($pending['client_id']) && !empty($pending['client_secret'])) {
            $exchange = GscOAuth::handleCallback($code, $pending['client_id'], $pending['client_secret']);
            if ($exchange['success']) {
                wp_safe_redirect(admin_url('admin.php?page=laca-seo&view=search-console&connected=1'));
                exit;
            } else {
                $connectError = $exchange['error'] ?? __('Lỗi xác thực OAuth từ Google.', 'lacadev');
            }
        } else {
            $connectError = __('Không tìm thấy Client ID hoặc Client Secret đang chờ xác thực.', 'lacadev');
        }
    }
}

$siteUrl = home_url('/');
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
    <?php if ($connectError): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($connectError); ?></p></div>
    <?php endif; ?>

    <?php if (!$isConnected): ?>
        <!-- Not Connected: Setup & Connect Form -->
        <div class="laca-seo-card">
            <div class="laca-seo-card__header">
                <h2 class="laca-seo-card__title"><?php esc_html_e('Kết nối Google Search Console (OAuth 2.0)', 'lacadev'); ?></h2>
                <p class="laca-seo-card__subtitle"><?php esc_html_e('Đồng bộ dữ liệu thứ hạng từ khóa, lượt click, lỗi sitemap và kiểm tra trạng thái index trực tiếp từ Google.', 'lacadev'); ?></p>
            </div>

            <div class="laca-seo-guide-steps" style="margin: 20px 0; background: #f8fafc; padding: 18px; border-radius: 8px; border-left: 4px solid #3b82f6;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;"><?php esc_html_e('Hướng dẫn thiết lập Google Cloud OAuth Client:', 'lacadev'); ?></h4>
                <ol style="margin: 0; padding-left: 20px; line-height: 1.8; font-size: 13px;">
                    <li><?php esc_html_e('Truy cập Google Cloud Console › Credentials và tạo mới một OAuth client ID kiểu Web application.', 'lacadev'); ?></li>
                    <li>
                        <?php esc_html_e('Tại mục Authorized redirect URIs, dán chính xác URL sau:', 'lacadev'); ?><br>
                        <code style="display:inline-block; padding: 4px 8px; background: #e2e8f0; border-radius: 4px; font-weight: 600; margin-top: 4px;"><?php echo esc_html($redirectUri); ?></code>
                    </li>
                    <li><?php esc_html_e('Bật Google Search Console API trong Library của Google Cloud Project.', 'lacadev'); ?></li>
                    <li><?php esc_html_e('Sao chép Client ID và Client Secret vào biểu mẫu bên dưới và bấm nút Kết nối.', 'lacadev'); ?></li>
                </ol>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=laca-seo&view=search-console')); ?>" style="max-width: 600px;">
                <?php wp_nonce_field('laca_gsc_connect_action'); ?>
                <div class="laca-seo-field-group" style="margin-bottom: 16px;">
                    <label for="laca_gsc_client_id"><strong><?php esc_html_e('Google Client ID', 'lacadev'); ?></strong></label>
                    <input type="text" name="client_id" id="laca_gsc_client_id" class="large-text" required placeholder="xxxx.apps.googleusercontent.com">
                </div>
                <div class="laca-seo-field-group" style="margin-bottom: 20px;">
                    <label for="laca_gsc_client_secret"><strong><?php esc_html_e('Google Client Secret', 'lacadev'); ?></strong></label>
                    <input type="password" name="client_secret" id="laca_gsc_client_secret" class="large-text" required placeholder="GOCSPX-xxxx">
                </div>
                <button type="submit" name="laca_gsc_connect_init" class="button button-primary button-large">
                    <span class="dashicons dashicons-google" style="margin-top:4px;"></span> <?php esc_html_e('Đăng nhập & Cấp quyền với Google', 'lacadev'); ?>
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- Connected: Dashboard -->
        <div class="laca-seo-card">
            <div class="laca-seo-card__header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 class="laca-seo-card__title"><?php esc_html_e('Hiệu suất tìm kiếm (28 ngày qua)', 'lacadev'); ?></h2>
                    <p class="laca-seo-card__subtitle"><?php printf(esc_html__('Dữ liệu từ tài khoản Google Search Console cho trang web %s', 'lacadev'), esc_html($siteUrl)); ?></p>
                </div>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('laca_gsc_disconnect_action'); ?>
                    <button type="submit" name="laca_gsc_disconnect" class="button button-secondary" onclick="return confirm('Bạn có chắc muốn ngắt kết nối Google Search Console không?');">
                        <?php esc_html_e('Ngắt kết nối', 'lacadev'); ?>
                    </button>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="laca-seo-stats-grid" style="margin: 20px 0;">
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
                <h3 style="margin-top: 30px; font-size: 15px;"><?php esc_html_e('Top từ khóa tìm kiếm hàng đầu', 'lacadev'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Từ khóa truy vấn', 'lacadev'); ?></th>
                            <th style="width: 100px;"><?php esc_html_e('Clicks', 'lacadev'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Hiển thị', 'lacadev'); ?></th>
                            <th style="width: 90px;"><?php esc_html_e('CTR', 'lacadev'); ?></th>
                            <th style="width: 90px;"><?php esc_html_e('Vị trí', 'lacadev'); ?></th>
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
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
