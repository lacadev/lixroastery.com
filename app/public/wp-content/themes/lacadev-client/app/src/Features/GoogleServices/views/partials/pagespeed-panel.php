<?php
/**
 * PageSpeed Insights Single Strategy Panel Partial.
 * Renders performance metrics for a specific strategy (mobile or desktop).
 *
 * @package LacaDevClient
 *
 * @var string $strategy 'mobile'|'desktop'
 * @var array<string, mixed>|null $psiData
 * @var string|null $siteUrl
 * @var bool $isActive
 */

defined('ABSPATH') || exit;

$strategy = strtolower((string) ($strategy ?? ($psiData['strategy'] ?? 'mobile')));
$isDesktop = ($strategy === 'desktop');
$isActive = !empty($isActive);
$siteUrl = $siteUrl ?? home_url('/');

$hasData = !empty($psiData) && (!empty($psiData['field']['hasData']) || !empty($psiData['lab']['score']));
$hasField = !empty($psiData['field']['hasData']);
$testedDate = (string) ($psiData['testedDate'] ?? '');
$labScore = isset($psiData['lab']['score']) ? (int) $psiData['lab']['score'] : null;
$labScoreCategory = (string) ($psiData['lab']['scoreCategory'] ?? '—');
$labScoreClass = (string) ($psiData['lab']['scoreClass'] ?? 'average');
?>
<div
    class="laca-google-psi__panel"
    data-strategy="<?php echo esc_attr($strategy); ?>"
    role="tabpanel"
    id="laca-psi-panel-<?php echo esc_attr($strategy); ?>"
    aria-labelledby="laca-psi-tab-<?php echo esc_attr($strategy); ?>"
    <?php echo !$isActive ? 'hidden' : ''; ?>
