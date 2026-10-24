<?php

namespace App\Settings\LacaTools\Management;

use App\Features\DynamicCPT\DynamicCptManager;

/**
 * ListTableInlineEditor
 *
 * Cho phép cuộn ngang (horizontal scroll) và chỉnh sửa trực tiếp (inline edit)
 * các trường dữ liệu ngoài danh sách cho Product và tất cả Custom Post Types.
 *
 * Tính năng:
 *  1. Bọc table trong .laca-table-scroll với sticky columns (checkbox, ảnh, tiêu đề).
 *  2. Tự động hiển thị các cột tùy biến từ DynamicMeta hoặc cấu hình riêng.
 *  3. Click vào ô để sửa trực tiếp (text input hoặc select/taxonomy) với AJAX lưu tự động.
 *  4. Loại bỏ các cột tĩnh trùng lặp (ví dụ core taxonomy-*, is_in_stock).
 *  5. Preload cache meta và term để triệt tiêu hoàn toàn truy vấn N+1.
 */
class ListTableInlineEditor
{
    private const NONCE_ACTION = 'laca_inline_edit_nonce';

    /**
     * Cache cấu hình của màn hình hiện tại để inject vào admin_footer.
     *
     * @var array
     */
    private array $currentScreenConfig = [];

    public function register(): void
    {
        add_action('init', [$this, 'setupListTableColumns'], 25);
        add_action('admin_head-edit.php', [$this, 'outputEarlyHeadScript'], 1);
        add_action('manage_posts_extra_tablenav', [$this, 'outputSkeletonPlaceholder'], 1, 1);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 99);
        add_action('admin_footer-edit.php', [$this, 'outputConfigScript'], 5);
        add_action('wp_ajax_laca_inline_update_field', [$this, 'handleAjaxUpdate']);
        add_action('wp_ajax_laca_inline_get_options', [$this, 'handleAjaxGetOptions']);
    }

    /**
     * Lấy danh sách post types hỗ trợ cuộn ngang và sửa trực tiếp.
     *
     * @return string[]
     */
    public function getSupportedPostTypes(): array
    {
        $types = [];

        // 1. WooCommerce Product
        if (class_exists('WooCommerce') || post_type_exists('product')) {
            $types[] = 'product';
        }

        // 2. Tất cả Dynamic CPTs
        if (class_exists(DynamicCptManager::class)) {
            foreach (DynamicCptManager::getAll() as $cpt) {
                if (!empty($cpt['slug'])) {
                    $types[] = (string) $cpt['slug'];
                }
            }
        }

        // 3. Các CPT public khác có UI trong admin
        $public_cpts = get_post_types(['public' => true, 'show_ui' => true], 'names');
        $excluded = [
            'attachment', 'revision', 'nav_menu_item', 'custom_css',
            'customize_changeset', 'oembed_cache', 'user_request',
            'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation',
        ];

        foreach ($public_cpts as $slug) {
            if (!in_array($slug, $excluded, true) && !in_array($slug, $types, true)) {
                $types[] = $slug;
            }
        }

        return (array) apply_filters('laca_inline_edit_post_types', array_unique($types));
    }

    /**
     * Cấu hình các trường tùy biến cho một post type.
     *
     * @param string $post_type
     * @return array
     */
    public function getPostTypeFields(string $post_type): array
    {
        $fields = [];

        // 1. Trường chung cho Product (WooCommerce)
        if ($post_type === 'product' && (class_exists('WooCommerce') || post_type_exists('product'))) {
            $fields['stock_status'] = [
                'label' => __('Hàng tồn kho', 'laca'),
                'type'  => 'stock_status',
            ];
        }

        // 2. Trường chung menu_order cho các post type có hỗ trợ thứ tự hiển thị
        if (is_post_type_hierarchical($post_type) || post_type_supports($post_type, 'page-attributes')) {
            $fields['menu_order'] = [
                'label' => __('Thứ tự (Order)', 'laca'),
                'type'  => 'meta',
            ];
        }

        // 3. Tự động thêm các taxonomy công khai của post type
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $tax_slug => $tax_obj) {
            if (!$tax_obj->show_ui || in_array($tax_slug, ['post_format', 'translation_priority'], true)) {
                continue;
            }
            if (!isset($fields[$tax_slug])) {
                $fields[$tax_slug] = [
                    'label'    => $tax_obj->labels->name ?? $tax_obj->label,
                    'type'     => 'taxonomy',
                    'taxonomy' => $tax_slug,
                ];
            }
        }

        // 4. Quét DynamicMeta fields nếu có
        $dynamic_fields = $this->discoverDynamicMetaFields($post_type);
        if (!empty($dynamic_fields)) {
            $fields = array_merge($fields, $dynamic_fields);
        }

        // 5. Filter đăng ký field đặc thù của site từ child theme (§2.6)
        $fields = apply_filters('laca/list_inline/fields', $fields, $post_type);
        $fields = apply_filters("laca_cpt_{$post_type}_admin_custom_fields", $fields, $post_type);
        return (array) apply_filters('laca_admin_custom_fields', $fields, $post_type);
    }

    /**
     * Tự động trích xuất các trường từ file DynamicMeta của CPT.
     *
     * @param string $post_type
     * @return array
     */
    private function discoverDynamicMetaFields(string $post_type): array
    {
        $metaDir = class_exists(DynamicCptManager::class) ? DynamicCptManager::getMetaDir() : '';
        if (!$metaDir) {
            return [];
        }

        $filePath = $metaDir . '/' . sanitize_key($post_type) . '-meta.php';
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if (!$content) {
            return [];
        }

        $fields = [];

        // Match Field::make('type', 'slug', __('Label', ...))
        $pattern = '/(?:Field::make|\\\\Carbon_Fields\\\\Field\\\\Field::make)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*__\(\s*[\'"]([^\'"]*)[\'"])?/i';
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            $supportedTypes = ['text', 'textarea', 'date', 'select', 'radio', 'checkbox'];
            foreach ($matches as $match) {
                $type  = strtolower($match[1]);
                $slug  = $match[2];
                $label = !empty($match[3]) ? $match[3] : ucfirst(str_replace('_', ' ', $slug));

                if (in_array($type, $supportedTypes, true)) {
                    $fields[$slug] = [
                        'label' => $label,
                        'type'  => 'meta',
                    ];
                }
            }
        }

        return $fields;
    }

    /**
     * Đăng ký cột hiển thị cho từng post type được hỗ trợ.
     */
    public function setupListTableColumns(): void
    {
        $supported = $this->getSupportedPostTypes();

        foreach ($supported as $post_type) {
            if (!apply_filters('laca/list_inline/enabled', true, $post_type)) {
                continue;
            }

            $fields = $this->getPostTypeFields($post_type);
            if (empty($fields)) {
                continue;
            }

            // Hook lọc cột chuẩn xác cho Product, Page và các CPT
            if ($post_type === 'product') {
                $column_hook = 'manage_edit-product_columns';
                $render_hook = 'manage_product_posts_custom_column';
            } elseif ($post_type === 'page') {
                $column_hook = 'manage_pages_columns';
                $render_hook = 'manage_pages_custom_column';
            } else {
                $column_hook = "manage_{$post_type}_posts_columns";
                $render_hook = "manage_{$post_type}_posts_custom_column";
            }

            add_filter($column_hook, function ($columns) use ($fields, $post_type) {
                return $this->injectCustomColumns($columns, $fields, $post_type);
            }, 30);

            add_action($render_hook, function ($column, $post_id) use ($fields, $post_type) {
                $this->renderCustomColumnCell($column, $post_id, $fields, $post_type);
            }, 10, 2);
        }

        // Preload meta & terms cache để tránh N+1
        add_filter('the_posts', function ($posts, $query) use ($supported) {
            if (!is_admin() || !$query->is_main_query() || empty($posts)) {
                return $posts;
            }

            $current_type = $query->get('post_type') ?: 'post';
            if (in_array($current_type, $supported, true)) {
                update_post_caches($posts, $current_type, true, true);
            }

            return $posts;
        }, 10, 2);
    }

    /**
     * Chèn các cột tùy biến vào ngay sau cột Tên / Tiêu đề và loại bỏ cột tĩnh trùng lặp.
     *
     * @param array  $columns
     * @param array  $fields
     * @param string $post_type
     * @return array
     */
    private function injectCustomColumns(array $columns, array $fields, string $post_type): array
    {
        if (empty($fields) || isset($columns['laca_' . array_key_first($fields)])) {
            return $columns;
        }

        // Loại bỏ các cột taxonomy tĩnh mặc định trùng lặp của WP / WooCommerce
        foreach ($fields as $field_key => $field_config) {
            if (($field_config['type'] ?? '') === 'taxonomy') {
                $tax = $field_config['taxonomy'] ?? $field_key;
                unset($columns['taxonomy-' . $tax]);
                unset($columns[$tax]);
                if ($tax === 'post_tag') {
                    unset($columns['tags']);
                }
            }
        }

        // Với product, loại bỏ các cột trùng lặp nếu trường tùy biến đã thay thế
        if ($post_type === 'product') {
            if (isset($fields['stock_status'])) {
                unset($columns['is_in_stock']);
            }
            if (isset($fields['product_cat'])) {
                unset($columns['product_cat']);
                unset($columns['taxonomy-product_cat']);
            }
        }

        $new_columns = [];
        $inserted    = false;

        // Vị trí chèn ưu tiên: sau 'name' (WooCommerce) hoặc 'title' (Standard/CPT)
        $target_key = isset($columns['name']) ? 'name' : (isset($columns['title']) ? 'title' : null);

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            if ($target_key && $key === $target_key && !$inserted) {
                foreach ($fields as $field_key => $field_config) {
                    $new_columns['laca_' . $field_key] = $field_config['label'] ?? ucfirst($field_key);
                }
                $inserted = true;
            }
        }

        // Trường hợp không tìm thấy name/title, append vào cuối
        if (!$inserted) {
            foreach ($fields as $field_key => $field_config) {
                $new_columns['laca_' . $field_key] = $field_config['label'] ?? ucfirst($field_key);
            }
        }

        return $new_columns;
    }

    /**
     * Render nội dung cho ô dữ liệu tùy biến (cho phép inline edit).
     */
    private function renderCustomColumnCell(string $column, int $post_id, array $fields, string $post_type): void
    {
        if (strpos($column, 'laca_') !== 0) {
            return;
        }

        if (get_post_type($post_id) !== $post_type) {
            return;
        }

        $field_key = substr($column, 5); // strip 'laca_'
        if (!isset($fields[$field_key])) {
            return;
        }

        $field_config  = $fields[$field_key];
        $field_type    = $field_config['type'] ?? 'meta';
        $raw_value     = $this->getRawValue($post_id, $field_key, $field_config);
        $display_value = $this->getDisplayValue($post_id, $field_key, $field_config);

        $display_text = $display_value !== '' ? $display_value : '—';
        $empty_class  = $display_value === '' ? ' laca-inline-empty' : '';

        printf(
            '<div class="laca-inline-cell" role="button" tabindex="0" data-post-id="%d" data-key="%s" data-type="%s" data-post-type="%s" title="%s">' .
                '<span class="laca-inline-val%s">%s</span>' .
                '<span class="hidden laca-inline-raw" data-key="%s" data-value="%s"></span>' .
            '</div>',
            absint($post_id),
            esc_attr($field_key),
            esc_attr($field_type),
            esc_attr($post_type),
            esc_attr__('Click để sửa nhanh', 'laca'),
            esc_attr($empty_class),
            esc_html($display_text),
            esc_attr($field_key),
            esc_attr($raw_value)
        );
    }

    /**
     * Lấy giá trị thô để nạp vào form sửa nhanh.
     */
    public function getRawValue(int $post_id, string $key, array $config): string
    {
        $type = $config['type'] ?? 'meta';

        if ($type === 'stock_status') {
            if (function_exists('wc_get_product')) {
                $product = wc_get_product($post_id);
                if ($product) {
                    return (string) $product->get_stock_status('edit');
                }
            }
            return (string) get_post_meta($post_id, '_stock_status', true);
        }

        if ($type === 'taxonomy') {
            $taxonomy = $config['taxonomy'] ?? $key;
            $terms    = get_the_terms($post_id, $taxonomy);
            if (is_wp_error($terms) || empty($terms)) {
                return '';
            }
            return implode(',', wp_list_pluck($terms, 'term_id'));
        }

        if ($key === 'menu_order') {
            $p = get_post($post_id);
            return $p ? (string) $p->menu_order : '0';
        }

        // Carbon Fields hoặc WP Meta: đọc từ postmeta cache đã preload
        $val = get_post_meta($post_id, '_' . $key, true);
        if ($val === '' || $val === false) {
            $val = get_post_meta($post_id, $key, true);
        }
        if (($val === '' || $val === false) && function_exists('carbon_get_post_meta')) {
            $val = carbon_get_post_meta($post_id, $key);
        }

        if (is_array($val) || is_object($val)) {
            return '';
        }

        return (string) $val;
    }

    /**
     * Lấy giá trị định dạng hiển thị ra bảng.
     */
    public function getDisplayValue(int $post_id, string $key, array $config): string
    {
        $type = $config['type'] ?? 'meta';

        if ($type === 'stock_status') {
            $raw     = $this->getRawValue($post_id, $key, $config);
            $options = function_exists('wc_get_product_stock_status_options')
                ? wc_get_product_stock_status_options()
                : $this->getStockStatusOptions();
            return $options[$raw] ?? ($raw ?: '—');
        }

        if ($type === 'taxonomy') {
            $taxonomy = $config['taxonomy'] ?? $key;
            $terms    = get_the_terms($post_id, $taxonomy);
            if (is_wp_error($terms) || empty($terms)) {
                return '—';
            }
            $names = wp_list_pluck($terms, 'name');
            $decoded_names = array_map(fn($n) => html_entity_decode((string) $n, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $names);
            return implode(', ', $decoded_names) ?: '—';
        }

        if ($type === 'select') {
            $raw     = $this->getRawValue($post_id, $key, $config);
            $options = $config['options'] ?? [];
            return $options[$raw] ?? ($raw !== '' ? $raw : '—');
        }

        if ($type === 'toggle') {
            $raw = $this->getRawValue($post_id, $key, $config);
            return $raw ? __('Bật', 'laca') : __('Tắt', 'laca');
        }

        if ($key === 'menu_order') {
            $p = get_post($post_id);
            return $p ? (string) $p->menu_order : '0';
        }

        $raw = $this->getRawValue($post_id, $key, $config);
        return $raw !== '' ? $raw : '—';
    }

    /**
     * Danh sách trạng thái tồn kho WooCommerce.
     *
     * @return array
     */
    public function getStockStatusOptions(): array
    {
        return [
            'instock'     => __('Còn hàng', 'laca'),
            'outofstock'  => __('Hết hàng', 'laca'),
            'onbackorder' => __('Đang chờ hàng', 'laca'),
        ];
    }

    /**
     * Lấy tất cả term options của 1 taxonomy, phân cấp cây danh mục gọn gàng.
     *
     * @param string $taxonomy
     * @return array
     */
    public function getTaxonomyOptions(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        // Gom nhóm theo parent_id trong memory
        $by_parent = [];
        foreach ($terms as $term) {
            $parent_id = (int) $term->parent;
            $by_parent[$parent_id][] = $term;
        }

        $options = [];
        $build_tree = function (int $parent_id, int $depth) use (&$build_tree, &$by_parent, &$options) {
            if (empty($by_parent[$parent_id])) {
                return;
            }
            foreach ($by_parent[$parent_id] as $term) {
                $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                $name   = html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $options[] = [
                    'value'  => (string) $term->term_id,
                    'label'  => $name,
                    'prefix' => $prefix,
                    'depth'  => $depth,
                    'parent' => (int) $term->parent,
                ];
                $build_tree((int) $term->term_id, $depth + 1);
            }
        };

        if (isset($by_parent[0])) {
            $build_tree(0, 0);
        }

        // Bổ sung các term mồ côi hoặc không theo hierarchy
        $added_ids = array_column($options, 'value');
        foreach ($terms as $term) {
            if (!in_array((string) $term->term_id, $added_ids, true)) {
                $name = html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $options[] = [
                    'value'  => (string) $term->term_id,
                    'label'  => $name,
                    'prefix' => '',
                    'depth'  => 0,
                    'parent' => (int) $term->parent,
                ];
            }
        }

        return $options;
    }

    /**
     * Enqueue script data trên màn hình edit.php.
     */
    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'edit.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'edit') {
            return;
        }

        $post_type = $screen->post_type ?: 'post';
        $supported = $this->getSupportedPostTypes();

        if (!in_array($post_type, $supported, true) || !apply_filters('laca/list_inline/enabled', true, $post_type)) {
            return;
        }

        $fields = $this->getPostTypeFields($post_type);
        if (empty($fields)) {
            return;
        }

        $field_data = [];
        foreach ($fields as $key => $config) {
            $type = $config['type'] ?? 'meta';
            $field_data[$key] = [
                'type'     => $type,
                'label'    => $config['label'] ?? ucfirst($key),
                'taxonomy' => $config['taxonomy'] ?? $key,
                'options'  => [],
            ];

            if ($type === 'stock_status') {
                foreach ($this->getStockStatusOptions() as $val => $lbl) {
                    $field_data[$key]['options'][] = [
                        'value' => (string) $val,
                        'label' => (string) $lbl,
                    ];
                }
            } elseif ($type === 'taxonomy') {
                $taxonomy = $config['taxonomy'] ?? $key;
                $field_data[$key]['options'] = $this->getTaxonomyOptions($taxonomy);
            } elseif ($type === 'select') {
                foreach (($config['options'] ?? []) as $val => $lbl) {
                    $field_data[$key]['options'][] = [
                        'value' => (string) $val,
                        'label' => (string) $lbl,
                    ];
                }
            }
        }

        $this->currentScreenConfig = [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'postType' => $post_type,
            'fields'   => $field_data,
            'i18n'     => [
                'saving'    => __('Đang lưu...', 'laca'),
                'saved'     => __('Đã lưu!', 'laca'),
                'saveError' => __('Lưu thất bại. Vui lòng thử lại.', 'laca'),
                'empty'     => '—',
                'multiHelp' => __('Giữ Ctrl / Cmd để chọn nhiều danh mục', 'laca'),
            ],
        ];

        // Backup localize trên theme-admin-js-bundle
        wp_localize_script('theme-admin-js-bundle', 'lacaInlineTableConfig', $this->currentScreenConfig);
    }

    /**
     * In trực tiếp thẻ script cấu hình vào footer trang edit.php.
     * Đảm bảo 100% lacaInlineTableConfig luôn có sẵn kể cả khi wp_localize_script chạy lệch thứ tự.
     */
    public function outputConfigScript(): void
    {
        if (empty($this->currentScreenConfig)) {
            return;
        }

        echo '<script id="laca-inline-table-config">';
        echo 'window.lacaInlineTableConfig = ' . wp_json_encode($this->currentScreenConfig) . ';';
        echo '</script>';
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local') {
            echo '<!-- LACA_NUM_QUERIES: ' . get_num_queries() . ' -->' . "\n";
        }
    }

    /**
     * AJAX endpoint lấy options của 1 taxonomy khi cần tải động.
     */
    public function handleAjaxGetOptions(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '';
        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            wp_send_json_error(['message' => __('Taxonomy không tồn tại.', 'laca')]);
        }

        $options = $this->getTaxonomyOptions($taxonomy);
        wp_send_json_success(['options' => $options]);
    }

    /**
     * AJAX handler lưu giá trị khi sửa trực tiếp trên danh sách.
     */
    public function handleAjaxUpdate(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $field_key = isset($_POST['field_key']) ? sanitize_key(wp_unslash($_POST['field_key'])) : '';
        $raw_value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        if (!$post_id || empty($field_key)) {
            wp_send_json_error(['message' => __('Thiếu thông tin cập nhật.', 'laca')]);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Bạn không có quyền chỉnh sửa mục này.', 'laca')]);
        }

        $post_type = get_post_type($post_id);
        $fields    = $this->getPostTypeFields($post_type);

        if (!isset($fields[$field_key])) {
            wp_send_json_error(['message' => __('Trường dữ liệu không hợp lệ.', 'laca')]);
        }

        $config = $fields[$field_key];
        $type   = $config['type'] ?? 'meta';

        // 1. Cập nhật tồn kho WooCommerce
        if ($type === 'stock_status') {
            if (is_array($raw_value)) {
                $raw_value = reset($raw_value);
            }
            $status  = sanitize_key((string) $raw_value);
            $options = $this->getStockStatusOptions();

            if (!array_key_exists($status, $options)) {
                wp_send_json_error(['message' => __('Trạng thái kho không hợp lệ.', 'laca')]);
            }

            if (function_exists('wc_get_product')) {
                $product = wc_get_product($post_id);
                if ($product) {
                    $product->set_stock_status($status);
                    $product->save();
                }
            }
            update_post_meta($post_id, '_stock_status', $status);
        }
        // 2. Cập nhật Taxonomy
        elseif ($type === 'taxonomy') {
            $taxonomy = $config['taxonomy'] ?? $field_key;
            $term_ids = is_array($raw_value) ? $raw_value : explode(',', (string) $raw_value);
            $term_ids = array_values(array_filter(array_map('intval', $term_ids)));

            wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
        }
        // 3. Cập nhật Custom Meta / Carbon Fields
        else {
            if (is_array($raw_value)) {
                $sanitized = array_map('sanitize_text_field', $raw_value);
            } else {
                $sanitized = sanitize_text_field((string) $raw_value);
            }

            if ($field_key === 'menu_order') {
                wp_update_post([
                    'ID'         => $post_id,
                    'menu_order' => (int) $raw_value,
                ]);
            } else {
                if (function_exists('carbon_set_post_meta')) {
                    carbon_set_post_meta($post_id, $field_key, $sanitized);
                }
                update_post_meta($post_id, $field_key, $sanitized);
            }
        }

        wp_send_json_success([
            'value'   => $this->getRawValue($post_id, $field_key, $config),
            'display' => $this->getDisplayValue($post_id, $field_key, $config),
        ]);
    }

    /**
     * Xuất thẻ style và script sớm trong <head> của edit.php.
     * 1. Đặt class laca-table-loading để hiển thị skeleton.
     * 2. Ngăn chặn tuyệt đối hiện tượng co rúm cột / vỡ chữ theo chiều dọc trước khi JS chạy.
     */
    public function outputEarlyHeadScript(): void
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, $this->getSupportedPostTypes(), true) || !apply_filters('laca/list_inline/enabled', true, $screen->post_type)) {
            return;
        }

        echo '<script id="laca-table-early-loading">document.documentElement.classList.add("laca-table-loading");</script>' . "\n";
        echo '<style id="laca-table-early-prevent-fouc">
            html.edit-php,
            body.edit-php {
                overflow-x: hidden !important;
                overflow-x: clip !important;
            }
            body.edit-php #wpbody-content,
            body.edit-php .wrap {
                min-width: 0 !important;
                max-width: 100% !important;
                overflow-x: clip !important;
                box-sizing: border-box !important;
            }
            body.edit-php #posts-filter {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                overflow: visible !important;
                box-sizing: border-box !important;
            }
            body.edit-php .tablenav,
            body.edit-php .tablenav.top,
            body.edit-php .tablenav.bottom {
                overflow: visible !important;
            }
            body.edit-php .laca-table-scroll {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: auto !important;
                overflow-y: visible !important;
                -webkit-overflow-scrolling: touch !important;
                box-sizing: border-box !important;
                scrollbar-gutter: stable;
            }
            body.edit-php table.wp-list-table {
                width: max-content !important;
                min-width: 100% !important;
                table-layout: auto !important;
            }
            body.edit-php table.wp-list-table th,
            body.edit-php table.wp-list-table td {
                white-space: nowrap !important;
                word-break: normal !important;
                overflow-wrap: normal !important;
            }
            body.edit-php table.wp-list-table tbody tr:nth-child(odd) th,
            body.edit-php table.wp-list-table tbody tr:nth-child(odd) td {
                background-color: #ffffff !important;
            }
            body.edit-php table.wp-list-table tbody tr:nth-child(even) th,
            body.edit-php table.wp-list-table tbody tr:nth-child(even) td {
                background-color: #f8fafc !important;
            }
            html.laca-table-loading body.edit-php #posts-filter table.wp-list-table,
            html.laca-table-loading body.edit-php #posts-filter .laca-table-scroll {
                opacity: 0 !important;
                height: 0 !important;
                max-height: 0 !important;
                overflow: hidden !important;
                position: absolute !important;
                left: -9999px !important;
                pointer-events: none !important;
            }
        </style>' . "\n";
        echo '<script id="laca-table-early-failsafe">setTimeout(function(){ document.documentElement.classList.remove("laca-table-loading"); document.documentElement.classList.add("laca-table-ready"); }, 1500);</script>' . "\n";
    }

    /**
     * Xuất khung Skeleton Shimmer trước bảng thật trong edit.php.
     *
     * @param string $which 'top' hoặc 'bottom'
     */
    public function outputSkeletonPlaceholder(string $which): void
    {
        static $skeleton_outputted = false;
        if ($which !== 'top' || $skeleton_outputted) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, $this->getSupportedPostTypes(), true)) {
            return;
        }

        $skeleton_outputted = true;
        ?>
        <div class="laca-table-skeleton" id="laca-table-skeleton" aria-hidden="true">
            <div class="laca-skeleton-scroll">
                <div class="laca-skeleton-table">
                    <!-- Header -->
                    <div class="laca-skeleton-row is-header">
                        <div class="laca-skeleton-cell is-cb"><span class="laca-shimmer-box is-cb"></span></div>
                        <div class="laca-skeleton-cell is-thumb"><span class="laca-shimmer-box is-thumb"></span></div>
                        <div class="laca-skeleton-cell is-name"><span class="laca-shimmer-line is-w180"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w110"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w80"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w90"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w120"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w90"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w100"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w70"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w110"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w80"></span></div>
                    </div>
                    <!-- Body Rows (6 rows) -->
                    <?php for ($r = 0; $r < 6; $r++): ?>
                    <div class="laca-skeleton-row <?php echo ($r % 2 === 1) ? 'is-alt' : ''; ?>">
                        <div class="laca-skeleton-cell is-cb"><span class="laca-shimmer-box is-cb"></span></div>
                        <div class="laca-skeleton-cell is-thumb"><span class="laca-shimmer-box is-thumb"></span></div>
                        <div class="laca-skeleton-cell is-name">
                            <span class="laca-shimmer-line is-w220"></span>
                            <span class="laca-shimmer-line is-w140" style="margin-top:6px;"></span>
                        </div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w110"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w80"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w90"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w120"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w90"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w100"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w70"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w110"></span></div>
                        <div class="laca-skeleton-cell"><span class="laca-shimmer-line is-w80"></span></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
