<?php

/**
 * Carbon Fields Helper Functions
 *
 * @package WPEmergeTheme
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

    if (file_exists(APP_APP_SETUP_DIR . 'category_meta.php')) {
        include_once APP_APP_SETUP_DIR . 'category_meta.php';
    }

    if (file_exists(APP_APP_SETUP_DIR . 'contact_page_meta.php')) {
        include_once APP_APP_SETUP_DIR . 'contact_page_meta.php';
    }
}

/**
 * Get a theme option with a default value.
 *
 * @param  string $name
 * @param  mixed  $default
 * @return mixed
 */
function app_get_theme_option( $name, $default = null ) {
    if ( function_exists( 'laca_theme_option' ) ) {
        $value = laca_theme_option( $name, $default );
        if ( ! empty( $value ) || is_null( $default ) ) {
            return $value;
        }
        return $default;
    }

    $value = function_exists( 'carbon_get_theme_option' ) ? carbon_get_theme_option( $name ) : null;

    if ( empty( $value ) && ! is_null( $default ) ) {
        return $default;
    }

    return $value;
}
