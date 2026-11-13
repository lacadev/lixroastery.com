<?php

namespace App\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BlockCatalogProvider
 *
 * Phục vụ danh mục Gutenberg block (opt-in qua `laca.catalog === true` trong `block.json`)
 * kèm manifest SHA-256 từng file và danh sách phụ thuộc `requires` cho Hub LacaDev.
 */
class BlockCatalogProvider
{
    private const NAMESPACE           = 'lacadev/v1';
    private const ROUTE               = 'blocks-catalog';
    private const KEY_OPTION          = 'laca_catalog_key';
    private const LEGACY_OPTION       = 'laca_block_sync_allow_legacy';
    private const MAX_PREVIEW_BYTES   = 524288; // 512 KB

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::ROUTE, [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'listBlocks'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/(?P<block>[a-zA-Z0-9_\-\/%]+)/files', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getBlockFiles'],
            'permission_callback' => '__return_true',
            'args'                => [
                'block' => ['required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/files', [
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
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Xác thực Catalog Key không hợp lệ.', 'laca'),
            ], 401);
        }

        $blocks = [];

        // Quét parent trước, child sau — nếu trùng đường dẫn tương đối thì child ghi đè parent.
        foreach ($this->resolveBlockDirs() as $rootDir) {
            $relPaths = $this->scanRelativePaths($rootDir);
            foreach ($relPaths as $relPath) {
                $blockDir      = "{$rootDir}/{$relPath}";
                $blockJsonPath = "{$blockDir}/block.json";
                if (!is_file($blockJsonPath)) {
                    continue;
                }

                $data = json_decode((string) file_get_contents($blockJsonPath), true);
                if (!is_array($data)) {
                    continue;
                }

                // Chỉ đưa vào catalog các block opt-in rõ ràng `laca.catalog === true`.
                if (($data['laca']['catalog'] ?? false) !== true) {
                    continue;
                }

                $requires = (isset($data['laca']['requires']) && is_array($data['laca']['requires']))
                    ? $data['laca']['requires']
                    : [];

                $files       = [];
                $shaManifest = [];
                $this->encodeDirectoryFiles($blockDir, $blockDir, $files, $shaManifest);

                $leafName          = basename($relPath);
                $blocks[$relPath] = [
                    'name'        => $leafName,
                    'path'        => $relPath,
                    'title'       => (string) ($data['title'] ?? $leafName),
                    'description' => (string) ($data['description'] ?? ''),
                    'version'     => (string) ($data['version'] ?? '1.0.0'),
                    'requires'    => $requires,
                    'checksum'    => $this->computeChecksumFromFiles($files),
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
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Xác thực Catalog Key không hợp lệ.', 'laca'),
            ], 401);
        }

