<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class ProductsPage implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        if (!$this->canAddSnippetTaxonomy() || !$this->canAddSnippetShop()) {
            return $data;
        }

        $data['ProductsPage'] = [
            '@context' => 'https://schema.org/',
            '@graph'   => [],
        ];

        while (have_posts()) {
            the_post();
            $postId = get_the_ID();
            $product = wc_get_product($postId);
            $url = $jsonld->getPostUrl($postId);

            $part = [
                '@type'       => 'Product',
                'name'        => $jsonld->getProductTitle($product),
                'url'         => $url,
                '@id'         => $url,
                'description' => $jsonld->getProductDesc($product),
            ];

            $data['ProductsPage']['@graph'][] = $part;
        }

        wp_reset_postdata();

        return $data;
    }

    private function canAddSnippetTaxonomy(): bool
    {
        if (!is_tax() && !is_category() && !is_tag()) {
            return true;
        }

        $tax = get_query_var('taxonomy');
        return !Settings::get("titles.remove_{$tax}_snippet_data", false);
    }

    private function canAddSnippetShop(): bool
    {
        if (!function_exists('is_shop') || !is_shop()) {
            return true;
        }

        return Settings::get('general.remove_shop_snippet_data', 'on') !== 'on';
    }
}
