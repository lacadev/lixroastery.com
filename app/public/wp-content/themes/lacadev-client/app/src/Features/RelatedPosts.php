<?php

namespace App\Features;

/**
 * RelatedPosts
 *
 * Hiển thị 3 bài liên quan cuối mỗi single post.
 * Logic ưu tiên: cùng tag > cùng category > bài mới nhất.
 *
 * Nhúng qua filter the_content (tự động) hoặc
 * gọi trực tiếp bằng shortcode [laca_related_posts].
 */
class RelatedPosts
{
    public function init(): void
    {
        add_filter('the_content', [$this, 'appendToContent'], 99);
        add_shortcode('laca_related_posts', [$this, 'renderShortcode']);
    }

    public function appendToContent(string $content): string
    {
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        return $content . $this->render(get_the_ID());
    }

    public function renderShortcode(array $atts): string
    {
        return $this->render(get_the_ID());
    }

    private function render(int $postId): string
    {
        $posts = $this->getRelated($postId);
        if (empty($posts)) {
            return '';
        }

        ob_start();
        ?>
        <div class="laca-related-posts">
            <h3 class="laca-related-posts__title">Bài viết liên quan</h3>
            <div class="laca-related-posts__grid">
                <?php foreach ($posts as $post): setup_postdata($post); ?>
                <article class="laca-related-posts__item">
                    <a href="<?php echo esc_url(get_permalink($post)); ?>" class="laca-related-posts__thumb-link">
                        <?php if (has_post_thumbnail($post)): ?>
                            <?php echo get_the_post_thumbnail($post, 'medium', ['class' => 'laca-related-posts__thumb', 'loading' => 'lazy']); ?>
                        <?php else: ?>
                            <div class="laca-related-posts__no-thumb"></div>
                        <?php endif; ?>
                    </a>
                    <div class="laca-related-posts__meta">
                        <span class="laca-related-posts__date"><?php echo esc_html(get_the_date('d/m/Y', $post)); ?></span>
                    </div>
                    <h4 class="laca-related-posts__post-title">
                        <a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                    </h4>
                    <p class="laca-related-posts__excerpt">
                        <?php echo esc_html(wp_trim_words(get_the_excerpt($post), 15, '…')); ?>
                    </p>
                </article>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
        </div>

        <style>
        .laca-related-posts { margin: 48px 0 0; padding-top: 32px; border-top: 1px solid #eee; }
        .laca-related-posts__title { font-size: 1.2rem; font-weight: 700; margin: 0 0 20px; }
        .laca-related-posts__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        @media (max-width: 640px) { .laca-related-posts__grid { grid-template-columns: 1fr; } }
        @media (min-width: 641px) and (max-width: 900px) { .laca-related-posts__grid { grid-template-columns: 1fr 1fr; } }
        .laca-related-posts__item { display: flex; flex-direction: column; gap: 8px; }
        .laca-related-posts__thumb-link { display: block; border-radius: 6px; overflow: hidden; aspect-ratio: 16/9; }
        .laca-related-posts__thumb { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; }
        .laca-related-posts__thumb-link:hover .laca-related-posts__thumb { transform: scale(1.04); }
        .laca-related-posts__no-thumb { width: 100%; height: 100%; background: #f0f0f1; }
        .laca-related-posts__meta { font-size: 11px; color: #999; }
        .laca-related-posts__post-title { font-size: 14px; font-weight: 700; margin: 0; line-height: 1.4; }
        .laca-related-posts__post-title a { color: inherit; text-decoration: none; }
        .laca-related-posts__post-title a:hover { color: var(--primary-color, #2271b1); }
        .laca-related-posts__excerpt { font-size: 12px; color: #666; margin: 0; line-height: 1.5; }
        [data-theme="dark"] .laca-related-posts { border-color: #333; }
        [data-theme="dark"] .laca-related-posts__no-thumb { background: #2a2a2a; }
        [data-theme="dark"] .laca-related-posts__excerpt { color: #aaa; }
        </style>
        <?php
        return ob_get_clean();
    }

    private function getRelated(int $postId, int $count = 3): array
    {
        $args = [
            'post_type'      => get_post_type($postId),
            'posts_per_page' => $count,
            'post__not_in'   => [$postId],
            'no_found_rows'  => true,
            'orderby'        => 'relevance',
        ];

        // Try matching tags first
        $tags = wp_get_post_tags($postId, ['fields' => 'ids']);
        if (!empty($tags)) {
            $args['tag__in'] = $tags;
            $query = new \WP_Query($args);
            if ($query->have_posts() && count($query->posts) >= $count) {
                return $query->posts;
            }
        }

        // Fallback: same category
        unset($args['tag__in']);
        $cats = wp_get_post_categories($postId);
        if (!empty($cats)) {
            $args['category__in'] = $cats;
            $args['orderby']      = 'date';
            $args['order']        = 'DESC';
            $query = new \WP_Query($args);
            if ($query->have_posts()) {
                return $query->posts;
            }
        }

        // Last resort: latest posts
        unset($args['category__in']);
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
        $query = new \WP_Query($args);
        return $query->posts;
    }
}
