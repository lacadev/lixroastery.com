<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Honeypot đăng nhập độc lập — bật mặc định, hoạt động dù reCAPTCHA tắt.
 *
 * - Field ẩn `laca_login_hp` (tên KHÁC `laca_hp_email` của reCAPTCHA để không trùng).
 * - Không dùng bẫy thời gian: trình quản lý mật khẩu tự submit < 2s sẽ bị chặn nhầm,
 *   còn bot chỉ cần bỏ field timestamp là qua.
 * - Chạy ở `authenticate` priority 1, trước mọi hook xác thực khác.
 * - Chỉ bắt request là form wp-login.php (có field `log`).
 *
 * @since 2026-11 (Plan Security Parity)
 */
class LoginHoneypot
{
    /** Option bật/tắt honeypot đăng nhập */
    private const OPTION_KEY = 'laca_login_honeypot';

    /** Tên field honeypot — khác laca_hp_email của reCAPTCHA */
    private const HP_FIELD = 'laca_login_hp';

    public function register(): void
    {
        // Chỉ hoạt động khi option bật (mặc định bật)
        if (get_option(self::OPTION_KEY, 'yes') !== 'yes') {
            return;
        }

        // Priority 1 — chạy trước authenticate mặc định (priority 20) và 2FA (priority 5/30)
        add_filter('authenticate', [$this, 'checkHoneypot'], 1, 3);

        // In field ẩn trên form login
        add_action('login_form', [$this, 'renderHiddenFields']);
    }

    /**
     * Kiểm tra honeypot.
     *
     * @param \WP_User|\WP_Error|null $user
     * @param string $username
     * @param string $password
     * @return \WP_User|\WP_Error|null
     */
    public function checkHoneypot($user, $username, $password)
    {
        // Chỉ kiểm tra form wp-login.php (có field `log`)
        if (!isset($_POST['log'])) {
            return $user;
        }

        // 1. Honeypot field — nếu có giá trị → bot
        $hp = isset($_POST[self::HP_FIELD])
            ? sanitize_text_field(wp_unslash($_POST[self::HP_FIELD]))
            : '';

        // Không tự gọi wp_login_failed: wp_authenticate() đã tự gọi khi trả về WP_Error (tránh đếm 2 lần)
        if ($hp !== '') {
            return new \WP_Error(
                'laca_honeypot_triggered',
                '<strong>' . esc_html__('Lỗi:', 'laca') . '</strong> ' .
                esc_html__('Yêu cầu đăng nhập không hợp lệ.', 'laca')
            );
        }

        return $user;
    }

    /**
     * In field honeypot ẩn trên form login.
     */
    public function renderHiddenFields(): void
    {
        // Field honeypot — ẩn bằng CSS, bot tự điền
        echo '<input type="text" name="' . esc_attr(self::HP_FIELD) . '" '
            . 'value="" autocomplete="off" tabindex="-1" aria-hidden="true" '
            . 'style="position:absolute;left:-9999px;opacity:0;pointer-events:none;height:0;width:0;overflow:hidden;">';
    }
}

