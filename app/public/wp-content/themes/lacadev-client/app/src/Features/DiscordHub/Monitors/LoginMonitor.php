<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Settings;
use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\Notifier;

/**
 * Phát hiện brute-force theo IP (cửa sổ cố định): wp-login.php, XML-RPC (kể cả
 * system.multicall — mỗi lần thử bắn 1 wp_login_failed) và REST Application Password
 * (core KHÔNG bắn wp_login_failed cho trường hợp này → bắt riêng).
 *
 * Chỉ cảnh báo khi vượt ngưỡng và tại các mốc leo thang (50/200/1000) → 1 đợt tấn công
 * không thành hàng trăm tin nhắn.
 */
final class LoginMonitor
{
    const KEY_PREFIX   = 'laca_dh_bf_';
    const ESCALATION   = [50, 200, 1000, 5000];
    const MAX_USERNAMES = 10;

    public function register(): void

    {
        add_action('wp_login_failed', [$this, 'onLoginFailed'], 10, 1);
        add_action('application_password_failed_authentication', [$this, 'onAppPasswordFailed'], 10, 0);
        add_action('wp_login', [$this, 'onLogin'], 10, 2);
    }

    public function onAppPasswordFailed(): void

    {
        $user = isset($_SERVER['PHP_AUTH_USER']) ? sanitize_user(wp_unslash($_SERVER['PHP_AUTH_USER'])) : '';
        $this->onLoginFailed($user);
    }

    /**
     * @param string $username
     */
    public function onLoginFailed($username): void
    {
        $ip        = Context::clientIp();
        $window    = Settings::threshold('bruteforce_window', 5 * MINUTE_IN_SECONDS);
        $threshold = Settings::threshold('bruteforce_count', 5);
        $now       = time();
        $key       = self::KEY_PREFIX . md5($ip);

        $state = get_transient($key);
        if (!is_array($state) || $now - (int) $state['start'] >= $window) {
            $state = ['start' => $now, 'count' => 0, 'users' => []];
        }
        $state['count']++;

        $username = Embed::truncate(sanitize_user((string) $username), 60);
        if ('' !== $username && !in_array($username, $state['users'], true) && count($state['users']) < self::MAX_USERNAMES) {
            $state['users'][] = $username;
        }
        // TTL = phần còn lại của cửa sổ (cửa sổ cố định, không trượt theo mỗi lần sai).
        set_transient($key, $state, max(1, $window - ($now - (int) $state['start'])));

        $count = (int) $state['count'];
        if ($count !== $threshold + 1 && !in_array($count, self::ESCALATION, true)) {
            return;
        }

        $valid = array_values(array_filter($state['users'], 'username_exists'));
        $embed = Embed::make(
            sprintf('🚨 Brute-force: %d lần đăng nhập sai từ 1 IP', $count),
            Embed::COLOR_DANGER
        )
            ->codeField('IP', $ip)
            ->field('Quốc gia', Context::country() ?? 'N/A (Bot tra GeoIP)')
            ->field('Kênh', Context::origin())
            ->field('Thời gian', sprintf('%d lần trong %d giây', $count, max(1, $now - (int) $state['start'])))
            ->field('Username bị dò', Embed::code(implode(', ', $state['users'])), false, true)
            ->codeField('User-Agent', Context::userAgent(), false);

        if ($valid) {
            $embed->field('⚠️ Username TỒN TẠI đang bị dò', Embed::code(implode(', ', $valid)), false, true);
        }

        Notifier::send(Settings::CHANNEL_SECURITY, 'login.bruteforce', $embed, 'bf:' . md5($ip) . ':' . $count);
    }

    /**
     * Đăng nhập thành công vào tài khoản quản trị từ IP vừa brute-force → khả năng bị chiếm tài khoản.
     *
     * @param string   $login
     * @param \WP_User $user
     */
    public function onLogin($login, $user): void
    {
        if (!$user instanceof \WP_User || !user_can($user, 'manage_options')) {
            return;
        }
        $ip    = Context::clientIp();
        $state = get_transient(self::KEY_PREFIX . md5($ip));
        if (!is_array($state) || (int) $state['count'] <= Settings::threshold('bruteforce_count', 5)) {
            return;
        }

        $embed = Embed::make('🚨 Quản trị viên đăng nhập THÀNH CÔNG từ IP đang brute-force', Embed::COLOR_DANGER)
            ->description('Có thể tài khoản đã bị dò ra mật khẩu. Kiểm tra ngay, đổi mật khẩu và huỷ phiên đăng nhập nếu không phải chính chủ.', true)
            ->codeField('Username', $user->user_login)
            ->codeField('IP', $ip)
            ->field('Số lần sai trước đó', (string) (int) $state['count'])
            ->field('Quản trị', Embed::link('Xem user', admin_url('user-edit.php?user_id=' . (int) $user->ID)), true, true);

        Notifier::send(Settings::CHANNEL_SECURITY, 'login.suspicious_success', $embed);
    }
}
