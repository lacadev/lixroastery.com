<?php
/**
 * WordPress AJAX Routes.
 * WARNING: Do not use Route::all() here, otherwise you will override
 * ALL AJAX requests which you most likely do not want to do.
 *
 * @link https://docs.wpemerge.com/#/framework/routing/methods
 *
 * @package WPEmergeTheme
 */

use WPEmerge\Facades\Route;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}