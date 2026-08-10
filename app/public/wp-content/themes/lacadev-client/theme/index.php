<?php
/**
 * App Layout: layouts/app.php
 *
 * The main template file.
 *
 * @package WPEmergeTheme
 */

theBreadcrumb();

// Default layout for main blog index
$layout = 'card';
$wrapper_class = 'block-blog';
?>

<main class="archive-post <?php echo esc_attr($wrapper_class); ?>">
    <?php get_template_part('template-parts/page-hero'); ?>

    <div class="container">
        <div class="archive-content">
            <?php if (have_posts()) : ?>
                <?php
                // START: N+1 Prevention
                $current_post_type = get_post_type() ?: 'post';
                update_post_caches($GLOBALS['wp_query']->posts, $current_post_type, true, true);
                update_object_term_cache(wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'), $current_post_type);
                // END: N+1 Prevention
                ?>

                <div class="blog-list">
                    <?php 
                    while (have_posts()) : the_post(); 
                        $author_name = get_the_author();
                        $time_diff = human_time_diff(get_the_time('U'), current_time('timestamp'));
                        $time_diff_text = sprintf(__('%s ago', 'laca'), $time_diff);
                        ?>
                        <div class="blog-item">
                            <div class="blog-card">
                                <a href="<?php the_permalink(); ?>" class="card-link">
                                    <div class="card-image-wrap">
                                        <?php theResponsivePostThumbnail('mobile', ['alt' => esc_attr(get_the_title())]); ?>
                                    </div>
                                    <div class="card-body">
                                        <h3 class="card-title"><?php the_title(); ?></h3>
                                        <div class="card-meta">
                                            <span class="meta-author"><?php printf(__('By %s', 'laca'), esc_html($author_name)); ?></span>
                                            <span class="meta-date"><?php echo esc_html($time_diff_text); ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <?php thePagination(); ?>

            <?php else : ?>
                <div class="no-posts">
                    <p><?php _e('Chưa có bài viết nào.', 'laca'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php wp_reset_postdata(); ?>