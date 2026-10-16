<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\ActivityLog;
use App\Features\DiscordHub\Dispatcher;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Lỗi PHP nghiêm trọng (fatal) — cùng loại lỗi WP gửi email "Your Site is Experiencing a Technical Issue".
 *
 * Bắt ở 2 chỗ vì handler của WP (WP_Fatal_Error_Handler) gọi wp_die() → exit, và exit
 * trong 1 shutdown function chặn mọi shutdown function đăng ký SAU nó:
 *  - filter `wp_php_error_message`: chạy ngay trước khi WP hiện trang lỗi (wp-admin, hoặc
 *    frontend khi chưa gửi header);
 *  - shutdown function riêng: các trường hợp WP không hiện trang lỗi (frontend đã in output).
 *
 * Request đang chết nên không chờ cron: gửi đồng bộ ngay, nhưng tối đa 1 lần/phút — site
 * sập thì MỌI request đều fatal, phần còn lại chỉ gộp vào cùng 1 tin (×N) để gửi lượt sau.
 */
final class FatalErrorMonitor
{
    private const FATAL_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    private const FLUSH_THROTTLE_KEY = 'laca_dh_fatal_flush';

    private bool $reported = false;

    public function register(): void
    {
        add_filter('wp_php_error_message', [$this, 'onWpErrorMessage'], 1, 2);
        register_shutdown_function([$this, 'onShutdown']);
    }

    /**
     * @param string $message
     * @param array  $error
     */
    public function onWpErrorMessage($message, $error)
    {
        $this->report(is_array($error) ? $error : []);
        return $message;
    }

    public function onShutdown(): void
    {
        $error = error_get_last();
        if (is_array($error)) {
            $this->report($error);
        }
    }

    private function report(array $error): void
    {
        if ($this->reported || !in_array((int) ($error['type'] ?? 0), self::FATAL_TYPES, true)) {
            return;
        }
        $this->reported = true;

        // Không bao giờ để chính bộ báo lỗi gây thêm lỗi trong lúc site đang lỗi.
        try {
            $file = $this->relativePath((string) ($error['file'] ?? ''));
            $line = (int) ($error['line'] ?? 0);
            $message = $this->cleanMessage((string) ($error['message'] ?? ''));

            $embed = Embed::make(I18n::t('🔥 Lỗi nghiêm trọng (PHP Fatal Error)', '🔥 Critical error (PHP Fatal Error)'), Embed::COLOR_DANGER)
                ->description("```\n" . Embed::truncate(str_replace('```', "'''", $message), 1500) . "\n```", true)
                ->codeField(I18n::t('Vị trí', 'Location'), $file . ':' . $line, false)
                ->field(I18n::t('Thủ phạm', 'Source'), $this->culprit($file))
                ->codeField(I18n::t('Trang lỗi', 'Request'), $this->requestPath())
                ->field(
                    I18n::t('Xử lý', 'Next steps'),
                    I18n::block(
                        'WordPress đã gửi email kèm link Recovery Mode tới email quản trị. Lỗi lặp lại sẽ được gộp vào tin này (×N).',
                        'WordPress emailed a Recovery Mode link to the admin address. Repeats are merged into this message (×N).'
                    ),
                    false,
                    true
                );

            // "Ai vừa làm gì" ngay trước lỗi — thường chính là nguyên nhân (bật plugin, lưu cài đặt, sửa code…).
            $recent = ActivityLog::since(15 * MINUTE_IN_SECONDS);
            if ($recent) {
                $lines = [];
                foreach (array_slice($recent, 0, 8) as $item) {
                    $lines[] = wp_date('H:i', (int) $item['t']) . ' · ' . Embed::escape($item['actor']) . ' · ' . Embed::escape($item['title']);
                }
                $embed->field(I18n::t('🕵️ Hoạt động 15 phút trước lỗi', '🕵️ Activity in the 15 minutes before'), Embed::truncate(implode("\n", $lines), Embed::LIMIT_FIELD_VALUE), false, true);
            }

            Notifier::send(Settings::CHANNEL_SECURITY, 'php.fatal', $embed, 'fatal:' . md5($file . ':' . $line . ':' . $message));

            if (false === get_transient(self::FLUSH_THROTTLE_KEY)) {
                set_transient(self::FLUSH_THROTTLE_KEY, 1, MINUTE_IN_SECONDS);
                Dispatcher::flush(null, 8.0);
            }
        } catch (\Throwable $e) {
            error_log('[laca-discord-hub] Không gửi được báo cáo fatal error: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }
    }

    /**
     * Đường dẫn tuyệt đối trên server (lộ username hosting) → tương đối với gốc WordPress.
     */
    private function relativePath(string $path): string
    {
        $root = wp_normalize_path(ABSPATH);
        $path = wp_normalize_path($path);
        return str_starts_with($path, $root) ? substr($path, strlen($root)) : basename($path);
    }

    private function cleanMessage(string $message): string
    {
        // Chỉ bỏ tiền tố ABSPATH — KHÔNG wp_normalize_path() cả message (sẽ đổi "\" của namespace thành "/").
        $message = str_replace([ABSPATH, wp_normalize_path(ABSPATH), rtrim(ABSPATH, '/\\')], ['', '', ''], $message);
        // Stack trace dài vượt giới hạn embed và ít giá trị trên Discord — giữ 6 dòng đầu.
        return implode("\n", array_slice(explode("\n", trim($message)), 0, 6));
    }

    private function culprit(string $relative): string
    {
        if (preg_match('#^wp-content/(plugins|themes|mu-plugins)/([^/]+)#', $relative, $m)) {
            $kind = ['plugins' => 'Plugin', 'themes' => 'Theme', 'mu-plugins' => 'MU-plugin'][$m[1]];
            return $kind . ': ' . $m[2];
        }
        return str_starts_with($relative, 'wp-includes/') || str_starts_with($relative, 'wp-admin/') ? 'WordPress core' : '—';
    }

    /**
     * Chỉ path, bỏ query string (có thể chứa token/nonce/recovery key).
     */
    private function requestPath(): string
    {
        if (defined('WP_CLI') && WP_CLI) {
            return 'WP-CLI';
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        return '' !== $path ? $path : '/';
    }
}
