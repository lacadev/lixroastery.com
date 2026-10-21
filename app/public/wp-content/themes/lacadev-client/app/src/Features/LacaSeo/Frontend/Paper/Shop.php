<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

class Shop extends Singular
{
    private function getShopPage(): ?\WP_Post
    {
        $shopId = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
        return $shopId > 0 ? get_post($shopId) : null;
    }

    public function title(): string
    {
        $shop = $this->getShopPage();
        if ($shop) {
            $meta = (string) get_post_meta($shop->ID, '_laca_seo_title', true);
            if ($meta !== '') {
                return Variables::replace($meta, $shop);
            }
        }

        return Paper::get_from_options('pt_product_archive_title', [], '%pt_plural% Archive %page% %sep% %sitename%');
    }

    public function description(): string
    {
        $shop = $this->getShopPage();
        if ($shop) {
            $meta = (string) get_post_meta($shop->ID, '_laca_seo_description', true);
            if ($meta !== '') {
                return Variables::replace($meta, $shop);
            }
            if (!empty($shop->post_excerpt)) {
                return wp_strip_all_tags($shop->post_excerpt);
            }
        }

        return Paper::get_from_options('pt_product_archive_description', [], '%pt_plural% Archive %page% %sep% %sitename%');
    }

    public function robots(): array
    {
        $shop = $this->getShopPage();
        if ($shop) {
            $robots = Paper::robots_combine(get_post_meta($shop->ID, '_laca_seo_robots', true));
            if (!empty($robots)) {
                return $robots;
            }
        }

        if (Settings::get('titles.pt_product_custom_robots', 'off') === 'on') {
            return Paper::robots_combine((array) Settings::get('titles.pt_product_robots', []), true);
        }

        return [];
    }

    public function advanced_robots(): ?array
    {
        $shop = $this->getShopPage();
        if ($shop) {
            $adv = Paper::advanced_robots_combine(get_post_meta($shop->ID, '_laca_seo_advanced_robots', true));
            if (!empty($adv)) {
                return $adv;
            }
        }

        if (Settings::get('titles.pt_product_custom_robots', 'off') === 'on') {
            $adv = Paper::advanced_robots_combine((array) Settings::get('titles.pt_product_advanced_robots', []));
            if (!empty($adv)) {
                return $adv;
            }
        }

        return null;
    }

    public function canonical(): array
    {
        $shop = $this->getShopPage();
        $canonical = $shop ? get_permalink($shop->ID) : home_url('/shop/');
        $override = $shop ? (string) get_post_meta($shop->ID, '_laca_seo_canonical_url', true) : '';

        return [
            'canonical'          => $canonical,
            'canonical_unpaged'  => $canonical,
            'canonical_override' => $override,
        ];
    }

    public function keywords(): string
    {
        $shop = $this->getShopPage();
        return $shop ? (string) get_post_meta($shop->ID, '_laca_seo_focus_keyword', true) : '';
    }
}
