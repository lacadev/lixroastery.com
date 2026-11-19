<?php

namespace App\Features\FrontendChatbot;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nhật ký hội thoại chatbot — để admin biết khách hỏi gì, câu nào chưa trả
 * lời được (gợi ý viết bài/FAQ) và câu trả lời được đánh giá ra sao.
 *
 * Chỉ lưu khi bật (option laca_chatbot_log_enabled), tự xoá sau N ngày
 * (laca_chatbot_log_days, mặc định 30). Không lưu IP — chỉ mã phiên ngẫu
 * nhiên do trình duyệt tạo để gom các câu của cùng một cuộc chat.
 */
class ChatbotLog
{
    public const TABLE_VERSION = '1';
    private const VERSION_KEY  = 'laca_chatbot_log_db_version';
    public const CRON_HOOK     = 'laca_chatbot_log_purge';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_chatbot_log';
    }

    public static function enabled(): bool
    {
        return get_option('laca_chatbot_log_enabled', '1') === '1';
    }

    public static function days(): int
    {
        return max(1, min(365, (int) get_option('laca_chatbot_log_days', 30)));
    }

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'install']);
        add_action(self::CRON_HOOK, [self::class, 'purge']);
        // Gọi từ FrontendChatbotHandler::init() (đang trong hook init) → lên lịch luôn.
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function install(): void
    {
        if (get_option(self::VERSION_KEY) === self::TABLE_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session     CHAR(32) NOT NULL DEFAULT '',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            lang        VARCHAR(10) NOT NULL DEFAULT '',
            page_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            question    TEXT NOT NULL,
            answer      TEXT NOT NULL,
            found       TINYINT(1) NOT NULL DEFAULT 0,
            source      VARCHAR(10) NOT NULL DEFAULT 'ai',
            rating      TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY found (found),
            KEY session (session)
        ) {$charset};");
        update_option(self::VERSION_KEY, self::TABLE_VERSION, false);
    }

    /** @return int ID dòng log (0 nếu tắt log / lỗi). */
    public static function add(string $session, string $lang, int $pageId, string $question, string $answer, bool $found, string $source): int
    {
        if (!self::enabled()) {
            return 0;
        }
        self::install();
        global $wpdb;
        $ok = $wpdb->insert(self::table(), [
            'session'  => substr(preg_replace('/[^a-f0-9]/', '', $session), 0, 32),
            'lang'     => substr($lang, 0, 10),
            'page_id'  => max(0, $pageId),
            'question' => mb_substr($question, 0, 500),
            'answer'   => mb_substr($answer, 0, 3000),
            'found'    => $found ? 1 : 0,
            'source'   => substr($source, 0, 10),
        ]);
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /** Đánh giá 👍(1)/👎(-1) — phải đúng mã phiên để không ai sửa log người khác. */
    public static function rate(int $id, string $session, int $rating): bool
    {
        if ($id <= 0 || $session === '' || !in_array($rating, [-1, 1], true)) {
            return false;
        }
        global $wpdb;
        return (bool) $wpdb->update(self::table(), ['rating' => $rating], ['id' => $id, 'session' => $session]);
    }

    public static function purge(): void
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::table())) !== self::table()) {
            return;
        }
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE created_at < (NOW() - INTERVAL %d DAY)',
            self::days()
        ));
    }

    /** @return array{total:int,sessions:int,unanswered:int,up:int,down:int,faq:int} */
    public static function stats(int $days = 30): array
    {
        global $wpdb;
        $empty = ['total' => 0, 'sessions' => 0, 'unanswered' => 0, 'up' => 0, 'down' => 0, 'faq' => 0];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::table())) !== self::table()) {
            return $empty;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) total, COUNT(DISTINCT session) sessions, SUM(found = 0) unanswered,
                    SUM(rating = 1) up, SUM(rating = -1) down, SUM(source = %s) faq
             FROM ' . self::table() . ' WHERE created_at >= (NOW() - INTERVAL %d DAY)',
            'faq',
            $days
        ), ARRAY_A);
        return array_map('intval', array_merge($empty, (array) $row));
    }

    /** Câu chưa trả lời được (không tìm thấy nội dung), gom câu trùng. */
    public static function unanswered(int $limit = 20, int $days = 30): array
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::table())) !== self::table()) {
            return [];
        }
        return (array) $wpdb->get_results($wpdb->prepare(
            'SELECT LOWER(TRIM(question)) q, COUNT(*) n, MAX(created_at) last_at
             FROM ' . self::table() . ' WHERE found = 0 AND created_at >= (NOW() - INTERVAL %d DAY)
             GROUP BY q ORDER BY n DESC, last_at DESC LIMIT %d',
            $days,
            $limit
        ), ARRAY_A);
    }

    public static function recent(int $limit = 30): array
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::table())) !== self::table()) {
            return [];
        }
        return (array) $wpdb->get_results($wpdb->prepare(
            'SELECT id, session, created_at, lang, page_id, question, answer, found, source, rating
             FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d',
            $limit
        ), ARRAY_A);
    }
}
