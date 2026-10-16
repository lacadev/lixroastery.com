<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\FileWatch\Baseline;
use App\Features\DiscordHub\FileWatch\Scope;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Cài/cập nhật/xoá plugin, theme, core TRONG wp-admin (kể cả auto-update qua WP-Cron).
 * Báo về kênh Audit kèm người thực hiện, đồng thời chấp nhận thư mục vừa đổi vào
 * baseline giám sát file — để lượt quét sau không báo nhầm là "sửa ngoài luồng".
 */
final class UpdateMonitor
{
    public function register(): void
    {
        add_action('upgrader_process_complete', [$this, 'onUpgrade'], 20, 2);
        add_action('deleted_plugin', [$this, 'onDeletedPlugin'], 10, 2);
        add_action('deleted_theme', [$this, 'onDeletedTheme'], 10, 2);
    }

    /**
     * @param \WP_Upgrader $upgrader
     * @param array        $extra
     */
    public function onUpgrade($upgrader, $extra): void
    {
        $type = (string) ($extra['type'] ?? '');
        $action = (string) ($extra['action'] ?? '');
        $actor = wp_get_current_user()->user_login ?: (wp_doing_cron() ? 'wp-cron' : 'system');
        $source = 'update:' . $actor;
        $items = [];

        if ('plugin' === $type) {
            $files = (array) ($extra['plugins'] ?? (isset($extra['plugin']) ? [$extra['plugin']] : []));
            if ('install' === $action && is_object($upgrader) && method_exists($upgrader, 'plugin_info') && $upgrader->plugin_info()) {
                $files = [$upgrader->plugin_info()];
            }
            foreach ($files as $file) {
                $dir = dirname((string) $file);
                $this->accept('.' === $dir ? 'wp-content/plugins/' . $file : 'wp-content/plugins/' . $dir, '.' === $dir, $source);
                $items[] = $this->pluginLabel((string) $file);
            }
        } elseif ('theme' === $type) {
            $themes = (array) ($extra['themes'] ?? (isset($extra['theme']) ? [$extra['theme']] : []));
            if ('install' === $action && is_object($upgrader) && method_exists($upgrader, 'theme_info') && $upgrader->theme_info()) {
                $themes = [$upgrader->theme_info()->get_stylesheet()];
            }
            foreach ($themes as $stylesheet) {
                $this->accept('wp-content/themes/' . $stylesheet, false, $source);
                $theme = wp_get_theme((string) $stylesheet);
                $items[] = $theme->exists() ? $theme->get('Name') . ' ' . $theme->get('Version') : (string) $stylesheet;
            }
        } elseif ('core' === $type) {
            // Core chỉ đụng file gốc trong phạm vi giám sát (wp-admin/wp-includes không giám sát).
            if (Settings::fileWatchEnabled() && Baseline::ensureSchema()) {
                foreach (Scope::walk() as $rel => $abs) {
                    if (!str_contains($rel, '/')) {
                        Baseline::acceptFile($rel, $source);
                    }
                }
            }
            $items[] = 'WordPress ' . get_bloginfo('version');
        } else {
            return; // translation…: không ảnh hưởng file code
        }

        if (!$items) {
            return;
        }

        $labels = [
            'plugin' => [I18n::t('plugin', 'plugin'), '🔌'],
            'theme' => [I18n::t('theme', 'theme'), '🎨'],
            'core' => ['core', '⚙️'],
        ];
        [$label, $icon] = $labels[$type];
        $verb = 'install' === $action ? I18n::t('Cài mới', 'Installed') : I18n::t('Cập nhật', 'Updated');

        $embed = Embed::make(sprintf('%s %s %s', $icon, $verb, $label), Embed::COLOR_INFO)
            ->description(implode("\n", array_map(static fn ($i) => '• ' . Embed::escape($i), array_slice($items, 0, 15))), true)
            ->field(I18n::t('Thực hiện bởi', 'Performed by'), Context::actor())
            ->field(I18n::t('Nguồn', 'Origin'), Context::origin());

        Notifier::send(Settings::CHANNEL_AUDIT, 'update.' . $type, $embed);
    }

    /**
     * @param string $pluginFile
     * @param bool   $deleted
     */
    public function onDeletedPlugin($pluginFile, $deleted): void
    {
        if (!$deleted) {
            return;
        }
        $dir = dirname((string) $pluginFile);
        $this->accept('.' === $dir ? 'wp-content/plugins/' . $pluginFile : 'wp-content/plugins/' . $dir, '.' === $dir, 'delete:' . (wp_get_current_user()->user_login ?: 'system'));
        $this->notifyDeleted('plugin', (string) $pluginFile);
    }

    /**
     * @param string $stylesheet
     * @param bool   $deleted
     */
    public function onDeletedTheme($stylesheet, $deleted): void
    {
        if (!$deleted) {
            return;
        }
        $this->accept('wp-content/themes/' . $stylesheet, false, 'delete:' . (wp_get_current_user()->user_login ?: 'system'));
        $this->notifyDeleted('theme', (string) $stylesheet);
    }

    private function accept(string $relative, bool $singleFile, string $source): void
    {
        if (!Settings::fileWatchEnabled() || !Baseline::ensureSchema()) {
            return;
        }
        $singleFile ? Baseline::acceptFile($relative, $source) : Baseline::acceptTree($relative, $source);
    }

    private function notifyDeleted(string $type, string $name): void
    {
        $embed = Embed::make(
            'plugin' === $type ? I18n::t('🗑️ Xoá plugin', '🗑️ Plugin deleted') : I18n::t('🗑️ Xoá theme', '🗑️ Theme deleted'),
            Embed::COLOR_WARNING
        )
            ->codeField(I18n::t('Tên', 'Name'), $name)
            ->field(I18n::t('Thực hiện bởi', 'Performed by'), Context::actor())
            ->field(I18n::t('Nguồn', 'Origin'), Context::origin());
        Notifier::send(Settings::CHANNEL_AUDIT, 'delete.' . $type, $embed);
    }

    private function pluginLabel(string $file): string
    {
        $abs = WP_PLUGIN_DIR . '/' . $file;
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (is_file($abs)) {
            $data = get_plugin_data($abs, false, false);
            if (!empty($data['Name'])) {
                return $data['Name'] . ' ' . $data['Version'];
            }
        }
        return $file;
    }
}
