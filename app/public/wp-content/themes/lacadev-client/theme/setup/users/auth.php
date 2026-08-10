<?php

use Overtrue\Socialite\SocialiteManager;

add_action('wp_ajax_nopriv_user_login', 'mm_user_login');
add_action('wp_ajax_user_login', 'mm_user_login');

/**
 * Lấy cấu hình Socialite cho Google một cách "lazy"
 * để tránh gọi carbon_get_theme_option() quá sớm
 * trước khi Carbon Fields đăng ký xong các fields.
 *
 * @return array
 */
function lacadev_get_social_driver_config()
{
    // Phòng trường hợp Carbon Fields chưa sẵn sàng
    if (!function_exists('carbon_get_theme_option')) {
        return [
            'google' => [
                'client_id'     => '',
                'client_secret' => '',
                'redirect'      => home_url('/wp-admin/admin-ajax.php?action=google_admin_callback'),
            ],
        ];
    }

    return [
        'google' => [
            'client_id'     => carbon_get_theme_option('google_client_id'),
            'client_secret' => carbon_get_theme_option('google_client_secret'),
            'redirect'      => home_url('/wp-admin/admin-ajax.php?action=google_admin_callback'),
        ],
    ];
}
function mm_user_login()
{
    if (empty($_POST)) {
        wp_send_json_error(__('Empty request.', 'laca'));
    }

    if (!isset($_POST['_token']) || !wp_verify_nonce(wp_unslash($_POST['_token']), 'user_dang_nhap')) {
        wp_send_json_error(__('Token mismatch!', 'laca'));
    }

    if (empty($_POST['user_login']) || empty($_POST['password'])) {
        wp_send_json_error(__('Tài khoản hoặc mật khẩu không đúng, vui lòng kiểm tra lại', 'laca'));
    }

    $user = wp_signon([
        'user_login'    => wp_unslash($_POST['user_login']),
        'user_password' => wp_unslash($_POST['password']),
        'remember'      => true,
    ], false);

    if (is_wp_error($user)) {
        wp_send_json_error($user->get_error_message());
    }

    $redirect_url = !empty($_POST['redirect_to']) ? wp_validate_redirect(wp_unslash($_POST['redirect_to']), home_url()) : home_url();

    // Return success with alert data for AJAX handler
    wp_send_json_success([
        'redirect' => $redirect_url,
        'alert' => [
            'title' => __('Xin chào, ', 'laca') . $user->user_email,
            'message' => __('Chúc mừng bạn đã đăng nhập thành công', 'laca')
        ]
    ]);
}

add_action('wp_ajax_nopriv_user_register', 'mm_user_register');
add_action('wp_ajax_user_register', 'mm_user_register');
function mm_user_register()
{
    if (empty($_POST)) {
        wp_send_json_error(__('Empty request.', 'laca'));
    }

    if (!isset($_REQUEST['_token']) || !wp_verify_nonce(wp_unslash($_REQUEST['_token']), 'user_dang_ky_thanh_vien')) {
        wp_send_json_error(__('Token mismatch!', 'laca'));
    }

    if (empty($_POST['first_name'])) {
        wp_send_json_error(__('Vui lòng nhập họ', 'laca'));
    }

    if (empty($_POST['last_name'])) {
        wp_send_json_error(__('Vui lòng nhập tên', 'laca'));
    }

    if (empty($_POST['email']) || !is_email(wp_unslash($_POST['email']))) {
        wp_send_json_error(__('Vui lòng nhập email hợp lệ', 'laca'));
    }

    if (empty($_POST['password'])) {
        wp_send_json_error(__('Vui lòng nhập mật khẩu', 'laca'));
    }

    if ($_POST['password'] !== $_POST['password_confirmation']) {
        wp_send_json_error(__('Vui lòng kiểm tra lại mật khẩu', 'laca'));
    }

    $userParams = [
        'user_login'   => sanitize_user(wp_unslash($_POST['user_login'])),
        'user_email'   => sanitize_email(wp_unslash($_POST['email'])),
        'user_pass'    => wp_unslash($_POST['password_confirmation']),
        'display_name' => sanitize_text_field(wp_unslash($_POST['last_name'])),
        'first_name'   => sanitize_text_field(wp_unslash($_POST['first_name'])),
        'last_name'    => sanitize_text_field(wp_unslash($_POST['last_name'])),
    ];

    $idUser = wp_insert_user($userParams);

    if (is_wp_error($idUser)) {
        wp_send_json_error($idUser->get_error_message());
    }

    if (!empty($_POST['birthday'])) {
        update_user_meta($idUser, '_user_birthday', sanitize_text_field(wp_unslash($_POST['birthday'])));
    }
    if (!empty($_POST['sex'])) {
        update_user_meta($idUser, '_user_gender', sanitize_text_field(wp_unslash($_POST['sex'])));
    }

    // Attempt auto-login after register
    wp_set_current_user($idUser);
    wp_set_auth_cookie($idUser);

    wp_send_json_success([
        'message' => __('Đăng ký thành công!', 'laca'),
        'redirect' => home_url()
    ]);
}

