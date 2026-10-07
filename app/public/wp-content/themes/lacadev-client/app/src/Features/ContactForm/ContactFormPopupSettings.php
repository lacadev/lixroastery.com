<?php

namespace App\Features\ContactForm;

/**
 * ContactFormPopupSettings
 *
 * Quản lý cấu hình nội dung + hành vi popup SweetAlert2 (Thành công/Thất
 * bại) VÀ các thông báo lỗi hệ thống của Contact Form (phiên hết hạn, field
 * bắt buộc, sai định dạng, lỗi kỹ thuật, lỗi mạng...).
 *
 * 2 cấp cấu hình dùng CHUNG 1 schema (field giống hệt nhau):
 *   - GLOBAL: lưu ở wp_option `laca_cf_popup_global_settings`, áp dụng mặc
 *     định cho MỌI form.
 *   - PER-FORM: lưu trong `style_settings` JSON của từng form (bảng
 *     ContactFormTable), CHỈ có hiệu lực khi form đó bật cờ `popup_override`
 *     — lúc này ghi đè lên global theo từng key riêng lẻ.
 *
 * Thông báo lỗi hệ thống (msg_*) CHỈ có ở cấp GLOBAL (không hợp lý để mỗi
 * form nói khác nhau về "phiên làm việc hết hạn", đây là chrome hệ thống).
 */
class ContactFormPopupSettings
{
    public const OPTION_KEY = 'laca_cf_popup_global_settings';

    /**
     * Giá trị mặc định — giữ NGUYÊN VĂN các chuỗi tiếng Việt đang hardcode
     * trong ContactFormAjaxHandler trước khi có tính năng này, để không đổi
     * hành vi hiển thị cho site nào chưa từng cấu hình gì.
     */
    public const DEFAULTS = [
        // Trạng thái Thành công
        'popup_success_title' => '✓ Thành công!',
        'popup_success_title_i18n' => [],
        'popup_success_desc' => 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất!',
        'popup_success_desc_i18n' => [],
        'popup_success_icon_mode' => 'default', // default|custom|hidden
        'popup_success_custom_icon' => '',

        // Trạng thái Thất bại
        'popup_error_title' => '✕ Thất bại',
        'popup_error_title_i18n' => [],
        'popup_error_desc' => 'Đã có lỗi xảy ra. Vui lòng thử lại.',
        'popup_error_desc_i18n' => [],
        'popup_error_icon_mode' => 'default',
        'popup_error_custom_icon' => '',

        // Màu sắc/bo góc/CSS popup (giữ nguyên key cũ, nay dùng chung global+override)
        'popup_success_color' => '#28a745',
        'popup_error_color' => '#dc3545',
        'popup_button_color' => '', // rỗng = fallback primary_color của form (xem buildPopupCss())
        'popup_border_radius' => 12,
        'popup_custom_css' => '',

        // Nút đóng — popup_close_mode CHỈ quyết định ô nhập nào hiện trong
        // admin UI (text tự do hay grid icon preset), cả 2 đều ghi cùng 1
        // giá trị vào popup_close_text để render không cần phân nhánh.
        'popup_close_mode' => 'text', // text|icon
        'popup_close_text' => 'Đóng',
        'popup_close_text_i18n' => [],

        // Tự ẩn popup / hiện nút đóng
        'popup_dismiss_mode' => 'button', // button|timer
        'popup_dismiss_seconds' => 3,

        // Thông báo lỗi hệ thống (CHỈ áp dụng ở cấp global)
        'msg_session_expired' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.',
        'msg_session_expired_i18n' => [],
        'msg_invalid_form' => 'Form không hợp lệ.',
        'msg_invalid_form_i18n' => [],
        'msg_form_not_found' => 'Form không tồn tại.',
        'msg_form_not_found_i18n' => [],
        'msg_field_required_suffix' => 'là bắt buộc.',
        'msg_field_required_suffix_i18n' => [],
        'msg_invalid_email_suffix' => 'Địa chỉ email không hợp lệ.',
        'msg_invalid_email_suffix_i18n' => [],
        'msg_invalid_url_suffix' => 'Đường dẫn URL không hợp lệ.',
        'msg_invalid_url_suffix_i18n' => [],
        'msg_invalid_phone_suffix' => 'Số điện thoại không hợp lệ.',
        'msg_invalid_phone_suffix_i18n' => [],
        'msg_technical_error' => 'Thông tin của bạn đã được ghi nhận, nhưng hệ thống đang gặp sự cố kỹ thuật. Vui lòng liên hệ trực tiếp nếu cần gấp.',
        'msg_technical_error_i18n' => [],
        'msg_email_failed' => 'Thông tin của bạn đã được lưu lại, nhưng hệ thống gửi email đang gặp sự cố. Vui lòng liên hệ trực tiếp nếu cần gấp.',
        'msg_email_failed_i18n' => [],
        'msg_network_error' => 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối internet.',
        'msg_network_error_i18n' => [],
    ];

