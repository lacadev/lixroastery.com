<?php

namespace App\Cli\Sync;

use App\Settings\LacaTools\Management\SiteConfig\ConfigRegistry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LocalSafe
 *
 * Đảm bảo database sau khi kéo từ production về môi trường local
 * được làm sạch, ngắt kết nối gửi ra ngoài, đổi URL an toàn và ẩn danh.
 */
class LocalSafe
{
    /**
     * Thực hiện toàn bộ quy trình làm sạch và an toàn cho local
     *
     * @param string $prodUrl URL production
     * @param string $localUrl URL local
     * @param bool $anonymize Có ẩn danh thông tin cá nhân hay không
     * @return array{success: bool, steps: array<string>, errors: array<string>}
     */
    public static function makeSafe(string $prodUrl, string $localUrl = '', bool $anonymize = false): array
    {
        $steps = [];
        $errors = [];

        if (empty($localUrl)) {
            $localUrl = home_url();
        }

        $prodUrl = rtrim(trim($prodUrl), '/');
        $localUrl = rtrim(trim($localUrl), '/');

        // 1. Search & Replace URL
        if (!empty($prodUrl) && !empty($localUrl) && $prodUrl !== $localUrl) {
            $srResult = self::replaceUrls($prodUrl, $localUrl);
            $steps[] = sprintf(__('Đã thay thế URL từ %s sang %s.', 'laca'), $prodUrl, $localUrl);
            if (!empty($srResult['output'])) {
                $steps[] = $srResult['output'];
            }
        }

        // 2. Ngắt kết nối hub và các key tracker danh tính
        self::neutralizeHubAndTracker();
        $steps[] = __('Đã vô hiệu hóa danh tính site và ngắt kết nối Hub/Tracker.', 'laca');

        // 3. Vô hiệu hóa Discord hub đẩy thông báo ra production
        self::neutralizeDiscordHub();
        $steps[] = __('Đã tắt chế độ cho phép gửi thông báo ngoài production trong Discord Hub.', 'laca');

        // 4. Kiểm tra và ngắt SMTP thật nếu cần
        self::neutralizeSmtp();
        $steps[] = __('Đã kiểm tra cấu hình gửi email (SMTP).', 'laca');

        // 5. Tắt các plugin chỉ dành riêng cho production
        $deactivated = self::deactivateProductionPlugins();
        if (!empty($deactivated)) {
            $steps[] = sprintf(__('Đã tạm tắt các plugin production: %s', 'laca'), implode(', ', $deactivated));
        }

        // 6. Reset Cron, Transient, Rewrite và Cache
        self::resetRuntimeCaches();
        $steps[] = __('Đã xóa transient, flush rewrite rules và làm mới bộ đệm ThemeOptionsCache.', 'laca');

        // 7. Ghi nhận option laca_prod_url và laca_last_pull
        update_option('laca_prod_url', $prodUrl, false);

        // 8. Ẩn danh dữ liệu cá nhân nếu được yêu cầu
        if ($anonymize) {
            $anonResult = self::anonymizeData();
            $steps[] = sprintf(
                __('Đã ẩn danh dữ liệu: %d người dùng, %d đơn liên hệ, %d email log.', 'laca'),
                $anonResult['users'],
                $anonResult['contacts'],
                $anonResult['emails']
            );
        }

        return [
            'success' => empty($errors),
            'steps'   => $steps,
            'errors'  => $errors,
        ];
    }

