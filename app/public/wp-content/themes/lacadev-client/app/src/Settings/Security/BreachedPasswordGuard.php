<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kiểm tra mật khẩu rò rỉ qua Have I Been Pwned Pwned Passwords API v3 (k-Anonymity):
 * - Gửi 5 ký tự đầu SHA-1 (viết HOA) tới https://api.pwnedpasswords.com/range/{prefix}.
 * - Header Add-Padding: true.
 * - User-Agent: WordPress; laca-theme (không gửi URL/domain/email).
 * - Timeout <= 3s, fail-open (nếu lỗi kết nối thì bỏ qua, không chặn).
 * - Không lưu mật khẩu/hash vào DB.
 *
 * @since 2026-11
 */
final class BreachedPasswordGuard
{
    private const API_URL = 'https://api.pwnedpasswords.com/range/';

    public function register(): void
    {
        if (defined('LACA_SECURITY_BYPASS') && LACA_SECURITY_BYPASS) {
            return;
        }

        // Khi đổi mật khẩu ở Profile hoặc Reset mật khẩu
        add_action('user_profile_update_errors', [$this, 'checkPasswordOnProfileUpdate'], 10, 3);
        add_action('validate_password_reset', [$this, 'checkPasswordOnReset'], 10, 2);

        // Khi đăng nhập: kiểm tra nền
        add_filter('check_password', [$this, 'checkPasswordOnLogin'], 10, 4);

        // Chuyển hướng và cảnh báo nếu có user_meta laca_password_breached
        add_action('admin_init', [$this, 'enforceChangeBreachedPassword']);
        add_action('admin_notices', [$this, 'renderBreachedPasswordNotice']);

        // Xoá meta khi mật khẩu được đổi thành công
        add_action('profile_update', [$this, 'clearBreachedMetaOnSuccess'], 10, 2);
        add_action('after_password_reset', [$this, 'clearBreachedMetaAfterReset'], 10, 1);
    }

