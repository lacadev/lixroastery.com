<?php

/**
 * Cart AJAX Handler
 *
 * Xử lý AJAX cho popup giỏ hàng ở header (xóa 1 dòng / đổi số lượng) — trả
 * lại HTML giỏ hàng đã render mới (laca_render_mini_cart(), app/helpers/
 * mini-cart-render.php) để JS thay thẳng vào popup, không reload trang.
 * KHÔNG có action "thêm vào giỏ" riêng — sản phẩm được thêm qua nút
 * "Add to cart" sẵn có của WooCommerce ở trang sản phẩm/archive (native
 * AJAX), handler này chỉ lo thao tác BÊN TRONG popup đã mở.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class CartAjaxHandler
{
    public function __construct()
    {
        add_action('wp_ajax_laca_cart_remove', [$this, 'handleRemove']);
        add_action('wp_ajax_nopriv_laca_cart_remove', [$this, 'handleRemove']);

        add_action('wp_ajax_laca_cart_update_qty', [$this, 'handleUpdateQty']);
        add_action('wp_ajax_nopriv_laca_cart_update_qty', [$this, 'handleUpdateQty']);

        // Badge số lượng ở icon giỏ hàng tự cập nhật qua cơ chế fragment có
        // sẵn của WooCommerce (wc-cart-fragments, tự enqueue khi WooCommerce
        // active) — chạy sau MỌI lần add-to-cart thật trên site, kể cả từ
        // nút add-to-cart gốc ở trang sản phẩm (không đi qua handler này).
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'cartCountFragment']);
    }

    public function handleRemove(): void
    {
        if (!function_exists('WC') || !check_ajax_referer('theme_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid request'], 403);
        }

        $cart_key = isset($_POST['cart_key']) ? sanitize_text_field(wp_unslash($_POST['cart_key'])) : '';
        if (!$cart_key) {
            wp_send_json_error(['message' => 'Missing cart_key'], 400);
        }

        WC()->cart->remove_cart_item($cart_key);
        WC()->cart->calculate_totals();

        $this->sendCartHtml();
    }

    public function handleUpdateQty(): void
    {
        if (!function_exists('WC') || !check_ajax_referer('theme_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid request'], 403);
        }

        $cart_key = isset($_POST['cart_key']) ? sanitize_text_field(wp_unslash($_POST['cart_key'])) : '';
        $quantity = isset($_POST['quantity']) ? max(0, (int) $_POST['quantity']) : -1;
        if (!$cart_key || $quantity < 0) {
            wp_send_json_error(['message' => 'Invalid cart_key/quantity'], 400);
        }

        if ($quantity === 0) {
            WC()->cart->remove_cart_item($cart_key);
        } else {
            WC()->cart->set_quantity($cart_key, $quantity);
        }
        WC()->cart->calculate_totals();

        $this->sendCartHtml();
    }

    private function sendCartHtml(): void
    {
        ob_start();
        laca_render_mini_cart();
        $html = ob_get_clean();

        wp_send_json_success([
            'html'  => $html,
            'count' => WC()->cart->get_cart_contents_count(),
        ]);
    }

    public function cartCountFragment(array $fragments): array
    {
        if (function_exists('WC')) {
            $fragments['.header__cart-count'] = '<span class="header__cart-count">'
                . (int) WC()->cart->get_cart_contents_count() . '</span>';
        }
        return $fragments;
    }
}

new CartAjaxHandler();
