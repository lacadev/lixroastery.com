<?php

namespace App\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BlockSyncReceiver
 *
 * Nhận block Gutenberg từ Hub qua REST API, kiểm tra chữ ký HMAC, allowlist file,
 * SHA-256 từng file, dependency (`parent`, `functions`, `ajax`), bảo vệ tuỳ biến cục bộ (409),
 * tạo snapshot và hỗ trợ khôi phục (`/sync-block/rollback`).
 */
class BlockSyncReceiver
{
    private const NAMESPACE        = 'lacadev/v1';
    private const ROUTE            = 'sync-block';
    private const KEY_OPTION       = 'laca_sync_key';
    private const LOG_OPTION       = 'laca_block_activity_log';
    private const INST_OPTION      = 'laca_blocks_installed';
    private const LEGACY_OPTION    = 'laca_block_sync_allow_legacy';
    private const MAX_FILE_BYTES   = 5 * 1024 * 1024;  // 5 MB
    private const MAX_TOTAL_BYTES  = 20 * 1024 * 1024; // 20 MB
    private const MAX_SNAPSHOTS    = 3;
    private const ABSENT_MARKER    = '.laca-absent-before-sync';

    private const ALLOWED_EXTENSIONS = [
        'json', 'js', 'css', 'scss',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg',
        'woff', 'woff2', 'txt', 'md',
    ];

