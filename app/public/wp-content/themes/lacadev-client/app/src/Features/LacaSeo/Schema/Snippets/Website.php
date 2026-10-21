<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Website implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $data['WebSite'] = [
            '@type' => 'WebSite',
            '@id'   => home_url('/#website'),
            'url'   => get_home_url(),
            'name'  => $jsonld->getWebsiteName(),
        ];

        $altName = Settings::get('titles.website_alternate_name', '');
        if (!empty($altName)) {
            $data['WebSite']['alternateName'] = $altName;
        }

        $jsonld->addProp('publisher', $data['WebSite'], 'publisher', $data);
        $jsonld->addProp('language', $data['WebSite']);

        // Sitelinks Searchbox
        $disableSearch = apply_filters('rank_math/json_ld/disable_search', !is_front_page() || is_paged());
        $disableSearch = apply_filters('laca_seo/json_ld/disable_search', $disableSearch);
        if ($disableSearch) {
            return $data;
        }

        $searchUrl = home_url('/?s={search_term_string}');
        $searchUrl = apply_filters('rank_math/json_ld/search_url', $searchUrl);
        $searchUrl = apply_filters('laca_seo/json_ld/search_url', $searchUrl);

        $data['WebSite']['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => $searchUrl,
            'query-input' => 'required name=search_term_string',
        ];

        return $data;
    }
}
