<?php

namespace App\Settings\Security;

use App\Helpers\ClientIp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quản lý chính sách đăng nhập nâng cao (Wordfence-style):
 * - Khoá IP tức thì khi đăng nhập username không tồn tại hoặc bị cấm.
 * - Cấm đăng ký / tạo tài khoản trùng tên bị cấm.
 * - Bắt buộc 2FA theo vai trò với thời gian ân hạn.
 * - Giới hạn tần suất yêu cầu Quên mật khẩu theo IP.
 * - Tắt Application Passwords.
 * - Lối thoát khẩn cấp qua hằng số LACA_SECURITY_BYPASS.
 *
 * @since 2026-11
 */
final class LoginPolicy
{
    const OPTION_KEY = 'laca_login_policy';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $defaults = [
            'lock_invalid_usernames' => false,
            'banned_usernames'       => ['admin', 'administrator', 'root', 'test', 'user', 'demo'],
            'require_2fa_roles'      => [],
            'require_2fa_grace_days' => 7,
            'lostpw_max_per_hour'    => 5,
            'disable_app_passwords'  => false,
            'breached_check'         => 'admins',
            'new_ip_alert'           => true,
        ];

        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $defaults);
    }

    public function register(): void
    {
        // 5.3 Lối thoát khẩn cấp: hiển thị notice khi LACA_SECURITY_BYPASS bật
        if (defined('LACA_SECURITY_BYPASS') && LACA_SECURITY_BYPASS) {
            add_action('admin_notices', [$this, 'renderBypassNotice']);
            return;
        }

        // 5.2 Khoá IP ngay lập tức (priority 4 trước LoginGuard priority 5)
        add_action('wp_login_failed', [$this, 'onLoginFailedEarly'], 4, 1);

        // 5.2 Cấm tạo user mới với tên bị cấm
        add_filter('validate_username', [$this, 'filterValidateUsername'], 10, 2);
        add_action('user_profile_update_errors', [$this, 'validateNewUserProfileErrors'], 10, 3);
        add_filter('registration_errors', [$this, 'validateRegistrationErrors'], 10, 3);

        // 5.4 Bắt buộc 2FA theo vai trò
        add_action('admin_init', [$this, 'enforce2faRedirect']);
        add_action('admin_notices', [$this, 'render2faGraceNotice']);

        // 5.5 Giới hạn Quên mật khẩu
        add_action('lostpassword_post', [$this, 'limitLostPasswordAttempts'], 10, 1);

        // 5.6 Tắt Application Passwords
        $cfg = self::config();
        if (!empty($cfg['disable_app_passwords'])) {
            add_filter('wp_is_application_passwords_available', '__return_false');
        }
    }

    public function renderBypassNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html__('CẢNH BÁO BẢO MẬT:', 'laca'); ?></strong>
                <?php echo esc_html__('Đang bật chế độ vượt bảo mật LACA_SECURITY_BYPASS — xoá khỏi wp-config.php sau khi xử lý xong.', 'laca'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Hook wp_login_failed sớm (priority 4).
     */
    public function onLoginFailedEarly($username): void
    {
        $rawUsername = is_string($username) ? trim($username) : '';
        if ($rawUsername === '') {
            return;
        }

        $cfg = self::config();
        $ip  = ClientIp::get();

        $cleanName = mb_strtolower($rawUsername, 'UTF-8');
        $banned    = array_map(fn($n) => mb_strtolower(trim($n), 'UTF-8'), (array) ($cfg['banned_usernames'] ?? []));

        $exists = username_exists($rawUsername) || (is_email($rawUsername) && email_exists($rawUsername));

        // Trường hợp 1: username không tồn tại
        if (!$exists) {
            if (in_array($cleanName, $banned, true)) {
                LoginGuard::lockNow($ip, $rawUsername, 'banned_username');
                return;
            }

            if (!empty($cfg['lock_invalid_usernames'])) {
                LoginGuard::lockNow($ip, $rawUsername, 'invalid_username');
                return;
            }
        }
    }

    /**
     * Filter validate_username: không cho phép tạo user có tên trong banned_usernames.
     */
    public function filterValidateUsername(bool $valid, string $username): bool
    {
        if (!$valid) {
            return false;
        }

        $cfg = self::config();
        $cleanName = mb_strtolower(trim($username), 'UTF-8');
        $banned = array_map(fn($n) => mb_strtolower(trim($n), 'UTF-8'), (array) ($cfg['banned_usernames'] ?? []));

        if (in_array($cleanName, $banned, true)) {
            return false;
        }

        return true;
    }

    /**
     * Chặn tạo user mới trong wp-admin/user-new.php
     */
    public function validateNewUserProfileErrors(\WP_Error $errors, bool $update, $user): void
    {
        if ($update) {
            return;
        }

        $username = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';
        if ($username !== '' && !$this->filterValidateUsername(true, $username)) {
            $errors->add('laca_banned_username', __('Tên đăng nhập này không được phép dùng.', 'laca'));
        }
    }

    /**
     * Chặn đăng ký người dùng ở frontend (nếu bật đăng ký)
     */
    public function validateRegistrationErrors(\WP_Error $errors, string $sanitized_user_login, string $user_email): \WP_Error
    {
        if (!$this->filterValidateUsername(true, $sanitized_user_login)) {
            $errors->add('laca_banned_username', __('Tên đăng nhập này không được phép dùng.', 'laca'));
        }
        return $errors;
    }

    /**
     * 5.4 Bắt buộc 2FA theo vai trò
     */
    public function enforce2faRedirect(): void
    {
        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        if (!get_option('laca_2fa_master_enabled', 0)) {
            return;
        }

        $cfg = self::config();
        $requiredRoles = (array) ($cfg['require_2fa_roles'] ?? []);
        if (empty($requiredRoles)) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return;
        }

        $userRoles = (array) $user->roles;
        $matched = array_intersect($userRoles, $requiredRoles);
        if (empty($matched)) {
            return;
        }

        $enabled  = get_user_meta($user->ID, 'laca_2fa_enabled', true);
        $verified = get_user_meta($user->ID, 'laca_2fa_verified', true);
        if ($enabled && $verified) {
            return;
        }

        // Đánh dấu thời điểm bắt đầu áp dụng nếu chưa có
        $since = (int) get_user_meta($user->ID, 'laca_2fa_enforce_since', true);
        if ($since <= 0) {
            $since = time();
            update_user_meta($user->ID, 'laca_2fa_enforce_since', $since);
        }

        $graceDays = max(0, min(30, (int) ($cfg['require_2fa_grace_days'] ?? 7)));
        $deadline  = $since + ($graceDays * DAY_IN_SECONDS);

        if (time() >= $deadline) {
            // Đã hết ân hạn → chuyển hướng về trang thiết lập 2FA
            $targetUrl = admin_url('profile.php#laca-2fa-section');

            // Bỏ qua các URL cho phép
            global $pagenow;
            if ($pagenow === 'profile.php' || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
                return;
            }

            wp_safe_redirect($targetUrl);
            exit;
        }
    }

    public function render2faGraceNotice(): void
    {
        if (!get_option('laca_2fa_master_enabled', 0)) {
            return;
        }

        $cfg = self::config();
        $requiredRoles = (array) ($cfg['require_2fa_roles'] ?? []);
        if (empty($requiredRoles)) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return;
        }

        $userRoles = (array) $user->roles;
        if (empty(array_intersect($userRoles, $requiredRoles))) {
            return;
        }

        $enabled  = get_user_meta($user->ID, 'laca_2fa_enabled', true);
        $verified = get_user_meta($user->ID, 'laca_2fa_verified', true);
        if ($enabled && $verified) {
            return;
        }

        $since = (int) get_user_meta($user->ID, 'laca_2fa_enforce_since', true);
        if ($since <= 0) {
            $since = time();
            update_user_meta($user->ID, 'laca_2fa_enforce_since', $since);
        }

        $graceDays = max(0, min(30, (int) ($cfg['require_2fa_grace_days'] ?? 7)));
        $deadline  = $since + ($graceDays * DAY_IN_SECONDS);

        if (time() < $deadline) {
            $dateStr = wp_date('d/m/Y', $deadline);
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    /* translators: %s: ngày hết hạn */
                    echo sprintf(
                        esc_html__('Tài khoản của bạn thuộc nhóm bắt buộc bật xác thực 2 bước (2FA). Vui lòng kích hoạt trước ngày %s.', 'laca'),
                        '<strong>' . esc_html($dateStr) . '</strong>'
                    );
                    ?>
                    <a href="<?php echo esc_url(admin_url('profile.php#laca-2fa-section')); ?>" style="margin-left:8px;font-weight:600;">
                        <?php echo esc_html__('Thiết lập ngay →', 'laca'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * 5.5 Giới hạn Quên mật khẩu theo IP
     *
     * @param \WP_Error $errors
     */
    public function limitLostPasswordAttempts($errors): void
    {
        $cfg = self::config();
        $maxPerHour = (int) ($cfg['lostpw_max_per_hour'] ?? 5);
        if ($maxPerHour <= 0) {
            return;
        }

        $ip = ClientIp::get();
        if ('unknown' === $ip || (class_exists(LoginGuard::class) && LoginGuard::isWhitelistedIp($ip))) {
            return;
        }

        $transientKey = 'laca_lostpw_' . md5($ip);
        $attempts = (int) get_transient($transientKey);
        $attempts++;

        if ($attempts > $maxPerHour) {
            if ($errors instanceof \WP_Error) {
                $errors->add('laca_lostpw_limit', __('Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau.', 'laca'));
            }
            return;
        }

        set_transient($transientKey, $attempts, HOUR_IN_SECONDS);
    }
}

