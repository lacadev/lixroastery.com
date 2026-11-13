<?php

namespace App\Settings\LacaTools\Management;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tính toán và kiểm tra dung lượng lưu trữ (DB, Uploads, wp-content, ổ đĩa trống)
 * cùng cảnh báo quyền file (chỉ đọc, không tự chmod).
 *
 * Cache transient `laca_storage_health` (TTL 12 giờ).
 * Không tự quét khi tải Dashboard nếu chưa có cache.
 *
 * @package App\Settings\LacaTools\Management
 */
class StorageHealth
{
    const TRANSIENT_KEY = 'laca_storage_health';
    const TTL           = 12 * HOUR_IN_SECONDS;
    const NONCE_ACTION  = 'laca_storage_health_nonce';
    const MAX_SCAN_SEC  = 15.0;

    /**
     * Lấy dữ liệu đã cache (không tự tính nếu chưa có).
     */
    public static function getCached(): ?array
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        return is_array($cached) ? $cached : null;
    }

    /**
     * Tính toán lại dung lượng và quyền file, lưu vào transient 12 giờ.
     */
    public static function compute(): array
    {
        global $wpdb;

        // 1. Dung lượng Database (chỉ các bảng thuộc prefix hiện tại)
        $likePrefix = $wpdb->esc_like($wpdb->prefix) . '%';
        $dbBytes    = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s AND table_name LIKE %s',
                DB_NAME,
                $likePrefix
            )
        );

        // 2. Dung lượng Uploads & wp-content (giới hạn tổng thời gian quét 15 giây)
        $deadline   = microtime(true) + self::MAX_SCAN_SEC;
        $isPartial  = false;

        // 2.1 Quét Plugins
        $pluginDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : (WP_CONTENT_DIR . '/plugins');
        $pluginsBytes = 0;
        $pluginsList  = [];
        if (is_dir($pluginDir) && is_readable($pluginDir)) {
            $entries = scandir($pluginDir);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    if (microtime(true) >= $deadline) { $isPartial = true; break; }
                    $pPath = $pluginDir . '/' . $entry;
                    $pSize = self::scanSingleDirSize($pPath, $deadline, $isPartial);
                    $pluginsBytes += $pSize;
                    if ($pSize > 0) {
                        $pluginsList[] = [
                            'name'      => $entry,
                            'path'      => 'plugins/' . $entry,
                            'bytes'     => $pSize,
                            'formatted' => (string) size_format($pSize, 2),
                        ];
                    }
                }
            }
        }
        usort($pluginsList, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
        $topPlugins = array_slice($pluginsList, 0, 10);
        foreach ($topPlugins as &$tp) {
            $tp['percent'] = $pluginsBytes > 0 ? round(($tp['bytes'] / $pluginsBytes) * 100, 1) : 0;
        }
        unset($tp);

        // 2.2 Quét Themes
        $themeRoot = get_theme_root();
        $themesBytes = 0;
        $themesList  = [];
        if (is_dir($themeRoot) && is_readable($themeRoot)) {
            $entries = scandir($themeRoot);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    if (microtime(true) >= $deadline) { $isPartial = true; break; }
                    $tPath = $themeRoot . '/' . $entry;
                    $tSize = self::scanSingleDirSize($tPath, $deadline, $isPartial);
                    $themesBytes += $tSize;
                    if ($tSize > 0) {
                        $themesList[] = [
                            'name'      => $entry,
                            'path'      => 'themes/' . $entry,
                            'bytes'     => $tSize,
                            'formatted' => (string) size_format($tSize, 2),
                        ];
                    }
                }
            }
        }
        usort($themesList, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
        $topThemes = array_slice($themesList, 0, 10);
        foreach ($topThemes as &$tt) {
            $tt['percent'] = $themesBytes > 0 ? round(($tt['bytes'] / $themesBytes) * 100, 1) : 0;
        }
        unset($tt);

        // 2.3 Quét Uploads
        $uploadInfo = wp_upload_dir();
        $uploadsDir = !empty($uploadInfo['basedir']) ? (string) $uploadInfo['basedir'] : (WP_CONTENT_DIR . '/uploads');
        $uploadsBytes = 0;
        $uploadsList  = [];
        if (is_dir($uploadsDir) && is_readable($uploadsDir)) {
            $entries = scandir($uploadsDir);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    if (microtime(true) >= $deadline) { $isPartial = true; break; }
                    $uPath = $uploadsDir . '/' . $entry;
                    $uSize = self::scanSingleDirSize($uPath, $deadline, $isPartial);
                    $uploadsBytes += $uSize;
                    if ($uSize > 0) {
                        $uploadsList[] = [
                            'name'      => $entry,
                            'path'      => 'uploads/' . $entry,
                            'bytes'     => $uSize,
                            'formatted' => (string) size_format($uSize, 2),
                        ];
                    }
                }
            }
        }
        usort($uploadsList, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
        $topUploads = array_slice($uploadsList, 0, 10);
        foreach ($topUploads as &$tu) {
            $tu['percent'] = $uploadsBytes > 0 ? round(($tu['bytes'] / $uploadsBytes) * 100, 1) : 0;
        }
        unset($tu);

        // 2.4 Quét các file/thư mục khác trong wp-content (cache, upgrade, languages, mu-plugins, debug.log...)
        $otherBytes = 0;
        if (is_dir(WP_CONTENT_DIR) && is_readable(WP_CONTENT_DIR)) {
            $entries = scandir(WP_CONTENT_DIR);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    if ($entry === 'plugins' || $entry === 'themes' || $entry === 'uploads') continue;
                    if (microtime(true) >= $deadline) { $isPartial = true; break; }
                    $oPath = WP_CONTENT_DIR . '/' . $entry;
                    $otherBytes += self::scanSingleDirSize($oPath, $deadline, $isPartial);
                }
            }
        }

        $wpContentBytes = $pluginsBytes + $themesBytes + $uploadsBytes + $otherBytes;
        $totalBytes     = $wpContentBytes + $dbBytes;

        // 3. Dung lượng trống ổ đĩa (nếu hàm disk_free_space khả dụng)
        $freeFormatted = '';
        if (self::isDiskFreeSpaceAvailable()) {
            $free = @disk_free_space(ABSPATH);
            if ($free !== false && $free >= 0) {
                $freeFormatted = (string) size_format((float) $free, 2);
            }
        }

        // 4. Kiểm tra quyền file (chỉ cảnh báo)
        $permissions = self::checkPermissions($uploadsDir);

        $data = [
            'db_bytes'             => $dbBytes,
            'db_formatted'         => (string) size_format($dbBytes, 2),
            'uploads_bytes'        => $uploadsBytes,
            'uploads_partial'      => $isPartial,
            'uploads_formatted'    => self::formatDirScan($uploadsBytes, $isPartial),
            'plugins_bytes'        => $pluginsBytes,
            'plugins_formatted'    => (string) size_format($pluginsBytes, 2),
            'themes_bytes'         => $themesBytes,
            'themes_formatted'     => (string) size_format($themesBytes, 2),
            'other_bytes'          => $otherBytes,
            'other_formatted'      => (string) size_format($otherBytes, 2),
            'wp_content_bytes'     => $wpContentBytes,
            'wp_content_partial'   => $isPartial,
            'wp_content_formatted' => self::formatDirScan($wpContentBytes, $isPartial),
            'total_bytes'          => $totalBytes,
            'total_formatted'      => (string) size_format($totalBytes, 2),
            'top_plugins'          => $topPlugins,
            'top_themes'           => $topThemes,
            'top_uploads'          => $topUploads,
            'free_formatted'       => $freeFormatted,
            'permissions'          => $permissions,
            'updated_at'           => wp_date('H:i d/m/Y'),
            'partial'              => $isPartial,
        ];

        set_transient(self::TRANSIENT_KEY, $data, self::TTL);

        return $data;
    }

    public static function scanSingleDirSize(string $path, float $deadline, bool &$partial = false): int
    {
        if (is_file($path)) {
            $s = @filesize($path);
            return $s !== false ? (int) $s : 0;
        }

        if (!is_dir($path) || !is_readable($path)) {
            return 0;
        }

        $res = self::scanDirSize($path, $deadline);
        if ($res['partial']) {
            $partial = true;
        }
        return $res['bytes'];
    }

    /**
     * Quét dung lượng thư mục bằng RecursiveDirectoryIterator, dừng khi chạm deadline.
     *
     * @return array{bytes:int,partial:bool}
     */
    private static function scanDirSize(string $dir, float $deadline): array
    {
        if (!is_dir($dir) || !is_readable($dir)) {
            return ['bytes' => 0, 'partial' => false];
        }

        $bytes   = 0;
        $partial = false;
        $counter = 0;

        try {
            $dirIter = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
            $iter    = new \RecursiveIteratorIterator($dirIter, \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD);

            foreach ($iter as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isLink()) {
                    continue;
                }
                if ($file->isFile()) {
                    $size = @$file->getSize();
                    if ($size !== false) {
                        $bytes += (int) $size;
                    }
                }

                $counter++;
                if (($counter % 250) === 0 && microtime(true) >= $deadline) {
                    $partial = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            // Bỏ qua thư mục con không đọc được
        }

        return [
            'bytes'   => $bytes,
            'partial' => $partial,
        ];
    }

    private static function formatDirScan(int $bytes, bool $partial): string
    {
        $sizeStr = (string) size_format($bytes, 2);
        if ($partial) {
            return sprintf(
                /* translators: %s: formatted size */
                __('≥ %s (chưa quét hết)', 'laca'),
                $sizeStr
            );
        }
        return $sizeStr;
    }

    private static function isDiskFreeSpaceAvailable(): bool
    {
        if (!function_exists('disk_free_space')) {
            return false;
        }
        $disabled = (string) ini_get('disable_functions');
        if ($disabled !== '') {
            $list = array_map('trim', explode(',', strtolower($disabled)));
            if (in_array('disk_free_space', $list, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Kiểm tra quyền các file và thư mục quan trọng.
     *
     * @return array<int,array{path:string,current:string,suggest:string,level:string,reason:string}>
     */
    private static function checkPermissions(string $uploadsDir): array
    {
        $issues = [];

        // 1. wp-config.php
        $wpConfig = ABSPATH . 'wp-config.php';
        if (!file_exists($wpConfig) && file_exists(dirname(ABSPATH) . '/wp-config.php')) {
            $wpConfig = dirname(ABSPATH) . '/wp-config.php';
        }
        if (file_exists($wpConfig)) {
            $perm = (int) @fileperms($wpConfig);
            $oct  = self::formatOctal($perm);
            if (($perm & 0022) !== 0) {
                $issues[] = [
                    'path'    => 'wp-config.php',
                    'current' => $oct,
                    'suggest' => '0640 / 0600',
                    'level'   => 'danger',
                    'reason'  => __('Ghi được bởi group/others', 'laca'),
                ];
            } elseif (($perm & 0004) !== 0) {
                $issues[] = [
                    'path'    => 'wp-config.php',
                    'current' => $oct,
                    'suggest' => '0640 / 0600',
                    'level'   => 'warning',
                    'reason'  => __('Đọc được bởi world', 'laca'),
                ];
            }
        }

        // 2. wp-content, uploads, thư mục theme đang là 0777
        $dirsToCheck = [
            'wp-content'         => WP_CONTENT_DIR,
            'wp-content/uploads' => $uploadsDir,
            'theme'              => get_template_directory(),
        ];
        foreach ($dirsToCheck as $label => $dirPath) {
            if ($dirPath && is_dir($dirPath)) {
                $perm = (int) @fileperms($dirPath);
                if (($perm & 0777) === 0777) {
                    $issues[] = [
                        'path'    => $label,
                        'current' => self::formatOctal($perm),
                        'suggest' => '0755',
                        'level'   => 'danger',
                        'reason'  => __('Quyền 0777 quá rộng', 'laca'),
                    ];
                }
            }
        }

        // 3. uploads không ghi được
        if (is_dir($uploadsDir) && !wp_is_writable($uploadsDir)) {
            $perm = (int) @fileperms($uploadsDir);
            $issues[] = [
                'path'    => 'wp-content/uploads',
                'current' => self::formatOctal($perm),
                'suggest' => '0755',
                'level'   => 'danger',
                'reason'  => __('Thư mục uploads không ghi được', 'laca'),
            ];
        }

        // 4. .htaccess ghi được bởi world
        $htaccess = ABSPATH . '.htaccess';
        if (file_exists($htaccess)) {
            $perm = (int) @fileperms($htaccess);
            if (($perm & 0002) !== 0) {
                $issues[] = [
                    'path'    => '.htaccess',
                    'current' => self::formatOctal($perm),
                    'suggest' => '0644',
                    'level'   => 'warning',
                    'reason'  => __('Ghi được bởi world', 'laca'),
                ];
            }
        }

        return $issues;
    }

    private static function formatOctal(int $perm): string
    {
        return '0' . substr(sprintf('%o', $perm), -3);
    }

    /**
     * Render nội dung bên trong widget (dùng cho cả lần tải đầu và AJAX refresh).
     */
    public static function renderWidgetInner(?array $data): string
    {
        ob_start();
        if ($data === null) :
            ?>
            <div class="laca-empty">
                <div class="laca-empty__title"><?php esc_html_e('Chưa có số liệu', 'laca'); ?></div>
                <p class="laca-empty__desc">
                    <?php esc_html_e('Nhấn nút bên dưới để tính dung lượng lưu trữ và kiểm tra quyền file.', 'laca'); ?>
                </p>
                <div style="margin-top:10px;">
                    <button type="button" class="laca-btn laca-btn--primary laca-btn--sm" data-laca-storage-refresh="1">
                        <?php esc_html_e('Tính ngay', 'laca'); ?>
                    </button>
                </div>
            </div>
            <?php
        else :
            $perms = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];
            ?>
            <div class="laca-storage-stats">
                <div class="laca-stat">
                    <span class="laca-stat__label"><?php esc_html_e('Database', 'laca'); ?></span>
                    <span class="laca-stat__value"><?php echo esc_html((string) ($data['db_formatted'] ?? '0 B')); ?></span>
                </div>
                <div class="laca-stat">
                    <span class="laca-stat__label"><?php esc_html_e('Uploads', 'laca'); ?></span>
                    <span class="laca-stat__value"><?php echo esc_html((string) ($data['uploads_formatted'] ?? '0 B')); ?></span>
                </div>
                <div class="laca-stat">
                    <span class="laca-stat__label"><?php esc_html_e('wp-content', 'laca'); ?></span>
                    <span class="laca-stat__value"><?php echo esc_html((string) ($data['wp_content_formatted'] ?? '0 B')); ?></span>
                </div>
            </div>

            <?php if (!empty($data['free_formatted'])) : ?>
                <div class="laca-storage-free">
                    <span><?php esc_html_e('Dung lượng trống ổ đĩa:', 'laca'); ?></span>
                    <strong><?php echo esc_html((string) $data['free_formatted']); ?></strong>
                </div>
            <?php endif; ?>

            <div class="laca-storage-perms">
                <?php if (empty($perms)) : ?>
                    <span class="laca-badge laca-badge--success"><?php esc_html_e('Quyền file ổn', 'laca'); ?></span>
                <?php else : ?>
                    <ul class="laca-perm-list">
                        <?php foreach ($perms as $issue) :
                            $badgeClass = ($issue['level'] ?? 'warning') === 'danger' ? 'laca-badge--danger' : 'laca-badge--warning';
                        ?>
                            <li class="laca-perm-item">
                                <span class="laca-badge <?php echo esc_attr($badgeClass); ?>">
                                    <?php echo esc_html((string) ($issue['path'] ?? '')); ?>
                                    (<?php echo esc_html((string) ($issue['current'] ?? '')); ?>)
                                </span>
                                <span class="laca-perm-item__detail">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: 1: reason, 2: suggested permission */
                                            __('%1$s — gợi ý: %2$s', 'laca'),
                                            (string) ($issue['reason'] ?? ''),
                                            (string) ($issue['suggest'] ?? '')
                                        )
                                    );
                                    ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="laca-storage-footer">
                <div class="laca-storage-meta">
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: timestamp */
                                __('Cập nhật lúc %s', 'laca'),
                                (string) ($data['updated_at'] ?? '')
                            )
                        );
                        ?>
                    </span>
                    <button type="button" class="laca-btn laca-btn--ghost laca-btn--sm" data-laca-storage-refresh="1">
                        <?php esc_html_e('Tính lại', 'laca'); ?>
                    </button>
                </div>
                <div style="display:flex; gap:12px; align-items:center;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-system&tab=storage')); ?>" class="laca-storage-link">
                        <?php esc_html_e('Chi tiết →', 'laca'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-db-cleaner')); ?>" class="laca-storage-link">
                        <?php esc_html_e('Dọn dẹp DB →', 'laca'); ?>
                    </a>
                </div>
            </div>
            <?php
        endif;

        return (string) ob_get_clean();
    }
}

