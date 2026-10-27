<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Core orchestrator for Google Services (GSC & GA4).
 * Completely standalone from Laca SEO and Rank Math.
 */
class GoogleServices
{
    public const OPTION_KEY = 'laca_google_services';
    public const LEGACY_SETTINGS_KEY = 'laca_seo_general';
    public const LEGACY_OAUTH_KEY = 'laca_seo_gsc';
    public const MIGRATED_FLAG = 'laca_google_services_migrated';

    /**
     * Allowed settings keys for laca_google_services option.
     */
    public const SETTINGS_KEYS = [
        'ga4_measurement_id',
        'gtm_container_id',
        'ga_anonymize_ip',
        'ga_exclude_loggedin',
        'ga_exclude_role',
        'ga_omit_on_404',
        'ga_ecommerce_events',
        'ga4_property_id',
        'ga4_goal_events',
        'gsc_site_url',
        'pagespeed_api_key',
        'key_metrics',
    ];

    /**
     * Boot Google Services features.
     */
    public static function boot(): void
    {
        // One-time migration on admin_init
        add_action('admin_init', [self::class, 'migrate']);

        // Legacy redirect for old Search Console URL
        add_action('admin_init', [self::class, 'handleLegacyRedirect']);

        // Register frontend tracking
        Tracking::register();

        // Register entity stats (metabox & admin bar)
        EntityStats::register();

        if (is_admin()) {
            (new \App\Settings\LacaAdmin\GoogleServicesPage())->register();
            add_action('admin_notices', [self::class, 'renderDashboardNotice']);
            add_action('admin_enqueue_scripts', [self::class, 'enqueueDashboardAssets']);
        }

        // AJAX handlers for dashboard report
        add_action('wp_ajax_laca_google_report', [self::class, 'ajaxGoogleReport']);
        add_action('wp_ajax_laca_ga4_realtime', [self::class, 'ajaxGa4Realtime']);
        add_action('wp_ajax_laca_pagespeed_run', [self::class, 'ajaxPageSpeedRun']);
        add_action('wp_ajax_laca_gsc_queries', [self::class, 'ajaxGscQueries']);
    }

    /**
     * One-time copy migration from legacy Laca SEO options.
     * Does NOT delete legacy options.
     */
    public static function migrate(): void
    {
        if (get_option(self::MIGRATED_FLAG)) {
            return;
        }

        $currentSettings = get_option(self::OPTION_KEY, []);
        if (!is_array($currentSettings)) {
            $currentSettings = [];
        }

        $legacySettings = get_option(self::LEGACY_SETTINGS_KEY, []);
        if (is_array($legacySettings)) {
            foreach (self::SETTINGS_KEYS as $key) {
                if (!isset($currentSettings[$key]) && isset($legacySettings[$key])) {
                    $currentSettings[$key] = $legacySettings[$key];
                }
            }
            update_option(self::OPTION_KEY, $currentSettings, false);
        }

        // Copy OAuth credentials if new option does not exist yet or lacks valid tokens
        $oauthCurrent = get_option(GscOAuth::OPTION_KEY, null);
        $currentCreds = (is_string($oauthCurrent) && $oauthCurrent !== '') ? GscCrypto::decrypt($oauthCurrent, true) : null;
        $hasCurrentTokens = is_array($currentCreds) && (!empty($currentCreds['access_token']) || !empty($currentCreds['refresh_token']));

        if (!$hasCurrentTokens) {
            $oauthLegacy = get_option(self::LEGACY_OAUTH_KEY, null);
            if (is_string($oauthLegacy) && $oauthLegacy !== '') {
                $legacyCreds = GscCrypto::decrypt($oauthLegacy, true);
                if (is_array($legacyCreds) && (!empty($legacyCreds['access_token']) || !empty($legacyCreds['refresh_token']))) {
                    update_option(GscOAuth::OPTION_KEY, $oauthLegacy, false);
                }
            }
        }

        update_option(self::MIGRATED_FLAG, 1, false);
    }

