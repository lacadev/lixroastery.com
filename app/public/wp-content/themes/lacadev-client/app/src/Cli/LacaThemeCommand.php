<?php

namespace App\Cli;

use App\Settings\ChildBuildNotice;
use App\Settings\ThemeSnapshot;
use App\Settings\ThemeUpdater;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quản lý cập nhật, kiểm tra chữ ký, snapshot và khôi phục Parent Theme (`wp laca-theme ...`).
 */
class LacaThemeCommand
{
    private const THEME_SLUG = 'lacadev-client/theme';

    private function ensureUpdater(): ThemeUpdater
    {
        $existing = ThemeUpdater::getInstance(self::THEME_SLUG);
        if ($existing !== null) {
            return $existing;
        }

        $defaultJsonUrl = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local')
            ? 'http://lacadev.local/theme-updates/lacadev-client.json'
            : 'https://lacadev.com/theme-updates/lacadev-client.json';

        $updateJsonUrl = (string) apply_filters('laca_theme_update_json_url', $defaultJsonUrl);

        return ThemeUpdater::boot(self::THEME_SLUG, $updateJsonUrl);
    }

    /**
     * Xem trạng thái phiên bản cài đặt, chữ ký manifest, cập nhật và build-meta của theme con.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Định dạng kết quả đầu ra (`table` hoặc `json`). Mặc định: `table`.
     *
     * @when after_wp_load
     */
    public function status(array $args, array $assocArgs): void
    {
        $updater          = $this->ensureUpdater();
        $installedVersion = $updater->getCurrentVersion();
        $inspect          = $updater->inspectManifestStatus();
        $manifest         = $inspect['manifest'];

        $transient       = get_site_transient('update_themes');
        $updateAvailable = is_object($transient) && !empty($transient->response[self::THEME_SLUG]);

        $blockedData = get_transient(ThemeUpdater::BLOCKED_TRANSIENT);
        $blockedMsg  = (is_array($blockedData) && !empty($blockedData['reason']))
            ? (string) $blockedData['reason']
            : null;

        if ($manifest !== null && $blockedMsg === null) {
            $manifestVer = (string) ($manifest->version ?? '');
            if ($manifestVer !== '' && $installedVersion !== '' && ThemeSnapshot::compareVersions($manifestVer, $installedVersion, '>')) {
                $updateAvailable = true;
            }
        }

        $payload = [
            'slug'              => self::THEME_SLUG,
            'installed_version' => $installedVersion,
            'manifest_version'  => $manifest !== null ? (string) ($manifest->version ?? '') : null,
            'signature_valid'   => (bool) $inspect['signature_valid'],
            'sha256'            => $manifest !== null ? (string) ($manifest->sha256 ?? '') : null,
            'update_available'  => $updateAvailable,
            'blocked'           => $blockedMsg,
            'error'             => $inspect['error_code'],
            'child_build_meta'  => ChildBuildNotice::readChildBuildMeta(),
        ];

        $format = strtolower((string) ($assocArgs['format'] ?? 'table'));
        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }

