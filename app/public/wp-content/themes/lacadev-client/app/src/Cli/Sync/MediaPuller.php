<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MediaPuller
 *
 * Tải hình ảnh trong wp-content/uploads/ từ production về local có chọn lọc,
 * phục vụ cho lập trình viên khi cần làm việc offline.
 */
class MediaPuller
{
    /**
     * Kéo uploads có chọn lọc theo thời gian
     *
     * @param array{since?: string, yes?: bool} $options
     * @return array{success: bool, folders: array<string>, errors: array<string>}
     */
    public static function pull(array $options = []): array
    {
        $prod = SyncConfig::getProduction();
        $sshTarget = $prod['ssh'] ?? '';
        $remotePath = rtrim($prod['path'] ?? '', '/');

        if (empty($sshTarget) || empty($remotePath)) {
            return [
                'success' => false,
                'folders' => [],
                'errors'  => [__('Thiếu cấu hình SSH production trong laca-sync.yml.', 'laca')],
            ];
        }

        $since = $options['since'] ?? '30d';
        $days = 30;
        if (preg_match('/^(\d+)d$/', $since, $m)) {
            $days = (int) $m[1];
        }

        // Tạo danh sách các tháng YYYY/MM tương ứng với số ngày
        $months = [];
        $now = time();
        $startTime = $now - ($days * 86400);

        $cur = $startTime;
        while ($cur <= $now) {
            $months[gmdate('Y/m', $cur)] = true;
            // Bước sang tháng tiếp theo
            $cur = strtotime('+15 days', $cur);
        }
        $targetMonths = array_keys($months);

        $parsedSsh = SyncConfig::parseSshTarget($sshTarget);
        $sshPortArg = ($parsedSsh['port'] !== 22) ? sprintf('-e "ssh -p %d -o BatchMode=yes"', $parsedSsh['port']) : '-e "ssh -o BatchMode=yes"';
        $remoteHostStr = $parsedSsh['user'] ? $parsedSsh['user'] . '@' . $parsedSsh['host'] : $parsedSsh['host'];

        $uploadDir = wp_upload_dir();
        $baseDir = wp_normalize_path($uploadDir['basedir']);

        $pulledFolders = [];
        $errors = [];

        foreach ($targetMonths as $ym) {
            $localFolder = $baseDir . '/' . $ym;
            if (!is_dir($localFolder)) {
                wp_mkdir_p($localFolder);
            }

            $remoteFolder = $remotePath . '/wp-content/uploads/' . $ym;

            // rsync -rlc <remote>:<remoteFolder>/ <localFolder>/
            $cmd = sprintf(
                'rsync -rlc %s %s:%s/ %s/',
                $sshPortArg,
                escapeshellarg($remoteHostStr),
                escapeshellarg($remoteFolder),
                escapeshellarg($localFolder)
            );

            $res = ShellProcess::run(['bash', '-c', $cmd], null, ABSPATH, null, 180);
            if ($res['exit_code'] === 0) {
                $pulledFolders[] = 'uploads/' . $ym;
            } else {
                // Có thể tháng đó trên remote chưa có thư mục nào
                if (!str_contains($res['stderr'], 'No such file or directory')) {
                    $errors[] = sprintf(__('Lỗi rsync thư mục uploads/%s: %s', 'laca'), $ym, trim($res['stderr']));
                }
            }
        }

        return [
            'success' => empty($errors),
            'folders' => $pulledFolders,
            'errors'  => $errors,
        ];
    }
}

