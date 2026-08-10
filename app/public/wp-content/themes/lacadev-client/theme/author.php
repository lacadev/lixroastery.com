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
?>
<div class="page-listing">
	<div class="container">
		<?php
		theBreadcrumb();
		?>
		<h2 class="title-block">Các bài viết của tác giả: <?php the_author(); ?></h2>

		<div class="row gy-5">
			<?php
			$author_id = get_the_author_meta('ID');
			$args = array(
				'author' => $author_id,
				'post_type' => array('service', 'blog'),
			);
			$author_query = new WP_Query($args);
			?>
			<?php
			if ( $author_query->have_posts() ) :
				while ( $author_query->have_posts() ) : $author_query->the_post();
					get_template_part('template-parts/loop','post');
				endwhile;
			else :
				echo 'Không có bài viết nào.';
			endif;
			wp_reset_postdata();
			?>
		</div>
	</div>
</div>


