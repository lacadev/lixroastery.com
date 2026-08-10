<?php
/**
 * The template for displaying Comments
 *
 * The area of the page that contains comments and the comment form.
 *
 * @package WPEmergeTheme
 */

/*
 * If the current post is protected by a password and the visitor has not yet
 * entered the password we will return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}
?>
<section class="section-comments" id="comments">
	<?php if ( have_comments() ) : ?>
		<h3><?php comments_number( __( 'No Responses', 'laca' ), __( 'One Response', 'laca' ), __( '% Responses', 'laca' ) ); ?></h3>
		<ol class="comments">
			<?php
			wp_list_comments(
				[
					'callback' => 'lacadev_custom_comments_callback',
				]
			);
			?>
		</ol>

		<?php
		carbon_pagination(
			'comments',
			[
				'enable_numbers' => true,
				'prev_html'      => '<a href="{URL}" class="paging__prev">' . esc_html__( '« Previous Comments', 'laca' ) . '</a>',
				'next_html'      => '<a href="{URL}" class="paging__next">' . esc_html__( 'Next Comments »', 'laca' ) . '</a>',
			]
		);
		?>
	<?php else : ?>
		<?php if ( ! comments_open() ) : ?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'laca' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	$commenter = wp_get_current_commenter();
	$req       = get_option( 'require_name_email' );
	$html_req  = ( $req ? " required='required'" : '' );

	$site_key    = function_exists('getOption') ? getOption('recaptcha_site_key') : (function_exists('carbon_get_theme_option') ? carbon_get_theme_option('recaptcha_site_key') : '');
	$use_captcha = function_exists('getOption') ? getOption('enable_recaptcha_comment') : (function_exists('carbon_get_theme_option') ? carbon_get_theme_option('enable_recaptcha_comment') : false);

	$recaptcha_notice = '';
	if ( $site_key && $use_captcha ) {
		$recaptcha_notice = '<div class="comment-form-recaptcha">
			<small style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted, #777);">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 16v-4M12 8h.01"/>
				</svg>
				<span>' . __( 'Bảo vệ bởi reCAPTCHA', 'laca' ) . ' - <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline;">' . __( 'Bảo mật', 'laca' ) . '</a> & <a href="https://policies.google.com/terms" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline;">' . __( 'Điều khoản', 'laca' ) . '</a></span>
			</small>
		</div>';
	}

	$fields = [
		'author' => '<p class="comment-form-author"><input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" maxlength="245" placeholder="' . esc_attr__( 'Tên *', 'laca' ) . '"' . $html_req . ' /></p>',
		'email'  => '<p class="comment-form-email"><input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="30" maxlength="100" placeholder="' . esc_attr__( 'Email *', 'laca' ) . '"' . $html_req . ' /></p>',
		'url'    => '<p class="comment-form-url"><input id="url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" maxlength="200" placeholder="' . esc_attr__( 'Trang web (Không bắt buộc)', 'laca' ) . '" /></p>',
	];

	comment_form(
		[
			'title_reply'          => __( 'Bình luận', 'laca' ),
			'title_reply_to'       => __( 'Trả lời tới %s', 'laca' ),
			'comment_notes_before' => '',
			'comment_notes_after'  => '',
			'fields'               => apply_filters( 'comment_form_default_fields', $fields ),
			'comment_field'        => '<p class="comment-form-comment"><textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525" required="required" placeholder="' . esc_attr__( 'Viết bình luận của bạn...', 'laca' ) . '"></textarea></p>',
			'class_form'           => 'comment-form expandable-form',
			'label_submit'         => __( 'Gửi bình luận', 'laca' ),
            'submit_button'        => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" />',
            'submit_field'         => $recaptcha_notice . '<p class="form-submit">%1$s %2$s</p>',
		]
	);
	?>
</section>
