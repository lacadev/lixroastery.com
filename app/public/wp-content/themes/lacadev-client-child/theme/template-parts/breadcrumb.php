<?php
if (function_exists('carbon_get_post_meta') && carbon_get_post_meta(get_the_ID(), 'hide_breadcrumb')) {
	return;
}

$banner = getPostThumbnailUrl(get_the_ID());
$title = getPageTitle();
?>
<div class="breadcumb">
	<div class="container">
		<?php
		if ( function_exists('rank_math_the_breadcrumbs') ) :
			rank_math_the_breadcrumbs();
		endif;
		?>
	</div>
</div>