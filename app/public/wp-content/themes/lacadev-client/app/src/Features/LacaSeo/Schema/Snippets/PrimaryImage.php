<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class PrimaryImage implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $image = \App\Features\LacaSeo\Frontend\OpenGraph\Image::getDetails();
        if (empty($image) || empty($image['url'])) {
            return $data;
        }

        $data['primaryImage'] = [
            '@type'   => 'ImageObject',
            '@id'     => $image['url'],
            'url'     => $image['url'],
            'width'   => $image['width'] ?? 0,
            'height'  => $image['height'] ?? 0,
            'caption' => '',
        ];

        $jsonld->addProp('language', $data['primaryImage']);

        return $data;
    }
}
