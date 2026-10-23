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

        // 4. Google Search Console 28-day metrics if connected
        $gscCreds = \App\Features\LacaSeo\Modules\SearchConsole\GscOAuth::getCredentials();
        $gscPerf = null;
        if (!empty($gscCreds['access_token'])) {
            $gscPerf = \App\Features\LacaSeo\Modules\SearchConsole\GscClient::getPerformanceSummary(home_url('/'));
        }

        ?>
        <div class="laca-seo-dashboard-widget-content" style="padding: 10px 0;">
            <p><strong><?php esc_html_e('Chỉ số SEO cốt lõi của website:', 'lacadev'); ?></strong></p>
            <ul style="margin: 12px 0; line-height: 1.8;">
                <li>
                    <span class="dashicons dashicons-randomize" style="color: var(--laca-accent, #18181b);"></span>
                    <?php esc_html_e('Chuyển hướng hoạt động (301/302):', 'lacadev'); ?>
                    <strong><?php echo esc_html($redirectionsCount); ?></strong>
                </li>
                <li>
                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                    <?php esc_html_e('Lỗi 404 đã ghi nhận:', 'lacadev'); ?>
                    <strong><?php echo esc_html($notfoundCount); ?></strong>
                </li>
                <li>
                    <span class="dashicons dashicons-admin-links" style="color: #008a20;"></span>
                    <?php esc_html_e('Liên kết nội bộ đã phân tích:', 'lacadev'); ?>
                    <strong><?php echo esc_html($linksCount); ?></strong>
                </li>
                <?php if ($gscPerf): ?>
                <li>
                    <span class="dashicons dashicons-google" style="color: #ea4335;"></span>
                    <?php esc_html_e('Google Search (28 ngày):', 'lacadev'); ?>
                    <strong><?php echo esc_html(number_format_i18n($gscPerf['totalClicks'] ?? 0)); ?> clicks</strong>
                    <span style="color:#64748b;">(<?php echo esc_html(number_format_i18n($gscPerf['totalImpressions'] ?? 0)); ?> hiển thị)</span>
                </li>
                <?php endif; ?>
            </ul>
            <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=laca-seo')); ?>" class="button button-primary">
                    <?php esc_html_e('Mở Bảng điều khiển SEO', 'lacadev'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
