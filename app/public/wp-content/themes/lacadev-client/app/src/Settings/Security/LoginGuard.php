<?php

namespace App\Settings\Security;

use App\Helpers\ClientIp;

/**
 * Giới hạn đăng nhập sai theo IP (chống brute-force) — áp dụng cho mọi đường đăng nhập
 * đi qua wp_authenticate(): trang đăng nhập (kể cả URL tuỳ chỉnh), WooCommerce My Account, XML-RPC.
 *
 *  - Sai 5 lần trong 15 phút → khoá IP 30 phút.
 *  - Bị khoá 3 lần trong 24 giờ → khoá 24 giờ.
 *  - Trong lúc bị khoá, ĐÚNG mật khẩu cũng bị từ chối (nếu không, kẻ tấn công vẫn dò tiếp được).
 *
 * Ngưỡng chỉnh qua filter `laca_login_guard_settings`; IP được miễn qua `laca_login_guard_whitelist`.
 * Bắn action `laca_login_guard_lockout` khi khoá (Discord Hub dùng để báo).
 */
final class LoginGuard
{
    private const PREFIX = 'laca_lg_';

    /** Option key cho cấu hình (admin UI) */
    const SETTINGS_OPTION = 'laca_login_guard_settings';

    /** Option key cho index IP bị khoá (hiển thị trong admin) */
    const LOCKED_INDEX_OPTION = 'laca_login_guard_locked_ips';

    public function register(): void
    {
        add_filter('authenticate', [$this, 'blockLocked'], 99, 1);
        add_action('wp_login_failed', [$this, 'onFailed'], 5, 1);
        add_action('wp_login', [$this, 'onSuccess'], 10, 1);
        add_filter('laca_login_guard_whitelist', [$this, 'filterLocalWhitelist']);

        // AJAX endpoints cho admin UI
        add_action('wp_ajax_laca_login_guard_unlock', [$this, 'ajaxUnlock']);
    }

