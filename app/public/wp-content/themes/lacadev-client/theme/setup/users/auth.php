<?php

use Overtrue\Socialite\SocialiteManager;

add_action('wp_ajax_nopriv_user_login', 'mm_user_login');
add_action('wp_ajax_user_login', 'mm_user_login');

/**
 * Cấu hình driver Google cho Socialite.
 * Giá trị được lấy động từ Carbon Fields (Laca Admin > Login Socials),
 * có fallback về redirect URI chuẩn dùng admin-ajax.php.
 * 
 * @return array
 */
function lacadev_get_social_driver_config()
{
    $default_redirect = admin_url('admin-ajax.php?action=google_admin_callback');
    $get = static function (string $key) {
        if (function_exists('laca_theme_option')) {
            return laca_theme_option($key);
        }
        return function_exists('carbon_get_theme_option') ? carbon_get_theme_option($key) : '';
    };

    $redirect = $get('google_redirect_uri') ?: $default_redirect;

    return [
        'google' => [
            'client_id'     => trim((string) $get('google_client_id')),
            'client_secret' => trim((string) $get('google_client_secret')),
            'redirect'      => $redirect,
        ],
    ];
}

function lacadev_is_google_login_enabled(): bool
{
    $enabled = function_exists('laca_theme_option')
        ? laca_theme_option('enable_login_google')
        : (function_exists('carbon_get_theme_option')
            ? carbon_get_theme_option('enable_login_google')
            : get_option('_enable_login_google', get_option('enable_login_google', false)));

    return in_array($enabled, [true, 1, '1', 'yes', 'on'], true);
}

