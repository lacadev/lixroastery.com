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
        'ga4_property_id',
        'ga4_goal_events',
        'gsc_site_url',
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

        if (is_admin()) {
            (new \App\Settings\LacaAdmin\GoogleServicesPage())->register();
            add_action('admin_notices', [self::class, 'renderDashboardNotice']);
            add_action('admin_enqueue_scripts', [self::class, 'enqueueDashboardAssets']);
        }

        // AJAX handler for dashboard report
        add_action('wp_ajax_laca_google_report', [self::class, 'ajaxGoogleReport']);
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

        // Copy OAuth credentials if new option does not exist yet
        $oauthCurrent = get_option(GscOAuth::OPTION_KEY, null);
        if ($oauthCurrent === null) {
            $oauthLegacy = get_option(self::LEGACY_OAUTH_KEY, null);
            if ($oauthLegacy !== null) {
                update_option(GscOAuth::OPTION_KEY, $oauthLegacy, false);
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
                $current[$key] = $settings[$key];
            }
        }

        return update_option(self::OPTION_KEY, $current, false);
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

        $hasCap = current_user_can('manage_options');
        if (!$hasCap && class_exists('\App\Features\LacaSeo\Auth\CapabilityManager')) {
            $hasCap = \App\Features\LacaSeo\Auth\CapabilityManager::hasCap('site_analysis');
        }

        if (!$hasCap) {
            return;
        }

        $isMock = !empty($_GET['laca_mock_google']) ? '1' : '0';
        $nonce = wp_create_nonce('laca_google_report_nonce');
        $ajaxUrl = admin_url('admin-ajax.php');
        ?>
        <div id="laca-google-report" class="laca-google-report-wrap" data-ajax-url="<?php echo esc_url($ajaxUrl); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-mock="<?php echo esc_attr($isMock); ?>" style="margin: 20px 0 24px 0; clear: both;">
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
                <div class="laca-google-report-skeleton" aria-busy="true" aria-label="<?php esc_attr_e('Đang tải dữ liệu báo cáo Google...', 'lacadev'); ?>">
                    <div style="height: 48px; border-radius: 8px; margin-bottom: 16px; background: var(--laca-bg-subtle, #f4f4f5); border: 1px solid var(--laca-border, #e4e4e7);"></div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 24px;">
                        <div style="height: 360px; border-radius: 8px; background: var(--laca-bg-subtle, #f4f4f5); border: 1px solid var(--laca-border, #e4e4e7);"></div>
                        <div style="height: 360px; border-radius: 8px; background: var(--laca-bg-subtle, #f4f4f5); border: 1px solid var(--laca-border, #e4e4e7);"></div>
                    </div>
                </div>
            </div>
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

        $hasCap = current_user_can('manage_options');
        if (!$hasCap && class_exists('\App\Features\LacaSeo\Auth\CapabilityManager')) {
            $hasCap = \App\Features\LacaSeo\Auth\CapabilityManager::hasCap('site_analysis');
        }

        if (!$hasCap) {
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

        $hasCap = current_user_can('manage_options');
        if (!$hasCap && class_exists('\App\Features\LacaSeo\Auth\CapabilityManager')) {
            $hasCap = \App\Features\LacaSeo\Auth\CapabilityManager::hasCap('site_analysis');
        }

        if (!$hasCap) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'lacadev')], 403);
        }

        $range = sanitize_key(wp_unslash($_REQUEST['range'] ?? '28d'));
        if (!in_array($range, ['7d', '28d', '90d'], true)) {
            $range = '28d';
        }

        $refresh = !empty($_REQUEST['refresh']);

        if (!empty($_REQUEST['laca_mock_google'])) {
            $_GET['laca_mock_google'] = '1';
        }

        $report = GoogleDashboardService::getDashboardReport($range, $refresh);
        $gsc = $report['gsc'];
        $ga  = $report['ga4'];

        $rangeLabels = [
            '7d'  => __('7 ngày qua', 'lacadev'),
            '28d' => __('28 ngày qua', 'lacadev'),
            '90d' => __('90 ngày qua', 'lacadev'),
        ];

        ob_start();
        include __DIR__ . '/views/partials/google-report.php';
        $html = ob_get_clean();

        wp_send_json_success([
            'html'  => $html,
            'range' => $range,
        ]);
    }
}
