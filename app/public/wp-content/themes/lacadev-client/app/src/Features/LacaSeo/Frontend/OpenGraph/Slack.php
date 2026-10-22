<?php

namespace App\Features\LacaSeo\Frontend\OpenGraph;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Emits Slack enhanced sharing tags (twitter:label1, twitter:data1, twitter:label2, twitter:data2).
 */
class Slack
{
    private static int $dataTagCount = 0;

    public function output(): void
    {
        self::$dataTagCount = 0;
        $data = $this->getEnhancedData();

        foreach ($data as $key => $value) {
            if (empty($value)) {
                continue;
            }
            $this->outputDataTag((string) $key, (string) $value);
        }
    }

    private function outputDataTag(string $key, string $value): void
    {
        self::$dataTagCount++;
        echo '<meta name="twitter:label' . self::$dataTagCount . '" content="' . esc_attr($key) . '" />' . "\n";
        echo '<meta name="twitter:data' . self::$dataTagCount . '" content="' . esc_attr($value) . '" />' . "\n";
    }

    /**
     * @return array<string, string|int>
     */
    private function getEnhancedData(): array
    {
        if ($this->isProduct()) {
            return $this->getProductData();
        }

        if ($this->isPage()) {
            return $this->getPageData();
        }

        if ($this->isSingular()) {
            return $this->getPostData();
        }

        if ($this->isTerm()) {
            return $this->getTermData();
        }

        if ($this->isAuthor()) {
            return $this->getAuthorData();
        }

        return [];
    }

    private function isProduct(): bool
    {
        return $this->isWooProduct() || $this->isEddProduct();
    }

    private function isWooProduct(): bool
    {
        return Settings::get('titles.pt_product_slack_enhanced_sharing', 'on') === 'on'
            && is_singular('product')
            && function_exists('wc_get_product');
    }

    private function isEddProduct(): bool
    {
        return class_exists('Easy_Digital_Downloads')
            && is_singular('download')
            // getRaw: field pt_download_* chỉ được đăng ký khi EDD có post type 'download'.
            && Settings::getRaw('titles.pt_download_slack_enhanced_sharing', 'on') === 'on';
    }

    private function isPage(): bool
    {
        return Settings::get('titles.pt_page_slack_enhanced_sharing', 'on') === 'on'
            && is_singular('page')
            && !is_front_page();
    }

    private function isSingular(): bool
    {
        return is_singular() && Settings::get('titles.pt_' . get_post_type() . '_slack_enhanced_sharing', 'on') === 'on';
    }

    private function isTerm(): bool
    {
        if (is_category() || is_tag() || is_tax()) {
            $object = get_queried_object();
            return $object && Settings::get('titles.tax_' . $object->taxonomy . '_slack_enhanced_sharing', 'off') === 'on';
        }
        return false;
    }

    private function isAuthor(): bool
    {
        return Settings::get('titles.author_slack_enhanced_sharing', 'on') === 'on' && is_author();
    }

    /**
     * @return array<string, string>
     */
    private function getProductData(): array
    {
        if ($this->isWooProduct()) {
            return $this->getWooProductData();
        }
        return [];
    }

    /**
     * @return array<string, string>
     */
    private function getWooProductData(): array
    {
        global $post;
        $product = function_exists('wc_get_product') ? \wc_get_product($post) : null;
        if (!$product) {
            return [];
        }

        $data = [];
        $data[__('Price', 'lacadev')] = $this->getProductPrice($product);
        $data[__('Availability', 'lacadev')] = $this->getProductAvailability($product);

        return $data;
    }

    private function getProductAvailability(object $product): string
    {
        $avail = $product->get_availability();
        $text = $avail['availability'] ?? '';
        return !empty($text) ? $text : __('In stock', 'lacadev');
    }

    private function getProductPrice(object $product): string
    {
        $price = wp_strip_all_tags(\wc_price($product->get_price()));
        if ($product->is_type('variable')) {
            $lowest  = \wc_format_decimal($product->get_variation_price('min', false), \wc_get_price_decimals());
            $highest = \wc_format_decimal($product->get_variation_price('max', false), \wc_get_price_decimals());

            $price = wp_strip_all_tags(\wc_price($lowest) . ' - ' . \wc_price($highest));
            if ($lowest === $highest) {
                $price = wp_strip_all_tags(\wc_price($lowest));
            }
        }
        return $price;
    }

    /**
     * @return array<string, string>
     */
    private function getPostData(): array
    {
        global $post;
        $data = [];

        $author = get_the_author();
        if (empty($author) && $post && !empty($post->post_author)) {
            $author = (string) get_the_author_meta('display_name', $post->post_author);
        }

        if (!empty($author)) {
            $data[__('Written by', 'lacadev')] = $author;
        }

        if ($post && !post_password_required($post)) {
            $data[__('Time to read', 'lacadev')] = $this->calculateTimeToRead($post);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function getPageData(): array
    {
        global $post;
        $data = [];

        if ($post && !post_password_required($post)) {
            $data[__('Time to read', 'lacadev')] = $this->calculateTimeToRead($post);
        }

        return $data;
    }

    private function calculateTimeToRead(object $post): string
    {
        $content = wp_strip_all_tags($post->post_content ?? '');
        $words_per_minute = 200;

        $words = preg_match_all('/\p{L}+/u', $content);
        $minutes = floor($words / $words_per_minute);

        if ($minutes > 0) {
            return sprintf(
                _n('%d minute', '%d minutes', (int) $minutes, 'lacadev'),
                (int) $minutes
            );
        }

        return __('Less than a minute', 'lacadev');
    }

    /**
     * @return array<string, int|string>
     */
    private function getTermData(): array
    {
        global $wp_query;
        $data = [];

        $term = $wp_query->get_queried_object();
        if (!$term || !($term instanceof \WP_Term)) {
            return $data;
        }

        $label = __('Items', 'lacadev');
        $post_type_object = get_post_type_object(get_post_type());
        if (is_object($post_type_object) && isset($post_type_object->labels->name)) {
            $label = $post_type_object->labels->name;
        }

        $count = !empty($term->category_count) ? $term->category_count : $term->count;
        $data[$label] = $count;

        return $data;
    }

    /**
     * @return array<string, int|string>
     */
    private function getAuthorData(): array
    {
        global $wp_query;
        $data = [];

        $author = $wp_query->get_queried_object();
        if (!$author || !is_object($author) || !isset($author->ID)) {
            return $data;
        }

        $userdata = get_userdata($author->ID);
        if (!$userdata || !is_object($userdata) || !isset($userdata->ID)) {
            return $data;
        }

        $data[__('Name', 'lacadev')] = $author->display_name;
        $data[__('Posts', 'lacadev')] = count_user_posts($author->ID);

        return $data;
    }
}
