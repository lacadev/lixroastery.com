<?php
/**
 * Declare all your actions and filters here.
 *
 * @package WPEmergeTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ------------------------------------------------------------------------
 * WordPress
 * ------------------------------------------------------------------------
 */

/**
 * Assets
 */
add_action('wp_enqueue_scripts', 'app_action_theme_enqueue_assets');
add_action('admin_enqueue_scripts', 'app_action_admin_enqueue_assets');
add_action('login_enqueue_scripts', 'app_action_login_enqueue_assets');
add_action('enqueue_block_editor_assets', 'app_action_editor_enqueue_assets');
add_action('wp_head', 'app_action_add_favicon', 5);
add_action('login_head', 'app_action_add_favicon', 5);
add_action('admin_head', 'app_action_add_favicon', 5);
add_filter('upload_dir', 'app_filter_fix_upload_dir_url_schema');

/**
 * Keep Laca Admin submenu grouped and ordered from one place.
 */
add_action('init', function () {
    if (class_exists('\App\Settings\LacaAdmin\LacaAdminMenuOrganizer')) {
        (new \App\Settings\LacaAdmin\LacaAdminMenuOrganizer())->register();
    }
});

/**
 * Xếp mọi custom post type (tĩnh lẫn tạo qua Dynamic CPT) nằm liền kề
 * ngay sau "Laca Theme" trong sidebar, thay vì rải rác theo menu_position.
 */
add_action('init', function () {
    if (class_exists('\App\Settings\LacaAdmin\CptMenuGrouper')) {
        (new \App\Settings\LacaAdmin\CptMenuGrouper())->register();
    }
});

/**
 * Content
 */
add_filter('excerpt_more', 'app_filter_excerpt_more');
add_filter('excerpt_length', 'app_filter_excerpt_length', 999);
add_filter('the_content', 'app_filter_fix_shortcode_empty_paragraphs');

// Attach all suitable hooks from `the_content` on `app_content`.
add_filter('app_content', 'do_shortcode', 9);
add_filter('app_content', 'app_filter_fix_shortcode_empty_paragraphs', 10);
add_filter('app_content', 'wptexturize', 10);
add_filter('app_content', 'wpautop', 10);
add_filter('app_content', 'shortcode_unautop', 10);
add_filter('app_content', 'prepend_attachment', 10);
add_filter('app_content', 'wp_make_content_images_responsive', 10);
add_filter('app_content', 'convert_smilies', 20);

/**
 * Login
 */
add_filter('login_headerurl', 'app_filter_login_headerurl');
if (version_compare(get_bloginfo('version'), '5.2', '<')) {
    add_filter('login_headertext', 'app_filter_login_headertext');
}
add_filter('login_headertext', 'app_filter_login_headertext');
add_filter('login_message', 'app_login_google_admin_message');

/**
 * ------------------------------------------------------------------------
 * External Libraries and Plugins.
 * ------------------------------------------------------------------------
 */

/**
 * Carbon Fields
 */
// add_action( 'after_setup_theme', 'app_bootstrap_carbon_fields', 100 );
add_action('carbon_fields_register_fields', 'app_bootstrap_carbon_fields_register_fields');

/**
 * Theme Updater — tự động check & nhận update từ clients.lacadev.com
 * Chỉ chạy ở admin để tiết kiệm tài nguyên frontend
 */
if (is_admin()) {
    add_action('init', static function () {
        new \App\Settings\ThemeUpdater(
            'lacadev-client/theme',
            'https://clients.lacadev.com/theme-updates/lacadev-client.json'
        );
        new \App\Widgets\BlockSyncWidget();

        // Block Marketplace — trang "Laca Theme → Block Marketplace" cho
        // site khách browse + yêu cầu đồng bộ block từ clients.lacadev.com
        // (qua hub). Chỉ cần đăng ký AJAX handler ở đây, phần render page
        // được gọi trực tiếp từ theme-options.php (child theme).
        if (class_exists('\App\Settings\BlockMarketplace')) {
            new \App\Settings\BlockMarketplace();
        }
    });
}

/**
 * LacaDev Tracker Client — gửi logs & alerts về lacadev CMS.
 * Phải đăng ký vô điều kiện (không bọc is_admin()) vì cron (wp-cron.php)
 * và REST request (/laca/v1/remote-update) không đi qua wp-admin nên
 * is_admin() luôn false ở đó — nếu bọc trong is_admin(), cron và route
 * REST của tracker sẽ không bao giờ đăng ký được.
 */
add_action('init', static function () {
    new \App\Settings\LacaDevTrackerClient();
});

/**
 * Dọn cron mồ côi `laca_fim_scan` để lại sau khi gỡ App\Features\ClientTracker\Tracker
 * (đã gộp chức năng theme_switched + FIM sâu vào LacaDevTrackerClient — xem
 * doc/TRACKER_HUB_CLIENT_SYNC.md, Giai đoạn 3). Chỉ chạy 1 lần rồi tự đánh dấu,
 * không chạy lại mỗi request.
 */
add_action('init', function () {
    if (!get_option('_laca_tracker_fim_cron_cleaned')) {
        wp_clear_scheduled_hook('laca_fim_scan');
        update_option('_laca_tracker_fim_cron_cleaned', 1, false);
    }
}, 1);

/**
 * Block Sync Receiver — REST API endpoint nhận blocks từ lacadev.com
 * Chạy cả frontend để REST API hoạt động đúng
 */
