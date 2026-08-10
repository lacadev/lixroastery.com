<?php
/**
 * Load Carbon Fields.
 *
 * @package WPEmergeCli
 */

/**
 * Bootstrap Carbon Fields container definitions.
 */
function app_bootstrap_carbon_fields_register_fields() {
    // Check if the child theme's theme-options.php exists, even if the parent theme is currently active.
    $child_theme_options = get_theme_root() . '/lacadev-client-child/theme/setup/theme-options.php';
    if (file_exists($child_theme_options)) {
        include_once $child_theme_options;
    } else {
        $theme_options = locate_template('theme/setup/theme-options.php');
        if ($theme_options) {
            include_once $theme_options;
        } else {
            $parent_theme_options = APP_APP_SETUP_DIR . 'theme-options.php';
            if (file_exists($parent_theme_options)) {
                include_once $parent_theme_options;
            }
        }
    }
    include_once APP_APP_SETUP_DIR . 'category_meta.php';
    include_once APP_APP_SETUP_DIR . 'contact_page_meta.php';
}

/**
 * Filter Google Maps API key for Carbon Fields.
 */
function app_filter_carbon_fields_google_maps_api_key() {
    return carbon_get_theme_option('crb_google_maps_api_key');
}
