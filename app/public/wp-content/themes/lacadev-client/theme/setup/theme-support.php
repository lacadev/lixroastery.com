<?php
/**
 * Declare theme functionality support.
 *
 * @link    https://developer.wordpress.org/reference/functions/add_theme_support/
 *
 * @hook    after_setup_theme
 * @package WPEmergeTheme
 */

use WPEmergeTheme\Facades\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Support automatic feed links.
 *
 * @link https://codex.wordpress.org/Automatic_Feed_Links
 */
add_theme_support('automatic-feed-links');

/**
 * Support post thumbnails.
 *
 * @link https://codex.wordpress.org/Post_Thumbnails
 */
add_theme_support('post-thumbnails');

/**
 * Support document title tag.
 *
 * @link https://codex.wordpress.org/Title_Tag
 */
add_theme_support('title-tag');

/**
 * Support menus.
 *
 * @link https://developer.wordpress.org/reference/functions/add_theme_support/
 */
add_theme_support('menus');

/**
 * Support HTML5 markup.
 *
 * @link https://codex.wordpress.org/Theme_Markup
 */
add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);

/**
 * Manually select Post Formats to be supported.
 *
 * @link http://codex.wordpress.org/Post_Formats
 */
// phpcs:ignore
// add_theme_support( 'post-formats', [ 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat' ] );

/**
 * Support default editor block styles.
 *
 * @link https://wordpress.org/gutenberg/handbook/extensibility/theme-support/
 */
add_theme_support('wp-block-styles');

/**
 * Support wide alignment for editor blocks.
 *
 * @link https://wordpress.org/gutenberg/handbook/extensibility/theme-support/
 */
add_theme_support('align-wide');

/**
 * Support color palette enforcement.
 *
 * @link https://wordpress.org/gutenberg/handbook/extensibility/theme-support/
 */
// phpcs:ignore
// add_theme_support( 'disable-custom-colors' );

/**
 * Support custom editor block font sizes.
 * Don't forget to edit resources/styles/shared/variables.scss when you update these.
 *
 * @link https://wordpress.org/gutenberg/handbook/extensibility/theme-support/
 */
add_theme_support(
    'editor-font-sizes',
    [
        [
            'name'      => __('extra small', 'laca'),
            'shortName' => __('XS', 'laca'),
            'size'      => (int)Config::get('variables.font-size.xs', 12),
            'slug'      => 'xs',
        ],
        [
            'name'      => __('small', 'laca'),
            'shortName' => __('S', 'laca'),
            'size'      => (int)Config::get('variables.font-size.s', 16),
            'slug'      => 's',
        ],
        [
            'name'      => __('regular', 'laca'),
            'shortName' => __('M', 'laca'),
            'size'      => (int)Config::get('variables.font-size.m', 20),
            'slug'      => 'm',
        ],
        [
            'name'      => __('large', 'laca'),
            'shortName' => __('L', 'laca'),
            'size'      => (int)Config::get('variables.font-size.l', 28),
            'slug'      => 'l',
        ],
        [
            'name'      => __('extra large', 'laca'),
            'shortName' => __('XL', 'laca'),
            'size'      => (int)Config::get('variables.font-size.xl', 36),
            'slug'      => 'xl',
        ],
    ]
);

/**
 * Support WooCommerce.
 */
add_theme_support('woocommerce');
