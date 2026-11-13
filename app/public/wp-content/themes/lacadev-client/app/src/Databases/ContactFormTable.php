<?php

namespace App\Databases;

/**
 * ContactFormTable
 *
 * Quản lý 2 custom tables:
 *   - wp_laca_contact_forms       : Định nghĩa form (tên, fields JSON, email templates)
 *   - wp_laca_contact_submissions : Submissions từ frontend (data JSON, IP, thời gian)
 */
class ContactFormTable
{
    const FORMS_TABLE       = 'laca_contact_forms';
    const SUBMISSIONS_TABLE = 'laca_contact_submissions';
    const TABLE_VERSION     = '1.2.0';
    const VERSION_KEY       = 'laca_contact_form_table_version';

    // -------------------------------------------------------------------------
    // Table names
    // -------------------------------------------------------------------------

    public static function getFormsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::FORMS_TABLE;
    }

    public static function getSubmissionsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::SUBMISSIONS_TABLE;
    }

    // -------------------------------------------------------------------------
    // Install / Upgrade
    // -------------------------------------------------------------------------

    public static function install(bool $force = false): void
    {
        global $wpdb;

        if (!$force) {
            $installedVersion = get_option(self::VERSION_KEY);
            if ($installedVersion === self::TABLE_VERSION) {
                // Tự chữa trường hợp bảng bị xoá mà option còn
                if (is_admin()) {
                    $checked = get_transient('laca_cf_table_check');
                    if ($checked === false) {
                        $formsTable       = self::getFormsTable();
                        $submissionsTable = self::getSubmissionsTable();
                        $existingForms = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($formsTable)));
                        $existingSubs  = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($submissionsTable)));
                        if ($existingForms === $formsTable && $existingSubs === $submissionsTable) {
                            set_transient('laca_cf_table_check', 1, 12 * HOUR_IN_SECONDS);
                            return;
                        }
                    } else {
                        return;
                    }
                } else {
                    return;
                }
            }
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $formsTable       = self::getFormsTable();
        $submissionsTable = self::getSubmissionsTable();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table 1: wp_laca_contact_forms
        $sqlForms = "CREATE TABLE {$formsTable} (
            id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name                 VARCHAR(255) NOT NULL DEFAULT '',
            fields               LONGTEXT NOT NULL COMMENT 'JSON array of field definitions',
            notify_email         VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Email nhận thông báo admin (hỗ trợ nhiều email phân cách dấu phẩy). Rỗng = dùng admin_email',
            email_admin_subject  VARCHAR(500) NOT NULL DEFAULT 'Đăng kí tư vấn [\$name - \$phone_number]',
            email_admin_body     LONGTEXT NOT NULL,
            email_customer_subject VARCHAR(500) NOT NULL DEFAULT 'Cảm ơn bạn đã liên hệ',
            email_customer_body  LONGTEXT NOT NULL,
            style_settings       LONGTEXT NULL COMMENT 'JSON object of form styling',
            is_active            TINYINT(1) NOT NULL DEFAULT 1,
            created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        // Table 2: wp_laca_contact_submissions
        $sqlSubmissions = "CREATE TABLE {$submissionsTable} (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id      BIGINT(20) UNSIGNED NOT NULL,
            data         LONGTEXT NOT NULL COMMENT 'JSON object {field_name: value}',
            ip_address   VARCHAR(45) NOT NULL DEFAULT '',
            user_agent   VARCHAR(500) NOT NULL DEFAULT '',
            is_read      TINYINT(1) NOT NULL DEFAULT 0,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        dbDelta($sqlForms);
        dbDelta($sqlSubmissions);

        update_option(self::VERSION_KEY, self::TABLE_VERSION);
    }

    // -------------------------------------------------------------------------
    // CRUD — Forms
    // -------------------------------------------------------------------------

    /**
     * Đảm bảo luôn có 1 form mặc định (dùng cho shortcode không truyền id và template-contact.php).
     * Lưu ID vào option 'laca_cf_default_form_id' và kiểm tra form còn tồn tại trước khi trả về.
     */
    public static function ensureDefaultForm(): int
    {
        $defaultId = (int) get_option('laca_cf_default_form_id', 0);
        if ($defaultId > 0 && self::getForm($defaultId) !== null) {
            return $defaultId;
        }

        // Nếu option chưa set, kiểm tra xem đã có form mặc định với tên 'Form Liên hệ' chưa
        global $wpdb;
        $formsTable  = self::getFormsTable();
        $defaultName = __('Form Liên hệ', 'laca');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT id FROM {$formsTable} WHERE name = %s ORDER BY id ASC LIMIT 1", $defaultName),
            ARRAY_A
        );
        if ($existing && !empty($existing['id'])) {
            $id = (int) $existing['id'];
            update_option('laca_cf_default_form_id', $id);
            return $id;
        }

        $newId = self::insertForm([
            'name'                   => __('Form Liên hệ', 'laca'),
            'fields'                 => [
                ['type' => 'text',     'name' => 'name',    'label' => __('Họ và tên', 'laca'),     'required' => true],
                ['type' => 'phone',    'name' => 'phone',   'label' => __('Số điện thoại', 'laca'), 'required' => true],
                ['type' => 'email',    'name' => 'email',   'label' => __('Email', 'laca'),         'required' => false],
                ['type' => 'textarea', 'name' => 'message', 'label' => __('Nội dung', 'laca'),      'required' => true],
            ],
            'notify_email'           => '',
            'email_admin_subject'    => __('Lời nhắn mới từ $name - $phone', 'laca'),
            'email_admin_body'       => sprintf(
                '<p><strong>%s:</strong> $name</p><p><strong>%s:</strong> $phone</p><p><strong>%s:</strong> $email</p><p><strong>%s:</strong></p><p>$message</p>',
                __('Họ và tên', 'laca'),
                __('Số điện thoại', 'laca'),
                __('Email', 'laca'),
                __('Nội dung', 'laca')
            ),
            'email_customer_subject' => __('Cảm ơn bạn đã liên hệ', 'laca'),
            'email_customer_body'    => sprintf(
                '<p>%s $name,</p><p>%s</p>',
                __('Chào', 'laca'),
                __('Chúng tôi đã nhận được lời nhắn của bạn và sẽ phản hồi sớm nhất.', 'laca')
            ),
            'is_active'              => 1,
        ]);

        if ($newId > 0) {
            update_option('laca_cf_default_form_id', $newId);
        }

        return $newId;
    }

    /**
     * Lấy tất cả forms (kèm submission count)
     */
    public static function getAllForms(): array
    {
        global $wpdb;
        $formsTable       = self::getFormsTable();
        $submissionsTable = self::getSubmissionsTable();

        return $wpdb->get_results(
            "SELECT f.*,
                    COUNT(s.id) AS submission_count,
                    SUM(CASE WHEN s.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
             FROM {$formsTable} f
             LEFT JOIN {$submissionsTable} s ON s.form_id = f.id
             GROUP BY f.id
             ORDER BY f.id DESC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Lấy 1 form theo ID
     */
    public static function getForm(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::getFormsTable() . " WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Làm sạch danh sách email nhận thông báo (hỗ trợ nhập nhiều email,
     * phân tách bởi dấu phẩy, chấm phẩy hoặc khoảng trắng).
     */
    public static function sanitizeEmailList($emails, ?array &$invalid = null): string
    {
        if (is_array($emails)) {
            $emails = implode(',', $emails);
        }
        $parts = preg_split('/[\s,;]+/', (string) $emails, -1, PREG_SPLIT_NO_EMPTY);
        $valid = [];
        foreach ($parts as $part) {
            $clean = sanitize_email($part);
            if ($clean && is_email($clean)) {
                if (!in_array($clean, $valid, true)) {
                    $valid[] = $clean;
                }
            } elseif ($invalid !== null) {
                $invalid[] = $part;
            }
        }
        return implode(', ', $valid);
    }

    /**
     * Insert form mới, trả về ID
     */
    public static function insertForm(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::getFormsTable(), [
            'name'                   => sanitize_text_field($data['name']),
            'fields'                 => wp_json_encode($data['fields'] ?? []),
            'notify_email'           => self::sanitizeEmailList($data['notify_email'] ?? ''),
            'email_admin_subject'    => sanitize_text_field($data['email_admin_subject'] ?? ''),
            'email_admin_body'       => wp_kses_post($data['email_admin_body'] ?? ''),
            'email_customer_subject' => sanitize_text_field($data['email_customer_subject'] ?? ''),
            'email_customer_body'    => wp_kses_post($data['email_customer_body'] ?? ''),
            'style_settings'         => wp_json_encode($data['style_settings'] ?? []),
            // Manager không gửi is_active → mặc định bật
            'is_active'              => array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Update form
     */
    public static function updateForm(int $id, array $data): bool
    {
        global $wpdb;
        $row = [
            'name'                   => sanitize_text_field($data['name']),
            'fields'                 => wp_json_encode($data['fields'] ?? []),
            'notify_email'           => self::sanitizeEmailList($data['notify_email'] ?? ''),
            'email_admin_subject'    => sanitize_text_field($data['email_admin_subject'] ?? ''),
            'email_admin_body'       => wp_kses_post($data['email_admin_body'] ?? ''),
            'email_customer_subject' => sanitize_text_field($data['email_customer_subject'] ?? ''),
            'email_customer_body'    => wp_kses_post($data['email_customer_body'] ?? ''),
                'style_settings'         => wp_json_encode($data['style_settings'] ?? []),
        ];
        // Chỉ đổi is_active khi nơi gọi gửi rõ — tránh lưu form là tự tắt form
        if (array_key_exists('is_active', $data)) {
            $row['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        return false !== $wpdb->update(self::getFormsTable(), $row, ['id' => $id]);
    }

    /**
     * Xóa form và toàn bộ submissions của form đó
     */
    public static function deleteForm(int $id): bool
    {
        global $wpdb;
        $wpdb->delete(self::getSubmissionsTable(), ['form_id' => $id]);
        return false !== $wpdb->delete(self::getFormsTable(), ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Submissions
    // -------------------------------------------------------------------------

    /**
     * Insert submission mới
     */
    public static function insertSubmission(int $formId, array $data, string $ip = '', string $userAgent = ''): int
    {
        global $wpdb;
        $wpdb->insert(self::getSubmissionsTable(), [
            'form_id'    => $formId,
            'data'       => wp_json_encode($data),
            'ip_address' => sanitize_text_field($ip),
            'user_agent' => sanitize_text_field(substr($userAgent, 0, 500)),
            'is_read'    => 0,
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Lấy submissions của 1 form (phân trang)
     */
    public static function getSubmissions(int $formId, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $limit  = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $limit;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::getSubmissionsTable() . "
                 WHERE form_id = %d
                 ORDER BY id DESC
                 LIMIT %d OFFSET %d",
                $formId,
                $limit,
                $offset
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Đếm tổng submissions của 1 form
     */
    public static function countSubmissions(int $formId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::getSubmissionsTable() . " WHERE form_id = %d",
                $formId
            )
        );
    }

    /**
     * Đếm tổng unread submissions trên TOÀN BỘ hệ thống (dùng cho badge menu)
     */
    public static function countAllUnread(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::getSubmissionsTable() . " WHERE is_read = 0"
        );
    }

    /**
     * Đánh dấu submission đã đọc
     */
    public static function markRead(int $submissionId): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            self::getSubmissionsTable(),
            ['is_read' => 1],
            ['id' => $submissionId]
        );
    }

    /**
     * Đánh dấu toàn bộ submissions của 1 form là đã đọc
     */
    public static function markAllRead(int $formId): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            self::getSubmissionsTable(),
            ['is_read' => 1],
            ['form_id' => $formId]
        );
    }

    /**
     * Xóa 1 submission
     */
    public static function deleteSubmission(int $submissionId): bool
    {
        global $wpdb;
        return false !== $wpdb->delete(
            self::getSubmissionsTable(),
            ['id' => $submissionId]
        );
    }

    /**
     * Lấy tất cả submissions của 1 form để export CSV
     */
    public static function getAllSubmissionsForExport(int $formId): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::getSubmissionsTable() . "
                 WHERE form_id = %d
                 ORDER BY id DESC",
                $formId
            ),
            ARRAY_A
        ) ?: [];
    }
}
