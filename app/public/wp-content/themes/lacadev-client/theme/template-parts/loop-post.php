<?php
global $post;
$postID = $post->ID;
$url = get_the_permalink($postID);
$thumbnail = getResponsivePostThumbnail($postID);
$title = get_the_title($postID);
$excerpt = get_the_excerpt($postID);
$category = get_the_terms($postID, 'blog_cat');
?>

<div class="loop-service">
	<a href="<?php echo esc_url($url); ?>">
		<div class="inner">
			<figure>
				<?php echo $thumbnail; ?>
			</figure>

			<div class="content">
				<?php 
				if ($title) :
					echo '<h3 class="heading"> ' . esc_html($title) . '</h3>';
				endif;

				if ($excerpt) :
					echo '<div class="desc"> ' . esc_html($excerpt) . '</div>';
				endif;
				?>
			</div>
		</div>
	</a>
</div>