    /**
     * Chạy search-replace an toàn bằng WP-CLI nếu có, hoặc query trực tiếp
     */
    private static function replaceUrls(string $prodUrl, string $localUrl): array
    {
        // Các biến thể URL: http, https, escape JSON "\/"
        $variants = [
            [$prodUrl, $localUrl],
        ];

        // Biến thể http <-> https, có/không www, và dạng escape JSON "https:\/\/…" (dữ liệu block, JSON options)
        $hosts = [$prodUrl];
        $hosts[] = preg_match('~^https://~i', $prodUrl) ? preg_replace('~^https://~i', 'http://', $prodUrl) : preg_replace('~^http://~i', 'https://', $prodUrl);
        foreach ($hosts as $h) {
            $hosts[] = preg_match('~://www\.~i', $h) ? preg_replace('~://www\.~i', '://', $h) : preg_replace('~://~', '://www.', $h, 1);
        }
        foreach (array_unique($hosts) as $from) {
            if ($from !== $prodUrl) {
                $variants[] = [$from, $localUrl];
            }
        }
        foreach ($variants as [$from, $to]) {
            $variants[] = [str_replace('/', '\\/', $from), str_replace('/', '\\/', $to)];
        }

        $outputLog = [];

        foreach ($variants as [$from, $to]) {
            if (class_exists('\WP_CLI') && method_exists('\WP_CLI', 'runcommand')) {
                try {
                    $cmd = sprintf(
                        'search-replace %s %s --all-tables-with-prefix --precise --skip-columns=guid --quiet',
                        escapeshellarg($from),
                        escapeshellarg($to)
                    );
                    \WP_CLI::runcommand($cmd);
                } catch (\Throwable $e) {
                    $outputLog[] = $e->getMessage();
                }
            } else {
                // Fallback cập nhật home và siteurl
                update_option('home', $localUrl);
                update_option('siteurl', $localUrl);
            }
        }

        return ['output' => implode('; ', array_filter($outputLog))];
    }

