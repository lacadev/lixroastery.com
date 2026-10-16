<?php

namespace App\Features\DiscordHub;

/**
 * Kiểm tra & vận hành Discord Hub qua WP-CLI.
 */
final class CliCommand
{
    /**
     * Trạng thái cấu hình và hàng đợi.
     *
     * ## EXAMPLES
     *     wp laca-discord status
     */
    public function status(): void
    {
        \WP_CLI::log('Bật:          ' . (Settings::isEnabled() ? 'có' : 'KHÔNG'));
        \WP_CLI::log('Environment:  ' . wp_get_environment_type() . (Settings::isEnvironmentAllowed() ? '' : ' (BỊ CHẶN — bật "Cho phép gửi ngoài production" trong admin)'));
        foreach (Settings::CHANNELS as $channel => $info) {
            $state = Settings::channelStatus($channel)['state'];
            $label = ['ok' => 'OK', 'broken' => 'KHÔNG GIẢI MÃ ĐƯỢC — dán lại webhook', 'empty' => 'chưa cấu hình'][$state];
            \WP_CLI::log(sprintf('Kênh %-9s %s', $channel . ':', $label));
        }
        $stats = Queue::stats();
        \WP_CLI::log('Hàng đợi:     ' . ($stats ? wp_json_encode($stats) : 'rỗng'));
        $next = wp_next_scheduled(Dispatcher::CRON_HOOK);
        \WP_CLI::log('Cron flush:   ' . ($next ? gmdate('c', $next) : 'không có'));
    }

    /**
     * Gửi 1 embed thử và flush ngay.
     *
     * ## OPTIONS
     * [--channel=<channel>]
     * : leads | audit | security
     * ---
     * default: audit
     * ---
     */
    public function test($args, $assoc): void
    {
        $channel = (string) ($assoc['channel'] ?? Settings::CHANNEL_AUDIT);
        if (!Settings::canSend($channel)) {
            \WP_CLI::error("Kênh {$channel} không gửi được (chưa cấu hình, đang tắt, hoặc môi trường không phải production).");
        }
        Notifier::send($channel, 'test', AdminPage::testEmbed($channel));
        $this->flush();
    }

    /**
     * Gửi toàn bộ hàng đợi ngay.
     */
    public function flush(): void
    {
        Dispatcher::flush(static function ($message) {
            \WP_CLI::log($message);
        });
        \WP_CLI::success('Flush xong. Còn lại: ' . wp_json_encode(Queue::stats()));
    }
}
