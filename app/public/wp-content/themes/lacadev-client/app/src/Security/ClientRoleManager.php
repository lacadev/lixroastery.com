<?php

namespace App\Security;

use App\Settings\AdminSettings;

/**
 * ClientRoleManager
 *
 * Quản lý vai trò "Khách hàng" (laca_client) trên site client:
 * - Khởi tạo role qua version-check (laca_client_role_version)
 * - Cấp quyền biên tập bài viết, trang, media, menu, form liên hệ, SEO
 * - Ẩn menu và chặn truy cập trực tiếp các trang kỹ thuật (plugins, themes, users, tools, Laca Admin)
 * - Trang Hướng dẫn sử dụng
 * - Tuyệt đối không ảnh hưởng tới Super User
 */
class ClientRoleManager
{
    public const ROLE_NAME    = 'laca_client';
    public const ROLE_VERSION = '1.0.0';
    public const OPT_VERSION  = 'laca_client_role_version';

    public function init(): void
    {
        add_action('init', [$this, 'maybeRegisterRole']);
        add_action('admin_menu', [$this, 'filterAdminMenu'], 999);
        // Sau priority 10 để menu cha 'lacadev-help' (AdminUxService) đã tồn tại.
        add_action('admin_menu', [$this, 'registerUserGuidePage'], 11);
        add_action('admin_init', [$this, 'blockDirectAccess']);
        add_action('admin_notices', [$this, 'renderAccessDeniedNotice']);
        add_action('carbon_fields_theme_options_container_saved', [$this, 'syncAssignedUsers']);
    }

    /**
     * Đồng bộ role laca_client cho các user được admin cấu hình
     */
    public function syncAssignedUsers(): void
    {
        if (!function_exists('carbon_get_theme_option')) {
            return;
        }

        $assignedIds = carbon_get_theme_option('laca_client_assigned_users');
        if (!is_array($assignedIds)) {
            return;
        }

        $assignedIds = array_map('intval', array_filter($assignedIds));
        $superLogins = class_exists(AdminSettings::class) ? AdminSettings::getSuperUserLogins() : [];

        foreach ($assignedIds as $uid) {
            $user = get_userdata($uid);
            if (!$user || in_array($user->user_login, $superLogins, true)) {
                continue;
            }
            if (!in_array(self::ROLE_NAME, $user->roles, true)) {
                $user->add_role(self::ROLE_NAME);
            }
        }
    }

    /**
     * Khởi tạo hoặc cập nhật vai trò laca_client qua version check
     */
    public function maybeRegisterRole(): void
    {
        $currentVersion = get_option(self::OPT_VERSION, '');
        if ($currentVersion === self::ROLE_VERSION) {
            return;
        }

        $caps = [
            'read'                      => true,
            'upload_files'              => true,

            // Bài viết (Posts)
            'edit_posts'                => true,
            'edit_others_posts'         => true,
            'edit_published_posts'      => true,
            'publish_posts'             => true,
            'delete_posts'              => true,
            'delete_others_posts'       => true,
            'delete_published_posts'    => true,

            // Trang (Pages)
            'edit_pages'                => true,
            'edit_others_pages'         => true,
            'edit_published_pages'      => true,
            'publish_pages'             => true,
            'delete_pages'              => true,
            'delete_others_pages'       => true,
            'delete_published_pages'    => true,

            // Giao diện (Menu)
            'edit_theme_options'        => true,

            // Form liên hệ & SEO
            'wpforms_view_entries'      => true,
            'wpforms_edit_entries'      => true,
            'fluentform_view_entries'   => true,
            'rank_math_onpage_seo'      => true,
            'rank_math_general'         => true,
            'wpseo_edit_advanced_metadata' => true,
        ];

        // Thêm role mới hoặc cập nhật caps cho role đã có
        $role = get_role(self::ROLE_NAME);
        if ($role) {
            foreach ($caps as $cap => $grant) {
                $role->add_cap($cap, $grant);
            }
        } else {
            add_role(self::ROLE_NAME, __('Khách hàng', 'laca'), $caps);
        }

        update_option(self::OPT_VERSION, self::ROLE_VERSION, false);
    }

    /**
     * Kiểm tra user hiện tại có phải super user hoặc không thuộc role laca_client
     */
    public static function isClientRoleUser(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $currentUser = wp_get_current_user();

        // 1. Super users không bao giờ bị giới hạn
        if (class_exists(AdminSettings::class)) {
            $superLogins = AdminSettings::getSuperUserLogins();
            if (in_array($currentUser->user_login, $superLogins, true)) {
                return false;
            }
        }

        // 2. Administrators không bị giới hạn (trừ khi role chính là laca_client)
        if (in_array('administrator', $currentUser->roles, true)) {
            return false;
        }

        // 3. User có role laca_client
        return in_array(self::ROLE_NAME, $currentUser->roles, true);
    }

