<?php

namespace App\Settings\Security;

use App\Features\DiscordHub\Settings;
use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\Notifier;

final class AdminLoginAlert
{
    const META_KEY = 'laca_known_login_ips';
    const MAX_IPS  = 10;

    public function register(): void
    {
        add_action('wp_login', [$this, 'onLogin'], 20, 2);
    }

    /**
     * @param string   $user_login
     * @param \WP_User $user
     */
    public function onLogin($user_login, $user): void
    {
        if (!$user instanceof \WP_User || !user_can($user, 'manage_options')) {
            return;
        }

        $config = LoginPolicy::config();
        if (empty($config['new_ip_alert'])) {
            return;
        }

        $ip = LoginGuard::clientIp();
        if (empty($ip)) {
            return;
        }

        $rawKnown = get_user_meta($user->ID, self::META_KEY, true);
        $knownIps = is_array($rawKnown) ? $rawKnown : [];

        // Lần đầu khởi tạo (chưa có meta): chỉ ghi nhận IP hiện tại, không gửi alert để tránh spam
        if (empty($knownIps)) {
            update_user_meta($user->ID, self::META_KEY, [$ip]);
            return;
        }

        // Nếu IP đã biết
        if (in_array($ip, $knownIps, true)) {
            $knownIps = array_diff($knownIps, [$ip]);
            array_unshift($knownIps, $ip);
            update_user_meta($user->ID, self::META_KEY, array_slice($knownIps, 0, self::MAX_IPS));
            return;
        }

        // IP mới chưa từng thấy
        array_unshift($knownIps, $ip);
        update_user_meta($user->ID, self::META_KEY, array_slice($knownIps, 0, self::MAX_IPS));

        $this->sendAlert($user, $ip);
    }

    private function sendAlert(\WP_User $user, string $ip): void
    {
        if (!class_exists(Notifier::class) || !class_exists(Embed::class)) {
            return;
        }

        $country = class_exists(Context::class) ? (Context::country() ?? 'N/A') : 'N/A';
        $ua      = class_exists(Context::class) ? Context::userAgent() : (isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'N/A');

        $embed = Embed::make(
            __('⚠️ Quản trị viên đăng nhập từ IP mới', 'laca'),
            Embed::COLOR_WARNING
        )
            ->description(
                __('Tài khoản quản trị vừa đăng nhập thành công từ một địa chỉ IP chưa từng xuất hiện trước đây.', 'laca'),
                true
            )
            ->codeField('Username', $user->user_login)
            ->codeField('IP', $ip)
            ->field(__('Quốc gia', 'laca'), $country)
            ->field(__('Thời gian', 'laca'), wp_date('Y-m-d H:i:s'))
            ->codeField('User-Agent', $ua, false)
            ->field(
                __('Quản trị', 'laca'),
                Embed::link(__('Xem tài khoản', 'laca'), admin_url('user-edit.php?user_id=' . (int) $user->ID)),
                true,
                true
            );

        Notifier::send(Settings::CHANNEL_SECURITY, 'login.new_ip', $embed);
    }
}

