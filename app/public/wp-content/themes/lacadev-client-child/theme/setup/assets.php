<?php
/**
 * Child Theme Asset Enqueue
 *
 * Parent theme đã enqueue tất cả assets chính.
 * File này chỉ thêm child-specific overrides.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue child theme frontend assets.
 * Chạy SAU parent's app_action_theme_enqueue_assets() nhờ priority cao hơn.
 */
function child_enqueue_frontend_assets()
{
    $child_version = wp_get_theme()->get('Version');
    $child_dir_uri = dirname(get_stylesheet_directory_uri());

	// ------------------------------------------------------------------
    // Theme style.css (cho phép viết CSS trực tiếp vào theme/style.css)
    // ------------------------------------------------------------------
    wp_enqueue_style(
        'child-style',
        get_stylesheet_uri(),
        ['theme-css-bundle'], // load sau parent CSS
        $child_version
    );
	
    // ------------------------------------------------------------------
    // CSS override từ resources/ (dùng khi không có build step)
    // ------------------------------------------------------------------
    $child_css_file = CHILD_RESOURCES_DIR . 'styles' . DIRECTORY_SEPARATOR . 'child.css';
    if (file_exists($child_css_file)) {
        wp_enqueue_style(
            'child-theme-css',
            $child_dir_uri . '/resources/styles/child.css',
            ['theme-css-bundle'], // load sau parent CSS
            $child_version
        );
    }

    // ------------------------------------------------------------------
    // CSS từ dist/ (dùng khi có Webpack/PostCSS build)
    // ------------------------------------------------------------------
    $child_dist_css = CHILD_DIST_DIR . 'styles' . DIRECTORY_SEPARATOR . 'child.css';
    if (file_exists($child_dist_css)) {
        wp_enqueue_style(
            'child-dist-css',
            $child_dir_uri . '/dist/styles/child.css',
            ['theme-css-bundle'],
            $child_version
        );
    }

    // ------------------------------------------------------------------
    // JS từ dist/ (nếu có custom JS)
    // ------------------------------------------------------------------
    $child_dist_js = CHILD_DIST_DIR . 'child.js';
    if (file_exists($child_dist_js)) {
        wp_enqueue_script(
            'child-theme-js',
            $child_dir_uri . '/dist/child.js',
            ['theme-js-bundle'], // load sau parent JS
            filemtime($child_dist_js), // cache-bust khi file thay đổi
            true // in footer
        );
    }
    // Stats Counter Block animation script — enqueue trực tiếp
    $sc_js = get_stylesheet_directory() . '/block-gutenberg/phucdainam-blocks-site/block-stats-counter/stats-counter.js';
    if ( file_exists( $sc_js ) ) {
        wp_enqueue_script(
            'block-stats-counter-js',
            get_stylesheet_directory_uri() . '/block-gutenberg/phucdainam-blocks-site/block-stats-counter/stats-counter.js',
            [],
            filemtime( $sc_js ),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'child_enqueue_frontend_assets', 20); // priority 20 — sau parent (10)

/**
 * Enqueue child theme admin assets (nếu cần ghi đè admin UI).
 */
function child_enqueue_admin_assets()
{
    $child_dir_uri = dirname(get_stylesheet_directory_uri());

    $child_admin_css = CHILD_DIST_DIR . 'styles' . DIRECTORY_SEPARATOR . 'admin-child.css';
    if (file_exists($child_admin_css)) {
        wp_enqueue_style(
            'child-admin-css',
            $child_dir_uri . '/dist/styles/admin-child.css',
            ['theme-admin-css-bundle'],
            wp_get_theme()->get('Version')
        );
    }
}
add_action('admin_enqueue_scripts', 'child_enqueue_admin_assets', 20);

/**
 * Fix favicon ở trang quản trị/đăng nhập.
 *
 * Assets::addFavicon() (vendor htmlburger/wpemerge-theme-core, hook vào
 * wp_head/login_head/admin_head) lấy URL qua getAssetUri('images/favicon.ico'),
 * mà getAssetUri() luôn đọc manifest.json từ APP_DIST_DIR — hằng số này
 * cứng vào thư mục dist/ của PARENT theme (lacadev-client/dist/). Nhưng dự
 * án build asset bằng webpack của CHILD theme, xuất ra
 * lacadev-client-child/dist/ — nên manifest.json phía parent không tồn tại,
 * getAssetUri() trả về '', href rỗng, browser hiện icon mặc định.
 *
 * Front-end không bị ảnh hưởng vì header.php lấy favicon qua theAsset(),
 * trỏ thẳng resources/images/ chứ không qua dist/manifest.json. Filter
 * `app_favicon_uri` được chừa sẵn đúng để ghi đè trường hợp này — trỏ thẳng
 * về file favicon.ico thật (đang dùng cho front-end) thay vì qua manifest.
 */
add_filter('app_favicon_uri', function () {
    return dirname(get_stylesheet_directory_uri()) . '/resources/images/favicon/favicon.ico';
});
