<?php

namespace App\Cli;

use App\Cli\Sync\DbPuller;
use App\Cli\Sync\DbRestorer;
use App\Cli\Sync\DriftDetector;
use App\Cli\Sync\FilePuller;
use App\Cli\Sync\LocalSafe;
use App\Cli\Sync\MediaPuller;
use App\Cli\Sync\SyncChecker;
use App\Cli\Sync\SyncConfig;
use App\Cli\Sync\SyncInit;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LacaSyncCommand
 *
 * Bộ công cụ WP-CLI đồng bộ Production -> Local cho Laca Theme.
 */
class LacaSyncCommand
{
    /**
     * Kiểm tra cấu hình và kết nối tới máy chủ production.
     *
     * ## EXAMPLES
     *
     *     wp laca sync-check
     *
     * @subcommand sync-check
     */
    public function syncCheck($args, $assocArgs): void
    {
        \WP_CLI::line('🔍 ' . __('Đang kiểm tra kết nối và điều kiện đồng bộ với Production...', 'laca'));

        $results = SyncChecker::checkAll();

        $rows = [];
        $hasError = false;

        foreach ($results as $item) {
            $statusLabel = match ($item['status']) {
                'OK'      => \WP_CLI::colorize('%G[OK]%n'),
                'WARNING' => \WP_CLI::colorize('%Y[CẢNH BÁO]%n'),
                default   => \WP_CLI::colorize('%R[LỖI]%n'),
            };

            if ($item['status'] === 'ERROR') {
                $hasError = true;
            }

            $rows[] = [
                'Hạng mục'  => $item['name'],
                'Trạng thái' => $statusLabel,
                'Chi tiết'  => $item['message'],
            ];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['Hạng mục', 'Trạng thái', 'Chi tiết']);

        // In các gợi ý khắc phục nếu có lỗi
        foreach ($results as $item) {
            if (!empty($item['fix'])) {
                \WP_CLI::line('');
                \WP_CLI::warning(sprintf("%s:\n   %s", $item['name'], $item['fix']));
            }
        }

        if ($hasError) {
            \WP_CLI::error(__('Kiểm tra phát hiện lỗi. Vui lòng khắc phục trước khi chạy lệnh đồng bộ.', 'laca'));
        } else {
            \WP_CLI::success(__('Môi trường đã sẵn sàng để đồng bộ với Production!', 'laca'));
        }
    }

