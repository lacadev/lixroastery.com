<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\JsonLd;

defined('ABSPATH') || exit;

class ProductWooCommerce
{
    private static ?ProductWooCommerce $instance = null;

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setProduct(array &$entity, JsonLd $jsonld): void
    {
        $product = wc_get_product(get_the_ID());
        if (!$product) {
            return;
        }

        $brand = \App\Features\LacaSeo\Modules\WooCommerce\WooCommerce::getBrand($product->get_id());
        if (!empty($brand)) {
            $entity['brand'] = [
                '@type' => 'Brand',
                'name'  => $brand,
            ];
        }

        $entity['name']             = $jsonld->getProductTitle($product);
        $entity['description']      = $jsonld->getProductDesc($product);
        $entity['sku']              = $product->get_sku() ?: '';
        $entity['category']         = Product::getCategory($product->get_id(), 'product_cat');
        $entity['mainEntityOfPage'] = ['@id' => ($jsonld->parts['canonical'] ?? '') . '#webpage'];

        $this->setGtin($product, $entity);
        $this->setWeight($product, $entity);
        $this->setDimensions($product, $entity);
        $this->setImages($product, $entity);
        $this->setRatings($product, $entity);
        $this->setOffers($product, $entity, Product::getSeller($jsonld));
    }

    private function setGtin(mixed $product, array &$entity): void
    {
        $gtinKey = (string) Settings::get('general.gtin', 'gtin');
        $val = \App\Features\LacaSeo\Modules\WooCommerce\WooCommerce::getGtinValue($product);
        if ($val !== '') {
            $entity[$gtinKey] = $val;
            if ($gtinKey === 'isbn') {
                $entity['@type'] = ['Product', 'Book'];
            }
        }
    }

    private function setWeight(mixed $product, array &$entity): void
    {
        if (!$product->has_weight()) {
            return;
        }

        $hash = [
            'lbs' => 'LBR',
            'kg'  => 'KGM',
            'g'   => 'GRM',
            'oz'  => 'ONZ',
        ];
        $unit = get_option('woocommerce_weight_unit');

        $entity['weight'] = [
            '@type'    => 'QuantitativeValue',
            'unitCode' => $hash[$unit] ?? 'LBR',
            'value'    => $product->get_weight(),
        ];
    }

    private function setDimensions(mixed $product, array &$entity): void
    {
        if (!$product->has_dimensions()) {
            return;
        }

        $hash = [
            'in' => 'INH',
            'm'  => 'MTR',
            'cm' => 'CMT',
            'mm' => 'MMT',
            'yd' => 'YRD',
        ];
        $unit = get_option('woocommerce_dimension_unit');
        $code = $hash[$unit] ?? '';

        $entity['height'] = [
            '@type'    => 'QuantitativeValue',
            'unitCode' => $code,
            'value'    => $product->get_height(),
        ];

        $entity['width'] = [
            '@type'    => 'QuantitativeValue',
            'unitCode' => $code,
            'value'    => $product->get_width(),
        ];

        $entity['depth'] = [
            '@type'    => 'QuantitativeValue',
            'unitCode' => $code,
            'value'    => $product->get_length(),
        ];
    }

    private function setImages(mixed $product, array &$entity): void
    {
        $images = $this->getImages($product);
        if ($images) {
            $entity['image'] = $images;
        }
    }

    public function getImages(mixed $product): array
    {
        if (!$product->get_image_id()) {
            return [];
        }

        $images = [];
        $image = wp_get_attachment_image_src($product->get_image_id(), 'single-post-thumbnail');
        if (!empty($image)) {
            $images[] = [
                '@type'  => 'ImageObject',
                'url'    => $image[0],
                'height' => $image[2],
                'width'  => $image[1],
            ];
        }

        $gallery = $product->get_gallery_image_ids();
        foreach ($gallery as $imgId) {
            $img = wp_get_attachment_image_src($imgId, 'single-post-thumbnail');
            if (empty($img)) {
                continue;
            }
            $images[] = [
                '@type'  => 'ImageObject',
                'url'    => $img[0],
                'height' => $img[2],
                'width'  => $img[1],
            ];
        }

        return $images;
    }

    private function setRatings(mixed $product, array &$entity): void
    {
        if ($product->get_rating_count() < 1) {
            return;
        }

        $entity['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => $product->get_average_rating(),
            'bestRating'  => '5',
            'ratingCount' => $product->get_rating_count(),
            'reviewCount' => $product->get_review_count(),
        ];
    }

    private function setOffers(mixed $product, array &$entity, array $seller): void
    {
        $offers = $this->getOffers($product, $seller);
        if ($offers) {
            $entity['offers'] = $offers;
        }
    }

    public function getOffers(mixed $product, array $seller): array
    {
        if ('' === $product->get_price()) {
            return [];
        }

        $priceValidUntil = gmdate('Y-12-31', time() + YEAR_IN_SECONDS);
        if ($product->get_date_on_sale_to()) {
            $priceValidUntil = gmdate('Y-m-d', $product->get_date_on_sale_to()->getTimestamp());
        }

        $offer = [
            '@type'           => 'Offer',
            'price'           => $product->get_price() ? wc_format_decimal($product->get_price(), wc_get_price_decimals()) : '0',
            'priceCurrency'   => get_woocommerce_currency(),
            'priceValidUntil' => $priceValidUntil,
            'availability'    => $this->getAvailability($product),
            'itemCondition'   => 'NewCondition',
            'url'             => $product->get_permalink(),
            'seller'          => $seller,
        ];

        return $offer;
    }

    public function getAvailability(mixed $product): string
    {
        if ($product->is_in_stock()) {
            $schema = ('onbackorder' === $product->get_stock_status()) ? 'BackOrder' : 'InStock';
        } else {
            $schema = 'OutOfStock';
        }

        return 'http://schema.org/' . $schema;
    }
}
