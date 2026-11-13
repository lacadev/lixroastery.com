<?php

namespace App\Settings\LacaTools\Management;

use App\Settings\LacaTools\Management\ConfigChangeLog;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LargeFileScanner
 *
 * Quét theo lô phát hiện các file kích thước > 50MB trong WordPress
 * và hỗ trợ xoá an toàn theo vùng được phép (uploads, cache, upgrade, backup, logs/sql ở root).
 * Tuyệt đối không cho phép xoá file trong plugins, themes, core, hoặc file thực thi (.php).
 *
 * @package App\Settings\LacaTools\Management
 */
class LargeFileScanner
{
    public const MIN_SIZE_BYTES = 52428800; // 50 MB (50 * 1024 * 1024)
    public const NONCE_ACTION = 'laca_large_file_scanner_nonce';
    public const TRANSIENT_SCAN_STATE = 'laca_large_file_scan_state';
    public const TRANSIENT_RESULT = 'laca_large_file_scan_result';
    public const BATCH_TIMEOUT_SECONDS = 8;

    public function register(): void
    {
        add_action('wp_ajax_laca_storage_scan_batch', [$this, 'ajaxScanBatch']);
        add_action('wp_ajax_laca_storage_delete_files', [$this, 'ajaxDeleteFiles']);
    }

