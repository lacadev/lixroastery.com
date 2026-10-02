<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trang archive sản phẩm (/shop VÀ /product-category/{slug} — WooCommerce
 * tự route taxonomy-product-cat.php về đúng file này qua
 * wc_get_template('archive-product.php'), xem file đó) — THAY 100% bản
 * mặc định của WooCommerce (loop + pagination riêng của WC) bằng cùng cơ
 * chế "CPT Grid" (tab lọc theo taxonomy + AJAX, xem
 * block-gutenberg/.../block-cpt-grid/render.php +
 * resources/scripts/theme/components/cpt-grid.js +
 * app/src/Ajax/CptGridAjaxHandler.php) đã dùng cho /journal-cat, để:
 * - Heading + mô tả: ở /shop lấy từ CHÍNH trang "Shop" thật (WooCommerce →
 *   Settings → Products → Shop page) — admin tự soạn nội dung qua Block
 *   Editor như soạn 1 Page bình thường, không cần sửa code mỗi khi đổi mô
 *   tả (cùng mô hình với laca_render_dynamic_cpt_archive_intro() dùng cho
 *   Dynamic CPT, nhưng "product" là CPT của WooCommerce nên không nằm
 *   trong danh sách Dynamic CPT — tự lấy qua wc_get_page_id('shop') thay vì
 *   gọi hàm đó). Ở /product-category/{slug} thì lấy tên + mô tả của CHÍNH
 *   danh mục đang xem (giống hệt cách taxonomy-journal-cat.php làm).
 * - Tab lọc = danh mục sản phẩm THẬT (product_cat, đã có dữ liệu sẵn),
 *   không bịa taxonomy "xuất xứ" mới. Tab tương ứng danh mục đang xem (nếu
 *   có) được đánh dấu active + lưới chỉ lọc đúng danh mục đó ngay từ đầu.
 * - Card sản phẩm = laca_render_product_grid_card() (qua
 *   laca_cpt_grid_render_card() tự delegate khi post type "product") để
 *   đồng bộ với Product Grid block và trang kết quả tìm kiếm.
 *
 * @package LacaDevClientChild
 */

get_header('shop');

$taxonomy  = 'product_cat';
$post_type = 'product';

$current_term = null;
if (is_tax($taxonomy)) {
    $queried = get_queried_object();
    if ($queried instanceof WP_Term && $taxonomy === $queried->taxonomy) {
        $current_term = $queried;
    }
}

$terms   = taxonomy_exists($taxonomy) ? laca_get_top_level_terms_with_content($taxonomy) : [];
$tax_obj = get_taxonomy($taxonomy);
$active_term_slug = $current_term ? $current_term->slug : '';

$per_page   = 8;
$query_args = [
    'post_type'      => $post_type,
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => 1,
    'no_found_rows'  => false,
];
if ($current_term) {
    $query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
        [
            'taxonomy'         => $taxonomy,
            'field'            => 'slug',
            'terms'            => $active_term_slug,
            'include_children' => true,
        ],
    ];
}
$query     = new WP_Query($query_args);
$max_pages = (int) $query->max_num_pages;

$unique_id = wp_unique_id('lix-shop-archive-');
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

// ── Heading + mô tả: danh mục đang xem (nếu có), ngược lại dùng trang Shop ──
$shop_page = null;
if (!$current_term) {
    $shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
    $shop_page    = $shop_page_id > 0 ? get_post($shop_page_id) : null;
    if ($shop_page && ('publish' !== $shop_page->post_status || 'page' !== $shop_page->post_type)) {
        $shop_page = null;
    }
}
?>

<section class="journal-archive shop-archive">
    <div class="container-fluid">
        <?php if ($current_term) : ?>
            <h1 class="journal-archive__title"><?php echo esc_html($current_term->name); ?></h1>
            <?php if ($current_term->description) : ?>
                <div class="journal-archive__desc"><?php echo wp_kses_post(wpautop($current_term->description)); ?></div>
            <?php endif; ?>
        <?php elseif ($shop_page) : ?>
            <h1 class="journal-archive__title"><?php echo esc_html(get_the_title($shop_page)); ?></h1>
            <div class="journal-archive__desc dynamic-cpt-archive-intro">
                <?php echo apply_filters('the_content', $shop_page->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php else : ?>
            <h1 class="journal-archive__title"><?php post_type_archive_title(); ?></h1>
        <?php endif; ?>

        <div class="block-cpt-grid__inner" id="<?php echo esc_attr($unique_id); ?>"
            data-cpt-grid-config='<?php echo esc_attr(wp_json_encode($config)); ?>'>

            <?php if (!empty($terms) && !is_wp_error($terms)) : ?>
                <div class="block-cpt-grid__tabs-wrap">
                    <div class="block-cpt-grid__tabs">
                        <button type="button" class="block-cpt-grid__tab<?php echo $current_term ? '' : ' is-active'; ?>" data-term-slug="">
                            <?php echo esc_html($tax_obj->labels->all_items ?? __('Tất cả', 'laca')); ?>
                        </button>
                        <?php foreach ($terms as $term) : ?>
                            <button type="button" class="block-cpt-grid__tab<?php echo $term->slug === $active_term_slug ? ' is-active' : ''; ?>" data-term-slug="<?php echo esc_attr($term->slug); ?>">
                                <?php echo esc_html($term->name); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="block-cpt-grid__list block-cpt-grid__list--cols-4">
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

<?php
get_footer('shop');
