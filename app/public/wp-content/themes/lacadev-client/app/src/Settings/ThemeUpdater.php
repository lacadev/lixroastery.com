<?php

namespace App\Settings;

use App\Security\ReleaseVerifier;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LacaDev Theme Updater
 *
 * Tự động kiểm tra, xác minh chữ ký Ed25519 + SHA-256, tạo snapshot dự phòng
 * và cập nhật Parent Theme từ Hub LacaDev.
 */
class ThemeUpdater
{
    public const BLOCKED_TRANSIENT     = 'laca_theme_update_blocked';
    public const INTEGRITY_THROTTLE_PREFIX = 'laca_theme_integrity_report_';

    /** @var array<string, self> */
    private static array $instances = [];

    /** Nguồn kích hoạt cập nhật do handler bên ngoài đặt (vd: 'remote') */
    private static ?string $forcedSource = null;

    /** Cache TTL: 6 giờ */
    private int $cacheTtl = 6 * HOUR_IN_SECONDS;

    /** Cache transient key — động theo từng theme */
    private string $cacheKey;

    /** Manifest đã được xác minh chữ ký trong request hiện tại */
    private ?object $verifiedManifest = null;

    /** Trạng thái ghi nhận trước khi cài đặt để báo cáo sau khi hoàn tất */
    private string $updatingFromVersion = '';
    private float $updateStartTime = 0.0;
    private bool $updateFailedInCurrentRun = false;
    private ?\WP_Error $lastUpgradeError = null;

    public function getLastUpgradeError(): ?\WP_Error
    {
        return $this->lastUpgradeError;
    }

    /**
     * Khởi tạo singleton theo themeSlug để tránh đăng ký trùng hook giữa Admin / CLI / REST / Cron.
     */
    public static function boot(string $themeSlug, string $updateInfoUrl): self
    {
        if (!isset(self::$instances[$themeSlug])) {
            self::$instances[$themeSlug] = new self($themeSlug, $updateInfoUrl);
        }

        return self::$instances[$themeSlug];
    }

    public static function getInstance(string $themeSlug = 'lacadev-client/theme'): ?self
    {
        return self::$instances[$themeSlug] ?? null;
    }

    public static function setForcedSource(?string $source): void
    {
        self::$forcedSource = $source;
    }

    public static function detectSource(): string
    {
        if (self::$forcedSource !== null && self::$forcedSource !== '') {
            return self::$forcedSource;
        }
        if (defined('WP_CLI') && \WP_CLI) {
            return 'cli';
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return 'ajax';
        }

        return 'dashboard';
    }

    public static function clearStaticCache(string $themeSlug = 'lacadev-client/theme'): void
    {
        delete_transient('lacadev_theme_update_' . md5($themeSlug));
        delete_transient(self::BLOCKED_TRANSIENT);
        if (isset(self::$instances[$themeSlug])) {
            self::$instances[$themeSlug]->verifiedManifest = null;
        }
    }

    /**
     * Gửi báo cáo kết quả cập nhật/rollback cấu trúc chuẩn về Hub.
     *
     * @param array<string, mixed> $extra
     */
    public static function sendUpdateReport(string $result, array $extra = []): void
    {
        if (!class_exists(LacaDevTrackerClient::class)) {
            return;
        }

        $source = (string) ($extra['source'] ?? self::detectSource());
        $from   = (string) ($extra['from'] ?? '');
        $to     = (string) ($extra['to'] ?? '');
        $error  = (string) ($extra['error'] ?? '');

        $meta = array_merge([
            'from'        => $from,
            'to'          => $to,
            'result'      => $result,
            'error'       => $error,
            'sha256'      => (string) ($extra['sha256'] ?? ''),
            'duration_ms' => (int) ($extra['duration_ms'] ?? 0),
            'source'      => $source,
        ], $extra);

        $level = in_array($result, ['failed', 'integrity_failed'], true) ? 'critical' : 'info';

        $content = match ($result) {
            'success'          => sprintf('Cập nhật parent theme thành công (%s -> %s) [%s]', $from ?: '?', $to ?: '?', $source),
            'rolled_back'      => sprintf('Khôi phục (rollback) parent theme (%s -> %s) [%s]', $from ?: '?', $to ?: '?', $source),
            'integrity_failed' => sprintf('Xác minh toàn vẹn gói cập nhật parent theme thất bại (%s): %s', $to ?: '?', $error ?: 'signature/checksum mismatch'),
            default            => sprintf('Cập nhật parent theme thất bại (%s -> %s): %s', $from ?: '?', $to ?: '?', $error ?: 'unknown error'),
        };

        LacaDevTrackerClient::sendStructuredLog('theme_update_result', $meta, $level, $content);
    }

