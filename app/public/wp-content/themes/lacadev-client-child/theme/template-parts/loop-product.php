<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Card sản phẩm cho trang kết quả tìm kiếm (theme/search.php) — gọi qua
 * get_template_part('template-parts/loop', 'product') từ CẢ 2 nơi: tải
 * trang lần đầu (search.php) VÀ load-more AJAX (parent lacadev_load_more_search(),
 * app/helpers/ajax.php).
 *
 * Dùng LẠI đúng laca_render_product_grid_card() (app/helpers/product-grid-render.php)
 * — card y hệt block Product Grid, đồng bộ giao diện sản phẩm trên toàn
 * site thay vì có 1 kiểu card riêng cho trang search. Bọc ngoài 1 div
 * ".loop-service" (không style gì, chỉ để search.js đếm đúng số item mới
 * sau mỗi lần "Xem thêm", xem resources/scripts/theme/pages/search.js).
 */
global $post;
?>
<div class="loop-service">
    <?php laca_render_product_grid_card($post->ID); ?>
</div>
