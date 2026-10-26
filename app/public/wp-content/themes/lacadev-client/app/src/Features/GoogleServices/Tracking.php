<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Injects Google Analytics 4 and Google Tag Manager measurement scripts.
 * Supports:
 * - Tag guards: Omit on 404, preview/customizer, and ?laca_tagverify=1
 * - User exclusion: Injects window['ga-disable-<G-ID>'] = true (Site Kit standard)
 * - Anti-duplicate counting with theme scripts
 * - Duplicate tag scanner via wp_remote_get
 * - WooCommerce ecommerce events (add_to_cart, purchase with deduplication)
 */
class Tracking
{
    public static function register(): void
    {
        $instance = new self();
        add_action('wp_head', [$instance, 'outputHeadTracking'], 20);
        add_action('wp_body_open', [$instance, 'outputBodyTracking'], 1);
        add_action('woocommerce_thankyou', [$instance, 'outputWooCommercePurchase'], 20);
        add_action('wp_footer', [$instance, 'outputWooCommerceAddToCart'], 99);
    }

    /**
     * Check if tag guard is triggered (should NOT output any tracking tags).
     */
    public static function isTagGuardTriggered(): bool
    {
        // 1. Verification request
        if (!empty($_GET['laca_tagverify'])) {
            return true;
        }

        // 2. Preview or Customizer
        if (function_exists('is_preview') && is_preview()) {
            return true;
        }
        if (function_exists('is_customize_preview') && is_customize_preview()) {
            return true;
        }

        // 3. 404 error page (default enabled)
        if (function_exists('is_404') && is_404()) {
            $opts = get_option('laca_google_services', []);
            $omit404 = !isset($opts['ga_omit_on_404']) || $opts['ga_omit_on_404'] === 'on';
            if ($omit404) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current user should have tracking disabled via window['ga-disable-<ID>'] = true.
     */
    public static function shouldDisableTrackingForUser(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $opts = get_option('laca_google_services', []);
        $exclude = !isset($opts['ga_exclude_loggedin']) || $opts['ga_exclude_loggedin'] === 'on';
        if (!$exclude) {
            return false;
        }

        $roleMode = $opts['ga_exclude_role'] ?? 'admin';
        if ($roleMode === 'all') {
            return true;
        }

        // Default 'admin': check manage_options
        return current_user_can('manage_options');
    }

    /**
     * Injects GA4 / GTM script in <head>.
     */
    public function outputHeadTracking(): void
    {
        if (self::isTagGuardTriggered()) {
            echo "<!-- Google tag omitted: tag guard active (404, preview, or verify) -->\n";
            return;
        }

        $googleOpts = get_option('laca_google_services', []);
        $ga4_id = is_array($googleOpts) ? trim((string) ($googleOpts['ga4_measurement_id'] ?? '')) : '';
        if ($ga4_id === '' && class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $ga4_id = trim((string) \App\Features\LacaSeo\Core\Settings::get('general.ga4_measurement_id', ''));
        }

        $gtm_id = is_array($googleOpts) ? trim((string) ($googleOpts['gtm_container_id'] ?? '')) : '';
        if ($gtm_id === '' && class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $gtm_id = trim((string) \App\Features\LacaSeo\Core\Settings::get('general.gtm_container_id', ''));
        }

        $anonymize = is_array($googleOpts) && isset($googleOpts['ga_anonymize_ip'])
            ? ($googleOpts['ga_anonymize_ip'] === 'on')
            : (class_exists(\App\Features\LacaSeo\Core\Settings::class) ? \App\Features\LacaSeo\Core\Settings::get('general.ga_anonymize_ip', 'on') === 'on' : true);

        $disableTracking = self::shouldDisableTrackingForUser();
        $manualScripts   = self::getManualScripts();

        // 1. Google Analytics 4 (Chống đếm trùng nếu đã chèn thủ công ở Laca Theme -> Scripts)
        if (!empty($ga4_id)) {
            if (stripos($manualScripts, $ga4_id) !== false) {
                echo "<!-- Google tag (gtag.js) [{$ga4_id}] omitted: already embedded in Laca Theme Scripts -->\n";
            } else {
                echo "<!-- Google tag (gtag.js) -->\n";
                if ($disableTracking) {
                    // Site Kit standard: inject ga-disable flag before library loads
                    echo "<script>window['ga-disable-" . esc_js($ga4_id) . "'] = true;</script>\n";
                }
                echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($ga4_id) . '"></script>' . "\n";
                echo "<script>\n";
                echo "  window.dataLayer = window.dataLayer || [];\n";
                echo "  function gtag(){dataLayer.push(arguments);}\n";
                echo "  gtag('js', new Date());\n";
                $config = [];
                if ($anonymize) {
                    $config['anonymize_ip'] = true;
                }
                echo "  gtag('config', '" . esc_js($ga4_id) . "', " . (!empty($config) ? wp_json_encode($config) : '{}') . ");\n";
                echo "</script>\n";
            }
        }

        // 2. Google Tag Manager (Chống đếm trùng nếu đã chèn thủ công)
        if (!empty($gtm_id)) {
            if (stripos($manualScripts, $gtm_id) !== false) {
                echo "<!-- Google Tag Manager [{$gtm_id}] omitted: already embedded in Laca Theme Scripts -->\n";
            } else {
                echo "<!-- Google Tag Manager - Laca SEO -->\n";
                echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
                echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
                echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
                echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
                echo "})(window,document,'script','dataLayer','" . esc_js($gtm_id) . "');</script>\n";
                echo "<!-- End Google Tag Manager -->\n";
            }
        }
    }

    /**
     * Injects GTM noscript iframe in <body>.
     */
    public function outputBodyTracking(): void
    {
        if (self::isTagGuardTriggered()) {
            return;
        }

        $googleOpts = get_option('laca_google_services', []);
        $gtm_id = is_array($googleOpts) ? trim((string) ($googleOpts['gtm_container_id'] ?? '')) : '';
        if ($gtm_id === '' && class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $gtm_id = trim((string) \App\Features\LacaSeo\Core\Settings::get('general.gtm_container_id', ''));
        }

        if (!empty($gtm_id)) {
            $manualScripts = self::getManualScripts();
            if (stripos($manualScripts, $gtm_id) !== false) {
                return;
            }
            echo '<!-- Google Tag Manager (noscript) -->' . "\n";
            echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm_id) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
            echo '<!-- End Google Tag Manager (noscript) -->' . "\n";
        }
    }

    /**
     * Output WooCommerce purchase event on order thank-you page.
     * Includes deduplication flag in order meta.
     *
     * @param int|string $orderId
     */
    public function outputWooCommercePurchase(mixed $orderId): void
    {
        if (self::isTagGuardTriggered() || self::shouldDisableTrackingForUser()) {
            return;
        }

        $opts = get_option('laca_google_services', []);
        if (empty($opts['ga_ecommerce_events']) || $opts['ga_ecommerce_events'] !== 'on') {
            return;
        }

        $ga4_id = trim((string) ($opts['ga4_measurement_id'] ?? ''));
        if (empty($ga4_id) || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        // Anti-duplicate protection: check order meta flag
        if ($order->get_meta('_laca_ga_purchase_tracked') === '1') {
            echo "<!-- GA4 purchase event omitted: already tracked for order #{$orderId} -->\n";
            return;
        }

        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'item_id'   => $product ? (string) $product->get_id() : (string) $item->get_product_id(),
                'item_name' => $item->get_name(),
                'price'     => (float) $order->get_item_total($item, false, true),
                'quantity'  => (int) $item->get_quantity(),
            ];
        }

        $eventData = [
            'transaction_id' => (string) $order->get_order_number(),
            'value'          => (float) $order->get_total(),
            'currency'       => $order->get_currency(),
            'tax'            => (float) $order->get_total_tax(),
            'shipping'       => (float) $order->get_shipping_total(),
            'items'          => $items,
        ];

        echo "<!-- GA4 Ecommerce: Purchase Event -->\n";
        echo "<script>\n";
        echo "  if (typeof gtag === 'function') {\n";
        echo "    gtag('event', 'purchase', " . wp_json_encode($eventData) . ");\n";
        echo "  }\n";
        echo "</script>\n";

        // Mark order as tracked to prevent double-firing on reload
        $order->update_meta_data('_laca_ga_purchase_tracked', '1');
        $order->save();
    }

