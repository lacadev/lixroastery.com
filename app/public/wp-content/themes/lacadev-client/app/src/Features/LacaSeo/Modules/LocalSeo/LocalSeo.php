<?php

namespace App\Features\LacaSeo\Modules\LocalSeo;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\Db;

defined('ABSPATH') || exit;

class LocalSeo
{
    public function register(): void
    {
        add_action('init', [$this, 'registerPostTypeAndTaxonomy']);
        (new Shortcodes())->register();
        (new Kml())->register();
        add_filter('laca_seo/json_ld', [$this, 'addLocalBusinessGraph'], 9, 2);
    }

    public function registerPostTypeAndTaxonomy(): void
    {
        $useMulti = Settings::get('titles.use_multiple_locations', 'off') === 'on';

        // Register CPT laca_seo_locations
        $singular = (string) Settings::get('titles.locations_post_type_label', 'Location');
        $plural = $singular . 's';

        register_post_type('laca_seo_locations', [
            'labels' => [
                'name'               => $plural,
                'singular_name'      => $singular,
                'add_new'            => __('Add New', 'lacadev'),
                'add_new_item'       => sprintf(__('Add New %s', 'lacadev'), $singular),
                'edit_item'          => sprintf(__('Edit %s', 'lacadev'), $singular),
                'new_item'           => sprintf(__('New %s', 'lacadev'), $singular),
                'view_item'          => sprintf(__('View %s', 'lacadev'), $singular),
                'search_items'       => sprintf(__('Search %s', 'lacadev'), $plural),
                'not_found'          => sprintf(__('No %s found', 'lacadev'), $plural),
                'not_found_in_trash' => sprintf(__('No %s found in Trash', 'lacadev'), $plural),
            ],
            'public'       => true,
            'show_ui'      => true,
            'show_in_menu' => 'laca-seo',
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'locations'],
        ]);

        register_taxonomy('laca_seo_location_category', ['laca_seo_locations', 'rank_math_locations'], [
            'hierarchical'      => true,
            'labels'            => [
                'name'          => __('Location Categories', 'lacadev'),
                'singular_name' => __('Location Category', 'lacadev'),
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'location-category'],
        ]);
    }

    public function addLocalBusinessGraph(array $data, mixed $jsonld): array
    {
        if (is_object($jsonld) && method_exists($jsonld, 'canAddGlobalEntities')) {
            if (!$jsonld->canAddGlobalEntities($data)) {
                return $data;
            }
        }

        $kgType = (string) Settings::get('titles.knowledgegraph_type', 'person');
        $url    = (string) Settings::get('titles.url', home_url());
        if (empty($url)) {
            $url = home_url();
        }

        $entity = [
            '@type' => '',
            '@id'   => '',
            'name'  => '',
            'url'   => $url,
        ];

        if (is_object($jsonld) && method_exists($jsonld, 'getSocialProfiles')) {
            $socialProfiles = $jsonld->getSocialProfiles();
            if (!empty($socialProfiles)) {
                $entity['sameAs'] = $socialProfiles;
            }
        }

        if (is_object($jsonld) && method_exists($jsonld, 'addProp')) {
            $jsonld->addProp('image', $entity);
        }

        if ($kgType === 'person') {
            $name = (string) Settings::get('titles.knowledgegraph_name', '');
            if ($name) {
                $entity['@type'] = is_singular() ? ['Organization', 'Person'] : 'Person';
                $entity['@id']   = home_url('/#person');
                $entity['name']  = $name;

                $phone = Settings::get('titles.phone', '');
                if ($phone) {
                    $entity['telephone'] = $phone;
                }

                if (isset($entity['logo'])) {
                    $entity['image'] = ['@id' => $entity['logo']['@id']];
                    if (!is_singular()) {
                        $entity['image'] = $entity['logo'];
                        unset($entity['logo']);
                    }
                }

                $data['publisher'] = $entity;
            }
            return $data;
        }

        // $kgType === 'company'
        $businessType = Settings::get('titles.local_business_type', 'LocalBusiness');
        if (empty($businessType)) {
            $businessType = 'LocalBusiness';
        }

        $name = Settings::get('titles.knowledgegraph_name', get_bloginfo('name'));

        $address = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => Settings::get('titles.local_address_street', ''),
            'addressLocality' => Settings::get('titles.local_address_locality', ''),
            'addressRegion'   => Settings::get('titles.local_address_region', ''),
            'postalCode'      => Settings::get('titles.local_address_postalcode', ''),
            'addressCountry'  => Settings::get('titles.local_address_country', ''),
        ];
        $address = array_filter($address);

        $geo = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => Settings::get('titles.geo_latitude', ''),
            'longitude' => Settings::get('titles.geo_longitude', ''),
        ];
        $geo = array_filter($geo);

        $localEntity = [
            '@type' => $businessType,
            '@id'   => $url . '#' . strtolower($businessType),
            'name'  => $name,
            'url'   => $url,
        ];

        if (!empty($address)) {
            $localEntity['address'] = $address;
        }
        if (count($geo) > 1) {
            $localEntity['geo'] = $geo;
        }

        $phone = Settings::get('titles.phone', '');
        if ($phone) {
            $localEntity['telephone'] = $phone;
        }

        $priceRange = Settings::get('titles.price_range', '');
        if ($priceRange) {
            $localEntity['priceRange'] = $priceRange;
        }

        $openingHours = Settings::get('titles.opening_hours', []);
        if (!empty($openingHours) && is_array($openingHours)) {
            $localEntity['openingHoursSpecification'] = $openingHours;
        }

        $data['localBusiness'] = $localEntity;

        // Also populate publisher for company
        $orgEntity = $entity;
        $orgEntity['@type'] = 'Organization';
        $orgEntity['@id']   = home_url('/#organization');
        $orgEntity['name']  = $name;
        if ($phone) {
            $orgEntity['telephone'] = $phone;
        }
        $data['publisher'] = $orgEntity;

        return $data;
    }
}
