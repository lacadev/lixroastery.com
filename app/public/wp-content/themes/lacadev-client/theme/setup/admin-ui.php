<?php
/**
 * Tinh chỉnh giao diện WordPress Admin theo chuẩn LacaDev.
 * 
 * - Đổi logo thanh admin bar sang branding LacaDev
 * - Đổi text chân trang (footer) admin
 * - Ẩn menu không dùng (comments...)
 * 
 * @package LacaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ẩn các menu thừa không cần thiết cho workflow LacaDev.
 */
add_action('admin_menu', function () {
    // Ẩn Comments (thường không dùng)
    remove_menu_page('edit-comments.php');
}, 999);

/**
 * Admin bar: ẩn WP logo, thay bằng branding LacaDev.
 */
add_action('admin_bar_menu', function (\WP_Admin_Bar $wp_admin_bar) {
    // Ẩn nút WP logo
    $wp_admin_bar->remove_node('wp-logo');

    // Thêm node logo LacaDev nếu file tồn tại
    $logo_path = get_template_directory() . '/dist/images/logo.png';
    $logo_url  = get_template_directory_uri() . '/dist/images/logo.png';
    if (!file_exists($logo_path) && function_exists('lacaResourceUrl')) {
        $logo_url  = lacaResourceUrl('images/dev/icon.svg');
        $logo_path = function_exists('lacaThemeRootDir') ? lacaThemeRootDir() . '/resources/images/dev/icon.svg' : '';
    }

    $fallback  = 'https://lacadev.com';
    $title     = ($logo_path && file_exists($logo_path))
        ? '<img src="' . esc_url($logo_url) . '" class="logo-admin-bar" alt="LacaDev" onerror="this.style.display=\'none\'">'
        : '<span class="ab-icon dashicons dashicons-admin-generic"></span><span class="ab-label">LacaDev</span>';

    $wp_admin_bar->add_node([
        'id'    => 'logo_author',
        'title' => $title,
        'href'  => esc_url($fallback),
        'meta'  => ['target' => '_blank'],
    ]);
}, 1);

/**
 * Text chân trang bên trái của trang quản trị.
 */
add_filter('admin_footer_text', function () {
    return sprintf(
        /* translators: 1: author name, 2: author URL */
        __('Website được phát triển bởi <a href="%s" target="_blank" rel="noopener">%s</a>', 'laca'),
        esc_url(AUTHOR['website'] ?? 'https://lacadev.com'),
        esc_html(AUTHOR['name'] ?? 'LacaDev')
    );
}, 11);

/**
 * Text chân trang bên phải: ẩn phiên bản WP core, chỉ hiện "WordPress + phiên bản".
 */
add_filter('update_footer', function () {
    return sprintf(
        /* translators: %s: WordPress version */
        __('Phiên bản %s', 'laca'),
        esc_html(get_bloginfo('version'))
    );
}, 11);
