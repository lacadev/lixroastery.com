<?php
/**
 * Shared Google Services Report Partial (GSC + GA4) — V2 Monochromatic Design.
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

$activeKeyMetrics = \App\Features\GoogleServices\GoogleServices::getActiveKeyMetrics();
?>
<div class="laca-google-report-body" id="laca-google-report">
    <!-- Toolbar: Range selection & Refresh -->
    <div class="laca-seo-card laca-google-toolbar">
        <div class="laca-google-toolbar__inner">
            <div class="laca-google-toolbar__title-wrap">
                <span class="dashicons dashicons-chart-pie laca-google-toolbar__icon"></span>
                <div>
                    <strong class="laca-google-toolbar__title"><?php esc_html_e('Báo cáo Hiệu quả Tìm kiếm & Lưu lượng Truy cập', 'lacadev'); ?></strong>
                    <div class="laca-google-toolbar__meta">
                        <?php if ($isMock): ?>
                            <span class="laca-badge"><?php esc_html_e('Dữ liệu mẫu', 'lacadev'); ?></span>
                        <?php else: ?>
                            <span><?php echo esc_html($rangeLabels[$range] ?? '28 ngày qua'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="laca-google-toolbar__actions">
                <div class="button-group laca-google-range-group" role="group" aria-label="<?php esc_attr_e('Khoảng thời gian', 'lacadev'); ?>">
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
                >
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Làm mới', 'lacadev'); ?>
                </button>
            </div>
        </div>
    </div>

    <?php if ($isMock): ?>
        <div class="laca-alert laca-alert--info laca-google-mock-notice" role="alert">
            <span class="dashicons dashicons-info laca-alert__icon" aria-hidden="true"></span>
            <div class="laca-alert__content">
                <h3 class="laca-alert__title"><?php esc_html_e('DỮ LIỆU MẪU — không phải số liệu thật', 'lacadev'); ?></h3>
                <p class="laca-alert__description"><?php esc_html_e('Đang hiển thị dữ liệu mô phỏng do có tham số ?laca_mock_google=1 trên môi trường phát triển cục bộ.', 'lacadev'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== 1. SEARCH FUNNEL ==================== -->
    <?php if ($gscComplete && $gaComplete): ?>
        <?php
        $gscClicks = (int) ($gsc['clicks'] ?? 0);
        $gscImpressions = (int) ($gsc['impressions'] ?? 0);
        $gscCtr = (float) ($gsc['ctr'] ?? 0);
        $gscClicksDelta = (string) ($gsc['clicks_delta'] ?? '—');
        $gscImpressionsDelta = (string) ($gsc['impressions_delta'] ?? '—');

        $organic = $ga['organicSearch'] ?? null;
        $hasOrganic = !empty($organic['hasOrganic']);
        $organicUsers = (int) ($organic['users'] ?? 0);
        $organicShare = (int) ($organic['share'] ?? 0);
        $organicEngagementRate = (string) ($organic['engagementRate'] ?? '—');
        ?>
        <div class="laca-seo-card laca-google-funnel">
            <div class="laca-google-funnel__header">
                <div class="laca-google-funnel__title-wrap">
                    <span class="dashicons dashicons-filter"></span>
                    <h3 class="laca-google-funnel__title">
                        <?php esc_html_e('Phễu tìm kiếm tự nhiên (Search Funnel)', 'lacadev'); ?>
                    </h3>
                </div>
                <span class="laca-google-funnel__source">
                    Google Search Console &rarr; Google Analytics 4
                </span>
            </div>

            <div class="laca-google-funnel__grid">
                <!-- Step 1: Impressions -->
                <div class="laca-google-funnel__step">
                    <div class="laca-google-funnel__step-label"><?php esc_html_e('1. Lượt hiển thị tìm kiếm', 'lacadev'); ?></div>
                    <div class="laca-google-funnel__step-value">
                        <?php echo number_format_i18n($gscImpressions); ?>
                    </div>
                    <div class="laca-google-funnel__step-meta">
                        <span class="laca-delta <?php echo str_starts_with($gscImpressionsDelta, '+') ? 'laca-delta--up' : (str_starts_with($gscImpressionsDelta, '-') ? 'laca-delta--down' : ''); ?>">
                            <?php echo esc_html($gscImpressionsDelta); ?>
                        </span>
                        <span><?php esc_html_e('so kỳ trước', 'lacadev'); ?></span>
                    </div>
                </div>

                <!-- Step 2: Clicks -->
                <div class="laca-google-funnel__step">
                    <div class="laca-google-funnel__step-label"><?php esc_html_e('2. Lượt nhấp tìm kiếm', 'lacadev'); ?></div>
                    <div class="laca-google-funnel__step-value">
                        <?php echo number_format_i18n($gscClicks); ?>
                    </div>
                    <div class="laca-google-funnel__step-meta">
                        <span class="laca-delta <?php echo str_starts_with($gscClicksDelta, '+') ? 'laca-delta--up' : (str_starts_with($gscClicksDelta, '-') ? 'laca-delta--down' : ''); ?>">
                            <?php echo esc_html($gscClicksDelta); ?>
                        </span>
                        <span>(CTR <?php echo number_format($gscCtr, 1); ?>%)</span>
                    </div>
                </div>

                <!-- Step 3: Organic Users / Traffic -->
                <div class="laca-google-funnel__step">
                    <div class="laca-google-funnel__step-label"><?php esc_html_e('3. Khách từ tìm kiếm (GA4)', 'lacadev'); ?></div>
                    <div class="laca-google-funnel__step-value">
                        <?php echo $hasOrganic ? number_format_i18n($organicUsers) : '0'; ?>
                    </div>
                    <div class="laca-google-funnel__step-meta">
                        <?php if ($hasOrganic): ?>
                            <?php if ($organicShare > 0): ?>
                                <span><?php echo esc_html($organicShare . '% ' . __('tổng phiên', 'lacadev')); ?></span>
                            <?php else: ?>
                                <span class="laca-delta"><?php echo esc_html($ga['users_delta'] ?? '—'); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="laca-google-funnel__empty-note"><?php esc_html_e('Chưa ghi nhận người dùng từ tìm kiếm tự nhiên trong khoảng này', 'lacadev'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 4: Engagement -->
                <div class="laca-google-funnel__step">
                    <div class="laca-google-funnel__step-label"><?php esc_html_e('4. Tương tác từ tìm kiếm', 'lacadev'); ?></div>
                    <div class="laca-google-funnel__step-value">
                        <?php echo $hasOrganic ? esc_html($organicEngagementRate) : '—'; ?>
                    </div>
                    <div class="laca-google-funnel__step-meta">
                        <?php if ($hasOrganic): ?>
                            <span><?php esc_html_e('tỷ lệ tương tác tự nhiên', 'lacadev'); ?></span>
                        <?php else: ?>
                            <span><?php esc_html_e('tương tác tìm kiếm', 'lacadev'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== 2. KEY METRICS (8 CARDS) ==================== -->
    <?php if ($gaComplete): ?>
        <?php
        $kmData = $ga['keyMetrics'] ?? [];
        $hasProductPostType = post_type_exists('product') || $isMock;
        $hasKeyEvents = !empty($ga['businessKpis']) || ((int) ($ga['keyEventsTotal'] ?? 0) > 0) || $isMock;
        $hasEcommerce = !empty($ga['ecommerceEvents']) || $isMock;

        // Metric definitions with conditions
        $metricCards = [
            'newVsReturning' => [
                'title'     => __('Khách mới / Quay lại', 'lacadev'),
                'value'     => $kmData['newVsReturning']['value'] ?? '—',
                'sub'       => $kmData['newVsReturning']['sub'] ?? '',
                'available' => true,
            ],
            'topSource' => [
                'title'     => __('Nguồn truy cập hàng đầu', 'lacadev'),
                'value'     => $kmData['topSource']['value'] ?? '—',
                'sub'       => $kmData['topSource']['sub'] ?? '',
                'available' => true,
            ],
            'topCities' => [
                'title'     => __('Top 3 thành phố', 'lacadev'),
                'value'     => $kmData['topCities']['value'] ?? '—',
                'sub'       => $kmData['topCities']['sub'] ?? '',
                'available' => true,
            ],
            'pagesPerSession' => [
                'title'     => __('Số trang / Lượt', 'lacadev'),
                'value'     => $kmData['pagesPerSession']['value'] ?? '—',
                'sub'       => $kmData['pagesPerSession']['sub'] ?? '',
                'available' => true,
            ],
            'sessionDuration' => [
                'title'     => __('Thời lượng trung bình', 'lacadev'),
                'value'     => $kmData['sessionDuration']['value'] ?? '—',
                'sub'       => $kmData['sessionDuration']['sub'] ?? '',
                'available' => true,
            ],
            'topProduct' => [
                'title'     => __('Sản phẩm xem nhiều nhất', 'lacadev'),
                'value'     => $kmData['topProduct']['value'] ?? '—',
                'sub'       => $kmData['topProduct']['sub'] ?? '',
                'available' => $hasProductPostType,
            ],
            'bestConversionSource' => [
                'title'     => __('Nguồn chuyển đổi tốt nhất', 'lacadev'),
                'value'     => $kmData['bestConversionSource']['value'] ?? '—',
                'sub'       => $kmData['bestConversionSource']['sub'] ?? '',
                'available' => $hasKeyEvents,
            ],
            'cartAndPurchaseSource' => [
                'title'     => __('Nguồn thêm giỏ / mua hàng', 'lacadev'),
                'value'     => $kmData['cartAndPurchaseSource']['value'] ?? '—',
                'sub'       => $kmData['cartAndPurchaseSource']['sub'] ?? '',
                'available' => $hasEcommerce,
            ],
        ];

        // Filter by active settings and availability
        $visibleCards = [];
        foreach ($metricCards as $key => $card) {
            if (in_array($key, $activeKeyMetrics, true) && $card['available']) {
                $visibleCards[$key] = $card;
            }
        }
        ?>
        <?php if (!empty($visibleCards)): ?>
            <div class="laca-seo-card laca-google-keymetrics">
                <div class="laca-google-keymetrics__header">
                    <div class="laca-google-keymetrics__title-wrap">
                        <span class="dashicons dashicons-star-filled"></span>
                        <h3 class="laca-google-keymetrics__title">
                            <?php esc_html_e('Chỉ số chính cho Cửa hàng (Key Metrics)', 'lacadev'); ?>
                        </h3>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="laca-google-keymetrics__settings-link">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('Cấu hình Google Analytics', 'lacadev'); ?>
                    </a>
                </div>

                <div class="laca-google-keymetrics__grid">
                    <?php foreach ($visibleCards as $kId => $vCard): ?>
                        <div class="laca-google-keymetrics__card">
                            <div class="laca-google-keymetrics__label"><?php echo esc_html($vCard['title']); ?></div>
                            <div class="laca-google-keymetrics__value" title="<?php echo esc_attr($vCard['value']); ?>">
                                <?php echo esc_html($vCard['value']); ?>
                            </div>
                            <?php if (!empty($vCard['sub'])): ?>
                                <div class="laca-google-keymetrics__meta"><?php echo esc_html($vCard['sub']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ==================== 3. DUAL GRID: GSC & GA4 ==================== -->
    <div class="<?php echo ($gscComplete && $gaComplete) ? 'laca-google-dual-grid' : 'laca-google-single-grid'; ?>">

        <?php if ($gscComplete): ?>
            <!-- CỘT TRÁI: GOOGLE SEARCH CONSOLE -->
            <div class="laca-seo-card laca-google-card">
                <div class="laca-seo-card__header laca-seo-header-flex laca-google-card__header">
                    <div>
                        <h2 class="laca-seo-card__title laca-google-card__title">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('Google Search Console', 'lacadev'); ?>
                        </h2>
                        <p class="laca-seo-card__subtitle laca-google-card__subtitle">
                            <?php esc_html_e('Bên ngoài trang — Hành vi tìm kiếm tự nhiên', 'lacadev'); ?>
                        </p>
                    </div>
                    <div class="laca-google-card__header-actions">
                        <span class="laca-badge">Organic Search</span>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-small">
                            <?php esc_html_e('Cài đặt', 'lacadev'); ?>
                        </a>
                    </div>
                </div>

                <?php if (empty($gsc['connected'])): ?>
                    <div class="laca-google-card__empty-state">
                        <span class="dashicons dashicons-google laca-google-card__empty-icon"></span>
                        <h3 class="laca-google-card__empty-title"><?php esc_html_e('Chưa kết nối Google Search Console', 'lacadev'); ?></h3>
                        <p class="laca-google-card__empty-desc">
                            <?php esc_html_e('Kết nối tài khoản Google để theo dõi thứ hạng từ khóa, lượt hiển thị và số lượt click tự nhiên từ Google.', 'lacadev'); ?>
                        </p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-primary">
                            <?php esc_html_e('Thiết lập Search Console', 'lacadev'); ?> &rarr;
                        </a>
                    </div>
                <?php elseif (!empty($gsc['error'])): ?>
                    <div class="laca-google-card__error-wrap">
                        <div class="laca-alert laca-alert--error" role="alert">
                            <span class="dashicons dashicons-no-alt laca-alert__icon" aria-hidden="true"></span>
                            <div class="laca-alert__content">
                                <h4 class="laca-alert__title">
                                    <?php
                                    if (!empty($gsc['status'])) {
                                        printf(esc_html__('Lỗi Google Search Console (HTTP %d)', 'lacadev'), (int) $gsc['status']);
                                    } else {
                                        esc_html_e('Lỗi kết nối Google Search Console', 'lacadev');
                                    }
                                    ?>
                                </h4>
                                <p class="laca-alert__description"><?php echo esc_html($gsc['error']); ?></p>
                                <a href="<?php echo esc_url($gsc['setup_url'] ?? admin_url('admin.php?page=laca-google-search-console')); ?>" class="button button-secondary button-small">
                                    <?php esc_html_e('Chọn property khác', 'lacadev'); ?> &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($gsc['isCollecting'])): ?>
                        <div class="laca-google-card__notice-wrap">
                            <div class="laca-alert laca-alert--info">
                                <span class="dashicons dashicons-update laca-alert__icon" aria-hidden="true"></span>
                                <div class="laca-alert__content">
                                    <h4 class="laca-alert__title"><?php esc_html_e('Đang thu thập dữ liệu', 'lacadev'); ?></h4>
                                    <p class="laca-alert__description"><?php esc_html_e('Google cần 24–48 giờ để bắt đầu có số liệu báo cáo.', 'lacadev'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!empty($gsc['isZeroData'])): ?>
                        <div class="laca-google-card__notice-wrap">
                            <div class="laca-alert laca-alert--info">
                                <span class="dashicons dashicons-info laca-alert__icon" aria-hidden="true"></span>
                                <div class="laca-alert__content">
                                    <h4 class="laca-alert__title"><?php esc_html_e('Chưa có lượt truy cập trong khoảng này', 'lacadev'); ?></h4>
                                    <p class="laca-alert__description"><?php esc_html_e('Không có lượt hiển thị hay nhấp chuột nào được ghi nhận.', 'lacadev'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Dữ liệu đến ngày -->
                    <div class="laca-google-card__date-bar">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span><?php printf(esc_html__('Dữ liệu đến ngày %s', 'lacadev'), esc_html($gsc['dataDate'] ?? date('d/m', strtotime('-1 day')))); ?></span>
                    </div>

                    <!-- 4 KPI Blocks GSC -->
                    <div class="laca-seo-stats-grid laca-google-stats-grid">
                        <!-- Clicks -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('Tổng lượt click', 'lacadev'); ?></span>
                                <div class="laca-google-stat-card__value-wrap">
                                    <strong class="laca-seo-stat-card__value">
                                        <?php echo number_format_i18n((int) ($gsc['clicks'] ?? 0)); ?>
                                    </strong>
                                    <?php $cDelta = (string) ($gsc['clicks_delta'] ?? '—'); ?>
                                    <span class="laca-delta <?php echo str_starts_with($cDelta, '+') ? 'laca-delta--up' : (str_starts_with($cDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                        <?php echo esc_html($cDelta); ?>
                                    </span>
                                </div>
                                <small class="laca-google-stat-card__sub"><?php esc_html_e('so với kỳ trước', 'lacadev'); ?></small>
                            </div>
                        </div>

                        <!-- Impressions -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('Lượt hiển thị', 'lacadev'); ?></span>
                                <div class="laca-google-stat-card__value-wrap">
                                    <strong class="laca-seo-stat-card__value">
                                        <?php echo number_format_i18n((int) ($gsc['impressions'] ?? 0)); ?>
                                    </strong>
                                    <?php $iDelta = (string) ($gsc['impressions_delta'] ?? '—'); ?>
                                    <span class="laca-delta <?php echo str_starts_with($iDelta, '+') ? 'laca-delta--up' : (str_starts_with($iDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                        <?php echo esc_html($iDelta); ?>
                                    </span>
                                </div>
                                <small class="laca-google-stat-card__sub"><?php esc_html_e('so với kỳ trước', 'lacadev'); ?></small>
                            </div>
                        </div>

                        <!-- CTR -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('CTR trung bình', 'lacadev'); ?></span>
                                <div class="laca-google-stat-card__value-wrap">
                                    <strong class="laca-seo-stat-card__value">
                                        <?php echo esc_html((string) ($gsc['ctr'] ?? 0)); ?>%
                                    </strong>
                                    <?php $ctrDelta = (string) ($gsc['ctr_delta'] ?? '—'); ?>
                                    <span class="laca-delta <?php echo str_starts_with($ctrDelta, '+') ? 'laca-delta--up' : (str_starts_with($ctrDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                        <?php echo esc_html($ctrDelta); ?>
                                    </span>
                                </div>
                                <small class="laca-google-stat-card__sub"><?php esc_html_e('tỷ lệ nhấp', 'lacadev'); ?></small>
                            </div>
                        </div>

                        <!-- Average Position -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('Vị trí trung bình', 'lacadev'); ?></span>
                                <div class="laca-google-stat-card__value-wrap">
                                    <strong class="laca-seo-stat-card__value">
                                        #<?php echo esc_html((string) ($gsc['position'] ?? 0)); ?>
                                    </strong>
                                    <?php $posDelta = (string) ($gsc['position_delta'] ?? '—'); ?>
                                    <span class="laca-delta <?php echo str_starts_with($posDelta, '-') ? 'laca-delta--up' : (str_starts_with($posDelta, '+') ? 'laca-delta--down' : ''); ?>">
                                        <?php echo esc_html($posDelta); ?>
                                    </span>
                                </div>
                                <small class="laca-google-stat-card__sub"><?php esc_html_e('thứ hạng Google', 'lacadev'); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Biểu đồ GSC: Click & Lượt hiển thị theo ngày -->
                    <?php if (!empty($gsc['clicksSeries']) || !empty($gsc['impressionsSeries'])): ?>
                        <div class="laca-google-card__chart-section">
                            <?php if (!empty($gsc['clicksSeries'])): ?>
                                <div class="laca-google-card__chart-wrap">
                                    <div class="laca-google-card__chart-header">
                                        <h4 class="laca-google-card__chart-title"><?php esc_html_e('Lượt click theo ngày', 'lacadev'); ?></h4>
                                    </div>
                                    <?php
                                    echo \App\Features\GoogleServices\Chart::line([
                                        [
                                            'name'   => __('Lượt click', 'lacadev'),
                                            'data'   => $gsc['clicksSeries'],
                                            'dashed' => false,
                                        ],
                                    ], [
                                        'title'  => __('Biểu đồ lượt click theo ngày', 'lacadev'),
                                        'height' => 130,
                                    ]);
                                    ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($gsc['impressionsSeries'])): ?>
                                <div class="laca-google-card__chart-wrap">
                                    <div class="laca-google-card__chart-header">
                                        <h4 class="laca-google-card__chart-title"><?php esc_html_e('Lượt hiển thị theo ngày', 'lacadev'); ?></h4>
                                    </div>
                                    <?php
                                    echo \App\Features\GoogleServices\Chart::line([
                                        [
                                            'name'   => __('Lượt hiển thị', 'lacadev'),
                                            'data'   => $gsc['impressionsSeries'],
                                            'dashed' => false,
                                        ],
                                    ], [
                                        'title'  => __('Biểu đồ lượt hiển thị theo ngày', 'lacadev'),
                                        'height' => 130,
                                    ]);
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Bảng Top từ khóa tìm kiếm -->
                    <div class="laca-google-table-section">
                        <div class="laca-google-table-section__header">
                            <h4 class="laca-google-table-section__title">
                                <?php esc_html_e('Top từ khóa tìm kiếm hàng đầu', 'lacadev'); ?>
                            </h4>
                            <button
                                type="button"
                                class="laca-google-btn-link"
                                id="laca-open-gsc-queries-btn"
                                data-laca-gsc-all="1"
                                aria-haspopup="dialog"
                                aria-expanded="false"
                            >
                                <?php esc_html_e('Xem tất cả (100 từ khoá)', 'lacadev'); ?> &rarr;
                            </button>
                        </div>
                        <div class="laca-seo-table-responsive laca-google-table-wrap">
                            <table class="wp-list-table widefat striped laca-google-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Từ khóa', 'lacadev'); ?></th>
                                        <th class="laca-col-right laca-col-w65"><?php esc_html_e('Clicks', 'lacadev'); ?></th>
                                        <th class="laca-col-right laca-col-w75"><?php esc_html_e('Hiển thị', 'lacadev'); ?></th>
                                        <th class="laca-col-right laca-col-w60"><?php esc_html_e('Vị trí', 'lacadev'); ?></th>
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
                                            <td colspan="4" class="laca-table-empty">
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
                                                <td class="laca-col-right laca-cell-bold"><?php echo number_format_i18n($qClicks); ?></td>
                                                <td class="laca-col-right laca-cell-muted"><?php echo number_format_i18n($qImpressions); ?></td>
                                                <td class="laca-col-right"><?php echo esc_html((string) $qPos); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bảng Top trang đích Organic -->
                    <?php if (!empty($gsc['pages'])): ?>
                        <div class="laca-google-table-section">
                            <h4 class="laca-google-table-section__title">
                                <?php esc_html_e('Top trang đích có lượng truy cập cao', 'lacadev'); ?>
                            </h4>
                            <div class="laca-seo-table-responsive laca-google-table-wrap">
                                <table class="wp-list-table widefat striped laca-google-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Đường dẫn trang', 'lacadev'); ?></th>
                                            <th class="laca-col-right laca-col-w65"><?php esc_html_e('Clicks', 'lacadev'); ?></th>
                                            <th class="laca-col-right laca-col-w60"><?php esc_html_e('CTR', 'lacadev'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($gsc['pages'], 0, 5) as $pg): ?>
                                            <tr>
                                                <td><code><?php echo esc_html($pg['url']); ?></code></td>
                                                <td class="laca-col-right laca-cell-bold"><?php echo number_format_i18n((int) ($pg['clicks'] ?? 0)); ?></td>
                                                <td class="laca-col-right laca-cell-muted"><?php echo esc_html((string) ($pg['ctr'] ?? 0)); ?>%</td>
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
            <div class="laca-seo-card laca-google-card">
                <div class="laca-seo-card__header laca-seo-header-flex laca-google-card__header">
                    <div>
                        <h2 class="laca-seo-card__title laca-google-card__title">
                            <span class="dashicons dashicons-chart-area"></span>
                            <?php esc_html_e('Google Analytics 4', 'lacadev'); ?>
                        </h2>
                        <p class="laca-seo-card__subtitle laca-google-card__subtitle">
                            <?php esc_html_e('Bên trong trang — Lưu lượng & Tương tác thực tế', 'lacadev'); ?>
                        </p>
                    </div>
                    <div class="laca-google-card__header-actions">
                        <span class="laca-badge">On-Site Behavior</span>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-small">
                            <?php esc_html_e('Cài đặt', 'lacadev'); ?>
                        </a>
                    </div>
                </div>

                <?php if (empty($ga['connected'])): ?>
                    <div class="laca-google-card__empty-state">
                        <span class="dashicons dashicons-chart-pie laca-google-card__empty-icon"></span>
                        <h3 class="laca-google-card__empty-title"><?php esc_html_e('Chưa kết nối Google Analytics 4', 'lacadev'); ?></h3>
                        <p class="laca-google-card__empty-desc">
                            <?php esc_html_e('Kết nối GA4 để xem số lượng người dùng thực tế, nguồn truy cập, thời lượng tương tác và tỷ lệ chuyển đổi.', 'lacadev'); ?>
                        </p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-primary">
                            <?php esc_html_e('Thiết lập Google Analytics', 'lacadev'); ?> &rarr;
                        </a>
                    </div>
                <?php elseif (!empty($ga['error'])): ?>
                    <div class="laca-google-card__error-wrap">
                        <div class="laca-alert laca-alert--error" role="alert">
                            <span class="dashicons dashicons-no-alt laca-alert__icon" aria-hidden="true"></span>
                            <div class="laca-alert__content">
                                <h4 class="laca-alert__title">
                                    <?php
                                    if (!empty($ga['status'])) {
                                        printf(esc_html__('Lỗi Google Analytics 4 (HTTP %d)', 'lacadev'), (int) $ga['status']);
                                    } else {
                                        esc_html_e('Lỗi kết nối Google Analytics 4', 'lacadev');
                                    }
                                    ?>
                                </h4>
                                <p class="laca-alert__description"><?php echo esc_html($ga['error']); ?></p>
                                <a href="<?php echo esc_url($ga['setup_url'] ?? admin_url('admin.php?page=laca-google-analytics')); ?>" class="button button-secondary button-small">
                                    <?php esc_html_e('Xem cài đặt Analytics', 'lacadev'); ?> &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($ga['isCollecting'])): ?>
                        <div class="laca-google-card__notice-wrap">
                            <div class="laca-alert laca-alert--info">
                                <span class="dashicons dashicons-update laca-alert__icon" aria-hidden="true"></span>
                                <div class="laca-alert__content">
                                    <h4 class="laca-alert__title"><?php esc_html_e('Đang thu thập dữ liệu', 'lacadev'); ?></h4>
                                    <p class="laca-alert__description"><?php esc_html_e('Google cần 24–48 giờ để bắt đầu có số liệu báo cáo.', 'lacadev'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!empty($ga['isZeroData'])): ?>
                        <div class="laca-google-card__notice-wrap">
                            <div class="laca-alert laca-alert--info">
                                <span class="dashicons dashicons-info laca-alert__icon" aria-hidden="true"></span>
                                <div class="laca-alert__content">
                                    <h4 class="laca-alert__title"><?php esc_html_e('Chưa có lượt truy cập trong khoảng này', 'lacadev'); ?></h4>
                                    <p class="laca-alert__description"><?php esc_html_e('Không có phiên hay người dùng nào được ghi nhận.', 'lacadev'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Ô Realtime Active Users (30 phút qua) - Đơn sắc neutral -->
                    <div class="laca-google-realtime">
                        <div class="laca-google-realtime__info">
                            <span class="laca-google-realtime__dot"></span>
                            <div>
                                <div class="laca-google-realtime__title">
                                    <?php esc_html_e('Đang hoạt động (30 phút qua)', 'lacadev'); ?>
                                </div>
                                <div class="laca-google-realtime__desc">
                                    <?php esc_html_e('Người dùng trực tiếp trên website theo GA4 Realtime', 'lacadev'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="laca-google-realtime__count-wrap">
                            <strong id="laca-ga4-realtime-count" class="laca-ga4-realtime-count laca-google-realtime__value">
                                <?php echo number_format_i18n((int) ($ga['realtimeUsers'] ?? 0)); ?>
                            </strong>
                            <span class="laca-google-realtime__unit"><?php esc_html_e('người', 'lacadev'); ?></span>
                        </div>
                    </div>

                    <?php if (($ga['users'] ?? 0) === 0 && ($ga['realtimeUsers'] ?? 0) > 0): ?>
                        <div class="notice notice-info inline laca-google-realtime-notice">
                            <p>
                                <span class="dashicons dashicons-info"></span>
                                <span><?php esc_html_e('GA4 cần 24–48 giờ để xử lý báo cáo; số đang hoạt động là dữ liệu trực tiếp', 'lacadev'); ?></span>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- 4 KPI Blocks GA4 -->
                    <div class="laca-seo-stats-grid laca-google-stats-grid">
                        <!-- Active Users -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('Người dùng hoạt động', 'lacadev'); ?></span>
                                <div class="laca-google-stat-card__value-wrap">
                                    <strong class="laca-seo-stat-card__value">
                                        <?php echo number_format_i18n((int) ($ga['users'] ?? 0)); ?>
                                    </strong>
                                    <?php $uDelta = (string) ($ga['users_delta'] ?? '—'); ?>
                                    <span class="laca-delta <?php echo str_starts_with($uDelta, '+') ? 'laca-delta--up' : (str_starts_with($uDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                        <?php echo esc_html($uDelta); ?>
                                    </span>
                                </div>
                                <small class="laca-google-stat-card__sub"><?php esc_html_e('so với kỳ trước', 'lacadev'); ?></small>
                            </div>
                        </div>

                        <!-- Sessions -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('Số phiên truy cập', 'lacadev'); ?></span>
                                <div class="laca-google-stat-card__value-wrap">
                                    <strong class="laca-seo-stat-card__value">
                                        <?php echo number_format_i18n((int) ($ga['sessions'] ?? 0)); ?>
                                    </strong>
                                    <?php $sDelta = (string) ($ga['sessions_delta'] ?? '—'); ?>
                                    <span class="laca-delta <?php echo str_starts_with($sDelta, '+') ? 'laca-delta--up' : (str_starts_with($sDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                        <?php echo esc_html($sDelta); ?>
                                    </span>
                                </div>
                                <small class="laca-google-stat-card__sub"><?php esc_html_e('lượt ghé thăm', 'lacadev'); ?></small>
                            </div>
                        </div>

                        <!-- Avg Engagement Duration -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('Thời gian tương tác TB', 'lacadev'); ?></span>
                                <div class="laca-google-stat-card__value-wrap">
                                    <strong class="laca-seo-stat-card__value">
                                        <?php echo esc_html($ga['avgEngagement'] ?? '—'); ?>
                                    </strong>
                                    <?php $dDelta = (string) ($ga['avgEngagement_delta'] ?? '—'); ?>
                                    <span class="laca-delta <?php echo str_starts_with($dDelta, '+') ? 'laca-delta--up' : (str_starts_with($dDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                        <?php echo esc_html($dDelta); ?>
                                    </span>
                                </div>
                                <small class="laca-google-stat-card__sub"><?php esc_html_e('trên mỗi phiên', 'lacadev'); ?></small>
                            </div>
                        </div>

                        <!-- Conversion Rate -->
                        <div class="laca-seo-stat-card">
                            <div class="laca-seo-stat-card__info">
                                <span class="laca-seo-stat-card__label"><?php esc_html_e('Tỷ lệ chuyển đổi', 'lacadev'); ?></span>
                                <?php if (!empty($ga['conversionRate'])): ?>
                                    <div class="laca-google-stat-card__value-wrap">
                                        <strong class="laca-seo-stat-card__value">
                                            <?php echo esc_html((string) $ga['conversionRate']); ?>
                                        </strong>
                                        <?php $cvDelta = (string) ($ga['conversionRate_delta'] ?? '—'); ?>
                                        <span class="laca-delta <?php echo str_starts_with($cvDelta, '+') ? 'laca-delta--up' : (str_starts_with($cvDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                            <?php echo esc_html($cvDelta); ?>
                                        </span>
                                    </div>
                                    <small class="laca-google-stat-card__sub"><?php esc_html_e('sự kiện chính / phiên', 'lacadev'); ?></small>
                                <?php else: ?>
                                    <div class="laca-google-stat-card__empty-conversion">
                                        <span class="laca-cell-muted"><?php esc_html_e('Chưa cấu hình sự kiện chính trong GA4', 'lacadev'); ?></span>
                                    </div>
                                    <small class="laca-google-stat-card__sub">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-google-analytics')); ?>" class="laca-google-btn-link">
                                            <?php esc_html_e('Cấu hình mục tiêu', 'lacadev'); ?> &rarr;
                                        </a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Biểu đồ GA4: Người dùng theo ngày (Kỳ này vs Kỳ trước) -->
                    <?php if (!empty($ga['chartSeries']['curr'])): ?>
                        <div class="laca-google-card__chart-section">
                            <div class="laca-google-card__chart-wrap">
                                <div class="laca-google-card__chart-header">
                                    <h4 class="laca-google-card__chart-title"><?php esc_html_e('Người dùng theo ngày', 'lacadev'); ?></h4>
                                    <div class="laca-google-card__chart-legend">
                                        <span class="laca-google-card__legend-item laca-google-card__legend-item--curr">
                                            <span class="laca-google-card__legend-line laca-google-card__legend-line--solid"></span>
                                            <?php esc_html_e('Kỳ này', 'lacadev'); ?>
                                        </span>
                                        <?php if (!empty($ga['chartSeries']['prev'])): ?>
                                            <span class="laca-google-card__legend-item laca-google-card__legend-item--prev">
                                                <span class="laca-google-card__legend-line laca-google-card__legend-line--dashed"></span>
                                                <?php esc_html_e('Kỳ trước', 'lacadev'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                                $gaSeries = [
                                    [
                                        'name'   => __('Kỳ này', 'lacadev'),
                                        'data'   => $ga['chartSeries']['curr'],
                                        'dashed' => false,
                                    ],
                                ];
                                if (!empty($ga['chartSeries']['prev'])) {
                                    $gaSeries[] = [
                                        'name'   => __('Kỳ trước', 'lacadev'),
                                        'data'   => $ga['chartSeries']['prev'],
                                        'dashed' => true,
                                    ];
                                }
                                echo \App\Features\GoogleServices\Chart::line($gaSeries, [
                                    'title'  => __('Biểu đồ người dùng theo ngày', 'lacadev'),
                                    'height' => 160,
                                ]);
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Nguồn lưu lượng (Traffic Channels) -->
                    <div class="laca-google-table-section">
                        <h4 class="laca-google-table-section__title">
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
                            <div class="laca-google-channels__bar">
                                <?php foreach ($ga['channels'] as $idx => $ch): ?>
                                    <div
                                        class="laca-google-channels__segment laca-google-channels__segment--<?php echo esc_attr((string) ($idx % 5)); ?>"
                                        style="--w: <?php echo esc_attr((string) $ch['share']); ?>%; /* động */"
                                        title="<?php echo esc_attr($ch['name'] . ': ' . $ch['share'] . '%'); ?>"
                                    ></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="laca-google-channels__list">
                                <?php foreach ($ga['channels'] as $idx => $ch): ?>
                                    <div class="laca-google-channels__item">
                                        <span class="laca-google-channels__dot laca-google-channels__dot--<?php echo esc_attr((string) ($idx % 5)); ?>"></span>
                                        <strong><?php echo esc_html($ch['name']); ?>: <?php echo esc_html((string) $ch['share']); ?>%</strong>
                                        <span class="laca-cell-muted">(<?php echo number_format_i18n((int) $ch['sessions']); ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="laca-table-empty">
                                <?php esc_html_e('Chưa có dữ liệu kênh.', 'lacadev'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Khối Hành động mục tiêu / Chuyển đổi (Business KPI / Key Events) -->
                    <?php if (!empty($ga['businessKpis'])): ?>
                        <div class="laca-google-table-section">
                            <h4 class="laca-google-table-section__title">
                                <?php esc_html_e('Hành động mục tiêu giá trị (Key Events / Conversions)', 'lacadev'); ?>
                            </h4>
                            <div class="laca-google-kpis-grid">
                                <?php foreach ($ga['businessKpis'] as $kpi): ?>
                                    <?php $kDelta = (string) ($kpi['delta'] ?? '—'); ?>
                                    <div class="laca-google-kpis-grid__card">
                                        <div class="laca-google-kpis-grid__label"><?php echo esc_html($kpi['title']); ?></div>
                                        <div class="laca-google-kpis-grid__value">
                                            <?php echo number_format_i18n((int) $kpi['value']); ?>
                                            <span class="laca-google-kpis-grid__unit"><?php esc_html_e('lượt', 'lacadev'); ?></span>
                                        </div>
                                        <small class="laca-delta <?php echo str_starts_with($kDelta, '+') ? 'laca-delta--up' : (str_starts_with($kDelta, '-') ? 'laca-delta--down' : ''); ?>">
                                            <?php echo esc_html($kDelta); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Bảng Top trang có lượt xem cao & Thời gian tương tác TB -->
                    <div class="laca-google-table-section">
                        <h4 class="laca-google-table-section__title">
                            <?php esc_html_e('Top trang có lượt xem cao & Thời gian tương tác', 'lacadev'); ?>
                        </h4>
                        <div class="laca-seo-table-responsive laca-google-table-wrap">
                            <table class="wp-list-table widefat striped laca-google-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Trang nội dung', 'lacadev'); ?></th>
                                        <th class="laca-col-right laca-col-w75"><?php esc_html_e('Lượt xem', 'lacadev'); ?></th>
                                        <th class="laca-col-right laca-col-w95"><?php esc_html_e('Tương tác TB', 'lacadev'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ga['topPages'])): ?>
                                        <tr>
                                            <td colspan="3" class="laca-table-empty">
                                                <?php esc_html_e('Chưa có dữ liệu trang trong khoảng thời gian này.', 'lacadev'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ga['topPages'] as $tp): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($tp['title'] ?? $tp['pageTitle'] ?? 'Trang'); ?></strong><br>
                                                    <small class="laca-cell-muted"><?php echo esc_html($tp['path'] ?? $tp['pagePath'] ?? '/'); ?></small>
                                                </td>
                                                <td class="laca-col-right laca-cell-bold">
                                                    <?php echo number_format_i18n((int) ($tp['views'] ?? $tp['screenPageViews'] ?? 0)); ?>
                                                </td>
                                                <td class="laca-col-right laca-cell-muted">
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

    <!-- ==================== 4. KHỐI PAGESPEED INSIGHTS & WEB VITALS ==================== -->
    <?php
    $psiData = null;
    if ($isMock) {
        $psiData = \App\Features\GoogleServices\PageSpeedClient::getMockData(home_url('/'), 'mobile');
    } else {
        $psiCached = get_transient('laca_psi_' . md5(home_url('/') . '_mobile'));
        if (is_array($psiCached)) {
            $psiData = $psiCached;
        }
    }
    include __DIR__ . '/pagespeed-card.php';
    ?>

    <!-- ==================== 5. DIALOG 100 TỪ KHOÁ ==================== -->
    <dialog id="laca-gsc-dialog" class="laca-google-dialog">
        <div class="laca-google-dialog__header">
            <h3 class="laca-google-dialog__title"><?php esc_html_e('100 từ khoá tìm kiếm hàng đầu', 'lacadev'); ?></h3>
            <button type="button" class="laca-google-dialog__close" id="laca-gsc-queries-close" aria-label="<?php esc_attr_e('Đóng', 'lacadev'); ?>">&times;</button>
        </div>
        <div class="laca-google-dialog__toolbar">
            <input
                type="search"
                id="laca-gsc-queries-search"
                class="laca-google-dialog__search"
                placeholder="<?php esc_attr_e('Lọc theo từ khoá...', 'lacadev'); ?>"
                aria-label="<?php esc_attr_e('Lọc theo từ khoá', 'lacadev'); ?>"
            />
            <span id="laca-gsc-queries-count" class="laca-google-dialog__count"></span>
        </div>
        <div class="laca-google-dialog__body" id="laca-gsc-queries-body">
            <div class="laca-google-dialog__loading" id="laca-gsc-queries-loading">
                <span class="dashicons dashicons-update laca-spin"></span>
                <?php esc_html_e('Đang tải dữ liệu từ khoá từ Google Search Console...', 'lacadev'); ?>
            </div>
            <div class="laca-google-dialog__content" id="laca-gsc-queries-content"></div>
        </div>
    </dialog>
</div>
