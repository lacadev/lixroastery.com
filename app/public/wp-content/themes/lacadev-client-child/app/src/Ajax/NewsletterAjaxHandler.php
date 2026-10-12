<?php

/**
 * Newsletter AJAX Handler
 *
 * Nhận email từ form đăng ký ở footer (theme/template-parts/newsletter-form.php),
 * đăng ký luôn (không cần xác nhận qua email), gửi email cảm ơn
 * (NewsletterMailer) rồi giao NewsletterSync đẩy sang Google Sheet /
 * Mailchimp / Brevo (nếu đã cấu hình).
 *
 * Trả về `code` thay vì câu thông báo: câu hiển thị lấy từ Theme Options
 * theo ngôn ngữ trang (in sẵn vào data-* của form), vì request admin-ajax
 * không chắc mang đúng ngôn ngữ Polylang của trang đang xem.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterAjaxHandler
{
    public const ACTION = 'lix_newsletter_subscribe';
    public const NONCE_ACTION = 'lix_newsletter';
    public const RECAPTCHA_ACTION = 'newsletter';

    public function __construct()
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handle']);

        // Trang được LiteSpeed Cache lưu lâu hơn tuổi thọ nonce (12–24h) →
        // nonce in sẵn trong HTML hết hạn. JS gọi action này lấy nonce mới
        // rồi thử lại 1 lần (admin-ajax không bị cache).
        add_action('wp_ajax_lix_newsletter_nonce', [$this, 'refreshNonce']);
        add_action('wp_ajax_nopriv_lix_newsletter_nonce', [$this, 'refreshNonce']);
    }

    /**
     * Dùng chung key reCAPTCHA v3 của Laca Admin → Google reCAPTCHA. Chỉ trả
     * site key khi có đủ CẢ secret key — thiếu 1 trong 2 thì form chạy không
     * reCAPTCHA thay vì chặn mọi người đăng ký.
     */
    public static function recaptchaSiteKey(): string
    {
        $site_key = trim((string) carbon_get_theme_option('recaptcha_site_key'));
        $secret_key = trim((string) carbon_get_theme_option('recaptcha_secret_key'));

        return ($site_key !== '' && $secret_key !== '') ? $site_key : '';
    }

    public function refreshNonce(): void
    {
        nocache_headers();
        wp_send_json_success(['nonce' => wp_create_nonce(self::NONCE_ACTION)]);
    }

    public function handle(): void
    {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['code' => 'nonce'], 403);
        }

        // Honeypot: người thật không thấy ô này; bot điền vào thì giả vờ
        // thành công để bot không dò ra cơ chế chặn.
        if (!empty($_POST['website'])) {
            wp_send_json_success(['code' => 'subscribed']);
        }

        if (function_exists('lacadev_check_rate_limit')) {
            lacadev_check_rate_limit('newsletter', 5, 10 * MINUTE_IN_SECONDS);
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if ($email === '' || !is_email($email) || strlen($email) > 190) {
            wp_send_json_error(['code' => 'invalid'], 400);
        }
        $email = strtolower($email);

        // Kiểm tra reCAPTCHA sau validate email: email sai thì báo ngay, không
        // tốn 1 lượt gọi Google.
        if (self::recaptchaSiteKey() !== '' && !$this->verifyRecaptcha()) {
            wp_send_json_error(['code' => 'captcha'], 403);
        }

        $lang = isset($_POST['lang']) ? substr(sanitize_key(wp_unslash($_POST['lang'])), 0, 10) : '';
        if (function_exists('pll_languages_list') && !in_array($lang, pll_languages_list(), true)) {
            $lang = '';
        }

        // Chỉ nhận URL nội bộ của site — referer từ domain lạ bị bỏ trống.
        $source_url = substr(wp_validate_redirect(esc_url_raw((string) wp_get_raw_referer()), ''), 0, 255);

        $subscriber = NewsletterSubscribers::findByEmail($email);

        if (!$subscriber) {
            $subscriber = NewsletterSubscribers::add($email, $lang, $source_url);
            if (!$subscriber) {
                // Request khác vừa chèn cùng email trong tích tắc.
                wp_send_json_success(['code' => 'exists']);
            }
        } elseif ($subscriber['status'] === NewsletterSubscribers::STATUS_UNSUBSCRIBED) {
            // Đăng ký lại sau khi huỷ — khách chủ động bấm Subscribe nên đăng ký luôn.
            $subscriber = NewsletterSubscribers::setStatus((int) $subscriber['id'], NewsletterSubscribers::STATUS_SUBSCRIBED, ['lang' => $lang]);
        } else {
            // Đã đăng ký: không gửi lại email cảm ơn.
            wp_send_json_success(['code' => 'exists']);
        }

        // Gửi email cảm ơn + đồng bộ sau khi đã trả phản hồi — SMTP lỗi cũng
        // không ảnh hưởng việc đăng ký (đã lưu DB), admin thấy lỗi ở menu Newsletter.
        NewsletterMailer::queueWelcome($subscriber);
        NewsletterSync::queue(NewsletterSubscribers::STATUS_SUBSCRIBED, $subscriber);

        wp_send_json_success(['code' => 'subscribed']);
    }

    private function verifyRecaptcha(): bool
    {
        $token = isset($_POST['recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['recaptcha_token'])) : '';
        if ($token === '') {
            return false;
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 8,
            'body' => [
                'secret' => trim((string) carbon_get_theme_option('recaptcha_secret_key')),
                'response' => $token,
                'remoteip' => function_exists('lacadev_get_real_ip') ? lacadev_get_real_ip() : '',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($result) || empty($result['success'])) {
            return false;
        }

        $threshold = (float) carbon_get_theme_option('recaptcha_score') ?: 0.5;
        $expected_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));

        // Kiểm action + hostname: chặn dùng lại token sinh từ form khác/site khác.
        return isset($result['score'], $result['action'])
            && (float) $result['score'] >= $threshold
            && $result['action'] === self::RECAPTCHA_ACTION
            && (empty($result['hostname']) || strtolower((string) $result['hostname']) === $expected_host);
    }
}

new NewsletterAjaxHandler();