    /**
     * Redirect legacy laca-seo&view=search-console visits to Laca Admin Google Services.
     */
    public static function handleLegacyRedirect(): void
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';

        // If this is the legacy Search Console page without OAuth callback params, redirect
        if ($page === 'laca-seo' && $view === 'search-console') {
            if (empty($_GET['laca_gsc_oauth']) && empty($_GET['laca_google_oauth']) && empty($_GET['code'])) {
                wp_safe_redirect(admin_url('admin.php?page=laca-google-search-console'));
                exit;
            }
        }
    }

    /**
     * Get setting value from laca_google_services option.
     */
    public static function getOption(string $key = '', mixed $default = null): mixed
    {
        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        if ($key === '') {
            return $options;
        }

        return $options[$key] ?? $default;
    }

    /**
     * Update settings in laca_google_services option.
     *
     * @param array<string, mixed> $settings
     */
    public static function updateSettings(array $settings): bool
    {
        $current = get_option(self::OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }

        foreach (self::SETTINGS_KEYS as $key) {
            if (array_key_exists($key, $settings)) {
                if ($key === 'key_metrics') {
                    $current[$key] = is_array($settings[$key]) ? array_values(array_filter(array_map('sanitize_key', $settings[$key]))) : [];
                } else {
                    $current[$key] = $settings[$key];
                }
            }
        }

        return update_option(self::OPTION_KEY, $current, false);
    }

    public const KEY_METRICS_MAP = [
        'newVsReturning'       => ['new_vs_returning', 'newvsreturning'],
        'topTrafficSource'     => ['topSource', 'top_traffic_source', 'topsource', 'top_source'],
        'topCities'            => ['top_cities', 'topcities'],
        'pagesPerSession'      => ['pages_per_session', 'pagespersession'],
        'sessionDuration'      => ['session_duration', 'sessionduration'],
        'topProducts'          => ['topProduct', 'top_products', 'topproduct', 'topproducts', 'top_product'],
        'bestConversionSource' => ['best_conversion_source', 'bestconversionsource'],
        'addToCartSource'      => ['cartAndPurchaseSource', 'cart_or_purchase_source', 'cartandpurchasesource', 'cart_and_purchase_source', 'add_to_cart_source'],
    ];

    /**
     * Default enabled key metrics cards (first 6).
     *
     * @return string[]
     */
    public static function getDefaultKeyMetrics(): array
    {
        return [
            'newVsReturning', 'new_vs_returning',
            'topTrafficSource', 'topSource', 'top_traffic_source',
            'topCities', 'top_cities',
            'pagesPerSession', 'pages_per_session',
            'sessionDuration', 'session_duration',
            'topProducts', 'topProduct', 'top_products',
        ];
    }

    /**
     * Get active key metrics keys configured in settings.
     *
     * @return string[]
     */
    public static function getActiveKeyMetrics(): array
    {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts) || !isset($opts['key_metrics'])) {
            return self::getDefaultKeyMetrics();
        }

        if (!is_array($opts['key_metrics'])) {
            return [];
        }

        $active = [];
        foreach ($opts['key_metrics'] as $rawKey) {
            $k = (string) $rawKey;
            $clean = strtolower(str_replace(['-', '_'], '', $k));
            foreach (self::KEY_METRICS_MAP as $canonical => $aliases) {
                if (strcasecmp($k, $canonical) === 0 || $clean === strtolower(str_replace(['-', '_'], '', $canonical))) {
                    $active[] = $canonical;
                    $active[] = $aliases[0];
                    break;
                }
                foreach ($aliases as $al) {
                    if (strcasecmp($k, $al) === 0 || $clean === strtolower(str_replace(['-', '_'], '', $al))) {
                        $active[] = $canonical;
                        $active[] = $aliases[0];
                        break 2;
                    }
                }
            }
        }

        return array_values(array_unique($active));
    }

    /**
     * Get capability required to view Google Services reports.
     * Filterable via 'laca/google/report_capability'. Default is 'manage_options'.
     */
    public static function getReportCapability(): string
    {
        return (string) apply_filters('laca/google/report_capability', 'manage_options');
    }

    /**
     * Check if current user has capability to view Google Services reports.
     */
    public static function userCanViewReport(): bool
    {
        $cap = self::getReportCapability();
        $hasCap = current_user_can($cap);
        if (!$hasCap && class_exists('\App\Features\LacaSeo\Auth\CapabilityManager')) {
            $hasCap = \App\Features\LacaSeo\Auth\CapabilityManager::hasCap('site_analysis');
        }

        return (bool) $hasCap;
    }

    /**
     * Check setup state for Google Services (GSC & GA4) following Site Kit principles.
     *
     * @return array{
     *     connected: bool,
     *     email: string|null,
     *     gsc: array{status: string, reason?: string, code?: int, property: string},
     *     ga: array{status: string, reason?: string, code?: int, property_id: string, measurement_id: string},
     *     data: array{gsc_available: bool, ga_available: bool, collecting: bool},
     *     is_any_complete: bool,
     *     is_all_complete: bool,
     * }
     */
    public static function setupState(): array
    {
        $isConnected    = GscOAuth::isConnected();
        $connectedEmail = $isConnected ? GscOAuth::getConnectedEmail() : null;
        $opts           = self::getOption();
        if (!is_array($opts)) {
            $opts = [];
        }

        // 1. Google Search Console setup check
        $gscProp    = (string) ($opts['gsc_property'] ?? ($opts['gsc_site_url'] ?? ''));
        $gscLastErr = get_transient('laca_gsc_last_error_code');

        if (!$isConnected || !GscOAuth::hasGscScope()) {
            $gscState = [
                'status'   => 'incomplete',
                'reason'   => __('Chưa kết nối tài khoản Google hoặc thiếu quyền Search Console.', 'lacadev'),
                'property' => $gscProp,
            ];
        } elseif (empty($gscProp)) {
            $gscState = [
                'status'   => 'incomplete',
                'reason'   => __('Chưa chọn property Search Console.', 'lacadev'),
                'property' => '',
            ];
        } elseif ($gscLastErr && in_array((int) $gscLastErr, [401, 403, 404], true)) {
            $gscState = [
                'status'   => 'error',
                'code'     => (int) $gscLastErr,
                'reason'   => sprintf(__('Lỗi API Search Console (%d)', 'lacadev'), (int) $gscLastErr),
                'property' => $gscProp,
            ];
        } else {
            $gscState = [
                'status'   => 'complete',
                'property' => $gscProp,
            ];
        }

        // 2. Google Analytics 4 setup check
        $gaPropId  = (string) ($opts['ga4_property_id'] ?? '');
        $gaMsId    = (string) ($opts['ga4_measurement_id'] ?? '');
        $gaLastErr = get_transient('laca_ga_last_error_code');

        if (!$isConnected || !GscOAuth::hasAnalyticsScope()) {
            $gaState = [
                'status'         => 'incomplete',
                'reason'         => __('Chưa kết nối tài khoản Google hoặc thiếu quyền Google Analytics.', 'lacadev'),
                'property_id'    => $gaPropId,
                'measurement_id' => $gaMsId,
            ];
        } elseif (empty($gaPropId)) {
            $gaState = [
                'status'         => 'incomplete',
                'reason'         => __('Chưa chọn thuộc tính GA4.', 'lacadev'),
                'property_id'    => '',
                'measurement_id' => $gaMsId,
            ];
        } else {
            // Check if measurement ID belongs to a web stream of the selected property
            $streamMismatch = false;
            if (!empty($gaPropId) && !empty($gaMsId)) {
                $cachedStreams = get_transient('laca_ga_streams_' . $gaPropId);
                if (is_array($cachedStreams) && !empty($cachedStreams)) {
                    $matched = false;
                    foreach ($cachedStreams as $str) {
                        $sMid = $str['measurementId'] ?? ($str['webStreamData']['measurementId'] ?? '');
                        if (!empty($sMid) && strcasecmp($sMid, $gaMsId) === 0) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        $streamMismatch = true;
                    }
                }
            }

            if (empty($gaMsId)) {
                $gaState = [
                    'status'         => 'incomplete',
                    'reason'         => __('Chưa cấu hình mã đo lường GA4.', 'lacadev'),
                    'property_id'    => $gaPropId,
                    'measurement_id' => '',
                ];
            } elseif ($streamMismatch) {
                $gaState = [
                    'status'         => 'incomplete',
                    'reason'         => __('Mã đo lường không thuộc luồng web của thuộc tính GA4 đã chọn.', 'lacadev'),
                    'property_id'    => $gaPropId,
                    'measurement_id' => $gaMsId,
                ];
            } elseif ($gaLastErr && in_array((int) $gaLastErr, [401, 403, 404], true)) {
                $gaState = [
                    'status'         => 'error',
                    'code'           => (int) $gaLastErr,
                    'reason'         => sprintf(__('Lỗi API Google Analytics 4 (%d)', 'lacadev'), (int) $gaLastErr),
                    'property_id'    => $gaPropId,
                    'measurement_id' => $gaMsId,
                ];
            } else {
                $gaState = [
                    'status'         => 'complete',
                    'property_id'    => $gaPropId,
                    'measurement_id' => $gaMsId,
                ];
            }
        }

        $isAnyComplete = ($gscState['status'] === 'complete' || $gaState['status'] === 'complete');
        $isAllComplete = ($gscState['status'] === 'complete' && $gaState['status'] === 'complete');

        $gscAvailable = (bool) get_option('laca_google_data_available_gsc', false);
        $gaAvailable  = (bool) get_option('laca_google_data_available_ga', false);

        return [
            'connected'       => $isConnected,
            'email'           => $connectedEmail,
            'gsc'             => $gscState,
            'ga'              => $gaState,
            'data'            => [
                'gsc_available' => $gscAvailable,
                'ga_available'  => $gaAvailable,
                'collecting'    => (!$gscAvailable || !$gaAvailable),
            ],
            'is_any_complete' => $isAnyComplete,
            'is_all_complete' => $isAllComplete,
        ];
    }

    /**
     * Render full-width Google Report container at top of wp-admin/index.php.
     */
    public static function renderDashboardNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'dashboard') {
            return;
        }

        if (!self::userCanViewReport()) {
            return;
        }

        $isMock = GoogleDashboardService::isMockMode() ? '1' : '0';
        $state  = self::setupState();

        // Chỉ hiển thị khối báo cáo khi ít nhất 1 dịch vụ complete (hoặc ở chế độ mock)
        if ($isMock !== '1' && empty($state['is_any_complete'])) {
            return;
        }

        $nonce     = wp_create_nonce('laca_google_report_nonce');
        $ajaxUrl   = admin_url('admin-ajax.php');
        $userRange = DateRange::getUserRange();
        ?>
        <div id="laca-google-report" class="laca-google-report-wrap" data-ajax-url="<?php echo esc_url($ajaxUrl); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-mock="<?php echo esc_attr($isMock); ?>" data-range="<?php echo esc_attr($userRange); ?>" style="margin: 20px 0 24px 0; clear: both;">
            <div class="laca-google-report-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div class="laca-google-report-header__title" style="display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-chart-area" style="font-size: 20px; width: 20px; height: 20px; color: var(--laca-fg, #18181b);" aria-hidden="true"></span>
                    <h2 style="font-size: 16px; font-weight: 600; margin: 0; color: var(--laca-fg, #18181b);">
                        <?php esc_html_e('Báo cáo Google Search Console & Google Analytics 4', 'lacadev'); ?>
                    </h2>
                </div>
                <div class="laca-google-report-header__actions" style="display: flex; align-items: center; gap: 8px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary button-small" style="font-size: 12px; height: 28px; line-height: 26px;">
                        <?php esc_html_e('Cấu hình Dịch vụ Google', 'lacadev'); ?>
                    </a>
                    <button type="button" class="button button-secondary button-small laca-google-report-toggle" aria-expanded="true" title="<?php esc_attr_e('Thu gọn / Mở rộng', 'lacadev'); ?>" style="font-size: 12px; height: 28px; line-height: 26px; padding: 0 8px;">
                        <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true" style="margin-top: 3px;"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Thu gọn / Mở rộng', 'lacadev'); ?></span>
                    </button>
                </div>
            </div>
            <div class="laca-google-report-container" id="laca-google-report-container">
                <!-- Skeleton loader -->
                <?php include __DIR__ . '/views/partials/google-report-skeleton.php'; ?>
            </div>
            <template id="laca-google-report-skeleton-tpl">
                <?php include __DIR__ . '/views/partials/google-report-skeleton.php'; ?>
            </template>
        </div>
        <?php
    }

    /**
     * Enqueue assets for index.php (WP Dashboard).
     */
    public static function enqueueDashboardAssets(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }

        if (!self::userCanViewReport()) {
            return;
        }

        $isMock = GoogleDashboardService::isMockMode() ? '1' : '0';
        $state  = self::setupState();

        if ($isMock !== '1' && empty($state['is_any_complete'])) {
            return;
        }

        $distDir = dirname(get_stylesheet_directory()) . '/dist/';
        $themeRootUri = dirname(get_stylesheet_directory_uri());

        if (file_exists($distDir . 'styles/laca-seo.css')) {
            wp_enqueue_style(
                'laca-seo-admin',
                $themeRootUri . '/dist/styles/laca-seo.css',
                ['dashicons'],
                (string) filemtime($distDir . 'styles/laca-seo.css')
            );
        }

        $base = dirname(get_template_directory_uri());
        $baseDir = dirname(get_template_directory());
        $jsFile = $baseDir . '/resources/scripts/admin/google-dashboard-report.js';

        if (file_exists($jsFile)) {
            wp_enqueue_script(
                'laca-google-dashboard-report',
                $base . '/resources/scripts/admin/google-dashboard-report.js',
                [],
                (string) filemtime($jsFile),
                true
            );
            wp_localize_script('laca-google-dashboard-report', 'lacaGoogleReportData', [
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('laca_google_report_nonce'),
                'loadingText' => __('Đang tải báo cáo...', 'lacadev'),
            ]);
        }
    }

    /**
     * AJAX endpoint to fetch / refresh GSC and GA4 report data and return HTML partial.
     */
    public static function ajaxGoogleReport(): void
    {
        $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? '';
        $valid = wp_verify_nonce($nonce, 'laca_google_report_nonce') || wp_verify_nonce($nonce, 'laca_seo_admin_nonce');
        if (!$valid) {
            wp_die('-1', 403);
        }

        if (!self::userCanViewReport()) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'lacadev')], 403);
        }

        $range = sanitize_key(wp_unslash($_REQUEST['range'] ?? DateRange::getUserRange()));
        if (!in_array($range, DateRange::SUPPORTED_RANGES, true)) {
            $range = DateRange::DEFAULT_RANGE;
        }

        // Save preference per-user
        DateRange::setUserRange($range);

        $refresh = !empty($_REQUEST['refresh']);

        if (!empty($_REQUEST['laca_mock_google']) && function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local' && current_user_can('manage_options')) {
            $_GET['laca_mock_google'] = '1';
        }

        $report = GoogleDashboardService::getDashboardReport($range, $refresh);
        $gsc = $report['gsc'];
        $ga  = $report['ga4'];

        $rangeLabels = DateRange::getRangeLabels();

        ob_start();
        include __DIR__ . '/views/partials/google-report.php';
        $html = ob_get_clean();

        wp_send_json_success([
            'html'  => $html,
            'range' => $range,
        ]);
    }

    /**
     * AJAX endpoint to fetch latest GA4 Realtime active users (cached 60s).
     */
    public static function ajaxGa4Realtime(): void
    {
        $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? '';
        $valid = wp_verify_nonce($nonce, 'laca_google_report_nonce') || wp_verify_nonce($nonce, 'laca_seo_admin_nonce');
        if (!$valid) {
            wp_die('-1', 403);
        }

        if (!self::userCanViewReport()) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'lacadev')], 403);
        }

        if (!empty($_REQUEST['laca_mock_google']) && function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local' && current_user_can('manage_options')) {
            $_GET['laca_mock_google'] = '1';
        }

        if (GoogleDashboardService::isMockMode()) {
            wp_send_json_success(['activeUsers' => 3]);
        }

        $opts = get_option(self::OPTION_KEY, []);
        $propertyId = (string) ($opts['ga4_property_id'] ?? '');
        if (empty($propertyId) && class_exists('\App\Features\LacaSeo\Core\Settings')) {
            $propertyId = (string) \App\Features\LacaSeo\Core\Settings::get('general.ga4_property_id', '');
        }

        if (empty($propertyId)) {
            wp_send_json_success(['activeUsers' => 0]);
        }

        $result = GaClient::runRealtimeReport($propertyId, false);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $activeUsers = (int) ($result['activeUsers'] ?? 0);
        wp_send_json_success(['activeUsers' => $activeUsers]);
    }

    /**
     * AJAX endpoint to run PageSpeed Insights audit.
     */
    public static function ajaxPageSpeedRun(): void
    {
        $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? '';
        $valid = wp_verify_nonce($nonce, 'laca_google_report_nonce') || wp_verify_nonce($nonce, 'laca_seo_admin_nonce');
        if (!$valid) {
            wp_die('-1', 403);
        }

        if (!self::userCanViewReport()) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'lacadev')], 403);
        }

        $strategy = sanitize_key(wp_unslash($_REQUEST['strategy'] ?? 'mobile'));
        $refresh  = !empty($_REQUEST['refresh']);

        // Only accept URL with the same host as home_url()
        $homeHost = (string) parse_url(home_url('/'), PHP_URL_HOST);
        $rawUrl   = (string) wp_unslash($_REQUEST['url'] ?? home_url('/'));
        $targetUrl = esc_url_raw($rawUrl);
        $targetHost = (string) parse_url($targetUrl, PHP_URL_HOST);
        if (empty($targetUrl) || strtolower($targetHost) !== strtolower($homeHost)) {
            $targetUrl = home_url('/');
        }

        if (!empty($_REQUEST['laca_mock_google']) && function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local' && current_user_can('manage_options')) {
            $_GET['laca_mock_google'] = '1';
        }

        if (GoogleDashboardService::isMockMode()) {
            if (!empty($_REQUEST['laca_mock_psi_error'])) {
                $keyUrl = admin_url('admin.php?page=laca-google-search-console#laca-psi-key');
                $result = new \WP_Error('psi_error_429', __('Đã hết hạn mức miễn phí của Google cho hôm nay. Thêm PageSpeed API key để có hạn mức riêng.', 'lacadev'), [
                    'status'      => 429,
                    'actionUrl'   => $keyUrl,
                    'actionLabel' => __('Thêm API key', 'lacadev'),
                    'link'        => $keyUrl,
                ]);
            } else {
                $result = PageSpeedClient::getMockData($targetUrl, $strategy);
            }
        } else {
            $result = PageSpeedClient::run($targetUrl, $strategy, $refresh);
        }

        if (is_wp_error($result)) {
            $errData = $result->get_error_data();
            $statusCode = is_array($errData) && isset($errData['status']) ? (int) $errData['status'] : 400;
            wp_send_json_error([
                'message'     => $result->get_error_message(),
                'code'        => $result->get_error_code(),
                'status'      => $statusCode,
                'actionUrl'   => is_array($errData) ? ($errData['actionUrl'] ?? $errData['link'] ?? null) : null,
                'actionLabel' => is_array($errData) ? ($errData['actionLabel'] ?? null) : null,
                'link'        => is_array($errData) ? ($errData['link'] ?? null) : null,
            ], $statusCode >= 400 && $statusCode < 600 ? $statusCode : 400);
        }

        $psiData = $result;
        $siteUrl = $targetUrl;
        $isActive = true;
        ob_start();
        include __DIR__ . '/views/partials/pagespeed-panel.php';
        $html = ob_get_clean();

        wp_send_json_success([
            'html'     => $html,
            'strategy' => $strategy,
            'data'     => $result,
        ]);
    }

    /**
     * AJAX endpoint to fetch up to 100 queries for GSC modal dialog.
     */
    public static function ajaxGscQueries(): void
    {
        $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? '';
        $valid = wp_verify_nonce($nonce, 'laca_google_report_nonce') || wp_verify_nonce($nonce, 'laca_seo_admin_nonce');
        if (!$valid) {
            wp_die('-1', 403);
        }

        if (!self::userCanViewReport()) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'lacadev')], 403);
        }

        $range = sanitize_key(wp_unslash($_REQUEST['range'] ?? DateRange::getUserRange()));
        if (!in_array($range, DateRange::SUPPORTED_RANGES, true)) {
            $range = DateRange::DEFAULT_RANGE;
        }

        if (!empty($_REQUEST['laca_mock_google']) && function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local' && current_user_can('manage_options')) {
            $_GET['laca_mock_google'] = '1';
        }

        $refresh = !empty($_REQUEST['refresh']);

        if (GoogleDashboardService::isMockMode()) {
            $rows = GoogleDashboardService::getMockGscQueries100($range);
        } else {
            $siteUrl = (string) self::getOption('gsc_site_url', '');
            if (empty($siteUrl)) {
                $siteUrl = GscClient::getSiteUrl();
            }
            $result = GscClient::getQueries100($siteUrl, $range, $refresh);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
            $rows = is_array($result) ? $result : [];
        }

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="5" class="laca-google-dialog__empty">' . esc_html__('Không tìm thấy từ khóa tìm kiếm trong khoảng thời gian này.', 'lacadev') . '</td></tr>';
        } else {
            foreach ($rows as $r) {
                $q = esc_html($r['query'] ?? '');
                $cl = number_format_i18n((int) ($r['clicks'] ?? 0));
                $imp = number_format_i18n((int) ($r['impressions'] ?? 0));
                $ctr = number_format((float) ($r['ctr'] ?? 0), 1) . '%';
                $pos = number_format((float) ($r['position'] ?? 0), 1);
                echo '<tr>';
                echo '<td class="col-query">' . $q . '</td>';
                echo '<td class="col-clicks" data-val="' . (int) ($r['clicks'] ?? 0) . '">' . esc_html($cl) . '</td>';
                echo '<td class="col-impressions" data-val="' . (int) ($r['impressions'] ?? 0) . '">' . esc_html($imp) . '</td>';
                echo '<td class="col-ctr" data-val="' . (float) ($r['ctr'] ?? 0) . '">' . esc_html($ctr) . '</td>';
                echo '<td class="col-position" data-val="' . (float) ($r['position'] ?? 0) . '">' . esc_html($pos) . '</td>';
                echo '</tr>';
            }
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'rows'  => $rows,
            'total' => count($rows),
            'range' => $range,
            'html'  => $html,
        ]);
    }
}