    /** Grid icon preset dùng cho CẢ icon trạng thái lẫn icon nút đóng. */
    public const ICON_PRESET = ['✓', '✕', '⚠', 'ℹ', '★', '♥', '👍', '👎', '🎉', '🔔', '⏰', '→'];

    /** Các key là map {lang: text} — dùng để lặp sanitize + render dịch. */
    private const I18N_KEYS = [
        'popup_success_title_i18n', 'popup_success_desc_i18n',
        'popup_error_title_i18n', 'popup_error_desc_i18n',
        'popup_close_text_i18n',
        'msg_session_expired_i18n', 'msg_invalid_form_i18n', 'msg_form_not_found_i18n',
        'msg_field_required_suffix_i18n', 'msg_invalid_email_suffix_i18n',
        'msg_invalid_url_suffix_i18n', 'msg_invalid_phone_suffix_i18n',
        'msg_technical_error_i18n', 'msg_email_failed_i18n', 'msg_network_error_i18n',
    ];

    /** Key text ngắn (title/button/msg suffix...) — sanitize_text_field. */
    private const TEXT_KEYS = [
        'popup_success_title', 'popup_error_title',
        'popup_success_custom_icon', 'popup_error_custom_icon',
        'popup_close_text',
        'msg_session_expired', 'msg_invalid_form', 'msg_form_not_found',
        'msg_field_required_suffix', 'msg_invalid_email_suffix',
        'msg_invalid_url_suffix', 'msg_invalid_phone_suffix',
    ];

    /** Key mô tả dài hơn, cho phép xuống dòng — sanitize_textarea_field. */
    private const TEXTAREA_KEYS = [
        'popup_success_desc', 'popup_error_desc',
        'msg_technical_error', 'msg_email_failed', 'msg_network_error',
    ];

    private const COLOR_KEYS = ['popup_success_color', 'popup_error_color', 'popup_button_color'];

    private const ENUM_KEYS = [
        'popup_success_icon_mode' => ['default', 'custom', 'hidden'],
        'popup_error_icon_mode' => ['default', 'custom', 'hidden'],
        'popup_close_mode' => ['text', 'icon'],
        'popup_dismiss_mode' => ['button', 'timer'],
    ];

    /**
     * Cài đặt global hiện tại, merge lên DEFAULTS (key nào chưa cấu hình
     * thì dùng mặc định — site mới/chưa từng mở phần cài đặt này vẫn chạy
     * đúng y hệt hành vi hardcode cũ).
     */
    public static function getGlobal(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        return array_merge(self::DEFAULTS, is_array($saved) ? $saved : []);
    }

    public static function saveGlobal(array $raw): array
    {
        $clean = self::sanitize($raw, true);
        update_option(self::OPTION_KEY, $clean);
        return $clean;
    }

