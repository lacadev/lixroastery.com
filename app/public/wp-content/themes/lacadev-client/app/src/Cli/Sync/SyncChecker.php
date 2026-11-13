<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SyncChecker
 *
 * Kiểm tra cấu hình và các điều kiện kết nối với môi trường production
 */
class SyncChecker
{
    /**
     * Chạy toàn bộ các bước kiểm tra và trả về danh sách kết quả
     *
     * @return array<array{name: string, status: string, message: string, fix?: string}>
     */
    public static function checkAll(): array
    {
        $results = [];
        $cfgPath = SyncConfig::locateConfigFile();

        // 1. Kiểm tra file cấu hình
        if (!$cfgPath) {
            $results[] = [
                'name'    => __('File cấu hình laca-sync.yml', 'laca'),
                'status'  => 'ERROR',
                'message' => __('Không tìm thấy file laca-sync.yml ở thư mục gốc của repository.', 'laca'),
                'fix'     => __('Chạy lệnh "wp laca sync-init" để tạo file mẫu laca-sync.example.yml rồi sao chép thành laca-sync.yml.', 'laca'),
            ];
            return $results;
        }

        $results[] = [
            'name'    => __('File cấu hình laca-sync.yml', 'laca'),
            'status'  => 'OK',
            'message' => sprintf(__('Đã nạp từ %s', 'laca'), $cfgPath),
        ];

        $prod = SyncConfig::getProduction();
        $sshTarget = $prod['ssh'] ?? '';
        $remotePath = rtrim($prod['path'] ?? '', '/');
        $remoteUrl = rtrim($prod['url'] ?? '', '/');
        $remoteWp = $prod['wp'] ?? 'wp';

        if (empty($sshTarget) || empty($remotePath)) {
            $results[] = [
                'name'    => __('Thông số SSH production', 'laca'),
                'status'  => 'ERROR',
                'message' => __('Thiếu mục "ssh" hoặc "path" trong phần production của laca-sync.yml.', 'laca'),
                'fix'     => __('Cập nhật ssh: "user@host:port" và path: "/home/user/public_html" trong file laca-sync.yml.', 'laca'),
            ];
            return $results;
        }

        // 2. Kiểm tra kết nối SSH
        $sshTest = ShellProcess::runSsh($sshTarget, 'true', 10);
        if ($sshTest['exit_code'] !== 0) {
            $results[] = [
                'name'    => __('Kết nối SSH tới production', 'laca'),
                'status'  => 'ERROR',
                'message' => sprintf(__('Không thể kết nối SSH tới "%s": %s', 'laca'), $sshTarget, trim($sshTest['stderr'] ?: $sshTest['stdout'])),
                'fix'     => __('Kiểm tra SSH key (~/.ssh/id_rsa hoặc id_ed25519) đã được thêm vào cPanel / Authorized Keys, hoặc cấu hình ~/.ssh/config.', 'laca'),
            ];
            return $results;
        }

        $results[] = [
            'name'    => __('Kết nối SSH tới production', 'laca'),
            'status'  => 'OK',
            'message' => sprintf(__('Đã xác thực thành công với %s', 'laca'), $sshTarget),
        ];

        // 3. Kiểm tra các binary hỗ trợ trên remote: rsync, gzip
        $binCheckCmd = 'command -v rsync >/dev/null 2>&1 && command -v gzip >/dev/null 2>&1 && echo "TOOLS_OK" || echo "TOOLS_MISSING"';
        $binRes = ShellProcess::runSsh($sshTarget, $binCheckCmd, 10);
        if (trim($binRes['stdout']) !== 'TOOLS_OK') {
            $results[] = [
                'name'    => __('Công cụ server (rsync, gzip)', 'laca'),
                'status'  => 'WARNING',
                'message' => __('Một trong các công cụ rsync hoặc gzip không tìm thấy trên server.', 'laca'),
                'fix'     => __('Liên hệ quản trị hosting cPanel để bật rsync và gzip trong môi trường SSH.', 'laca'),
            ];
        } else {
            $results[] = [
                'name'    => __('Công cụ server (rsync, gzip)', 'laca'),
                'status'  => 'OK',
                'message' => __('rsync và gzip đều sẵn sàng trên server.', 'laca'),
            ];
        }

        // 4. Kiểm tra WP-CLI trên remote
        $wpInfoCmd = sprintf('cd %s && %s --info 2>/dev/null', escapeshellarg($remotePath), (SyncConfig::remoteWpCommand((string) $remoteWp) ?? 'wp'));
        $wpInfoRes = ShellProcess::runSsh($sshTarget, $wpInfoCmd, 15);
        if ($wpInfoRes['exit_code'] !== 0 || !str_contains($wpInfoRes['stdout'], 'OS information')) {
            $results[] = [
                'name'    => __('WP-CLI trên production', 'laca'),
                'status'  => 'ERROR',
                'message' => sprintf(__('Không thể chạy WP-CLI bằng lệnh "%s" tại thư mục "%s".', 'laca'), $remoteWp, $remotePath),
                'fix'     => __("Server chưa có wp-cli hoặc sai đường dẫn. Để cài vào hosting cPanel:\n   mkdir -p ~/bin && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar\n   chmod +x wp-cli.phar && mv wp-cli.phar ~/bin/wp\n   Sau đó cấu hình wp: '~/bin/wp' trong laca-sync.yml.", 'laca'),
            ];
            return $results;
        }

        $results[] = [
            'name'    => __('WP-CLI trên production', 'laca'),
            'status'  => 'OK',
            'message' => __('WP-CLI khả dụng và phản hồi tốt.', 'laca'),
        ];

        // 5. Kiểm tra đúng thư mục WordPress và so khớp URL
        $homeCmd = sprintf('cd %s && %s option get home --quiet 2>/dev/null', escapeshellarg($remotePath), (SyncConfig::remoteWpCommand((string) $remoteWp) ?? 'wp'));
        $homeRes = ShellProcess::runSsh($sshTarget, $homeCmd, 15);
        $actualHome = rtrim(trim($homeRes['stdout']), '/');

        if ($actualHome === '') {
            $results[] = [
                'name'    => __('Thư mục WordPress production', 'laca'),
                'status'  => 'ERROR',
                'message' => sprintf(__('Thư mục "%s" không nhận diện được WordPress hợp lệ hoặc không đọc được option home.', 'laca'), $remotePath),
                'fix'     => __('Kiểm tra lại đường dẫn "path" trong laca-sync.yml.', 'laca'),
            ];
            return $results;
        }

        if (!empty($remoteUrl) && $actualHome !== $remoteUrl) {
            $results[] = [
                'name'    => __('Khớp URL production', 'laca'),
                'status'  => 'WARNING',
                'message' => sprintf(__('URL thực tế (%s) khác với cấu hình url trong laca-sync.yml (%s).', 'laca'), $actualHome, $remoteUrl),
                'fix'     => sprintf(__('Nên cập nhật url: "%s" trong laca-sync.yml.', 'laca'), $actualHome),
            ];
        } else {
            $results[] = [
                'name'    => __('Khớp URL production', 'laca'),
                'status'  => 'OK',
                'message' => sprintf(__('URL production khớp: %s', 'laca'), $actualHome),
            ];
        }

        // 6. Dung lượng DB production & dung lượng trống local
        $dbSizeCmd = sprintf('cd %s && %s db size --size_format=b --quiet 2>/dev/null', escapeshellarg($remotePath), (SyncConfig::remoteWpCommand((string) $remoteWp) ?? 'wp'));
        $dbSizeRes = ShellProcess::runSsh($sshTarget, $dbSizeCmd, 15);
        $prodDbBytes = (int) trim($dbSizeRes['stdout']);

        $localFreeSpace = @disk_free_space(ABSPATH);

        if ($prodDbBytes > 0) {
            $humanProdDb = size_format($prodDbBytes, 2);
            $requiredBytes = $prodDbBytes * 3;
            $humanRequired = size_format($requiredBytes, 2);
            $humanFree = $localFreeSpace ? size_format($localFreeSpace, 2) : __('Không xác định', 'laca');

            if ($localFreeSpace && $localFreeSpace < $requiredBytes) {
                $results[] = [
                    'name'    => __('Dung lượng ổ đĩa local', 'laca'),
                    'status'  => 'ERROR',
                    'message' => sprintf(__('Dung lượng DB: %s. Local cần ít nhất 3x (%s) nhưng chỉ còn trống %s.', 'laca'), $humanProdDb, $humanRequired, $humanFree),
                    'fix'     => __('Dọn bớt ổ đĩa local trước khi kéo database.', 'laca'),
                ];
            } else {
                $results[] = [
                    'name'    => __('Ước tính dung lượng DB & Ổ đĩa', 'laca'),
                    'status'  => 'OK',
                    'message' => sprintf(__('Dung lượng DB: %s (yêu cầu local >= %s, hiện trống %s)', 'laca'), $humanProdDb, $humanRequired, $humanFree),
                ];
            }
        } else {
            $results[] = [
                'name'    => __('Ước tính dung lượng DB', 'laca'),
                'status'  => 'WARNING',
                'message' => __('Không thể đo trực tiếp dung lượng DB từ production.', 'laca'),
            ];
        }

        return $results;
    }
}

