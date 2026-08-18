<?php

namespace App\Settings\LoginLog;

/**
 * LoginLogTable
 *
 * DB table: wp_laca_login_log
 * Ghi lại LÝ DO THẬT của mỗi lần đăng nhập thất bại (mã lỗi WP_Error gốc,
 * trước khi bị `login_errors` filter thay bằng câu chung ở performance.php)
 * để admin kiểm tra khi cần, mà không lộ chi tiết ra cho người dùng cuối.
 */
class LoginLogTable
{
    public static function install(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . 'laca_login_log';
        $charset = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            return;
        }

        $sql = "CREATE TABLE {$table} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username       VARCHAR(255)    NOT NULL DEFAULT '',
            ip             VARCHAR(45)     NOT NULL DEFAULT '',
            error_code     VARCHAR(50)     NOT NULL DEFAULT '',
            error_message  VARCHAR(500)    NOT NULL DEFAULT '',
            attempted_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_error_code   (error_code),
            KEY idx_attempted_at (attempted_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log(string $username, string $ip, string $errorCode, string $errorMessage): void
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'laca_login_log',
            [
                'username'      => sanitize_text_field($username),
                'ip'            => sanitize_text_field($ip),
                'error_code'    => sanitize_key($errorCode),
                'error_message' => sanitize_text_field(wp_strip_all_tags($errorMessage)),
                'attempted_at'  => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        // Auto-purge log cũ hơn 90 ngày — theo đúng quy ước đã dùng ở EmailLogTable.
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}laca_login_log WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    public static function getLogs(int $page = 1, int $perPage = 30, string $errorCode = ''): array
    {
        global $wpdb;
        $table  = $wpdb->prefix . 'laca_login_log';
        $offset = ($page - 1) * $perPage;
        $where  = $errorCode ? $wpdb->prepare('WHERE error_code = %s', $errorCode) : '';

        return $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY attempted_at DESC LIMIT {$perPage} OFFSET {$offset}",
            ARRAY_A
        ) ?: [];
    }

    public static function countLogs(string $errorCode = ''): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'laca_login_log';
        $where = $errorCode ? $wpdb->prepare('WHERE error_code = %s', $errorCode) : '';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }

    /**
     * Danh sách mã lỗi thực tế đang có trong log — dùng để build filter
     * "subsubsub" động, không cần khai báo tay từng loại lỗi có thể xảy ra.
     */
    public static function getDistinctErrorCodes(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'laca_login_log';
        $rows  = $wpdb->get_col("SELECT DISTINCT error_code FROM {$table} WHERE error_code != '' ORDER BY error_code ASC");
        return $rows ?: [];
    }
}
