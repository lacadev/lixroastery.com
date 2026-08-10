<?php
/**
 * Shared pagination markup for AJAX archive responses.
 *
 * Mirrors parent thePagination() structure so theme SCSS (.pagination-container) applies.
 *
 * @package LacaDevClientChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build paginated navigation HTML from paginate_links() args (type forced to array).
 *
 * @param array<string, mixed> $args Arguments for paginate_links(): base, format, current, total, etc.
 * @return string Safe HTML fragment (WordPress-escaped pieces from paginate_links).
 */
function lacadev_child_pagination_markup( array $args ): string {
	$total = isset( $args['total'] ) ? (int) $args['total'] : 0;
	if ( $total <= 1 ) {
		return '';
	}
	$defaults = [
		'mid_size'  => 2,
		'type'      => 'array',
		'prev_next' => true,
		'prev_text' => '&laquo;',
		'next_text' => '&raquo;',
	];
	$merged         = wp_parse_args( $args, $defaults );
	$merged['type'] = 'array';
	$pages          = paginate_links( $merged );
	if ( ! is_array( $pages ) ) {
		return '';
	}
	$out = '<nav class="pagination-container" aria-label="' . esc_attr__( 'Page navigation', 'laca' ) . '"><ul class="pagination-list">';
	foreach ( $pages as $page ) {
		$is_current = strpos( $page, 'current' ) !== false;
		$is_dots    = strpos( $page, 'dots' ) !== false;
		$item_class = 'pagination-item';
		if ( $is_current ) {
			$item_class .= ' is-active';
		}
		if ( $is_dots ) {
			$item_class .= ' is-dots';
		}
		$out .= '<li class="' . esc_attr( $item_class ) . '">' . $page . '</li>';
	}
	$out .= '</ul></nav>';
	return $out;
}