>
    <?php if (!$hasData): ?>
        <div class="laca-google-psi__empty">
            <p>
                <?php
                if ($isDesktop) {
                    esc_html_e('Chưa có dữ liệu kiểm tra tốc độ gần đây trên Máy tính cho trang chủ. Bạn có thể kiểm tra hiệu năng Core Web Vitals ngay trong trang quản trị.', 'lacadev');
                } else {
                    esc_html_e('Chưa có dữ liệu kiểm tra tốc độ gần đây trên Di động cho trang chủ. Bạn có thể kiểm tra hiệu năng Core Web Vitals ngay trong trang quản trị.', 'lacadev');
                }
                ?>
            </p>
            <button type="button" class="button button-secondary laca-google-psi__run-btn" data-laca-psi-run>
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <?php esc_html_e('Kiểm tra PageSpeed', 'lacadev'); ?>
            </button>
        </div>
    <?php elseif ($hasField): ?>
        <?php
        $fMetrics = $psiData['field']['metrics'] ?? [];
        $lcp = $fMetrics['lcp'] ?? [];
        $inp = $fMetrics['inp'] ?? [];
        $cls = $fMetrics['cls'] ?? [];
        ?>
        <div class="laca-google-psi__grid">
            <!-- LCP -->
            <div class="laca-google-psi__metric <?php echo !empty($lcp['categoryClass']) ? esc_attr('laca-google-psi__metric--' . $lcp['categoryClass']) : ''; ?>">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php echo esc_html($lcp['label'] ?? __('LCP (Nội dung lớn nhất)', 'lacadev')); ?></span>
                    <span class="laca-google-psi__badge"><?php echo esc_html($lcp['category'] ?? '—'); ?></span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo esc_html($lcp['value'] ?? '—'); ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Nội dung chính hiển thị trên màn hình', 'lacadev'); ?></div>
            </div>

            <!-- INP -->
            <div class="laca-google-psi__metric <?php echo !empty($inp['categoryClass']) ? esc_attr('laca-google-psi__metric--' . $inp['categoryClass']) : ''; ?>">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php echo esc_html($inp['label'] ?? __('INP (Tương tác đến phản hồi)', 'lacadev')); ?></span>
                    <span class="laca-google-psi__badge"><?php echo esc_html($inp['category'] ?? '—'); ?></span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo esc_html($inp['value'] ?? '—'); ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Phản hồi thao tác bấm/cuộn tức thì', 'lacadev'); ?></div>
            </div>

            <!-- CLS -->
            <div class="laca-google-psi__metric <?php echo !empty($cls['categoryClass']) ? esc_attr('laca-google-psi__metric--' . $cls['categoryClass']) : ''; ?>">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php echo esc_html($cls['label'] ?? __('CLS (Dịch chuyển bố cục)', 'lacadev')); ?></span>
                    <span class="laca-google-psi__badge"><?php echo esc_html($cls['category'] ?? '—'); ?></span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo esc_html($cls['value'] ?? '—'); ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Bố cục giao diện hiển thị ổn định', 'lacadev'); ?></div>
            </div>

            <!-- Lighthouse Score -->
            <div class="laca-google-psi__metric <?php echo !empty($labScoreClass) ? esc_attr('laca-google-psi__metric--' . $labScoreClass) : ''; ?>">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php esc_html_e('Lighthouse Điểm hiệu năng', 'lacadev'); ?></span>
                    <span class="laca-google-psi__badge"><?php echo esc_html($labScoreCategory); ?></span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo $labScore !== null ? esc_html($labScore . '/100') : '—'; ?></div>
                <div class="laca-google-psi__metric-desc"><?php echo $isDesktop ? esc_html__('Điểm hiệu năng tổng thể trên thiết bị máy tính', 'lacadev') : esc_html__('Điểm hiệu năng tổng thể trên thiết bị di động', 'lacadev'); ?></div>
            </div>
        </div>

        <div class="laca-google-psi__footer">
            <span><?php printf(esc_html__('Đo lúc %s · %s', 'lacadev'), esc_html($testedDate ?: date('d/m/Y H:i')), $isDesktop ? __('Máy tính', 'lacadev') : __('Di động', 'lacadev')); ?></span>
            <button type="button" class="button button-secondary button-small laca-google-psi__run-btn" data-laca-psi-run>
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <?php esc_html_e('Kiểm tra lại', 'lacadev'); ?>
            </button>
            <a href="<?php echo esc_url('https://pagespeed.web.dev/report?url=' . rawurlencode($siteUrl) . ($isDesktop ? '&form_factor=desktop' : '')); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary button-small laca-google-psi__ext-link">
                <span><?php esc_html_e('Chi tiết', 'lacadev'); ?></span>
                <span class="dashicons dashicons-external" aria-hidden="true"></span>
            </a>
        </div>
    <?php else: ?>
        <div class="laca-google-psi__notice">
            <?php esc_html_e('Chưa đủ dữ liệu người dùng thực (CrUX) cho URL này — hiển thị kết quả đo thử (Lighthouse)', 'lacadev'); ?>
        </div>

        <?php
        $labMetrics = $psiData['lab']['metrics'] ?? [];
        $labLcp = $labMetrics['lcp'] ?? [];
        $labCls = $labMetrics['cls'] ?? [];
        $labTbt = $labMetrics['tbt'] ?? [];
        $labFcp = $labMetrics['fcp'] ?? [];
        ?>
        <div class="laca-google-psi__grid">
            <!-- Lighthouse Score -->
            <div class="laca-google-psi__metric <?php echo !empty($labScoreClass) ? esc_attr('laca-google-psi__metric--' . $labScoreClass) : ''; ?>">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php esc_html_e('Lighthouse Điểm hiệu năng', 'lacadev'); ?></span>
                    <span class="laca-google-psi__badge"><?php echo esc_html($labScoreCategory); ?></span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo $labScore !== null ? esc_html($labScore . '/100') : '—'; ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Điểm hiệu năng phòng thí nghiệm', 'lacadev'); ?></div>
            </div>

            <!-- Lab LCP -->
            <div class="laca-google-psi__metric">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php echo esc_html($labLcp['label'] ?? __('LCP (Nội dung lớn nhất)', 'lacadev')); ?></span>
                    <span class="laca-google-psi__badge">Lab</span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo esc_html($labLcp['displayValue'] ?? '—'); ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Thời gian tải nội dung lớn nhất', 'lacadev'); ?></div>
            </div>

            <!-- Lab CLS -->
            <div class="laca-google-psi__metric">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php echo esc_html($labCls['label'] ?? __('CLS (Dịch chuyển bố cục)', 'lacadev')); ?></span>
                    <span class="laca-google-psi__badge">Lab</span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo esc_html($labCls['displayValue'] ?? '—'); ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Độ dịch chuyển bố cục trang', 'lacadev'); ?></div>
            </div>

            <!-- Lab TBT -->
            <div class="laca-google-psi__metric">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php echo esc_html($labTbt['label'] ?? __('TBT (Thời gian chặn)', 'lacadev')); ?></span>
                    <span class="laca-google-psi__badge">Lab</span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo esc_html($labTbt['displayValue'] ?? '—'); ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Tổng thời gian luồng chính bị chặn', 'lacadev'); ?></div>
            </div>

            <!-- Lab FCP -->
            <div class="laca-google-psi__metric">
                <div class="laca-google-psi__metric-header">
                    <span class="laca-google-psi__metric-label"><?php echo esc_html($labFcp['label'] ?? __('FCP (Nội dung đầu tiên)', 'lacadev')); ?></span>
                    <span class="laca-google-psi__badge">Lab</span>
                </div>
                <div class="laca-google-psi__metric-value"><?php echo esc_html($labFcp['displayValue'] ?? '—'); ?></div>
                <div class="laca-google-psi__metric-desc"><?php esc_html_e('Thời gian xuất hiện phần tử đầu tiên', 'lacadev'); ?></div>
            </div>
        </div>

        <div class="laca-google-psi__footer">
            <span><?php printf(esc_html__('Đo lúc %s · %s', 'lacadev'), esc_html($testedDate ?: date('d/m/Y H:i')), $isDesktop ? __('Máy tính', 'lacadev') : __('Di động', 'lacadev')); ?></span>
            <button type="button" class="button button-secondary button-small laca-google-psi__run-btn" data-laca-psi-run>
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <?php esc_html_e('Kiểm tra lại', 'lacadev'); ?>
            </button>
            <a href="<?php echo esc_url('https://pagespeed.web.dev/report?url=' . rawurlencode($siteUrl) . ($isDesktop ? '&form_factor=desktop' : '')); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary button-small laca-google-psi__ext-link">
                <span><?php esc_html_e('Chi tiết', 'lacadev'); ?></span>
                <span class="dashicons dashicons-external" aria-hidden="true"></span>
            </a>
        </div>
    <?php endif; ?>
</div>
