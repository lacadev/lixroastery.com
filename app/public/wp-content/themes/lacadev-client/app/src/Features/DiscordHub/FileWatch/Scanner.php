<?php

namespace App\Features\DiscordHub\FileWatch;

use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Quét định kỳ (WP-Cron 15 phút) phát hiện file thêm/sửa/xoá NGOÀI luồng hợp lệ.
 *
 * Luồng hợp lệ tự cập nhật baseline ngay khi xảy ra nên không bị báo:
 *  - Git deploy (GitHub Actions gửi manifest — DeployReceiver),
 *  - Theme/Plugin File Editor (FileEditorMonitor — đã báo riêng kèm diff),
 *  - cài/cập nhật/xoá plugin, theme, core trong wp-admin (UpdateMonitor).
 * Còn lại (sửa qua cPanel File Manager, FTP, SSH, hoặc mã độc) → cảnh báo đỏ.
 *
 * Tối ưu cho shared hosting: so size+mtime trước, chỉ băm SHA-256 file có dấu hiệu đổi.
 * Giới hạn đã biết: file bị sửa nhưng giữ nguyên CẢ size lẫn mtime (touch -r) sẽ lọt.
 */
final class Scanner
{
    public const CRON_HOOK = 'laca_discord_hub_fim_scan';
    public const CRON_SCHEDULE = 'laca_dh_15min';
    public const STATE_OPTION = 'laca_dh_fim_state';
    public const INFLIGHT_OPTION = 'laca_dh_deploy_inflight';
    private const INFLIGHT_TTL = 45 * MINUTE_IN_SECONDS;
    private const LIST_LIMIT = 15;

