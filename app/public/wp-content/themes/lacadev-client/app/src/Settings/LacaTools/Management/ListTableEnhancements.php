<?php

namespace App\Settings\LacaTools\Management;

/**
 * ListTableEnhancements
 * Adds Views column, ID columns, and Duplication support to admin list tables.
 * Extracted from ManagementExperience (lines 1425–1590).
 */
class ListTableEnhancements
{
    private const VIEW_COUNT_META = '_gm_view_count';

    public function __construct(private ContentAuditService $auditService) {}

    public function register(): void
    {
        add_action('init', function () {
            $this->addViewsColumn();
            $this->enrichProductList();
            $this->enrichPostList();
            $this->enableDuplication();
        });
    }

    /**
     * Adds a "Views" column to all dashboard post types.
     */
    public function addViewsColumn(): void
    {
        // Lượt xem chỉ áp dụng cho post/CPT — page không có khái niệm
        // "bài viết được xem nhiều", nên loại page ra khỏi cột này.
        $post_types = array_diff($this->auditService->getDashboardPostTypes(), ['page']);

        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", function ($columns) {
                $pos = 1;
                if (isset($columns['featured_image'])) {
                    $pos = array_search('featured_image', array_keys($columns)) + 1;
                } elseif (isset($columns['lacadev_thumb'])) {
                    $pos = array_search('lacadev_thumb', array_keys($columns)) + 1;
                }
                return insertArrayAtPosition($columns, ['laca_views' => 'Lượt xem'], $pos);
            }, 10000);

            add_filter("manage_edit-{$post_type}_sortable_columns", function ($sortable) {
                $sortable['laca_views'] = 'laca_views';
                return $sortable;
            });
        }

        $render_views_col = function ($column, $post_id) {
            if ($column === 'laca_views') {
                $views = get_post_meta($post_id, self::VIEW_COUNT_META, true) ?: 0;
                echo '<div class="laca-views-col">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo '<strong>' . number_format_i18n($views) . '</strong>';
                echo '</div>';
            }
        };

        add_action('manage_posts_custom_column', $render_views_col, 10, 2);
        add_action('manage_pages_custom_column', $render_views_col, 10, 2);

        add_action('pre_get_posts', function ($query) {
            if (!is_admin() || !$query->is_main_query() || $query->get('orderby') !== 'laca_views') {
                return;
            }
            $query->set('meta_key', self::VIEW_COUNT_META);
            $query->set('orderby', 'meta_value_num');
        });
    }

    /**
     * Enriches WooCommerce product list with thumbnail and ID columns.
     */
    public function enrichProductList(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('manage_edit-product_columns', function ($columns) {
            $new_columns = [];
            foreach ($columns as $key => $value) {
                if ($key === 'name') {
                    $new_columns['lacadev_thumb'] = 'Ảnh';
                }
                $new_columns[$key] = $value;
                if ($key === 'cb') {
                    $new_columns['lacadev_id'] = 'ID';
                }
            }
            return $new_columns;
        }, 20);

        add_action('manage_product_posts_custom_column', function ($column, $post_id) {
            if ($column === 'lacadev_id') {
                echo '<span style="color: #999; font-family: monospace;">#' . esc_html($post_id) . '</span>';
            }
            if ($column === 'lacadev_thumb') {
                echo get_the_post_thumbnail($post_id, [40, 40], ['style' => 'border-radius: 4px; border: 1px solid #ddd;']);
            }
        }, 10, 2);
    }

    /**
     * Enriches Post list with ID column.
     */
    public function enrichPostList(): void
    {
        add_filter('manage_post_posts_columns', function ($columns) {
            $columns['post_id_val'] = 'ID';
            return $columns;
        });

        add_action('manage_post_posts_custom_column', function ($column, $post_id) {
            if ($column === 'post_id_val') {
                echo '<span style="color: #999;">' . esc_html($post_id) . '</span>';
            }
        }, 10, 2);
    }

    /**
     * Enables post/product duplication via row action.
     */
    public function enableDuplication(): void
    {
        $add_duplicate_link = function ($actions, $post) {
            if (current_user_can('edit_posts')) {
                $actions['duplicate'] = '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=lacadev_duplicate_post&post=' . $post->ID), 'lacadev_duplicate_post_nonce') . '" title="Sao chép nội dung này">Sao chép</a>';
            }
            return $actions;
        };

        add_filter('post_row_actions', $add_duplicate_link, 10, 2);
        add_filter('page_row_actions', $add_duplicate_link, 10, 2);

        add_action('admin_post_lacadev_duplicate_post', function () {
            if (!isset($_GET['post']) || !current_user_can('edit_posts')) {
                wp_die('No post to duplicate!');
            }

            check_admin_referer('lacadev_duplicate_post_nonce');

            $post_id = absint($_GET['post']);
            $post    = get_post($post_id);

            if ($post) {
                $args = [
                    'post_author'  => get_current_user_id(),
                    'post_content' => $post->post_content,
                    'post_excerpt' => $post->post_excerpt,
                    'post_status'  => 'draft',
                    'post_title'   => $post->post_title . ' (Bản sao)',
                    'post_type'    => $post->post_type,
                    'post_parent'  => $post->post_parent,
                    'menu_order'   => $post->menu_order,
                ];

                $new_post_id = wp_insert_post($args);

                foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
                    $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
                    wp_set_object_terms($new_post_id, $terms, $taxonomy);
                }

                foreach (get_post_custom($post_id) as $key => $values) {
                    foreach ($values as $value) {
                        add_post_meta($new_post_id, $key, $value);
                    }
                }

                wp_redirect(admin_url('edit.php?post_type=' . $post->post_type));
                exit;
            }
        });
    }
}