    private const ALLOWED_PHP_PATHS = [
        'render.php',
        'icons.php',
        'icon-render.php',
        'post-source-query.php',
        'build/index.asset.php',
    ];

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::ROUTE, [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'receiveBlock'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/rollback', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rollbackBlock'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // REST CALLBACKS
    // =========================================================================

    public function receiveBlock(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Xác thực chữ ký hoặc API Key không hợp lệ.', 'laca'),
            ], 401);
        }

        $blockName = self::sanitizeBlockRelativePath((string) ($request->get_param('block_name') ?? ''));
        $version   = sanitize_text_field((string) ($request->get_param('version') ?? '1.0.0'));
        $files     = $request->get_param('files');
        $manifest  = $request->get_param('manifest');
        $force     = filter_var($request->get_param('force'), FILTER_VALIDATE_BOOLEAN);

        if ($blockName === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Thiếu block_name hợp lệ.', 'laca'),
            ], 400);
        }

        if (empty($files) || !is_array($files)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Danh sách files rỗng hoặc không hợp lệ.', 'laca'),
            ], 400);
        }

        // 1. Kiểm tra allowlist đuôi file, đường dẫn, kích thước & giải mã base64 TRƯỚC KHI ghi.
        $validated = $this->validateAndDecodeFiles($files);
        if (!empty($validated['rejected'])) {
            return new \WP_REST_Response([
                'success'  => false,
                'code'     => 'rejected_files',
                'rejected' => $validated['rejected'],
                'message'  => __('Gói block chứa file không được phép hoặc vượt giới hạn dung lượng.', 'laca'),
            ], 422);
        }

        $decodedFiles = $validated['decoded'];

        // 2. Xác minh SHA-256 từng file nếu có manifest.files.
        if (is_array($manifest) && isset($manifest['files'])) {
            if (!is_array($manifest['files']) || empty($manifest['files'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'code'    => 'invalid_manifest_files',
                    'message' => __('Trường manifest.files không hợp lệ.', 'laca'),
                ], 422);
            }

            $mismatched = [];
            foreach ($decodedFiles as $relPath => $binary) {
                $expectedSha = strtolower(trim((string) ($manifest['files'][$relPath] ?? '')));
                $actualSha   = hash('sha256', $binary);
                if ($expectedSha === '' || !hash_equals($actualSha, $expectedSha)) {
                    $mismatched[] = $relPath;
                }
            }
            foreach (array_keys($manifest['files']) as $manifestRel) {
                if (!array_key_exists((string) $manifestRel, $decodedFiles)) {
                    $mismatched[] = (string) $manifestRel;
                }
            }

            if (!empty($mismatched)) {
                return new \WP_REST_Response([
                    'success'    => false,
                    'code'       => 'manifest_checksum_mismatch',
                    'mismatched' => array_values(array_unique($mismatched)),
                    'message'    => __('Checksum SHA-256 của một hoặc nhiều file không khớp manifest.', 'laca'),
                ], 422);
            }
        }

        // 3. Kiểm tra dependency (`parent`, `functions`, `ajax`) TRƯỚC KHI ghi.
        $requires = [];
        if (is_array($manifest) && isset($manifest['requires']) && is_array($manifest['requires'])) {
            $requires = $manifest['requires'];
        } elseif (is_array($request->get_param('requires'))) {
            $requires = (array) $request->get_param('requires');
        } elseif (isset($decodedFiles['block.json'])) {
            $parsedBlockJson = json_decode($decodedFiles['block.json'], true);
            if (is_array($parsedBlockJson) && isset($parsedBlockJson['laca']['requires']) && is_array($parsedBlockJson['laca']['requires'])) {
                $requires = $parsedBlockJson['laca']['requires'];
            }
        }

        $missingDeps = $this->checkMissingDependencies($requires);
        if (!empty($missingDeps)) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'missing_dependencies',
                'missing' => $missingDeps,
                'message' => __('Site khách chưa đáp ứng đủ phụ thuộc của block.', 'laca'),
            ], 409);
        }

        // 4. Bảo vệ tuỳ biến cục bộ của khách hàng (409 local_modified).
        $childBlockRoot = dirname(get_stylesheet_directory()) . '/block-gutenberg';
        $incomingJson   = isset($decodedFiles['block.json']) ? json_decode($decodedFiles['block.json'], true) : null;
        $incomingWpName = is_array($incomingJson) ? (string) ($incomingJson['name'] ?? '') : '';

        $installedRaw = get_option(self::INST_OPTION, []);
        $installedRaw = is_array($installedRaw) ? $installedRaw : [];
        $record       = $this->normalizeRecord($installedRaw[$blockName] ?? null, $blockName);

        $existingRelPath = $this->findExistingBlockRelPath($childBlockRoot, $blockName, $incomingWpName, $record['path'] ?? null);
        $targetRelPath   = $existingRelPath ?? $blockName;
        $blockDir        = $childBlockRoot . '/' . $targetRelPath;

        if ($record !== null) {
            $recordedChecksum = $record['checksum'];
            $checkDir         = is_dir($blockDir) ? $blockDir : ($childBlockRoot . '/' . $record['path']);
            $diskChecksum     = is_dir($checkDir) ? $this->computeChecksum($checkDir) : '';

            if (($recordedChecksum === '' || $diskChecksum !== $recordedChecksum) && !$force) {
                return new \WP_REST_Response([
                    'success'           => false,
                    'code'              => 'local_modified',
                    'disk_checksum'     => $diskChecksum,
                    'recorded_checksum' => $recordedChecksum,
                    'path'              => $targetRelPath,
                    'message'           => __('Block trên site khách đã được chỉnh sửa cục bộ — cần force=true để ghi đè.', 'laca'),
                ], 409);
            }
        } elseif ($existingRelPath !== null && is_dir($blockDir)) {
            $diskChecksum = $this->computeChecksum($blockDir);
            if (!$force) {
                return new \WP_REST_Response([
                    'success'           => false,
                    'code'              => 'local_modified',
                    'disk_checksum'     => $diskChecksum,
                    'recorded_checksum' => null,
                    'path'              => $existingRelPath,
                    'message'           => __('Block đã tồn tại trên site khách nhưng chưa có bản ghi đồng bộ — từ chối ghi đè tự động.', 'laca'),
                ], 409);
            }
        }

        // 5. Chuyển bản cũ sang snapshot bằng WP_Filesystem::move (không xoá trực tiếp).
        $oldVersion = $record['version'] ?? ($existingRelPath !== null ? 'custom' : null);
        $snapErr    = $this->snapshotExistingBlock($blockName, $blockDir, $oldVersion);
        if (is_wp_error($snapErr)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $snapErr->get_error_message(),
            ], 500);
        }

        // 6. Ghi bộ file mới vào đúng $blockDir.
        try {
            $this->writeDecodedBlockFiles($childBlockRoot, $blockDir, $decodedFiles);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: error message when writing synced block files */
                    __('Lỗi ghi file block: %s', 'laca'),
                    $e->getMessage()
                ),
            ], 500);
        }

        $newChecksum = $this->computeChecksum($blockDir);
        $installedAt = gmdate('c');

        $installedRaw[$blockName] = [
            'version'      => $version,
            'checksum'     => $newChecksum,
            'path'         => $targetRelPath,
            'installed_at' => $installedAt,
        ];
        update_option(self::INST_OPTION, $installedRaw);

        $isUpdate = ($oldVersion !== null);
        $logMsg   = $isUpdate
            ? "🔄 Cập nhật <strong>{$blockName}</strong> {$oldVersion} → {$version}"
            : "✅ Nhận <strong>{$blockName}</strong> ({$version})";
        $this->appendLog($logMsg);

        return new \WP_REST_Response([
            'success'           => true,
            'installed_version' => $version,
            'checksum'          => $newChecksum,
            'path'              => $targetRelPath,
            'message'           => $isUpdate
                ? sprintf(
                    /* translators: 1: block name, 2: previous version, 3: new version */
                    __('Đã cập nhật %1$s từ %2$s lên %3$s.', 'laca'),
                    $blockName,
                    (string) $oldVersion,
                    $version
                )
                : sprintf(
                    /* translators: 1: block name, 2: installed version */
                    __('Đã nhận %1$s v%2$s thành công.', 'laca'),
                    $blockName,
                    $version
                ),
        ], 200);
    }

    public function rollbackBlock(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Xác thực chữ ký hoặc API Key không hợp lệ.', 'laca'),
            ], 401);
        }

        $blockName = self::sanitizeBlockRelativePath((string) ($request->get_param('block_name') ?? ''));
        if ($blockName === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Thiếu block_name hợp lệ.', 'laca'),
            ], 400);
        }

        $snapRoot = $this->getBlockBackupDir($blockName);
        $snaps    = $this->listBlockSnapshots($snapRoot);
        if (empty($snaps)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy bản snapshot nào để khôi phục cho block này.', 'laca'),
            ], 404);
        }

        $latestSnap = $snaps[0];

        $fs = $this->getFilesystem();
        if (!$fs) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không khởi tạo được WP_Filesystem.', 'laca'),
            ], 500);
        }

        $installedRaw   = get_option(self::INST_OPTION, []);
        $installedRaw   = is_array($installedRaw) ? $installedRaw : [];
        $record         = $this->normalizeRecord($installedRaw[$blockName] ?? null, $blockName);
        $targetRelPath  = $record['path'] ?? $blockName;
        $childBlockRoot = dirname(get_stylesheet_directory()) . '/block-gutenberg';
        $blockDir       = $childBlockRoot . '/' . $targetRelPath;

        $holdRoot = $this->getHoldRoot();
        $holdPath = $holdRoot . '/' . str_replace('/', '__', $blockName) . '-rollback-' . time() . '-' . wp_generate_password(4, false, false);

        // Trường hợp snapshot là marker trạng thái "chưa từng cài trước khi sync": dời thư mục ra khu lưu trữ.
        if (is_file($latestSnap['path'] . '/' . self::ABSENT_MARKER)) {
            if ($fs->is_dir($blockDir)) {
                if (!$fs->move($blockDir, $holdPath, true)) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => __('Không thể thu hồi thư mục block về trạng thái ban đầu.', 'laca'),
                    ], 500);
                }
            }
            $fs->delete($latestSnap['path'], true);
            unset($installedRaw[$blockName]);
            update_option(self::INST_OPTION, $installedRaw);
            $this->appendLog("⏪ Gỡ <strong>{$blockName}</strong> về trạng thái trước khi đồng bộ");

            return new \WP_REST_Response([
                'success'           => true,
                'installed_version' => null,
                'checksum'          => null,
                'path'              => $targetRelPath,
                'uninstalled'       => true,
                'message'           => sprintf(
                    /* translators: %s: block name */
                    __('Đã khôi phục %s về trạng thái chưa cài ban đầu.', 'laca'),
                    $blockName
                ),
            ], 200);
        }

        $movedOld = false;
        if ($fs->is_dir($blockDir)) {
            if (!$fs->move($blockDir, $holdPath, true)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Không thể tạm dời thư mục block hiện tại.', 'laca'),
                ], 500);
            }
            $movedOld = true;
        }

        $parentTargetDir = dirname($blockDir);
        if (!is_dir($parentTargetDir)) {
            wp_mkdir_p($parentTargetDir);
        }

        if (!$fs->move($latestSnap['path'], $blockDir, true)) {
            if ($movedOld) {
                $fs->move($holdPath, $blockDir, true);
            }
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không thể khôi phục thư mục block từ snapshot.', 'laca'),
            ], 500);
        }

        if ($movedOld && $fs->is_dir($holdPath)) {
            $fs->delete($holdPath, true);
        }

        $restoredVersion = $latestSnap['version'];
        if (is_file($blockDir . '/block.json')) {
            $bJson = json_decode((string) file_get_contents($blockDir . '/block.json'), true);
            if (is_array($bJson) && !empty($bJson['version'])) {
                $restoredVersion = (string) $bJson['version'];
            }
        }

        $newChecksum              = $this->computeChecksum($blockDir);
        $installedRaw[$blockName] = [
            'version'      => $restoredVersion,
            'checksum'     => $newChecksum,
            'path'         => $targetRelPath,
            'installed_at' => gmdate('c'),
        ];
        update_option(self::INST_OPTION, $installedRaw);
        $this->appendLog("⏪ Rollback <strong>{$blockName}</strong> về {$restoredVersion}");

        return new \WP_REST_Response([
            'success'           => true,
            'installed_version' => $restoredVersion,
            'checksum'          => $newChecksum,
            'path'              => $targetRelPath,
            'message'           => sprintf(
                /* translators: 1: block name, 2: restored version */
                __('Đã khôi phục %1$s về phiên bản %2$s.', 'laca'),
                $blockName,
                $restoredVersion
            ),
        ], 200);
    }

    public function getStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Xác thực chữ ký hoặc API Key không hợp lệ.', 'laca'),
            ], 401);
        }

        $installedRaw = get_option(self::INST_OPTION, []);
        $installedRaw = is_array($installedRaw) ? $installedRaw : [];

        // Trả `installed` dạng map block => version để tương thích ngược với Hub UI, kèm `records` chi tiết.
        $versionMap = [];
        foreach ($installedRaw as $key => $val) {
            $versionMap[(string) $key] = is_array($val) ? (string) ($val['version'] ?? '') : (string) $val;
        }

        $response = [
            'success'   => true,
            'installed' => $versionMap,
            'records'   => $installedRaw,
        ];

        $blockName = self::sanitizeBlockRelativePath((string) ($request->get_param('block') ?? ''));
        if ($blockName !== '') {
            $childBlockRoot = dirname(get_stylesheet_directory()) . '/block-gutenberg';
            $record         = $this->normalizeRecord($installedRaw[$blockName] ?? null, $blockName);
            $existingRel    = $this->findExistingBlockRelPath($childBlockRoot, $blockName, '', $record['path'] ?? null);
            $targetRel      = $existingRel ?? $blockName;
            $blockDir       = $childBlockRoot . '/' . $targetRel;

            $diskChecksum         = is_dir($blockDir) ? $this->computeChecksum($blockDir) : null;
            $response['checksum'] = $diskChecksum;
            $response['path']     = is_dir($blockDir) ? $targetRel : null;
            $response['local_modified'] = ($diskChecksum !== null) && (
                $record === null || $record['checksum'] === '' || $diskChecksum !== $record['checksum']
            );
        }

        return new \WP_REST_Response($response, 200);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function authenticate(\WP_REST_Request $request): bool
    {
        $catalogKey  = LacaDevTrackerClient::getCatalogKey();
        $allowLegacy = get_option(self::LEGACY_OPTION, 'no') === 'yes';

        if (!empty($catalogKey)) {
            $sigHeader = $request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature');
            if (!empty($sigHeader)) {
                $verify = \App\Security\RequestSigner::verify($request, $catalogKey);
                return !empty($verify['valid']);
            }
        }

        if (!$allowLegacy) {
            return false;
        }

        $storedKey  = (string) (get_option(self::KEY_OPTION, '') ?: $catalogKey);
        $requestKey = (string) ($request->get_header('X-Laca-Key') ?? '');

        if ($storedKey === '' || $requestKey === '') {
            return false;
        }

        return hash_equals($storedKey, $requestKey);
    }

    /**
     * Kiểm tra allowlist đuôi file, đường dẫn hợp lệ, kích thước mỗi file <= 5MB và tổng <= 20MB.
     *
     * @param array<mixed, mixed> $files
     * @return array{decoded: array<string, string>, rejected: array<int, array{file: string, reason: string}>}
     */
    private function validateAndDecodeFiles(array $files): array
    {
        $decoded    = [];
        $rejected   = [];
        $totalBytes = 0;

        foreach ($files as $rawRelPath => $base64Content) {
            $relPath = str_replace('\\', '/', trim((string) $rawRelPath));

            if (
                $relPath === ''
                || str_starts_with($relPath, '/')
                || str_ends_with($relPath, '/')
                || str_contains($relPath, '..')
                || str_contains($relPath, '//')
                || str_contains($relPath, "\0")
                || !preg_match('#^[a-zA-Z0-9._/-]+$#', $relPath)
            ) {
                $rejected[] = ['file' => (string) $rawRelPath, 'reason' => 'invalid_path'];
                continue;
            }

            $ext = strtolower((string) pathinfo($relPath, PATHINFO_EXTENSION));
            if ($ext === 'php') {
                if (!in_array($relPath, self::ALLOWED_PHP_PATHS, true)) {
                    $rejected[] = ['file' => $relPath, 'reason' => 'disallowed_php_location'];
                    continue;
                }
            } else {
                if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    $rejected[] = ['file' => $relPath, 'reason' => 'disallowed_extension'];
                    continue;
                }
                // Chặn double-extension kiểu shell.php.jpg
                if (preg_match('/\.(php\d*|phtml|phar)\./i', basename($relPath))) {
                    $rejected[] = ['file' => $relPath, 'reason' => 'suspicious_double_extension'];
                    continue;
                }
            }

            if (!is_string($base64Content)) {
                $rejected[] = ['file' => $relPath, 'reason' => 'invalid_base64'];
                continue;
            }

            $binary = base64_decode($base64Content, true);
            if ($binary === false) {
                $rejected[] = ['file' => $relPath, 'reason' => 'invalid_base64'];
                continue;
            }

            $size = strlen($binary);
            if ($size > self::MAX_FILE_BYTES) {
                $rejected[] = ['file' => $relPath, 'reason' => 'file_too_large'];
                continue;
            }

            $totalBytes += $size;
            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                $rejected[] = ['file' => $relPath, 'reason' => 'payload_too_large'];
                continue;
            }

            $decoded[$relPath] = $binary;
        }

        return [
            'decoded'  => $decoded,
            'rejected' => $rejected,
        ];
    }

    /**
     * Kiểm tra các yêu cầu phụ thuộc `parent`, `functions`, `ajax`.
     *
     * @param array<string, mixed> $requires
     * @return string[]
     */
    private function checkMissingDependencies(array $requires): array
    {
        $missing = [];

        if (!empty($requires['parent']) && is_string($requires['parent'])) {
            $constraint = trim($requires['parent']);
            $parentVer  = (string) (wp_get_theme('lacadev-client/theme')->get('Version') ?: '');
            if ($parentVer === '' || !self::satisfiesVersionConstraint($parentVer, $constraint)) {
                $missing[] = 'parent:' . $constraint . ' (current:' . ($parentVer ?: 'none') . ')';
            }
        }

        if (!empty($requires['functions']) && is_array($requires['functions'])) {
            foreach ($requires['functions'] as $fn) {
                $fnName = trim((string) $fn);
                if ($fnName !== '' && !function_exists($fnName)) {
                    $missing[] = 'function:' . $fnName;
                }
            }
        }

        if (!empty($requires['ajax']) && is_array($requires['ajax'])) {
            foreach ($requires['ajax'] as $action) {
                $actName = trim((string) $action);
                if ($actName === '') {
                    continue;
                }
                if (!has_action('wp_ajax_' . $actName) && !has_action('wp_ajax_nopriv_' . $actName)) {
                    $missing[] = 'ajax:' . $actName;
                }
            }
        }

        return $missing;
    }

    public static function satisfiesVersionConstraint(string $installed, string $constraint): bool
    {
        $parts = preg_split('/\s*,\s*/', trim($constraint)) ?: [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(>=|<=|>|<|==|=)\s*(.+)$/', $part, $m)) {
                $op  = $m[1] === '=' ? '==' : $m[1];
                $ver = trim($m[2]);
            } else {
                $op  = '>=';
                $ver = trim($part);
            }
            if (!version_compare($installed, $ver, $op)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Chuẩn hoá bản ghi trong `laca_blocks_installed` (tương thích cả chuỗi cũ lẫn object mới).
     *
     * @return array{version: string, checksum: string, path: string, installed_at: string}|null
     */
    private function normalizeRecord(mixed $raw, string $defaultPath): ?array
    {
        if ($raw === null || $raw === false || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            return [
                'version'      => $raw,
                'checksum'     => '',
                'path'         => $defaultPath,
                'installed_at' => '',
            ];
        }

        if (is_array($raw)) {
            return [
                'version'      => (string) ($raw['version'] ?? ''),
                'checksum'     => (string) ($raw['checksum'] ?? ''),
                'path'         => (string) ($raw['path'] ?? $defaultPath),
                'installed_at' => (string) ($raw['installed_at'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Tìm đường dẫn tương đối của block đang tồn tại trên đĩa của child theme (kể cả khi nằm trong bucket theo site).
     */
    private function findExistingBlockRelPath(
        string $childBlockRoot,
        string $blockName,
        string $incomingWpName = '',
        ?string $recordedPath = null
    ): ?string {
        if ($recordedPath !== null && $recordedPath !== '' && is_dir($childBlockRoot . '/' . $recordedPath)) {
            return $recordedPath;
        }

        if (is_dir($childBlockRoot . '/' . $blockName)) {
            return $blockName;
        }

        if (!is_dir($childBlockRoot)) {
            return null;
        }

        $paths = function_exists('lacadev_scan_block_relative_paths')
            ? lacadev_scan_block_relative_paths($childBlockRoot)
            : $this->fallbackScanRelativePaths($childBlockRoot);

        $targetLeaf = basename($blockName);
        foreach ($paths as $rel) {
            if ($rel === $blockName || basename($rel) === $targetLeaf) {
                return $rel;
            }

            if ($incomingWpName !== '' && is_file("{$childBlockRoot}/{$rel}/block.json")) {
                $json = json_decode((string) file_get_contents("{$childBlockRoot}/{$rel}/block.json"), true);
                if (is_array($json) && ($json['name'] ?? '') === $incomingWpName) {
                    return $rel;
                }
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function fallbackScanRelativePaths(string $rootDir): array
    {
        $paths   = [];
        $entries = @scandir($rootDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $p = "{$rootDir}/{$entry}";
            if (!is_dir($p)) {
                continue;
            }
            if (is_file("{$p}/block.json")) {
                $paths[] = $entry;
                continue;
            }
            foreach ((@scandir($p) ?: []) as $sub) {
                if ($sub === '.' || $sub === '..') {
                    continue;
                }
                if (is_dir("{$p}/{$sub}") && is_file("{$p}/{$sub}/block.json")) {
                    $paths[] = "{$entry}/{$sub}";
                }
            }
        }

        return $paths;
    }

    private function getFilesystem(): ?\WP_Filesystem_Base
    {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        return ($wp_filesystem instanceof \WP_Filesystem_Base) ? $wp_filesystem : null;
    }

    private function getBlocksBackupRoot(): string
    {
        $suffix  = substr(wp_hash('laca-block-snap'), 0, 12);
        $baseDir = WP_CONTENT_DIR . '/laca-backups';
        $rootDir = $baseDir . '/blocks-' . $suffix;

        foreach ([$baseDir, $rootDir] as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            if (!file_exists($dir . '/index.php')) {
                @file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
            }
            if (!file_exists($dir . '/.htaccess')) {
                @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            }
        }

        return $rootDir;
    }

    private function getHoldRoot(): string
    {
        $hold = $this->getBlocksBackupRoot() . '/_hold';
        if (!is_dir($hold)) {
            wp_mkdir_p($hold);
        }

        return $hold;
    }

    private function getBlockBackupDir(string $blockName): string
    {
        $slug = str_replace('/', '__', self::sanitizeBlockRelativePath($blockName));
        $dir  = $this->getBlocksBackupRoot() . '/' . $slug;
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $dir;
    }

    /**
     * Di chuyển thư mục block hiện tại vào khu vực snapshot (tối đa 3 bản), hoặc ghi marker nếu cài mới lần đầu.
     */
    private function snapshotExistingBlock(string $blockName, string $blockDir, ?string $oldVersion): true|\WP_Error
    {
        $fs = $this->getFilesystem();
        if (!$fs) {
            return new \WP_Error('laca_fs_unavailable', __('Không khởi tạo được WP_Filesystem.', 'laca'));
        }

        $snapParent = $this->getBlockBackupDir($blockName);
        $stamp      = gmdate('Ymd-His');
        $usec       = sprintf('%06d', (int) ((microtime(true) - floor(microtime(true))) * 1000000));

        if (is_dir($blockDir)) {
            $safeOld = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) ($oldVersion ?: 'prev')) ?: 'prev';
            $dest    = "{$snapParent}/{$stamp}-{$usec}__{$safeOld}";
            $idx     = 1;
            while (file_exists($dest)) {
                $dest = "{$snapParent}/{$stamp}-{$usec}-{$idx}__{$safeOld}";
                $idx++;
            }

            if (!$fs->move($blockDir, $dest, true)) {
                return new \WP_Error(
                    'laca_block_snapshot_failed',
                    __('Không thể tạo snapshot cho thư mục block cũ trước khi cập nhật.', 'laca')
                );
            }
            @touch($dest);
        } else {
            // Đánh dấu block chưa từng tồn tại trước lần đồng bộ đầu tiên để có thể rollback sạch không cần rm.
            $existingSnaps = $this->listBlockSnapshots($snapParent);
            if (empty($existingSnaps)) {
                $dest = "{$snapParent}/{$stamp}-{$usec}__absent";
                wp_mkdir_p($dest);
                @file_put_contents($dest . '/' . self::ABSENT_MARKER, gmdate('c') . "\n");
                @touch($dest);
            }
        }

        $this->pruneBlockSnapshots($snapParent, $fs);

        return true;
    }

    /**
     * @return array<int, array{name: string, path: string, version: string, mtime: int}>
     */
    private function listBlockSnapshots(string $snapParent): array
    {
        if (!is_dir($snapParent)) {
            return [];
        }

        $entries = @scandir($snapParent) ?: [];
        $items   = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $full = "{$snapParent}/{$entry}";
            if (!is_dir($full)) {
                continue;
            }

            $ver = '1.0.0';
            if (preg_match('/^\d{8}-\d{6}-\d{6}(?:-\d+)?__(.+)$/', $entry, $m)) {
                $ver = $m[1];
            } elseif (preg_match('/^(.+)-\d{8}-\d{6}(?:-\d+)?$/', $entry, $m)) {
                $ver = $m[1];
            }

            $items[] = [
                'name'    => $entry,
                'path'    => $full,
                'version' => $ver,
                'mtime'   => (int) (@filemtime($full) ?: 0),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $aStamped = (bool) preg_match('/^\d{8}-\d{6}-\d{6}/', $a['name']);
            $bStamped = (bool) preg_match('/^\d{8}-\d{6}-\d{6}/', $b['name']);
            if ($aStamped && $bStamped) {
                return strcmp($b['name'], $a['name']);
            }
            if ($b['mtime'] !== $a['mtime']) {
                return $b['mtime'] <=> $a['mtime'];
            }
            return strcmp($b['name'], $a['name']);
        });

        return $items;
    }

    private function pruneBlockSnapshots(string $snapParent, \WP_Filesystem_Base $fs): void
    {
        $items = $this->listBlockSnapshots($snapParent);
        if (count($items) <= self::MAX_SNAPSHOTS) {
            return;
        }

        $safeBase = trailingslashit(realpath($snapParent) ?: $snapParent);
        foreach (array_slice($items, self::MAX_SNAPSHOTS) as $old) {
            $realOld = realpath($old['path']);
            if ($realOld !== false && str_starts_with(trailingslashit($realOld), $safeBase)) {
                $fs->delete($realOld, true);
            }
        }
    }

    /**
     * Làm sạch "block relative path" — tên block phẳng hoặc "{bucket}/{block}".
     */
    private static function sanitizeBlockRelativePath(string $raw): string
    {
        $raw      = strtolower(trim($raw));
        $segments = array_filter(explode('/', $raw), static fn($s) => $s !== '');
        $segments = array_map(static fn($s) => preg_replace('/[^a-z0-9_-]/', '', $s), $segments);
        $segments = array_filter($segments, static fn($s) => $s !== '' && $s !== '.' && $s !== '..');
        $segments = array_slice($segments, 0, 2);

        return implode('/', $segments);
    }

    /**
     * Ghi danh sách file đã kiểm duyệt vào thư mục đích (không xoá đệ quy, kiểm tra chặt prefix `trailingslashit`).
     *
     * @param array<string, string> $decodedFiles
     * @throws \RuntimeException
     */
    private function writeDecodedBlockFiles(string $childBlockRoot, string $blockDir, array $decodedFiles): void
    {
        if (!is_dir($childBlockRoot) && !wp_mkdir_p($childBlockRoot)) {
            throw new \RuntimeException('Unable to create block-gutenberg root directory.');
        }

        $realBase = realpath($childBlockRoot);
        if ($realBase === false) {
            throw new \RuntimeException('Unable to resolve block-gutenberg root path.');
        }
        $safeBasePrefix = trailingslashit($realBase);

        if (!is_dir($blockDir) && !wp_mkdir_p($blockDir)) {
            throw new \RuntimeException("Unable to create block directory: {$blockDir}");
        }

        $realBlockDir = realpath($blockDir);
        if ($realBlockDir === false || !str_starts_with(trailingslashit($realBlockDir), $safeBasePrefix)) {
            throw new \RuntimeException('Block directory lies outside child block-gutenberg root.');
        }
        $safeBlockPrefix = trailingslashit($realBlockDir);

        foreach ($decodedFiles as $cleanPath => $binary) {
            $targetPath = $realBlockDir . '/' . $cleanPath;
            $targetDir  = dirname($targetPath);

            if (!is_dir($targetDir) && !wp_mkdir_p($targetDir)) {
                throw new \RuntimeException("Unable to create directory: {$targetDir}");
            }

            $realTargetDir = realpath($targetDir);
            if (
                $realTargetDir === false
                || !str_starts_with(trailingslashit($realTargetDir), $safeBasePrefix)
                || !str_starts_with(trailingslashit($realTargetDir), $safeBlockPrefix)
            ) {
                throw new \RuntimeException('Target file directory escaped block boundary.');
            }

            if (file_put_contents($targetPath, $binary) === false) {
                throw new \RuntimeException("Unable to write file: {$cleanPath}");
            }
        }
    }

    /**
     * Tính checksum MD5 chuẩn hoá của thư mục block trên đĩa.
     */
    public function computeChecksum(string $blockDir): string
    {
        if (!is_dir($blockDir)) {
            return '';
        }

        $realDir  = realpath($blockDir) ?: $blockDir;
        $hashes   = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $fileName = $file->getFilename();
            if ($fileName === '.DS_Store' || str_ends_with($fileName, '.map')) {
                continue;
            }
            $relPath = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($realDir))), '/');
            if ($relPath === 'node_modules' || str_starts_with($relPath, 'node_modules/') || str_contains($relPath, '/node_modules/')) {
                continue;
            }
            $hash = md5_file($file->getPathname());
            if ($hash !== false) {
                $hashes[$relPath] = $hash;
            }
        }

        ksort($hashes);

        return md5((string) wp_json_encode($hashes));
    }

    private function appendLog(string $message): void
    {
        $log = get_option(self::LOG_OPTION, []);
        $log = is_array($log) ? $log : [];

        array_unshift($log, [
            'time'    => current_time('mysql'),
            'message' => $message,
        ]);

        $log = array_slice($log, 0, 50);
        update_option(self::LOG_OPTION, $log, false);
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
