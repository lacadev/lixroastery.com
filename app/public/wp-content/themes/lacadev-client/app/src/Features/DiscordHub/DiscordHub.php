<?php

namespace App\Features\DiscordHub;

use App\Features\DiscordHub\Monitors\CommentMonitor;
use App\Features\DiscordHub\Monitors\FormMonitor;
use App\Features\DiscordHub\Monitors\LoginMonitor;
use App\Features\DiscordHub\Monitors\PostMonitor;
use App\Features\DiscordHub\Monitors\UserMonitor;

/**
 * Discord Hub: bắt sự kiện site (lead, bài viết, bình luận, user, đăng nhập lỗi)
 * và đẩy về Discord qua hàng đợi có rate-limit. Cấu hình ở Laca Admin → Discord Hub.
 */
final class DiscordHub
{
    public function boot(): void
    {
        if (is_admin()) {
            (new AdminPage())->register();
        }

        // Chưa cấu hình kênh nào / đang tắt → không đăng ký hook nào (zero overhead).
        if (!Settings::isEnabled() || !Settings::hasAnyChannel()) {
            return;
        }

        Dispatcher::register();

        (new PostMonitor())->register();
        (new CommentMonitor())->register();
        (new UserMonitor())->register();
        (new LoginMonitor())->register();
        (new FormMonitor())->register();

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('laca-discord', CliCommand::class);
        }
    }
}
