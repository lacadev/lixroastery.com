<?php
/**
 * Layout Mode Helpers for Parent Theme.
 *
 * @package WPEmergeTheme
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('laca_get_current_layout_mode')) {
	/**
	 * Lấy chế độ khung (fluid | boxed) hiện tại của site/trang.
	 *
	 * Thứ tự ưu tiên:
	 * 1. Tham số preview dev (?laca_layout=boxed|fluid, ?laca_page_layout=boxed|fluid)
	 *    khi ở môi trường local hoặc có quyền manage_options.
	 * 2. Meta riêng của trang/post (nếu là trang đơn và có chọn fluid/boxed khác inherit).
	 * 3. Theme Option của site (layout_container_mode trong AdminSettings).
	 * 4. Mặc định 'fluid'.
	 *
	 * @return string 'fluid'|'boxed'
	 */
	function laca_get_current_layout_mode(): string
	{
		// 1. Dev preview param (local environment hoặc manage_options)
		$is_local = function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local';
		$can_preview = $is_local || current_user_can('manage_options');

		if ($can_preview) {
			// Preview page override: ?laca_page_layout=boxed|fluid
			if (isset($_GET['laca_page_layout'])) {
				$page_preview = sanitize_key($_GET['laca_page_layout']);
				if (in_array($page_preview, ['fluid', 'boxed'], true)) {
					return $page_preview;
				}
			}
			// Preview site layout: ?laca_layout=boxed|fluid
			if (isset($_GET['laca_layout'])) {
				$site_preview = sanitize_key($_GET['laca_layout']);
				if (in_array($site_preview, ['fluid', 'boxed'], true)) {
					// Nếu trang hiện tại có meta riêng mà không có preview page override:
					if (is_singular()) {
						$page_mode = function_exists('getPostMeta') ? getPostMeta('layout_container_mode', get_the_ID()) : '';
						if (!$page_mode) {
							$page_mode = get_post_meta(get_the_ID(), '_layout_container_mode', true);
						}
						if ($page_mode && in_array($page_mode, ['fluid', 'boxed'], true)) {
							return $page_mode;
						}
					}
					return $site_preview;
				}
			}
		}

		// 2. Meta trang (Tầng 2)
		if (is_singular()) {
			$post_id = get_the_ID();
			if ($post_id) {
				$page_mode = function_exists('getPostMeta') ? getPostMeta('layout_container_mode', $post_id) : '';
				if (!$page_mode) {
					$page_mode = get_post_meta($post_id, '_layout_container_mode', true);
				}
				if ($page_mode && in_array($page_mode, ['fluid', 'boxed'], true)) {
					return $page_mode;
				}
			}
		}

		// 3. Option site (Tầng 1)
		$site_mode = function_exists('getOption') ? getOption('layout_container_mode') : '';
		if (!$site_mode && function_exists('carbon_get_theme_option')) {
			$site_mode = carbon_get_theme_option('layout_container_mode');
		}
		if ($site_mode && in_array($site_mode, ['fluid', 'boxed'], true)) {
			return $site_mode;
		}

		// 4. Mặc định site fluid
		return 'fluid';
	}
}
