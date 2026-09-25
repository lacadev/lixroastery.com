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

						<!-- Menu chính — bên trái -->
						<nav class="header__nav header__nav--left" aria-label="<?php esc_attr_e('Main menu', 'laca'); ?>">
							<?php
							wp_nav_menu([
								'theme_location' => 'main-menu',
								'container' => false,
								'items_wrap' => '<ul class="%2$s">%3$s</ul>',
								'menu_class' => 'header__menu-list',
								'walker' => new Laca_Menu_Walker(),
								'fallback_cb' => false,
							]);
							?>
						</nav>

						<!-- Logo / tên site — giữa -->
						<div class="header__logo">
							<a href="<?php echo esc_url(home_url('/')); ?>" class="header__logo-link">
								<?php
								$logo_id = carbon_get_theme_option('logo');
								$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
								if ($logo_url):
									?>
									<img src="<?php echo esc_url($logo_url); ?>" class="header__logo-img"
										alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
								<?php else: ?>
									<span class="header__logo-text"><?php bloginfo('name'); ?></span>
								<?php endif; ?>
							</a>
						</div>

						<!-- Menu phụ + đổi ngôn ngữ — bên phải -->
						<div class="header__right">
							<nav class="header__nav header__nav--right"
								aria-label="<?php esc_attr_e('Secondary menu', 'laca'); ?>">
								<?php
								wp_nav_menu([
									'theme_location' => 'header-right-menu',
									'container' => false,
									'items_wrap' => '<ul class="%2$s">%3$s</ul>',
									'menu_class' => 'header__menu-list',
									'walker' => new Laca_Menu_Walker(),
									'fallback_cb' => false,
								]);
								?>
							</nav>
							<?php laca_language_switcher_hover(); ?>
						</div>

						<!-- Hamburger (mobile) -->
						<div class="header__hamburger" id="btn-hamburger"
							aria-label="<?php esc_attr_e('Mở menu', 'laca'); ?>" role="button" tabindex="0"
							aria-expanded="false" aria-controls="header-overlay">
							<span></span>
							<span></span>
							<span></span>
						</div>

					</div>
				</div>

				<!-- Mobile overlay: gộp cả 2 menu + ngôn ngữ -->
				<div class="header__overlay" id="header-overlay" aria-hidden="true">
					<div class="header__overlay-backdrop"></div>
					<div class="header__overlay-panel">
						<button class="header__overlay-close" id="btn-overlay-close"
							aria-label="<?php esc_attr_e('Đóng menu', 'laca'); ?>">
							<span></span>
							<span></span>
						</button>
						<nav class="header__overlay-nav" aria-label="<?php esc_attr_e('Main menu mobile', 'laca'); ?>">
							<?php
							wp_nav_menu([
								'theme_location' => 'main-menu',
								'container' => false,
								'items_wrap' => '<ul class="%2$s">%3$s</ul>',
								'menu_class' => 'header__overlay-menu-list',
								'walker' => new Laca_Menu_Walker(),
								'fallback_cb' => false,
							]);
							wp_nav_menu([
								'theme_location' => 'header-right-menu',
								'container' => false,
								'items_wrap' => '<ul class="%2$s">%3$s</ul>',
								'menu_class' => 'header__overlay-menu-list header__overlay-menu-list--secondary',
								'walker' => new Laca_Menu_Walker(),
								'fallback_cb' => false,
							]);
							?>
						</nav>
						<?php laca_language_switcher_hover(); ?>
					</div>
				</div>
			</header>
		<?php endif; ?>