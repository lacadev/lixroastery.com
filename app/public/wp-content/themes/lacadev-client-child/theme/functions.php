<?php
/**
 * Child Theme Functions
 *
 * Child theme của lacadev-client (WPEmerge).
 * Parent theme đã bootstrap WPEmerge — child theme chỉ cần thêm overrides.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// CHILD THEME DIRECTORY CONSTANTS
// (dùng prefix CHILD_ để tránh conflict với parent constants)
// =============================================================================

define('CHILD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('CHILD_THEME_DIR', CHILD_DIR . 'theme' . DIRECTORY_SEPARATOR);
define('CHILD_APP_DIR', CHILD_DIR . 'app' . DIRECTORY_SEPARATOR);
define('CHILD_RESOURCES_DIR', CHILD_DIR . 'resources' . DIRECTORY_SEPARATOR);
define('CHILD_DIST_DIR', CHILD_DIR . 'dist' . DIRECTORY_SEPARATOR);
define('CHILD_THEME_SETUP_DIR', CHILD_THEME_DIR . 'setup' . DIRECTORY_SEPARATOR);

// =============================================================================
// CHILD THEME SETUP
// after_setup_theme chạy SAU khi parent's functions.php đã load xong
// =============================================================================

add_action('after_setup_theme', function () {
    // Load child theme textdomain (ghi đè nếu cần)
    load_child_theme_textdomain('laca', CHILD_THEME_DIR . 'languages');

    // Load child assets
    require_once CHILD_THEME_SETUP_DIR . 'assets.php';
    
    // Load child theme options (Carbon Fields)
    require_once CHILD_THEME_SETUP_DIR . 'theme-options.php';

}, 20); // priority 20 — sau parent (10)

// =============================================================================
// CHILD HOOKS
// =============================================================================

require_once CHILD_APP_DIR . 'hooks.php';
