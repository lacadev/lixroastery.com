<?php
	/**
	 * App Layout: layouts/app.php
	 *
	 * This is the template that is used for displaying all posts by default.
	 *
	 * @link    https://codex.wordpress.org/Template_Hierarchy
	 *
	 * @package WPEmergeTheme
	 */

// ── Query context ────────────────────────────────────────────────────────────
global $wp_query;

$current_post_type = get_post_type() ?: 'post';
$posts_per_page    = (int) get_option( 'posts_per_page', 12 );
$paged             = (int) max( 1, get_query_var( 'paged', 1 ) );

// ── Term filter params ───────────────────────────────────────────────────────
$cat_id   = 0;
$tag_id   = 0;
$tax_term = 0;
$taxonomy = '';

if ( is_category() ) {
	$cat_id = get_queried_object_id();
} elseif ( is_tag() ) {
	$tag_id = get_queried_object_id();
} elseif ( is_tax() ) {
	$term     = get_queried_object();
	$tax_term = $term->term_id ?? 0;
	$taxonomy = $term->taxonomy ?? '';
}

// ── N+1 prevention trên lần load đầu ────────────────────────────────────────
if ( ! empty( $wp_query->posts ) ) {
	update_post_caches( $wp_query->posts, $current_post_type, true, true );
	update_object_term_cache( wp_list_pluck( $wp_query->posts, 'ID' ), $current_post_type );
}

// ── AJAX config truyền vào JS qua data-* ────────────────────────────────────
$ajax_config = wp_json_encode( [
	'action'         => 'lacadev_archive_load',
	'nonce'          => wp_create_nonce( 'theme_nonce' ),
	'ajaxurl'        => admin_url( 'admin-ajax.php' ),
	'post_type'      => esc_js( $current_post_type ),
	'posts_per_page' => $posts_per_page,
	'cat_id'         => $cat_id,
	'tag_id'         => $tag_id,
	'tax_term'       => $tax_term,
	'taxonomy'       => esc_js( $taxonomy ),
	'current_page'   => $paged,
	'max_pages'      => (int) $wp_query->max_num_pages,
] );
?>

<div class="breadcumb">
	<div class="container-fluid">
		<?php
		if ( function_exists( 'rank_math_the_breadcrumbs' ) ) :
			rank_math_the_breadcrumbs();
		endif;
		?>
	</div>
</div>

<div class="archive-post laca-news-list-archive" data-archive-config='<?php echo $ajax_config; ?>'>
	<?php get_template_part( 'template-parts/page-hero' ); ?>

	<div class="container-fluid">

		<?php if ( have_posts() ) : ?>

			<?php /* Grid bài viết – JS sẽ replace nội dung khi đổi trang */ ?>
			<div id="archive-grid" class="laca-news-list__grid" aria-live="polite" aria-atomic="true">
				<?php
				while ( have_posts() ) : the_post();
					$post_id    = get_the_ID();
					$post_url   = get_permalink();
					$post_title = get_the_title();
					$thumb_id   = get_post_thumbnail_id();

					$cats     = get_the_terms( $post_id, 'category' );
					$cat_name = '';
					$cat_url  = '';
					if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
						$cat_name = esc_html( $cats[0]->name );
						$cat_link = esc_url( get_term_link( $cats[0] ) );
					}

					$excerpt_raw = has_excerpt( $post_id )
						? get_the_excerpt( $post_id )
						: wp_strip_all_tags( get_the_content() );
					$excerpt = wp_trim_words( $excerpt_raw, 18, '…' );
				?>
				<article
					class="laca-news-list__item"
					data-aos="fade-up"
				>
					<a href="<?php echo esc_url( $post_url ); ?>" class="laca-news-list__thumb-link" tabindex="-1" aria-hidden="true">
						<div class="laca-news-list__thumb">
							<?php if ( $thumb_id ) : ?>
								<?php echo wp_get_attachment_image( $thumb_id, 'large', false, [
									'class'   => 'laca-news-list__img',
									'loading' => 'lazy',
									'alt'     => esc_attr( $post_title ),
								] ); ?>
							<?php else : ?>
								<div class="laca-news-list__img-placeholder"></div>
							<?php endif; ?>
						</div>
					</a>

					<div class="laca-news-list__content">
						<?php if ( $cat_name ) : ?>
							<a href="<?php echo $cat_link ?? '#'; ?>" class="laca-news-list__cat"><?php echo $cat_name; ?></a>
						<?php endif; ?>

						<h3 class="laca-news-list__item-title">
							<a href="<?php echo esc_url( $post_url ); ?>"><?php echo esc_html( $post_title ); ?></a>
						</h3>

						<?php if ( $excerpt ) : ?>
							<p class="laca-news-list__excerpt"><?php echo esc_html( $excerpt ); ?></p>
						<?php endif; ?>

						<!-- <a href="<?php echo esc_url( $post_url ); ?>" class="laca-news-list__read-more">
							<?php //esc_html_e( 'Xem thêm', 'laca' ); ?>
						</a> -->
					</div>
				</article>
					<?php
					?>
				<?php endwhile; ?>
			</div><!-- #archive-grid -->

			<?php /* Pagination wrapper – JS thay thế nội dung */ ?>
			<div id="archive-pagination">
				<?php thePagination(); ?>
			</div>

		<?php else : ?>
			<div class="laca-news-list__empty">
				<p><?php esc_html_e( 'Chưa có bài viết nào trong mục này.', 'laca' ); ?></p>
			</div>
		<?php endif; ?>

	</div><!-- /.container-fluid -->
</div>

<?php wp_reset_postdata(); ?>