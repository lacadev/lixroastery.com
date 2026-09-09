<?php
/**
 * Đăng ký thêm menu location riêng cho theme header của lixroastery (menu
 * phải bên cạnh logo — "Contact"...). Không đặt ở theme cha (menus.php của
 * lacadev-client) vì đây là location đặc thù cho layout header của site
 * này, không dùng chung cho các site khác.
 *
 * register_nav_menus() gọi nhiều lần sẽ MERGE danh sách location (không ghi
 * đè), nên gọi lại ở đây an toàn — không ảnh hưởng 'main-menu'/'footer-menu'
 * đã đăng ký ở theme cha.
 *
 * @hook after_setup_theme (priority 20 — chạy sau theme cha, xem functions.php)
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

register_nav_menus([
    'header-right-menu' => __('Header Right Menu', 'laca'),
]);
