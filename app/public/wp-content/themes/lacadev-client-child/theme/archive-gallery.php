<?php
/**
 * App Layout: layouts/app.php
 *
 * Archive template for 'gallery' custom post type.
 * Tiêu đề THƯ VIỆN + filter dropdown (AJAX) + lightgallery popup.
 *
 * @link    https://codex.wordpress.org/Template_Hierarchy
 * @package WPEmergeTheme
 */

// ── Query context ─────────────────────────────────────────────────────────────
global $wp_query;

$posts_per_page = (int) get_option( 'posts_per_page', 12 );
$paged          = (int) max( 1, get_query_var( 'paged', 1 ) );

// ── Active filter: taxonomy term (gallery_cat) ────────────────────────────────
$active_cat_slug = isset( $_GET['gallery-cat'] ) ? sanitize_title( wp_unslash( $_GET['gallery-cat'] ) ) : '';

// ── Filter categories (parent terms có bài) ───────────────────────────────────
$filter_cats = get_terms( [
	'taxonomy'   => 'gallery-cat',
	'hide_empty' => true,
	'parent'     => 0,
] );

// ── Active cat label (default = "Tất cả") ───────────────────────────────────
$active_cat_label = __( 'Tất cả', 'laca' );
if ( $active_cat_slug && ! is_wp_error( $filter_cats ) ) {
	foreach ( $filter_cats as $cat ) {
		if ( $cat->slug === $active_cat_slug ) {
			$active_cat_label = $cat->name;
			break;
		}
	}
}

// ── Archive base URL ──────────────────────────────────────────────────────────
$archive_url = get_post_type_archive_link( 'gallery' ) ?: home_url( '/thu-vien/' );

