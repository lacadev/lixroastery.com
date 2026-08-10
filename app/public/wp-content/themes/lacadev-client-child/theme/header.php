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
	<style>
		:root {
			/* Theme colors */
			--primary-color:
				<?php echo carbon_get_theme_option('primary_color'); ?>
			;
			--second-color:
				<?php echo carbon_get_theme_option('secondary_color'); ?>
			;
			--bg-color:
				<?php echo carbon_get_theme_option('bg_color'); ?>
			;
		}
	</style>
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
			<?php
			// ── Get menu items once, split into two halves ─────────────
			$menu_location = 'main-menu';
			$menu_locations = get_nav_menu_locations();
			$menu_obj = isset($menu_locations[$menu_location]) ? wp_get_nav_menu_object($menu_locations[$menu_location]) : null;
			$menu_items = $menu_obj ? wp_get_nav_menu_items($menu_obj->term_id) : [];

			// Populate contextual classes like current-menu-item/current-menu-ancestor.
			if (!empty($menu_items) && function_exists('_wp_menu_item_classes_by_context')) {
				_wp_menu_item_classes_by_context($menu_items);
			}

			// Only top-level items (parent = 0) for the split
			$top_items = array_values(array_filter($menu_items, fn($item) => (int) $item->menu_item_parent === 0));
			$total = count($top_items);
			$half = (int) ceil($total / 2);
			$left_ids = array_map(fn($i) => $i->ID, array_slice($top_items, 0, $half));
			$right_ids = array_map(fn($i) => $i->ID, array_slice($top_items, $half));

			// Children indexed by parent ID
			$children = [];
			foreach ($menu_items as $item) {
				$pid = (int) $item->menu_item_parent;
				if ($pid !== 0) {
					$children[$pid][] = $item;
				}
			}

			// Helper: lấy grandchildren (level 3) cho một child item
			$grandchildren = [];
			foreach ($menu_items as $item) {
				$pid = (int) $item->menu_item_parent;
				if ($pid !== 0 && !isset($children[$pid])) {
					// cấp 3: parent là một trong children của top-level
					$grandchildren[$pid][] = $item;
				}
			}
			// Cập nhật lại $children: chỉ là cấp 2 (direct children of top-level)
			// $children đã đúng vì chỉ filter pid !== 0, nhưng cần tách grandchildren ra
			// Rebuild: children[top_id] = chỉ items có parent là top_id
			$top_ids_all = array_map(fn($i) => $i->ID, $top_items);
			$lvl2 = []; // direct children of top-level
			$lvl3 = []; // children of lvl2 items
			foreach ($menu_items as $item) {
				$pid = (int) $item->menu_item_parent;
				if (in_array($pid, $top_ids_all, true)) {
					$lvl2[$pid][] = $item;
				} elseif ($pid !== 0) {
					$lvl3[$pid][] = $item;
				}
			}

			// Allowed classes to keep on menu items
			$allowed_item_classes = ['mega-menu'];

			// Helper: render <li> items for a set of top-level IDs
			$normalize_path = static function (string $url): string {
				$url = trim($url);
				if ($url === '') {
					return '';
				}
				$parts = wp_parse_url($url);
				$path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';
				if ($path === '') {
					$path = '/';
				}
				return $path;
			};
			$current_request = $normalize_path(home_url((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));
			if ($current_request === '') {
				$current_request = $normalize_path(home_url('/'));
			}

			// Match parent Laca_Menu_Walker / menus.php: strip WP false-positive current_* on CPT views for unrelated menu items (e.g. blog page marked parent on project archive).
			$cpt_for_menu = get_query_var('post_type');
			if (is_array($cpt_for_menu)) {
				$cpt_for_menu = reset($cpt_for_menu);
			}
			if (!$cpt_for_menu && is_post_type_archive()) {
				$qo_archive = get_queried_object();
				$cpt_for_menu = ($qo_archive instanceof WP_Post_Type) ? $qo_archive->name : '';
			}
			if (!$cpt_for_menu && is_singular()) {
				$cpt_for_menu = get_post_type();
			}

			$strip_false_cpt_active = static function ($menu_item) use ($cpt_for_menu): array {
				$classes = is_array($menu_item->classes ?? null) ? array_values((array) $menu_item->classes) : [];
				if (!$cpt_for_menu || $cpt_for_menu === 'post' || $cpt_for_menu === 'page') {
					return $classes;
				}
				$belongs = ($menu_item->type === 'post_type_archive' && $menu_item->object === $cpt_for_menu);
				if (!$belongs && $menu_item->type === 'taxonomy') {
					$tax = get_taxonomy($menu_item->object);
					$belongs = $tax && in_array($cpt_for_menu, (array) $tax->object_type, true);
				}
				if (!$belongs) {
					$classes = array_diff($classes, [
						'current_page_parent',
						'current_page_ancestor',
						'current-menu-parent',
						'current-menu-ancestor',
						'current_page_item',
						'current-menu-item',
					]);
				}
				return $classes;
			};

			$is_item_active = static function ($menu_item) use ($normalize_path, $current_request, $strip_false_cpt_active): bool {
				$item_classes = $strip_false_cpt_active($menu_item);
				$active_classes = [
					'current-menu-item',
					'current-menu-parent',
					'current-menu-ancestor',
					'current_page_item',
					'current_page_parent',
					'current_page_ancestor',
				];
				if (!empty(array_intersect($active_classes, $item_classes))) {
					return true;
				}

				$item_url = $normalize_path((string) ($menu_item->url ?? ''));
				if ($item_url !== '' && $item_url === $current_request) {
					return true;
				}

				// Front page fallback: ensure "Home" item can be highlighted.
				if (is_front_page()) {
					$front_page_id = (int) get_option('page_on_front');
					$item_object_id = isset($menu_item->object_id) ? (int) $menu_item->object_id : 0;

					if (in_array('menu-item-home', $item_classes, true)) {
						return true;
					}

					if ($front_page_id > 0 && $item_object_id === $front_page_id) {
						return true;
					}

					if ($item_url === '/' || $item_url === '') {
						return true;
					}
				}

				return false;
			};

			$render_items = function (array $ids) use ($menu_items, $lvl2, $lvl3, $allowed_item_classes, $is_item_active): void {
				foreach ($menu_items as $item) {
					if (!in_array($item->ID, $ids, true))
						continue;

					// Build classes for this top-level item
					$raw = array_intersect((array) $item->classes, $allowed_item_classes);
					$kids = $lvl2[$item->ID] ?? [];
					if ($kids)
						$raw[] = 'has-children';
					$active = $is_item_active($item);
					if ($active)
						$raw[] = 'actived-menu';
					$class_str = implode(' ', array_filter(array_unique($raw)));

					echo '<li class="menu-item ' . esc_attr($class_str) . '">';
					echo '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';

					if ($kids) {
						echo '<ul class="sub-menu">';
						foreach ($kids as $child) {
							$grandkids = $lvl3[$child->ID] ?? [];
							$child_raw = $grandkids ? ['has-children'] : [];
							if ($is_item_active($child)) {
								$child_raw[] = 'actived-menu';
							}
							$child_cls = implode(' ', array_filter($child_raw));
							echo '<li class="menu-item' . ($child_cls ? ' ' . esc_attr($child_cls) : '') . '">';
							echo '<a href="' . esc_url($child->url) . '">' . esc_html($child->title) . '</a>';
							if ($grandkids) {
								echo '<ul class="sub-menu">';
								foreach ($grandkids as $grand) {
									$grand_cls = $is_item_active($grand) ? ' actived-menu' : '';
									echo '<li class="menu-item' . esc_attr($grand_cls) . '"><a href="' . esc_url($grand->url) . '">' . esc_html($grand->title) . '</a></li>';
								}
								echo '</ul>';
							}
							echo '</li>';
						}
						echo '</ul>';
					}
					echo '</li>';
				}
			};
			?>
			<header class="header" id="header">
				<div class="container-fluid">
					<div class="header__inner">

						<!-- Left nav (first half) -->
						<div class="header__nav-left">
							<nav class="header__nav" aria-label="<?php esc_attr_e('Main menu left', 'laca'); ?>">
								<ul class="header__menu-list header__menu-list--left">
									<?php $render_items($left_ids); ?>
								</ul>
							</nav>
						</div>

						<!-- Logo — center -->
						<div class="header__logo">
							<?php
							$logo_id = carbon_get_theme_option('logo');
							$logo_url = wp_get_attachment_image_url($logo_id, 'full');
							if ($logo_url):
								?>
								<a href="<?php echo esc_url(home_url('/')); ?>" class="header__logo-link">
									<img src="<?php echo esc_url($logo_url); ?>" class="header__logo-img"
										alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
								</a>
							<?php endif; ?>
						</div>

						<!-- Right nav (second half) -->
						<div class="header__nav-right">
							<nav class="header__nav" aria-label="<?php esc_attr_e('Main menu right', 'laca'); ?>">
								<ul class="header__menu-list header__menu-list--right">
									<?php $render_items($right_ids); ?>
								</ul>
							</nav>
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

				<!-- Mobile overlay: full menu via wp_nav_menu -->
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
								'menu_class' => 'header__overlay-menu-list',
								'container' => false,
								'items_wrap' => '<ul class="%2$s">%3$s</ul>',
								'walker' => new Laca_Menu_Walker(),
							]);
							?>
						</nav>
					</div>
				</div>
			</header>
		<?php endif; ?>