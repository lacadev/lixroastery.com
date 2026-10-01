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
if (!is_front_page() && is_page()):
	echo get_template_part('template-parts/breadcrumb');
endif;

if (is_front_page()):
	the_content();
else:
	// .container — block mặc định (paragraph/heading/list/table...) không tự
	// có khung như các block riêng của theme (mỗi block custom đã tự gắn
	// class container/container-fluid vào section của chính nó), nên trước
	// đây in ra sát mép trái/phải màn hình, không đọc được. Page thường
	// (About/Terms/Privacy...) hầu như chỉ dùng block mặc định nên đóng khung
	// theo .container y hệt phần còn lại của site.
	?>
	<div class="wrapper-content">
		<?php
		the_content();
		?>
	</div>
	<?php
endif;
?>