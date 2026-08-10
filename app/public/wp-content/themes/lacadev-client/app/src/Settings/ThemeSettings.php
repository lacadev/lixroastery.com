<?php
namespace App\Settings;

use PHPMailer\PHPMailer\PHPMailer;

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
     * Tự động tăng lượt xem khi vào trang single
     */
    public function trackPostViews() {
        add_action('wp_head', function() {
            if (is_single() || is_page()) {
                if (function_exists('updateViewCount')) {
                    updateViewCount(get_the_ID());
                }
            }
        });
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
                $phpmailer->Password   = get_option('_smtp_password');
                $phpmailer->From       = get_option('_admin_email');
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
                    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
                    <!--[if lt IE 9]>
                    <script src="//oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
                    <script src="//oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
                    <![endif]-->';
        }, PHP_INT_MAX);
    }

    public function addFooterData() {
        add_action('wp_footer', static function () {
            echo getOption('footer_scripts');
        }, PHP_INT_MAX);
    }
}
