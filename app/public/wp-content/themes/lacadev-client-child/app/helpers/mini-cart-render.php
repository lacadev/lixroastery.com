<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render nội dung popup giỏ hàng (danh sách sản phẩm + tổng tiền + nút
 * thanh toán) — dùng CHUNG giữa theme/header.php (render lúc tải trang) và
 * App\Ajax\CartAjaxHandler (render lại sau khi xóa/đổi số lượng), tránh lặp
 * markup (cùng nguyên tắc với laca_render_product_grid_cards(),
 * app/helpers/product-grid-render.php).
 */
if (!function_exists('laca_render_mini_cart')) {
    function laca_render_mini_cart(): void
    {
        if (!function_exists('WC')) {
            return;
        }

        $cart = WC()->cart;
        $count = $cart->get_cart_contents_count();

        // Dòng phụ "N sản phẩm" — in LUÔN TRONG hàm này (không phải ở
        // header.php) vì cả hàm được gọi lại nguyên khối sau mỗi lần AJAX
        // xóa/đổi số lượng (CartAjaxHandler) — số lượng phải cập nhật
        // theo, không thể tách ra ngoài phần bị AJAX ghi đè.
        printf(
            '<p class="header__cart-count-label">%s</p>',
            esc_html(
                sprintf(
                    /* translators: %d: số lượng sản phẩm trong giỏ — tiếng Việt không chia số ít/nhiều nên không cần _n() */
                    __('%d sản phẩm', 'laca'),
                    $count
                )
            )
        );

        if ($cart->is_empty()) {
            echo '<p class="header__cart-empty">' . esc_html__('Giỏ hàng trống.', 'laca') . '</p>';
            return;
        }
        ?>
        <ul class="header__cart-list">
            <?php foreach ($cart->get_cart() as $cart_key => $cart_item) :
                $product = $cart_item['data'];
                if (!$product || !$product->exists() || $cart_item['quantity'] <= 0) {
                    continue;
                }
                $permalink = $product->get_permalink($cart_item);
                $thumbnail = $product->get_image('thumbnail');
                $name      = $product->get_name();
                $quantity  = (int) $cart_item['quantity'];
                $subtotal  = WC()->cart->get_product_subtotal($product, $quantity);
            ?>
                <li class="header__cart-item" data-cart-key="<?php echo esc_attr($cart_key); ?>">
                    <div class="header__cart-item-image">
                        <a href="<?php echo esc_url($permalink); ?>">
                            <?php echo wp_kses_post($thumbnail); ?>
                        </a>
                        <button type="button" class="header__cart-remove" data-cart-action="remove" aria-label="<?php esc_attr_e('Xóa khỏi giỏ hàng', 'laca'); ?>">&times;</button>
                    </div>
                    <div class="header__cart-item-info">
                        <div class="header__cart-item-row">
                            <a href="<?php echo esc_url($permalink); ?>" class="header__cart-item-name">
                                <?php echo esc_html($name); ?>
                            </a>
                            <div class="header__cart-item-qty">
                                <button type="button" class="header__cart-qty-btn" data-cart-action="decrease" aria-label="<?php esc_attr_e('Giảm số lượng', 'laca'); ?>">−</button>
                                <span class="header__cart-qty-value"><?php echo esc_html($quantity); ?></span>
                                <button type="button" class="header__cart-qty-btn" data-cart-action="increase" aria-label="<?php esc_attr_e('Tăng số lượng', 'laca'); ?>">+</button>
                            </div>
                        </div>
                        <div class="header__cart-item-subtotal"><?php echo wp_kses_post($subtotal); ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="header__cart-footer">
            <div class="header__cart-subtotal">
                <span><?php esc_html_e('Tổng cộng', 'laca'); ?></span>
                <strong><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></strong>
            </div>
            <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="header__cart-checkout">
                <?php esc_html_e('Thanh toán', 'laca'); ?>
            </a>
            <p class="header__cart-note">
                <?php esc_html_e('Đã bao gồm thuế. Phí vận chuyển và mã giảm giá tính ở bước thanh toán.', 'laca'); ?>
            </p>
        </div>
        <?php
    }
}
