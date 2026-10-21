<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Frontend\Paper\Paper;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Webpage implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $canonical = Paper::get()->get_canonical();
        $entity = [
            '@type' => $this->getType(),
            '@id'   => $canonical . '#webpage',
            'url'   => ($canonical !== '') ? $canonical : false,
            'name'  => Paper::get()->get_title(),
        ];

        if (is_singular() && !empty($jsonld->parts)) {
            $entity['datePublished'] = $jsonld->parts['published'] ?? '';
            $entity['dateModified']  = $jsonld->parts['modified'] ?? '';
        }

        if (is_front_page()) {
            $jsonld->addProp('publisher', $entity, 'about', $data);
        }

        $jsonld->addProp('is_part_of', $entity, 'website');
        $jsonld->addProp('thumbnail', $entity, 'primaryImageOfPage', $data);
        $jsonld->addProp('language', $entity);

        if (isset($data['BreadcrumbList'])) {
            $entity['breadcrumb'] = ['@id' => $data['BreadcrumbList']['@id']];
        }

        $data['WebPage'] = $entity;
        return $data;
    }

    private function getType(): string
    {
        $aboutPage = (int) Settings::get('titles.local_seo_about_page', 0);
        $contactPage = (int) Settings::get('titles.local_seo_contact_page', 0);

        $hash = [
            'SearchResultsPage' => is_search(),
            'ProfilePage'       => is_author(),
            'CollectionPage'    => is_home() || is_archive(),
            'AboutPage'         => ($aboutPage > 0 && is_page($aboutPage)),
            'ContactPage'       => ($contactPage > 0 && is_page($contactPage)),
        ];

        $filtered = array_filter($hash);
        return !empty($filtered) ? (string) key($filtered) : 'WebPage';
    }
}