add_action('init', static function () {
    new \App\Settings\BlockSyncReceiver();
    // BlockAutoloader disabled — blocks are already registered by
    // lacadev_child_register_synced_blocks() at init priority 15.
    // Having both causes duplicate "already registered" notices → "headers already sent" → admin 403/404.
    // new \App\Settings\BlockAutoloader();

    // Block Catalog Provider — REST API đọc-chỉ phục vụ danh mục block cho
    // hub (lacadev.com) đọc, dùng cho tính năng "site khách yêu cầu đồng bộ
    // block". Chạy trên mọi site dùng theme này, tự bảo vệ bằng Catalog Key
    // riêng — chỉ site nào có key đúng (clients.lacadev.com) mới thực sự được
    // hub gọi tới trong thực tế.
    new \App\Settings\BlockCatalogProvider();
}, 5);

/**
 * CONTACT FORM — Frontend AJAX + Shortcode
 */
add_action('init', function () {
    if (class_exists('\App\Features\ContactForm\ContactFormAjaxHandler')) {
        (new \App\Features\ContactForm\ContactFormAjaxHandler())->init();
    }
}, 10);

/**
 * Maintenance Mode
 */
add_action('init', function () {
    if (class_exists('\App\Settings\MaintenanceModeManager')) {
        (new \App\Settings\MaintenanceModeManager())->init();
    }
}, 1);

/**
 * Email Log
 */
add_action('init', function () {
    if (class_exists('\App\Settings\EmailLog\EmailLogManager')) {
        (new \App\Settings\EmailLog\EmailLogManager())->init();
    }
});

/**
 * Related Posts
 */
add_action('init', function () {
    if (class_exists('\App\Features\RelatedPosts')) {
        (new \App\Features\RelatedPosts())->init();
    }
});

/**
 * Exit Intent Popup
 */
add_action('init', function () {
    if (class_exists('\App\Features\ExitIntentPopup')) {
        (new \App\Features\ExitIntentPopup())->init();
    }
});

/**
 * Frontend Chatbot
 */
add_action('init', function () {
    if (class_exists('\App\Features\FrontendChatbot\FrontendChatbotHandler')) {
        (new \App\Features\FrontendChatbot\FrontendChatbotHandler())->init();
    }
});

/**
 * Security — Custom Login, 2FA, Security Manager
 */
add_action('init', function () {
    if (class_exists('\App\Settings\Security\CustomLoginManager')) {
        new \App\Settings\Security\CustomLoginManager();
    }
}, 1);

add_action('init', function () {
    if (class_exists('\App\Settings\Security\TwoFactorAuth')) {
        new \App\Settings\Security\TwoFactorAuth();
    }
});

add_action('init', function () {
    if (class_exists('\App\Settings\Security\SecurityManager')) {
        (new \App\Settings\Security\SecurityManager())->init();
    }
});

/**
 * Bảo vệ tài khoản Super User mặc định (lacadev) — không cho user khác xoá
 * hoặc đổi quyền, áp dụng cả REST API nên không được gate trong is_admin().
 */
add_action('init', function () {
    if (class_exists('\App\Settings\Security\SuperUserGuard')) {
        (new \App\Settings\Security\SuperUserGuard())->init();
    }
});

/**
 * Pages/Posts list table: Add Thumbnail column
 */
function app_add_featured_image_column($cols) {
    if (is_array($cols)) {
        $cols = insertArrayAtPosition($cols, ['featured_image' => 'Image'], 1);
    }
    return $cols;
}
add_filter('manage_page_posts_columns', 'app_add_featured_image_column', 9999);
add_filter('manage_post_posts_columns', 'app_add_featured_image_column', 9999);

function app_render_featured_image_column($column, $postId) {
    if ($column !== 'featured_image') {
        return;
    }
    
    // Generate nonce for CSRF protection
    $nonce = wp_create_nonce('update_post_thumbnail');
    $nonce_attr = esc_attr($nonce);
    $post_id_attr = absint($postId);
    
    $thumbnailUrl = get_the_post_thumbnail_url($postId, 'thumbnail');
    
    if ($thumbnailUrl) {
        // Has thumbnail - show image with remove button (same as Service)
        echo "<div class='thumbnail-wrap'>";
        echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
        echo "<img src='" . esc_url($thumbnailUrl) . "' class='thumbnail-preview' alt='Thumbnail'/>";
        echo "</a>";
        // Remove button (X)
        echo "<a class='remove-thumbnail' href='javascript:void(0)' data-trigger-remove-thumbnail data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}' title='Remove thumbnail'>
                <svg viewBox='0 0 12 12'>
                    <path d='M11 1L1 11M1 1l10 10' stroke='currentColor' stroke-width='2' stroke-linecap='round'/>
                </svg>
            </a>";
        echo "</div>";
    } else {
        // No thumbnail - show WordPress-style "Set featured image" link (same as Service)
        echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
        echo "<div class='no-image-text'>Choose image</div>";
        echo "</a>";
    }
}
add_action('manage_page_posts_custom_column', 'app_render_featured_image_column', 10, 2);
add_action('manage_post_posts_custom_column', 'app_render_featured_image_column', 10, 2);

/**
 * Expose WooCommerce category thumbnail via WP core REST API.
 * Allows Gutenberg editor to load category images through @wordpress/data.
 */
add_action('rest_api_init', function () {
    register_rest_field('product_cat', 'cat_image_url', [
        'get_callback' => function (array $term) {
            $thumb_id = get_term_meta($term['id'], 'thumbnail_id', true);
            if (!$thumb_id) {
                return '';
            }
            $src = wp_get_attachment_image_src((int) $thumb_id, 'woocommerce_single');
            return $src ? esc_url($src[0]) : '';
        },
        'update_callback' => null,
        'schema' => [
            'type'        => 'string',
            'description' => 'WooCommerce category thumbnail URL',
            'context'     => ['view', 'embed'],
        ],
    ]);
});
