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

    public function register(): void
    {
        add_filter('authenticate', [$this, 'blockLocked'], 99, 1);
        add_action('wp_login_failed', [$this, 'onFailed'], 5, 1);
        add_action('wp_login', [$this, 'onSuccess'], 10, 1);
    }

    public static function config(): array
    {
        return (array) apply_filters('laca_login_guard_settings', [
            'max_fails' => 5,
            'window' => 15 * MINUTE_IN_SECONDS,
            'lockout' => 30 * MINUTE_IN_SECONDS,
            'long_lockout' => DAY_IN_SECONDS,
            'strikes_for_long' => 3,
        ]);
    }

    /**
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function blockLocked($user)
    {
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
        $strikeKey = self::PREFIX . 's_' . md5($ip);
        $strikes = (int) get_transient($strikeKey) + 1;
        set_transient($strikeKey, $strikes, DAY_IN_SECONDS);

        $duration = $strikes >= (int) $cfg['strikes_for_long'] ? (int) $cfg['long_lockout'] : (int) $cfg['lockout'];
        set_transient(self::PREFIX . 'l_' . md5($ip), $now + $duration, $duration);

        do_action('laca_login_guard_lockout', $ip, $duration, $strikes, (string) $username);
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

    private static function isWhitelisted(string $ip): bool
    {
        return in_array($ip, (array) apply_filters('laca_login_guard_whitelist', []), true);
    }
}
