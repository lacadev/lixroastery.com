<?php

/**
 * Newsletter Subscribers — lớp truy cập bảng lưu email đăng ký ở footer.
 *
 * DB của site là nguồn dữ liệu chính; Google Sheet / Mailchimp / Brevo
 * (NewsletterSync) chỉ là bản đồng bộ phụ — dịch vụ ngoài lỗi thì email
 * vẫn không mất. Dùng bảng riêng thay vì CPT để danh sách lớn vẫn nhẹ (không
 * đụng wp_posts/wp_postmeta) và có UNIQUE(email) chặn trùng ở tầng DB.
 *
 * Vòng đời: pending (chờ bấm link xác nhận trong email) → subscribed →
 * unsubscribed (bấm link huỷ, hoặc huỷ từ Mailchimp/Brevo qua webhook).
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterSubscribers
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_SUBSCRIBED, self::STATUS_UNSUBSCRIBED];

    private const DB_VERSION = '1.1';
    private const DB_VERSION_OPTION = 'lix_newsletter_db_version';
    private const COLUMNS = 'id, email, lang, source_url, status, created_at, confirm_sent_at, confirmed_at, unsubscribed_at';

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
        $installed = get_option(self::DB_VERSION_OPTION);
        if ($installed === self::DB_VERSION) {
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
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            confirm_sent_at datetime DEFAULT NULL,
            confirmed_at datetime DEFAULT NULL,
            unsubscribed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};");

        // Bản 1.0 chưa có xác nhận qua email — email đăng ký từ trước coi như
        // đã đồng ý (đã tick nhận tin ở form), chuyển thẳng sang subscribed.
        if ($installed === '1.0') {
            $wpdb->query("UPDATE {$table} SET status = 'subscribed', confirmed_at = created_at WHERE status = 'pending' AND confirmed_at IS NULL");
        }

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public static function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT ' . self::COLUMNS . ' FROM ' . self::table() . ' WHERE id = %d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT ' . self::COLUMNS . ' FROM ' . self::table() . ' WHERE email = %s', $email), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Thêm email mới ở trạng thái pending.
     *
     * @return array|null Dòng vừa tạo; null nếu email đã tồn tại (có thể do
     *                    request khác vừa chèn cùng lúc).
     */
    public static function addPending(string $email, string $lang, string $source_url): ?array
    {
        global $wpdb;
        $table = self::table();

        // INSERT IGNORE + UNIQUE(email): chặn trùng nguyên tử, không bị race
        // khi 2 request cùng email tới gần như đồng thời.
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (email, lang, source_url, status, created_at) VALUES (%s, %s, %s, %s, %s)",
            $email,
            $lang,
            $source_url,
            self::STATUS_PENDING,
            current_time('mysql')
        ));

        return $wpdb->rows_affected > 0 ? self::find((int) $wpdb->insert_id) : null;
    }

    /**
     * Đổi trạng thái + ghi mốc thời gian tương ứng.
     *
     * @param array $extra Cột bổ sung cần cập nhật (vd lang khi đăng ký lại).
     */
    public static function setStatus(int $id, string $status, array $extra = []): ?array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return null;
        }

        $data = array_merge($extra, ['status' => $status]);
        if ($status === self::STATUS_SUBSCRIBED) {
            $data['confirmed_at'] = current_time('mysql');
            $data['unsubscribed_at'] = null;
        } elseif ($status === self::STATUS_UNSUBSCRIBED) {
            $data['unsubscribed_at'] = current_time('mysql');
        }

        global $wpdb;
        $wpdb->update(self::table(), $data, ['id' => $id]);

        return self::find($id);
    }

    public static function markConfirmSent(int $id): void
    {
        global $wpdb;
        $wpdb->update(self::table(), ['confirm_sent_at' => current_time('mysql')], ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::table(), ['id' => $id], ['%d']);
    }

    /**
     * @return array<string, int> Số email theo từng trạng thái (+ 'all').
     */
    public static function countByStatus(): array
    {
        global $wpdb;
        $counts = array_fill_keys(self::STATUSES, 0);

        foreach ($wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY status', ARRAY_A) ?: [] as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        $counts['all'] = array_sum($counts);

        return $counts;
    }

    public static function count(string $search = '', string $status = ''): int
    {
        global $wpdb;
        [$where, $args] = self::buildWhere($search, $status);
        $sql = 'SELECT COUNT(*) FROM ' . self::table() . ' ' . $where;

        return (int) $wpdb->get_var($args ? $wpdb->prepare($sql, $args) : $sql);
    }

    /**
     * @param int $limit 0 = lấy tất cả (dùng khi xuất CSV).
     */
    public static function query(string $search = '', int $limit = 50, int $offset = 0, string $status = ''): array
    {
        global $wpdb;
        [$where, $args] = self::buildWhere($search, $status);

        $sql = 'SELECT ' . self::COLUMNS . ' FROM ' . self::table() . " {$where} ORDER BY created_at DESC, id DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $args[] = $limit;
            $args[] = $offset;
        }

        return $wpdb->get_results($args ? $wpdb->prepare($sql, $args) : $sql, ARRAY_A) ?: [];
    }

    /**
     * @return array{0: string, 1: array}
     */
    private static function buildWhere(string $search, string $status): array
    {
        global $wpdb;
        $clauses = [];
        $args = [];

        if ($search !== '') {
            $clauses[] = 'email LIKE %s';
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }
        if (in_array($status, self::STATUSES, true)) {
            $clauses[] = 'status = %s';
            $args[] = $status;
        }

        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $args];
    }
}

add_action('init', [NewsletterSubscribers::class, 'maybeInstall']);
