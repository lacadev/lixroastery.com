<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Frontend\Breadcrumbs as BreadcrumbTrail;
use App\Features\LacaSeo\Frontend\Paper\Paper;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Breadcrumbs implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $trail = new BreadcrumbTrail();
        $crumbs = $trail->getCrumbs();
        if (empty($crumbs)) {
            return $data;
        }

        $canonical = Paper::get()->get_canonical();
        if (empty($canonical)) {
            return $data;
        }
        $entity = [
            '@type'           => 'BreadcrumbList',
            '@id'             => $canonical . '#breadcrumb',
            'itemListElement' => [],
        ];

        $pos = 1;
        foreach ($crumbs as $crumb) {
            if (!empty($crumb['hide_in_schema']) || empty($crumb[1])) {
                continue;
            }

            $entity['itemListElement'][] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'item'     => [
                    '@id'  => $crumb[1],
                    'name' => $crumb[0],
                ],
            ];
            $pos++;
        }

        $entity = apply_filters('rank_math/snippet/breadcrumb', $entity);
        $entity = apply_filters('laca_seo/snippet/breadcrumb', $entity);

        if (empty($entity['itemListElement'])) {
            return $data;
        }

        $data['BreadcrumbList'] = $entity;
        return $data;
    }
}
