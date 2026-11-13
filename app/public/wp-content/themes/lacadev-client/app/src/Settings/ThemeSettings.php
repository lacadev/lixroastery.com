<?php
namespace App\Settings;

use PHPMailer\PHPMailer\PHPMailer;
use App\Helpers\Crypto;

class ThemeSettings {
    public function __construct() {
        $this->useGmailSmtp();
        $this->hookAfterLogout();
        $this->hideAdminBar();
        $this->AddActiveClassToCurrentMenu();
        $this->addHeaderData();
        $this->addFooterData();
        $this->trackPostViews();
    }

    /**
     * Tự động tăng lượt xem bài viết bất đồng bộ qua PostViewTracker.
     * Không ghi DB trực tiếp trong request để hỗ trợ full-page cache.
     */
    public function trackPostViews() {
        // Đã được xử lý bất đồng bộ bởi \App\Features\Performance\PostViewTracker
    }

    public function useGmailSmtp() {
        if (get_option('_use_smtp') === 'yes') {
            add_action('phpmailer_init', static function (PHPMailer $phpmailer) {
                $phpmailer->isSMTP();
                $phpmailer->Host       = get_option('_smtp_host');
                $phpmailer->SMTPAuth   = true;
                $phpmailer->SMTPSecure = get_option('_smtp_secure');
                $phpmailer->Port       = get_option('_smtp_port');
                $phpmailer->Username   = get_option('_smtp_username');

                // Giải mã mật khẩu SMTP (hỗ trợ cả plaintext cũ lẫn tagged encryption)
                $rawPassword = get_option('_smtp_password');
                if (Crypto::isTagged($rawPassword)) {
                    $password = Crypto::decryptTagged($rawPassword);
                    if ($password === null) {
                        // Giải mã thất bại — hiện admin notice
                        add_action('admin_notices', static function () {
                            echo '<div class="notice notice-error"><p>';
                            echo esc_html__('Không thể giải mã mật khẩu SMTP. Vui lòng vào Laca Admin → Email để nhập lại.', 'laca');
                            echo '</p></div>';
                        });
                        return; // Bỏ qua cấu hình SMTP
                    }
                } else {
                    $password = $rawPassword;
                }
                $phpmailer->Password = $password;

                // BUG THẬT đã gặp: "_admin_email" không phải option nào cả
                // (option thật của WP là "admin_email", không gạch dưới —
                // field admin thật trong màn hình Cài đặt cũng không tên
                // "admin_email") nên luôn trả về rỗng, khiến PHPMailer báo
                // "Invalid address: (From)" và HỦY gửi hoàn toàn dù cấu
                // hình SMTP đúng — vì vậy form không bao giờ gửi được mail.
                // Gmail SMTP còn bắt buộc "From" phải TRÙNG account đang
                // authenticate (_smtp_username), nếu không Gmail sẽ từ chối
                // hoặc tự ý thay đổi From — nên dùng đúng username đó thay
                // vì admin_email chung chung (có thể không phải @gmail.com).
                $phpmailer->From       = get_option('_smtp_username');
                $phpmailer->FromName   = get_bloginfo('name');
            });
        }
    }

    public function hookAfterLogout() {
        add_action('wp_logout', function () {
            updateUserMeta(get_current_user_id(), 'last_login', '');
            wp_redirect(home_url());
            exit();
        });
    }

    public function hideAdminBar() {
        if (is_user_logged_in()) {
            show_admin_bar(true);
            add_filter('show_admin_bar', '__return_true');
        } else {
            show_admin_bar(false);
            add_filter('show_admin_bar', '__return_false');
        }
    }

    /**
     * Thêm class active vào current menu
     *
     * @param $classes
     */
    public function AddActiveClassToCurrentMenu() {
        add_filter('nav_menu_css_class', static function ($classes) {
            if (in_array('current-menu-item', $classes, true)
                || in_array('current-menu-parent', $classes, true)
                || in_array('current-menu-ancestor', $classes, true)
                || in_array('current_page_item', $classes, true)
                || in_array('current_page_parent', $classes, true)
                || in_array('current_page_ancestor', $classes, true)
            ) {
                $classes[] = 'actived-menu';
            }

            // Remove default WP current classes, keep only project active class.
            return array_values(array_diff($classes, [
                'current-menu-item',
                'current-menu-parent',
                'current-menu-ancestor',
                'current_page_item',
                'current_page_parent',
                'current_page_ancestor',
            ]));
        }, 10, 2);
    }

    public function addHeaderData() {
        add_action('wp_head', static function () {
            $obj        = get_queried_object();
            if ($obj instanceof \WP_Term) {
                $description = $obj->description;
                $image       = '';
            } elseif ($obj instanceof \WP_Post) {
                if (has_excerpt($obj->ID)) {
                    $description = getExcerpt($obj->ID, 160);
                }
                $image = getPostThumbnailUrl($obj->ID, 1200, 628);
            } else {
                $description = get_bloginfo('description');
                $image       = '';
            }

            echo '<meta http-equiv="Content-Type" content="text/html;charset=utf-8"/>
                    <meta name="author" content="' . esc_attr(AUTHOR['name']) . '" />
                    <meta name="copyright" content="' . esc_attr(AUTHOR['name']) . ' [' . esc_attr(AUTHOR['email']) . '] [' . esc_attr(AUTHOR['website']) . ']" />
                    <meta http-equiv="Content-Language" content="' . get_bloginfo('language') . '"/>
                    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>';
        }, PHP_INT_MAX);
    }

    public function addFooterData() {
        add_action('wp_footer', static function () {
            echo getOption('footer_scripts');
        }, PHP_INT_MAX);
    }
}
