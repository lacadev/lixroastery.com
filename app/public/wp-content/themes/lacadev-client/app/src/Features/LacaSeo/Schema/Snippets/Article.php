<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Variables;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Article implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $schemaType = \App\Features\LacaSeo\Schema\Db::getDefaultSchemaType($jsonld->postId) ?: 'Article';

        $entity = [
            '@type'          => $schemaType,
            'headline'       => $jsonld->parts['title'] ?? '',
            'keywords'       => Variables::replace('%keywords%', $jsonld->post),
            'datePublished'  => $jsonld->parts['published'] ?? '',
            'dateModified'   => $jsonld->parts['modified'] ?? '',
            'isPrimary'      => true,
            'articleSection' => Variables::replace('%primary_taxonomy_terms%', $jsonld->post),
            'author'         => !empty($data['ProfilePage']) ? [
                '@id'  => $data['ProfilePage']['@id'],
                'name' => $jsonld->parts['author'] ?? '',
            ] : [
                '@type' => 'Person',
                'name'  => $jsonld->parts['author'] ?? '',
            ],
        ];

        $jsonld->addProp('publisher', $entity, 'publisher', $data);

        if (!empty($jsonld->parts['desc'])) {
            $entity['description'] = $jsonld->parts['desc'];
        }

        return $entity;
    }
}
