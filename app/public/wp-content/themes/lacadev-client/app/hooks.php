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
 * Body class: xuất class laca-layout-fluid hoặc laca-layout-boxed theo 3 tầng (site -> trang).
 */
add_filter('body_class', function (array $classes): array {
    $mode = function_exists('laca_get_current_layout_mode') ? laca_get_current_layout_mode() : 'fluid';
    $classes[] = 'laca-layout-' . $mode;
    return $classes;
});

/**
 * Filter render_block_data hỗ trợ preview block override khi test trên local / admin
 * e.g. ?laca_block_override=boxed (không ghi DB, chỉ phục vụ layout audit).
 */
add_filter('render_block_data', function (array $parsed_block): array {
    if (is_admin()) {
        return $parsed_block;
    }
    $is_local = function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local';
    if (($is_local || current_user_can('manage_options')) && isset($_GET['laca_block_override'])) {
        $target_mode = sanitize_key($_GET['laca_block_override']);
        if (in_array($target_mode, ['fluid', 'boxed'], true)) {
            if (isset($parsed_block['blockName']) && strpos($parsed_block['blockName'], 'lacadev/') === 0) {
                if (!isset($GLOBALS['__laca_block_override_applied'])) {
                    $parsed_block['attrs']['containerMode'] = $target_mode;
                    $GLOBALS['__laca_block_override_applied'] = true;
                }
            }
        }
    }
    return $parsed_block;
});

/**
 * Keep Laca Admin submenu grouped and ordered from one place.
 */
add_action('init', function () {
    if (class_exists('\App\Settings\LacaAdmin\LacaAdminMenuOrganizer')) {
        (new \App\Settings\LacaAdmin\LacaAdminMenuOrganizer())->register();
    }
});

/**
 * Google Services (GSC & GA4 standalone module + Laca Admin pages).
 */
add_action('init', function () {
    if (class_exists('\App\Features\GoogleServices\GoogleServices')) {
        \App\Features\GoogleServices\GoogleServices::boot();
    }
});

/**
 * UI Kit trang mẫu (style guide sống) để review.
 */
add_action('init', function () {
    if (is_admin() && class_exists('\App\Settings\LacaAdmin\UiKitPage')) {
        (new \App\Settings\LacaAdmin\UiKitPage())->register();
    }
});

/**
 * Checklist phát hành production — đầu trang Laca Admin (chỉ Super User).
 */
add_action('init', function () {
    if (is_admin() && class_exists('\App\Settings\LacaAdmin\ReleaseChecklist')) {
        (new \App\Settings\LacaAdmin\ReleaseChecklist())->register();
    }
});

// Tra DNS (SPF/DMARC) chạy nền qua WP-Cron — request cron không phải is_admin() nên đăng ký riêng.
add_action('laca_release_checklist_dns', ['\App\Settings\LacaAdmin\ReleaseChecklist', 'refreshDns']);

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
 * Carbon Fields AJAX Auto-Save Handler for Theme Options (Laca Theme & Laca Admin).
 */
add_action('carbon_fields_theme_options_container_saved', static function ($user_data, $container) {
    if (!empty($_POST['carbon_fields_auto_save']) || wp_doing_ajax()) {
        wp_send_json_success([
            'message'   => __('Đã tự động lưu cài đặt thành công.', 'laca'),
            'timestamp' => current_time('H:i:s'),
        ]);
    }
}, 10, 2);

/**
 * Theme Updater — tự động check & nhận update từ Hub LacaDev.
 * Khởi tạo trong Admin, WP-CLI, WP-Cron và REST API (phục vụ remote-update).
 */
$lacaBootThemeUpdater = static function (): void {
    if (!class_exists('\App\Settings\ThemeUpdater')) {
        return;
    }

    $defaultJsonUrl = wp_get_environment_type() === 'local'
        ? 'http://lacadev.local/theme-updates/lacadev-client.json'
        : 'https://lacadev.com/theme-updates/lacadev-client.json';
    $updateJsonUrl = (string) apply_filters(
        'laca_theme_update_json_url',
        $defaultJsonUrl
    );

    \App\Settings\ThemeUpdater::boot(
        'lacadev-client/theme',
        $updateJsonUrl
    );
};

if (is_admin() || (defined('WP_CLI') && \WP_CLI) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
    add_action('init', $lacaBootThemeUpdater);
}
add_action('rest_api_init', $lacaBootThemeUpdater, 5);

