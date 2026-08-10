<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * File Integrity Monitor (FIM)
 *
 * Tạo baseline MD5 toàn bộ file, phát hiện Modified / Added / Deleted.
 * Port logic từ foxblock_scan_core_files_callback() trong Foxblock/admin/admin.php.
 *
 * Options:
 *   laca_file_baseline       — serialized array [rel_path => md5]
 *   laca_file_baseline_time  — datetime string
 */
class FileIntegrityMonitor
{
    /** Extensions được theo dõi */
    private static array $extensions = ['php', 'js', 'json', 'htaccess', 'sh'];

    /** Thư mục bỏ qua */
    private static array $excludeDirs = [
        'wp-content/uploads/',
        'wp-content/cache/',
        'wp-content/backups/',
        'wp-content/updraft/',
    ];

    // ── Lấy danh sách file ──────────────────────────────────────────────────

    public static function getFileList(): array
    {
        $base    = wp_normalize_path(ABSPATH);
        $files   = [];
        try {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iter as $file) {
                if (!$file->isFile()) continue;
                $abs = wp_normalize_path($file->getPathname());
                $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                if (!in_array($ext, self::$extensions, true)) continue;
                $rel = ltrim(str_replace($base, '', $abs), '/');
                if (self::isExcluded($rel)) continue;
                $files[$rel] = $abs;
            }
        } catch (\Exception $e) {
            // ignore unreadable dirs
        }
        return $files;
    }

    private static function isExcluded(string $rel): bool
    {
        foreach (self::$excludeDirs as $dir) {
            if (str_starts_with($rel, $dir)) return true;
        }
        return false;
    }

    // ── Khởi tạo baseline ───────────────────────────────────────────────────

    public static function createBaseline(): array
    {
        $files    = self::getFileList();
        $baseline = [];
        foreach ($files as $rel => $abs) {
            $hash = @md5_file($abs);
            if ($hash !== false) $baseline[$rel] = $hash;
        }
        update_option('laca_file_baseline', $baseline);
        update_option('laca_file_baseline_time', current_time('mysql'));
        return ['total' => count($baseline), 'time' => current_time('mysql')];
    }

    // ── So sánh với baseline ─────────────────────────────────────────────────

    public static function compareBaseline(): array
    {
        $baseline = get_option('laca_file_baseline', false);
        if ($baseline === false || empty($baseline)) {
            return ['needs_init' => true];
        }

        $current  = self::getFileList();
        $results  = ['modified' => [], 'deleted' => [], 'added' => []];

        foreach ($baseline as $rel => $expectedHash) {
            $abs = wp_normalize_path(ABSPATH . $rel);
            if (!file_exists($abs)) {
                $results['deleted'][] = ['path' => $rel, 'time' => '-'];
            } elseif (md5_file($abs) !== $expectedHash) {
                $results['modified'][] = ['path' => $rel, 'time' => date('Y-m-d H:i:s', filemtime($abs))];
            }
        }

        foreach ($current as $rel => $abs) {
            if (!isset($baseline[$rel])) {
                $results['added'][] = ['path' => $rel, 'time' => date('Y-m-d H:i:s', filemtime($abs))];
            }
        }

        $results['total']     = count($results['modified']) + count($results['deleted']) + count($results['added']);
        $results['base_time'] = get_option('laca_file_baseline_time', 'Chưa xác định');
        $results['needs_init']= false;
        return $results;
    }

    // ── Cập nhật baseline ─────────────────────────────────────────────────────

    public static function updateBaseline(): array
    {
        return self::createBaseline();
    }

    // ── Trạng thái hiện tại ───────────────────────────────────────────────────

    public static function getStatus(): array
    {
        $baseline = get_option('laca_file_baseline', false);
        return [
            'has_baseline' => $baseline !== false && !empty($baseline),
            'baseline_time'=> get_option('laca_file_baseline_time', ''),
            'file_count'   => $baseline ? count($baseline) : 0,
        ];
    }
}
