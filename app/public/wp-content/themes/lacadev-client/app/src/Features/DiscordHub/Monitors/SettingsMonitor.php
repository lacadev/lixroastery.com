<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Người dùng bấm Lưu cài đặt ở bất kỳ đâu trong wp-admin: Settings của WP, Laca Admin
 * (Carbon Fields), WooCommerce, plugin khác, REST /wp/v2/settings.
 *
 * Chỉ ghi khi có người quản trị thao tác (request POST, đã đăng nhập, có quyền quản trị) —
 * WP và plugin tự ghi option ngầm hàng nghìn lần/ngày (cache, cron, transient…) sẽ bị bỏ qua.
 * Gom mọi option đổi trong 1 request thành 1 tin; so giá trị ĐẦU và CUỐI của request để
 * loại kiểu lưu "xoá rồi thêm lại y nguyên" (Carbon Fields làm vậy với field complex).
 */
final class SettingsMonitor
{
    /** Option ghi tự động / nội bộ — không phải người dùng đổi cài đặt. */
    private const IGNORE_PATTERN = '/^(?:_site_transient_|_transient_|cron$|rewrite_rules$|recently_edited$|recently_activated$|uninstall_plugins$|can_compress_scripts$|db_upgraded$|fresh_site$|active_plugins$|theme_mods_|auto_core_update_|auto_updater\.lock$|core_updater\.lock$|user_count$|action_scheduler|as_|wp_force_deactivated_plugins$|_wp_suggested_policy|finished_|laca_discord_(?!hub$|hub_webhooks$)|laca_dh_|_laca_tracker_|_transient|.*_children$|.*_lock$|.*_last_run$|.*_timestamp$|.*_version$|.*_db_version$|.*_rewrite_rules_flushed$)/i';

    /** Tên option/khoá con chứa bí mật → chỉ báo "đã đổi", không gửi giá trị. */
    private const SECRET_PATTERN = '/(key|secret|token|passw|pwd|salt|auth|private|license|webhook|credential|client_id|login_slug|login_url|admin_slug)/i';

    private const LIST_LIMIT = 15;

    /** @var array<string,array{old:mixed,new:mixed,old_exists:bool,new_exists:bool}> */
    private array $changes = [];

    public function register(): void
    {
        add_action('updated_option', [$this, 'onUpdated'], 10, 3);
        add_action('added_option', [$this, 'onAdded'], 10, 2);
        add_action('delete_option', [$this, 'beforeDelete'], 10, 1);
        add_action('deleted_option', [$this, 'onDeleted'], 10, 1);
        add_action('shutdown', [$this, 'report'], 4);
    }

    /**
     * @param string $option
     * @param mixed  $old
     * @param mixed  $new
     */
    public function onUpdated($option, $old, $new): void
    {
        if ($this->tracking((string) $option)) {
            $this->remember((string) $option, $old, true, $new, true);
        }
    }

    /**
     * @param string $option
     * @param mixed  $value
     */
    public function onAdded($option, $value): void
    {
        if ($this->tracking((string) $option)) {
            $this->remember((string) $option, null, false, $value, true);
        }
    }

    /**
     * @param string $option
     */
    public function beforeDelete($option): void
    {
        if ($this->tracking((string) $option) && !isset($this->changes[$option])) {
            $this->changes[$option] = ['old' => get_option($option), 'new' => null, 'old_exists' => true, 'new_exists' => true];
        }
    }

    /**
     * @param string $option
     */
    public function onDeleted($option): void
    {
        if (isset($this->changes[$option])) {
            $this->changes[$option]['new'] = null;
            $this->changes[$option]['new_exists'] = false;
        }
    }

    /**
     * @param mixed $old
     * @param mixed $new
     */
    private function remember(string $option, $old, bool $oldExists, $new, bool $newExists): void
    {
        if (!isset($this->changes[$option])) {
            $this->changes[$option] = ['old' => $old, 'new' => $new, 'old_exists' => $oldExists, 'new_exists' => $newExists];
            return;
        }
        $this->changes[$option]['new'] = $new;
        $this->changes[$option]['new_exists'] = $newExists;
    }

    private function tracking(string $option): bool
    {
        if (preg_match(self::IGNORE_PATTERN, $option)) {
            return false;
        }
        // Không cache kết quả: option có thể được ghi trước khi WP xác định user của request.
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
        return in_array($method, ['POST', 'PUT', 'PATCH'], true)
            && !wp_doing_cron()
            && !(defined('WP_CLI') && WP_CLI)
            && did_action('set_current_user')
            && is_user_logged_in()
            && (current_user_can('manage_options') || current_user_can('manage_woocommerce') || current_user_can('edit_theme_options'));
    }

