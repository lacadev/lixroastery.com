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
// .block-top-hero là con đầu tiên của #main_content).
$laca_first_block_name = null;
foreach (parse_blocks(get_the_content()) as $laca_block) {
	if (!empty($laca_block['blockName'])) {
		$laca_first_block_name = $laca_block['blockName'];
		break;
	}
}
$laca_page_starts_with_top_hero = $laca_first_block_name === 'lacadev/top-hero-block';

if (!is_front_page() && is_page() && !$laca_page_starts_with_top_hero):
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