<?php

namespace App\Settings;

/**
 * BlockMarketplace
 *
 * Trang "Laca Theme → Block Marketplace" cho site khách hàng browse danh
 * mục Gutenberg block của clients.lacadev.com, xem preview, và bấm "Yêu cầu
 * đồng bộ" (block mới) / "Cập nhật" (block đã cài có bản mới).
 *
 * Chỉ nói chuyện với HUB (lacadev.com) qua kênh tracker log đã cấu hình sẵn
 * (Laca Admin → 📡 Tracker) — không gọi thẳng clients.lacadev.com, giữ đúng
 * nguyên tắc "site khách chỉ có 1 điểm liên hệ duy nhất là hub".
 */
class BlockMarketplace
{
    private const CACHE_KEY = 'laca_marketplace_catalog_cache';
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    private const RATE_LIMIT_PREFIX = 'laca_marketplace_rl_';
    private const RATE_LIMIT_WINDOW = 5 * MINUTE_IN_SECONDS;

    public function __construct()
    {
        add_action('wp_ajax_laca_marketplace_request_sync', [$this, 'ajaxRequestSync']);
    }

    // =========================================================================
    // RENDER (gọi từ theme-options.php qua Field::make('html', ...)->set_html())
    // =========================================================================

    public static function renderPage(): string
    {
        if (!LacaDevTrackerClient::isConfigured()) {
            return '<div class="laca-alert laca-alert--warning">'
                . '⚠️ Cần cấu hình Tracker (Endpoint URL + Secret Key) tại <strong>Laca Admin → 📡 Tracker</strong> trước khi dùng Block Marketplace.'
                . '</div>';
        }

        $data = self::getCatalogData();
        if ($data === null) {
            return '<div class="laca-alert laca-alert--error">'
                . '❌ Không lấy được danh mục block từ hub. Thử tải lại trang sau ít phút.'
                . '</div>';
        }

        ob_start();
        self::renderMarketplaceUi($data);

        return (string) ob_get_clean();
    }

    /**
     * @return array{blocks:array<int,array<string,mixed>>,installed:array<string,string>,requests:array<string,array<string,mixed>>}|null
     */
    private static function getCatalogData(): ?array
    {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $fresh = self::fetchFromHub();
        if ($fresh === null) {
            return null;
        }

        set_transient(self::CACHE_KEY, $fresh, self::CACHE_TTL);

        return $fresh;
    }

    private static function fetchFromHub(): ?array
    {
        $endpoint = LacaDevTrackerClient::getEndpoint();
        $secret   = LacaDevTrackerClient::getSecretKey();

        if (empty($endpoint) || empty($secret)) {
            return null;
        }

        // Endpoint tracker có dạng .../wp-json/laca/v1/tracker/log — đổi
        // sang đúng route catalog cùng namespace REST, không cần thêm 1
        // trường cấu hình URL riêng cho Block Marketplace.
        $catalogUrl = str_replace('/tracker/log', '/blocks-catalog', $endpoint);
        $url        = add_query_arg('secret_key', $secret, $catalogUrl);

        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success'])) {
            return null;
        }

