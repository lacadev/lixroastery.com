<?php

/**
 * Câu chữ của Newsletter (form footer, email cảm ơn, trang huỷ đăng ký) —
 * 1 nguồn mặc định dùng chung cho Theme Options (set_default_value) và lúc
 * hiển thị, tránh lệch nhau giữa 2 nơi.
 *
 * Thứ tự lấy câu: admin đã nhập ở Theme Options cho đúng ngôn ngữ → câu mặc
 * định (câu gốc tiếng Việt, dịch sang ngôn ngữ đó qua file .po).
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
    /**
     * Câu mặc định — câu gốc tiếng Việt, ngôn ngữ khác dịch qua file .po
     * (theme/languages/{locale}.po, sửa bằng Loco Translate). Phải gọi trong
     * hàm (không phải hằng) để __() chạy theo locale đang bật.
     *
     * @return array<string, string>
     */
    private static function strings(): array
    {
        return [
            // Form ở footer
            'newsletter_title' => __('Kết nối cùng sứ mệnh - Tham gia vòng tròn bền vững', 'laca'),
            'newsletter_placeholder' => __('Địa chỉ email của bạn', 'laca'),
            'newsletter_button' => __('Đăng ký', 'laca'),
            'newsletter_consent' => __('Khi đăng ký, bạn đồng ý nhận email từ LIX Roastery. Bạn có thể huỷ đăng ký bất cứ lúc nào.', 'laca'),
            'newsletter_msg_success' => __('Cảm ơn bạn đã đăng ký! Email chào mừng đang được gửi tới hộp thư của bạn.', 'laca'),
            'newsletter_msg_exists' => __('Email này đã được đăng ký.', 'laca'),
            'newsletter_msg_invalid' => __('Vui lòng nhập địa chỉ email hợp lệ.', 'laca'),
            'newsletter_msg_limit' => __('Bạn đã thử quá nhiều lần. Vui lòng thử lại sau vài phút.', 'laca'),
            'newsletter_msg_captcha' => __('Không thể xác minh yêu cầu của bạn. Vui lòng tải lại trang và thử lại.', 'laca'),
            'newsletter_msg_error' => __('Đã có lỗi xảy ra. Vui lòng thử lại.', 'laca'),

            // Email cảm ơn sau khi đăng ký
            'newsletter_mail_subject' => __('Cảm ơn bạn đã đăng ký nhận tin từ LIX Roastery', 'laca'),
            'newsletter_mail_body' => __("Cảm ơn bạn đã tham gia vòng tròn bền vững cùng LIX Roastery!\nTừ bây giờ, bạn sẽ nhận được tin tức, câu chuyện và cập nhật mới nhất từ chúng tôi. Rất vui được đồng hành cùng bạn.", 'laca'),

            // Trang sau khi bấm link huỷ trong email
            'newsletter_page_unsubscribe' => __('Bạn muốn ngừng nhận email từ LIX Roastery?', 'laca'),
            'newsletter_page_unsubscribe_button' => __('Huỷ đăng ký', 'laca'),
            'newsletter_page_unsubscribed' => __('Bạn đã huỷ đăng ký thành công. Rất tiếc khi phải chia tay bạn.', 'laca'),
            'newsletter_page_invalid' => __('Liên kết không hợp lệ hoặc đã hết hạn.', 'laca'),
        ];
    }

    /**
     * Câu mặc định của các bản trước (double opt-in, bản đầu tiên). Carbon
     * Fields lưu cả tab khi bấm Lưu nên option có thể đang chứa nguyên các câu
     * này dù admin chưa từng sửa — coi như chưa nhập để dùng câu mặc định mới.
     */
    private const LEGACY_DEFAULTS = [
        'newsletter_mail_subject' => [
            'Please confirm your subscription to LIX Roastery',
            'Xác nhận đăng ký nhận tin từ LIX Roastery',
        ],
        'newsletter_mail_body' => [
            "Thank you for joining the sustainable circle.\nPlease confirm your email address by clicking the button below. If you did not sign up, you can simply ignore this email.",
            "Cảm ơn bạn đã tham gia vòng tròn bền vững cùng LIX Roastery.\nVui lòng bấm nút bên dưới để xác nhận địa chỉ email. Nếu bạn không đăng ký, hãy bỏ qua email này.",
        ],
        'newsletter_msg_success' => [
            'Thank you for joining the circle!',
            'Thank you for joining the circle! Your subscription is confirmed.',
            'Cảm ơn bạn đã tham gia! Đăng ký của bạn đã được xác nhận.',
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
        static $cache = [];

        $locale = self::locale(self::lang($lang));
        if (isset($cache[$locale])) {
            return $cache[$locale];
        }

        // Email/trang huỷ phải theo ngôn ngữ LÚC KHÁCH ĐĂNG KÝ, không theo
        // locale của request hiện tại → tạm chuyển locale để __() dịch đúng.
        $switched = $locale !== '' && $locale !== determine_locale() && switch_to_locale($locale);
        $cache[$locale] = self::strings();
        if ($switched) {
            restore_previous_locale();
        }

        return $cache[$locale];
    }

    public static function get(string $key, string $lang = ''): string
    {
        $lang = self::lang($lang);
        $default = self::defaults($lang)[$key] ?? '';
        $value = get_option('_' . $key . $lang);

        if (!is_string($value) || trim($value) === '' || in_array(str_replace("\r\n", "\n", $value), self::LEGACY_DEFAULTS[$key] ?? [], true)) {
            return $default;
        }

        // Carbon Fields lưu CẢ tab khi admin bấm lưu: nếu tab của ngôn ngữ này
        // từng được lưu lúc mặc định còn là câu của ngôn ngữ KHÁC, option chứa
        // nguyên câu đó chưa sửa — coi như admin chưa nhập, dùng mặc định đúng
        // ngôn ngữ.
        foreach (self::languages() as $other) {
            if ($other !== $lang && $value === (self::defaults($other)[$key] ?? null)) {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Slug các ngôn ngữ Polylang (rỗng nếu site không đa ngôn ngữ).
     *
     * @return string[]
     */
    private static function languages(): array
    {
        return function_exists('pll_languages_list') ? (array) pll_languages_list(['fields' => 'slug']) : [];
    }

    /**
     * Locale WordPress của 1 ngôn ngữ Polylang (vd 'en' → 'en_US'); không có
     * Polylang thì dùng locale của site.
     */
    private static function locale(string $lang): string
    {
        if ($lang !== '' && function_exists('pll_languages_list')) {
            foreach ((array) pll_languages_list(['fields' => '']) as $language) {
                if (isset($language->slug, $language->locale) && $language->slug === $lang) {
                    return (string) $language->locale;
                }
            }
        }

        return get_locale();
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