    /**
     * Tự động whitelist localhost (127.0.0.1 / ::1) khi đang ở môi trường
     * local hoặc development — tránh tình trạng lập trình viên/tester tự
     * khoá chính mình trong lúc phát triển.
     */
    public function filterLocalWhitelist(array $ips): array
    {
        if (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), ['local', 'development'], true)) {
            $ips[] = '127.0.0.1';
            $ips[] = '::1';
        }
        return $ips;
    }

    public static function config(): array
    {
        $defaults = [
            'max_fails'        => 5,
            'window'           => 15 * MINUTE_IN_SECONDS,
            'lockout'          => 30 * MINUTE_IN_SECONDS,
            'long_lockout'     => DAY_IN_SECONDS,
            'strikes_for_long' => 3,
            'whitelist_ips'    => [],
        ];

        // Đọc cấu hình từ option (admin UI)
        $saved = get_option(self::SETTINGS_OPTION, []);
        if (is_array($saved)) {
            $defaults = wp_parse_args($saved, $defaults);
        }

        // Giữ tương thích filter cũ
        return (array) apply_filters('laca_login_guard_settings', $defaults);
    }

    /**
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function blockLocked($user)
    {
        if (defined('LACA_SECURITY_BYPASS') && LACA_SECURITY_BYPASS) {
            return $user;
        }

        $remaining = self::lockRemaining(ClientIp::get());
        if ($remaining <= 0) {
            return $user;
        }
        return new \WP_Error(
            'laca_too_many_attempts',
            sprintf(
                /* translators: %d: số phút */
                __('Đăng nhập sai quá nhiều lần. Vui lòng thử lại sau %d phút.', 'laca'),
                (int) ceil($remaining / MINUTE_IN_SECONDS)
            )
        );
    }

    /**
     * @param string $username
     */
    public function onFailed($username): void
    {
        if (defined('LACA_SECURITY_BYPASS') && LACA_SECURITY_BYPASS) {
            return;
        }

        $ip = ClientIp::get();
        if ('unknown' === $ip || self::isWhitelisted($ip) || self::lockRemaining($ip) > 0) {
            return;
        }

        $cfg = self::config();
        $key = self::PREFIX . md5($ip);
        $now = time();
        $state = get_transient($key);
        if (!is_array($state) || $now - (int) $state['start'] >= (int) $cfg['window']) {
            $state = ['start' => $now, 'count' => 0];
        }
        $state['count']++;

        if ($state['count'] < (int) $cfg['max_fails']) {
            set_transient($key, $state, max(1, (int) $cfg['window'] - ($now - (int) $state['start'])));
            return;
        }

        delete_transient($key);
        self::executeLockout($ip, (string) $username, 'max_fails');
    }

    /**
     * Khoá IP ngay lập tức (dùng chung cho onFailed và LoginPolicy).
     */
    public static function lockNow(string $ip, string $username, string $reason): void
    {
        if (defined('LACA_SECURITY_BYPASS') && LACA_SECURITY_BYPASS) {
            return;
        }

        if ('unknown' === $ip || self::isWhitelisted($ip) || self::lockRemaining($ip) > 0) {
            return;
        }

        self::executeLockout($ip, $username, $reason);
    }

    /**
     * Thực hiện các bước khoá IP và lưu transient / index.
     */
    private static function executeLockout(string $ip, string $username, string $reason): void
    {
        $cfg = self::config();
        $now = time();

        $strikeKey = self::PREFIX . 's_' . md5($ip);
        $strikes = (int) get_transient($strikeKey) + 1;
        set_transient($strikeKey, $strikes, DAY_IN_SECONDS);

        $duration = $strikes >= (int) $cfg['strikes_for_long'] ? (int) $cfg['long_lockout'] : (int) $cfg['lockout'];
        set_transient(self::PREFIX . 'l_' . md5($ip), $now + $duration, $duration);

        // Thêm vào index để hiển thị trong admin UI
        self::addToLockedIndex($ip, $now + $duration);

        do_action('laca_login_guard_lockout', $ip, $duration, $strikes, (string) $username, $reason);
    }

    /**
     * @param string $login
     */
    public function onSuccess($login): void
    {
        delete_transient(self::PREFIX . md5(ClientIp::get()));
    }

    public static function lockRemaining(string $ip): int
    {
        $until = (int) get_transient(self::PREFIX . 'l_' . md5($ip));
        return max(0, $until - time());
    }

    public static function unlock(string $ip): void
    {
        delete_transient(self::PREFIX . 'l_' . md5($ip));
        delete_transient(self::PREFIX . 's_' . md5($ip));
        delete_transient(self::PREFIX . md5($ip));
    }

    public static function isWhitelistedIp(string $ip): bool
    {
        return self::isWhitelisted($ip);
    }

    public static function clientIp(): string
    {
        $ip = class_exists(ClientIp::class) ? ClientIp::get() : '';
        if (empty($ip) || $ip === 'unknown') {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        }
        return $ip;
    }

    private static function isWhitelisted(string $ip): bool
    {
        $cfg = self::config();
        $configIps = isset($cfg['whitelist_ips']) && is_array($cfg['whitelist_ips']) ? $cfg['whitelist_ips'] : [];
        $filterIps = (array) apply_filters('laca_login_guard_whitelist', []);
        $all = array_merge($configIps, $filterIps);
        return in_array($ip, $all, true);
    }

    // ═══════════════════════════════════════════════════════════
    //  LOCKED IP INDEX (cho admin UI)
    // ═══════════════════════════════════════════════════════════

    /**
     * Thêm IP vào index khi bị khoá.
     */
    private static function addToLockedIndex(string $ip, int $until): void
    {
        $index = get_option(self::LOCKED_INDEX_OPTION, []);
        if (!is_array($index)) {
            $index = [];
        }
        $index[$ip] = $until;
        update_option(self::LOCKED_INDEX_OPTION, $index, false);
    }

    /**
     * Xoá IP khỏi index khi mở khoá.
     */
    private static function removeFromLockedIndex(string $ip): void
    {
        $index = get_option(self::LOCKED_INDEX_OPTION, []);
        if (!is_array($index)) {
            return;
        }
        unset($index[$ip]);
        update_option(self::LOCKED_INDEX_OPTION, $index, false);
    }

    /**
     * Lấy danh sách IP đang bị khoá (đã dọn mục hết hạn).
     *
     * @return array<string, int> IP => unlock timestamp
     */
    public static function getLockedIps(): array
    {
        $index = get_option(self::LOCKED_INDEX_OPTION, []);
        if (!is_array($index)) {
            return [];
        }

        $now     = time();
        $changed = false;
        foreach ($index as $ip => $until) {
            if ($until <= $now) {
                unset($index[$ip]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::LOCKED_INDEX_OPTION, $index, false);
        }

        return $index;
    }

    /**
     * AJAX: Mở khoá IP từ admin UI.
     */
    public function ajaxUnlock(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
        }
        check_ajax_referer('laca_login_guard_nonce', '_nonce');

        $ip = sanitize_text_field(wp_unslash($_POST['ip'] ?? ''));
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            wp_send_json_error(esc_html__('Địa chỉ IP không hợp lệ.', 'laca'));
        }

        self::unlock($ip);
        self::removeFromLockedIndex($ip);

        wp_send_json_success([
            /* translators: %s: IP address */
            'message' => sprintf(esc_html__('Đã mở khoá IP %s.', 'laca'), $ip),
        ]);
    }
}
