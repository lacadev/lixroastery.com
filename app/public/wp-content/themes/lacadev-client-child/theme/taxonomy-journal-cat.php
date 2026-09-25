<?php
/**
 * App Layout: layouts/app.php
 *
 * Template cho archive danh mục journal-cat (/journal-cat/{slug}) — hiện tab
 * các danh mục "anh em" (con của cùng 1 cha), nội dung bên dưới là LƯỚI BÀI
 * VIẾT thật của tab đang active. Tái dùng đúng cơ chế AJAX của block CPT
 * Grid (CptGridAjaxHandler, cpt-grid.js, laca_cpt_grid_render_cards()) vì
 * bản chất vẫn là lọc bài viết theo 1 term — chỉ khác chỗ danh sách tab ở
 * đây là "các con/anh em của 1 cha cụ thể", không phải "tất cả danh mục cấp
 * 1" như ở archive-journal.php.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_term = get_queried_object();
if (!($current_term instanceof WP_Term)) {
    return;
}

$taxonomy  = $current_term->taxonomy;
$post_type = 'journal';

// Đang xem 1 danh mục CHA (có con) → tab = các con của nó, active mặc định
// = con đầu tiên (giống thiết kế: vào /journal-cat/find-the-origin thấy
// ngay tab "Genetic" active). Đang xem 1 danh mục LÁ (không có con) → tab =
// các "anh em" (con khác cùng cha), active = chính nó — để điều hướng nhất
// quán dù vào từ URL cha hay URL con.
$children = get_terms(['taxonomy' => $taxonomy, 'parent' => $current_term->term_id, 'hide_empty' => false]);
$children = is_wp_error($children) ? [] : $children;

if (!empty($children)) {
    $tabs = $children;
    // get_terms() sắp xếp theo tên (alphabet), không phải thứ tự "có ý
    // nghĩa" — nếu mặc định chọn tab đầu tiên theo alphabet mà tab đó chưa
    // có bài nào thì sẽ ra danh sách rỗng ngay lần đầu tải trang, dù các
    // tab khác có bài. Ưu tiên chọn tab ĐẦU TIÊN (theo thứ tự hiển thị)
    // thực sự có bài viết; nếu không tab nào có bài thì đành lấy tab đầu.
    $active_slug = $tabs[0]->slug;
    foreach ($tabs as $t) {
        if ((int) $t->count > 0) {
            $active_slug = $t->slug;
            break;
        }
    }
} elseif ($current_term->parent) {
    $siblings = get_terms(['taxonomy' => $taxonomy, 'parent' => $current_term->parent, 'hide_empty' => false]);
    $tabs = is_wp_error($siblings) || empty($siblings) ? [$current_term] : $siblings;
    $active_slug = $current_term->slug;
} else {
    // Danh mục cấp 1 không có con — chỉ 1 tab là chính nó.
    $tabs = [$current_term];
    $active_slug = $current_term->slug;
}

$per_page = (int) get_option('posts_per_page', 9);
$query = new WP_Query([
    'post_type'      => $post_type,
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => 1,
    'no_found_rows'  => false,
    'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery
        [
            'taxonomy'         => $taxonomy,
            'field'            => 'slug',
            'terms'            => $active_slug,
            'include_children' => true,
        ],
    ],
]);
$max_pages = (int) $query->max_num_pages;

$unique_id = wp_unique_id('lix-journal-cat-');
$config = [
    'action'         => 'laca_cpt_grid_load',
    'nonce'          => wp_create_nonce('theme_nonce'),
    'ajaxurl'        => admin_url('admin-ajax.php'),
    'postType'       => $post_type,
    'taxonomy'       => $taxonomy,
    'paginationMode' => 'numbered',
    'perPagePC'      => $per_page,
    'perPageMobile'  => 0,
    'minCount'       => $per_page,
    'currentPage'    => 1,
    'maxPages'       => $max_pages,
];
?>

<section class="journal-archive">
    <div class="container">
        <h1 class="journal-archive__title"><?php echo esc_html($current_term->name); ?></h1>
        <?php if ($current_term->description) : ?>
            <div class="journal-archive__desc"><?php echo wp_kses_post(wpautop($current_term->description)); ?></div>
        <?php endif; ?>

        <div
            class="block-cpt-grid__inner"
            id="<?php echo esc_attr($unique_id); ?>"
            data-cpt-grid-config='<?php echo esc_attr(wp_json_encode($config)); ?>'
        >
            <?php if (count($tabs) > 1) : ?>
                <div class="block-cpt-grid__tabs-wrap">
                    <button type="button" class="block-cpt-grid__tabs-nav block-cpt-grid__tabs-nav--prev"><?php esc_html_e('Prev', 'laca'); ?></button>
                    <div class="block-cpt-grid__tabs">
                        <?php foreach ($tabs as $tab_term) : ?>
                            <button
                                type="button"
                                class="block-cpt-grid__tab<?php echo $tab_term->slug === $active_slug ? ' is-active' : ''; ?>"
                                data-term-slug="<?php echo esc_attr($tab_term->slug); ?>"
                            ><?php echo esc_html($tab_term->name); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="block-cpt-grid__tabs-nav block-cpt-grid__tabs-nav--next"><?php esc_html_e('Next', 'laca'); ?></button>
                </div>
            <?php endif; ?>

            <div class="block-cpt-grid__list block-cpt-grid__list--cols-3">
                <?php laca_cpt_grid_render_cards($query, $taxonomy); ?>
            </div>

            <div class="block-cpt-grid__pagination">
                <?php
                echo lacadev_child_pagination_markup([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    'base'    => '#cgpage-%#%',
                    'format'  => '',
                    'current' => 1,
                    'total'   => $max_pages,
                ]);
                ?>
            </div>
        </div>
    </div>
</section>
