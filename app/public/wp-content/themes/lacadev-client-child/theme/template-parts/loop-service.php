<?php
global $post;
$postID = $post->ID;
$url = get_the_permalink($postID);
$title = get_the_title($postID);
$excerpt = get_the_excerpt($postID);
$first_letter = mb_substr($title, 0, 1);
?>

<article class="block-service__item loop-service">
	<a href="<?php echo esc_url($url); ?>" class="item__link">
		<span class="item__icon"><?php echo esc_html($first_letter); ?></span>
		
		<div class="content">
			<?php if ($title) : ?>
				<h3 class="item__title"><?php echo esc_html($title); ?></h3>
			<?php endif; ?>

			<?php if ($excerpt) : ?>
				<div class="item__desc">
					<?php echo esc_html($excerpt); ?>
				</div>
			<?php endif; ?>
		</div>
	</a>
</article>
