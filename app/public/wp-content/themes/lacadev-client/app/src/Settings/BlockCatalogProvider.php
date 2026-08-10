<?php

namespace App\Settings;

/**
 * BlockCatalogProvider
 *
 * Phục vụ danh mục Gutenberg block (metadata + preview.png + toàn bộ file
 * nguồn) đọc-chỉ (read-only) cho hub (lacadev.com), để hub cache lại và cho
 * site khách hàng duyệt/yêu cầu đồng bộ từng block.
 *
 * Quét cả block-gutenberg/ của theme cha (lacadev-client) lẫn theme con
 * (lacadev-client-child) — nếu trùng tên, ưu tiên bản ở theme con (coi như
 * bản mới nhất/tùy biến).
 *
 * Endpoint:
 *   GET /wp-json/lacadev/v1/blocks-catalog             — danh sách block + metadata + preview
 *   GET /wp-json/lacadev/v1/blocks-catalog/{block}/files — toàn bộ file nguồn của 1 block (base64)
 *
 * Xác thực: header X-Laca-Catalog-Key, so khớp option `laca_catalog_key`
 * (khác với `laca_sync_key` của BlockSyncReceiver — 2 vai trò khác nhau:
 * BlockSyncReceiver NHẬN block được đẩy tới, còn provider này CUNG CẤP
 * block cho hub đọc).
 */
class BlockCatalogProvider
{
    private const NAMESPACE  = 'lacadev/v1';
    private const ROUTE      = 'blocks-catalog';
    private const KEY_OPTION = 'laca_catalog_key';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::ROUTE, [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'listBlocks'],
            'permission_callback' => '__return_true', // Auth được xử lý trong callback
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/(?P<block>[a-zA-Z0-9_-]+)/files', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getBlockFiles'],
            'permission_callback' => '__return_true',
            'args'                => [
                'block' => ['required' => true],
            ],
        ]);
    }

    // =========================================================================
    // REST CALLBACKS
    // =========================================================================

    public function listBlocks(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Catalog Key không hợp lệ.'], 401);
        }

        $blocks = [];

        // Quét theme cha trước, theme con sau — nếu trùng tên, bản con ghi
        // đè bản cha (coi bản con là mới nhất/tùy biến).
        foreach ($this->resolveBlockDirs() as $dir) {
            foreach ((glob("{$dir}/*/block.json") ?: []) as $file) {
                $data = json_decode((string) file_get_contents($file), true);
                if (!is_array($data)) {
                    continue;
                }

                $blockDir = dirname($file);
                $name     = basename($blockDir);

                $blocks[$name] = [
                    'name'        => $name,
                    'title'       => $data['title'] ?? $name,
                    'description' => $data['description'] ?? '',
                    'version'     => $data['version'] ?? '1.0.0',
                    'preview'     => $this->encodePreview($blockDir),
                ];
            }
        }

        ksort($blocks);

        return new \WP_REST_Response([
            'success' => true,
            'blocks'  => array_values($blocks),
        ], 200);
    }

    public function getBlockFiles(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Catalog Key không hợp lệ.'], 401);
        }

        $blockName = sanitize_key($request->get_param('block') ?? '');
        $blockDir  = $this->findBlockDir($blockName);

        if (!$blockDir) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Không tìm thấy block.'], 404);
        }

        $version       = '1.0.0';
        $blockJsonPath = "{$blockDir}/block.json";
        if (file_exists($blockJsonPath)) {
            $json    = json_decode((string) file_get_contents($blockJsonPath), true);
            $version = $json['version'] ?? '1.0.0';
        }

        $files = [];
        $this->encodeDirectoryFiles($blockDir, $blockDir, $files);

        if (empty($files)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Không có file trong block.'], 404);
        }

        return new \WP_REST_Response([
            'success'    => true,
            'block_name' => $blockName,
            'version'    => $version,
            'files'      => $files,
        ], 200);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function authenticate(\WP_REST_Request $request): bool
    {
        $storedKey  = get_option(self::KEY_OPTION, '');
        $requestKey = $request->get_header('X-Laca-Catalog-Key') ?? '';

        if (empty($storedKey) || empty($requestKey)) {
            return false;
        }

        return hash_equals($storedKey, $requestKey);
    }

    /**
     * Trả về danh sách thư mục block-gutenberg cần quét, theo thứ tự
     * cha → con (để vòng lặp gọi sau — theme con — ghi đè đúng ý nếu trùng tên).
     */
    private function resolveBlockDirs(): array
    {
        $dirs = [];

        $parent = dirname(get_template_directory()) . '/block-gutenberg';
        if (is_dir($parent)) {
            $dirs[] = $parent;
        }

        $child = dirname(get_stylesheet_directory()) . '/block-gutenberg';
        if (is_dir($child) && $child !== $parent) {
            $dirs[] = $child;
        }

        return $dirs;
    }

    private function findBlockDir(string $blockName): ?string
    {
        // Quét theo thứ tự con trước (ưu tiên bản con nếu trùng tên ở cả 2 nơi)
        foreach (array_reverse($this->resolveBlockDirs()) as $dir) {
            $candidate = "{$dir}/{$blockName}";
            if (is_dir($candidate) && file_exists("{$candidate}/block.json")) {
                return $candidate;
            }
        }

        return null;
    }

    private function encodePreview(string $blockDir): ?string
    {
        $previewPath = "{$blockDir}/preview.png";
        if (!file_exists($previewPath)) {
            return null;
        }

        return base64_encode((string) file_get_contents($previewPath));
    }

    /**
     * Đệ quy đọc tất cả files trong thư mục, encode base64 — cùng logic với
     * BlockSyncSender::encodeDirectoryFiles() bên hub, để tương thích trực
     * tiếp với BlockSyncReceiver::writeBlockFiles() phía site khách.
     */
    private function encodeDirectoryFiles(string $baseDir, string $currentDir, array &$files): void
    {
        $items = scandir($currentDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (in_array($item, ['node_modules', '.git', '.DS_Store'], true)) {
                continue;
            }

            $path         = "{$currentDir}/{$item}";
            $relativePath = ltrim(str_replace($baseDir, '', $path), '/');

            if (is_dir($path)) {
                $this->encodeDirectoryFiles($baseDir, $path, $files);
            } elseif (is_file($path)) {
                if (str_ends_with($item, '.map')) {
                    continue;
                }
                $files[$relativePath] = base64_encode((string) file_get_contents($path));
            }
        }
    }

    // =========================================================================
    // STATIC: AUTO-GENERATE CATALOG KEY
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
