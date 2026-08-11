<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Theme header partial.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">

<head>
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
	<link rel="profile" href="http://gmpg.org/xfn/11" />
	<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />
	<?php wp_head(); ?>

	<link rel="apple-touch-icon" sizes="57x57" href="<?php theAsset('favicon/apple-icon-57x57.png'); ?>">
	<link rel="apple-touch-icon" sizes="60x60" href="<?php theAsset('favicon/apple-icon-60x60.png'); ?>">
	<link rel="apple-touch-icon" sizes="72x72" href="<?php theAsset('favicon/apple-icon-72x72.png'); ?>">
	<link rel="apple-touch-icon" sizes="76x76" href="<?php theAsset('favicon/apple-icon-76x76.png'); ?>">
	<link rel="apple-touch-icon" sizes="114x114" href="<?php theAsset('favicon/apple-icon-114x114.png'); ?>">
	<link rel="apple-touch-icon" sizes="120x120" href="<?php theAsset('favicon/apple-icon-120x120.png'); ?>">
	<link rel="apple-touch-icon" sizes="144x144" href="<?php theAsset('favicon/apple-icon-144x144.png'); ?>">
	<link rel="apple-touch-icon" sizes="152x152" href="<?php theAsset('favicon/apple-icon-152x152.png'); ?>">
	<link rel="apple-touch-icon" sizes="180x180" href="<?php theAsset('favicon/apple-icon-180x180.png'); ?>">
	<link rel="icon" type="image/png" sizes="192x192" href="<?php theAsset('favicon/android-icon-192x192.png'); ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php theAsset('favicon/favicon-32x32.png'); ?>">
	<link rel="icon" type="image/png" sizes="96x96" href="<?php theAsset('favicon/favicon-96x96.png'); ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php theAsset('favicon/favicon-16x16.png'); ?>">
	<link rel="manifest" href="<?php theAsset('favicon/manifest.json'); ?>">
	<meta name="msapplication-TileColor" content="#ffffff">
	<meta name="msapplication-TileImage" content="<?php theAsset('favicon/ms-icon-144x144.png'); ?>">
	<meta name="theme-color" content="#ffffff">
	<?php
	$critical_css_path = get_template_directory() . '/dist/styles/critical.css';
	if (file_exists($critical_css_path)) {
		echo '<style id="critical-css">' . file_get_contents($critical_css_path) . '</style>';
	}
	?>
</head>

<body <?php body_class(); ?>>
	<?php
	app_shim_wp_body_open();
	?>

	<!-- Skip to content link for accessibility -->
	<a class="skip-link screen-reader-text" href="#main-content">
		<?php esc_html_e('Skip to content', 'laca'); ?>
	</a>

	<?php
	if (is_home() || is_front_page()):
		echo '<h1 class="site-name screen-reader-text">' . esc_html(get_bloginfo('name')) . '</h1>';
	endif;
	?>

	<div class="wrapper">
		<?php if (!is_404()): ?>
			<header class="header" id="header">
				<div class="container-fluid">
					<div class="header__inner">


					</div>
				</div>
			</header>
		<?php endif; ?>