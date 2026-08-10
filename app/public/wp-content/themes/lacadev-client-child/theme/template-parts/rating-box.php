<div class="rating-box">
    <div class="rating-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="rating-box__body">
        <p class="rating-cta"><?php _e('Đánh giá bài viết', 'laca'); ?></p>
        <div class="rating-stars">
            <?php if (function_exists('kk_star_ratings')) : ?>
                <?php echo kk_star_ratings(); ?>
            <?php endif; ?>
        </div>
    </div>
</div>
