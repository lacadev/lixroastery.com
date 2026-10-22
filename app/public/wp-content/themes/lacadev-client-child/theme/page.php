<?php
/**
 * App Layout: layouts/app.php
 *
 * This is the template that is used for displaying all pages by default.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WPEmergeTheme
 */
?>
<?php
// Trang có block Top Hero (lacadev/top-hero-block) làm block ĐẦU TIÊN thì
// không in breadcrumb — theo thiết kế, header phải nằm sát/đè lên hero,
// không có khoảng trắng breadcrumb chen giữa làm lệch "móc nối" margin-top
// âm của .block-top-hero với main#main_content (xem block-top-hero/style.scss
// + resources/styles/theme/layout/_general.scss, cả 2 cùng giả định
// .block-top-hero là con đầu tiên của #main_content). Cùng điều kiện này
// cũng được theme/header.php dùng để gắn class overlay trong suốt lên
// header — xem app/helpers/top-hero-helpers.php.
if (!is_front_page() && is_page() && !laca_page_starts_with_top_hero()):
	echo get_template_part('template-parts/breadcrumb');
endif;

if (is_front_page()):
	the_content();
else:
	// R1: Template KHÔNG bọc nội dung block trong container/container-fluid.
	// Bọc bằng div.entry-content (không padding ngang).
	// Core block tự nhận lề qua container-gutter trong CSS (R3).
	?>
	<div class="entry-content">
		<?php
		the_content();
		?>
	</div>
	<?php
endif;
?>