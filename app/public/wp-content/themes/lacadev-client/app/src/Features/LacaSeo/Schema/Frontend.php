<?php

namespace App\Features\LacaSeo\Schema;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;
use App\Features\LacaSeo\Frontend\Paper\Paper;

defined('ABSPATH') || exit;

class Frontend
{
    public function register(): void
    {
        add_filter('laca_seo/json_ld', [$this, 'addSchema'], 10, 2);
        add_filter('laca_seo/snippet/rich_snippet_article_entity', [$this, 'addNameProperty'], 11);
        add_filter('laca_seo/snippet/rich_snippet_event_entity', [$this, 'validateEventSchema'], 11);
        add_filter('laca_seo/json_ld', [$this, 'connectSchemaEntities'], 99, 2);
        add_filter('laca_seo/schema/validated_data', [$this, 'removePersonEntity'], 999);
    }

    public function validateEventSchema(array $schema): array
    {
        if (!empty($schema['startDate']) && !str_contains($schema['startDate'], 'T')) {
            $startDate = date_i18n('Y-m-d H:i:sP', strtotime($schema['startDate']));
            $schema['startDate'] = str_replace(' ', 'T', $startDate);
        }

        if (!empty($schema['endDate']) && !str_contains($schema['endDate'], 'T')) {
            $endDate = date_i18n('Y-m-d H:i:sP', strtotime($schema['endDate']));
            $schema['endDate'] = str_replace(' ', 'T', $endDate);
        }

        return $schema;
    }

    public function addNameProperty(array $schema): array
    {
        if (empty($schema['headline'])) {
            return $schema;
        }

        $schema['name'] = $schema['headline'];
        if (!isset($schema['articleSection'])) {
            global $post;
            $schema['articleSection'] = \App\Features\LacaSeo\Core\Variables::replace('%primary_taxonomy_terms%', $post);
        }

        return $schema;
    }

    /**
     * Add per-post schemas from database.
     */
    public function addSchema(array $data, JsonLd $jsonld): array
    {
        if (!is_singular() || post_password_required()) {
            return $data;
        }

        global $post;
        if (!$post) {
            return $data;
        }

        $schemas = array_filter(
            Db::getSchemas($post->ID),
            function ($schema) {
                return !in_array($schema['@type'] ?? '', ['WooCommerceProduct', 'EDDProduct'], true);
            }
        );

        $schemas = $jsonld->replaceVariables($schemas, $post, $data);
        $schemas = $jsonld->filter($schemas, $jsonld, $data);

        return array_merge($data, $schemas);
    }

    /**
     * Connect different schema entities using isPartOf, publisher, and mainEntityOfPage properties.
     */
    public function connectSchemaEntities(array $schemas, JsonLd $jsonld): array
    {
        if (empty($schemas)) {
            return $schemas;
        }

        $canonical = Paper::get()->get_canonical();
        $jsonld->parts['canonical'] = $canonical;

        $schemaTypes = [];
        foreach ($schemas as $id => $schema) {
            if ((!str_starts_with((string) $id, 'schema-') && 'richSnippet' !== $id) || empty($schema) || !is_array($schema)) {
                continue;
            }

            $schemaTypes[] = $schema['@type'] ?? '';
            $this->connectProperties($schema, (string) $id, $jsonld, $schemas);
            $this->addMainEntityOfPage($schema, $jsonld);
            $schemas[$id] = $schema;
        }

        return $this->changeWebpageEntity($schemas, $schemaTypes);
    }

    private function connectProperties(array &$schema, string $id, JsonLd $jsonld, array $schemas): void
    {
        if (isset($schema['isCustom'])) {
            unset($schema['isCustom']);
            return;
        }

        if (isset($schema['image']) && empty($schema['image']['url']) && !is_array($schema['image'])) {
            unset($schema['image']);
        }

        $canonical = $jsonld->parts['canonical'] ?? Paper::get()->get_canonical();
        $schema['@id'] = $canonical . '#' . $id;

        if (empty($schema['@type'])) {
            return;
        }

        $types = array_map('strtolower', (array) $schema['@type']);
        foreach ($types as $type) {
            $isEvent = str_contains($type, 'event');
            if ($isEvent) {
                $jsonld->addProp('publisher', $schema, 'organizer', $schemas);
            }

            $props = [
                'is_part_of' => [
                    'key'   => 'webpage',
                    'value' => !in_array($type, ['jobposting', 'musicgroup', 'person', 'product', 'productgroup', 'restaurant', 'service'], true) && !$isEvent,
                ],
                'publisher'  => [
                    'key'   => 'publisher',
                    'value' => !in_array($type, ['jobposting', 'musicgroup', 'person', 'product', 'productgroup', 'restaurant', 'service'], true) && !$isEvent,
                ],
                'thumbnail'  => [
                    'key'   => 'image',
                    'value' => isset($schema['image']) || !in_array($type, ['videoobject'], true),
                ],
                'language'   => [
                    'key'   => 'inLanguage',
                    'value' => !in_array($type, ['person', 'service', 'restaurant', 'product', 'productgroup', 'musicgroup', 'musicalbum', 'jobposting'], true),
                ],
            ];

            if (isset($schema['image']) && in_array($type, ['product', 'productgroup'], true) && is_array($schema['image'])) {
                $props['thumbnail']['value'] = false;
            }

            foreach ($props as $prop => $propData) {
                if (!$propData['value']) {
                    continue;
                }
                $jsonld->addProp($prop, $schema, $propData['key'], $schemas);
            }
        }
    }

    private function addMainEntityOfPage(array &$schema, JsonLd $jsonld): void
    {
        if (!isset($schema['isPrimary'])) {
            return;
        }

        if (!empty($schema['isPrimary'])) {
            $canonical = $jsonld->parts['canonical'] ?? Paper::get()->get_canonical();
            $schema['mainEntityOfPage'] = ['@id' => $canonical . '#webpage'];
        }

        unset($schema['isPrimary']);
    }

    public function removePersonEntity(array $data): array
    {
        if (empty($data['ProfilePage']) || empty($data['ProfilePage']['@id']) || !is_singular()) {
            return $data;
        }

        $tempData = $data;
        $id = $tempData['ProfilePage']['@id'];
        $ids = [];

        unset($tempData['ProfilePage']);
        array_walk_recursive($tempData, function ($val, $key) use (&$ids, $id) {
            if ('@id' === $key && $val === $id) {
                $ids[] = $val;
            }
        });

        if (empty($ids)) {
            unset($data['ProfilePage']);
        }

        return $data;
    }

    private function changeWebpageEntity(array $schemas, array $types): array
    {
        if (in_array('Product', $types, true) && isset($schemas['WebPage'])) {
            $schemas['WebPage']['@type'] = 'ItemPage';
        }

        return $schemas;
    }
}
