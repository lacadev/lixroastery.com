<?php

namespace App\Settings\LacaAdmin;

defined('ABSPATH') || exit;

/**
 * Renders static preview of 3 dashboard layout options for GSC + GA4 reports.
 * Accessible at admin.php?page=laca-dashboard-preview
 */
class DashboardPreviewPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            null, // Hidden page
            __('Xem trước bố cục Dashboard (GSC + GA4)', 'laca'),
            __('Dashboard Preview', 'laca'),
            'manage_options',
            'laca-dashboard-preview',
            [$this, 'render']
        );
    }

    public function enqueueAssets(): void
    {
        if (($_GET['page'] ?? '') !== 'laca-dashboard-preview') {
            return;
        }

        $stylePath = get_theme_file_path('dist/styles/laca-seo.css');
        $styleUri  = get_theme_file_uri('dist/styles/laca-seo.css');
        if (file_exists($stylePath)) {
            wp_enqueue_style('laca-seo-admin', $styleUri, ['dashicons'], (string) filemtime($stylePath));
        }
    }

    public function render(): void
    {
        $layout = strtoupper(sanitize_key($_GET['layout'] ?? 'A'));
        if (!in_array($layout, ['A', 'B', 'C'], true)) {
            $layout = 'A';
        }

        // Mock data
        $gsc = [
            'clicks'      => 1420,
            'impressions' => 28500,
            'ctr'         => 4.98,
            'position'    => 11.2,
            'queries'     => [
                ['query' => 'ca phe dac san da nang', 'clicks' => 312, 'impressions' => 4500, 'ctr' => 6.9, 'position' => 3.1],
                ['query' => 'lix roastery', 'clicks' => 285, 'impressions' => 1200, 'ctr' => 23.7, 'position' => 1.0],
                ['query' => 'mua ca phe hat arabica', 'clicks' => 145, 'impressions' => 3200, 'ctr' => 4.5, 'position' => 5.4],
                ['query' => 'gia ca phe rang xay nguyen chat', 'clicks' => 98, 'impressions' => 2100, 'ctr' => 4.6, 'position' => 7.8],
                ['query' => 'workshop pha che ca phe', 'clicks' => 76, 'impressions' => 1800, 'ctr' => 4.2, 'position' => 4.2],
            ],
            'pages'       => [
                ['url' => '/san-pham/ca-phe-lix-specialty/', 'clicks' => 480, 'impressions' => 7200],
                ['url' => '/journal/nghe-thuat-rang-ca-phe/', 'clicks' => 310, 'impressions' => 6100],
                ['url' => '/ve-chung-toi/', 'clicks' => 195, 'impressions' => 2400],
                ['url' => '/workshop/', 'clicks' => 120, 'impressions' => 1900],
            ],
        ];

        $ga = [
            'users'        => 3840,
            'sessions'     => 5260,
            'pageviews'    => 14320,
            'engagement'   => 68.4,
            'pages'        => [
                ['path' => '/', 'title' => 'Trang chủ | LIX Roastery', 'views' => 4820, 'users' => 2150],
                ['path' => '/shop/', 'title' => 'Cửa hàng | LIX Roastery', 'views' => 3450, 'users' => 1620],
                ['path' => '/journal/', 'title' => 'Tạp chí & Kiến thức Cà phê', 'views' => 2180, 'users' => 980],
                ['path' => '/contact/', 'title' => 'Liên hệ đặt hàng & Workshop', 'views' => 950, 'users' => 490],
            ],
            'channels'     => [
                ['name' => 'Organic Search', 'sessions' => 2840, 'share' => '54%'],
                ['name' => 'Direct', 'sessions' => 1450, 'share' => '27%'],
                ['name' => 'Social', 'sessions' => 620, 'share' => '12%'],
                ['name' => 'Referral', 'sessions' => 350, 'share' => '7%'],
            ],
        ];

        ?>
        <div class="wrap laca-admin-page laca-seo-dashboard" style="max-width: 1400px; margin: 20px auto;">
            <div style="background: #18181b; color: #fff; padding: 20px 24px; border-radius: 8px; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h1 style="color: #fff; margin: 0 0 6px 0; font-size: 20px; font-weight: 700;">
                            ✨ Xem trước Bố cục Báo cáo GSC & GA4 trên Dashboard
                        </h1>
                        <p style="margin: 0; color: #a1a1aa; font-size: 13.5px;">
                            Chọn 1 trong 3 phương án thiết kế bên dưới để nghiệm thu trước khi áp dụng vào Dashboard chính thức.
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-dashboard-preview&layout=A')); ?>" class="button <?php echo $layout === 'A' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600;">
                            Phương án A (Xếp dọc)
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-dashboard-preview&layout=B')); ?>" class="button <?php echo $layout === 'B' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600;">
                            Phương án B (2 Cột song song)
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-dashboard-preview&layout=C')); ?>" class="button <?php echo $layout === 'C' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600;">
                            Phương án C (Tab hợp nhất)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Notice giải thích phương án đang chọn -->
            <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 6px; background: #fff; border: 1px solid var(--laca-border-emphasized, #d4d4d8);">
                <?php if ($layout === 'A'): ?>
                    <strong>Phương án A: Bố cục Xếp dọc (Stacked Full Sections)</strong>
                    <p style="margin: 4px 0 0 0; color: #52525b; font-size: 13px;">
                        Google Search Console và Google Analytics 4 nằm ở 2 tầng card riêng biệt. Mỗi khu vực có 4 thẻ KPI lớn, biểu đồ xu hướng đơn sắc và 2 bảng chi tiết từ khóa / trang web. Rất thoáng và xem được toàn bộ thông số chi tiết mà không bị bó hẹp.
                    </p>
                <?php elseif ($layout === 'B'): ?>
                    <strong>Phương án B: Bố cục 2 Cột song song (Split Side-by-Side)</strong>
                    <p style="margin: 4px 0 0 0; color: #52525b; font-size: 13px;">
                        Màn hình desktop chia làm 2 cột: Cột trái Search Console (Organic Search) đối sánh trực tiếp với cột phải Analytics (Lưu lượng truy cập). Giúp so sánh nhanh hiệu quả SEO và chuyển đổi, bảng dữ liệu rút gọn top 5.
                    </p>
                <?php else: ?>
                    <strong>Phương án C: Bố cục Tab hợp nhất (Unified Tabbed Card)</strong>
                    <p style="margin: 4px 0 0 0; color: #52525b; font-size: 13px;">
                        Gom toàn bộ dữ liệu vào một Card duy nhất có 2 Tab chuyển đổi: "🔍 Google Search Console" và "📈 Google Analytics 4". Cực kỳ gọn gàng, Dashboard không bị dài, phù hợp cho người quản trị thích sự tinh tế tối giản.
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($layout === 'A'): ?>
                <!-- ==================== LAYOUT A: XẾP DỌC ==================== -->
                <!-- Khối GSC -->
                <div class="laca-seo-card" style="margin-bottom: 24px;">
                    <div class="laca-seo-card__header laca-seo-header-flex">
                        <div>
                            <h2 class="laca-seo-card__title">🔍 Google Search Console (28 ngày qua)</h2>
                            <p class="laca-seo-card__subtitle">Dữ liệu tìm kiếm tự nhiên từ Google Search cho tài khoản <code>sc-domain:lixroastery.com</code></p>
                        </div>
                        <span class="laca-badge" style="background: #f4f4f5; color: #18181b; padding: 4px 10px; border-radius: 4px; font-weight: 500; font-size: 12px; border: 1px solid #d4d4d8;">
                            Độ trễ số liệu: 3 ngày
                        </span>
                    </div>

                    <div class="laca-seo-stats-grid">
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-info"><span class="dashicons dashicons-chart-area"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">Tổng lượt click</span>
                                <strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($gsc['clicks']); ?></strong>
                            </div>
                        </div>
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-info"><span class="dashicons dashicons-visibility"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">Tổng lượt hiển thị</span>
                                <strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($gsc['impressions']); ?></strong>
                            </div>
                        </div>
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-percent"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">CTR trung bình</span>
                                <strong class="laca-seo-stat-card__value"><?php echo $gsc['ctr']; ?>%</strong>
                            </div>
                        </div>
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-performance"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">Vị trí trung bình</span>
                                <strong class="laca-seo-stat-card__value"><?php echo $gsc['position']; ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Biểu đồ đường SVG đơn sắc mô phỏng -->
                    <div style="margin: 20px 24px; padding: 16px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 600; font-size: 13px;">Xu hướng Click & Hiển thị</span>
                            <span style="font-size: 12px; color: #71717a;">— Đen: Clicks / — Xám: Hiển thị (÷10)</span>
                        </div>
                        <svg viewBox="0 0 800 120" style="width: 100%; height: 100px; overflow: visible;">
                            <line x1="0" y1="100" x2="800" y2="100" stroke="#e4e4e7" stroke-width="1" />
                            <line x1="0" y1="50" x2="800" y2="50" stroke="#f4f4f5" stroke-width="1" />
                            <!-- Line impressions (grey) -->
                            <polyline fill="none" stroke="#a1a1aa" stroke-width="2" stroke-dasharray="4" points="0,85 80,75 160,80 240,65 320,55 400,60 480,45 560,50 640,35 720,40 800,25" />
                            <!-- Line clicks (dark monochrome) -->
                            <polyline fill="none" stroke="#18181b" stroke-width="2.5" points="0,90 80,82 160,85 240,70 320,62 400,68 480,52 560,58 640,42 720,48 800,30" />
                        </svg>
                    </div>

                    <!-- 2 bảng chi tiết -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; padding: 0 24px 24px 24px;">
                        <div>
                            <h4 style="margin: 0 0 10px 0; font-size: 14px;">Top từ khóa tìm kiếm</h4>
                            <div class="laca-seo-table-responsive" style="border: 1px solid #e4e4e7; border-radius: 6px;">
                                <table class="wp-list-table widefat striped" style="margin: 0;">
                                    <thead><tr><th>Từ khóa</th><th style="text-align:right">Clicks</th><th style="text-align:right">Vị trí</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($gsc['queries'] as $q): ?>
                                            <tr><td><strong><?php echo esc_html($q['query']); ?></strong></td><td style="text-align:right"><?php echo $q['clicks']; ?></td><td style="text-align:right"><?php echo $q['position']; ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 10px 0; font-size: 14px;">Top trang có lượt click cao</h4>
                            <div class="laca-seo-table-responsive" style="border: 1px solid #e4e4e7; border-radius: 6px;">
                                <table class="wp-list-table widefat striped" style="margin: 0;">
                                    <thead><tr><th>Đường dẫn trang</th><th style="text-align:right">Clicks</th><th style="text-align:right">Hiển thị</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($gsc['pages'] as $p): ?>
                                            <tr><td><code><?php echo esc_html($p['url']); ?></code></td><td style="text-align:right"><?php echo $p['clicks']; ?></td><td style="text-align:right"><?php echo $p['impressions']; ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Khối GA4 -->
                <div class="laca-seo-card" style="margin-bottom: 24px;">
                    <div class="laca-seo-card__header laca-seo-header-flex">
                        <div>
                            <h2 class="laca-seo-card__title">📈 Google Analytics 4 (28 ngày qua)</h2>
                            <p class="laca-seo-card__subtitle">Dữ liệu truy cập thực tế & người dùng hoạt động từ thuộc tính GA4</p>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-small button-secondary">
                            Cấu hình GA4
                        </a>
                    </div>

                    <div class="laca-seo-stats-grid">
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-info"><span class="dashicons dashicons-admin-users"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">Người dùng hoạt động</span>
                                <strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($ga['users']); ?></strong>
                            </div>
                        </div>
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-info"><span class="dashicons dashicons-update"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">Số phiên (Sessions)</span>
                                <strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($ga['sessions']); ?></strong>
                            </div>
                        </div>
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-visibility"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">Lượt xem trang (Views)</span>
                                <strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($ga['pageviews']); ?></strong>
                            </div>
                        </div>
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__icon is-success"><span class="dashicons dashicons-yes-alt"></span></div>
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label">Tỷ lệ tương tác</span>
                                <strong class="laca-seo-stat-card__value"><?php echo $ga['engagement']; ?>%</strong>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; padding: 20px 24px 24px 24px;">
                        <div>
                            <h4 style="margin: 0 0 10px 0; font-size: 14px;">Top trang có nhiều lượt xem nhất</h4>
                            <div class="laca-seo-table-responsive" style="border: 1px solid #e4e4e7; border-radius: 6px;">
                                <table class="wp-list-table widefat striped" style="margin: 0;">
                                    <thead><tr><th>Trang</th><th style="text-align:right">Lượt xem</th><th style="text-align:right">User</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($ga['pages'] as $p): ?>
                                            <tr><td><strong><?php echo esc_html($p['title']); ?></strong><br><small style="color:#71717a"><?php echo esc_html($p['path']); ?></small></td><td style="text-align:right"><?php echo number_format_i18n($p['views']); ?></td><td style="text-align:right"><?php echo number_format_i18n($p['users']); ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 10px 0; font-size: 14px;">Kênh truy cập hàng đầu</h4>
                            <div class="laca-seo-table-responsive" style="border: 1px solid #e4e4e7; border-radius: 6px;">
                                <table class="wp-list-table widefat striped" style="margin: 0;">
                                    <thead><tr><th>Kênh (Channel)</th><th style="text-align:right">Phiên</th><th style="text-align:right">Tỷ lệ</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($ga['channels'] as $c): ?>
                                            <tr><td><strong><?php echo esc_html($c['name']); ?></strong></td><td style="text-align:right"><?php echo number_format_i18n($c['sessions']); ?></td><td style="text-align:right"><?php echo $c['share']; ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($layout === 'B'): ?>
                <!-- ==================== LAYOUT B: 2 CỘT SONG SONG (ĐÃ TỐI ƯU THEO YÊU CẦU) ==================== -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 24px; align-items: start;">
                    <!-- CỘT TRÁI: GOOGLE SEARCH CONSOLE -->
                    <div class="laca-seo-card" style="margin-bottom: 0;">
                        <div class="laca-seo-card__header laca-seo-header-flex" style="padding: 16px 20px;">
                            <div>
                                <h2 class="laca-seo-card__title" style="font-size: 16px;">🔍 Google Search Console</h2>
                                <p class="laca-seo-card__subtitle" style="font-size: 12.5px;">Bên ngoài trang — Hành vi tìm kiếm (28 ngày qua)</p>
                            </div>
                            <span class="laca-badge" style="background: #f4f4f5; color: #18181b; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; border: 1px solid #d4d4d8;">
                                Organic Search
                            </span>
                        </div>

                        <!-- 4 thẻ KPI có delta % so với kỳ trước -->
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px 20px;">
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">Tổng lượt Clicks</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;"><?php echo number_format_i18n($gsc['clicks']); ?></div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+12.4%</span>
                                    <span style="color: #71717a;">vs 28 ngày trước</span>
                                </div>
                            </div>
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">Tổng lượt Hiển thị</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;"><?php echo number_format_i18n($gsc['impressions']); ?></div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+18.2%</span>
                                    <span style="color: #71717a;">vs 28 ngày trước</span>
                                </div>
                            </div>
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">CTR trung bình</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;"><?php echo $gsc['ctr']; ?>%</div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+0.5%</span>
                                    <span style="color: #71717a;">vs 28 ngày trước</span>
                                </div>
                            </div>
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">Vị trí trung bình</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;"><?php echo $gsc['position']; ?></div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+1.8</span>
                                    <span style="color: #71717a;">thứ hạng tăng</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bảng 1: Top từ khóa kèm CTR và Vị trí trung bình -->
                        <div style="padding: 0 20px 16px 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <h4 style="margin: 0; font-size: 13.5px; font-weight: 600;">Top từ khóa tìm kiếm</h4>
                                <span style="font-size: 11px; color: #71717a;">Vùng cơ hội: Top 4 – 10</span>
                            </div>
                            <div class="laca-seo-table-responsive" style="border: 1px solid #e4e4e7; border-radius: 6px;">
                                <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                                    <thead>
                                        <tr>
                                            <th>Từ khóa truy vấn</th>
                                            <th style="text-align: right; width: 60px;">Clicks</th>
                                            <th style="text-align: right; width: 60px;">CTR</th>
                                            <th style="text-align: right; width: 85px;">Vị trí TB</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gsc['queries'] as $q): ?>
                                            <?php
                                            $pos = (float) $q['position'];
                                            $isOpportunity = ($pos >= 4.0 && $pos <= 10.0);
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($q['query']); ?></strong>
                                                    <?php if ($isOpportunity): ?>
                                                        <span style="background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 600; padding: 1px 5px; border-radius: 3px; margin-left: 4px;">
                                                            Cơ hội
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right; font-weight: 600;"><?php echo $q['clicks']; ?></td>
                                                <td style="text-align: right; color: #52525b;"><?php echo $q['ctr']; ?>%</td>
                                                <td style="text-align: right;">
                                                    <span style="display: inline-block; padding: 1px 6px; border-radius: 4px; font-weight: 600; font-size: 11.5px; background: <?php echo $pos <= 3.0 ? '#dcfce7; color: #166534;' : ($isOpportunity ? '#fef3c7; color: #92400e;' : '#f4f4f5; color: #71717a;'); ?>">
                                                        #<?php echo $pos; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Bảng 2: Top trang đích nhận click từ Google (Landing Pages) -->
                        <div style="padding: 0 20px 20px 20px;">
                            <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600;">Top trang đích nhận click từ Google (Organic Landing Pages)</h4>
                            <div class="laca-seo-table-responsive" style="border: 1px solid #e4e4e7; border-radius: 6px;">
                                <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                                    <thead>
                                        <tr>
                                            <th>Trang đích</th>
                                            <th style="text-align: right; width: 75px;">Clicks</th>
                                            <th style="text-align: right; width: 85px;">Hiển thị</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gsc['pages'] as $p): ?>
                                            <tr>
                                                <td><code style="font-size: 12px;"><?php echo esc_html($p['url']); ?></code></td>
                                                <td style="text-align: right; font-weight: 600;"><?php echo number_format_i18n($p['clicks']); ?></td>
                                                <td style="text-align: right; color: #52525b;"><?php echo number_format_i18n($p['impressions']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- CỘT PHẢI: GOOGLE ANALYTICS 4 -->
                    <div class="laca-seo-card" style="margin-bottom: 0;">
                        <div class="laca-seo-card__header laca-seo-header-flex" style="padding: 16px 20px;">
                            <div>
                                <h2 class="laca-seo-card__title" style="font-size: 16px;">📈 Google Analytics 4</h2>
                                <p class="laca-seo-card__subtitle" style="font-size: 12.5px;">Bên trong trang — Trải nghiệm & Chuyển đổi (28 ngày qua)</p>
                            </div>
                            <span class="laca-badge" style="background: #f4f4f5; color: #18181b; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; border: 1px solid #d4d4d8;">
                                Traffic & Events
                            </span>
                        </div>

                        <!-- 4 thẻ KPI có Thời gian tương tác TB và Tỷ lệ chuyển đổi chung -->
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px 20px;">
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">Người dùng hoạt động</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;"><?php echo number_format_i18n($ga['users']); ?></div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+14.1%</span>
                                    <span style="color: #71717a;">vs 28 ngày trước</span>
                                </div>
                            </div>
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">Số phiên truy cập</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;"><?php echo number_format_i18n($ga['sessions']); ?></div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+9.8%</span>
                                    <span style="color: #71717a;">vs 28 ngày trước</span>
                                </div>
                            </div>
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">Thời gian tương tác TB</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;">1m 45s</div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+15 giây</span>
                                    <span style="color: #71717a;">đọc sâu hơn</span>
                                </div>
                            </div>
                            <div style="background: #fafafa; border: 1px solid var(--laca-border-emphasized, #d4d4d8); padding: 12px 14px; border-radius: 6px;">
                                <div style="color: #71717a; font-size: 12px; margin-bottom: 2px;">Tỷ lệ chuyển đổi chung</div>
                                <div style="font-size: 22px; font-weight: 700; color: #18181b;">4.7%</div>
                                <div style="font-size: 11.5px; margin-top: 4px;">
                                    <span style="color: #10b981; font-weight: 600;">+0.8%</span>
                                    <span style="color: #71717a;">(248 Key Events)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Kênh thu hút lượng truy cập (Session Default Channel Grouping) -->
                        <div style="padding: 0 20px 16px 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <h4 style="margin: 0; font-size: 13.5px; font-weight: 600;">Kênh thu hút truy cập (Channel Grouping)</h4>
                                <span style="font-size: 11px; color: #71717a;">5,260 phiên</span>
                            </div>

                            <!-- Thanh phân bổ kênh dạng Stacked Segmented Bar -->
                            <div style="height: 12px; border-radius: 6px; overflow: hidden; display: flex; width: 100%; margin-bottom: 10px; background: #e4e4e7;">
                                <div style="width: 54%; background: #18181b;" title="Organic Search: 54%"></div>
                                <div style="width: 27%; background: #52525b;" title="Direct: 27%"></div>
                                <div style="width: 12%; background: #a1a1aa;" title="Social: 12%"></div>
                                <div style="width: 7%; background: #d4d4d8;" title="Referral: 7%"></div>
                            </div>

                            <!-- Chú thích chi tiết kênh -->
                            <div style="display: flex; gap: 14px; flex-wrap: wrap; font-size: 11.5px;">
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 9px; height: 9px; border-radius: 2px; background: #18181b; display: inline-block;"></span>
                                    <strong>Organic: 54%</strong> <span style="color: #71717a;">(2,840)</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 9px; height: 9px; border-radius: 2px; background: #52525b; display: inline-block;"></span>
                                    <strong>Direct: 27%</strong> <span style="color: #71717a;">(1,450)</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 9px; height: 9px; border-radius: 2px; background: #a1a1aa; display: inline-block;"></span>
                                    <strong>Social: 12%</strong> <span style="color: #71717a;">(620)</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 9px; height: 9px; border-radius: 2px; background: #d4d4d8; display: inline-block;"></span>
                                    <strong>Referral: 7%</strong> <span style="color: #71717a;">(350)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Khối Hành động mục tiêu / Chuyển đổi (Business KPI) -->
                        <div style="padding: 0 20px 16px 20px;">
                            <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600;">Hành động mục tiêu giá trị (Key Events / Conversions)</h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                                    <div style="font-size: 11.5px; color: #52525b;">Bấm Mua / Thêm giỏ (Cà phê)</div>
                                    <div style="font-size: 18px; font-weight: 700; color: #18181b; margin-top: 2px;">186 <span style="font-size: 12px; font-weight: normal; color: #71717a;">lượt</span></div>
                                    <small style="color: #10b981; font-weight: 600; font-size: 11px;">+18.5% so với kỳ trước</small>
                                </div>
                                <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                                    <div style="font-size: 11.5px; color: #52525b;">Gửi Form / Đặt lịch workshop</div>
                                    <div style="font-size: 18px; font-weight: 700; color: #18181b; margin-top: 2px;">62 <span style="font-size: 12px; font-weight: normal; color: #71717a;">lượt</span></div>
                                    <small style="color: #10b981; font-weight: 600; font-size: 11px;">6.5% tỷ lệ trên trang liên hệ</small>
                                </div>
                            </div>
                        </div>

                        <!-- Bảng Top trang xem nhiều kèm Thời gian tương tác TB -->
                        <div style="padding: 0 20px 20px 20px;">
                            <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600;">Top trang có lượt xem cao & Thời gian tương tác</h4>
                            <div class="laca-seo-table-responsive" style="border: 1px solid #e4e4e7; border-radius: 6px;">
                                <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                                    <thead>
                                        <tr>
                                            <th>Trang nội dung</th>
                                            <th style="text-align: right; width: 75px;">Lượt xem</th>
                                            <th style="text-align: right; width: 95px;">Tương tác TB</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Trang chủ | LIX Roastery</strong><br><small style="color: #71717a;">/</small></td>
                                            <td style="text-align: right; font-weight: 600;">4,820</td>
                                            <td style="text-align: right; color: #52525b;">1m 15s</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Cửa hàng Cà phê Đặc sản</strong><br><small style="color: #71717a;">/shop/</small></td>
                                            <td style="text-align: right; font-weight: 600;">3,450</td>
                                            <td style="text-align: right; color: #52525b;">2m 05s</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tạp chí: Nghệ thuật rang cà phê</strong><br><small style="color: #71717a;">/journal/</small></td>
                                            <td style="text-align: right; font-weight: 600;">2,180</td>
                                            <td style="text-align: right; color: #10b981; font-weight: 600;">2m 40s</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Liên hệ & Đặt lịch Workshop</strong><br><small style="color: #71717a;">/contact/</small></td>
                                            <td style="text-align: right; font-weight: 600;">950</td>
                                            <td style="text-align: right; color: #52525b;">1m 55s</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- ==================== LAYOUT C: TAB HỢP NHẤT ==================== -->
                <?php
                $tabActive = sanitize_key($_GET['tab'] ?? 'gsc');
                ?>
                <div class="laca-seo-card">
                    <div class="laca-seo-card__header laca-seo-header-flex" style="border-bottom: none; padding-bottom: 0;">
                        <div>
                            <h2 class="laca-seo-card__title">Báo cáo Dịch vụ Google</h2>
                            <p class="laca-seo-card__subtitle">Dữ liệu hiệu suất tìm kiếm và lưu lượng truy cập</p>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <select style="height: 32px; font-size: 13px;">
                                <option>28 ngày qua</option>
                                <option>7 ngày qua</option>
                                <option>90 ngày qua</option>
                            </select>
                        </div>
                    </div>

                    <!-- Nav Tab Pills -->
                    <div style="padding: 0 24px; margin-top: 16px; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                        <nav style="display: flex; gap: 8px; margin-bottom: -1px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-dashboard-preview&layout=C&tab=gsc')); ?>" style="padding: 8px 16px; font-weight: 600; text-decoration: none; border-bottom: 2px solid <?php echo $tabActive === 'gsc' ? '#18181b' : 'transparent'; ?>; color: <?php echo $tabActive === 'gsc' ? '#18181b' : '#71717a'; ?>;">
                                🔍 Google Search Console
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-dashboard-preview&layout=C&tab=ga')); ?>" style="padding: 8px 16px; font-weight: 600; text-decoration: none; border-bottom: 2px solid <?php echo $tabActive === 'ga' ? '#18181b' : 'transparent'; ?>; color: <?php echo $tabActive === 'ga' ? '#18181b' : '#71717a'; ?>;">
                                📈 Google Analytics 4
                            </a>
                        </nav>
                    </div>

                    <div style="padding: 24px;">
                        <?php if ($tabActive === 'gsc'): ?>
                            <div class="laca-seo-stats-grid" style="margin-bottom: 24px;">
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">Clicks</span><strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($gsc['clicks']); ?></strong></div></div>
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">Hiển thị</span><strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($gsc['impressions']); ?></strong></div></div>
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">CTR</span><strong class="laca-seo-stat-card__value"><?php echo $gsc['ctr']; ?>%</strong></div></div>
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">Vị trí</span><strong class="laca-seo-stat-card__value"><?php echo $gsc['position']; ?></strong></div></div>
                            </div>
                            <table class="wp-list-table widefat striped">
                                <thead><tr><th>Từ khóa tìm kiếm hàng đầu</th><th style="text-align:right">Clicks</th><th style="text-align:right">Hiển thị</th><th style="text-align:right">CTR</th><th style="text-align:right">Vị trí</th></tr></thead>
                                <tbody>
                                    <?php foreach ($gsc['queries'] as $q): ?>
                                        <tr><td><strong><?php echo esc_html($q['query']); ?></strong></td><td style="text-align:right"><?php echo $q['clicks']; ?></td><td style="text-align:right"><?php echo $q['impressions']; ?></td><td style="text-align:right"><?php echo $q['ctr']; ?>%</td><td style="text-align:right"><?php echo $q['position']; ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="laca-seo-stats-grid" style="margin-bottom: 24px;">
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">Người dùng</span><strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($ga['users']); ?></strong></div></div>
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">Số phiên</span><strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($ga['sessions']); ?></strong></div></div>
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">Lượt xem trang</span><strong class="laca-seo-stat-card__value"><?php echo number_format_i18n($ga['pageviews']); ?></strong></div></div>
                                <div class="laca-seo-stat-card"><div class="laca-seo-stat-card__info"><span class="laca-seo-stat-card__label">Tương tác</span><strong class="laca-seo-stat-card__value"><?php echo $ga['engagement']; ?>%</strong></div></div>
                            </div>
                            <table class="wp-list-table widefat striped">
                                <thead><tr><th>Trang xem nhiều</th><th style="text-align:right">Lượt xem trang</th><th style="text-align:right">Người dùng</th></tr></thead>
                                <tbody>
                                    <?php foreach ($ga['pages'] as $p): ?>
                                        <tr><td><strong><?php echo esc_html($p['title']); ?></strong><br><small style="color:#71717a"><?php echo esc_html($p['path']); ?></small></td><td style="text-align:right"><?php echo number_format_i18n($p['views']); ?></td><td style="text-align:right"><?php echo number_format_i18n($p['users']); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
