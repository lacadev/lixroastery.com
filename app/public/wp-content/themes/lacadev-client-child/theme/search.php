<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Override trang kết quả tìm kiếm (parent lacadev-client/theme/search.php)
 * để khớp thiết kế Figma: heading = từ khóa thật, dòng phụ "{n} kết quả
 * cho ...", và 1 hàng tab điều hướng neo tới từng nhóm post type.
 *
 * GIỮ NGUYÊN 100% phần truy vấn/pagination/markup "search-section" +
 * "load-more-btn" của bản gốc (parent) vì JS load-more
 * (resources/scripts/theme/pages/search.js) và AJAX handler
 * (lacadev_load_more_search(), parent app/helpers/ajax.php) đang bám đúng
 * các class/data-attribute này — đổi là gãy "Xem thêm".
 *
 * @package lacadev-child
 */

get_header();

$search_query = get_search_query();

// Cùng cách nhóm post type với bản gốc parent.
$post_types = get_post_types(['public' => true], 'objects');
$organized_types = [
    'product' => [],
    'post' => [],
    'page' => [],
    'other' => [],
];

foreach ($post_types as $post_type) {
    $type_name = $post_type->name;

    if ($type_name === 'attachment') {
        continue;
    }

    if ($type_name === 'product') {
        $organized_types['product'][] = $type_name;
    } elseif ($type_name === 'post') {
        $organized_types['post'][] = $type_name;
    } elseif ($type_name === 'page') {
        $organized_types['page'][] = $type_name;
    } else {
        $organized_types['other'][] = $type_name;
    }
}

/**
 * Chạy TRƯỚC toàn bộ WP_Query (không render ngay) để biết tổng số kết quả
 * và danh sách tab (chỉ nhóm có kết quả) TRƯỚC khi in heading/tab-nav —
 * 2 khối này nằm TRÊN các section trong layout mới, khác bản gốc (vốn in
 * luôn từng section ngay khi query xong, không có hàng tab).
 */
$sections = [];

if (!empty($organized_types['product']) && class_exists('WooCommerce')) {
    $paged_product = max(1, get_query_var('paged_product', 1));
    $products = new WP_Query([
        'post_type' => 'product',
        'posts_per_page' => 8,
        's' => $search_query,
        'post_status' => 'publish',
        'paged' => $paged_product,
    ]);
    if ($products->have_posts()) {
        update_post_caches($products->posts, 'product', true, true);
        $sections['product'] = [
            'label' => __('Sản phẩm', 'laca'),
            'query' => $products,
            'template_slug' => 'product',
            'paged' => $paged_product,
        ];
    }
}

if (!empty($organized_types['post'])) {
    $paged_post = max(1, get_query_var('paged_post', 1));
    $posts = new WP_Query([
        'post_type' => 'post',
        'posts_per_page' => 8,
        's' => $search_query,
        'post_status' => 'publish',
        'paged' => $paged_post,
    ]);
    if ($posts->have_posts()) {
        update_post_caches($posts->posts, 'post', true, true);
        update_object_term_cache(wp_list_pluck($posts->posts, 'ID'), 'post');
        $sections['post'] = [
            'label' => __('Bài viết', 'laca'),
            'query' => $posts,
            'template_slug' => 'post',
            'paged' => $paged_post,
        ];
    }
}

if (!empty($organized_types['page'])) {
    $paged_page = max(1, get_query_var('paged_page', 1));
    $pages = new WP_Query([
        'post_type' => 'page',
        'posts_per_page' => 8,
        's' => $search_query,
        'post_status' => 'publish',
        'paged' => $paged_page,
    ]);
    if ($pages->have_posts()) {
        update_post_caches($pages->posts, 'page', true, true);
        $sections['page'] = [
            'label' => __('Trang', 'laca'),
            'query' => $pages,
            'template_slug' => 'post',
            'paged' => $paged_page,
        ];
    }
}

