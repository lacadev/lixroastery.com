<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DbPuller
 *
 * Thực hiện kéo cơ sở dữ liệu từ production về local,
 * streaming qua SSH nén gzip, tự động sao lưu local trước khi import,
 * và gọi LocalSafe để đảm bảo an toàn tuyệt đối.
 */
class DbPuller
{
    /**
     * Đường dẫn thư mục lưu bản sao lưu local
     */
    public static function getBackupDir(): string
    {
        $dir = ABSPATH . '../../app/sql/backups';
        if (!is_dir($dir)) {
            $dir = ABSPATH . '../sql/backups';
        }
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return realpath($dir) ?: $dir;
    }

    /**
     * Đường dẫn thư mục lưu file pull từ production
     */
    public static function getPullDir(): string
    {
        $dir = ABSPATH . '../../app/sql/pulls';
        if (!is_dir($dir)) {
            $dir = ABSPATH . '../sql/pulls';
        }
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return realpath($dir) ?: $dir;
    }

    /**
     * Kiểm tra an toàn trước khi kéo DB
     */
    public static function checkPreconditions(): array
    {
        // 1. Kiểm tra môi trường: chỉ cho phép local hoặc development
        if (function_exists('wp_get_environment_type')) {
            $env = wp_get_environment_type();
            if ($env !== 'local' && $env !== 'development') {
                return [
                    'valid'   => false,
                    'message' => sprintf(__('Lệnh pull-db chỉ được phép chạy ở môi trường "local" hoặc "development" (môi trường hiện tại: %s).', 'laca'), $env),
                ];
            }
        }

        $prod = SyncConfig::getProduction();
        $prodUrl = rtrim($prod['url'] ?? '', '/');
        $localUrl = rtrim(home_url(), '/');

        if (empty($prodUrl)) {
            return [
                'valid'   => false,
                'message' => __('Chưa cấu hình production.url trong laca-sync.yml.', 'laca'),
            ];
        }

        // Không cho phép pull nếu site local trùng URL với production
        if ($prodUrl === $localUrl) {
            return [
                'valid'   => false,
                'message' => sprintf(__('URL local (%s) trùng với URL production (%s)! Ngăn chặn để tránh ghi đè nhầm.', 'laca'), $localUrl, $prodUrl),
            ];
        }

        return [
            'valid'   => true,
            'message' => 'OK',
        ];
    }

    /**
     * Sao lưu database local trước khi ghi đè
     *
     * @return array{success: bool, file: string, message: string}
     */
    public static function backupLocal(): array
    {
        $backupDir = self::getBackupDir();
        $filename = 'local-' . gmdate('Ymd-His') . '.sql.gz';
        $targetPath = $backupDir . '/' . $filename;

        $cmd = sprintf('wp db export - | gzip -c > %s', escapeshellarg($targetPath));
        $res = ShellProcess::run(['bash', '-c', $cmd], null, ABSPATH, null, 180);

        if ($res['exit_code'] !== 0 || !is_file($targetPath) || filesize($targetPath) === 0) {
            return [
                'success' => false,
                'file'    => '',
                'message' => sprintf(__('Sao lưu local thất bại: %s', 'laca'), trim($res['stderr'] ?: $res['stdout'])),
            ];
        }

        if (!ShellProcess::verifyGzipFile($targetPath)) {
            return [
                'success' => false,
                'file'    => '',
                'message' => __('File sao lưu local bị lỗi định dạng gzip sau khi xuất.', 'laca'),
            ];
        }

        // Liệt kê các bản sao lưu cũ hơn 5 bản mới nhất (không tự xoá theo quy tắc an toàn)
        $backups = DbRestorer::getAvailableBackups();
        if (count($backups) > 5) {
            $oldOnes = array_slice($backups, 5);
            $oldFilesList = implode("\n", array_map(fn ($b) => '   rm ' . escapeshellarg($b['path']), $oldOnes));
            error_log(sprintf("[laca-sync] Hiện có %d bản sao lưu cũ trong %s. Nếu muốn dọn bớt dung lượng, hãy tự chạy:\n%s", count($oldOnes), $backupDir, $oldFilesList));
        }

        return [
            'success' => true,
            'file'    => $targetPath,
            'message' => sprintf(__('Đã sao lưu local thành công: %s (%s)', 'laca'), basename($targetPath), size_format(filesize($targetPath), 2)),
        ];
    }

