<?php

namespace App\Features\DiscordHub;

/**
 * Hàng đợi bền vững (bảng riêng) cho embed chờ gửi.
 *
 * Vì sao không dùng option/transient: nhiều request đồng thời read-modify-write
 * cùng 1 option sẽ ghi đè nhau → mất sự kiện. Bảng riêng cho phép:
 *  - INSERT nguyên tử, không mất sự kiện khi spam đồng thời;
 *  - "claim" nguyên tử bằng UPDATE ... LIMIT (nhiều worker không gửi trùng);
 *  - gộp sự kiện trùng (debounce) bằng UNIQUE dedupe_key + ON DUPLICATE KEY UPDATE hits+1;
 *  - lease hết hạn → tự nhặt lại khi worker chết giữa chừng.
 */
final class Queue
{
    const DB_VERSION = '1';
    const OPTION_DB  = 'laca_discord_queue_db';

    /** Chặn lũ: vượt ngưỡng thì gộp phần dư vào 1 dòng "quá tải" duy nhất. */
    const MAX_PENDING_PER_CHANNEL = 300;

    /** Sự kiện không gửi được sau 2 ngày thì bỏ (Discord/webhook hỏng kéo dài). */
    const MAX_AGE = 2 * DAY_IN_SECONDS;

    /** @var bool|null */
    private static $ready = null;

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_discord_queue';
    }

    public static function ensureSchema(): bool
    {
        if (null !== self::$ready) {
            return self::$ready;
        }
        if (self::DB_VERSION === get_option(self::OPTION_DB)) {
            return self::$ready = true;
        }
        // Lần tạo bảng trước đã lỗi (thiếu quyền CREATE?) → không thử lại mỗi request.
        if (get_transient('laca_dh_schema_failed')) {
            return self::$ready = false;
        }

        global $wpdb;
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(
            "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  channel varchar(20) NOT NULL,
  dedupe_key varchar(191) DEFAULT NULL,
  payload longtext NOT NULL,
  hits int(10) unsigned NOT NULL DEFAULT 1,
  attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
  available_at int(10) unsigned NOT NULL DEFAULT 0,
  claim_token char(32) DEFAULT NULL,
  claimed_until int(10) unsigned NOT NULL DEFAULT 0,
  created_at int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY dedupe_key (dedupe_key),
  KEY channel_ready (channel,available_at),
  KEY claim_token (claim_token)
) {$charset};"
        );

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
        if ($exists) {
            update_option(self::OPTION_DB, self::DB_VERSION, true);
        } else {
            set_transient('laca_dh_schema_failed', 1, HOUR_IN_SECONDS);
            error_log('[laca-discord-hub] Không tạo được bảng hàng đợi ' . $table); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }
        return self::$ready = $exists;
    }

    /**
     * Đưa 1 embed vào hàng đợi.
     *
     * @param string|null $dedupeKey Sự kiện cùng key khi đang chờ gửi sẽ gộp thành 1 dòng (hits+1).
     */
    public static function push(string $channel, array $embed, ?string $dedupeKey = null): bool
    {
        if (!self::ensureSchema()) {
            return false;
        }

        global $wpdb;
        $table = self::table();

        $pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE channel = %s", $channel));
        if ($pending >= self::MAX_PENDING_PER_CHANNEL) {
            $embed     = Embed::make('⚠️ Hàng đợi quá tải — đang bỏ bớt sự kiện', Embed::COLOR_DANGER)
                ->description('Kênh **' . $channel . '** nhận quá nhiều sự kiện cùng lúc (spam form/brute-force?). Các sự kiện vượt ngưỡng ' . self::MAX_PENDING_PER_CHANNEL . ' đã bị bỏ, chỉ đếm số lượng.', true)
                ->toArray();
            $dedupeKey = 'overflow:' . $channel;
        }

        $payload = wp_json_encode($embed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (false === $payload) {
            return false;
        }

        $now = time();
        if (null === $dedupeKey) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (channel, dedupe_key, payload, available_at, created_at) VALUES (%s, NULL, %s, %d, %d)",
                    $channel,
                    $payload,
                    $now,
                    $now
                )
            );
        } else {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (channel, dedupe_key, payload, available_at, created_at) VALUES (%s, %s, %s, %d, %d)
                    ON DUPLICATE KEY UPDATE hits = hits + 1",
                    $channel,
                    substr($channel . ':' . $dedupeKey, 0, 191),
                    $payload,
                    $now,
                    $now
                )
            );
        }

        return false !== $result;
    }

    /**
     * Claim nguyên tử tối đa $limit dòng sẵn sàng của 1 kênh.
     * dedupe_key bị xoá khi claim để sự kiện mới cùng key tạo dòng mới thay vì cộng vào dòng đang gửi.
     *
     * @return array<int,array{id:int,payload:string,hits:int,attempts:int}>
     */
    public static function claim(string $channel, int $limit, int $leaseSeconds): array
    {
        global $wpdb;
        $table = self::table();
        $now   = time();
        $token = wp_generate_password(32, false);

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET claim_token = %s, claimed_until = %d, dedupe_key = NULL
                WHERE channel = %s AND available_at <= %d AND claimed_until < %d
                ORDER BY id ASC LIMIT %d",
                $token,
                $now + $leaseSeconds,
                $channel,
                $now,
                $now,
                $limit
            )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id, payload, hits, attempts FROM {$table} WHERE claim_token = %s ORDER BY id ASC", $token),
            ARRAY_A
        );

        return array_map(
            static function ($row) {
                return [
                    'id'       => (int) $row['id'],
                    'payload'  => (string) $row['payload'],
                    'hits'     => (int) $row['hits'],
                    'attempts' => (int) $row['attempts'],
                ];
            },
            (array) $rows
        );
    }

    /**
     * @param int[] $ids
     */
    public static function delete(array $ids): void
    {
        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (!$ids) {
            return;
        }
        global $wpdb;
        $table        = self::table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids));
    }

    /**
     * Trả dòng về hàng đợi (không gửi được lần này).
     *
     * @param int[] $ids
     */
    public static function release(array $ids, int $availableAt, bool $countAttempt): void
    {
        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (!$ids) {
            return;
        }
        global $wpdb;
        $table        = self::table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $args         = array_merge([$availableAt, $countAttempt ? 1 : 0], $ids);
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET claim_token = NULL, claimed_until = 0, available_at = %d, attempts = LEAST(attempts + %d, 255)
                WHERE id IN ({$placeholders})",
                $args
            )
        );
    }

    /**
     * @return string[]
     */
    public static function readyChannels(): array
    {
        if (!self::ensureSchema()) {
            return [];
        }
        global $wpdb;
        $table = self::table();
        $now   = time();
        return array_map(
            'strval',
            (array) $wpdb->get_col(
                $wpdb->prepare("SELECT DISTINCT channel FROM {$table} WHERE available_at <= %d AND claimed_until < %d", $now, $now)
            )
        );
    }

    /**
     * Thời điểm sớm nhất có dòng cần xử lý (để hẹn lịch cron retry), null nếu hàng đợi rỗng.
     */
    public static function nextDueAt(): ?int
    {
        if (!self::ensureSchema()) {
            return null;
        }
        global $wpdb;
        $table = self::table();
        $next  = $wpdb->get_var("SELECT MIN(GREATEST(available_at, claimed_until)) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- không có input.
        return null === $next ? null : (int) $next;
    }

    public static function purgeExpired(): int
    {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %d AND claimed_until < %d", time() - self::MAX_AGE, time()));
    }

    public static function makeReady(string $channel): void
    {
        if (!self::ensureSchema()) {
            return;
        }
        global $wpdb;
        $table = self::table();
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET available_at = %d WHERE channel = %s AND claimed_until < %d", time(), $channel, time()));
    }

    public static function dropChannel(string $channel): int
    {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE channel = %s", $channel));
    }

    /**
     * @return array<string,int> số dòng theo kênh
     */
    public static function stats(): array
    {
        if (!self::ensureSchema()) {
            return [];
        }
        global $wpdb;
        $table = self::table();
        $rows  = (array) $wpdb->get_results("SELECT channel, COUNT(*) AS total FROM {$table} GROUP BY channel", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- không có input.
        $out   = [];
        foreach ($rows as $row) {
            $out[(string) $row['channel']] = (int) $row['total'];
        }
        return $out;
    }
}
