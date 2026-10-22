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
if (isset($_GET['laca_gsc_oauth'])) {
    $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));

    if (isset($_GET['error'])) {
        if (!GscOAuth::verifyState($state)) {
            $connectError = __('Mã trạng thái bảo mật (State Nonce) không hợp lệ.', 'lacadev');
        } else {
            delete_option('laca_seo_gsc_pending');
            $errorCode = sanitize_text_field(wp_unslash($_GET['error']));
            $errorDesc = sanitize_text_field(wp_unslash($_GET['error_description'] ?? ''));

            if ($errorCode === 'access_denied') {
                $connectError = __('Google từ chối quyền truy cập. Nếu ứng dụng OAuth đang ở chế độ Testing, hãy thêm email của bạn vào Test users (Google Cloud → Google Auth Platform → Audience) hoặc bấm Publish app, rồi thử lại.', 'lacadev');
            } else {
                $connectError = sprintf(
                    __('Lỗi kết nối từ Google (%s)%s.', 'lacadev'),
                    esc_html($errorCode),
                    $errorDesc !== '' ? ': ' . esc_html($errorDesc) : ''
                );
            }
        }
    } elseif (isset($_GET['code'])) {
        $code = sanitize_text_field(wp_unslash($_GET['code']));
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

            <details class="laca-seo-prep-details" open>
                <summary class="laca-seo-prep-details__summary">
                    <span class="dashicons dashicons-info"></span>
                    <strong><?php esc_html_e('Chuẩn bị Google Cloud (4 bước bắt buộc)', 'lacadev'); ?></strong>
                </summary>
                <div class="laca-seo-prep-details__content">
                    <ol class="laca-seo-prep-details__list">
                        <li>
                            <strong><?php esc_html_e('Bật Google Search Console API:', 'lacadev'); ?></strong>
                            <?php esc_html_e('Vào Google Cloud Console › APIs & Services › Library, tìm kiếm và bật "Google Search Console API".', 'lacadev'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('OAuth Consent / Audience (Tránh lỗi 403 access_denied):', 'lacadev'); ?></strong>
                            <?php esc_html_e('Nếu ứng dụng OAuth đang ở chế độ Testing, hãy thêm email của bạn vào danh sách Test users (Google Cloud → Google Auth Platform → Audience) hoặc bấm Publish app để chuyển sang Production, rồi thử lại.', 'lacadev'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Tạo OAuth Client ID:', 'lacadev'); ?></strong>
                            <?php esc_html_e('Tại mục Credentials, tạo Client ID loại Web application. Thêm địa chỉ sau vào mục Authorized redirect URIs:', 'lacadev'); ?>
                            <div class="laca-seo-prep-details__uri-row">
                                <code class="laca-seo-code-badge" id="laca-gsc-redirect-uri"><?php echo esc_html($redirectUri); ?></code>
                                <button type="button" class="button button-small laca-seo-copy-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js($redirectUri); ?>'); alert(lacaSeoData.i18n.copied);">
                                    <span class="dashicons dashicons-admin-page"></span> <?php esc_html_e('Sao chép URI', 'lacadev'); ?>
                                </button>
                            </div>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Scope (Phạm vi truy cập):', 'lacadev'); ?></strong>
                            <?php esc_html_e('Đảm bảo đã cho phép phạm vi đọc dữ liệu tìm kiếm:', 'lacadev'); ?>
                            <code class="laca-seo-code-badge">webmasters.readonly</code>
                        </li>
                    </ol>
                </div>
            </details>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=laca-seo&view=search-console')); ?>" class="laca-seo-form-limit">
                <?php wp_nonce_field('laca_gsc_connect_action'); ?>
                <div class="laca-seo-field-group">
                    <label for="laca_gsc_client_id"><strong><?php esc_html_e('Google Client ID', 'lacadev'); ?></strong></label>
                    <input type="text" name="client_id" id="laca_gsc_client_id" class="large-text" required placeholder="xxxx.apps.googleusercontent.com">
                </div>
                <div class="laca-seo-field-group">
                    <label for="laca_gsc_client_secret"><strong><?php esc_html_e('Google Client Secret', 'lacadev'); ?></strong></label>
                    <input type="password" name="client_secret" id="laca_gsc_client_secret" class="large-text" required placeholder="GOCSPX-xxxx">
                </div>
                <button type="submit" name="laca_gsc_connect_init" class="button button-primary button-large">
                    <span class="dashicons dashicons-google" aria-hidden="true"></span> <?php esc_html_e('Đăng nhập & Cấp quyền với Google', 'lacadev'); ?>
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- Connected: Dashboard -->
        <div class="laca-seo-card">
            <div class="laca-seo-card__header laca-seo-header-flex">
                <div>
                    <h2 class="laca-seo-card__title"><?php esc_html_e('Hiệu suất tìm kiếm (28 ngày qua)', 'lacadev'); ?></h2>
                    <p class="laca-seo-card__subtitle"><?php printf(esc_html__('Dữ liệu từ tài khoản Google Search Console cho trang web %s', 'lacadev'), esc_html($siteUrl)); ?></p>
                </div>
                <form method="post" class="laca-seo-form-inline">
                    <?php wp_nonce_field('laca_gsc_disconnect_action'); ?>
                    <button type="submit" name="laca_gsc_disconnect" class="button button-secondary" onclick="return confirm('Bạn có chắc muốn ngắt kết nối Google Search Console không?');">
                        <?php esc_html_e('Ngắt kết nối', 'lacadev'); ?>
                    </button>
                </form>
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
