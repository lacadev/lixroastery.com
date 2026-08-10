<?php
/**
 * Gutenberg Blocks Registration
 * 
 * Register ReactJS-based Gutenberg blocks
 * 
 * @package LacaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Option key for Gutenberg block category mapping.
 */
const LACADEV_BLOCK_CATEGORY_MAP_OPTION = 'lacadev_block_category_map';

/**
 * Normalize Gutenberg block name (namespace/block-name).
 */
function lacadev_normalize_block_name($block_name)
{
    $block_name = strtolower(trim((string) $block_name));
    return preg_replace('/[^a-z0-9_\\-\\/]/', '', $block_name);
}

/**
 * Get custom block categories used by this project.
 */
function lacadev_get_custom_block_categories($post = null)
{
    $default_base_category = [
        'slug' => 'lacadev-blocks',
        'title' => __('La Cà Blocks', 'laca'),
        'icon' => 'admin-customizer',
    ];

    /**
     * TOÀN BỘ category "theo dự án" gom về 1 chỗ DUY NHẤT ở đây để dễ quản
     * lý/cập nhật — kể cả 'pdn-blocks' (trước đây đăng ký riêng qua 1 filter
     * khác `lacadev_project_block_category_config`, nay gộp chung vào mảng
     * này cho gọn).
     */
    $site_categories = apply_filters('lacadev_site_block_categories', [
        [
            'slug' => 'site-lixroastery',
            'title' => __('LixRoastery', 'laca'),
            'icon' => 'coffee',
        ],
        [
            'slug' => 'site-phucdainam',
            'title' => __('Phúc Đại Nam', 'laca'),
            'icon' => 'screenoptions',
        ],
        [
            'slug' => 'site-nhakhoathienphuoc',
            'title' => __('Nha Khoa Thiện Phước', 'laca'),
            'icon' => 'store',
        ],
        [
            'slug' => 'site-unclassified',
            'title' => __('Site khác (chưa phân loại)', 'laca'),
            'icon' => 'category',
        ],
    ], $post);

    if (!is_array($site_categories)) {
        $site_categories = [];
    }

    // Tự phát hiện thêm category từ các thư mục bucket-theo-site vật lý —
    // slug lấy trực tiếp từ tên thư mục, không cần đăng ký tay ở mảng trên.
    // Nếu trùng slug với entry tay ở trên, bản tự phát hiện THẮNG (ưu tiên
    // dữ liệu tươi từ category.json đi theo thư mục bucket).
    $merged = [];
    foreach (array_merge([$default_base_category], $site_categories) as $category) {
        $slug = sanitize_key((string) ($category['slug'] ?? ''));
        if ($slug !== '') {
            $merged[$slug] = $category;
        }
    }
    foreach (lacadev_discover_bucket_categories() as $category) {
        $merged[$category['slug']] = $category;
    }

    return array_values($merged);
}

/**
 * Get block category map saved from Admin UI.
 */
function lacadev_get_block_category_map()
{
    $raw = get_option(LACADEV_BLOCK_CATEGORY_MAP_OPTION, []);
    if (!is_array($raw)) {
        return [];
    }

    $sanitized = [];
    foreach ($raw as $block_name => $slug) {
        $block_name = lacadev_normalize_block_name($block_name);
        $slug = sanitize_key((string) $slug);
        if ($block_name === '' || $slug === '') {
            continue;
        }
        $sanitized[$block_name] = $slug;
    }

    return $sanitized;
}

/**
 * Resolve final category for a block from admin mapping.
 */
function lacadev_resolve_block_category($block_name, $default_slug, $post = null)
{
    $default_slug = sanitize_key((string) $default_slug);
    if (empty($block_name)) {
        return $default_slug;
    }

    $available_categories = lacadev_get_custom_block_categories($post);
    $allowed_slugs = array_map(static function ($category) {
        return sanitize_key((string) ($category['slug'] ?? ''));
    }, $available_categories);

    $map = lacadev_get_block_category_map();
    $mapped_slug = isset($map[$block_name]) ? sanitize_key((string) $map[$block_name]) : '';

    if (!empty($mapped_slug) && in_array($mapped_slug, $allowed_slugs, true)) {
        return $mapped_slug;
    }

    return $default_slug;
}

