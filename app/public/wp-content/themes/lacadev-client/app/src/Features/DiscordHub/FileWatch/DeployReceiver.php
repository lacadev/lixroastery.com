<?php

namespace App\Features\DiscordHub\FileWatch;

use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Nhận thông báo deploy từ GitHub Actions (.github/workflows/deploy.yml) để giám sát
 * file biết thay đổi nào là "do deploy".
 *
 * Endpoint: admin-ajax.php?action=laca_discord_hub_deploy (không dùng REST vì theme có
 * tuỳ chọn chặn REST API với khách — `_disable_rest_api`).
 *
 * Xác thực HMAC-SHA256 bằng Deploy key (tạo trong Laca Admin → Discord Hub):
 *   X-Laca-Timestamp: unix time    X-Laca-Signature: hex(hmac_sha256(key, ts . "\n" . raw_body))
 * Lệch giờ > 5 phút → từ chối; chữ ký đã dùng → từ chối (chống replay).
 */
final class DeployReceiver
{
    public const ACTION = 'laca_discord_hub_deploy';
    /** Thời điểm deploy cuối ký đúng HMAC (checklist dùng để xác nhận GitHub Secrets). */
    public const LAST_DEPLOY_OPTION = 'laca_dh_last_deploy';
    private const MAX_SKEW = 300;
    private const MAX_BODY = 4 * MB_IN_BYTES;
    private const LIST_LIMIT = 15;

    public function register(): void
    {
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handle']);
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);
        $error = $this->verify($raw);
        if (null !== $error) {
            wp_send_json_error(['message' => $error], 403);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            wp_send_json_error(['message' => 'Invalid JSON'], 400);
        }

        $phase = (string) ($payload['phase'] ?? '');
        if ('start' === $phase) {
            update_option(Scanner::INFLIGHT_OPTION, time(), false);
            wp_send_json_success(['message' => 'Deploy start recorded']);
        }
        if ('finish' === $phase) {
            update_option(self::LAST_DEPLOY_OPTION, time(), false);
            wp_send_json_success($this->finish($payload));
        }

