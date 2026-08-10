<?php

namespace App\PostTypes\Concerns;

/**
 * Trait BlockSyncSender
 *
 * Xử lý toàn bộ logic Block Sync từ lacadev.com đến client sites.
 * Sử dụng trong: App\PostTypes\Project
 */
trait BlockSyncSender
{
    // =========================================================================
    // HOOKS REGISTRATION
    // =========================================================================

    public function registerBlockSyncHooks(): void
    {
        add_action('add_meta_boxes', [$this, 'registerBlockSyncMetaBox']);
        add_action('wp_ajax_laca_push_blocks', [$this, 'ajaxPushBlocks']);
        add_action('wp_ajax_laca_fetch_client_blocks', [$this, 'ajaxFetchClientBlocks']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueBlockSyncAssets']);
    }

    public function enqueueBlockSyncAssets(string $hook): void
    {
        global $post;
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        if (!$post || $post->post_type !== 'project') {
            return;
        }

        // SweetAlert2
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
            [],
            '11',
            true
        );
        wp_enqueue_style(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
            [],
            '11'
        );

        // Inline script cho Block Sync UI
        wp_add_inline_script('sweetalert2', $this->getBlockSyncInlineScript(), 'after');
    }

    // =========================================================================
    // META BOX
    // =========================================================================

    public function registerBlockSyncMetaBox(): void
    {
        add_meta_box(
            'laca_block_sync_manager',
            'Block Sync Manager',
            [$this, 'renderBlockSyncMetaBox'],
            'project',
            'normal',
            'high'
        );
    }

    public function renderBlockSyncMetaBox(int|\WP_Post $post): void
    {
        $postId           = is_int($post) ? $post : $post->ID;
        $installedMeta    = get_post_meta($postId, '_block_sync_versions', true) ?: [];
        $availableBlocks  = $this->getAvailableBlocks();
        $nonce            = wp_create_nonce('laca_block_sync');
        $blockDir         = $this->resolveBlockDir();
        ?>
        <div id="laca-block-sync-wrap" style="margin:-6px -12px -12px">

            <!-- Header toolbar -->
            <div style="
                display:flex; align-items:center; justify-content:space-between;
                padding:12px 16px; background:#f8f9fa; border-bottom:1px solid #e2e4e7;
            ">
                <div style="display:flex;gap:8px;align-items:center">
                    <label style="display:flex;gap:6px;align-items:center;font-size:13px;cursor:pointer">
                        <input type="checkbox" id="laca-select-all-blocks"> Chọn tất cả
                    </label>
                    <button
                        type="button"
                        id="laca-push-blocks-btn"
                        class="button button-primary"
                        style="display:flex;gap:6px;align-items:center"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                    >
                        <span class="dashicons dashicons-upload" style="margin-top:3px"></span>
                        Push Selected
                    </button>
                    <button
                        type="button"
                        id="laca-sync-outdated-btn"
                        class="button"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        title="Push tất cả blocks đang cũ hơn lacadev"
                    >
                        🔄 Sync Outdated
                    </button>
                    <button
                        type="button"
                        id="laca-refresh-status-btn"
                        class="button"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        title="Tải lại trạng thái từ client"
                    >
                        ↻ Refresh
                    </button>
                </div>
                <span id="laca-sync-status-text" style="font-size:12px;color:#888"></span>
            </div>

            <!-- Block table -->
            <table class="widefat fixed striped" style="border:none;border-radius:0">
                <thead>
                    <tr style="background:#f0f0f1">
                        <th style="width:36px; padding:10px 12px">☐</th>
                        <th style="padding:10px 12px">Block Name</th>
                        <th style="padding:10px 12px; width:120px">Phiên bản</th>
                        <th style="padding:10px 12px; width:140px">Client hiện có</th>
                        <th style="padding:10px 12px; width:120px">Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="laca-blocks-table-body">
                    <?php foreach ($availableBlocks as $block): ?>
                    <?php
                        $name          = $block['name'];
                        $localVersion  = $block['version'];
                        $clientVersion = $installedMeta[$name] ?? null;
                        $badge         = $this->getVersionBadge($localVersion, $clientVersion);
                    ?>
                    <tr data-block="<?php echo esc_attr($name); ?>"
                        data-local-ver="<?php echo esc_attr($localVersion); ?>"
                        data-client-ver="<?php echo esc_attr($clientVersion ?? ''); ?>">
                        <td style="padding:10px 12px">
                            <input type="checkbox"
                                class="laca-block-checkbox"
                                value="<?php echo esc_attr($name); ?>">
                        </td>
                        <td style="padding:10px 12px">
                            <strong><?php echo esc_html($block['title']); ?></strong>
                            <br>
                            <small style="color:#888;font-family:monospace"><?php echo esc_html($name); ?></small>
                        </td>
                        <td style="padding:10px 12px">
                            <code><?php echo esc_html($localVersion); ?></code>
                        </td>
                        <td class="laca-client-ver" style="padding:10px 12px">
                            <?php if ($clientVersion): ?>
                                <code><?php echo esc_html($clientVersion); ?></code>
                            <?php else: ?>
                                <span style="color:#999">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="laca-status-badge" style="padding:10px 12px">
                            <?php echo $badge; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($availableBlocks)): ?>
                    <tr>
                        <td colspan="5" style="padding:16px;text-align:center;color:#888">
                            Không tìm thấy blocks trong <code><?php echo esc_html($blockDir); ?></code>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // AJAX: Push blocks to client
    // =========================================================================