/**
 * Parse block.json metadata.
 */
function lacadev_read_block_metadata($block_json_path)
{
    if (!file_exists($block_json_path)) {
        return [];
    }

    if (function_exists('wp_json_file_decode')) {
        $decoded = wp_json_file_decode($block_json_path, ['associative' => true]);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $content = file_get_contents($block_json_path);
    if ($content === false || $content === '') {
        return [];
    }

    // Remove UTF-8 BOM if present to avoid json_decode() returning null.
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $content = substr($content, 3);
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Quét 1 thư mục block-gutenberg, trả về relative path của từng block tìm
 * được — hỗ trợ song song 2 dạng: block nằm phẳng ngay dưới root (chưa phân
 * loại site) VÀ block nằm trong 1 thư mục "bucket" theo site (vd
 * nhakhoathienphuoc-blocks-site/), lồng đúng 1 cấp.
 *
 * Quy ước: 1 thư mục có block.json ngay bên trong = 1 block; 1 thư mục
 * KHÔNG có block.json bên trong = 1 bucket, quét thêm 1 cấp con để tìm
 * block (không hỗ trợ lồng sâu hơn 1 cấp).
 *
 * @return string[] Relative path (có thể chứa '/') tính từ $rootDir.
 */
function lacadev_scan_block_relative_paths(string $rootDir): array
{
    if (!is_dir($rootDir)) {
        return [];
    }

    $relative_paths = [];
    $entries = scandir($rootDir);

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $entry_path = "{$rootDir}/{$entry}";
        if (!is_dir($entry_path)) {
            continue;
        }

        if (file_exists("{$entry_path}/block.json")) {
            $relative_paths[] = $entry;
            continue;
        }

        $sub_entries = scandir($entry_path);
        if (!is_array($sub_entries)) {
            continue;
        }

        foreach ($sub_entries as $sub_entry) {
            if ($sub_entry === '.' || $sub_entry === '..') {
                continue;
            }

            $sub_entry_path = "{$entry_path}/{$sub_entry}";
            if (is_dir($sub_entry_path) && file_exists("{$sub_entry_path}/block.json")) {
                $relative_paths[] = "{$entry}/{$sub_entry}";
            }
        }
    }

    return $relative_paths;
}

/**
 * Đọc title/icon category cho 1 bucket từ file category.json trong chính
 * thư mục bucket (nếu có) — file này ĐI THEO thư mục khi bucket được
 * copy/đồng bộ sang site khác, nên site khác tự có tên đẹp không cần đăng
 * ký gì thêm. Không có file → tự suy title từ tên thư mục (bỏ hậu tố
 * -blocks-site/-site/-blocks, đổi -/_ thành khoảng trắng, viết hoa từng chữ).
 *
 * @return array{title:string,icon:string}
 */
function lacadev_get_bucket_category_info(string $bucketDir): array
{
    $configPath = $bucketDir . '/category.json';
    if (file_exists($configPath)) {
        $decoded = lacadev_read_block_metadata($configPath);
        if (!empty($decoded['title'])) {
            return [
                'title' => (string) $decoded['title'],
                'icon' => !empty($decoded['icon']) ? (string) $decoded['icon'] : 'category',
            ];
        }
    }

    $bucketName = basename($bucketDir);
    $pretty = preg_replace('/-(blocks-site|site|blocks)$/', '', $bucketName);
    $pretty = str_replace(['-', '_'], ' ', (string) $pretty);
    $pretty = ucwords(trim($pretty));

    return [
        'title' => $pretty !== '' ? $pretty : $bucketName,
        'icon' => 'category',
    ];
}

/**
 * Tự phát hiện category từ các thư mục "bucket" theo site nằm dưới
 * block-gutenberg/ của child theme — 1 thư mục KHÔNG có block.json trực
 * tiếp nhưng có ít nhất 1 block con = 1 bucket. Slug = tên thư mục bucket
 * (sanitize_key), title/icon lấy từ lacadev_get_bucket_category_info(). Nhờ
 * vậy chỉ cần git mv 1 block vào 1 thư mục mới là nó tự có category, không
 * cần đăng ký gì thêm ở đây.
 *
 * @return array[] Mỗi entry: ['slug' => ..., 'title' => ..., 'icon' => ...]
 */
function lacadev_discover_bucket_categories(): array
{
    $childBlocksDir = dirname(get_stylesheet_directory()) . '/block-gutenberg';
    if (!is_dir($childBlocksDir)) {
        return [];
    }

    $entries = scandir($childBlocksDir);
    if (!is_array($entries)) {
        return [];
    }

    $categories = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $entryPath = "{$childBlocksDir}/{$entry}";
        if (!is_dir($entryPath) || file_exists("{$entryPath}/block.json")) {
            continue; // không phải bucket (là block phẳng hoặc không phải thư mục)
        }

        $hasBlockChild = false;
        foreach ((scandir($entryPath) ?: []) as $subEntry) {
            if ($subEntry === '.' || $subEntry === '..') {
                continue;
            }
            if (is_dir("{$entryPath}/{$subEntry}") && file_exists("{$entryPath}/{$subEntry}/block.json")) {
                $hasBlockChild = true;
                break;
            }
        }

        if (!$hasBlockChild) {
            continue;
        }

        $info = lacadev_get_bucket_category_info($entryPath);
        $categories[] = [
            'slug' => sanitize_key($entry),
            'title' => $info['title'],
            'icon' => $info['icon'],
        ];
    }

    return $categories;
}

