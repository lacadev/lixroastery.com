<?php

namespace App\Features\LacaSeo\Modules\LocalSeo;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\Db;

defined('ABSPATH') || exit;

class StoreLocator
{
    public function getData(array $atts): string
    {
        $unit    = Settings::get('titles.map_unit', 'kilometers') === 'miles' ? 'mi' : 'km';
        $radius  = (int) ($_POST['rank-math-search-radius'] ?? $_POST['laca_search_radius'] ?? ($atts['search_radius'] ?? 20));
        $address = sanitize_text_field($_POST['rank-math-search-address'] ?? $_POST['laca_search_address'] ?? '');

        ob_start();
        ?>
        <div class="laca-seo-business-wrapper rank-math-business-wrapper">
            <form id="laca-seo-local-store-locator" class="rank-math-local-store-locator" method="post" action="">
                <?php if (!empty($atts['show_radius'])) { ?>
                    <div class="rank-math-form-field">
                        <select name="rank-math-search-radius">
                            <?php
                            foreach ([1, 5, 10, 20, 40, 50, 75, 100, 200, 300, 400, 500, 1000] as $val) {
                                echo "<option value='{$val}' " . selected($radius, $val, false) . ">{$val}{$unit}</option>";
                            }
                            ?>
                        </select>
                    </div>
                <?php } ?>

                <div class="rank-math-form-field">
                    <input type="text" name="rank-math-search-address" id="rank-math-search-address"
                           placeholder="<?php esc_attr_e('Address, Suburb, Region, Zip or Landmark', 'lacadev'); ?>"
                           value="<?php echo esc_attr($address); ?>" />
                    <input type="hidden" name="lat" id="rank-math-lat" value="<?php echo esc_attr($_POST['lat'] ?? ''); ?>" />
                    <input type="hidden" name="lng" id="rank-math-lng" value="<?php echo esc_attr($_POST['lng'] ?? ''); ?>" />
                </div>
                <div class="rank-math-form-field">
                    <button type="submit" name="rank-math-submit" value="search"><?php esc_html_e('Search', 'lacadev'); ?></button>
                </div>
            </form>
            <?php
            echo $this->getResults($atts, $unit);
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function searchNearby(float $latitude, float $longitude, float $radius, string $unit = 'km', int $category = 0): array
    {
        global $wpdb;

        $earthRadius = ($unit === 'mi') ? 3959 : 6371;

        $innerJoin = '';
        if ($category > 0) {
            $innerJoin .= $wpdb->prepare(
                " INNER JOIN {$wpdb->term_relationships} AS tr ON p.ID = tr.object_id
                  INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                  AND tt.taxonomy IN ('laca_seo_location_category', 'rank_math_location_category')
                  AND tt.term_id = %d",
                $category
            );
        }

        $sql = $wpdb->prepare(
            "SELECT DISTINCT
                p.*,
                map_lat.meta_value as locLat,
                map_lng.meta_value as locLong,
                ( %d * acos(
                    cos( radians( %s ) )
                    * cos( radians( map_lat.meta_value ) )
                    * cos( radians( map_lng.meta_value ) - radians( %s ) )
                    + sin( radians( %s ) )
                    * sin( radians( map_lat.meta_value ) )
                ) )
                AS distance
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} map_lat ON p.ID = map_lat.post_id
            INNER JOIN {$wpdb->postmeta} map_lng ON p.ID = map_lng.post_id
            {$innerJoin}
            WHERE 1 = 1
            AND p.post_type IN ('laca_seo_locations', 'rank_math_locations')
            AND p.post_status = 'publish'
            AND (map_lat.meta_key IN ('_laca_seo_geo_latitude', 'rank_math_local_business_latitide', 'rank_math_local_business_latitude'))
            AND (map_lng.meta_key IN ('_laca_seo_geo_longitude', 'rank_math_local_business_longitude'))
            HAVING distance < %s
            ORDER BY distance ASC",
            $earthRadius,
            (string) $latitude,
            (string) $longitude,
            (string) $latitude,
            (string) $radius
        );

        return (array) $wpdb->get_results($sql);
    }

    private function getResults(array $atts, string $unit): string
    {
        $address = sanitize_text_field($_POST['rank-math-search-address'] ?? $_POST['laca_search_address'] ?? '');
        if (empty($address)) {
            return '';
        }

        $latitude  = (float) ($_POST['lat'] ?? 0);
        $longitude = (float) ($_POST['lng'] ?? 0);
        $radius    = (float) (!empty($atts['show_radius']) ? ($_POST['rank-math-search-radius'] ?? 20) : ($atts['search_radius'] ?? 20));
        $category  = (int) ($_POST['rank-math-location-category'] ?? 0);

        if (!$latitude && !$longitude) {
            return '';
        }

        $results = $this->searchNearby($latitude, $longitude, $radius, $unit, $category);
        if (empty($results)) {
            return esc_html__('Sorry, no locations were found.', 'lacadev');
        }

        $addressRenderer = new Address();
        $output = '';

        foreach ($results as $location) {
            $schemas = Db::getSchemas($location->ID);
            $schema = !empty($schemas) ? current($schemas) : [];
            $schema['name'] = $location->post_title;

            $output .= '<div class="laca-seo-location-result rank-math-location-result">';
            $output .= '<h4 class="rank-math-business-title">' . esc_html($location->post_title) . '</h4>';
            $output .= '<div class="distance"><strong>' . esc_html(round($location->distance, 2)) . ' ' . esc_html($unit) . '</strong></div>';
            $output .= $addressRenderer->getData($atts, $schema);
            $output .= '</div>';
        }

        return $output;
    }
}