if (is_admin()) {
    add_action('init', static function () {
        if (class_exists('\App\Settings\ChildBuildNotice')) {
            (new \App\Settings\ChildBuildNotice())->register();
        }

        if (class_exists('\App\Widgets\BlockSyncWidget')) {
            new \App\Widgets\BlockSyncWidget();
        }

        // Block Marketplace — trang "Laca Theme → Block Marketplace"
        if (class_exists('\App\Settings\BlockMarketplace')) {
            new \App\Settings\BlockMarketplace();
        }
    });
}

/**
 * LacaDev Tracker Client — gửi logs & alerts về lacadev CMS.
 */
add_action('init', static function () {
    if (class_exists('\App\Settings\LacaDevTrackerClient')) {
        new \App\Settings\LacaDevTrackerClient();
    }

    if (class_exists('\App\Features\ClientSupport\ClientSupportManager')) {
        (new \App\Features\ClientSupport\ClientSupportManager())->init();
    }
    if (class_exists('\App\Security\ClientRoleManager')) {
        (new \App\Security\ClientRoleManager())->init();
    }
});

/**
 * Dọn cron mồ côi `laca_fim_scan` để lại sau khi gỡ App\Features\ClientTracker\Tracker
 */
add_action('init', function () {
    if (!get_option('_laca_tracker_fim_cron_cleaned')) {
        wp_clear_scheduled_hook('laca_fim_scan');
        update_option('_laca_tracker_fim_cron_cleaned', 1, false);
    }
}, 1);

/**
 * Block Sync Receiver — REST API endpoint nhận blocks từ lacadev.com
 */
add_action('init', static function () {
    if (class_exists('\App\Settings\BlockSyncReceiver')) {
        new \App\Settings\BlockSyncReceiver();
    }
    if (class_exists('\App\Settings\BlockCatalogProvider')) {
        new \App\Settings\BlockCatalogProvider();
    }
}, 5);

/**
 * ============================================================================
 * HUB ONLY: Database Tables, Project Endpoints & Project Management Hooks
 * ============================================================================
 */
if (!defined('LACADEV_HUB_SCHEMA_VERSION')) {
    define('LACADEV_HUB_SCHEMA_VERSION', '1.3.0');
}

function laca_install_project_manager_tables(): void
{
    if (class_exists('\App\Databases\DbVersionManager')) {
        \App\Databases\DbVersionManager::maybeInstall(
            LACADEV_HUB_SCHEMA_VERSION,
            'lacadev_hub_db_version',
            [
                fn() => class_exists('\App\Databases\ProjectLogTable') && \App\Databases\ProjectLogTable::install(),
                fn() => class_exists('\App\Databases\ProjectAlertTable') && \App\Databases\ProjectAlertTable::install(),
                fn() => class_exists('\App\Databases\ContactFormTable') && \App\Databases\ContactFormTable::install(true),
                fn() => class_exists('\App\Settings\EmailLog\EmailLogTable') && \App\Settings\EmailLog\EmailLogTable::install(),
                fn() => class_exists('\App\Settings\LoginLog\LoginLogTable') && \App\Settings\LoginLog\LoginLogTable::install(),
                fn() => class_exists('\App\Databases\UptimeLogTable') && \App\Databases\UptimeLogTable::install(),
            ]
        );
    }
}
add_action('after_switch_theme', 'laca_install_project_manager_tables');
add_action('after_setup_theme', 'laca_install_project_manager_tables', 1);

add_action('init', function () {
    if (class_exists('\App\Settings\LacaTools\ProjectPdfExporter')) {
        (new \App\Settings\LacaTools\ProjectPdfExporter())->init();
    }
    
    if (class_exists('\App\Settings\LacaTools\TrackerEndpointHandler')) {
        (new \App\Settings\LacaTools\TrackerEndpointHandler())->init();
    }

    if (class_exists('\App\Settings\LacaTools\ClientPortalEndpoint')) {
        (new \App\Settings\LacaTools\ClientPortalEndpoint())->init();
    }

    if (class_exists('\App\Settings\LacaTools\ClientRequestHandler')) {
        (new \App\Settings\LacaTools\ClientRequestHandler())->init();
    }

    if (class_exists('\App\Settings\LacaTools\BlockCatalogHub')) {
        (new \App\Settings\LacaTools\BlockCatalogHub())->init();
    }

    if (class_exists('\App\Settings\LacaTools\ThemePackageEndpoint')) {
        (new \App\Settings\LacaTools\ThemePackageEndpoint())->init();
    }

    if (class_exists('\App\Settings\LacaTools\SiteHealthChecker')) {
        (new \App\Settings\LacaTools\SiteHealthChecker())->init();
    }

    if (class_exists('\App\Settings\LacaTools\ProjectTrackerGenerator')) {
        (new \App\Settings\LacaTools\ProjectTrackerGenerator())->init();
    }
});