        $rawBlock = rawurldecode((string) ($request->get_param('block') ?? ''));
        if (str_contains($rawBlock, '..') || str_starts_with($rawBlock, '/') || str_contains($rawBlock, "\0")) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Tên block không hợp lệ.', 'laca'),
            ], 400);
        }

        $blockName = trim((string) preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $rawBlock), '/');
        if ($blockName === '' || str_contains($blockName, '//') || substr_count($blockName, '/') > 1) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Tên block không hợp lệ.', 'laca'),
            ], 400);
        }

        $blockDir = $this->findBlockDir($blockName);
        if (!$blockDir) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy block trong catalog.', 'laca'),
            ], 404);
        }

        $blockJsonPath = "{$blockDir}/block.json";
        $json          = is_file($blockJsonPath)
            ? json_decode((string) file_get_contents($blockJsonPath), true)
            : null;

        if (!is_array($json) || ($json['laca']['catalog'] ?? false) !== true) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Block này không được bật chia sẻ trong catalog.', 'laca'),
            ], 404);
        }

        $version  = (string) ($json['version'] ?? '1.0.0');
        $requires = (isset($json['laca']['requires']) && is_array($json['laca']['requires']))
            ? $json['laca']['requires']
            : [];

        $files       = [];
        $shaManifest = [];
        $this->encodeDirectoryFiles($blockDir, $blockDir, $files, $shaManifest);

        if (empty($files)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không có file hợp lệ trong block.', 'laca'),
            ], 404);
        }

        return new \WP_REST_Response([
            'success'    => true,
            'block_name' => $blockName,
            'version'    => $version,
            'requires'   => $requires,
            'checksum'   => $this->computeChecksumFromFiles($files),
            'files'      => $files,
            'manifest'   => [
                'files'    => $shaManifest,
                'requires' => $requires,
            ],
        ], 200);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function authenticate(\WP_REST_Request $request): bool
    {
        $candidateKeys = array_values(array_unique(array_filter([
            LacaDevTrackerClient::getCatalogKey(),
            (string) get_option(self::KEY_OPTION, ''),
        ])));

        $sigHeader = $request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature');
        if (!empty($sigHeader) && !empty($candidateKeys)) {
            foreach ($candidateKeys as $key) {
                $verify = \App\Security\RequestSigner::verify($request, $key);
                if (!empty($verify['valid'])) {
                    return true;
                }
            }

            return false;
        }

        if (get_option(self::LEGACY_OPTION, 'no') !== 'yes') {
            return false;
        }

        $requestKey = (string) ($request->get_header('X-Laca-Catalog-Key') ?? $request->get_header('X-Laca-Key') ?? '');
        if ($requestKey === '' || empty($candidateKeys)) {
            return false;
        }

        foreach ($candidateKeys as $storedKey) {
            if (hash_equals($storedKey, $requestKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function scanRelativePaths(string $rootDir): array
    {
        if (function_exists('lacadev_scan_block_relative_paths')) {
            return lacadev_scan_block_relative_paths($rootDir);
        }

        if (!is_dir($rootDir)) {
            return [];
        }

        $paths   = [];
        $entries = @scandir($rootDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = "{$rootDir}/{$entry}";
            if (!is_dir($entryPath)) {
                continue;
            }
            if (is_file("{$entryPath}/block.json")) {
                $paths[] = $entry;
                continue;
            }
            foreach ((@scandir($entryPath) ?: []) as $sub) {
                if ($sub === '.' || $sub === '..') {
                    continue;
                }
                if (is_dir("{$entryPath}/{$sub}") && is_file("{$entryPath}/{$sub}/block.json")) {
                    $paths[] = "{$entry}/{$sub}";
                }
            }
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    private function resolveBlockDirs(): array
    {
        $dirs   = [];
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
        foreach (array_reverse($this->resolveBlockDirs()) as $dir) {
            $candidate     = "{$dir}/{$blockName}";
            $realCandidate = realpath($candidate);
            $realDir       = realpath($dir);
            if (
                $realCandidate !== false
                && $realDir !== false
                && str_starts_with(trailingslashit($realCandidate), trailingslashit($realDir))
                && is_dir($realCandidate)
                && is_file("{$realCandidate}/block.json")
            ) {
                return $realCandidate;
            }
        }

        return null;
    }

    private function encodePreview(string $blockDir): ?string
    {
        $previewPath = "{$blockDir}/preview.png";
        if (!is_file($previewPath)) {
            return null;
        }

        $size = @filesize($previewPath);
        if ($size === false || $size > self::MAX_PREVIEW_BYTES) {
            return null;
        }

        return base64_encode((string) file_get_contents($previewPath));
    }

    /**
     * Đệ quy đọc tất cả file hợp lệ của block (giữ `build/`, loại `*.map`, `node_modules`, `.DS_Store`, `preview.png > 512KB`).
     *
     * @param array<string, string> $files
     * @param array<string, string> $shaManifest
     */
    private function encodeDirectoryFiles(string $baseDir, string $currentDir, array &$files, array &$shaManifest): void
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
            $relativePath = ltrim(str_replace('\\', '/', substr($path, strlen($baseDir))), '/');

            if (is_dir($path)) {
                $this->encodeDirectoryFiles($baseDir, $path, $files, $shaManifest);
            } elseif (is_file($path)) {
                if (str_ends_with($item, '.map')) {
                    continue;
                }
                if ($relativePath === 'preview.png') {
                    $size = @filesize($path);
                    if ($size !== false && $size > self::MAX_PREVIEW_BYTES) {
                        continue;
                    }
                }
                $raw                        = (string) file_get_contents($path);
                $files[$relativePath]       = base64_encode($raw);
                $shaManifest[$relativePath] = hash('sha256', $raw);
            }
        }
    }

    /**
     * @param array<string, string> $files
     */
    private function computeChecksumFromFiles(array $files): string
    {
        $hashes = [];
        foreach ($files as $relativePath => $base64Content) {
            $content               = base64_decode($base64Content, true);
            $hashes[$relativePath] = $content !== false ? md5($content) : '';
        }
        ksort($hashes);

        return md5((string) wp_json_encode($hashes));
    }

    public static function ensureApiKey(): string
    {
        $key = (string) get_option(self::KEY_OPTION, '');
        if ($key === '') {
            $key = wp_generate_uuid4();
            update_option(self::KEY_OPTION, $key);
        }

        return $key;
    }
}
