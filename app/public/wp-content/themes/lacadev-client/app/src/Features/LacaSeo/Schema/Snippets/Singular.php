<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Schema\Db;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Singular implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $schema = $this->canAddSchema($jsonld);
        if (false === $schema) {
            return $data;
        }

        $hook = 'snippet/rich_snippet_' . $schema;
        $pre = apply_filters("rank_math/{$hook}", false, $jsonld->parts, $data);
        $pre = apply_filters("laca_seo/{$hook}", $pre, $jsonld->parts, $data);
        if (false !== $pre) {
            $custom = apply_filters("laca_seo/{$hook}_custom_entity", []);
            $data['richSnippet'] = $custom;
            return $data;
        }

        $entity = [];
        if ($schema === 'article') {
            $obj = new Article();
            $entity = $obj->process($data, $jsonld);
        } elseif ($schema === 'product') {
            $obj = new Product();
            $entity = $obj->process($data, $jsonld);
        }

        $entity = apply_filters("rank_math/{$hook}_entity", $entity);
        $entity = apply_filters("laca_seo/{$hook}_entity", $entity);

        $data['richSnippet'] = $entity;
        return $data;
    }

    private function canAddSchema(JsonLd $jsonld): string|false
    {
        if (empty($jsonld->postId)) {
            return false;
        }

        $schemas = Db::getSchemas($jsonld->postId);
        if (!empty($schemas)) {
            $hasProduct = array_filter($schemas, function ($s) {
                return !empty($s['@type']) && in_array($s['@type'], ['WooCommerceProduct', 'EDDProduct'], true);
            });
            return !empty($hasProduct) ? 'product' : false;
        }

        if (metadata_exists('post', $jsonld->postId, '_laca_seo_rich_snippet')) {
            return (string) get_post_meta($jsonld->postId, '_laca_seo_rich_snippet', true);
        }

        return $this->getDefaultSchema($jsonld);
    }

    private function getDefaultSchema(JsonLd $jsonld): string|false
    {
        $type = Db::getDefaultSchemaType($jsonld->postId);
        if (!$type) {
            return false;
        }

        $type = strtolower($type);
        if (in_array($type, ['article', 'blogposting', 'newsarticle'], true)) {
            return 'article';
        }
        if (in_array($type, ['woocommerceproduct', 'product'], true)) {
            return 'product';
        }

        return $type;
    }
}
