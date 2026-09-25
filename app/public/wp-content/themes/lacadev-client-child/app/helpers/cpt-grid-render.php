<?php
/**
 * Shared card markup cho block "CPT Grid" (block-cpt-grid) — dùng chung giữa
 * lần render đầu (render.php, SSR) và AJAX handler (CptGridAjaxHandler.php)
 * để tránh viết trùng markup ở 2 nơi.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * In ra 1 card bài viết cho block CPT Grid.
 *
 * @param int    $post_id  ID bài viết.
 * @param string $taxonomy Taxonomy dùng làm nhãn danh mục trên card (rỗng = không hiện nhãn).
 */
function laca_cpt_grid_render_card(int $post_id, string $taxonomy = ''): void
{
    $title   = get_the_title($post_id);
    $url     = get_permalink($post_id);
    $excerpt = has_excerpt($post_id)
        ? get_the_excerpt($post_id)
        : wp_trim_words(wp_strip_all_tags(get_the_content(null, false, $post_id)), 20);

    $term_name = '';
    if ($taxonomy && taxonomy_exists($taxonomy)) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (!empty($terms) && !is_wp_error($terms)) {
            $term_name = $terms[0]->name;
        }
    }

    $date = get_the_date('d M', $post_id);
    ?>
    <article class="block-cpt-grid__card">
        <a class="block-cpt-grid__card-link" href="<?php echo esc_url($url); ?>">
            <div class="block-cpt-grid__card-thumb">
                <?php if (has_post_thumbnail($post_id)) : ?>
                    <?php echo get_the_post_thumbnail($post_id, 'large', ['loading' => 'lazy', 'alt' => esc_attr($title)]); ?>
                <?php else : ?>
                    <div class="block-cpt-grid__card-thumb-placeholder"></div>
                <?php endif; ?>
            </div>
            <div class="block-cpt-grid__card-body">
                <?php if ($term_name || $date) : ?>
                    <p class="block-cpt-grid__card-meta">
                        <?php if ($term_name) : ?><span><?php echo esc_html(mb_strtoupper($term_name)); ?></span><?php endif; ?>
                        <?php if ($term_name && $date) : ?><span class="block-cpt-grid__card-meta-sep">|</span><?php endif; ?>
                        <?php if ($date) : ?><span><?php echo esc_html(mb_strtoupper($date)); ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
                <h3 class="block-cpt-grid__card-title"><?php echo esc_html($title); ?></h3>
                <?php if ($excerpt) : ?>
                    <p class="block-cpt-grid__card-excerpt"><?php echo esc_html($excerpt); ?></p>
                <?php endif; ?>
            </div>
        </a>
    </article>
    <?php
}

/**
 * In ra danh sách card cho 1 WP_Query — dùng chung cho SSR lẫn AJAX.
 *
 * @param WP_Query $query    Query đã chạy (have_posts()/the_post()).
 * @param string   $taxonomy Taxonomy dùng làm nhãn danh mục trên card.
 */
function laca_cpt_grid_render_cards(WP_Query $query, string $taxonomy = ''): void
{
    if (!$query->have_posts()) {
        echo '<div class="block-cpt-grid__empty"><p>' . esc_html__('Chưa có bài viết nào.', 'laca') . '</p></div>';
        return;
    }

    while ($query->have_posts()) {
        $query->the_post();
        laca_cpt_grid_render_card(get_the_ID(), $taxonomy);
    }
    wp_reset_postdata();
}
