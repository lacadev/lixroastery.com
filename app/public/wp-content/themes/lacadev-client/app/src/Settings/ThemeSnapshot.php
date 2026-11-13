<?php

namespace App\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quản lý snapshot dự phòng và khôi phục (rollback) cho Parent Theme lacadev-client.
 */
class ThemeSnapshot
{
    public const MAX_SNAPSHOTS = 3;

    /**
     * Trả về thư mục lưu trữ snapshot của theme (kèm .htaccess và index.php bảo vệ).
     */
    public static function dir(): string
    {
        $suffix  = substr(wp_hash('laca-snap'), 0, 12);
        $baseDir = WP_CONTENT_DIR . '/laca-backups';
        $snapDir = $baseDir . '/themes-' . $suffix;

        self::ensureProtectedDir($baseDir);
        self::ensureProtectedDir($snapDir);

        return $snapDir;
    }

    /**
     * Tạo thư mục kèm index.php và .htaccess chặn truy cập trực tiếp.
     */
    private static function ensureProtectedDir(string $dir): void
    {
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $indexFile = $dir . '/index.php';
        if (!file_exists($indexFile)) {
            @file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }

        $htaccessFile = $dir . '/htaccess.tmp';
        $targetHtaccess = $dir . '/.htaccess';
        if (!file_exists($targetHtaccess)) {
            unset($htaccessFile);
            @file_put_contents($targetHtaccess, "Require all denied\n");
        }
    }

    /**
     * Đóng gói thư mục hiện tại của lacadev-client thành gói snapshot zip trước khi cập nhật.
     */
    public static function create(string $themeRoot, string $version): string|\WP_Error
    {
        if (!class_exists('\ZipArchive')) {
            return new \WP_Error(
                'laca_snapshot_zip_missing',
                __('Máy chủ thiếu tiện ích mở rộng ZipArchive để tạo bản sao lưu theme.', 'laca')
            );
        }

        $realThemeRoot = realpath($themeRoot);
        if ($realThemeRoot === false || !is_dir($realThemeRoot)) {
            return new \WP_Error(
                'laca_snapshot_source_missing',
                __('Không tìm thấy thư mục gốc của parent theme để tạo snapshot.', 'laca')
            );
        }

        $safeVer = preg_replace('/[^a-zA-Z0-9._-]/', '-', trim($version)) ?: 'unknown';
        $stamp   = gmdate('Ymd-His');
        $snapDir = self::dir();

        $fileName = "lacadev-client-{$safeVer}-{$stamp}.zip";
        $zipPath  = $snapDir . '/' . $fileName;
        $counter  = 1;
        while (file_exists($zipPath)) {
            $fileName = "lacadev-client-{$safeVer}-{$stamp}-{$counter}.zip";
            $zipPath  = $snapDir . '/' . $fileName;
            $counter++;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new \WP_Error(
                'laca_snapshot_open_failed',
                __('Không thể khởi tạo file zip snapshot cho theme.', 'laca')
            );
        }

        $prefixLen = strlen($realThemeRoot) + 1;
        $iterator  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realThemeRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $fullPath = $item->getPathname();
            if ($item->isLink()) {
                continue;
            }

            $relPath = str_replace('\\', '/', substr($fullPath, $prefixLen));
            if ($relPath === '' || $relPath === 'node_modules' || str_starts_with($relPath, 'node_modules/') || str_contains($relPath, '/node_modules/')) {
                continue;
            }

            $entryName = 'lacadev-client/' . $relPath;
            if ($item->isDir()) {
                $zip->addEmptyDir($entryName);
            } elseif ($item->isFile()) {
                $zip->addFile($fullPath, $entryName);
            }
        }

        if (!$zip->close() || !is_file($zipPath)) {
            return new \WP_Error(
                'laca_snapshot_write_failed',
                __('Không thể hoàn tất ghi dữ liệu snapshot cho theme.', 'laca')
            );
        }

        self::pruneOldSnapshots();

