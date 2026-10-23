<?php
/**
 * Asset helpers.
 *
 * @package WPEmergeTheme
 */

use WPEmergeTheme\Facades\Theme;
use WPEmergeTheme\Facades\Assets;

/**
 * Enhanced asset loading with performance optimizations
 */
function app_action_theme_enqueue_assets()
{
    $version = wp_get_theme()->get('Version');
    $theme_root_dir = dirname(get_stylesheet_directory());
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';

    /**
     * Enqueue the built-in comment-reply script for singular pages.
     */
    if (is_singular()) {
        wp_enqueue_script('comment-reply');
    }

    /**
     * Critical JS (inline or very small) - load in head for critical functionality
     */
    if (file_exists($dist_path . 'critical.js')) {
        wp_enqueue_script('theme-critical-js', $dist_url . 'critical.js', [], $version, false);
    }

    /**
     * Vendors bundle
     */
    $vendors_deps = [];
    if (file_exists($dist_path . 'vendors.js')) {
        wp_enqueue_script('theme-vendors-js', $dist_url . 'vendors.js', [], $version, true);
        $vendors_deps = ['theme-vendors-js'];
    }

    /**
     * Main JavaScript bundle (deferred)
     */
    Assets::enqueueScript('theme-js-bundle', $dist_url . 'theme.js', $vendors_deps, true);

    /**
     * Conditional assets based on page type
     */
    if (is_home() || is_archive() || is_search()) {
        if (file_exists($dist_path . 'archive.js')) {
            wp_enqueue_script('theme-archive-js', $dist_url . 'archive.js', ['theme-js-bundle'], $version, true);
        }
    }

    if (is_single() && comments_open()) {
        if (file_exists($dist_path . 'comments.js')) {
            wp_enqueue_script('theme-comments-js', $dist_url . 'comments.js', ['theme-js-bundle'], $version, true);
        }
    }

    /**
     * Enqueue styles with preload optimization
     */
    Assets::enqueueStyle('theme-css-bundle', $dist_url . 'styles/theme.css');

    /**
     * Conditional CSS based on page type
     */
    if (is_single()) {
        if (file_exists($dist_path . 'styles/single.css')) {
            wp_enqueue_style('theme-single-css', $dist_url . 'styles/single.css', ['theme-css-bundle'], $version);
        }
    }

    /**
     * Enqueue theme's style.css file to allow overrides for the bundled styles.
     */
    Assets::enqueueStyle('theme-styles', get_template_directory_uri() . '/style.css');

    /**
     * Localize script with minimal data
     */
    wp_localize_script('theme-js-bundle', 'themeData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('theme_nonce'),
        'isHome' => is_home(),
        'isMobile' => wp_is_mobile(),
        'currentUrl' => get_permalink(),
    ]);
}

/**
 * Enqueue admin assets.
 *
 * @return void
 */
