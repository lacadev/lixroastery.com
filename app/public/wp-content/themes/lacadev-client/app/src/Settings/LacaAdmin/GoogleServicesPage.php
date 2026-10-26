<?php

namespace App\Settings\LacaAdmin;

use App\Features\GoogleServices\GoogleServices;
use App\Features\GoogleServices\GscClient;
use App\Features\GoogleServices\GscOAuth;
use App\Features\GoogleServices\GaClient;
use App\Features\GoogleServices\Tracking;

defined('ABSPATH') || exit;

/**
 * Manages Google Services pages under Laca Admin:
 * - Google Analytics & GTM (admin.php?page=laca-google-analytics)
 * - Google Search Console (admin.php?page=laca-google-search-console)
 *
 * Uses laca_google_services and laca_google_oauth as the single source of truth.
 */
class GoogleServicesPage
{
    public const PAGE_ANALYTICS = 'laca-google-analytics';
    public const PAGE_SEARCH_CONSOLE = 'laca-google-search-console';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerPages']);
        add_action('admin_init', [$this, 'handleActions']);
        add_action('admin_init', [$this, 'handleOAuthCallback']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerPages(): void
    {
        add_submenu_page(
            'laca-admin',
            __('Google Analytics & GTM', 'lacadev'),
            __('Google Analytics', 'lacadev'),
            'manage_options',
            self::PAGE_ANALYTICS,
            [$this, 'renderAnalyticsPage']
        );

        add_submenu_page(
            'laca-admin',
            __('Google Search Console', 'lacadev'),
            __('Search Console', 'lacadev'),
            'manage_options',
            self::PAGE_SEARCH_CONSOLE,
            [$this, 'renderSearchConsolePage']
        );
    }

    public function enqueueAssets(): void
    {
        $currentPage = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if ($currentPage !== self::PAGE_ANALYTICS && $currentPage !== self::PAGE_SEARCH_CONSOLE) {
            return;
        }

        wp_enqueue_media();

        $stylePath = get_theme_file_path('dist/styles/laca-seo.css');
        $styleUri  = get_theme_file_uri('dist/styles/laca-seo.css');
        if (file_exists($stylePath)) {
            wp_enqueue_style(
                'laca-seo-admin',
                $styleUri,
                ['dashicons'],
                (string) filemtime($stylePath)
            );
        }

        $scriptPath = get_theme_file_path('dist/laca-seo.js');
        $scriptUri  = get_theme_file_uri('dist/laca-seo.js');
        if (file_exists($scriptPath)) {
            wp_enqueue_script(
                'laca-seo-admin',
                $scriptUri,
                ['wp-i18n'],
                (string) filemtime($scriptPath),
                true
            );

            wp_localize_script('laca-seo-admin', 'lacaSeoData', [
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('laca_seo_admin_nonce'),
                'variables' => [],
                'i18n'      => [
                    'confirmReset' => __('Bạn có chắc chắn muốn khôi phục mặc định toàn bộ cài đặt không?', 'lacadev'),
                    'saved'        => __('Đã lưu cài đặt thành công.', 'lacadev'),
                    'copied'       => __('Đã sao chép vào bộ nhớ tạm!', 'lacadev'),
                ],
            ]);
        }
    }

    /**
     * Handle actions: OAuth initiation, switch account, disconnect, and property selection.
     */
    public function handleActions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 1. Connect OAuth init
        if (isset($_POST['laca_google_connect_init'])) {
            check_admin_referer('laca_google_connect_action');

            $clientId     = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
            $clientSecret = sanitize_text_field(wp_unslash($_POST['client_secret'] ?? ''));
            $returnTo     = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_SEARCH_CONSOLE));

            if ($clientSecret === '') {
                $clientSecret = (string) (GscOAuth::getCredentials()['client_secret'] ?? '');
            }

            if ($clientId === '' || $clientSecret === '') {
                wp_safe_redirect(add_query_arg(['error' => urlencode(__('Vui lòng nhập đầy đủ Client ID và Client Secret.', 'lacadev'))], admin_url('admin.php?page=' . $returnTo)));
                exit;
            }

            GscOAuth::setPendingCredentials($clientId, $clientSecret);