    /**
     * Vô hiệu hóa toàn bộ key tracker, catalog, remote hub
     */
    public static function neutralizeHubAndTracker(): void
    {
        $optionsToDelete = [
            '_laca_project_id',
            '_laca_tracker_endpoint',
            '_laca_tracker_secret_key',
            '_laca_key_tracker',
            '_laca_key_catalog',
            '_laca_key_remote',
            'laca_project_id',
            'laca_tracker_endpoint',
            'laca_tracker_secret_key',
            'laca_key_tracker',
            'laca_key_catalog',
            'laca_key_remote',
            'laca_block_sync_key',
            'laca_block_sync_endpoint',
            'laca_theme_updater_url',
        ];

        foreach ($optionsToDelete as $opt) {
            delete_option($opt);
        }

        // Tẩy các options có tiền tố _laca_key_
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '\_laca\_key\_%' 
                OR option_name LIKE 'laca\_key\_%'"
        );
    }

    /**
     * Đặt allow_non_production = false trong laca_discord_hub
     */
    public static function neutralizeDiscordHub(): void
    {
        $hubSettings = get_option('laca_discord_hub', []);
        if (is_array($hubSettings)) {
            $hubSettings['allow_non_production'] = false;
            update_option('laca_discord_hub', $hubSettings);
        }
    }

    /**
     * Ngắt kết nối SMTP nếu không phải local mailpit
     */
    public static function neutralizeSmtp(): void
    {
        // Option thật của theme là "_use_smtp" (ThemeSettings). DB production bật SMTP thật → mail từ local đi thẳng tới
        // mail server thật (không qua Mailpit) → khách thật nhận email. Luôn tắt sau khi kéo DB.
        if (get_option('_use_smtp') === 'yes') {
            update_option('_use_smtp', 'no');
        }
    }

    /**
     * Tắt các plugin chỉ dùng cho production
     */
    public static function deactivateProductionPlugins(): array
    {
        $cfg = SyncConfig::getLocal();
        $targetPlugins = $cfg['deactivate_plugins'] ?? [
            'litespeed-cache',
            'wp-rocket',
            'w3-total-cache',
            'wordfence',
            'updraftplus',
            'all-in-one-wp-migration',
        ];

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $activePlugins = (array) get_option('active_plugins', []);
        $toDeactivate = [];

        foreach ($activePlugins as $pluginPath) {
            $slug = dirname($pluginPath);
            if ($slug === '.') {
                $slug = basename($pluginPath, '.php');
            }

            foreach ($targetPlugins as $target) {
                if ($slug === $target || str_contains($pluginPath, $target)) {
                    $toDeactivate[] = $pluginPath;
                    break;
                }
            }
        }

        if (!empty($toDeactivate) && function_exists('deactivate_plugins')) {
            deactivate_plugins($toDeactivate);
        }

        return $toDeactivate;
    }

    /**
     * Reset runtime caches, transient, rewrite rules
     */
    public static function resetRuntimeCaches(): void
    {
        global $wpdb;

        // Xóa transient
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '\_transient\_%' 
                OR option_name LIKE '\_site\_transient\_%'"
        );

        // Hủy cron cũ
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name = 'cron'");

        // Flush rewrite
        flush_rewrite_rules(false);

        // Làm mới ThemeOptionsCache nếu class tồn tại
        if (class_exists('\App\Helpers\ThemeOptionsCache')) {
            \App\Helpers\ThemeOptionsCache::invalidate();
            \App\Helpers\ThemeOptionsCache::buildSnapshot();
        }

        // Xóa cache kiểm tra sức khỏe dung lượng và audit
        delete_option('laca_storage_health');
        delete_option('laca_security_audit_result');
    }

    /**
     * Ẩn danh dữ liệu người dùng và lịch sử/form
     */
    public static function anonymizeData(): array
    {
        global $wpdb;

        // 1. Ẩn danh user không phải admin
        $admins = get_users(['role' => 'administrator', 'fields' => 'ID']);
        $adminIds = array_map('intval', (array) $admins);

        $allUsers = $wpdb->get_col("SELECT ID FROM {$wpdb->users}");
        $anonCount = 0;

        foreach ($allUsers as $userId) {
            $uid = (int) $userId;
            if (in_array($uid, $adminIds, true)) {
                continue;
            }

            $fakeEmail = "user{$uid}@example.invalid";
            $wpdb->update(
                $wpdb->users,
                [
                    'user_email' => $fakeEmail,
                    'user_nicename' => "user{$uid}",
                    'display_name' => "User {$uid}",
                ],
                ['ID' => $uid]
            );

            // Xóa thông tin cá nhân trong usermeta (SĐT, địa chỉ, billing, shipping)
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->usermeta} 
                     WHERE user_id = %d 
                       AND (meta_key LIKE 'billing\_%' 
                         OR meta_key LIKE 'shipping\_%' 
                         OR meta_key IN ('phone', 'phone_number', 'address', 'first_name', 'last_name'))",
                    $uid
                )
            );

            $anonCount++;
        }

        // 2. Ẩn danh các bản ghi liên hệ laca_contact_submissions
        $tableSubmissions = $wpdb->prefix . 'laca_contact_submissions';
        $contactCount = 0;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableSubmissions)) === $tableSubmissions) {
            $contactCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableSubmissions}");
            if ($contactCount > 0) {
                $wpdb->query(
                    "UPDATE {$tableSubmissions} 
                     SET email = CONCAT('anonymous_', id, '@example.invalid'),
                         name = 'Anonymous User',
                         phone = '0000000000',
                         message = '[Đã ẩn danh dữ liệu cá nhân theo quy định bảo mật]' 
                     WHERE 1=1"
                );
            }
        }

        // 3. Ẩn danh email log laca_email_log
        $tableEmailLog = $wpdb->prefix . 'laca_email_log';
        $emailLogCount = 0;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableEmailLog)) === $tableEmailLog) {
            $emailLogCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableEmailLog}");
            if ($emailLogCount > 0) {
                $wpdb->query(
                    "UPDATE {$tableEmailLog} 
                     SET recipient = CONCAT('anonymous_', id, '@example.invalid'),
                         subject = '[Ẩn danh]',
                         message = '[Nội dung email đã ẩn danh]' 
                     WHERE 1=1"
                );
            }
        }

        return [
            'users'    => $anonCount,
            'contacts' => $contactCount,
            'emails'   => $emailLogCount,
        ];
    }
}