    /**
     * Kéo DB từ production qua SSH stream nén
     *
     * @param array $options
     * @param callable|null $progressCallback
     * @return array{success: bool, file: string, bytes: int, error: string}
     */
    public static function pullFromProduction(array $options = [], ?callable $progressCallback = null): array
    {
        $prod = SyncConfig::getProduction();
        $dbCfg = SyncConfig::getDbConfig();

        $sshTarget = $prod['ssh'] ?? '';
        $remotePath = rtrim($prod['path'] ?? '', '/');
        $remoteWp = $prod['wp'] ?? 'wp';

        if (empty($sshTarget) || empty($remotePath)) {
            return [
                'success' => false,
                'file'    => '',
                'bytes'   => 0,
                'error'   => __('Thiếu cấu hình SSH production trong laca-sync.yml.', 'laca'),
            ];
        }

        $pullDir = self::getPullDir();
        $filename = 'prod-' . gmdate('Ymd-His') . '.sql.gz';
        $localFilePath = $pullDir . '/' . $filename;

        $wpCmd = SyncConfig::remoteWpCommand((string) $remoteWp);
        if ($wpCmd === null) {
            return ['success' => false, 'file' => '', 'bytes' => 0,
                'error' => __('Giá trị production.wp trong laca-sync.yml chứa ký tự không hợp lệ.', 'laca')];
        }
        $cdWp = sprintf('cd %s && %s', escapeshellarg($remotePath), $wpCmd);

        // Lấy prefix + danh sách bảng THẬT trên server để so mẫu loại trừ (wp db export không hiểu ký tự đại diện)
        $prefixRes = ShellProcess::runSsh($sshTarget, $cdWp . ' db prefix', 60);
        $tablesRes = ShellProcess::runSsh($sshTarget, $cdWp . ' db tables --all-tables-with-prefix --format=csv', 60);
        $remotePrefix = trim((string) $prefixRes['stdout']);
        $remoteTables = array_values(array_filter(array_map('trim', explode(',', trim((string) $tablesRes['stdout'])))));
        if ($prefixRes['exit_code'] !== 0 || $tablesRes['exit_code'] !== 0 || $remotePrefix === '' || empty($remoteTables)) {
            return ['success' => false, 'file' => '', 'bytes' => 0,
                'error' => sprintf(__('Không đọc được danh sách bảng trên production: %s', 'laca'), trim($tablesRes['stderr'] . ' ' . $prefixRes['stderr']))];
        }
        foreach ($remoteTables as $t) {
            if (!SyncConfig::isSafeTableName($t)) {
                return ['success' => false, 'file' => '', 'bytes' => 0,
                    'error' => sprintf(__('Tên bảng không hợp lệ trên production: %s', 'laca'), $t)];
            }
        }

        // Mẫu so trên tên bảng ĐÃ BỎ prefix; hỗ trợ "*" (vd "wf*" = mọi bảng Wordfence). "wflogs" cũ = "wf*"
        $matchTables = static function (array $patterns) use ($remoteTables, $remotePrefix): array {
            $out = [];
            foreach ($patterns as $pattern) {
                $pattern = trim((string) $pattern);
                $pattern = $pattern === 'wflogs' ? 'wf*' : $pattern;
                if ($pattern === '') {
                    continue;
                }
                foreach ($remoteTables as $table) {
                    $short = str_starts_with($table, $remotePrefix) ? substr($table, strlen($remotePrefix)) : $table;
                    if (fnmatch($pattern, $short) || fnmatch($pattern, $table)) {
                        $out[] = $table;
                    }
                }
            }
            return array_values(array_unique($out));
        };

        $excludeFull  = $matchTables((array) ($dbCfg['exclude_tables'] ?? []));
        $dataOnlyFull = $matchTables((array) ($dbCfg['exclude_data_only'] ?? []));

        // --tables từ CLI: chỉ nhận bảng tồn tại trên server (có hoặc không kèm prefix)
        $specificTables = [];
        foreach ((array) ($options['tables'] ?? []) as $t) {
            $t = trim((string) $t);
            $full = in_array($t, $remoteTables, true) ? $t : $remotePrefix . $t;
            if (in_array($full, $remoteTables, true)) {
                $specificTables[] = $full;
            }
        }
        if (!empty($options['tables']) && empty($specificTables)) {
            return ['success' => false, 'file' => '', 'bytes' => 0,
                'error' => __('Không bảng nào trong --tables tồn tại trên production.', 'laca')];
        }

        $allExcludes = array_values(array_unique(array_merge($excludeFull, $dataOnlyFull)));
        $excludeFlags = !empty($allExcludes) && empty($specificTables)
            ? ' --exclude_tables=' . escapeshellarg(implode(',', $allExcludes)) : '';
        $tableFlag = !empty($specificTables) ? ' --tables=' . escapeshellarg(implode(',', $specificTables)) : '';

        $remoteExportCmd = sprintf(
            '%s db export - --single-transaction --quick --default-character-set=utf8mb4%s%s | gzip -c',
            $cdWp,
            $tableFlag,
            $excludeFlags
        );

        $streamRes = ShellProcess::streamSshToFile($sshTarget, $remoteExportCmd, $localFilePath, $progressCallback);

        if (!$streamRes['success'] || $streamRes['bytes'] === 0) {
            return [
                'success' => false,
                'file'    => '',
                'bytes'   => 0,
                'error'   => $streamRes['error'] ?: __('Không nhận được dữ liệu từ production.', 'laca'),
            ];
        }

        // Bảng chỉ lấy cấu trúc (bỏ dữ liệu): xuất --no-data rồi nối vào file chính (gzip nối tiếp hợp lệ)
        if (!empty($dataOnlyFull) && empty($specificTables)) {
            $schemaFile = $localFilePath . '.schema.gz';
            $remoteSchemaCmd = sprintf(
                '%s db export - --no-data --tables=%s --default-character-set=utf8mb4 | gzip -c',
                $cdWp,
                escapeshellarg(implode(',', $dataOnlyFull))
            );

            $schemaRes = ShellProcess::streamSshToFile($sshTarget, $remoteSchemaCmd, $schemaFile);
            if ($schemaRes['success'] && $schemaRes['bytes'] > 0 && is_file($schemaFile)) {
                $fpMain = fopen($localFilePath, 'ab');
                $fpSchema = fopen($schemaFile, 'rb');
                if ($fpMain && $fpSchema) {
                    while (!feof($fpSchema)) {
                        fwrite($fpMain, fread($fpSchema, 65536));
                    }
                    fclose($fpMain);
                    fclose($fpSchema);
                }
                @unlink($schemaFile); // file tạm do chính lệnh này tạo
            }
        }

        // "wp db export | gzip" trả mã thoát của gzip → wp lỗi vẫn ra file gzip hợp lệ nhưng rỗng. Kiểm nội dung thật.
        $gz = @gzopen($localFilePath, 'rb');
        $head = $gz ? (string) gzread($gz, 262144) : '';
        if ($gz) {
            gzclose($gz);
        }
        if (stripos($head, 'CREATE TABLE') === false) {
            return [
                'success' => false,
                'file'    => $localFilePath,
                'bytes'   => (int) (filesize($localFilePath) ?: 0),
                'error'   => __('File tải về không chứa dữ liệu SQL hợp lệ (wp-cli trên production có thể đã báo lỗi). Chạy "wp laca sync-check".', 'laca'),
            ];
        }

        // Kiểm tra tính toàn vẹn gzip
        if (!ShellProcess::verifyGzipFile($localFilePath)) {
            return [
                'success' => false,
                'file'    => $localFilePath,
                'bytes'   => filesize($localFilePath) ?: 0,
                'error'   => __('File SQL nén tải về bị lỗi định dạng gzip (corrupted).', 'laca'),
            ];
        }

        return [
            'success' => true,
            'file'    => $localFilePath,
            'bytes'   => filesize($localFilePath) ?: 0,
            'error'   => '',
        ];
    }

    /**
     * Nhập file nén vào database local
     */
    public static function importToLocal(string $gzFilePath): array
    {
        if (!is_file($gzFilePath) || !is_readable($gzFilePath)) {
            return [
                'success' => false,
                'message' => sprintf(__('Không tìm thấy file SQL nén: %s', 'laca'), $gzFilePath),
            ];
        }

        $cmd = sprintf('gunzip -c %s | wp db import -', escapeshellarg($gzFilePath));
        $res = ShellProcess::run(['bash', '-c', $cmd], null, ABSPATH, null, 600);

        if ($res['exit_code'] !== 0) {
            return [
                'success' => false,
                'message' => sprintf(__('Lỗi khi import vào database local: %s', 'laca'), trim($res['stderr'] ?: $res['stdout'])),
            ];
        }

        return [
            'success' => true,
            'message' => __('Đã nhập thành công toàn bộ dữ liệu vào local database.', 'laca'),
        ];
    }
}

