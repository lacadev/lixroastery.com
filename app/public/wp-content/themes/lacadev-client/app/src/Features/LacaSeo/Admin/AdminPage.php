<?php

namespace App\Features\LacaSeo\Admin;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\GeneralFields;
use App\Features\LacaSeo\Fields\TitlesFields;
use App\Features\LacaSeo\Modules\RoleManager\CapabilityManager;

defined('ABSPATH') || exit;

/**
 * Main admin router and page controller for admin.php?page=laca-seo.
 */
class AdminPage
{
    public const MENU_SLUG = 'laca-seo';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 9);
        add_action('admin_menu', [$this, 'ensureDashboardFirstInMenu'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('current_screen', [$this, 'suppressForeignNotices']);
        add_action('wp_ajax_laca_seo_toggle_module', [$this, 'ajaxToggleModule']);
        add_action('wp_ajax_laca_seo_preview_vars', [$this, 'ajaxPreviewVars']);
    }

    /**
     * Suppress foreign third-party admin notices on Laca SEO admin pages.
     */
    public function suppressForeignNotices(\WP_Screen $screen): void
    {
        if (isset($_GET['page']) && $_GET['page'] === self::MENU_SLUG) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            remove_all_actions('network_admin_notices');
            remove_all_actions('user_admin_notices');
        }
    }

    /**
     * Register Laca SEO as a top-level menu entry between Laca Admin (3) and Laca Theme (3.1).
     */
    public function registerMenu(): void
    {
        add_menu_page(
            'Laca SEO',
            'Laca SEO',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage'],
            'dashicons-search',
            '3.05'
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Laca SEO',
            'Bảng điều khiển',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Ensure the Laca SEO dashboard ("Bảng điều khiển") is always the first submenu item,
     * so clicking the top-level "Laca SEO" menu item opens the dashboard directly.
     */
    public function ensureDashboardFirstInMenu(): void
    {
        global $submenu;
        if (empty($submenu[self::MENU_SLUG]) || !is_array($submenu[self::MENU_SLUG])) {
            return;
        }

        $dashboardIdx = null;
        foreach ($submenu[self::MENU_SLUG] as $idx => $item) {
            if (isset($item[2]) && $item[2] === self::MENU_SLUG) {
                $dashboardIdx = $idx;
                break;
            }
        }

        if ($dashboardIdx !== null && $dashboardIdx !== 0) {
            $dashItem = $submenu[self::MENU_SLUG][$dashboardIdx];
            unset($submenu[self::MENU_SLUG][$dashboardIdx]);
            array_unshift($submenu[self::MENU_SLUG], $dashItem);
            $submenu[self::MENU_SLUG] = array_values($submenu[self::MENU_SLUG]);
        }
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

        $nav_groups = [
            'overview' => [
                'title' => 'Tổng quan',
                'items' => [
                    'dashboard' => ['label' => 'Bảng điều khiển', 'icon' => 'dashicons-dashboard'],
                ],
            ],
            'settings' => [
                'title' => 'Cài đặt',
                'items' => [
                    'general' => ['label' => 'Cài đặt chung', 'icon' => 'dashicons-admin-settings'],
                    'titles'  => ['label' => 'Tiêu đề & Thẻ Meta', 'icon' => 'dashicons-heading'],
                    'sitemap' => ['label' => 'Sơ đồ trang XML', 'icon' => 'dashicons-networking'],
                ],
            ],
            'tools' => [
                'title' => 'Công cụ',
                'items' => [
                    'instant-indexing' => ['label' => 'Index tức thì (IndexNow)', 'icon' => 'dashicons-update'],
                    'redirections'     => ['label' => 'Chuyển hướng (Redirections)', 'icon' => 'dashicons-randomize'],
                    '404-monitor'      => ['label' => 'Theo dõi 404 (404 Monitor)', 'icon' => 'dashicons-dismiss'],
                ],
            ],
            'system' => [
                'title' => 'Hệ thống',
                'items' => [
                    'import-export' => ['label' => 'Sao lưu & Nhập/Xuất', 'icon' => 'dashicons-migrate'],
                    'guide'         => ['label' => 'Hướng dẫn tối ưu SEO', 'icon' => 'dashicons-book'],
                ],
            ],
        ];

        ?>
        <div class="wrap laca-seo-wrap">
            <h1 class="wp-heading-inline" style="display:none;">Laca SEO</h1>
            <hr class="wp-header-end" style="display:none;">

            <header class="laca-seo-header">
                <div class="laca-seo-header__branding">
                    <span class="dashicons dashicons-search laca-seo-header__logo" aria-hidden="true"></span>
                    <h2 class="laca-seo-header__title">Laca SEO</h2>
                </div>
                <div class="laca-seo-header__actions">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'guide'], admin_url('admin.php'))); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-book" aria-hidden="true"></span> Hướng dẫn tối ưu
                    </a>
                </div>
            </header>

            <div class="laca-seo-notices">
                <?php
                Notices::render();

                if (isset($_GET['updated'])) {
                    echo '<div class="notice notice-success is-dismissible laca-seo-notice laca-seo-auto-dismiss laca-alert laca-alert--success"><span class="dashicons dashicons-yes-alt laca-alert__icon" aria-hidden="true"></span><div class="laca-alert__content"><p>' . esc_html__('Cài đặt SEO đã được lưu thành công.', 'lacadev') . '</p></div></div>';
                }
                if (isset($_GET['reset'])) {
                    echo '<div class="notice notice-warning is-dismissible laca-seo-notice laca-seo-auto-dismiss laca-alert laca-alert--warning"><span class="dashicons dashicons-warning laca-alert__icon" aria-hidden="true"></span><div class="laca-alert__content"><p>' . esc_html__('Đã khôi phục cài đặt mặc định của tab này.', 'lacadev') . '</p></div></div>';
                }
                ?>
            </div>

            <div class="laca-seo-layout">
                <!-- Mobile Select (< 1024px) -->
                <div class="laca-seo-nav-select-wrap">
                    <label for="laca-seo-nav-select" class="screen-reader-text"><?php esc_html_e('Chuyển trang Laca SEO', 'lacadev'); ?></label>
                    <select id="laca-seo-nav-select" class="laca-select laca-seo-nav-select" onchange="if(this.value) window.location.href=this.value;">
                        <option value=""><?php esc_html_e('Đi tới...', 'lacadev'); ?></option>
                        <?php foreach ($nav_groups as $group): ?>
                            <optgroup label="<?php echo esc_attr($group['title']); ?>">
                                <?php foreach ($group['items'] as $key => $info): ?>
                                    <option value="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => $key], admin_url('admin.php'))); ?>" <?php selected($view, $key); ?>>
                                        <?php echo esc_html($info['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Desktop Sidebar (>= 1024px) -->
                <aside class="laca-seo-sidebar" aria-label="<?php esc_attr_e('Laca SEO Navigation', 'lacadev'); ?>">
                    <nav class="laca-seo-nav">
                        <?php foreach ($nav_groups as $group): ?>
                            <div class="laca-seo-nav__group">
                                <div class="laca-seo-nav__group-title"><?php echo esc_html($group['title']); ?></div>
                                <ul class="laca-seo-nav__list">
                                    <?php foreach ($group['items'] as $view_key => $view_info): ?>
                                        <li class="laca-seo-nav__item">
                                            <a
                                                href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => $view_key], admin_url('admin.php'))); ?>"
                                                class="laca-seo-nav__link<?php echo ($view === $view_key) ? ' is-active' : ''; ?>"
                                            >
                                                <span class="dashicons <?php echo esc_attr($view_info['icon']); ?>" aria-hidden="true"></span>
                                                <span class="laca-seo-nav__text"><?php echo esc_html($view_info['label']); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                </aside>

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
                            include __DIR__ . '/views/sitemap.php';
                            break;
                        case 'instant-indexing':
                            include __DIR__ . '/views/instant-indexing.php';
                            break;
                        case 'redirections':
                            include __DIR__ . '/views/redirections.php';
                            break;
                        case '404-monitor':
                            include __DIR__ . '/views/404-monitor.php';
                            break;
                        case 'search-console':
                            wp_safe_redirect(admin_url('admin.php?page=laca-google-search-console'));
                            exit;
                        case 'dashboard':
                        default:
                            include __DIR__ . '/views/dashboard.php';
                            break;
                    }
                    ?>
                </main>
            </div>
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

        if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền.'], 403);
        }

        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
        $field_id = sanitize_key(wp_unslash($_POST['field_id'] ?? ''));
        $post_id  = (int) ($_POST['post_id'] ?? 0);
        $term_id  = (int) ($_POST['term_id'] ?? 0);
        $user_id  = (int) ($_POST['user_id'] ?? 0);

        if ($post_id > 0) {
            $sample = get_post($post_id);
            if ($sample instanceof \WP_Post) {
                // Clone so we don't pollute global post object
                $sample = clone $sample;
                if (isset($_POST['current_title']) && $_POST['current_title'] !== '') {
                    $sample->post_title = sanitize_text_field(wp_unslash($_POST['current_title']));
                }
                if (isset($_POST['current_excerpt']) && $_POST['current_excerpt'] !== '') {
                    $sample->post_excerpt = sanitize_textarea_field(wp_unslash($_POST['current_excerpt']));
                }
                if (isset($_POST['current_content']) && $_POST['current_content'] !== '') {
                    $sample->post_content = wp_unslash($_POST['current_content']);
                }
            }
        } elseif ($term_id > 0) {
            $tax = sanitize_key($_POST['taxonomy'] ?? '');
            $sample = $tax ? get_term($term_id, $tax) : get_term($term_id);
            if ($sample instanceof \WP_Term) {
                $sample = clone $sample;
                if (isset($_POST['current_title']) && $_POST['current_title'] !== '') {
                    $sample->name = sanitize_text_field(wp_unslash($_POST['current_title']));
                }
                if (isset($_POST['current_excerpt']) && $_POST['current_excerpt'] !== '') {
                    $sample->description = sanitize_textarea_field(wp_unslash($_POST['current_excerpt']));
                }
            }
        } elseif ($user_id > 0) {
            $sample = get_userdata($user_id);
        } else {
            $sample = $this->resolvePreviewObject($field_id);
        }

        if ($sample === false || $sample === null && $field_id !== '') {
            if ($sample === false) {
                wp_send_json_success(['preview' => '', 'empty' => true]);
                return;
            }
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
