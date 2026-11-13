<?php

namespace App\Features\Performance;

/**
 * Bộ đếm lượt xem bài viết bất đồng bộ (Beacon + REST + Buffer + Cron).
 *
 * Giải quyết triệt để vấn đề:
 * - Request xem bài viết không ghi DB trực tiếp (0 WRITES).
 * - Tương thích hoàn toàn với Full-Page Cache (HTML tĩnh lưu 48h vẫn đếm đúng view).
 * - Chống spam lượt xem: 1 IP chỉ đếm 1 view / bài / 30 phút.
 * - Loại bỏ bot và crawler tự động.
 * - Gom bộ đệm ghi theo lô mỗi 5 phút qua cron, chống lock dòng MySQL khi traffic cao.
 */
class PostViewTracker
{
    public const BUFFER_OPTION   = 'laca_view_buffer'; // bản cũ (1 option chung) — không còn ghi, chỉ để tương thích
    public const BUFFER_PREFIX   = 'laca_vbuf_';       // 1 dòng/bài, tăng nguyên tử
    public const CRON_HOOK       = 'laca_flush_view_counts';
    public const VIEW_COUNT_META = '_gm_view_count';
    public const LOCK_TRANSIENT  = 'laca_flush_view_lock';

    /**
     * Khởi tạo và đăng ký hooks.
     */
    public static function register(): void
    {
        // 1. Thêm lịch cron 5 phút
        add_filter('cron_schedules', [self::class, 'addCronSchedule']);

        // 2. Lên lịch cron định kỳ
        add_action('init', [self::class, 'maybeScheduleCron']);

        // 3. Callback xử lý cron
        add_action(self::CRON_HOOK, [self::class, 'flushBuffer']);

        // 4. REST API Endpoint
        add_action('rest_api_init', [self::class, 'registerRestRoutes']);

        // 5. In script beacon ở footer trang single post
        add_action('wp_footer', [self::class, 'renderTrackingScript'], 99);
    }