function mm_user_login()
{
    if (empty($_POST)) {
        wp_send_json_error(__('Yêu cầu không hợp lệ.', 'laca'));
    }

    if (!isset($_POST['_token']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_token'])), 'user_dang_nhap')) {
        wp_send_json_error(__('Phiên làm việc không hợp lệ!', 'laca'), 403);
    }

    $userLogin = isset($_POST['user_login']) ? sanitize_user(wp_unslash($_POST['user_login'])) : '';
    $userPass  = isset($_POST['password'])
        ? (string) wp_unslash($_POST['password'])
        : (isset($_POST['user_pass']) ? (string) wp_unslash($_POST['user_pass']) : '');

    if ($userLogin === '' || $userPass === '') {
        wp_send_json_error(__('Tài khoản hoặc mật khẩu không đúng, vui lòng kiểm tra lại', 'laca'));
    }

    $user = wp_signon([
        'user_login'    => $userLogin,
        'user_password' => $userPass,
        'remember'      => true,
    ], false);

    if (is_wp_error($user)) {
        wp_send_json_error(__('Tài khoản hoặc mật khẩu không đúng, vui lòng kiểm tra lại', 'laca'));
    }

    $redirectUrl = !empty($_POST['redirect_to'])
        ? wp_validate_redirect(sanitize_url(wp_unslash($_POST['redirect_to'])), home_url())
        : home_url();

    wp_send_json_success([
        'redirect' => $redirectUrl,
        'alert'    => [
            /* translators: %s: user email */
            'title'   => sprintf(__('Xin chào, %s', 'laca'), $user->user_email),
            'message' => __('Chúc mừng bạn đã đăng nhập thành công', 'laca'),
        ],
    ]);
}

add_action('wp_ajax_nopriv_user_register', 'mm_user_register');
add_action('wp_ajax_user_register', 'mm_user_register');

function mm_user_register()
{
    if (empty($_POST)) {
        wp_send_json_error(__('Yêu cầu không hợp lệ.', 'laca'));
    }

    if (!get_option('users_can_register')) {
        wp_send_json_error(__('Chức năng đăng ký thành viên hiện đang tắt.', 'laca'), 403);
    }

    if (!isset($_REQUEST['_token']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_token'])), 'user_dang_ky_thanh_vien')) {
        wp_send_json_error(__('Phiên làm việc không hợp lệ!', 'laca'), 403);
    }

    $rawEmail = isset($_POST['email'])
        ? (string) wp_unslash($_POST['email'])
        : (isset($_POST['user_email']) ? (string) wp_unslash($_POST['user_email']) : '');

    if ($rawEmail === '' || !is_email($rawEmail)) {
        wp_send_json_error(__('Vui lòng nhập email hợp lệ', 'laca'));
    }

    $userEmail = sanitize_email($rawEmail);
    $username  = isset($_POST['user_login']) ? sanitize_user(wp_unslash($_POST['user_login'])) : '';
    if ($username === '') {
        wp_send_json_error(__('Vui lòng nhập tên đăng nhập hợp lệ', 'laca'));
    }

    $userPass = isset($_POST['password'])
        ? (string) wp_unslash($_POST['password'])
        : (isset($_POST['user_pass']) ? (string) wp_unslash($_POST['user_pass']) : '');

    if ($userPass === '') {
        wp_send_json_error(__('Vui lòng nhập mật khẩu', 'laca'));
    }

    if (isset($_POST['password_confirmation']) && $userPass !== (string) wp_unslash($_POST['password_confirmation'])) {
        wp_send_json_error(__('Vui lòng kiểm tra lại mật khẩu', 'laca'));
    }

    if (username_exists($username)) {
        wp_send_json_error(__('Tên đăng nhập đã tồn tại!', 'laca'));
    }

    if (email_exists($userEmail)) {
        wp_send_json_error(__('Email đã tồn tại!', 'laca'));
    }

    $firstName = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $lastName  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';

    $userParams = [
        'user_login'   => $username,
        'user_email'   => $userEmail,
        'user_pass'    => $userPass,
        'display_name' => $lastName !== '' ? $lastName : $username,
        'first_name'   => $firstName,
        'last_name'    => $lastName,
    ];

    $userId = wp_insert_user($userParams);
    if (is_wp_error($userId)) {
        wp_send_json_error($userId->get_error_message());
    }

    $user = get_user_by('id', $userId);
    if (!$user) {
        wp_send_json_error(__('Không thể khởi tạo phiên đăng nhập.', 'laca'));
    }

    if (!empty($_POST['birthday'])) {
        update_user_meta($userId, '_user_birthday', sanitize_text_field(wp_unslash($_POST['birthday'])));
    }
    if (!empty($_POST['sex'])) {
        update_user_meta($userId, '_user_gender', sanitize_text_field(wp_unslash($_POST['sex'])));
    }

    wp_set_current_user($userId, $user->user_login);
    wp_set_auth_cookie($userId);
    do_action('wp_login', $user->user_login, $user);

    wp_send_json_success([
        'message'  => __('Đăng ký thành công!', 'laca'),
        'redirect' => home_url(),
    ]);
}

function lacadev_issue_oauth_state(): string
{
    $state         = bin2hex(random_bytes(24));
    $browserSecret = bin2hex(random_bytes(24));
    $cookiePath    = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $cookieDomain  = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';

    if (!headers_sent()) {
        setcookie('laca_oauth_state', $browserSecret, [
            'expires'  => time() + 600,
            'path'     => $cookiePath,
            'domain'   => $cookieDomain,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    set_transient(
        'laca_oauth_st_' . hash('sha256', $state),
        hash_hmac('sha256', $state, $browserSecret),
        600
    );

    return $state;
}

function lacadev_verify_oauth_state(): bool
{
    $state         = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
    $browserSecret = isset($_COOKIE['laca_oauth_state']) ? sanitize_text_field(wp_unslash($_COOKIE['laca_oauth_state'])) : '';
    $cookiePath    = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $cookieDomain  = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';

    if (!headers_sent()) {
        setcookie('laca_oauth_state', '', [
            'expires'  => time() - 3600,
            'path'     => $cookiePath,
            'domain'   => $cookieDomain,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    if ($state === '' || $browserSecret === '' || !preg_match('/^[a-f0-9]{32,64}$/', $state)) {
        return false;
    }

    $transientKey = 'laca_oauth_st_' . hash('sha256', $state);
    $storedMac    = get_transient($transientKey);
    delete_transient($transientKey);

    if (!is_string($storedMac) || $storedMac === '') {
        return false;
    }

    return hash_equals($storedMac, hash_hmac('sha256', $state, $browserSecret));
}

function lacadev_is_oauth_email_verified($user): bool
{
    if (!is_object($user)) {
        return false;
    }

    $raw = [];
    if (method_exists($user, 'getRaw')) {
        $raw = (array) $user->getRaw();
    } elseif (method_exists($user, 'getOriginal')) {
        $raw = (array) $user->getOriginal();
    }

    return ($raw['email_verified'] ?? false) === true || ($raw['verified_email'] ?? false) === true;
}

function socialCallback()
{
    if (!lacadev_is_google_login_enabled()) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'disabled', wp_login_url()));
        exit;
    }

    if (!lacadev_verify_oauth_state()) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'invalid_state', wp_login_url()));
        exit;
    }

    $driver = isset($_GET['driver']) ? sanitize_key(wp_unslash($_GET['driver'])) : '';
    if (!in_array($driver, ['google'], true)) {
        wp_safe_redirect(wp_login_url());
        exit;
    }

    try {
        $socialite = new SocialiteManager(lacadev_get_social_driver_config());
        $user      = $socialite->driver($driver)->stateless()->user();
    } catch (\Throwable $e) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'oauth_failed', wp_login_url()));
        exit;
    }

    $rawEmail = is_object($user) ? (string) ($user->email ?? '') : '';
    if ($rawEmail === '' || !is_email($rawEmail)) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'no_email', wp_login_url()));
        exit;
    }

    if (!lacadev_is_oauth_email_verified($user)) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'email_unverified', wp_login_url()));
        exit;
    }

    $email      = sanitize_email($rawEmail);
    $socialUser = get_user_by('email', $email);
    if ($socialUser) {
        wp_set_current_user($socialUser->ID, $socialUser->user_login);
        wp_set_auth_cookie($socialUser->ID);
        do_action('wp_login', $socialUser->user_login, $socialUser);
        socialCallbackRedirectUrl();
    }

    if (!get_option('users_can_register')) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'user_not_found', wp_login_url()));
        exit;
    }

    $userId = wp_create_user($email, wp_generate_password(24, true, true), $email);
    if (!is_wp_error($userId)) {
        $userInfo    = get_user_by('id', $userId);
        $displayName = sanitize_text_field((string) ($user->name ?? $email));
        wp_update_user([
            'ID'           => $userId,
            'display_name' => $displayName,
            'first_name'   => $displayName,
        ]);
        if ($userInfo) {
            wp_set_current_user($userId, $userInfo->user_login);
            wp_set_auth_cookie($userId);
            do_action('wp_login', $userInfo->user_login, $userInfo);
            socialCallbackRedirectUrl();
        }
    }

    wp_safe_redirect(home_url('/'));
    exit;
}

