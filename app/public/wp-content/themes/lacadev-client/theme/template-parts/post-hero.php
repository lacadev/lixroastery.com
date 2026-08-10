<!-- Hero Section -->
<div class="post-hero">
    <div class="container">
        <h1 class="post-title"><?php the_title(); ?></h1>
        <div class="post-meta">
            <span class="meta-item">
                <?php 
                if (get_post_type() === 'service') {
                    _e('Dịch vụ chuyên nghiệp', 'laca');
                } else {
                    the_category(', ');
                }
                ?>
            </span>
            <span class="meta-separator">•</span>
            <span class="meta-item"><?php echo get_the_date('d/m/Y'); ?></span>
            <span class="meta-separator">•</span>
            <span class="meta-item"><span class="dashicons dashicons-visibility" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px; vertical-align: middle;"></span><?php theViewCount(); ?> lượt xem</span>
        </div>
    </div>
</div>
