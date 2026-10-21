<?php

namespace App\Features\LacaSeo\Modules\WooCommerce;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * WooCommerce SEO Integration module.
 * Provides Brand taxonomy helper, GTIN hooks, Generator removal, and hidden product controls.
 */
class WooCommerce
{
    public function register(): void
    {
        // 1. Remove WooCommerce generator tag if enabled
        if (Settings::get('general.wc_remove_generator', 'on') === 'on') {
            remove_action('get_the_generator_html', 'wc_generator_tag', 10);
            remove_action('get_the_generator_xhtml', 'wc_generator_tag', 10);
        }

        // 2. GTIN display on single product frontend if enabled
        if (Settings::get('general.show_gtin', 'off') === 'on') {
            add_action('woocommerce_product_meta_start', [$this, 'outputGtinMeta']);
            add_filter('woocommerce_available_variation', [$this, 'filterVariationGtin'], 10, 3);
            add_action('wp_footer', [$this, 'outputVariationScript']);
        }

        // 3. Admin GTIN fields in WooCommerce edit product screens
        if (is_admin()) {
            add_action('woocommerce_product_options_sku', [$this, 'addGtinAdminField']);
            add_action('woocommerce_product_after_variable_attributes', [$this, 'addVariationGtinAdminField'], 10, 3);
            add_action('woocommerce_admin_process_product_object', [$this, 'saveGtinAdminData']);
            add_action('woocommerce_save_product_variation', [$this, 'saveVariationGtinAdminData'], 10, 2);
        }
    }

    /**
     * Retrieve the Brand name of a product based on Settings.
     */
    public static function getBrand(int $productId): string
    {
        $tax = (string) Settings::get('general.product_brand', '');
        if ($tax !== '' && taxonomy_exists($tax)) {
            $terms = get_the_terms($productId, $tax);
            if (!is_wp_error($terms) && !empty($terms[0])) {
                return (string) $terms[0]->name;
            }
        }
        return '';
    }

    /**
     * Get GTIN code for a product or variation.
     */
    public static function getGtinValue(mixed $product): string
    {
        if (!is_object($product)) {
            return '';
        }

        $val = '';
        if (method_exists($product, 'get_meta')) {
            $val = (string) $product->get_meta('_laca_seo_gtin_code');
        }
        if ($val === '' && method_exists($product, 'get_global_unique_id')) {
            $val = (string) $product->get_global_unique_id();
        }

        return $val;
    }

    /**
     * Output GTIN meta in product meta area.
     */
    public function outputGtinMeta(): void
    {
        global $product;
        if (!is_object($product)) {
            return;
        }

        $gtin = self::getGtinValue($product);
        $label = (string) Settings::get('general.gtin_label', 'GTIN:');
        $label = $label !== '' ? rtrim($label) . ' ' : 'GTIN: ';

        $hidden = ($gtin === '') ? 'style="display:none;"' : '';
        echo '<span class="laca-seo-gtin-wrapper" ' . $hidden . '>';
        echo esc_html($label . $gtin);
        echo '</span>';
    }

    /**
     * Add GTIN to variation parameters.
     */
    public function filterVariationGtin(array $args, mixed $product, mixed $variation): array
    {
        $gtin = self::getGtinValue($variation);
        if ($gtin !== '') {
            $label = (string) Settings::get('general.gtin_label', 'GTIN:');
            $label = $label !== '' ? rtrim($label) . ' ' : 'GTIN: ';
            $args['laca_seo_gtin'] = esc_html($label . $gtin);
        }
        return $args;
    }

    /**
     * JS switcher for variable products to update GTIN on option change.
     */
    public function outputVariationScript(): void
    {
        global $product;
        if (!is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
            return;
        }
        ?>
        <script>
        (function() {
            // WHY: WooCommerce triggers 'found_variation' and 'reset_data' exclusively via jQuery events on variations_form.
            if (typeof jQuery === 'undefined') return;
            var $form = jQuery('.variations_form');
            var $wrapper = jQuery('.laca-seo-gtin-wrapper');
            if ($form.length && $wrapper.length) {
                var defaultText = $wrapper.text();
                $form.on('found_variation', function(e, variation) {
                    if (variation && variation.laca_seo_gtin) {
                        $wrapper.text(variation.laca_seo_gtin).show();
                    }
                });
                $form.on('reset_data', function() {
                    $wrapper.text(defaultText);
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Admin simple product GTIN field.
     */
    public function addGtinAdminField(): void
    {
        if (!function_exists('woocommerce_wp_text_input')) {
            return;
        }

        $gtinKey = (string) Settings::get('general.gtin', 'gtin');
        $label = 'GTIN';
        if ($gtinKey === 'isbn') {
            $label = 'ISBN';
        } elseif ($gtinKey === 'mpn') {
            $label = 'MPN';
        }

        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id'          => '_laca_seo_gtin_code',
            'label'       => $label,
            'description' => sprintf(__('Mã %s cho sản phẩm này.', 'lacadev'), $label),
            'desc_tip'    => true,
        ]);
        echo '</div>';
    }

    /**
     * Admin variable product GTIN field.
     */
    public function addVariationGtinAdminField(int $loop, array $variation_data, mixed $variation): void
    {
        if (!function_exists('woocommerce_wp_text_input')) {
            return;
        }

        $gtinKey = (string) Settings::get('general.gtin', 'gtin');
        $label = 'GTIN';
        if ($gtinKey === 'isbn') {
            $label = 'ISBN';
        } elseif ($gtinKey === 'mpn') {
            $label = 'MPN';
        }

        $varId = is_object($variation) ? $variation->ID : (int) $variation;
        $val = (string) get_post_meta($varId, '_laca_seo_gtin_code', true);

        woocommerce_wp_text_input([
            'id'            => "_laca_seo_gtin_code_var{$loop}",
            'name'          => "_laca_seo_gtin_code_var[{$loop}]",
            'value'         => $val,
            'label'         => $label,
            'description'   => sprintf(__('Mã %s cho biến thể này.', 'lacadev'), $label),
            'desc_tip'      => true,
            'wrapper_class' => 'form-row form-row-full',
        ]);
    }

    /**
     * Save admin simple product GTIN.
     */
    public function saveGtinAdminData(mixed $product): void
    {
        if (!isset($_POST['_laca_seo_gtin_code'])) {
            return;
        }
        $code = sanitize_text_field(wp_unslash($_POST['_laca_seo_gtin_code']));
        if (method_exists($product, 'update_meta_data')) {
            $product->update_meta_data('_laca_seo_gtin_code', $code);
        }
    }

    /**
     * Save admin variable product GTIN.
     */
    public function saveVariationGtinAdminData(int $variation_id, int $loop): void
    {
        if (!isset($_POST['_laca_seo_gtin_code_var'][$loop])) {
            return;
        }
        $code = sanitize_text_field(wp_unslash($_POST['_laca_seo_gtin_code_var'][$loop]));
        update_post_meta($variation_id, '_laca_seo_gtin_code', $code);
    }
}
