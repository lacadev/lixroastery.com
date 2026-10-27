<?php
/**
 * Shared Google Services Report Skeleton Partial.
 * Renders an authentic loading placeholder matching the real report layout.
 *
 * @package LacaDevClient
 */

defined('ABSPATH') || exit;
?>
<div class="laca-google-report-skeleton" aria-busy="true" aria-live="polite">
    <span class="screen-reader-text"><?php esc_html_e('Đang tải báo cáo Google…', 'lacadev'); ?></span>

    <!-- 1. Toolbar Skeleton -->
    <div class="laca-seo-card laca-google-skeleton__card laca-google-skeleton__card--toolbar">
        <div class="laca-google-toolbar__inner">
            <div class="laca-google-toolbar__title-wrap">
                <div class="laca-google-skeleton__box laca-google-skeleton__icon"></div>
                <div>
                    <div class="laca-google-skeleton__box laca-google-skeleton__title"></div>
                    <div class="laca-google-skeleton__box laca-google-skeleton__subtitle"></div>
                </div>
            </div>
            <div class="laca-google-toolbar__actions">
                <div class="button-group laca-google-skeleton__pills">
                    <div class="laca-google-skeleton__box laca-google-skeleton__pill"></div>
                    <div class="laca-google-skeleton__box laca-google-skeleton__pill"></div>
                    <div class="laca-google-skeleton__box laca-google-skeleton__pill"></div>
                    <div class="laca-google-skeleton__box laca-google-skeleton__pill"></div>
                </div>
                <div class="laca-google-skeleton__box laca-google-skeleton__btn"></div>
            </div>
        </div>
    </div>

    <!-- 2. Search Funnel Skeleton -->
    <div class="laca-seo-card laca-google-skeleton__card laca-google-skeleton__card--funnel">
        <div class="laca-google-skeleton__box laca-google-skeleton__section-title"></div>
        <div class="laca-google-funnel__grid">
            <div class="laca-google-skeleton__box laca-google-skeleton__funnel-step"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__funnel-step"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__funnel-step"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__funnel-step"></div>
        </div>
    </div>

    <!-- 3. Key Metrics Skeleton -->
    <div class="laca-seo-card laca-google-skeleton__card laca-google-skeleton__card--key-metrics">
        <div class="laca-google-skeleton__box laca-google-skeleton__section-title"></div>
        <div class="laca-google-key-metrics__grid">
            <div class="laca-google-skeleton__box laca-google-skeleton__metric-card"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__metric-card"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__metric-card"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__metric-card"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__metric-card"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__metric-card"></div>
        </div>
    </div>

    <!-- 4. Dual Grid (GSC + GA4) Skeleton -->
    <div class="laca-google-grid">
        <!-- GSC Column -->
        <div class="laca-seo-card laca-google-card laca-google-skeleton__card">
            <div class="laca-google-skeleton__box laca-google-skeleton__card-header"></div>
            <div class="laca-google-kpis">
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
            </div>
            <div class="laca-google-skeleton__box laca-google-skeleton__chart"></div>
            <div class="laca-google-skeleton__table">
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
            </div>
        </div>

        <!-- GA4 Column -->
        <div class="laca-seo-card laca-google-card laca-google-skeleton__card">
            <div class="laca-google-skeleton__box laca-google-skeleton__card-header"></div>
            <div class="laca-google-kpis">
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__kpi"></div>
            </div>
            <div class="laca-google-skeleton__box laca-google-skeleton__chart"></div>
            <div class="laca-google-skeleton__table">
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
                <div class="laca-google-skeleton__box laca-google-skeleton__table-row"></div>
            </div>
        </div>
    </div>

    <!-- 5. PageSpeed Insights Skeleton -->
    <div class="laca-seo-card laca-google-skeleton__card laca-google-skeleton__card--psi">
        <div class="laca-google-psi__header">
            <div class="laca-google-skeleton__box laca-google-skeleton__psi-title"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__psi-actions"></div>
        </div>
        <div class="laca-google-psi__grid">
            <div class="laca-google-skeleton__box laca-google-skeleton__psi-metric"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__psi-metric"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__psi-metric"></div>
            <div class="laca-google-skeleton__box laca-google-skeleton__psi-metric"></div>
        </div>
    </div>
</div>
