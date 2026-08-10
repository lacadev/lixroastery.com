<?php

/**
 * PDN TV Archive AJAX Handler
 *
 * Handle AJAX filter by taxonomy tv_cat + pagination.
 * Action: lacadev_pdn_tv_archive_load.
 *
 * @package LacaDevClientChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PdnTvAjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_lacadev_pdn_tv_archive_load', [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_lacadev_pdn_tv_archive_load', [ $this, 'handle' ] );
	}

	public function handle(): void {
		if ( ! check_ajax_referer( 'theme_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
		}

		$cat_slug       = isset( $_POST['cat_slug'] ) ? sanitize_title( wp_unslash( $_POST['cat_slug'] ) ) : '';
		$paged          = max( 1, (int) ( $_POST['paged'] ?? 1 ) );
		$posts_per_page = max( 1, min( 48, (int) ( $_POST['posts_per_page'] ?? 12 ) ) );

		$args = [
			'post_type'      => 'pdn_tv',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'no_found_rows'  => false,
		];

		if ( $cat_slug ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'taxonomy' => 'tv_cat',
					'field'    => 'slug',
					'terms'    => $cat_slug,
				],
			];
		}

		$query = new WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			$item_index = 0;
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_card( get_the_ID(), $item_index );
				++$item_index;
			}
		} else {
			echo '<div class="laca-gallery-list__empty"><p>' . esc_html__( 'Chưa có video nào.', 'laca' ) . '</p></div>';
		}
		$cards_html = ob_get_clean();

		$archive_url      = get_post_type_archive_link( 'pdn_tv' ) ?: '';
		$pagination_html = $this->render_pagination_html( $paged, (int) $query->max_num_pages, (string) $archive_url );

		$active_label = __( 'Tất cả', 'laca' );
		if ( $cat_slug ) {
			$term = get_term_by( 'slug', $cat_slug, 'tv_cat' );
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
	 * Pagination for pdn_tv archive.
	 *
	 * @param int    $paged       Current page.
	 * @param int    $total_pages Total pages.
	 * @param string $archive_url From get_post_type_archive_link( 'pdn_tv' ).
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

	private function get_youtube_url( int $post_id ): string {
		$ytb_url = trim( (string) carbon_get_post_meta( $post_id, 'ytb_url' ) );
		return $ytb_url ? esc_url_raw( $ytb_url ) : '';
	}

	/**
	 * @param int $post_id    Post ID.
	 * @param int $item_index Stagger AOS delay (0-based).
	 */
	private function render_card( int $post_id, int $item_index = 0 ): void {
		$investor    = carbon_get_post_meta( $post_id, 'investor' );
		$floors      = carbon_get_post_meta( $post_id, 'floors' );
		$location    = carbon_get_post_meta( $post_id, 'location' );
		$area        = carbon_get_post_meta( $post_id, 'total_area' );
		$youtube_url = $this->get_youtube_url( $post_id );
		$delay_ms    = $item_index * 100;
		?>
		<article
			class="laca-gallery-card"
			data-aos="fade-up"
			data-aos-delay="<?php echo esc_attr( (string) $delay_ms ); ?>"
		>
			<?php if ( $youtube_url ) : ?>
				<a class="laca-gallery-card__link" href="<?php echo esc_url( $youtube_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php else : ?>
				<div class="laca-gallery-card__link">
			<?php endif; ?>
				<div class="laca-gallery-card__img">
					<?php if ( has_post_thumbnail( $post_id ) ) : ?>
						<?php echo get_the_post_thumbnail( $post_id, 'large', [ 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ] ); ?>
					<?php else : ?>
						<div class="laca-gallery-card__img-placeholder"></div>
					<?php endif; ?>
				</div>

				<div class="laca-gallery-card__body">
					<div class="laca-gallery-card__title-wrap">
						<span class="laca-gallery-card__yt-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
								<path d="m9.75 15.5 6.5-3.5-6.5-3.5v7Z"/>
							</svg>
						</span>
						<h3 class="laca-gallery-card__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
					</div>
					<ul class="laca-gallery-card__meta">
						<?php if ( $investor ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Chủ đầu tư:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $investor ); ?></span></li>
						<?php endif; ?>
						<?php if ( $area ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Diện tích:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $area ); ?></span></li>
						<?php endif; ?>
						<?php if ( $location ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Địa chỉ:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $location ); ?></span></li>
						<?php endif; ?>
						<?php if ( $floors ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Số tầng:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $floors ); ?></span></li>
						<?php endif; ?>
					</ul>
				</div>
			<?php if ( $youtube_url ) : ?>
				</a>
			<?php else : ?>
				</div>
			<?php endif; ?>
		</article>
		<?php
	}
}

new PdnTvAjaxHandler();