add_action('wp_ajax_nopriv_user_reset_password', 'mm_user_reset_password');
add_action('wp_ajax_user_reset_password', 'mm_user_reset_password');
function mm_user_reset_password()
{
    wp_send_json_success(true);
}

add_action('wp_ajax_nopriv_google_login', 'googleLogin');
add_action('wp_ajax_google_login', 'googleLogin');
function googleLogin() {
    if (is_user_logged_in()) {
        socialCallbackRedirectUrl();
        die();
    }

    $redirect = !empty($_GET['redirect_to']) ? $_GET['redirect_to'] : null;
    // Debug cấu hình Google để kiểm tra client_id có đang rỗng không
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[lacadev][google_login] DRIVER_CONFIG = ' . print_r(lacadev_get_social_driver_config(), true));
    }

    $socialite = new SocialiteManager(lacadev_get_social_driver_config());

    $driver = $socialite->driver('google');

    // Nếu có redirect_to thì override redirect URI
    if ($redirect) {
        $driver->withRedirectUrl($redirect);
    }

    // Thực hiện redirect HTTP đúng chuẩn, không in ra nội dung "HTTP/1.0 302 Found ..."
    $response = $driver->redirect();

    if (is_object($response) && method_exists($response, 'send')) {
        // RedirectResponse từ thư viện Socialite
        $response->send();
    } else {
        // Fallback: cố gắng lấy URL và dùng wp_safe_redirect
        if (is_object($response) && method_exists($response, 'getTargetUrl')) {
            $targetUrl = $response->getTargetUrl();
        } else {
            $targetUrl = (string) $response;
        }
        wp_safe_redirect($targetUrl);
    }
    exit;
}

function socialCallbackRedirectUrl()
{
    $user = wp_get_current_user();

    echo '<script>opener.socialLoginReturn({
                success: true,
                notification: {
                    title: "' . __('Xin chào, ', 'laca') . $user->user_email . '", 
                    message: "' . __('Chúc mừng bạn đã đăng nhập thành công', 'laca') . '"
                },
                redirect: "/"
            });window.close();</script>';
}

add_action('wp_ajax_nopriv_google_admin_callback', 'googleAdminCallback');
add_action('wp_ajax_google_admin_callback', 'googleAdminCallback');
/**
 * Xử lý callback đăng nhập/đăng ký admin bằng Google
 */
function googleAdminCallback() {
    $socialite = new SocialiteManager(lacadev_get_social_driver_config());
    $user = $socialite->driver('google')->user();

    if (!$user || empty($user->getEmail())) {
        echo '<script>alert("Không lấy được thông tin từ Google!");window.close();</script>';
        exit;
    }

    // Kiểm tra email có phải admin không
    $admin_user = get_user_by('email', $user->getEmail());
    if ($admin_user && in_array('administrator', $admin_user->roles)) {
        // Đăng nhập user admin
        wp_set_current_user($admin_user->ID);
        wp_set_auth_cookie($admin_user->ID);

        // Kích hoạt hook wp_login để các tính năng chào mừng (popup) vẫn hoạt động
        /**
         * @see mm_set_login_alert_flag() trong app/helpers/login.php
         */
        do_action('wp_login', $admin_user->user_login, $admin_user);

        // Redirect thẳng về trang quản trị thay vì dùng window.opener JS
        $redirect = admin_url();
        wp_safe_redirect($redirect);
        exit;
    } else {
        // Nếu không phải admin thì quay lại trang login với thông báo lỗi đơn giản
        $login_url = wp_login_url();
        $login_url = add_query_arg('google_admin_error', 'no_admin', $login_url);
        wp_safe_redirect($login_url);
        exit;
    }
}

