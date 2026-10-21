<?php

namespace App\Features\LacaSeo\Schema\Pro;

use App\Features\LacaSeo\Schema\Db;
use App\Features\LacaSeo\Schema\JsonLd;

defined('ABSPATH') || exit;

class DisplayConditions
{
    private static array $conditions = [];
    private static array $insertSchemas = [];

    public static function registerPostType(): void
    {
        register_post_type('laca_seo_schema_tpl', [
            'labels' => [
                'name'               => __('Schema Templates', 'lacadev'),
                'singular_name'      => __('Schema Template', 'lacadev'),
                'add_new'            => __('Add New', 'lacadev'),
                'add_new_item'       => __('Add New Template', 'lacadev'),
                'edit_item'          => __('Edit Template', 'lacadev'),
                'new_item'           => __('New Template', 'lacadev'),
                'view_item'          => __('View Template', 'lacadev'),
                'search_items'       => __('Search Templates', 'lacadev'),
                'not_found'          => __('No templates found', 'lacadev'),
                'not_found_in_trash' => __('No templates found in Trash', 'lacadev'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'supports'            => ['title', 'custom-fields'],
            'can_export'          => true,
        ]);
    }

    /**
     * Get Schema data from Schema Templates post type (laca_seo_schema_tpl or rank_math_schema).
     */
    public static function getSchemaTemplates(array $data = [], ?JsonLd $jsonld = null): array
    {
        global $wpdb;

        // Query both template post types for full compatibility
        $templates = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('laca_seo_schema_tpl', 'rank_math_schema') AND post_status='publish'"
        );

        if (empty($templates)) {
            return [];
        }

        $newdata = [];
        foreach ($templates as $templateId) {
            self::$conditions = [
                'general'  => '',
                'singular' => '',
                'archive'  => '',
            ];

            $schema = Db::getSchemas((int) $templateId);
            if (empty($schema)) {
                // If not found in _laca_seo_schema_*, also check rank_math_schema_* on template
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND (meta_key LIKE %s OR meta_key LIKE %s)",
                    $templateId,
                    '_laca_seo_schema_%',
                    'rank_math_schema_%'
                ));
                foreach ($rows as $row) {
                    $val = maybe_unserialize($row->meta_value);
                    if (!empty($val) && is_array($val)) {
                        $schema['schema-' . $row->meta_id] = $val;
                    }
                }
            }

            if (empty($schema)) {
                continue;
            }

            $currentSchema = current($schema);
            self::prepareInsertedSchemas($currentSchema);

            if (!self::canAdd($currentSchema)) {
                continue;
            }

            if (is_admin()) {
                $newdata[] = [
                    'id'     => $templateId,
                    'schema' => $currentSchema,
                ];
                continue;
            }

            if ($jsonld) {
                $schema = $jsonld->replaceVariables($schema);
                $schema = $jsonld->filter($schema, $jsonld, $data);
            }

            $newdata[] = $schema;
        }

