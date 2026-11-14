<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * FooterContactHandler
 *
 * Xử lý AJAX submission cho form liên hệ footer và popup dự án
 * (action: laca_footer_contact_submit, nonce: laca_footer_contact_nonce).
 */
class FooterContactHandler
{
    public const FORM_OPTION_KEY   = 'laca_footer_contact_form_id';
    private const RATE_LIMIT_MAX   = 5;
    private const RATE_LIMIT_TTL   = 600; // 10 phút
    private const MAX_FIELD_LENGTH = 200;

    public function init(): void
    {
        add_action('wp_ajax_laca_footer_contact_submit', [$this, 'handleSubmit']);
        add_action('wp_ajax_nopriv_laca_footer_contact_submit', [$this, 'handleSubmit']);
    }

    public function handleSubmit(): void
    {
        // 1. Nonce check
        if (!check_ajax_referer('laca_footer_contact_nonce', 'nonce', false)) {
            wp_send_json_error(
                __('Phiên làm việc đã hết hạn, vui lòng tải lại trang và thử lại.', 'laca'),
                403
            );
        }

        // 2. Rate limit theo IP: tối đa 5 lần / 10 phút
        $ip    = ContactFormAjaxHandler::getClientIp();
        $rlKey = 'laca_fcf_rl_' . substr(hash('sha256', $ip !== '' ? $ip : 'unknown'), 0, 32);
        $now   = time();
        $state = get_transient($rlKey);

        if (!is_array($state) || empty($state['expires']) || (int) $state['expires'] <= $now) {
            $state = [
                'count'   => 0,
                'expires' => $now + self::RATE_LIMIT_TTL,
            ];
        }

        if ((int) $state['count'] >= self::RATE_LIMIT_MAX) {
            wp_send_json_error(
                __('Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.', 'laca'),
                429
            );
        }

        $state['count'] = (int) $state['count'] + 1;
        set_transient($rlKey, $state, max(1, (int) $state['expires'] - $now));

        // 3. Sanitize & Validate 5 trường bắt buộc
        $requiredFields = [
            'tf_address',
            'tf_scale',
            'tf_budget',
            'tf_name',
            'tf_phone',
        ];

        $data = [];
        foreach ($requiredFields as $fieldName) {
            $raw = $_POST[$fieldName] ?? '';
            if (!is_scalar($raw)) {
                $raw = '';
            }
            $clean = trim(sanitize_text_field(wp_unslash((string) $raw)));
            if (function_exists('mb_substr')) {
                $clean = mb_substr($clean, 0, self::MAX_FIELD_LENGTH, 'UTF-8');
            } else {
                $clean = substr($clean, 0, self::MAX_FIELD_LENGTH);
            }

            if ($clean === '') {
                wp_send_json_error(
                    __('Vui lòng điền đầy đủ thông tin bắt buộc.', 'laca'),
                    422
                );
            }

            $data[$fieldName] = $clean;
        }

        $normalizedPhone = preg_replace('/[\s.\-]+/', '', $data['tf_phone']) ?? '';
        if (!preg_match('/^(\+?84|0)\d{8,10}$/', $normalizedPhone)) {
            wp_send_json_error(
                __('Số điện thoại không hợp lệ. Vui lòng kiểm tra lại.', 'laca'),
                422
            );
        }
        $data['tf_phone'] = $normalizedPhone;

        // 4. Lưu vào ContactFormTable
        try {
            ContactFormTable::install();
            $form = $this->ensureFooterForm();
            if ($form === null || empty($form['id'])) {
                error_log('[FooterContact] Không thể khởi tạo hoặc lấy Form liên hệ footer.');
                wp_send_json_error(
                    __('Không thể lưu yêu cầu lúc này. Vui lòng thử lại sau.', 'laca'),
                    500
                );
            }

            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) && is_scalar($_SERVER['HTTP_USER_AGENT'])
                ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']))
                : '';

            $submissionId = ContactFormTable::insertSubmission(
                (int) $form['id'],
                $data,
                $ip,
                $userAgent
            );

            if ($submissionId <= 0) {
                error_log('[FooterContact] insertSubmission() trả về 0 cho form #' . (int) $form['id']);
                wp_send_json_error(
                    __('Không thể lưu yêu cầu lúc này. Vui lòng thử lại sau.', 'laca'),
                    500
                );
            }
        } catch (\Throwable $e) {
            error_log('[FooterContact] Lỗi lưu DB: ' . $e->getMessage() . ' tại ' . $e->getFile() . ':' . $e->getLine());
            wp_send_json_error(
                __('Không thể lưu yêu cầu lúc này. Vui lòng thử lại sau.', 'laca'),
                500
            );
        }