    /**
     * Kiểm tra mật khẩu có nằm trong cơ sở dữ liệu HIBP hay không.
     *
     * @return bool|null true = bị lộ, false = an toàn, null = lỗi API / fail-open
     */
    public static function isPasswordBreached(string $password): ?bool
    {
        if ($password === '') {
            return false;
        }

        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        // Cho phép test mock/offline qua filter
        $pre = apply_filters('laca_pre_check_breached_password', null, $password, $sha1);
        if ($pre !== null) {
            return (bool) $pre;
        }

        $url = self::API_URL . $prefix;
        $response = wp_remote_get($url, [
            'timeout'     => 3,
            'redirection' => 1,
            'user-agent'  => 'WordPress; laca-theme',
            'headers'     => [
                'Add-Padding' => 'true',
            ],
            'sslverify'   => true,
        ]);

        if (is_wp_error($response)) {
            return null; // Fail-open
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null; // Fail-open
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return false;
        }

        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $hashSuffix = strtoupper(trim($parts[0]));
                $count      = (int) trim($parts[1]);
                if ($hashSuffix === $suffix && $count > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Xác định người dùng có thuộc diện kiểm tra mật khẩu hay không.
     */
    public static function isUserTargeted(int $userId): bool
    {
        $cfg = LoginPolicy::config();
        $mode = $cfg['breached_check'] ?? 'admins';

        if ($mode === 'off') {
            return false;
        }

        if ($mode === 'all') {
            return true;
        }

        if ($mode === 'admins') {
            return user_can($userId, 'manage_options');
        }

        return false;
    }

    /**
     * Chặn lưu mật khẩu bị lộ trong Profile.
     */
    public function checkPasswordOnProfileUpdate(\WP_Error $errors, bool $update, $user): void
    {
        if (empty($_POST['pass1'])) {
            return;
        }

        $targetUserId = is_object($user) && isset($user->ID) ? (int) $user->ID : 0;
        if ($targetUserId > 0 && !self::isUserTargeted($targetUserId)) {
            return;
        }

        $pass = (string) $_POST['pass1'];
        if (self::isPasswordBreached($pass) === true) {
            $errors->add(
                'laca_breached_password',
                __('Mật khẩu này đã xuất hiện trong các vụ rò rỉ dữ liệu, hãy chọn mật khẩu khác.', 'laca')
            );
        }
    }

    /**
     * Chặn đặt mật khẩu bị lộ khi reset mật khẩu.
     */
    public function checkPasswordOnReset(\WP_Error $errors, $user): void
    {
        if (empty($_POST['pass1'])) {
            return;
        }

        $targetUserId = is_object($user) && isset($user->ID) ? (int) $user->ID : 0;
        if ($targetUserId > 0 && !self::isUserTargeted($targetUserId)) {
            return;
        }

        $pass = (string) $_POST['pass1'];
        if (self::isPasswordBreached($pass) === true) {
            $errors->add(
                'laca_breached_password',
                __('Mật khẩu này đã xuất hiện trong các vụ rò rỉ dữ liệu, hãy chọn mật khẩu khác.', 'laca')
            );
        }
    }

    /**
     * Khi đăng nhập thành công: kiểm tra mật khẩu nếu chưa kiểm tra trong 24h.
     */
    public function checkPasswordOnLogin(bool $check, string $password, string $hash, $userId): bool
    {
        if (!$check || empty($userId) || !self::isUserTargeted((int) $userId)) {
            return $check;
        }

        $lastChecked = (int) get_user_meta((int) $userId, 'laca_hibp_checked_at', true);
        if ($lastChecked > 0 && (time() - $lastChecked) < DAY_IN_SECONDS) {
            return $check;
        }

        update_user_meta((int) $userId, 'laca_hibp_checked_at', time());

        if (self::isPasswordBreached($password) === true) {
            update_user_meta((int) $userId, 'laca_password_breached', time());
        } else {
            delete_user_meta((int) $userId, 'laca_password_breached');
        }

        return $check;
    }

    /**
     * Bắt buộc đổi mật khẩu khi có flag laca_password_breached.
     */
    public function enforceChangeBreachedPassword(): void
    {
        if (defined('LACA_SECURITY_BYPASS') && LACA_SECURITY_BYPASS) {
            return;
        }

        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return;
        }

        $isBreached = (int) get_user_meta($user->ID, 'laca_password_breached', true);
        if ($isBreached <= 0) {
            return;
        }

        global $pagenow;
        if ($pagenow === 'profile.php' || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
            return;
        }

        wp_safe_redirect(admin_url('profile.php#password'));
        exit;
    }

    public function renderBreachedPasswordNotice(): void
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return;
        }

        $isBreached = (int) get_user_meta($user->ID, 'laca_password_breached', true);
        if ($isBreached <= 0) {
            return;
        }

        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html__('CẢNH BÁO MẬT KHẨU RÒ RỈ:', 'laca'); ?></strong>
                <?php echo esc_html__('Mật khẩu của bạn đã xuất hiện trong danh sách các vụ lộ lọt dữ liệu trên Internet. Vui lòng đổi mật khẩu mới ngay lập tức để bảo vệ website.', 'laca'); ?>
                <a href="<?php echo esc_url(admin_url('profile.php#password')); ?>" style="margin-left:8px;font-weight:600;">
                    <?php echo esc_html__('Đổi mật khẩu ngay →', 'laca'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Xoá flag rò rỉ khi người dùng cập nhật mật khẩu mới.
     */
    public function clearBreachedMetaOnSuccess(int $userId, $oldUserData = null): void
    {
        if (!empty($_POST['pass1'])) {
            delete_user_meta($userId, 'laca_password_breached');
            update_user_meta($userId, 'laca_hibp_checked_at', time());
        }
    }

    public function clearBreachedMetaAfterReset($user): void
    {
        if (is_object($user) && isset($user->ID)) {
            delete_user_meta($user->ID, 'laca_password_breached');
            update_user_meta($user->ID, 'laca_hibp_checked_at', time());
        }
    }
}

