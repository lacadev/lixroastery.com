<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Card Glossary cho trang kết quả tìm kiếm (theme/search.php) — cùng lỗi
 * với loop-product.php/loop-journal.php: "glossary" là post type public
 * thật (xem theme/archive-glossary.php) nên search sẽ trả về kết quả,
 * nhưng get_template_part('template-parts/loop', 'glossary') từng không
 * có file, render ra rỗng.
 *
 * Glossary không có ảnh đại diện (archive-glossary.php cũng không dùng
 * thumbnail, chỉ title = thuật ngữ + content = định nghĩa) nên card này
 * KHÔNG có <figure>, chỉ còn phần .content bên trong .loop-service — CSS
 * (_search-results.scss) không bắt buộc phải có figure.
 */
global $post;
$postID     = $post->ID;
$url        = get_the_permalink($postID);
$title      = get_the_title($postID);
$definition = wp_trim_words(wp_strip_all_tags($post->post_content), 24);
?>

<div class="loop-service loop-glossary">
    <a href="<?php echo esc_url($url); ?>">
        <div class="inner">
            <div class="content">
                <?php if ($title) : ?>
                    <h3 class="heading"><?php echo esc_html($title); ?></h3>
                <?php endif; ?>

                <?php if ($definition) : ?>
                    <div class="desc"><?php echo esc_html($definition); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </a>
</div>
