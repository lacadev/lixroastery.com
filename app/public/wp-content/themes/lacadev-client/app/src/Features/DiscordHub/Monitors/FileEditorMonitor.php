<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\FileWatch\Baseline;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Sửa code qua Giao diện → Theme File Editor / Plugins → Plugin File Editor.
 *
 * Core lưu file trong AJAX `edit-theme-plugin-file` (handler priority 1) rồi wp_send_json → die.
 * Bắt ở priority 0 để chụp nội dung CŨ, so với nội dung MỚI ở shutdown → diff chính xác
 * từng dòng + biết chắc ai sửa (user đăng nhập) — điều không thể có khi sửa qua cPanel/FTP.
 * Core tự hoàn tác nếu code mới gây fatal (loopback check) → cũng báo lại lần "sửa hụt" này.
 */
final class FileEditorMonitor
{
    private const MAX_FILE_SIZE = 1024 * 1024;
    private const MAX_DIFF_LINES = 40;
    private const CONTEXT_LINES = 1;

    private ?string $abs = null;
    private ?string $relative = null;
    private ?string $before = null;

    public function register(): void
    {
        add_action('wp_ajax_edit-theme-plugin-file', [$this, 'captureBefore'], 0);
    }

    public function captureBefore(): void
    {
        $file = isset($_POST['file']) ? wp_unslash((string) $_POST['file']) : '';
        if ('' === $file || 0 !== validate_file($file)) {
            return;
        }

        if (!empty($_POST['theme'])) {
            $stylesheet = sanitize_text_field(wp_unslash((string) $_POST['theme']));
            $abs = get_theme_root($stylesheet) . '/' . $stylesheet . '/' . $file;
        } elseif (!empty($_POST['plugin'])) {
            $abs = WP_PLUGIN_DIR . '/' . $file;
        } else {
            return;
        }

        $abs = wp_normalize_path($abs);
        $root = wp_normalize_path(ABSPATH);
        if (!str_starts_with($abs, $root) || !is_file($abs) || filesize($abs) > self::MAX_FILE_SIZE) {
            return;
        }

        $this->abs = $abs;
        $this->relative = substr($abs, strlen($root));
        $this->before = (string) file_get_contents($abs);
        add_action('shutdown', [$this, 'report'], 1);
    }

    public function report(): void
    {
        if (null === $this->abs) {
            return;
        }

        try {
            clearstatcache(true, $this->abs);
            $after = (string) @file_get_contents($this->abs);
            $attempted = isset($_POST['newcontent']) ? str_replace("\r\n", "\n", wp_unslash((string) $_POST['newcontent'])) : null;

            if ($after === $this->before) {
                // Không đổi gì, hoặc WP đã tự hoàn tác vì code mới gây lỗi.
                if (null !== $attempted && $attempted !== str_replace("\r\n", "\n", $this->before)) {
                    $this->send(
                        I18n::t('↩️ Sửa code qua File Editor bị WordPress tự hoàn tác', '↩️ File Editor change was rolled back by WordPress'),
                        Embed::COLOR_WARNING,
                        $this->diff($this->before, $attempted),
                        I18n::block('Code mới gây lỗi nên WordPress đã khôi phục bản cũ — file KHÔNG thay đổi.', 'The new code caused an error so WordPress restored the old version — the file did NOT change.')
                    );
                }
                return;
            }

            $this->send(
                I18n::t('✏️ Sửa code qua Theme/Plugin File Editor', '✏️ Code edited via Theme/Plugin File Editor'),
                Embed::COLOR_DANGER,
                $this->diff($this->before, $after),
                I18n::block(
                    'Code trên production bị sửa trực tiếp — thay đổi này KHÔNG có trong Git và sẽ bị ghi đè (hoặc tồn tại âm thầm) ở lần deploy sau.',
                    'Production code was edited directly — this change is NOT in Git and will be overwritten (or silently persist) on the next deploy.'
                )
            );

            if (Settings::fileWatchEnabled() && Baseline::ensureSchema()) {
                Baseline::acceptFile($this->relative, 'editor:' . (wp_get_current_user()->user_login ?: 'unknown'));
            }
        } catch (\Throwable $e) {
            error_log('[laca-discord-hub] FileEditorMonitor: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }
    }

    /**
     * @param array{text:string,added:int,removed:int} $diff
     */
    private function send(string $title, int $color, array $diff, string $note): void
    {
        $embed = Embed::make($title, $color)
            ->description($note . "\n```diff\n" . str_replace('```', "'''", $diff['text']) . "\n```", true)
            ->codeField('File', (string) $this->relative, false)
            ->field(I18n::t('Người sửa', 'Edited by'), Context::actor())
            ->codeField('IP', Context::clientIp())
            ->field(I18n::t('Thay đổi', 'Changes'), sprintf('+%d / −%d', $diff['added'], $diff['removed']));

        Notifier::send(Settings::CHANNEL_SECURITY, 'file.editor', $embed);
    }

    /**
     * Diff kiểu unified rút gọn (dùng engine Text_Diff có sẵn trong WP core).
     *
     * @return array{text:string,added:int,removed:int}
     */
    private function diff(string $old, string $new): array
    {
        if (!class_exists('Text_Diff', false)) {
            require_once ABSPATH . WPINC . '/wp-diff.php';
        }

        $oldLines = explode("\n", str_replace("\r\n", "\n", $old));
        $newLines = explode("\n", str_replace("\r\n", "\n", $new));
        $engine = new \Text_Diff('auto', [$oldLines, $newLines]);

        $out = [];
        $added = $removed = 0;
        $lineNo = 1;
        foreach ($engine->getDiff() as $op) {
            $orig = (array) ($op->orig ?: []);
            $final = (array) ($op->final ?: []);

            if ($op instanceof \Text_Diff_Op_copy) {
                // Chỉ giữ vài dòng ngữ cảnh quanh chỗ sửa.
                if ($out && count($orig) > 0) {
                    foreach (array_slice($orig, 0, self::CONTEXT_LINES) as $line) {
                        $out[] = '  ' . $line;
                    }
                }
                $lineNo += count($orig);
                continue;
            }

            $out[] = sprintf('@@ %s %d @@', I18n::t('dòng', 'line'), $lineNo);
            foreach ($orig as $line) {
                $out[] = '- ' . $line;
                $removed++;
            }
            foreach ($final as $line) {
                $out[] = '+ ' . $line;
                $added++;
            }
            $lineNo += count($orig);
        }

        if (count($out) > self::MAX_DIFF_LINES) {
            $out = array_slice($out, 0, self::MAX_DIFF_LINES);
            $out[] = I18n::t('… (diff dài, đã cắt bớt)', '… (diff truncated)');
        }

        return ['text' => Embed::truncate(implode("\n", $out), 3000), 'added' => $added, 'removed' => $removed];
    }
}
