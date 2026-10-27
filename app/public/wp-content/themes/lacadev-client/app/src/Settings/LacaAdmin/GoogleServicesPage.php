<?php

namespace App\Settings\LacaAdmin;

use App\Features\GoogleServices\GoogleServices;
use App\Features\GoogleServices\GscClient;
use App\Features\GoogleServices\GscOAuth;
use App\Features\GoogleServices\GaClient;
use App\Features\GoogleServices\Tracking;
use App\Features\GoogleServices\GuideContent;
use App\Features\GoogleServices\PageSpeedClient;

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

        // Enqueue bundled laca-seo styles and scripts from child/parent dist
        $distDir = dirname(get_stylesheet_directory()) . '/dist/';
        $themeRootUri = dirname(get_stylesheet_directory_uri());
        if (!file_exists($distDir . 'styles/laca-seo.css')) {
            $distDir = dirname(get_template_directory()) . '/dist/';
            $themeRootUri = dirname(get_template_directory_uri());
        }

        $stylePath = $distDir . 'styles/laca-seo.css';
        $styleUri  = $themeRootUri . '/dist/styles/laca-seo.css';
        if (file_exists($stylePath)) {
            wp_enqueue_style(
                'laca-seo-admin',
                $styleUri,
                ['dashicons'],
                (string) filemtime($stylePath)
            );
        }

        $scriptPath = $distDir . 'laca-seo.js';
        $scriptUri  = $themeRootUri . '/dist/laca-seo.js';
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
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ($_REQUEST['_wpnonce'] ?? '')));
            if (!wp_verify_nonce($nonce, 'laca_refresh_gsc_sites_action') && !wp_verify_nonce($nonce, 'laca_save_gsc_site_url_action')) {
                check_admin_referer('laca_refresh_gsc_sites_action');
            }
            if (!current_user_can('manage_options')) {
                wp_die(__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
            }
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

        // 5b. Refresh GA4 properties list
        if (isset($_POST['laca_refresh_ga_properties'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ($_REQUEST['_wpnonce'] ?? '')));
            if (!wp_verify_nonce($nonce, 'laca_refresh_ga_properties_action') && !wp_verify_nonce($nonce, 'laca_save_ga4_property_action')) {
                check_admin_referer('laca_refresh_ga_properties_action');
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
            }

            GaClient::clearPropertiesCache();
            $freshProps = GaClient::listProperties(true);
            if (is_array($freshProps)) {
                foreach ($freshProps as $prop) {
                    $numericId = $prop['id'] ?? $prop['propertyId'] ?? '';
                    if ($numericId !== '') {
                        delete_transient('laca_ga_streams_' . $numericId);
                    }
                }
            }

            wp_safe_redirect(add_query_arg(['refreshed_ga_props' => '1'], admin_url('admin.php?page=' . self::PAGE_ANALYTICS)));
            exit;
        }

        // 6. Save Google Analytics & GTM configuration form
        if (isset($_POST['laca_save_google_analytics'])) {
            check_admin_referer('laca_save_google_analytics_action');

            $measurementId = sanitize_text_field(wp_unslash($_POST['ga4_measurement_id'] ?? ''));
            $gtmId         = sanitize_text_field(wp_unslash($_POST['gtm_container_id'] ?? ''));
            $anonymize     = !empty($_POST['ga_anonymize_ip']) ? 'on' : 'off';
            $excludeAdmin  = !empty($_POST['ga_exclude_loggedin']) ? 'on' : 'off';
            $excludeRole   = sanitize_key(wp_unslash($_POST['ga_exclude_role'] ?? 'admin'));
            $omit404       = !empty($_POST['ga_omit_on_404']) ? 'on' : 'off';
            $ecommerce     = !empty($_POST['ga_ecommerce_events']) ? 'on' : 'off';
            $goalEvents    = sanitize_textarea_field(wp_unslash($_POST['ga4_goal_events'] ?? ''));
            $keyMetrics    = isset($_POST['key_metrics']) && is_array($_POST['key_metrics'])
                ? array_values(array_filter(array_map('sanitize_key', (array) $_POST['key_metrics'])))
                : [];

            GoogleServices::updateSettings([
                'ga4_measurement_id'  => $measurementId,
                'gtm_container_id'    => $gtmId,
                'ga_anonymize_ip'     => $anonymize,
                'ga_exclude_loggedin' => $excludeAdmin,
                'ga_exclude_role'     => $excludeRole,
                'ga_omit_on_404'      => $omit404,
                'ga_ecommerce_events' => $ecommerce,
                'ga4_goal_events'     => $goalEvents,
                'key_metrics'         => $keyMetrics,
            ]);

            wp_safe_redirect(add_query_arg(['updated' => '1'], admin_url('admin.php?page=' . self::PAGE_ANALYTICS)));
            exit;
        }

        // 6b. Check duplicate tags on website
        if (isset($_POST['laca_check_duplicate_tags'])) {
            check_admin_referer('laca_check_duplicate_tags_action');
            if (!current_user_can('manage_options')) {
                wp_die(__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
            }

            $res = Tracking::checkDuplicateTags();
            set_transient('laca_tag_check_result', $res, 600);

            wp_safe_redirect(add_query_arg(['tag_checked' => '1'], admin_url('admin.php?page=' . self::PAGE_ANALYTICS)));
            exit;
        }

        // 7. Save PageSpeed API Key
        if (isset($_POST['laca_save_pagespeed_api_key'])) {
            check_admin_referer('laca_save_pagespeed_api_key_action');
            if (!current_user_can('manage_options')) {
                wp_die(__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
            }

            $key = sanitize_text_field(wp_unslash($_POST['pagespeed_api_key'] ?? ''));
            if (!empty($key)) {
                PageSpeedClient::setApiKey($key);
                $arg = 'psi_updated';
            } else {
                $arg = 'updated';
            }

            $returnTo = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_SEARCH_CONSOLE));
            wp_safe_redirect(add_query_arg([$arg => '1'], admin_url('admin.php?page=' . $returnTo)));
            exit;
        }

        // 7b. Clear PageSpeed API Key
        if (isset($_POST['laca_clear_pagespeed_api_key'])) {
            check_admin_referer('laca_save_pagespeed_api_key_action');
            if (!current_user_can('manage_options')) {
                wp_die(__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
            }

            PageSpeedClient::setApiKey('');

            $returnTo = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_SEARCH_CONSOLE));
            wp_safe_redirect(add_query_arg(['psi_cleared' => '1'], admin_url('admin.php?page=' . $returnTo)));
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
        $excludeRole    = (string) ($options['ga_exclude_role'] ?? 'admin');
        $omit404        = ($options['ga_omit_on_404'] ?? 'on') === 'on';
        $ecommerce      = ($options['ga_ecommerce_events'] ?? 'off') === 'on';
        $tagCheck       = get_transient('laca_tag_check_result');
        $goalEvents     = (string) ($options['ga4_goal_events'] ?? '');

        // Auto-discover property and load stream info if connected with GA scope
        $gaProperties = [];
        $propStreamsMap = [];
        $matchedProp = null;
        $hostMismatchWarning = null;

        if ($isConnected && $hasGaScope) {
            $gaPropertiesResult = GaClient::listProperties();
            $gaProperties = is_wp_error($gaPropertiesResult) ? [] : $gaPropertiesResult;

            if (!empty($gaProperties)) {
                foreach ($gaProperties as $prop) {
                    $pid = (string) ($prop['propertyId'] ?? $prop['id'] ?? '');
                    if ($pid === '') {
                        continue;
                    }
                    $streams = GaClient::getDataStreams($pid);
                    $propStreamsMap[$pid] = is_array($streams) ? $streams : [];

                    if (!empty($currentMsId)) {
                        foreach ($propStreamsMap[$pid] as $stream) {
                            $mId = (string) ($stream['measurementId'] ?? '');
                            if ($mId !== '' && strcasecmp($mId, $currentMsId) === 0) {
                                $matchedProp = $prop;
                            }
                        }
                    }
                }
            }

            if (empty($currentPropId) && !empty($currentMsId) && $matchedProp) {
                $currentPropId = (string) ($matchedProp['propertyId'] ?? $matchedProp['id'] ?? '');
                if ($currentPropId !== '') {
                    GoogleServices::updateSettings(['ga4_property_id' => $currentPropId]);
                }
            }

            // Warning if Measurement ID belongs to a property whose displayName does not contain the site host
            if ($matchedProp) {
                $siteHost = (string) wp_parse_url(home_url(), PHP_URL_HOST);
                $cleanHost = preg_replace('/^www\./i', '', strtolower($siteHost));
                $domainName = explode('.', $cleanHost)[0] ?? '';

                $propDisplayName = (string) ($matchedProp['displayName'] ?? '');
                $propNorm = preg_replace('/[^a-z0-9]/', '', strtolower($propDisplayName));
                $hostNorm = preg_replace('/[^a-z0-9]/', '', strtolower($cleanHost));
                $domainNorm = preg_replace('/[^a-z0-9]/', '', strtolower($domainName));

                $containsHost = false;
                if ($cleanHost !== '' && stripos($propDisplayName, $cleanHost) !== false) {
                    $containsHost = true;
                } elseif ($hostNorm !== '' && str_contains($propNorm, $hostNorm)) {
                    $containsHost = true;
                } elseif ($domainNorm !== '' && strlen($domainNorm) >= 3 && str_contains($propNorm, $domainNorm)) {
                    $containsHost = true;
                }

                if (!$containsHost) {
                    $hostMismatchWarning = sprintf(
                        __("Mã đo lường đang nằm trong property '%s', kiểm tra lại có đúng website này không.", 'lacadev'),
                        $propDisplayName
                    );
                }
            }
        }

        ?>
        <div class="wrap laca-seo-wrap laca-admin-page laca-seo-view-general">
            <h1><?php echo esc_html__('Google Analytics & Tag Manager', 'lacadev'); ?></h1>

            <?php $this->renderNotices(); ?>
            <?php $this->renderSetupStepper(self::PAGE_ANALYTICS); ?>

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

            <!-- Card: Phát hiện tag trùng và mã ngoại lai -->
            <div class="laca-seo-card" style="margin-bottom: 24px;">
                <div class="laca-seo-card__header laca-seo-header-flex">
                    <div>
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Kiểm tra mã Google trên website (Tag Scanner)', 'lacadev'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Quét toàn bộ mã nguồn trang chủ để phát hiện mã trùng lặp, mã ngoại lai từ plugin khác hoặc mã sót trong theme.', 'lacadev'); ?></p>
                    </div>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('laca_check_duplicate_tags_action'); ?>
                        <button type="submit" name="laca_check_duplicate_tags" class="button button-secondary">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> <?php esc_html_e('Kiểm tra mã trên website', 'lacadev'); ?>
                        </button>
                    </form>
                </div>
                <?php if (!empty($tagCheck) && is_array($tagCheck)): ?>
                    <div style="padding: 16px 20px; border-top: 1px solid var(--laca-border, #e4e4e7); background: <?php echo !empty($tagCheck['hasWarnings']) ? 'var(--laca-warning-bg, #fffbeb)' : 'var(--laca-success-bg, #f0fdf4)'; ?>;">
                        <div style="font-weight: 600; margin-bottom: 6px; color: <?php echo !empty($tagCheck['hasWarnings']) ? '#92400e' : '#166534'; ?>;">
                            <?php echo !empty($tagCheck['hasWarnings']) ? '⚠️ ' . esc_html($tagCheck['message']) : '✓ ' . esc_html($tagCheck['message']); ?>
                        </div>
                        <div style="font-size: 13px; color: var(--laca-fg, #18181b);">
                            <strong><?php esc_html_e('Các mã tìm thấy:', 'lacadev'); ?></strong>
                            <?php if (!empty($tagCheck['foundIds'])): ?>
                                <code><?php echo esc_html(implode(', ', $tagCheck['foundIds'])); ?></code>
                            <?php else: ?>
                                <em><?php esc_html_e('Không tìm thấy mã nào.', 'lacadev'); ?></em>
                            <?php endif; ?>
                            <?php if (!empty($tagCheck['externalIds'])): ?>
                                <div style="margin-top: 6px; color: #dc2626;">
                                    <strong><?php esc_html_e('Mã ngoại lai (chưa khai báo trong cấu hình):', 'lacadev'); ?></strong>
                                    <code><?php echo esc_html(implode(', ', $tagCheck['externalIds'])); ?></code>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($tagCheck['duplicateIds'])): ?>
                                <div style="margin-top: 6px; color: #dc2626;">
                                    <strong><?php esc_html_e('Mã bị chèn lặp lại:', 'lacadev'); ?></strong>
                                    <code><?php echo esc_html(implode(', ', $tagCheck['duplicateIds'])); ?></code>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Hướng dẫn Google Analytics 4 (Google Cloud & GA) -->
            <?php GuideContent::renderForAnalytics(home_url()); ?>

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
                        <?php if (!empty($hostMismatchWarning)): ?>
                            <div class="notice notice-warning inline" style="margin: 0 0 16px 0; padding: 10px 14px; border-left-color: #f59e0b; background: #fffbeb;">
                                <p style="margin: 0; font-size: 13.5px; color: #92400e; line-height: 1.5;">
                                    <span class="dashicons dashicons-warning" style="color: #f59e0b; margin-top: 1px;"></span>
                                    <?php echo esc_html($hostMismatchWarning); ?>
                                </p>
                            </div>
                        <?php endif; ?>

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
                                            <?php
                                            $pid = (string) ($prop['propertyId'] ?? $prop['id'] ?? '');
                                            $streams = $propStreamsMap[$pid] ?? [];
                                            $streamLabels = [];
                                            $hasMatch = false;

                                            foreach ($streams as $str) {
                                                $strName = trim((string) ($str['displayName'] ?? ''));
                                                $strMid  = trim((string) ($str['measurementId'] ?? ''));

                                                if ($strName !== '' && $strMid !== '') {
                                                    $streamLabels[] = $strName . ' ' . $strMid;
                                                } elseif ($strMid !== '') {
                                                    $streamLabels[] = $strMid;
                                                } elseif ($strName !== '') {
                                                    $streamLabels[] = $strName;
                                                }

                                                if (!empty($currentMsId) && !empty($strMid) && strcasecmp($strMid, $currentMsId) === 0) {
                                                    $hasMatch = true;
                                                }
                                            }

                                            $streamSuffix = !empty($streamLabels) ? ' — luồng: ' . implode(', ', $streamLabels) : '';
                                            $matchSuffix  = $hasMatch ? ' (✓ khớp mã đo lường)' : '';
                                            $optionLabel  = $prop['displayName'] . ' (' . $pid . ')' . $streamSuffix . $matchSuffix;
                                            ?>
                                            <option value="<?php echo esc_attr($pid); ?>" <?php selected($currentPropId, $pid); ?>>
                                                <?php echo esc_html($optionLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="laca_save_ga4_property" class="button button-secondary">
                                <?php esc_html_e('Lưu thuộc tính GA4', 'lacadev'); ?>
                            </button>
                            <button type="submit" name="laca_refresh_ga_properties" class="button button-secondary" title="<?php esc_attr_e('Tải lại danh sách thuộc tính từ Google Analytics', 'lacadev'); ?>">
                                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> <?php esc_html_e('Tải lại danh sách', 'lacadev'); ?>
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
                                <input type="text" name="ga4_measurement_id" id="ga4_measurement_id" class="regular-text laca-seo-input" value="<?php echo esc_attr($currentMsId); ?>" placeholder="G-XXXXXXXXXX" style="width: 100%; max-width: 560px;">
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
                                <input type="text" name="gtm_container_id" id="gtm_container_id" class="regular-text laca-seo-input" value="<?php echo esc_attr($gtmId); ?>" placeholder="GTM-XXXXXXX" style="width: 100%; max-width: 560px;">
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
                                <input type="hidden" name="ga_anonymize_ip" value="off">
                                <label class="laca-seo-switch">
                                    <input type="checkbox" role="switch" name="ga_anonymize_ip" id="ga_anonymize_ip" value="on" <?php checked($anonymize); ?>>
                                    <span class="laca-seo-switch__slider"></span>
                                </label>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Ẩn octet cuối của địa chỉ IP người dùng trước khi gửi đến Google Analytics (khuyến nghị bật để tuân thủ quyền riêng tư / GDPR).', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 4: Exclude Logged-in Users -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga_exclude_loggedin" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Bỏ qua người dùng đăng nhập', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <input type="hidden" name="ga_exclude_loggedin" value="off">
                                <label class="laca-seo-switch">
                                    <input type="checkbox" role="switch" name="ga_exclude_loggedin" id="ga_exclude_loggedin" value="on" <?php checked($excludeAdmin); ?>>
                                    <span class="laca-seo-switch__slider"></span>
                                </label>
                                <div style="margin-top: 10px; display: flex; gap: 16px;">
                                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
                                        <input type="radio" name="ga_exclude_role" value="admin" <?php checked($excludeRole, 'admin'); ?>>
                                        <?php esc_html_e('Chỉ Quản trị viên (Administrator, mặc định)', 'lacadev'); ?>
                                    </label>
                                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
                                        <input type="radio" name="ga_exclude_role" value="all" <?php checked($excludeRole, 'all'); ?>>
                                        <?php esc_html_e('Mọi người dùng đã đăng nhập', 'lacadev'); ?>
                                    </label>
                                </div>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Chuẩn Site Kit: Tự động gắn window[\'ga-disable-<ID>\'] = true để không ghi nhận truy cập mà vẫn giữ nguyên thẻ script cho các công cụ xác minh.', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 5: Tag guard 404 -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga_omit_on_404" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Bảo vệ trang 404 (Tag Guard)', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <input type="hidden" name="ga_omit_on_404" value="off">
                                <label class="laca-seo-switch">
                                    <input type="checkbox" role="switch" name="ga_omit_on_404" id="ga_omit_on_404" value="on" <?php checked($omit404); ?>>
                                    <span class="laca-seo-switch__slider"></span>
                                </label>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Không xuất mã đo lường ở các trang lỗi 404 để tránh làm loãng dữ liệu phiên truy cập và giảm thiểu request vô ích.', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 6: WooCommerce Ecommerce Events -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga_ecommerce_events" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Sự kiện WooCommerce', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <input type="hidden" name="ga_ecommerce_events" value="off">
                                <label class="laca-seo-switch">
                                    <input type="checkbox" role="switch" name="ga_ecommerce_events" id="ga_ecommerce_events" value="on" <?php checked($ecommerce); ?>>
                                    <span class="laca-seo-switch__slider"></span>
                                </label>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Tự động gửi sự kiện thêm vào giỏ hàng (add_to_cart) và mua hàng thành công (purchase) với cơ chế chống gửi lặp khi tải lại trang cảm ơn.', 'lacadev'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 7: Key Events / Goal Events -->
                        <div class="laca-seo-field-row">
                            <div class="laca-seo-field-row__label-col">
                                <label for="ga4_goal_events" style="font-weight: 600; color: var(--laca-fg);">
                                    <?php esc_html_e('Mục tiêu chuyển đổi (Key Events)', 'lacadev'); ?>
                                </label>
                            </div>
                            <div class="laca-seo-field-row__control-col">
                                <textarea name="ga4_goal_events" id="ga4_goal_events" rows="4" class="regular-text laca-seo-input" style="width: 100%; max-width: 560px; font-family: monospace; font-size: 13px;"><?php echo esc_textarea($goalEvents); ?></textarea>
                                <p class="description laca-seo-field-desc">
                                    <?php esc_html_e('Mỗi dòng một mục tiêu theo định dạng: event_name|Nhãn hiển thị. Ví dụ:', 'lacadev'); ?><br>
                                    <code>purchase|Đơn hàng</code><br>
                                    <code>generate_lead|Đăng ký tư vấn</code>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Chỉ số chính (Key Metrics) cho Báo cáo -->
                <?php
                $activeKeyMetrics = GoogleServices::getActiveKeyMetrics();
                $hasProductPostType = post_type_exists('product') || apply_filters('laca/google/has_product_post_type', false);
                $hasKeyEvents = !empty($goalEvents) || apply_filters('laca/google/has_key_events', false);
                $hasEcommerce = ($ecommerce === 'on') || apply_filters('laca/google/has_ecommerce_events', false);

                $allMetrics = [
                    'new_vs_returning' => [
                        'label'     => __('Khách mới / Quay lại (newVsReturning)', 'lacadev'),
                        'desc'      => __('Tỷ lệ phần trăm và số lượng người dùng mới so với người dùng quay lại.', 'lacadev'),
                        'available' => true,
                    ],
                    'top_traffic_source' => [
                        'label'     => __('Nguồn hàng đầu (Top Traffic Source)', 'lacadev'),
                        'desc'      => __('Kênh mang lại lượng phiên truy cập cao nhất cho website.', 'lacadev'),
                        'available' => true,
                    ],
                    'top_cities' => [
                        'label'     => __('Thành phố top 3 (Top Cities)', 'lacadev'),
                        'desc'      => __('3 khu vực / thành phố có lượt truy cập nhiều nhất.', 'lacadev'),
                        'available' => true,
                    ],
                    'pages_per_session' => [
                        'label'     => __('Trang / Lượt (screenPageViewsPerSession)', 'lacadev'),
                        'desc'      => __('Số trang trung bình người dùng xem trong mỗi phiên truy cập.', 'lacadev'),
                        'available' => true,
                    ],
                    'session_duration' => [
                        'label'     => __('Thời lượng trung bình (averageSessionDuration)', 'lacadev'),
                        'desc'      => __('Thời gian trung bình người dùng tương tác trên website.', 'lacadev'),
                        'available' => true,
                    ],
                    'top_products' => [
                        'label'     => __('Sản phẩm xem nhiều (Top Viewed Products)', 'lacadev'),
                        'desc'      => __('Trang sản phẩm có lượt xem cao nhất (đường dẫn /product/ hoặc /san-pham/).', 'lacadev'),
                        'available' => $hasProductPostType,
                        'reason'    => __('Yêu cầu website có post type sản phẩm (product)', 'lacadev'),
                    ],
                    'best_conversion_source' => [
                        'label'     => __('Nguồn chuyển đổi tốt nhất (Best Conversion Source)', 'lacadev'),
                        'desc'      => __('Kênh truy cập có tỷ lệ kích hoạt sự kiện mục tiêu cao nhất.', 'lacadev'),
                        'available' => $hasKeyEvents,
                        'reason'    => __('Yêu cầu cấu hình ít nhất 1 Mục tiêu chuyển đổi (Key Event)', 'lacadev'),
                    ],
                    'cart_or_purchase_source' => [
                        'label'     => __('Nguồn thêm giỏ / Mua hàng (Cart & Purchase Source)', 'lacadev'),
                        'desc'      => __('Kênh mang lại nhiều lượt thêm vào giỏ hàng hoặc mua hàng nhất.', 'lacadev'),
                        'available' => $hasEcommerce,
                        'reason'    => __('Yêu cầu bật Theo dõi Sự kiện WooCommerce hoặc có sự kiện trong 90 ngày', 'lacadev'),
                    ],
                ];
                ?>
                <div class="laca-seo-card" style="margin-top: 24px;">
                    <div class="laca-seo-card__header">
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Chỉ số chính cho Cửa hàng (Key Metrics)', 'lacadev'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Tùy chọn hiển thị 8 ô chỉ số trọng điểm trên báo cáo Google Analytics. Các ô thiếu điều kiện dữ liệu sẽ tự động ẩn trên Dashboard.', 'lacadev'); ?></p>
                    </div>

                    <div class="laca-seo-fields-container" style="padding: 0 20px;">
                        <?php foreach ($allMetrics as $mKey => $mInfo): ?>
                            <?php $isChecked = in_array($mKey, $activeKeyMetrics, true); ?>
                            <div class="laca-seo-field-row">
                                <div class="laca-seo-field-row__label-col">
                                    <label for="metric_<?php echo esc_attr($mKey); ?>" style="font-weight: 600; color: var(--laca-fg);">
                                        <?php echo esc_html($mInfo['label']); ?>
                                    </label>
                                </div>
                                <div class="laca-seo-field-row__control-col">
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="key_metrics[]" id="metric_<?php echo esc_attr($mKey); ?>" value="<?php echo esc_attr($mKey); ?>" <?php checked($isChecked); ?>>
                                        <span><?php esc_html_e('Bật hiển thị ô chỉ số này', 'lacadev'); ?></span>
                                    </label>
                                    <p class="description laca-seo-field-desc" style="margin: 4px 0 0 0;">
                                        <?php echo esc_html($mInfo['desc']); ?>
                                    </p>
                                    <?php if (!$mInfo['available'] && !empty($mInfo['reason'])): ?>
                                        <div style="font-size: 12px; color: var(--laca-fg-muted); margin-top: 4px;">
                                            ⚠️ <em><?php echo esc_html($mInfo['reason']); ?></em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
            <?php $this->renderSetupStepper(self::PAGE_SEARCH_CONSOLE); ?>

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
                    <?php GuideContent::renderForSearchConsole(home_url(), $redirectUri, GscOAuth::getLegacyRedirectUri()); ?>

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

            <!-- Card: PageSpeed Insights API Key (Tuỳ chọn) -->
            <div class="laca-card" id="laca-psi-key" style="margin-top: 24px;">
                <div class="laca-card__header">
                    <h2 class="laca-card__title" style="display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-performance" style="font-size: 20px; width: 20px; height: 20px; color: var(--laca-fg, #18181b);" aria-hidden="true"></span>
                        <?php esc_html_e('Google PageSpeed Insights API Key (Tùy chọn)', 'lacadev'); ?>
                    </h2>
                </div>
                <div class="laca-card__body">
                    <p style="color: var(--laca-fg-muted, #71717a); font-size: 13px; margin: 0 0 16px 0;">
                        <?php esc_html_e('Không bắt buộc: PageSpeed Insights API v5 có thể kiểm tra hiệu suất ẩn danh. Thêm API Key từ Google Cloud Console để tăng giới hạn truy vấn (quota). Khóa API được lưu mã hóa an toàn và không bao giờ in trực tiếp ra mã HTML.', 'lacadev'); ?>
                    </p>
                    <form method="post" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        <?php wp_nonce_field('laca_save_pagespeed_api_key_action'); ?>
                        <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_SEARCH_CONSOLE); ?>">
                        <div style="flex: 1; min-width: 320px;">
                            <label for="pagespeed_api_key_input" style="display: block; font-weight: 600; margin-bottom: 6px;">
                                <?php esc_html_e('PageSpeed Insights API Key:', 'lacadev'); ?>
                                <?php if (PageSpeedClient::hasApiKey()): ?>
                                    <span class="laca-badge laca-badge--success" style="margin-left: 8px; font-weight: normal; font-size: 11px;">
                                        ✓ <?php esc_html_e('Đã cấu hình & mã hóa an toàn', 'lacadev'); ?>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <input type="password" id="pagespeed_api_key_input" name="pagespeed_api_key" value="" placeholder="<?php echo PageSpeedClient::hasApiKey() ? '••••••••••••••••••••••••••••••••' : esc_attr__('AIzaSy...', 'lacadev'); ?>" autocomplete="off" style="width: 100%; max-width: 560px;">
                        </div>
                        <button type="submit" name="laca_save_pagespeed_api_key" class="button button-secondary">
                            <?php echo PageSpeedClient::hasApiKey() ? esc_html__('Cập nhật khóa mới', 'lacadev') : esc_html__('Lưu API Key', 'lacadev'); ?>
                        </button>
                        <?php if (PageSpeedClient::hasApiKey()): ?>
                            <button type="submit" name="laca_clear_pagespeed_api_key" class="button button-link-delete" style="color: #b32d2e; text-decoration: none; padding: 4px 8px;" onclick="return confirm('<?php echo esc_js(__('Bạn có chắc chắn muốn xóa khóa PageSpeed API?', 'lacadev')); ?>');">
                                <?php esc_html_e('Xóa khóa API', 'lacadev'); ?>
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
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
        if (isset($_GET['psi_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã lưu khóa API PageSpeed Insights (đã mã hóa an toàn).', 'lacadev') . '</p></div>';
        }
        if (isset($_GET['psi_cleared'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã xóa khóa API PageSpeed Insights.', 'lacadev') . '</p></div>';
        }
        if (isset($_GET['refreshed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã làm mới danh sách property từ Google Search Console.', 'lacadev') . '</p></div>';
        }
        if (isset($_GET['refreshed_ga_props'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã làm mới danh sách thuộc tính.', 'lacadev') . '</p></div>';
        }
        if (isset($_GET['tag_checked'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Đã hoàn thành kiểm tra mã Google trên website.', 'lacadev') . '</p></div>';
        }
        if (!empty($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(wp_unslash($_GET['error'])) . '</p></div>';
        }
    }

    /**
     * Render 4-step setup status stepper for Google Services.
     */
    public function renderSetupStepper(string $activePage): void
    {
        $state = GoogleServices::setupState();

        $step1Complete   = !empty($state['connected']);
        $step2Complete   = (($state['gsc']['status'] ?? '') === 'complete');
        $step3Complete   = (($state['ga']['status'] ?? '') === 'complete');
        $step4Complete   = !empty($state['data']['gsc_available']) && !empty($state['data']['ga_available']);
        $step4Collecting = ($step2Complete || $step3Complete) && !$step4Complete;

        ?>
        <div class="laca-seo-card laca-google-stepper-card" style="margin-bottom: 24px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--laca-border, #e4e4e7); padding-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <div>
                    <h3 style="margin: 0; font-size: 15px; font-weight: 600; color: var(--laca-fg, #18181b); display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-forms" style="font-size: 18px; width: 18px; height: 18px; color: var(--laca-accent, #2563eb);"></span>
                        <?php esc_html_e('Trạng thái thiết lập Dịch vụ Google', 'lacadev'); ?>
                    </h3>
                    <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--laca-fg-muted, #71717a);">
                        <?php esc_html_e('Hoàn tất 4 bước bên dưới để kích hoạt báo cáo Search Console và Google Analytics 4 trên Bảng điều khiển WordPress.', 'lacadev'); ?>
                    </p>
                </div>
                <div>
                    <?php if (!empty($state['is_all_complete'])): ?>
                        <span class="laca-badge" style="background: #10b981; color: #fff; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 500;">
                            ✓ <?php esc_html_e('Đã thiết lập đầy đủ', 'lacadev'); ?>
                        </span>
                    <?php elseif (!empty($state['is_any_complete'])): ?>
                        <span class="laca-badge" style="background: #f59e0b; color: #fff; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 500;">
                            ! <?php esc_html_e('Đã kết nối một phần', 'lacadev'); ?>
                        </span>
                    <?php else: ?>
                        <span class="laca-badge" style="background: var(--laca-bg-subtle, #f4f4f5); color: var(--laca-fg-muted, #71717a); padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 500; border: 1px solid var(--laca-border, #e4e4e7);">
                            <?php esc_html_e('Chưa thiết lập', 'lacadev'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <!-- Bước 1: Tài khoản Google -->
                <div style="padding: 14px; border: 1px solid <?php echo $step1Complete ? '#bbf7d0' : '#fed7aa'; ?>; background: <?php echo $step1Complete ? '#f0fdf4' : '#fffbeb'; ?>; border-radius: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo $step1Complete ? '#166534' : '#9a3412'; ?>;">
                            <?php esc_html_e('Bước 1', 'lacadev'); ?>
                        </span>
                        <span class="dashicons <?php echo $step1Complete ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size: 16px; width: 16px; height: 16px; color: <?php echo $step1Complete ? '#10b981' : '#f59e0b'; ?>;"></span>
                    </div>
                    <strong style="display: block; font-size: 13.5px; color: #18181b; margin-bottom: 4px;">
                        <?php esc_html_e('Kết nối Google (OAuth)', 'lacadev'); ?>
                    </strong>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: <?php echo $step1Complete ? '#15803d' : '#71717a'; ?>; min-height: 36px;">
                        <?php if ($step1Complete): ?>
                            <?php echo esc_html($state['email'] ? $state['email'] : __('Đã kết nối OAuth', 'lacadev')); ?>
                        <?php else: ?>
                            <?php esc_html_e('Chưa kết nối tài khoản Google Cloud OAuth.', 'lacadev'); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($step1Complete): ?>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('laca_google_switch_account_action'); ?>
                            <input type="hidden" name="return_to" value="<?php echo esc_attr($activePage); ?>">
                            <button type="submit" name="laca_google_switch_account" class="button button-small" style="font-size: 11.5px;">
                                <?php esc_html_e('Đổi tài khoản', 'lacadev'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE . '#laca-oauth-card')); ?>" class="button button-small button-primary" style="font-size: 11.5px;">
                            <?php esc_html_e('Kết nối ngay', 'lacadev'); ?> &rarr;
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Bước 2: Search Console -->
                <div style="padding: 14px; border: 1px solid <?php echo $step2Complete ? '#bbf7d0' : (($state['gsc']['status'] ?? '') === 'error' ? '#fecaca' : '#e4e4e7'); ?>; background: <?php echo $step2Complete ? '#f0fdf4' : (($state['gsc']['status'] ?? '') === 'error' ? '#fef2f2' : '#fafafa'); ?>; border-radius: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo $step2Complete ? '#166534' : '#52525b'; ?>;">
                            <?php esc_html_e('Bước 2', 'lacadev'); ?>
                        </span>
                        <span class="dashicons <?php echo $step2Complete ? 'dashicons-yes-alt' : (($state['gsc']['status'] ?? '') === 'error' ? 'dashicons-dismiss' : 'dashicons-marker'); ?>" style="font-size: 16px; width: 16px; height: 16px; color: <?php echo $step2Complete ? '#10b981' : (($state['gsc']['status'] ?? '') === 'error' ? '#ef4444' : '#a1a1aa'); ?>;"></span>
                    </div>
                    <strong style="display: block; font-size: 13.5px; color: #18181b; margin-bottom: 4px;">
                        <?php esc_html_e('Search Console Property', 'lacadev'); ?>
                    </strong>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: <?php echo $step2Complete ? '#15803d' : '#71717a'; ?>; min-height: 36px; word-break: break-all;">
                        <?php if ($step2Complete): ?>
                            <code><?php echo esc_html($state['gsc']['property'] ?? ''); ?></code>
                        <?php else: ?>
                            <?php echo esc_html($state['gsc']['reason'] ?? __('Chưa chọn property Search Console.', 'lacadev')); ?>
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)); ?>" class="button button-small <?php echo $step2Complete ? '' : 'button-primary'; ?>" style="font-size: 11.5px;">
                        <?php echo $step2Complete ? esc_html__('Đổi property', 'lacadev') : esc_html__('Chọn property', 'lacadev') . ' &rarr;'; ?>
                    </a>
                </div>

                <!-- Bước 3: GA4 & Mã đo lường -->
                <div style="padding: 14px; border: 1px solid <?php echo $step3Complete ? '#bbf7d0' : (($state['ga']['status'] ?? '') === 'error' ? '#fecaca' : '#e4e4e7'); ?>; background: <?php echo $step3Complete ? '#f0fdf4' : (($state['ga']['status'] ?? '') === 'error' ? '#fef2f2' : '#fafafa'); ?>; border-radius: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo $step3Complete ? '#166534' : '#52525b'; ?>;">
                            <?php esc_html_e('Bước 3', 'lacadev'); ?>
                        </span>
                        <span class="dashicons <?php echo $step3Complete ? 'dashicons-yes-alt' : (($state['ga']['status'] ?? '') === 'error' ? 'dashicons-dismiss' : 'dashicons-marker'); ?>" style="font-size: 16px; width: 16px; height: 16px; color: <?php echo $step3Complete ? '#10b981' : (($state['ga']['status'] ?? '') === 'error' ? '#ef4444' : '#a1a1aa'); ?>;"></span>
                    </div>
                    <strong style="display: block; font-size: 13.5px; color: #18181b; margin-bottom: 4px;">
                        <?php esc_html_e('GA4 & Khớp mã đo lường', 'lacadev'); ?>
                    </strong>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: <?php echo $step3Complete ? '#15803d' : '#71717a'; ?>; min-height: 36px; word-break: break-all;">
                        <?php if ($step3Complete): ?>
                            ID: <code><?php echo esc_html($state['ga']['property_id'] ?? ''); ?></code><br>
                            Luồng: <code><?php echo esc_html($state['ga']['measurement_id'] ?? ''); ?></code>
                        <?php else: ?>
                            <?php echo esc_html($state['ga']['reason'] ?? __('Chưa cấu hình thuộc tính GA4.', 'lacadev')); ?>
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_ANALYTICS)); ?>" class="button button-small <?php echo $step3Complete ? '' : 'button-primary'; ?>" style="font-size: 11.5px;">
                        <?php echo $step3Complete ? esc_html__('Đổi thuộc tính', 'lacadev') : esc_html__('Cấu hình GA4', 'lacadev') . ' &rarr;'; ?>
                    </a>
                </div>

                <!-- Bước 4: Trạng thái Dữ liệu -->
                <div style="padding: 14px; border: 1px solid <?php echo $step4Complete ? '#bbf7d0' : ($step4Collecting ? '#bfdbfe' : '#e4e4e7'); ?>; background: <?php echo $step4Complete ? '#f0fdf4' : ($step4Collecting ? '#eff6ff' : '#fafafa'); ?>; border-radius: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo $step4Complete ? '#166534' : ($step4Collecting ? '#1e40af' : '#71717a'); ?>;">
                            <?php esc_html_e('Bước 4', 'lacadev'); ?>
                        </span>
                        <span class="dashicons <?php echo $step4Complete ? 'dashicons-yes-alt' : ($step4Collecting ? 'dashicons-update' : 'dashicons-clock'); ?>" style="font-size: 16px; width: 16px; height: 16px; color: <?php echo $step4Complete ? '#10b981' : ($step4Collecting ? '#3b82f6' : '#a1a1aa'); ?>;"></span>
                    </div>
                    <strong style="display: block; font-size: 13.5px; color: #18181b; margin-bottom: 4px;">
                        <?php esc_html_e('Dữ liệu báo cáo', 'lacadev'); ?>
                    </strong>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: <?php echo $step4Complete ? '#15803d' : ($step4Collecting ? '#1e40af' : '#71717a'); ?>; min-height: 36px;">
                        <?php if ($step4Complete): ?>
                            <?php esc_html_e('Đã có dữ liệu báo cáo từ Google.', 'lacadev'); ?>
                        <?php elseif ($step4Collecting): ?>
                            <?php esc_html_e('Đang thu thập dữ liệu (Google cần 24–48 giờ).', 'lacadev'); ?>
                        <?php else: ?>
                            <?php esc_html_e('Chờ hoàn tất cấu hình các bước trước.', 'lacadev'); ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($state['is_any_complete'])): ?>
                        <a href="<?php echo esc_url(admin_url('index.php#laca-google-report')); ?>" class="button button-small" style="font-size: 11.5px;">
                            <span class="dashicons dashicons-chart-area" style="font-size: 14px; width: 14px; height: 14px; margin-top: 3px;"></span>
                            <?php esc_html_e('Xem trên Dashboard', 'lacadev'); ?>
                        </a>
                    <?php else: ?>
                        <button type="button" class="button button-small" disabled style="font-size: 11.5px;">
                            <?php esc_html_e('Chưa sẵn sàng', 'lacadev'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
