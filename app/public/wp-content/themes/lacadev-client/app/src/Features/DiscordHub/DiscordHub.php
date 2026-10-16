<?php

namespace App\Features\DiscordHub;

use App\Features\DiscordHub\FileWatch\DeployReceiver;
use App\Features\DiscordHub\FileWatch\Scanner;
use App\Features\DiscordHub\Monitors\CommentMonitor;
use App\Features\DiscordHub\Monitors\FatalErrorMonitor;
use App\Features\DiscordHub\Monitors\FileEditorMonitor;
use App\Features\DiscordHub\Monitors\FormMonitor;
use App\Features\DiscordHub\Monitors\LoginMonitor;
use App\Features\DiscordHub\Monitors\PostMonitor;
use App\Features\DiscordHub\Monitors\UpdateMonitor;
use App\Features\DiscordHub\Monitors\UserMonitor;

/**
 * Discord Hub: bắt sự kiện site (lead, bài viết, bình luận, user, đăng nhập lỗi, fatal error)
 * và đẩy về Discord qua hàng đợi có rate-limit. Cấu hình ở Laca Admin → Discord Hub.
 */
final class DiscordHub
{
    /**
     * Mọi bước đều bọc try/catch: đây là tính năng PHỤ, không bao giờ được làm sập site.
     * Sự cố thật 16/10/2026: FTP deploy đang ghi đè dở 1 file monitor → "Class not found"
     * ném ra từ boot() → fatal toàn site cho tới khi upload xong.
     */
    public function boot(): void
    {
        $this->safely('admin', function () {
            if (is_admin()) {
                (new AdminPage())->register();
            }
        });

        // Chưa cấu hình kênh nào / đang tắt → không đăng ký hook nào (zero overhead).
        if (!$this->safely('settings', fn () => Settings::isEnabled() && Settings::hasAnyChannel())) {
            return;
        }

        // Đăng ký bắt fatal TRƯỚC các monitor khác để báo được cả lỗi xảy ra sau đó.
        $this->safely('fatal', fn () => (new FatalErrorMonitor())->register());
        $this->safely('dispatcher', fn () => Dispatcher::register());

        foreach ([PostMonitor::class, CommentMonitor::class, UserMonitor::class, LoginMonitor::class, FormMonitor::class, FileEditorMonitor::class, UpdateMonitor::class] as $monitor) {
            $this->safely($monitor, fn () => (new $monitor())->register());
        }

        $this->safely('file-watch', function () {
            if (Settings::fileWatchEnabled()) {
                (new Scanner())->register();
            } elseif (wp_next_scheduled(Scanner::CRON_HOOK)) {
                Scanner::unschedule();
            }
            if (null !== Settings::deployKeyMeta()) {
                (new DeployReceiver())->register();
            }
        });

        if (defined('WP_CLI') && WP_CLI) {
            $this->safely('cli', fn () => \WP_CLI::add_command('laca-discord', CliCommand::class));
        }
    }

    /**
     * @return mixed Kết quả callback, hoặc false nếu lỗi.
     */
    private function safely(string $step, callable $callback)
    {
        try {
            return $callback() ?? true;
        } catch (\Throwable $e) {
            error_log(sprintf('[laca-discord-hub] Bỏ qua bước "%s": %s tại %s:%d', $step, $e->getMessage(), $e->getFile(), $e->getLine())); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            return false;
        }
    }
}