            $authUrl = GscOAuth::buildAuthUrl($clientId, null, $returnTo, GscOAuth::SCOPES);
            wp_redirect($authUrl);
            exit;
        }

        // 1b. Switch Google Account (re-triggers OAuth with select_account without re-entering credentials)
        if (isset($_POST['laca_google_switch_account'])) {
            check_admin_referer('laca_google_switch_account_action');

            $creds        = GscOAuth::getCredentials();
            $clientId     = $creds['client_id'] ?? '';
            $clientSecret = $creds['client_secret'] ?? '';
            $returnTo     = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_SEARCH_CONSOLE));

            if (empty($clientId) || empty($clientSecret)) {
                wp_safe_redirect(add_query_arg(['error' => urlencode(__('Chưa có thông tin Client ID. Vui lòng kết nối trước.', 'lacadev'))], admin_url('admin.php?page=' . $returnTo)));
                exit;
            }

            GscOAuth::setPendingCredentials($clientId, $clientSecret);

            $authUrl = GscOAuth::buildAuthUrl($clientId, null, $returnTo, GscOAuth::SCOPES);
            wp_redirect($authUrl);
            exit;
        }

        // 2. Grant additional scope (e.g. Analytics)
        if (isset($_POST['laca_google_grant_scope'])) {
            check_admin_referer('laca_google_grant_scope_action');

            $creds        = GscOAuth::getCredentials();
            $clientId     = $creds['client_id'] ?? '';
            $clientSecret = $creds['client_secret'] ?? '';
            $returnTo     = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_ANALYTICS));

            if (empty($clientId) || empty($clientSecret)) {
                wp_safe_redirect(add_query_arg(['error' => urlencode(__('Chưa có thông tin Client ID. Vui lòng kết nối tài khoản Google trước.', 'lacadev'))], admin_url('admin.php?page=' . $returnTo)));
                exit;
            }

            GscOAuth::setPendingCredentials($clientId, $clientSecret);

            $authUrl = GscOAuth::buildAuthUrl($clientId, null, $returnTo, GscOAuth::SCOPES);
            wp_redirect($authUrl);
            exit;
        }

        // 3. Disconnect
        if (isset($_POST['laca_google_disconnect'])) {
            check_admin_referer('laca_google_disconnect_action');
            $returnTo = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_SEARCH_CONSOLE));

            GscOAuth::disconnect();
            wp_safe_redirect(add_query_arg(['disconnected' => '1'], admin_url('admin.php?page=' . $returnTo)));
            exit;
        }

        // 4. Save GSC Site URL selection
        if (isset($_POST['laca_save_gsc_site_url'])) {
            check_admin_referer('laca_save_gsc_site_url_action');
            $selectedUrl = esc_url_raw(wp_unslash($_POST['gsc_site_url'] ?? ''));
            if (empty($selectedUrl) && !empty($_POST['gsc_site_url'])) {
                // Support sc-domain: format which is not a regular URL
                $selectedUrl = sanitize_text_field(wp_unslash($_POST['gsc_site_url']));
            }

            GoogleServices::updateSettings(['gsc_site_url' => $selectedUrl]);

            wp_safe_redirect(add_query_arg(['updated' => '1'], admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)));
            exit;
        }

        // 4b. Refresh GSC sites list
        if (isset($_POST['laca_refresh_gsc_sites'])) {
            check_admin_referer('laca_refresh_gsc_sites_action');
            delete_transient('laca_gsc_sites_list');
            wp_safe_redirect(add_query_arg(['refreshed' => '1'], admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)));
            exit;
        }

        // 5. Save GA4 Property ID selection
        if (isset($_POST['laca_save_ga4_property'])) {
            check_admin_referer('laca_save_ga4_property_action');
            $propertyId = sanitize_text_field(wp_unslash($_POST['ga4_property_id'] ?? ''));
            $measurementId = sanitize_text_field(wp_unslash($_POST['ga4_measurement_id'] ?? ''));

            $updates = ['ga4_property_id' => $propertyId];
            if (!empty($measurementId)) {
                $updates['ga4_measurement_id'] = $measurementId;
            }
            GoogleServices::updateSettings($updates);

            wp_safe_redirect(add_query_arg(['updated' => '1'], admin_url('admin.php?page=' . self::PAGE_ANALYTICS)));
            exit;
        }

        // 6. Save Google Analytics & GTM configuration form
        if (isset($_POST['laca_save_google_analytics'])) {
            check_admin_referer('laca_save_google_analytics_action');

            $measurementId = sanitize_text_field(wp_unslash($_POST['ga4_measurement_id'] ?? ''));
            $gtmId         = sanitize_text_field(wp_unslash($_POST['gtm_container_id'] ?? ''));
            $anonymize     = !empty($_POST['ga_anonymize_ip']) ? 'on' : 'off';
            $excludeAdmin  = !empty($_POST['ga_exclude_loggedin']) ? 'on' : 'off';
            $goalEvents    = sanitize_textarea_field(wp_unslash($_POST['ga4_goal_events'] ?? ''));

            GoogleServices::updateSettings([
                'ga4_measurement_id'  => $measurementId,
                'gtm_container_id'    => $gtmId,
                'ga_anonymize_ip'     => $anonymize,
                'ga_exclude_loggedin' => $excludeAdmin,
                'ga4_goal_events'     => $goalEvents,
            ]);

            wp_safe_redirect(add_query_arg(['updated' => '1'], admin_url('admin.php?page=' . self::PAGE_ANALYTICS)));
            exit;
        }
    }

    /**
     * Handle OAuth callback from Google (new redirect URI or legacy redirect URI).
     * Runs early on admin_init before any HTML or headers are printed.
     */
    public function handleOAuthCallback(): void
    {
        $isNew = isset($_GET['laca_google_oauth']) || (isset($_GET['page']) && $_GET['page'] === self::PAGE_SEARCH_CONSOLE && isset($_GET['laca_gsc_oauth']));
        $isLegacy = (isset($_GET['page']) && $_GET['page'] === 'laca-seo' && isset($_GET['view']) && $_GET['view'] === 'search-console' && isset($_GET['laca_gsc_oauth']));

        if (!$isNew && !$isLegacy) {
            return;
        }

        // Legacy POST actions from old Search Console view
        if (isset($_POST['laca_gsc_disconnect'])) {
            check_admin_referer('laca_gsc_disconnect_action');
            if (current_user_can('manage_options')) {
                GscOAuth::disconnect();
                wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE . '&disconnected=1'));
                exit;
            }
        }

        if (isset($_POST['laca_gsc_connect_init'])) {
            check_admin_referer('laca_gsc_connect_action');
            if (current_user_can('manage_options')) {
                $clientId     = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
                $clientSecret = sanitize_text_field(wp_unslash($_POST['client_secret'] ?? ''));

                if ($clientSecret === '') {
                    $clientSecret = (string) (GscOAuth::getCredentials()['client_secret'] ?? '');
                }

                if ($clientId === '' || $clientSecret === '') {
                    wp_safe_redirect(add_query_arg(['error' => urlencode(__('Vui lòng nhập đầy đủ Client ID và Client Secret.', 'lacadev'))], admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)));
                    exit;
                }

                GscOAuth::setPendingCredentials($clientId, $clientSecret);

                $authUrl = GscOAuth::buildAuthUrl($clientId, null, self::PAGE_SEARCH_CONSOLE, GscOAuth::SCOPES);
                wp_redirect($authUrl);
                exit;
            }
        }

        // OAuth callback from Google
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        $returnTo = GscOAuth::getStateReturnTo($state);
        $callbackRedirectUri = $isLegacy ? GscOAuth::getLegacyRedirectUri() : GscOAuth::getRedirectUri();

        if (isset($_GET['error'])) {
            if (!GscOAuth::verifyState($state)) {
                $connectError = __('Mã trạng thái bảo mật (State Nonce) không hợp lệ.', 'lacadev');
            } else {
                delete_option('laca_google_pending');
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
            wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
            exit;
        }

        if (isset($_GET['code'])) {
            $code = sanitize_text_field(wp_unslash($_GET['code']));
            if (!GscOAuth::verifyState($state)) {
                $connectError = __('Mã trạng thái bảo mật (State Nonce) không hợp lệ.', 'lacadev');
                wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
                exit;
            }

            $pending = get_option('laca_google_pending', []);
            if (empty($pending['client_id'])) {
                $pending = get_option('laca_seo_gsc_pending', []);
            }
            delete_option('laca_google_pending');
            delete_option('laca_seo_gsc_pending');

            if (!empty($pending['client_id']) && !empty($pending['client_secret'])) {
                $exchange = GscOAuth::handleCallback($code, $pending['client_id'], $pending['client_secret'], null, $callbackRedirectUri);
                if ($exchange['success']) {
                    wp_safe_redirect(add_query_arg(['connected' => '1'], admin_url($returnTo)));
                    exit;
                }

                $connectError = $exchange['error'] ?? __('Lỗi xác thực OAuth từ Google.', 'lacadev');
                wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
                exit;
            }

            $connectError = __('Không tìm thấy Client ID hoặc Client Secret đang chờ xác thực.', 'lacadev');
            wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
            exit;
        }
    }

    /**
     * Render Google Analytics & GTM page.
     */
    public function renderAnalyticsPage(): void
    {
        $isConnected    = GscOAuth::isConnected();
        $hasGaScope     = GscOAuth::hasAnalyticsScope();
        $manualIds      = Tracking::findManualGoogleIds();
        $connectedEmail = $isConnected ? GscOAuth::getConnectedEmail() : null;

        $options        = GoogleServices::getOption();
        $currentPropId  = (string) ($options['ga4_property_id'] ?? '');
        $currentMsId    = (string) ($options['ga4_measurement_id'] ?? '');
        $gtmId          = (string) ($options['gtm_container_id'] ?? '');
        $anonymize      = ($options['ga_anonymize_ip'] ?? 'on') === 'on';
        $excludeAdmin   = ($options['ga_exclude_loggedin'] ?? 'on') === 'on';
        $goalEvents     = (string) ($options['ga4_goal_events'] ?? '');

        // Auto-discover property if connected with GA scope and property ID is missing
        $gaProperties = [];
        if ($isConnected && $hasGaScope) {
            $gaPropertiesResult = GaClient::listProperties();
            $gaProperties = is_wp_error($gaPropertiesResult) ? [] : $gaPropertiesResult;
            if (empty($currentPropId) && !empty($currentMsId)) {
                $foundProp = GaClient::findPropertyByMeasurementId($currentMsId);
                if ($foundProp && !empty($foundProp['propertyId'])) {
                    $currentPropId = $foundProp['propertyId'];
                    GoogleServices::updateSettings(['ga4_property_id' => $currentPropId]);
                }
            }
        }

        ?>
        <div class="wrap laca-seo-wrap laca-admin-page laca-seo-view-general">
            <h1><?php echo esc_html__('Google Analytics & Tag Manager', 'lacadev'); ?></h1>

            <?php $this->renderNotices(); ?>

            <!-- Alert chống đếm trùng mã -->
            <?php if (!empty($manualIds)): ?>
                <div class="notice notice-warning inline" style="margin: 16px 0; padding: 12px 16px; border-left-color: #f59e0b;">
                    <p style="margin: 0; font-size: 13.5px; line-height: 1.6;">
                        <strong>⚠️ <?php esc_html_e('Cảnh báo phát hiện mã theo dõi thủ công:', 'lacadev'); ?></strong><br>
                        <?php
                        printf(
                            esc_html__('Đang có mã %s được chèn thủ công ở Laca Theme → Scripts (Header/Footer). Hệ thống sẽ tự động bỏ qua (không xuất thêm) nếu Measurement ID hoặc Container ID bên dưới trùng khớp để tránh đếm 2 lần.', 'lacadev'),
                            '<code>' . esc_html(implode(', ', $manualIds)) . '</code>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Khối kết nối Google OAuth cho GA4 Data API -->
            <div class="laca-seo-card" style="margin-bottom: 24px;">
                <div class="laca-seo-card__header laca-seo-header-flex">
                    <div>
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Đồng bộ Google Analytics 4 (Google Cloud OAuth)', 'lacadev'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Kết nối OAuth để tự động lấy số liệu lượt truy cập, người dùng hoạt động và xem báo cáo trực tiếp trên Dashboard.', 'lacadev'); ?></p>
                    </div>
                    <?php if ($isConnected): ?>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <span class="laca-badge" style="background: #10b981; color: #fff; padding: 4px 10px; border-radius: 9999px; font-weight: 500; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span>
                                <?php echo esc_html($connectedEmail ? sprintf(__('Đã kết nối OAuth: %s', 'lacadev'), $connectedEmail) : __('Đã kết nối OAuth', 'lacadev')); ?>
                            </span>
                            <form method="post" style="margin: 0; display: inline-block;">
                                <?php wp_nonce_field('laca_google_switch_account_action'); ?>
                                <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_ANALYTICS); ?>">
                                <button type="submit" name="laca_google_switch_account" class="button button-secondary">
                                    <span class="dashicons dashicons-randomize" style="margin-top: 2px;"></span> <?php esc_html_e('Đổi tài khoản Google', 'lacadev'); ?>
                                </button>
                            </form>
                            <form method="post" style="margin: 0; display: inline-block;">
                                <?php wp_nonce_field('laca_google_disconnect_action'); ?>
                                <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_ANALYTICS); ?>">
                                <button type="submit" name="laca_google_disconnect" class="button button-secondary" onclick="return confirm('Bạn có chắc muốn ngắt kết nối Google OAuth không?');">
                                    <?php esc_html_e('Ngắt kết nối', 'lacadev'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="laca-seo-fields-container" style="padding: 20px;">
                    <?php if (!$isConnected): ?>
                        <div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 16px; border-radius: 6px;">
                            <p style="margin: 0 0 12px 0; font-size: 13.5px;">
                                <?php esc_html_e('Chưa kết nối tài khoản Google Cloud OAuth. Vui lòng thiết lập Client ID và Client Secret tại trang Search Console hoặc bấm nút bên dưới:', 'lacadev'); ?>
                            </p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-google" style="margin-top: 3px;"></span> <?php esc_html_e('Cấu hình kết nối Google OAuth', 'lacadev'); ?>
                            </a>
                        </div>
                    <?php elseif (!$hasGaScope): ?>
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 16px; border-radius: 6px;">
                            <p style="margin: 0 0 12px 0; font-size: 13.5px; color: #1e40af;">
                                <strong><?php esc_html_e('Tài khoản Google đã kết nối nhưng chưa có quyền đọc Google Analytics.', 'lacadev'); ?></strong><br>
                                <?php esc_html_e('Vui lòng cấp thêm quyền Google Analytics (scope: analytics.readonly) để lấy dữ liệu báo cáo.', 'lacadev'); ?>
                            </p>
                            <form method="post">
                                <?php wp_nonce_field('laca_google_grant_scope_action'); ?>
                                <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_ANALYTICS); ?>">
                                <button type="submit" name="laca_google_grant_scope" class="button button-primary">
                                    <span class="dashicons dashicons-shield" style="margin-top: 3px;"></span> <?php esc_html_e('Cấp thêm quyền Google Analytics', 'lacadev'); ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Đã kết nối & có quyền GA: Chọn thuộc tính GA4 -->
                        <form method="post" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                            <?php wp_nonce_field('laca_save_ga4_property_action'); ?>
                            <div style="flex: 1; min-width: 320px;">
                                <label for="ga4_property_select" style="display: block; font-weight: 600; margin-bottom: 6px;">
                                    <?php esc_html_e('Thuộc tính GA4 (Property) được chọn để lấy báo cáo:', 'lacadev'); ?>
                                </label>
                                <?php if (empty($gaProperties)): ?>
                                    <input type="text" name="ga4_property_id" id="ga4_property_select" class="regular-text" value="<?php echo esc_attr($currentPropId); ?>" placeholder="Ví dụ: 123456789">
                                    <p class="description"><?php esc_html_e('Nhập ID thuộc tính GA4 (dãy số dạng 123456789).', 'lacadev'); ?></p>
                                <?php else: ?>
                                    <select name="ga4_property_id" id="ga4_property_select" style="width: 100%; max-width: 560px;">
                                        <option value=""><?php esc_html_e('-- Chọn thuộc tính GA4 --', 'lacadev'); ?></option>
                                        <?php foreach ($gaProperties as $prop): ?>
                                            <option value="<?php echo esc_attr($prop['propertyId']); ?>" <?php selected($currentPropId, $prop['propertyId']); ?>>
                                                <?php echo esc_html($prop['displayName'] . ' (ID: ' . $prop['propertyId'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="laca_save_ga4_property" class="button button-secondary">
                                <?php esc_html_e('Lưu thuộc tính GA4', 'lacadev'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form cấu hình chi tiết GA4 & GTM (Dùng field-row chuẩn 320px 1fr) -->
            <form method="post" class="laca-seo-form">
                <?php wp_nonce_field('laca_save_google_analytics_action'); ?>
                <input type="hidden" name="laca_save_google_analytics" value="1">

                <div class="laca-seo-card">
                    <div class="laca-seo-card__header">
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Cấu hình mã đo lường & Script chèn', 'lacadev'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Dữ liệu cấu hình Google Analytics và Tag Manager độc lập, tự động tối ưu hóa và chống đếm lặp.', 'lacadev'); ?></p>
                    </div>

                    <div class="laca-seo-fields-container" style="padding: 0 20px;">
                        <!-- Row 1: GA4 Measurement ID -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga4_measurement_id" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Google Analytics 4 Measurement ID', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <input type="text" name="ga4_measurement_id" id="ga4_measurement_id" class="regular-text" value="<?php echo esc_attr($currentMsId); ?>" placeholder="G-XXXXXXXXXX" style="max-width: 460px;">
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Mã đo lường GA4 (bắt đầu bằng "G-"). Hệ thống sẽ tự động chèn gtag.js và cấu hình đo lường.', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 2: GTM Container ID -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="gtm_container_id" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Google Tag Manager Container ID', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <input type="text" name="gtm_container_id" id="gtm_container_id" class="regular-text" value="<?php echo esc_attr($gtmId); ?>" placeholder="GTM-XXXXXXX" style="max-width: 460px;">
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Mã vùng chứa Google Tag Manager (bắt đầu bằng "GTM-"). Hệ thống sẽ chèn đoạn mã trong <head> và iframe noscript trong <body>.', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 3: Anonymize IP -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga_anonymize_ip" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Ẩn danh địa chỉ IP', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <label class="laca-seo-switch">
                                    <input type="checkbox" name="ga_anonymize_ip" id="ga_anonymize_ip" value="on" <?php checked($anonymize); ?>>
                                    <span class="laca-seo-switch__slider"></span>
                                </label>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Ẩn octet cuối của địa chỉ IP người dùng trước khi gửi đến Google Analytics (khuyến nghị bật để tuân thủ quyền riêng tư / GDPR).', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 4: Exclude Logged-in Admins -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga_exclude_loggedin" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Bỏ qua quản trị viên đăng nhập', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <label class="laca-seo-switch">
                                    <input type="checkbox" name="ga_exclude_loggedin" id="ga_exclude_loggedin" value="on" <?php checked($excludeAdmin); ?>>
                                    <span class="laca-seo-switch__slider"></span>
                                </label>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Không theo dõi và không gửi dữ liệu khi người dùng có quyền quản trị (Administrator) đang đăng nhập website.', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 5: Key Events / Goal Events -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga4_goal_events" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Mục tiêu chuyển đổi (Key Events)', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <textarea name="ga4_goal_events" id="ga4_goal_events" rows="4" class="regular-text" style="width: 100%; max-width: 560px; font-family: monospace; font-size: 13px;"><?php echo esc_textarea($goalEvents); ?></textarea>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Mỗi dòng một mục tiêu theo định dạng: event_name|Nhãn hiển thị. Ví dụ:', 'lacadev'); ?><br>
                                    <code>purchase|Đơn hàng</code><br>
                                    <code>generate_lead|Đăng ký tư vấn</code>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="laca-seo-sticky-footer" style="margin-top: 24px;">
                    <div class="laca-seo-sticky-footer__inner">
                        <button type="submit" class="button button-primary button-hero laca-seo-save-btn">
                            <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Lưu thay đổi', 'lacadev'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render Google Search Console page.
     */
    public function renderSearchConsolePage(): void
    {
        $isConnected    = GscOAuth::isConnected();
        $creds          = GscOAuth::getCredentials();
        $redirectUri    = GscOAuth::getRedirectUri();
        $savedSiteUrl   = (string) GoogleServices::getOption('gsc_site_url', '');
        $connectedEmail = $isConnected ? GscOAuth::getConnectedEmail() : null;

        $sitesResult = $isConnected ? GscClient::listSites() : [];
        $sites = is_wp_error($sitesResult) ? [] : $sitesResult;

        $hasSavedInSites = false;
        foreach ($sites as $item) {
            $url = is_array($item) ? ($item['siteUrl'] ?? '') : (string) $item;
            if ($url === $savedSiteUrl) {
                $hasSavedInSites = true;
                break;
            }
        }

        if ($isConnected && (!$hasSavedInSites || empty($savedSiteUrl)) && !empty($sites)) {
            $siteUrl = GscClient::selectBestSiteUrl($sites);
            if (!empty($siteUrl)) {
                GoogleServices::updateSettings(['gsc_site_url' => $siteUrl]);
            } else {
                $siteUrl = GscClient::getSiteUrl();
            }
        } else {
            $siteUrl = !empty($savedSiteUrl) ? $savedSiteUrl : GscClient::getSiteUrl();
        }

        ?>
        <div class="wrap laca-seo-wrap laca-admin-page laca-seo-view-search-console">
            <h1><?php echo esc_html__('Google Search Console', 'lacadev'); ?></h1>

            <?php $this->renderNotices(); ?>

            <!-- Khối kết nối Google OAuth -->
            <div class="laca-seo-card" style="margin-bottom: 24px;">
                <div class="laca-seo-card__header laca-seo-header-flex">
                    <div>
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Kết nối Google OAuth 2.0', 'lacadev'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Kết nối dùng chung cho cả Google Search Console và Google Analytics 4.', 'lacadev'); ?></p>
                    </div>
                    <?php if ($isConnected): ?>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <span class="laca-badge" style="background: #10b981; color: #fff; padding: 4px 10px; border-radius: 9999px; font-weight: 500; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span>
                                <?php echo esc_html($connectedEmail ? sprintf(__('Đã kết nối OAuth: %s', 'lacadev'), $connectedEmail) : __('Đã kết nối OAuth', 'lacadev')); ?>
                            </span>
                            <form method="post" style="margin: 0; display: inline-block;">
                                <?php wp_nonce_field('laca_google_switch_account_action'); ?>
                                <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_SEARCH_CONSOLE); ?>">
                                <button type="submit" name="laca_google_switch_account" class="button button-secondary">
                                    <span class="dashicons dashicons-randomize" style="margin-top: 2px;"></span> <?php esc_html_e('Đổi tài khoản Google', 'lacadev'); ?>
                                </button>
                            </form>
                            <form method="post" style="margin: 0; display: inline-block;">
                                <?php wp_nonce_field('laca_google_disconnect_action'); ?>
                                <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_SEARCH_CONSOLE); ?>">
                                <button type="submit" name="laca_google_disconnect" class="button button-secondary" onclick="return confirm('Bạn có chắc muốn ngắt kết nối Google OAuth không?');">
                                    <?php esc_html_e('Ngắt kết nối', 'lacadev'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="laca-seo-fields-container" style="padding: 20px;">
                    <?php $this->renderGoogleCloudPrepDetails($redirectUri); ?>

                    <?php if (!$isConnected): ?>
                        <form method="post" class="laca-seo-form-limit" style="margin-top: 20px;">
                            <?php wp_nonce_field('laca_google_connect_action'); ?>
                            <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_SEARCH_CONSOLE); ?>">

                            <div class="laca-seo-field-group" style="margin-bottom: 16px;">
                                <label for="laca_google_client_id"><strong><?php esc_html_e('Google Client ID', 'lacadev'); ?></strong></label>
                                <input type="text" name="client_id" id="laca_google_client_id" class="large-text" required placeholder="xxxx.apps.googleusercontent.com" value="<?php echo esc_attr($creds['client_id'] ?? ''); ?>" style="max-width: 560px;">
                            </div>

                            <div class="laca-seo-field-group" style="margin-bottom: 20px;">
                                <label for="laca_google_client_secret"><strong><?php esc_html_e('Google Client Secret', 'lacadev'); ?></strong></label>
                                <?php // Không in secret ra HTML; để trống = giữ secret đã lưu. ?>
                                <input type="password" name="client_secret" id="laca_google_client_secret" class="large-text" autocomplete="new-password" <?php echo empty($creds['client_secret']) ? 'required placeholder="GOCSPX-xxxx"' : 'placeholder="' . esc_attr__('Đã lưu — để trống để giữ nguyên', 'lacadev') . '"'; ?> style="max-width: 560px;">
                            </div>

                            <button type="submit" name="laca_google_connect_init" class="button button-primary button-large">
                                <span class="dashicons dashicons-google" aria-hidden="true" style="margin-top: 3px;"></span> <?php esc_html_e('Đăng nhập & Cấp quyền với Google', 'lacadev'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Đã kết nối: Hiển thị Property Selector -->
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--laca-border, #e4e4e7);">
                            <?php if (empty($sites)): ?>
                                <div class="laca-alert laca-alert--warning" role="alert" style="margin-bottom: 16px;">
                                    <span class="dashicons dashicons-warning laca-alert__icon" aria-hidden="true"></span>
                                    <div class="laca-alert__content">
                                        <p style="margin: 0 0 10px 0;">
                                            <?php
                                             $accText = !empty($connectedEmail) ? ' ' . esc_html($connectedEmail) : '';
                                             printf(
                                                 esc_html__('Tài khoản Google%s chưa có property nào trong Search Console — thêm site tại %s rồi bấm Tải lại.', 'lacadev'),
                                                 $accText,
                                                 '<a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a>'
                                             );
                                            ?>
                                        </p>
                                        <form method="post" style="margin: 0; display: inline-block;">
                                            <?php wp_nonce_field('laca_refresh_gsc_sites_action'); ?>
                                            <button type="submit" name="laca_refresh_gsc_sites" class="button button-secondary button-small">
                                                <span class="dashicons dashicons-update" style="margin-top: 2px;"></span> <?php esc_html_e('Tải lại danh sách', 'lacadev'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="post" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                                    <?php wp_nonce_field('laca_save_gsc_site_url_action'); ?>
                                    <div style="flex: 1; min-width: 320px;">
                                        <label for="gsc_site_url_select" style="display: block; font-weight: 600; margin-bottom: 6px;">
                                            <?php esc_html_e('Trang web / Property Search Console được chọn:', 'lacadev'); ?>
                                        </label>
                                        <select name="gsc_site_url" id="gsc_site_url_select" style="width: 100%; max-width: 560px;">
                                            <?php foreach ($sites as $siteEntry): ?>
                                                <?php
                                                $optUrl = is_array($siteEntry) ? ($siteEntry['siteUrl'] ?? '') : (string) $siteEntry;
                                                $perm   = is_array($siteEntry) ? ($siteEntry['permissionLevel'] ?? 'siteOwner') : 'siteOwner';
                                                $isUnverified = ($perm === 'siteUnverifiedUser');
                                                $label  = $optUrl . ($isUnverified ? ' (' . __('chưa xác minh', 'lacadev') . ')' : '');
                                                ?>
                                                <option value="<?php echo esc_attr($optUrl); ?>" <?php selected($siteUrl, $optUrl); ?> <?php disabled($isUnverified); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="laca_save_gsc_site_url" class="button button-secondary">
                                        <?php esc_html_e('Lưu Property', 'lacadev'); ?>
                                    </button>
                                    <button type="submit" name="laca_refresh_gsc_sites" class="button button-secondary" title="<?php esc_attr_e('Tải lại danh sách property từ Google', 'lacadev'); ?>">
                                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> <?php esc_html_e('Tải lại danh sách', 'lacadev'); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Link sang báo cáo Dashboard -->
                        <div style="margin-top: 24px; padding: 16px; background: var(--laca-bg-subtle, #f4f4f5); border-radius: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <strong style="font-size: 14px;"><?php esc_html_e('Báo cáo Search Console & GA4', 'lacadev'); ?></strong>
                                <p style="margin: 4px 0 0 0; color: var(--laca-fg-muted, #71717a); font-size: 13px;">
                                    <?php printf(esc_html__('Báo cáo đầy đủ với biểu đồ lượt click, hiển thị, CTR và top từ khóa cho %s hiển thị tại Bảng điều khiển WordPress.', 'lacadev'), '<code>' . esc_html($siteUrl) . '</code>'); ?>
                                </p>
                            </div>
                            <a href="<?php echo esc_url(admin_url('index.php#laca-google-report')); ?>" class="button button-primary">
                                <span class="dashicons dashicons-chart-area" style="margin-top: 3px;"></span> <?php esc_html_e('Xem Báo cáo trên Bảng điều khiển', 'lacadev'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render common Google Cloud preparation instructions.
     */
    private function renderGoogleCloudPrepDetails(string $redirectUri): void
    {
        $legacyRedirectUri = GscOAuth::getLegacyRedirectUri();
        ?>
        <details class="laca-seo-prep-details" open style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; padding: 12px 16px; background: var(--laca-bg-card, #fff);">
            <summary class="laca-seo-prep-details__summary" style="cursor: pointer; font-weight: 600;">
                <span class="dashicons dashicons-info" style="color: var(--laca-accent, #2563eb);"></span>
                <?php esc_html_e('Hướng dẫn chuẩn bị Google Cloud Console (Bắt buộc)', 'lacadev'); ?>
            </summary>
            <div class="laca-seo-prep-details__content" style="margin-top: 12px; font-size: 13.5px; line-height: 1.6;">
                <ol style="margin-left: 20px; padding-left: 0;">
                    <li style="margin-bottom: 8px;">
                        <strong><?php esc_html_e('Bật các API Google:', 'lacadev'); ?></strong>
                        <?php esc_html_e('Trong Google Cloud Console › APIs & Services › Library, tìm và bật: "Google Search Console API", "Google Analytics Data API", và "Google Analytics Admin API".', 'lacadev'); ?>
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong><?php esc_html_e('OAuth Consent / Test Users:', 'lacadev'); ?></strong>
                        <?php esc_html_e('Nếu ứng dụng OAuth đang ở chế độ Testing, hãy thêm email tài khoản Google của bạn vào Test users (Audience) hoặc bấm Publish app để tránh lỗi 403 access_denied.', 'lacadev'); ?>
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong><?php esc_html_e('Tạo OAuth Client ID:', 'lacadev'); ?></strong>
                        <?php esc_html_e('Tại mục Credentials, tạo Client ID loại Web application. Nhập địa chỉ Authorized redirect URI sau:', 'lacadev'); ?>
                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
                            <code style="background: var(--laca-bg-subtle, #f4f4f5); padding: 4px 8px; border-radius: 4px; font-size: 12px; word-break: break-all;"><?php echo esc_html($redirectUri); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($redirectUri); ?>'); alert(window.lacaSeoData?.i18n?.copied || 'Đã sao chép!');">
                                <span class="dashicons dashicons-admin-page" style="margin-top: 2px;"></span> <?php esc_html_e('Sao chép', 'lacadev'); ?>
                            </button>
                        </div>
                        <p style="margin: 6px 0 0 0; font-size: 12px; color: var(--laca-fg-muted, #71717a);">
                            <?php printf(esc_html__('Lưu ý: URI cũ (%s) vẫn tạm thời được hỗ trợ trong giai đoạn chuyển tiếp.', 'lacadev'), '<code>' . esc_html($legacyRedirectUri) . '</code>'); ?>
                        </p>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Phạm vi truy cập (Scopes):', 'lacadev'); ?></strong>
                        <code>webmasters.readonly</code>, <code>analytics.readonly</code>, <code>openid</code>, <code>email</code>.
                    </li>
                </ol>
            </div>
        </details>
        <?php
    }

    private function renderNotices(): void
    {
        if (isset($_GET['connected'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã kết nối tài khoản Google thành công!', 'lacadev') . '</p></div>';
        }
        if (isset($_GET['disconnected'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Đã ngắt kết nối tài khoản Google.', 'lacadev') . '</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã lưu cài đặt thành công.', 'lacadev') . '</p></div>';
        }
        if (isset($_GET['refreshed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã làm mới danh sách property từ Google Search Console.', 'lacadev') . '</p></div>';
        }
        if (!empty($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(wp_unslash($_GET['error'])) . '</p></div>';
        }
    }
}
