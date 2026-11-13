<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DriftDetector
 *
 * Phát hiện sự sai lệch giữa production và local:
 * - So sánh file bằng rsync checksum (-c)
 * - So sánh phiên bản plugin, theme, WP core
 * - Kiểm tra trạng thái git local chưa commit
 */
class DriftDetector
{
    /**
     * Chạy phát hiện lệch và trả về cấu trúc dữ liệu chi tiết
     *
     * @param array $options
     * @return array{
     *   has_drift: bool,
     *   exit_code: int,
     *   file_drifts: array<array{path: string, type: string, description: string}>,
     *   version_drifts: array<array{item: string, type: string, prod_version: string, local_version: string, status: string}>,
     *   local_dirty_files: array<string>,
     *   errors: array<string>
     * }
     */
    public static function detect(array $options = []): array
    {
        $prod = SyncConfig::getProduction();
        $filesCfg = SyncConfig::getFilesConfig();

        $sshTarget = $prod['ssh'] ?? '';
        $remotePath = rtrim($prod['path'] ?? '', '/');
        $remoteWp = $prod['wp'] ?? 'wp';

        $fileDrifts = [];
        $versionDrifts = [];
        $errors = [];

        if (empty($sshTarget) || empty($remotePath)) {
            return [
                'has_drift'         => false,
                'exit_code'         => 1,
                'file_drifts'       => [],
                'version_drifts'    => [],
                'local_dirty_files' => [],
                'errors'            => [__('Thiếu thông số production (ssh/path) trong laca-sync.yml.', 'laca')],
            ];
        }

        // 1. Kiểm tra git status local xem có file nào chưa commit không
        $localDirtyFiles = self::getLocalDirtyFiles();

        // 2. So sánh file qua rsync -rlcn --itemize-changes -c
        $paths = (array) ($filesCfg['paths'] ?? ['wp-content/themes', 'wp-content/plugins', 'wp-content/mu-plugins']);
        $excludes = (array) ($filesCfg['exclude'] ?? ['node_modules', '.git', '*.log', 'cache', 'wflogs', 'upgrade', 'uploads']);

        // Tạo file exclude tạm
        $tmpExclude = tempnam(sys_get_temp_dir(), 'laca_sync_exc_');
        file_put_contents($tmpExclude, implode("\n", $excludes) . "\n");

        $parsedSsh = SyncConfig::parseSshTarget($sshTarget);
        $sshPortArg = ($parsedSsh['port'] !== 22) ? sprintf('-e "ssh -p %d -o BatchMode=yes"', $parsedSsh['port']) : '-e "ssh -o BatchMode=yes"';

        $remoteHostStr = $parsedSsh['user'] ? $parsedSsh['user'] . '@' . $parsedSsh['host'] : $parsedSsh['host'];

        foreach ($paths as $relPath) {
            $relPath = trim($relPath, '/');
            $localFullPath = ABSPATH . $relPath;

            if (!is_dir($localFullPath)) {
                continue;
            }

            // rsync -rlcn --itemize-changes -c --exclude-from=<tmp> <remoteHost>:<remotePath>/<relPath>/ <localFullPath>/
            $cmd = sprintf(
                'rsync -rlcn --itemize-changes -c --exclude-from=%s %s %s:%s/%s/ %s/',
                escapeshellarg($tmpExclude),
                $sshPortArg,
                escapeshellarg($remoteHostStr),
                escapeshellarg($remotePath),
                escapeshellarg($relPath),
                escapeshellarg($localFullPath)
            );

            $res = ShellProcess::run(['bash', '-c', $cmd], null, ABSPATH, null, 120);
            if ($res['exit_code'] !== 0) {
                $errors[] = sprintf(__('Lỗi rsync đường dẫn %s: %s', 'laca'), $relPath, trim($res['stderr'] ?: $res['stdout']));
                continue;
            }

            $lines = explode("\n", trim($res['stdout']));
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '.')) {
                    continue;
                }

                // Phân tích định dạng itemize-changes của rsync: >f+++++++++ filename hoặc >f.st...... filename
                // Ký tự đầu tiên:
                // >: file nhận từ server (prod khác local)
                // c: thay đổi (checksum/size/date)
                // *deleting: chỉ có ở local (nếu chạy với --delete)
                $parts = preg_split('/\s+/', $line, 2);
                if (count($parts) < 2) {
                    continue;
                }

                $flags = $parts[0];
                $fileItem = $parts[1];
                $fullRelPath = $relPath . '/' . $fileItem;

                $type = 'MODIFIED';
                $desc = __('Sửa đổi nội dung', 'laca');

                if (str_starts_with($flags, '>f++++++') || str_starts_with($flags, 'cd++++++')) {
                    $type = 'CREATED';
                    $desc = __('Mới thêm trên production', 'laca');
                } elseif (str_starts_with($flags, '*deleting')) {
                    $type = 'DELETED';
                    $desc = __('Đã xóa trên production (chỉ có ở local)', 'laca');
                }

