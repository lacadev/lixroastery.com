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
	<div class="container-fluid">
		<div class="single-post__content">
			<?php theContent(); ?>
		</div>
	</div>

	<?php if (get_post_type() === 'journal' && function_exists('laca_render_journal_related_products')): ?>
		<?php laca_render_journal_related_products($post_id); ?>
	<?php endif; ?>
</article>