/**
 * Thêm nút đăng nhập Google vào trang login
 */
add_action('login_form', function () {
    // Lấy URL để bắt đầu quá trình đăng nhập Google
    $google_login_url = admin_url('admin-ajax.php?action=google_login&redirect_to=' . urlencode(admin_url('admin-ajax.php?action=google_admin_callback')));
    ?>
    <div class="google-login-container">
        <a href="<?php echo esc_url($google_login_url); ?>" class="button google-login-button">
            <span class="google-icon">
                <svg viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                </svg>
            </span>
            <span class="button-text">Google</span>
        </a>
    </div>
    <?php
});

/**
 * Set welcome alert flag on login
 */
add_action('wp_login', 'mm_set_login_alert_flag', 10, 2);
function mm_set_login_alert_flag($user_login, $user) {
    set_transient('show_welcome_alert_' . $user->ID, true, 60);
}

/**
 * Inject welcome alert script in admin
 */
add_action('admin_enqueue_scripts', 'mm_inject_login_alert_script', 20);
function mm_inject_login_alert_script() {
    $user_id = get_current_user_id();
    if (get_transient('show_welcome_alert_' . $user_id)) {
        delete_transient('show_welcome_alert_' . $user_id);
        $user = get_userdata($user_id);
        
        // Get current hour in 24-hour format
        $current_hour = (int) current_time('H');
        $current_day = strtolower(current_time('l')); // lower case full day name
        
        $title = '';
        $message = '';
        $icon = '☕'; // Default
        
        // Check for weekend first
        if ($current_day === 'saturday' || $current_day === 'sunday') {
            $title = __('Cuối tuần vui vẻ, ', 'laca') . $user->display_name;
            $message = __('Hãy gác lại công việc, dành thời gian nghỉ ngơi và sạc đầy năng lượng nhé! ✨', 'laca');
            $icon = '🌈';
        } else {
            // Weekday logic
            if ($current_hour >= 5 && $current_hour < 11) {
                // Morning (5:00 - 10:59)
                $title = sprintf(__('Chào buổi sáng %s', 'laca'), $user->display_name);
                $message = __('Một tách cà phê thơm và bắt đầu ngày mới thật hứng khởi nào! ☕', 'laca');
                $icon = '☀️';
            } elseif ($current_hour >= 11 && $current_hour < 14) {
                // Lunch (11:00 - 13:59)
                $title = sprintf(__('Nghỉ trưa thôi %s', 'laca'), $user->display_name);
                $message = __('Đừng quên ăn trưa và chợp mắt một lát để nạp lại pin nhé! 🍱', 'laca');
                $icon = '🔋';
            } elseif ($current_hour >= 14 && $current_hour < 18) {
                // Afternoon (14:00 - 17:59)
                $title = sprintf(__('Chào buổi chiều %s', 'laca'), $user->display_name);
                $message = __('Giữ vững sự tập trung, sắp hoàn thành mục tiêu ngày rồi! 💪', 'laca');
                $icon = '🌇';
            } else {
                // Evening/Night (18:00 - 4:59)
                $title = sprintf(__('Chào buổi tối %s', 'laca'), $user->display_name);
                $message = __('Đã muộn rồi, làm việc vừa sức và sớm đi ngủ nhé! 🌕', 'laca');
                $icon = '🌙';
            }
        }
        
        $script = "
            localStorage.setItem('show_alert', JSON.stringify({
                title: '" . esc_js($title) . "',
                message: '" . esc_js($message) . "',
                icon: '" . esc_js($icon) . "'
            }));
        ";
        wp_add_inline_script('theme-admin-js-bundle', $script, 'before');
    }
}
