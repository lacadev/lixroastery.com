<?php

namespace App\Features\DiscordHub\FileWatch;

/**
 * Baseline hash của từng file (bảng riêng — ~15.000 dòng, không nhét vào wp_options).
 *
 * Mỗi dòng ghi luôn "nguồn" của lần thay đổi được chấp nhận gần nhất (deploy / File
 * Editor / cập nhật trong WP / quét) để tra cứu khi cần.
 */
final class Baseline
{
    private const DB_VERSION = '1';
    private const OPTION_DB = 'laca_discord_files_db';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_discord_files';
    }

    public static function ensureSchema(): bool
    {
        if (self::DB_VERSION === get_option(self::OPTION_DB)) {
            return true;
        }

        global $wpdb;
        $table = self::table();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(
            "CREATE TABLE {$table} (
  path_hash char(40) NOT NULL,
  path text NOT NULL,
  size bigint(20) unsigned NOT NULL DEFAULT 0,
  mtime int(10) unsigned NOT NULL DEFAULT 0,
  hash char(64) NOT NULL DEFAULT '',
  source varchar(191) NOT NULL DEFAULT '',
  updated_at int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (path_hash)
) {$wpdb->get_charset_collate()};"
        );

        $ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
        if ($ok) {
            update_option(self::OPTION_DB, self::DB_VERSION, true);
        }
        return $ok;
    }

    public static function isEmpty(): bool
    {
        global $wpdb;
        return 0 === (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- không có input.
    }

    public static function count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- không có input.
    }

    /**
     * Bản đồ gọn path => "size:mtime" để so nhanh, nạp theo lô (keyset pagination).
     *
     * Không nạp cả bảng 1 lần qua wpdb: mỗi dòng thành 1 stdClass + 1 mảng → ~18MB cho
     * 14k file, trong khi WP + WooCommerce đã chiếm ~110MB → vượt memory_limit 128MB
     * (lỗi thật khi test). Hash chỉ được đọc khi file có dấu hiệu đổi (get()).
     *
     * @return array<string,string>
     */
    public static function compactMap(): array
    {
        global $wpdb;
        $table = self::table();
        $map = [];
        $cursor = '';
        do {
            $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT path_hash, path, size, mtime FROM {$table} WHERE path_hash > %s ORDER BY path_hash LIMIT 2000", $cursor), ARRAY_N); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            foreach ($rows as $row) {
                $map[(string) $row[1]] = $row[2] . ':' . $row[3];
                $cursor = (string) $row[0];
            }
            $wpdb->flush();
        } while (2000 === count($rows));

        return $map;
    }

    public static function get(string $path): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT size, mtime, hash FROM ' . self::table() . ' WHERE path_hash = %s', sha1($path)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $row ? ['size' => (int) $row['size'], 'mtime' => (int) $row['mtime'], 'hash' => (string) $row['hash']] : null;
    }

    public static function upsert(string $path, int $size, int $mtime, string $hash, string $source): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . self::table() . ' (path_hash, path, size, mtime, hash, source, updated_at) VALUES (%s, %s, %d, %d, %s, %s, %d)
            ON DUPLICATE KEY UPDATE size = VALUES(size), mtime = VALUES(mtime), hash = VALUES(hash), source = VALUES(source), updated_at = VALUES(updated_at)', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            sha1($path),
            $path,
            $size,
            $mtime,
            $hash,
            mb_substr($source, 0, 191),
            time()
        ));
    }

    public static function delete(string $path): void
    {
        global $wpdb;
        $wpdb->delete(self::table(), ['path_hash' => sha1($path)]);
    }

    /**
     * Chấp nhận trạng thái HIỆN TẠI trên đĩa của 1 file làm baseline (sau deploy/File Editor/cập nhật).
     */
    public static function acceptFile(string $path, string $source): void
    {
        $abs = Scope::abs($path);
        clearstatcache(true, $abs);
        if (!is_file($abs)) {
            self::delete($path);
            return;
        }
        $hash = (string) hash_file('sha256', $abs);
        self::upsert($path, (int) filesize($abs), (int) filemtime($abs), $hash, $source);
    }

    /**
     * Chấp nhận trạng thái hiện tại của cả 1 thư mục (plugin/theme vừa cài/cập nhật/xoá).
     */
    public static function acceptTree(string $relDir, string $source): int
    {
        $relDir = trim(wp_normalize_path($relDir), '/');
        $seen = [];
        foreach (Scope::walk($relDir) as $rel => $abs) {
            $seen[$rel] = true;
            self::upsert($rel, (int) filesize($abs), (int) filemtime($abs), (string) hash_file('sha256', $abs), $source);
        }

        // Xoá dòng của file không còn tồn tại trong thư mục.
        global $wpdb;
        $like = $wpdb->esc_like($relDir . '/') . '%';
        $existing = (array) $wpdb->get_col($wpdb->prepare('SELECT path FROM ' . self::table() . ' WHERE path LIKE %s', $like)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ($existing as $path) {
            if (!isset($seen[$path])) {
                self::delete((string) $path);
            }
        }
        return count($seen);
    }

    public static function drop(): void
    {
        global $wpdb;
        $wpdb->query('DROP TABLE IF EXISTS ' . self::table()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- không có input.
        delete_option(self::OPTION_DB);
    }
}
