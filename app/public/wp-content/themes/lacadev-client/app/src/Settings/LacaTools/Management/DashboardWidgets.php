<?php

namespace App\Settings\LacaTools\Management;

/**
 * DashboardWidgets
 * Registers and renders all 6 WordPress dashboard widgets.
 * Extracted from ManagementExperience (lines 88–1423).
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

        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget('lacadev_management_hub',    '🚀 LacaDev Business Hub',   [$this, 'renderDashboardWidget']);
            wp_add_dashboard_widget('lacadev_content_tracker',  '📈 Báo cáo Nội dung',       [$this, 'renderContentTrackerWidget']);
            wp_add_dashboard_widget('lacadev_site_health',      '🩺 Tình trạng Website',      [$this, 'renderSiteHealthWidget']);
            wp_add_dashboard_widget('lacadev_media_insights',   '🖼️ Thư viện Media',          [$this, 'renderMediaLibraryWidget']);
            wp_add_dashboard_widget('lacadev_todo_widget',      '✅ Việc cần làm',            [$this, 'renderTodoWidget']);
            wp_add_dashboard_widget('lacadev_quick_search',     '🔍 Tìm kiếm nhanh',          [$this, 'renderQuickSearchWidget']);
            if (post_type_exists('project')) {
                wp_add_dashboard_widget('lacadev_project_charts', '📊 Thống kê Dự án', [$this, 'renderProjectChartsWidget']);
            }
        });

        add_action('wp_ajax_lacadev_quick_search', [$this, 'ajaxQuickSearch']);
    }

    public function enqueueDashboardScripts(string $hook): void
    {
        if ('index.php' !== $hook) {
            return;
        }

        // lacadev-client là theme độc lập (không phải child theme).
        // style.css đặt trong subfolder /theme/ nên get_template_directory_uri() trả về .../lacadev-client/theme/
        // Dùng dirname() để lên 1 level về đúng theme root: .../lacadev-client/
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
        // Styles extracted to resources/styles/admin/_admin-dashboard.scss
        ?>
        <div class="laca-charts-grid">
            <div class="laca-chart-block">
                <h4>Trạng thái dự án</h4>
                <canvas id="laca-chart-status" height="200"></canvas>
            </div>
            <div class="laca-chart-block">
                <h4>Dự án theo tháng (12 tháng gần nhất)</h4>
                <canvas id="laca-chart-monthly" height="200"></canvas>
            </div>
        </div>
        <?php if (!post_type_exists('project')) : ?>
            <p style="text-align:center;color:#999;font-size:12px;margin-top:10px;">Chưa có dữ liệu dự án.</p>
        <?php endif; ?>
        <?php
    }


    // ──────────────────────────────────────────────────────────────
    // Shared CSS
    // ──────────────────────────────────────────────────────────────

    public function renderWidgetStyles(): void
    {
        // All styles extracted to resources/styles/admin/_admin-dashboard.scss
        // Enqueued via wp_enqueue_style('lacadev-admin') in the theme.
    }



    // ──────────────────────────────────────────────────────────────
    // Widget 1: Business Hub
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
            $woo_stats = "<div class='stat-item'><span class='stat-value'>" . esc_html($products_count) . "</span><span class='stat-label'>Sản phẩm</span></div>";
            $woo_stats .= "<div class='stat-item'><span class='stat-value'>" . esc_html($orders_count) . "</span><span class='stat-label'>Đơn hàng</span></div>";
        }

        $maintenance_status = (get_option('_is_maintenance') === 'yes')
            ? '<span class="stat-maintenance--on">🔴 Đang Bật</span>'
            : '<span class="stat-maintenance--off">🟢 Đã Tắt</span>';
        ?>
        <div class="lacadev-dashboard-grid">
            <div class="stat-item"><span class="stat-value"><?php echo esc_html($posts_count); ?></span><span class="stat-label">Bài viết</span></div>
            <div class="stat-item"><span class="stat-value"><?php echo esc_html($pages_count); ?></span><span class="stat-label">Trang</span></div>
            <?php echo $cpt_stats; ?>
            <?php echo $woo_stats; ?>
            <div class="stat-item"><span class="stat-value"><?php echo $maintenance_status; ?></span><span class="stat-label">Bảo trì</span></div>
        </div>
        <div class="hub-section-title">Thao tác nhanh</div>
        <div class="lacadev-actions-list">
            <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="lacadev-btn-quick"><span>🌐</span> Xem site</a>
            <a href="<?php echo esc_url(admin_url('post-new.php')); ?>" class="lacadev-btn-quick"><span>📝</span> Viết bài mới</a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_status=draft&post_type=post')); ?>" class="lacadev-btn-quick"><span>📋</span> Bản nháp</a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_status=future&post_type=post')); ?>" class="lacadev-btn-quick"><span>📅</span> Đã lên lịch</a>
            <?php foreach (get_post_types(['public' => true, '_builtin' => false], 'objects') as $pt) : ?>
                <?php if (class_exists('WooCommerce') && $pt->name === 'product') continue; ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $pt->name)); ?>" class="lacadev-btn-quick"><span>➕</span> Thêm <?php echo esc_html($pt->labels->singular_name); ?></a>
            <?php endforeach; ?>
            <?php if (class_exists('WooCommerce')) : ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="lacadev-btn-quick"><span>🎁</span> Thêm sản phẩm</a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order&status=wc-processing')); ?>" class="lacadev-btn-quick"><span>🚩</span> Đơn hàng mới</a>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('upload.php?detached=1&mode=list')); ?>" class="lacadev-btn-quick"><span>🖼️</span> Media không dùng</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=app-theme-options.php')); ?>" class="lacadev-btn-quick"><span>⚙️</span> Cấu hình Theme</a>
        </div>
        <?php
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
            <li><span class="health-label">🔒 SSL (HTTPS)</span><span class="health-value <?php echo $is_ssl ? 'health-ok' : 'health-warn'; ?>"><?php echo $is_ssl ? 'Bật' : 'Tắt'; ?></span></li>
            <li><span class="health-label">⚙️ WordPress</span><span class="health-value"><?php echo esc_html($wp_ver); ?></span></li>
            <li><span class="health-label">🐘 PHP</span><span class="health-value"><?php echo esc_html($php_ver); ?></span></li>
            <li>
                <span class="health-label">📝 Bản nháp</span>
                <span class="health-value"><?php echo esc_html($draft_total); ?><?php if ($draft_total > 0) : ?><a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=draft')); ?>">Xem</a><?php endif; ?></span>
            </li>
            <li>
                <span class="health-label">📅 Đã lên lịch</span>
                <span class="health-value"><?php echo esc_html($scheduled_count); ?><?php if ($scheduled_count > 0) : ?><a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=future')); ?>">Xem</a><?php endif; ?></span>
            </li>
            <li>
                <span class="health-label">🗑️ Thùng rác</span>
                <span class="health-value"><?php echo esc_html($trash_total); ?><?php if ($trash_total > 0) : ?><a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=trash')); ?>">Dọn</a><?php endif; ?></span>
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
            <li><span class="health-label">📊 Tổng số file</span><span class="health-value"><?php echo esc_html($stats['total_files']); ?></span></li>
            <li><span class="health-label">💾 Tổng dung lượng</span><span class="health-value"><?php echo esc_html($stats['total_size']); ?></span></li>
            <li>
                <span class="health-label">🖼️ Media không sử dụng</span>
                <span class="health-value">
                    <?php echo esc_html($orphan_count); ?>
                    <?php if ($orphan_count > 0) : ?>
                        <a class="health-link" href="<?php echo esc_url(admin_url('upload.php?detached=1&mode=list')); ?>">Xem</a>
                    <?php endif; ?>
                </span>
            </li>
        </ul>
        <div class="hub-section-title" style="margin-top: 15px; font-size: 11px;">Mẹo: Dọn dẹp media không dùng giúp web nhẹ hơn.</div>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Widget 4: To-do
    // ──────────────────────────────────────────────────────────────

    public function renderTodoWidget(): void
    {
        $items = [];

        $pending_comments = (int) wp_count_comments()->moderated;
        if ($pending_comments > 0) {
            $items[] = ['label' => 'Bình luận chờ duyệt', 'count' => $pending_comments, 'url' => admin_url('edit-comments.php?comment_status=moderated'), 'icon' => '💬'];
        }

        if (class_exists('WooCommerce')) {
            $new_orders = wc_get_orders(['status' => ['wc-processing', 'wc-on-hold'], 'return' => 'count']);
            if ($new_orders > 0) {
                $items[] = ['label' => 'Đơn hàng mới cần xử lý', 'count' => (int) $new_orders, 'url' => admin_url('edit.php?post_type=shop_order&status=wc-processing'), 'icon' => '🚩'];
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
                $items[] = ['label' => 'Sản phẩm sắp hết hàng', 'count' => $low_stock, 'url' => admin_url('edit.php?post_type=product&stock_status=lowstock'), 'icon' => '📦'];
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
                $items[] = ['label' => ($pt_obj ? $pt_obj->labels->singular_name : $pt) . ' bản nháp', 'count' => $draft_pt, 'url' => admin_url('edit.php?post_status=draft&post_type=' . $pt), 'icon' => '📋'];
            }

            if (isset($scheduled_counts[$pt])) {
                $pt_obj   = get_post_type_object($pt);
                $items[] = ['label' => ($pt_obj ? $pt_obj->labels->singular_name : $pt) . ' lên lịch (7 ngày)', 'count' => (int) $scheduled_counts[$pt]->count, 'url' => admin_url('edit.php?post_status=future&post_type=' . $pt), 'icon' => '📅'];
            }
        }

        if (empty($items)) : ?>
            <div class="laca-todo-empty">Không có việc cần làm ngay.</div>
        <?php else : ?>
            <ul class="laca-todo-list">
                <?php foreach ($items as $item) : ?>
                    <li>
                        <a href="<?php echo esc_url($item['url']); ?>" class="laca-todo-item">
                            <span><span><?php echo esc_html($item['icon']); ?></span> <?php echo esc_html($item['label']); ?></span>
                            <span class="laca-todo-badge"><?php echo (int) $item['count']; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif;
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
        // nonce & ajax_url injected via wp_localize_script('lacadev-dashboard-search') in enqueueDashboardScripts()
        ?>
        <div class="laca-quick-search-wrap">
            <input type="text" class="laca-quick-search-input" placeholder="Tìm theo tiêu đề (bài viết, trang, CPT)..." autocomplete="off">
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
        if (defined('RANK_MATH_VERSION'))  $seo_meta_key = 'rank_math_seo_score';
        elseif (defined('WPSEO_VERSION')) $seo_meta_key = '_yoast_wpseo_linkdex';

        $low_seo_posts = null;
        if ($seo_meta_key) {
            $low_seo_posts = new \WP_Query(['post_type' => $allow_post_types, 'posts_per_page' => $paged_limit, 'meta_key' => $seo_meta_key, 'orderby' => 'meta_value_num', 'order' => 'ASC', 'post_status' => 'publish', 'no_found_rows' => false, 'meta_query' => [['key' => $seo_meta_key, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']]]);
        }

        $health_report = get_option('lacadev_deep_health_report');
        $health_raw    = $health_report['issues'] ?? [];
        $health_count  = count($health_raw);
        $last_scan     = $health_report['last_updated'] ?? '';
        $health_label  = ($health_count > 0) ? "Nội dung lỗi ($health_count)" : "Nội dung lỗi";
        ?>
        <div class="laca-widget-header-row">
            <select class="laca-report-select">
                <option value="laca-new">Mới nhất</option>
                <option value="laca-updated">Cập nhật</option>
                <option value="laca-views">Xem nhiều</option>
                <option value="laca-health" <?php echo ($health_count > 0) ? 'style="color:#d63638;font-weight:700;"' : ''; ?>><?php echo esc_html($health_label); ?></option>
                <?php if ($seo_meta_key) : ?><option value="laca-seo">Cần tối ưu SEO</option><?php endif; ?>
            </select>
        </div>

        <!-- Tab: Mới nhất -->
        <div id="laca-new" class="laca-tab-content active" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($new_posts->have_posts()) : foreach ($new_posts->posts as $idx => $p) :
                    $pt_obj = get_post_type_object($p->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($p->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-date">Mới</span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $p->post_type); ?> · <?php echo esc_html(get_the_date('d/m/Y', $p->ID)); ?></span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($new_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($new_posts->found_posts / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li>Chưa có nội dung.</li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Cập nhật -->
        <div id="laca-updated" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($updated_posts) : foreach ($updated_posts as $idx => $upost) :
                    $pt_obj = get_post_type_object($upost->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($upost->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($upost->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-date">Cập nhật</span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $upost->post_type); ?> · <?php echo esc_html(mysql2date('d/m/Y', $upost->post_modified)); ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
                    <?php if ($updated_found > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($updated_found / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li>Chưa có thay đổi.</li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Nội dung lỗi -->
        <div id="laca-health" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <?php if ($last_scan) : ?><div class="laca-scan-info" style="font-size:10px;color:#888;margin-bottom:8px;padding-left:4px;">🕒 Quét toàn diện gần nhất: <?php echo date_i18n('H:i d/m/Y', strtotime($last_scan)); ?></div><?php endif; ?>
            <ul class="laca-post-list laca-paged-list">
                <?php if (!empty($health_raw)) : foreach ($health_raw as $idx => $issue) :
                    $pt_obj = get_post_type_object($issue['post_type']); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($issue['ID'])); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($issue['post_title'])); ?></a>
                            <span class="laca-post-meta">
                                <?php foreach ((array) ($issue['errors'] ?? []) as $err_label) : ?>
                                    <span class="laca-issue-tag"><?php echo esc_html($err_label); ?></span>
                                <?php endforeach; ?>
                                <span style="font-size:11px;"><?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $issue['post_type']); ?></span>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
                    <?php if ($health_count > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($health_count / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li>🎉 Tuyệt vời! Không tìm thấy vấn đề nào.</li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Xem nhiều -->
        <div id="laca-views" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($view_posts->have_posts()) : foreach ($view_posts->posts as $idx => $vp) :
                    $views  = get_post_meta($vp->ID, self::VIEW_COUNT_META, true) ?: 0;
                    $pt_obj = get_post_type_object($vp->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($vp->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($vp->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-views"><?php echo (int) $views; ?> xem</span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $vp->post_type); ?></span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($view_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($view_posts->found_posts / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li>Chưa ghi nhận lượt xem.</li><?php endif; ?>
            </ul>
        </div>

        <!-- Tab: SEO -->
        <?php if ($seo_meta_key) : ?>
        <div id="laca-seo" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($low_seo_posts && $low_seo_posts->have_posts()) : foreach ($low_seo_posts->posts as $idx => $sp) :
                    $score  = get_post_meta($sp->ID, $seo_meta_key, true);
                    $pt_obj = get_post_type_object($sp->post_type); ?>
                    <li class="laca-list-item" <?php echo ($idx >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($sp->ID)); ?>" class="laca-post-link"><?php echo esc_html($this->decodeTitleForDisplay($sp->post_title)); ?></a>
                            <span class="laca-post-meta"><span class="laca-badge badge-seo"><?php echo (int) $score; ?>đ</span> <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $sp->post_type); ?> · Cần tối ưu SEO</span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($low_seo_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($low_seo_posts->found_posts / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?><li>Mọi thứ đều ổn!</li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php
        // Tab switching + pagination JS extracted to resources/scripts/admin/dashboard-tracker.js
        // Enqueued in enqueueDashboardScripts() via wp_enqueue_script('lacadev-dashboard-tracker').
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
}