add_action('wp_ajax_nopriv_google_login', 'googleLogin');
add_action('wp_ajax_google_login', 'googleLogin');
function googleLogin()
{
    if (!lacadev_is_google_login_enabled()) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'disabled', wp_login_url()));
        exit;
    }

    if (is_user_logged_in()) {
        socialCallbackRedirectUrl();
        exit;
    }

    $state     = lacadev_issue_oauth_state();
    $socialite = new SocialiteManager(lacadev_get_social_driver_config());
    $response  = $socialite->driver('google')
        ->stateless()
        ->with(['state' => $state])
        ->redirect();

    if (is_object($response) && method_exists($response, 'send')) {
        $response->send();
    } elseif (is_object($response) && method_exists($response, 'getTargetUrl')) {
        header('Location: ' . $response->getTargetUrl(), true, 302);
    }
    exit;
}

function socialCallbackRedirectUrl()
{
    $user = wp_get_current_user();

    echo '<script>opener.socialLoginReturn({
                success: true,
                notification: {
                    title: "' . esc_js(__('Xin chào, ', 'laca') . $user->user_email) . '",
                    message: "' . esc_js(__('Chúc mừng bạn đã đăng nhập thành công', 'laca')) . '"
                },
                redirect: "/"
            });window.close();</script>';
    exit;
}

add_action('wp_ajax_nopriv_google_admin_callback', 'googleAdminCallback');
add_action('wp_ajax_google_admin_callback', 'googleAdminCallback');
add_action('wp_ajax_nopriv_social_login_callback', 'googleAdminCallback');
add_action('wp_ajax_social_login_callback', 'googleAdminCallback');

/**
 * Xử lý callback đăng nhập quản trị viên bằng Google (chỉ cho phép tài khoản có role administrator).
 */
