<?php

namespace App\Settings;

/**
 * BlockSyncReceiver
 *
 * Nhận block files từ lacadev.com qua REST API, ghi vào block-gutenberg/ của child theme.
 * Blocks được ghi vào: lacadev-client-child/block-gutenberg/{block_name}/
 * Nhờ vậy khi update lacadev-client (parent theme) sẽ không ảnh hưởng đến blocks đã sync.
 *
 * Endpoint: POST /wp-json/lacadev/v1/sync-block
 * Status:   GET  /wp-json/lacadev/v1/sync-block/status
 */
class BlockSyncReceiver
{
    private const NAMESPACE   = 'lacadev/v1';
    private const ROUTE       = 'sync-block';
    private const KEY_OPTION  = 'laca_sync_key';
    private const LOG_OPTION  = 'laca_block_activity_log';
    private const INST_OPTION = 'laca_blocks_installed';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::ROUTE, [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'receiveBlock'],
            'permission_callback' => '__return_true', // Auth được xử lý trong callback
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // REST CALLBACKS
    // =========================================================================

    public function receiveBlock(\WP_REST_Request $request): \WP_REST_Response
    {
        // --- Authenticate ---
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'API Key không hợp lệ.',
            ], 401);
        }

        // --- Validate payload ---
        $blockName = self::sanitizeBlockRelativePath((string) ($request->get_param('block_name') ?? ''));
        $version   = sanitize_text_field($request->get_param('version') ?? '1.0.0');
        $files     = $request->get_param('files') ?? [];

        if (empty($blockName)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu block_name'], 400);
        }

        if (empty($files) || !is_array($files)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Không có files'], 400);
        }

        // --- Ghi files ---
        // Ghi vào child theme để tách biệt với parent theme (lacadev-client).
        // Khi update lacadev-client sẽ không xoá blocks đã sync.
        // get_stylesheet_directory() trả về .../lacadev-client-child/theme khi child theme active,
        // nên dirname() lên 1 cấp → .../lacadev-client-child/
        $blockDir = dirname(get_stylesheet_directory()) . '/block-gutenberg/' . $blockName;

        // Xác định đây là install mới hay update
        $installed = get_option(self::INST_OPTION, []);
        $oldVersion = $installed[$blockName] ?? null;
        $isUpdate   = $oldVersion !== null;

        try {
            $this->writeBlockFiles($blockDir, $files);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Lỗi ghi file: ' . $e->getMessage(),
            ], 500);
        }

        // --- Cập nhật option installed ---
        $installed[$blockName] = $version;
        update_option(self::INST_OPTION, $installed);

        // --- Ghi Activity Log ---
        if ($isUpdate) {
            $logMsg = "🔄 Cập nhật <strong>{$blockName}</strong> {$oldVersion} → {$version}";
        } else {
            $logMsg = "✅ Nhận <strong>{$blockName}</strong> ({$version})";
        }
        $this->appendLog($logMsg);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $isUpdate
                ? "Đã cập nhật {$blockName} từ {$oldVersion} lên {$version}"
                : "Đã nhận {$blockName} v{$version} thành công",
        ], 200);
    }

    public function getStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'API Key không hợp lệ.'], 401);
        }

        $response = [
            'success'   => true,
            'installed' => get_option(self::INST_OPTION, []),
        ];

        // Nếu hub hỏi kèm ?block=xxx, trả thêm checksum nội dung hiện tại của
        // block đó trên đĩa — dùng để hub phát hiện site khách đã tự sửa file
        // trước khi ghi đè (xem BlockSyncSender::detectConflict() phía hub).
        $blockName = self::sanitizeBlockRelativePath((string) ($request->get_param('block') ?? ''));
        if (!empty($blockName)) {
            $blockDir = dirname(get_stylesheet_directory()) . '/block-gutenberg/' . $blockName;
            $response['checksum'] = is_dir($blockDir) ? $this->computeChecksum($blockDir) : null;
        }

        return new \WP_REST_Response($response, 200);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function authenticate(\WP_REST_Request $request): bool
    {
        $storedKey  = get_option(self::KEY_OPTION, '');
        $requestKey = $request->get_header('X-Laca-Key') ?? '';

        if (empty($storedKey) || empty($requestKey)) {
            return false;
        }

        return hash_equals($storedKey, $requestKey);
    }

    /**
     * Làm sạch "block relative path" — tên block phẳng (không "/") hoặc
     * "{bucket}/{block}" (đúng 1 dấu "/"). Dùng thay cho sanitize_key() vì
     * sanitize_key() xoá sạch dấu "/" và sẽ phá path có bucket.
     */
    private static function sanitizeBlockRelativePath(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $segments = array_filter(explode('/', $raw), static fn ($s) => $s !== '');
        $segments = array_map(static fn ($s) => preg_replace('/[^a-z0-9_-]/', '', $s), $segments);
        $segments = array_filter($segments, static fn ($s) => $s !== '' && $s !== '.' && $s !== '..');
        $segments = array_slice($segments, 0, 2);

        return implode('/', $segments);
    }

    /**
     * Giải mã base64 và ghi files vào block directory.
     * $files = ['relative/path' => 'base64_encoded_content']
     *
     * @throws \RuntimeException nếu không thể tạo thư mục hoặc ghi file
     */
    private function writeBlockFiles(string $blockDir, array $files): void
    {
        // Validate block name để tránh path traversal
        // Validate trong phạm vi block-gutenberg/ của child theme
        $childBlockGutenberg = dirname(get_stylesheet_directory()) . '/block-gutenberg';
        $realStyleDir = realpath($childBlockGutenberg);
        if ($realStyleDir === false) {
            // Thư mục chưa tồn tại - sẽ được tạo khi ghi file đầu tiên
            $realStyleDir = $childBlockGutenberg;
        }

        // Xóa sạch thư mục block CŨ (nếu có) trước khi ghi bản mới. Nếu
        // không làm vậy, file bị xóa/đổi tên giữa các phiên bản (vd refactor
        // block) sẽ nằm lại trên đĩa mãi mãi — khiến checksum tính từ disk
        // (getStatus()) không bao giờ khớp với checksum hub lưu từ đúng
        // payload vừa push, gây báo nhầm "site khách đã tự sửa" ở lần push
        // kế tiếp dù khách không hề đụng vào file nào.
        $realBlockDir = realpath($blockDir);
        if ($realBlockDir !== false && str_starts_with($realBlockDir, (string) $realStyleDir)) {
            $this->deleteDirectoryContents($realBlockDir);
        }

        foreach ($files as $relativePath => $base64Content) {
            // Sanitize path: loại bỏ ký tự nguy hiểm
            $cleanPath = preg_replace('/[^a-zA-Z0-9\/_\-.]/u', '', $relativePath);
            if (str_contains($cleanPath, '..')) {
                continue; // Skip path traversal attempts
            }

            $targetPath = $blockDir . '/' . $cleanPath;

            // Tạo thư mục nếu chưa có
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                if (!wp_mkdir_p($targetDir)) {
                    throw new \RuntimeException("Không thể tạo thư mục: {$targetDir}");
                }
            }

            // Xác minh path nằm trong child theme directory
            $realTargetDir = realpath($targetDir);
            if ($realTargetDir === false || !str_starts_with($realTargetDir, (string) $realStyleDir)) {
                continue; // Skip nếu path ngoài child theme
            }

            // Decode và ghi file
            $content = base64_decode($base64Content, strict: true);
            if ($content === false) {
                continue; // Skip file bị hỏng
            }

            file_put_contents($targetPath, $content);
        }
    }

    /**
     * Tính checksum nội dung hiện tại của 1 block trên đĩa — md5 của toàn bộ
     * md5(file) theo từng path tương đối, đã sắp xếp để thứ tự đọc thư mục
     * không ảnh hưởng kết quả. Phải cùng thuật toán với
     * BlockSyncSender::computeChecksumFromFiles() phía hub (hash trên nội
     * dung đã giải mã base64) để 2 bên so sánh được trực tiếp.
     */
    private function computeChecksum(string $blockDir): string
    {
        $hashes   = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($blockDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $relPath = ltrim(str_replace($blockDir, '', $file->getPathname()), '/');
            $hash    = md5_file($file->getPathname());
            if ($hash !== false) {
                $hashes[$relPath] = $hash;
            }
        }

        ksort($hashes);

        return md5((string) wp_json_encode($hashes));
    }

    /**
     * Xóa toàn bộ nội dung 1 thư mục (đệ quy) nhưng giữ lại thư mục gốc.
     * Chỉ gọi sau khi đã xác nhận $dir nằm trong phạm vi an toàn
     * block-gutenberg/ của child theme (xem writeBlockFiles()).
     */
    private function deleteDirectoryContents(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }

    /**
     * Ghi entry vào activity log (giữ tối đa 50 entries gần nhất).
     */
    private function appendLog(string $message): void
    {
        $log = get_option(self::LOG_OPTION, []);

        array_unshift($log, [
            'time'    => current_time('mysql'),
            'message' => $message,
        ]);

        // Giữ tối đa 50 entries
        $log = array_slice($log, 0, 50);

        update_option(self::LOG_OPTION, $log, false);
    }

    // =========================================================================
    // STATIC: AUTO-GENERATE API KEY
    // =========================================================================

    public static function ensureApiKey(): string
    {
        $key = get_option(self::KEY_OPTION, '');
        if (empty($key)) {
            $key = wp_generate_uuid4();
            update_option(self::KEY_OPTION, $key);
        }
        return $key;
    }
}
