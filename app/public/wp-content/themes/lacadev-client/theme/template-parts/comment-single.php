<?php
/**
 * Single Comment Template
 *
 * @package WPEmergeTheme
 */

$comment = $args['comment'] ?? null;
$depth   = $args['depth'] ?? 1;
$passed_args = $args['args'] ?? [];

if ( ! $comment ) {
	return;
}

$tag = ( isset($passed_args['style']) && 'b' === $passed_args['style'] ) ? 'b' : 'li';
$add_below = 'div-comment';
?>
<<?php echo $tag; ?> <?php comment_class( empty( $passed_args['has_children'] ) ? 'custom-comment' : 'parent custom-comment' ); ?> id="comment-<?php comment_ID(); ?>">
	<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
		<header class="comment-meta">
			<div class="comment-author vcard">
				<?php 
				$avatar_size = $passed_args['avatar_size'] ?? 48;
				if ( 0 != $avatar_size ) {
					echo get_avatar( $comment, $avatar_size );
				}
				?>
				<?php printf( '<span class="author-name">%s</span>', get_comment_author_link( $comment ) ); ?>
			</div><!-- .comment-author -->

			<div class="comment-metadata">
				<a href="<?php echo esc_url( get_comment_link( $comment, $passed_args ) ); ?>">
					<time datetime="<?php comment_time( 'c' ); ?>">
						<?php printf( __( '%s ago', 'laca' ), human_time_diff( get_comment_time('U'), current_time('timestamp') ) ); ?>
					</time>
				</a>
			</div><!-- .comment-metadata -->
		</header><!-- .comment-meta -->

		<?php if ( '0' == $comment->comment_approved ) : ?>
		<p class="comment-awaiting-moderation"><?php _e( 'Your comment is awaiting moderation.', 'laca' ); ?></p>
		<?php endif; ?>

		<div class="comment-content">
			<?php comment_text(); ?>
		</div><!-- .comment-content -->

		<div class="comment-actions">
			<?php
			comment_reply_link(
				array_merge(
					$passed_args,
					array(
						'add_below' => $add_below,
						'depth'     => $depth,
						'max_depth' => $passed_args['max_depth'] ?? 5,
						'before'    => '<div class="reply">',
						'after'     => '</div>',
					)
				)
			);
			?>
		</div>
	</article><!-- .comment-body -->