function app_action_admin_enqueue_assets()
{
    // dist/ nằm ở .../lacadev-client/dist/ nên cần dirname() để lên 1 level
    $template_dir = dirname(get_stylesheet_directory_uri());

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        'theme-admin-css-bundle',
        $template_dir . '/dist/styles/admin.css'
    );
    Assets::enqueueStyle(
        'theme-editor-css-bundle',
        $template_dir . '/dist/styles/editor.css'
    );

    /**
     * Enqueue vendors.js if exists (same fix as frontend)
     * CRITICAL: Load in head (false) to ensure it's available before admin.js
     */
    $admin_deps = [];
    $theme_root = dirname(get_stylesheet_directory());
    $vendors_path = $theme_root . '/dist/vendors.js';
    
    if (file_exists($vendors_path)) {
        $base_uri = get_stylesheet_directory_uri();
        $theme_uri = dirname($base_uri);
        $vendors_url = $theme_uri . '/dist/vendors.js';
        
        // Load in <head> without defer to ensure Swal is available
        wp_enqueue_script('theme-vendors-js', $vendors_url, [], wp_get_theme()->get('Version'), false);
        $admin_deps = ['theme-vendors-js'];
    }

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-admin-js-bundle',
        $template_dir . '/dist/admin.js',
        $admin_deps,
        true
    );

    /**
     * Localize admin script data with nonce for AJAX requests and i18n strings
     */
    wp_localize_script('theme-admin-js-bundle', 'ajaxurl_params', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('update_post_thumbnail'),  // Must match backend check_ajax_referer
    ]);

    /**
     * Localize i18n strings for admin JavaScript
     */
    wp_localize_script('theme-admin-js-bundle', 'adminI18n', [
        // Thumbnail removal
        'removeThumbnailTitle' => __('Remove Thumbnail?', 'lacadev'),
        'removeThumbnailText' => __('Are you sure you want to remove this featured image?', 'lacadev'),
        'removeThumbnailConfirm' => __('Yes, remove it', 'lacadev'),
        'removeThumbnailCancel' => __('Cancel', 'lacadev'),
        'removedTitle' => __('Removed!', 'lacadev'),
        'removedText' => __('Featured image has been removed.', 'lacadev'),
        'errorTitle' => __('Error!', 'lacadev'),
        'failedRemove' => __('Failed to remove thumbnail.', 'lacadev'),
        
        // UI labels
        'chooseImage' => __('Choose image', 'lacadev'),
        'setFeaturedImage' => __('Set featured image', 'lacadev'),
    ]);

    /**
     * Localize project chart data — chỉ inject trên trang Dashboard (index.php).
     * Dữ liệu được đọc từ custom post type 'project' nếu đã đăng ký.
     */
    $current_screen = get_current_screen();
    if ($current_screen && $current_screen->id === 'dashboard' && post_type_exists('project')) {
        global $wpdb;

        // byStatus: đếm project theo meta _project_status (Carbon Fields)
        $status_labels = [
            'pending'     => '🕐 Chờ làm',
            'in_progress' => '🔨 Đang làm',
            'done'        => '✅ Đã xong',
            'maintenance' => '🔧 Đang bảo trì',
            'paused'      => '⏸️ Tạm dừng',
        ];

        $status_rows = $wpdb->get_results("
            SELECT
                COALESCE(pm.meta_value, 'pending') AS `key`,
                COUNT(*) AS `count`
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id AND pm.meta_key = '_project_status'
            WHERE p.post_type = 'project'
              AND p.post_status NOT IN ('trash','auto-draft','inherit')
            GROUP BY `key`
        ");

        $by_status = [];
        foreach ($status_rows as $row) {
            $by_status[] = [
                'key'   => $row->key,
                'label' => $status_labels[$row->key] ?? ucfirst($row->key),
                'count' => (int) $row->count,
            ];
        }

        // byMonth: đếm project tạo mới trong 12 tháng gần nhất
        $month_rows = $wpdb->get_results("
            SELECT
                DATE_FORMAT(post_date, '%Y-%m') AS ym,
                COUNT(*) AS cnt
            FROM {$wpdb->posts}
            WHERE post_type = 'project'
              AND post_status NOT IN ('trash','auto-draft','inherit')
              AND post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY ym
            ORDER BY ym ASC
        ");

        // Lấp đầy các tháng còn thiếu
        $month_map = [];
        foreach ($month_rows as $r) {
            $month_map[$r->ym] = (int) $r->cnt;
        }
        $by_month = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym    = date('Y-m', strtotime("-{$i} months"));
            $label = 'T' . (int) date('n', strtotime("-{$i} months"));
            $by_month[] = [
                'month' => $label,
                'count' => $month_map[$ym] ?? 0,
            ];
        }

        wp_localize_script('theme-admin-js-bundle', 'lacaProjectCharts', [
            'primary'  => '#18181b', // Token --laca-accent đơn sắc
            'byStatus' => $by_status,
            'byMonth'  => $by_month,
        ]);
    }
}

/**
 * Preload critical assets in admin_head
 */
