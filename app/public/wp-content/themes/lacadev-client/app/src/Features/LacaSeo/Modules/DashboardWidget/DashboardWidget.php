<?php

namespace App\Features\LacaSeo\Modules\DashboardWidget;

use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Modules\RoleManager\CapabilityManager;
use App\Features\LacaSeo\Modules\Links\Storage;

defined('ABSPATH') || exit;

/**
 * Registers an SEO summary widget on the main WordPress admin dashboard.
 */
class DashboardWidget
{
    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'addWidget']);
        add_action('admin_notices', [$this, 'renderDashboardNotice']);
        add_action('wp_ajax_laca_google_report', [$this, 'ajaxGoogleReport']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addWidget(): void
    {
        if (!CapabilityManager::hasCap('site_analysis') && !current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'laca_seo_dashboard_widget',
            '<span class="dashicons dashicons-chart-pie" style="margin-right: 6px;"></span>' . esc_html__('Tổng quan Laca SEO', 'lacadev'),
            [$this, 'render']
        );
    }

    public function render(): void
    {
        global $wpdb;

        // 1. Redirections count
        $redirectionsCount = 0;
        $redTable = $wpdb->prefix . 'laca_seo_redirections';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $redTable))) {
            $redirectionsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$redTable} WHERE status = 'active'");
        }

        // 2. 404 count
        $notfoundCount = 0;
        $nfTable = $wpdb->prefix . 'laca_seo_404_logs';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $nfTable))) {
            $notfoundCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$nfTable}");
        }

        // 3. Total internal links
        $linksCount = 0;
        $lkTable = Storage::getLinksTable();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $lkTable))) {
            $linksCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$lkTable}");
        }
        ?>
        <div class="laca-seo-dashboard-widget-content" style="padding: 10px 0;">
            <p><strong><?php esc_html_e('Chỉ số SEO cốt lõi của website:', 'lacadev'); ?></strong></p>
            <ul style="margin: 12px 0; line-height: 1.8;">
                <li>
                    <span class="dashicons dashicons-randomize" style="color: var(--laca-accent, #18181b);"></span>
                    <?php esc_html_e('Chuyển hướng hoạt động (301/302):', 'lacadev'); ?>
                    <strong><?php echo esc_html((string) $redirectionsCount); ?></strong>
                </li>
                <li>
                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                    <?php esc_html_e('Lỗi 404 đã ghi nhận:', 'lacadev'); ?>
                    <strong><?php echo esc_html((string) $notfoundCount); ?></strong>
                </li>
                <li>
                    <span class="dashicons dashicons-admin-links" style="color: #008a20;"></span>
                    <?php esc_html_e('Liên kết nội bộ đã phân tích:', 'lacadev'); ?>
                    <strong><?php echo esc_html((string) $linksCount); ?></strong>
                </li>
            </ul>
            <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=laca-seo')); ?>" class="button button-primary">
                    <?php esc_html_e('Mở Bảng điều khiển SEO', 'lacadev'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render full-width Google Report container at the top of wp-admin/index.php.
     */
    public function renderDashboardNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'dashboard') {
            return;
        }

        if (!CapabilityManager::hasCap('site_analysis') && !current_user_can('manage_options')) {
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
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-seo')); ?>" class="button button-secondary button-small" style="font-size: 12px; height: 28px; line-height: 26px;">
                        <?php esc_html_e('Bảng điều khiển SEO', 'lacadev'); ?>
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
     * AJAX handler for returning Google Report HTML.
     */
    public function ajaxGoogleReport(): void
    {
        (new \App\Features\LacaSeo\Admin\AdminPage())->ajaxGoogleReport();
    }

    /**
     * Enqueue assets for index.php (WP Dashboard).
     */
    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }

        if (!CapabilityManager::hasCap('site_analysis') && !current_user_can('manage_options')) {
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
}
