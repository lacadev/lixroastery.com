<?php

namespace App\Features\LacaSeo\Core;

use App\Features\LacaSeo\Fields\Field;
use App\Features\LacaSeo\Fields\GeneralFields;
use App\Features\LacaSeo\Fields\TitlesFields;

defined('ABSPATH') || exit;

/**
 * Settings manager: reads and writes laca_seo_* options with static caching and registry defaults.
 */
class Settings
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * Map of option groups to their registry fields.
     *
     * @var array<string, array<string, Field>>|null
     */
    private static ?array $registryMap = null;

    /**
     * Get a setting by dotted key, e.g. Settings::get('general.strip_category_base', 'off')
     * or Settings::get('titles.pt_post_title').
     */
    public static function get(string $key, mixed $fallback = null): mixed
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return $fallback;
        }

        [$group, $id] = $parts;
        $groupOptions = self::getGroup($group);

        $value = $fallback;
        if (array_key_exists($id, $groupOptions)) {
            $value = $groupOptions[$id];
        } else {
            // Check fallback from registry default
            $registry = self::getRegistryMap();
            if (isset($registry[$group][$id])) {
                $value = $registry[$group][$id]->getDefault();
            }
        }

        return apply_filters('laca_seo/settings/get', $value, $key);
    }

    /**
     * Get raw setting value from stored database options without registry default fallback.
     */
    public static function getRaw(string $key, mixed $fallback = null): mixed
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return $fallback;
        }

        [$group, $id] = $parts;
        $saved = get_option('laca_seo_' . $group, []);
        if (is_array($saved) && array_key_exists($id, $saved)) {
            return $saved[$id];
        }

        return $fallback;
    }

    /**
     * Get an entire option group as an array, populated with defaults from registry.
     *
     * @return array<string, mixed>
     */
    public static function getGroup(string $group): array
    {
        if (isset(self::$cache[$group])) {
            return self::$cache[$group];
        }

        $option_name = 'laca_seo_' . $group;
        $saved = get_option($option_name, []);

        if (!is_array($saved)) {
            $saved = [];
        }

        // Merge with registry defaults
        $registry = self::getRegistryMap();
        if (isset($registry[$group])) {
            foreach ($registry[$group] as $id => $field) {
                if (!array_key_exists($id, $saved)) {
                    $saved[$id] = $field->getDefault();
                }
            }
        }

        self::$cache[$group] = $saved;
        return $saved;
    }

    /**
     * Update an entire option group.
     *
     * @param string $group
     * @param array<string, mixed> $values
     * @param bool $autoload Whether this option should autoload on every page
     */
    public static function updateGroup(string $group, array $values, ?bool $autoload = null): bool
    {
        $option_name = 'laca_seo_' . $group;

        if ($autoload === null) {
            // General and titles autoload for frontend head performance
            $autoload = in_array($group, ['general', 'titles'], true);
        }

        $result = update_option($option_name, $values, $autoload);
        self::$cache[$group] = $values;

        return $result;
    }

    /**
     * Clear memory cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$registryMap = null;
    }

    /**
     * Build indexed registry map: [group => [id => Field]].
     *
     * @return array<string, array<string, Field>>
     */
    public static function getRegistryMap(): array
    {
        if (self::$registryMap !== null) {
            return self::$registryMap;
        }

        $map = [
            'general'          => [],
            'titles'           => [],
            'sitemap'          => [],
            'instant_indexing' => [],
        ];

        // General fields
        foreach (GeneralFields::get() as $tab => $fields) {
            foreach ($fields as $field) {
                $map['general'][$field->getId()] = $field;
            }
        }

        // Titles fields
        foreach (TitlesFields::get() as $tab => $fields) {
            foreach ($fields as $field) {
                $map['titles'][$field->getId()] = $field;
            }
        }

        // Sitemap fields
        foreach (\App\Features\LacaSeo\Fields\SitemapFields::get() as $tab => $fields) {
            foreach ($fields as $field) {
                $map['sitemap'][$field->getId()] = $field;
            }
        }

        // Instant Indexing fields
        foreach (\App\Features\LacaSeo\Fields\InstantIndexingFields::get() as $tab => $fields) {
            foreach ($fields as $field) {
                $map['instant_indexing'][$field->getId()] = $field;
            }
        }

        // Llms fields
        foreach (\App\Features\LacaSeo\Fields\LlmsFields::get() as $tab => $fields) {
            foreach ($fields as $field) {
                $grp = $field->getGroup() ?: 'general';
                $map[$grp][$field->getId()] = $field;
            }
        }

        self::$registryMap = $map;
        return self::$registryMap;
    }

    /**
     * Get a Field object by group and field ID.
     */
    public static function getField(string $group, string $fieldId): ?Field
    {
        $map = self::getRegistryMap();
        return $map[$group][$fieldId] ?? null;
    }
}