// ── AJAX config ───────────────────────────────────────────────────────────────
$ajax_config = wp_json_encode( [
	'action'         => 'lacadev_gallery_archive_load',
	'nonce'          => wp_create_nonce( 'theme_nonce' ),
	'ajaxurl'        => admin_url( 'admin-ajax.php' ),
	'posts_per_page' => $posts_per_page,
	'cat_slug'       => $active_cat_slug,
	'current_page'   => $paged,
	'max_pages'      => (int) $wp_query->max_num_pages,
	'archive_url'    => esc_url( $archive_url ),
	'query_param'    => 'gallery-cat',
	'pretty_paged'   => (bool) get_option( 'permalink_structure' ),
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

<div id="laca-gallery-archive" class="laca-gallery-archive" data-archive-config='<?php echo $ajax_config; ?>'>
	<?php //get_template_part( 'template-parts/page-hero' ); ?>

	<div class="container-fluid">

		<?php /* ── Toolbar: filter dropdown ── */ ?>
		<?php if ( ! is_wp_error( $filter_cats ) && ! empty( $filter_cats ) ) : ?>
			<div class="laca-gallery-toolbar">
				<h1 class="laca-gallery-toolbar__title-page" data-aos="fade-up"><?php echo getPageTitle(); ?></h1>
				<div class="laca-gallery-filter" aria-label="<?php esc_attr_e( 'Lọc danh mục', 'laca' ); ?>">

					<button
						type="button"
						class="laca-gallery-filter__trigger"
						aria-expanded="false"
						aria-haspopup="listbox"
					>
						<span class="laca-gallery-filter__label"><?php echo esc_html( $active_cat_label ); ?></span>
						<span class="laca-gallery-filter__arrow" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M137.4 374.6c12.5 12.5 32.8 12.5 45.3 0l128-128c9.2-9.2 11.9-22.9 6.9-34.9s-16.6-19.8-29.6-19.8L32 192c-12.9 0-24.6 7.8-29.6 19.8s-2.2 25.7 6.9 34.9l128 128z"/></svg>
						</span>
					</button>

					<ul class="laca-gallery-filter__list" role="listbox" aria-label="<?php esc_attr_e( 'Danh mục', 'laca' ); ?>">
						<li
							role="option"
							data-cat-slug=""
							class="<?php echo ( ! $active_cat_slug ) ? 'is-active' : ''; ?>"
						><?php esc_html_e( 'Tất cả', 'laca' ); ?></li>

						<?php foreach ( $filter_cats as $cat ) : ?>
							<li
								role="option"
								data-cat-slug="<?php echo esc_attr( $cat->slug ); ?>"
								class="<?php echo ( $active_cat_slug === $cat->slug ) ? 'is-active' : ''; ?>"
							><?php echo esc_html( $cat->name ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php /* ── Grid ── */ ?>
		<?php if ( have_posts() ) : ?>

			<div id="gallery-grid" class="laca-gallery-list__grid" aria-live="polite" aria-atomic="true">
				<?php
				while ( have_posts() ) : the_post();
					$post_id  = get_the_ID();
					$investor = carbon_get_post_meta( $post_id, 'investor' );
					$floors   = carbon_get_post_meta( $post_id, 'floors' );
					$location = carbon_get_post_meta( $post_id, 'location' );
					$area     = carbon_get_post_meta( $post_id, 'total_area' );
					$gallery  = carbon_get_post_meta( $post_id, 'project_gallery' ); // array of attachment IDs

					// Build lightgallery items
					$lg_items = [];
					if ( ! empty( $gallery ) && is_array( $gallery ) ) {
						foreach ( $gallery as $att_id ) {
							$full  = wp_get_attachment_image_url( $att_id, 'full' );
							$thumb = wp_get_attachment_image_url( $att_id, 'medium' );
							if ( $full ) {
								$lg_items[] = [ 'src' => $full, 'thumb' => $thumb ?: $full, 'subHtml' => '' ];
							}
						}
					}
					if ( empty( $lg_items ) && has_post_thumbnail() ) {
						$lg_items[] = [
							'src'     => get_the_post_thumbnail_url( null, 'full' ),
							'thumb'   => get_the_post_thumbnail_url( null, 'medium' ),
							'subHtml' => esc_html( get_the_title() ),
						];
					}
				?>
				<article
					class="laca-gallery-card"
					data-aos="fade-up"
					data-gallery-id="<?php echo esc_attr( $post_id ); ?>"
					data-gallery-items='<?php echo esc_attr( wp_json_encode( $lg_items ) ); ?>'
				>
					<div class="laca-gallery-card__img">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'large', [ 'loading' => 'lazy', 'alt' => get_the_title() ] ); ?>
						<?php else : ?>
							<div class="laca-gallery-card__img-placeholder"></div>
						<?php endif; ?>
						<div class="laca-gallery-card__img-overlay">
							<span class="laca-gallery-card__img-icon">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
							</span>
						</div>
					</div>

					<div class="laca-gallery-card__body">
						<h3 class="laca-gallery-card__title"><?php the_title(); ?></h3>

						<ul class="laca-gallery-card__meta">
							<?php if ( $investor ) : ?>
								<li>
									<span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Chủ đầu tư:', 'laca' ); ?></span>
									<span class="laca-gallery-card__meta-value"><?php echo esc_html( $investor ); ?></span>
								</li>
							<?php endif; ?>
							<?php if ( $area ) : ?>
								<li>
									<span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Diện tích:', 'laca' ); ?></span>
									<span class="laca-gallery-card__meta-value"><?php echo esc_html( $area ); ?></span>
								</li>
							<?php endif; ?>
							<?php if ( $location ) : ?>
								<li>
									<span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Địa chỉ:', 'laca' ); ?></span>
									<span class="laca-gallery-card__meta-value"><?php echo esc_html( $location ); ?></span>
								</li>
							<?php endif; ?>
							<?php if ( $floors ) : ?>
								<li>
									<span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Số tầng:', 'laca' ); ?></span>
									<span class="laca-gallery-card__meta-value"><?php echo esc_html( $floors ); ?></span>
								</li>
							<?php endif; ?>
						</ul>

						<!-- <button type="button" class="laca-gallery-card__btn js-open-gallery">
							<?php //esc_html_e( 'Xem chi tiết', 'laca' ); ?> <span aria-hidden="true">→</span>
						</button> -->
					</div>
				</article>
				<?php endwhile; ?>
			</div><!-- #gallery-grid -->

			<div id="gallery-pagination">
				<?php thePagination(); ?>
			</div>

		<?php else : ?>
			<div class="laca-gallery-list__empty">
				<p><?php esc_html_e( 'Chưa có thư viện thiết kế nào.', 'laca' ); ?></p>
			</div>
		<?php endif; ?>

	</div><!-- /.container-fluid -->
</div><!-- .laca-gallery-archive -->

<?php wp_reset_postdata(); ?>
