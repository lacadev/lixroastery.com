<?php
/**
 * Google reCAPTCHA v3 Integration
 *
 * Bảo vệ Đăng nhập, Đăng ký, Bình luận và Form liên hệ (shortcode laca_contact_form).
 *
 * @package LaCaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Recaptcha {

    public const HANDLE = 'google-recaptcha';
    public const ACTION_FORM = 'submit';
    public const ACTION_CONTACT = 'contact';

    private $site_key;
    private $secret_key;
    private $score_threshold;
    private $expected_hostname;

    /**
     * Đọc cấu hình trang Laca Admin → Google reCAPTCHA.
     *
     * KHÔNG dùng getOption(): hàm đó nối hậu tố ngôn ngữ (vd "recaptcha_site_keyen") trong
     * khi các field này đăng ký không hậu tố → trên site Polylang mọi giá trị đọc ra rỗng và
     * reCAPTCHA tắt hoàn toàn dù admin đã nhập đủ key.
     */
    public static function config(): array {
        $get = static function ($key) {
            return function_exists('carbon_get_theme_option') ? carbon_get_theme_option($key) : null;
        };
        // Ô number ở trình duyệt tiếng Việt có thể gửi "0,5".
        $score = str_replace(',', '.', trim((string) $get('recaptcha_score')));

        return [
            'site_key'   => trim((string) $get('recaptcha_site_key')),
            'secret_key' => trim((string) $get('recaptcha_secret_key')),
            'score'      => is_numeric($score) ? max(0.0, min(1.0, (float) $score)) : 0.5,
            'login'      => (bool) $get('enable_recaptcha_login'),
            'register'   => (bool) $get('enable_recaptcha_register'),
            'comment'    => (bool) $get('enable_recaptcha_comment'),
            'contact'    => (bool) $get('enable_recaptcha_contact'),
        ];
    }

    /**
     * @param string $form login|register|comment|contact
     */
    public static function isEnabledFor(string $form): bool {
        $config = self::config();
        return $config['site_key'] !== '' && $config['secret_key'] !== '' && !empty($config[$form]);
    }

    /**
     * Nạp script Google theo nhu cầu: chỉ trang nào thật sự có form cần bảo vệ mới tải
     * api.js (~150 KB), không làm chậm mọi trang. Gọi được cả sau wp_enqueue_scripts
     * (shortcode, comment_form) — WP tự in script đăng ký muộn ở footer.
     */
    public static function enqueue(): void {
        if (wp_script_is(self::HANDLE, 'registered')) {
            wp_enqueue_script(self::HANDLE);
        }
    }

    public function __construct() {
        $config = self::config();
        $this->site_key = $config['site_key'];
        $this->secret_key = $config['secret_key'];
        $this->score_threshold = $config['score'];
        $this->expected_hostname = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));

        if ($this->site_key === '' || $this->secret_key === '') {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'register_script'], 5);
        add_action('login_enqueue_scripts', [$this, 'register_script'], 5);

        if ($config['login']) {
            add_action('login_form', [$this, 'print_hidden_field']);
            add_filter('authenticate', [$this, 'verify_login'], 30, 3);
        }

        if ($config['register']) {
            add_action('register_form', [$this, 'print_hidden_field']);
            add_filter('registration_errors', [$this, 'verify_registration'], 10, 3);
        }

        if ($config['comment']) {
            add_action('comment_form', [$this, 'print_hidden_field']);
            add_filter('preprocess_comment', [$this, 'verify_comment']);
        }

        if ($config['contact']) {
            add_filter('laca_verify_recaptcha', [$this, 'verify_contact'], 10, 2);
        }
    }

    public function register_script() {
        // in_footer: script có thể được enqueue muộn (trong shortcode/comment_form).
        wp_register_script(self::HANDLE, 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($this->site_key), [], null, true);

        // Form tải lại trang (đăng nhập/đăng ký/bình luận): điền token sẵn và làm mới mỗi
        // 90s (token sống 2 phút). Form AJAX (liên hệ) tự lấy token mới lúc bấm gửi qua
        // window.lacaRecaptcha.execute() vì token chỉ dùng được 1 lần.
        $script = 'window.lacaRecaptcha = {'
            . 'siteKey: ' . wp_json_encode($this->site_key) . ','
            . 'execute: function (action) { return new Promise(function (resolve) {'
            . '  if (!window.grecaptcha) { resolve(""); return; }'
            // Key sai tên miền / Google chậm: không để nút Gửi quay mãi, server sẽ báo lỗi rõ.
            . '  setTimeout(function () { resolve(""); }, 8000);'
            . '  grecaptcha.ready(function () { grecaptcha.execute(window.lacaRecaptcha.siteKey, {action: action}).then(resolve, function () { resolve(""); }); });'
            . '}); }'
            . '};'
            . '(function () {'
            . '  function fill() {'
            . '    var inputs = document.querySelectorAll("input.laca-recaptcha-response[data-recaptcha-refresh]");'
            . '    if (!inputs.length) { return; }'
            . '    window.lacaRecaptcha.execute(' . wp_json_encode(self::ACTION_FORM) . ').then(function (token) { inputs.forEach(function (input) { input.value = token; }); });'
            . '  }'
            . '  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", fill); } else { fill(); }'
            . '  setInterval(fill, 90000);'
            . '})();';
        wp_add_inline_script(self::HANDLE, $script);

        if (did_action('login_enqueue_scripts')) {
            self::enqueue();
        }
    }

    /**
     * Print hidden input field
     */
    public function print_hidden_field() {
        self::enqueue();
        echo '<input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" data-recaptcha-refresh value="">';
        echo '<input type="text" name="laca_hp_email" class="laca-hp-email" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;pointer-events:none;">';
        echo '<input type="hidden" name="laca_form_ts" value="' . esc_attr((string) time()) . '">';
    }

    /**
     * Verify Token Logic
     */
    private function verify_token($token, $expected_action = self::ACTION_FORM) {
        if (empty($token)) {
            return new WP_Error('recaptcha_error', __('Vui lòng tải lại trang để xác thực reCAPTCHA.', 'laca'));
        }

        $remote_ip = class_exists(\App\Helpers\ClientIp::class) ? \App\Helpers\ClientIp::get() : ($_SERVER['REMOTE_ADDR'] ?? '');

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body' => [
                'secret'   => $this->secret_key,
                'response' => $token,
                'remoteip' => $remote_ip,
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('recaptcha_error', __('Lỗi kết nối đến máy chủ xác thực.', 'laca'));
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!is_array($result)) {
            return new WP_Error('recaptcha_error', __('Phản hồi xác thực không hợp lệ.', 'laca'));
        }

        if (empty($result['success']) || !$result['success']) {
            return new WP_Error('recaptcha_error', __('Xác thực reCAPTCHA thất bại. Bạn có phải là robot?', 'laca'));
        }

        if (!isset($result['score']) || !is_numeric($result['score']) || (float) $result['score'] < $this->score_threshold) {
             return new WP_Error('recaptcha_low_score', __('Hệ thống nghi ngờ bạn là robot. Điểm tín nhiệm thấp.', 'laca'));
        }

        if (!empty($expected_action) && (!isset($result['action']) || $result['action'] !== $expected_action)) {
            return new WP_Error('recaptcha_action_mismatch', __('Phiên xác thực không hợp lệ (action).', 'laca'));
        }

        if (!empty($this->expected_hostname) && !empty($result['hostname'])) {
            $result_hostname = strtolower((string) $result['hostname']);
            if ($result_hostname !== $this->expected_hostname) {
                return new WP_Error('recaptcha_hostname_mismatch', __('Phiên xác thực không hợp lệ (hostname).', 'laca'));
            }
        }

        if (!empty($result['challenge_ts'])) {
            $challenge_time = strtotime((string) $result['challenge_ts']);
            if ($challenge_time !== false && (time() - $challenge_time) > 180) {
                return new WP_Error('recaptcha_timeout', __('Mã xác thực đã hết hạn, vui lòng thử lại.', 'laca'));
            }
        }

        return true;
    }

    /**
     * Validate anti-bot context fields.
     */
    private function validate_submission_context() {
        $honeypot = isset($_POST['laca_hp_email']) ? sanitize_text_field(wp_unslash($_POST['laca_hp_email'])) : '';
        if ($honeypot !== '') {
            return new WP_Error('bot_detected', __('Yêu cầu không hợp lệ.', 'laca'));
        }

        $form_ts = isset($_POST['laca_form_ts']) ? (int) $_POST['laca_form_ts'] : 0;
        if ($form_ts <= 0) {
            return new WP_Error('invalid_form_context', __('Phiên gửi biểu mẫu không hợp lệ.', 'laca'));
        }

        $elapsed = time() - $form_ts;
        if ($elapsed < 2 || $elapsed > 7200) {
            return new WP_Error('invalid_form_timing', __('Thời gian gửi biểu mẫu không hợp lệ.', 'laca'));
        }

        return true;
    }

    /**
     * Validate Login
     */
    public function verify_login($user, $username, $password) {
        // Chỉ form wp-login.php (field "log"). wp-login.php gọi wp_signon() cả khi chỉ MỞ
        // trang → không chặn ở đây sẽ hiện lỗi ngay lúc vào trang; form đăng nhập
        // WooCommerce / XML-RPC / Application Password không có token nên cũng bỏ qua.
        if (!isset($_POST['log'])) {
            return $user;
        }

        $context_check = $this->validate_submission_context();
        if (is_wp_error($context_check)) {
            return $context_check;
        }

        $token = isset($_POST['laca_recaptcha_response']) ? sanitize_text_field(wp_unslash($_POST['laca_recaptcha_response'])) : '';
        $verify = $this->verify_token($token, self::ACTION_FORM);

        if (is_wp_error($verify)) {
            return $verify;
        }

        return $user;
    }

    /**
     * Validate Registration
     */
    public function verify_registration($errors, $sanitized_user_login, $user_email) {
        $context_check = $this->validate_submission_context();
        if (is_wp_error($context_check)) {
            $errors->add('recaptcha_error', $context_check->get_error_message());
            return $errors;
        }

        $token = isset($_POST['laca_recaptcha_response']) ? sanitize_text_field(wp_unslash($_POST['laca_recaptcha_response'])) : '';
        $verify = $this->verify_token($token, self::ACTION_FORM);

        if (is_wp_error($verify)) {
            $errors->add('recaptcha_error', $verify->get_error_message());
        }

        return $errors;
    }

    /**
     * Validate Comment
     */
    public function verify_comment($commentdata) {
        if (!is_user_logged_in()) {
            $context_check = $this->validate_submission_context();
            if (is_wp_error($context_check)) {
                wp_die($context_check->get_error_message());
            }

            $token = isset($_POST['laca_recaptcha_response']) ? sanitize_text_field(wp_unslash($_POST['laca_recaptcha_response'])) : '';
            $verify = $this->verify_token($token, self::ACTION_FORM);

            if (is_wp_error($verify)) {
                wp_die($verify->get_error_message());
            }
        }
        return $commentdata;
    }

    /**
     * Validate Contact Form (filter laca_verify_recaptcha của ContactFormAjaxHandler).
     *
     * @param true|WP_Error $result
     * @param string        $token
     * @return true|WP_Error
     */
    public function verify_contact($result, $token) {
        if (is_wp_error($result)) {
            return $result;
        }
        return $this->verify_token(sanitize_text_field(wp_unslash((string) $token)), self::ACTION_CONTACT);
    }
}

add_action('init', function() {
    new Laca_Recaptcha();
});
