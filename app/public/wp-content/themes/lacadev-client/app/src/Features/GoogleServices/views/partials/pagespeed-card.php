<?php
/**
 * PageSpeed Insights Card Partial.
 * Renders Core Web Vitals (CrUX) and Lighthouse (Lab) performance metrics
 * with dual panels for Mobile and Desktop rendered upfront for instant switching.
 *
 * @package LacaDevClient
 *
 * @var array<string, mixed>|null $psiData
 * @var array<string, mixed>|null $psiMobile
 * @var array<string, mixed>|null $psiDesktop
 * @var string|null $siteUrl
 * @var string|null $activeStrategy
 * @var bool|null $isMock
 */

defined('ABSPATH') || exit;

$siteUrl = $siteUrl ?? home_url('/');
$activeStrategy = strtolower((string) ($activeStrategy ?? ($psiData['strategy'] ?? 'mobile')));
if ($activeStrategy !== 'desktop') {
    $activeStrategy = 'mobile';
}
$isDesktop = ($activeStrategy === 'desktop');
$isMock = !empty($isMock);

// If empty data explicitly passed as an array, treat both strategies as empty
if (isset($psiData) && is_array($psiData) && empty($psiData)) {
    $psiMobile  = $psiMobile ?? [];
    $psiDesktop = $psiDesktop ?? [];
}

// 1. Resolve Mobile Data
if (!isset($psiMobile)) {
    if (isset($psiData) && (!isset($psiData['strategy']) || strtolower((string) $psiData['strategy']) === 'mobile')) {
        $psiMobile = $psiData;
    } elseif ($isMock) {
        $psiMobile = \App\Features\GoogleServices\PageSpeedClient::getMockData($siteUrl, 'mobile');
    } else {
        $cachedMobile = get_transient('laca_psi_' . md5($siteUrl . '_mobile'));
        $psiMobile = is_array($cachedMobile) ? $cachedMobile : null;
    }
}

// 2. Resolve Desktop Data
if (!isset($psiDesktop)) {
    if (isset($psiData) && isset($psiData['strategy']) && strtolower((string) $psiData['strategy']) === 'desktop') {
        $psiDesktop = $psiData;
    } elseif ($isMock) {
        $psiDesktop = \App\Features\GoogleServices\PageSpeedClient::getMockData($siteUrl, 'desktop');
    } else {
        $cachedDesktop = get_transient('laca_psi_' . md5($siteUrl . '_desktop'));
        $psiDesktop = is_array($cachedDesktop) ? $cachedDesktop : null;
    }
}

$extReportUrl = 'https://pagespeed.web.dev/report?url=' . rawurlencode($siteUrl) . ($isDesktop ? '&form_factor=desktop' : '');
?>
<div class="laca-seo-card laca-google-psi" id="laca-google-psi-card" data-active-strategy="<?php echo esc_attr($activeStrategy); ?>">
    <div class="laca-google-psi__header">
        <div class="laca-google-psi__title-wrap">
            <span class="dashicons dashicons-performance laca-google-psi__icon" aria-hidden="true"></span>
            <h3 class="laca-google-psi__title">
                <?php esc_html_e('Tốc độ Trang & Trải nghiệm Người dùng (PageSpeed Insights)', 'lacadev'); ?>
            </h3>
            <div class="laca-google-psi__strategy-tabs" role="tablist" aria-label="<?php esc_attr_e('Thiết bị kiểm tra', 'lacadev'); ?>">
                <button
                    type="button"
                    role="tab"
                    id="laca-psi-tab-mobile"
                    aria-controls="laca-psi-panel-mobile"
                    class="button button-small laca-google-psi__tab laca-google-psi-tab <?php echo !$isDesktop ? 'button-primary is-active' : 'button-secondary'; ?>"
                    aria-selected="<?php echo !$isDesktop ? 'true' : 'false'; ?>"
                    aria-pressed="<?php echo !$isDesktop ? 'true' : 'false'; ?>"
                    data-strategy="mobile"
                    tabindex="<?php echo !$isDesktop ? '0' : '-1'; ?>"
                >
                    <span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
                    <span><?php esc_html_e('Di động', 'lacadev'); ?></span>
                </button>
                <button
                    type="button"
                    role="tab"
                    id="laca-psi-tab-desktop"
                    aria-controls="laca-psi-panel-desktop"
                    class="button button-small laca-google-psi__tab laca-google-psi-tab <?php echo $isDesktop ? 'button-primary is-active' : 'button-secondary'; ?>"
                    aria-selected="<?php echo $isDesktop ? 'true' : 'false'; ?>"
                    aria-pressed="<?php echo $isDesktop ? 'true' : 'false'; ?>"
                    data-strategy="desktop"
                    tabindex="<?php echo $isDesktop ? '0' : '-1'; ?>"
                >
                    <span class="dashicons dashicons-desktop" aria-hidden="true"></span>
                    <span><?php esc_html_e('Máy tính', 'lacadev'); ?></span>
                </button>
            </div>
        </div>
        <div class="laca-google-psi__actions">
            <span class="laca-google-psi__strategy-label screen-reader-text" id="laca-psi-strategy-label"><?php echo $isDesktop ? esc_html__('Máy tính', 'lacadev') : esc_html__('Di động', 'lacadev'); ?></span>
            <span class="laca-google-psi__url">
                URL: <code><?php echo esc_html($siteUrl); ?></code>
            </span>
            <a href="<?php echo esc_url($extReportUrl); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary button-small laca-google-psi__ext-link" id="laca-psi-ext-link">
                <span><?php esc_html_e('Chi tiết', 'lacadev'); ?></span>
                <span class="dashicons dashicons-external" aria-hidden="true"></span>
            </a>
        </div>
    </div>

    <!-- Dual Strategy Panels (Mobile & Desktop) -->
    <?php
    // Panel 1: Mobile
    $strategy = 'mobile';
    $psiData = $psiMobile;
    $isActive = (!$isDesktop);
    include __DIR__ . '/pagespeed-panel.php';

    // Panel 2: Desktop
    $strategy = 'desktop';
    $psiData = $psiDesktop;
    $isActive = $isDesktop;
    include __DIR__ . '/pagespeed-panel.php';
    ?>
</div>
