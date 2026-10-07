<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * ContactFormAjaxHandler
 *
 * Xử lý frontend AJAX submission và đăng ký shortcode.
 *
 * Shortcode: [laca_contact_form id="X"]
 *   → Render HTML form và JS validation (Pristine.js)
 *
 * AJAX endpoint: wp_ajax_nopriv_laca_cf_submit (cả logged-in lẫn guest)
 *   → Validate → Lưu DB → Gửi email → Trả JSON
 *
 * ⚠️ Tên action "laca_cf_submit" (KHÔNG phải "laca_contact_submit") — lỗi
 * thật đã gặp: action "laca_contact_submit" bị TRÙNG với 1 handler legacy
 * khác (lacadev_handle_contact_submit(), app/helpers/ajax.php, phục vụ
 * template-contact.php cũ) cùng đăng ký trên CÙNG tên action. WordPress
 * chạy MỌI callback đã đăng ký cho 1 action — handler legacy chạy trước,
 * tự check_ajax_referer('laca_contact_nonce', 'nonce') (field "nonce",
 * KHÁC field "_nonce" form này gửi lên) nên luôn fail và tự wp_die(-1, 403)
 * NGAY LẬP TỨC, khiến handleSubmit() ở đây không bao giờ chạy tới — mọi
 * submit qua shortcode [laca_contact_form] luôn báo "Thất bại" dù dữ liệu
 * hợp lệ. Đổi sang tên action riêng để không bao giờ đụng hàng nữa.
 */
class ContactFormAjaxHandler
{
    public function init(): void
    {
        add_action('wp_ajax_laca_cf_submit',        [$this, 'handleSubmit']);
        add_action('wp_ajax_nopriv_laca_cf_submit', [$this, 'handleSubmit']);
        add_shortcode('laca_contact_form', [$this, 'renderShortcode']);
    }

    // =========================================================================
    // AJAX SUBMIT HANDLER
    // =========================================================================