    /**
     * Danh sách các thư mục gốc được phép xoá file.
     *
     * @return array<int, string>
     */
    public static function getDeletableRoots(): array
    {
        $wpContent = wp_normalize_path(WP_CONTENT_DIR);
        $roots = [
            $wpContent . '/uploads',
            $wpContent . '/cache',
            $wpContent . '/upgrade',
            $wpContent . '/backups',
            $wpContent . '/ai1wm-backups',
            $wpContent . '/updraft',
        ];

        // Tìm thêm các thư mục backup dạng wp-content/backups* nếu có
        $dirs = glob($wpContent . '/backups*', GLOB_ONLYDIR);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                $dirNorm = wp_normalize_path($dir);
                if (!in_array($dirNorm, $roots, true)) {
                    $roots[] = $dirNorm;
                }
            }
        }

        return apply_filters('laca_storage_deletable_roots', $roots);
    }

    /**
     * Danh sách phần mở rộng file bị cấm tuyệt đối xoá.
     *
     * @return array<int, string>
     */
    public static function getForbiddenExtensions(): array
    {
        return [
            'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps',
            'htaccess', 'user.ini', 'env', 'sh', 'bash', 'bat', 'cmd', 'exe',
            'pl', 'cgi', 'py', 'rb'
        ];
    }

    /**
     * Kiểm tra xem một file có được phép xoá an toàn hay không.
     *
     * @param string $path Đường dẫn file (tương đối hoặc tuyệt đối)
     * @return array{deletable: bool, reason: string, real_path: string, rel_path: string, size: int}
     */
    public static function isDeletable(string $path): array
    {
        $realPath = realpath($path);
        if ($realPath === false || !file_exists($realPath)) {
            // Thử resolve từ ABSPATH nếu là đường dẫn tương đối
            $altPath = ABSPATH . ltrim($path, '/\\');
            $realPath = realpath($altPath);
        }

        if ($realPath === false || !file_exists($realPath)) {
            return [
                'deletable' => false,
                'reason'    => __('File không tồn tại trên hệ thống.', 'laca'),
                'real_path' => '',
                'rel_path'  => $path,
                'size'      => 0,
            ];
        }

        $realPath = wp_normalize_path($realPath);
        $size     = (int) @filesize($realPath);
        $relPath  = self::getRelativePath($realPath);

        // 1. Không phải thư mục hoặc symlink
        if (is_dir($realPath) || is_link($realPath)) {
            return [
                'deletable' => false,
                'reason'    => __('Không phải file thông thường hoặc là liên kết ảo.', 'laca'),
                'real_path' => $realPath,
                'rel_path'  => $relPath,
                'size'      => $size,
            ];
        }

        // 2. Kiểm tra phần mở rộng bị cấm
        $ext = strtolower((string) pathinfo($realPath, PATHINFO_EXTENSION));
        $filename = strtolower((string) basename($realPath));
        if (in_array($ext, self::getForbiddenExtensions(), true) || in_array($filename, ['.htaccess', '.user.ini', '.env'], true)) {
            return [
                'deletable' => false,
                'reason'    => __('File thực thi hoặc cấu hình nhạy cảm — không được xoá.', 'laca'),
                'real_path' => $realPath,
                'rel_path'  => $relPath,
                'size'      => $size,
            ];
        }

        // 3. Vùng cấm tuyệt đối: plugins, themes, mu-plugins, languages, wp-admin, wp-includes
        $wpContent = wp_normalize_path(WP_CONTENT_DIR);
        $absPath   = wp_normalize_path(ABSPATH);

        $forbiddenPrefixes = [
            $wpContent . '/plugins/',
            $wpContent . '/themes/',
            $wpContent . '/mu-plugins/',
            $wpContent . '/languages/',
            $absPath . 'wp-admin/',
            $absPath . 'wp-includes/',
        ];

        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($realPath, $prefix)) {
                return [
                    'deletable' => false,
                    'reason'    => __('Không xoá được ở đây — xử lý qua FTP/hosting', 'laca'),
                    'real_path' => $realPath,
                    'rel_path'  => $relPath,
                    'size'      => $size,
                ];
            }
        }

        // Core files ở ABSPATH root
        if (dirname($realPath) === rtrim($absPath, '/')) {
            $coreFiles = ['wp-config.php', 'wp-settings.php', 'wp-load.php', 'wp-login.php', 'index.php', 'xmlrpc.php', 'wp-cron.php'];
            if (in_array($filename, $coreFiles, true)) {
                return [
                    'deletable' => false,
                    'reason'    => __('File mã nguồn cốt lõi WordPress — không được xoá.', 'laca'),
                    'real_path' => $realPath,
                    'rel_path'  => $relPath,
                    'size'      => $size,
                ];
            }

            // Ở root ABSPATH: Chỉ cho phép xoá nếu là file log, sql, zip, tar, tar.gz, wpress
            $allowedRootExts = ['log', 'sql', 'gz', 'zip', 'tar', 'wpress'];
            if (in_array($ext, $allowedRootExts, true)) {
                return [
                    'deletable' => true,
                    'reason'    => '',
                    'real_path' => $realPath,
                    'rel_path'  => $relPath,
                    'size'      => $size,
                ];
            }

            return [
                'deletable' => false,
                'reason'    => __('Không xoá được ở đây — xử lý qua FTP/hosting', 'laca'),
                'real_path' => $realPath,
                'rel_path'  => $relPath,
                'size'      => $size,
            ];
        }

        // Cấp 1 của wp-content: cho phép xoá các file log, sql, zip, tar, tar.gz, wpress
        if (dirname($realPath) === rtrim($wpContent, '/')) {
            $allowedWpContentExts = ['log', 'sql', 'gz', 'zip', 'tar', 'wpress'];
            if (in_array($ext, $allowedWpContentExts, true)) {
                return [
                    'deletable' => true,
                    'reason'    => '',
                    'real_path' => $realPath,
                    'rel_path'  => $relPath,
                    'size'      => $size,
                ];
            }

            return [
                'deletable' => false,
                'reason'    => __('Không xoá được ở đây — xử lý qua FTP/hosting', 'laca'),
                'real_path' => $realPath,
                'rel_path'  => $relPath,
                'size'      => $size,
            ];
        }

        // 4. Kiểm tra nằm trong các thư mục được phép xoá
        $allowedRoots = self::getDeletableRoots();
        foreach ($allowedRoots as $allowedRoot) {
            $allowedPrefix = rtrim($allowedRoot, '/') . '/';
            if (str_starts_with($realPath, $allowedPrefix)) {
                return [
                    'deletable' => true,
                    'reason'    => '',
                    'real_path' => $realPath,
                    'rel_path'  => $relPath,
                    'size'      => $size,
                ];
            }
        }

        return [
            'deletable' => false,
            'reason'    => __('Không xoá được ở đây — xử lý qua FTP/hosting', 'laca'),
            'real_path' => $realPath,
            'rel_path'  => $relPath,
            'size'      => $size,
        ];
    }

    /**
     * Phân loại file theo loại nội dung.
     *
     * @param string $path
     * @param string $relPath
     * @return array{type: string, label: string}
     */
    public static function detectFileType(string $path, string $relPath): array
    {
        $pathLower = strtolower($relPath);
        $ext       = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if (str_contains($pathLower, 'node_modules') || str_contains($pathLower, '/vendor/')) {
            return ['type' => 'build', 'label' => __('Thư viện build (node_modules)', 'laca')];
        }

        if (
            str_contains($pathLower, 'backup') ||
            str_contains($pathLower, 'updraft') ||
            str_contains($pathLower, 'ai1wm') ||
            $ext === 'wpress' ||
            ($ext === 'sql' && str_contains($pathLower, 'dump'))
        ) {
            return ['type' => 'backup', 'label' => __('Backup', 'laca')];
        }

        if ($ext === 'log' || str_ends_with($pathLower, 'debug.log') || str_ends_with($pathLower, 'error.log')) {
            return ['type' => 'log', 'label' => __('Log', 'laca')];
        }

        if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv'], true)) {
            return ['type' => 'video', 'label' => __('Video', 'laca')];
        }

        if (in_array($ext, ['zip', 'tar', 'gz', 'rar', '7z', 'bz2'], true)) {
            return ['type' => 'archive', 'label' => __('Lưu trữ', 'laca')];
        }

        if ($ext === 'sql') {
            return ['type' => 'backup', 'label' => __('Database SQL', 'laca')];
        }

        return ['type' => 'other', 'label' => __('Khác', 'laca')];
    }

    /**
     * Lấy đường dẫn tương đối từ ABSPATH để hiển thị an toàn.
     */
    public static function getRelativePath(string $realPath): string
    {
        $abs = wp_normalize_path(ABSPATH);
        $path = wp_normalize_path($realPath);

        if (str_starts_with($path, $abs)) {
            return substr($path, strlen($abs));
        }

        return basename($path);
    }

    /**
     * Lấy kết quả quét đã lưu.
     *
     * @return array|null
     */
    public static function getSavedResult(): ?array
    {
        $data = get_transient(self::TRANSIENT_RESULT);
        return is_array($data) ? $data : null;
    }

    /**
     * AJAX: Quét theo lô file lớn (> 50MB).
     */
    public function ajaxScanBatch(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTION, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền hoặc phiên làm việc hết hạn.', 'laca'), 403);
        }

        $reset = !empty($_POST['reset']);
        $state = null;

        if (!$reset) {
            $state = get_transient(self::TRANSIENT_SCAN_STATE);
        }

        if (!is_array($state) || $reset) {
            // Khởi tạo hàng đợi duyệt
            $wpContent = wp_normalize_path(WP_CONTENT_DIR);
            $absPath   = wp_normalize_path(ABSPATH);

            $queue = [$wpContent];

            // Thêm các file ở root ABSPATH (cấp 1) vào danh sách kiểm tra
            $rootFiles = [];
            $rootEntries = @scandir($absPath);
            if (is_array($rootEntries)) {
                foreach ($rootEntries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $fullPath = $absPath . $entry;
                    if (is_file($fullPath) && !is_link($fullPath)) {
                        $rootFiles[] = $fullPath;
                    }
                }
            }

            $state = [
                'status'       => 'scanning',
                'queue'        => $queue,
                'root_files'   => $rootFiles,
                'items'        => [],
                'scanned_dirs' => 0,
                'total_bytes'  => 0,
                'start_time'   => time(),
            ];
        }

        $startTime = microtime(true);
        $queue     = &$state['queue'];
        $items     = &$state['items'];
        $rootFiles = &$state['root_files'];

        // 1. Quét các file ở ABSPATH root trước nếu còn
        while (!empty($rootFiles)) {
            $filePath = array_shift($rootFiles);
            $size = (int) @filesize($filePath);
            if ($size >= self::MIN_SIZE_BYTES) {
                $this->appendFoundItem($items, $filePath, $size);
            }
            if ((microtime(true) - $startTime) >= self::BATCH_TIMEOUT_SECONDS) {
                set_transient(self::TRANSIENT_SCAN_STATE, $state, 3600);
                wp_send_json_success([
                    'status'       => 'scanning',
                    'scanned_dirs' => $state['scanned_dirs'],
                    'found_count'  => count($items),
                    'message'      => sprintf(
                        /* translators: 1: scanned count, 2: found count */
                        esc_html__('Đã kiểm tra file gốc, tìm thấy %2$d file lớn...', 'laca'),
                        $state['scanned_dirs'],
                        count($items)
                    ),
                ]);
            }
        }

        // 2. Quét hàng đợi thư mục trong wp-content
        while (!empty($queue)) {
            $dir = array_shift($queue);
            $state['scanned_dirs']++;

            if (!is_dir($dir) || is_link($dir) || !is_readable($dir)) {
                continue;
            }

            $entries = @scandir($dir);
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $fullPath = rtrim($dir, '/\\') . '/' . $entry;

                if (is_link($fullPath)) {
                    continue;
                }

                if (is_dir($fullPath)) {
                    // Không đi sâu vào .git
                    if ($entry === '.git') {
                        continue;
                    }
                    $queue[] = $fullPath;
                } elseif (is_file($fullPath)) {
                    $size = (int) @filesize($fullPath);
                    if ($size >= self::MIN_SIZE_BYTES) {
                        $this->appendFoundItem($items, $fullPath, $size);
                    }
                }
            }

            if ((microtime(true) - $startTime) >= self::BATCH_TIMEOUT_SECONDS && !empty($queue)) {
                set_transient(self::TRANSIENT_SCAN_STATE, $state, 3600);
                wp_send_json_success([
                    'status'       => 'scanning',
                    'scanned_dirs' => $state['scanned_dirs'],
                    'found_count'  => count($items),
                    'message'      => sprintf(
                        /* translators: 1: scanned count, 2: found count */
                        esc_html__('Đã duyệt %1$d thư mục, tìm thấy %2$d file lớn...', 'laca'),
                        $state['scanned_dirs'],
                        count($items)
                    ),
                ]);
            }
        }

        // Quét xong toàn bộ!
        // Sắp xếp theo dung lượng giảm dần
        usort($items, function ($a, $b) {
            return ($b['size'] ?? 0) <=> ($a['size'] ?? 0);
        });

        $totalBytes = 0;
        foreach ($items as $it) {
            $totalBytes += ($it['size'] ?? 0);
        }

        $result = [
            'status'          => 'done',
            'scanned_dirs'    => $state['scanned_dirs'],
            'total_count'     => count($items),
            'total_bytes'     => $totalBytes,
            'total_formatted' => size_format($totalBytes),
            'items'           => $items,
            'updated_at'      => current_time('mysql'),
        ];

        // Lưu kết quả cuối cùng (24h) và xoá state quét tạm
        set_transient(self::TRANSIENT_RESULT, $result, 86400);
        delete_transient(self::TRANSIENT_SCAN_STATE);

        wp_send_json_success([
            'status'          => 'done',
            'scanned_dirs'    => $result['scanned_dirs'],
            'total_count'     => $result['total_count'],
            'total_bytes'     => $result['total_bytes'],
            'total_formatted' => $result['total_formatted'],
            'items'           => $items,
            'updated_at'      => $result['updated_at'],
            'message'         => sprintf(
                /* translators: 1: total files, 2: total size */
                esc_html__('Quét hoàn tất: tìm thấy %1$d file (> 50 MB), tổng cộng %2$s.', 'laca'),
                $result['total_count'],
                $result['total_formatted']
            ),
        ]);
    }

    /**
     * Thêm file tìm được vào mảng kết quả.
     */
    private function appendFoundItem(array &$items, string $fullPath, int $size): void
    {
        $relPath = self::getRelativePath($fullPath);
        $check   = self::isDeletable($fullPath);
        $type    = self::detectFileType($fullPath, $relPath);
        $mtime   = (int) @filemtime($fullPath);

        $items[] = [
            'path'            => $fullPath,
            'rel_path'        => $relPath,
            'name'            => basename($fullPath),
            'size'            => $size,
            'size_formatted'  => size_format($size),
            'mtime'           => $mtime,
            'mtime_formatted' => $mtime > 0 ? wp_date('Y-m-d H:i', $mtime) : '—',
            'type'            => $type['type'],
            'type_label'      => $type['label'],
            'deletable'       => $check['deletable'],
            'lock_reason'     => $check['reason'],
        ];
    }

    /**
     * AJAX: Xoá danh sách file đã chọn an toàn.
     */
    public function ajaxDeleteFiles(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTION, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền hoặc phiên làm việc hết hạn.', 'laca'), 403);
        }

        $paths = isset($_POST['paths']) ? (array) $_POST['paths'] : [];
        if (empty($paths)) {
            wp_send_json_error(esc_html__('Vui lòng chọn ít nhất một file để xoá.', 'laca'), 400);
        }

        $results = [];
        $deletedPaths = [];
        $totalDeletedBytes = 0;

        foreach ($paths as $path) {
            $pathStr = sanitize_text_field((string) $path);
            if ($pathStr === '') {
                continue;
            }

            $check = self::isDeletable($pathStr);

            if (!$check['deletable']) {
                $results[] = [
                    'path'    => $pathStr,
                    'success' => false,
                    'message' => $check['reason'] ?: __('File không được phép xoá.', 'laca'),
                ];
                continue;
            }

            $realPath = $check['real_path'];
            $fileSize = $check['size'];
            $relPath  = $check['rel_path'];

            // Chỉ xoá đúng loại file trang này tìm ra (> 50MB) — không biến endpoint thành công cụ xoá file tuỳ ý trong uploads
            if ($fileSize < self::MIN_SIZE_BYTES) {
                $results[] = [
                    'path'    => $pathStr,
                    'success' => false,
                    'message' => __('Chỉ xoá được file lớn hơn 50 MB từ kết quả quét.', 'laca'),
                ];
                continue;
            }

            // Tiến hành xoá file
            $deleted = false;
            if (function_exists('wp_delete_file')) {
                $deleted = @wp_delete_file($realPath);
            }
            if (!$deleted && file_exists($realPath)) {
                $deleted = @unlink($realPath);
            }

            if ($deleted || !file_exists($realPath)) {
                $deletedPaths[] = $realPath;
                $totalDeletedBytes += $fileSize;

                // Ghi nhật ký vào ConfigChangeLog
                if (class_exists(ConfigChangeLog::class)) {
                    ConfigChangeLog::log(
                        'storage_delete',
                        '',
                        sprintf('%s — %s', $relPath, size_format($fileSize))
                    );
                }

                $results[] = [
                    'path'    => $pathStr,
                    'success' => true,
                    'message' => __('Đã xoá thành công.', 'laca'),
                ];
            } else {
                $results[] = [
                    'path'    => $pathStr,
                    'success' => false,
                    'message' => __('Không thể xoá file (kiểm tra phân quyền ghi của máy chủ).', 'laca'),
                ];
            }
        }

        // Cập nhật lại cache kết quả quét nếu có file bị xoá
        if (!empty($deletedPaths)) {
            $saved = self::getSavedResult();
            if (is_array($saved) && isset($saved['items']) && is_array($saved['items'])) {
                $newItems = [];
                $newTotal = 0;
                foreach ($saved['items'] as $it) {
                    $itemPath = wp_normalize_path((string) ($it['path'] ?? ''));
                    $wasDeleted = false;
                    foreach ($deletedPaths as $dp) {
                        if (wp_normalize_path($dp) === $itemPath) {
                            $wasDeleted = true;
                            break;
                        }
                    }
                    if (!$wasDeleted) {
                        $newItems[] = $it;
                        $newTotal += ($it['size'] ?? 0);
                    }
                }
                $saved['items'] = $newItems;
                $saved['total_count'] = count($newItems);
                $saved['total_bytes'] = $newTotal;
                $saved['total_formatted'] = size_format($newTotal);
                set_transient(self::TRANSIENT_RESULT, $saved, 86400);
            }
        }

        wp_send_json_success([
            'results'             => $results,
            'deleted_count'       => count($deletedPaths),
            'deleted_size_format' => size_format($totalDeletedBytes),
            'message'             => sprintf(
                /* translators: 1: number of deleted files, 2: total size */
                esc_html__('Đã xoá thành công %1$d file (%2$s).', 'laca'),
                count($deletedPaths),
                size_format($totalDeletedBytes)
            ),
        ]);
    }
}