        // 5. Gửi email thông báo: ưu tiên không làm gián đoạn trải nghiệm thu lead của form footer / popup dự án.
        // Khác có chủ đích với ContactFormAjaxHandler (vốn trả HTTP 500 khi gửi mail lỗi): một khi lead đã lưu
        // an toàn vào ContactFormTable và bắn hook laca_contact_form_submitted, nếu gửi mail lỗi thì chỉ ghi
        // error_log để admin kiểm tra SMTP, vẫn trả 200 thành công nhằm tránh khách bấm gửi lại gây trùng bản ghi.
        try {
            $emailSent = ContactFormEmailService::sendAll($form, $data, $ip);
            if (!$emailSent) {
                error_log(sprintf(
                    '[FooterContact] Gửi email thất bại cho submission #%d (form #%d).',
                    $submissionId,
                    (int) $form['id']
                ));
            }

            $fields = json_decode($form['fields'] ?? '[]', true) ?: [];
            do_action('laca_contact_form_submitted', $form, $fields, $data, $submissionId, $emailSent);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[FooterContact] Ngoại lệ khi gửi email/hook cho submission #%d: %s',
                $submissionId,
                $e->getMessage()
            ));
        }

        wp_send_json_success(
            __('Cảm ơn bạn! Yêu cầu tư vấn đã được ghi nhận, chúng tôi sẽ liên hệ sớm nhất.', 'laca')
        );
    }

    /**
     * Đảm bảo form "Form liên hệ footer" tồn tại trong ContactFormTable (tạo 1 lần).
     */
    private function ensureFooterForm(): ?array
    {
        $savedId = (int) get_option(self::FORM_OPTION_KEY, 0);
        if ($savedId > 0) {
            $existing = ContactFormTable::getForm($savedId);
            if ($existing !== null) {
                return $existing;
            }
        }

        global $wpdb;
        $formsTable = ContactFormTable::getFormsTable();
        $formName   = __('Form liên hệ footer', 'laca');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $found = $wpdb->get_row(
            $wpdb->prepare("SELECT id FROM {$formsTable} WHERE name = %s ORDER BY id ASC LIMIT 1", $formName),
            ARRAY_A
        );
        if ($found && !empty($found['id'])) {
            $id = (int) $found['id'];
            update_option(self::FORM_OPTION_KEY, $id, false);
            return ContactFormTable::getForm($id);
        }

        $payload = [
            'name'                   => $formName,
            'fields'                 => [
                ['type' => 'text',  'name' => 'tf_name',    'label' => __('Họ và tên', 'laca'),             'required' => true],
                ['type' => 'phone', 'name' => 'tf_phone',   'label' => __('Số điện thoại liên hệ', 'laca'), 'required' => true],
                ['type' => 'text',  'name' => 'tf_address', 'label' => __('Địa chỉ xây dựng', 'laca'),      'required' => true],
                ['type' => 'text',  'name' => 'tf_scale',   'label' => __('Quy mô xây dựng', 'laca'),       'required' => true],
                ['type' => 'text',  'name' => 'tf_budget',  'label' => __('Ngân sách', 'laca'),             'required' => true],
            ],
            'notify_email'           => '',
            'email_admin_subject'    => __('Yêu cầu tư vấn mới [$tf_name - $tf_phone]', 'laca'),
            'email_admin_body'       => '$all_fields',
            'email_customer_subject' => '',
            'email_customer_body'    => '',
            'is_active'              => 1,
        ];

        $newId = ContactFormTable::insertForm($payload);
        if ($newId <= 0) {
            ContactFormTable::install(true);
            $newId = ContactFormTable::insertForm($payload);
        }

        if ($newId > 0) {
            update_option(self::FORM_OPTION_KEY, $newId, false);
            return ContactFormTable::getForm($newId);
        }

        return null;
    }
}
