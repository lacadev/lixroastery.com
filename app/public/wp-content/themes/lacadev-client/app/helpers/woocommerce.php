<?php

add_filter('woocommerce_empty_price_html', fn() => __('<span>Liên hệ</span>', 'laca'));

/**
 * Ajax thêm vào giỏ hàng
 */
add_action('wp_ajax_nopriv_gm_add_to_cart', 'ajaxAddToCart');
add_action('wp_ajax_gm_add_to_cart', 'ajaxAddToCart');
function ajaxAddToCart()
{
	if (empty($_POST['product_id'])) {
		wp_send_json_error(__('ID sản phẩm không hợp lệ', 'laca'));
	}

	$product_id = apply_filters('woocommerce_add_to_cart_product_id', absint(wp_unslash($_POST['product_id'])));
	$quantity = empty($_POST['quantity']) ? 1 : apply_filters('woocommerce_stock_amount', absint(wp_unslash($_POST['quantity'])));
	$variation_id = isset($_POST['variation_id']) ? absint(wp_unslash($_POST['variation_id'])) : 0;
	$variation = isset($_POST['variation']) ? (array) $_POST['variation'] : [];

	if (wc()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation)) {
		WC_AJAX::get_refreshed_fragments(); // this calls wp_die() internally
	} else {
		wp_send_json_error(__('Thêm vào giỏ thất bại', 'laca'));
	}
}

/**
 * Lấy danh mục sản phẩm gốc
 */
function getRootProductCategories()
{
	return get_terms([
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'parent'     => 0,
	]);
}

/**
 * Lấy sản phẩm theo danh mục
 */
function getProductsByCategory(WP_Term $productCat, $productCount = null)
{
	$productCount = $productCount ?: get_option('posts_per_page');
	return new WP_Query([
		'post_type'      => 'product',
		'posts_per_page' => $productCount,
		'tax_query'      => [[
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $productCat->term_id,
		]],
	]);
}

/**
 * Lấy sản phẩm nổi bật
 */
function getFeaturedProducts($productCount = null)
{
	$productCount = $productCount ?: get_option('posts_per_page');
	return new WP_Query([
		'post_type'      => 'product',
		'posts_per_page' => $productCount,
		'tax_query'      => [[
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => 'featured',
		]],
	]);
}

/**
 * Lấy sản phẩm giảm giá
 */
function getIsOnSaleProducts($productCount = null)
{
	$productCount = $productCount ?: get_option('posts_per_page');
	$product_ids_on_sale = wc_get_product_ids_on_sale();
	
	if (empty($product_ids_on_sale)) {
		return new WP_Query(['post_type' => 'product', 'post__in' => [0]]);
	}

	return new WP_Query([
		'post_type'      => 'product',
		'posts_per_page' => $productCount,
		'post__in'       => $product_ids_on_sale,
		'post_status'    => 'publish',
	]);
}

/**
 * Lấy sản phẩm bán chạy
 */
function getBestSellingProducts($productCount = null)
{
	$productCount = $productCount ?: get_option('posts_per_page');
	return new WP_Query([
		'post_type'      => 'product',
		'posts_per_page' => $productCount,
		'meta_key'       => 'total_sales',
		'orderby'        => 'meta_value_num',
	]);
}

/**
 * Lấy sản phẩm đánh giá cao
 */
function getTopRatingProducts($productCount = null)
{
	$productCount = $productCount ?: get_option('posts_per_page');
	return new WP_Query([
		'post_type'      => 'product',
		'posts_per_page' => $productCount,
		'meta_key'       => '_wc_average_rating',
		'orderby'        => 'meta_value_num',
	]);
}

/**
 * Lấy phần trăm giảm giá của sản phẩm
 */
function getProductPercentageSaleOff($product)
{
	if (!$product || !$product->is_on_sale()) return 0;

	if ($product->is_type('variable')) {
		$percentages = [];
		$prices = $product->get_variation_prices();
		foreach ($prices['regular_price'] as $vid => $regular_price) {
			$sale_price = $prices['sale_price'][$vid];
			if ($regular_price > 0 && $sale_price < $regular_price) {
				$percentages[] = ($regular_price - $sale_price) / $regular_price * 100;
			}
		}
		return count($percentages) ? round(max($percentages)) : 0;
	} else {
		$regular_price = (float) $product->get_regular_price();
		$sale_price = (float) $product->get_sale_price();

		if ($regular_price > 0 && $sale_price > 0 && $regular_price > $sale_price) {
			return round(($regular_price - $sale_price) / $regular_price * 100);
		}
	}

	return 0;
}

function theProductPercentageSaleOff()
{
	global $product;
	$percent = getProductPercentageSaleOff($product);
	if ($percent) {
		echo "<span class=\"product__percent-sale-off\">{$percent}%</span>";
	}
}

/**
 * Lấy giá sản phẩm
 */
function getProductPrice(WC_Product $product)
{
	if ($product->is_type('variable')) {
		return $product->get_variation_price('min');
	}

	$regular_price = $product->get_regular_price();
	$sale_price = $product->get_sale_price();

	if ($sale_price) {
		echo "<div class='price-product'>
                <span class='price regular-price'>" . number_format($regular_price, 0, ',', '.') . " VND</span>
                <span class='price sale-price'>" . number_format($sale_price, 0, ',', '.') . " VND</span>
            </div>";
	} else {
		echo "<div class='price-product'>
                <span class='price regular-price'>" . number_format($regular_price, 0, ',', '.') . " VND</span>
            </div>";
	}
}

function theProductPrice()
{
	global $product;
	getProductPrice($product);
}