        return [
            'blocks'    => is_array($body['blocks'] ?? null) ? $body['blocks'] : [],
            'installed' => is_array($body['installed'] ?? null) ? $body['installed'] : [],
            'requests'  => is_array($body['requests'] ?? null) ? $body['requests'] : [],
        ];
    }

    private static function renderMarketplaceUi(array $data): void
    {
        $blocks    = $data['blocks'];
        $installed = $data['installed'];
        $requests  = $data['requests'];
        $nonce     = wp_create_nonce('laca_marketplace');
        ?>
        <div id="laca-marketplace-wrap">
            <div class="laca-alert laca-alert--info laca-mb-3">
                <p class="laca-alert__title">🛒 Block Marketplace</p>
                <p class="laca-alert__description">Đây là danh sách các khối (block) giao diện có sẵn từ clients.lacadev.com mà bạn có thể thêm vào site của mình. Bấm <strong>"🧩 Yêu cầu đồng bộ"</strong> ở block muốn dùng — quản trị viên sẽ xem xét và duyệt, sau đó block sẽ tự động xuất hiện trong trình soạn thảo bài viết/trang của bạn. Nếu block đã có trên site và có bản mới hơn, nút <strong>"🔄 Cập nhật"</strong> sẽ hiện ra để bạn cập nhật ngay (không cần chờ duyệt lại).</p>
            </div>

            <div class="laca-mp-filter-bar">
                <button type="button" class="button button-primary laca-mp-filter" data-filter="all">Tất cả (<?php echo count($blocks); ?>)</button>
                <button type="button" class="button laca-mp-filter" data-filter="installed">Đã cài</button>
                <button type="button" class="button laca-mp-filter" data-filter="not-installed">Chưa cài</button>
                <button type="button" class="button laca-mp-filter" data-filter="update">Có bản cập nhật</button>
            </div>

            <div id="laca-mp-grid" class="laca-mp-grid">
                <?php foreach ($blocks as $block): ?>
                    <?php
                    // Định danh gửi lên server (path đầy đủ nếu block nằm trong 1
                    // bucket theo site, fallback về "name" cho nguồn catalog cũ
                    // chưa có field "path"). "name"/"title" vẫn dùng để hiển thị.
                    $name          = (string) ($block['path'] ?? $block['name']);
                    $localVersion  = $installed[$name] ?? null;
                    $isInstalled   = $localVersion !== null;
                    $hasUpdate     = $isInstalled && version_compare((string) $block['version'], (string) $localVersion, '>');
                    $requestInfo   = $requests[$name] ?? null;
                    $requestStatus = $requestInfo['status'] ?? null;

                    $filterState = 'not-installed';
                    if ($hasUpdate) {
                        $filterState = 'update';
                    } elseif ($isInstalled) {
                        $filterState = 'installed';
                    }
                    ?>
                    <div class="laca-mp-card" data-filter-state="<?php echo esc_attr($filterState); ?>">
                        <?php if (!empty($block['preview'])): ?>
                            <img src="data:image/png;base64,<?php echo esc_attr($block['preview']); ?>" class="laca-mp-preview" alt="">
                        <?php else: ?>
                            <div class="laca-mp-preview--empty">Không có preview</div>
                        <?php endif; ?>
                        <div class="laca-mp-body">
                            <strong class="laca-mp-title"><?php echo esc_html((string) $block['title']); ?></strong>
                            <?php if (!empty($block['description'])): ?>
                                <p class="laca-mp-desc"><?php echo esc_html((string) $block['description']); ?></p>
                            <?php endif; ?>
                            <p class="laca-mp-meta">
                                Bản mới nhất: <code><?php echo esc_html((string) $block['version']); ?></code>
                                <?php if ($isInstalled): ?>
                                    &middot; Đang dùng: <code><?php echo esc_html((string) $localVersion); ?></code>
                                <?php endif; ?>
                            </p>

                            <?php if ($hasUpdate): ?>
                                <button type="button" class="button button-primary laca-mp-action-btn laca-mp-btn-full" data-block="<?php echo esc_attr($name); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">🔄 Cập nhật lên <?php echo esc_html((string) $block['version']); ?></button>
                            <?php elseif ($isInstalled): ?>
                                <span class="laca-mp-status-banner laca-mp-status-banner--success">✅ Đã cài — mới nhất</span>
                            <?php elseif ($requestStatus === 'pending' || $requestStatus === 'auto_approved'): ?>
                                <span class="laca-mp-status-banner laca-mp-status-banner--pending">⏳ Đang chờ duyệt</span>
                            <?php elseif ($requestStatus === 'rejected'): ?>
                                <div class="laca-mp-status-banner laca-mp-status-banner--rejected">
                                    ✕ Đã bị từ chối<?php echo !empty($requestInfo['reason']) ? ': ' . esc_html((string) $requestInfo['reason']) : ''; ?>
                                </div>
                                <button type="button" class="button laca-mp-action-btn laca-mp-btn-full" data-block="<?php echo esc_attr($name); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">Yêu cầu lại</button>
                            <?php else: ?>
                                <button type="button" class="button laca-mp-action-btn laca-mp-btn-full" data-block="<?php echo esc_attr($name); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">🧩 Yêu cầu đồng bộ</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($blocks)): ?>
                    <p class="laca-status-muted">Chưa có block nào trong danh mục.</p>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.laca-mp-filter').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.laca-mp-filter').forEach(function (b) { b.classList.remove('button-primary'); });
                    this.classList.add('button-primary');
                    var filter = this.dataset.filter;
                    document.querySelectorAll('.laca-mp-card').forEach(function (card) {
                        var state = card.dataset.filterState;
                        var show = filter === 'all'
                            || (filter === 'installed' && (state === 'installed' || state === 'update'))
                            || (filter === 'not-installed' && state === 'not-installed')
                            || (filter === 'update' && state === 'update');
                        card.style.display = show ? '' : 'none';
                    });
                });
            });

            document.querySelectorAll('.laca-mp-action-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var block        = this.dataset.block;
                    var nonce        = this.dataset.nonce;
                    var originalText = this.textContent;
                    this.disabled    = true;
                    this.textContent = '⏳ Đang gửi...';
                    var self = this;

                    var fd = new FormData();
                    fd.append('action', 'laca_marketplace_request_sync');
                    fd.append('nonce', nonce);
                    fd.append('block', block);

                    fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data.success) {
                                self.textContent = '⏳ Đang chờ duyệt';
                            } else {
                                self.disabled    = false;
                                self.textContent = originalText;
                                alert((data.data && data.data.message) || 'Có lỗi xảy ra');
                            }
                        })
                        .catch(function (e) {
                            self.disabled    = false;
                            self.textContent = originalText;
                            alert('Lỗi mạng: ' + e.message);
                        });
                });
            });
        });
        </script>
        <?php
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public function ajaxRequestSync(): void
    {
        check_ajax_referer('laca_marketplace', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $blockName = self::sanitizeBlockRelativePath((string) ($_POST['block'] ?? ''));
        if (empty($blockName)) {
            wp_send_json_error(['message' => 'Thiếu tên block'], 400);
        }

        // Rate-limit: tối đa 1 request / 5 phút cho cùng 1 block, tránh spam
        $rateLimitKey = self::RATE_LIMIT_PREFIX . md5($blockName);
        if (get_transient($rateLimitKey)) {
            wp_send_json_error(['message' => 'Bạn vừa gửi yêu cầu cho block này — vui lòng đợi vài phút trước khi gửi lại.']);
        }

        $sent = LacaDevTrackerClient::requestBlockSync($blockName);

        if (!$sent) {
            wp_send_json_error(['message' => 'Gửi yêu cầu thất bại — kiểm tra lại cấu hình Tracker.']);
        }

        set_transient($rateLimitKey, 1, self::RATE_LIMIT_WINDOW);
        // Xoá cache catalog để lần load trang kế tiếp thấy trạng thái "đang chờ duyệt" mới nhất
        delete_transient(self::CACHE_KEY);

        wp_send_json_success(['message' => 'Đã gửi yêu cầu đồng bộ.']);
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
}
