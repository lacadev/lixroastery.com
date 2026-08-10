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
 * AJAX endpoint: wp_ajax_nopriv_laca_contact_submit (cả logged-in lẫn guest)
 *   → Validate → Lưu DB → Gửi email → Trả JSON
 */
class ContactFormAjaxHandler
{
    public function init(): void
    {
        add_action('wp_ajax_laca_contact_submit',        [$this, 'handleSubmit']);
        add_action('wp_ajax_nopriv_laca_contact_submit', [$this, 'handleSubmit']);
        add_shortcode('laca_contact_form', [$this, 'renderShortcode']);
    }

    // =========================================================================
    // AJAX SUBMIT HANDLER
    // =========================================================================

    public function handleSubmit(): void
    {
        // 1. Nonce check
        if (!check_ajax_referer('laca_contact_submit_nonce', '_nonce', false)) {
            wp_send_json_error(['message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'], 403);
        }

        // 2. Form ID
        $formId = absint($_POST['form_id'] ?? 0);
        if (!$formId) {
            wp_send_json_error(['message' => 'Form không hợp lệ.'], 400);
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form) {
            wp_send_json_error(['message' => 'Form không tồn tại.'], 404);
        }

        $fields = self::extractFlatFields($form);

        // 3. Validate & Sanitize từng field
        $data   = [];
        $errors = [];

        foreach ($fields as $field) {
            $name     = $field['name'];
            $label    = $field['label'];
            $required = !empty($field['required']);
            $type     = $field['type'];

            // Lấy giá trị raw từ POST
            $rawValue = $_POST[$name] ?? '';

            // Multiselect / checkbox gửi dạng array
            if (in_array($type, ['multiselect', 'checkbox'], true)) {
                $rawValue = is_array($rawValue) ? $rawValue : [];
            }

            // Validate required
            if ($required) {
                $isEmpty = is_array($rawValue) ? empty($rawValue) : (trim((string) $rawValue) === '');
                if ($isEmpty) {
                    $errors[] = $label . ' là bắt buộc.';
                    continue;
                }
            }

            // Sanitize theo type
            $cleanValue = self::sanitizeByType($type, $rawValue, $field);

            // Validate format
            $formatError = self::validateFormat($type, $cleanValue, $label);
            if ($formatError) {
                $errors[] = $formatError;
                continue;
            }

            $data[$name] = $cleanValue;
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

        // 5. Lưu DB
        ContactFormTable::insertSubmission($formId, $data, $ip);

        // 6. Gửi email
        ContactFormEmailService::sendAll($form, $data, $ip);

        wp_send_json_success(['message' => 'Gửi thành công! Chúng tôi sẽ liên hệ lại sớm.']);
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
        $scopedCss     = self::buildScopedCss($wrapId, $styleSettings);

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
                <input type="hidden" name="action" value="laca_contact_submit">
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

                <div class="laca-cf-form-row laca-cf-submit-row">
                    <button type="submit" class="laca-cf-submit-btn" aria-busy="false">
                        <span class="laca-cf-btn-text">Gửi thông tin</span>
                        <span class="laca-cf-btn-loading" hidden aria-hidden="true">
                            <svg class="laca-cf-spinner" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="31.4"/>
                            </svg>
                            Đang gửi...
                        </span>
                    </button>
                </div>
                <p class="laca-cf-fallback-msg" role="status" aria-live="polite" hidden></p>
            </form>
        </div>

        <script>
        (function() {
            const FORM_ID  = '<?php echo esc_js($formElId); ?>';
            const AJAX_URL = '<?php echo esc_js($ajaxUrl); ?>';

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
                        window.Swal.fire({ ...opts, ...getThemeColors() });
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
                            showFieldError(el, 'Trường này là bắt buộc.');
                            valid = false;
                        }
                    });

                    // Email format check
                    const emailEl = formEl.querySelector('input[type="email"]');
                    if (emailEl && emailEl.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
                        showFieldError(emailEl, 'Email không hợp lệ.');
                        valid = false;
                    }

                    // Phone format check (Vietnam)
                    const phoneEl = formEl.querySelector('input[type="tel"]');
                    if (phoneEl && phoneEl.value.trim() && !/^[0-9\s\+\-\(\)]{8,20}$/.test(phoneEl.value.trim())) {
                        showFieldError(phoneEl, 'Số điện thoại không hợp lệ.');
                        valid = false;
                    }

                    return valid;
                };

                // ── Real-time clear errors on input ───────────────────────────

