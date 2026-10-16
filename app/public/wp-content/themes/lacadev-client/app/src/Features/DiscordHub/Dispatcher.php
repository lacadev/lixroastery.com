<?php

namespace App\Features\DiscordHub;

/**
 * Điều phối việc flush hàng đợi — đây là lớp "non-blocking" thật sự.
 *
 * Lưu ý: `wp_remote_post(['blocking' => false])` KHÔNG non-blocking với transport cURL:
 * Requests vẫn gọi curl_exec() đồng bộ (chờ DNS + TCP + TLS + response tới hết timeout),
 * chỉ bỏ qua phần đọc body. Đặt timeout cực nhỏ (0.01s) thì request bị huỷ trước khi TLS
 * xong → mất tin âm thầm. Vì vậy agent:
 *
 *   1. Trong request: chỉ INSERT vào hàng đợi (~1ms).
 *   2. Ở `shutdown`: nếu SAPI hỗ trợ (LiteSpeed LSAPI / PHP-FPM) → finish request
 *      (trả response cho trình duyệt) rồi mới gửi Discord ở chế độ blocking, đọc được 429.
 *   3. SAPI không hỗ trợ (mod_php) → hẹn WP-Cron chạy ngay + spawn_cron().
 *   4. CLI/Cron → gửi trực tiếp.
 *   5. Lỗi/rate-limit → hẹn WP-Cron single event đúng thời điểm được phép gửi lại.
 */
final class Dispatcher
{
    const CRON_HOOK     = 'laca_discord_hub_flush';
    const TIME_BUDGET   = 20;
    const LOCK_TIMEOUT  = 0;

    /** @var bool */
    private static $dirty = false;

    public static function register(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'flush']);
        add_action('shutdown', [self::class, 'onShutdown'], PHP_INT_MAX);
    }

    public static function markDirty(): void
    {
        self::$dirty = true;
    }

    public static function onShutdown(): void
    {
        if (!self::$dirty) {
            return;
        }
        self::$dirty = false;

        if ((defined('WP_CLI') && WP_CLI) || wp_doing_cron()) {
            self::flush();
            return;
        }

        if (apply_filters('laca_discord_hub/use_finish_request', true) && self::finishRequest()) {
            self::flush();
            return;
        }

        self::scheduleAt(time());
        if (function_exists('spawn_cron') && !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)) {
            spawn_cron();
        }
    }

    /**
     * Gửi toàn bộ kênh đang có hàng chờ trong giới hạn thời gian.
     *
     * @param callable|null $logger
     * @param float|null    $budget Giây tối đa (mặc định TIME_BUDGET) — lúc fatal error cần ngắn hơn.
     */
    public static function flush($logger = null, ?float $budget = null): void
    {
        if (!Settings::isEnabled() || !Settings::isEnvironmentAllowed()) {
            return;
        }

        $lock = self::acquireLock();
        if (false === $lock) {
            // Worker khác đang gửi; hẹn lại phòng khi nó vừa kết thúc trước khi thấy dòng mới.
            self::scheduleAt(time() + 30);
            return;
        }

        $deadline = microtime(true) + ($budget ?? (float) apply_filters('laca_discord_hub/flush_time_budget', self::TIME_BUDGET));
        $sender   = new DiscordWebhookSender(is_callable($logger) ? $logger : null);

        try {
            Queue::purgeExpired();
            foreach (Queue::readyChannels() as $channel) {
                if (microtime(true) >= $deadline - 1) {
                    break;
                }
                $sender->drainChannel($channel, $deadline);
            }
        } finally {
            self::releaseLock($lock);
        }

        $next = Queue::nextDueAt();
        if (null !== $next) {
            self::scheduleAt(max($next, time() + 5));
        }
    }

    /**
     * Hẹn đúng 1 single event (event sớm nhất thắng). WP bỏ qua event trùng hook
     * trong 10 phút, nên phải tự dời event cũ thay vì schedule thêm.
     */
    public static function scheduleAt(int $timestamp): void
    {
        $next = wp_next_scheduled(self::CRON_HOOK);
        if ($next && $next <= $timestamp) {
            return;
        }
        if ($next) {
            wp_unschedule_event($next, self::CRON_HOOK);
        }
        wp_schedule_single_event($timestamp, self::CRON_HOOK);
    }

    /**
     * Trả response cho client rồi tiếp tục chạy (LiteSpeed: litespeed_finish_request,
     * PHP-FPM: fastcgi_finish_request). Chạy ở shutdown PHP_INT_MAX nên output buffer
     * của WP (wp_ob_end_flush_all @ priority 1) và LiteSpeed Cache đã flush xong.
     */
    private static function finishRequest(): bool
    {
        if (function_exists('litespeed_finish_request')) {
            ignore_user_abort(true);
            litespeed_finish_request();
            return true;
        }
        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
            fastcgi_finish_request();
            return true;
        }
        return false;
    }

    /**
     * Mutex MySQL GET_LOCK — tự nhả khi connection đóng (worker chết không để lại lock treo).
     *
     * @return string|false|null tên lock, false nếu đang bị giữ, null nếu DB không hỗ trợ (vẫn chạy: claim đã nguyên tử).
     */
    private static function acquireLock()
    {
        global $wpdb;
        $name = 'laca_dh_' . substr(md5(DB_NAME . '|' . $wpdb->prefix), 0, 24);
        $got  = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, self::LOCK_TIMEOUT));
        if (null === $got) {
            return null;
        }
        return '1' === (string) $got ? $name : false;
    }

    /**
     * @param string|null $name
     */
    private static function releaseLock($name): void
    {
        if (is_string($name)) {
            global $wpdb;
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }
}
