<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Thao tác hệ thống hay gây lỗi site: bật/tắt plugin, đổi theme, sửa menu, Customizer.
 * (Widget, permalink, cài đặt plugin… đi qua option → SettingsMonitor.)
 */
final class SystemMonitor
{
    /** @var array<int,bool> menu đã báo trong request (Lưu menu bắn nhiều lần) */
    private array $menus = [];

    public function register(): void
    {
        add_action('activated_plugin', [$this, 'onActivated'], 10, 2);
        add_action('deactivated_plugin', [$this, 'onDeactivated'], 10, 2);
        add_action('switch_theme', [$this, 'onSwitchTheme'], 10, 3);
        add_action('wp_update_nav_menu', [$this, 'onMenu'], 10, 1);
        add_action('customize_save_after', [$this, 'onCustomize'], 10, 1);
    }

    /**
     * @param string $plugin
     * @param bool   $network
     */
    public function onActivated($plugin, $network = false): void
    {
        $this->send('plugin.activate', I18n::t('🟢 Bật plugin', '🟢 Plugin activated'), Embed::COLOR_WARNING, $this->pluginName((string) $plugin));
    }

    /**
     * @param string $plugin
     * @param bool   $network
     */
    public function onDeactivated($plugin, $network = false): void
    {
        $this->send('plugin.deactivate', I18n::t('🔴 Tắt plugin', '🔴 Plugin deactivated'), Embed::COLOR_WARNING, $this->pluginName((string) $plugin));
    }

    /**
     * @param string    $newName
     * @param \WP_Theme $newTheme
     * @param \WP_Theme $oldTheme
     */
    public function onSwitchTheme($newName, $newTheme = null, $oldTheme = null): void
    {
        $old = $oldTheme instanceof \WP_Theme ? $oldTheme->get('Name') : '—';
        $this->send('theme.switch', I18n::t('🎨 Đổi theme', '🎨 Theme switched'), Embed::COLOR_DANGER, $old . ' → ' . (string) $newName);
    }

    /**
     * @param int $menuId
     */
    public function onMenu($menuId): void
    {
        $menuId = (int) $menuId;
        if (isset($this->menus[$menuId]) || !is_user_logged_in()) {
            return;
        }
        $this->menus[$menuId] = true;
        $menu = wp_get_nav_menu_object($menuId);
        $this->send('menu.update', I18n::t('🧭 Sửa menu', '🧭 Menu updated'), Embed::COLOR_INFO, $menu ? $menu->name : '#' . $menuId);
    }

    /**
     * @param \WP_Customize_Manager $manager
     */
    public function onCustomize($manager): void
    {
        $ids = is_object($manager) && method_exists($manager, 'unsanitized_post_values') ? array_keys((array) $manager->unsanitized_post_values()) : [];
        $list = array_slice($ids, 0, 15);
        $detail = $list ? implode(', ', $list) . (count($ids) > 15 ? ' …' : '') : I18n::t('(không rõ)', '(unknown)');
        $this->send('customizer.save', I18n::t('🖌️ Lưu Customizer', '🖌️ Customizer saved'), Embed::COLOR_INFO, $detail);
    }

    private function send(string $event, string $title, int $color, string $detail): void
    {
        $embed = Embed::make($title, $color)
            ->description(Embed::code($detail), true)
            ->field(I18n::t('Thực hiện bởi', 'Performed by'), Context::actor())
            ->field(I18n::t('Nguồn', 'Origin'), Context::origin())
            ->codeField('IP', Context::clientIp());
        Notifier::send(Settings::CHANNEL_AUDIT, $event, $embed);
    }

    private function pluginName(string $file): string
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $abs = WP_PLUGIN_DIR . '/' . $file;
        if (is_file($abs)) {
            $data = get_plugin_data($abs, false, false);
            if (!empty($data['Name'])) {
                return $data['Name'] . ' ' . $data['Version'];
            }
        }
        return $file;
    }
}