                $fileDrifts[] = [
                    'path'        => $fullRelPath,
                    'type'        => $type,
                    'flags'       => $flags,
                    'description' => $desc,
                ];
            }
        }

        @unlink($tmpExclude);

        // 3. So sánh phiên bản Core, Plugins, Themes
        $versionDrifts = self::compareVersions($sshTarget, $remotePath, $remoteWp);

        $hasDrift = !empty($fileDrifts) || !empty($versionDrifts);
        $exitCode = $hasDrift ? 3 : 0;

        return [
            'has_drift'         => $hasDrift,
            'exit_code'         => $exitCode,
            'file_drifts'       => $fileDrifts,
            'version_drifts'    => $versionDrifts,
            'local_dirty_files' => $localDirtyFiles,
            'errors'            => $errors,
        ];
    }

    /**
     * So sánh phiên bản plugin, theme và core WordPress giữa prod và local
     */
    public static function compareVersions(string $sshTarget, string $remotePath, string $remoteWp): array
    {
        $drifts = [];

        // Lấy danh sách plugin trên remote
        $remotePluginCmd = sprintf('cd %s && %s plugin list --format=json 2>/dev/null', escapeshellarg($remotePath), (SyncConfig::remoteWpCommand((string) $remoteWp) ?? 'wp'));
        $remotePluginRes = ShellProcess::runSsh($sshTarget, $remotePluginCmd, 20);
        $remotePlugins = json_decode($remotePluginRes['stdout'], true) ?: [];

        // Lấy danh sách plugin local
        $localPlugins = [];
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $allPlugins = get_plugins();
        foreach ($allPlugins as $file => $data) {
            $name = dirname($file);
            if ($name === '.') {
                $name = basename($file, '.php');
            }
            $localPlugins[$name] = [
                'name'    => $name,
                'version' => $data['Version'] ?? '',
                'title'   => $data['Name'] ?? $name,
            ];
        }

        // So khớp plugin
        foreach ($remotePlugins as $rp) {
            $rSlug = $rp['name'] ?? '';
            $rVer = (string) ($rp['version'] ?? '');
            if (!$rSlug) {
                continue;
            }

            if (!isset($localPlugins[$rSlug])) {
                $drifts[] = [
                    'item'          => $rSlug,
                    'type'          => 'plugin',
                    'prod_version'  => $rVer,
                    'local_version' => __('Chưa cài', 'laca'),
                    'status'        => __('Chỉ có ở production', 'laca'),
                ];
            } else {
                $lVer = $localPlugins[$rSlug]['version'];
                if ($rVer !== '' && $lVer !== '' && version_compare($rVer, $lVer, '!=')) {
                    $status = version_compare($rVer, $lVer, '>') ? __('Production mới hơn', 'laca') : __('Local mới hơn', 'laca');
                    $drifts[] = [
                        'item'          => $rSlug,
                        'type'          => 'plugin',
                        'prod_version'  => $rVer,
                        'local_version' => $lVer,
                        'status'        => $status,
                    ];
                }
            }
        }

        // So khớp Core version
        global $wp_version;
        $remoteCoreCmd = sprintf('cd %s && %s core version 2>/dev/null', escapeshellarg($remotePath), (SyncConfig::remoteWpCommand((string) $remoteWp) ?? 'wp'));
        $remoteCoreRes = ShellProcess::runSsh($sshTarget, $remoteCoreCmd, 15);
        $rCoreVer = trim($remoteCoreRes['stdout']);

        if ($rCoreVer !== '' && (string) $wp_version !== '' && version_compare($rCoreVer, $wp_version, '!=')) {
            $drifts[] = [
                'item'          => 'WordPress Core',
                'type'          => 'core',
                'prod_version'  => $rCoreVer,
                'local_version' => (string) $wp_version,
                'status'        => version_compare($rCoreVer, $wp_version, '>') ? __('Production mới hơn', 'laca') : __('Local mới hơn', 'laca'),
            ];
        }

        return $drifts;
    }

    /**
     * Lấy danh sách file local đang dirty (chưa commit trong git)
     */
    public static function getLocalDirtyFiles(): array
    {
        $res = ShellProcess::run(['git', 'status', '--porcelain'], null, ABSPATH, null, 10);
        if ($res['exit_code'] !== 0) {
            // Thử ở repo root
            $res = ShellProcess::run(['git', 'status', '--porcelain'], null, dirname(ABSPATH, 2), null, 10);
        }

        if ($res['exit_code'] !== 0 || trim($res['stdout']) === '') {
            return [];
        }

        $lines = explode("\n", trim($res['stdout']));
        $dirty = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line, 2);
            if (isset($parts[1])) {
                $dirty[] = $parts[1];
            }
        }

        return $dirty;
    }
}

