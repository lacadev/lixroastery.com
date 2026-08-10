<?php
/**
 * App Layout: layouts/app.php
 *
 * Archive template for 'project' custom post type.
 * Same layout/filter behavior as archive-gallery, with project custom meta.
 *
 * @link    https://codex.wordpress.org/Template_Hierarchy
 * @package WPEmergeTheme
 */

global $wp_query;

$posts_per_page = (int) get_option( 'posts_per_page', 12 );
$paged          = (int) max( 1, get_query_var( 'paged', 1 ) );

$active_cat_slug = isset( $_GET['project_cat'] ) ? sanitize_title( wp_unslash( $_GET['project_cat'] ) ) : '';

$filter_cats = get_terms( [
	'taxonomy'   => 'project_cat',
	'hide_empty' => true,
	'parent'     => 0,
] );

$active_cat_label = __( 'Tất cả', 'laca' );
if ( $active_cat_slug && ! is_wp_error( $filter_cats ) ) {
	foreach ( $filter_cats as $cat ) {
		if ( $cat->slug === $active_cat_slug ) {
			$active_cat_label = $cat->name;
			break;
		}
	}
}

$archive_url = get_post_type_archive_link( 'project' );

$ajax_config = wp_json_encode( [
	'action'         => 'lacadev_project_archive_load',
	'nonce'          => wp_create_nonce( 'theme_nonce' ),
	'ajaxurl'        => admin_url( 'admin-ajax.php' ),
	'posts_per_page' => $posts_per_page,
	'cat_slug'       => $active_cat_slug,
	'current_page'   => $paged,
	'max_pages'      => (int) $wp_query->max_num_pages,
	'archive_url'    => esc_url( $archive_url ),
	'query_param'    => 'project_cat',
	'pretty_paged'   => (bool) get_option( 'permalink_structure' ),
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

<div id="laca-project-archive" class="laca-gallery-archive laca-project-archive" data-archive-config='<?php echo $ajax_config; ?>'>
	<?php //get_template_part( 'template-parts/page-hero' ); ?>

	<div class="container-fluid">
		<?php if ( ! is_wp_error( $filter_cats ) && ! empty( $filter_cats ) ) : ?>
			<div class="laca-gallery-toolbar">
				<!-- <h2 class="laca-gallery-toolbar__title"><?php //echo esc_html( $active_cat_label ); ?></h2> -->
				<h1 class="laca-gallery-toolbar__title-page" data-aos="fade-up"><?php echo getPageTitle(); ?></h1>
				<div class="laca-gallery-filter" aria-label="<?php esc_attr_e( 'Lọc danh mục', 'laca' ); ?>">
					<button type="button" class="laca-gallery-filter__trigger" aria-expanded="false" aria-haspopup="listbox">
						<span class="laca-gallery-filter__label"><?php echo esc_html( $active_cat_label ); ?></span>
						<span class="laca-gallery-filter__arrow" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M137.4 374.6c12.5 12.5 32.8 12.5 45.3 0l128-128c9.2-9.2 11.9-22.9 6.9-34.9s-16.6-19.8-29.6-19.8L32 192c-12.9 0-24.6 7.8-29.6 19.8s-2.2 25.7 6.9 34.9l128 128z"/></svg>
						</span>
					</button>

					<ul class="laca-gallery-filter__list" role="listbox" aria-label="<?php esc_attr_e( 'Danh mục', 'laca' ); ?>">
						<li role="option" data-cat-slug="" class="<?php echo ( ! $active_cat_slug ) ? 'is-active' : ''; ?>"><?php esc_html_e( 'Tất cả', 'laca' ); ?></li>
						<?php foreach ( $filter_cats as $cat ) : ?>
							<li role="option" data-cat-slug="<?php echo esc_attr( $cat->slug ); ?>" class="<?php echo ( $active_cat_slug === $cat->slug ) ? 'is-active' : ''; ?>"><?php echo esc_html( $cat->name ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div id="project-grid" class="laca-gallery-list__grid" aria-live="polite" aria-atomic="true">
				<?php
				while ( have_posts() ) : the_post();
					$post_id    = get_the_ID();
					$investor   = get_post_meta( $post_id, '_investor', true );
					$location   = get_post_meta( $post_id, '_location', true );
					$floors     = get_post_meta( $post_id, '_floors', true );
					$front_area = get_post_meta( $post_id, '_front_area', true );
				?>
					<article
						class="laca-gallery-card"
						data-aos="fade-up"
					>
						<a class="laca-gallery-card__link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
							<div class="laca-gallery-card__img">
								<?php if ( has_post_thumbnail( $post_id ) ) : ?>
									<?php echo get_the_post_thumbnail( $post_id, 'large', [ 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ] ); ?>
								<?php else : ?>
									<div class="laca-gallery-card__img-placeholder"></div>
								<?php endif; ?>
							</div>

							<div class="laca-gallery-card__body">
								<h3 class="laca-gallery-card__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
								<?php if ( $investor || $location || $floors || $front_area ) : ?>
								<ul class="laca-gallery-card__meta">
									<?php if ( $investor ) : ?>
										<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Chủ đầu tư:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $investor ); ?></span></li>
									<?php endif; ?>
									<?php if ( $location ) : ?>
										<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Địa điểm:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $location ); ?></span></li>
									<?php endif; ?>
									<?php if ( $floors ) : ?>
										<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Số tầng:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $floors ); ?></span></li>
									<?php endif; ?>
									<?php if ( $front_area ) : ?>
										<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Mặt tiền:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $front_area ); ?></span></li>
									<?php endif; ?>
								</ul>
								<?php endif; ?>
							</div>
						</a>
					</article>
				<?php endwhile; ?>
			</div>

			<div id="project-pagination">
				<?php thePagination(); ?>
			</div>
		<?php else : ?>
			<div class="laca-gallery-list__empty">
				<p><?php esc_html_e( 'Chưa có dự án nào.', 'laca' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php wp_reset_postdata(); ?>