function googleAdminCallback()
{
    if (!lacadev_is_google_login_enabled()) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'disabled', wp_login_url()));
        exit;
    }

    if (!lacadev_verify_oauth_state()) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'invalid_state', wp_login_url()));
        exit;
    }

    try {
        $socialite = new SocialiteManager(lacadev_get_social_driver_config());
        $user      = $socialite->driver('google')->stateless()->user();
    } catch (\Throwable $e) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'oauth_failed', wp_login_url()));
        exit;
    }

    $rawEmail = is_object($user) ? (string) ($user->email ?? '') : '';
    if ($rawEmail === '' || !is_email($rawEmail)) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'no_email', wp_login_url()));
        exit;
    }

    if (!lacadev_is_oauth_email_verified($user)) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'email_unverified', wp_login_url()));
        exit;
    }

    $adminUser = get_user_by('email', sanitize_email($rawEmail));
    if (!$adminUser) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'user_not_found', wp_login_url()));
        exit;
    }

    if (!in_array('administrator', (array) $adminUser->roles, true)) {
        wp_safe_redirect(add_query_arg('google_admin_error', 'no_admin', wp_login_url()));
        exit;
    }

    wp_set_current_user($adminUser->ID, $adminUser->user_login);
    wp_set_auth_cookie($adminUser->ID);
    do_action('wp_login', $adminUser->user_login, $adminUser);

    wp_safe_redirect(admin_url());
    exit;
}

/**
 * Thêm nút đăng nhập Google vào trang login
 */
add_action('login_form', function () {
    if (!lacadev_is_google_login_enabled()) {
        return;
    }

    $config = lacadev_get_social_driver_config();
    if (empty($config['google']['client_id'])) {
        return;
    }

    $redirect_uri = $config['google']['redirect'] ?? admin_url('admin-ajax.php?action=google_admin_callback');
    $google_login_url = admin_url('admin-ajax.php?action=google_login&redirect_to=' . urlencode($redirect_uri));
    ?>
    <div class="google-login-container">
        <a href="<?php echo esc_url($google_login_url); ?>" class="button google-login-button">
            <svg class="google-icon" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <?php esc_html_e('Đăng nhập bằng Google', 'laca'); ?>
        </a>
    </div>
    <?php
});

/**
 * Hiển thị thông báo lỗi nếu có khi đăng nhập Google thất bại
 */
add_filter('login_message', function ($message) {
    if (isset($_GET['google_admin_error'])) {
        $error = sanitize_key(wp_unslash($_GET['google_admin_error']));
        $error_message = '';
        
        switch ($error) {
            case 'user_not_found':
                $error_message = __('Email Google này chưa được cấp tài khoản trên hệ thống. Vui lòng liên hệ Quản trị viên.', 'laca');
                break;
            case 'no_admin':
                $error_message = __('Tài khoản của bạn không có quyền Quản trị viên để đăng nhập qua cổng này.', 'laca');
                break;
            case 'no_email':
                $error_message = __('Không thể lấy thông tin email từ Google. Vui lòng thử lại.', 'laca');
                break;
            case 'email_unverified':
                $error_message = __('Email Google của bạn chưa được xác minh.', 'laca');
                break;
            case 'invalid_state':
                $error_message = __('Phiên xác thực Google đã hết hạn hoặc không hợp lệ. Vui lòng thử lại.', 'laca');
                break;
            case 'disabled':
                $error_message = __('Tính năng đăng nhập bằng Google hiện đang bị tắt.', 'laca');
                break;
            default:
                $error_message = __('Đăng nhập bằng Google thất bại. Vui lòng thử lại.', 'laca');
                break;
        }
        
        if (!empty($error_message)) {
            $message = '<div id="login_error" class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
        }
    }
    
    return $message;
});

/**
 * Enqueue Toastr CSS & JS cho admin
 */
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', [], '2.1.4');
    wp_enqueue_script('toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', ['jquery'], '2.1.4', true);
});

/**
 * Helper function để trigger toastr notification từ PHP
 * Sử dụng: laca_show_admin_notice('Thành công!', 'success', 'Tiêu đề');
 */
function laca_show_admin_notice($message, $type = 'success', $title = '') {
    add_action('admin_footer', function () use ($message, $type, $title) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        "closeButton": true,
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "timeOut": "4000"
                    };
                    toastr['<?php echo esc_js($type); ?>'](
                        '<?php echo esc_js($message); ?>',
                        '<?php echo esc_js($title); ?>'
                    );
                }
            });
        </script>
        <?php
    });
}

function sendToastNotification($message, $icon = 'success')
{
    $admin_handle = class_exists('\App\Contracts\AssetHandles') ? \App\Contracts\AssetHandles::ADMIN_JS : 'theme-admin-js-bundle';
    if (wp_script_is($admin_handle, 'enqueued')) {
        $script = "
            window.dispatchEvent(new CustomEvent('toastNotify', {
                message: '" . esc_js($message) . "',
                icon: '" . esc_js($icon) . "'
            }));
        ";
        wp_add_inline_script($admin_handle, $script, 'before');
    }
}