    /**
     * @param string $themeSlug     Slug đầy đủ của theme ('lacadev-client/theme').
     * @param string $updateInfoUrl URL file manifest JSON trên Hub.
     */
    public function __construct(
        private string $themeSlug,
        private string $updateInfoUrl,
    ) {
        $this->updateInfoUrl = (string) apply_filters('laca_theme_update_json_url', $this->updateInfoUrl);
        $this->cacheKey      = 'lacadev_theme_update_' . md5($this->themeSlug);
        self::$instances[$this->themeSlug] = $this;

        add_filter('pre_set_site_transient_update_themes', [$this, 'checkForUpdate']);
        add_filter('themes_api', [$this, 'themeInfo'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'verifiedDownload'], 10, 4);
        add_filter('upgrader_pre_install', [$this, 'snapshotBeforeInstall'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);
        // Ưu tiên 9: phải chạy trước Theme_Upgrader::check_package (ưu tiên 10).
        add_filter('upgrader_source_selection', [$this, 'allowNestedThemePackage'], 9, 4);
        add_filter('upgrader_source_selection', [$this, 'filterSourceSelection'], 10, 4);
        add_filter('upgrader_package_options', [$this, 'backupWholeThemeFolder']);
        add_filter('http_request_args', [$this, 'filterDownloadRequestArgs'], 10, 2);
        add_filter('http_request_host_is_external', [$this, 'allowLocalHubHost'], 10, 2);
        // Ưu tiên 9: gỡ current_after của core khi parent theme active trực tiếp để tránh switch_theme sai.
        add_filter('upgrader_post_install', [$this, 'preventFalseThemeSwitch'], 9, 3);
        // Hỗ trợ AJAX "Update now" trên themes.php khi slug có dấu gạch chéo.
        add_action('wp_ajax_update-theme', [$this, 'handleAjaxThemeUpdate'], 0);
        add_action('wp_ajax_update_theme', [$this, 'handleAjaxThemeUpdate'], 0);

        if (is_admin()) {
            add_action('admin_notices', [$this, 'renderBlockedUpdateNotice']);
        }
    }

    /**
     * Hiển thị cảnh báo trong Admin khi bản cập nhật mới bị chặn do yêu cầu phiên bản PHP / WordPress.
     */
    public function renderBlockedUpdateNotice(): void
    {
        if (!current_user_can('update_themes')) {
            return;
        }

        $blocked = get_transient(self::BLOCKED_TRANSIENT);
        if (!is_array($blocked) || empty($blocked['reason'])) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html((string) $blocked['reason']) . '</p></div>';
    }

    /**
     * Core wp_ajax_update_theme dùng preg_replace('/[^A-z0-9_\-]/', '', $slug)
     * làm mất dấu / trong 'lacadev-client/theme'. Intercept tại priority 0.
     */
    public function handleAjaxThemeUpdate(): void
    {
        $rawSlug       = isset($_POST['slug']) ? wp_unslash($_POST['slug']) : '';
        $cleanSlug     = preg_replace('/[^A-z0-9_\-]/', '', $rawSlug);
        $expectedClean = preg_replace('/[^A-z0-9_\-]/', '', $this->themeSlug);

        if ($rawSlug !== $this->themeSlug && $cleanSlug !== $expectedClean) {
            return;
        }

        check_ajax_referer('updates');

        $status = [
            'update'     => 'theme',
            'slug'       => $this->themeSlug,
            'oldVersion' => '',
            'newVersion' => '',
        ];

        if (!current_user_can('update_themes')) {
            $status['errorMessage'] = __('Bạn không có quyền cập nhật giao diện trên trang này.', 'laca');
            wp_send_json_error($status);
        }

        $theme = wp_get_theme($this->themeSlug);
        if ($theme->exists()) {
            $status['oldVersion'] = $theme->get('Version');
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Theme_Upgrader($skin);
        $result   = $upgrader->upgrade($this->themeSlug);

        if (is_wp_error($result)) {
            $status['errorCode']    = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error($status);
        } elseif ($result === false) {
            if ($skin->get_errors()->has_errors()) {
                $status['errorMessage'] = $skin->get_error_messages();
            } else {
                $status['errorMessage'] = $upgrader->strings['up_to_date'] ?? __('Giao diện đang ở phiên bản mới nhất.', 'laca');
            }
            wp_send_json_error($status);
        }

        $updatedTheme = wp_get_theme($this->themeSlug);
        if ($updatedTheme->exists()) {
            $status['newVersion'] = $updatedTheme->get('Version');
        }

        wp_send_json_success($status);
    }

    /**
     * Cho phép request tới host local (vd .local) khi ở môi trường local.
     */
    public function allowLocalHubHost(bool $allow, string $host): bool
    {
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local') {
            if (str_ends_with($host, '.local') || $host === 'lacadev.local') {
                return true;
            }
        }

        return $allow;
    }

    /**
     * Đổi slug sao lưu tạm thành thư mục gốc của theme để tránh lỗi thư mục lồng cấp.
     */
    public function backupWholeThemeFolder(array $options): array
    {
        $hookExtra = $options['hook_extra'] ?? [];
        if (($hookExtra['theme'] ?? '') !== $this->themeSlug || empty($hookExtra['temp_backup']['slug'])) {
            return $options;
        }

        $options['hook_extra']['temp_backup']['slug'] = strtok($this->themeSlug, '/');
        return $options;
    }

    /**
     * Xác minh SHA-256 của gói tải về trước khi giải nén (`upgrader_pre_download`).
     */
    public function verifiedDownload(mixed $reply, string $package, mixed $upgrader, array $hookExtra = []): mixed
    {
        if ($reply !== false) {
            return $reply;
        }

        $theme   = (string) ($hookExtra['theme'] ?? '');
        $themes  = (array) ($hookExtra['themes'] ?? []);
        $manifest = $this->verifiedManifest ?? $this->getRemoteInfo();

        $matchesTheme = ($theme === $this->themeSlug) || in_array($this->themeSlug, $themes, true);
        $matchesUrl   = ($manifest !== null && !empty($manifest->download_url) && $package === (string) $manifest->download_url);

        if (!$matchesTheme && !$matchesUrl) {
            return false;
        }

        $this->updateFailedInCurrentRun = false;
        $this->lastUpgradeError         = null;
        $this->updatingFromVersion      = $this->getCurrentVersion();
        $this->updateStartTime          = microtime(true);

        $allowUnsigned = (bool) apply_filters('laca_allow_unsigned_theme_manifest', false);
        if ($manifest === null || (!$allowUnsigned && empty($manifest->sha256))) {
            $this->updateFailedInCurrentRun = true;
            $err = new \WP_Error(
                'laca_manifest_unverified',
                __('Không thể xác minh chữ ký manifest phát hành — đã huỷ cập nhật.', 'laca')
            );
            $this->lastUpgradeError = $err;
            $this->reportResult('integrity_failed', [
                'from'  => $this->updatingFromVersion,
                'to'    => (string) ($manifest->version ?? ''),
                'error' => 'missing_verified_manifest',
            ]);
            return $err;
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmp = download_url($package, 300);
        if (is_wp_error($tmp)) {
            $this->updateFailedInCurrentRun = true;
            $this->lastUpgradeError         = $tmp;
            $this->reportResult('failed', [
                'from'        => $this->updatingFromVersion,
                'to'          => (string) ($manifest->version ?? ''),
                'error'       => $tmp->get_error_code() . ': ' . $tmp->get_error_message(),
                'duration_ms' => (int) round((microtime(true) - $this->updateStartTime) * 1000),
            ]);
            return $tmp;
        }

        if (!$allowUnsigned && !ReleaseVerifier::verifyFile($tmp, (string) $manifest->sha256)) {
            $actualHash = is_file($tmp) ? (string) hash_file('sha256', $tmp) : '';
            wp_delete_file($tmp);
            $this->updateFailedInCurrentRun = true;
            $err = new \WP_Error(
                'laca_package_checksum',
                __('Gói cập nhật không khớp checksum đã ký — đã huỷ cập nhật.', 'laca')
            );
            $this->lastUpgradeError = $err;
            $this->reportResult('integrity_failed', [
                'from'        => $this->updatingFromVersion,
                'to'          => (string) ($manifest->version ?? ''),
                'sha256'      => $actualHash,
                'error'       => 'laca_package_checksum',
                'duration_ms' => (int) round((microtime(true) - $this->updateStartTime) * 1000),
            ]);

            return $err;
        }

        return $tmp;
    }

    /**
     * Tạo snapshot dự phòng trước khi ghi đè thư mục theme (`upgrader_pre_install`).
     */
    public function snapshotBeforeInstall(mixed $response, array $hookExtra = []): mixed
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $theme  = (string) ($hookExtra['theme'] ?? '');
        $themes = (array) ($hookExtra['themes'] ?? []);
        if ($theme !== $this->themeSlug && !in_array($this->themeSlug, $themes, true)) {
            return $response;
        }

        if ($this->updatingFromVersion === '') {
            $this->updatingFromVersion = $this->getCurrentVersion();
        }
        if ($this->updateStartTime <= 0.0) {
            $this->updateStartTime = microtime(true);
        }

        $themesRoot = get_theme_root($this->themeSlug) ?: (WP_CONTENT_DIR . '/themes');
        $parentDir  = trailingslashit($themesRoot) . strtok($this->themeSlug, '/');

        if (is_dir($parentDir)) {
            $snap = ThemeSnapshot::create($parentDir, $this->updatingFromVersion ?: 'unknown');
            if (is_wp_error($snap)) {
                $allowWithoutSnapshot = (bool) apply_filters('laca_update_allow_without_snapshot', false);
                if (!$allowWithoutSnapshot) {
                    $this->updateFailedInCurrentRun = true;
                    $this->reportResult('failed', [
                        'from'        => $this->updatingFromVersion,
                        'to'          => (string) ($this->verifiedManifest->version ?? ''),
                        'error'       => $snap->get_error_code() . ': ' . $snap->get_error_message(),
                        'duration_ms' => (int) round((microtime(true) - $this->updateStartTime) * 1000),
                    ]);

                    return $snap;
                }
            }
        }

        return $response;
    }

    /**
     * Hỗ trợ gói theme lồng cấp (`theme/style.css`).
     */
    public function allowNestedThemePackage(mixed $source, mixed $remoteSource, mixed $upgrader, array $hookExtra = []): mixed
    {
        if (is_wp_error($source) || !($upgrader instanceof \Theme_Upgrader)) {
            return $source;
        }

        $theme  = $hookExtra['theme'] ?? '';
        $themes = (array) ($hookExtra['themes'] ?? []);
        if ($theme !== $this->themeSlug && !in_array($this->themeSlug, $themes, true)) {
            return $source;
        }

        $relStyle = basename($this->themeSlug) . '/style.css';

        global $wp_filesystem;
        $workingDir = (string) $source;
        if ($wp_filesystem) {
            $workingDir = str_replace($wp_filesystem->wp_content_dir(), trailingslashit(WP_CONTENT_DIR), $workingDir);
        }
        if (is_dir($workingDir) && !file_exists(trailingslashit($workingDir) . $relStyle)) {
            $this->updateFailedInCurrentRun = true;
            return new \WP_Error(
                'lacadev_theme_package_invalid',
                sprintf(
                    /* translators: %s: đường dẫn style.css trong gói cập nhật */
                    __('Gói cập nhật không hợp lệ: thiếu %s.', 'laca'),
                    $relStyle
                )
            );
        }

        remove_filter('upgrader_source_selection', [$upgrader, 'check_package']);
        return $source;
    }

    /**
     * Ngăn core tự switch_theme sai khi parent theme lồng cấp đang active trực tiếp.
     */
    public function preventFalseThemeSwitch(mixed $response, array $hookExtra, array $result): mixed
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $theme = $hookExtra['theme'] ?? '';
        if ($theme !== $this->themeSlug) {
            return $response;
        }

        if (get_stylesheet() === $this->themeSlug) {
            global $wp_filter;
            if (isset($wp_filter['upgrader_post_install']->callbacks[10])) {
                foreach ($wp_filter['upgrader_post_install']->callbacks[10] as $id => $callback) {
                    if (is_array($callback['function']) && isset($callback['function'][1]) && $callback['function'][1] === 'current_after') {
                        $upgrader = $callback['function'][0];
                        if ($upgrader instanceof \Theme_Upgrader) {
                            remove_filter('upgrader_post_install', [$upgrader, 'current_after'], 10);
                            if (empty($upgrader->bulk)) {
                                $upgrader->maintenance_mode(false);
                            }
                            break;
                        }
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Hook vào WordPress update check — kiểm tra tương thích PHP/WP trước khi đưa vào response.
     */
    public function checkForUpdate(mixed $transient): mixed
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $remoteInfo = $this->getRemoteInfo();
        if (!$remoteInfo || empty($remoteInfo->version)) {
            unset($transient->response[$this->themeSlug]);
            return $transient;
        }

        $currentVersion = (string) ($transient->checked[$this->themeSlug] ?? $this->getCurrentVersion());
        if ($currentVersion === '' || !version_compare((string) $remoteInfo->version, $currentVersion, '>')) {
            delete_transient(self::BLOCKED_TRANSIENT);
            unset($transient->response[$this->themeSlug]);
            return $transient;
        }

        $requiresPhp = (string) ($remoteInfo->requires_php ?? '8.1');
        $requiresWp  = (string) ($remoteInfo->requires ?? '6.4');
        $wpVersion   = (string) ($GLOBALS['wp_version'] ?? get_bloginfo('version'));

        if (version_compare(PHP_VERSION, $requiresPhp, '<')) {
            $reason = sprintf(
                /* translators: 1: new theme version, 2: required PHP version, 3: current PHP version */
                __('Bản cập nhật Parent Theme %1$s yêu cầu PHP >= %2$s (hiện tại: %3$s).', 'laca'),
                (string) $remoteInfo->version,
                $requiresPhp,
                PHP_VERSION
            );
            set_transient(self::BLOCKED_TRANSIENT, [
                'version'      => (string) $remoteInfo->version,
                'requires_php' => $requiresPhp,
                'requires'     => $requiresWp,
                'reason'       => $reason,
            ], $this->cacheTtl);
            unset($transient->response[$this->themeSlug]);
            return $transient;
        }

        if ($wpVersion !== '' && version_compare($wpVersion, $requiresWp, '<')) {
            $reason = sprintf(
                /* translators: 1: new theme version, 2: required WordPress version, 3: current WordPress version */
                __('Bản cập nhật Parent Theme %1$s yêu cầu WordPress >= %2$s (hiện tại: %3$s).', 'laca'),
                (string) $remoteInfo->version,
                $requiresWp,
                $wpVersion
            );
            set_transient(self::BLOCKED_TRANSIENT, [
                'version'      => (string) $remoteInfo->version,
                'requires_php' => $requiresPhp,
                'requires'     => $requiresWp,
                'reason'       => $reason,
            ], $this->cacheTtl);
            unset($transient->response[$this->themeSlug]);
            return $transient;
        }

        delete_transient(self::BLOCKED_TRANSIENT);

        $transient->response[$this->themeSlug] = [
            'theme'        => $this->themeSlug,
            'new_version'  => (string) $remoteInfo->version,
            'url'          => (string) ($remoteInfo->details_url ?? 'https://lacadev.com'),
            'package'      => (string) $remoteInfo->download_url,
            'requires'     => $requiresWp,
            'requires_php' => $requiresPhp,
        ];

        return $transient;
    }

    /**
     * Trả về thông tin theme cho màn hình "View version details".
     */
    public function themeInfo(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'theme_information' || empty($args->slug) || $args->slug !== $this->themeSlug) {
            return $result;
        }

        $remoteInfo = $this->getRemoteInfo();
        if (!$remoteInfo) {
            return $result;
        }

        return (object) [
            'name'          => $remoteInfo->name ?? 'La Cà Dev - Client',
            'slug'          => $this->themeSlug,
            'version'       => $remoteInfo->version,
            'author'        => '<a href="https://lacadev.com">La Cà Dev</a>',
            'homepage'      => 'https://lacadev.com',
            'download_link' => $remoteInfo->download_url,
            'last_updated'  => $remoteInfo->last_updated ?? '',
            'requires'      => $remoteInfo->requires ?? '6.4',
            'requires_php'  => $remoteInfo->requires_php ?? '8.1',
            'sections'      => [
                'description' => $remoteInfo->description ?? 'La Cà Dev Parent Theme — cập nhật tự động từ lacadev.com',
                'changelog'   => $remoteInfo->changelog ?? '',
            ],
        ];
    }

    /**
     * Xoá cache và báo cáo thành công về Hub sau khi cài đặt hoàn tất (`upgrader_process_complete`).
     */
    public function clearCache(mixed $upgrader, array $options): void
    {
        if (($options['type'] ?? '') !== 'theme') {
            return;
        }

        $singleTheme = (string) ($options['theme'] ?? '');
        $bulkThemes  = (array) ($options['themes'] ?? []);
        $isTarget    = ($singleTheme === $this->themeSlug) || in_array($this->themeSlug, $bulkThemes, true);

        if (!$isTarget) {
            return;
        }

        // Tránh gửi báo cáo trùng 2 lần khi bulk_upgrade gọi cả single run lẫn bulk upgrader_process_complete.
        if (!empty($options['bulk']) && $this->updatingFromVersion === '') {
            delete_transient($this->cacheKey);
            return;
        }

        delete_transient($this->cacheKey);

        if ($this->updateFailedInCurrentRun) {
            return;
        }

        if ($upgrader instanceof \WP_Upgrader && is_wp_error($upgrader->result)) {
            return;
        }

        wp_clean_themes_cache();
        $newVersion = $this->getCurrentVersion();
        $fromVer    = $this->updatingFromVersion;
        $durationMs = $this->updateStartTime > 0
            ? (int) round((microtime(true) - $this->updateStartTime) * 1000)
            : 0;

        $sha256 = (string) ($this->verifiedManifest->sha256 ?? '');

        // Reset cho lượt tiếp theo (đặc biệt khi bulk_upgrade).
        $this->updatingFromVersion = '';
        $this->updateStartTime     = 0.0;

        $this->reportResult('success', [
            'from'        => $fromVer,
            'to'          => $newVersion,
            'sha256'      => $sha256,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Báo cáo kết quả cập nhật lên Hub.
     *
     * @param array<string, mixed> $meta
     */
    private function reportResult(string $result, array $meta = []): void
    {
        $meta['source'] = self::detectSource();
        self::sendUpdateReport($result, $meta);
    }

    /**
     * Kiểm tra trạng thái manifest phục vụ WP-CLI `wp laca-theme status`.
     *
     * @return array{
     *   cached_error: array<string,mixed>|null,
     *   manifest: object|null,
     *   signature_valid: bool,
     *   error_code: string|null
     * }
     */
    public function inspectManifestStatus(): array
    {
        $cached = get_transient($this->cacheKey);
        if (is_array($cached) && !empty($cached['error'])) {
            return [
                'cached_error'    => $cached,
                'manifest'        => null,
                'signature_valid' => false,
                'error_code'      => (string) ($cached['reason'] ?? 'fetch_failed'),
            ];
        }

        $info = $this->getRemoteInfo();
        if ($info !== null) {
            return [
                'cached_error'    => null,
                'manifest'        => $info,
                'signature_valid' => ReleaseVerifier::verifyManifest($info),
                'error_code'      => null,
            ];
        }

        $afterCached = get_transient($this->cacheKey);
        return [
            'cached_error'    => is_array($afterCached) ? $afterCached : null,
            'manifest'        => null,
            'signature_valid' => false,
            'error_code'      => is_array($afterCached) ? (string) ($afterCached['reason'] ?? 'fetch_failed') : 'fetch_failed',
        ];
    }

    /**
     * Fetch và xác minh chữ ký Ed25519 của manifest từ Hub, cache 6 giờ.
     */
    private function getRemoteInfo(): ?object
    {
        $allowUnsigned = (bool) apply_filters('laca_allow_unsigned_theme_manifest', false);
        $cached        = get_transient($this->cacheKey);

        if ($cached !== false) {
            if (is_array($cached) && !empty($cached['error'])) {
                return null;
            }
            if (is_object($cached)) {
                if ($allowUnsigned || ReleaseVerifier::verifyManifest($cached)) {
                    $this->verifiedManifest = $cached;
                    return $cached;
                }
            }
            delete_transient($this->cacheKey);
        }

        $response = wp_remote_get($this->updateInfoUrl, [
            'timeout'    => 10,
            'user-agent' => 'LacaDev-ThemeUpdater/' . ($this->getCurrentVersion() ?: '1.0'),
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient($this->cacheKey, [
                'error'  => true,
                'reason' => 'http_error',
            ], HOUR_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if (!is_object($data) || empty($data->version)) {
            set_transient($this->cacheKey, [
                'error'  => true,
                'reason' => 'invalid_json',
            ], HOUR_IN_SECONDS);
            return null;
        }

        if (!$allowUnsigned && !ReleaseVerifier::verifyManifest($data)) {
            set_transient($this->cacheKey, [
                'error'   => true,
                'reason'  => 'integrity_failed',
                'version' => (string) ($data->version ?? ''),
            ], HOUR_IN_SECONDS);

            $throttleKey = self::INTEGRITY_THROTTLE_PREFIX . md5($this->themeSlug);
            if (!get_transient($throttleKey)) {
                set_transient($throttleKey, 1, 6 * HOUR_IN_SECONDS);
                $this->reportResult('integrity_failed', [
                    'from'   => $this->getCurrentVersion(),
                    'to'     => (string) ($data->version ?? ''),
                    'sha256' => (string) ($data->sha256 ?? ''),
                    'error'  => 'manifest_signature_invalid',
                ]);
            }

            return null;
        }

        $this->verifiedManifest = $data;
        set_transient($this->cacheKey, $data, $this->cacheTtl);

        update_option(ChildBuildNotice::MANIFEST_META_OPTION, [
            'version'                   => (string) $data->version,
            'child_rebuild_recommended' => !empty($data->child_rebuild_recommended),
            'verified_at'               => time(),
        ], false);

        return $data;
    }

    /**
     * Đảm bảo thư mục giải nén được đổi tên về đúng slug theme (`lacadev-client`).
     *
     * @return string|\WP_Error
     */
    public function filterSourceSelection(string $source, string $remoteSource, mixed $upgrader, array $hookExtra = []): string|\WP_Error
    {
        $theme  = $hookExtra['theme'] ?? '';
        $themes = (array) ($hookExtra['themes'] ?? []);
        if ($theme !== $this->themeSlug && $theme !== basename($this->themeSlug) && !in_array($this->themeSlug, $themes, true)) {
            return $source;
        }

        $targetDirName = basename($this->themeSlug);
        if (str_contains($this->themeSlug, '/')) {
            $parts         = explode('/', $this->themeSlug);
            $targetDirName = $parts[0];
        }

        $currentDirName = basename(rtrim($source, '/'));
        if ($currentDirName === $targetDirName) {
            return $source;
        }

        global $wp_filesystem;
        $newSource = trailingslashit(dirname(rtrim($source, '/'))) . $targetDirName . '/';

        if ($wp_filesystem && $wp_filesystem->move($source, $newSource)) {
            return $newSource;
        }

        return $source;
    }

    /**
     * Tự động ký HMAC vào request tải gói cập nhật theme từ Hub.
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function filterDownloadRequestArgs(array $args, string $url): array
    {
        $parsed = parse_url($url);
        $path   = $parsed['path'] ?? '';

        if (!str_starts_with($path, '/wp-json/laca/v1/theme-package/') || !$this->isTrustedHubUrl($url)) {
            return $args;
        }

        $trackerKey = LacaDevTrackerClient::getTrackerKey() ?: LacaDevTrackerClient::getSecretKey();
        $projectId  = LacaDevTrackerClient::getProjectId();

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = [];
        }

        if (!empty($trackerKey) && !empty($projectId)) {
            $sigHeaders = \App\Security\RequestSigner::sign(
                'GET',
                $path,
                '',
                $trackerKey,
                $projectId
            );
            $args['headers'] = array_merge($args['headers'], $sigHeaders);
        }

        // Chỉ gửi key thô (legacy) khi KHÔNG ký được.
        if (!empty($trackerKey) && empty($projectId)) {
            $args['headers']['X-Laca-Key'] = $trackerKey;
        }

        $args['timeout'] = max((int) ($args['timeout'] ?? 30), 300);

        return $args;
    }

    /**
     * Chỉ ký/gửi key tới đúng host của Hub và bắt buộc https ngoài môi trường local.
     */
    private function isTrustedHubUrl(string $url): bool
    {
        $normalize = static fn($u) => strtolower(rtrim((string) parse_url((string) $u, PHP_URL_HOST), '.'));

        $host = $normalize($url);
        if ($host === '') {
            return false;
        }

        $trusted = array_filter([
            $normalize($this->updateInfoUrl),
            $normalize(LacaDevTrackerClient::getEndpoint()),
        ]);
        if (!in_array($host, $trusted, true)) {
            return false;
        }

        $isLocal = function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local';
        return $isLocal || parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    /**
     * Lấy version hiện tại của theme từ style.css.
     */
    public function getCurrentVersion(): string
    {
        $themeData = wp_get_theme($this->themeSlug);
        return $themeData->get('Version') ?: '';
    }
}
