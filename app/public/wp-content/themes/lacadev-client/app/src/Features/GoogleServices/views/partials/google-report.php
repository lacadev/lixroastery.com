<?php
/**
 * Shared Google Services Report Partial (GSC + GA4) — Option B (2 parallel columns).
 * Shared between Laca SEO Dashboard (views/dashboard.php) and WordPress Dashboard (index.php).
 *
 * @var array<string, mixed> $gsc
 * @var array<string, mixed> $ga
 * @var string $range
 * @var array<string, string> $rangeLabels
 */

defined('ABSPATH') || exit;

$range = $range ?? \App\Features\GoogleServices\DateRange::getUserRange();
$rangeLabels = $rangeLabels ?? \App\Features\GoogleServices\DateRange::getRangeLabels();

$isMock = !empty($gsc['isMock']) || !empty($ga['isMock']);
$setupState = \App\Features\GoogleServices\GoogleServices::setupState();

$gscComplete = $isMock || (($setupState['gsc']['status'] ?? '') === 'complete') || (!empty($gsc['connected']) && empty($gsc['needsSetup']));
$gaComplete  = $isMock || (($setupState['ga']['status'] ?? '') === 'complete') || (!empty($ga['connected']) && empty($ga['needsSetup']));

if (!$gscComplete && !$gaComplete) {
    return;
}
?>
<div class="laca-google-report-body">
    <!-- Toolbar: Range selection & Refresh -->
    <div class="laca-seo-card laca-google-toolbar" style="margin-bottom: 20px; padding: 12px 18px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-chart-pie" style="font-size: 20px; width: 20px; height: 20px; color: var(--laca-fg, #18181b);"></span>
                <div>
                    <strong style="font-size: 14px; color: var(--laca-fg, #18181b);"><?php esc_html_e('Báo cáo Hiệu quả Tìm kiếm & Lưu lượng Truy cập', 'lacadev'); ?></strong>
                    <div style="font-size: 12px; color: var(--laca-fg-muted, #71717a);">
                        <?php if (!empty($gsc['isMock'])): ?>
                            <span class="laca-badge"><?php esc_html_e('Dữ liệu mẫu', 'lacadev'); ?></span>
                        <?php else: ?>
                            <span><?php echo esc_html($rangeLabels[$range] ?? '28 ngày qua'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; max-width: 100%;">
                <div class="button-group" role="group" aria-label="<?php esc_attr_e('Khoảng thời gian', 'lacadev'); ?>" style="display: inline-flex; flex-wrap: wrap; max-width: 100%;">
                    <?php foreach ($rangeLabels as $rKey => $rLabel): ?>
                        <?php
                        $shortLabel = match ($rKey) {
                            '7d'  => '7 ngày',
                            '14d' => '14 ngày',
                            '28d' => '28 ngày',
                            '90d' => '90 ngày',
                            default => $rLabel,
                        };
                        ?>
                        <button
                            type="button"
                            class="button laca-google-range-btn <?php echo $range === $rKey ? 'button-primary' : 'button-secondary'; ?>"
                            data-range="<?php echo esc_attr($rKey); ?>"
                            style="font-size: 11.5px; height: 28px; line-height: 26px; padding: 0 8px;"
                        >
                            <?php echo esc_html($shortLabel); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <button
                    type="button"
                    class="button button-secondary laca-google-refresh-btn"
                    data-refresh="1"
                    title="<?php esc_attr_e('Làm mới dữ liệu từ Google (bỏ qua bộ nhớ tạm)', 'lacadev'); ?>"
                    style="font-size: 12px; height: 30px; line-height: 28px;"
                >
                    <span class="dashicons dashicons-update" style="margin-top: 3px; font-size: 15px; width: 15px; height: 15px;"></span>
                    <?php esc_html_e('Làm mới', 'lacadev'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php if (!empty($gsc['isMock']) || !empty($ga['isMock'])): ?>
        <div class="laca-alert laca-alert--info" role="alert" style="margin-bottom: 20px;">
            <span class="dashicons dashicons-info laca-alert__icon" aria-hidden="true"></span>
            <div class="laca-alert__content">
                <h3 class="laca-alert__title"><?php esc_html_e('DỮ LIỆU MẪU — không phải số liệu thật', 'lacadev'); ?></h3>
                <p class="laca-alert__description"><?php esc_html_e('Đang hiển thị dữ liệu mô phỏng do có tham số ?laca_mock_google=1 trên môi trường phát triển cục bộ.', 'lacadev'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== BỐ CỤC KHỐI BÁO CÁO (PHƯƠNG ÁN B) ==================== -->
    <div class="<?php echo ($gscComplete && $gaComplete) ? 'laca-google-dual-grid' : 'laca-google-single-grid'; ?>" <?php echo (!$gscComplete || !$gaComplete) ? 'style="display: grid; grid-template-columns: 1fr; gap: 20px;"' : ''; ?>>

        <?php if ($gscComplete): ?>
        <!-- CỘT TRÁI: GOOGLE SEARCH CONSOLE -->
        <div class="laca-seo-card laca-google-card" style="margin-bottom: 0;">
            <div class="laca-seo-card__header laca-seo-header-flex" style="padding: 16px 20px; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                <div>
                    <h2 class="laca-seo-card__title" style="font-size: 15.5px; display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-search" style="font-size: 18px; width: 18px; height: 18px;"></span>
                        <?php esc_html_e('Google Search Console', 'lacadev'); ?>
                    </h2>
                    <p class="laca-seo-card__subtitle" style="font-size: 12.5px; margin-top: 2px;">
                        <?php esc_html_e('Bên ngoài trang — Hành vi tìm kiếm tự nhiên', 'lacadev'); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <span class="laca-badge" style="background: var(--laca-bg-subtle, #f4f4f5); color: var(--laca-fg, #18181b); padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; border: 1px solid var(--laca-border-emphasized, #d4d4d8);">
                        Organic Search
                    </span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-small" style="font-size: 11px; height: 24px; line-height: 22px;">
                        <?php esc_html_e('Cài đặt', 'lacadev'); ?>
                    </a>
                </div>
            </div>

            <?php if (empty($gsc['connected'])): ?>
                <!-- Trạng thái chưa kết nối GSC -->
                <div style="padding: 32px 24px; text-align: center; background: var(--laca-bg-subtle, #fafafa);">
                    <span class="dashicons dashicons-google" style="font-size: 36px; width: 36px; height: 36px; color: var(--laca-fg-subtle, #a1a1aa); margin-bottom: 12px;"></span>
                    <h3 style="font-size: 15px; margin: 0 0 6px 0; color: var(--laca-fg, #18181b);"><?php esc_html_e('Chưa kết nối Google Search Console', 'lacadev'); ?></h3>
                    <p style="margin: 0 auto 16px auto; max-width: 380px; color: var(--laca-fg-muted, #71717a); font-size: 13px;">
                        <?php esc_html_e('Kết nối tài khoản Google để theo dõi thứ hạng từ khóa, lượt hiển thị và số lượt click tự nhiên từ Google.', 'lacadev'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-primary">
                        <?php esc_html_e('Thiết lập Search Console', 'lacadev'); ?> &rarr;
                    </a>
                </div>
            <?php elseif (!empty($gsc['error'])): ?>
                <!-- Lỗi trả về từ Google Search Console -->
                <div style="padding: 20px;">
                    <div class="laca-alert laca-alert--error" role="alert" style="margin: 0; padding: 14px 16px; border: 1px solid var(--laca-border-danger, #fca5a5); border-radius: 6px; background: var(--laca-bg-danger-subtle, #fef2f2);">
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <span class="dashicons dashicons-no-alt" style="color: var(--laca-danger, #ef4444); font-size: 20px; line-height: 1.2;"></span>
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 600; color: var(--laca-fg, #18181b);">
                                    <?php
                                    if (!empty($gsc['status'])) {
                                        printf(esc_html__('Lỗi Google Search Console (HTTP %d)', 'lacadev'), (int) $gsc['status']);
                                    } else {
                                        esc_html_e('Lỗi kết nối Google Search Console', 'lacadev');
                                    }
                                    ?>
                                </h4>
                                <p style="margin: 0 0 10px 0; font-size: 13px; color: var(--laca-fg-muted, #71717a);">
                                    <?php echo esc_html($gsc['error']); ?>
                                </p>
                                <a href="<?php echo esc_url($gsc['setup_url'] ?? admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary button-small">
                                    <?php esc_html_e('Chọn property khác', 'lacadev'); ?> &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($gsc['isCollecting'])): ?>
                    <div style="padding: 16px 20px 0 20px;">
                        <div class="laca-alert laca-alert--info" style="margin: 0; padding: 12px 14px; border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 6px;">
                            <span class="dashicons dashicons-update laca-alert__icon" style="color: #2563eb;" aria-hidden="true"></span>
                            <div class="laca-alert__content">
                                <h4 class="laca-alert__title" style="color: #1e40af; font-size: 13px; font-weight: 600; margin: 0 0 2px 0;"><?php esc_html_e('Đang thu thập dữ liệu', 'lacadev'); ?></h4>
                                <p class="laca-alert__description" style="color: #1e3a8a; font-size: 12px; margin: 0;"><?php esc_html_e('Google cần 24–48 giờ để bắt đầu có số liệu báo cáo.', 'lacadev'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($gsc['isZeroData'])): ?>
                    <div style="padding: 16px 20px 0 20px;">
                        <div class="laca-alert laca-alert--info" style="margin: 0; padding: 12px 14px; border: 1px solid #e4e4e7; background: #fafafa; border-radius: 6px;">
                            <span class="dashicons dashicons-info laca-alert__icon" style="color: #71717a;" aria-hidden="true"></span>
                            <div class="laca-alert__content">
                                <h4 class="laca-alert__title" style="color: #27272a; font-size: 13px; font-weight: 600; margin: 0 0 2px 0;"><?php esc_html_e('Chưa có lượt truy cập trong khoảng này', 'lacadev'); ?></h4>
                                <p class="laca-alert__description" style="color: #71717a; font-size: 12px; margin: 0;"><?php esc_html_e('Không có lượt hiển thị hay nhấp chuột nào được ghi nhận.', 'lacadev'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Dòng dữ liệu đến ngày -->
                <div style="padding: 12px 20px 0 20px; font-size: 12px; color: var(--laca-fg-muted, #71717a); display: flex; align-items: center; gap: 6px;">
                    <span class="dashicons dashicons-calendar-alt" style="font-size: 14px; width: 14px; height: 14px; color: var(--laca-fg-subtle, #a1a1aa);"></span>
                    <span><?php printf(esc_html__('Dữ liệu đến ngày %s', 'lacadev'), esc_html($gsc['dataDate'] ?? date('d/m', strtotime('-1 day')))); ?></span>
                </div>

                <!-- 4 KPI Blocks GSC -->
                <div class="laca-seo-stats-grid" style="padding: 16px 20px 20px 20px; margin: 0; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    <!-- Clicks -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('Tổng lượt click', 'lacadev'); ?></span>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                    <?php echo number_format_i18n((int) ($gsc['clicks'] ?? 0)); ?>
                                </strong>
                                <?php
                                $cDelta = (string) ($gsc['clicks_delta'] ?? '—');
                                if ($cDelta !== '—'):
                                    $cColor = str_starts_with($cDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($cDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                    <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($cColor); ?>;">
                                        <?php echo esc_html($cDelta); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('so với kỳ trước', 'lacadev'); ?></small>
                        </div>
                    </div>

                    <!-- Impressions -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('Lượt hiển thị', 'lacadev'); ?></span>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                    <?php echo number_format_i18n((int) ($gsc['impressions'] ?? 0)); ?>
                                </strong>
                                <?php
                                $iDelta = (string) ($gsc['impressions_delta'] ?? '—');
                                if ($iDelta !== '—'):
                                    $iColor = str_starts_with($iDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($iDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                    <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($iColor); ?>;">
                                        <?php echo esc_html($iDelta); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('so với kỳ trước', 'lacadev'); ?></small>
                        </div>
                    </div>

                    <!-- CTR -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('CTR trung bình', 'lacadev'); ?></span>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                    <?php echo esc_html((string) ($gsc['ctr'] ?? 0)); ?>%
                                </strong>
                                <?php
                                $ctrDelta = (string) ($gsc['ctr_delta'] ?? '—');
                                if ($ctrDelta !== '—'):
                                    $ctrColor = str_starts_with($ctrDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($ctrDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                    <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($ctrColor); ?>;">
                                        <?php echo esc_html($ctrDelta); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('tỷ lệ nhấp', 'lacadev'); ?></small>
                        </div>
                    </div>

                    <!-- Average Position -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('Vị trí trung bình', 'lacadev'); ?></span>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                    #<?php echo esc_html((string) ($gsc['position'] ?? 0)); ?>
                                </strong>
                                <?php
                                $posDelta = (string) ($gsc['position_delta'] ?? '—');
                                if ($posDelta !== '—'):
                                    $posColor = str_starts_with($posDelta, '-') ? 'var(--laca-success, #10b981)' : (str_starts_with($posDelta, '+') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                    <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($posColor); ?>;">
                                        <?php echo esc_html($posDelta); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('thứ hạng Google', 'lacadev'); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Bảng Top từ khóa tìm kiếm -->
                <div style="padding: 0 20px 16px 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h4 style="margin: 0; font-size: 13.5px; font-weight: 600; color: var(--laca-fg, #18181b);">
                            <?php esc_html_e('Top từ khóa tìm kiếm hàng đầu', 'lacadev'); ?>
                        </h4>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-seo&view=search-console')); ?>" style="font-size: 12px; color: var(--laca-accent, #2563eb); text-decoration: none;">
                            <?php esc_html_e('Xem tất cả', 'lacadev'); ?> &rarr;
                        </a>
                    </div>
                    <div class="laca-seo-table-responsive" style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; overflow-x: auto;">
                        <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Từ khóa', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 65px;"><?php esc_html_e('Clicks', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 75px;"><?php esc_html_e('Hiển thị', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 60px;"><?php esc_html_e('Vị trí', 'lacadev'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $displayQueries = $gsc['queries'] ?? [];
                                if (empty($displayQueries) && !empty($gsc['rows'])) {
                                    $displayQueries = array_slice($gsc['rows'], 0, 8);
                                }
                                ?>
                                <?php if (empty($displayQueries)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--laca-fg-muted, #71717a); padding: 14px;">
                                            <?php esc_html_e('Chưa có dữ liệu từ khóa trong khoảng thời gian này.', 'lacadev'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($displayQueries, 0, 8) as $q): ?>
                                        <?php
                                        $qName = $q['query'] ?? ($q['keys'][0] ?? '');
                                        $qClicks = (int) ($q['clicks'] ?? 0);
                                        $qImpressions = (int) ($q['impressions'] ?? 0);
                                        $qPos = round((float) ($q['position'] ?? 0), 1);
                                        ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($qName); ?></strong></td>
                                            <td style="text-align: right; font-weight: 600;"><?php echo number_format_i18n($qClicks); ?></td>
                                            <td style="text-align: right; color: var(--laca-fg-muted, #71717a);"><?php echo number_format_i18n($qImpressions); ?></td>
                                            <td style="text-align: right;"><?php echo esc_html((string) $qPos); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bảng Top trang đích Organic -->
                <?php if (!empty($gsc['pages'])): ?>
                    <div style="padding: 0 20px 20px 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600; color: var(--laca-fg, #18181b);">
                            <?php esc_html_e('Top trang đích có lượng truy cập cao', 'lacadev'); ?>
                        </h4>
                        <div class="laca-seo-table-responsive" style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; overflow-x: auto;">
                            <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Đường dẫn trang', 'lacadev'); ?></th>
                                        <th style="text-align: right; width: 65px;"><?php esc_html_e('Clicks', 'lacadev'); ?></th>
                                        <th style="text-align: right; width: 60px;"><?php esc_html_e('CTR', 'lacadev'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($gsc['pages'], 0, 5) as $pg): ?>
                                        <tr>
                                            <td><code><?php echo esc_html($pg['url']); ?></code></td>
                                            <td style="text-align: right; font-weight: 600;"><?php echo number_format_i18n((int) ($pg['clicks'] ?? 0)); ?></td>
                                            <td style="text-align: right; color: var(--laca-fg-muted, #71717a);"><?php echo esc_html((string) ($pg['ctr'] ?? 0)); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($gaComplete): ?>
        <!-- CỘT PHẢI: GOOGLE ANALYTICS 4 -->
        <div class="laca-seo-card laca-google-card" style="margin-bottom: 0;">
            <div class="laca-seo-card__header laca-seo-header-flex" style="padding: 16px 20px; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                <div>
                    <h2 class="laca-seo-card__title" style="font-size: 15.5px; display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-chart-area" style="font-size: 18px; width: 18px; height: 18px;"></span>
                        <?php esc_html_e('Google Analytics 4', 'lacadev'); ?>
                    </h2>
                    <p class="laca-seo-card__subtitle" style="font-size: 12.5px; margin-top: 2px;">
                        <?php esc_html_e('Bên trong trang — Lưu lượng & Tương tác thực tế', 'lacadev'); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <span class="laca-badge" style="background: var(--laca-bg-subtle, #f4f4f5); color: var(--laca-fg, #18181b); padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; border: 1px solid var(--laca-border-emphasized, #d4d4d8);">
                        On-Site Behavior
                    </span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-small" style="font-size: 11px; height: 24px; line-height: 22px;">
                        <?php esc_html_e('Cài đặt', 'lacadev'); ?>
                    </a>
                </div>
            </div>

            <?php if (empty($ga['connected'])): ?>
                <!-- Trạng thái chưa kết nối GA4 -->
                <div style="padding: 32px 24px; text-align: center; background: var(--laca-bg-subtle, #fafafa);">
                    <span class="dashicons dashicons-chart-pie" style="font-size: 36px; width: 36px; height: 36px; color: var(--laca-fg-subtle, #a1a1aa); margin-bottom: 12px;"></span>
                    <h3 style="font-size: 15px; margin: 0 0 6px 0; color: var(--laca-fg, #18181b);"><?php esc_html_e('Chưa kết nối Google Analytics 4', 'lacadev'); ?></h3>
                    <p style="margin: 0 auto 16px auto; max-width: 380px; color: var(--laca-fg-muted, #71717a); font-size: 13px;">
                        <?php esc_html_e('Kết nối GA4 để xem số lượng người dùng thực tế, nguồn truy cập, thời lượng tương tác và tỷ lệ chuyển đổi.', 'lacadev'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-primary">
                        <?php esc_html_e('Thiết lập Google Analytics', 'lacadev'); ?> &rarr;
                    </a>
                </div>
            <?php elseif (!empty($ga['error'])): ?>
                <!-- Lỗi trả về từ Google Analytics 4 -->
                <div style="padding: 20px;">
                    <div class="laca-alert laca-alert--error" role="alert" style="margin: 0; padding: 14px 16px; border: 1px solid var(--laca-border-danger, #fca5a5); border-radius: 6px; background: var(--laca-bg-danger-subtle, #fef2f2);">
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <span class="dashicons dashicons-no-alt" style="color: var(--laca-danger, #ef4444); font-size: 20px; line-height: 1.2;"></span>
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 600; color: var(--laca-fg, #18181b);">
                                    <?php
                                    if (!empty($ga['status'])) {
                                        printf(esc_html__('Lỗi Google Analytics 4 (HTTP %d)', 'lacadev'), (int) $ga['status']);
                                    } else {
                                        esc_html_e('Lỗi kết nối Google Analytics 4', 'lacadev');
                                    }
                                    ?>
                                </h4>
                                <p style="margin: 0 0 10px 0; font-size: 13px; color: var(--laca-fg-muted, #71717a);">
                                    <?php echo esc_html($ga['error']); ?>
                                </p>
                                <a href="<?php echo esc_url($ga['setup_url'] ?? admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-secondary button-small">
                                    <?php esc_html_e('Xem cài đặt Analytics', 'lacadev'); ?> &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($ga['isCollecting'])): ?>
                    <div style="padding: 16px 20px 0 20px;">
                        <div class="laca-alert laca-alert--info" style="margin: 0; padding: 12px 14px; border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 6px;">
                            <span class="dashicons dashicons-update laca-alert__icon" style="color: #2563eb;" aria-hidden="true"></span>
                            <div class="laca-alert__content">
                                <h4 class="laca-alert__title" style="color: #1e40af; font-size: 13px; font-weight: 600; margin: 0 0 2px 0;"><?php esc_html_e('Đang thu thập dữ liệu', 'lacadev'); ?></h4>
                                <p class="laca-alert__description" style="color: #1e3a8a; font-size: 12px; margin: 0;"><?php esc_html_e('Google cần 24–48 giờ để bắt đầu có số liệu báo cáo.', 'lacadev'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($ga['isZeroData'])): ?>
                    <div style="padding: 16px 20px 0 20px;">
                        <div class="laca-alert laca-alert--info" style="margin: 0; padding: 12px 14px; border: 1px solid #e4e4e7; background: #fafafa; border-radius: 6px;">
                            <span class="dashicons dashicons-info laca-alert__icon" style="color: #71717a;" aria-hidden="true"></span>
                            <div class="laca-alert__content">
                                <h4 class="laca-alert__title" style="color: #27272a; font-size: 13px; font-weight: 600; margin: 0 0 2px 0;"><?php esc_html_e('Chưa có lượt truy cập trong khoảng này', 'lacadev'); ?></h4>
                                <p class="laca-alert__description" style="color: #71717a; font-size: 12px; margin: 0;"><?php esc_html_e('Không có phiên hay người dùng nào được ghi nhận.', 'lacadev'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Ô Realtime Active Users (30 phút qua) -->
                <div class="laca-ga-realtime-card" style="margin: 16px 20px 0 20px; padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="laca-pulse-dot" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10b981;"></span>
                        <div>
                            <div style="font-size: 13px; font-weight: 600; color: #166534;">
                                <?php esc_html_e('Đang hoạt động (30 phút qua)', 'lacadev'); ?>
                            </div>
                            <div style="font-size: 11.5px; color: #15803d;">
                                <?php esc_html_e('Người dùng trực tiếp trên website theo GA4 Realtime', 'lacadev'); ?>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 4px;">
                        <strong id="laca-ga4-realtime-count" class="laca-ga4-realtime-count" style="font-size: 26px; font-weight: 700; color: #166534; line-height: 1;">
                            <?php echo number_format_i18n((int) ($ga['realtimeUsers'] ?? 0)); ?>
                        </strong>
                        <span style="font-size: 12px; color: #15803d; font-weight: 500;"><?php esc_html_e('người', 'lacadev'); ?></span>
                    </div>
                </div>

                <?php if (($ga['users'] ?? 0) === 0 && ($ga['realtimeUsers'] ?? 0) > 0): ?>
                    <div class="notice notice-info inline laca-ga-realtime-notice" style="margin: 12px 20px 0 20px; padding: 8px 12px; border-left-color: #3b82f6; background: #eff6ff; font-size: 12.5px; color: #1e40af;">
                        <p style="margin: 0; display: flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-info" style="color: #3b82f6; font-size: 16px; width: 16px; height: 16px;"></span>
                            <span><?php esc_html_e('GA4 cần 24–48 giờ để xử lý báo cáo; số đang hoạt động là dữ liệu trực tiếp', 'lacadev'); ?></span>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- 4 KPI Blocks GA4 -->
                <div class="laca-seo-stats-grid" style="padding: 16px 20px 20px 20px; margin: 0; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    <!-- Active Users -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('Người dùng hoạt động', 'lacadev'); ?></span>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                    <?php echo number_format_i18n((int) ($ga['users'] ?? 0)); ?>
                                </strong>
                                <?php
                                $uDelta = (string) ($ga['users_delta'] ?? '—');
                                if ($uDelta !== '—'):
                                    $uColor = str_starts_with($uDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($uDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                    <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($uColor); ?>;">
                                        <?php echo esc_html($uDelta); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('so với kỳ trước', 'lacadev'); ?></small>
                        </div>
                    </div>

                    <!-- Sessions -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('Số phiên truy cập', 'lacadev'); ?></span>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                    <?php echo number_format_i18n((int) ($ga['sessions'] ?? 0)); ?>
                                </strong>
                                <?php
                                $sDelta = (string) ($ga['sessions_delta'] ?? '—');
                                if ($sDelta !== '—'):
                                    $sColor = str_starts_with($sDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($sDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                    <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($sColor); ?>;">
                                        <?php echo esc_html($sDelta); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('lượt ghé thăm', 'lacadev'); ?></small>
                        </div>
                    </div>

                    <!-- Avg Engagement Duration -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('Thời gian tương tác TB', 'lacadev'); ?></span>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                    <?php echo esc_html($ga['avgEngagement'] ?? '—'); ?>
                                </strong>
                                <?php
                                $dDelta = (string) ($ga['avgEngagement_delta'] ?? '—');
                                if ($dDelta !== '—'):
                                    $dColor = str_starts_with($dDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($dDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                    <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($dColor); ?>;">
                                        <?php echo esc_html($dDelta); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('trên mỗi phiên', 'lacadev'); ?></small>
                        </div>
                    </div>

                    <!-- Conversion Rate -->
                    <div class="laca-seo-stat-card" style="padding: 14px;">
                        <div class="laca-seo-stat-card__info">
                            <span class="laca-seo-stat-card__label" style="font-size: 12px;"><?php esc_html_e('Tỷ lệ chuyển đổi', 'lacadev'); ?></span>
                            <?php if (!empty($ga['conversionRate'])): ?>
                                <div style="display: flex; align-items: baseline; gap: 8px; margin: 4px 0 2px 0;">
                                    <strong class="laca-seo-stat-card__value" style="font-size: 22px; line-height: 1.1;">
                                        <?php echo esc_html((string) $ga['conversionRate']); ?>
                                    </strong>
                                    <?php
                                    $cvDelta = (string) ($ga['conversionRate_delta'] ?? '—');
                                    if ($cvDelta !== '—'):
                                        $cvColor = str_starts_with($cvDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($cvDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                    ?>
                                        <span style="font-size: 12px; font-weight: 600; color: <?php echo esc_attr($cvColor); ?>;">
                                            <?php echo esc_html($cvDelta); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--laca-fg-subtle, #a1a1aa);">—</span>
                                    <?php endif; ?>
                                </div>
                                <small style="color: var(--laca-fg-muted, #71717a); font-size: 11px;"><?php esc_html_e('sự kiện chính / phiên', 'lacadev'); ?></small>
                            <?php else: ?>
                                <div style="margin: 6px 0 2px 0;">
                                    <span style="font-size: 12px; color: var(--laca-fg-muted, #71717a);">
                                        <?php esc_html_e('Chưa cấu hình sự kiện chính trong GA4', 'lacadev'); ?>
                                    </span>
                                </div>
                                <small style="font-size: 11px;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" style="color: var(--laca-accent, #2563eb); text-decoration: none;">
                                        <?php esc_html_e('Cấu hình mục tiêu', 'lacadev'); ?> &rarr;
                                    </a>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Nguồn lưu lượng (Traffic Channels) -->
                <div style="padding: 0 20px 16px 20px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 13.5px; font-weight: 600; color: var(--laca-fg, #18181b);">
                        <?php esc_html_e('Kênh lưu lượng truy cập (Traffic Acquisition)', 'lacadev'); ?>
                    </h4>
                    <?php
                    $totalChannelSessions = 0;
                    if (!empty($ga['channels'])) {
                        foreach ($ga['channels'] as $ch) {
                            $totalChannelSessions += (int) ($ch['sessions'] ?? 0);
                        }
                    }
                    ?>
                    <?php if ($totalChannelSessions > 0): ?>
                        <div style="height: 10px; display: flex; border-radius: 9999px; overflow: hidden; background: var(--laca-bg-subtle, #f4f4f5); margin-bottom: 12px;">
                            <?php foreach ($ga['channels'] as $ch): ?>
                                <div style="width: <?php echo esc_attr((string) $ch['share']); ?>%; background: <?php echo esc_attr($ch['color']); ?>;" title="<?php echo esc_attr($ch['name'] . ': ' . $ch['share'] . '%'); ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px 18px; font-size: 12px;">
                            <?php foreach ($ga['channels'] as $ch): ?>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="width: 9px; height: 9px; border-radius: 2px; background: <?php echo esc_attr($ch['color']); ?>; display: inline-block;"></span>
                                    <strong><?php echo esc_html($ch['name']); ?>: <?php echo esc_html((string) $ch['share']); ?>%</strong>
                                    <span style="color: var(--laca-fg-muted, #71717a);">(<?php echo number_format_i18n((int) $ch['sessions']); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 12px; color: var(--laca-fg-muted, #71717a); padding: 4px 0;">
                            <?php esc_html_e('Chưa có dữ liệu kênh.', 'lacadev'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Khối Hành động mục tiêu / Chuyển đổi (Business KPI / Key Events) -->
                <?php if (!empty($ga['businessKpis'])): ?>
                    <div style="padding: 0 20px 16px 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600; color: var(--laca-fg, #18181b);">
                            <?php esc_html_e('Hành động mục tiêu giá trị (Key Events / Conversions)', 'lacadev'); ?>
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <?php foreach ($ga['businessKpis'] as $kpi): ?>
                                <?php
                                $kDelta = (string) ($kpi['delta'] ?? '—');
                                $kColor = str_starts_with($kDelta, '+') ? 'var(--laca-success, #10b981)' : (str_starts_with($kDelta, '-') ? 'var(--laca-danger, #ef4444)' : 'var(--laca-fg-muted, #71717a)');
                                ?>
                                <div style="background: var(--laca-bg-subtle, #fafafa); border: 1px solid var(--laca-border, #e4e4e7); border-left: 3px solid var(--laca-fg, #18181b); padding: 10px 12px; border-radius: 4px;">
                                    <div style="font-size: 11.5px; color: var(--laca-fg-muted, #52525b);"><?php echo esc_html($kpi['title']); ?></div>
                                    <div style="font-size: 18px; font-weight: 700; color: var(--laca-fg, #18181b); margin-top: 2px;">
                                        <?php echo number_format_i18n((int) $kpi['value']); ?> <span style="font-size: 12px; font-weight: normal; color: var(--laca-fg-muted, #71717a);"><?php esc_html_e('lượt', 'lacadev'); ?></span>
                                    </div>
                                    <small style="color: <?php echo esc_attr($kColor); ?>; font-weight: 600; font-size: 11px;"><?php echo esc_html($kDelta); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Bảng Top trang có lượt xem cao & Thời gian tương tác TB -->
                <div style="padding: 0 20px 16px 20px;">
                    <h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 600; color: var(--laca-fg, #18181b);">
                        <?php esc_html_e('Top trang có lượt xem cao & Thời gian tương tác', 'lacadev'); ?>
                    </h4>
                    <div class="laca-seo-table-responsive" style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; overflow-x: auto;">
                        <table class="wp-list-table widefat striped" style="margin: 0; font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Trang nội dung', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 75px;"><?php esc_html_e('Lượt xem', 'lacadev'); ?></th>
                                    <th style="text-align: right; width: 95px;"><?php esc_html_e('Tương tác TB', 'lacadev'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ga['topPages'])): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: var(--laca-fg-muted, #71717a); padding: 14px;">
                                            <?php esc_html_e('Chưa có dữ liệu trang trong khoảng thời gian này.', 'lacadev'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ga['topPages'] as $tp): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($tp['title'] ?? $tp['pageTitle'] ?? 'Trang'); ?></strong><br>
                                                <small style="color: var(--laca-fg-muted, #71717a);"><?php echo esc_html($tp['path'] ?? $tp['pagePath'] ?? '/'); ?></small>
                                            </td>
                                            <td style="text-align: right; font-weight: 600;">
                                                <?php echo number_format_i18n((int) ($tp['views'] ?? $tp['screenPageViews'] ?? 0)); ?>
                                            </td>
                                            <td style="text-align: right; color: <?php echo !empty($tp['highlight']) ? 'var(--laca-success, #10b981); font-weight: 600;' : 'var(--laca-fg-muted, #52525b);'; ?>">
                                                <?php echo esc_html($tp['duration'] ?? '—'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
