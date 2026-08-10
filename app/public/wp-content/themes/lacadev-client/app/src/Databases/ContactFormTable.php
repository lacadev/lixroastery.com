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
    const TABLE_VERSION     = '1.1.0';
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

    public static function install(): void
    {
        global $wpdb;

        $currentVersion = get_option(self::VERSION_KEY, '0');
        // Force run dbDelta to ensure tables exist in case they were dropped but option remained
        // if (version_compare($currentVersion, self::TABLE_VERSION, '>=')) {
        //     return;
        // }

        $charsetCollate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // --- Table: laca_contact_forms ---
        $formsTable = self::getFormsTable();
        $sqlForms   = "CREATE TABLE {$formsTable} (
            id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name                 VARCHAR(255) NOT NULL DEFAULT '',
            fields               LONGTEXT NOT NULL COMMENT 'JSON array of field definitions',
            notify_email         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Email nhận thông báo admin. Rỗng = dùng admin_email',
            email_admin_subject  VARCHAR(500) NOT NULL DEFAULT 'Đăng kí tư vấn [\$name - \$phone_number]',
            email_admin_body     LONGTEXT NOT NULL,
            email_customer_subject VARCHAR(500) NOT NULL DEFAULT 'Cảm ơn bạn đã liên hệ',
            email_customer_body  LONGTEXT NOT NULL,
            style_settings       LONGTEXT NULL COMMENT 'JSON: custom CSS variables (primary_color, border_radius, etc.)',
            is_active            TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) {$charsetCollate};";
        dbDelta($sqlForms);

        // --- Table: laca_contact_submissions ---
        $subTable = self::getSubmissionsTable();
        $sqlSubs  = "CREATE TABLE {$subTable} (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id    BIGINT(20) UNSIGNED NOT NULL,
            data       LONGTEXT NOT NULL COMMENT 'JSON key-value của toàn bộ fields submit',
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            is_read    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id   (form_id),
            KEY is_read   (is_read),
            KEY created_at (created_at)
        ) {$charsetCollate};";
        dbDelta($sqlSubs);

        update_option(self::VERSION_KEY, self::TABLE_VERSION);
    }

    // -------------------------------------------------------------------------
    // CRUD — Forms
    // -------------------------------------------------------------------------

    /**
     * Lấy tất cả forms (kèm submission count)
     */
    public static function getAllForms(): array
    {
        global $wpdb;
        $formsTable = self::getFormsTable();
        $subTable   = self::getSubmissionsTable();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM {$subTable} s WHERE s.form_id = f.id) AS submission_count,
                    (SELECT COUNT(*) FROM {$subTable} s WHERE s.form_id = f.id AND s.is_read = 0) AS unread_count
             FROM {$formsTable} f
             ORDER BY f.created_at DESC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Lấy 1 form theo ID
     */
    public static function getForm(int $id): ?array
    {
        global $wpdb;
        $formsTable = self::getFormsTable();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$formsTable} WHERE id = %d", $id), // phpcs:ignore
            ARRAY_A
        );
        return $row ?: null;
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
            'notify_email'           => sanitize_email($data['notify_email'] ?? ''),
            'email_admin_subject'    => sanitize_text_field($data['email_admin_subject'] ?? ''),
            'email_admin_body'       => wp_kses_post($data['email_admin_body'] ?? ''),
            'email_customer_subject' => sanitize_text_field($data['email_customer_subject'] ?? ''),
            'email_customer_body'    => wp_kses_post($data['email_customer_body'] ?? ''),
            'style_settings'         => wp_json_encode($data['style_settings'] ?? []),
            'is_active'              => 1,
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Update form theo ID
     */
    public static function updateForm(int $id, array $data): bool
    {
        global $wpdb;
        $result = $wpdb->update(
            self::getFormsTable(),
            [
                'name'                   => sanitize_text_field($data['name']),
                'fields'                 => wp_json_encode($data['fields'] ?? []),
                'notify_email'           => sanitize_email($data['notify_email'] ?? ''),
                'email_admin_subject'    => sanitize_text_field($data['email_admin_subject'] ?? ''),
                'email_admin_body'       => wp_kses_post($data['email_admin_body'] ?? ''),
                'email_customer_subject' => sanitize_text_field($data['email_customer_subject'] ?? ''),
                'email_customer_body'    => wp_kses_post($data['email_customer_body'] ?? ''),
                'style_settings'         => wp_json_encode($data['style_settings'] ?? []),
            ],
            ['id' => $id]
        );
        return $result !== false;
    }

    /**
     * Xoá form và toàn bộ submissions liên quan
     */
    public static function deleteForm(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::getSubmissionsTable(), ['form_id' => $id]);
        $wpdb->delete(self::getFormsTable(), ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // CRUD — Submissions
    // -------------------------------------------------------------------------

    /**
     * Lưu submission mới, trả về ID
     */
    public static function insertSubmission(int $formId, array $data, string $ip): int
    {
        global $wpdb;
        $wpdb->insert(self::getSubmissionsTable(), [
            'form_id'    => $formId,
            'data'       => wp_json_encode($data),
            'ip_address' => sanitize_text_field($ip),
            'is_read'    => 0,
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Lấy submissions theo form_id với phân trang
     */
    public static function getSubmissions(int $formId, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $subTable = self::getSubmissionsTable();
        $offset   = ($page - 1) * $perPage;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$subTable} WHERE form_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $formId,
                $perPage,
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
        $subTable = self::getSubmissionsTable();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$subTable} WHERE form_id = %d", $formId)
        );
    }

    /**
     * Đếm tổng submissions chưa đọc của tất cả forms
     */
    public static function countAllUnread(): int
    {
        global $wpdb;
        $subTable = self::getSubmissionsTable();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subTable} WHERE is_read = 0");
    }

    /**
     * Đánh dấu 1 submission đã đọc
     */
    public static function markRead(int $submissionId): void
    {
        global $wpdb;
        $wpdb->update(self::getSubmissionsTable(), ['is_read' => 1], ['id' => $submissionId]);
    }

    /**
     * Xoá 1 submission
     */
    public static function deleteSubmission(int $submissionId): void
    {
        global $wpdb;
        $wpdb->delete(self::getSubmissionsTable(), ['id' => $submissionId]);
    }

    // -------------------------------------------------------------------------
    // Uninstall
    // -------------------------------------------------------------------------

    public static function uninstall(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query('DROP TABLE IF EXISTS ' . self::getSubmissionsTable());
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query('DROP TABLE IF EXISTS ' . self::getFormsTable());
        delete_option(self::VERSION_KEY);
    }
}