        \WP_CLI::line(sprintf('Installed version : %s', $payload['installed_version'] ?: 'N/A'));
        \WP_CLI::line(sprintf('Manifest version  : %s', $payload['manifest_version'] ?? 'N/A'));
        \WP_CLI::line(sprintf('Signature valid   : %s', $payload['signature_valid'] ? 'yes' : 'no'));
        \WP_CLI::line(sprintf('Package SHA-256   : %s', $payload['sha256'] ?? 'N/A'));
        \WP_CLI::line(sprintf('Update available  : %s', $payload['update_available'] ? 'yes' : 'no'));
        \WP_CLI::line(sprintf('Blocked reason    : %s', $payload['blocked'] ?? 'none'));
        \WP_CLI::line(sprintf('Error status      : %s', $payload['error'] ?? 'none'));
        \WP_CLI::line(sprintf(
            'Child build-meta  : %s',
            $payload['child_build_meta'] ? (string) wp_json_encode($payload['child_build_meta'], JSON_UNESCAPED_SLASHES) : 'missing'
        ));
    }

    /**
     * Xoá cache manifest và ép WordPress kiểm tra bản cập nhật theme ngay lập tức.
     *
     * @when after_wp_load
     */
    public function check(array $args, array $assocArgs): void
    {
        $updater = $this->ensureUpdater();
        ThemeUpdater::clearStaticCache(self::THEME_SLUG);
        delete_transient(ThemeUpdater::INTEGRITY_THROTTLE_PREFIX . md5(self::THEME_SLUG));
        delete_site_transient('update_themes');

        require_once ABSPATH . 'wp-admin/includes/update.php';
        wp_update_themes();

        $inspect = $updater->inspectManifestStatus();
        if (!$inspect['signature_valid']) {
            \WP_CLI::warning(sprintf('Manifest check failed (%s).', $inspect['error_code'] ?? 'unknown'));
            return;
        }

        $transient = get_site_transient('update_themes');
        if (is_object($transient) && !empty($transient->response[self::THEME_SLUG]['new_version'])) {
            \WP_CLI::success(sprintf(
                'Update available: %s -> %s',
                $updater->getCurrentVersion(),
                (string) $transient->response[self::THEME_SLUG]['new_version']
            ));
            return;
        }

        \WP_CLI::success(sprintf('Theme is up to date (%s).', $updater->getCurrentVersion()));
    }

    /**
     * Cập nhật Parent Theme thông qua `Theme_Upgrader` (có kiểm tra chữ ký, SHA-256 và snapshot).
     *
     * @when after_wp_load
     */
    public function update(array $args, array $assocArgs): void
    {
        $updater = $this->ensureUpdater();
        ThemeUpdater::clearStaticCache(self::THEME_SLUG);
        delete_site_transient('update_themes');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        wp_update_themes();

        $skin     = new \Automatic_Upgrader_Skin();
        $upgrader = new \Theme_Upgrader($skin);
        $result   = $upgrader->upgrade(self::THEME_SLUG);

        $lastErr = $updater->getLastUpgradeError();
        if (is_wp_error($lastErr)) {
            \WP_CLI::error($lastErr->get_error_code() . ': ' . $lastErr->get_error_message());
        }

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_code() . ': ' . $result->get_error_message());
        }

        if ($result === false || $result === null) {
            $messages = $skin->get_upgrade_messages();
            \WP_CLI::error('Upgrade failed: ' . (!empty($messages) ? implode(' | ', $messages) : 'no update available or filesystem error.'));
        }

        wp_clean_themes_cache();
        \WP_CLI::success(sprintf('Parent theme updated to version %s.', $updater->getCurrentVersion()));
    }

    /**
     * Liệt kê các bản snapshot dự phòng của Parent Theme.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Định dạng (`table` hoặc `json`). Mặc định: `table`.
     *
     * @when after_wp_load
     */
    public function snapshots(array $args, array $assocArgs): void
    {
        $items  = ThemeSnapshot::list();
        $format = strtolower((string) ($assocArgs['format'] ?? 'table'));

        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }

        if (empty($items)) {
            \WP_CLI::line('No snapshots found.');
            return;
        }

        \WP_CLI\Utils\format_items('table', $items, ['file', 'version', 'type', 'size', 'created_at']);
    }

    /**
     * Khôi phục Parent Theme từ bản snapshot gần nhất (hoặc chỉ định qua `--file`).
     *
     * ## OPTIONS
     *
     * [--file=<name>]
     * : Tên file snapshot cụ thể trong danh sách `snapshots`.
     *
     * @when after_wp_load
     */
    public function rollback(array $args, array $assocArgs): void
    {
        $this->ensureUpdater();
        $file   = isset($assocArgs['file']) ? trim((string) $assocArgs['file']) : '';
        $result = ThemeSnapshot::restore($file);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_code() . ': ' . $result->get_error_message());
        }

        wp_clean_themes_cache();
        $ver = wp_get_theme(self::THEME_SLUG)->get('Version') ?: 'unknown';
        \WP_CLI::success(sprintf('Parent theme rolled back to version %s.', $ver));
    }
}
