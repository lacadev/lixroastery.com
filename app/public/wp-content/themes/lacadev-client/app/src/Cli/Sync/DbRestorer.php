<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DbRestorer
 *
 * Khôi phục cơ sở dữ liệu local từ các bản sao lưu trong app/sql/backups/
 */
class DbRestorer
{
    /**
     * Lấy danh sách các bản sao lưu local đã có
     *
     * @return array<array{path: string, filename: string, size: int, mtime: int}>
     */
    public static function getAvailableBackups(): array
    {
        $backupDir = ABSPATH . '../../app/sql/backups';
        if (!is_dir($backupDir)) {
            $backupDir = ABSPATH . '../sql/backups';
        }

        if (!is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir . '/*.sql.gz') ?: [];
        $result = [];

        foreach ($files as $file) {
            $result[] = [
                'path'     => $file,
                'filename' => basename($file),
                'size'     => filesize($file) ?: 0,
                'mtime'    => filemtime($file) ?: 0,
            ];
        }

        // Sắp xếp mới nhất lên đầu
        usort($result, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $result;
    }

    /**
     * Thực hiện khôi phục DB từ file backup
     */
    public static function restore(?string $specificFile = null): array
    {
        $backups = self::getAvailableBackups();
        if (empty($backups)) {
            return [
                'success' => false,
                'message' => __('Không tìm thấy bản sao lưu nào trong thư mục app/sql/backups/.', 'laca'),
            ];
        }

        $targetFile = null;
        if (!empty($specificFile)) {
            if (is_file($specificFile)) {
                $targetFile = $specificFile;
            } else {
                // Thử tìm theo tên file trong danh sách
                foreach ($backups as $b) {
                    if ($b['filename'] === $specificFile || basename($b['filename'], '.sql.gz') === $specificFile) {
                        $targetFile = $b['path'];
                        break;
                    }
                }
            }

            if (!$targetFile) {
                return [
                    'success' => false,
                    'message' => sprintf(__('Không tìm thấy file sao lưu: %s', 'laca'), $specificFile),
                ];
            }
        } else {
            $targetFile = $backups[0]['path'];
        }

        // Kiểm tra toàn vẹn file gz
        if (!ShellProcess::verifyGzipFile($targetFile)) {
            return [
                'success' => false,
                'message' => sprintf(__('File sao lưu bị hỏng hoặc không đúng định dạng gzip: %s', 'laca'), $targetFile),
            ];
        }

        // Nhập vào cơ sở dữ liệu local: gunzip -c <targetFile> | wp db import -
        $cmd = sprintf('gunzip -c %s | wp db import -', escapeshellarg($targetFile));
        $res = ShellProcess::run(['bash', '-c', $cmd], null, ABSPATH, null, 300);

        if ($res['exit_code'] !== 0) {
            return [
                'success' => false,
                'message' => sprintf(__('Import database thất bại: %s', 'laca'), trim($res['stderr'] ?: $res['stdout'])),
            ];
        }

        // Làm mới cache sau khi khôi phục
        LocalSafe::resetRuntimeCaches();

        return [
            'success' => true,
            'file'    => $targetFile,
            'message' => sprintf(__('Đã khôi phục thành công database từ bản sao lưu: %s', 'laca'), basename($targetFile)),
        ];
    }
}

