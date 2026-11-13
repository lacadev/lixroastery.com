<?php

namespace App\Settings\LacaTools\Management;

/**
 * DashboardWidgets
 * Registers and renders all WordPress dashboard widgets.
 * Extracted from ManagementExperience.
 * Depends on ContentAuditService and MediaService for data.
 */
class DashboardWidgets
{
    private const VIEW_COUNT_META = '_gm_view_count';

    public function __construct(
        private ContentAuditService $auditService,
        private MediaService $mediaService,
    ) {}

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_head', [$this, 'renderWidgetStyles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueDashboardScripts']);

        add_action('all_admin_notices', [$this, 'renderOverview'], 1);
        // Mặc định 2 cột: 4 cột của WP để lại các ô "Drag boxes here" trống.
        add_filter('get_user_option_screen_layout_dashboard', static fn ($columns) => $columns ?: 2);

        add_action('wp_dashboard_setup', function () {
            remove_action('welcome_panel', 'wp_welcome_panel');
            remove_meta_box('dashboard_site_health', 'dashboard', 'normal');

            if (class_exists(\App\Features\ProjectManagement\LacaProjectsHub::class)) {
                wp_add_dashboard_widget('lacadev_management_hub', __('LacaDev Business Hub', 'laca'), [$this, 'renderDashboardWidget']);
                wp_add_dashboard_widget('lacadev_todo_widget',     __('Việc cần làm', 'laca'),          [$this, 'renderTodoWidget']);
            }

            wp_add_dashboard_widget('lacadev_content_tracker',  __('Báo cáo nội dung', 'laca'), [$this, 'renderContentTrackerWidget']);
            wp_add_dashboard_widget('lacadev_site_health',      __('Tình trạng website', 'laca'), [$this, 'renderSiteHealthWidget']);
            wp_add_dashboard_widget('lacadev_media_insights',   __('Thư viện media', 'laca'), [$this, 'renderMediaLibraryWidget']);
            wp_add_dashboard_widget('lacadev_quick_search',     __('Tìm kiếm nhanh', 'laca'), [$this, 'renderQuickSearchWidget']);
            if (post_type_exists('project')) {
                wp_add_dashboard_widget('lacadev_project_charts', __('Thống kê dự án', 'laca'), [$this, 'renderProjectChartsWidget']);
            }
            if (current_user_can('manage_options')) {
                wp_add_dashboard_widget('lacadev_storage_health', __('Lưu trữ & quyền file', 'laca'), [$this, 'renderStorageHealthWidget']);
            }
        });