/**
 * Auto-generate Tracker Secret Key khi tạo/lưu project lần đầu
 * Hook: save_post_project — chỉ chạy cho post_type=project
 */
add_action('save_post_project', 'laca_auto_generate_tracker_key', 20, 2);

function laca_auto_generate_tracker_key(int $postId, \WP_Post $post): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ($post->post_status === 'trash' || $post->post_status === 'auto-draft') {
        return;
    }
    if (!current_user_can('edit_post', $postId)) {
        return;
    }

    $existingKey = get_post_meta($postId, '_tracker_secret_key', true);
    if (empty($existingKey)) {
        $secretKey = bin2hex(random_bytes(32));
        update_post_meta($postId, '_tracker_secret_key', $secretKey);
    }
}

/**
 * Enqueue nonce cho AJAX của Project Manager (admin only)
 */
add_action('admin_enqueue_scripts', 'laca_project_manager_admin_scripts');

function laca_project_manager_admin_scripts(string $hook): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'project') {
        return;
    }

    $data = [
        'ajaxurl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('laca_project_manager'),
        'post_id'   => (int) (get_the_ID() ?: ($_GET['post'] ?? 0)),
        'i18n'      => [
            'confirm_delete' => __('Bạn có chắc muốn xoá?', 'laca'),
            'resolving'      => __('Đang xử lý...', 'laca'),
            'deleted'        => __('Đã xoá', 'laca'),
            'resolved'       => __('Đã đánh dấu xử lý', 'laca'),
        ],
    ];
    wp_add_inline_script(
        'jquery',
        'var lacaProjectManager = ' . wp_json_encode($data) . ';',
        'before'
    );
}

/**
 * Thêm CSS cho admin list view của project
 */
add_action('admin_head', 'laca_project_list_admin_styles');

function laca_project_list_admin_styles(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'project') {
        return;
    }
    ?>
    <style>
        .column-laca_status  { width: 120px; }
        .column-laca_expiry  { width: 160px; }
        .column-laca_alerts  { width: 80px; text-align: center; }
        .column-laca_client  { width: 150px; }
        .column-laca_domain  { width: 150px; }
        .wp-list-table .column-laca_expiry { font-size: 12px; line-height: 1.4; }
    </style>
    <?php
}

/**
 * ============================================================================
 * CONTACT FORM — Frontend AJAX + Shortcode
 * ============================================================================
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
 * Login Log
 */
