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
        let currentRange = wrap.dataset.range || '28d';

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

        // 3. Realtime GA4 Auto-update (every 60s while tab is visible)
        let realtimeTimer = null;

        function updateRealtime() {
            if (!container || !ajaxUrl || !nonce) return;
            const countEl = container.querySelector('#laca-ga4-realtime-count, .laca-ga4-realtime-count');
            if (!countEl) return;

            const formData = new FormData();
            formData.append('action', 'laca_ga4_realtime');
            formData.append('nonce', nonce);
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
                    if (data && data.success && data.data && typeof data.data.activeUsers !== 'undefined') {
                        countEl.textContent = new Intl.NumberFormat().format(data.data.activeUsers);
                    }
                })
                .catch(function () {
                    // Fail silently for background polling
                });
        }

        function startRealtimePolling() {
            stopRealtimePolling();
            if (document.hidden) return;
            const countEl = container && container.querySelector('#laca-ga4-realtime-count, .laca-ga4-realtime-count');
            if (!countEl) return;

            realtimeTimer = setInterval(function () {
                if (!document.hidden) {
                    updateRealtime();
                }
            }, 60000);
        }

        function stopRealtimePolling() {
            if (realtimeTimer) {
                clearInterval(realtimeTimer);
                realtimeTimer = null;
            }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopRealtimePolling();
            } else {
                const countEl = container && container.querySelector('#laca-ga4-realtime-count, .laca-ga4-realtime-count');
                if (countEl) {
                    updateRealtime();
                    startRealtimePolling();
                }
            }
        });

        // 4. PageSpeed Insights AJAX Handler
        function runPageSpeed(btn) {
            if (!container || !ajaxUrl || !nonce) return;

            const psiCard = container.querySelector('#laca-google-psi-card, .laca-google-psi');
            const allPsiBtns = container.querySelectorAll('[data-laca-psi-run]');

            const originalText = btn.innerHTML;
            allPsiBtns.forEach(function (b) {
                b.disabled = true;
            });
            btn.innerHTML = '<span class="dashicons dashicons-update laca-spin" aria-hidden="true"></span> Đang kiểm tra… (có thể mất ~30 giây)';

            const controller = new AbortController();
            const timeoutId = setTimeout(function () {
                controller.abort();
            }, 70000);

            const formData = new FormData();
            formData.append('action', 'laca_pagespeed_run');
            formData.append('nonce', nonce);
            formData.append('strategy', 'mobile');
            formData.append('refresh', '1');
            if (isMock) {
                formData.append('laca_mock_google', '1');
            }

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(function (response) {
                    clearTimeout(timeoutId);
                    return response.json();
                })
                .then(function (res) {
                    if (res && res.success && res.data && res.data.html) {
                        if (psiCard) {
                            psiCard.outerHTML = res.data.html;
                        } else {
                            container.insertAdjacentHTML('beforeend', res.data.html);
                        }
                        bindPsiEvents();
                    } else {
                        const errMsg = (res && res.data && res.data.message) ? res.data.message : 'Không thể hoàn thành kiểm tra PageSpeed.';
                        const is429 = (res && res.data && (res.data.status === 429 || res.data.code === 'psi_error_429' || String(errMsg).includes('429')));
                        renderPsiError(errMsg, is429);
                    }
                })
                .catch(function (err) {
                    clearTimeout(timeoutId);
                    const isAbort = err && err.name === 'AbortError';
                    const msg = isAbort ? 'Quá thời gian phản hồi (70 giây). Vui lòng thử lại sau.' : 'Lỗi kết nối khi gửi yêu cầu kiểm tra PageSpeed.';
                    renderPsiError(msg, false);
                });

            function renderPsiError(message, is429) {
                if (!psiCard) return;
                let hint = '';
                if (is429) {
                    hint = '<div style="margin-top: 6px; font-size: 12px; color: var(--laca-fg-muted);">Hạn mức truy vấn API miễn phí đã đạt giới hạn. Bạn có thể cấu hình PageSpeed API Key trong trang Cài đặt Dịch vụ Google để tăng hạn mức.</div>';
                }
                const existingAlert = psiCard.querySelector('.laca-google-psi__alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                const alertHtml = `
                    <div class="laca-google-psi__alert">
                        <strong>Lỗi kiểm tra tốc độ:</strong>
                        <span>${escapeHtml(message)}</span>
                        ${hint}
                        <div>
                            <button type="button" class="button button-secondary button-small" data-laca-psi-run style="margin-top: 8px;">
                                <span class="dashicons dashicons-update" aria-hidden="true"></span> Thử lại
                            </button>
                        </div>
                    </div>
                `;
                const header = psiCard.querySelector('.laca-google-psi__header');
                if (header) {
                    header.insertAdjacentHTML('afterend', alertHtml);
                } else {
                    psiCard.insertAdjacentHTML('afterbegin', alertHtml);
                }
                allPsiBtns.forEach(function (b) {
                    b.disabled = false;
                });
                btn.innerHTML = originalText;
                bindPsiEvents();
            }
        }

        function bindPsiEvents() {
            if (!container) return;
            container.querySelectorAll('[data-laca-psi-run]').forEach(function (b) {
                b.replaceWith(b.cloneNode(true));
            });
            container.querySelectorAll('[data-laca-psi-run]').forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    runPageSpeed(b);
                });
            });
        }

        function bindReportEvents() {
            if (!container) return;

            // Range buttons
            container.querySelectorAll('.laca-google-range-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const newRange = btn.dataset.range || '28d';
                    if (newRange !== currentRange) {
                        stopRealtimePolling();
                        loadReport(newRange, false);
                    }
                });
            });

            // Refresh button
            const refreshBtn = container.querySelector('.laca-google-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    stopRealtimePolling();
                    loadReport(currentRange, true);
                });
            }

            // PageSpeed run buttons
            bindPsiEvents();

            // Start Realtime Polling if Realtime block is present
            startRealtimePolling();
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Start loading initial report
        loadReport(currentRange, false);
    }
})();

