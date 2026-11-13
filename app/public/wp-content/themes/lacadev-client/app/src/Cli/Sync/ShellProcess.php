<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ShellProcess
 *
 * Tiện ích thực thi lệnh hệ thống an toàn bằng proc_open,
 * tuân thủ strict escaping và streaming cho các tập tin DB lớn.
 */
class ShellProcess
{
    /**
     * Chạy một lệnh và lấy output kết quả
     *
     * @param array<string> $args Danh sách tham số (tham số đầu tiên là binary)
     * @param string|null $stdin Nội dung gửi vào stdin
     * @param string|null $cwd Thư mục làm việc
     * @param array|null $env Biến môi trường
     * @param int $timeout Giới hạn thời gian (giây)
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    public static function run(
        array $args,
        ?string $stdin = null,
        ?string $cwd = null,
        ?array $env = null,
        int $timeout = 60
    ): array {
        $cmdLine = self::buildCommand($args);

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($cmdLine, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            return [
                'exit_code' => -1,
                'stdout'    => '',
                'stderr'    => __('Không thể khởi tạo tiến trình hệ thống.', 'laca'),
            ];
        }

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exit_code' => $exitCode,
            'stdout'    => (string) $stdout,
            'stderr'    => (string) $stderr,
        ];
    }

    /**
     * Chạy lệnh SSH tới máy chủ đích
     */
    public static function runSsh(
        string $sshTarget,
        string $remoteCommand,
        int $timeout = 60
    ): array {
        $parsed = SyncConfig::parseSshTarget($sshTarget);
        $args = ['ssh', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10', '-o', 'ServerAliveInterval=30'];

        if ($parsed['port'] !== 22) {
            $args[] = '-p';
            $args[] = (string) $parsed['port'];
        }

        $dest = $parsed['user'] ? $parsed['user'] . '@' . $parsed['host'] : $parsed['host'];
        $args[] = $dest;
        $args[] = $remoteCommand;

        return self::run($args, null, null, null, $timeout);
    }

    /**
     * Stream dữ liệu từ lệnh xuất qua SSH thẳng vào file local nén gz
     *
     * @param string $sshTarget
     * @param string $remoteCmd
     * @param string $localFilePath
     * @param callable|null $progressCallback Nhận ($bytesReceived)
     * @return array{success: bool, bytes: int, error: string}
     */
    public static function streamSshToFile(
        string $sshTarget,
        string $remoteCmd,
        string $localFilePath,
        ?callable $progressCallback = null
    ): array {
        $dir = dirname($localFilePath);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $fp = @fopen($localFilePath, 'wb');
        if (!$fp) {
            return [
                'success' => false,
                'bytes'   => 0,
                'error'   => sprintf(__('Không thể mở file đích để ghi: %s', 'laca'), $localFilePath),
            ];
        }

        $parsed = SyncConfig::parseSshTarget($sshTarget);
        $args = ['ssh', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10', '-o', 'ServerAliveInterval=30'];
        if ($parsed['port'] !== 22) {
            $args[] = '-p';
            $args[] = (string) $parsed['port'];
        }
        $dest = $parsed['user'] ? $parsed['user'] . '@' . $parsed['host'] : $parsed['host'];
        $args[] = $dest;
        $args[] = $remoteCmd;

        $cmdLine = self::buildCommand($args);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'], // stdout (dữ liệu gzip stream về)
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($cmdLine, $descriptors, $pipes);
        if (!is_resource($process)) {
            fclose($fp);
            return [
                'success' => false,
                'bytes'   => 0,
                'error'   => __('Không thể mở tiến trình SSH.', 'laca'),
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], false);

        $totalBytes = 0;
        $chunkSize = 65536; // 64KB buffer

        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], $chunkSize);
            if ($chunk !== false && strlen($chunk) > 0) {
                $written = fwrite($fp, $chunk);
                $totalBytes += $written;
                if ($progressCallback !== null) {
                    $progressCallback($totalBytes);
                }
            }
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        fclose($fp);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return [
                'success' => false,
                'bytes'   => $totalBytes,
                'error'   => sprintf(__('Lệnh SSH trả về mã lỗi %d: %s', 'laca'), $exitCode, trim((string) $stderr)),
            ];
        }

        return [
            'success' => true,
            'bytes'   => $totalBytes,
            'error'   => '',
        ];
    }

    /**
     * Kiểm tra tính toàn vẹn của file gzip
     */
    public static function verifyGzipFile(string $filePath): bool
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return false;
        }

        $res = self::run(['gzip', '-t', $filePath]);
        return $res['exit_code'] === 0;
    }

    /**
     * Dựng chuỗi command line an toàn với escapeshellarg
     */
    public static function buildCommand(array $args): string
    {
        $escaped = array_map('escapeshellarg', $args);
        return implode(' ', $escaped);
    }
}

