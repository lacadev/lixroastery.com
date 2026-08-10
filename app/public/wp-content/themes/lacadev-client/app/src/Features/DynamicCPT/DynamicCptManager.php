<?php

namespace App\Features\DynamicCPT;

/**
 * DynamicCptManager
 *
 * Đọc cấu hình CPT từ wp_options và đăng ký chúng lên WordPress.
 * Phải được khởi tạo trên mọi request (cả frontend lẫn admin)
 * để URL routing / archive / single hoạt động đúng.
 */
class DynamicCptManager
{
    const OPTION_KEY = 'laca_dynamic_cpts';

    /**
     * Thư mục chứa meta files — luôn trỏ về child theme (hoặc parent nếu không có child).
     * Dùng method thay vì const để hỗ trợ get_stylesheet_directory() runtime.
     */
    
    public static function getMetaDir(): string
    {
        $dir = get_stylesheet_directory(); // filesystem path, e.g. .../lacadev-client-child/theme
        // WPEmerge child themes có style.css trong /theme subfolder.
        // Strip /theme để về child theme root thực sự.
        if (basename($dir) === 'theme') {
            $dir = dirname($dir);
        }
        return $dir . '/app/src/PostTypes/DynamicMeta';
    }

    public function __construct()
    {
        add_action('init', [$this, 'registerAll'], 5);

        // Carbon Fields fires carbon_fields_register_fields trên init priority 0.
        // Meta files phải được require_once TRƯỚC priority 0, tức là trên after_setup_theme.
        add_action('after_setup_theme', [$this, 'loadAllMetaFiles'], 9999);
    }

    /**
     * Lấy toàn bộ CPT config đã lưu.
     */
    public static function getAll(): array
    {
        $raw  = get_option(self::OPTION_KEY, '[]');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Lưu toàn bộ CPT config.
     */
    public static function saveAll(array $cpts): void
    {
        update_option(self::OPTION_KEY, wp_json_encode($cpts), false);
    }

    /**
     * Thêm cột ảnh đại diện vào admin list — y hệt AbstractPostType::showThumbnailOnList.
     */
    private function registerThumbnailColumn(string $slug): void
    {
        add_filter('manage_' . $slug . '_posts_columns', static function (array $cols): array {
            return insertArrayAtPosition($cols, ['featured_image' => 'Image'], 1);
        }, 9999);

        add_action('manage_' . $slug . '_posts_custom_column', static function (string $column, int $post_id): void {
            if ($column !== 'featured_image') {
                return;
            }

            $nonce        = wp_create_nonce('update_post_thumbnail');
            $nonce_attr   = esc_attr($nonce);
            $post_id_attr = absint($post_id);
            $thumbnail    = get_the_post_thumbnail_url($post_id, 'thumbnail');

            if ($thumbnail) {
                echo "<div class='thumbnail-wrap'>";
                echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
                echo "<img src='" . esc_url($thumbnail) . "' class='thumbnail-preview' alt='Thumbnail'/>";
                echo "</a>";
                echo "<a class='remove-thumbnail' href='javascript:void(0)' data-trigger-remove-thumbnail data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}' title='Remove thumbnail'>"
                   . "<svg viewBox='0 0 12 12'><path d='M11 1L1 11M1 1l10 10' stroke='currentColor' stroke-width='2' stroke-linecap='round'/></svg>"
                   . "</a>";
                echo "</div>";
            } else {
                echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
                echo "<div class='no-image-text'>Choose image</div>";
                echo "</a>";
            }
        }, 10, 2);
    }

    /**
     * Đăng ký tất cả dynamic CPT + taxonomy trên hook init.
     */
    public function registerAll(): void
    {
        foreach (self::getAll() as $cpt) {
            $slug = sanitize_key($cpt['slug'] ?? '');
            if (!$slug) {
                continue;
            }

            $singular = sanitize_text_field($cpt['singular']  ?? $slug);
            $plural   = sanitize_text_field($cpt['plural']    ?? $slug);
            $icon     = sanitize_text_field($cpt['menu_icon'] ?? 'dashicons-admin-post');
            $supports = array_map('sanitize_key', (array)($cpt['supports'] ?? ['title', 'editor', 'thumbnail']));
            $url_slug = sanitize_title($cpt['url_slug'] ?: $slug);

            register_extended_post_type(
                $slug,
                [
                    'public'            => true,
                    'show_ui'           => true,
                    'show_in_nav_menus' => true,
                    'show_in_menu'      => true,
                    'show_in_feed'      => true,
                    'menu_icon'         => $icon,
                    'supports'          => $supports,
                    'show_in_rest'      => true,
                    'has_archive'       => true,
                    'rewrite'           => ['with_front' => true],
                    'menu_position'     => 25,
                ],
                [
                    'singular' => $singular,
                    'plural'   => $plural,
                    'slug'     => $url_slug,
                ]
            );

            // Thêm cột thumbnail vào danh sách admin (giống AbstractPostType::showThumbnailOnList)
            if (\in_array('thumbnail', $supports, true)) {
                $this->registerThumbnailColumn($slug);
            }

            $taxonomies = $cpt['taxonomies'] ?? [];

            if (!empty($taxonomies['category'])) {
                register_taxonomy_for_object_type('category', $slug);
            }

            if (!empty($taxonomies['tag'])) {
                register_taxonomy_for_object_type('post_tag', $slug);
            }

            foreach ((array)($taxonomies['custom'] ?? []) as $tax) {
                $tax_slug = sanitize_key($tax['slug'] ?? '');
                if (!$tax_slug) {
                    continue;
                }

                register_extended_taxonomy(
                    $tax_slug,
                    [$slug],
                    [
                        'show_admin_column' => true,
                        'show_in_rest'      => true,
                        'allow_hierarchy'   => !empty($tax['hierarchical']),
                        'rest_base'         => $tax_slug,
                    ],
                    [
                        'singular' => sanitize_text_field($tax['singular'] ?? $tax_slug),
                        'plural'   => sanitize_text_field($tax['plural']   ?? $tax_slug),
                        'slug'     => $tax_slug,
                    ]
                );
            }
        }
    }

    /**
     * Load TẤT CẢ file *-meta.php trong thư mục DynamicMeta.
     * Áp dụng cho cả static CPT (project, service...) lẫn dynamic CPT.
     * Các file này tự hook vào carbon_fields_register_fields nên chỉ cần require_once.
     */
    public function loadAllMetaFiles(): void
    {
        $dir = self::getMetaDir();
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*-meta.php') ?: [] as $file) {
            require_once $file;
        }
    }
}