    /**
     * Sanitize dữ liệu thô (từ $_POST global panel HOẶC từ mảng đã
     * json_decode của style_json per-form) theo ĐÚNG 1 schema dùng chung.
     *
     * @param bool $withSystemMessages true khi gọi từ global panel (có khối
     *             "Thông báo lỗi hệ thống"); per-form không gửi mấy key này
     *             nên tự bỏ qua nếu $raw không có, không cần cờ riêng, nhưng
     *             để rõ ý định lúc đọc code.
     */
    public static function sanitize(array $raw, bool $withSystemMessages = false): array
    {
        $clean = [];

        foreach (self::TEXT_KEYS as $key) {
            if (isset($raw[$key]) && trim((string) $raw[$key]) !== '') {
                $clean[$key] = sanitize_text_field((string) $raw[$key]);
            }
        }

        foreach (self::TEXTAREA_KEYS as $key) {
            if (isset($raw[$key]) && trim((string) $raw[$key]) !== '') {
                $clean[$key] = sanitize_textarea_field((string) $raw[$key]);
            }
        }

        foreach (self::COLOR_KEYS as $key) {
            if (!empty($raw[$key])) {
                $hex = sanitize_hex_color($raw[$key]);
                if ($hex) {
                    $clean[$key] = $hex;
                }
            }
        }

        foreach (self::ENUM_KEYS as $key => $allowed) {
            if (in_array($raw[$key] ?? '', $allowed, true)) {
                $clean[$key] = $raw[$key];
            }
        }

        if (isset($raw['popup_border_radius'])) {
            $clean['popup_border_radius'] = max(0, min(40, (int) $raw['popup_border_radius']));
        }
        if (isset($raw['popup_dismiss_seconds'])) {
            $clean['popup_dismiss_seconds'] = max(1, min(30, (int) $raw['popup_dismiss_seconds']));
        }
        if (!empty($raw['popup_custom_css'])) {
            $clean['popup_custom_css'] = wp_strip_all_tags(stripslashes((string) $raw['popup_custom_css']));
        }

        foreach (self::I18N_KEYS as $key) {
            if (empty($raw[$key]) || !is_array($raw[$key])) {
                continue;
            }
            $isTextarea = str_starts_with($key, 'popup_success_desc') || str_starts_with($key, 'popup_error_desc')
                || in_array($key, ['msg_technical_error_i18n', 'msg_email_failed_i18n', 'msg_network_error_i18n'], true);
            $cleanMap = [];
            foreach ($raw[$key] as $langSlug => $val) {
                $langSlug = sanitize_key((string) $langSlug);
                $val = $isTextarea ? sanitize_textarea_field((string) $val) : sanitize_text_field((string) $val);
                if ($langSlug && $val !== '') {
                    $cleanMap[$langSlug] = $val;
                }
            }
            if (!empty($cleanMap)) {
                $clean[$key] = $cleanMap;
            }
        }

        if (!$withSystemMessages) {
            // Per-form override không cần/không hiện khối msg_* — bỏ hẳn
            // nếu lỡ có gửi lên, tránh 1 form "khoá" nhầm message hệ thống
            // của toàn site qua style_settings của riêng nó.
            foreach (array_keys($clean) as $key) {
                if (str_starts_with($key, 'msg_')) {
                    unset($clean[$key]);
                }
            }
        }

        return $clean;
    }

    /**
     * Gộp global + override riêng form (nếu có bật) thành 1 mảng cuối dùng
     * để render — CHƯA áp dụng dịch theo ngôn ngữ (xem resolveForLang()).
     *
     * @param array $formStyleSettings style_settings đã json_decode của 1 form
     */
    public static function resolve(array $formStyleSettings): array
    {
        $result = self::getGlobal();

        if (!empty($formStyleSettings['popup_override'])) {
            foreach ($result as $key => $default) {
                if (str_starts_with($key, 'msg_')) {
                    continue; // msg_* luôn theo global, không override theo form
                }
                if (array_key_exists($key, $formStyleSettings)) {
                    $result[$key] = $formStyleSettings[$key];
                }
            }
        }

        return $result;
    }

    /**
     * resolve() + chọn đúng bản dịch theo $lang cho TẤT CẢ cặp key/key_i18n
     * — dùng ngay tại nơi echo ra HTML/JSON response, không phải tự tra
     * cứu lại i18n map ở từng nơi gọi.
     */
    public static function resolveForLang(array $formStyleSettings, string $lang): array
    {
        $resolved = self::resolve($formStyleSettings);
        $lang = sanitize_key($lang);

        foreach (self::I18N_KEYS as $i18nKey) {
            $baseKey = substr($i18nKey, 0, -strlen('_i18n'));
            if ($lang !== '' && !empty($resolved[$i18nKey][$lang])) {
                $resolved[$baseKey] = $resolved[$i18nKey][$lang];
            }
        }

        return $resolved;
    }

