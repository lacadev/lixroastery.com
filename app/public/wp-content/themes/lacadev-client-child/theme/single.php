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

	theBreadcrumb();
	$post_id = get_the_ID();
?>

<article class="single-post">

	<!-- Hero Section (title + meta) -->
	<?php get_template_part( 'template-parts/post-hero' ); ?>

	<div class="container">

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="single-post__featured-image">
				<?php the_post_thumbnail( 'full', [ 'loading' => 'eager', 'class' => 'single-post__featured-img' ] ); ?>
			</div>
		<?php endif; ?>

		<!-- Two-column layout: Main + Sidebar -->
		<div class="single-post__layout">

			<!-- ── MAIN ───────────────────────────────────────── -->
			<div class="single-post__main">

				<!-- Content Body -->
				<div class="single-post__content">
					<?php theContent(); ?>
				</div>

				<!-- Tags -->
				<?php
				$tags = get_the_tags();
				if ( $tags ) :
				?>
				<div class="single-post__tags">
					<?php foreach ( $tags as $tag ) : ?>
						<a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="single-post__tag">
							#<?php echo esc_html( $tag->name ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- Share -->
				<div class="single-post__share">
					<?php get_template_part( 'template-parts/share_box' ); ?>
				</div>

				<!-- Rating -->
				<?php get_template_part( 'template-parts/rating-box' ); ?>

			</div><!-- /.single-post__main -->

			<!-- ── SIDEBAR ────────────────────────────────────── -->
			<aside class="single-post__sidebar">

				<!-- Contact box (tái dùng style từ single-project) -->
				<div class="project-contact-box">
					<h3 class="project-contact-box__title">Liên hệ tư vấn</h3>
					<p class="project-contact-box__desc">
						Để lại thông tin liên hệ của bạn hoặc gọi hotline để được tư vấn sớm nhất.
					</p>
					<form class="project-contact-form" method="post">
						<div class="project-contact-form__field">
							<input type="text" name="contact_name" placeholder="Họ và tên" class="project-contact-form__input" required>
						</div>
						<div class="project-contact-form__field">
							<input type="tel" name="contact_phone" placeholder="Số điện thoại" class="project-contact-form__input" required>
						</div>
						<div class="project-contact-form__field">
							<input type="text" name="contact_address" placeholder="Địa chỉ" class="project-contact-form__input">
						</div>
						<button type="submit" class="project-contact-form__submit">Gửi thông tin ngay</button>
					</form>
				</div>

				<!-- Related posts -->
				<?php
				$cats = wp_get_post_categories( $post_id );
				$related_args = [
					'post_type'      => 'post',
					'posts_per_page' => 4,
					'post__not_in'   => [ $post_id ],
					'orderby'        => 'rand',
				];
				if ( ! empty( $cats ) ) {
					$related_args['category__in'] = $cats;
				}
				$related = new WP_Query( $related_args );
				if ( $related->have_posts() ) :
				?>
				<div class="post-related">
					<h3 class="post-related__title">Bài viết liên quan</h3>
					<ul class="post-related__list">
					<?php while ( $related->have_posts() ) : $related->the_post(); ?>
						<li class="post-related__item">
							<a href="<?php the_permalink(); ?>" class="post-related__link">
								<?php if ( has_post_thumbnail() ) : ?>
								<div class="post-related__thumb">
									<?php the_post_thumbnail( 'thumbnail', [ 'class' => 'post-related__img', 'loading' => 'lazy' ] ); ?>
								</div>
								<?php endif; ?>
								<div class="post-related__info">
									<span class="post-related__name"><?php the_title(); ?></span>
									<span class="post-related__date"><?php echo get_the_date( 'd/m/Y' ); ?></span>
								</div>
							</a>
						</li>
					<?php endwhile; wp_reset_postdata(); ?>
					</ul>
				</div>
				<?php endif; ?>

			</aside><!-- /.single-post__sidebar -->

		</div><!-- /.single-post__layout -->

	</div><!-- /.container -->
</article>