    public function handleSubmit(): void
    {
        // $lang cần có TRƯỚC cả bước check nonce/form — client luôn gửi kèm
        // "_lang" bất kể nonce còn hạn hay không, để 2 lỗi sớm nhất (phiên
        // hết hạn, form không hợp lệ) vẫn hiện đúng ngôn ngữ trang.
        $lang = sanitize_key($_POST['_lang'] ?? (function_exists('pll_current_language') ? (pll_current_language() ?: '') : ''));
        // Chưa biết form nào (hoặc form không tồn tại) nên chỉ dùng được
        // cài đặt GLOBAL — không có style_settings riêng để override.
        $earlyPopup = ContactFormPopupSettings::resolveForLang([], $lang);

        // 1. Nonce check
        if (!check_ajax_referer('laca_contact_submit_nonce', '_nonce', false)) {
            wp_send_json_error(['message' => $earlyPopup['msg_session_expired']], 403);
        }

        // 2. Form ID
        $formId = absint($_POST['form_id'] ?? 0);
        if (!$formId) {
            wp_send_json_error(['message' => $earlyPopup['msg_invalid_form']], 400);
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form) {
            wp_send_json_error(['message' => $earlyPopup['msg_form_not_found']], 404);
        }

        $fields = self::extractFlatFields($form);

        $styleSettings = json_decode($form['style_settings'] ?? '{}', true) ?: [];
        $popup         = ContactFormPopupSettings::resolveForLang($styleSettings, $lang);

        // 3. Validate & Sanitize từng field
        $data   = [];
        $errors = [];

        foreach ($fields as $field) {
            // Áp bản dịch theo ngôn ngữ Polylang của khách đang xem — để
            // thông báo lỗi ("$label là bắt buộc.") và fallback nhãn "Khác"
            // hiện đúng ngôn ngữ khách nhìn thấy trên form, không phải luôn
            // luôn ngôn ngữ mặc định.
            $field    = self::applyFieldTranslation($field, $lang);
            $name     = $field['name'];
            $label    = $field['label'];
            $required = !empty($field['required']);
            $type     = $field['type'];

            // Lấy giá trị raw từ POST — BẮT BUỘC wp_unslash() trước khi
            // dùng: WordPress tự động addslashes() MỌI giá trị $_POST (mô
            // phỏng magic_quotes cũ), sanitize_text_field()/sanitize_
            // textarea_field() KHÔNG tự strip slashes này. Thiếu bước này
            // là lý do thật đã gặp: khách gõ "I don't" nhưng email admin/
            // khách nhận được lại hiện "I don\'t" (backslash thừa).
            $rawValue = wp_unslash($_POST[$name] ?? '');

            // checkbox có 3 dạng: 1 ô đơn (options rỗng/1 phần tử — gửi lên
            // dạng STRING khi tick, KHÔNG có key khi bỏ tick, name không có
            // "[]"), nhóm "chỉ chọn 1" (single_choice — cũng gửi STRING vì
            // name trùng nhau không có "[]", JS tự bỏ tick ô khác), hoặc
            // nhóm nhiều lựa chọn mặc định (name="...[]", luôn gửi array).
            // Trước đây ép CẢ 2 dạng đầu thành array nên luôn bị coi là rỗng
            // dù đã tick (bug) — chỉ ép array cho multiselect và checkbox
            // NHÓM nhiều lựa chọn, không áp dụng cho 2 dạng single ở trên.
            $isSingleCheckbox = $type === 'checkbox' && count($field['options'] ?? []) <= 1;
            $isSingleChoiceGroup = $type === 'checkbox' && !empty($field['single_choice']) && count($field['options'] ?? []) > 1;
            if ($type === 'multiselect' || ($type === 'checkbox' && !$isSingleCheckbox && !$isSingleChoiceGroup)) {
                $rawValue = is_array($rawValue) ? $rawValue : [];
            }

            // Validate required
            if ($required) {
                $isEmpty = is_array($rawValue) ? empty($rawValue) : (trim((string) $rawValue) === '');
                if ($isEmpty) {
                    $errors[] = $label . ' ' . $popup['msg_field_required_suffix'];
                    continue;
                }
            }

            // Sanitize theo type
            $cleanValue = self::sanitizeByType($type, $rawValue, $field);

            // Validate format
            $formatError = self::validateFormat($type, $cleanValue, $label, $popup);
            if ($formatError) {
                $errors[] = $formatError;
                continue;
            }

            $data[$name] = $cleanValue;

            // Option "Khác" (has_other) — lấy thêm giá trị tự do người dùng
            // nhập ở ô nhập kèm theo, rồi thay sentinel "__other__" bằng nội
            // dung thật để hiện đúng trong email/CSV thay vì hiện chữ thô.
            if (!empty($field['has_other']) && in_array($type, ['checkbox', 'radio'], true)) {
                $otherRaw = sanitize_text_field(wp_unslash($_POST[$name . '_other'] ?? ''));
                $data[$name . '_other'] = $otherRaw;

                $otherText = $otherRaw !== '' ? $otherRaw : ($field['other_label'] ?: __('Khác', 'laca'));
                if (is_array($cleanValue)) {
                    $data[$name] = array_map(
                        fn($v) => $v === '__other__' ? $otherText : $v,
                        $cleanValue
                    );
                } elseif ($cleanValue === '__other__') {
                    $data[$name] = $otherText;
                }
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(['message' => implode('<br>', $errors), 'errors' => $errors], 422);
        }

        // 3.5. Verify reCAPTCHA
        $isRecaptchaEnabled = function_exists('getOption') ? getOption('enable_recaptcha_contact') : false;
        if ($isRecaptchaEnabled) {
            $token  = $_POST['laca_recaptcha_response'] ?? '';
            $verify = apply_filters('laca_verify_recaptcha', true, $token);
            if (is_wp_error($verify)) {
                wp_send_json_error(['message' => $verify->get_error_message()], 400);
            }
        }

        // 4. Lấy IP
        $ip = self::getClientIp();

        // 5+6. Lưu DB rồi gửi email — bọc try/catch vì đây là 2 thao tác duy
        // nhất chạm ra ngoài PHP (DB, SMTP): 1 exception không bắt ở đây (vd
        // PHPMailer lỗi, DB mất kết nối tạm thời) sẽ làm hỏng toàn bộ response
        // JSON (PHP in thêm "Fatal error:..." dạng HTML trước phần JSON),
        // khiến fetch().then(res=>res.json()) ở trình duyệt ném lỗi parse và
        // rơi vào nhánh .catch() chung chung "Lỗi kết nối" — trong khi lỗi
        // thật có thể chỉ là gửi email thất bại, dữ liệu vẫn cần được lưu.
        try {
            // Dữ liệu luôn được lưu TRƯỚC, không phụ thuộc email gửi được hay
            // không (tránh mất submission chỉ vì SMTP lỗi tạm thời).
            ContactFormTable::insertSubmission($formId, $data, $ip);

            // Trả về đúng trạng thái THẬT (trước đây luôn báo "thành công" dù
            // wp_mail() lỗi, người gửi không biết tin nhắn có tới nơi hay
            // không). Dữ liệu đã lưu DB nên dù email lỗi, admin vẫn xem được
            // submission trong màn hình quản trị — chỉ cảnh báo người gửi để
            // họ có thể liên hệ lại qua kênh khác nếu cần.
            $emailSent = ContactFormEmailService::sendAll($form, $data, $ip, $lang);
        } catch (\Throwable $e) {
            error_log('[ContactForm] handleSubmit() lỗi khi lưu DB/gửi email: ' . $e->getMessage() . ' tại ' . $e->getFile() . ':' . $e->getLine());
            wp_send_json_error([
                'message' => $popup['msg_technical_error'],
            ], 500);
        }

        if ($emailSent) {
            wp_send_json_success(['message' => $popup['popup_success_desc']]);
        }

        wp_send_json_error([
            'message' => $popup['msg_email_failed'],
        ], 500);
    }

    // =========================================================================
    // SHORTCODE RENDERER
    // =========================================================================

    public function renderShortcode(array $atts): string
    {
        $atts   = shortcode_atts(['id' => 0, 'class' => ''], $atts, 'laca_contact_form');
        $formId = absint($atts['id']);

        if (!$formId) {
            return '<p class="laca-cf-error">Thiếu ID form. Dùng: [laca_contact_form id="X"]</p>';
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form || !$form['is_active']) {
            return '<p class="laca-cf-error">Form không tồn tại hoặc đã bị tắt.</p>';
        }

        $rawData    = json_decode($form['fields'] ?? '[]', true) ?: [];
        $isRowBased = !empty($rawData) && isset($rawData[0]['cols']);
        $nonce      = wp_create_nonce('laca_contact_submit_nonce');
        $ajaxUrl    = admin_url('admin-ajax.php');
        $extraClass = sanitize_html_class($atts['class']);
        $formElId   = 'laca-cf-form-' . $formId;
        $wrapId     = 'laca-cf-' . $formId;

        // Build scoped CSS vars từ style_settings
        $styleSettings = json_decode($form['style_settings'] ?? '{}', true) ?: [];
        $currentLang   = function_exists('pll_current_language') ? (pll_current_language() ?: '') : '';
        // resolve(): gộp cài đặt GLOBAL (laca_cf_popup_global_settings) với
        // override riêng form này (nếu style_settings.popup_override bật),
        // rồi chọn đúng bản dịch theo $currentLang cho MỌI chuỗi popup/
        // thông báo hệ thống — thay thế toàn bộ string hardcode tiếng Việt
        // trước đây (xem ContactFormPopupSettings).
        $popup         = ContactFormPopupSettings::resolveForLang($styleSettings, $currentLang);
        // buildPopupCss() cần primary_color (CHỈ có ở $styleSettings per-form,
        // KHÔNG thuộc schema popup chung) để fallback màu nút khi admin để
        // trống popup_button_color — giữ đúng hành vi cũ.
        $popupCssSource = $popup + ['primary_color' => $styleSettings['primary_color'] ?? ''];
        $scopedCss     = self::buildScopedCss($wrapId, $styleSettings)
            . self::buildPopupCss('laca-cf-swal-' . $formId, $popupCssSource);

        // Enqueue inline CSS once
        if (!wp_style_is('laca-contact-form', 'done')) {
            add_action('wp_footer', [__CLASS__, 'printInlineCss'], 5);
        }

        ob_start();
        ?>
        <?php if ($scopedCss): ?>
        <style><?php echo $scopedCss; // Đã sanitize qua esc_attr trên từng giá trị ?></style>
        <?php endif; ?>
        <div class="laca-contact-form-wrap <?php echo esc_attr($extraClass); ?>" id="<?php echo esc_attr($wrapId); ?>">
            <form class="laca-contact-form" id="<?php echo esc_attr($formElId); ?>" novalidate>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                <input type="hidden" name="action" value="laca_cf_submit">
                <input type="hidden" name="_lang" value="<?php echo esc_attr(function_exists('pll_current_language') ? (pll_current_language() ?: '') : ''); ?>">
                <?php if (function_exists('getOption') && getOption('enable_recaptcha_contact')): ?>
                    <input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" value="">
                <?php endif; ?>

                <?php if ($isRowBased): ?>
                    <?php foreach ($rawData as $row): ?>
                        <?php
                        // Skip rows that have no fields at all
                        $hasAnyField = false;
                        foreach ($row['cols'] as $col) {
                            if (!empty($col['fields'])) { $hasAnyField = true; break; }
                        }
                        if (!$hasAnyField) continue;

                        // Build CSS grid-template-columns from col spans
                        $gridCols = implode(' ', array_map(
                            fn($c) => $c['span'] . 'fr',
                            $row['cols']
                        ));
                        ?>
                        <div class="laca-cf-layout-row" style="display:grid;grid-template-columns:<?php echo esc_attr($gridCols); ?>;gap:12px;align-items:start">
                            <?php foreach ($row['cols'] as $col): ?>
                                <?php if (!empty($col['fields'])): ?>
                                    <div class="laca-cf-col-group" style="display:flex;flex-direction:column;gap:12px">
                                        <?php foreach ($col['fields'] as $field): ?>
                                            <?php $this->renderField($field); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($rawData as $field): ?>
                        <?php $this->renderField($field); ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php
                // Chữ nút Submit: ưu tiên bản dịch theo ngôn ngữ Polylang
                // hiện tại (style_settings.btn_text_i18n[lang], nhập ở tab
                // "Trường" — xem contact-form.js buildBtnTextI18nBlock()),
                // sau đó tới giá trị admin đã nhập (style_settings.btn_text —
                // TRƯỚC ĐÂY bị bỏ qua, luôn hiện cứng "Gửi thông tin" dù
                // admin đã đổi), cuối cùng mới tới chữ mặc định.
                $btnText = '';
                if (function_exists('pll_current_language')) {
                    $lang = pll_current_language();
                    if ($lang) {
                        $btnText = (string) ($styleSettings['btn_text_i18n'][$lang] ?? '');
                    }
                }
                if ($btnText === '') {
                    $btnText = (string) ($styleSettings['btn_text'] ?? '');
                }
                if ($btnText === '') {
                    $btnText = __('Gửi thông tin', 'laca');
                }
                ?>
                <div class="laca-cf-form-row laca-cf-submit-row">
                    <button type="submit" class="laca-cf-submit-btn" aria-busy="false">
                        <span class="laca-cf-btn-text"><?php echo esc_html($btnText); ?></span>
                        <span class="laca-cf-btn-loading" hidden aria-hidden="true">
                            <svg class="laca-cf-spinner" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="31.4"/>
                            </svg>
                            <?php echo esc_html($popup['msg_submitting_text']); ?>
                        </span>
                    </button>
                </div>
                <p class="laca-cf-fallback-msg" role="status" aria-live="polite" hidden></p>
            </form>
        </div>

        <?php
        // CSP (theme/setup/security.php) chặn MỌI inline <script> không có
        // đúng nonce của request — script này echo trực tiếp qua ob_start()
        // nên KHÔNG tự động được gắn nonce như wp_enqueue_script()
        // (script_loader_tag) hay wp_add_inline_script() (wp_inline_script_
        // attributes) đã xử lý sẵn. Thiếu dòng này, trình duyệt câm lặng
        // chặn toàn bộ script (console báo "violates Content Security
        // Policy"), JS không bao giờ gắn được submit handler, nên form rơi
        // về submit thường của trình duyệt (tải lại trang) — đúng lỗi thật
        // đã gặp trên production.
        $cspNonceAttr = defined('LACA_CSP_NONCE') ? ' nonce="' . esc_attr(LACA_CSP_NONCE) . '"' : '';

        // Build sẵn phần "khung" (title/icon/nút đóng/tự ẩn) cho popup
        // Thành công & Thất bại — PHẦN NỘI DUNG ĐỘNG (message cụ thể từ JSON
        // response, hoặc lỗi mạng) được JS merge đè lên lúc showSwal().
        $buildPopupBase = static function (string $state) use ($popup): array {
            $opts = [
                'title' => $popup['popup_' . $state . '_title'],
                'confirmButtonText' => $popup['popup_close_text'],
            ];
            $iconMode = $popup['popup_' . $state . '_icon_mode'] ?? 'default';
            $customIcon = (string) ($popup['popup_' . $state . '_custom_icon'] ?? '');
            if ($iconMode === 'hidden') {
                // Không set 'icon' — Swal không vẽ icon/vòng tròn nào.
            } elseif ($iconMode === 'custom' && $customIcon !== '') {
                $opts['icon'] = $state;
                $opts['iconHtml'] = '<span style="font-size:3.75em;line-height:1">' . esc_html($customIcon) . '</span>';
            } else {
                $opts['icon'] = $state;
            }
            if (($popup['popup_dismiss_mode'] ?? 'button') === 'timer') {
                $opts['timer'] = max(1, (int) ($popup['popup_dismiss_seconds'] ?? 3)) * 1000;
                $opts['timerProgressBar'] = true;
                $opts['showConfirmButton'] = false;
            }
            return $opts;
        };
        $popupSuccessBase = $buildPopupBase('success');
        $popupErrorBase   = $buildPopupBase('error');
        ?>
        <script<?php echo $cspNonceAttr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        (function() {
            const FORM_ID  = '<?php echo esc_js($formElId); ?>';
            const AJAX_URL = '<?php echo esc_js($ajaxUrl); ?>';
            const SWAL_CLASS = '<?php echo esc_js('laca-cf-swal-' . $formId); ?>';
            const POPUP_SUCCESS_BASE = <?php echo wp_json_encode($popupSuccessBase); ?>;
            const POPUP_ERROR_BASE   = <?php echo wp_json_encode($popupErrorBase); ?>;
            const POPUP_SUCCESS_DESC = <?php echo wp_json_encode($popup['popup_success_desc']); ?>;
            const POPUP_ERROR_DESC   = <?php echo wp_json_encode($popup['popup_error_desc']); ?>;
            const MSG_NETWORK_ERROR  = <?php echo wp_json_encode($popup['msg_network_error']); ?>;
            const MSG_FIELD_REQUIRED = <?php echo wp_json_encode(__('Trường này', 'laca') . ' ' . $popup['msg_field_required_suffix']); ?>;
            const MSG_INVALID_EMAIL  = <?php echo wp_json_encode($popup['msg_invalid_email_suffix']); ?>;
            const MSG_INVALID_PHONE  = <?php echo wp_json_encode($popup['msg_invalid_phone_suffix']); ?>;

            // Wait for DOM + theme.js to expose window.Swal
            function boot() {
                const formEl = document.getElementById(FORM_ID);
                if (!formEl) return;

                // ── Helpers ──────────────────────────────────────────────────

                const getThemeColors = () => ({
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                    color:      document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff'    : '#000',
                });

                const showSwal = (opts) => {
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({
                            ...opts,
                            ...getThemeColors(),
                            customClass: { popup: SWAL_CLASS, ...(opts.customClass || {}) },
                        });
                    } else {
                        // Fallback khi Swal chưa load (SSR/cache edge cases)
                        if (opts.icon === 'success') {
                            const banner = formEl.querySelector('.laca-cf-fallback-msg');
                            if (banner) {
                                banner.className = 'laca-cf-fallback-msg laca-cf-fallback-msg--success';
                                banner.textContent = opts.text || opts.title || 'Gửi thành công!';
                                banner.hidden = false;
                            }
                        } else {
                            alert((opts.title ? opts.title + '\n' : '') + (opts.text || ''));
                        }
                    }
                };

                // Show inline error dưới field
                const showFieldError = (fieldEl, message) => {
                    if (!fieldEl) return;
                    fieldEl.classList.add('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'true');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const errEl = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (errEl) { errEl.textContent = message; errEl.hidden = false; }
                };

                const clearFieldError = (fieldEl) => {
                    if (!fieldEl) return;
                    fieldEl.classList.remove('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'false');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const errEl = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (errEl) { errEl.textContent = ''; errEl.hidden = true; }
                };

                const clearAllErrors = () => {
                    formEl.querySelectorAll('.laca-cf-field-invalid').forEach(clearFieldError);
                };

                // ── Client-side validation ────────────────────────────────────

                const validateForm = () => {
                    clearAllErrors();
                    let valid = true;

                    formEl.querySelectorAll('[data-required="true"]').forEach(function(el) {
                        let isEmpty;
                        if (el.type === 'checkbox' || el.type === 'radio') {
                            isEmpty = !formEl.querySelector('[name="' + el.name + '"]:checked');
                        } else {
                            isEmpty = !el.value.trim();
                        }
                        if (isEmpty) {
                            showFieldError(el, MSG_FIELD_REQUIRED);
                            valid = false;
                        }
                    });

                    // Email format check
                    const emailEl = formEl.querySelector('input[type="email"]');
                    if (emailEl && emailEl.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
                        showFieldError(emailEl, MSG_INVALID_EMAIL);
                        valid = false;
                    }

                    // Phone format check (Vietnam)
                    const phoneEl = formEl.querySelector('input[type="tel"]');
                    if (phoneEl && phoneEl.value.trim() && !/^[0-9\s\+\-\(\)]{8,20}$/.test(phoneEl.value.trim())) {
                        showFieldError(phoneEl, MSG_INVALID_PHONE);
                        valid = false;
                    }

                    return valid;
                };

                // ── Real-time clear errors on input ───────────────────────────

                formEl.querySelectorAll('input, select, textarea').forEach(function(el) {
                    el.addEventListener('input', function() { clearFieldError(el); });
                    el.addEventListener('blur', function() {
                        if (el.getAttribute('data-required') === 'true' && !el.value.trim()) {
                            showFieldError(el, MSG_FIELD_REQUIRED);
                        } else {
                            clearFieldError(el);
                        }
                    });
                });

                // ── "Khác" (has_other): hiện/ẩn ô nhập chi tiết theo lựa chọn ──
                // Lắng nghe change trên cả form (không chỉ riêng ô toggle) vì
                // với radio, bấm 1 lựa chọn KHÁC (không phải "Khác") cũng phải
                // ẩn ô nhập lại — event delegation xử lý đúng cả 2 hướng.
                const syncOtherToggles = function() {
                    formEl.querySelectorAll('.laca-cf-other-toggle').forEach(function(toggle) {
                        const target = document.getElementById(toggle.dataset.otherTarget || '');
                        if (!target) return;
                        if (toggle.checked) {
                            target.style.display = '';
                        } else {
                            target.style.display = 'none';
                            target.value = '';
                        }
                    });
                };
                formEl.addEventListener('change', syncOtherToggles);
                syncOtherToggles();

                // ── Checkbox "chỉ chọn 1" (laca-cf-checkbox-group--single) ──
                // Vẫn là <input type="checkbox"> (style/markup không đổi) nhưng
                // hành vi chọn-1-trong-nhóm giống radio — tick 1 ô thì tự bỏ
                // tick các ô CÒN LẠI cùng nhóm (name trùng nhau, không có "[]"
                // nên trình duyệt submit đúng 1 giá trị).
                formEl.addEventListener('change', function(e) {
                    const target = e.target;
                    if (!target.matches('input[type="checkbox"]')) return;
                    const group = target.closest('.laca-cf-checkbox-group--single');
                    if (!group || !target.checked) return;
                    group.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        if (cb !== target) cb.checked = false;
                    });
                    // Bỏ tick lập trình (set .checked) không tự bắn "change"
                    // nên nếu vừa bỏ tick ô "Khác" ở trên, gọi lại thủ công để
                    // ẩn ngay ô nhập chi tiết thay vì đợi lần change kế tiếp.
                    syncOtherToggles();
                });

                // ── Khoá nút Submit cho tới khi tick hết checkbox đơn bắt buộc ──
                // (vd "Đồng ý điều khoản") — chỉ áp dụng cho checkbox ĐƠN (name
                // không có "[]"), không áp dụng cho nhóm nhiều lựa chọn vì
                // "bắt buộc" ở nhóm nghĩa là "chọn ít nhất 1 option", không phải
                // "phải tick 1 ô cụ thể". Khai báo syncSubmitLock() ở scope
                // ngoài để submit handler bên dưới gọi lại được sau khi
                // formEl.reset() (reset không tự bắn "change") — mặc định
                // (không có checkbox bắt buộc nào) phải LUÔN mở khoá, không
                // được để no-op, vì loading-state của submit handler có set
                // disabled=true tạm thời bất kể form có checkbox hay không.
                let syncSubmitLock = function() {
                    const btn2 = formEl.querySelector('.laca-cf-submit-btn');
                    if (btn2) btn2.disabled = false;
                };
                const requiredSingleCheckboxes = Array.prototype.slice
                    .call(formEl.querySelectorAll('input[type="checkbox"][required]'))
                    .filter(function(cb) { return cb.name.indexOf('[') === -1; });

                if (requiredSingleCheckboxes.length) {
                    const submitBtnEl = formEl.querySelector('.laca-cf-submit-btn');
                    syncSubmitLock = function() {
                        const allChecked = requiredSingleCheckboxes.every(function(cb) { return cb.checked; });
                        if (submitBtnEl) {
                            submitBtnEl.disabled = !allChecked;
                            submitBtnEl.classList.toggle('is-locked', !allChecked);
                        }
                    };
                    requiredSingleCheckboxes.forEach(function(cb) {
                        cb.addEventListener('change', syncSubmitLock);
                    });
                    syncSubmitLock();
                }

                // ── Submit handler ────────────────────────────────────────────

                formEl.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (!validateForm()) return;

                    const btn     = formEl.querySelector('.laca-cf-submit-btn');
                    const btnText = btn.querySelector('.laca-cf-btn-text');
                    const btnLoad = btn.querySelector('.laca-cf-btn-loading');

                    // Loading state
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    btnText.hidden = true;
                    btnLoad.hidden = false;

                    fetch(AJAX_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(formEl),
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(json) {
                        if (json.success) {
                            showSwal(Object.assign({}, POPUP_SUCCESS_BASE, {
                                text: json.data.message || POPUP_SUCCESS_DESC,
                            }));
                            formEl.reset();
                            clearAllErrors();
                            syncSubmitLock(); // reset() không tự bắn "change" trên checkbox
                        } else {
                            const msg = (json.data && json.data.message)
                                ? json.data.message
                                : POPUP_ERROR_DESC;
                            showSwal(Object.assign({}, POPUP_ERROR_BASE, {
                                html: '<p>' + msg + '</p>',
                            }));
                        }
                    })
                    .catch(function() {
                        showSwal(Object.assign({}, POPUP_ERROR_BASE, {
                            text: MSG_NETWORK_ERROR,
                        }));
                    })
                    .finally(function() {
                        btn.setAttribute('aria-busy', 'false');
                        btnText.hidden = false;
                        btnLoad.hidden = true;
                        syncSubmitLock(); // trả lại đúng trạng thái khoá thay vì luôn mở khoá cứng
                    });
                });
            }

            // Boot sau khi DOM ready — Swal sẽ available vì theme.js chạy trước
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // RENDER FIELD HELPERS
    // =========================================================================

    /**
     * Áp bản dịch theo ngôn ngữ Polylang hiện tại lên field (nếu form có cấu
     * hình field['i18n'][lang]) — dùng chung cho renderField() (hiển thị)
     * và handleSubmit() (thông báo lỗi/fallback nhãn "Khác" đúng ngôn ngữ).
     *
     * "options" KHÔNG bị ghi đè — mảng gốc vẫn dùng làm VALUE submit thật để
     * dữ liệu submissions nhất quán dù khách xem form ở ngôn ngữ nào; bản
     * dịch của options chỉ lưu riêng vào field['option_labels'] (cùng index)
     * để renderField() hiển thị nhãn đúng ngôn ngữ mà không đổi value.
     * "placeholder" của field "hidden" bị dùng làm giá trị submit thật (xem
     * renderField() case 'hidden') nên KHÔNG dịch, tránh đổi data theo ngôn
     * ngữ hiển thị.
     *
     * public (không phải private) vì ContactFormEmailService::
     * buildAllFieldsTable() cũng gọi hàm này từ bên ngoài class — trước đây
     * để private gây Fatal Error "Call to private method ... from scope
     * ContactFormEmailService" ngay khi có $lang khác rỗng (luôn xảy ra khi
     * Polylang bật), khiến email KHÁCH HÀNG (có truyền $lang) luôn crash
     * trong khi email ADMIN (không truyền $lang) vẫn gửi bình thường.
     */
    public static function applyFieldTranslation(array $field, string $lang = ''): array
    {
        if (empty($field['i18n']) || !is_array($field['i18n'])) {
            return $field;
        }
        if ($lang === '' && function_exists('pll_current_language')) {
            $lang = (string) (pll_current_language() ?: '');
        }
        $override = $lang !== '' ? ($field['i18n'][$lang] ?? null) : null;
        if (!$override || !is_array($override)) {
            return $field;
        }

        $type = $field['type'] ?? '';
        foreach (['label', 'other_label'] as $key) {
            if (isset($override[$key]) && $override[$key] !== '') {
                $field[$key] = $override[$key];
            }
        }
        if ($type !== 'hidden' && isset($override['placeholder']) && $override['placeholder'] !== '') {
            $field['placeholder'] = $override['placeholder'];
        }
        if ($type === 'content' && isset($override['content'])) {
            $field['content'] = $override['content'];
        }
        if (!empty($override['options']) && is_array($override['options'])) {
            $labels = $field['options'] ?? [];
            foreach ($override['options'] as $idx => $val) {
                if ($val !== '') {
                    $labels[$idx] = $val;
                }
            }
            $field['option_labels'] = $labels;
        }

        return $field;
    }

    private function renderField(array $field): void
    {
        $field = self::applyFieldTranslation($field);

        // "content" không có name/label/placeholder — chỉ in ra ghi chú tĩnh
        // (admin tự viết, hỗ trợ <a>/<strong>/<em> qua nút soạn thảo), không
        // thu thập dữ liệu nên tách riêng khỏi luồng render field thông thường.
        if (($field['type'] ?? '') === 'content') {
            $content = wp_kses_post($field['content'] ?? '');
            if ($content !== '') {
                echo '<div class="laca-cf-form-row laca-cf-type-content laca-cf-content-block">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            return;
        }

        $name         = esc_attr($field['name']);
        $label        = esc_html($field['label'] ?? '');
        $placeholder  = esc_attr($field['placeholder'] ?? '');
        $required     = !empty($field['required']);
        $showLabel    = !empty($field['show_label']);
        $displayLabel = $label !== '' ? $label : esc_html($field['placeholder'] ?? '');
        $labelClass   = 'laca-cf-label' . (!$showLabel ? ' screen-reader-text' : '');
        $type         = $field['type'];
        $rawCol       = $field['col_width'] ?? '12';
        $colWidth     = in_array($rawCol, ['12','6','4','3'], true) ? $rawCol : '12';
        $reqAttr      = $required ? 'required data-required="true"' : 'data-required="false"';
        $reqMark      = $required ? ' <span class="laca-cf-required" aria-hidden="true">*</span>' : '';
        $fieldId      = 'laca-cf-field-' . esc_attr($name) . '-' . uniqid('', true);
        ?>
        <div class="laca-cf-form-row laca-cf-type-<?php echo esc_attr($type); ?> laca-cf-col-<?php echo esc_attr($colWidth); ?>">
            <?php if ($type !== 'hidden'): ?>
                <label for="<?php echo esc_attr($fieldId); ?>" class="<?php echo esc_attr($labelClass); ?>">
                    <?php echo $displayLabel . $reqMark; ?>
                </label>
            <?php endif; ?>

            <?php
            switch ($type) {
                case 'textarea':
                    echo '<textarea id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-textarea" placeholder="' . $placeholder . '" rows="4" ' . $reqAttr . '></textarea>';
                    break;

                case 'select':
                    $options = $field['options'] ?? [];
                    $optionLabels = $field['option_labels'] ?? $options;
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-select" ' . $reqAttr . '>';
                    echo '<option value="">— Chọn ' . $label . ' —</option>';
                    foreach ($options as $idx => $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($optionLabels[$idx] ?? $opt) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'multiselect':
                    $options = $field['options'] ?? [];
                    $optionLabels = $field['option_labels'] ?? $options;
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '[]" class="laca-cf-select laca-cf-multiselect" multiple size="4" ' . $reqAttr . '>';
                    foreach ($options as $idx => $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($optionLabels[$idx] ?? $opt) . '</option>';
                    }
                    echo '</select>';
                    echo '<p class="laca-cf-hint">Giữ Ctrl / Cmd để chọn nhiều.</p>';
                    break;

                case 'radio':
                    $options    = $field['options'] ?? [];
                    $optionLabels = $field['option_labels'] ?? $options;
                    $hasOther   = !empty($field['has_other']);
                    $otherLabel = ($field['other_label'] ?? '') !== '' ? $field['other_label'] : __('Khác', 'laca');
                    echo '<div class="laca-cf-radio-group" id="' . esc_attr($fieldId) . '" ' . $reqAttr . '>';
                    foreach ($options as $idx => $opt) {
                        $optId = esc_attr($fieldId . '-' . $idx);
                        echo '<label class="laca-cf-radio-label"><input type="radio" id="' . $optId . '" name="' . $name . '" value="' . esc_attr($opt) . '"> ' . esc_html($optionLabels[$idx] ?? $opt) . '</label>';
                    }
                    if ($hasOther) {
                        $otherOptId   = esc_attr($fieldId . '-other');
                        $otherInputId = esc_attr($fieldId . '-other-input');
                        echo '<label class="laca-cf-radio-label"><input type="radio" id="' . $otherOptId . '" name="' . $name . '" value="__other__" class="laca-cf-other-toggle" data-other-target="' . $otherInputId . '"> ' . esc_html($otherLabel) . '</label>';
                        echo '<input type="text" id="' . $otherInputId . '" name="' . $name . '_other" class="laca-cf-input laca-cf-other-input" placeholder="' . esc_attr__('Vui lòng ghi rõ…', 'laca') . '" style="display:none">';
                    }
                    echo '</div>';
                    break;

                case 'checkbox':
                    $options    = $field['options'] ?? [];
                    $optionLabels = $field['option_labels'] ?? $options;
                    $hasOther   = !empty($field['has_other']);
                    $otherLabel = ($field['other_label'] ?? '') !== '' ? $field['other_label'] : __('Khác', 'laca');
                    // Bật "Khác" thì luôn coi là nhóm nhiều lựa chọn (dù chỉ
                    // có 0-1 option thật) vì đã có ít nhất 2 lựa chọn hiển thị.
                    if (count($options) <= 1 && !$hasOther) {
                        // Single checkbox
                        $singleOpt = $options[0] ?? 'yes';
                        $singleLabel = $optionLabels[0] ?? $singleOpt;
                        echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . esc_attr($fieldId) . '" name="' . $name . '" value="' . esc_attr($singleOpt) . '" ' . $reqAttr . '> ' . esc_html($singleLabel) . '</label>';
                    } else {
                        // Nhóm nhiều lựa chọn — mặc định tick được nhiều ô
                        // (name="...[]"). Admin bật "Chỉ cho phép chọn 1" thì
                        // render CÙNG name (không có "[]", giống radio) +
                        // class riêng để JS tự bỏ tick các ô khác trong cùng
                        // nhóm khi 1 ô được chọn — vẫn style checkbox nhưng
                        // hành vi submit là 1 giá trị duy nhất.
                        $isSingleChoiceGroup = !empty($field['single_choice']);
                        $groupClass = 'laca-cf-checkbox-group' . ($isSingleChoiceGroup ? ' laca-cf-checkbox-group--single' : '');
                        $inputName  = $isSingleChoiceGroup ? $name : ($name . '[]');
                        echo '<div class="' . esc_attr($groupClass) . '" id="' . esc_attr($fieldId) . '">';
                        foreach ($options as $idx => $opt) {
                            $optId = esc_attr($fieldId . '-' . $idx);
                            echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . $optId . '" name="' . $inputName . '" value="' . esc_attr($opt) . '" data-required="' . ($required ? 'true' : 'false') . '"> ' . esc_html($optionLabels[$idx] ?? $opt) . '</label>';
                        }
                        if ($hasOther) {
                            $otherOptId   = esc_attr($fieldId . '-other');
                            $otherInputId = esc_attr($fieldId . '-other-input');
                            echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . $otherOptId . '" name="' . $inputName . '" value="__other__" class="laca-cf-other-toggle" data-other-target="' . $otherInputId . '"> ' . esc_html($otherLabel) . '</label>';
                            echo '<input type="text" id="' . $otherInputId . '" name="' . $name . '_other" class="laca-cf-input laca-cf-other-input" placeholder="' . esc_attr__('Vui lòng ghi rõ…', 'laca') . '" style="display:none">';
                        }
                        echo '</div>';
                    }
                    break;

                case 'date':
                    echo '<input type="date" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" ' . $reqAttr . '>';
                    break;

                case 'datetime':
                    echo '<input type="datetime-local" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" ' . $reqAttr . '>';
                    break;

                case 'hidden':
                    echo '<input type="hidden" name="' . $name . '" value="' . $placeholder . '">';
                    break;

                default:
                    // text, email, phone, number, url
                    $inputType = match ($type) {
                        'email'  => 'email',
                        'phone'  => 'tel',
                        'number' => 'number',
                        'url'    => 'url',
                        default  => 'text',
                    };
                    $autocomplete = match ($type) {
                        'email' => 'email',
                        'phone' => 'tel',
                        'text'  => 'on',
                        default => 'off',
                    };
                    echo '<input type="' . esc_attr($inputType) . '" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" placeholder="' . $placeholder . '" autocomplete="' . esc_attr($autocomplete) . '" ' . $reqAttr . '>';
            }
            ?>
            <span class="laca-cf-field-error" hidden aria-live="polite"></span>
        </div>
        <?php
    }

    // =========================================================================
    // INLINE CSS
    // =========================================================================

    public static function printInlineCss(): void
    {
        ?>
        <style id="laca-contact-form-css">
        .laca-contact-form-wrap { max-width: 700px; }
        /* New row-based layout: flex column of layout rows */
        .laca-contact-form { display: flex; flex-direction: column; gap: 16px; align-items: stretch; }
        /* Each layout row uses CSS grid (inline style sets grid-template-columns) */
        .laca-cf-layout-row { align-items: start; }
        /* Mobile: force single column */
        @media (max-width: 640px) {
            .laca-cf-layout-row { grid-template-columns: 1fr !important; }
        }
        /* Old flat-format fields (fallback) */
        .laca-cf-col-12  { grid-column: span 12; }
        .laca-cf-col-6   { grid-column: span 6; }
        .laca-cf-col-4   { grid-column: span 4; }
        .laca-cf-col-3   { grid-column: span 3; }
        .laca-cf-form-row { display: flex; flex-direction: column; gap: 5px; }
        .laca-cf-label { font-weight: 600; font-size: 14px; }
        .laca-cf-required { color: #d9534f; margin-left: 2px; }
        .laca-cf-input,
        .laca-cf-textarea,
        .laca-cf-select {
            width: 100%; padding: 10px 14px; border: 1px solid #ccc;
            border-radius: 6px; font-size: 14px; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
        }
        .laca-cf-input:focus,
        .laca-cf-textarea:focus,
        .laca-cf-select:focus {
            outline: none;
            border-color: var(--cf-primary, var(--primary-color, #2271b1));
            box-shadow: 0 0 0 3px rgba(34,113,177,.15);
        }
        .laca-cf-label { color: var(--cf-label-color, inherit); display: var(--cf-label-display, block); }
        .laca-cf-input, .laca-cf-textarea, .laca-cf-select {
            border-color: var(--cf-input-border, #ccc) !important;
            border-radius: var(--cf-input-radius, 6px) !important;
            padding: var(--cf-input-spacing, 10px 14px) !important;
        }
        .laca-cf-field-invalid { border-color: #d9534f !important; box-shadow: 0 0 0 3px rgba(217,83,79,.15) !important; }
        .laca-cf-field-error { color: #d9534f; font-size: 12px; margin-top: 2px; }
        .laca-cf-radio-group, .laca-cf-checkbox-group { display: flex; flex-direction: column; gap: 8px; }
        .laca-cf-radio-label, .laca-cf-checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; }
        .laca-cf-multiselect { padding: 4px; }
        .laca-cf-hint { margin: 4px 0 0; font-size: 12px; color: #888; }
        .laca-cf-content-block { font-size: 14px; line-height: 1.6; color: #444; }
        .laca-cf-content-block a { color: var(--cf-primary, var(--primary-color, #2271b1)); text-decoration: underline; }
        /* Submit row — căn trái/giữa/phải qua --cf-submit-align (tab Giao diện) */
        .laca-cf-submit-row { flex-direction: row; align-items: center; justify-content: var(--cf-submit-align, flex-end); }
        .laca-cf-submit-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 28px; background: var(--cf-primary, var(--primary-color, #2271b1));
            color: #fff; border: none; border-radius: var(--cf-btn-radius, 6px); font-size: 15px;
            font-weight: 600; cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .laca-cf-submit-btn:hover  { background: var(--cf-secondary, var(--secondary-color, #1a5a9e)); }
        .laca-cf-submit-btn:active { transform: scale(0.98); }
        .laca-cf-submit-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }
        /* hidden attribute must not be overridden by display:flex */
        [hidden] { display: none !important; }
        .laca-cf-btn-loading { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; }
        /* Spinner */
        @keyframes laca-spin { to { stroke-dashoffset: -31.4; } }
        .laca-cf-spinner circle {
            animation: laca-spin 0.8s linear infinite;
            transform-origin: center;
        }
        /* Fallback message (no Swal) */
        .laca-cf-fallback-msg { display: none; margin-top: 10px; padding: 10px 14px; border-radius: 6px; font-size: 14px; }
        .laca-cf-fallback-msg--success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .laca-cf-error { color: #d9534f; font-style: italic; }
        </style>
        <?php
    }

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Extract a flat list of field objects from a form row.
     * Handles both old flat format and new row-based format.
     */
    private static function extractFlatFields(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }
        // Old flat format: first item has 'type' and no 'cols'
        if (isset($raw[0]['type']) && !isset($raw[0]['cols'])) {
            return array_values(array_filter($raw, fn($f) => ($f['type'] ?? '') !== 'content'));
        }
        // New row-based format
        $fields = [];
        foreach ($raw as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    // "content" là ghi chú tĩnh, không thu thập dữ liệu — bỏ
                    // qua ở đây để không bị validate/submit như field thật.
                    if (($field['type'] ?? '') === 'content') {
                        continue;
                    }
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    // =========================================================================
    // VALIDATION / SANITIZATION HELPERS
    // =========================================================================

    private static function sanitizeByType(string $type, mixed $value, array $field): mixed
    {
        $isSingleCheckbox    = $type === 'checkbox' && count($field['options'] ?? []) <= 1;
        $isSingleChoiceGroup = $type === 'checkbox' && !empty($field['single_choice']) && count($field['options'] ?? []) > 1;
        $hasOther            = !empty($field['has_other']);

        if (in_array($type, ['multiselect', 'checkbox'], true) && is_array($value) && !$isSingleCheckbox && !$isSingleChoiceGroup) {
            // Nhóm checkbox/multiselect có bật "Khác" sẽ gửi thêm giá trị
            // sentinel "__other__" (xem renderField()) — phải cho phép nó
            // lọt qua array_filter, không thì bị coi như 1 lựa chọn không
            // hợp lệ và bị loại bỏ.
            $allowed = $field['options'] ?? [];
            if ($hasOther) {
                $allowed[] = '__other__';
            }
            return array_filter($value, fn($v) => in_array($v, $allowed, true));
        }

        // Client cố tình bypass HTML (gửi mảng cho field lẽ ra chỉ 1 giá trị,
        // vd forge request thẳng không qua form thật) — lấy phần tử CUỐI,
        // giống hành vi PHP collapse tự nhiên khi nhiều field trùng name
        // không có "[]" cùng gửi lên. Tránh warning "Array to string
        // conversion" + tránh so sánh chuỗi "Array" không khớp option nào.
        if (($type === 'radio' || $isSingleChoiceGroup) && is_array($value)) {
            $value = (string) (end($value) ?: '');
        }

        $value = (string) $value;

        // Checkbox "chỉ chọn 1" validate giống hệt radio: value phải nằm
        // trong đúng danh sách option (hoặc sentinel "__other__") mới giữ.
        if ($type === 'radio' || $isSingleChoiceGroup) {
            $allowed = $field['options'] ?? [];
            if ($hasOther) {
                $allowed[] = '__other__';
            }
            return in_array($value, $allowed, true) ? sanitize_text_field($value) : '';
        }

        return match ($type) {
            'email'  => sanitize_email($value),
            'url'    => esc_url_raw($value),
            'number' => is_numeric($value) ? $value : '',
            'date', 'datetime' => sanitize_text_field($value),
            'textarea' => sanitize_textarea_field($value),
            'select' => in_array($value, $field['options'] ?? [], true) ? sanitize_text_field($value) : '',
            default   => sanitize_text_field($value),
        };
    }

    private static function validateFormat(string $type, mixed $value, string $label, array $popup): string
    {
        if ($type === 'email' && !empty($value) && !is_email($value)) {
            return $label . ': ' . $popup['msg_invalid_email_suffix'];
        }
        if ($type === 'url' && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            return $label . ': ' . $popup['msg_invalid_url_suffix'];
        }
        if ($type === 'phone' && !empty($value) && !preg_match('/^[0-9\s\+\-\(\)]{8,20}$/', $value)) {
            return $label . ': ' . $popup['msg_invalid_phone_suffix'];
        }
        return '';
    }

    /**
     * Sinh CSS variables scoped theo wrap ID từ style_settings.
     */
    private static function buildScopedCss(string $wrapId, array $s): string
    {
        if (empty($s)) {
            return '';
        }

        $allowed = [
            'primary_color'      => '--cf-primary',
            'secondary_color'    => '--cf-secondary',
            'input_border_color' => '--cf-input-border',
            'label_color'        => '--cf-label-color',
        ];

        $vars = [];
        foreach ($allowed as $key => $var) {
            if (!empty($s[$key])) {
                $val    = preg_replace('/[^a-zA-Z0-9#()\s,%.+-]/', '', $s[$key]);
                $vars[] = $var . ':' . $val;
            }
        }

        // Numeric properties (px values)
        foreach (['btn_border_radius' => '--cf-btn-radius', 'input_border_radius' => '--cf-input-radius'] as $key => $var) {
            if (isset($s[$key])) {
                $val    = (int) $s[$key];
                $vars[] = $var . ':' . $val . 'px';
            }
        }

        // Spacing
        if (!empty($s['input_spacing'])) {
            $val = preg_replace('/[^0-9px\s]/', '', $s['input_spacing']);
            if ($val) {
                $vars[] = '--cf-input-spacing:' . $val;
            }
        }

        // Show label
        if (isset($s['show_label']) && !$s['show_label']) {
            $vars[] = '--cf-label-display:none';
        }

        // Căn nút Submit (trái/giữa/phải)
        if (!empty($s['submit_align'])) {
            $align = match ($s['submit_align']) {
                'left'   => 'flex-start',
                'center' => 'center',
                'right'  => 'flex-end',
                default  => '',
            };
            if ($align) {
                $vars[] = '--cf-submit-align:' . $align;
            }
        }

        $css = '';
        if (!empty($vars)) {
            $css .= '#' . $wrapId . '{' . implode(';', $vars) . '}';
        }

        // Custom CSS
        if (!empty($s['custom_css'])) {
            $custom = wp_strip_all_tags($s['custom_css']);
            $custom = str_replace('__FORM__', '#' . $wrapId, $custom);
            $css .= "\n" . $custom;
        }

        return $css;
    }

    /**
     * CSS cho popup SweetAlert2 (Thành công/Thất bại) — scoped theo class
     * riêng từng form ($swalClass = "laca-cf-swal-{id}", gắn vào
     * customClass.popup lúc Swal.fire(), xem showSwal() trong
     * renderShortcode()). KHÔNG dùng "#wrapId .swal2-popup" như
     * buildScopedCss() vì SweetAlert2 chèn popup thẳng vào <body>, không
     * nằm trong DOM của form — scope theo #wrapId sẽ không bao giờ khớp.
     */
    private static function buildPopupCss(string $swalClass, array $s): string
    {
        if (empty($s)) {
            return '';
        }

        $css = '';
        $sanitizeColor = static fn($v) => preg_replace('/[^a-zA-Z0-9#()\s,%.+-]/', '', (string) $v);

        if (isset($s['popup_border_radius'])) {
            $css .= '.' . $swalClass . '.swal2-popup{border-radius:' . (int) $s['popup_border_radius'] . 'px}';
        }

        if (!empty($s['popup_success_color'])) {
            $c = $sanitizeColor($s['popup_success_color']);
            $css .= '.' . $swalClass . ' .swal2-icon.swal2-success{border-color:' . $c . '}'
                . '.' . $swalClass . ' .swal2-success-ring{border-color:' . $c . '4d}'
                . '.' . $swalClass . ' .swal2-success-line-tip,.' . $swalClass . ' .swal2-success-line-long{background-color:' . $c . '}';
        }

        if (!empty($s['popup_error_color'])) {
            $c = $sanitizeColor($s['popup_error_color']);
            $css .= '.' . $swalClass . ' .swal2-icon.swal2-error{border-color:' . $c . '}'
                . '.' . $swalClass . ' .swal2-x-mark-line-left,.' . $swalClass . ' .swal2-x-mark-line-right{background-color:' . $c . '}';
        }

        // Màu nút: ưu tiên popup_button_color riêng, không có thì dùng luôn
        // primary_color chung của form cho đồng bộ (không bắt buộc cấu hình
        // thêm 1 màu mới nếu admin không cần tách riêng).
        $buttonColor = $s['popup_button_color'] ?? $s['primary_color'] ?? '';
        if (!empty($buttonColor)) {
            $c = $sanitizeColor($buttonColor);
            $css .= '.' . $swalClass . ' .swal2-confirm{background-color:' . $c . ' !important;border-color:' . $c . ' !important}';
        }

        if (!empty($s['popup_custom_css'])) {
            $custom = wp_strip_all_tags($s['popup_custom_css']);
            $custom = str_replace('__POPUP__', '.' . $swalClass, $custom);
            $css .= "\n" . $custom;
        }

        return $css;
    }

    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return 'unknown';
    }
}
