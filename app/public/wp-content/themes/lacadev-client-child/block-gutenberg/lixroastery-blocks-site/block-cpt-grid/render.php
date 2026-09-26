<?php
if (!defined('ABSPATH')) {
    exit;
}

$container_type  = ($attributes['containerType'] ?? 'container-fluid') === 'container' ? 'container' : 'container-fluid';
$post_type       = sanitize_key($attributes['postType'] ?? 'post');
$taxonomy        = sanitize_key($attributes['taxonomy'] ?? '');
$columns         = max(1, min(4, (int) ($attributes['columns'] ?? 3)));
$pagination_mode = ($attributes['paginationMode'] ?? 'numbered') === 'load-more' ? 'load-more' : 'numbered';
$per_page_pc     = max(1, (int) ($attributes['perPagePC'] ?? 9));
$per_page_mobile = max(0, (int) ($attributes['perPageMobile'] ?? 0));
$min_count       = max(1, (int) ($attributes['minCount'] ?? 6));

if (!post_type_exists($post_type)) {
    if (current_user_can('edit_posts')) {
        echo '<div class="block-cpt-grid__admin-notice">'
            . esc_html__('CPT Grid: post type chưa tồn tại hoặc chưa được chọn.', 'laca')
            . '</div>';
    }
    return;
}

if ($taxonomy && (!taxonomy_exists($taxonomy) || !is_object_in_taxonomy($post_type, $taxonomy))) {
    $taxonomy = '';
}

// SSR ban đầu luôn dùng số lượng của PC (an toàn cho SEO/khi JS chưa chạy) —
// nếu đang ở mobile và có cấu hình riêng, JS sẽ tự gọi lại AJAX để sửa ngay
// sau khi trang tải xong (xem resources/scripts/theme/components/cpt-grid.js).
$initial_per_page = $pagination_mode === 'load-more' ? $min_count : $per_page_pc;

$unique_id = wp_unique_id('lix-cpt-grid-');

$query_args = [
    'post_type'      => $post_type,
    'post_status'    => 'publish',
    'posts_per_page' => $initial_per_page,
    'paged'          => 1,
    'no_found_rows'  => false,
];

$query = new WP_Query($query_args);
$max_pages = (int) $query->max_num_pages;

$config = [
    'action'         => 'laca_cpt_grid_load',
    'nonce'          => wp_create_nonce('theme_nonce'),
    'ajaxurl'        => admin_url('admin-ajax.php'),
    'postType'       => $post_type,
    'taxonomy'       => $taxonomy,
    'paginationMode' => $pagination_mode,
    'perPagePC'      => $per_page_pc,
    'perPageMobile'  => $per_page_mobile,
    'minCount'       => $min_count,
    'currentPage'    => 1,
    'maxPages'       => $max_pages,
];

// containerType gắn thẳng vào section này (KHÔNG bọc thêm 1 div riêng) —
// giống class Bootstrap thật (.container/.container-fluid tự là khung
// ngoài cùng), tránh 1 lớp div thừa không cần thiết.
$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-cpt-grid ' . $container_type]);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div
        class="block-cpt-grid__inner"
        id="<?php echo esc_attr($unique_id); ?>"
        data-cpt-grid-config='<?php echo esc_attr(wp_json_encode($config)); ?>'
    >
        <?php if ($taxonomy) :
            // Chỉ hiện danh mục CẤP 1 làm tab (ẩn danh mục con khỏi tab) —
            // bấm 1 tab cha sẽ tự động gộp cả bài viết của các danh mục
            // con bên trong (WP_Query tax_query mặc định include_children).
            $terms = laca_get_top_level_terms_with_content($taxonomy);
            $tax_obj = get_taxonomy($taxonomy);
        ?>
            <?php if (!empty($terms) && !is_wp_error($terms)) : ?>
                <div class="block-cpt-grid__tabs-wrap">
                    <div class="block-cpt-grid__tabs">
                        <button type="button" class="block-cpt-grid__tab is-active" data-term-slug="">
                            <?php echo esc_html($tax_obj->labels->all_items ?? __('All', 'laca')); ?>
                        </button>
                        <?php foreach ($terms as $term) : ?>
                            <button type="button" class="block-cpt-grid__tab" data-term-slug="<?php echo esc_attr($term->slug); ?>">
                                <?php echo esc_html($term->name); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="block-cpt-grid__list block-cpt-grid__list--cols-<?php echo esc_attr((string) $columns); ?>">
            <?php laca_cpt_grid_render_cards($query, $taxonomy); ?>
        </div>

        <?php if ('numbered' === $pagination_mode) : ?>
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
        <?php else : ?>
            <div class="block-cpt-grid__load-more">
                <div class="block-cpt-grid__sentinel" aria-hidden="true"></div>
                <p class="block-cpt-grid__loading-text"><?php esc_html_e('Đang tải thêm…', 'laca'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