    /**
     * Injects client-side listener for WooCommerce add_to_cart AJAX events.
     */
    public function outputWooCommerceAddToCart(): void
    {
        if (self::isTagGuardTriggered() || self::shouldDisableTrackingForUser()) {
            return;
        }

        $opts = get_option('laca_google_services', []);
        if (empty($opts['ga_ecommerce_events']) || $opts['ga_ecommerce_events'] !== 'on') {
            return;
        }

        $ga4_id = trim((string) ($opts['ga4_measurement_id'] ?? ''));
        if (empty($ga4_id) || !function_exists('is_woocommerce')) {
            return;
        }

        ?>
        <script>
        (function() {
            if (typeof window.jQuery === 'undefined' || typeof window.gtag !== 'function') return;
            window.jQuery(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
                if (!$button || !$button.length) return;
                var productId = $button.data('product_id') || '';
                var productName = $button.data('product_name') || $button.attr('aria-label') || '';
                var quantity = $button.data('quantity') || 1;
                gtag('event', 'add_to_cart', {
                    items: [{
                        item_id: String(productId),
                        item_name: String(productName),
                        quantity: Number(quantity)
                    }]
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Check website for duplicate or external Google tracking tags.
     *
     * @param string|null $verifyUrl
     * @return array{
     *     url: string,
     *     configuredGa4: string,
     *     configuredGtm: string,
     *     foundIds: string[],
     *     duplicateIds: string[],
     *     externalIds: string[],
     *     hasWarnings: bool,
     *     message: string,
     * }
     */
    public static function checkDuplicateTags(?string $verifyUrl = null): array
    {
        $url = $verifyUrl ?: home_url('/?laca_tagverify=1');

        $opts = get_option('laca_google_services', []);
        $configuredGa4 = trim((string) ($opts['ga4_measurement_id'] ?? ''));
        $configuredGtm = trim((string) ($opts['gtm_container_id'] ?? ''));

        $response = wp_remote_get($url, [
            'timeout'   => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [
                'url'           => $url,
                'configuredGa4' => $configuredGa4,
                'configuredGtm' => $configuredGtm,
                'foundIds'      => [],
                'duplicateIds'  => [],
                'externalIds'   => [],
                'hasWarnings'   => true,
                'message'       => sprintf(__('Không thể kiểm tra website: %s', 'lacadev'), $response->get_error_message()),
            ];
        }

        $body = (string) wp_remote_retrieve_body($response);
        $foundIds = [];

        // Match Google IDs in HTML source
        if (preg_match_all('/\b(G-[A-Z0-9]{4,}|GT-[A-Z0-9]{4,}|GTM-[A-Z0-9]{4,}|UA-\d+-\d+)\b/', $body, $matches)) {
            $foundIds = array_values(array_unique($matches[1]));
        }

        // Also check theme scripts
        $manualIds = self::findManualGoogleIds();
        $allFound = array_values(array_unique(array_merge($foundIds, $manualIds)));

        $configuredList = array_filter([$configuredGa4, $configuredGtm]);
        $externalIds = array_values(array_diff($allFound, $configuredList));

        // Check if any ID appears multiple times in raw body
        $duplicateIds = [];
        foreach ($configuredList as $cId) {
            $count = substr_count($body, $cId);
            if ($count > 2) { // script tag + config call is normally 2
                $duplicateIds[] = $cId;
            }
        }

        $hasWarnings = !empty($externalIds) || !empty($duplicateIds);
        $msg = $hasWarnings
            ? __('Phát hiện mã Google bên ngoài hoặc bị chèn trùng lặp.', 'lacadev')
            : __('Không phát hiện mã trùng lặp hoặc mã ngoại lai.', 'lacadev');

        return [
            'url'           => $url,
            'configuredGa4' => $configuredGa4,
            'configuredGtm' => $configuredGtm,
            'foundIds'      => $allFound,
            'duplicateIds'  => $duplicateIds,
            'externalIds'   => $externalIds,
            'hasWarnings'   => $hasWarnings,
            'message'       => $msg,
        ];
    }

    /**
     * Get manual scripts content from Carbon theme options.
     */
    public static function getManualScripts(): string
    {
        $scripts = '';
        if (function_exists('carbon_get_theme_option')) {
            $scripts = (string) carbon_get_theme_option('crb_header_script') . (string) carbon_get_theme_option('crb_footer_script');
        }
        if (empty($scripts)) {
            $scripts = (string) get_option('_crb_header_script', '') . (string) get_option('crb_header_script', '')
                     . (string) get_option('_crb_footer_script', '') . (string) get_option('crb_footer_script', '');
        }
        return $scripts;
    }

    /**
     * Extracts manual Google Measurement or Tag Manager IDs from theme scripts.
     *
     * @return array<string>
     */
    public static function findManualGoogleIds(): array
    {
        $scripts = self::getManualScripts();
        if (empty($scripts)) {
            return [];
        }

        $ids = [];
        if (preg_match_all('/\b(G-[A-Z0-9]{4,}|GTM-[A-Z0-9]{4,}|UA-\d+-\d+)\b/', $scripts, $matches)) {
            $ids = array_unique($matches[1]);
        }
        return array_values($ids);
    }
}

