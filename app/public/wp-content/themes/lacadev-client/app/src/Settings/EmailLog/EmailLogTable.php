<?php

namespace App\Settings\EmailLog;

/**
 * EmailLogTable
 *
 * DB table: wp_laca_email_log
 * Log mọi email theme gửi đi để admin kiểm tra.
 */
class EmailLogTable
{
    public static function install(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . 'laca_email_log';
        $charset = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            return;
        }

        $sql = "CREATE TABLE {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            to_email    VARCHAR(255)    NOT NULL DEFAULT '',
            subject     VARCHAR(500)    NOT NULL DEFAULT '',
            status      VARCHAR(20)     NOT NULL DEFAULT 'sent',
            source      VARCHAR(100)    NOT NULL DEFAULT '',
            sent_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status  (status),
            KEY idx_sent_at (sent_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log(string $to, string $subject, string $status = 'sent', string $source = ''): void
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'laca_email_log',
            [
                'to_email' => sanitize_email($to),
                'subject'  => sanitize_text_field($subject),
                'status'   => sanitize_key($status),
                'source'   => sanitize_text_field($source),
                'sent_at'  => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        // Auto-purge logs older than 90 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}laca_email_log WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    public static function getLogs(int $page = 1, int $perPage = 30, string $status = ''): array
    {
        global $wpdb;
        $table  = $wpdb->prefix . 'laca_email_log';
        $offset = ($page - 1) * $perPage;
        $where  = $status ? $wpdb->prepare('WHERE status = %s', $status) : '';

        return $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY sent_at DESC LIMIT {$perPage} OFFSET {$offset}",
            ARRAY_A
        ) ?: [];
    }

    public static function countLogs(string $status = ''): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'laca_email_log';
        $where = $status ? $wpdb->prepare('WHERE status = %s', $status) : '';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }
}