add_action('admin_head', function() {
    $theme_root_dir = dirname(get_stylesheet_directory());
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url = $theme_root_uri . '/dist/';
    
    // Preload important fonts (qua filter lacadev_preload_fonts)
    $fonts = apply_filters('lacadev_preload_fonts', []);

    foreach ($fonts as $font_rel) {
        $font_path = $dist_path . ltrim($font_rel, '/');
        if (file_exists($font_path)) {
            $ext = pathinfo($font_rel, PATHINFO_EXTENSION);
            $type = $ext === 'woff2' ? 'font/woff2' : ($ext === 'woff' ? 'font/woff' : 'font/ttf');
            echo '<link rel="preload" href="' . $dist_url . ltrim($font_rel, '/') . '" as="font" type="' . esc_attr($type) . '" crossorigin>' . "\n";
        }
    }
}, 1);

/**
 * Enqueue login assets.
 *
 * @return void
 */
function app_action_login_enqueue_assets()
{
    $template_dir = dirname(get_stylesheet_directory_uri());
    $resolveLogoUrl = static function ($rawValue): string {
        // Carbon Fields image can return ID, URL string, or array.
        if (empty($rawValue)) {
            return '';
        }

        if (is_numeric($rawValue)) {
            $url = wp_get_attachment_image_url((int) $rawValue, 'full');
            return $url ?: '';
        }

        if (is_array($rawValue)) {
            if (!empty($rawValue['url']) && is_string($rawValue['url'])) {
                return esc_url_raw($rawValue['url']);
            }

            if (!empty($rawValue['id']) && is_numeric($rawValue['id'])) {
                $url = wp_get_attachment_image_url((int) $rawValue['id'], 'full');
                return $url ?: '';
            }

            if (!empty($rawValue['value']) && is_numeric($rawValue['value'])) {
                $url = wp_get_attachment_image_url((int) $rawValue['value'], 'full');
                return $url ?: '';
            }

            return '';
        }

        if (is_string($rawValue)) {
            if (filter_var($rawValue, FILTER_VALIDATE_URL)) {
                return esc_url_raw($rawValue);
            }

            if (ctype_digit($rawValue)) {
                $url = wp_get_attachment_image_url((int) $rawValue, 'full');
                return $url ?: '';
            }
        }

        return '';
    };

    $login_logo_raw = carbon_get_theme_option('login_logo');
    $login_logo_url = $resolveLogoUrl($login_logo_raw);
    if (empty($login_logo_url)) {
        $login_logo_url = $resolveLogoUrl(carbon_get_theme_option('logo'));
    }
    $pickLoginI18n = static function (string $key, string $lang, string $fallback) {
        $value = carbon_get_theme_option("{$key}_{$lang}");
        if (empty($value)) {
            // Backward compatibility with old single-language keys.
            $value = carbon_get_theme_option($key);
        }
        return !empty($value) ? $value : $fallback;
    };

    $loginVi = [
        'userLabel' => $pickLoginI18n('login_user_label', 'vi', 'Ai đang ghé trạm?'),
        'userPlaceholder' => $pickLoginI18n('login_user_placeholder', 'vi', 'Điền tên hoặc email vào đây nhé'),
        'passLabel' => $pickLoginI18n('login_password_label', 'vi', 'Chìa khóa'),
        'passPlaceholder' => $pickLoginI18n('login_password_placeholder', 'vi', 'Nhập chìa khóa mở cửa'),
        'welcomeText' => nl2br(sanitize_textarea_field($pickLoginI18n('login_welcome_text', 'vi', "Chào mừng về Trạm Laca!\nCắm sạc, pha trà và bắt đầu nào!"))),
        'forgetPwd' => $pickLoginI18n('login_forgot_label', 'vi', 'Rớt chìa khoá?'),
        'backToBlog' => $pickLoginI18n('login_back_label', 'vi', '← Rời khỏi Trạm'),
    ];

    $loginEn = [
        'userLabel' => $pickLoginI18n('login_user_label', 'en', "Who's visiting the station?"),
        'userPlaceholder' => $pickLoginI18n('login_user_placeholder', 'en', 'Enter name or email here'),
        'passLabel' => $pickLoginI18n('login_password_label', 'en', 'The Key'),
        'passPlaceholder' => $pickLoginI18n('login_password_placeholder', 'en', 'Enter your key to open'),
        'welcomeText' => nl2br(sanitize_textarea_field($pickLoginI18n('login_welcome_text', 'en', "Welcome to Laca Station!\nCharge up, brew some tea and let's go!"))),
        'forgetPwd' => $pickLoginI18n('login_forgot_label', 'en', 'Lost your key?'),
        'backToBlog' => $pickLoginI18n('login_back_label', 'en', '← Leave the Station'),
    ];

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-login-js-bundle',
        $template_dir . '/dist/login.js',
        [],
        true
    );

    wp_localize_script('theme-login-js-bundle', 'loginI18n', [
        'logoUrl' => $login_logo_url,
        'locales' => [
            'vi' => $loginVi,
            'en' => $loginEn,
        ],
        'userLabel' => $loginVi['userLabel'],
        'userPlaceholder' => $loginVi['userPlaceholder'],
        'passLabel' => $loginVi['passLabel'],
        'passPlaceholder' => $loginVi['passPlaceholder'],
        'welcomeText' => $loginVi['welcomeText'],
        'forgetPwd' => $loginVi['forgetPwd'],
        'backToBlog' => $loginVi['backToBlog'],
        'language' => get_bloginfo('language'),
        'homeUrl' => home_url('/'),
    ]);

    // Ensure placeholders can be overridden from Carbon Fields without requiring JS rebuild.
    wp_add_inline_script('theme-login-js-bundle', "(function(){document.addEventListener('DOMContentLoaded',function(){var cfg=window.loginI18n||{};var locales=cfg.locales||{};var lang=(document.documentElement.lang||'').indexOf('en')!==-1?'en':'vi';var data=locales[lang]||locales.vi||{};var userPlaceholder=data.userPlaceholder||cfg.userPlaceholder||'';var passPlaceholder=data.passPlaceholder||cfg.passPlaceholder||'';var user=document.getElementById('user_login');var pass=document.getElementById('user_pass');if(user&&userPlaceholder){user.setAttribute('placeholder',userPlaceholder);}if(pass&&passPlaceholder){pass.setAttribute('placeholder',passPlaceholder);}});}());", 'after');

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        'theme-login-css-bundle',
        $template_dir . '/dist/styles/login.css'
    );

    // Force override login logo in case theme CSS uses !important.
    if (!empty($login_logo_url)) {
        $safe_logo_url = esc_url_raw($login_logo_url);
        $login_logo_css = "#login h1 a{background-image:url('{$safe_logo_url}') !important;}";
        wp_add_inline_style('theme-login-css-bundle', $login_logo_css);
    }
}

