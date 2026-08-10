<?php

/**
 * Gallery Archive AJAX Handler
 *
 * Xử lý AJAX filter theo taxonomy gallery_cat + pagination.
 * Action: lacadev_gallery_archive_load (cả logged-in và guest).
 *
 * @package LacaDevClientChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GalleryAjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_lacadev_gallery_archive_load',        [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_lacadev_gallery_archive_load', [ $this, 'handle' ] );
	}

	public function handle(): void {
		// ── Nonce verification ────────────────────────────────────────────────
		if ( ! check_ajax_referer( 'theme_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
		}

		$cat_slug       = isset( $_POST['cat_slug'] ) ? sanitize_title( wp_unslash( $_POST['cat_slug'] ) ) : '';
		$paged          = max( 1, (int) ( $_POST['paged'] ?? 1 ) );
		$posts_per_page = max( 1, min( 48, (int) ( $_POST['posts_per_page'] ?? 12 ) ) );

		// ── Build Query ───────────────────────────────────────────────────────
		$args = [
			'post_type'      => 'gallery',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'no_found_rows'  => false,
		];

		if ( $cat_slug ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'taxonomy' => 'gallery-cat',
					'field'    => 'slug',
					'terms'    => $cat_slug,
				],
			];
		}

		$query = new WP_Query( $args );

		// ── Render cards HTML ─────────────────────────────────────────────────
		ob_start();
		if ( $query->have_posts() ) {
			$item_index = 0;
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_card( get_the_ID(), $item_index );
				++$item_index;
			}
		} else {
			echo '<div class="laca-gallery-list__empty"><p>' . esc_html__( 'Chưa có thư viện thiết kế nào.', 'laca' ) . '</p></div>';
		}
		$cards_html = ob_get_clean();

		$archive_url      = get_post_type_archive_link( 'gallery' ) ?: '';
		$pagination_html = $this->render_pagination_html( $paged, (int) $query->max_num_pages, (string) $archive_url );

		// ── Active cat label ─────────────────────────────────────────────────
		$active_label = __( 'Tất cả', 'laca' );
		if ( $cat_slug ) {
			$term = get_term_by( 'slug', $cat_slug, 'gallery-cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$active_label = $term->name;
			}
		}

		wp_reset_postdata();

		wp_send_json_success( [
			'html'         => $cards_html,
			'pagination'   => $pagination_html,
			'max_pages'    => (int) $query->max_num_pages,
			'active_label' => $active_label,
		] );
	}

	/**
	 * Pagination links for gallery archive (pretty / plain permalinks).
	 *
	 * @param int    $paged       Current page.
	 * @param int    $total_pages Total pages.
	 * @param string $archive_url From get_post_type_archive_link( 'gallery' ).
	 */
	private function render_pagination_html( int $paged, int $total_pages, string $archive_url ): string {
		if ( $total_pages <= 1 ) {
			return '';
		}
		if ( $archive_url === '' ) {
			$archive_url = home_url( '/' );
		}
		$archive_url = untrailingslashit( $archive_url );
		if ( get_option( 'permalink_structure' ) ) {
			$base   = trailingslashit( $archive_url ) . 'page/%#%/';
			$format = '';
		} else {
			$base   = esc_url( add_query_arg( 'paged', '%#%', $archive_url ) );
			$format = '';
		}
		return lacadev_child_pagination_markup(
			[
				'base'    => $base,
				'format'  => $format,
				'current' => $paged,
				'total'   => $total_pages,
			]
		);
	}

	/**
	 * Render một card item.
	 *
	 * @param int $post_id    Post ID.
	 * @param int $item_index Stagger AOS delay (0-based).
	 */
	private function render_card( int $post_id, int $item_index = 0 ): void {
		$investor  = carbon_get_post_meta( $post_id, 'investor' );
		$floors    = carbon_get_post_meta( $post_id, 'floors' );
		$location  = carbon_get_post_meta( $post_id, 'location' );
		$area      = carbon_get_post_meta( $post_id, 'total_area' );
		$gallery   = carbon_get_post_meta( $post_id, 'project_gallery' ); // array of attachment IDs

		// Build lightgallery data: [ { src, thumb, subHtml } ]
		$lg_items = [];
		if ( ! empty( $gallery ) && is_array( $gallery ) ) {
			foreach ( $gallery as $att_id ) {
				$full  = wp_get_attachment_image_url( $att_id, 'full' );
				$thumb = wp_get_attachment_image_url( $att_id, 'medium' );
				if ( $full ) {
					$lg_items[] = [
						'src'     => $full,
						'thumb'   => $thumb ?: $full,
						'subHtml' => '',
					];
				}
			}
		}
		// Nếu không có gallery, dùng featured image
		if ( empty( $lg_items ) && has_post_thumbnail( $post_id ) ) {
			$lg_items[] = [
				'src'     => get_the_post_thumbnail_url( $post_id, 'full' ),
				'thumb'   => get_the_post_thumbnail_url( $post_id, 'medium' ),
				'subHtml' => esc_html( get_the_title( $post_id ) ),
			];
		}

		$lg_data  = wp_json_encode( $lg_items );
		$delay_ms = $item_index * 100;
		?>
		<article
			class="laca-gallery-card"
			data-aos="fade-up"
			data-aos-delay="<?php echo esc_attr( (string) $delay_ms ); ?>"
			data-gallery-id="<?php echo esc_attr( $post_id ); ?>"
			data-gallery-items='<?php echo esc_attr( $lg_data ); ?>'
		>
			<div class="laca-gallery-card__img">
				<?php if ( has_post_thumbnail( $post_id ) ) : ?>
					<?php echo get_the_post_thumbnail( $post_id, 'large', [ 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ] ); ?>
				<?php else : ?>
					<div class="laca-gallery-card__img-placeholder"></div>
				<?php endif; ?>
				<div class="laca-gallery-card__img-overlay">
					<span class="laca-gallery-card__img-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
					</span>
				</div>
			</div>

			<div class="laca-gallery-card__body">
				<h3 class="laca-gallery-card__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>

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

				<button type="button" class="laca-gallery-card__btn js-open-gallery">
					<?php esc_html_e( 'Xem chi tiết', 'laca' ); ?> <span aria-hidden="true">→</span>
				</button>
			</div>
		</article>
		<?php
	}
}

new GalleryAjaxHandler();