    /**
     * Thêm chu kỳ 5 phút cho WP-Cron.
     *
     * @param array<string, mixed> $schedules
     * @return array<string, mixed>
     */
    public static function addCronSchedule(array $schedules): array
    {
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = [
                'interval' => 300,
                'display'  => __('Mỗi 5 phút (Laca)', 'laca'),
            ];
        }
        return $schedules;
    }

    /**
     * Lên lịch cron nếu chưa có.
     */
    public static function maybeScheduleCron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'five_minutes', self::CRON_HOOK);
        }
    }

    /**
     * Kiểm tra tính năng có đang bật không.
     */
    public static function isEnabled(): bool
    {
        // Mặc định bật nếu chưa cấu hình
        $val = get_option('_enable_post_view_counter');
        if ($val === false || $val === '') {
            return true;
        }
        return in_array($val, ['yes', '1', 1, true], true);
    }

    /**
     * Đăng ký REST Route POST /wp-json/laca/v1/view.
     */
    public static function registerRestRoutes(): void
    {
        register_rest_route('laca/v1', '/view', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handleViewRequest'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => static fn($param) => is_numeric($param) && (int) $param > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Xử lý request ghi nhận view từ frontend beacon.
     */
    public static function handleViewRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!self::isEnabled()) {
            return new \WP_REST_Response(['success' => false, 'reason' => 'disabled'], 200);
        }

        $postId = (int) $request->get_param('id');
        $post   = get_post($postId);
        if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'post') {
            return new \WP_REST_Response(['success' => false, 'reason' => 'invalid_post'], 400);
        }

        // 1. Kiểm tra bot
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? trim((string) $_SERVER['HTTP_USER_AGENT']) : '';
        if (empty($userAgent) || self::isBot($userAgent)) {
            return new \WP_REST_Response(['success' => false, 'reason' => 'bot_ignored'], 200);
        }

        // 2. Chống đếm lặp: trình duyệt tự nhớ 30 phút/bài (localStorage). Server chỉ chặn thêm khi có object cache bền —
        // KHÔNG dùng transient vì không có Redis thì mỗi lượt xem đẻ 2 dòng wp_options (hàng chục nghìn dòng khi đông khách)
        if (wp_using_ext_object_cache()) {
            $rateKey = 'v_' . md5(self::getClientIp() . '_' . $postId);
            if (!wp_cache_add($rateKey, 1, 'laca_views_rl', 30 * MINUTE_IN_SECONDS)) {
                return new \WP_REST_Response(['success' => true, 'duplicate' => true], 200);
            }
        }

        // 3. Gom vào bộ đệm
        self::recordView($postId);

        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * Nhận diện bot/crawler qua User-Agent.
     */
    public static function isBot(string $ua): bool
    {
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|mediapartners|lighthouse|pagespeed/i',
            $ua
        );
    }

    /**
     * Lấy IP khách truy cập thực tế.
     */
    public static function getClientIp(): string
    {
        // Dùng helper chung của theme (xử lý proxy tin cậy) — không tự tin X-Forwarded-For vì giả mạo được
        if (class_exists(\App\Helpers\ClientIp::class)) {
            return \App\Helpers\ClientIp::get();
        }
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    }

    /**
     * Ghi nhận view vào bộ đệm tạm thời (không ghi trực tiếp vào post meta).
     */
    public static function recordView(int $postId): void
    {
        // Có Redis/Memcached: cộng trong cache, không đụng DB
        if (wp_using_ext_object_cache()) {
            wp_cache_add('ids', [], 'laca_views');
            $ids = (array) wp_cache_get('ids', 'laca_views');
            if (!in_array($postId, $ids, true)) {
                $ids[] = $postId;
                wp_cache_set('ids', $ids, 'laca_views');
            }
            wp_cache_add('p_' . $postId, 0, 'laca_views');
            wp_cache_incr('p_' . $postId, 1, 'laca_views');
            return;
        }

        // Không có object cache: 1 dòng/bài, tăng NGUYÊN TỬ bằng 1 câu SQL (không đọc-sửa-ghi → không mất lượt,
        // không dồn mọi bài vào chung 1 dòng). option_name là UNIQUE nên ON DUPLICATE KEY hoạt động.
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no')
             ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
            self::BUFFER_PREFIX . $postId
        ));
    }

    /**
     * Xả bộ đệm và cập nhật theo lô vào _gm_view_count (chạy định kỳ qua Cron).
     */
    public static function flushBuffer(): int
    {
        // Khoá chống chạy chồng
        if (get_transient(self::LOCK_TRANSIENT)) {
            return 0;
        }
        set_transient(self::LOCK_TRANSIENT, 1, 60);

        global $wpdb;
        $pending = [];

        if (wp_using_ext_object_cache()) {
            foreach ((array) wp_cache_get('ids', 'laca_views') as $postId) {
                $count = (int) wp_cache_get('p_' . $postId, 'laca_views');
                if ($count > 0) {
                    $pending[(int) $postId] = $count;
                    wp_cache_decr('p_' . $postId, $count, 'laca_views');
                }
            }
        }

        // Bộ đệm kiểu cũ (1 option chung) còn sót từ bản trước → gộp nốt rồi bỏ
        $legacy = get_option(self::BUFFER_OPTION, null);
        if (is_array($legacy)) {
            foreach ($legacy as $postId => $count) {
                if ((int) $postId > 0 && (int) $count > 0) {
                    $pending[(int) $postId] = ($pending[(int) $postId] ?? 0) + (int) $count;
                }
            }
            delete_option(self::BUFFER_OPTION);
        }

        // Dòng bộ đệm SQL (kể cả còn sót khi vừa bật/tắt object cache)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 2000",
            $wpdb->esc_like(self::BUFFER_PREFIX) . '%'
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $postId = (int) substr($row['option_name'], strlen(self::BUFFER_PREFIX));
            $count  = (int) $row['option_value'];
            if ($postId <= 0 || $count <= 0) {
                continue;
            }
            // Trừ đúng số đã đọc (lượt mới phát sinh trong lúc xả vẫn được giữ lại), rồi dọn dòng về 0
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) - %d WHERE option_name = %s",
                $count,
                $row['option_name']
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = '0'",
                $row['option_name']
            ));
            $pending[$postId] = ($pending[$postId] ?? 0) + $count;
        }

        $totalUpdated = 0;
        foreach ($pending as $postId => $increment) {
            $current = (int) get_post_meta($postId, self::VIEW_COUNT_META, true);
            update_post_meta($postId, self::VIEW_COUNT_META, $current + $increment);
            delete_transient('post_view_count_' . $postId);
            $totalUpdated += $increment;
        }

        delete_transient(self::LOCK_TRANSIENT);
        return $totalUpdated;
    }

    /**
     * In script beacon ở footer trang single post.
     */
    public static function renderTrackingScript(): void
    {
        if (!is_single() || !self::isEnabled()) {
            return;
        }

        $postId = get_the_ID();
        if (!$postId) {
            return;
        }

        $endpoint = esc_url_raw(rest_url('laca/v1/view'));
        ?>
        <script>
        (function() {
            var postId = <?php echo (int) $postId; ?>;
            var endpoint = <?php echo wp_json_encode($endpoint); ?>;
            var sent = false;

            // Chống đếm lặp phía trình duyệt: 1 lượt / bài / 30 phút
            var seenKey = 'laca_v_' + postId;
            try {
                var last = parseInt(localStorage.getItem(seenKey) || '0', 10);
                if (last && Date.now() - last < 30 * 60 * 1000) { sent = true; }
            } catch (e) {}

            function sendView() {
                if (sent || document.visibilityState === 'hidden') return;
                sent = true;
                try { localStorage.setItem(seenKey, String(Date.now())); } catch (e) {}
                var payload = JSON.stringify({ id: postId });
                if (navigator.sendBeacon) {
                    try {
                        var blob = new Blob([payload], { type: 'application/json' });
                        navigator.sendBeacon(endpoint, blob);
                        return;
                    } catch (e) {}
                }
                if (window.fetch) {
                    fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: payload,
                        keepalive: true
                    }).catch(function() {});
                }
            }

            // Gửi sau khi trang tải xong và người xem nhìn thấy bài viết >= 1s
            if (document.readyState === 'complete') {
                setTimeout(sendView, 1000);
            } else {
                window.addEventListener('load', function() {
                    setTimeout(sendView, 1000);
                });
            }
        })();
        </script>
        <?php
    }
}