    public function ajaxPushBlocks(): void
    {
        check_ajax_referer('laca_block_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $postId     = absint($_POST['post_id'] ?? 0);
        $blockNames = array_map('sanitize_key', (array) ($_POST['blocks'] ?? []));

        if (!$postId || get_post_type($postId) !== 'project') {
            wp_send_json_error(['message' => 'Project không hợp lệ'], 400);
        }

        if (empty($blockNames)) {
            wp_send_json_error(['message' => 'Chưa chọn block nào'], 400);
        }

        $apiKey      = carbon_get_post_meta($postId, 'sync_api_key');
        $endpointUrl = carbon_get_post_meta($postId, 'sync_endpoint_url');

        if (empty($apiKey) || empty($endpointUrl)) {
            wp_send_json_error(['message' => 'Chưa cấu hình API Key hoặc Endpoint URL'], 400);
        }

        $results       = [];
        $installedMeta = get_post_meta($postId, '_block_sync_versions', true) ?: [];

        foreach ($blockNames as $blockName) {
            $result = $this->pushSingleBlock($blockName, $apiKey, $endpointUrl);

            if ($result['success']) {
                $installedMeta[$blockName] = $result['version'];
            }

            $results[$blockName] = $result;
        }

        // Lưu versions đã sync thành công
        update_post_meta($postId, '_block_sync_versions', $installedMeta);

        wp_send_json_success(['results' => $results]);
    }

    // =========================================================================
    // AJAX: Fetch installed versions from client
    // =========================================================================

    public function ajaxFetchClientBlocks(): void
    {
        check_ajax_referer('laca_block_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $postId = absint($_POST['post_id'] ?? 0);

        if (!$postId || get_post_type($postId) !== 'project') {
            wp_send_json_error(['message' => 'Project không hợp lệ'], 400);
        }

        $apiKey      = carbon_get_post_meta($postId, 'sync_api_key');
        $endpointUrl = carbon_get_post_meta($postId, 'sync_endpoint_url');

        if (empty($apiKey) || empty($endpointUrl)) {
            wp_send_json_error(['message' => 'Chưa cấu hình API Key hoặc Endpoint URL'], 400);
        }

        // Gọi GET /status endpoint
        // endpointUrl là .../sync-block → status là .../sync-block/status
        $statusUrl = rtrim($endpointUrl, '/') . '/status';
        $response  = wp_remote_get($statusUrl, [
            'headers' => [
                'X-Laca-Key' => $apiKey,
                'Accept'     => 'application/json',
            ],
            'timeout' => 15,
            'sslverify' => false, // Cho phép self-signed cert môi trường staging
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Không kết nối được client: ' . $response->get_error_message()]);
        }

        $code    = wp_remote_retrieve_response_code($response);
        // Strip UTF-8 BOM (\xEF\xBB\xBF) + whitespace — BOM là lý do phổ biến nhất
        // khiến json_decode báo "Syntax error" dù body nhìn có vẻ đúng
        $rawBody = wp_remote_retrieve_body($response);
        $rawBody = ltrim($rawBody, "\xEF\xBB\xBF"); // Strip UTF-8 BOM
        $rawBody = trim($rawBody);
        $body    = json_decode($rawBody, true);

        if ($code === 401) {
            wp_send_json_error(['message' => 'API Key không hợp lệ — kiểm tra lại API Key trong tab 🧩 Block Sync']);
        }

        if ($code !== 200) {
            wp_send_json_error(['message' => "Server client trả về HTTP {$code}. Kiểm tra URL endpoint."]);
        }

        // Dùng array_key_exists thay isset để xử lý đúng khi installed = [] (mảng rỗng)
        if (!is_array($body) || !array_key_exists('installed', $body)) {
            $jsonErr = json_last_error_msg();
            $preview = mb_substr(strip_tags($rawBody), 0, 200);
            wp_send_json_error(['message' => "Response không hợp lệ. JSON error: {$jsonErr}. URL gọi: {$statusUrl}. Body: {$preview}"]);
        }

        // Cập nhật meta
        update_post_meta($postId, '_block_sync_versions', $body['installed']);

        wp_send_json_success(['installed' => $body['installed']]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function pushSingleBlock(string $blockName, string $apiKey, string $endpointUrl): array
    {
        $blockDir = $this->resolveBlockDir() . "/{$blockName}";

        if (!is_dir($blockDir)) {
            return [
                'success' => false,
                'message' => "Thư mục block không tồn tại: {$blockName}",
                'version' => null,
            ];
        }

        // Đọc version từ block.json
        $blockJsonPath = "{$blockDir}/block.json";
        $version       = '1.0.0';
        if (file_exists($blockJsonPath)) {
            $blockJson = json_decode(file_get_contents($blockJsonPath), true);
            $version   = $blockJson['version'] ?? '1.0.0';
        }

        // Encode tất cả files
        $files = [];
        $this->encodeDirectoryFiles($blockDir, $blockDir, $files);

        if (empty($files)) {
            return [
                'success' => false,
                'message' => "Không tìm thấy files trong block: {$blockName}",
                'version' => null,
            ];
        }

        // POST đến client endpoint
        $response = wp_remote_post($endpointUrl, [
            'headers' => [
                'X-Laca-Key'   => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => json_encode([
                'block_name' => $blockName,
                'version'    => $version,
                'files'      => $files,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Lỗi kết nối: ' . $response->get_error_message(),
                'version' => null,
            ];
        }

        $code    = wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);

        // Strip UTF-8 BOM (\xEF\xBB\xBF) và whitespace trước/sau JSON.
        // BOM vô hình nhưng làm json_decode() trả về null.
        $cleanBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody);
        $body      = json_decode(trim($cleanBody), true);


        if ($code === 401) {
            return [
                'success' => false,
                'message' => 'API Key không hợp lệ (401)',
                'version' => null,
            ];
        }

        if ($code !== 200 || empty($body['success'])) {
            $msg = $body['message'] ?? ('HTTP ' . $code . ' | body: ' . substr($rawBody, 0, 120));
            return [
                'success' => false,
                'message' => "Client từ chối: {$msg}",
                'version' => null,
            ];
        }

        return [
            'success' => true,
            'message' => $body['message'] ?? 'Thành công',
            'version' => $version,
        ];
    }

    /**
     * Đệ quy đọc tất cả files trong thư mục, encode base64.
     * $files sẽ được populate dạng ['relative/path' => 'base64_content'].
     */
    private function encodeDirectoryFiles(string $baseDir, string $currentDir, array &$files): void
    {
        $items = scandir($currentDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Bỏ qua các file dev không cần thiết
            if (in_array($item, ['node_modules', '.git', '.DS_Store'], true)) {
                continue;
            }

            $path         = "{$currentDir}/{$item}";
            $relativePath = ltrim(str_replace($baseDir, '', $path), '/');

            if (is_dir($path)) {
                $this->encodeDirectoryFiles($baseDir, $path, $files);
            } elseif (is_file($path)) {
                // Bỏ map files (debug only, to nặng)
                if (str_ends_with($item, '.map')) {
                    continue;
                }
                $files[$relativePath] = base64_encode(file_get_contents($path));
            }
        }
    }

    /**
     * Lấy danh sách tất cả blocks có block.json trong block-gutenberg/.
     */
    /**
     * Resolve đường dẫn filesystem đến thư mục block-gutenberg.
     *
     * get_template_directory() đôi khi trỏ tới subfolder '/theme' trên một số hosting.
     * Fallback: leo lên dirname() nếu không tìm thấy block-gutenberg ngay bên dưới.
     */
    private function resolveBlockDir(): string
    {
        // Ưu tiên 1: block-gutenberg nằm ngay trong template directory
        $candidate = get_template_directory() . '/block-gutenberg';
        if (is_dir($candidate)) {
            return $candidate;
        }

        // Ưu tiên 2: template directory là subfolder (ví dụ: /lacadev/theme),
        // thực tế block-gutenberg nằm cạnh nó tại /lacadev/block-gutenberg
        $parentCandidate = dirname(get_template_directory()) . '/block-gutenberg';
        if (is_dir($parentCandidate)) {
            return $parentCandidate;
        }

        // Fallback cuối: trả về candidate gốc (metabox sẽ hiển thị message debug)
        return $candidate;
    }

    private function getAvailableBlocks(): array
    {
        $blocks   = [];
        $blockDir = $this->resolveBlockDir();

        $files = glob("{$blockDir}/*/block.json");
        if (empty($files)) {
            return $blocks;
        }

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $blocks[] = [
                'name'    => basename(dirname($file)),
                'title'   => $data['title'] ?? basename(dirname($file)),
                'version' => $data['version'] ?? '1.0.0',
            ];
        }

        usort($blocks, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $blocks;
    }

    private function getVersionBadge(string $localVersion, ?string $clientVersion): string
    {
        if ($clientVersion === null) {
            return '<span style="
                background:#6c757d22; color:#6c757d;
                padding:2px 8px; border-radius:12px;
                font-size:11px; font-weight:600;
            ">🆕 Chưa có</span>';
        }

        if (version_compare($localVersion, $clientVersion, '>')) {
            return '<span style="
                background:#fd7e1422; color:#fd7e14;
                padding:2px 8px; border-radius:12px;
                font-size:11px; font-weight:600;
            ">⚠️ Cũ hơn</span>';
        }

        return '<span style="
            background:#19875422; color:#198754;
            padding:2px 8px; border-radius:12px;
            font-size:11px; font-weight:600;
        ">✅ Đồng bộ</span>';
    }

    // =========================================================================
    // INLINE JS
    // =========================================================================

    private function getBlockSyncInlineScript(): string
    {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {

    // Select All checkbox
    const selectAll = document.getElementById('laca-select-all-blocks');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.laca-block-checkbox')
                .forEach(cb => cb.checked = this.checked);
        });
    }

    // --- Helper: collect selected blocks ---
    function getSelectedBlocks() {
        return [...document.querySelectorAll('.laca-block-checkbox:checked')]
            .map(cb => cb.value);
    }

    // --- Helper: collect outdated blocks ---
    function getOutdatedBlocks() {
        return [...document.querySelectorAll('#laca-blocks-table-body tr')]
            .filter(tr => {
                const local  = tr.dataset.localVer;
                const client = tr.dataset.clientVer;
                if (!client) return true; // Chưa có = cần push
                return local !== client;  // Khác version = cần update
            })
            .map(tr => tr.dataset.block);
    }

    // --- Push Flow ---
    async function runPush(blocks) {
        if (!blocks.length) {
            Swal.fire({ icon: 'warning', title: 'Chưa chọn block nào', timer: 2000, showConfirmButton: false });
            return;
        }

        const postId = document.getElementById('laca-push-blocks-btn').dataset.postId;
        const nonce  = document.getElementById('laca-push-blocks-btn').dataset.nonce;

        // Hiện progress
        Swal.fire({
            title: '📦 Đang đẩy blocks...',
            html:  `<p id="swal-progress-text">Chuẩn bị...</p>
                    <ul id="swal-results" style="text-align:left;list-style:none;padding:0;max-height:200px;overflow-y:auto"></ul>`,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });

        const fd = new FormData();
        fd.append('action',  'laca_push_blocks');
        fd.append('nonce',   nonce);
        fd.append('post_id', postId);
        blocks.forEach(b => fd.append('blocks[]', b));

        document.getElementById('swal-progress-text').textContent = `Đang push ${blocks.length} block(s)...`;

        try {
            const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: data.data?.message || 'Có lỗi xảy ra' });
                return;
            }

            const results = data.data.results;
            const list    = document.getElementById('swal-results');
            let successCount = 0, failCount = 0;

            for (const [blockName, result] of Object.entries(results)) {
                const li = document.createElement('li');
                li.style.cssText = 'padding:4px 0; border-bottom:1px solid #f0f0f0';
                if (result.success) {
                    successCount++;
                    li.innerHTML = `✅ <strong>${blockName}</strong> → ${result.version}`;
                    // Cập nhật bảng
                    const row = document.querySelector(`tr[data-block="${blockName}"]`);
                    if (row) {
                        row.dataset.clientVer = result.version;
                        row.querySelector('.laca-client-ver').innerHTML = `<code>${result.version}</code>`;
                        row.querySelector('.laca-status-badge').innerHTML =
                            `<span style="background:#19875422;color:#198754;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">✅ Đồng bộ</span>`;
                    }
                } else {
                    failCount++;
                    li.innerHTML = `❌ <strong>${blockName}</strong>: ${result.message}`;
                }
                list.prepend(li);
            }

            Swal.fire({
                icon:  failCount === 0 ? 'success' : (successCount > 0 ? 'warning' : 'error'),
                title: failCount === 0 ? '✅ Hoàn tất!' : `⚠️ ${successCount} thành công, ${failCount} lỗi`,
                html:  list.outerHTML,
                confirmButtonText: 'Đóng',
            });

        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: e.message });
        }
    }

