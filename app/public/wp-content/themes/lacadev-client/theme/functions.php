<?php
use WPEmerge\Facades\WPEmerge;
use WPEmergeTheme\Facades\Theme;

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// SECURITY & PERMISSIONS
// =============================================================================
/**
 * Define super users (Developers) who can see system menus and hidden users.
 */
add_filter('lacadev_super_user_logins', function($logins) {
    return ['lacadev']; // Add your developer username here
});

/**
 * Inject night sky background to login page
 */
add_action('login_header', function() {
    ?>
    <div class="login-night-sky" aria-hidden="true">
        <div class="alp-stars">
            <?php foreach (range(1, 80) as $i) : 
                $size = rand(15, 30) / 10;
                $left = rand(0, 10000) / 100;
                $top = rand(0, 10000) / 100;
                $dur = rand(20, 50) / 10;
                $delay = rand(0, 50) / 10;
            ?>
                <div class="alp-star" style="left:<?php echo $left; ?>%; top:<?php echo $top; ?>%; width:<?php echo $size; ?>px; height:<?php echo $size; ?>px; --d:<?php echo $dur; ?>s; animation-delay:<?php echo $delay; ?>s;"></div>
            <?php endforeach; ?>
        </div>
        <div class="alp-moon"></div>
    </div>
    <?php
});

/**
 * Custom check for super user status
 */
add_filter('lacadev_is_super_user', function($is_super, $current_user) {
    // Developers are always super users regardless of role
    $super_logins = apply_filters('lacadev_super_user_logins', ['lacadev']);
    if (in_array($current_user->user_login, $super_logins, true)) {
        return true;
    }
    return $is_super;
}, 10, 2);

// =============================================================================
// THEME INFORMATION
// =============================================================================
define('AUTHOR', [
    'name' => 'La Cà Dev',
    'email' => 'mooms.dev@gmail.com',
    'phone_number' => '0989.64.67.66',
    'website' => 'https://lacadev.com/',
    'date_started' => get_option('_theme_info_date_started'),
    'date_published' => get_option('_theme_info_date_publish'),
]);

// =============================================================================
// DIRECTORY CONSTANTS
// =============================================================================

// Directory Names
define('APP_APP_DIR_NAME', 'app');
define('APP_APP_HELPERS_DIR_NAME', 'helpers');
define('APP_APP_ROUTES_DIR_NAME', 'routes');
define('APP_APP_SETUP_DIR_NAME', 'setup');
define('APP_DIST_DIR_NAME', 'dist');
define('APP_RESOURCES_DIR_NAME', 'resources');
define('APP_THEME_DIR_NAME', 'theme');
define('APP_VENDOR_DIR_NAME', 'vendor');

// Theme Component Names
define('APP_THEME_USER_NAME', 'users');
define('APP_THEME_TAXONOMY_NAME', 'taxonomies');
define('APP_THEME_WIDGET_NAME', 'widgets');
define('APP_THEME_BLOCK_NAME', 'blocks');
define('APP_THEME_WALKER_NAME', 'walkers');

// Directory Paths
define('APP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APP_APP_DIR', APP_DIR . APP_APP_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_HELPERS_DIR', APP_APP_DIR . APP_APP_HELPERS_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_ROUTES_DIR', APP_APP_DIR . APP_APP_ROUTES_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_RESOURCES_DIR', APP_DIR . APP_RESOURCES_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_THEME_DIR', APP_DIR . APP_THEME_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_VENDOR_DIR', APP_DIR . APP_VENDOR_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_DIST_DIR', APP_DIR . APP_DIST_DIR_NAME . DIRECTORY_SEPARATOR);

