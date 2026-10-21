<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Publisher implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $type = (string) Settings::get('titles.knowledgegraph_type', 'company');
        $id = ($type === 'company') ? 'organization' : 'person';

        $data['publisher'] = [
            '@type' => $this->getPublisherType($type),
            '@id'   => home_url("/#{$id}"),
            'name'  => $jsonld->getOrganizationName(),
        ];

        $socialProfiles = $jsonld->getSocialProfiles();
        if (!empty($socialProfiles)) {
            $data['publisher']['sameAs'] = $socialProfiles;
        }

        $jsonld->addProp('image', $data['publisher']);

        if (empty($data['publisher']['logo'])) {
            return $data;
        }

        if ('person' === $type) {
            $data['publisher']['image'] = $data['publisher']['logo'];
        }

        if (!is_singular()) {
            unset($data['publisher']['logo']);
        }

        return $data;
    }

    private function getPublisherType(string $type): string|array
    {
        if ('company' === $type) {
            return 'Organization';
        }

        if (!is_singular()) {
            return 'Person';
        }

        return ['Person', 'Organization'];
    }
}