    // --- Push Selected ---
    document.getElementById('laca-push-blocks-btn')?.addEventListener('click', function () {
        runPush(getSelectedBlocks());
    });

    // --- Sync Outdated ---
    document.getElementById('laca-sync-outdated-btn')?.addEventListener('click', function () {
        const outdated = getOutdatedBlocks();
        if (!outdated.length) {
            Swal.fire({ icon: 'success', title: 'Tất cả đã đồng bộ!', timer: 2000, showConfirmButton: false });
            return;
        }
        Swal.fire({
            icon: 'question',
            title: `Sync ${outdated.length} block(s) lỗi thời?`,
            text: outdated.join(', '),
            showCancelButton: true,
            confirmButtonText: 'Sync ngay',
        }).then(r => { if (r.isConfirmed) runPush(outdated); });
    });

    // --- Refresh Status ---
    document.getElementById('laca-refresh-status-btn')?.addEventListener('click', async function () {
        const postId = this.dataset.postId;
        const nonce  = this.dataset.nonce;
        const statusEl = document.getElementById('laca-sync-status-text');

        statusEl.textContent = '⏳ Đang tải...';
        this.disabled = true;

        const fd = new FormData();
        fd.append('action',  'laca_fetch_client_blocks');
        fd.append('nonce',   nonce);
        fd.append('post_id', postId);

        try {
            const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                statusEl.textContent = '❌ ' + (data.data?.message || 'Lỗi');
                return;
            }

            const installed = data.data.installed;
            document.querySelectorAll('#laca-blocks-table-body tr').forEach(row => {
                const block   = row.dataset.block;
                const version = installed[block] || null;
                row.dataset.clientVer = version || '';

                const localVer    = row.dataset.localVer;
                const clientVerEl = row.querySelector('.laca-client-ver');
                const badgeEl     = row.querySelector('.laca-status-badge');

                clientVerEl.innerHTML = version ? `<code>${version}</code>` : '<span style="color:#999">—</span>';

                if (!version) {
                    badgeEl.innerHTML = `<span style="background:#6c757d22;color:#6c757d;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">🆕 Chưa có</span>`;
                } else if (localVer !== version) {
                    badgeEl.innerHTML = `<span style="background:#fd7e1422;color:#fd7e14;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">⚠️ Cũ hơn</span>`;
                } else {
                    badgeEl.innerHTML = `<span style="background:#19875422;color:#198754;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">✅ Đồng bộ</span>`;
                }
            });

            statusEl.textContent = `✅ Cập nhật ${new Date().toLocaleTimeString('vi-VN')}`;
        } catch (e) {
            statusEl.textContent = '❌ Lỗi: ' + e.message;
        } finally {
            this.disabled = false;
        }
    });
});
JS;
    }
}
