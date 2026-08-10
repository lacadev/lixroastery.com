<?php
/**
 * Search Results Template
 *
 * Displays search results categorized by post type with pagination
 *
 * @package lacadev
 */

get_header();

$search_query = get_search_query();
?>

<div class="page-listing search-results-page">
    <div class="container">
        <?php theBreadcrumb(); ?>
        
        <h1 class="title-single text-center my-5 text-uppercase">
            <?php _e('Kết quả tìm kiếm', 'laca'); ?>
        </h1>
        <h4 class="title-result text-center mb-5">
            <?php printf(__('Từ khóa: "%s"', 'laca'), esc_html($search_query)); ?>
        </h4>

        <?php
        // Get all public post types
        $post_types = get_post_types(['public' => true], 'objects');
        $has_results = false;
        
        // Organize post types
        $organized_types = [
            'product' => [],
            'post' => [],
            'page' => [],
            'other' => []
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
        
        // Search Products
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
                $has_results = true;

                // Pre-cache results for N+1 prevention
                update_post_caches($products->posts, 'product', true, true);
                
                $total_products = $products->found_posts;
                $displayed_products = ($paged_product - 1) * 8 + $products->post_count;
                ?>
                <section class="search-section search-section--products">
                    <h2 class="search-section__title">
                        <strong><?php _e('Sản phẩm liên quan', 'laca'); ?></strong> 
                        <span class="search-section__count" data-displayed="<?php echo $displayed_products; ?>" data-total="<?php echo $total_products; ?>">
                            (hiển thị <?php echo $displayed_products; ?>/<?php echo $total_products; ?>)
                        </span>:
                    </h2>
                    <div class="list-post">
                        <?php
                        while ($products->have_posts()) {
                            $products->the_post();
                            get_template_part('template-parts/loop', 'product');
                        }
                        wp_reset_postdata();
                        ?>
                    </div>
                    <?php if ($products->max_num_pages > 1) : ?>
                        <div class="load-more-container text-center mt-4">
                            <button class="load-more-btn btn btn-primary" 
                                    data-post-type="product" 
                                    data-search="<?php echo esc_attr($search_query); ?>" 
                                    data-page="1"
                                    data-max-pages="<?php echo $products->max_num_pages; ?>">
                                <?php _e('Xem thêm', 'laca'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
                <?php
            }
        }
        
        // Search Posts
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
                $has_results = true;

                // Pre-cache results for N+1 prevention
                update_post_caches($posts->posts, 'post', true, true);
                update_object_term_cache(wp_list_pluck($posts->posts, 'ID'), 'post');

                $total_posts = $posts->found_posts;
                $displayed_posts = ($paged_post - 1) * 8 + $posts->post_count;
                ?>
                <section class="search-section search-section--posts">
                    <h2 class="search-section__title">
                        <strong><?php _e('Bài viết liên quan', 'laca'); ?></strong> 
                        <span class="search-section__count" data-displayed="<?php echo $displayed_posts; ?>" data-total="<?php echo $total_posts; ?>">
                            (hiển thị <?php echo $displayed_posts; ?>/<?php echo $total_posts; ?>)
                        </span>:
                    </h2>
                    <div class="list-post">
                        <?php
                        while ($posts->have_posts()) {
                            $posts->the_post();
                            get_template_part('template-parts/loop', 'post');
                        }
                        wp_reset_postdata();
                        ?>
                    </div>
                    <?php if ($posts->max_num_pages > 1) : ?>
                        <div class="load-more-container">
                            <button class="load-more-btn btn btn-primary" 
                                    data-post-type="post" 
                                    data-search="<?php echo esc_attr($search_query); ?>" 
                                    data-page="1"
                                    data-max-pages="<?php echo $posts->max_num_pages; ?>">
                                <?php _e('Xem thêm', 'laca'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
                <?php
            }
        }
        
        // Search Pages
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
                $has_results = true;

                // Pre-cache results for N+1 prevention
                update_post_caches($pages->posts, 'page', true, true);

                $total_pages = $pages->found_posts;
                $displayed_pages = ($paged_page - 1) * 8 + $pages->post_count;
                ?>
                <section class="search-section search-section--pages">
                    <h2 class="search-section__title">
                        <strong><?php _e('Trang liên quan', 'laca'); ?></strong> 
                        <span class="search-section__count" data-displayed="<?php echo $displayed_pages; ?>" data-total="<?php echo $total_pages; ?>">
                            (hiển thị <?php echo $displayed_pages; ?>/<?php echo $total_pages; ?>)
                        </span>:
                    </h2>
                    <div class="list-post">
                        <?php
                        while ($pages->have_posts()) {
                            $pages->the_post();
                            get_template_part('template-parts/loop', 'post');
                        }
                        wp_reset_postdata();
                        ?>
                    </div>
                    <?php if ($pages->max_num_pages > 1) : ?>
                        <div class="load-more-container">
                            <button class="load-more-btn btn btn-primary" 
                                    data-post-type="page" 
                                    data-search="<?php echo esc_attr($search_query); ?>" 
                                    data-page="1"
                                    data-max-pages="<?php echo $pages->max_num_pages; ?>">
                                <?php _e('Xem thêm', 'laca'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
                <?php
            }
        }
        
        // Search Other Custom Post Types
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
                    $has_results = true;
                    $post_type_obj = get_post_type_object($custom_type);
                    $type_label = $post_type_obj->labels->name;
                    $total_custom = $custom_posts->found_posts;
                    $displayed_custom = ($paged_custom - 1) * 8 + $custom_posts->post_count;
                    ?>
                    <section class="search-section search-section--<?php echo esc_attr($custom_type); ?>">
                        <h2 class="search-section__title">
                            <strong><?php printf(__('%s liên quan', 'laca'), esc_html($type_label)); ?></strong> 
                            <span class="search-section__count" data-displayed="<?php echo $displayed_custom; ?>" data-total="<?php echo $total_custom; ?>">
                                (hiển thị <?php echo $displayed_custom; ?>/<?php echo $total_custom; ?>)
                            </span>:
                        </h2>
                        <div class="list-post">
                            <?php
                            while ($custom_posts->have_posts()) {
                                $custom_posts->the_post();
                                get_template_part('template-parts/loop', $custom_type);
                            }
                            wp_reset_postdata();
                            ?>
                        </div>
                        <?php if ($custom_posts->max_num_pages > 1) : ?>
                            <div class="load-more-container">
                                <button class="load-more-btn btn btn-primary" 
                                        data-post-type="<?php echo esc_attr($custom_type); ?>" 
                                        data-search="<?php echo esc_attr($search_query); ?>" 
                                        data-page="1"
                                        data-max-pages="<?php echo $custom_posts->max_num_pages; ?>">
                                    <?php _e('Xem thêm', 'laca'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php
                }
            }
        }
        
        // No results found
        if (!$has_results) {
            ?>
            <div class="search-results__empty text-center my-5">
                <h3><?php printf(__('Không tìm thấy kết quả nào cho "%s"', 'laca'), esc_html($search_query)); ?></h3>
                <p><?php _e('Vui lòng thử lại với từ khóa khác.', 'laca'); ?></p>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<?php
get_footer();
