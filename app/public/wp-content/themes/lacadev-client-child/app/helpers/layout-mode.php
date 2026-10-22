<?php
/**
 * Block Layout Mode Helper for Child Theme.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('laca_block_layout_class')) {
	/**
	 * Trả về CSS class tương ứng với containerMode của block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string 'is-layout-fluid' | 'is-layout-boxed' | ''
	 */
	function laca_block_layout_class(array $attributes): string
	{
		$mode = $attributes['containerMode'] ?? 'inherit';
		if ($mode === 'boxed') {
			return 'is-layout-boxed';
		}
		if ($mode === 'fluid') {
			return 'is-layout-fluid';
		}
		return '';
	}
}
