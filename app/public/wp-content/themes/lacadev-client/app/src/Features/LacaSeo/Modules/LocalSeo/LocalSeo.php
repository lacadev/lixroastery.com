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
        if ($singular === 'RM Locations' || $singular === 'RM Location' || $singular === 'Location') {
            $singular = __('Địa điểm', 'lacadev');
            $plural = __('Địa điểm', 'lacadev');
        } else {
            $plural = $singular . 's';
        }

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
            'public'       => $useMulti,
            'show_ui'      => $useMulti,
            'show_in_menu' => $useMulti ? 'laca-seo' : false,
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive'  => $useMulti,
            'rewrite'      => ['slug' => 'locations'],
        ]);

        register_taxonomy('laca_seo_location_category', ['laca_seo_locations', 'rank_math_locations'], [
            'hierarchical'      => true,
            'labels'            => [
                'name'          => __('Location Categories', 'lacadev'),
                'singular_name' => __('Location Category', 'lacadev'),
            ],
            'show_ui'           => $useMulti,
            'show_admin_column' => $useMulti,
            'query_var'         => $useMulti,
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

        $rawAddr = Settings::get('titles.local_address', []);
        if (!is_array($rawAddr)) {
            $rawAddr = [];
        }
        $address = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $rawAddr['streetAddress'] ?? (Settings::getRaw('titles.local_address_street') ?: ''),
            'addressLocality' => $rawAddr['addressLocality'] ?? (Settings::getRaw('titles.local_address_locality') ?: ''),
            'addressRegion'   => $rawAddr['addressRegion'] ?? (Settings::getRaw('titles.local_address_region') ?: ''),
            'postalCode'      => $rawAddr['postalCode'] ?? (Settings::getRaw('titles.local_address_postalcode') ?: ''),
            'addressCountry'  => $rawAddr['addressCountry'] ?? (Settings::getRaw('titles.local_address_country') ?: ''),
        ];
        $address = array_filter($address);

        $rawGeo = (string) Settings::get('titles.geo', '');
        $geoParts = $rawGeo !== '' ? array_map('trim', explode(',', $rawGeo)) : [];
        $lat = !empty($geoParts[0]) ? $geoParts[0] : (Settings::getRaw('titles.geo_latitude') ?: '');
        $lng = !empty($geoParts[1]) ? $geoParts[1] : (Settings::getRaw('titles.geo_longitude') ?: '');
        $geo = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $lat,
            'longitude' => $lng,
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