/**
 * Enqueue editor assets.
 *
 * @return void
 */
function app_action_editor_enqueue_assets()
{
    $template_dir = dirname(get_stylesheet_directory_uri());

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-editor-js-bundle',
        $template_dir . '/dist/editor.js',
        // Khai báo rõ phụ thuộc wp-* — bundle này import @wordpress/* qua
        // externals map (xem resources/build/webpack/externals.js) nên cần
        // các global window.wp.* tương ứng đã sẵn sàng TRƯỚC khi chạy. Luôn
        // an toàn khai báo (dù không dùng hết) vì đang ở màn hình Block
        // Editor, các handle này chắc chắn đã được core đăng ký sẵn.
        ['wp-element', 'wp-components', 'wp-compose', 'wp-block-editor', 'wp-data', 'wp-hooks', 'wp-i18n'],
        true
    );

    /**
    * Enqueue styles.
    */
    Assets::enqueueStyle(
        'theme-editor-css-bundle',
        $template_dir . '/dist/styles/editor.css'
    );

    // Support for block editor styles (classic and modern)
    add_editor_style($template_dir . '/dist/styles/editor.css');

    // Inject theme colors and fonts as CSS variables for the editor
    $primary_color = getOption('primary_color');
    $secondary_color = getOption('secondary_color');
    $bg_color = getOption('bg_color');
    
    $primary_color_dark = getOption('primary_color_dark');
    $secondary_color_dark = getOption('secondary_color_dark');
    $bg_color_dark = getOption('bg_color_dark');

    $custom_css = "
        :root, .editor-styles-wrapper {
            --primary-color: {$primary_color};
            --secondary-color: {$secondary_color};
            --bg-color: {$bg_color};
            --primary-color-dark: {$primary_color_dark};
            --secondary-color-dark: {$secondary_color_dark};
            --bg-color-dark: {$bg_color_dark};
            font-family: 'Quicksand', sans-serif !important;
        }
    ";
    wp_add_inline_style('theme-editor-css-bundle', $custom_css);
}