        return $zipPath;
    }

    /**
     * Liệt kê các bản snapshot hiện có (mới nhất lên đầu).
     *
     * @return array<int, array{file: string, path: string, version: string, size: int, mtime: int, created_at: string}>
     */
    public static function list(): array
    {
        $snapDir = self::dir();
        if (!is_dir($snapDir)) {
            return [];
        }

        $entries = scandir($snapDir);
        if (!is_array($entries)) {
            return [];
        }

        $items = [];
        foreach ($entries as $entry) {
            if (!preg_match('/^lacadev-client-(.+)-(\d{8}-\d{6})(?:-\d+)?\.zip$/', $entry, $matches)) {
                continue;
            }

            $fullPath = $snapDir . '/' . $entry;
            if (!is_file($fullPath)) {
                continue;
            }

            $mtime   = (int) (@filemtime($fullPath) ?: 0);
            $items[] = [
                'file'       => $entry,
                'path'       => $fullPath,
                'version'    => $matches[1],
                'size'       => (int) (@filesize($fullPath) ?: 0),
                'mtime'      => $mtime,
                'created_at' => gmdate('Y-m-d H:i:s', $mtime),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ($b['mtime'] !== $a['mtime']) {
                return $b['mtime'] <=> $a['mtime'];
            }
            return strcmp($b['file'], $a['file']);
        });

        return $items;
    }

    /**
     * Giữ tối đa MAX_SNAPSHOTS bản mới nhất do chính lớp này tạo trong dir().
     */
    private static function pruneOldSnapshots(): void
    {
        $items = self::list();
        if (count($items) <= self::MAX_SNAPSHOTS) {
            return;
        }

        $snapDir = trailingslashit(realpath(self::dir()) ?: self::dir());
        foreach (array_slice($items, self::MAX_SNAPSHOTS) as $old) {
            $realOld = realpath($old['path']);
            if ($realOld !== false && str_starts_with($realOld, $snapDir) && str_starts_with(basename($realOld), 'lacadev-client-')) {
                wp_delete_file($realOld);
            }
        }
    }

    /**
     * Khôi phục parent theme từ một file snapshot (hoặc bản mới nhất nếu để trống $file).
     */
    public static function restore(string $file = ''): true|\WP_Error
    {
        $startTime = microtime(true);
        $snapshots = self::list();

        if (empty($snapshots)) {
            return new \WP_Error(
                'laca_snapshot_empty',
                __('Chưa có bản sao lưu snapshot nào để khôi phục.', 'laca')
            );
        }

        $currentTheme = wp_get_theme('lacadev-client/theme');
        $fromVersion  = $currentTheme->exists() ? (string) $currentTheme->get('Version') : '';

        $selected = null;
        if ($file === '') {
            foreach ($snapshots as $item) {
                if ($fromVersion === '' || $item['version'] !== $fromVersion) {
                    $selected = $item;
                    break;
                }
            }
            if ($selected === null) {
                $selected = $snapshots[0];
            }
        } else {
            $baseFile = basename($file);
            if ($baseFile !== $file) {
                return new \WP_Error(
                    'laca_snapshot_invalid_name',
                    __('Tên file snapshot không hợp lệ.', 'laca')
                );
            }
            foreach ($snapshots as $item) {
                if ($item['file'] === $baseFile) {
                    $selected = $item;
                    break;
                }
            }
        }

        if ($selected === null || !is_file($selected['path'])) {
            return new \WP_Error(
                'laca_snapshot_not_found',
                __('Không tìm thấy file snapshot được yêu cầu.', 'laca')
            );
        }

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();
        global $wp_filesystem;
        if (!$wp_filesystem) {
            return new \WP_Error(
                'laca_filesystem_unavailable',
                __('Không khởi tạo được WP_Filesystem để khôi phục theme.', 'laca')
            );
        }

        $upgradeBase = WP_CONTENT_DIR . '/upgrade';
        if (!is_dir($upgradeBase)) {
            wp_mkdir_p($upgradeBase);
        }

        $rand       = wp_generate_password(10, false, false);
        $tmpExtract = $upgradeBase . '/laca-rollback-' . $rand;
        wp_mkdir_p($tmpExtract);

        $unzipped = unzip_file($selected['path'], $tmpExtract);
        if (is_wp_error($unzipped)) {
            $wp_filesystem->delete($tmpExtract, true);
            return $unzipped;
        }

        $extractedThemeDir = $tmpExtract . '/lacadev-client';
        if (!is_file($extractedThemeDir . '/theme/style.css')) {
            $wp_filesystem->delete($tmpExtract, true);
            return new \WP_Error(
                'laca_snapshot_corrupt',
                __('Gói snapshot không hợp lệ: thiếu lacadev-client/theme/style.css.', 'laca')
            );
        }

        $themesRoot = get_theme_root('lacadev-client/theme');
        if (!$themesRoot || !is_dir($themesRoot)) {
            $themesRoot = WP_CONTENT_DIR . '/themes';
        }

        $targetDir = trailingslashit($themesRoot) . 'lacadev-client';
        $holdDir   = self::dir() . '/lacadev-client.rollback-' . time() . '-' . wp_generate_password(4, false, false);
        $movedOld  = false;

        if ($wp_filesystem->is_dir($targetDir)) {
            if (!$wp_filesystem->move($targetDir, $holdDir, true)) {
                $wp_filesystem->delete($tmpExtract, true);
                return new \WP_Error(
                    'laca_rollback_move_current_failed',
                    __('Không thể tạm dời thư mục theme hiện tại để khôi phục.', 'laca')
                );
            }
            $movedOld = true;
        }

        if (!$wp_filesystem->move($extractedThemeDir, $targetDir, true)) {
            if ($movedOld) {
                $wp_filesystem->move($holdDir, $targetDir, true);
            }
            $wp_filesystem->delete($tmpExtract, true);
            return new \WP_Error(
                'laca_rollback_move_new_failed',
                __('Không thể chuyển bản snapshot vào thư mục themes/lacadev-client.', 'laca')
            );
        }

        // Dọn thư mục tạm sau khi đổi chỗ thành công.
        if ($movedOld && $wp_filesystem->is_dir($holdDir)) {
            $wp_filesystem->delete($holdDir, true);
        }
        if ($wp_filesystem->is_dir($tmpExtract)) {
            $wp_filesystem->delete($tmpExtract, true);
        }

        // Khi gọi rollback mặc định (không chỉ định file), pop bản snapshot vừa dùng để các lần rollback kế tiếp lùi tiếp trên stack.
        if ($file === '' && is_file($selected['path'])) {
            wp_delete_file($selected['path']);
        }

        wp_clean_themes_cache();
        delete_site_transient('update_themes');

        $restoredTheme = wp_get_theme('lacadev-client/theme');
        $toVersion     = $restoredTheme->exists() ? (string) $restoredTheme->get('Version') : $selected['version'];
        $durationMs    = (int) round((microtime(true) - $startTime) * 1000);

        if (class_exists(ThemeUpdater::class)) {
            ThemeUpdater::clearStaticCache('lacadev-client/theme');
            ThemeUpdater::sendUpdateReport('rolled_back', [
                'from'        => $fromVersion,
                'to'          => $toVersion,
                'snapshot'    => $selected['file'],
                'duration_ms' => $durationMs,
            ]);
        }

        return true;
    }
}
