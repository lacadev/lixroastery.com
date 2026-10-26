<?php
/**
 * Preview page for Google Report Site Kit Parity layouts (Option A vs Option B).
 * Accessible at wp-admin/admin.php?page=laca-google-report-preview
 */

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'lacadev'));
}

$variant = isset($_GET['variant']) && strtoupper((string) $_GET['variant']) === 'B' ? 'B' : 'A';
$range = sanitize_key($_GET['range'] ?? '28d');
$activeTab = sanitize_key($_GET['tab'] ?? 'gsc');

$siteUrl = home_url();
?>
<div class="wrap laca-seo-wrap" style="max-width: 1400px; margin: 20px auto 40px auto; padding: 0 16px;">
    <!-- Banner Chọn Phương Án Xem Trước -->
    <div style="background: #18181b; color: #f4f4f5; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span style="background: #eab308; color: #000; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                        <?php esc_html_e('DỮ LIỆU MẪU', 'lacadev'); ?>
                    </span>
                    <span style="font-size: 13px; color: #a1a1aa;">
                        <?php esc_html_e('Google Site Kit Parity — Preview Bố Cục Dashboard (G3)', 'lacadev'); ?>
                    </span>
                </div>
                <h1 style="color: #fff; margin: 0 0 6px 0; font-size: 20px; font-weight: 700;">
                    <?php esc_html_e('Xem trước & So sánh Bố cục Báo cáo Google', 'lacadev'); ?>
                </h1>
                <p style="margin: 0; color: #d4d4d8; font-size: 13px; max-width: 800px; line-height: 1.5;">
                    <?php esc_html_e('Vui lòng xem thử 2 phương án bố cục bên dưới cho Dashboard Google Site Kit Parity và chọn phương án bạn muốn áp dụng cho Laca Admin.', 'lacadev'); ?>
                </p>
            </div>

            <!-- Switcher A / B -->
            <div style="display: flex; gap: 10px; background: #27272a; padding: 6px; border-radius: 8px; border: 1px solid #3f3f46;">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-google-report-preview', 'variant' => 'A'])); ?>"
                   class="button <?php echo $variant === 'A' ? 'button-primary' : ''; ?>"
                   style="<?php echo $variant === 'A' ? 'background: #3b82f6; border-color: #3b82f6;' : 'background: transparent; color: #e4e4e7; border-color: transparent;'; ?> font-weight: 600; padding: 4px 14px; height: auto;">
                    <?php esc_html_e('Phương án A (Hàng Chỉ số chính trên 2 cột)', 'lacadev'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-google-report-preview', 'variant' => 'B'])); ?>"
                   class="button <?php echo $variant === 'B' ? 'button-primary' : ''; ?>"
                   style="<?php echo $variant === 'B' ? 'background: #3b82f6; border-color: #3b82f6;' : 'background: transparent; color: #e4e4e7; border-color: transparent;'; ?> font-weight: 600; padding: 4px 14px; height: auto;">
                    <?php esc_html_e('Phương án B (Chỉ số chính là Tab thứ 3)', 'lacadev'); ?>
                </a>
            </div>
        </div>

        <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid #27272a; font-size: 12.5px; color: #a1a1aa; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong><?php echo $variant === 'A' ? esc_html__('Đang xem: Phương án A', 'lacadev') : esc_html__('Đang xem: Phương án B', 'lacadev'); ?></strong> —
                <?php if ($variant === 'A'): ?>
                    <span><?php esc_html_e('Hàng Key Metrics hiển thị ngay lập tức phía trên 2 cột GSC / GA4 mà không cần bấm chuyển tab.', 'lacadev'); ?></span>
                <?php else: ?>
                    <span><?php esc_html_e('Key Metrics thành tab riêng gọn gàng, người dùng chuyển đổi giữa GSC, GA4, Key Metrics Cửa hàng và PageSpeed.', 'lacadev'); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <a href="<?php echo esc_url(admin_url('index.php#laca-google-report')); ?>" style="color: #60a5fa; text-decoration: none;">
                    &larr; <?php esc_html_e('Về Dashboard chính', 'lacadev'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- KHUNG BÁO CÁO MOCKUP -->
    <div class="laca-google-report-body" style="background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

        <!-- Top Toolbar -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #f4f4f5;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-chart-pie" style="font-size: 22px; width: 22px; height: 22px; color: #18181b;"></span>
                <div>
                    <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #18181b;">
                        <?php esc_html_e('Báo cáo Google Site Kit Parity', 'lacadev'); ?>
                    </h2>
                    <span style="font-size: 12px; color: #71717a;">
                        <?php esc_html_e('Khoảng thời gian kết thúc hôm qua kèm so sánh cùng kỳ trước đó', 'lacadev'); ?>
                    </span>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
                <div class="button-group">
                    <button type="button" class="button <?php echo $range === '7d' ? 'button-primary' : 'button-secondary'; ?>" style="font-size: 12px;">7 ngày</button>
                    <button type="button" class="button <?php echo $range === '14d' ? 'button-primary' : 'button-secondary'; ?>" style="font-size: 12px;">14 ngày</button>
                    <button type="button" class="button <?php echo $range === '28d' ? 'button-primary' : 'button-secondary'; ?>" style="font-size: 12px;">28 ngày qua</button>
                    <button type="button" class="button <?php echo $range === '90d' ? 'button-primary' : 'button-secondary'; ?>" style="font-size: 12px;">90 ngày</button>
                </div>
                <button type="button" class="button button-secondary" style="font-size: 12px;">
                    <span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px; margin-top: 3px;"></span>
                    <?php esc_html_e('Làm mới', 'lacadev'); ?>
                </button>
            </div>
        </div>

        <!-- 1. PHẦN CHUNG: SEARCH FUNNEL (Site Kit searchFunnelGA4) -->
        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 10px; padding: 18px 20px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-filter" style="color: #18181b; font-size: 18px; width: 18px; height: 18px;"></span>
                    <strong style="font-size: 14px; color: #18181b;"><?php esc_html_e('Phễu tìm kiếm tự nhiên (Search Funnel)', 'lacadev'); ?></strong>
                </div>
                <span style="font-size: 12px; color: #71717a;">Google Search Console &rarr; Google Analytics 4</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                <!-- Funnel Step 1 -->
                <div style="background: #fff; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 12px 14px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #71717a;"><?php esc_html_e('1. Lượt hiển thị tìm kiếm', 'lacadev'); ?></div>
                    <div style="font-size: 22px; font-weight: 700; color: #18181b; margin: 4px 0;">12.450</div>
                    <div style="font-size: 11.5px; color: #10b981; font-weight: 600;">+15.2% <?php esc_html_e('so kỳ trước', 'lacadev'); ?></div>
                </div>

                <!-- Funnel Step 2 -->
                <div style="background: #fff; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 12px 14px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #71717a;"><?php esc_html_e('2. Lượt nhấp tìm kiếm', 'lacadev'); ?></div>
                    <div style="font-size: 22px; font-weight: 700; color: #18181b; margin: 4px 0;">840</div>
                    <div style="font-size: 11.5px; color: #10b981; font-weight: 600;">+8.4% <?php esc_html_e('CTR 6.7%', 'lacadev'); ?></div>
                </div>

                <!-- Funnel Step 3 -->
                <div style="background: #fff; border: 1px solid #e4e4e7; border-left: 3px solid #3b82f6; padding: 12px 14px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #71717a;"><?php esc_html_e('3. Khách từ tìm kiếm (GA4)', 'lacadev'); ?></div>
                    <div style="font-size: 22px; font-weight: 700; color: #18181b; margin: 4px 0;">712</div>
                    <div style="font-size: 11.5px; color: #10b981; font-weight: 600;">+6.8% <?php esc_html_e('Organic Users', 'lacadev'); ?></div>
                </div>

                <!-- Funnel Step 4 -->
                <div style="background: #fff; border: 1px solid #e4e4e7; border-left: 3px solid #10b981; padding: 12px 14px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #71717a;"><?php esc_html_e('4. Tỉ lệ tương tác Organic', 'lacadev'); ?></div>
                    <div style="font-size: 22px; font-weight: 700; color: #18181b; margin: 4px 0;">64.5%</div>
                    <div style="font-size: 11.5px; color: #10b981; font-weight: 600;">+2.1% <?php esc_html_e('tương tác tốt', 'lacadev'); ?></div>
                </div>
            </div>
        </div>

        <?php if ($variant === 'A'): ?>
            <!-- ============================== PHƯƠNG ÁN A ============================== -->
            <!-- HÀNG CHỈ SỐ CHÍNH (KEY METRICS CHO SHOP) NẰM TRÊN 2 CỘT -->
            <div style="margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-star-filled" style="color: #eab308; font-size: 18px; width: 18px; height: 18px;"></span>
                        <strong style="font-size: 14.5px; color: #18181b;"><?php esc_html_e('Chỉ số chính cho Cửa hàng (Key Metrics)', 'lacadev'); ?></strong>
                        <span style="background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px;">Phương án A: Hàng trên</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics#laca-key-metrics-config')); ?>" style="font-size: 12px; color: #71717a; text-decoration: none;">
                        <span class="dashicons dashicons-admin-generic" style="font-size: 14px; width: 14px; height: 14px; margin-top: 2px;"></span>
                        <?php esc_html_e('Tuỳ chỉnh ô hiển thị', 'lacadev'); ?>
                    </a>
                </div>

                <!-- Grid 6 ô Key Metrics mặc định -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <!-- Metric 1: Khách mới vs quay lại -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11.5px; color: #71717a;"><?php esc_html_e('Khách mới / Quay lại', 'lacadev'); ?></div>
                        <div style="font-size: 17px; font-weight: 700; color: #18181b; margin: 4px 0;">64% / 36%</div>
                        <div style="font-size: 11px; color: #71717a;">798 mới &bull; 452 quay lại</div>
                    </div>

                    <!-- Metric 2: Nguồn truy cập hàng đầu -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11.5px; color: #71717a;"><?php esc_html_e('Nguồn truy cập hàng đầu', 'lacadev'); ?></div>
                        <div style="font-size: 17px; font-weight: 700; color: #18181b; margin: 4px 0;">Organic Search</div>
                        <div style="font-size: 11px; color: #10b981; font-weight: 600;">58% tổng lượt truy cập</div>
                    </div>

                    <!-- Metric 3: Thành phố hàng đầu -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11.5px; color: #71717a;"><?php esc_html_e('Thành phố hàng đầu', 'lacadev'); ?></div>
                        <div style="font-size: 17px; font-weight: 700; color: #18181b; margin: 4px 0;">TP. Hồ Chí Minh</div>
                        <div style="font-size: 11px; color: #71717a;">52% &bull; HN: 26% &bull; ĐN: 9%</div>
                    </div>

                    <!-- Metric 4: Số trang mỗi lượt -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11.5px; color: #71717a;"><?php esc_html_e('Số trang / Lượt', 'lacadev'); ?></div>
                        <div style="font-size: 17px; font-weight: 700; color: #18181b; margin: 4px 0;">3.40</div>
                        <div style="font-size: 11px; color: #10b981; font-weight: 600;">+0.35 trang / phiên</div>
                    </div>

                    <!-- Metric 5: Thời lượng lượt truy cập -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11.5px; color: #71717a;"><?php esc_html_e('Thời lượng trung bình', 'lacadev'); ?></div>
                        <div style="font-size: 17px; font-weight: 700; color: #18181b; margin: 4px 0;">2m 15s</div>
                        <div style="font-size: 11px; color: #10b981; font-weight: 600;">+18s so kỳ trước</div>
                    </div>

                    <!-- Metric 6: Sản phẩm xem nhiều nhất -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 11.5px; color: #71717a;"><?php esc_html_e('Sản phẩm xem nhiều', 'lacadev'); ?></div>
                        <div style="font-size: 15px; font-weight: 700; color: #18181b; margin: 4px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Arabica Cầu Đất</div>
                        <div style="font-size: 11px; color: #71717a;">482 lượt xem trang</div>
                    </div>
                </div>
            </div>

            <!-- BỐ CỤC 2 CỘT SONG SONG: GSC VÀ GA4 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap: 20px; margin-bottom: 24px;">
                <!-- CỘT GSC -->
                <div style="border: 1px solid #e4e4e7; border-radius: 10px; padding: 18px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f4f4f5;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-search" style="font-size: 18px; width: 18px; height: 18px; color: #18181b;"></span>
                            <strong style="font-size: 14.5px; color: #18181b;">Google Search Console</strong>
                        </div>
                        <span style="font-size: 12px; color: #71717a;">Hành vi tìm kiếm tự nhiên</span>
                    </div>

                    <!-- GSC 4 Cards -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px;">
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">Tổng số lượt nhấp</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">840</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+8.4%</div>
                        </div>
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">Tổng số lượt hiển thị</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">12.450</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+15.2%</div>
                        </div>
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">CTR trung bình</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">6.7%</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+0.4%</div>
                        </div>
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">Vị trí trung bình</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">14.2</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+1.8 thứ hạng</div>
                        </div>
                    </div>

                    <!-- Top 5 Queries -->
                    <strong style="font-size: 13px; color: #18181b; display: block; margin-bottom: 8px;">Top từ khoá tìm kiếm</strong>
                    <table class="wp-list-table widefat striped" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>Từ khoá</th>
                                <th style="text-align: right; width: 60px;">Clicks</th>
                                <th style="text-align: right; width: 70px;">Hiển thị</th>
                                <th style="text-align: right; width: 60px;">Vị trí</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>lix roastery</strong></td>
                                <td style="text-align: right; font-weight: 600;">320</td>
                                <td style="text-align: right;">1.420</td>
                                <td style="text-align: right;">1.2</td>
                            </tr>
                            <tr>
                                <td><strong>cà phê arabica cầu đất</strong></td>
                                <td style="text-align: right; font-weight: 600;">145</td>
                                <td style="text-align: right;">2.890</td>
                                <td style="text-align: right;">4.5</td>
                            </tr>
                            <tr>
                                <td><strong>cà phê pha phin ngon</strong></td>
                                <td style="text-align: right; font-weight: 600;">92</td>
                                <td style="text-align: right;">3.100</td>
                                <td style="text-align: right;">7.8</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CỘT GA4 -->
                <div style="border: 1px solid #e4e4e7; border-radius: 10px; padding: 18px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f4f4f5;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-chart-area" style="font-size: 18px; width: 18px; height: 18px; color: #18181b;"></span>
                            <strong style="font-size: 14.5px; color: #18181b;">Google Analytics 4</strong>
                        </div>
                        <span style="font-size: 12px; color: #71717a;">Hành vi người dùng trên trang</span>
                    </div>

                    <!-- GA4 4 Cards -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px;">
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">Tổng số người dùng</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">1.250</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+12.1%</div>
                        </div>
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">Lượt xem trang</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">6.250</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+14.5%</div>
                        </div>
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">Tỉ lệ tương tác</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">68.5%</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+3.2%</div>
                        </div>
                        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #18181b; padding: 10px 12px; border-radius: 4px;">
                            <div style="font-size: 11.5px; color: #71717a;">Thời gian tương tác TB</div>
                            <div style="font-size: 18px; font-weight: 700; color: #18181b;">1m 45s</div>
                            <div style="font-size: 11px; color: #10b981; font-weight: 600;">+14s</div>
                        </div>
                    </div>

                    <!-- Top 3 Pages -->
                    <strong style="font-size: 13px; color: #18181b; display: block; margin-bottom: 8px;">Top trang có lượt xem cao</strong>
                    <table class="wp-list-table widefat striped" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>Trang</th>
                                <th style="text-align: right; width: 75px;">Lượt xem</th>
                                <th style="text-align: right; width: 95px;">Tương tác TB</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Trang chủ — LIX Roastery</strong><br>
                                    <small style="color: #71717a;">/</small>
                                </td>
                                <td style="text-align: right; font-weight: 600;">2.140</td>
                                <td style="text-align: right; color: #10b981; font-weight: 600;">2m 10s</td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Cà phê Arabica Cầu Đất Đặc Biệt</strong><br>
                                    <small style="color: #71717a;">/product/ca-phe-arabica-cau-dat/</small>
                                </td>
                                <td style="text-align: right; font-weight: 600;">850</td>
                                <td style="text-align: right; color: #10b981; font-weight: 600;">1m 45s</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- ============================== PHƯƠNG ÁN B ============================== -->
            <!-- KEY METRICS THÀNH TAB THỨ 3 BÊN CẠNH GSC VÀ GA4 -->
            <div style="margin-bottom: 24px;">
                <!-- Tab Navigation -->
                <div style="display: flex; gap: 4px; border-bottom: 2px solid #e4e4e7; margin-bottom: 20px;">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-google-report-preview', 'variant' => 'B', 'tab' => 'gsc'])); ?>"
                       style="padding: 10px 18px; text-decoration: none; font-size: 13.5px; font-weight: 600; border-bottom: 2px solid <?php echo $activeTab === 'gsc' ? '#18181b' : 'transparent'; ?>; margin-bottom: -2px; color: <?php echo $activeTab === 'gsc' ? '#18181b' : '#71717a'; ?>;">
                        <span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                        <?php esc_html_e('1. Search Console (Tìm kiếm)', 'lacadev'); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-google-report-preview', 'variant' => 'B', 'tab' => 'ga'])); ?>"
                       style="padding: 10px 18px; text-decoration: none; font-size: 13.5px; font-weight: 600; border-bottom: 2px solid <?php echo $activeTab === 'ga' ? '#18181b' : 'transparent'; ?>; margin-bottom: -2px; color: <?php echo $activeTab === 'ga' ? '#18181b' : '#71717a'; ?>;">
                        <span class="dashicons dashicons-chart-area" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                        <?php esc_html_e('2. Google Analytics 4 (Lưu lượng)', 'lacadev'); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-google-report-preview', 'variant' => 'B', 'tab' => 'metrics'])); ?>"
                       style="padding: 10px 18px; text-decoration: none; font-size: 13.5px; font-weight: 600; border-bottom: 2px solid <?php echo $activeTab === 'metrics' ? '#18181b' : 'transparent'; ?>; margin-bottom: -2px; color: <?php echo $activeTab === 'metrics' ? '#18181b' : '#71717a'; ?>;">
                        <span class="dashicons dashicons-star-filled" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px; color: #eab308;"></span>
                        <?php esc_html_e('3. Chỉ số chính Cửa hàng (Key Metrics)', 'lacadev'); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-google-report-preview', 'variant' => 'B', 'tab' => 'psi'])); ?>"
                       style="padding: 10px 18px; text-decoration: none; font-size: 13.5px; font-weight: 600; border-bottom: 2px solid <?php echo $activeTab === 'psi' ? '#18181b' : 'transparent'; ?>; margin-bottom: -2px; color: <?php echo $activeTab === 'psi' ? '#18181b' : '#71717a'; ?>;">
                        <span class="dashicons dashicons-performance" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                        <?php esc_html_e('4. Tốc độ trang (PageSpeed)', 'lacadev'); ?>
                    </a>
                </div>

                <!-- Tab Content -->
                <?php if ($activeTab === 'metrics'): ?>
                    <!-- TAB 3: KEY METRICS RIÊNG BIỆT -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 10px; padding: 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <div>
                                <h3 style="margin: 0; font-size: 15px; color: #18181b;">
                                    <?php esc_html_e('Chỉ số vận hành & Hiệu quả Cửa hàng', 'lacadev'); ?>
                                </h3>
                                <p style="margin: 4px 0 0 0; font-size: 12px; color: #71717a;">
                                    <?php esc_html_e('Dữ liệu phân tích chuyên sâu cho shop trực tuyến từ Google Analytics 4', 'lacadev'); ?>
                                </p>
                            </div>
                            <span style="background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px;">Phương án B: Tab 3</span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
                            <!-- 8 cards detailed in Tab 3 -->
                            <div style="background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px;">
                                <div style="font-size: 12px; color: #71717a;">Khách mới so với Quay lại</div>
                                <div style="font-size: 20px; font-weight: 700; color: #18181b; margin: 6px 0;">64% mới &bull; 36% quay lại</div>
                                <div style="font-size: 12px; color: #10b981; font-weight: 600;">+4.2% tỉ lệ khách quay lại mua sắm</div>
                            </div>
                            <div style="background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px;">
                                <div style="font-size: 12px; color: #71717a;">Kênh mang lại nhiều khách nhất</div>
                                <div style="font-size: 20px; font-weight: 700; color: #18181b; margin: 6px 0;">Organic Search (58%)</div>
                                <div style="font-size: 12px; color: #71717a;">712 người dùng &bull; Tỉ lệ giữ chân cao</div>
                            </div>
                            <div style="background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px;">
                                <div style="font-size: 12px; color: #71717a;">Thành phố dẫn đầu đơn hàng & tương tác</div>
                                <div style="font-size: 20px; font-weight: 700; color: #18181b; margin: 6px 0;">TP. Hồ Chí Minh (52%)</div>
                                <div style="font-size: 12px; color: #71717a;">Hà Nội (26%), Đà Nẵng (9%)</div>
                            </div>
                            <div style="background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px;">
                                <div style="font-size: 12px; color: #71717a;">Hành động Thêm giỏ hàng (Add to Cart)</div>
                                <div style="font-size: 20px; font-weight: 700; color: #18181b; margin: 6px 0;">184 lượt thêm giỏ</div>
                                <div style="font-size: 12px; color: #10b981; font-weight: 600;">+24.3% so cùng kỳ trước</div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($activeTab === 'ga'): ?>
                    <!-- TAB 2: GA4 TOÀN PHẦN -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 10px; padding: 20px;">
                        <h3 style="margin: 0 0 14px 0; font-size: 15px; color: #18181b;">Google Analytics 4 — Toàn màn hình rộng</h3>
                        <p style="font-size: 13px; color: #71717a;">Toàn bộ bảng trang, sự kiện, kênh truy cập, biểu đồ hiển thị rộng rãi ở tab này.</p>
                    </div>
                <?php elseif ($activeTab === 'psi'): ?>
                    <!-- TAB 4: PAGESPEED -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 10px; padding: 20px;">
                        <h3 style="margin: 0 0 14px 0; font-size: 15px; color: #18181b;">PageSpeed Insights & Web Vitals</h3>
                        <p style="font-size: 13px; color: #71717a;">Chỉ số tốc độ LCP, INP, CLS và điểm Lighthouse cho trang chủ.</p>
                    </div>
                <?php else: ?>
                    <!-- TAB 1: SEARCH CONSOLE TOÀN PHẦN -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 10px; padding: 20px;">
                        <h3 style="margin: 0 0 14px 0; font-size: 15px; color: #18181b;">Google Search Console — Toàn màn hình rộng</h3>
                        <p style="font-size: 13px; color: #71717a;">Bảng 100 từ khoá, bảng trang tìm kiếm và biểu đồ hiển thị rộng rãi ở tab này.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 4. KHỐI PAGESPEED INSIGHTS (G4) DÙNG CHUNG PHÍA DƯỚI -->
        <div style="border-top: 1px solid #f4f4f5; padding-top: 20px; margin-top: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-performance" style="font-size: 20px; width: 20px; height: 20px; color: #18181b;"></span>
                    <strong style="font-size: 14px; color: #18181b;"><?php esc_html_e('Tốc độ Trang & Trải nghiệm Người dùng (PageSpeed Insights)', 'lacadev'); ?></strong>
                </div>
                <span style="font-size: 12px; color: #71717a;">
                    URL: <code><?php echo esc_html($siteUrl); ?></code>
                </span>
            </div>

            <!-- Field CrUX Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: #166534; font-weight: 600;">LCP (Tải nội dung lớn nhất)</span>
                        <span style="background: #10b981; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 9999px; font-weight: 700;">TỐT</span>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: #166534; margin: 4px 0;">1.8s</div>
                    <div style="font-size: 11px; color: #15803d;">Khách hàng thấy nội dung chính nhanh</div>
                </div>

                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: #166534; font-weight: 600;">INP (Tương tác đến phản hồi)</span>
                        <span style="background: #10b981; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 9999px; font-weight: 700;">TỐT</span>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: #166534; margin: 4px 0;">85ms</div>
                    <div style="font-size: 11px; color: #15803d;">Phản hồi bấm nút, cuộn trang tức thì</div>
                </div>

                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: #166534; font-weight: 600;">CLS (Độ dịch chuyển bố cục)</span>
                        <span style="background: #10b981; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 9999px; font-weight: 700;">TỐT</span>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: #166534; margin: 4px 0;">0.02</div>
                    <div style="font-size: 11px; color: #15803d;">Bố cục giao diện rất ổn định</div>
                </div>

                <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; padding: 12px 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: #52525b; font-weight: 600;">Lighthouse Điểm hiệu năng</span>
                        <span style="background: #10b981; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 9999px; font-weight: 700;">94/100</span>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: #18181b; margin: 4px 0;">94 điểm</div>
                    <div style="font-size: 11px; color: #71717a;">Mobile: 89 / Desktop: 98</div>
                </div>
            </div>
        </div>

    </div>
</div>