        add_action('wp_ajax_lacadev_quick_search', [$this, 'ajaxQuickSearch']);
        add_action('wp_ajax_laca_storage_health_refresh', [$this, 'ajaxStorageHealthRefresh']);
    }

    public function enqueueDashboardScripts(string $hook): void
    {
        if ('index.php' !== $hook) {
            return;
        }

        // Theme subfolder /theme/ -> dùng dirname để lên theme root
        $base = dirname(get_template_directory_uri());
        $ver  = wp_get_theme()->get('Version') ?: '1.0.0';

        wp_enqueue_script(
            'lacadev-dashboard-search',
            $base . '/resources/scripts/admin/dashboard-search.js',
            [],
            $ver,
            true
        );
        wp_localize_script('lacadev-dashboard-search', 'lacadevSearch', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('lacadev_quick_search'),
        ]);

        wp_enqueue_script(
            'lacadev-dashboard-tracker',
            $base . '/resources/scripts/admin/dashboard-tracker.js',
            [],
            $ver,
            true
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 7: Project Charts
    // ──────────────────────────────────────────────────────────────

    public function renderProjectChartsWidget(): void
    {
        ?>
        <div class="laca-charts-grid">
            <div class="laca-chart-block">
                <h4><?php esc_html_e('Trạng thái dự án', 'laca'); ?></h4>
                <canvas id="laca-chart-status" height="200"></canvas>
            </div>
            <div class="laca-chart-block">
                <h4><?php esc_html_e('Dự án theo tháng (12 tháng gần nhất)', 'laca'); ?></h4>
                <canvas id="laca-chart-monthly" height="200"></canvas>
            </div>
        </div>
        <?php if (!post_type_exists('project')) : ?>
            <p class="laca-widget-empty"><?php esc_html_e('Chưa có dữ liệu dự án.', 'laca'); ?></p>
        <?php endif; ?>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Shared CSS
    // ──────────────────────────────────────────────────────────────

    public function renderWidgetStyles(): void
    {
        // Enqueued via wp_enqueue_style('lacadev-admin') in the theme.
    }

    // ──────────────────────────────────────────────────────────────
    // Overview Banner
    // ──────────────────────────────────────────────────────────────

    /**
     * Khối tổng quan đầu Dashboard (thay banner Welcome + widget Hub + Việc cần làm):
     * số liệu → việc đang chờ → thao tác nhanh. In qua all_admin_notices để chiếm hết
     * chiều ngang trên lưới widget; h1 "Dashboard" gốc của WP ẩn bằng CSS.
     */
    public function renderOverview(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'dashboard') {
            return;
        }

        $stats = [
            ['label' => __('Bài viết', 'laca'), 'value' => (int) wp_count_posts()->publish],
            ['label' => __('Trang', 'laca'), 'value' => (int) wp_count_posts('page')->publish],
        ];
        if (class_exists(\App\Databases\ContactFormTable::class)) {
            $stats[] = ['label' => __('Form liên hệ chưa đọc', 'laca'), 'value' => \App\Databases\ContactFormTable::countAllUnread(), 'url' => admin_url('admin.php?page=laca-contact-forms')];
        }
        if (class_exists('WooCommerce')) {
            $stats[] = ['label' => __('Sản phẩm', 'laca'), 'value' => (int) wp_count_posts('product')->publish];
        }
        $maintenanceOn = get_option('_is_maintenance') === 'yes';

        $actions = [
            ['label' => __('Viết bài mới', 'laca'), 'url' => admin_url('post-new.php'), 'primary' => true],
            ['label' => __('Xem site', 'laca'), 'url' => home_url('/'), 'external' => true],
            ['label' => __('Bản nháp', 'laca'), 'url' => admin_url('edit.php?post_status=draft&post_type=post')],
            ['label' => __('Đã lên lịch', 'laca'), 'url' => admin_url('edit.php?post_status=future&post_type=post')],
        ];
        foreach (get_post_types(['public' => true, '_builtin' => false], 'objects') as $pt) {
            if (class_exists('WooCommerce') && $pt->name === 'product') {
                continue;
            }
            /* translators: %s: tên loại nội dung, vd "Dự án" */
            $actions[] = ['label' => sprintf(__('Thêm %s', 'laca'), $pt->labels->singular_name), 'url' => admin_url('post-new.php?post_type=' . $pt->name)];
        }
        if (class_exists('WooCommerce')) {
            $actions[] = ['label' => __('Thêm sản phẩm', 'laca'), 'url' => admin_url('post-new.php?post_type=product')];
        }
        $actions[] = ['label' => __('Media không dùng', 'laca'), 'url' => admin_url('upload.php?detached=1&mode=list')];
        $actions[] = ['label' => __('Cấu hình Theme', 'laca'), 'url' => admin_url('admin.php?page=app-theme-options.php')];

        $todo = $this->getTodoItems();
        ?>
        <div class="laca-dash">
            <h1 class="laca-dash__title"><?php esc_html_e('Bảng điều khiển', 'laca'); ?></h1>

            <div class="laca-dash__stats">
                <?php foreach ($stats as $stat) : ?>
                    <div class="laca-dash__stat">
                        <span class="laca-stat__label"><?php echo esc_html($stat['label']); ?></span>
                        <?php if (!empty($stat['url'])) : ?>
                            <a class="laca-stat__value" href="<?php echo esc_url($stat['url']); ?>"><?php echo esc_html(number_format_i18n($stat['value'])); ?></a>
                        <?php else : ?>
                            <span class="laca-stat__value"><?php echo esc_html(number_format_i18n($stat['value'])); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="laca-dash__stat">
                    <span class="laca-stat__label"><?php esc_html_e('Chế độ bảo trì', 'laca'); ?></span>
                    <span class="laca-dash__state <?php echo $maintenanceOn ? 'is-on' : 'is-off'; ?>">
                        <?php echo $maintenanceOn ? esc_html__('Đang bật', 'laca') : esc_html__('Đang tắt', 'laca'); ?>
                    </span>
                </div>
            </div>

            <div class="laca-dash__cols">
                <section class="laca-dash__card" aria-labelledby="laca-dash-todo">
                    <h2 id="laca-dash-todo" class="laca-dash__card-title"><?php esc_html_e('Việc cần làm', 'laca'); ?></h2>
                    <?php if ($todo === []) : ?>
                        <p class="laca-dash__empty"><?php esc_html_e('Không có việc cần làm ngay.', 'laca'); ?></p>
                    <?php else : ?>
                        <ul class="laca-dash__todo">
                            <?php foreach ($todo as $item) : ?>
                                <li>
                                    <a href="<?php echo esc_url($item['url']); ?>">
                                        <span><?php echo esc_html($item['label']); ?></span>
                                        <span class="laca-dash__count"><?php echo esc_html(number_format_i18n($item['count'])); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="laca-dash__card" aria-labelledby="laca-dash-actions">
                    <h2 id="laca-dash-actions" class="laca-dash__card-title"><?php esc_html_e('Thao tác nhanh', 'laca'); ?></h2>
                    <div class="laca-dash__actions">
                        <?php foreach ($actions as $action) : ?>
                            <a
                                class="laca-btn laca-btn--sm <?php echo !empty($action['primary']) ? 'laca-btn--solid' : 'laca-btn--outline'; ?>"
                                href="<?php echo esc_url($action['url']); ?>"
                                <?php echo !empty($action['external']) ? 'target="_blank" rel="noopener"' : ''; ?>
                            ><?php echo esc_html($action['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Hub Exclusive Widget: Business Hub & To-do
    // ──────────────────────────────────────────────────────────────

    public function renderDashboardWidget(): void
    {
        $posts_count = (int) wp_count_posts()->publish;
        $pages_count = wp_count_posts('page')->publish;

        $cpt_stats = '';
        foreach (get_post_types(['public' => true, '_builtin' => false], 'objects') as $post_type) {
            if (class_exists('WooCommerce') && $post_type->name === 'product') continue;
            $count = (int) wp_count_posts($post_type->name)->publish;
            $cpt_stats .= "<div class='stat-item'><span class='stat-value'>" . esc_html($count) . "</span><span class='stat-label'>" . esc_html($post_type->label) . "</span></div>";
        }

        $woo_stats = '';
        if (class_exists('WooCommerce')) {
            $products_count = (int) wp_count_posts('product')->publish;
            $orders_count   = (int) wc_get_orders(['status' => 'completed', 'return' => 'count']);
            $woo_stats = "<div class='stat-item'><span class='stat-value'>" . esc_html($products_count) . "</span><span class='stat-label'>" . esc_html__('Sản phẩm', 'laca') . "</span></div>";
            $woo_stats .= "<div class='stat-item'><span class='stat-value'>" . esc_html($orders_count) . "</span><span class='stat-label'>" . esc_html__('Đơn hàng', 'laca') . "</span></div>";
        }

        $maintenance_status = (get_option('_is_maintenance') === 'yes')
            ? '<span class="stat-maintenance--on">🔴 ' . esc_html__('Đang Bật', 'laca') . '</span>'
            : '<span class="stat-maintenance--off">🟢 ' . esc_html__('Đã Tắt', 'laca') . '</span>';
        ?>
        <div class="lacadev-dashboard-grid">
            <div class="stat-item"><span class="stat-value"><?php echo esc_html($posts_count); ?></span><span class="stat-label"><?php esc_html_e('Bài viết', 'laca'); ?></span></div>
            <div class="stat-item"><span class="stat-value"><?php echo esc_html($pages_count); ?></span><span class="stat-label"><?php esc_html_e('Trang', 'laca'); ?></span></div>
            <?php echo $cpt_stats; ?>
            <?php echo $woo_stats; ?>
            <div class="stat-item"><span class="stat-value"><?php echo $maintenance_status; ?></span><span class="stat-label"><?php esc_html_e('Bảo trì', 'laca'); ?></span></div>
        </div>
        <div class="hub-section-title"><?php esc_html_e('Thao tác nhanh', 'laca'); ?></div>
        <div class="lacadev-actions-list">
            <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="lacadev-btn-quick"><span>🌐</span> <?php esc_html_e('Xem site', 'laca'); ?></a>
            <a href="<?php echo esc_url(admin_url('post-new.php')); ?>" class="lacadev-btn-quick"><span>📝</span> <?php esc_html_e('Viết bài mới', 'laca'); ?></a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_status=draft&post_type=post')); ?>" class="lacadev-btn-quick"><span>📋</span> <?php esc_html_e('Bản nháp', 'laca'); ?></a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_status=future&post_type=post')); ?>" class="lacadev-btn-quick"><span>📅</span> <?php esc_html_e('Đã lên lịch', 'laca'); ?></a>
            <?php foreach (get_post_types(['public' => true, '_builtin' => false], 'objects') as $pt) : ?>
                <?php if (class_exists('WooCommerce') && $pt->name === 'product') continue; ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $pt->name)); ?>" class="lacadev-btn-quick"><span>➕</span> <?php printf(esc_html__('Thêm %s', 'laca'), esc_html($pt->labels->singular_name)); ?></a>
            <?php endforeach; ?>
            <?php if (class_exists('WooCommerce')) : ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="lacadev-btn-quick"><span>🎁</span> <?php esc_html_e('Thêm sản phẩm', 'laca'); ?></a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order&status=wc-processing')); ?>" class="lacadev-btn-quick"><span>🚩</span> <?php esc_html_e('Đơn hàng mới', 'laca'); ?></a>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('upload.php?detached=1&mode=list')); ?>" class="lacadev-btn-quick"><span>🖼️</span> <?php esc_html_e('Media không dùng', 'laca'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=app-theme-options.php')); ?>" class="lacadev-btn-quick"><span>⚙️</span> <?php esc_html_e('Cấu hình Theme', 'laca'); ?></a>
        </div>
        <?php
    }

    public function renderTodoWidget(): void
    {
        $items = $this->getTodoItems();
        if (empty($items)) : ?>
            <div class="laca-todo-empty"><?php esc_html_e('Không có việc cần làm ngay.', 'laca'); ?></div>
        <?php else : ?>
            <ul class="laca-todo-list">
                <?php foreach ($items as $item) : ?>
                    <li>
                        <a href="<?php echo esc_url($item['url']); ?>" class="laca-todo-item">
                            <span><?php echo esc_html($item['label']); ?></span>
                            <span class="laca-todo-badge"><?php echo (int) $item['count']; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif;
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 2: Site Health
    // ──────────────────────────────────────────────────────────────

    public function renderSiteHealthWidget(): void
    {
        $is_ssl      = is_ssl() || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $php_ver     = PHP_VERSION;
        $wp_ver      = get_bloginfo('version');
        $all_types   = $this->auditService->getDashboardPostTypes();
        $draft_total = $trash_total = 0;

        foreach ($all_types as $pt) {
            $counts       = wp_count_posts($pt);
            $draft_total += (int) ($counts->draft ?? 0);
            $trash_total += (int) ($counts->trash ?? 0);
        }

        $scheduled       = new \WP_Query(['post_type' => $all_types, 'post_status' => 'future', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false]);
        $scheduled_count = $scheduled->found_posts;
        ?>
        <ul class="laca-health-list">
            <li><span class="health-label">🔒 <?php esc_html_e('SSL (HTTPS)', 'laca'); ?></span><span class="health-value <?php echo $is_ssl ? 'health-ok' : 'health-warn'; ?>"><?php echo $is_ssl ? esc_html__('Bật', 'laca') : esc_html__('Tắt', 'laca'); ?></span></li>
            <li><span class="health-label">⚙️ WordPress</span><span class="health-value"><?php echo esc_html($wp_ver); ?></span></li>
            <li><span class="health-label">🐘 PHP</span><span class="health-value"><?php echo esc_html($php_ver); ?></span></li>
            <li>
                <span class="health-label">📝 <?php esc_html_e('Bản nháp', 'laca'); ?></span>
                <span class="health-value"><?php echo esc_html($draft_total); ?><?php if ($draft_total > 0) : ?><a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=draft')); ?>"><?php esc_html_e('Xem', 'laca'); ?></a><?php endif; ?></span>
            </li>
            <li>
                <span class="health-label">📅 <?php esc_html_e('Đã lên lịch', 'laca'); ?></span>
                <span class="health-value"><?php echo esc_html($scheduled_count); ?><?php if ($scheduled_count > 0) : ?><a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=future')); ?>"><?php esc_html_e('Xem', 'laca'); ?></a><?php endif; ?></span>
            </li>
            <li>
                <span class="health-label">🗑️ <?php esc_html_e('Thùng rác', 'laca'); ?></span>
                <span class="health-value"><?php echo esc_html($trash_total); ?><?php if ($trash_total > 0) : ?><a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=trash')); ?>"><?php esc_html_e('Dọn', 'laca'); ?></a><?php endif; ?></span>
            </li>
        </ul>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 3: Media Library
    // ──────────────────────────────────────────────────────────────

    public function renderMediaLibraryWidget(): void
    {
        $stats        = $this->mediaService->getMediaStats();
        $orphan_count = $stats['orphan_count'];
        ?>
        <ul class="laca-health-list laca-media-list">
            <li><span class="health-label">📊 <?php esc_html_e('Tổng số file', 'laca'); ?></span><span class="health-value"><?php echo esc_html($stats['total_files']); ?></span></li>
            <li><span class="health-label">💾 <?php esc_html_e('Tổng dung lượng', 'laca'); ?></span><span class="health-value"><?php echo esc_html($stats['total_size']); ?></span></li>
            <li>
                <span class="health-label">🖼️ <?php esc_html_e('Media không sử dụng', 'laca'); ?></span>
                <span class="health-value">
                    <?php echo esc_html($orphan_count); ?>
                    <?php if ($orphan_count > 0) : ?>
                        <a class="health-link" href="<?php echo esc_url(admin_url('upload.php?detached=1&mode=list')); ?>"><?php esc_html_e('Xem', 'laca'); ?></a>
                    <?php endif; ?>
                </span>
            </li>
        </ul>
        <div class="hub-section-title laca-scan-info"><?php esc_html_e('Mẹo: Dọn dẹp media không dùng giúp web nhẹ hơn.', 'laca'); ?></div>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 4: To-do Helper
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array<int,array{label:string,count:int,url:string}>
     */
    private function getTodoItems(): array
    {
        $items = [];

        $pending_comments = (int) wp_count_comments()->moderated;
        if ($pending_comments > 0) {
            $items[] = ['label' => __('Bình luận chờ duyệt', 'laca'), 'count' => $pending_comments, 'url' => admin_url('edit-comments.php?comment_status=moderated')];
        }

        if (class_exists('WooCommerce')) {
            $new_orders = wc_get_orders(['status' => ['wc-processing', 'wc-on-hold'], 'return' => 'count']);
            if ($new_orders > 0) {
                $items[] = ['label' => __('Đơn hàng mới cần xử lý', 'laca'), 'count' => (int) $new_orders, 'url' => admin_url('edit.php?post_type=shop_order&status=wc-processing')];
            }

            global $wpdb;
            $threshold = (int) get_option('woocommerce_notify_low_stock_amount', 2);
            $low_stock = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_stock   ON p.ID = pm_stock.post_id   AND pm_stock.meta_key = '_stock'
                 INNER JOIN {$wpdb->postmeta} pm_manage  ON p.ID = pm_manage.post_id  AND pm_manage.meta_key = '_manage_stock' AND pm_manage.meta_value = 'yes'
                 WHERE p.post_type = 'product' AND p.post_status = 'publish'
                 AND CAST(pm_stock.meta_value AS SIGNED) <= %d AND CAST(pm_stock.meta_value AS SIGNED) > 0",
                $threshold
            ));
            if ($low_stock > 0) {
                $items[] = ['label' => __('Sản phẩm sắp hết hàng', 'laca'), 'count' => $low_stock, 'url' => admin_url('edit.php?post_type=product&stock_status=lowstock')];
            }
        }

        $all_types          = (array) $this->auditService->getDashboardPostTypes();
        global $wpdb;
        $type_placeholders  = implode(',', array_fill(0, count($all_types), '%s'));
        $now                = current_time('mysql');
        $future             = date('Y-m-d H:i:s', strtotime('+7 days', current_time('timestamp')));
        $scheduled_counts   = $wpdb->get_results($wpdb->prepare(
            "SELECT post_type, COUNT(*) as count FROM {$wpdb->posts}
             WHERE post_type IN ({$type_placeholders}) AND post_status = 'future'
             AND post_date BETWEEN %s AND %s GROUP BY post_type",
            array_merge($all_types, [$now, $future])
        ), OBJECT_K);

        foreach ($all_types as $pt) {
            $counts   = wp_count_posts($pt);
            $draft_pt = (int) ($counts->draft ?? 0);
            if ($draft_pt > 0) {
                $pt_obj   = get_post_type_object($pt);
                $items[] = ['label' => sprintf(/* translators: %s: tên loại nội dung */ __('%s bản nháp', 'laca'), $pt_obj ? $pt_obj->labels->singular_name : $pt), 'count' => $draft_pt, 'url' => admin_url('edit.php?post_status=draft&post_type=' . $pt)];
            }

            if (isset($scheduled_counts[$pt])) {
                $pt_obj   = get_post_type_object($pt);
                $items[] = ['label' => sprintf(/* translators: %s: tên loại nội dung */ __('%s lên lịch (7 ngày)', 'laca'), $pt_obj ? $pt_obj->labels->singular_name : $pt), 'count' => (int) $scheduled_counts[$pt]->count, 'url' => admin_url('edit.php?post_status=future&post_type=' . $pt)];
            }
        }

        return $items;
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 5: Quick Search
    // ──────────────────────────────────────────────────────────────

    public function ajaxQuickSearch(): void
    {
        check_ajax_referer('lacadev_quick_search', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $term = trim(sanitize_text_field(wp_unslash($_POST['search_keyword'] ?? '')));
        if (strlen($term) < 2) {
            wp_send_json_success(['items' => [], 'message' => 'Nhập ít nhất 2 ký tự']);
        }

        global $wpdb;
        $post_types       = $this->getSearchablePostTypes();
        $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $like             = '%' . $wpdb->esc_like($term) . '%';
        $statuses         = ['publish', 'draft', 'future', 'private'];
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type, post_status, post_date
             FROM {$wpdb->posts}
             WHERE post_type IN ($type_placeholders) AND post_title LIKE %s AND post_status IN ($status_placeholders)
             ORDER BY post_title ASC LIMIT 15",
            array_merge($post_types, [$like], $statuses)
        ));

        $status_labels = ['draft' => 'Bản nháp', 'future' => 'Lên lịch', 'private' => 'Riêng tư', 'publish' => ''];
        $items = [];
        foreach ((array) $results as $row) {
            $pt_obj = get_post_type_object($row->post_type);
            $sl     = ($status_labels[$row->post_status] ?? $row->post_status);
            $items[] = [
                'id'        => (int) $row->ID,
                'title'     => $this->decodeTitleForDisplay($row->post_title),
                'edit_url'  => get_edit_post_link((int) $row->ID, 'raw'),
                'post_type' => $pt_obj ? $pt_obj->labels->singular_name : $row->post_type,
                'status'    => $sl ? " ($sl)" : '',
                'date'      => date_i18n('d/m/Y', strtotime($row->post_date)),
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    public function renderQuickSearchWidget(): void
    {
        ?>
        <div class="laca-quick-search-wrap">
            <input type="text" class="laca-quick-search-input" placeholder="<?php esc_attr_e('Tìm theo tiêu đề (bài viết, trang, CPT)...', 'laca'); ?>" autocomplete="off">
            <div class="laca-quick-search-results"></div>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 6: Content Tracker
    // ──────────────────────────────────────────────────────────────

    public function renderContentTrackerWidget(): void
    {
        $allow_post_types = $this->auditService->getDashboardPostTypes() ?: ['post'];
        $limit            = max(1, (int) carbon_get_theme_option('dashboard_widget_limit') ?: 5);
        $paged_limit      = 100;

        $new_posts = new \WP_Query(['post_type' => $allow_post_types, 'posts_per_page' => $paged_limit, 'orderby' => 'date', 'order' => 'DESC', 'post_status' => 'publish', 'no_found_rows' => false]);

        global $wpdb;
        $pt_in        = implode(',', array_fill(0, count((array) $allow_post_types), '%s'));
        $updated_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS ID, post_title, post_modified, post_type, post_date
             FROM {$wpdb->posts}
             WHERE post_type IN ($pt_in) AND post_status = 'publish' AND post_modified != post_date
             ORDER BY post_modified DESC LIMIT %d",
            array_merge((array) $allow_post_types, [$paged_limit])
        ));
        $updated_found = $wpdb->get_var('SELECT FOUND_ROWS()');

        $view_posts = new \WP_Query(['post_type' => $allow_post_types, 'posts_per_page' => $paged_limit, 'meta_key' => self::VIEW_COUNT_META, 'orderby' => 'meta_value_num', 'order' => 'DESC', 'post_status' => 'publish', 'no_found_rows' => false]);

        $seo_meta_key  = '';
        if (class_exists('\App\Features\LacaSeo\LacaSeo'))   $seo_meta_key = '_laca_seo_seo_score';
        elseif (defined('RANK_MATH_VERSION'))  $seo_meta_key = 'rank_math_seo_score';
        elseif (defined('WPSEO_VERSION')) $seo_meta_key = '_yoast_wpseo_linkdex';

        $low_seo_posts = null;
        if ($seo_meta_key) {
            $low_seo_posts = new \WP_Query(['post_type' => $allow_post_types, 'posts_per_page' => $paged_limit, 'meta_key' => $seo_meta_key, 'orderby' => 'meta_value_num', 'order' => 'ASC', 'post_status' => 'publish', 'no_found_rows' => false, 'meta_query' => [['key' => $seo_meta_key, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']]]);
        }

        $health_report = get_option('lacadev_deep_health_report');
        $health_raw    = $health_report['issues'] ?? [];
        $health_count  = count($health_raw);
        $last_scan     = $health_report['last_updated'] ?? '';
        $health_label  = ($health_count > 0) ? sprintf(__('Nội dung lỗi (%d)', 'laca'), $health_count) : __('Nội dung lỗi', 'laca');
        ?>
        <div class="laca-widget-header-row">
            <select class="laca-report-select">
                <option value="laca-new"><?php esc_html_e('Mới nhất', 'laca'); ?></option>
                <option value="laca-updated"><?php esc_html_e('Cập nhật', 'laca'); ?></option>
                <option value="laca-views"><?php esc_html_e('Xem nhiều', 'laca'); ?></option>
                <option value="laca-health" class="<?php echo ($health_count > 0) ? 'laca-status-danger' : ''; ?>"><?php echo esc_html($health_label); ?></option>
                <?php if ($seo_meta_key) : ?><option value="laca-seo"><?php esc_html_e('Cần tối ưu SEO', 'laca'); ?></option><?php endif; ?>
            </select>
        </div>

        <!-- Tab: Mới nhất -->
        <div id="laca-new" class="laca-tab-content active" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($new_posts->have_posts()) : foreach ($new_posts->posts as $idx => $p) :
                    $pt_obj = get_post_type_object($p->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none; /* động */"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($p->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-date"><?php esc_html_e('Mới', 'laca'); ?></span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $p->post_type); ?> · <?php echo esc_html(get_the_date('d/m/Y', $p->ID)); ?></span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($new_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« <?php esc_html_e('Trước', 'laca'); ?></button>
                            <span class="laca-page-info"><?php printf(esc_html__('Trang %1$d / %2$d', 'laca'), 1, ceil($new_posts->found_posts / $limit)); ?></span>
                            <button class="laca-page-btn next"><?php esc_html_e('Sau', 'laca'); ?> »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li><?php esc_html_e('Chưa có nội dung.', 'laca'); ?></li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Cập nhật -->
        <div id="laca-updated" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($updated_posts) : foreach ($updated_posts as $idx => $upost) :
                    $pt_obj = get_post_type_object($upost->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none; /* động */"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($upost->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($upost->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-date"><?php esc_html_e('Cập nhật', 'laca'); ?></span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $upost->post_type); ?> · <?php echo esc_html(mysql2date('d/m/Y', $upost->post_modified)); ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
                    <?php if ($updated_found > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« <?php esc_html_e('Trước', 'laca'); ?></button>
                            <span class="laca-page-info"><?php printf(esc_html__('Trang %1$d / %2$d', 'laca'), 1, ceil($updated_found / $limit)); ?></span>
                            <button class="laca-page-btn next"><?php esc_html_e('Sau', 'laca'); ?> »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li><?php esc_html_e('Chưa có thay đổi.', 'laca'); ?></li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Nội dung lỗi -->
        <div id="laca-health" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <?php if ($last_scan) : ?><div class="laca-scan-info">🕒 <?php printf(esc_html__('Quét toàn diện gần nhất: %s', 'laca'), date_i18n('H:i d/m/Y', strtotime($last_scan))); ?></div><?php endif; ?>
            <ul class="laca-post-list laca-paged-list">
                <?php if (!empty($health_raw)) : foreach ($health_raw as $idx => $issue) :
                    $pt_obj = get_post_type_object($issue['post_type']); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none; /* động */"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($issue['ID'])); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($issue['post_title'])); ?></a>
                            <span class="laca-post-meta">
                                <?php foreach ((array) ($issue['errors'] ?? []) as $err_label) : ?>
                                    <span class="laca-issue-tag"><?php echo esc_html($err_label); ?></span>
                                <?php endforeach; ?>
                                <span class="laca-note-date"><?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $issue['post_type']); ?></span>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
                    <?php if ($health_count > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« <?php esc_html_e('Trước', 'laca'); ?></button>
                            <span class="laca-page-info"><?php printf(esc_html__('Trang %1$d / %2$d', 'laca'), 1, ceil($health_count / $limit)); ?></span>
                            <button class="laca-page-btn next"><?php esc_html_e('Sau', 'laca'); ?> »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li>🎉 <?php esc_html_e('Tuyệt vời! Không tìm thấy vấn đề nào.', 'laca'); ?></li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Xem nhiều -->
        <div id="laca-views" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($view_posts->have_posts()) : foreach ($view_posts->posts as $idx => $vp) :
                    $views  = get_post_meta($vp->ID, self::VIEW_COUNT_META, true) ?: 0;
                    $pt_obj = get_post_type_object($vp->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none; /* động */"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($vp->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($vp->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-views"><?php echo (int) $views; ?> <?php esc_html_e('xem', 'laca'); ?></span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $vp->post_type); ?></span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($view_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« <?php esc_html_e('Trước', 'laca'); ?></button>
                            <span class="laca-page-info"><?php printf(esc_html__('Trang %1$d / %2$d', 'laca'), 1, ceil($view_posts->found_posts / $limit)); ?></span>
                            <button class="laca-page-btn next"><?php esc_html_e('Sau', 'laca'); ?> »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li><?php esc_html_e('Chưa ghi nhận lượt xem.', 'laca'); ?></li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: SEO -->
        <?php if ($seo_meta_key) : ?>
        <div id="laca-seo" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($low_seo_posts && $low_seo_posts->have_posts()) : foreach ($low_seo_posts->posts as $idx => $sp) :
                    $score  = get_post_meta($sp->ID, $seo_meta_key, true);
                    $pt_obj = get_post_type_object($sp->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none; /* động */"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($sp->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($sp->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-seo"><?php echo (int) $score; ?>đ</span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $sp->post_type); ?> · <?php esc_html_e('Cần tối ưu SEO', 'laca'); ?></span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($low_seo_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« <?php esc_html_e('Trước', 'laca'); ?></button>
                            <span class="laca-page-info"><?php printf(esc_html__('Trang %1$d / %2$d', 'laca'), 1, ceil($low_seo_posts->found_posts / $limit)); ?></span>
                            <button class="laca-page-btn next"><?php esc_html_e('Sau', 'laca'); ?> »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li><?php esc_html_e('Mọi thứ đều ổn!', 'laca'); ?></li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function getSearchablePostTypes(): array
    {
        $exclude = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'action_monitor'];
        $types   = array_values(array_diff(array_keys(get_post_types([], 'names')), $exclude));

        foreach (['service', 'project'] as $cpt) {
            if (!in_array($cpt, $types, true)) {
                $types[] = $cpt;
            }
        }

        return apply_filters('lacadev_searchable_post_types', $types);
    }

    private function decodeTitleForDisplay(string $title): string
    {
        return htmlspecialchars_decode(html_entity_decode($title, ENT_QUOTES, 'UTF-8'), ENT_QUOTES);
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 8: Storage Health & File Permissions
    // ──────────────────────────────────────────────────────────────

    public function renderStorageHealthWidget(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $cached = StorageHealth::getCached();
        $nonce  = wp_create_nonce(StorageHealth::NONCE_ACTION);
        $i18n   = [
            'calculating'  => __('Đang tính...', 'laca'),
            'errorGeneric' => __('Lỗi kết nối máy chủ.', 'laca'),
        ];
        ?>
        <div class="laca-storage-widget" id="laca-storage-health-box">
            <?php echo StorageHealth::renderWidgetInner($cached); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <script>
        (function() {
            const box = document.getElementById('laca-storage-health-box');
            if (!box) return;

            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const nonce   = <?php echo wp_json_encode($nonce); ?>;
            const i18n    = <?php echo wp_json_encode($i18n); ?>;

            box.addEventListener('click', function(e) {
                const btn = e.target.closest('[data-laca-storage-refresh]');
                if (!btn) return;

                const origText = btn.textContent;
                btn.disabled = true;
                btn.textContent = i18n.calculating;

                const body = new URLSearchParams({
                    action: 'laca_storage_health_refresh',
                    _nonce: nonce
                });

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data && res.data.html) {
                        box.innerHTML = res.data.html;
                    } else {
                        btn.disabled = false;
                        btn.textContent = origText;
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = origText;
                });
            });
        })();
        </script>
        <?php
    }

    public function ajaxStorageHealthRefresh(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(StorageHealth::NONCE_ACTION, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
        }

        $data = StorageHealth::compute();
        wp_send_json_success([
            'data' => $data,
            'html' => StorageHealth::renderWidgetInner($data),
        ]);
    }
}