    /**
     * Kéo database từ production về local.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Tự động đồng ý mà không cần hỏi xác nhận.
     *
     * [--anonymize]
     * : Ẩn danh dữ liệu người dùng và lịch sử/liên hệ sau khi kéo.
     *
     * [--no-backup]
     * : Bỏ qua bước sao lưu database local trước khi ghi đè.
     *
     * [--tables=<tables>]
     * : Chỉ kéo các bảng cụ thể (phân cách bằng dấu phẩy).
     *
     * ## EXAMPLES
     *
     *     wp laca pull-db
     *     wp laca pull-db --anonymize
     *     wp laca pull-db --tables=wp_posts,wp_postmeta
     *
     * @subcommand pull-db
     */
    public function pullDb($args, $assocArgs): void
    {
        $startTime = microtime(true);

        // 1. Kiểm tra an toàn trước
        $pre = DbPuller::checkPreconditions();
        if (!$pre['valid']) {
            \WP_CLI::error($pre['message']);
        }

        $prod = SyncConfig::getProduction();
        $prodUrl = $prod['url'] ?? '';
        $localUrl = home_url();

        \WP_CLI::line(\WP_CLI::colorize('%C== BẮT ĐẦU QUY TRÌNH KÉO DATABASE PRODUCTION VỀ LOCAL ==%n'));
        \WP_CLI::line(sprintf(__('Production URL : %s', 'laca'), $prodUrl));
        \WP_CLI::line(sprintf(__('Local URL      : %s', 'laca'), $localUrl));

        $anonymize = !empty($assocArgs['anonymize']) || (!empty(SyncConfig::getDbConfig()['anonymize']));

        if (empty($assocArgs['yes'])) {
            \WP_CLI::confirm(
                \WP_CLI::colorize('%YDatabase local hiện tại sẽ được thay thế bằng dữ liệu từ production. Bạn có chắc chắn muốn tiếp tục?%n'),
                $assocArgs
            );
        }

        // 2. Sao lưu local
        if (empty($assocArgs['no-backup'])) {
            \WP_CLI::line('📦 ' . __('Đang sao lưu database local...', 'laca'));
            $bakRes = DbPuller::backupLocal();
            if (!$bakRes['success']) {
                \WP_CLI::error($bakRes['message']);
            }
            \WP_CLI::line('   ' . $bakRes['message']);
        }

        // 3. Kéo từ production
        \WP_CLI::line('⬇️  ' . __('Đang xuất và tải dữ liệu nén qua SSH từ production...', 'laca'));
        $pullOpts = [];
        if (!empty($assocArgs['tables'])) {
            $pullOpts['tables'] = explode(',', (string) $assocArgs['tables']);
        }

        $pullRes = DbPuller::pullFromProduction($pullOpts, function ($bytes) {
            static $lastPrint = 0;
            if (time() - $lastPrint >= 2) {
                \WP_CLI::line(sprintf(__('   Đã nhận: %s...', 'laca'), size_format($bytes, 2)));
                $lastPrint = time();
            }
        });

        if (!$pullRes['success']) {
            \WP_CLI::error($pullRes['error']);
        }

        \WP_CLI::line(sprintf(__('   Tải về hoàn tất: %s (%s)', 'laca'), basename($pullRes['file']), size_format($pullRes['bytes'], 2)));

        // 4. Nhập vào local database
        \WP_CLI::line('📥 ' . __('Đang giải nén và nhập dữ liệu vào database local...', 'laca'));
        $importRes = DbPuller::importToLocal($pullRes['file']);
        if (!$importRes['success']) {
            \WP_CLI::error($importRes['message']);
        }

        // 5. Làm an toàn local (LocalSafe)
        \WP_CLI::line('🛡️  ' . __('Đang kích hoạt chế độ an toàn local (LocalSafe)...', 'laca'));
        $safeRes = LocalSafe::makeSafe($prodUrl, $localUrl, $anonymize);

        foreach ($safeRes['steps'] as $step) {
            \WP_CLI::line('   ✓ ' . $step);
        }

        // Ghi nhận lần kéo gần nhất
        $pullMeta = [
            'time'       => time(),
            'user'       => get_current_user(),
            'file'       => basename($pullRes['file']),
            'bytes'      => $pullRes['bytes'],
            'prod_url'   => $prodUrl,
            'anonymized' => $anonymize,
        ];
        update_option('laca_last_pull', $pullMeta, false);

        $elapsed = round(microtime(true) - $startTime, 2);
        \WP_CLI::success(sprintf(__('Đồng bộ database thành công trong %s giây!', 'laca'), $elapsed));
        \WP_CLI::line('');
        \WP_CLI::line(\WP_CLI::colorize('%G💡 LƯU Ý:%n'));
        \WP_CLI::line(__('   - Mật khẩu đăng nhập local giờ đây giống với tài khoản trên production.', 'laca'));
        \WP_CLI::line(__('   - Nếu cần đặt lại mật khẩu admin local, hãy chạy: wp user update <admin-user> --user_pass=<new_pass>', 'laca'));
    }

    /**
     * Phát hiện sai lệch (drift) giữa production và git local.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Định dạng hiển thị: table hoặc json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp laca drift
     *     wp laca drift --format=json
     *
     * @subcommand drift
     */
    public function drift($args, $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'table';

        if ($format === 'table') {
            \WP_CLI::line('🔎 ' . __('Đang so sánh sai lệch tập tin và phiên bản giữa production và local...', 'laca'));
        }

        $result = DriftDetector::detect();

        if ($format === 'json') {
            \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            if ($result['exit_code'] !== 0) {
                exit($result['exit_code']);
            }
            return;
        }

        // Bảng 1: Lệch phiên bản plugin / core
        if (!empty($result['version_drifts'])) {
            \WP_CLI::line('');
            \WP_CLI::line(\WP_CLI::colorize('%C-- LỆCH PHIÊN BẢN (PLUGIN / CORE / THEME) --%n'));
            $rows = [];
            foreach ($result['version_drifts'] as $vd) {
                $rows[] = [
                    'Thành phần'     => $vd['item'],
                    'Loại'           => $vd['type'],
                    'Bản Production' => $vd['prod_version'],
                    'Bản Local'      => $vd['local_version'],
                    'Tình trạng'     => $vd['status'],
                ];
            }
            \WP_CLI\Utils\format_items('table', $rows, ['Thành phần', 'Loại', 'Bản Production', 'Bản Local', 'Tình trạng']);
        }

        // Bảng 2: Lệch file
        if (!empty($result['file_drifts'])) {
            \WP_CLI::line('');
            \WP_CLI::line(\WP_CLI::colorize('%C-- LỆCH TẬP TIN CODE (RSYNC CHECKSUM) --%n'));
            $rows = [];
            foreach ($result['file_drifts'] as $fd) {
                $typeLabel = match ($fd['type']) {
                    'CREATED' => \WP_CLI::colorize('%G[MỚI]%n'),
                    'DELETED' => \WP_CLI::colorize('%R[ĐÃ XOÁ]%n'),
                    default   => \WP_CLI::colorize('%Y[SỬA]%n'),
                };
                $rows[] = [
                    'Loại'     => $typeLabel,
                    'Đường dẫn' => $fd['path'],
                    'Mô tả'    => $fd['description'],
                ];
            }
            \WP_CLI\Utils\format_items('table', $rows, ['Loại', 'Đường dẫn', 'Mô tả']);
        }

        // Cảnh báo file dirty local nếu có
        if (!empty($result['local_dirty_files'])) {
            \WP_CLI::line('');
            \WP_CLI::warning(sprintf(
                __("Local đang có %d tập tin chưa commit git. Hãy chú ý khi kéo file để tránh ghi đè.", 'laca'),
                count($result['local_dirty_files'])
            ));
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                \WP_CLI::warning($err);
            }
        }

