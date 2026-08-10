<?php

/**
 * Admin UI Branding & Menu Customization.
 *
 * @package LacaDev
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ẩn các menu thừa không cần thiết cho workflow LacaDev.
 */
add_action('admin_menu', function () {
    // Ẩn Comments (thường không dùng)
    remove_menu_page('edit-comments.php');

    // Ẩn Tools mặc định, giữ lại LacaTools custom
    // remove_menu_page('tools.php');
}, 999);

/**
 * Admin bar: ẩn WP logo, thay bằng branding LacaDev.
 */
add_action('admin_bar_menu', function (\WP_Admin_Bar $wp_admin_bar) {
    // Ẩn nút WP logo
    $wp_admin_bar->remove_node('wp-logo');

    // Thêm node logo LacaDev
    $logo_url = get_template_directory_uri() . '/dist/images/logo.png';
    $fallback = 'https://lacadev.com';

    $wp_admin_bar->add_node([
        'id'    => 'logo_author',
        'title' => '<img src="' . esc_url($logo_url) . '" class="logo-admin-bar" alt="LacaDev" onerror="this.style.display=\'none\'">',
        'href'  => esc_url($fallback),
        'meta'  => ['target' => '_blank'],
    ]);
}, 25);

/**
 * Ẩn các items không cần trong admin bar.
 */
add_action('admin_bar_menu', function (\WP_Admin_Bar $wp_admin_bar) {
    $wp_admin_bar->remove_node('wp-logo');
    $wp_admin_bar->remove_node('customize');
    $wp_admin_bar->remove_node('comments');
    $wp_admin_bar->remove_node('updates');
    $wp_admin_bar->remove_node('new-content');
}, 999);

/**
 * Custom admin footer — bên trái.
 */
add_filter('admin_footer_text', function () {
    return sprintf(
        '✦ <strong>LacaDev</strong> &mdash; Crafted with ❤ &nbsp;|&nbsp; <a href="https://lacadev.com" target="_blank">lacadev.com</a>'
    );
});

/**
 * Custom admin footer — bên phải (version).
 */
add_filter('update_footer', function () {
    $theme = wp_get_theme();
    return sprintf(
        'Theme v%s &nbsp;|&nbsp; WordPress %s',
        esc_html($theme->get('Version')),
        esc_html(get_bloginfo('version'))
    );
}, 11);

/**
 * Thêm class body cho theme mặc định dark sidebar.
 */
// add_filter('admin_body_class', function (string $classes) {
//     return $classes . ' laca-dark-admin';
// });