    /**
     * HTML "khung" (skeleton) cho khối cấu hình Popup — DÙNG CHUNG cho cả
     * trang danh sách (global) và tab Giao diện của 1 form (per-form), vì 2
     * trang này KHÔNG BAO GIỜ render cùng lúc nên có thể dùng chung HẾT id/
     * tên hàm JS mà không đụng nhau. Theo đúng kiến trúc "dumb shell + JS
     * hydrate" đã có của cả trang edit form: PHP KHÔNG echo giá trị hiện tại
     * (không value="..."), mọi giá trị do JS tự đọc từ state (styles hoặc
     * popupDefaults, tuỳ ngữ cảnh — xem contact-form.js) lúc init.
     */
    public static function renderFieldsHtml(): string
    {
        ob_start();
        ?>
        <div class="laca-cf-field-group">
            <label class="lcf-form-label">Màu Thành công</label>
            <div class="lcf-color-row">
                <input type="color" id="popup-success-color" oninput="lcfStyleUpdate('popup_success_color',this.value)">
                <input type="text" class="lcf-color-text" id="popup-success-color-text" maxlength="7"
                       oninput="lcfStyleUpdate('popup_success_color',this.value);document.getElementById('popup-success-color').value=this.value">
            </div>
        </div>
        <div class="laca-cf-field-group">
            <label class="lcf-form-label">Màu Thất bại</label>
            <div class="lcf-color-row">
                <input type="color" id="popup-error-color" oninput="lcfStyleUpdate('popup_error_color',this.value)">
                <input type="text" class="lcf-color-text" id="popup-error-color-text" maxlength="7"
                       oninput="lcfStyleUpdate('popup_error_color',this.value);document.getElementById('popup-error-color').value=this.value">
            </div>
        </div>
        <div class="laca-cf-field-group">
            <label class="lcf-form-label">Màu nút trong Popup <small style="font-weight:400">(để trống = dùng Màu chính)</small></label>
            <div class="lcf-color-row">
                <input type="color" id="popup-button-color" oninput="lcfStyleUpdate('popup_button_color',this.value)">
                <input type="text" class="lcf-color-text" id="popup-button-color-text" maxlength="7"
                       oninput="lcfStyleUpdate('popup_button_color',this.value);document.getElementById('popup-button-color').value=this.value">
            </div>
        </div>
        <div class="laca-cf-field-group">
            <label class="lcf-form-label">Bo góc Popup (px)</label>
            <div class="lcf-range-row">
                <input type="range" min="0" max="40" id="popup-radius"
                       oninput="lcfStyleUpdate('popup_border_radius',this.value);document.getElementById('popup-radius-num').value=this.value">
                <input type="number" min="0" max="40" id="popup-radius-num" class="lcf-range-num"
                       oninput="lcfStyleUpdate('popup_border_radius',this.value);document.getElementById('popup-radius').value=this.value">
                <span class="lcf-range-unit">px</span>
            </div>
        </div>
        <div class="laca-cf-field-group" style="grid-column:1/-1">
            <label class="lcf-form-label">Custom CSS riêng cho Popup</label>
            <textarea class="widefat laca-cf-email-body" id="popup-custom-css" rows="4"
                      oninput="lcfStyleUpdate('popup_custom_css',this.value)"
                      placeholder="/* Dùng __POPUP__ để ám chỉ class popup (ví dụ: __POPUP__ .swal2-title { font-size:22px } */"></textarea>
        </div>

        <?php foreach (['success' => 'Thành công', 'error' => 'Thất bại'] as $state => $stateLabel): ?>
            <div class="laca-cf-field-group" style="grid-column:1/-1">
                <h4 class="lcf-email-section-title" style="margin:14px 0 4px">Nội dung popup "<?php echo esc_html($stateLabel); ?>"</h4>
            </div>
            <div class="laca-cf-field-group">
                <label class="lcf-form-label">Tiêu đề</label>
                <input type="text" class="widefat" id="popup-<?php echo esc_attr($state); ?>-title"
                       oninput="lcfPopupFieldUpdate('popup_<?php echo esc_attr($state); ?>_title',this.value)">
                <div id="popup_<?php echo esc_attr($state); ?>_title-i18n-container"></div>
            </div>
            <div class="laca-cf-field-group">
                <label class="lcf-form-label">Mô tả</label>
                <textarea class="widefat" id="popup-<?php echo esc_attr($state); ?>-desc" rows="2"
                          oninput="lcfPopupFieldUpdate('popup_<?php echo esc_attr($state); ?>_desc',this.value)"></textarea>
                <div id="popup_<?php echo esc_attr($state); ?>_desc-i18n-container"></div>
            </div>
            <div class="laca-cf-field-group">
                <label class="lcf-form-label">Icon</label>
                <select class="widefat" id="popup-<?php echo esc_attr($state); ?>-icon-mode"
                        onchange="lcfPopupFieldUpdate('popup_<?php echo esc_attr($state); ?>_icon_mode',this.value)">
                    <option value="default">Mặc định (✓/✕)</option>
                    <option value="custom">Icon tuỳ chỉnh</option>
                    <option value="hidden">Ẩn hoàn toàn</option>
                </select>
                <div class="lcf-icon-pick-wrap" id="popup-<?php echo esc_attr($state); ?>-custom-icon-wrap" hidden>
                    <div class="lcf-icon-pick-grid" id="popup_<?php echo esc_attr($state); ?>_custom_icon-icon-grid"></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="laca-cf-field-group" style="grid-column:1/-1">
            <h4 class="lcf-email-section-title" style="margin:14px 0 4px">Nút đóng &amp; tự ẩn</h4>
        </div>
        <div class="laca-cf-field-group">
            <label class="lcf-form-label">Nút đóng</label>
            <select class="widefat" id="popup-close-mode" onchange="lcfPopupFieldUpdate('popup_close_mode',this.value)">
                <option value="text">Nhập văn bản</option>
                <option value="icon">Chọn icon</option>
            </select>
            <div id="popup-close-text-wrap">
                <input type="text" class="widefat" id="popup-close-text"
                       oninput="lcfPopupFieldUpdate('popup_close_text',this.value)" style="margin-top:6px">
                <div id="popup_close_text-i18n-container"></div>
            </div>
            <div class="lcf-icon-pick-wrap" id="popup-close-icon-wrap" hidden>
                <div class="lcf-icon-pick-grid" id="popup_close_text-icon-grid"></div>
            </div>
        </div>
        <div class="laca-cf-field-group">
            <label class="lcf-form-label">Tự ẩn popup</label>
            <select class="widefat" id="popup-dismiss-mode" onchange="lcfPopupFieldUpdate('popup_dismiss_mode',this.value)">
                <option value="button">Hiện nút đóng, không tự ẩn</option>
                <option value="timer">Tự ẩn sau (giây)</option>
            </select>
            <div id="popup-dismiss-seconds-wrap" hidden style="margin-top:6px">
                <input type="number" class="widefat" id="popup-dismiss-seconds" min="1" max="30"
                       oninput="lcfPopupFieldUpdate('popup_dismiss_seconds',this.value)">
            </div>
        </div>

        <div class="laca-cf-field-group" style="grid-column:1/-1">
            <button type="button" class="button" onclick="lcfPopupPreview('success')">👁 Xem trước — Thành công</button>
            <button type="button" class="button" onclick="lcfPopupPreview('error')" style="margin-left:8px">👁 Xem trước — Thất bại</button>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * HTML "khung" khối "Thông báo lỗi hệ thống" — CHỈ xuất hiện ở panel
     * cài đặt GLOBAL (trang danh sách form), không có ở per-form vì các
     * thông báo này là chrome hệ thống dùng chung, không override theo form.
     */
    public static function renderSystemMessagesHtml(): string
    {
        $messages = [
            'msg_session_expired' => 'Phiên làm việc hết hạn',
            'msg_invalid_form' => 'Form không hợp lệ',
            'msg_form_not_found' => 'Form không tồn tại',
            'msg_field_required_suffix' => 'Field bắt buộc (nối sau tên field, vd "Email là bắt buộc.")',
            'msg_invalid_email_suffix' => 'Email sai định dạng',
            'msg_invalid_url_suffix' => 'URL sai định dạng',
            'msg_invalid_phone_suffix' => 'Số điện thoại sai định dạng',
            'msg_technical_error' => 'Lỗi kỹ thuật khi lưu/gửi email (dữ liệu vẫn đã lưu)',
            'msg_email_failed' => 'Lỗi gửi email xác nhận (dữ liệu vẫn đã lưu)',
            'msg_network_error' => 'Mất kết nối mạng lúc gửi form (lỗi phía trình duyệt khách)',
        ];

        ob_start();
        ?>
        <div class="laca-cf-field-group" style="grid-column:1/-1">
            <h3 class="lcf-email-section-title" style="margin:18px 0 4px">⚠️ Thông báo lỗi hệ thống</h3>
            <p class="lcf-form-help" style="margin:0 0 10px">Áp dụng cho MỌI form — không override theo từng form riêng.</p>
        </div>
        <?php foreach ($messages as $key => $desc): ?>
            <div class="laca-cf-field-group" style="grid-column:1/-1">
                <label class="lcf-form-label"><?php echo esc_html($desc); ?></label>
                <input type="text" class="widefat" id="<?php echo esc_attr($key); ?>"
                       oninput="lcfPopupFieldUpdate('<?php echo esc_attr($key); ?>',this.value)">
                <div id="<?php echo esc_attr($key); ?>-i18n-container"></div>
            </div>
        <?php endforeach; ?>
        <?php
        return (string) ob_get_clean();
    }
}
