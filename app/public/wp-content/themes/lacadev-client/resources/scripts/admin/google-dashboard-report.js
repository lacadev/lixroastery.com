/**
 * LacaDev WP Dashboard Google Services Report (GSC + GA4)
 * Handles AJAX lazy loading of the full-width Google Report, range switching,
 * refresh bypassing cache, and collapse/expand toggle saved to localStorage.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initGoogleDashboardReport();
    });

    function initGoogleDashboardReport() {
        const wrap = document.getElementById('laca-google-report');
        if (!wrap) return;

        const container = document.getElementById('laca-google-report-container');
        const toggleBtn = wrap.querySelector('.laca-google-report-toggle');
        const ajaxUrl = wrap.dataset.ajaxUrl || (window.lacaGoogleReportData && window.lacaGoogleReportData.ajaxUrl);
        const nonce = wrap.dataset.nonce || (window.lacaGoogleReportData && window.lacaGoogleReportData.nonce);
        const isMock = wrap.dataset.mock === '1';

        // 1. Collapse / Expand persistence via localStorage
        const STORAGE_KEY = 'laca_google_report_collapsed';
        const isCollapsed = localStorage.getItem(STORAGE_KEY) === '1';

        if (isCollapsed && container && toggleBtn) {
            container.classList.add('is-collapsed');
            toggleBtn.setAttribute('aria-expanded', 'false');
            const icon = toggleBtn.querySelector('.dashicons');
            if (icon) {
                icon.classList.remove('dashicons-arrow-up-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
            }
        }

        if (toggleBtn && container) {
            toggleBtn.addEventListener('click', function () {
                const nowCollapsed = container.classList.toggle('is-collapsed');
                toggleBtn.setAttribute('aria-expanded', nowCollapsed ? 'false' : 'true');
                localStorage.setItem(STORAGE_KEY, nowCollapsed ? '1' : '0');

                const icon = toggleBtn.querySelector('.dashicons');
                if (icon) {
                    icon.classList.toggle('dashicons-arrow-up-alt2', !nowCollapsed);
                    icon.classList.toggle('dashicons-arrow-down-alt2', nowCollapsed);
                }
            });
        }

        // 2. AJAX Load Report
        let currentRange = '28d';

        function loadReport(range, isRefresh) {
            if (!container || !ajaxUrl || !nonce) return;

            // Show skeleton if initial load or switching range
            if (!isRefresh) {
                container.innerHTML = `
                    <div class="laca-google-report-skeleton" aria-busy="true">
                        <div class="laca-skeleton-bar"></div>
                        <div class="laca-skeleton-grid">
                            <div class="laca-skeleton-card"></div>
                            <div class="laca-skeleton-card"></div>
                        </div>
                    </div>
                `;
            } else {
                container.style.opacity = '0.5';
            }

            const formData = new FormData();
            formData.append('action', 'laca_google_report');
            formData.append('nonce', nonce);
            formData.append('range', range || '28d');
            if (isRefresh) {
                formData.append('refresh', '1');
            }
            if (isMock) {
                formData.append('laca_mock_google', '1');
            }

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    container.style.opacity = '1';
                    if (data && data.success && data.data && data.data.html) {
                        container.innerHTML = data.data.html;
                        currentRange = data.data.range || range || '28d';
                        bindReportEvents();
                    } else {
                        const errMsg = (data && data.data && data.data.message) ? data.data.message : 'Không thể tải dữ liệu báo cáo Google.';
                        renderError(errMsg);
                    }
                })
                .catch(function () {
                    container.style.opacity = '1';
                    renderError('Lỗi kết nối khi tải báo cáo Google.');
                });
        }

        function renderError(message) {
            if (!container) return;
            container.innerHTML = `
                <div class="laca-seo-card" style="padding: 24px; text-align: center;">
                    <div class="laca-alert laca-alert--error" style="display: inline-block; text-align: left; max-width: 520px;">
                        <span class="dashicons dashicons-no-alt laca-alert__icon" aria-hidden="true"></span>
                        <div class="laca-alert__content">
                            <h3 class="laca-alert__title">Không thể tải báo cáo</h3>
                            <p class="laca-alert__description">${escapeHtml(message)}</p>
                            <button type="button" class="button button-secondary laca-google-retry-btn" style="margin-top: 8px;">
                                Thử lại
                            </button>
                        </div>
                    </div>
                </div>
            `;
            const retryBtn = container.querySelector('.laca-google-retry-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', function () {
                    loadReport(currentRange, false);
                });
            }
        }

        function bindReportEvents() {
            if (!container) return;

            // Range buttons
            container.querySelectorAll('.laca-google-range-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const newRange = btn.dataset.range || '28d';
                    if (newRange !== currentRange) {
                        loadReport(newRange, false);
                    }
                });
            });

            // Refresh button
            const refreshBtn = container.querySelector('.laca-google-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    loadReport(currentRange, true);
                });
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Start loading initial report
        loadReport('28d', false);
    }
})();