/**
 * Add favicon proxy.
 *
 * @return void
 * @link WPEmergeTheme\Assets\Assets::addFavicon()
 */
function app_action_add_favicon()
{
    Assets::addFavicon();
}

/**
 * Advanced script optimization with defer/async/preload
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    // Scripts to defer (non-critical)
    // NOTE: theme-vendors-js is NOT deferred - it must load blocking to ensure Swal/dependencies are available
    $default_defer = [
        'theme-js-bundle',
        'theme-admin-js-bundle',
        'theme-login-js-bundle',
        'theme-editor-js-bundle',
        'theme-archive-js',
        'theme-comments-js',
        'child-theme-js',
        'sourcebuster-js',
        'wc-order-attribution',
    ];
    $defer_scripts = apply_filters('lacadev_defer_scripts', $default_defer);

    // Scripts to async (tracking, analytics)
    $default_async = [
        'google-analytics',
        'facebook-pixel',
        'hotjar'
    ];
    $async_scripts = apply_filters('lacadev_async_scripts', $default_async);

    if (in_array($handle, $defer_scripts, true)) {
        if (strpos($tag, ' defer') === false) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        return $tag;
    }

    if (in_array($handle, $async_scripts, true)) {
        if (strpos($tag, ' async') === false) {
            return str_replace('<script ', '<script async ', $tag);
        }
        return $tag;
    }

    return $tag;
}, 10, 3);

/**
 * Advanced style optimization
 */
add_filter('style_loader_tag', function ($tag, $handle, $href) {
    if (is_admin() || is_customize_preview() || wp_is_json_request() || (function_exists('is_feed') && is_feed())) {
        return $tag;
    }

    // Chỉ chuyển sang async tải bất đồng bộ khi Critical CSS đã được in ở request này
    if (empty($GLOBALS['lacadev_critical_css_inlined'])) {
        return $tag;
    }

    $default_async = [
        'theme-css-bundle',
        'theme-styles',
    ];
    $async_handles = apply_filters('lacadev_async_style_handles', $default_async);

    // Kiểm tra khớp handle chính xác hoặc tiền tố (ví dụ block-block-*)
    $is_async = false;
    foreach ($async_handles as $async_handle) {
        if ($handle === $async_handle || (str_ends_with($async_handle, '*') && str_starts_with($handle, rtrim($async_handle, '*')))) {
            $is_async = true;
            break;
        }
    }

    if (!$is_async) {
        return $tag;
    }

    // Lấy media attribute gốc (nếu có, ví dụ woocommerce-smallscreen: only screen and (max-width: 768px))
    $media = 'all';
    if (preg_match('/media=[\'"]([^\'"]+)[\'"]/', $tag, $matches)) {
        $media = esc_attr($matches[1]);
    }

    // CSP có 'unsafe-inline' và không có nonce trên frontend nên onload inline chạy an toàn
    $async_tag = preg_replace(
        '/<link\s+([^>]*?)media=[\'"][^\'"]*[\'"]([^>]*?)>/',
        '<link $1media="print" onload="this.media=\'' . $media . '\';this.onload=null;"$2>',
        $tag
    );

    if ($async_tag === $tag) {
        $async_tag = str_replace('<link ', '<link media="print" onload="this.media=\'' . $media . '\';this.onload=null;" ', $tag);
    }

    return $async_tag . '<noscript>' . $tag . '</noscript>' . "\n";
}, 10, 3);