if (!empty($organized_types['other'])) {
    foreach ($organized_types['other'] as $custom_type) {
        $paged_var = 'paged_' . $custom_type;
        $paged_custom = max(1, get_query_var($paged_var, 1));
        $custom_posts = new WP_Query([
            'post_type' => $custom_type,
            'posts_per_page' => 8,
            's' => $search_query,
            'post_status' => 'publish',
            'paged' => $paged_custom,
        ]);
        if ($custom_posts->have_posts()) {
            $post_type_obj = get_post_type_object($custom_type);
            $sections[$custom_type] = [
                'label' => $post_type_obj->labels->name,
                'query' => $custom_posts,
                'template_slug' => $custom_type,
                'paged' => $paged_custom,
            ];
        }
    }
}

$total_results = 0;
foreach ($sections as $section) {
    $total_results += (int) $section['query']->found_posts;
}

$active_key = array_key_first($sections);
?>


<div class="search-results-page">
    <?php theBreadcrumb(); ?>

    <div class="container-fluid">
        <h1 class="search-results-page__heading">
            <?php echo esc_html($search_query); ?>
        </h1>
        <p class="search-results-page__subtitle">
            <?php
            printf(
                /* translators: 1: số kết quả, 2: từ khóa tìm kiếm */
                esc_html__('%1$d kết quả cho "%2$s"', 'laca'),
                (int) $total_results,
                esc_html($search_query)
            );
            ?>
        </p>

        <?php if (!empty($sections)): ?>
            <nav class="search-results-page__tabs" aria-label="<?php esc_attr_e('Lọc theo loại kết quả', 'laca'); ?>">
                <?php foreach ($sections as $key => $section): ?>
                    <a href="#search-section-<?php echo esc_attr($key); ?>"
                        class="search-results-page__tab<?php echo $key === $active_key ? ' is-active' : ''; ?>">
                        <?php echo esc_html($section['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php foreach ($sections as $key => $section):
            $query = $section['query'];
            $total_displayed = ($section['paged'] - 1) * 8 + $query->post_count;
            ?>
            <section id="search-section-<?php echo esc_attr($key); ?>"
                class="search-section search-section--<?php echo esc_attr($key); ?>">
                <h2 class="search-section__title">
                    <strong><?php printf(esc_html__('%s liên quan', 'laca'), esc_html($section['label'])); ?></strong>
                    <span class="search-section__count" data-displayed="<?php echo (int) $total_displayed; ?>"
                        data-total="<?php echo (int) $query->found_posts; ?>">
                        (hiển thị <?php echo (int) $total_displayed; ?>/<?php echo (int) $query->found_posts; ?>)
                    </span>:
                </h2>
                <div class="list-post">
                    <?php
                    // Dynamic CPT tạo qua DynamicCptManager chỉ tự sinh
                    // archive-{slug}.php/single-{slug}.php, KHÔNG sinh
                    // template-parts/loop-{slug}.php — nếu thiếu file này,
                    // get_template_part() render ra rỗng (badge "hiển thị
                    // x/y" vẫn đúng, chỉ lưới trống) cho MỌI CPT mới tạo sau
                    // này. Dùng loop-post.php (card chung) làm fallback.
                    $templateSlug = $section['template_slug'];
                    if (!locate_template("template-parts/loop-{$templateSlug}.php")) {
                        $templateSlug = 'post';
                    }
                    while ($query->have_posts()) {
                        $query->the_post();
                        get_template_part('template-parts/loop', $templateSlug);
                    }
                    wp_reset_postdata();
                    ?>
                </div>
                <?php if ($query->max_num_pages > 1): ?>
                    <div class="load-more-container text-center mt-4">
                        <button class="load-more-btn btn btn-primary" data-post-type="<?php echo esc_attr($key); ?>"
                            data-search="<?php echo esc_attr($search_query); ?>" data-page="1"
                            data-max-pages="<?php echo (int) $query->max_num_pages; ?>">
                            <?php esc_html_e('Xem thêm', 'laca'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if (empty($sections)): ?>
            <div class="search-results-page__empty text-center my-5">
                <h3><?php printf(esc_html__('Không tìm thấy kết quả nào cho "%s"', 'laca'), esc_html($search_query)); ?>
                </h3>
                <p><?php esc_html_e('Vui lòng thử lại với từ khóa khác.', 'laca'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
