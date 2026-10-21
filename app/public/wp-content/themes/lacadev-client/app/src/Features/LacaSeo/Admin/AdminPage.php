<?php

namespace App\Features\LacaSeo\Admin;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\GeneralFields;
use App\Features\LacaSeo\Fields\TitlesFields;

defined('ABSPATH') || exit;

/**
 * Main admin router and page controller for admin.php?page=laca-seo.
 */
class AdminPage
{
    public const MENU_SLUG = 'laca-seo';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_laca_seo_toggle_module', [$this, 'ajaxToggleModule']);
        add_action('wp_ajax_laca_seo_preview_vars', [$this, 'ajaxPreviewVars']);
    }

    /**
     * Register SEO menu entry under Laca Theme group.
     */
    public function registerMenu(): void
    {
        add_submenu_page(
            'laca-admin',
            'Laca SEO',
            'SEO',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Enqueue dedicated admin assets only on Laca SEO pages.
     */
    public function enqueueAssets(string $hook): void
    {
        if (empty($_GET['page']) || $_GET['page'] !== self::MENU_SLUG) {
            return;
        }

        wp_enqueue_media();

        // Enqueue bundled laca-seo styles and scripts from child/parent dist
        $theme_root_uri = dirname(get_stylesheet_directory_uri());
        // filemtime để trình duyệt nhận bản build mới ngay (quy ước cache-busting của theme).
        $dist_dir = dirname(get_stylesheet_directory()) . '/dist/';

        if (file_exists(dirname(get_stylesheet_directory()) . '/dist/styles/laca-seo.css')) {
            wp_enqueue_style(
                'laca-seo-admin',
                $theme_root_uri . '/dist/styles/laca-seo.css',
                ['dashicons'],
                (string) filemtime($dist_dir . 'styles/laca-seo.css')
            );
        }

        if (file_exists(dirname(get_stylesheet_directory()) . '/dist/laca-seo.js')) {
            wp_enqueue_script(
                'laca-seo-admin',
                $theme_root_uri . '/dist/laca-seo.js',
                ['wp-i18n'],
                (string) filemtime($dist_dir . 'laca-seo.js'),
                true
            );

            wp_localize_script('laca-seo-admin', 'lacaSeoData', [
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('laca_seo_admin_nonce'),
                'variables' => \App\Features\LacaSeo\Core\Variables::getAvailableList(),
                'i18n'      => [
                    'confirmReset' => 'Bạn có chắc chắn muốn khôi phục mặc định toàn bộ cài đặt trong tab này không?',
                    'saved'        => 'Đã lưu cài đặt thành công.',
                    'copied'       => 'Đã sao chép vào bộ nhớ tạm!',
                ],
            ]);
        }
    }

    /**
     * Render the main Laca SEO page layout with navigation tabs.
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'lacadev'));
        }

        $view = sanitize_key($_GET['view'] ?? 'dashboard');

        $views = [
            'dashboard'     => ['label' => 'Bảng điều khiển', 'icon' => 'dashicons-dashboard'],
            'general'       => ['label' => 'Cài đặt chung', 'icon' => 'dashicons-admin-settings'],
            'titles'        => ['label' => 'Tiêu đề & Thẻ Meta', 'icon' => 'dashicons-heading'],
            'sitemap'       => ['label' => 'Sơ đồ trang XML (Sắp có)', 'icon' => 'dashicons-networking', 'badge' => 'Phase 3'],
            'import-export' => ['label' => 'Sao lưu & Nhập/Xuất', 'icon' => 'dashicons-migrate'],
            'guide'         => ['label' => 'Hướng dẫn tối ưu SEO', 'icon' => 'dashicons-book'],
        ];

        ?>
        <div class="wrap laca-seo-wrap">
            <h1 class="wp-heading-inline" style="display:none;">Laca SEO</h1>
            <hr class="wp-header-end" style="display:none;">

            <header class="laca-seo-header">
                <div class="laca-seo-header__branding">
                    <span class="dashicons dashicons-search laca-seo-header__logo" aria-hidden="true"></span>
                    <h2 class="laca-seo-header__title">Laca SEO</h2>
                    <span class="laca-seo-header__badge">Professional</span>
                </div>
                <div class="laca-seo-header__actions">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'guide'], admin_url('admin.php'))); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-book"></span> Hướng dẫn tối ưu
                    </a>
                </div>
            </header>

            <div class="laca-seo-notices">
                <?php
                Notices::render();

                if (isset($_GET['updated'])) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cài đặt SEO đã được lưu thành công.', 'lacadev') . '</p></div>';
                }
                if (isset($_GET['reset'])) {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Đã khôi phục cài đặt mặc định của tab này.', 'lacadev') . '</p></div>';
                }
                ?>
            </div>

            <nav class="laca-seo-nav" aria-label="Laca SEO Navigation">
                <ul class="laca-seo-nav__list">
                    <?php foreach ($views as $view_key => $view_info): ?>
                        <li class="laca-seo-nav__item">
                            <a
                                href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => $view_key], admin_url('admin.php'))); ?>"
                                class="laca-seo-nav__link<?php echo ($view === $view_key) ? ' is-active' : ''; ?>"
                            >
                                <span class="dashicons <?php echo esc_attr($view_info['icon']); ?>" aria-hidden="true"></span>
                                <span class="laca-seo-nav__text"><?php echo esc_html($view_info['label']); ?></span>
                                <?php if (!empty($view_info['badge'])): ?>
                                    <span class="laca-seo-nav__badge"><?php echo esc_html($view_info['badge']); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <main class="laca-seo-content">
                <?php
                switch ($view) {
                    case 'general':
                        include __DIR__ . '/views/general.php';
                        break;
                    case 'titles':
                        include __DIR__ . '/views/titles.php';
                        break;
                    case 'import-export':
                        include __DIR__ . '/views/import-export.php';
                        break;
                    case 'guide':
                        include __DIR__ . '/views/guide.php';
                        break;
                    case 'sitemap':
                        echo '<div class="laca-seo-card"><h2 class="laca-seo-card__title">Sơ đồ trang XML (Sitemap)</h2><p>Tính năng Sitemap XML chuyên sâu đang được triển khai hoàn thiện trong Phase 3.</p></div>';
                        break;
                    case 'dashboard':
                    default:
                        include __DIR__ . '/views/dashboard.php';
                        break;
                }
                ?>
            </main>
        </div>
        <?php
    }

    /**
     * AJAX handler to toggle module on/off.
     */
    public function ajaxToggleModule(): void
    {
        check_ajax_referer('laca_seo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $module = sanitize_key($_POST['module'] ?? '');
        $status = sanitize_key($_POST['status'] ?? '');

        if (!isset(Modules::DEFINITIONS[$module])) {
            wp_send_json_error(['message' => 'Module invalid']);
        }

        $active = Modules::getActive();

        if ($status === 'on') {
            if (!in_array($module, $active, true)) {
                $active[] = $module;
            }
        } else {
            $active = array_diff($active, [$module]);
        }

        Modules::setActive($active);
        wp_send_json_success(['active' => Modules::getActive()]);
    }

    /**
     * AJAX handler to preview rendered variable output for a sample post.
     */
    public function ajaxPreviewVars(): void
    {
        check_ajax_referer('laca_seo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền.'], 403);
        }

        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
        $field_id = sanitize_key(wp_unslash($_POST['field_id'] ?? ''));

        $sample = $this->resolvePreviewObject($field_id);
        if ($sample === false) {
            wp_send_json_success(['preview' => '', 'empty' => true]);
            return;
        }

        $rendered = \App\Features\LacaSeo\Core\Variables::replace($template, $sample);

        wp_send_json_success(['preview' => wp_strip_all_tags($rendered), 'empty' => false]);
    }

    /**
     * Đối tượng mẫu cho preview theo id field: pt_{type}_* → bài mới nhất của type đó,
     * tax_{tax}_* → term nhiều bài nhất, author_* → tác giả bài mới nhất, còn lại → không object.
     *
     * @return \WP_Post|\WP_Term|\WP_User|null|false false = ngữ cảnh cần object nhưng site chưa có nội dung
     */
    private function resolvePreviewObject(string $field_id)
    {
        // So khớp slug dài nhất trước: post type/taxonomy có thể chứa "_" (vd variety_cat).
        $match = static function (string $prefix, array $slugs) use ($field_id): ?string {
            usort($slugs, static fn($a, $b) => strlen($b) - strlen($a));
            foreach ($slugs as $slug) {
                if (str_starts_with($field_id, $prefix . $slug . '_')) {
                    return $slug;
                }
            }
            return null;
        };

        $post_type = $match('pt_', array_keys(\App\Features\LacaSeo\Core\Helper::getAccessiblePostTypes()));
        if ($post_type !== null) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'posts_per_page' => 1,
                'post_status'    => $post_type === 'attachment' ? 'inherit' : 'publish',
                'no_found_rows'  => true,
            ]);
            return $posts[0] ?? false;
        }

        $taxonomy = $match('tax_', array_keys(\App\Features\LacaSeo\Core\Helper::getAccessibleTaxonomies()));
        if ($taxonomy === null && preg_match('/^remove_(.+)_snippet_data$/', $field_id, $m)) {
            $taxonomy = $m[1];
        }
        if ($taxonomy !== null) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'number'     => 1,
                'orderby'    => 'count',
                'order'      => 'DESC',
                'hide_empty' => false,
            ]);
            return (!is_wp_error($terms) && !empty($terms)) ? $terms[0] : false;
        }

        if (str_starts_with($field_id, 'author_')) {
            $posts = get_posts(['posts_per_page' => 1, 'post_status' => 'publish', 'no_found_rows' => true]);
            return !empty($posts) ? get_userdata((int) $posts[0]->post_author) ?: false : false;
        }

        // Homepage, misc, breadcrumbs, image format…: biến cấp site, không cần object.
        return null;
    }
}
