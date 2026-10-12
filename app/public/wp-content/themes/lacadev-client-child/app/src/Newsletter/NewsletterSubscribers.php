<?php

/**
 * Newsletter Subscribers — lớp truy cập bảng lưu email đăng ký ở footer.
 *
 * DB của site là nguồn dữ liệu chính; Google Sheet (NewsletterAjaxHandler)
 * chỉ là bản đồng bộ phụ — webhook lỗi/hết hạn thì email vẫn không mất.
 * Dùng bảng riêng thay vì CPT để danh sách lớn vẫn nhẹ (không đụng
 * wp_posts/wp_postmeta) và có UNIQUE(email) chặn trùng ở tầng DB.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterSubscribers
{
    private const DB_VERSION = '1.0';
    private const DB_VERSION_OPTION = 'lix_newsletter_db_version';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lix_newsletter_subscribers';
    }

    /**
     * Tạo/nâng cấp bảng khi DB_VERSION đổi — child theme đã active sẵn nên
     * không dựa vào after_switch_theme được.
     */
    public static function maybeInstall(): void
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        // dbDelta yêu cầu đúng format: 2 dấu cách sau PRIMARY KEY, mỗi cột 1 dòng.
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            lang varchar(10) NOT NULL DEFAULT '',
            source_url varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY created_at (created_at)
        ) {$charset};");

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    /**
     * @return bool true nếu thêm mới, false nếu email đã tồn tại.
     */
    public static function add(string $email, string $lang, string $source_url): bool
    {
        global $wpdb;
        $table = self::table();

        // INSERT IGNORE + UNIQUE(email): chặn trùng nguyên tử, không bị race
        // khi 2 request cùng email tới gần như đồng thời.
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (email, lang, source_url, created_at) VALUES (%s, %s, %s, %s)",
            $email,
            $lang,
            $source_url,
            current_time('mysql')
        ));

        return $wpdb->rows_affected > 0;
    }

    public static function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::table(), ['id' => $id], ['%d']);
    }

    public static function count(string $search = ''): int
    {
        global $wpdb;
        $table = self::table();

        if ($search === '') {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE email LIKE %s",
            '%' . $wpdb->esc_like($search) . '%'
        ));
    }

    /**
     * @param int $limit 0 = lấy tất cả (dùng khi xuất CSV).
     */
    public static function query(string $search = '', int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = self::table();

        $where = '';
        $args = [];
        if ($search !== '') {
            $where = 'WHERE email LIKE %s';
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $sql = "SELECT id, email, lang, source_url, created_at FROM {$table} {$where} ORDER BY created_at DESC, id DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $args[] = $limit;
            $args[] = $offset;
        }

        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, $args);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
}

add_action('init', [NewsletterSubscribers::class, 'maybeInstall']);