// Setup Directories
define('APP_APP_SETUP_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR);
// define('APP_APP_SETUP_ECOMMERCE_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_ECOMMERCE_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_USER_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_USER_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_TAXONOMY_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_TAXONOMY_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_WIDGET_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_WIDGET_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_BLOCK_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_BLOCK_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_WALKER_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_WALKER_NAME . DIRECTORY_SEPARATOR);

// =============================================================================
// DEPENDENCIES & AUTOLOADING
// =============================================================================

// Load composer dependencies
if (file_exists(APP_VENDOR_DIR . 'autoload.php')) {
    require_once APP_VENDOR_DIR . 'autoload.php';
    \Carbon_Fields\Carbon_Fields::boot();
}

// Enable Theme shortcut
WPEmerge::alias('Theme', \WPEmergeTheme\Facades\Theme::class);

// Load helpers
require_once APP_APP_DIR . 'helpers.php';

// Load responsive image helpers (NEW - for automatic srcset/sizes)
require_once APP_APP_DIR . 'helpers/responsive-images.php';

// Bootstrap Theme
// phpcs:ignore
Theme::bootstrap(require APP_APP_DIR . 'config.php');

// Register hooks
require_once APP_APP_DIR . 'hooks.php';

// =============================================================================
// THEME SETUP
// =============================================================================
add_action('after_setup_theme', function () {
    // Load textdomain
    load_theme_textdomain('laca', APP_THEME_DIR . 'languages');

    // Load theme components
    require_once APP_APP_SETUP_DIR . 'theme-support.php';
    require_once APP_APP_SETUP_DIR . 'menus.php';

    // Load security & SEO (Phase 1 improvements)
    require_once APP_APP_SETUP_DIR . 'security.php';
    require_once APP_APP_SETUP_DIR . 'recaptcha.php';
    // contact-recaptcha.php removed - recaptcha.php handles all forms including contact
    // require_once APP_APP_SETUP_DIR . 'seo.php';

    // Load image optimization (Phase 2 improvements)
    require_once APP_APP_SETUP_DIR . 'image-optimization.php';

    // Load advanced optimization modules
    require_once APP_APP_SETUP_DIR . 'assets.php';
    require_once APP_APP_SETUP_DIR . 'performance.php';
    require_once APP_APP_SETUP_DIR . 'pwa.php';

    // Load Gutenberg blocks (Carbon Fields)
    // $blocks_dir = APP_APP_SETUP_DIR . '/blocks';
    // $block_files = glob($blocks_dir . '/*.php');
    // foreach ($block_files as $block_file) {
    //     require_once $block_file;
    // }

    // Load ReactJS Gutenberg blocks
    require_once APP_APP_SETUP_DIR . 'gutenberg-blocks.php';

});

// =============================================================================
// AUTOLOAD COMPONENTS
// =============================================================================
$folders = [
    // APP_APP_SETUP_ECOMMERCE_DIR,
    APP_APP_SETUP_TAXONOMY_DIR,
    APP_APP_SETUP_WALKER_DIR,
];

foreach ($folders as $folder) {
    $filesPath = scandir($folder);
    if ($filesPath !== false) {
        foreach ($filesPath as $item) {
            $file = $folder . $item;
            if (is_file($file)) {
                require_once $folder . $item;
            }
        }
    }
}

/**
 * Localize AJAX search data (script bundled in theme.js)
 */
function custom_ajax_search_script()
{
    wp_localize_script('theme-js-bundle', 'themeSearch', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('theme_search_nonce'),
    ]);
}
add_action('wp_enqueue_scripts', 'custom_ajax_search_script');

/**
 * Register custom query vars for search pagination
 */
function lacadev_register_search_query_vars($vars)
{
    $vars[] = 'paged_post';
    $vars[] = 'paged_page';
    $vars[] = 'paged_product';
    $vars[] = 'paged_service';
    // Add more custom post types as needed
    return $vars;
}
add_filter('query_vars', 'lacadev_register_search_query_vars');

// =============================================================================
// CUSTOM POST TYPES
// =============================================================================
// Dynamic CPT — đăng ký CPT được tạo qua admin panel (Appearance > Custom Post Types)
new \App\Features\DynamicCPT\DynamicCptManager();

// =============================================================================
// DATABASE TABLES
// =============================================================================
add_action('after_switch_theme', function () {
    \App\Databases\ContactFormTable::install();
    \App\Settings\EmailLog\EmailLogTable::install();
});

// Đảm bảo bảng luôn tồn tại
\App\Databases\ContactFormTable::install();
\App\Settings\EmailLog\EmailLogTable::install();

// =============================================================================
// COMMENTS CALLBACK
// =============================================================================
function lacadev_custom_comments_callback( $comment, $args, $depth ) {
    $GLOBALS['comment'] = $comment;
    
    $tag = ( isset($args['style']) && 'b' === $args['style'] ) ? 'b' : 'li';
    $add_below = 'div-comment';
    ?>
    <<?php echo $tag; ?> <?php comment_class( empty( $args['has_children'] ) ? 'custom-comment' : 'parent custom-comment' ); ?> id="comment-<?php comment_ID(); ?>">
        <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
            <div class="comment-meta">
                <div class="comment-author vcard">
                    <?php 
                    $avatar_size = $args['avatar_size'] ?? 48;
                    if ( 0 != $avatar_size ) {
                        echo get_avatar( $comment, $avatar_size );
                    }
                    ?>
                    <?php printf( '<span class="author-name">%s</span>', get_comment_author_link( $comment ) ); ?>
                </div><!-- .comment-author -->

                <div class="comment-metadata">
                    <a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
                        <time datetime="<?php comment_time( 'c' ); ?>">
                            <?php printf( __( '%s ago', 'laca' ), human_time_diff( get_comment_time('U'), current_time('timestamp') ) ); ?>
                        </time>
                    </a>
                </div><!-- .comment-metadata -->
            </div><!-- .comment-meta -->

            <?php if ( '0' == $comment->comment_approved ) : ?>
            <p class="comment-awaiting-moderation"><?php _e( 'Your comment is awaiting moderation.', 'laca' ); ?></p>
            <?php endif; ?>

            <div class="comment-content">
                <?php comment_text(); ?>
            </div><!-- .comment-content -->

            <div class="comment-actions">
                <?php
                comment_reply_link(
                    array_merge(
                        $args,
                        array(
                            'add_below' => $add_below,
                            'depth'     => $depth,
                            'max_depth' => $args['max_depth'] ?? 5,
                            'before'    => '<div class="reply">',
                            'after'     => '</div>',
                        )
                    )
                );
                ?>
            </div>
        </article><!-- .comment-body -->
    <?php
}
