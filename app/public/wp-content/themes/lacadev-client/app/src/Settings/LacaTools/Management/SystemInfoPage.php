<?php

namespace App\Settings\LacaTools\Management;

use App\Settings\LacaTools\Management\StorageHealth;
use App\Settings\LacaTools\Management\LargeFileScanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SystemInfoPage
 *
 * Quản trị trang Laca Admin → Hệ thống & dung lượng (slug: laca-system).
 * Gồm 2 tab chính:
 * - tab=server: Thông số máy chủ, giới hạn PHP, cấu hình WordPress và PHP Extensions.
 * - tab=storage: Quản lý dung lượng chi tiết (/wp-content breakdown, Top 10) & Quét file lớn (> 50MB) an toàn.
 *
 * @package App\Settings\LacaTools\Management
 */
class SystemInfoPage
{
    public const SLUG = 'laca-system';
    public const CAP  = 'manage_options';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 25);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'laca-admin',
            esc_html__('Hệ thống', 'laca'),
            esc_html__('Hệ thống', 'laca'),
            self::CAP,
            self::SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, self::SLUG)) {
            return;
        }

        $base = dirname(get_template_directory_uri());

        wp_enqueue_script(
            'laca-system-info',
            $base . '/resources/scripts/admin/laca-system.js',
            ['jquery'],
            defined('LACA_THEME_VERSION') ? LACA_THEME_VERSION : '1.0.0',
            true
        );

        wp_localize_script('laca-system-info', 'lacaSystemData', [
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'storageNonce'      => wp_create_nonce(StorageHealth::NONCE_ACTION),
            'scannerNonce'      => wp_create_nonce(LargeFileScanner::NONCE_ACTION),
            'copiedText'        => esc_html__('Đã sao chép thông tin hệ thống vào clipboard!', 'laca'),
            'copyBtnText'       => esc_html__('Sao chép thông tin hệ thống', 'laca'),
            'calculating'       => esc_html__('Đang tính toán...', 'laca'),
            'scanning'          => esc_html__('Đang quét...', 'laca'),
            'deleteConfirm'     => esc_html__('Bạn có chắc chắn muốn xoá các file đã chọn? Thao tác này không thể hoàn tác.', 'laca'),
            'deleting'          => esc_html__('Đang xoá...', 'laca'),
            'selectAtLeastOne'  => esc_html__('Vui lòng chọn ít nhất một file để xoá.', 'laca'),
        ]);
    }

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $activeTab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'server';
        if (!in_array($activeTab, ['server', 'storage'], true)) {
            $activeTab = 'server';
        }

        $baseUrl = admin_url('admin.php?page=' . self::SLUG);
        ?>
        <div class="wrap laca-system-wrap">
            <div class="laca-page-header">
                <div class="laca-page-header__content">
                    <h1 class="laca-page-header__title"><?php esc_html_e('Hệ thống & Dung lượng', 'laca'); ?></h1>
                    <p class="laca-page-header__desc">
                        <?php esc_html_e('Xem chi tiết thông số máy chủ, cấu hình môi trường và quản lý dung lượng lưu trữ an toàn.', 'laca'); ?>
                    </p>
                </div>
            </div>

            <nav class="nav-tab-wrapper laca-nav-tabs">
                <a href="<?php echo esc_url(add_query_arg('tab', 'server', $baseUrl)); ?>"
                   class="nav-tab <?php echo $activeTab === 'server' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <?php esc_html_e('Thông số máy chủ', 'laca'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'storage', $baseUrl)); ?>"
                   class="nav-tab <?php echo $activeTab === 'storage' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-database"></span>
                    <?php esc_html_e('Quản lý dung lượng', 'laca'); ?>
                </a>
            </nav>

            <div class="laca-system-tab-content" style="margin-top: 20px;">
                <?php
                if ($activeTab === 'storage') {
                    $this->renderStorageTab();
                } else {
                    $this->renderServerTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * =========================================================================
     * TAB 1: THÔNG SỐ MÁY CHỦ
     * =========================================================================
     */
    private function renderServerTab(): void
    {
        global $wpdb;

        // 1. Môi trường máy chủ
        $webServer = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown';
        $phpVersion = PHP_VERSION;
        $phpBadge = 'laca-badge--success';
        if (version_compare($phpVersion, '7.4', '<')) {
            $phpBadge = 'laca-badge--danger';
        } elseif (version_compare($phpVersion, '8.1', '<')) {
            $phpBadge = 'laca-badge--warning';
        }

        $dbVersion = method_exists($wpdb, 'db_server_info') ? $wpdb->db_server_info() : $wpdb->get_var('SELECT VERSION()');
        $dbVersion = $dbVersion ? sanitize_text_field((string) $dbVersion) : 'Unknown';

        $storageCache = StorageHealth::getCached();
        $dbSizeFormatted = (!empty($storageCache['db_formatted'])) ? $storageCache['db_formatted'] : __('Chưa tính', 'laca');

        $timezoneString = wp_timezone_string();
        $gmtOffset = get_option('gmt_offset');
        $offsetStr = ($gmtOffset >= 0 ? '+' : '') . $gmtOffset;

        $isRealCron = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $cronText = $isRealCron
            ? __('Cron thật của hosting (DISABLE_WP_CRON)', 'laca')
            : __('Pseudo-cron (mặc định của WordPress)', 'laca');

        $isHttps = is_ssl() || str_starts_with(home_url(), 'https');
        $httpsText = $isHttps ? __('Đã bật (HTTPS)', 'laca') : __('Chưa bật (HTTP)', 'laca');

        // 2. Giới hạn PHP
        $memoryLimit     = ini_get('memory_limit') ?: '—';
        $maxExecTime     = ini_get('max_execution_time') !== false ? ini_get('max_execution_time') . 's' : '—';
        $maxInputVars    = ini_get('max_input_vars') ?: '—';
        $uploadMaxSize   = size_format(wp_max_upload_size());
        $postMaxSize     = ini_get('post_max_size') ?: '—';
        $maxInputTime    = ini_get('max_input_time') !== false ? ini_get('max_input_time') . 's' : '—';
        $opcacheEnabled  = extension_loaded('Zend OPcache') && ini_get('opcache.enable');
        $opcacheText     = $opcacheEnabled ? __('Đã bật', 'laca') : __('Tắt', 'laca');

        // 3. WordPress
        $wpVersion       = get_bloginfo('version');
        $wpDebug         = (defined('WP_DEBUG') && WP_DEBUG) ? __('Bật', 'laca') : __('Tắt', 'laca');
        $wpDebugLog      = (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) ? __('Bật', 'laca') : __('Tắt', 'laca');
        $disallowEdit    = (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) ? __('Bật (an toàn)', 'laca') : __('Tắt', 'laca');
        $wpMemLimit      = (defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '40M') . ' / ' . (defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : '256M');
        $activePlugins   = count((array) get_option('active_plugins', []));
        $themeObj        = wp_get_theme();
        $themeNameVer    = $themeObj->get('Name') . ' (v' . $themeObj->get('Version') . ')';
        $isMultisite     = is_multisite() ? __('Có', 'laca') : __('Không', 'laca');

        // 4. PHP Extensions
        $loadedExts      = get_loaded_extensions();
        natcasesort($loadedExts);
        $importantExts   = ['curl', 'gd', 'imagick', 'mbstring', 'mysqli', 'openssl', 'zip', 'intl', 'exif', 'fileinfo', 'sodium', 'xml'];
        $missingExts     = [];
        foreach ($importantExts as $req) {
            if (!extension_loaded($req)) {
                $missingExts[] = $req;
            }
        }

        // Chuẩn bị text thuần để copy
        $copySummary = "### THÔNG SỐ HỆ THỐNG ###\n"
            . "- Web Server: {$webServer}\n"
            . "- PHP Version: {$phpVersion}\n"
            . "- Database: {$dbVersion} (Dung lượng: {$dbSizeFormatted})\n"
            . "- Timezone: {$timezoneString} (UTC{$offsetStr})\n"
            . "- WP-Cron: {$cronText}\n"
            . "- HTTPS: {$httpsText}\n\n"
            . "### GIỚI HẠN PHP ###\n"
            . "- Memory Limit: {$memoryLimit}\n"
            . "- Max Execution Time: {$maxExecTime}\n"
            . "- Max Input Vars: {$maxInputVars}\n"
            . "- Upload Max Size: {$uploadMaxSize}\n"
            . "- Post Max Size: {$postMaxSize}\n"
            . "- Max Input Time: {$maxInputTime}\n"
            . "- OPcache: {$opcacheText}\n\n"
            . "### WORDPRESS ###\n"
            . "- WordPress Version: {$wpVersion}\n"
            . "- Active Theme: {$themeNameVer}\n"
            . "- Active Plugins: {$activePlugins}\n"
            . "- WP Memory Limit: {$wpMemLimit}\n"
            . "- WP_DEBUG: {$wpDebug} | WP_DEBUG_LOG: {$wpDebugLog}\n"
            . "- DISALLOW_FILE_EDIT: {$disallowEdit}\n"
            . "- Multisite: {$isMultisite}";
        ?>
        <div class="laca-system-toolbar" style="margin-bottom: 20px; display:flex; justify-content: flex-end;">
            <button type="button" class="laca-btn laca-btn--secondary" id="laca-copy-system-info" data-clipboard-text="<?php echo esc_attr($copySummary); ?>">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e('Sao chép thông tin hệ thống', 'laca'); ?>
            </button>
        </div>

        <div class="laca-grid laca-grid--2col" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 20px;">
            <!-- Card 1: Môi trường máy chủ -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__title">
                        <span class="dashicons dashicons-admin-site-alt3" style="color:var(--laca-accent);"></span>
                        <?php esc_html_e('Môi trường máy chủ', 'laca'); ?>
                    </h2>
                </div>
                <div class="laca-card__body">
                    <table class="laca-kv-table" style="width:100%; border-collapse:collapse;">
                        <tbody>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted); width:40%;"><?php esc_html_e('Web server', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border); font-family:monospace;"><?php echo esc_html($webServer); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('Phiên bản PHP', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);">
                                    <span class="laca-badge <?php echo esc_attr($phpBadge); ?>"><?php echo esc_html($phpVersion); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('MySQL / MariaDB', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border); font-family:monospace;"><?php echo esc_html($dbVersion); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('Dung lượng database', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);">
                                    <strong><?php echo esc_html($dbSizeFormatted); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('Múi giờ site', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);"><?php echo esc_html($timezoneString . ' (UTC' . $offsetStr . ')'); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('WP-Cron', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);"><?php echo esc_html($cronText); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('HTTPS / SSL', 'laca'); ?></th>
                                <td style="padding:10px 0;">
                                    <span class="laca-badge <?php echo $isHttps ? 'laca-badge--success' : 'laca-badge--warning'; ?>">
                                        <?php echo esc_html($httpsText); ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 2: Giới hạn PHP -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__title">
                        <span class="dashicons dashicons-dashboard" style="color:var(--laca-accent);"></span>
                        <?php esc_html_e('Giới hạn PHP', 'laca'); ?>
                    </h2>
                </div>
                <div class="laca-card__body">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Memory Limit', 'laca'); ?></span>
                            <span class="laca-stat__value" style="font-size:18px;"><?php echo esc_html($memoryLimit); ?></span>
                        </div>
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Max Execution Time', 'laca'); ?></span>
                            <span class="laca-stat__value" style="font-size:18px;"><?php echo esc_html($maxExecTime); ?></span>
                        </div>
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Upload Max Size', 'laca'); ?></span>
                            <span class="laca-stat__value" style="font-size:18px;"><?php echo esc_html($uploadMaxSize); ?></span>
                        </div>
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Post Max Size', 'laca'); ?></span>
                            <span class="laca-stat__value" style="font-size:18px;"><?php echo esc_html($postMaxSize); ?></span>
                        </div>
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Max Input Vars', 'laca'); ?></span>
                            <span class="laca-stat__value" style="font-size:18px;"><?php echo esc_html($maxInputVars); ?></span>
                        </div>
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Max Input Time', 'laca'); ?></span>
                            <span class="laca-stat__value" style="font-size:18px;"><?php echo esc_html($maxInputTime); ?></span>
                        </div>
                    </div>
                    <div style="margin-top:16px; padding-top:12px; border-top:1px solid var(--laca-border); display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:var(--laca-fg-muted);"><?php esc_html_e('Zend OPcache:', 'laca'); ?></span>
                        <span class="laca-badge <?php echo $opcacheEnabled ? 'laca-badge--success' : 'laca-badge--neutral'; ?>">
                            <?php echo esc_html($opcacheText); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card 3: WordPress -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__title">
                        <span class="dashicons dashicons-wordpress" style="color:var(--laca-accent);"></span>
                        <?php esc_html_e('Cấu hình WordPress', 'laca'); ?>
                    </h2>
                </div>
                <div class="laca-card__body">
                    <table class="laca-kv-table" style="width:100%; border-collapse:collapse;">
                        <tbody>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted); width:45%;"><?php esc_html_e('Phiên bản WordPress', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);"><strong><?php echo esc_html($wpVersion); ?></strong></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('Theme đang dùng', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);"><?php echo esc_html($themeNameVer); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('Số plugin kích hoạt', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);"><?php echo esc_html((string) $activePlugins); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('WP Memory Limit', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border); font-family:monospace;"><?php echo esc_html($wpMemLimit); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('WP_DEBUG / DEBUG_LOG', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);">
                                    <?php echo esc_html($wpDebug . ' / ' . $wpDebugLog); ?>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; border-bottom:1px solid var(--laca-border); text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('Khóa sửa file (DISALLOW_FILE_EDIT)', 'laca'); ?></th>
                                <td style="padding:10px 0; border-bottom:1px solid var(--laca-border);"><?php echo esc_html($disallowEdit); ?></td>
                            </tr>
                            <tr>
                                <th style="padding:10px 0; text-align:left; color:var(--laca-fg-muted);"><?php esc_html_e('WordPress Multisite', 'laca'); ?></th>
                                <td style="padding:10px 0;"><?php echo esc_html($isMultisite); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 4: PHP Extensions -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__title">
                        <span class="dashicons dashicons-admin-plugins" style="color:var(--laca-accent);"></span>
                        <?php esc_html_e('PHP Extensions', 'laca'); ?>
                        <span style="font-size:12px; font-weight:normal; color:var(--laca-fg-muted);">
                            (<?php echo count($loadedExts); ?> <?php esc_html_e('đang nạp', 'laca'); ?>)
                        </span>
                    </h2>
                </div>
                <div class="laca-card__body">
                    <?php if (!empty($missingExts)) : ?>
                        <div class="laca-alert laca-alert--warning" style="margin-bottom:16px;">
                            <div class="laca-alert__title">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: list of missing extensions */
                                        __('Thiếu extension quan trọng cho WordPress: %s', 'laca'),
                                        implode(', ', $missingExts)
                                    )
                                );
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="laca-chips-container" style="display:flex; flex-wrap:wrap; gap:6px; max-height:260px; overflow-y:auto; padding:4px;">
                        <?php foreach ($loadedExts as $ext) :
                            $isImportant = in_array(strtolower($ext), $importantExts, true);
                        ?>
                            <span class="laca-badge <?php echo $isImportant ? 'laca-badge--success' : 'laca-badge--neutral'; ?>"
                                  style="font-family:monospace; font-size:12px; padding:3px 8px;"
                                  title="<?php echo $isImportant ? esc_attr__('Extension quan trọng của WordPress', 'laca') : ''; ?>">
                                <?php if ($isImportant) : ?>
                                    <span style="font-weight:bold; margin-right:3px;">✓</span>
                                <?php endif; ?>
                                <?php echo esc_html($ext); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Card 5: Hiệu năng & bộ nhớ đệm (Cache) -->
            <?php
            $cacheDiag = class_exists(\App\Features\Performance\CacheCompat::class)
                ? \App\Features\Performance\CacheCompat::getDiagnostics()
                : [];
            ?>
            <div class="laca-card" style="grid-column: 1 / -1;">
                <div class="laca-card__header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 class="laca-card__title">
                        <span class="dashicons dashicons-performance" style="color:var(--laca-accent);"></span>
                        <?php esc_html_e('Hiệu năng & bộ nhớ đệm (Cache)', 'laca'); ?>
                    </h2>
                    <span class="laca-badge laca-badge--success">
                        ✓ <?php esc_html_e('Sẵn sàng Cache toàn trang', 'laca'); ?>
                    </span>
                </div>
                <div class="laca-card__body">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
                        <!-- Object Cache -->
                        <div style="background:var(--laca-bg-alt, #f8fafc); border:1px solid var(--laca-border); border-radius:6px; padding:16px;">
                            <div style="font-weight:600; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                <span><?php esc_html_e('Object Cache', 'laca'); ?></span>
                                <span class="laca-badge <?php echo esc_attr($cacheDiag['object_cache']['badge'] ?? 'laca-badge--neutral'); ?>">
                                    <?php echo !empty($cacheDiag['object_cache']['enabled']) ? esc_html__('Đã kết nối', 'laca') : esc_html__('Chưa có', 'laca'); ?>
                                </span>
                            </div>
                            <p style="margin:0 0 6px; font-family:monospace; font-size:13px; color:var(--laca-fg);">
                                <?php echo esc_html($cacheDiag['object_cache']['type'] ?? '—'); ?>
                            </p>
                            <small style="color:var(--laca-fg-muted);">
                                <?php esc_html_e('Lưu kết quả truy vấn database vào RAM (Redis/Memcached) thay vì query lại nhiều lần.', 'laca'); ?>
                            </small>
                        </div>

                        <!-- OPcache -->
                        <div style="background:var(--laca-bg-alt, #f8fafc); border:1px solid var(--laca-border); border-radius:6px; padding:16px;">
                            <div style="font-weight:600; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                <span><?php esc_html_e('Zend OPcache', 'laca'); ?></span>
                                <span class="laca-badge <?php echo !empty($cacheDiag['opcache']['enabled']) ? 'laca-badge--success' : 'laca-badge--neutral'; ?>">
                                    <?php echo esc_html($cacheDiag['opcache']['status_text'] ?? '—'); ?>
                                </span>
                            </div>
                            <?php if (!empty($cacheDiag['opcache']['enabled'])): ?>
                                <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                                    <span style="color:var(--laca-fg-muted);"><?php esc_html_e('Dung lượng dùng:', 'laca'); ?></span>
                                    <strong><?php echo esc_html($cacheDiag['opcache']['used_memory'] . ' / ' . $cacheDiag['opcache']['free_memory']); ?></strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                                    <span style="color:var(--laca-fg-muted);"><?php esc_html_e('Tỷ lệ trúng (Hit rate):', 'laca'); ?></span>
                                    <strong><?php echo esc_html($cacheDiag['opcache']['hit_rate']); ?></strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:13px;">
                                    <span style="color:var(--laca-fg-muted);"><?php esc_html_e('Files bytecode:', 'laca'); ?></span>
                                    <span><?php echo esc_html($cacheDiag['opcache']['cached_files']); ?></span>
                                </div>
                            <?php else: ?>
                                <small style="color:var(--laca-fg-muted);">
                                    <?php esc_html_e('Nên bật OPcache trên máy chủ để tăng tốc độ thực thi PHP lên 3-5 lần.', 'laca'); ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <!-- Full-Page Cache -->
                        <div style="background:var(--laca-bg-alt, #f8fafc); border:1px solid var(--laca-border); border-radius:6px; padding:16px;">
                            <div style="font-weight:600; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                <span><?php esc_html_e('Bộ nhớ đệm trang (Page Cache)', 'laca'); ?></span>
                                <span class="laca-badge <?php echo esc_attr($cacheDiag['page_cache']['badge'] ?? 'laca-badge--neutral'); ?>">
                                    <?php echo esc_html($cacheDiag['page_cache']['detected'] ?? '—'); ?>
                                </span>
                            </div>
                            <small style="color:var(--laca-fg-muted); display:block; margin-top:8px;">
                                <?php esc_html_e('Theme đã tối ưu đạt ≤ 25 query và 0 lần ghi DB, sẵn sàng kết hợp cùng Nginx FastCGI, LiteSpeed hoặc Cloudflare Edge Cache.', 'laca'); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 6: Đồng bộ với Production (Chỉ hiển thị tại local/dev) -->
            <?php
            $isLocalEnv = function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), ['local', 'development'], true);
            if ($isLocalEnv) :
                $hasConfig = class_exists(\App\Cli\Sync\SyncConfig::class) && \App\Cli\Sync\SyncConfig::exists();
                $lastPull = get_option('laca_last_pull', []);
                $isProxyActive = class_exists(\App\Features\Dev\LocalMediaProxy::class) && \App\Features\Dev\LocalMediaProxy::isActive();
                $prodUrl = class_exists(\App\Features\Dev\LocalMediaProxy::class) ? \App\Features\Dev\LocalMediaProxy::getProductionUrl() : null;
            ?>
            <div class="laca-card" style="grid-column: 1 / -1;">
                <div class="laca-card__header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 class="laca-card__title">
                        <span class="dashicons dashicons-update" style="color:var(--laca-accent);"></span>
                        <?php esc_html_e('Đồng bộ với Production (Laca Sync)', 'laca'); ?>
                    </h2>
                    <span class="laca-badge <?php echo $hasConfig ? 'laca-badge--success' : 'laca-badge--warning'; ?>">
                        <?php echo $hasConfig ? esc_html__('Đã cấu hình laca-sync.yml', 'laca') : esc_html__('Chưa có laca-sync.yml', 'laca'); ?>
                    </span>
                </div>
                <div class="laca-card__body">
                    <p style="color:var(--laca-fg-muted); margin-bottom:16px;">
                        <?php esc_html_e('Công cụ đồng bộ dữ liệu và tập tin một chiều từ Production về Local. Mọi lệnh đều chỉ đọc từ server và bảo vệ tuyệt đối cơ sở dữ liệu trên hosting.', 'laca'); ?>
                    </p>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-bottom:20px;">
                        <!-- Trạng thái kéo DB gần nhất -->
                        <div style="background:var(--laca-bg-alt, #f8fafc); border:1px solid var(--laca-border); border-radius:6px; padding:12px;">
                            <div style="font-size:12px; color:var(--laca-fg-muted); margin-bottom:4px;"><?php esc_html_e('Lần kéo DB gần nhất', 'laca'); ?></div>
                            <div style="font-weight:600; font-size:14px;">
                                <?php if (!empty($lastPull['time'])) : ?>
                                    <?php echo esc_html(wp_date('d/m/Y H:i', (int) $lastPull['time'])); ?>
                                    <span style="font-size:12px; font-weight:normal; color:var(--laca-fg-muted);">
                                        (<?php echo esc_html(size_format($lastPull['bytes'] ?? 0, 1)); ?>)
                                    </span>
                                <?php else : ?>
                                    <span style="color:var(--laca-fg-muted);"><?php esc_html_e('Chưa kéo lần nào', 'laca'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Proxy hình ảnh -->
                        <div style="background:var(--laca-bg-alt, #f8fafc); border:1px solid var(--laca-border); border-radius:6px; padding:12px;">
                            <div style="font-size:12px; color:var(--laca-fg-muted); margin-bottom:4px;"><?php esc_html_e('Proxy hình ảnh Production', 'laca'); ?></div>
                            <div style="font-weight:600; font-size:14px; display:flex; align-items:center; gap:6px;">
                                <span class="laca-badge <?php echo $isProxyActive ? 'laca-badge--success' : 'laca-badge--neutral'; ?>">
                                    <?php echo $isProxyActive ? esc_html__('Đang bật', 'laca') : esc_html__('Tắt', 'laca'); ?>
                                </span>
                                <?php if ($prodUrl) : ?>
                                    <span style="font-size:12px; font-weight:normal; color:var(--laca-fg-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($prodUrl); ?>">
                                        <?php echo esc_html($prodUrl); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- VS Code Task & CLI -->
                        <div style="background:var(--laca-bg-alt, #f8fafc); border:1px solid var(--laca-border); border-radius:6px; padding:12px;">
                            <div style="font-size:12px; color:var(--laca-fg-muted); margin-bottom:4px;"><?php esc_html_e('Phím tắt VS Code', 'laca'); ?></div>
                            <div style="font-size:13px; font-family:monospace; color:var(--laca-fg);">
                                Terminal &rarr; Run Task &rarr; Laca...
                            </div>
                        </div>
                    </div>

                    <!-- Danh sách câu lệnh nhanh -->
                    <div style="background:#1e293b; color:#f8fafc; border-radius:6px; padding:16px; font-family:monospace; font-size:13px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #334155; padding-bottom:8px;">
                            <span style="color:#94a3b8; font-weight:bold;"><?php esc_html_e('Các lệnh WP-CLI thường dùng (chạy trong terminal Local):', 'laca'); ?></span>
                        </div>
                        <div style="display:grid; gap:8px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <code>wp laca sync-check</code>
                                <button type="button" class="laca-btn laca-btn--subtle laca-btn--sm" style="color:#38bdf8;" onclick="navigator.clipboard.writeText('wp laca sync-check'); alert('<?php echo esc_js(__('Đã sao chép lệnh!', 'laca')); ?>');">
                                    <?php esc_html_e('Sao chép', 'laca'); ?>
                                </button>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <code>wp laca pull-db --anonymize</code>
                                <button type="button" class="laca-btn laca-btn--subtle laca-btn--sm" style="color:#38bdf8;" onclick="navigator.clipboard.writeText('wp laca pull-db --anonymize'); alert('<?php echo esc_js(__('Đã sao chép lệnh!', 'laca')); ?>');">
                                    <?php esc_html_e('Sao chép', 'laca'); ?>
                                </button>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <code>wp laca drift</code>
                                <button type="button" class="laca-btn laca-btn--subtle laca-btn--sm" style="color:#38bdf8;" onclick="navigator.clipboard.writeText('wp laca drift'); alert('<?php echo esc_js(__('Đã sao chép lệnh!', 'laca')); ?>');">
                                    <?php esc_html_e('Sao chép', 'laca'); ?>
                                </button>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <code>wp laca pull-files</code>
                                <button type="button" class="laca-btn laca-btn--subtle laca-btn--sm" style="color:#38bdf8;" onclick="navigator.clipboard.writeText('wp laca pull-files'); alert('<?php echo esc_js(__('Đã sao chép lệnh!', 'laca')); ?>');">
                                    <?php esc_html_e('Sao chép', 'laca'); ?>
                                </button>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <code>wp laca restore-db</code>
                                <button type="button" class="laca-btn laca-btn--subtle laca-btn--sm" style="color:#38bdf8;" onclick="navigator.clipboard.writeText('wp laca restore-db'); alert('<?php echo esc_js(__('Đã sao chép lệnh!', 'laca')); ?>');">
                                    <?php esc_html_e('Sao chép', 'laca'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * =========================================================================
     * TAB 2: QUẢN LÝ DUNG LƯỢNG (Chi tiết & File lớn)
     * =========================================================================
     */
    private function renderStorageTab(): void
    {
        $storageCache = StorageHealth::getCached();
        $savedLargeFiles = LargeFileScanner::getSavedResult();
        ?>
        <div class="laca-storage-container">
            <!-- Segmented Sub-tabs -->
            <div class="laca-tabs laca-tabs--line" style="margin-bottom:20px;">
                <button type="button" class="laca-tabs__item is-active" data-laca-subtab="detail">
                    <span class="dashicons dashicons-chart-pie"></span>
                    <?php esc_html_e('Dung lượng chi tiết', 'laca'); ?>
                </button>
                <button type="button" class="laca-tabs__item" data-laca-subtab="large-files">
                    <span class="dashicons dashicons-media-default"></span>
                    <?php esc_html_e('File cỡ lớn (> 50 MB)', 'laca'); ?>
                    <?php if (!empty($savedLargeFiles['total_count'])) : ?>
                        <span class="laca-badge laca-badge--warning" style="margin-left:6px;"><?php echo (int) $savedLargeFiles['total_count']; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- SUBTAB 1: DUNG LƯỢNG CHI TIẾT -->
            <div id="laca-subtab-detail" class="laca-subtab-pane">
                <div id="laca-storage-detail-content">
                    <?php $this->renderStorageDetailInner($storageCache); ?>
                </div>
            </div>

            <!-- SUBTAB 2: FILE CỠ LỚN (> 50MB) -->
            <div id="laca-subtab-large-files" class="laca-subtab-pane" style="display:none;">
                <div class="laca-card" style="margin-bottom:20px;">
                    <div class="laca-card__header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h2 class="laca-card__title">
                            <span class="dashicons dashicons-search" style="color:var(--laca-accent);"></span>
                            <?php esc_html_e('Quét tìm file cỡ lớn (> 50 MB)', 'laca'); ?>
                        </h2>
                        <button type="button" class="laca-btn laca-btn--primary" id="laca-btn-scan-large-files">
                            <span class="dashicons dashicons-update"></span>
                            <span class="laca-btn-text"><?php esc_html_e('Quét file lớn', 'laca'); ?></span>
                        </button>
                    </div>
                    <div class="laca-card__body">
                        <p class="laca-card__desc" style="color:var(--laca-fg-muted); margin-bottom:16px;">
                            <?php esc_html_e('Hệ thống sẽ quét toàn bộ thư mục wp-content và các file gốc WordPress để phát hiện các file lưu trữ backup, log debug, hoặc video kích thước lớn (> 50MB).', 'laca'); ?>
                        </p>

                        <!-- Progress Bar during scan -->
                        <div id="laca-scan-progress-box" style="display:none; margin-bottom:20px; padding:16px; background:var(--laca-bg-muted); border-radius:var(--laca-radius-md);">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <span id="laca-scan-status-text" style="font-weight:500; font-size:13px;"><?php esc_html_e('Đang khởi động quét...', 'laca'); ?></span>
                            </div>
                            <div class="laca-progress">
                                <div id="laca-scan-progress-bar" class="laca-progress__bar" style="width: 30%;"></div>
                            </div>
                        </div>

                        <!-- Results Container -->
                        <div id="laca-large-files-result-container">
                            <?php $this->renderLargeFilesTable($savedLargeFiles); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render HTML nội dung chi tiết dung lượng.
     */
    public function renderStorageDetailInner(?array $data): void
    {
        if ($data === null) :
            ?>
            <div class="laca-empty">
                <div class="laca-empty__title"><?php esc_html_e('Chưa có số liệu dung lượng', 'laca'); ?></div>
                <p class="laca-empty__desc">
                    <?php esc_html_e('Hệ thống chưa tính toán dung lượng các phân vùng lưu trữ. Vui lòng nhấn nút bên dưới để bắt đầu quét.', 'laca'); ?>
                </p>
                <div style="margin-top:16px;">
                    <button type="button" class="laca-btn laca-btn--primary" data-laca-storage-refresh="1">
                        <?php esc_html_e('Tính ngay', 'laca'); ?>
                    </button>
                </div>
            </div>
            <?php
            return;
        endif;

        $wpContentBytes = (int) ($data['wp_content_bytes'] ?? 0);
        $dbBytes        = (int) ($data['db_bytes'] ?? 0);
        $totalBytes     = (int) ($data['total_bytes'] ?? ($wpContentBytes + $dbBytes));

        $pluginsBytes = (int) ($data['plugins_bytes'] ?? 0);
        $themesBytes  = (int) ($data['themes_bytes'] ?? 0);
        $uploadsBytes = (int) ($data['uploads_bytes'] ?? 0);
        $otherBytes   = (int) ($data['other_bytes'] ?? max(0, $wpContentBytes - $pluginsBytes - $themesBytes - $uploadsBytes));

        $pPlugins = $wpContentBytes > 0 ? round(($pluginsBytes / $wpContentBytes) * 100, 1) : 0;
        $pThemes  = $wpContentBytes > 0 ? round(($themesBytes / $wpContentBytes) * 100, 1) : 0;
        $pUploads = $wpContentBytes > 0 ? round(($uploadsBytes / $wpContentBytes) * 100, 1) : 0;
        $pOther   = $wpContentBytes > 0 ? round(($otherBytes / $wpContentBytes) * 100, 1) : 0;

        $topPlugins = is_array($data['top_plugins'] ?? null) ? $data['top_plugins'] : [];
        $topThemes  = is_array($data['top_themes'] ?? null) ? $data['top_themes'] : [];
        $topUploads = is_array($data['top_uploads'] ?? null) ? $data['top_uploads'] : [];
        ?>
        <!-- 3 Stat cards -->
        <div class="laca-card" style="margin-bottom:20px;">
            <div class="laca-card__header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2 class="laca-card__title"><?php esc_html_e('Tổng quan dung lượng', 'laca'); ?></h2>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size:12px; color:var(--laca-fg-muted);">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: timestamp */
                                __('Cập nhật lúc: %s', 'laca'),
                                (string) ($data['updated_at'] ?? '—')
                            )
                        );
                        ?>
                    </span>
                    <button type="button" class="laca-btn laca-btn--secondary laca-btn--sm" data-laca-storage-refresh="1">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Làm mới', 'laca'); ?>
                    </button>
                </div>
            </div>
            <div class="laca-card__body">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php esc_html_e('Thư mục /wp-content', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html((string) ($data['wp_content_formatted'] ?? size_format($wpContentBytes))); ?></span>
                    </div>
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php esc_html_e('Cơ sở dữ liệu (Database)', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html((string) ($data['db_formatted'] ?? size_format($dbBytes))); ?></span>
                    </div>
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php esc_html_e('Tổng cộng (Code + Media + DB)', 'laca'); ?></span>
                        <span class="laca-stat__value" style="color:var(--laca-accent);"><?php echo esc_html((string) ($data['total_formatted'] ?? size_format($totalBytes))); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breakdown wp-content -->
        <div class="laca-card" style="margin-bottom:20px;">
            <div class="laca-card__header">
                <h2 class="laca-card__title"><?php esc_html_e('Chi tiết thư mục /wp-content', 'laca'); ?></h2>
            </div>
            <div class="laca-card__body">
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <!-- Plugins -->
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px;">
                            <span>
                                <strong><?php esc_html_e('Plugins (Gói mở rộng)', 'laca'); ?></strong>
                                <code style="margin-left:6px; font-size:12px;">wp-content/plugins</code>
                            </span>
                            <span><strong><?php echo esc_html((string) ($data['plugins_formatted'] ?? size_format($pluginsBytes))); ?></strong> (<?php echo esc_html((string) $pPlugins); ?>%)</span>
                        </div>
                        <div class="laca-progress">
                            <div class="laca-progress__bar" style="width: <?php echo esc_attr((string) $pPlugins); ?>%; background: #3b82f6;"></div>
                        </div>
                    </div>

                    <!-- Themes -->
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px;">
                            <span>
                                <strong><?php esc_html_e('Themes (Giao diện)', 'laca'); ?></strong>
                                <code style="margin-left:6px; font-size:12px;">wp-content/themes</code>
                            </span>
                            <span><strong><?php echo esc_html((string) ($data['themes_formatted'] ?? size_format($themesBytes))); ?></strong> (<?php echo esc_html((string) $pThemes); ?>%)</span>
                        </div>
                        <div class="laca-progress">
                            <div class="laca-progress__bar" style="width: <?php echo esc_attr((string) $pThemes); ?>%; background: #8b5cf6;"></div>
                        </div>
                    </div>

                    <!-- Media Uploads -->
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px;">
                            <span>
                                <strong><?php esc_html_e('Media (Tải lên / Uploads)', 'laca'); ?></strong>
                                <code style="margin-left:6px; font-size:12px;">wp-content/uploads</code>
                            </span>
                            <span><strong><?php echo esc_html((string) ($data['uploads_formatted'] ?? size_format($uploadsBytes))); ?></strong> (<?php echo esc_html((string) $pUploads); ?>%)</span>
                        </div>
                        <div class="laca-progress">
                            <div class="laca-progress__bar" style="width: <?php echo esc_attr((string) $pUploads); ?>%; background: #10b981;"></div>
                        </div>
                    </div>

                    <!-- Other -->
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px;">
                            <span>
                                <strong><?php esc_html_e('Khác (Cache, Upgrade, Languages...)', 'laca'); ?></strong>
                                <code style="margin-left:6px; font-size:12px;">wp-content/*</code>
                            </span>
                            <span><strong><?php echo esc_html((string) ($data['other_formatted'] ?? size_format($otherBytes))); ?></strong> (<?php echo esc_html((string) $pOther); ?>%)</span>
                        </div>
                        <div class="laca-progress">
                            <div class="laca-progress__bar" style="width: <?php echo esc_attr((string) $pOther); ?>%; background: #94a3b8;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 10 breakdown cards -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px;">
            <!-- Top Plugins -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__title" style="font-size:14px;"><?php esc_html_e('Top Plugins lớn nhất', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body">
                    <?php if (empty($topPlugins)) : ?>
                        <p style="color:var(--laca-fg-muted); font-size:13px;"><?php esc_html_e('Chưa có thông tin.', 'laca'); ?></p>
                    <?php else : ?>
                        <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ($topPlugins as $item) : ?>
                                <li>
                                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                                        <span style="font-weight:500;"><?php echo esc_html((string) ($item['name'] ?? '')); ?></span>
                                        <span><?php echo esc_html((string) ($item['size_formatted'] ?? '')); ?></span>
                                    </div>
                                    <div class="laca-progress" style="height:4px;">
                                        <div class="laca-progress__bar" style="width: <?php echo esc_attr((string) ($item['percent'] ?? 0)); ?>%; background:#3b82f6;"></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Themes -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__title" style="font-size:14px;"><?php esc_html_e('Top Themes lớn nhất', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body">
                    <?php if (empty($topThemes)) : ?>
                        <p style="color:var(--laca-fg-muted); font-size:13px;"><?php esc_html_e('Chưa có thông tin.', 'laca'); ?></p>
                    <?php else : ?>
                        <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ($topThemes as $item) : ?>
                                <li>
                                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                                        <span style="font-weight:500;"><?php echo esc_html((string) ($item['name'] ?? '')); ?></span>
                                        <span><?php echo esc_html((string) ($item['size_formatted'] ?? '')); ?></span>
                                    </div>
                                    <div class="laca-progress" style="height:4px;">
                                        <div class="laca-progress__bar" style="width: <?php echo esc_attr((string) ($item['percent'] ?? 0)); ?>%; background:#8b5cf6;"></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Uploads -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__title" style="font-size:14px;"><?php esc_html_e('Top Thư mục Uploads lớn nhất', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body">
                    <?php if (empty($topUploads)) : ?>
                        <p style="color:var(--laca-fg-muted); font-size:13px;"><?php esc_html_e('Chưa có thông tin.', 'laca'); ?></p>
                    <?php else : ?>
                        <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ($topUploads as $item) : ?>
                                <li>
                                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                                        <span style="font-weight:500; font-family:monospace;"><?php echo esc_html((string) ($item['name'] ?? '')); ?></span>
                                        <span><?php echo esc_html((string) ($item['size_formatted'] ?? '')); ?></span>
                                    </div>
                                    <div class="laca-progress" style="height:4px;">
                                        <div class="laca-progress__bar" style="width: <?php echo esc_attr((string) ($item['percent'] ?? 0)); ?>%; background:#10b981;"></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render HTML bảng kết quả file lớn (> 50MB).
     */
    public function renderLargeFilesTable(?array $data): void
    {
        if ($data === null) :
            ?>
            <div class="laca-empty">
                <div class="laca-empty__title"><?php esc_html_e('Chưa quét file lớn', 'laca'); ?></div>
                <p class="laca-empty__desc">
                    <?php esc_html_e('Nhấn nút "Quét file lớn" ở trên để rà soát các file có kích thước vượt quá 50 MB.', 'laca'); ?>
                </p>
            </div>
            <?php
            return;
        endif;

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $totalCount = (int) ($data['total_count'] ?? count($items));
        $totalFormatted = (string) ($data['total_formatted'] ?? '0 B');
        ?>
        <!-- Stats and Warning -->
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:16px; margin-bottom:16px;">
            <div style="display:flex; gap:20px;">
                <div class="laca-stat">
                    <span class="laca-stat__label"><?php esc_html_e('Số file (> 50 MB)', 'laca'); ?></span>
                    <span class="laca-stat__value" style="font-size:20px;"><?php echo esc_html((string) $totalCount); ?></span>
                </div>
                <div class="laca-stat">
                    <span class="laca-stat__label"><?php esc_html_e('Tổng dung lượng', 'laca'); ?></span>
                    <span class="laca-stat__value" style="font-size:20px; color:var(--laca-danger);"><?php echo esc_html($totalFormatted); ?></span>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="laca-btn laca-btn--danger" id="laca-btn-delete-selected-files" disabled>
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Xoá file đã chọn', 'laca'); ?>
                </button>
            </div>
        </div>

        <div class="laca-alert laca-alert--warning" style="margin-bottom:16px;">
            <div class="laca-alert__title">
                <?php esc_html_e('Xem kỹ tên file trước khi xoá. Chỉ các file nằm trong vùng cho phép (uploads, cache, upgrade, backup) mới có thể chọn xoá.', 'laca'); ?>
            </div>
        </div>

        <?php if (empty($items)) : ?>
            <div class="laca-empty" style="padding:24px 0;">
                <div class="laca-empty__title" style="color:var(--laca-success);"><?php esc_html_e('Không tìm thấy file nào > 50 MB!', 'laca'); ?></div>
                <p class="laca-empty__desc"><?php esc_html_e('Dung lượng các file trên website đang ở mức tối ưu.', 'laca'); ?></p>
            </div>
        <?php else : ?>
            <div style="overflow-x:auto;">
                <table class="laca-table" style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:var(--laca-bg-muted); border-bottom:1px solid var(--laca-border);">
                            <th style="width:36px; padding:10px; text-align:center;">
                                <input type="checkbox" id="laca-select-all-deletable" title="<?php esc_attr_e('Chọn tất cả file có thể xoá', 'laca'); ?>">
                            </th>
                            <th style="padding:10px; text-align:left;"><?php esc_html_e('Tên file', 'laca'); ?></th>
                            <th style="padding:10px; text-align:left;"><?php esc_html_e('Đường dẫn tương đối', 'laca'); ?></th>
                            <th style="padding:10px; text-align:right; width:120px;"><?php esc_html_e('Dung lượng', 'laca'); ?></th>
                            <th style="padding:10px; text-align:right; width:140px;"><?php esc_html_e('Ngày sửa đổi', 'laca'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it) :
                            $canDelete = !empty($it['deletable']);
                            $lockReason = (string) ($it['lock_reason'] ?? '');
                            $typeClass = 'laca-badge--neutral';
                            if (($it['type'] ?? '') === 'backup') {
                                $typeClass = 'laca-badge--warning';
                            } elseif (($it['type'] ?? '') === 'video') {
                                $typeClass = 'laca-badge--info';
                            } elseif (($it['type'] ?? '') === 'log') {
                                $typeClass = 'laca-badge--danger';
                            }
                        ?>
                            <tr style="border-bottom:1px solid var(--laca-border);">
                                <td style="padding:10px; text-align:center;">
                                    <?php if ($canDelete) : ?>
                                        <input type="checkbox" class="laca-file-checkbox"
                                               value="<?php echo esc_attr((string) ($it['path'] ?? '')); ?>"
                                               data-name="<?php echo esc_attr((string) ($it['name'] ?? '')); ?>"
                                               data-size="<?php echo esc_attr((string) ($it['size_formatted'] ?? '')); ?>">
                                    <?php else : ?>
                                        <input type="checkbox" disabled title="<?php echo esc_attr($lockReason); ?>" style="opacity:0.4; cursor:not-allowed;">
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px;">
                                    <div style="font-weight:600;"><?php echo esc_html((string) ($it['name'] ?? '')); ?></div>
                                    <span class="laca-badge <?php echo esc_attr($typeClass); ?>" style="font-size:10px; padding:1px 6px; margin-top:3px; display:inline-block;">
                                        <?php echo esc_html((string) ($it['type_label'] ?? '')); ?>
                                    </span>
                                </td>
                                <td style="padding:10px; font-family:monospace; color:var(--laca-fg-muted); font-size:12px;">
                                    <?php echo esc_html((string) ($it['rel_path'] ?? '')); ?>
                                    <?php if (!$canDelete && $lockReason) : ?>
                                        <div style="font-size:11px; color:var(--laca-fg-subtle); margin-top:2px;">
                                            <span class="dashicons dashicons-lock" style="font-size:12px; width:12px; height:12px;"></span>
                                            <?php echo esc_html($lockReason); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px; text-align:right; font-weight:600;">
                                    <?php echo esc_html((string) ($it['size_formatted'] ?? '')); ?>
                                </td>
                                <td style="padding:10px; text-align:right; color:var(--laca-fg-muted);">
                                    <?php echo esc_html((string) ($it['mtime_formatted'] ?? '—')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif;
    }
}
