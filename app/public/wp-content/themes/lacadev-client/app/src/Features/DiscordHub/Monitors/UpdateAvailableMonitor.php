<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Báo khi có BẢN CẬP NHẬT MỚI cho core/plugin/theme (chưa cài).
 *
 * Không tự gọi API WordPress.org: WP đã tự kiểm tra 2 lần/ngày (và mỗi khi vào trang
 * Cập nhật/Plugins) rồi lưu vào site transient update_* — chỉ cần đọc lại khi transient
 * được ghi + 1 cron 2 lần/ngày làm lưới an toàn. Mỗi phiên bản chỉ báo 1 lần; ngoài ra mỗi
 * tuần gửi 1 tin tổng hợp mọi bản còn chờ. Gửi vào kênh Updates (hoặc Audit nếu chưa cấu hình).
 */
final class UpdateAvailableMonitor
{
    public const CRON_HOOK = 'laca_discord_hub_update_check';
    private const NOTIFIED_OPTION = 'laca_dh_updates_notified';
    private const DIGEST_OPTION = 'laca_dh_updates_digest_at';
    private const DIGEST_INTERVAL = WEEK_IN_SECONDS;

    private bool $scheduled = false;

    public function register(): void
    {
        foreach (['update_plugins', 'update_themes', 'update_core'] as $transient) {
            add_action('set_site_transient_' . $transient, [$this, 'deferCheck']);
        }
        add_action(self::CRON_HOOK, [self::class, 'check']);
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'twicedaily', self::CRON_HOOK);
        }
    }

    /**
     * Transient có thể được ghi nhiều lần trong 1 request → kiểm tra 1 lần ở cuối request.
     */
    public function deferCheck(): void
    {
        if (!$this->scheduled) {
            $this->scheduled = true;
            add_action('shutdown', [self::class, 'check'], 3);
        }
    }

    public static function check(): void
    {
        try {
            $pending = self::pending();
            $notified = get_option(self::NOTIFIED_OPTION, []);
            $notified = is_array($notified) ? $notified : [];
            $now = time();

            $new = [];
            $tracked = [];
            foreach ($pending as $key => $item) {
                $known = $notified[$key] ?? null;
                // Bản cũ của option lưu dạng key => version (chưa có "since").
                $knownTo = is_array($known) ? (string) ($known['to'] ?? '') : (string) $known;
                if ($knownTo !== $item['to']) {
                    $new[$key] = $item;
                }
                // "Chờ từ" tính theo lần ĐẦU phát hiện mục này cần cập nhật, kể cả khi sau đó có bản mới hơn.
                $since = is_array($known) && !empty($known['since']) ? (int) $known['since'] : $now;
                $tracked[$key] = ['to' => $item['to'], 'since' => $since];
            }

            // Chỉ giữ những gì còn đang chờ cập nhật (đã cập nhật xong thì xoá khỏi danh sách).
            update_option(self::NOTIFIED_OPTION, $tracked, false);

            if ($new) {
                self::notify($new, count($pending));
            }
            self::maybeDigest($pending, $tracked);
        } catch (\Throwable $e) {
            error_log('[laca-discord-hub] UpdateAvailableMonitor: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }
    }

    /**
     * Tổng hợp hằng tuần TẤT CẢ bản cập nhật còn chờ — người quản trị xem 2–4 tuần/lần chỉ cần
     * đọc tin mới nhất của kênh, không phải cuộn tìm từng tin "có bản mới" rải rác.
     *
     * @param array<string,array{type:string,name:string,from:string,to:string}> $pending
     * @param array<string,array{to:string,since:int}>                           $tracked
     */
    private static function maybeDigest(array $pending, array $tracked): void
    {
        $now = time();
        $last = (int) get_option(self::DIGEST_OPTION, 0);

        if (!$pending) {
            delete_option(self::DIGEST_OPTION);
            return;
        }
        // Lần đầu có bản chờ: tính mốc 7 ngày từ đây (vừa có tin "có bản mới", không cần tổng hợp trùng).
        if (0 === $last) {
            update_option(self::DIGEST_OPTION, $now, false);
            return;
        }
        if ($now - $last < self::DIGEST_INTERVAL) {
            return;
        }
        update_option(self::DIGEST_OPTION, $now, false);

        uasort($tracked, static fn ($a, $b) => $a['since'] <=> $b['since']);
        $icons = ['core' => '⚙️', 'plugin' => '🔌', 'theme' => '🎨'];
        $lines = [];
        $oldest = 0;
        foreach ($tracked as $key => $info) {
            if (!isset($pending[$key])) {
                continue;
            }
            $item = $pending[$key];
            $days = (int) floor(($now - $info['since']) / DAY_IN_SECONDS);
            $oldest = max($oldest, $days);
            $lines[] = sprintf(
                '%s **%s** %s → %s · %s',
                $icons[$item['type']],
                Embed::escape($item['name']),
                Embed::code($item['from']),
                Embed::code($item['to']),
                sprintf(I18n::t('chờ %1$d ngày', 'waiting %1$d days'), $days)
            );
        }

        $embed = Embed::make(
            sprintf(I18n::t('📋 Tổng hợp tuần: %1$d bản cập nhật đang chờ', '📋 Weekly summary: %1$d updates pending'), count($lines)),
            isset($pending['core']) ? Embed::COLOR_WARNING : Embed::COLOR_INFO
        )
            ->description(Embed::truncate(implode("\n", $lines), Embed::LIMIT_DESCRIPTION), true)
            ->field(I18n::t('Lâu nhất', 'Oldest'), sprintf(I18n::t('%1$d ngày', '%1$d days'), $oldest))
            ->field(I18n::t('Cập nhật', 'Update'), Embed::link(I18n::t('Mở trang Cập nhật', 'Open Updates'), admin_url('update-core.php')), true, true);

        Notifier::send(Settings::updatesChannel(), 'update.digest', $embed);
    }

    /**
     * @return array<string,array{type:string,name:string,from:string,to:string}>
     */
    private static function pending(): array
    {
        $items = [];

        $plugins = self::raw('update_plugins');
        if (is_object($plugins) && !empty($plugins->response)) {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $installed = get_plugins();
            foreach ((array) $plugins->response as $file => $info) {
                $to = (string) ($info->new_version ?? '');
                if ('' === $to || !isset($installed[$file])) {
                    continue;
                }
                $items['plugin:' . $file] = ['type' => 'plugin', 'name' => $installed[$file]['Name'], 'from' => $installed[$file]['Version'], 'to' => $to];
            }
        }

        $themes = self::raw('update_themes');
        if (is_object($themes) && !empty($themes->response)) {
            foreach ((array) $themes->response as $stylesheet => $info) {
                $theme = wp_get_theme((string) $stylesheet);
                $to = (string) ($info['new_version'] ?? '');
                if ('' !== $to && $theme->exists()) {
                    $items['theme:' . $stylesheet] = ['type' => 'theme', 'name' => $theme->get('Name'), 'from' => (string) $theme->get('Version'), 'to' => $to];
                }
            }
        }

        $core = self::raw('update_core');
        if (is_object($core) && !empty($core->updates)) {
            foreach ((array) $core->updates as $update) {
                if ('upgrade' === ($update->response ?? '') && !empty($update->current)) {
                    $items['core'] = ['type' => 'core', 'name' => 'WordPress', 'from' => get_bloginfo('version'), 'to' => (string) $update->current];
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Đọc site transient KHÔNG qua filter `pre_site_transient_*`: AdminSettings::disableAllUpdate()
     * trả null cho update_plugins với mọi request không phải Super User (kể cả WP-Cron) để ẩn
     * thông báo cập nhật khỏi khách — nếu đọc qua get_site_transient() sẽ không bao giờ thấy gì.
     *
     * @return mixed
     */
    private static function raw(string $name)
    {
        return wp_using_ext_object_cache()
            ? wp_cache_get($name, 'site-transient')
            : get_site_option('_site_transient_' . $name);
    }

    /**
     * @param array<string,array{type:string,name:string,from:string,to:string}> $new
     */
    private static function notify(array $new, int $totalPending): void
    {
        $icons = ['core' => '⚙️', 'plugin' => '🔌', 'theme' => '🎨'];
        $lines = [];
        foreach (array_slice($new, 0, 20) as $item) {
            $lines[] = sprintf('%s **%s** %s → %s', $icons[$item['type']], Embed::escape($item['name']), Embed::code($item['from']), Embed::code($item['to']));
        }
        if (count($new) > 20) {
            $lines[] = sprintf(I18n::t('… và %1$d mục khác', '… and %1$d more'), count($new) - 20);
        }

        $hasCore = isset($new['core']);
        $embed = Embed::make(
            sprintf(I18n::t('🔔 Có %1$d bản cập nhật mới', '🔔 %1$d new updates available'), count($new)),
            $hasCore ? Embed::COLOR_WARNING : Embed::COLOR_INFO
        )
            ->description(implode("\n", $lines), true)
            ->field(I18n::t('Tổng đang chờ', 'Total pending'), (string) $totalPending)
            ->field(I18n::t('Cập nhật', 'Update'), Embed::link(I18n::t('Mở trang Cập nhật', 'Open Updates'), admin_url('update-core.php')), true, true)
            ->field(
                I18n::t('Lưu ý', 'Note'),
                I18n::block('Nên sao lưu trước khi cập nhật trên production.', 'Back up before updating production.'),
                false,
                true
            );

        Notifier::send(Settings::updatesChannel(), 'update.available', $embed);
    }
}
