<?php
/**
 * Displays the post date/time, author, tags, categories and comments link.
 *
 * Should be called only within The Loop.
 *
 * It will be displayed only for post type "post".
 *
 * @package WPEmergeTheme
 */

if ( get_post_type() !== 'post' ) {
	return;
}
?>

<div class="article__meta">
	<p>
		<?php
		the_time( 'F jS, Y ' );
		/* translators: post author attribution */
		echo esc_html( sprintf( __( 'by %s', 'laca' ), get_the_author() ) );
		?>
	</p>

	<p>
		<?php
		esc_html_e( 'Posted in ', 'laca' );
		the_category( ', ' );
		if ( comments_open() ) {
			echo '<span> | </span>';
			comments_popup_link( __( 'No Comments', 'laca' ), __( '1 Comment', 'laca' ), __( '% Comments', 'laca' ) );
		}
		?>
	</p>

	<?php the_tags( '<p>' . __( 'Tags:', 'laca' ) . ' ', ', ', '</p>' ); ?>
</div>