/**
 * Safe block registration — prevents duplicate "already registered" notices.
 *
 * WP_Block_Type_Registry::register() emits _doing_it_wrong() when a block is
 * already registered.  When WP_DEBUG + WP_DEBUG_DISPLAY are on, the HTML
 * output breaks all subsequent header() calls ("headers already sent").
 *
 * This wrapper silently skips registration when the block already exists,
 * guaranteeing no notice is ever emitted.
 *
 * @param string $block_json  Absolute path to block.json.
 * @param array  $block_args  Extra args forwarded to register_block_type_from_metadata().
 * @return \WP_Block_Type|false
 */
function lacadev_safe_register_block(string $block_json, array $block_args = [])
{
    $metadata = lacadev_read_block_metadata($block_json);
    $name = isset($metadata['name']) ? lacadev_normalize_block_name($metadata['name']) : '';

    if ($name === '') {
        return false;
    }

    $registry = \WP_Block_Type_Registry::get_instance();
    if ($registry->is_registered($name)) {
        return $registry->get_registered($name);
    }

    return register_block_type_from_metadata($block_json, $block_args);
}

/**
 * Collect blocks from parent and synced child directories for admin mapping.
 */
function lacadev_collect_blocks_for_category_mapping()
{
    $blocks = [];

    $sources = [];
    if (defined('APP_DIR')) {
        $sources[] = [
            'dir' => trailingslashit(APP_DIR) . 'block-gutenberg',
            'source' => 'project',
        ];
    }

    $sources[] = [
        'dir' => dirname(get_stylesheet_directory()) . '/block-gutenberg',
        'source' => 'synced',
    ];

    foreach ($sources as $source_config) {
        $dir = $source_config['dir'];
        $source = $source_config['source'];

        if (!is_dir($dir)) {
            continue;
        }

        if ($source === 'synced') {
            $entries = lacadev_scan_block_relative_paths($dir);
        } else {
            $entries = scandir($dir);
            if (!is_array($entries)) {
                continue;
            }
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.js' || $entry === 'debug.js') {
                continue;
            }

            $block_json = $dir . '/' . $entry . '/block.json';
            $metadata = lacadev_read_block_metadata($block_json);
            if (empty($metadata)) {
                continue;
            }

            $block_name = isset($metadata['name']) ? lacadev_normalize_block_name($metadata['name']) : '';
            if ($block_name === '') {
                continue;
            }

            if ($source === 'synced' && strpos($entry, '/') !== false) {
                $default_slug = sanitize_key(dirname($entry));
            } elseif ($source === 'synced') {
                $default_slug = !empty($metadata['category']) ? sanitize_key((string) $metadata['category']) : 'site-unclassified';
            } else {
                $default_slug = 'pdn-blocks';
            }
            $title = !empty($metadata['title']) ? (string) $metadata['title'] : $block_name;

            if (!isset($blocks[$block_name])) {
                $blocks[$block_name] = [
                    'name' => $block_name,
                    'title' => $title,
                    'source' => $source,
                    'default_slug' => $default_slug,
                ];
            } elseif ($source === 'synced') {
                // Prefer synced source metadata when the same block exists in both places.
                $blocks[$block_name]['source'] = 'synced';
                $blocks[$block_name]['default_slug'] = $default_slug;
            }
        }
    }

    ksort($blocks);
    return $blocks;
}