        wp_send_json_error(['message' => 'Unknown phase'], 400);
    }

    /**
     * @return string|null thông báo lỗi, null nếu hợp lệ.
     */
    private function verify(string $raw): ?string
    {
        $key = Settings::deployKey();
        if (null === $key) {
            return 'Deploy key not configured';
        }
        if (strlen($raw) > self::MAX_BODY) {
            return 'Payload too large';
        }

        $ts = isset($_SERVER['HTTP_X_LACA_TIMESTAMP']) ? (int) $_SERVER['HTTP_X_LACA_TIMESTAMP'] : 0;
        $sig = isset($_SERVER['HTTP_X_LACA_SIGNATURE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_X_LACA_SIGNATURE']))) : '';
        if (abs(time() - $ts) > self::MAX_SKEW) {
            return 'Timestamp out of range';
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $sig) || !hash_equals(hash_hmac('sha256', $ts . "\n" . $raw, $key), $sig)) {
            return 'Bad signature';
        }

        $replayKey = 'laca_dh_dep_' . substr($sig, 0, 32);
        if (get_transient($replayKey)) {
            return 'Replay';
        }
        set_transient($replayKey, 1, 2 * self::MAX_SKEW);
        return null;
    }

    /**
     * Chấp nhận file vừa deploy vào baseline nếu nội dung trên đĩa KHỚP hash GitHub đã build.
     * Không khớp → file trên server khác bản deploy (upload hỏng, hoặc bị sửa chen vào) → cảnh báo.
     */
    private function finish(array $payload): array
    {
        delete_option(Scanner::INFLIGHT_OPTION);

        if (!Settings::fileWatchEnabled() || !Baseline::ensureSchema()) {
            return ['matched' => 0, 'mismatched' => [], 'note' => 'File watch disabled'];
        }

        $commit = substr(preg_replace('/[^a-f0-9]/', '', strtolower((string) ($payload['commit'] ?? ''))), 0, 40);
        $author = Embed::truncate(sanitize_text_field((string) ($payload['author'] ?? '')), 60);
        $source = 'deploy:' . substr($commit, 0, 7) . ($author ? ' ' . $author : '');

        $files = is_array($payload['files'] ?? null) ? $payload['files'] : [];
        $matched = 0;
        $mismatched = [];

        foreach ($files as $path => $hash) {
            $path = ltrim(wp_normalize_path((string) $path), '/');
            if (!Scope::covers($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
                continue;
            }
            $abs = Scope::abs($path);
            clearstatcache(true, $abs);
            $actual = is_file($abs) ? (string) hash_file('sha256', $abs) : '';
            if ($actual === $hash) {
                Baseline::upsert($path, (int) filesize($abs), (int) filemtime($abs), $hash, $source);
                $matched++;
            } else {
                $mismatched[] = ['path' => $path, 'missing' => '' === $actual];
            }
        }

        // File đã bị gỡ khỏi git → deploy xoá trên server → bỏ khỏi baseline (không báo "bị xoá").
        $removed = 0;
        foreach ((array) ($payload['roots'] ?? []) as $root) {
            $root = trim(wp_normalize_path((string) $root), '/');
            if ('' === $root || !Scope::covers($root . '/x.php')) {
                continue;
            }
            global $wpdb;
            $paths = (array) $wpdb->get_col($wpdb->prepare('SELECT path FROM ' . Baseline::table() . ' WHERE path LIKE %s', $wpdb->esc_like($root . '/') . '%')); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            foreach ($paths as $path) {
                if (!isset($files[$path]) && !is_file(Scope::abs((string) $path))) {
                    Baseline::delete((string) $path);
                    $removed++;
                }
            }
        }

        if ($mismatched) {
            $this->notifyMismatch($mismatched, $commit, $author);
        }

        // Lượt quét định kỳ kế tiếp (≤ 15 phút) sẽ bắt thay đổi ngoài luồng xen vào trong lúc deploy.
        return ['matched' => $matched, 'removed' => $removed, 'mismatched' => array_column(array_slice($mismatched, 0, 50), 'path')];
    }

    private function notifyMismatch(array $files, string $commit, string $author): void
    {
        $lines = [];
        foreach (array_slice($files, 0, self::LIST_LIMIT) as $file) {
            $lines[] = Embed::code($file['path']) . ($file['missing'] ? ' — ' . I18n::t('không có trên server', 'missing on server') : '');
        }
        if (count($files) > self::LIST_LIMIT) {
            $lines[] = sprintf(I18n::t('… và %1$d file khác', '… and %1$d more'), count($files) - self::LIST_LIMIT);
        }

        $embed = Embed::make(
            sprintf(I18n::t('⚠️ %1$d file trên server KHÁC bản vừa deploy', '⚠️ %1$d files on server DIFFER from the deployed build'), count($files)),
            Embed::COLOR_WARNING
        )
            ->description(I18n::block(
                'Nội dung trên hosting không khớp với bản GitHub vừa build. Thường do upload FTP lỗi giữa chừng (chạy lại deploy để sửa); nếu lặp lại → có thể file bị sửa xen vào trong lúc deploy.',
                'Hosting content does not match what GitHub just built. Usually an interrupted FTP upload (re-run the deploy); if it repeats, files may have been tampered with during the deploy.'
            ), true)
            ->field('Commit', Embed::code(substr($commit, 0, 7)) . ($author ? ' · ' . Embed::escape($author) : ''), true, true)
            ->field(I18n::t('File', 'Files'), Embed::truncate(implode("\n", $lines), Embed::LIMIT_FIELD_VALUE), false, true);

        Notifier::send(Settings::CHANNEL_SECURITY, 'deploy.mismatch', $embed);
    }
}
