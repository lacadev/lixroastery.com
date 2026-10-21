<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Product implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $entity = [
            '@type' => 'Product',
        ];

        if (class_exists('WooCommerce') && function_exists('is_product') && is_product()) {
            if (isset(WC()->structured_data)) {
                remove_action('wp_footer', [WC()->structured_data, 'output_structured_data'], 10);
                remove_action('woocommerce_email_order_details', [WC()->structured_data, 'output_email_structured_data'], 30);
            }
            ProductWooCommerce::get()->setProduct($entity, $jsonld);
        }

        return $entity;
    }

    public static function getSeller(JsonLd $jsonld): array
    {
        $siteUrl = home_url();
        $type = (string) Settings::get('titles.knowledgegraph_type', 'company');
        $seller = [
            '@type' => ('person' === $type) ? 'Person' : 'Organization',
            '@id'   => trailingslashit($siteUrl),
            'name'  => $jsonld->getWebsiteName(),
            'url'   => $siteUrl,
        ];

        if ('company' === $type) {
            $logo = Settings::get('titles.knowledgegraph_logo', '');
            if (!empty($logo)) {
                $seller['logo'] = $logo;
            }
        }

        return $seller;
    }

    public static function getCategory(int $productId, string $taxonomy): string
    {
        $categories = get_the_terms($productId, $taxonomy);
        if (is_wp_error($categories) || empty($categories)) {
            return '';
        }

        if (0 === $categories[0]->parent) {
            return (string) $categories[0]->name;
        }

        $ancestors = array_reverse(get_ancestors($categories[0]->term_id, $taxonomy));
        $chain = [];
        foreach ($ancestors as $ancestorId) {
            $term = get_term($ancestorId, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $chain[] = $term->name;
            }
        }
        $chain[] = $categories[0]->name;

        return implode(' > ', $chain);
    }
}
