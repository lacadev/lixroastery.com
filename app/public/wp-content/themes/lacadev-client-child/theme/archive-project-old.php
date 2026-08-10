<?php
	/**
	 * App Layout: layouts/app.php
	 *
	 * Archive template for 'project' custom post type.
	 * Displays a filterable grid of projects with AJAX pagination.
	 *
	 * @link    https://codex.wordpress.org/Template_Hierarchy
	 * @package WPEmergeTheme
	 */

// ── Query context ─────────────────────────────────────────────────────────────
global $wp_query;

$posts_per_page = (int) get_option( 'posts_per_page', 12 );
$paged          = (int) max( 1, get_query_var( 'paged', 1 ) );

// ── Active filter: taxonomy term (project_cat) ────────────────────────────────
$active_cat_slug = isset( $_GET['project_cat'] ) ? sanitize_title( wp_unslash( $_GET['project_cat'] ) ) : '';
$active_cat_id   = 0;
if ( $active_cat_slug ) {
	$filter_term = get_term_by( 'slug', $active_cat_slug, 'project_cat' );
	if ( $filter_term && ! is_wp_error( $filter_term ) ) {
		$active_cat_id = $filter_term->term_id;
	}
}

// ── Filter categories for tabs (chỉ lấy loại cha có bài) ─────────────────────
$filter_cats = get_terms( [
	'taxonomy'   => 'project_cat',
	'hide_empty' => true,
	'parent'     => 0, // chỉ lấy category cấp cha
] );

// ── N+1 prevention trên lần load đầu ─────────────────────────────────────────
if ( ! empty( $wp_query->posts ) ) {
	update_post_caches( $wp_query->posts, 'project', true, true );
	update_object_term_cache( wp_list_pluck( $wp_query->posts, 'ID' ), 'project' );
}

// ── Archive base URL (không có query string) ──────────────────────────────────
$archive_url = get_post_type_archive_link( 'project' ) ?: home_url( '/du-an/' );

// ── AJAX config truyền vào JS qua data-* ──────────────────────────────────────
$ajax_config = wp_json_encode( [
	'action'         => 'lacadev_project_archive_load',
	'nonce'          => wp_create_nonce( 'theme_nonce' ),
	'ajaxurl'        => admin_url( 'admin-ajax.php' ),
	'posts_per_page' => $posts_per_page,
	'cat_slug'       => $active_cat_slug,
	'current_page'   => $paged,
	'max_pages'      => (int) $wp_query->max_num_pages,
	'archive_url'    => esc_url( $archive_url ),
] );
?>

<div class="breadcumb">
	<div class="container-fluid">
		<?php
		if ( function_exists('rank_math_the_breadcrumbs') ) :
			rank_math_the_breadcrumbs();
		endif;
		?>
	</div>
</div>

