<?php

/**
 * Câu chữ của Newsletter (form footer, email xác nhận, trang xác nhận/huỷ) —
 * 1 nguồn mặc định THEO TỪNG NGÔN NGỮ, dùng chung cho Theme Options
 * (set_default_value) và lúc hiển thị, tránh lệch nhau giữa 2 nơi.
 *
 * Thứ tự lấy câu: admin đã nhập ở Theme Options cho đúng ngôn ngữ → mặc định
 * của ngôn ngữ đó → mặc định tiếng Anh (ngôn ngữ chưa có bản dịch sẵn).
 *
 * Đọc thẳng `_{key}{lang}` trong wp_options (đúng cách Carbon Fields lưu field
 * có hậu tố currentLanguage()) thay vì getOption(): email gửi trong request
 * AJAX và trang xác nhận phải theo ngôn ngữ LÚC KHÁCH ĐĂNG KÝ, không theo
 * ngôn ngữ Polylang của request hiện tại.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterTexts
{
    private const FALLBACK_LANG = 'en';

    private const DEFAULTS = [
        'en' => [
            // Form ở footer
            'newsletter_title' => 'Connect with our mission - Join the sustainable circle',
            'newsletter_placeholder' => 'Your Email Address',
            'newsletter_button' => 'Subscribe',
            'newsletter_consent' => 'By subscribing, you agree to receive emails from LIX Roastery. You can unsubscribe at any time.',
            'newsletter_msg_pending' => 'Almost there! Please check your inbox and click the link to confirm your subscription.',
            'newsletter_msg_exists' => 'This email is already subscribed.',
            'newsletter_msg_invalid' => 'Please enter a valid email address.',
            'newsletter_msg_limit' => 'Too many attempts. Please try again in a few minutes.',
            'newsletter_msg_captcha' => 'We could not verify your request. Please reload the page and try again.',
            'newsletter_msg_error' => 'Something went wrong. Please try again.',

            // Email xác nhận
            'newsletter_mail_subject' => 'Please confirm your subscription to LIX Roastery',
            'newsletter_mail_body' => "Thank you for joining the sustainable circle.\nPlease confirm your email address by clicking the button below. If you did not sign up, you can simply ignore this email.",
            'newsletter_mail_button' => 'Confirm subscription',

            // Trang sau khi bấm link trong email
            'newsletter_msg_success' => 'Thank you for joining the circle! Your subscription is confirmed.',
            'newsletter_page_unsubscribe' => 'Do you want to stop receiving emails from LIX Roastery?',
            'newsletter_page_unsubscribe_button' => 'Unsubscribe',
            'newsletter_page_unsubscribed' => 'You have been unsubscribed. We are sorry to see you go.',
            'newsletter_page_invalid' => 'This link is invalid or has expired.',
        ],
        'vi' => [
            'newsletter_title' => 'Kết nối cùng sứ mệnh - Tham gia vòng tròn bền vững',
            'newsletter_placeholder' => 'Địa chỉ email của bạn',
            'newsletter_button' => 'Đăng ký',
            'newsletter_consent' => 'Khi đăng ký, bạn đồng ý nhận email từ LIX Roastery. Bạn có thể huỷ đăng ký bất cứ lúc nào.',
            'newsletter_msg_pending' => 'Sắp xong rồi! Vui lòng kiểm tra hộp thư và bấm vào liên kết để xác nhận đăng ký.',
            'newsletter_msg_exists' => 'Email này đã được đăng ký.',
            'newsletter_msg_invalid' => 'Vui lòng nhập địa chỉ email hợp lệ.',
            'newsletter_msg_limit' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau vài phút.',
            'newsletter_msg_captcha' => 'Không thể xác minh yêu cầu của bạn. Vui lòng tải lại trang và thử lại.',
            'newsletter_msg_error' => 'Đã có lỗi xảy ra. Vui lòng thử lại.',

            'newsletter_mail_subject' => 'Xác nhận đăng ký nhận tin từ LIX Roastery',
            'newsletter_mail_body' => "Cảm ơn bạn đã tham gia vòng tròn bền vững cùng LIX Roastery.\nVui lòng bấm nút bên dưới để xác nhận địa chỉ email. Nếu bạn không đăng ký, hãy bỏ qua email này.",
            'newsletter_mail_button' => 'Xác nhận đăng ký',

            'newsletter_msg_success' => 'Cảm ơn bạn đã tham gia! Đăng ký của bạn đã được xác nhận.',
            'newsletter_page_unsubscribe' => 'Bạn muốn ngừng nhận email từ LIX Roastery?',
            'newsletter_page_unsubscribe_button' => 'Huỷ đăng ký',
            'newsletter_page_unsubscribed' => 'Bạn đã huỷ đăng ký thành công. Rất tiếc khi phải chia tay bạn.',
            'newsletter_page_invalid' => 'Liên kết không hợp lệ hoặc đã hết hạn.',
        ],
    ];

    /**
     * Bộ mặc định của 1 ngôn ngữ (thiếu key nào thì lấy tiếng Anh) — dùng cho
     * set_default_value() ở Theme Options theo đúng tab ngôn ngữ đang sửa.
     *
     * @return array<string, string>
     */
    public static function defaults(string $lang = ''): array
    {
        return array_merge(self::DEFAULTS[self::FALLBACK_LANG], self::DEFAULTS[self::lang($lang)] ?? []);
    }

    public static function get(string $key, string $lang = ''): string
    {
        $lang = self::lang($lang);
        $default = self::defaults($lang)[$key] ?? '';
        $value = get_option('_' . $key . $lang);

        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        // Carbon Fields lưu CẢ tab khi admin bấm lưu: nếu tab tiếng Việt từng
        // được lưu lúc mặc định còn là tiếng Anh, option chứa nguyên câu tiếng
        // Anh chưa sửa — coi như admin chưa nhập, dùng mặc định đúng ngôn ngữ.
        if ($lang !== self::FALLBACK_LANG && $value === (self::DEFAULTS[self::FALLBACK_LANG][$key] ?? null)) {
            return $default;
        }

        return $value;
    }

    /**
     * Ngôn ngữ dùng làm hậu tố option: ngôn ngữ đã lưu của khách → ngôn ngữ
     * mặc định Polylang → '' (site không đa ngôn ngữ).
     */
    public static function lang(string $lang = ''): string
    {
        if ($lang !== '') {
            return $lang;
        }

        return function_exists('pll_default_language') ? (string) pll_default_language() : currentLanguage();
    }
}