                formEl.querySelectorAll('input, select, textarea').forEach(function(el) {
                    el.addEventListener('input', function() { clearFieldError(el); });
                    el.addEventListener('blur', function() {
                        if (el.getAttribute('data-required') === 'true' && !el.value.trim()) {
                            showFieldError(el, 'Trường này là bắt buộc.');
                        } else {
                            clearFieldError(el);
                        }
                    });
                });

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
                            showSwal({
                                title: '✓ Thành công!',
                                text: json.data.message || 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất!',
                                icon: 'success',
                                confirmButtonText: 'Đóng',
                            });
                            formEl.reset();
                            clearAllErrors();
                        } else {
                            const msg = (json.data && json.data.message)
                                ? json.data.message
                                : 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            showSwal({
                                title: '✕ Thất bại',
                                html: '<p>' + msg + '</p>',
                                icon: 'error',
                                confirmButtonText: 'Thử lại',
                            });
                        }
                    })
                    .catch(function() {
                        showSwal({
                            title: '✕ Lỗi kết nối',
                            text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối internet.',
                            icon: 'error',
                            confirmButtonText: 'Đã hiểu',
                        });
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.setAttribute('aria-busy', 'false');
                        btnText.hidden = false;
                        btnLoad.hidden = true;
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

    private function renderField(array $field): void
    {
        $name        = esc_attr($field['name']);
        $label       = esc_html($field['label']);
        $placeholder = esc_attr($field['placeholder'] ?? '');
        $required    = !empty($field['required']);
        $type        = $field['type'];
        $rawCol      = $field['col_width'] ?? '12';
        $colWidth    = in_array($rawCol, ['12','6','4','3'], true) ? $rawCol : '12';
        $reqAttr     = $required ? 'required data-required="true"' : 'data-required="false"';
        $reqMark     = $required ? ' <span class="laca-cf-required" aria-hidden="true">*</span>' : '';
        $fieldId     = 'laca-cf-field-' . esc_attr($name) . '-' . uniqid('', true);
        ?>
        <div class="laca-cf-form-row laca-cf-type-<?php echo esc_attr($type); ?> laca-cf-col-<?php echo esc_attr($colWidth); ?>">
            <?php if ($type !== 'hidden'): ?>
                <label for="<?php echo esc_attr($fieldId); ?>" class="laca-cf-label">
                    <?php echo $label . $reqMark; ?>
                </label>
            <?php endif; ?>

            <?php
            switch ($type) {
                case 'textarea':
                    echo '<textarea id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-textarea" placeholder="' . $placeholder . '" rows="4" ' . $reqAttr . '></textarea>';
                    break;

                case 'select':
                    $options = $field['options'] ?? [];
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-select" ' . $reqAttr . '>';
                    echo '<option value="">— Chọn ' . $label . ' —</option>';
                    foreach ($options as $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'multiselect':
                    $options = $field['options'] ?? [];
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '[]" class="laca-cf-select laca-cf-multiselect" multiple size="4" ' . $reqAttr . '>';
                    foreach ($options as $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                    echo '<p class="laca-cf-hint">Giữ Ctrl / Cmd để chọn nhiều.</p>';
                    break;

                case 'radio':
                    $options = $field['options'] ?? [];
                    echo '<div class="laca-cf-radio-group" id="' . esc_attr($fieldId) . '" ' . $reqAttr . '>';
                    foreach ($options as $idx => $opt) {
                        $optId = esc_attr($fieldId . '-' . $idx);
                        echo '<label class="laca-cf-radio-label"><input type="radio" id="' . $optId . '" name="' . $name . '" value="' . esc_attr($opt) . '"> ' . esc_html($opt) . '</label>';
                    }
                    echo '</div>';
                    break;

                case 'checkbox':
                    $options = $field['options'] ?? [];
                    if (count($options) <= 1) {
                        // Single checkbox
                        $singleOpt = $options[0] ?? 'yes';
                        echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . esc_attr($fieldId) . '" name="' . $name . '" value="' . esc_attr($singleOpt) . '" ' . $reqAttr . '> ' . esc_html($singleOpt) . '</label>';
                    } else {
                        // Multiple checkboxes
                        echo '<div class="laca-cf-checkbox-group" id="' . esc_attr($fieldId) . '">';
                        foreach ($options as $idx => $opt) {
                            $optId = esc_attr($fieldId . '-' . $idx);
                            echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . $optId . '" name="' . $name . '[]" value="' . esc_attr($opt) . '" data-required="' . ($required ? 'true' : 'false') . '"> ' . esc_html($opt) . '</label>';
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
        /* Submit row */
        .laca-cf-submit-row { flex-direction: row; align-items: center; justify-content: flex-end; }
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
            return $raw;
        }
        // New row-based format
        $fields = [];
        foreach ($raw as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
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
        if (in_array($type, ['multiselect', 'checkbox'], true) && is_array($value)) {
            $allowed = $field['options'] ?? [];
            return array_filter($value, fn($v) => in_array($v, $allowed, true));
        }

        $value = (string) $value;

        return match ($type) {
            'email'  => sanitize_email($value),
            'url'    => esc_url_raw($value),
            'number' => is_numeric($value) ? $value : '',
            'date', 'datetime' => sanitize_text_field($value),
            'textarea' => sanitize_textarea_field($value),
            'select', 'radio' => in_array($value, $field['options'] ?? [], true) ? sanitize_text_field($value) : '',
            default   => sanitize_text_field($value),
        };
    }

    private static function validateFormat(string $type, mixed $value, string $label): string
    {
        if ($type === 'email' && !empty($value) && !is_email($value)) {
            return $label . ': Địa chỉ email không hợp lệ.';
        }
        if ($type === 'url' && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            return $label . ': Đường dẫn URL không hợp lệ.';
        }
        if ($type === 'phone' && !empty($value) && !preg_match('/^[0-9\s\+\-\(\)]{8,20}$/', $value)) {
            return $label . ': Số điện thoại không hợp lệ.';
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
