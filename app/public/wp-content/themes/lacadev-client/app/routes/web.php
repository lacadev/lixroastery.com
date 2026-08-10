<?php
/**
 * Web Routes.
 *
 * @link https://docs.wpemerge.com/#/framework/routing/methods
 *
 * @package WPEmergeTheme
 */

use WPEmerge\Facades\Route;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Pass all front-end requests through WPEmerge.
 * WARNING: Do not add routes after this - they will be ignored.
 *
 * @link https://docs.wpemerge.com/#/framework/routing/methods?id=handling-all-requests
 */
Route::all();