    /**
     * Ẩn các menu quản trị kỹ thuật khỏi thanh điều hướng của role laca_client
     */
    public function filterAdminMenu(): void
    {
        if (!self::isClientRoleUser()) {
            return;
        }

        // Ẩn các menu kỹ thuật cấp cao
        remove_menu_page('plugins.php');
        remove_menu_page('users.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('carbon_fields_container_lacadev_admin.php');
        remove_menu_page('laca-admin');
        remove_menu_page('edit.php?post_type=acf-field-group');

        // Đối với Themes: Ẩn themes.php nhưng nếu cần quản lý menu, điều hướng sang nav-menus.php
        remove_submenu_page('themes.php', 'themes.php');
        remove_submenu_page('themes.php', 'theme-editor.php');
        remove_submenu_page('themes.php', 'customize.php');
        remove_submenu_page('themes.php', 'widgets.php');

        // Bỏ menu themes.php chính nếu không có trang con nào khác ngoài nav-menus
        remove_menu_page('themes.php');
        add_menu_page(
            __('Menu điều hướng', 'laca'),
            __('Menu', 'laca'),
            'edit_theme_options',
            'nav-menus.php',
            '',
            'dashicons-menu-alt3',
            25
        );
    }

    /**
     * Chặn truy cập trực tiếp bằng URL vào các file PHP và trang quản trị cấm
     */
    public function blockDirectAccess(): void
    {
        if (!self::isClientRoleUser()) {
            return;
        }

        global $pagenow;

        $forbiddenFiles = [
            'plugins.php',
            'plugin-install.php',
            'plugin-editor.php',
            'themes.php',
            'theme-install.php',
            'theme-editor.php',
            'customize.php',
            'users.php',
            'user-new.php',
            'tools.php',
            'import.php',
            'export.php',
            'site-health.php',
            'options-general.php',
            'options-writing.php',
            'options-reading.php',
            'options-discussion.php',
            'options-media.php',
            'options-permalink.php',
            'options-privacy.php',
        ];

        // Cho phép user-edit.php nếu đang sửa chính profile của mình
        if ($pagenow === 'user-edit.php' && isset($_GET['user_id']) && (int) $_GET['user_id'] === get_current_user_id()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        $isForbiddenPage = false;
        if (!empty($page)) {
            // Cho phép các trang hỗ trợ và hướng dẫn
            if (in_array($page, ['laca-user-guide', 'laca-client-support', 'lacadev-help'], true)) {
                $isForbiddenPage = false;
            } elseif (str_contains($page, 'laca') || str_contains($page, 'carbon_fields')) {
                $isForbiddenPage = true;
            }
        }

        if (in_array($pagenow, $forbiddenFiles, true) || $isForbiddenPage) {
            wp_safe_redirect(admin_url('index.php?laca_access_denied=1'));
            exit;
        }
    }

    /**
     * Hiển thị thông báo khi bị chặn truy cập
     */
    public function renderAccessDeniedNotice(): void
    {
        if (isset($_GET['laca_access_denied']) && $_GET['laca_access_denied'] === '1') {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('Khu vực bị hạn chế: ', 'laca') . '</strong>'
                . esc_html__('Tài khoản của bạn không có quyền truy cập vào cấu hình hệ thống kỹ thuật này. Vui lòng liên hệ đội ngũ LacaDev nếu bạn cần thay đổi cài đặt.', 'laca') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Đăng ký trang Hướng dẫn sử dụng
     */
    public function registerUserGuidePage(): void
    {
        // Gộp vào menu "HD Sử dụng" để chỉ còn 1 lối vào hướng dẫn trên sidebar.
        if (menu_page_url('lacadev-help', false) !== '') {
            add_submenu_page(
                'lacadev-help',
                __('Hướng dẫn sử dụng', 'laca'),
                __('Hướng dẫn cơ bản', 'laca'),
                'read',
                'laca-user-guide',
                [$this, 'renderUserGuidePage']
            );
            return;
        }

        add_menu_page(
            __('Hướng dẫn sử dụng', 'laca'),
            __('Hướng dẫn', 'laca'),
            'read',
            'laca-user-guide',
            [$this, 'renderUserGuidePage'],
            'dashicons-welcome-learn-more',
            3
        );
    }

    /**
     * Render nội dung trang Hướng dẫn sử dụng
     */
    public function renderUserGuidePage(): void
    {
        ?>
        <div class="wrap" style="max-width:960px;">
            <h1 style="display:flex;align-items:center;gap:8px;font-size:24px;margin-bottom:20px;">
                <span class="dashicons dashicons-welcome-learn-more" style="font-size:28px;width:28px;height:28px;color:#0284c7;"></span>
                <?php echo esc_html__('Hướng dẫn sử dụng Website', 'laca'); ?>
            </h1>

            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px 20px;margin-bottom:24px;">
                <h3 style="margin:0 0 6px;color:#0369a1;font-size:15px;"><?php echo esc_html__('Chào mừng bạn đến với hệ thống quản trị website!', 'laca'); ?></h3>
                <p style="margin:0;font-size:13px;color:#334155;line-height:1.6;">
                    <?php echo esc_html__('Tài liệu này tổng hợp các thao tác cơ bản nhất để bạn có thể tự tin quản lý nội dung, cập nhật bài viết và tương tác với khách hàng một cách nhanh chóng.', 'laca'); ?>
                </p>
            </div>

            <!-- Mục 1: Gutenberg Block Editor -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <h2 style="font-size:18px;margin-top:0;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>🧩</span> <?php echo esc_html__('1. Chỉnh sửa trang bằng Block Editor (Gutenberg)', 'laca'); ?>
                </h2>
                <p style="font-size:13px;color:#475569;line-height:1.6;">
                    <?php echo esc_html__('Website được xây dựng theo kiến trúc Block linh hoạt. Bạn có thể dễ dàng chỉnh sửa văn bản, thay đổi hình ảnh và sắp xếp lại các phần:', 'laca'); ?>
                </p>
                <ul style="list-style:disc;padding-left:20px;font-size:13px;color:#334155;line-height:1.8;">
                    <li><strong><?php echo esc_html__('Mở trang cần sửa:', 'laca'); ?></strong> <?php echo esc_html__('Vào Trang → Tất cả các trang → Chọn trang cần cập nhật → Nhấn Chỉnh sửa.', 'laca'); ?></li>
                    <li><strong><?php echo esc_html__('Sửa nội dung:', 'laca'); ?></strong> <?php echo esc_html__('Nhấp chuột trực tiếp vào đoạn văn, tiêu đề hoặc ảnh để thay đổi nội dung tức thì.', 'laca'); ?></li>
                    <li><strong><?php echo esc_html__('Lưu thay đổi:', 'laca'); ?></strong> <?php echo esc_html__('Nhấn nút "Cập nhật" ở góc trên bên phải để áp dụng lên website.', 'laca'); ?></li>
                </ul>
            </div>

            <!-- Mục 2: Bài viết & Tin tức -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <h2 style="font-size:18px;margin-top:0;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>📝</span> <?php echo esc_html__('2. Thêm mới và quản lý bài viết / tin tức', 'laca'); ?>
                </h2>
                <ul style="list-style:disc;padding-left:20px;font-size:13px;color:#334155;line-height:1.8;">
                    <li><strong><?php echo esc_html__('Tạo bài mới:', 'laca'); ?></strong> <?php echo esc_html__('Vào Bài viết → Viết bài mới. Nhập tiêu đề và soạn thảo nội dung.', 'laca'); ?></li>
                    <li><strong><?php echo esc_html__('Ảnh đại diện:', 'laca'); ?></strong> <?php echo esc_html__('Tại cột bên phải, tìm mục "Ảnh đại diện" và tải lên hình ảnh minh hoạ cho bài viết.', 'laca'); ?></li>
                    <li><strong><?php echo esc_html__('Chuyên mục:', 'laca'); ?></strong> <?php echo esc_html__('Tích chọn chuyên mục phù hợp để bài viết xuất hiện đúng vị trí.', 'laca'); ?></li>
                </ul>
            </div>

            <!-- Mục 3: Form liên hệ -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <h2 style="font-size:18px;margin-top:0;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>📬</span> <?php echo esc_html__('3. Xem và xuất danh sách liên hệ từ khách hàng', 'laca'); ?>
                </h2>
                <p style="font-size:13px;color:#475569;line-height:1.6;">
                    <?php echo esc_html__('Khi khách hàng điền form liên hệ hoặc đặt hẹn trên website, dữ liệu sẽ được gửi về email của bạn đồng thời lưu trong hệ thống:', 'laca'); ?>
                </p>
                <ul style="list-style:disc;padding-left:20px;font-size:13px;color:#334155;line-height:1.8;">
                    <li><strong><?php echo esc_html__('Kiểm tra danh sách:', 'laca'); ?></strong> <?php echo esc_html__('Vào mục Form liên hệ trên menu để xem toàn bộ thông tin họ tên, số điện thoại, lời nhắn.', 'laca'); ?></li>
                    <li><strong><?php echo esc_html__('Xuất file Excel/CSV:', 'laca'); ?></strong> <?php echo esc_html__('Nhấn nút "Export" để tải danh sách khách hàng về máy tính khi cần tổng hợp.', 'laca'); ?></li>
                </ul>
            </div>

            <!-- Mục 4: Hỗ trợ kỹ thuật -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <h2 style="font-size:18px;margin-top:0;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>🆘</span> <?php echo esc_html__('4. Gửi yêu cầu hỗ trợ tới đội ngũ LacaDev', 'laca'); ?>
                </h2>
                <p style="font-size:13px;color:#475569;line-height:1.6;">
                    <?php echo esc_html__('Bất cứ khi nào bạn cần hỗ trợ kỹ thuật, gặp lỗi website hoặc muốn cập nhật thêm tính năng:', 'laca'); ?>
                </p>
                <div style="margin-top:16px;">
                    <a href="<?php echo esc_url(admin_url('index.php#laca-support-box')); ?>" class="button button-primary button-hero">
                        <?php echo esc_html__('Mở Widget Gửi Yêu Cầu Hỗ Trợ', 'laca'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}
