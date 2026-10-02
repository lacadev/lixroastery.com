<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Card Journal cho trang kết quả tìm kiếm (theme/search.php) — cùng lỗi
 * với loop-product.php trước đây: get_template_part('template-parts/loop',
 * 'journal') gọi tới file này nhưng nó CHƯA TỪNG TỒN TẠI nên tìm kiếm
 * "journal" luôn báo có kết quả (found_posts > 0) mà không render được gì.
 *
 * Markup giữ CÙNG CẤU TRÚC .loop-service với loop-post.php/loop-product.php
 * (bắt buộc cho JS đếm số item mới ở search.js) — chỉ thêm 1 dòng meta
 * (chuyên mục journal-cat + ngày đăng) cho khớp thiết kế card Journal.
 */
global $post;
$postID    = $post->ID;
$url       = get_the_permalink($postID);
$thumbnail = getResponsivePostThumbnail($postID);
$title     = get_the_title($postID);
$excerpt   = get_the_excerpt($postID);
$date      = get_the_date('d M', $postID);

$category = '';
$terms = get_the_terms($postID, 'journal-cat');
if (!empty($terms) && !is_wp_error($terms)) {
    $category = $terms[0]->name;
}
?>

<div class="loop-service loop-journal">
    <a href="<?php echo esc_url($url); ?>">
        <div class="inner">
            <figure>
                <?php echo $thumbnail; ?>
            </figure>

            <div class="content">
                <div class="meta">
                    <?php if ($category) : ?>
                        <span class="category"><?php echo esc_html($category); ?></span>
                    <?php endif; ?>
                    <span class="date"><?php echo esc_html($date); ?></span>
                </div>

                <?php if ($title) : ?>
                    <h3 class="heading"><?php echo esc_html($title); ?></h3>
                <?php endif; ?>

                <?php if ($excerpt) : ?>
                    <div class="desc"><?php echo esc_html($excerpt); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </a>
</div>
