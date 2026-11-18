<?php

namespace App\Settings\Security\TrafficMonitor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quản lý bảng DB `{$wpdb->prefix}laca_tm_visits` và `{$wpdb->prefix}laca_tm_events`.
 * Cài đặt ở admin_init (transient self-heal 12h), dọn dữ liệu cũ theo retention_days.
 */
final class TrafficTable
{
    public const TABLE_VERSION   = '1.0.0';
    public const VERSION_KEY     = 'laca_tm_db_version';
    public const CHECK_TRANSIENT = 'laca_tm_table_check';
    public const CLEANUP_HOOK    = 'laca_tm_daily_cleanup';
    public const CACHE_GROUP     = 'laca_tm';

    public function register(): void
    {
        add_action('admin_init', [self::class, 'onAdminInit']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
    }

    public static function onAdminInit(): void
    {
        self::install();
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function visitsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_tm_visits';
    }

    public static function eventsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_tm_events';
    }

    public static function isReady(): bool
    {
        return get_option(self::VERSION_KEY) === self::TABLE_VERSION;
    }

    public static function install(bool $force = false): void
    {
        global $wpdb;

        $visitsTable = self::visitsTable();
        $eventsTable = self::eventsTable();
        $collate     = $wpdb->get_charset_collate();

        if (!$force && get_option(self::VERSION_KEY) === self::TABLE_VERSION) {
            if (is_admin()) {
                $checked = get_transient(self::CHECK_TRANSIENT);
                if ($checked === false) {
                    $vExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($visitsTable)));
                    $eExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($eventsTable)));
                    if ($vExists === $visitsTable && $eExists === $eventsTable) {
                        set_transient(self::CHECK_TRANSIENT, 1, 12 * HOUR_IN_SECONDS);
                        return;
                    }
                } else {
                    return;
                }
            } else {
                return;
            }
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sqlVisits = "CREATE TABLE {$visitsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            day date NOT NULL,
            ip varchar(45) NOT NULL,
            category varchar(24) NOT NULL DEFAULT 'unknown',
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            suspicious bigint(20) unsigned NOT NULL DEFAULT 0,
            not_found bigint(20) unsigned NOT NULL DEFAULT 0,
            last_seen datetime NOT NULL,
            ua varchar(255) NOT NULL DEFAULT '',
            reasons text NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY day_ip (day,ip),
            KEY day_hits (day,hits),
            KEY category (category)
        ) {$collate};";

        $sqlEvents = "CREATE TABLE {$eventsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip varchar(45) NOT NULL,
            visited_at datetime NOT NULL,
            path varchar(500) NOT NULL DEFAULT '',
            referrer varchar(500) NOT NULL DEFAULT '',
            category varchar(24) NOT NULL DEFAULT 'unknown',
            reason varchar(255) NOT NULL DEFAULT '',
            method varchar(8) NOT NULL DEFAULT 'GET',
            status_code smallint(5) unsigned NOT NULL DEFAULT 0,
            ua varchar(255) NOT NULL DEFAULT '',
            score tinyint(3) unsigned NOT NULL DEFAULT 0,
            action varchar(16) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY ip_time (ip,visited_at),
            KEY visited_at (visited_at),
            KEY ip_status (ip,status_code),
            KEY path_time (path(191),visited_at),
            KEY category (category)
        ) {$collate};";

        dbDelta($sqlVisits);
        dbDelta($sqlEvents);

        update_option(self::VERSION_KEY, self::TABLE_VERSION, 'yes');
        set_transient(self::CHECK_TRANSIENT, 1, 12 * HOUR_IN_SECONDS);
    }

    /**
     * Xoá dữ liệu quá hạn theo lô LIMIT 5000 (tối đa 20 vòng) tránh khoá bảng lớn.
     */
    public static function cleanup(): void
    {
        if (!self::isReady()) {
            return;
        }

        global $wpdb;
        $days       = max(1, min(90, (int) (TrafficSettings::settings()['retention_days'] ?? 14)));
        $cutoffDay  = wp_date('Y-m-d', time() - $days * DAY_IN_SECONDS);
        $cutoffTime = $cutoffDay . ' 00:00:00';

        $visitsTable = self::visitsTable();
        $eventsTable = self::eventsTable();
        $batchSize   = 5000;

        for ($i = 0; $i < 20; $i++) {
            $deleted = (int) $wpdb->query(
                $wpdb->prepare("DELETE FROM {$visitsTable} WHERE day < %s LIMIT %d", $cutoffDay, $batchSize)
            );
            if ($deleted < $batchSize) {
                break;
            }
        }

        for ($i = 0; $i < 20; $i++) {
            $deleted = (int) $wpdb->query(
                $wpdb->prepare("DELETE FROM {$eventsTable} WHERE visited_at < %s LIMIT %d", $cutoffTime, $batchSize)
            );
            if ($deleted < $batchSize) {
                break;
            }
        }
    }

    /**
     * Xoá toàn bộ dữ liệu 2 bảng bằng TRUNCATE (chỉ gọi từ handler đã xác thực nonce + manage_options).
     */
    public static function purge(): void
    {
        if (!self::isReady()) {
            return;
        }

        global $wpdb;
        $visitsTable = self::visitsTable();
        $eventsTable = self::eventsTable();

        $wpdb->query("TRUNCATE TABLE {$visitsTable}");
        $wpdb->query("TRUNCATE TABLE {$eventsTable}");

        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }
}
