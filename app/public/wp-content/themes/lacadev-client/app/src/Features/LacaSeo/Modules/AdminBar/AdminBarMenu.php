<?php

namespace App\Features\LacaSeo\Modules\AdminBar;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Modules\RoleManager\CapabilityManager;
use WP_Admin_Bar;

defined('ABSPATH') || exit;

/**
 * Admin Bar top menu for quick SEO diagnostics and shortcuts.
 */
class AdminBarMenu
{
    public const MENU_ID = 'laca-seo';

    public function register(): void
    {
        add_action('admin_bar_menu', [$this, 'addMenu'], 100);
    }

    public function addMenu(WP_Admin_Bar $wpAdminBar): void
    {
        if (!CapabilityManager::hasCap('admin_bar')) {
            return;
        }

        // Root Node
        $wpAdminBar->add_node([
            'id'    => self::MENU_ID,
            'title' => '<span class="ab-icon dashicons dashicons-chart-line"></span><span class="ab-label">' . esc_html__('Laca SEO', 'lacadev') . '</span>',
            'href'  => admin_url('admin.php?page=laca-seo'),
            'meta'  => ['title' => __('Laca SEO Dashboard', 'lacadev')],
        ]);

        // Subnodes
        $wpAdminBar->add_node([
            'id'     => self::MENU_ID . '-dashboard',
            'parent' => self::MENU_ID,
            'title'  => esc_html__('Bảng điều khiển', 'lacadev'),
            'href'   => admin_url('admin.php?page=laca-seo'),
        ]);

        $wpAdminBar->add_node([
            'id'     => self::MENU_ID . '-titles',
            'parent' => self::MENU_ID,
            'title'  => esc_html__('Tiêu đề & Meta', 'lacadev'),
            'href'   => admin_url('admin.php?page=laca-seo&view=titles'),
        ]);

        $wpAdminBar->add_node([
            'id'     => self::MENU_ID . '-analyzer',
            'parent' => self::MENU_ID,
            'title'  => esc_html__('Phân tích SEO', 'lacadev'),
            'href'   => admin_url('admin.php?page=laca-seo&view=seo-analysis'),
        ]);

        // External quick tools
        $currentUrl = !is_admin() ? (home_url($_SERVER['REQUEST_URI'] ?? '/')) : home_url('/');
        $encodedUrl = rawurlencode($currentUrl);

        $wpAdminBar->add_node([
            'id'     => self::MENU_ID . '-psi',
            'parent' => self::MENU_ID,
            'title'  => esc_html__('Google PageSpeed Insights', 'lacadev'),
            'href'   => 'https://pagespeed.web.dev/report?url=' . $encodedUrl,
            'meta'   => ['target' => '_blank'],
        ]);

        $wpAdminBar->add_node([
            'id'     => self::MENU_ID . '-rich-results',
            'parent' => self::MENU_ID,
            'title'  => esc_html__('Google Rich Results Test', 'lacadev'),
            'href'   => 'https://search.google.com/test/rich-results?url=' . $encodedUrl,
            'meta'   => ['target' => '_blank'],
        ]);

        $wpAdminBar->add_node([
            'id'     => self::MENU_ID . '-gsc-inspect',
            'parent' => self::MENU_ID,
            'title'  => esc_html__('Kiểm tra Index trên Google', 'lacadev'),
            'href'   => 'https://search.google.com/search-console/inspect?resource_id=' . rawurlencode(home_url('/')) . '&id=' . $encodedUrl,
            'meta'   => ['target' => '_blank'],
        ]);
    }
}