    public function report(): void
    {
        $changes = $this->changes;
        $this->changes = [];
        try {
            $lines = [];
            foreach ($changes as $option => $change) {
                if ($change['old_exists'] === $change['new_exists'] && $this->same($change['old'], $change['new'])) {
                    continue;
                }
                $lines[] = $this->describe($option, $change);
            }
            if (!$lines) {
                return;
            }

            $total = count($lines);
            if ($total > self::LIST_LIMIT) {
                $lines = array_slice($lines, 0, self::LIST_LIMIT);
                $lines[] = sprintf(I18n::t('… và %1$d cài đặt khác', '… and %1$d more settings'), $total - self::LIST_LIMIT);
            }

            $embed = Embed::make(sprintf(I18n::t('⚙️ Lưu cài đặt (%1$d thay đổi)', '⚙️ Settings saved (%1$d changes)'), $total), Embed::COLOR_WARNING)
                ->description(implode("\n", $lines), true)
                ->field(I18n::t('Trang', 'Screen'), $this->screen(), true, true)
                ->field(I18n::t('Thực hiện bởi', 'Performed by'), Context::actor())
                ->codeField('IP', Context::clientIp());

            Notifier::send(Settings::CHANNEL_AUDIT, 'settings.saved', $embed);
        } catch (\Throwable $e) {
            error_log('[laca-discord-hub] SettingsMonitor: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }
    }

    /**
     * @param mixed $a
     * @param mixed $b
     */
    private function same($a, $b): bool
    {
        return maybe_serialize($a) === maybe_serialize($b) || (is_scalar($a) && is_scalar($b) && (string) $a === (string) $b);
    }

    private function describe(string $option, array $change): string
    {
        $name = Embed::code($this->label($option));

        if (!$change['new_exists']) {
            return sprintf('• %s: %s', $name, I18n::t('đã xoá', 'deleted'));
        }
        if (preg_match(self::SECRET_PATTERN, $option)) {
            return sprintf('• %s: 🔒 %s', $name, I18n::t('đã đổi (giá trị bí mật, không hiển thị)', 'changed (secret value hidden)'));
        }
        if (is_array($change['old']) && is_array($change['new'])) {
            return sprintf('• %s: %s', $name, $this->arrayDiff($change['old'], $change['new']));
        }
        if (!is_scalar($change['new']) && null !== $change['new']) {
            return sprintf('• %s: %s', $name, I18n::t('đã đổi', 'changed'));
        }
        return sprintf('• %s: %s → %s', $name, $this->value($change['old_exists'] ? $change['old'] : null), $this->value($change['new']));
    }

    /**
     * Mảng (WooCommerce, plugin…): chỉ liệt kê khoá con bị đổi.
     */
    private function arrayDiff(array $old, array $new): string
    {
        $parts = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $a = $old[$key] ?? null;
            $b = $new[$key] ?? null;
            if ($this->same($a, $b)) {
                continue;
            }
            if (preg_match(self::SECRET_PATTERN, (string) $key)) {
                $parts[] = Embed::escape((string) $key) . ': 🔒';
            } elseif ((is_scalar($a) || null === $a) && (is_scalar($b) || null === $b)) {
                $parts[] = Embed::escape((string) $key) . ': ' . $this->value($a) . ' → ' . $this->value($b);
            } else {
                $parts[] = Embed::escape((string) $key);
            }
            if (count($parts) >= 5) {
                $parts[] = '…';
                break;
            }
        }
        return $parts ? implode('; ', $parts) : I18n::t('đã đổi', 'changed');
    }

    /**
     * @param mixed $value
     */
    private function value($value): string
    {
        if (null === $value || '' === $value || false === $value) {
            return '—';
        }
        if (true === $value) {
            return 'true';
        }
        return Embed::code(Embed::truncate((string) $value, 60));
    }

    /**
     * Carbon Fields lưu theme option dạng "_ten_field" / "_complex|field|0|0|value" → hiển thị gọn hơn.
     */
    private function label(string $option): string
    {
        $label = ltrim($option, '_');
        if (str_contains($label, '|')) {
            $parts = array_values(array_filter(explode('|', $label), static fn ($p) => '' !== $p && !ctype_digit($p) && 'value' !== $p));
            $label = implode(' › ', $parts);
        }
        return Embed::truncate($label, 80);
    }

    /**
     * Màn hình admin nơi bấm Lưu (lấy từ Referer vì form thường POST tới options.php / admin-post.php).
     */
    private function screen(): string
    {
        $referer = (string) wp_get_referer();
        $script = isset($_SERVER['SCRIPT_NAME']) ? basename(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME']))) : '';
        if ('' === $referer) {
            return Embed::code($script ?: Context::origin());
        }
        $path = basename((string) wp_parse_url($referer, PHP_URL_PATH));
        parse_str((string) wp_parse_url($referer, PHP_URL_QUERY), $query);
        $label = $path . (isset($query['page']) ? ' › ' . sanitize_key((string) $query['page']) : '') . (isset($query['tab']) ? ' › ' . sanitize_key((string) $query['tab']) : '');
        return Embed::link($label, $referer);
    }
}
