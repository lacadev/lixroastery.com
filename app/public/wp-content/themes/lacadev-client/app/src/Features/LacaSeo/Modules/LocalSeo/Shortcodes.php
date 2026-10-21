<?php

namespace App\Features\LacaSeo\Modules\LocalSeo;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\Db;

defined('ABSPATH') || exit;

class Shortcodes
{
    private Address $address;
    private OpeningHours $openingHours;
    private StoreLocator $storeLocator;

    public function __construct()
    {
        $this->address = new Address();
        $this->openingHours = new OpeningHours();
        $this->storeLocator = new StoreLocator();
    }

    public function register(): void
    {
        // Primary shortcodes
        add_shortcode('laca_seo_contact_info', [$this, 'contactInfoShortcode']);
        add_shortcode('laca_seo_local', [$this, 'localShortcode']);

        // Rank Math compatibility aliases when RM is inactive
        if (!Helper::isRankMathActive() || Helper::isForceFrontend()) {
            add_shortcode('rank_math_contact_info', [$this, 'contactInfoShortcode']);
            add_shortcode('rank_math_local', [$this, 'localShortcode']);
            add_shortcode('wpseo_all_locations', [$this, 'localShortcode']);
            add_shortcode('wpseo_storelocator', [$this, 'storeLocatorShortcode']);
            add_shortcode('wpseo_opening_hours', [$this, 'openingHoursShortcode']);
            add_shortcode('wpseo_map', [$this, 'mapShortcode']);
        }
    }

    public function getAttributes(): array
    {
        return [
            'type'                   => ['default' => 'all'],
            'locations'              => ['default' => 'all'],
            'terms'                  => ['default' => ''],
            'show_company_name'      => ['default' => true],
            'show_logo'              => ['default' => false],
            'show_url'               => ['default' => true],
            'show_address'           => ['default' => true],
            'show_country'           => ['default' => true],
            'show_state'             => ['default' => true],
            'show_telephone'         => ['default' => true],
            'show_secondary_number'  => ['default' => false],
            'show_fax'               => ['default' => false],
            'show_email'             => ['default' => false],
            'show_vat_id'            => ['default' => false],
            'show_tax_id'            => ['default' => false],
            'show_coc_id'            => ['default' => false],
            'show_pricerange'        => ['default' => false],
            'show_opening_hours'     => ['default' => true],
            'show_days'              => ['default' => 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'hide_closed_days'       => ['default' => false],
            'show_on_one_line'       => ['default' => false],
            'opening_hours_note'     => ['default' => ''],
            'show_opening_now_label' => ['default' => false],
            'show_map'               => ['default' => false],
            'map_width'              => ['default' => '100%'],
            'map_height'             => ['default' => '300'],
            'search_radius'          => ['default' => 20],
            'show_radius'            => ['default' => false],
            'show_category_filter'   => ['default' => false],
            'limit'                  => ['default' => -1],
            'order'                  => ['default' => 'ASC'],
            'orderby'                => ['default' => 'title'],
        ];
    }

    public function contactInfoShortcode(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }
        $atts['type'] = 'contact-info';
        return $this->localShortcode($atts);
    }

    public function storeLocatorShortcode(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }
        $atts['type'] = 'store-locator';
        return $this->localShortcode($atts);
    }

    public function openingHoursShortcode(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }
        $atts['type'] = 'opening-hours';
        return $this->localShortcode($atts);
    }

    public function mapShortcode(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }
        $atts['type'] = 'map';
        return $this->localShortcode($atts);
    }

    public function localShortcode(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        $defaults = [];
        foreach ($this->getAttributes() as $key => $attr) {
            $defaults[$key] = $attr['default'];
        }

        if (isset($atts['show_priceRange'])) {
            $atts['show_pricerange'] = $atts['show_priceRange'];
            unset($atts['show_priceRange']);
        }

        $atts = shortcode_atts($defaults, $atts, 'laca_seo_local');

        if ($atts['type'] === 'store-locator') {
            return $this->storeLocator->getData($atts);
        }

        return $this->renderShortcodeData($atts);
    }

    private function renderShortcodeData(array $atts): string
    {
        $locations = $this->getLocations($atts);
        if (empty($locations)) {
            return '';
        }

        $output = '';
        foreach ($locations as $schema) {
            $type = $atts['type'];
            $output .= '<div class="laca-seo-local-box rank-math-local-box">';

            if (!empty($atts['show_company_name']) && !empty($schema['name'])) {
                $output .= '<h3 class="laca-seo-business-title rank-math-business-title">' . esc_html($schema['name']) . '</h3>';
            }

            if ($type === 'opening-hours') {
                $output .= $this->openingHours->getData($atts, $schema);
            } elseif ($type === 'address' || $type === 'contact-info') {
                $output .= $this->address->getData($atts, $schema);
            } else {
                // all / default
                if (!empty($atts['show_address'])) {
                    $output .= $this->address->getData($atts, $schema);
                }
                if (!empty($atts['show_opening_hours'])) {
                    $output .= $this->openingHours->getData($atts, $schema);
                }
            }

            $output .= '</div>';
        }

        return $output;
    }

    private function getLocations(array $atts): array
    {
        $schemas = [];

        // Check if multi locations post exists
        if (!empty($atts['locations']) && $atts['locations'] !== 'all') {
            $ids = array_map('absint', explode(',', (string) $atts['locations']));
            foreach ($ids as $id) {
                $postSchemas = Db::getSchemas($id);
                if (!empty($postSchemas)) {
                    $first = current($postSchemas);
                    $first['name'] = get_the_title($id);
                    $schemas[] = $first;
                }
            }
            if (!empty($schemas)) {
                return $schemas;
            }
        }

        // Query locations CPT if any published
        $args = [
            'post_type'      => ['laca_seo_locations', 'rank_math_locations'],
            'post_status'    => 'publish',
            'posts_per_page' => (int) ($atts['limit'] ?? -1),
            'orderby'        => $atts['orderby'] ?? 'title',
            'order'          => $atts['order'] ?? 'ASC',
        ];

        $posts = get_posts($args);
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $postSchemas = Db::getSchemas($post->ID);
                if (!empty($postSchemas)) {
                    $first = current($postSchemas);
                    if (empty($first['name'])) {
                        $first['name'] = $post->post_title;
                    }
                    $schemas[] = $first;
                }
            }
            if (!empty($schemas)) {
                return $schemas;
            }
        }

        // Fallback to global options
        $global = [
            '@type'                     => Settings::get('titles.local_business_type', 'LocalBusiness'),
            'name'                      => Settings::get('titles.knowledgegraph_name', get_bloginfo('name')),
            'url'                       => Settings::get('titles.url', home_url()),
            'telephone'                 => Settings::get('titles.phone', ''),
            'priceRange'                => Settings::get('titles.price_range', ''),
            'email'                     => Settings::get('titles.email', get_bloginfo('admin_email')),
            'address'                   => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => Settings::get('titles.local_address_street', ''),
                'addressLocality' => Settings::get('titles.local_address_locality', ''),
                'addressRegion'   => Settings::get('titles.local_address_region', ''),
                'postalCode'      => Settings::get('titles.local_address_postalcode', ''),
                'addressCountry'  => Settings::get('titles.local_address_country', ''),
            ],
            'openingHoursSpecification' => Settings::get('titles.opening_hours', []),
        ];

        return [$global];
    }
}