        if (!$result['has_drift']) {
            \WP_CLI::success(__('Tuyệt vời! Không phát hiện sai lệch nào giữa production và local.', 'laca'));
            exit(0);
        } else {
            \WP_CLI::warning(sprintf(
                __('Phát hiện %d file và %d mục phiên bản bị lệch giữa production và git.', 'laca'),
                count($result['file_drifts']),
                count($result['version_drifts'])
            ));
            \WP_CLI::line(__('Gợi ý: Dùng lệnh "wp laca pull-files" để kéo các thay đổi này về local.', 'laca'));
            exit(3);
        }
    }

    /**
     * Kéo các tập tin thay đổi từ production về local.
     *
     * ## OPTIONS
     *
     * [--path=<path>]
     * : Đường dẫn tương đối cụ thể cần kéo (ví dụ: wp-content/plugins/woocommerce).
     *
     * [--delete]
     * : Xóa các file chỉ có ở local mà không có ở production (CẨN TRỌNG).
     *
     * [--force-dirty]
     * : Ép chạy kể cả khi local có thay đổi chưa commit.
     *
     * [--yes]
     * : Tự động xác nhận.
     *
     * ## EXAMPLES
     *
     *     wp laca pull-files
     *     wp laca pull-files --path=wp-content/plugins/my-plugin
     *
     * @subcommand pull-files
     */
    public function pullFiles($args, $assocArgs): void
    {
        if (!empty($assocArgs['delete']) && empty($assocArgs['yes'])) {
            \WP_CLI::confirm(
                \WP_CLI::colorize('%RCẢNH BÁO: Tùy chọn --delete sẽ XÓA các file local không tồn tại trên production. Bạn có chắc chắn?%n'),
                $assocArgs
            );
        }

        \WP_CLI::line('⬇️  ' . __('Đang kéo các tập tin từ production về local...', 'laca'));

        $res = FilePuller::pull([
            'path'        => $assocArgs['path'] ?? null,
            'delete'      => !empty($assocArgs['delete']),
            'force_dirty' => !empty($assocArgs['force_dirty']),
            'yes'         => !empty($assocArgs['yes']),
        ]);

        if (!$res['success']) {
            foreach ($res['errors'] as $err) {
                \WP_CLI::error($err);
            }
            return;
        }

        \WP_CLI::success(__('Đã kéo các tập tin từ production về local thành công!', 'laca'));

        if (!empty($res['warnings'])) {
            foreach ($res['warnings'] as $warn) {
                \WP_CLI::line('');
                \WP_CLI::warning($warn);
            }
        }

        if (!empty($res['git_status'])) {
            \WP_CLI::line('');
            \WP_CLI::line(\WP_CLI::colorize('%CTình trạng git sau khi kéo:%n'));
            \WP_CLI::line($res['git_status']);
        }

        if (!empty($res['suggested_commit'])) {
            \WP_CLI::line('');
            \WP_CLI::line(\WP_CLI::colorize('%GGợi ý commit message:%n'));
            \WP_CLI::line($res['suggested_commit']);
        }
    }

    /**
     * Tải hình ảnh trong wp-content/uploads/ từ production về local có chọn lọc.
     *
     * ## OPTIONS
     *
     * [--since=<since>]
     * : Khoảng thời gian uploads gần nhất cần tải (ví dụ: 30d, 60d).
     * ---
     * default: 30d
     * ---
     *
     * [--yes]
     * : Bỏ qua hỏi xác nhận.
     *
     * ## EXAMPLES
     *
     *     wp laca pull-media --since=30d
     *
     * @subcommand pull-media
     */
    public function pullMedia($args, $assocArgs): void
    {
        $since = $assocArgs['since'] ?? '30d';

        \WP_CLI::line(sprintf(__('Đang tải hình ảnh uploads trong %s gần nhất từ production...', 'laca'), $since));

        $res = MediaPuller::pull([
            'since' => $since,
            'yes'   => !empty($assocArgs['yes']),
        ]);

        if (!$res['success']) {
            foreach ($res['errors'] as $err) {
                \WP_CLI::warning($err);
            }
        }

        if (!empty($res['folders'])) {
            \WP_CLI::success(sprintf(__('Đã tải xong các thư mục: %s', 'laca'), implode(', ', $res['folders'])));
        } else {
            \WP_CLI::line(__('Không có hình ảnh nào được tải.', 'laca'));
        }
    }

    /**
     * Làm sạch và an toàn cho cơ sở dữ liệu local (chạy độc lập).
     *
     * ## OPTIONS
     *
     * [--anonymize]
     * : Ẩn danh dữ liệu người dùng và lịch sử/liên hệ.
     *
     * ## EXAMPLES
     *
     *     wp laca local-safe
     *     wp laca local-safe --anonymize
     *
     * @subcommand local-safe
     */
    public function localSafe($args, $assocArgs): void
    {
        \WP_CLI::line('🛡️  ' . __('Đang thực thi các bước làm an toàn cho database local...', 'laca'));

        $prod = SyncConfig::getProduction();
        $prodUrl = $prod['url'] ?? (string) get_option('laca_prod_url', '');
        $anonymize = !empty($assocArgs['anonymize']);

        $res = LocalSafe::makeSafe($prodUrl, home_url(), $anonymize);

        foreach ($res['steps'] as $step) {
            \WP_CLI::line('   ✓ ' . $step);
        }

        \WP_CLI::success(__('Site local đã được bảo vệ an toàn!', 'laca'));
    }

    /**
     * Khôi phục database local từ bản sao lưu gần nhất.
     *
     * ## OPTIONS
     *
     * [--file=<file>]
     * : Đường dẫn hoặc tên file sao lưu cụ thể trong app/sql/backups/.
     *
     * [--yes]
     * : Tự động đồng ý mà không cần hỏi xác nhận.
     *
     * ## EXAMPLES
     *
     *     wp laca restore-db
     *     wp laca restore-db --file=local-20261112-120000.sql.gz
     *
     * @subcommand restore-db
     */
    public function restoreDb($args, $assocArgs): void
    {
        $specificFile = $assocArgs['file'] ?? null;

        if (empty($assocArgs['yes'])) {
            \WP_CLI::confirm(
                \WP_CLI::colorize('%YDatabase local hiện tại sẽ được khôi phục về bản sao lưu trước đó. Tiếp tục?%n'),
                $assocArgs
            );
        }

        \WP_CLI::line('📦 ' . __('Đang khôi phục database local...', 'laca'));

        $res = DbRestorer::restore($specificFile);

        if (!$res['success']) {
            \WP_CLI::error($res['message']);
        } else {
            \WP_CLI::success($res['message']);
        }
    }

    /**
     * Khởi tạo file cấu hình mẫu và các tác vụ đồng bộ cho repository.
     *
     * ## EXAMPLES
     *
     *     wp laca sync-init
     *
     * @subcommand sync-init
     */
    public function syncInit($args, $assocArgs): void
    {
        \WP_CLI::line('🚀 ' . __('Đang khởi tạo các mẫu công cụ đồng bộ vào repository...', 'laca'));

        $res = SyncInit::run();

        if (!empty($res['copied'])) {
            \WP_CLI::line(\WP_CLI::colorize('%GĐã tạo mới:%n'));
            foreach ($res['copied'] as $f) {
                \WP_CLI::line('   + ' . $f);
            }
        }

        if (!empty($res['skipped'])) {
            \WP_CLI::line(\WP_CLI::colorize('%YĐã bỏ qua vì đã tồn tại (không ghi đè):%n'));
            foreach ($res['skipped'] as $f) {
                \WP_CLI::line('   ~ ' . $f);
            }
        }

        if ($res['gitignore']) {
            \WP_CLI::line('   + ' . __('Đã thêm laca-sync.yml và app/sql/ vào .gitignore', 'laca'));
        }

        if (!empty($res['errors'])) {
            foreach ($res['errors'] as $err) {
                \WP_CLI::warning($err);
            }
        }

        \WP_CLI::success(__('Khởi tạo hoàn tất! Hãy sao chép laca-sync.example.yml thành laca-sync.yml và điền thông tin SSH.', 'laca'));
    }
}