<div class="archive-project laca-project-archive" data-archive-config='<?php echo $ajax_config; ?>'>
	<?php get_template_part( 'template-parts/page-hero' ); ?>

	<div class="container-fluid">

		<?php /* ── Filter tabs ── */ ?>
		<?php if ( ! is_wp_error( $filter_cats ) && ! empty( $filter_cats ) ) : ?>
			<nav class="laca-project-archive__filter" aria-label="<?php esc_attr_e( 'Lọc theo loại dự án', 'laca' ); ?>">
				<a
					href="<?php echo esc_url( $archive_url ); ?>"
					class="laca-project-archive__filter-btn<?php echo ( ! $active_cat_slug ) ? ' is-active' : ''; ?>"
					data-cat-slug=""
				>
					<?php esc_html_e( 'Tất cả', 'laca' ); ?>
				</a>
				<?php foreach ( $filter_cats as $cat ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( 'project_cat', $cat->slug, $archive_url ) ); ?>"
						class="laca-project-archive__filter-btn<?php echo ( $active_cat_slug === $cat->slug ) ? ' is-active' : ''; ?>"
						data-cat-slug="<?php echo esc_attr( $cat->slug ); ?>"
					>
						<?php echo esc_html( $cat->name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>

			<?php /* Grid dự án – JS replace nội dung khi filter/đổi trang */ ?>
			<div id="archive-grid" class="laca-project-list__grid" aria-live="polite" aria-atomic="true">
				<?php while ( have_posts() ) : the_post();
					$investor   = get_post_meta( get_the_ID(), '_investor', true );
					$location   = get_post_meta( get_the_ID(), '_location', true );
					$floors     = get_post_meta( get_the_ID(), '_floors', true );
					$front_area = get_post_meta( get_the_ID(), '_front_area', true );
				?>
				<article class="laca-project-list__card">
					<a
						href="<?php the_permalink(); ?>"
						class="laca-project-list__card-link"
						aria-label="<?php the_title_attribute(); ?>"
					>
						<div class="laca-project-list__card-img">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'large', [ 'loading' => 'lazy', 'alt' => get_the_title() ] ); ?>
							<?php else : ?>
								<div class="laca-project-list__card-img-placeholder"></div>
							<?php endif; ?>
						</div>
					</a>

					<div class="laca-project-list__card-body">
						<h3 class="laca-project-list__card-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>

						<ul class="laca-project-list__card-meta">
							<?php if ( $investor ) : ?>
								<li>
									<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512l388.6 0c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304l-91.4 0z"/></svg>
									<?php esc_html_e( 'Chủ đầu tư: ', 'laca' ); ?>
									<strong><?php echo esc_html( $investor ); ?></strong>
								</li>
							<?php endif; ?>
							<?php if ( $location ) : ?>
								<li>
									<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></svg>
									<?php esc_html_e( 'Địa điểm: ', 'laca' ); ?>
									<strong><?php echo esc_html( $location ); ?></strong>
								</li>
							<?php endif; ?>
							<?php if ( $floors ) : ?>
								<li>
									<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M48 0C21.5 0 0 21.5 0 48L0 464c0 26.5 21.5 48 48 48l96 0 0-80c0-26.5 21.5-48 48-48s48 21.5 48 48l0 80 96 0c26.5 0 48-21.5 48-48L384 48c0-26.5-21.5-48-48-48L48 0zM64 240c0-8.8 7.2-16 16-16l32 0c8.8 0 16 7.2 16 16l0 32c0 8.8-7.2 16-16 16l-32 0c-8.8 0-16-7.2-16-16l0-32zm112-16l32 0c8.8 0 16 7.2 16 16l0 32c0 8.8-7.2 16-16 16l-32 0c-8.8 0-16-7.2-16-16l0-32c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16l32 0c8.8 0 16 7.2 16 16l0 32c0 8.8-7.2 16-16 16l-32 0c-8.8 0-16-7.2-16-16l0-32zM64 96c0-8.8 7.2-16 16-16l32 0c8.8 0 16 7.2 16 16l0 32c0 8.8-7.2 16-16 16l-32 0c-8.8 0-16-7.2-16-16L64 96zm112-16l32 0c8.8 0 16 7.2 16 16l0 32c0 8.8-7.2 16-16 16l-32 0c-8.8 0-16-7.2-16-16l0-32c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16l32 0c8.8 0 16 7.2 16 16l0 32c0 8.8-7.2 16-16 16l-32 0c-8.8 0-16-7.2-16-16l0-32z"/></svg>
									<?php esc_html_e( 'Số tầng: ', 'laca' ); ?>
									<strong><?php echo esc_html( $floors ); ?></strong>
								</li>
							<?php endif; ?>
							<?php if ( $front_area ) : ?>
								<li>
									<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M344 0L488 0c13.3 0 24 10.7 24 24l0 144c0 9.7-5.8 18.5-14.8 22.2s-19.3 1.7-26.2-5.2l-39-39-87 87c-9.4 9.4-24.6 9.4-33.9 0l-32-32c-9.4-9.4-9.4-24.6 0-33.9l87-87L327 41c-6.9-6.9-8.9-17.2-5.2-26.2S334.3 0 344 0zM168 512L24 512c-13.3 0-24-10.7-24-24L0 344c0-9.7 5.8-18.5 14.8-22.2s19.3-1.7 26.2 5.2l39 39 87-87c9.4-9.4 24.6-9.4 33.9 0l32 32c9.4 9.4 9.4 24.6 0 33.9l-87 87 39 39c6.9 6.9 8.9 17.2 5.2 26.2s-12.5 14.8-22.2 14.8z"/></svg>
									<?php esc_html_e( 'Mặt tiền: ', 'laca' ); ?>
									<strong><?php echo esc_html( $front_area ); ?></strong>
								</li>
							<?php endif; ?>
						</ul>
					</div>
				</article>
				<?php endwhile; ?>
			</div><!-- #archive-grid -->

			<?php /* Pagination wrapper – JS thay thế nội dung */ ?>
			<div id="archive-pagination">
				<?php thePagination(); ?>
			</div>

		<?php else : ?>
			<div class="laca-project-list__empty">
				<p><?php esc_html_e( 'Chưa có dự án nào.', 'laca' ); ?></p>
			</div>
		<?php endif; ?>

	</div><!-- /.container-fluid -->
</div>

<?php wp_reset_postdata(); ?>
