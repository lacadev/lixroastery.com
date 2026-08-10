<?php

namespace App\Settings\LacaTools\Management;

/**
 * AdminUxService
 * Handles admin UX: Help menu, Merchant simplification, media submenu.
 * Extracted from ManagementExperience (lines 1592–1691).
 */
class AdminUxService
{
    public function register(): void
    {
        $this->addClientHelpMenu();
        add_action('admin_menu', [$this, 'registerUnattachedMediaMenu']);
        add_action('admin_notices', [$this, 'renderUnattachedMediaNotice']);
        $this->simplifyMerchantAdmin();
    }

    /**
     * Registers a submenu under 'Media' for Unattached media.
     */
    public function registerUnattachedMediaMenu(): void
    {
        add_submenu_page(
            'upload.php',
            'Media Không Dùng',
            'Media Không Dùng',
            'manage_options',
            'upload.php?detached=1&mode=list'
        );
    }

    /**
     * Hiển thị đoạn giải thích ngay trên trang "Media Không Dùng"
     * (thực chất là thư viện Media lọc theo trạng thái "chưa gắn vào bài viết nào").
     */
    public function renderUnattachedMediaNotice(): void
    {
        if (empty($_GET['detached']) || !function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'upload') {
            return;
        }

        echo '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">'
            . '<p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Media Không Dùng</p>'
            . '<p style="margin:0;font-size:13px;color:#374151">Danh sách này liệt kê các file ảnh/video đã tải lên nhưng chưa được gắn vào bài viết, trang hoặc sản phẩm nào. Bạn có thể xoá bớt để giải phóng dung lượng lưu trữ trên site.</p>'
            . '</div>';
    }

    /**
     * Adds a dedicated Help menu for clients.
     */
    private function addClientHelpMenu(): void
    {
        add_action('admin_menu', function () {
            add_menu_page(
                'Hướng dẫn sử dụng',
                'HD Sử dụng',
                'read',
                'lacadev-help',
                [$this, 'renderHelpPage'],
                'dashicons-format-video',
                2
            );
        });
    }

    /**
     * Renders the Help page.
     */
    public function renderHelpPage(): void
    {
        $page_title = carbon_get_theme_option('help_page_title') ?: 'Hướng dẫn quản trị Website Professional';
        $page_intro = carbon_get_theme_option('help_page_intro') ?: 'Chào mừng bạn đến với hệ thống quản trị website nâng cao. Hệ thống đã được tối ưu để bạn quản lý nội dung dễ dàng nhất.';
        $blocks     = carbon_get_theme_option('help_page_blocks');

        $phone   = carbon_get_theme_option('help_support_phone')   ?: (defined('AUTHOR') ? AUTHOR['phone_number'] : '');
        $email   = carbon_get_theme_option('help_support_email')   ?: (defined('AUTHOR') ? AUTHOR['email'] : '');
        $website = carbon_get_theme_option('help_support_website') ?: (defined('AUTHOR') ? AUTHOR['website'] : '');
        // Styles extracted to resources/styles/admin/_admin-help.scss
        ?>

        <div class="wrap laca-help-wrap">
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0 16px">
                <p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Trang trợ giúp dành cho bạn</p>
                <p style="margin:0;font-size:13px;color:#374151">Đây là trang hướng dẫn sử dụng website dành cho khách hàng. Nội dung bên dưới do quản trị viên cấu hình riêng cho từng dự án tại <strong>Laca Admin &gt; Quản trị &amp; HD Sử dụng</strong>.</p>
            </div>
            <h1 class="laca-help-header">
                <span>📖</span>
                <?php echo esc_html($page_title); ?>
            </h1>
            <p class="laca-help-intro"><?php echo nl2br(esc_html($page_intro)); ?></p>

            <div class="laca-help-grid">
                <?php if (!empty($blocks)) : ?>
                    <?php foreach ($blocks as $block) : ?>
                        <div class="laca-help-card" style="border-top-color: <?php echo esc_attr($block['border_color'] ?: '#2271b1'); ?>;">
                            <h3><?php echo esc_html($block['title']); ?></h3>
                            <div class="laca-help-card-content">
                                <?php echo wpautop(wp_kses_post($block['content'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="laca-help-card">
                        <h3>📝 Hướng dẫn mặc định</h3>
                        <p>Vui lòng vào <strong>Laca Admin &gt; Quản trị &amp; HD Sử dụng</strong> để cập nhật nội dung hướng dẫn cho khách hàng.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="laca-help-footer">
                <h3>📞 Hỗ trợ kỹ thuật LacaDev</h3>
                <p>Mọi vấn đề về vận hành hoặc yêu cầu nâng cấp, vui lòng liên hệ:</p>
                <div class="laca-help-footer-grid">
                    <div><strong>Hotline/Zalo:</strong><br><?php echo esc_html($phone); ?></div>
                    <div><strong>Email:</strong><br><?php echo esc_html($email); ?></div>
                    <div><strong>Website:</strong><br><a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Simplifies the admin for non-developer roles (hides dev-only menus).
     */
    private function simplifyMerchantAdmin(): void
    {
        add_action('admin_head', function () {
            if (current_user_can('manage_options') && !in_array(wp_get_current_user()->user_login, ['lacadev'])) {
                echo '<style>
                    #toplevel_page_laca-admin { display: none !important; }
                    #menu-settings, #menu-tools, #menu-plugins { display: none !important; }
                    .update-nag, .notice-warning, .notice-info.is-dismissible { display: none !important; }
                    #contextual-help-link-wrap { display: none !important; }
                    #wp-admin-bar-updates, #wp-admin-bar-comments, #wp-admin-bar-new-content { display: none !important; }
                </style>';
            }
        });
    }
}