    public function register(): void
    {
        add_filter('cron_schedules', static function ($schedules) {
            $schedules[self::CRON_SCHEDULE] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Laca Discord Hub — 15 phút'];
            return $schedules;
        });
        add_action(self::CRON_HOOK, [self::class, 'run']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function isDeployInFlight(): bool
    {
        $started = (int) get_option(self::INFLIGHT_OPTION, 0);
        return $started > 0 && time() - $started < self::INFLIGHT_TTL;
    }

    /**
     * @return array{status:string,files:int,added:int,modified:int,deleted:int,seconds:float}
     */
    public static function run(): array
    {
        $result = ['status' => 'skipped', 'files' => 0, 'added' => 0, 'modified' => 0, 'deleted' => 0, 'seconds' => 0.0];

        if (!Settings::fileWatchEnabled() || !Baseline::ensureSchema()) {
            return $result;
        }
        // Đang deploy: file đổi hàng loạt, chờ manifest ghi nhận xong rồi mới quét — tránh báo nhầm.
        if (self::isDeployInFlight()) {
            $result['status'] = 'deploy-in-progress';
            return $result;
        }

        global $wpdb;
        $lock = 'laca_dh_fim_' . substr(md5(DB_NAME . $wpdb->prefix), 0, 20);
        if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) {
            $result['status'] = 'locked';
            return $result;
        }

        $start = microtime(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // lần đầu phải băm toàn bộ (~15k file)
        }
        wp_raise_memory_limit('laca_discord_hub_fim');

        try {
            $baseline = Baseline::compactMap();
            $firstRun = [] === $baseline;
            $added = $modified = $dangerous = [];

            foreach (Scope::walk() as $rel => $abs) {
                $size = (int) @filesize($abs);
                $mtime = (int) @filemtime($abs);
                $quick = $baseline[$rel] ?? null;
                unset($baseline[$rel]);

                if ($quick === $size . ':' . $mtime) {
                    continue;
                }

                $known = null !== $quick ? Baseline::get($rel) : null;
                $hash = (string) @hash_file('sha256', $abs);
                if (null === $known) {
                    Baseline::upsert($rel, $size, $mtime, $hash, $firstRun ? 'baseline' : 'scan');
                    if (!$firstRun) {
                        $added[] = ['path' => $rel, 'size' => $size, 'mtime' => $mtime];
                        if (str_starts_with($rel, Scope::UPLOADS_DIR . '/')) {
                            $dangerous[] = ['path' => $rel, 'size' => $size, 'mtime' => $mtime];
                        }
                    }
                    continue;
                }

                Baseline::upsert($rel, $size, $mtime, $hash, $hash === $known['hash'] ? 'scan' : 'scan:changed');
                if ($hash !== $known['hash']) {
                    $modified[] = ['path' => $rel, 'size' => $size, 'mtime' => $mtime, 'old_size' => $known['size']];
                }
            }

            // Còn sót trong $baseline = file đã biến mất.
            $deleted = [];
            foreach (array_keys($baseline) as $rel) {
                Baseline::delete($rel);
                $deleted[] = ['path' => $rel];
            }

            $result = [
                'status' => $firstRun ? 'baseline' : 'ok',
                'files' => Baseline::count(),
                'added' => count($added),
                'modified' => count($modified),
                'deleted' => count($deleted),
                'seconds' => round(microtime(true) - $start, 2),
            ];
            update_option(self::STATE_OPTION, $result + ['at' => time()], false);

            if ($firstRun) {
                self::notifyBaseline($result);
            } else {
                if ($dangerous) {
                    self::notifyDangerousUploads($dangerous);
                }
                $others = array_values(array_filter($added, static fn ($f) => !str_starts_with($f['path'], Scope::UPLOADS_DIR . '/')));
                if ($others || $modified || $deleted) {
                    self::notifyChanges($others, $modified, $deleted);
                }
            }
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }

        return $result;
    }

    // ── Thông báo ─────────────────────────────────────────────────────────────

    private static function notifyBaseline(array $result): void
    {
        $embed = Embed::make(I18n::t('🛡️ Bắt đầu giám sát file', '🛡️ File monitoring started'), Embed::COLOR_INFO)
            ->description(I18n::block(
                sprintf('Đã ghi nhận baseline **%d** file code. Từ giờ mọi thay đổi không đến từ deploy, File Editor hay cập nhật trong WP sẽ được báo tại đây.', $result['files']),
                sprintf('Baseline recorded for **%d** code files. Any change not coming from a deploy, the File Editor or a WP update will be reported here.', $result['files'])
            ), true)
            ->field(I18n::t('Thời gian quét', 'Scan time'), $result['seconds'] . 's');
        Notifier::send(Settings::CHANNEL_SECURITY, 'files.baseline', $embed);
    }

    private static function notifyDangerousUploads(array $files): void
    {
        $embed = Embed::make(I18n::t('🚨 File thực thi xuất hiện trong uploads', '🚨 Executable file found in uploads'), Embed::COLOR_DANGER)
            ->description(I18n::block(
                'Thư mục uploads chỉ chứa ảnh/tài liệu — file PHP/script ở đây gần như chắc chắn là **webshell/mã độc**. Xoá ngay và kiểm tra log hosting.',
                'The uploads folder should only contain media — a PHP/script file here is almost certainly a **webshell/malware**. Delete it and check hosting logs.'
            ), true)
            ->field(I18n::t('File', 'Files'), self::formatList($files), false, true);
        Notifier::send(Settings::CHANNEL_SECURITY, 'files.dangerous_upload', $embed);
    }

    private static function notifyChanges(array $added, array $modified, array $deleted): void
    {
        $total = count($added) + count($modified) + count($deleted);
        $embed = Embed::make(
            sprintf(I18n::t('🚨 %1$d file bị thay đổi ngoài luồng', '🚨 %1$d files changed outside normal workflow'), $total),
            Embed::COLOR_DANGER
        )->description(I18n::block(
            'Thay đổi KHÔNG đến từ Git deploy, File Editor hay cập nhật plugin/theme trong WP — tức là sửa trực tiếp trên hosting (cPanel File Manager, FTP, SSH) hoặc **mã độc**. WordPress không biết ai sửa: xem log hosting (cPanel → Raw Access / FTP) theo thời điểm bên dưới.',
            'These changes did NOT come from a Git deploy, the File Editor or a WP plugin/theme update — i.e. direct edits on the hosting (cPanel File Manager, FTP, SSH) or **malware**. WordPress cannot know who did it: check hosting logs (cPanel → Raw Access / FTP) around the times below.'
        ), true);

        if ($added) {
            $embed->field(sprintf(I18n::t('➕ Thêm mới (%1$d)', '➕ Added (%1$d)'), count($added)), self::formatList($added), false, true);
        }
        if ($modified) {
            $embed->field(sprintf(I18n::t('✏️ Bị sửa (%1$d)', '✏️ Modified (%1$d)'), count($modified)), self::formatList($modified), false, true);
        }
        if ($deleted) {
            $embed->field(sprintf(I18n::t('🗑️ Bị xoá (%1$d)', '🗑️ Deleted (%1$d)'), count($deleted)), self::formatList($deleted), false, true);
        }

        Notifier::send(Settings::CHANNEL_SECURITY, 'files.changed', $embed);
    }

    /**
     * @param array<int,array{path:string,size?:int,mtime?:int}> $files
     */
    private static function formatList(array $files): string
    {
        $lines = [];
        foreach (array_slice($files, 0, self::LIST_LIMIT) as $file) {
            $meta = [];
            if (isset($file['size'])) {
                $meta[] = size_format($file['size']);
            }
            if (!empty($file['mtime'])) {
                $meta[] = wp_date('d/m H:i', $file['mtime']);
            }
            $lines[] = Embed::code($file['path']) . ($meta ? ' · ' . implode(' · ', $meta) : '');
        }
        if (count($files) > self::LIST_LIMIT) {
            $lines[] = sprintf(I18n::t('… và %1$d file khác', '… and %1$d more'), count($files) - self::LIST_LIMIT);
        }
        return Embed::truncate(implode("\n", $lines), Embed::LIMIT_FIELD_VALUE);
    }
}