/**
 * Inline Critical CSS + Preload assets in wp_head
 *
 * When critical.css exists (generated by `yarn critical`), its contents are
 * inlined directly into <head> so layout-essential rules (.container, body, etc.)
 * are available immediately — even though theme.css loads async.
 */
add_action('wp_head', function() {
    $theme_root_dir = dirname(get_stylesheet_directory());
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';
    
    // 1. Inline Critical CSS (prevents FOUC when theme.css is loaded async)
    // Child có thể trả file critical riêng theo loại trang (nhỏ + đúng hơn 1 file chung).
    $critical_path = apply_filters('lacadev_critical_css_path', $dist_path . 'styles/critical.css', $dist_path);
    if (!file_exists($critical_path)) {
        $critical_path = $dist_path . 'styles/critical.css';
    }
    // Tool sinh critical (yarn critical) cần CSS gốc dạng blocking để trích đủ rule —
    // ?lacadev_no_critical=1 tắt inline + async cho request đó (chỉ ảnh hưởng tốc độ, không lộ dữ liệu).
    $skip_critical = isset($_GET['lacadev_no_critical']); // phpcs:ignore WordPress.Security.NonceVerification
    if (!$skip_critical && file_exists($critical_path)) {
        $critical_css = file_get_contents($critical_path);
        if ($critical_css) {
            $GLOBALS['lacadev_critical_css_inlined'] = true;
            echo '<style id="critical-css">' . $critical_css . '</style>' . "\n";
        }
    }

    // 2. Preload Critical JS
    if (file_exists($dist_path . 'critical.js')) {
        echo '<link rel="preload" href="' . $dist_url . 'critical.js" as="script">' . "\n";
    }

    // 3. Preload Main CSS Bundle (if NOT using Critical CSS)
    if (empty($GLOBALS['lacadev_critical_css_inlined'])) {
         echo '<link rel="preload" href="' . $dist_url . 'styles/theme.css" as="style">' . "\n";
    }

    // 4. Preload important fonts (qua filter lacadev_preload_fonts)
    $fonts = apply_filters('lacadev_preload_fonts', []);

    foreach ($fonts as $font_rel) {
        $font_path = $dist_path . ltrim($font_rel, '/');
        if (file_exists($font_path)) {
            $ext = pathinfo($font_rel, PATHINFO_EXTENSION);
            $type = $ext === 'woff2' ? 'font/woff2' : ($ext === 'woff' ? 'font/woff' : 'font/ttf');
            echo '<link rel="preload" href="' . $dist_url . ltrim($font_rel, '/') . '" as="font" type="' . esc_attr($type) . '" crossorigin>' . "\n";
        }
    }
}, 1);

/**
 * Enhanced resource hints for performance
 */
add_filter('wp_resource_hints', function ($hints, $relation_type) {
    $custom_hints = apply_filters('lacadev_resource_hints', null, $relation_type);
    if (is_array($custom_hints)) {
        return $custom_hints;
    }

    if ('preconnect' === $relation_type) {
        $hints[] = 'https://fonts.gstatic.com';
        $hints[] = 'https://ajax.googleapis.com';
    }

    if ('dns-prefetch' === $relation_type) {
        $hints[] = '//fonts.googleapis.com';
        $hints[] = '//cdnjs.cloudflare.com';
    }

    if ('prefetch' === $relation_type && (is_home() || is_front_page())) {
        $page_for_posts = get_option('page_for_posts');
        if ($page_for_posts) {
            $hints[] = get_permalink($page_for_posts);
        }
    }

    return $hints;
}, 10, 2);

// NOTE: Các hooks được đăng ký trong app/hooks.php — không thêm lại ở đây để tránh duplicate.
