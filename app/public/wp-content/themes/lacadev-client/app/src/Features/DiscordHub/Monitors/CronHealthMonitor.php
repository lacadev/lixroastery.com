<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Sức khoẻ cron. Khi đã chuyển sang cron thật (DISABLE_WP_CRON + cron cPanel), nếu cron
 * cPanel bị xoá/hỏng thì MỌI việc định kỳ (quét file, tin sửa bài gửi trễ, kiểm tra cập nhật,
 * lịch WooCommerce) dừng âm thầm → cảnh báo khi cron không chạy quá 2 giờ.
 *
 * Chi phí mỗi request thường: đọc 1 option autoload + so thời gian.
 */
final class CronHealthMonitor
{
    public const LAST_RUN_OPTION = 'laca_dh_cron_last_run';
    private const STALE_AFTER = 2 * HOUR_IN_SECONDS;
    private const ALERT_THROTTLE = 'laca_dh_cron_stale_alerted';

    public function register(): void
    {
        if (wp_doing_cron()) {
            add_action('shutdown', [self::class, 'recordRun'], 1);
            return;
        }
        add_action('shutdown', [$this, 'checkStale'], 2);
    }

    public static function recordRun(): void
    {
        update_option(self::LAST_RUN_OPTION, time(), true);
    }

    public static function usesRealCron(): bool
    {
        return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
    }

    /**
     * Số sự kiện cron đã quá hạn hơn 15 phút mà chưa chạy.
     */
    public static function overdueCount(): int
    {
        $count = 0;
        $limit = time() - 15 * MINUTE_IN_SECONDS;
        foreach ((array) _get_cron_array() as $timestamp => $hooks) {
            if ((int) $timestamp < $limit) {
                $count += count((array) $hooks);
            }
        }
        return $count;
    }

    /**
     * Lệnh gợi ý cho cPanel → Cron Jobs (đường dẫn thật của site + PHP CLI cùng phiên bản).
     */
    public static function suggestedCommand(): string
    {
        $php = '/usr/local/bin/php';
        if (defined('PHP_BINARY') && PHP_BINARY && str_contains(basename(PHP_BINARY), 'lsphp')) {
            $cli = dirname(PHP_BINARY) . '/php';
            $php = @is_executable($cli) ? $cli : $php;
        }
        return sprintf('%s -q %swp-cron.php >/dev/null 2>&1', $php, wp_normalize_path(ABSPATH));
    }

    public function checkStale(): void
    {
        if (!self::usesRealCron()) {
            return;
        }
        $last = (int) get_option(self::LAST_RUN_OPTION, 0);
        if (0 === $last) {
            // Mới bật tính năng: lấy mốc từ bây giờ, không báo nhầm.
            update_option(self::LAST_RUN_OPTION, time(), true);
            return;
        }
        if (time() - $last < self::STALE_AFTER || get_transient(self::ALERT_THROTTLE)) {
            return;
        }
        set_transient(self::ALERT_THROTTLE, 1, 12 * HOUR_IN_SECONDS);

        $hours = (int) floor((time() - $last) / HOUR_IN_SECONDS);
        $embed = Embed::make(
            sprintf(I18n::t('⏰ Cron không chạy %1$d giờ', '⏰ Cron has not run for %1$d hours'), $hours),
            Embed::COLOR_DANGER
        )
            ->description(I18n::block(
                'Site đang dùng cron thật (DISABLE_WP_CRON) nhưng cron cPanel không chạy → quét file, kiểm tra cập nhật, tin sửa bài, lịch WooCommerce đều đang dừng. Kiểm tra cPanel → Cron Jobs.',
                'The site uses a real cron (DISABLE_WP_CRON) but the cPanel cron is not running → file scans, update checks, delayed edit notices and WooCommerce schedules are stalled. Check cPanel → Cron Jobs.'
            ), true)
            ->field(I18n::t('Lần chạy cuối', 'Last run'), wp_date('d/m/Y H:i', $last))
            ->field(I18n::t('Sự kiện quá hạn', 'Overdue events'), (string) self::overdueCount())
            ->codeField(I18n::t('Lệnh cron cPanel', 'cPanel cron command'), self::suggestedCommand(), false);

        Notifier::send(Settings::CHANNEL_SECURITY, 'cron.stale', $embed);
    }
}