        return $newdata;
    }

    /**
     * Whether schema can be added to current page.
     */
    public static function canAdd(array $schema): bool
    {
        if (empty($schema) || empty($schema['metadata']['displayConditions'])) {
            return false;
        }

        $post_ids      = [];
        $post_terms    = [];
        $post_types    = [];
        $all_singulars = [];

        $archive_ids   = [];
        $archive_terms = [];
        $archive_types = [];
        $all_archives  = [];

        $searches = [];
        $generals = [];

        foreach ($schema['metadata']['displayConditions'] as $condition) {
            $operator = $condition['condition'] ?? 'include';
            if ('insert' === $operator) {
                continue;
            }
            $category = $condition['category'] ?? '';
            $taxonomy = !empty($condition['postTaxonomy']) ? $condition['postTaxonomy'] : '';
            $type     = $condition['type'] ?? '';
            $value    = (int) ($condition['value'] ?? 0);

            if ('singular' === $category) {
                if ($taxonomy) {
                    self::addToConditions($post_terms, $condition, $operator);
                } elseif ($value && !$taxonomy) {
                    self::addToConditions($post_ids, $condition, $operator);
                } elseif ('all' === $type) {
                    self::addToConditions($all_singulars, $condition, $operator);
                } else {
                    self::addToConditions($post_types, $condition, $operator);
                }
            } elseif ('archive' === $category && 'search' !== $type) {
                if ($taxonomy) {
                    self::addToConditions($archive_terms, $condition, $operator);
                } elseif ($value && !$taxonomy) {
                    self::addToConditions($archive_ids, $condition, $operator);
                } elseif ('all' === $type) {
                    self::addToConditions($all_archives, $condition, $operator);
                } else {
                    self::addToConditions($archive_types, $condition, $operator);
                }
            } elseif ('archive' === $category && 'search' === $type) {
                self::addToConditions($searches, $condition, $operator);
            } elseif ('general' === $category) {
                self::addToConditions($generals, $condition, $operator);
            }
        }

        $all = [
            'singulars' => array_merge($post_ids, $post_terms, $post_types, $all_singulars),
            'archives'  => array_merge($archive_ids, $archive_terms, $archive_types, $all_archives),
            'searches'  => $searches,
            'generals'  => $generals,
        ];

        // Singular
        if ((is_singular() || is_admin() || !empty($GLOBALS['post'])) && !empty($all['singulars'])) {
            global $post;
            $post_id = !empty($post) ? $post->ID : get_the_ID();
            if (empty($post_id)) {
                return false;
            }
            $post_type = get_post_type($post_id);

            foreach ($all['singulars'] as $condition) {
                $operator = $condition['condition'] ?? 'include';
                $taxonomy = !empty($condition['postTaxonomy']) ? $condition['postTaxonomy'] : '';
                $type     = $condition['type'] ?? '';
                $value    = (int) ($condition['value'] ?? 0);

                $result = self::canAddSingular($operator, $type, $value, $taxonomy);

                if ($post_id === $value) {
                    return $result;
                }

                if ($taxonomy && has_term($value, $taxonomy, $post_id)) {
                    return $result;
                }

                if ($post_type === $type && !$taxonomy && !$value) {
                    return $result;
                }

                if ('all' === $type) {
                    return $result;
                }
            }
        }

        // Search
        if (is_search() && !empty($all['searches'])) {
            foreach ($all['searches'] as $condition) {
                return 'include' === ($condition['condition'] ?? 'include');
            }
        }

        // Archive
        if ((is_search() || is_archive()) && !empty($all['archives'])) {
            $object_id = is_archive() ? get_queried_object_id() : null;

            foreach ($all['archives'] as $condition) {
                $operator = $condition['condition'] ?? 'include';
                $type     = $condition['type'] ?? '';
                $value    = (int) ($condition['value'] ?? 0);
                $result   = 'include' === $operator;

                if ('author' === $type && is_author()) {
                    if (is_author($value) && $object_id === $value) {
                        return $result;
                    } elseif (!$value) {
                        return $result;
                    }
                } elseif ('category' === $type) {
                    if (is_category($value) && $object_id === $value) {
                        return $result;
                    } elseif (!$value && is_category()) {
                        return $result;
                    }
                } elseif ('post_tag' === $type) {
                    if (is_tag($value) && $object_id === $value) {
                        return $result;
                    } elseif (!$value && is_tag()) {
                        return $result;
                    }
                } elseif (is_tax($type) && $object_id === $value) {
                    return $result;
                } elseif (!$value && is_tax($type)) {
                    return $result;
                } elseif ('all' === $type) {
                    return $result;
                }
            }
        }

        // General
        if (!empty($all['generals'])) {
            foreach ($all['generals'] as $condition) {
                $operator = $condition['condition'] ?? 'include';
                return 'include' === $operator;
            }
        }

        return false;
    }

    public static function addToConditions(array &$data, array $condition, string $operator): void
    {
        if (self::isExclude($operator)) {
            array_unshift($data, $condition);
        } else {
            $data[] = $condition;
        }
    }

    private static function prepareInsertedSchemas(array $schema): void
    {
        if (empty($schema) || empty($schema['metadata']['displayConditions'])) {
            return;
        }

        foreach ($schema['metadata']['displayConditions'] as $condition) {
            $operator = $condition['condition'] ?? '';
            if ('insert' !== $operator) {
                continue;
            }

            if (empty($schema['metadata']['title'])) {
                continue;
            }

            $in_schema = $condition['category'] ?? '';
            if ('custom' === $in_schema) {
                if (empty($condition['value'])) {
                    continue;
                }
                $in_schema = $condition['value'];
            }

            if ('ProfilePage' === $in_schema && !is_singular()) {
                continue;
            }

            if ('ProfilePage' === $in_schema && !empty($condition['authorID'])) {
                $author_ids = wp_parse_id_list($condition['authorID']);
                global $post;
                if (!in_array((int) ($post->post_author ?? 0), $author_ids, true)) {
                    continue;
                }
            }

            $with_key = $schema['metadata']['title'];
            self::$insertSchemas[$in_schema][] = [
                'key'    => $with_key,
                'schema' => $schema,
            ];
        }
    }

    public static function getInsertableSchemas(): array
    {
        return self::$insertSchemas;
    }

    private static function canAddSingular(string $operator, string $type, int $value, string $taxonomy): bool
    {
        global $post;
        $currentPost = (is_admin() || is_singular() ? get_post(get_the_ID()) : null) ?: $post;
        if (empty($currentPost)) {
            return false;
        }

        if ('all' === $type) {
            return 'include' === $operator;
        }

        if ($type !== $currentPost->post_type) {
            return false;
        }

        if (!$value) {
            return 'include' === $operator;
        }

        if ($taxonomy && self::isExclude($operator)) {
            return !has_term($value, $taxonomy, $currentPost->ID);
        }

        if ($taxonomy) {
            return 'include' === $operator && has_term($value, $taxonomy, $currentPost->ID);
        }

        if (absint($currentPost->ID) === absint($value)) {
            return 'include' === $operator;
        }

        return self::isExclude($operator);
    }

    private static function isExclude(string $operator): bool
    {
        return 'exclude' === $operator;
    }
}
