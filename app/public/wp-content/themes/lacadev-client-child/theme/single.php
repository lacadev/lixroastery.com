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
$is_journal = get_post_type() === 'journal';
?>

<article class="single-post">
	<div class="container-fluid">
		<div class="single-post__content">
			<?php if ($is_journal && function_exists('laca_journal_render_content')): ?>
				<?php laca_journal_render_content($post_id); ?>
			<?php else: ?>
				<?php theContent(); ?>
			<?php endif; ?>
		</div>

		<?php if ($is_journal && function_exists('laca_journal_render_footer')): ?>
			<?php laca_journal_render_footer($post_id); ?>
		<?php endif; ?>
	</div>

	<?php if ($is_journal && function_exists('laca_render_journal_related_products')): ?>
		<?php laca_render_journal_related_products($post_id); ?>
	<?php endif; ?>
</article>
