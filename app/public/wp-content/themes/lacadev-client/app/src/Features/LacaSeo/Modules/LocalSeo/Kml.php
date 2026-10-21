<?php

namespace App\Features\LacaSeo\Modules\LocalSeo;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\Db;
use App\Features\LacaSeo\Sitemap\Router;

defined('ABSPATH') || exit;

class Kml
{
    public function register(): void
    {
        add_action('init', [$this, 'initRewrite'], 2);
        add_filter('laca_seo/sitemap/index/entry', [$this, 'addLocalSitemapIndex'], 10, 3);
        add_action('template_redirect', [$this, 'handleKmlRequest'], 0);
    }

    public function initRewrite(): void
    {
        $base = Router::getSitemapBase();
        add_rewrite_rule($base . 'locations\\.kml$', 'index.php?laca_sitemap=locations_kml', 'top');
    }

    public function handleKmlRequest(): void
    {
        $sitemapVar = get_query_var('laca_sitemap');
        if ($sitemapVar === 'locations_kml' || (isset($_GET['laca_sitemap']) && $_GET['laca_sitemap'] === 'locations_kml')) {
            header('Content-Type: application/vnd.google-earth.kml+xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow', true);
            echo $this->generateKml();
            exit;
        }
    }

    public function addLocalSitemapIndex(array $item, string $type, string $subType): array
    {
        return $item;
    }

    public function generateKml(): string
    {
        $locations = $this->getLocalLocations();
        $businessName = Settings::get('titles.knowledgegraph_name', get_bloginfo('name'));
        $businessUrl  = Settings::get('titles.url', home_url());

        $kml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $kml .= '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $kml .= "\t<Document>\n";
        $kml .= "\t\t<name>Locations for " . esc_html($businessName) . "</name>\n";
        $kml .= "\t\t<open>1</open>\n";
        $kml .= "\t\t<Folder>\n";

        if (!empty($businessUrl)) {
            $kml .= "\t\t\t<atom:link href=\"" . esc_url($businessUrl) . "\" />\n";
        }

        foreach ($locations as $loc) {
            $address = !empty($loc['address']) ? implode(', ', array_filter($loc['address'])) : '';
            $hasCoord = !empty($loc['coords']['latitude']) && !empty($loc['coords']['longitude']);

            $kml .= "\t\t\t<Placemark>\n";
            $kml .= "\t\t\t\t<name><![CDATA[" . html_entity_decode((string) $loc['name']) . "]]></name>\n";
            $kml .= "\t\t\t\t<description><![CDATA[" . html_entity_decode((string) $loc['description']) . "]]></description>\n";
            $kml .= "\t\t\t\t<address><![CDATA[" . $address . "]]></address>\n";
            $kml .= "\t\t\t\t<phoneNumber><![CDATA[" . ($loc['phone'] ?? '') . "]]></phoneNumber>\n";
            $kml .= "\t\t\t\t<atom:link href=\"" . esc_url($loc['url'] ?? '') . "\"/>\n";
            $kml .= "\t\t\t\t<LookAt>\n";

            if ($hasCoord) {
                $kml .= "\t\t\t\t\t<latitude>" . $loc['coords']['latitude'] . "</latitude>\n";
                $kml .= "\t\t\t\t\t<longitude>" . $loc['coords']['longitude'] . "</longitude>\n";
            }

            $kml .= "\t\t\t\t\t<altitude>0</altitude>\n";
            $kml .= "\t\t\t\t\t<range></range>\n";
            $kml .= "\t\t\t\t\t<tilt>0</tilt>\n";
            $kml .= "\t\t\t\t</LookAt>\n";
            $kml .= "\t\t\t\t<Point>\n";
            if ($hasCoord) {
                $kml .= "\t\t\t\t\t<coordinates>" . $loc['coords']['longitude'] . ',' . $loc['coords']['latitude'] . "</coordinates>\n";
            }
            $kml .= "\t\t\t\t</Point>\n";
            $kml .= "\t\t\t</Placemark>\n";
        }

        $kml .= "\t\t</Folder>\n";
        $kml .= "\t</Document>\n";
        $kml .= '</kml>';

        return $kml;
    }

    public function getLocalLocations(): array
    {
        $posts = get_posts([
            'post_type'      => ['laca_seo_locations', 'rank_math_locations'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        $locations = [];
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $schemas = Db::getSchemas($post->ID);
                $schema = !empty($schemas) ? current($schemas) : [];

                $lat = get_post_meta($post->ID, '_laca_seo_geo_latitude', true) ?: get_post_meta($post->ID, 'rank_math_local_business_latitide', true);
                $lng = get_post_meta($post->ID, '_laca_seo_geo_longitude', true) ?: get_post_meta($post->ID, 'rank_math_local_business_longitude', true);

                $addr = $schema['address'] ?? [];
                if (is_array($addr)) {
                    unset($addr['@type']);
                } else {
                    $addr = [];
                }

                $locations[] = [
                    'name'        => $post->post_title,
                    'description' => $post->post_excerpt ?: $post->post_title,
                    'phone'       => $schema['telephone'] ?? '',
                    'url'         => get_permalink($post),
                    'address'     => $addr,
                    'coords'      => [
                        'latitude'  => $lat ?: ($schema['geo']['latitude'] ?? ''),
                        'longitude' => $lng ?: ($schema['geo']['longitude'] ?? ''),
                    ],
                ];
            }
        }

        if (empty($locations)) {
            // Global setting location
            $locations[] = [
                'name'        => Settings::get('titles.knowledgegraph_name', get_bloginfo('name')),
                'description' => get_bloginfo('name') . ' - ' . get_bloginfo('description'),
                'phone'       => Settings::get('titles.phone', ''),
                'url'         => Settings::get('titles.url', home_url()),
                'address'     => [
                    Settings::get('titles.local_address_street', ''),
                    Settings::get('titles.local_address_locality', ''),
                    Settings::get('titles.local_address_region', ''),
                    Settings::get('titles.local_address_postalcode', ''),
                    Settings::get('titles.local_address_country', ''),
                ],
                'coords'      => [
                    'latitude'  => Settings::get('titles.geo_latitude', ''),
                    'longitude' => Settings::get('titles.geo_longitude', ''),
                ],
            ];
        }

        return $locations;
    }
}