/**
 * Register admin page under Laca Admin for block-category mapping.
 */
function lacadev_register_block_category_mapping_admin_page()
{
    add_submenu_page(
        'laca-admin',
        __('Block Categories', 'laca'),
        __('Block Categories', 'laca'),
        'manage_options',
        'lacadev-block-categories',
        'lacadev_render_block_category_mapping_admin_page'
    );
}
add_action('admin_menu', 'lacadev_register_block_category_mapping_admin_page', 99);

/**
 * Render block-category mapping admin page.
 */
function lacadev_render_block_category_mapping_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';
    if (isset($_POST['lacadev_save_block_category_map'])) {
        check_admin_referer('lacadev_save_block_category_map');

        $raw_map = isset($_POST['lacadev_block_category_map']) && is_array($_POST['lacadev_block_category_map'])
            ? wp_unslash($_POST['lacadev_block_category_map'])
            : [];

        $blocks = lacadev_collect_blocks_for_category_mapping();
        $valid_categories = array_map(static function ($category) {
            return sanitize_key((string) ($category['slug'] ?? ''));
        }, lacadev_get_custom_block_categories());

        $new_map = [];
        foreach ($blocks as $block_name => $block_data) {
            $selected_slug = isset($raw_map[$block_name]) ? sanitize_key((string) $raw_map[$block_name]) : '';
            if ($selected_slug === '') {
                continue;
            }
            if (!in_array($selected_slug, $valid_categories, true)) {
                continue;
            }
            if ($selected_slug === $block_data['default_slug']) {
                continue;
            }
            $new_map[$block_name] = $selected_slug;
        }

        update_option(LACADEV_BLOCK_CATEGORY_MAP_OPTION, $new_map);
        $notice = __('Đã lưu mapping block category.', 'laca');
    }

    $categories = lacadev_get_custom_block_categories();
    $category_labels = [];
    foreach ($categories as $category) {
        $slug = sanitize_key((string) ($category['slug'] ?? ''));
        $title = (string) ($category['title'] ?? $slug);
        if ($slug !== '') {
            $category_labels[$slug] = $title;
        }
    }

    $map = lacadev_get_block_category_map();
    $blocks = lacadev_collect_blocks_for_category_mapping();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Block Categories', 'laca'); ?></h1>
        <p><?php echo esc_html__('Chọn category cho từng block. Nếu để "Mặc định", hệ thống sẽ tự gán theo nguồn block.', 'laca'); ?>
        </p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><?php echo esc_html__('Block local của dự án: mặc định vào PĐN Blocks.', 'laca'); ?></li>
            <li><?php echo esc_html__('Block sync từ lacadev: mặc định vào La Cà Blocks.', 'laca'); ?></li>
        </ul>

        <?php if (!empty($notice)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($notice); ?></p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('lacadev_save_block_category_map'); ?>
            <table class="widefat striped" style="max-width: 1000px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Block', 'laca'); ?></th>
                        <th><?php echo esc_html__('Nguồn', 'laca'); ?></th>
                        <th><?php echo esc_html__('Mặc định', 'laca'); ?></th>
                        <th><?php echo esc_html__('Category áp dụng', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blocks)): ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('Chưa tìm thấy block nào.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($blocks as $block): ?>
                            <?php
                            $block_name = $block['name'];
                            $default_slug = $block['default_slug'];
                            $selected_slug = isset($map[$block_name]) ? $map[$block_name] : '';
                            $source_label = $block['source'] === 'synced' ? __('Sync từ lacadev', 'laca') : __('Local dự án', 'laca');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($block['title']); ?></strong><br>
                                    <code><?php echo esc_html($block_name); ?></code>
                                </td>
                                <td><?php echo esc_html($source_label); ?></td>
                                <td><?php echo esc_html($category_labels[$default_slug] ?? $default_slug); ?></td>
                                <td>
                                    <select name="<?php echo esc_attr('lacadev_block_category_map[' . $block_name . ']'); ?>">
                                        <option value=""><?php echo esc_html__('Mặc định', 'laca'); ?></option>
                                        <?php foreach ($category_labels as $slug => $label): ?>
                                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_slug, $slug); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p style="margin-top: 16px;">
                <button type="submit" name="lacadev_save_block_category_map" class="button button-primary">
                    <?php echo esc_html__('Lưu cấu hình', 'laca'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Register Gutenberg blocks scripts and styles
 */
function lacadev_register_gutenberg_blocks_assets()
{
    // Theme root is one level above `theme/` folder (see APP_DIR constant in `theme/functions.php`)
    if (!defined('APP_DIR')) {
        return;
    }

    // Legacy/global bundle (only used for blocks that don't have their own build folder)
    $asset_file = trailingslashit(APP_DIR) . 'dist/gutenberg/index.asset.php';

    if (!file_exists($asset_file)) {
        return;
    }

    $asset = require $asset_file;

    // URL root of theme (one level above `theme/`)
    $theme_root_uri = get_template_directory_uri();
    $theme_root_uri = preg_replace('#/theme/?$#', '', $theme_root_uri);

    // Register block editor script
    wp_register_script(
        'lacadev-gutenberg-blocks',
        $theme_root_uri . '/dist/gutenberg/index.js',
        $asset['dependencies'],
        $asset['version'],
        false
    );
}
add_action('init', 'lacadev_register_gutenberg_blocks_assets', 5);

/**
 * Register all custom blocks
 */
function lacadev_register_custom_blocks()
{
    // First, register assets
    lacadev_register_gutenberg_blocks_assets();

    // Get all block directories - use file path, not URL
    if (!defined('APP_DIR')) {
        return;
    }

    $blocks_dir = trailingslashit(APP_DIR) . 'block-gutenberg';
    // Luôn dùng get_template_directory_uri() để trỏ về parent theme URI,
    // đảm bảo asset link đúng kể cả khi dùng child theme.
    $theme_root_uri = get_template_directory_uri();
    $theme_root_uri = preg_replace('#/theme/?$#', '', $theme_root_uri);

    if (!is_dir($blocks_dir)) {
        return;
    }

    $blocks = scandir($blocks_dir);
    $registered_count = 0;

    foreach ($blocks as $block) {
        // Skip . and .. and index.js
        if ($block === '.' || $block === '..' || $block === 'index.js' || $block === 'debug.js') {
            continue;
        }

        $block_json = $blocks_dir . '/' . $block . '/block.json';

        if (file_exists($block_json)) {
            $metadata = lacadev_read_block_metadata($block_json);
            $block_name = isset($metadata['name']) ? lacadev_normalize_block_name($metadata['name']) : '';

            if ($block_name === '') {
                continue;
            }

            // Check if block has render.php for dynamic rendering
            $render_php = $blocks_dir . '/' . $block . '/render.php';

            // Check if block has a specific build folder (self-contained block)
            $has_individual_build = is_dir($blocks_dir . '/' . $block . '/build');

            $block_args = [];

            if ($has_individual_build) {
                $asset_file = $blocks_dir . '/' . $block . '/build/index.asset.php';
                $asset = [
                    'dependencies' => [],
                    'version' => null,
                ];

                if (file_exists($asset_file)) {
                    $asset = require $asset_file;
                }

                $editor_script_handle = 'block-' . $block . '-editor';
                $editor_style_handle = 'block-' . $block . '-editor';
                $style_handle = 'block-' . $block;

                wp_register_script(
                    $editor_script_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/index.js',
                    $asset['dependencies'] ?? [],
                    $asset['version'] ?? null
                );

                wp_register_style(
                    $editor_style_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/index.css',
                    [],
                    $asset['version'] ?? null
                );

                wp_register_style(
                    $style_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/style-index.css',
                    [],
                    $asset['version'] ?? null
                );

                $block_args['editor_script'] = $editor_script_handle;
                $block_args['editor_style'] = $editor_style_handle;
                $block_args['style'] = $style_handle;
            } else {
                // Backward compatibility for blocks that haven't been refactored
                $block_args['editor_script'] = 'lacadev-gutenberg-blocks';
            }

            // Add render callback if render.php exists
            if (file_exists($render_php)) {
                $block_args['render_callback'] = function ($attributes, $content) use ($render_php) {
                    ob_start();
                    require $render_php;
                    return ob_get_clean();
                };
            }

            $declared_category = !empty($metadata['category']) ? sanitize_key((string) $metadata['category']) : 'pdn-blocks';
            $block_args['category'] = lacadev_resolve_block_category($block_name, $declared_category);

            // Use safe wrapper — prevents "already registered" notices that break headers.
            $result = lacadev_safe_register_block($block_json, $block_args);

            if ($result) {
                $registered_count++;
            }
        }
    }

}
add_action('init', 'lacadev_register_custom_blocks', 10);

/**
 * Register custom block categories with WordPress core (dùng chung ĐÚNG 1
 * nguồn với lacadev_get_custom_block_categories() — trang admin "Block
 * Categories" và trình chèn block trong editor phải luôn thấy CÙNG 1 danh
 * sách category, không định nghĩa lại ở 2 nơi để tránh lệch nhau).
 */
function lacadev_register_block_category($categories, $post)
{
    return array_merge(
        lacadev_get_custom_block_categories($post),
        $categories
    );
}
add_filter('block_categories_all', 'lacadev_register_block_category', 10, 2);

/**
 * Đăng ký các blocks đã được sync về từ lacadev server.
 *
 * BlockSyncReceiver ghi files vào lacadev-client-child/block-gutenberg/{block_name}/
 * (child theme) để tách biệt với parent theme — update lacadev-client sẽ không xoá blocks đã sync.
 * Hàm này chạy sau priority 10 để không xảy ra conflict với parent theme register.
 */
function lacadev_child_register_synced_blocks(): void
{
    // Blocks được BlockSyncReceiver ghi vào child theme để tách biệt với parent theme.
    // get_stylesheet_directory() → .../lacadev-client-child/theme (khi child active)
    // dirname() → .../lacadev-client-child/
    $childBlocksDir = dirname(get_stylesheet_directory()) . '/block-gutenberg';

    if (!is_dir($childBlocksDir)) {
        return;
    }

    // URI tương ứng với child theme root
    // dirname() on a URL strips the double slash (http:// -> http:/), so we use a safer method
    $childThemeUri = get_stylesheet_directory_uri();
    $childThemeUri = preg_replace('#/theme/?$#', '', $childThemeUri);

    foreach (lacadev_scan_block_relative_paths($childBlocksDir) as $blockRelPath) {
        $blockJson = "{$childBlocksDir}/{$blockRelPath}/block.json";
        if (!file_exists($blockJson)) {
            continue;
        }

        $metadata = lacadev_read_block_metadata($blockJson);
        $registered_block_name = isset($metadata['name']) ? lacadev_normalize_block_name($metadata['name']) : '';

        if ($registered_block_name === '') {
            continue;
        }

        $registry = \WP_Block_Type_Registry::get_instance();

        // Skip when this block has already been registered by parent/local registration pass.
        if ($registry->is_registered($registered_block_name)) {
            continue;
        }

        // Tên thư mục CỦA CHÍNH BLOCK (không kèm bucket site) — dùng cho tên
        // handle, theo đúng quy ước block-block-{ten-thu-muc}-editor.
        $blockSlug = basename($blockRelPath);

        $blockArgs = [];
        $renderPhp = "{$childBlocksDir}/{$blockRelPath}/render.php";
        $hasBuild = is_dir("{$childBlocksDir}/{$blockRelPath}/build");

        if ($hasBuild) {
            $assetFile = "{$childBlocksDir}/{$blockRelPath}/build/index.asset.php";
            $asset = file_exists($assetFile) ? require $assetFile : ['dependencies' => [], 'version' => null];

            $scriptHandle = 'block-' . $blockSlug . '-editor';
            $styleHandle = 'block-' . $blockSlug;

            $indexJs = "{$childThemeUri}/block-gutenberg/{$blockRelPath}/build/index.js";
            $indexCss = "{$childThemeUri}/block-gutenberg/{$blockRelPath}/build/index.css";
            $styleCss = "{$childThemeUri}/block-gutenberg/{$blockRelPath}/build/style-index.css";

            wp_register_script($scriptHandle, $indexJs, $asset['dependencies'] ?? [], $asset['version'] ?? null);

            if (file_exists("{$childBlocksDir}/{$blockRelPath}/build/index.css")) {
                wp_register_style($scriptHandle, $indexCss, [], $asset['version'] ?? null);
                $blockArgs['editor_style'] = $scriptHandle;
            }

            if (file_exists("{$childBlocksDir}/{$blockRelPath}/build/style-index.css")) {
                wp_register_style($styleHandle, $styleCss, [], $asset['version'] ?? null);
                $blockArgs['style'] = $styleHandle;
            }

            $blockArgs['editor_script'] = $scriptHandle;
        }

        // Block nằm trong 1 bucket theo site → category = tên thư mục bucket
        // (tự động, xem lacadev_discover_bucket_categories()). Block phẳng
        // (chưa phân loại site) → giữ hành vi cũ: đọc field "category" khai
        // báo tay trong block.json, không có thì rơi về site-unclassified.
        if (strpos($blockRelPath, '/') !== false) {
            $declaredCategory = sanitize_key(dirname($blockRelPath));
        } else {
            $declaredCategory = !empty($metadata['category']) ? sanitize_key((string) $metadata['category']) : 'site-unclassified';
        }
        $blockArgs['category'] = lacadev_resolve_block_category($registered_block_name, $declaredCategory);

        if (file_exists($renderPhp)) {
            $blockArgs['render_callback'] = static function ($attributes, $content) use ($renderPhp) {
                ob_start();
                require $renderPhp;
                return ob_get_clean();
            };
        }

        // Use safe wrapper — prevents "already registered" notices that break headers.
        lacadev_safe_register_block($blockJson, $blockArgs);
    }
}
add_action('init', 'lacadev_child_register_synced_blocks', 15);

/**
 * Expose `image_url` on all public taxonomy terms via the REST API.
 *
 * Supports:
 *  - WooCommerce product_cat (thumbnail_id term meta)
 *  - ACF `term_image` field
 *  - Custom `term_image_url` term meta
 *
 * Usage in edit.js: `term.image_url`
 */
function lacadev_register_term_image_rest_field()
{
    $taxonomies = get_taxonomies(['public' => true], 'names');

    foreach ($taxonomies as $taxonomy) {
        register_rest_field(
            $taxonomy,
            'image_url',
            [
                'get_callback' => static function ($term_data) {
                    $tid = absint($term_data['id']);
                    $tax = $term_data['taxonomy'] ?? '';

                    // ACF
                    if (function_exists('get_field')) {
                        $acf = get_field('term_image', $tax . '_' . $tid);
                        if ($acf) {
                            return is_array($acf) ? $acf['url'] : $acf;
                        }
                    }

                    // Custom meta
                    $url = get_term_meta($tid, 'term_image_url', true);
                    if ($url) {
                        return $url;
                    }

                    // WooCommerce / taxonomy image plugins (thumbnail_id)
                    $thumb_id = get_term_meta($tid, 'thumbnail_id', true);
                    if ($thumb_id) {
                        return wp_get_attachment_image_url(absint($thumb_id), 'large') ?: '';
                    }

                    return '';
                },
                'schema' => [
                    'description' => 'Term image URL (WooCommerce, ACF, or custom meta)',
                    'type' => 'string',
                    'readonly' => true,
                ],
            ]
        );
    }
}
add_action('rest_api_init', 'lacadev_register_term_image_rest_field');