add_action('init', function () {
    if (class_exists('\App\Settings\LoginLog\LoginLogManager')) {
        (new \App\Settings\LoginLog\LoginLogManager())->init();
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
 * Post Audio - Nghe bài viết
 */
add_action('init', function () {
    if (class_exists('\App\Features\PostAudio\PostAudioManager')) {
        (new \App\Features\PostAudio\PostAudioManager())->init();
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
 * Chống brute-force (khoá IP sau nhiều lần đăng nhập sai) + chặn dò username.
 * Đăng ký vô điều kiện, sớm (trước khi wp-login.php/XML-RPC xác thực), độc lập với Discord Hub.
 */
add_action('after_setup_theme', function () {
    if (class_exists('\App\Settings\Security\LoginGuard')) {
        (new \App\Settings\Security\LoginGuard())->register();
    }
    if (class_exists('\App\Settings\Security\UserEnumerationGuard')) {
        (new \App\Settings\Security\UserEnumerationGuard())->register();
    }
    if (class_exists('\App\Settings\Security\CoreInfoFileCleanup')) {
        (new \App\Settings\Security\CoreInfoFileCleanup())->register();
    }
    if (class_exists('\App\Settings\Security\SvgUploadGuard')) {
        (new \App\Settings\Security\SvgUploadGuard())->register();
    }
    if (class_exists('\App\Settings\Security\LoginHoneypot')) {
        (new \App\Settings\Security\LoginHoneypot())->register();
    }
    if (class_exists('\App\Settings\Security\LoginPolicy')) {
        (new \App\Settings\Security\LoginPolicy())->register();
    }
    if (class_exists('\App\Settings\Security\BreachedPasswordGuard')) {
        (new \App\Settings\Security\BreachedPasswordGuard())->register();
    }
    if (class_exists('\App\Settings\Security\AdminLoginAlert')) {
        (new \App\Settings\Security\AdminLoginAlert())->register();
    }
}, 15);

/**
 * Discord Hub — đẩy lead/hoạt động/cảnh báo bảo mật về Discord (Laca Admin → Discord Hub).
 */
add_action('after_setup_theme', function () {
    try {
        if (class_exists('\App\Features\DiscordHub\DiscordHub')) {
            (new \App\Features\DiscordHub\DiscordHub())->boot();
        }
    } catch (\Throwable $e) {
        error_log('[laca-discord-hub] Không khởi động được: ' . $e->getMessage());
    }

    try {
        if (class_exists('\App\Features\LacaSeo\LacaSeo')) {
            \App\Features\LacaSeo\LacaSeo::boot();
        }
    } catch (\Throwable $e) {
        error_log('[laca-seo] Không khởi động được: ' . $e->getMessage());
    }

    try {
        if (class_exists('\App\Features\Dev\LocalMediaProxy')) {
            \App\Features\Dev\LocalMediaProxy::boot();
        }
    } catch (\Throwable $e) {
        error_log('[laca-dev-media-proxy] Không khởi động được: ' . $e->getMessage());
    }
}, 20);

/**
 * Đăng ký lệnh WP-CLI đồng bộ Production -> Local (wp laca ...)
 */
if (defined('WP_CLI') && \WP_CLI) {
    if (class_exists('\App\Cli\LacaSyncCommand')) {
        \WP_CLI::add_command('laca', \App\Cli\LacaSyncCommand::class);
    }
    if (class_exists('\App\Cli\LacaThemeCommand')) {
        \WP_CLI::add_command('laca-theme', \App\Cli\LacaThemeCommand::class);
    }
}

/**
 * Bảo vệ tài khoản Super User mặc định (lacadev)
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
    
    $nonce = wp_create_nonce('update_post_thumbnail');
    $nonce_attr = esc_attr($nonce);
    $post_id_attr = absint($postId);
    
    $thumbnailUrl = get_the_post_thumbnail_url($postId, 'thumbnail');
    
    if ($thumbnailUrl) {
        echo "<div class='thumbnail-wrap'>";
        echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
        echo "<img src='" . esc_url($thumbnailUrl) . "' class='thumbnail-preview' alt='Thumbnail'/>";
        echo "</a>";
        echo "<a class='remove-thumbnail' href='javascript:void(0)' data-trigger-remove-thumbnail data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}' title='Remove thumbnail'>
                <svg viewBox='0 0 12 12'>
                    <path d='M10 2L2 10M2 2L10 10' stroke='#ffffff' stroke-width='2' stroke-linecap='round'/>
                </svg>
            </a>";
        echo "</div>";
    } else {
        echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
        echo "<div class='no-image-text'><span>Choose</span><span>image</span></div>";
        echo "</a>";
    }
}
add_action('manage_page_posts_custom_column', 'app_render_featured_image_column', 10, 2);
add_action('manage_post_posts_custom_column', 'app_render_featured_image_column', 10, 2);

/**
 * Expose WooCommerce category thumbnail via WP core REST API.
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

/**
 * Register Theme Options Snapshot cache and Option Defaults.
 */
if (class_exists(\App\Helpers\ThemeOptionsCache::class)) {
    \App\Helpers\ThemeOptionsCache::register();
}
if (class_exists(\App\Helpers\OptionDefaults::class)) {
    \App\Helpers\OptionDefaults::register();
}
if (class_exists(\App\Features\Performance\PostViewTracker::class)) {
    \App\Features\Performance\PostViewTracker::register();
}
if (class_exists(\App\Features\Performance\FreshNonceHandler::class)) {
    \App\Features\Performance\FreshNonceHandler::register();
}
if (class_exists(\App\Features\Performance\CacheCompat::class)) {
    \App\Features\Performance\CacheCompat::register();
}
