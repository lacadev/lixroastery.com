<?php

namespace App\Settings\LacaAdmin;

use App\Settings\Security\SecurityAudit;
use App\Settings\Security\LoginGuard;
use App\Settings\Security\UserEnumerationGuard;
use App\Settings\Security\SvgUploadGuard;
use App\Settings\Security\LoginPolicy;
use App\Settings\LacaTools\Management\StorageHealth;
use App\Settings\LacaTools\Management\DatabaseCleaner;

if (!defined('ABSPATH')) {
    exit;
}

class LacaDashboard
{
    public const NONCE_ACTION = 'laca_dashboard_nonce';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 20);
        add_action('wp_ajax_laca_dashboard_refresh_audit', [$this, 'ajaxRefreshAudit']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'laca-admin',
            __('Tổng quan', 'laca'),
            __('Tổng quan', 'laca'),
            'manage_options',
            'laca-dashboard',
            [$this, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'laca-dashboard') === false) {
            return;
        }

        $base = dirname(get_template_directory_uri());

        wp_enqueue_script(
            'laca-dashboard-js',
            $base . '/resources/scripts/admin/laca-dashboard.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('laca-dashboard-js', 'lacaDashboardData', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce(self::NONCE_ACTION),
            'storageNonce'  => wp_create_nonce(StorageHealth::NONCE_ACTION),
            'checkingText'  => __('Đang kiểm tra…', 'laca'),
            'calculatingText'=> __('Đang tính…', 'laca'),
            'checkAgainText'=> __('Kiểm tra lại', 'laca'),
            'calcNowText'   => __('Tính ngay', 'laca'),
        ]);
    }

    public function ajaxRefreshAudit(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(__('Không có quyền truy cập.', 'laca'), 403);
        }

        $audit = SecurityAudit::run();
        wp_send_json_success([
            'score'       => $audit['score'],
            'pass'        => $audit['pass'],
            'warn'        => $audit['warn'],
            'fail'        => $audit['fail'],
            'html_hero'   => $this->renderHeroInner($audit),
            'html_todo'   => $this->renderTodoInner($audit),
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $audit   = SecurityAudit::getCached();
        $storage = StorageHealth::getCached();
        ?>
        <div class="wrap laca-wrap laca-dashboard-wrap">
            <h1 class="wp-heading-inline screen-reader-text"><?php echo esc_html__('Tổng quan Laca Admin', 'laca'); ?></h1>

            <!-- A. Hero Section -->
            <div class="laca-dashboard-hero" id="laca-dashboard-hero-container">
                <?php echo $this->renderHeroInner($audit); ?>
            </div>

            <!-- B. 2 Cột: Việc nên làm (C) & Dung lượng (D) -->
            <div class="laca-dashboard-grid-2">
                <!-- C. Việc nên làm -->
                <div class="laca-card laca-dashboard-todo-card" id="laca-dashboard-todo">
                    <div class="laca-card__header laca-card__header--flex">
                        <div>
                            <h2 class="laca-card__header-title">
                                <?php echo esc_html__('Việc nên làm', 'laca'); ?>
                                <span class="laca-badge laca-badge--neutral" id="laca-todo-badge">0</span>
                            </h2>
                            <p class="laca-card__header-description">
                                <?php echo esc_html__('Các khuyến nghị bảo mật và tối ưu cần xử lý để website hoạt động an toàn, trơn tru.', 'laca'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="laca-card__body" id="laca-dashboard-todo-container">
                        <?php echo $this->renderTodoInner($audit); ?>
                    </div>
                </div>

                <!-- D. Dung lượng website -->
                <div class="laca-card laca-dashboard-storage-card">
                    <div class="laca-card__header laca-card__header--flex">
                        <div>
                            <h2 class="laca-card__header-title"><?php echo esc_html__('Dung lượng website', 'laca'); ?></h2>
                            <p class="laca-card__header-description"><?php echo esc_html__('Phân bổ dữ liệu giữa mã nguồn, media và cơ sở dữ liệu.', 'laca'); ?></p>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-system&tab=storage')); ?>" class="laca-link-inline">
                            <?php echo esc_html__('Chi tiết →', 'laca'); ?>
                        </a>
                    </div>
                    <div class="laca-card__body" id="laca-dashboard-storage-container">
                        <?php echo $this->renderStorageInner($storage); ?>
                    </div>
                </div>
            </div>

            <!-- E. Chức năng Laca Admin -->
            <div class="laca-dashboard-section">
                <div class="laca-dashboard-section__header">
                    <h2 class="laca-dashboard-section__title"><?php echo esc_html__('Chức năng quản trị', 'laca'); ?></h2>
                    <p class="laca-dashboard-section__desc"><?php echo esc_html__('Truy cập nhanh các công cụ cấu hình, bảo mật và vận hành hệ thống.', 'laca'); ?></p>
                </div>
                <div class="laca-dashboard-features-grid">
                    <?php echo $this->renderFeaturesGrid(); ?>
                </div>
            </div>

            <!-- F. Thông số Hosting -->
            <div class="laca-dashboard-section">
                <div class="laca-dashboard-section__header laca-dashboard-section__header--flex">
                    <div>
                        <h2 class="laca-dashboard-section__title"><?php echo esc_html__('Môi trường máy chủ', 'laca'); ?></h2>
                        <p class="laca-dashboard-section__desc"><?php echo esc_html__('Thông số cấu hình môi trường hosting đang chạy website.', 'laca'); ?></p>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-system&tab=server')); ?>" class="laca-link-inline">
                        <?php echo esc_html__('Xem chi tiết →', 'laca'); ?>
                    </a>
                </div>
                <div class="laca-dashboard-hosting-grid">
                    <?php echo $this->renderHostingGrid(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderHeroInner(?array $audit): string
    {
        ob_start();
        $user = wp_get_current_user();
        $displayName = $user->display_name ?: $user->user_login;

        $hasAudit = is_array($audit) && isset($audit['score']);
        $score    = $hasAudit ? (int) $audit['score'] : null;

        if ($score !== null) {
            if ($score >= 80) {
                $statusTitle = __('Website đang ổn định', 'laca');
                $statusBadge = __('Tốt', 'laca');
                $badgeClass  = 'laca-badge--success';
                $circleColor = 'var(--laca-success, #16a34a)';
            } elseif ($score >= 50) {
                $statusTitle = __('Website cần chú ý', 'laca');
                $statusBadge = __('Cần chú ý', 'laca');
                $badgeClass  = 'laca-badge--warning';
                $circleColor = 'var(--laca-warning, #eab308)';
            } else {
                $statusTitle = __('Website cần xử lý ngay', 'laca');
                $statusBadge = __('Nguy hiểm', 'laca');
                $badgeClass  = 'laca-badge--danger';
                $circleColor = 'var(--laca-danger, #ef4444)';
            }
        } else {
            $statusTitle = __('Website chưa được kiểm tra sức khoẻ', 'laca');
            $statusBadge = __('Chưa kiểm tra', 'laca');
            $badgeClass  = 'laca-badge--neutral';
            $circleColor = 'var(--laca-border, #475569)';
        }

        $todos = $this->collectTodos($audit);
        $todoCount = count($todos);

        $todoSummary = $todoCount > 0
            ? sprintf(__('Còn %d việc nên làm để website an toàn và chạy nhanh hơn.', 'laca'), $todoCount)
            : __('Không còn việc nào cần xử lý. Website đang ở trạng thái tốt.', 'laca');

        // Circle metrics
        $radius = 48;
        $circumference = 2 * M_PI * $radius;
        $scoreVal = $score !== null ? max(0, min(100, $score)) : 0;
        $dashoffset = $circumference - ($circumference * ($scoreVal / 100));

        // 4 KPI data
        $securityLayers = $this->getSecurityLayers();
        $emailKpi       = $this->getEmailKpi();
        $dbCleanerKpi   = $this->getDbCleanerKpi();
        $updatesKpi     = $this->getUpdatesKpi();
        ?>
        <div class="laca-dashboard-hero__top">
            <div class="laca-dashboard-hero__intro">
                <div class="laca-dashboard-hero__greeting">
                    <?php echo esc_html(sprintf(__('Xin chào, %s', 'laca'), $displayName)); ?>
                </div>
                <h1 class="laca-dashboard-hero__title"><?php echo esc_html($statusTitle); ?></h1>
                <p class="laca-dashboard-hero__subtitle"><?php echo esc_html($todoSummary); ?></p>
                <div class="laca-dashboard-hero__actions">
                    <?php if ($todoCount > 0): ?>
                        <a href="#laca-dashboard-todo" class="laca-btn laca-btn--solid laca-btn--sm laca-btn--hero-action">
                            <?php echo esc_html__('Xem việc nên làm ↓', 'laca'); ?>
                        </a>
                    <?php endif; ?>
                    <button type="button" id="btn-refresh-audit" class="laca-btn laca-btn--outline laca-btn--sm laca-btn--hero-outline">
                        <span class="dashicons dashicons-update laca-btn-icon" aria-hidden="true"></span>
                        <span class="laca-btn-text"><?php echo esc_html__('Kiểm tra lại', 'laca'); ?></span>
                    </button>
                </div>
            </div>

            <div class="laca-dashboard-hero__score">
                <div class="laca-score-circle" aria-label="<?php echo esc_attr(sprintf(__('Điểm sức khoẻ %s trên 100', 'laca'), $score !== null ? $score : '0')); ?>">
                    <svg viewBox="0 0 120 120" class="laca-score-circle__svg">
                        <circle class="laca-score-circle__track" cx="60" cy="60" r="<?php echo $radius; ?>" />
                        <circle class="laca-score-circle__bar" cx="60" cy="60" r="<?php echo $radius; ?>"
                                stroke="<?php echo esc_attr($circleColor); ?>"
                                stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                                stroke-dashoffset="<?php echo esc_attr($dashoffset); ?>" />
                    </svg>
                    <div class="laca-score-circle__content">
                        <span class="laca-score-circle__number"><?php echo $score !== null ? (int) $score : '—'; ?></span>
                        <span class="laca-score-circle__label"><?php echo esc_html__('Điểm sức khoẻ', 'laca'); ?></span>
                        <span class="laca-badge <?php echo esc_attr($badgeClass); ?> laca-score-circle__badge">
                            <?php echo esc_html($statusBadge); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="laca-dashboard-hero__kpis">
            <!-- KPI 1: Lớp bảo mật -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-security')); ?>" class="laca-kpi-card">
                <div class="laca-kpi-card__header">
                    <span class="dashicons dashicons-shield laca-kpi-card__icon" aria-hidden="true"></span>
                    <span class="laca-kpi-card__title"><?php echo esc_html__('Lớp bảo mật', 'laca'); ?></span>
                </div>
                <div class="laca-kpi-card__value">
                    <span><?php echo esc_html($securityLayers['count'] . '/' . $securityLayers['total']); ?></span>
                    <span class="laca-badge <?php echo esc_attr($securityLayers['badge_class']); ?> laca-kpi-card__badge">
                        <?php echo esc_html($securityLayers['badge_text']); ?>
                    </span>
                </div>
                <div class="laca-kpi-card__bars" aria-hidden="true">
                    <?php for ($i = 0; $i < $securityLayers['total']; $i++): ?>
                        <span class="laca-kpi-card__bar <?php echo $i < $securityLayers['count'] ? 'is-active' : ''; ?>"></span>
                    <?php endfor; ?>
                </div>
            </a>

            <!-- KPI 2: Gửi email -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-admin')); ?>" class="laca-kpi-card">
                <div class="laca-kpi-card__header">
                    <span class="dashicons dashicons-email-alt laca-kpi-card__icon" aria-hidden="true"></span>
                    <span class="laca-kpi-card__title"><?php echo esc_html__('Gửi email', 'laca'); ?></span>
                </div>
                <div class="laca-kpi-card__value">
                    <span><?php echo esc_html($emailKpi['status']); ?></span>
                </div>
                <div class="laca-kpi-card__sub"><?php echo esc_html($emailKpi['sub']); ?></div>
            </a>

            <!-- KPI 3: Dọn dẹp tự động -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-db-cleaner')); ?>" class="laca-kpi-card">
                <div class="laca-kpi-card__header">
                    <span class="dashicons dashicons-database laca-kpi-card__icon" aria-hidden="true"></span>
                    <span class="laca-kpi-card__title"><?php echo esc_html__('Dọn dẹp tự động', 'laca'); ?></span>
                </div>
                <div class="laca-kpi-card__value">
                    <span><?php echo esc_html($dbCleanerKpi['status']); ?></span>
                </div>
                <div class="laca-kpi-card__sub"><?php echo esc_html($dbCleanerKpi['sub']); ?></div>
            </a>

            <!-- KPI 4: Cập nhật -->
            <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="laca-kpi-card">
                <div class="laca-kpi-card__header">
                    <span class="dashicons dashicons-update laca-kpi-card__icon" aria-hidden="true"></span>
                    <span class="laca-kpi-card__title"><?php echo esc_html__('Cập nhật hệ thống', 'laca'); ?></span>
                </div>
                <div class="laca-kpi-card__value">
                    <span><?php echo esc_html($updatesKpi['status']); ?></span>
                </div>
                <div class="laca-kpi-card__sub"><?php echo esc_html($updatesKpi['sub']); ?></div>
            </a>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function renderTodoInner(?array $audit): string
    {
        ob_start();
        $todos = $this->collectTodos($audit);

        if ($audit === null):
            ?>
            <div class="laca-empty">
                <span class="dashicons dashicons-shield laca-empty__icon" aria-hidden="true"></span>
                <p class="laca-empty__description"><?php echo esc_html__('Chưa có kết quả kiểm tra sức khoẻ hệ thống.', 'laca'); ?></p>
                <button type="button" class="laca-btn laca-btn--solid laca-btn--sm js-laca-run-audit-now">
                    <?php echo esc_html__('Kiểm tra ngay', 'laca'); ?>
                </button>
            </div>
            <?php
        elseif (empty($todos)):
            ?>
            <div class="laca-empty">
                <span class="dashicons dashicons-yes-alt laca-empty__icon laca-text-success" aria-hidden="true"></span>
                <p class="laca-empty__description"><?php echo esc_html__('Không còn việc nào cần làm. Website đang ở trạng thái tốt!', 'laca'); ?></p>
            </div>
            <?php
        else:
            ?>
            <div class="laca-todo-list">
                <?php foreach ($todos as $t):
                    $iconClass = $t['level'] === 'danger' ? 'dashicons-warning laca-todo-icon--danger' : 'dashicons-info laca-todo-icon--warning';
                ?>
                    <div class="laca-todo-item">
                        <div class="laca-todo-item__icon-wrap">
                            <span class="dashicons <?php echo esc_attr($iconClass); ?>" aria-hidden="true"></span>
                        </div>
                        <div class="laca-todo-item__content">
                            <?php // Tiêu đề audit mô tả trạng thái MONG MUỐN ("display_errors tắt") nên hiện mô tả vấn đề thực tế làm dòng chính ?>
                            <div class="laca-todo-item__title"><?php echo esc_html(!empty($t['desc']) ? $t['desc'] : $t['title']); ?></div>
                        </div>
                        <div class="laca-todo-item__action">
                            <a href="<?php echo esc_url($t['url']); ?>" class="laca-btn laca-btn--outline laca-btn--xs">
                                <?php echo esc_html__('Xử lý', 'laca'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        endif;

        return (string) ob_get_clean();
    }

    public function renderStorageInner(?array $storage): string
    {
        ob_start();
        if ($storage === null):
            ?>
            <div class="laca-empty">
                <span class="dashicons dashicons-database laca-empty__icon" aria-hidden="true"></span>
                <p class="laca-empty__description"><?php echo esc_html__('Chưa có số liệu dung lượng lưu trữ.', 'laca'); ?></p>
                <button type="button" class="laca-btn laca-btn--solid laca-btn--sm" data-laca-storage-refresh="1">
                    <?php echo esc_html__('Tính ngay', 'laca'); ?>
                </button>
            </div>
            <?php
        else:
            $totalBytes = (int) ($storage['total_bytes'] ?? 0);
            $uploadsBytes = (int) ($storage['uploads_bytes'] ?? 0);
            $dbBytes = (int) ($storage['db_bytes'] ?? 0);
            $wpContentBytes = (int) ($storage['wp_content_bytes'] ?? 0);
            $sourceBytes = max(0, $wpContentBytes - $uploadsBytes);

            $sourcePct = $totalBytes > 0 ? round(($sourceBytes / $totalBytes) * 100, 1) : 0;
            $mediaPct  = $totalBytes > 0 ? round(($uploadsBytes / $totalBytes) * 100, 1) : 0;
            $dbPct     = $totalBytes > 0 ? round(($dbBytes / $totalBytes) * 100, 1) : 0;
            ?>
            <div class="laca-dashboard-storage-overview">
                <div class="laca-dashboard-storage-total">
                    <span class="laca-dashboard-storage-total__label"><?php echo esc_html__('Tổng dung lượng', 'laca'); ?></span>
                    <span class="laca-dashboard-storage-total__val"><?php echo esc_html($storage['total_formatted'] ?? size_format($totalBytes, 2)); ?></span>
                </div>

                <!-- Stacked Progress Bar -->
                <div class="laca-stacked-bar" role="progressbar" aria-label="<?php echo esc_attr__('Phân bổ dung lượng', 'laca'); ?>">
                    <span class="laca-stacked-bar__seg laca-stacked-bar__seg--code" style="width: <?php echo esc_attr($sourcePct); ?>%;" title="<?php echo esc_attr(sprintf(__('Mã nguồn: %s%%', 'laca'), $sourcePct)); ?>"></span>
                    <span class="laca-stacked-bar__seg laca-stacked-bar__seg--media" style="width: <?php echo esc_attr($mediaPct); ?>%;" title="<?php echo esc_attr(sprintf(__('Media: %s%%', 'laca'), $mediaPct)); ?>"></span>
                    <span class="laca-stacked-bar__seg laca-stacked-bar__seg--db" style="width: <?php echo esc_attr($dbPct); ?>%;" title="<?php echo esc_attr(sprintf(__('Database: %s%%', 'laca'), $dbPct)); ?>"></span>
                </div>

                <!-- Legend -->
                <div class="laca-dashboard-storage-legend">
                    <div class="laca-dashboard-storage-legend__item">
                        <span class="laca-dashboard-storage-legend__dot laca-dashboard-storage-legend__dot--code"></span>
                        <div class="laca-dashboard-storage-legend__text">
                            <span class="laca-dashboard-storage-legend__name"><?php echo esc_html__('Mã nguồn (Plugins + Themes)', 'laca'); ?></span>
                            <span class="laca-dashboard-storage-legend__val"><?php echo esc_html(size_format($sourceBytes, 2) . " ($sourcePct%)"); ?></span>
                        </div>
                    </div>
                    <div class="laca-dashboard-storage-legend__item">
                        <span class="laca-dashboard-storage-legend__dot laca-dashboard-storage-legend__dot--media"></span>
                        <div class="laca-dashboard-storage-legend__text">
                            <span class="laca-dashboard-storage-legend__name"><?php echo esc_html__('Media (Uploads)', 'laca'); ?></span>
                            <span class="laca-dashboard-storage-legend__val"><?php echo esc_html(size_format($uploadsBytes, 2) . " ($mediaPct%)"); ?></span>
                        </div>
                    </div>
                    <div class="laca-dashboard-storage-legend__item">
                        <span class="laca-dashboard-storage-legend__dot laca-dashboard-storage-legend__dot--db"></span>
                        <div class="laca-dashboard-storage-legend__text">
                            <span class="laca-dashboard-storage-legend__name"><?php echo esc_html__('Cơ sở dữ liệu (MySQL)', 'laca'); ?></span>
                            <span class="laca-dashboard-storage-legend__val"><?php echo esc_html($storage['db_formatted'] . " ($dbPct%)"); ?></span>
                        </div>
                    </div>
                </div>

                <div class="laca-dashboard-storage-meta">
                    <span class="laca-dashboard-storage-meta__time">
                        <?php echo esc_html(sprintf(__('Cập nhật lúc %s', 'laca'), $storage['updated_at'] ?? '')); ?>
                    </span>
                    <button type="button" class="laca-btn laca-btn--ghost laca-btn--xs" data-laca-storage-refresh="1">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <span><?php echo esc_html__('Tính lại', 'laca'); ?></span>
                    </button>
                </div>
            </div>
            <?php
        endif;

        return (string) ob_get_clean();
    }

    public function renderFeaturesGrid(): string
    {
        ob_start();
        $features = [
            [
                'title' => __('Bảo mật', 'laca'),
                'desc'  => __('Khoá IP, URL đăng nhập riêng, 2FA, honeypot, security headers, quét mã độc.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-security'),
                'icon'  => 'dashicons-shield',
            ],
            [
                'title' => __('Tối ưu tốc độ', 'laca'),
                'desc'  => __('Tải ảnh chậm, giảm Heartbeat, gỡ Emoji/oEmbed/jQuery Migrate, giới hạn revision.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-tools'),
                'icon'  => 'dashicons-performance',
            ],
            [
                'title' => __('Dọn dẹp Database', 'laca'),
                'desc'  => __('Dọn revision, bản nháp tự động, thùng rác, spam và lịch dọn tự động.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-db-cleaner'),
                'icon'  => 'dashicons-database',
            ],
            [
                'title' => __('Hệ thống & dung lượng', 'laca'),
                'desc'  => __('Thông số máy chủ, giới hạn PHP, dung lượng từng phần, file lớn.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-system'),
                'icon'  => 'dashicons-admin-tools',
            ],
            [
                'title' => __('Gửi mail & Nhật ký', 'laca'),
                'desc'  => __('Cấu hình SMTP và xem danh sách email hệ thống đã gửi.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-email-log'),
                'icon'  => 'dashicons-email-alt',
            ],
            [
                'title' => __('Sao lưu cấu hình', 'laca'),
                'desc'  => __('Xuất/nhập toàn bộ cấu hình (Laca Admin + SEO) để dựng site mới nhanh.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-config'),
                'icon'  => 'dashicons-backup',
            ],
            [
                'title' => __('Form liên hệ', 'laca'),
                'desc'  => __('Tạo form liên hệ, tuỳ chỉnh các trường và xem tin nhắn khách gửi.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-contact-forms'),
                'icon'  => 'dashicons-feedback',
            ],
            [
                'title' => __('Laca SEO', 'laca'),
                'desc'  => __('Tối ưu thẻ meta, sitemap XML, kiểm tra điểm SEO bài viết và chuyển hướng.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-seo'),
                'icon'  => 'dashicons-search',
            ],
            [
                'title' => __('Bảo trì website', 'laca'),
                'desc'  => __('Bật chế độ bảo trì, tuỳ biến trang thông báo và giới hạn IP xem site.', 'laca'),
                'url'   => admin_url('admin.php?page=laca-tools#maintenance'),
                'icon'  => 'dashicons-hammer',
            ],
        ];

        foreach ($features as $f):
        ?>
            <a href="<?php echo esc_url($f['url']); ?>" class="laca-card laca-feature-card">
                <div class="laca-feature-card__icon-wrap">
                    <span class="dashicons <?php echo esc_attr($f['icon']); ?>" aria-hidden="true"></span>
                </div>
                <div class="laca-feature-card__body">
                    <h3 class="laca-feature-card__title"><?php echo esc_html($f['title']); ?></h3>
                    <p class="laca-feature-card__desc"><?php echo esc_html($f['desc']); ?></p>
                </div>
                <div class="laca-feature-card__link">
                    <span><?php echo esc_html__('Mở →', 'laca'); ?></span>
                </div>
            </a>
        <?php
        endforeach;

        return (string) ob_get_clean();
    }

    public function renderHostingGrid(): string
    {
        ob_start();
        $phpVer = PHP_VERSION;
        $wpVer  = get_bloginfo('version');
        // Giới hạn PHP thực tế; WP_MEMORY_LIMIT (mặc định 40M) chỉ nâng khi PHP thấp hơn nên không phản ánh giới hạn thật
        $memLimit = (string) ini_get('memory_limit');
        $maxUpload = size_format(wp_max_upload_size());
        $isSsl = is_ssl() || str_starts_with(home_url(), 'https://');
        $debugOn = defined('WP_DEBUG') && WP_DEBUG;

        $items = [
            [
                'label' => 'PHP',
                'val'   => $phpVer,
                'icon'  => 'dashicons-media-code',
                'class' => version_compare($phpVer, '8.1', '>=') ? 'laca-text-success' : 'laca-text-warning',
            ],
            [
                'label' => 'WORDPRESS',
                'val'   => $wpVer,
                'icon'  => 'dashicons-wordpress',
                'class' => '',
            ],
            [
                'label' => 'MEMORY LIMIT',
                'val'   => $memLimit,
                'icon'  => 'dashicons-dashboard',
                'class' => '',
            ],
            [
                'label' => 'UPLOAD TỐI ĐA',
                'val'   => $maxUpload,
                'icon'  => 'dashicons-upload',
                'class' => '',
            ],
            [
                'label' => 'HTTPS / SSL',
                'val'   => $isSsl ? __('Đã bật', 'laca') : __('Chưa bật', 'laca'),
                'icon'  => 'dashicons-lock',
                'class' => $isSsl ? 'laca-text-success' : 'laca-text-danger',
            ],
            [
                'label' => 'WP_DEBUG',
                'val'   => $debugOn ? __('Bật', 'laca') : __('Tắt', 'laca'),
                'icon'  => 'dashicons-admin-tools',
                'class' => $debugOn ? 'laca-text-warning' : 'laca-text-success',
            ],
        ];

        foreach ($items as $item):
        ?>
            <div class="laca-hosting-item">
                <span class="dashicons <?php echo esc_attr($item['icon']); ?> laca-hosting-item__icon" aria-hidden="true"></span>
                <div class="laca-hosting-item__meta">
                    <span class="laca-hosting-item__label"><?php echo esc_html($item['label']); ?></span>
                    <span class="laca-hosting-item__val <?php echo esc_attr($item['class']); ?>"><?php echo esc_html($item['val']); ?></span>
                </div>
            </div>
        <?php
        endforeach;

        return (string) ob_get_clean();
    }

    private function collectTodos(?array $audit): array
    {
        $todos = [];

        // 1. Từ audit checks (nếu có cache)
        if (is_array($audit) && !empty($audit['groups'])) {
            $failItems = [];
            $warnItems = [];
            foreach ($audit['groups'] as $cat => $checksList) {
                foreach ($checksList as $c) {
                    if ($c['status'] === 'fail') {
                        $failItems[] = [
                            'id'    => $c['id'],
                            'title' => $c['title'],
                            'desc'  => $c['desc'],
                            'level' => 'danger',
                            'url'   => $this->mapAuditUrl($c['id'], $c['fix'] ?? ''),
                        ];
                    } elseif ($c['status'] === 'warn') {
                        $warnItems[] = [
                            'id'    => $c['id'],
                            'title' => $c['title'],
                            'desc'  => $c['desc'],
                            'level' => 'warning',
                            'url'   => $this->mapAuditUrl($c['id'], $c['fix'] ?? ''),
                        ];
                    }
                }
            }
            // Ưu tiên fail rồi đến warn
            $todos = array_merge($failItems, $warnItems);
        }

        // 2. Kiểm tra thêm ngoài audit: Dọn dẹp tự động
        $sched = get_option(DatabaseCleaner::SCHEDULE_OPTION, []);
        if (empty($sched['enabled'])) {
            $todos[] = [
                'id'    => 'db_cleaner_off',
                'title' => __('Lịch dọn dẹp Database tự động đang tắt', 'laca'),
                'desc'  => __('Nên bật lịch dọn dẹp tự động hằng ngày hoặc hằng tuần để tránh DB phình to do revisions và spam.', 'laca'),
                'level' => 'warning',
                'url'   => admin_url('admin.php?page=laca-db-cleaner'),
            ];
        }

        // 3. Kiểm tra thêm ngoài audit: Cấu hình SMTP
        $useSmtp = get_option('_use_smtp') === 'yes';
        $smtpHost = get_option('_smtp_host', '');
        if (!$useSmtp || empty($smtpHost)) {
            $todos[] = [
                'id'    => 'smtp_not_configured',
                'title' => __('Chưa cấu hình gửi email qua SMTP', 'laca'),
                'desc'  => __('Website đang dùng hàm gửi mail mặc định, dễ bị rơi vào thư rác hoặc bị chặn gửi.', 'laca'),
                'level' => 'warning',
                'url'   => admin_url('admin.php?page=laca-admin'),
            ];
        }

        return array_slice($todos, 0, 6);
    }

    private function mapAuditUrl(string $id, string $fixUrl): string
    {
        if (!empty($fixUrl)) {
            return $fixUrl;
        }

        if (str_starts_with($id, 'header_')) {
            return admin_url('admin.php?page=laca-security&tab=hardening');
        }

        if (in_array($id, ['custom_login', '2fa', 'admin_user', 'admin_2fa', 'banned_username_exists', 'breached_check', 'app_passwords'], true)) {
            return admin_url('admin.php?page=laca-security&tab=login');
        }

        if (in_array($id, ['pending_updates', 'wp_version'], true)) {
            return admin_url('update-core.php');
        }

        if (in_array($id, ['php_version', 'display_errors', 'file_edit', 'wp_debug', 'wp_debug_log'], true)) {
            return admin_url('admin.php?page=laca-system&tab=server');
        }

        return admin_url('admin.php?page=laca-security&tab=audit');
    }

    private function getSecurityLayers(): array
    {
        $layers = [
            'login_guard'     => class_exists(LoginGuard::class),
            'login_honeypot'  => get_option('laca_login_honeypot', 'yes') === 'yes',
            'two_fa'          => (int) get_option('laca_2fa_master_enabled', 0) === 1,
            'custom_login'    => (int) get_option('laca_enable_custom_login', 0) === 1 && !empty(get_option('laca_login_slug', '')),
            'security_headers'=> false,
            'user_enum'       => class_exists(UserEnumerationGuard::class),
            'xmlrpc'          => !apply_filters('xmlrpc_enabled', true),
            'svg_guard'       => class_exists(SvgUploadGuard::class),
            'breached_pass'   => false,
        ];

        // Headers: ít nhất X-Frame và CSP
        $hdrs = get_option('laca_security_headers', []);
        if (!empty($hdrs['x_frame_options']) && !empty($hdrs['content_security_policy'])) {
            $layers['security_headers'] = true;
        }

        // Breached pass
        $lpCfg = class_exists(LoginPolicy::class) ? LoginPolicy::config() : [];
        if (($lpCfg['breached_check'] ?? 'admins') !== 'off') {
            $layers['breached_pass'] = true;
        }

        $active = count(array_filter($layers));
        $total  = count($layers);
        $pct    = round(($active / $total) * 100);

        if ($pct >= 75) {
            $badgeText  = __('Tốt', 'laca');
            $badgeClass = 'laca-badge--success';
        } elseif ($pct >= 40) {
            $badgeText  = __('Trung bình', 'laca');
            $badgeClass = 'laca-badge--warning';
        } else {
            $badgeText  = __('Yếu', 'laca');
            $badgeClass = 'laca-badge--danger';
        }

        return [
            'count'       => $active,
            'total'       => $total,
            'badge_text'  => $badgeText,
            'badge_class' => $badgeClass,
        ];
    }

    private function getEmailKpi(): array
    {
        $useSmtp = get_option('_use_smtp') === 'yes';
        $host    = get_option('_smtp_host', '');
        $user    = get_option('_smtp_username', '');

        if ($useSmtp && !empty($host) && !empty($user)) {
            return [
                'status' => __('Đã cấu hình SMTP', 'laca'),
                'sub'    => $host,
            ];
        }

        return [
            'status' => __('Chưa cấu hình', 'laca'),
            'sub'    => __('Dùng mail mặc định máy chủ', 'laca'),
        ];
    }

    private function getDbCleanerKpi(): array
    {
        $sched = get_option(DatabaseCleaner::SCHEDULE_OPTION, []);
        $enabled = !empty($sched['enabled']);
        $freq    = ($sched['frequency'] ?? 'daily') === 'weekly' ? __('hằng tuần', 'laca') : __('hằng ngày', 'laca');

        if ($enabled) {
            $next = wp_next_scheduled('laca_database_cleaner_cron');
            $sub  = $next ? sprintf(__('Lần chạy tới: %s', 'laca'), wp_date('H:i d/m', $next)) : __('Đang hoạt động', 'laca');
            return [
                'status' => sprintf(__('Đang bật · %s', 'laca'), $freq),
                'sub'    => $sub,
            ];
        }

        return [
            'status' => __('Đang tắt', 'laca'),
            'sub'    => __('Chưa có lịch chạy tự động', 'laca'),
        ];
    }

    private function getUpdatesKpi(): array
    {
        $core    = get_site_transient('update_core');
        $plugins = get_site_transient('update_plugins');
        $themes  = get_site_transient('update_themes');

        $cCount = 0;
        if (isset($core->updates) && is_array($core->updates)) {
            foreach ($core->updates as $u) {
                if (isset($u->response) && $u->response === 'upgrade') {
                    $cCount++;
                }
            }
        }
        $pCount = !empty($plugins->response) ? count((array) $plugins->response) : 0;
        $tCount = !empty($themes->response) ? count((array) $themes->response) : 0;
        $total  = $cCount + $pCount + $tCount;

        if ($total > 0) {
            return [
                'status' => sprintf(__('%d bản cập nhật', 'laca'), $total),
                'sub'    => sprintf(__('Core: %d · Plugin: %d · Theme: %d', 'laca'), $cCount, $pCount, $tCount),
            ];
        }

        return [
            'status' => __('Đã cập nhật đủ', 'laca'),
            'sub'    => __('Mọi thành phần đều mới nhất', 'laca'),
        ];
    }
}
