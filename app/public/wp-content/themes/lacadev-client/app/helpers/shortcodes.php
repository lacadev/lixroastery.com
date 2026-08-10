<?php
/**
 * Custom Shortcodes.
 *
 * Here, you can register Custom Shortcode for use in the Theme.
 *
 * @link https://developer.wordpress.org/reference/functions/add_shortcode/
 * @link https://developer.wordpress.org/reference/functions/shortcode_atts/
 *
 * @package WPEmergeTheme
 */

/**
 * Render the current year.
 */
function app_shortcode_year() {
	return date( 'Y' );
}
add_shortcode( 'year', 'app_shortcode_year' );