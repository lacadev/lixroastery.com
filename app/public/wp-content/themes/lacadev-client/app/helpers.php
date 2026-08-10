<?php

/**
 * Load helpers.
 *
 * @package WPEmergeTheme
 */

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Load base helpers.
 */
require_once APP_APP_HELPERS_DIR . 'shims.php';
require_once APP_APP_HELPERS_DIR . 'admin.php';
require_once APP_APP_HELPERS_DIR . 'content.php';
require_once APP_APP_HELPERS_DIR . 'login.php';
require_once APP_APP_HELPERS_DIR . 'shortcodes.php';
require_once APP_APP_HELPERS_DIR . 'title.php';
require_once APP_APP_HELPERS_DIR . 'functions.php';
require_once APP_APP_HELPERS_DIR . 'ajax.php';
require_once APP_APP_HELPERS_DIR . 'hooks.php';
require_once APP_APP_HELPERS_DIR . 'template_tags.php';
require_once APP_APP_HELPERS_DIR . 'woocommerce.php';
require_once APP_APP_HELPERS_DIR . 'carbon_fields.php';
require_once APP_APP_SETUP_DIR . 'users/auth.php';
require_once get_template_directory() . '/setup/admin-ui.php';

/**
 * Require custom helper files here.
 */
new \App\Settings\RequirePlugins();
new \App\Settings\AdminSettings();
new \App\Settings\AutoDownloadImage();
new \App\Settings\ThemeSettings();
new \App\Settings\LacaTools\Optimize();
// Dashboard widgets và LacaTools Management: load class files từ parent theme nếu chưa có.
// Child theme dùng autoloader riêng (App\ → app/src/) nên cần require tường minh.
if (!class_exists(\App\Settings\LacaTools\ManagementExperience::class)) {
    $parentLacaTools = get_template_directory() . '/app/src/Settings/LacaTools';
    $mgmtDir         = $parentLacaTools . '/Management';
    foreach ([
        $mgmtDir . '/ContentAuditService.php',
        $mgmtDir . '/MediaService.php',
        $mgmtDir . '/DashboardWidgets.php',
        $mgmtDir . '/ListTableEnhancements.php',
        $mgmtDir . '/AdminUxService.php',
        $parentLacaTools . '/AIChatHandler.php',
        $parentLacaTools . '/AITranslationParser.php',
        $parentLacaTools . '/AITranslationHandler.php',
        $parentLacaTools . '/AITranslationManager.php',
        $parentLacaTools . '/ProjectReportsManager.php',
        $parentLacaTools . '/ManagementExperience.php',
    ] as $classFile) {
        if (file_exists($classFile)) {
            require_once $classFile;
        }
    }
}
new \App\Settings\LacaTools\ManagementExperience();

// phpcs:disable
/**
 * Annoyed that you have to constantly add helper file require statements? Uncomment the bellow snippet!
 *
 * Automatically require all helper files in the app/helpers directory (non-recursive).
 */
/*
$helpers = glob( APP_APP_HELPERS_DIR . '*.php' );
foreach ( $helpers as $helper ) {
	if ( ! is_file( $helper ) ) {
		continue;
	}

	require_once $helper;
}
*/
// phpcs:enable