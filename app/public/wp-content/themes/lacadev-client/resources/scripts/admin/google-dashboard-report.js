/**
 * LacaDev WP Dashboard Google Services Report (GSC + GA4) — V2
 * Handles AJAX lazy loading of the full-width Google Report, range switching,
 * refresh bypassing cache, collapse/expand toggle, instant PSI Mobile/Desktop tabs & run,
 * authentic skeleton placeholder, and 100 queries dialog with client-side sort & filter.
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

        // 2. AJAX Load Report (U2 Authentic Skeleton, U3 Updating State & AbortController)
        let currentRange = wrap.dataset.range || '28d';
        let pendingRange = null;
        let currentPsiStrategy = 'mobile';
        let activeReportController = null;
        let isReportUpdating = false;

        function loadReport(range, isRefresh) {
            if (!container || !ajaxUrl || !nonce) return;

            const targetRange = range || currentRange || '28d';

            // U3: Prevent repeated clicks during in-flight request
            if (isReportUpdating) {
                // If user clicked the same range currently in flight or clicked refresh again, ignore
                if (isRefresh || targetRange === pendingRange) {
                    return;
                }
                // If user clicked a different range: abort previous request and continue with new range
                if (activeReportController) {
                    activeReportController.abort();
                }
            }

            isReportUpdating = true;
            pendingRange = targetRange;
            activeReportController = new AbortController();
            const controller = activeReportController;

            const reportBody = container.querySelector('.laca-google-report-body');

            if (!isRefresh && !reportBody) {
                // U2: Initial load -> use authentic skeleton from shared <template>
                const skeletonTpl = document.getElementById('laca-google-report-skeleton-tpl');
                if (skeletonTpl && skeletonTpl.innerHTML) {
                    container.innerHTML = skeletonTpl.innerHTML;
                } else {
                    container.innerHTML = '<div class="laca-google-report-skeleton" aria-busy="true"><span class="screen-reader-text">Đang tải báo cáo Google…</span></div>';
                }
            } else {
                // U3: Refresh or Date range change -> Dim existing content and show indeterminate progress bar
                if (reportBody) {
                    reportBody.classList.add('laca-google-report--dimmed');
                }

                let progressBar = container.querySelector('.laca-google-progress-bar');
                if (!progressBar) {
                    progressBar = document.createElement('div');
                    progressBar.className = 'laca-google-progress-bar';
                    progressBar.setAttribute('role', 'progressbar');
                    progressBar.setAttribute('aria-hidden', 'true');
                    progressBar.innerHTML = '<div class="laca-google-progress-bar__inner"></div>';
                    container.prepend(progressBar);
                }

                // Refresh button state: disabled, aria-busy="true", spinner, text "Đang cập nhật…"
                const refreshBtn = container.querySelector('.laca-google-refresh-btn');
                if (refreshBtn) {
                    refreshBtn.disabled = true;
                    refreshBtn.setAttribute('aria-busy', 'true');
                    refreshBtn.innerHTML = '<span class="dashicons dashicons-update laca-spin" aria-hidden="true"></span> ' + escapeHtml('Đang cập nhật…');
                }

                // Range buttons: immediately mark target range as active
                const rangeBtns = container.querySelectorAll('.laca-google-range-btn');
                rangeBtns.forEach(function (b) {
                    const isTarget = (b.dataset.range === targetRange);
                    b.classList.toggle('button-primary', isTarget);
                    b.classList.toggle('is-active', isTarget);
                    b.classList.toggle('button-secondary', !isTarget);
                });
            }

            const formData = new FormData();
            formData.append('action', 'laca_google_report');
            formData.append('nonce', nonce);
            formData.append('range', targetRange);
            if (isRefresh) {
                formData.append('refresh', '1');
            }
            if (isMock) {
                formData.append('laca_mock_google', '1');
            }

            // U3: Minimum 300ms loading duration to prevent flickering
            const minDelay = new Promise(function (resolve) {
                setTimeout(resolve, 300);
            });

            const fetchPromise = fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: controller.signal,
            }).then(function (response) {
                return response.json();
            });

            Promise.all([fetchPromise, minDelay])
                .then(function (results) {
                    const data = results[0];
                    isReportUpdating = false;
                    pendingRange = null;

                    if (data && data.success && data.data && data.data.html) {
                        container.innerHTML = data.data.html;
                        currentRange = data.data.range || targetRange || '28d';

                        // U3: Update "Cập nhật lúc HH:MM" in toolbar
                        const now = new Date();
                        const hh = String(now.getHours()).padStart(2, '0');
                        const mm = String(now.getMinutes()).padStart(2, '0');
                        const updateStr = 'Cập nhật lúc ' + hh + ':' + mm;

                        const actions = container.querySelector('.laca-google-toolbar__actions');
                        if (actions) {
                            let updatedEl = actions.querySelector('.laca-google-toolbar__updated');
                            if (!updatedEl) {
                                updatedEl = document.createElement('span');
                                updatedEl.className = 'laca-google-toolbar__updated';
                                actions.appendChild(updatedEl);
                            }
                            updatedEl.textContent = updateStr;
                        }

                        bindReportEvents();
                    } else {
                        const errMsg = (data && data.data && data.data.message) ? data.data.message : 'Không thể tải dữ liệu báo cáo Google.';
                        renderError(errMsg);
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        // Aborted due to new range selection, let the newer request complete
                        return;
                    }

                    isReportUpdating = false;

                    // Cleanup progress bar & dimmed state
                    const curReportBody = container.querySelector('.laca-google-report-body');
                    if (curReportBody) {
                        curReportBody.classList.remove('laca-google-report--dimmed');
                    }
                    const curProgressBar = container.querySelector('.laca-google-progress-bar');
                    if (curProgressBar) {
                        curProgressBar.remove();
                    }

                    // Restore refresh button
                    const curRefreshBtn = container.querySelector('.laca-google-refresh-btn');
                    if (curRefreshBtn) {
                        curRefreshBtn.disabled = false;
                        curRefreshBtn.removeAttribute('aria-busy');
                        curRefreshBtn.innerHTML = '<span class="dashicons dashicons-update"></span> ' + escapeHtml('Làm mới');
                    }

                    // Re-enable range buttons
                    const curRangeBtns = container.querySelectorAll('.laca-google-range-btn');
                    curRangeBtns.forEach(function (b) {
                        b.disabled = false;
                    });

                    renderError('Lỗi kết nối khi tải báo cáo Google.');
                });
        }

        function renderError(message) {
            if (!container) return;
            container.innerHTML = `
                <div class="laca-seo-card laca-google-report-error">
                    <div class="laca-alert laca-alert--error">
                        <span class="dashicons dashicons-no-alt laca-alert__icon" aria-hidden="true"></span>
                        <div class="laca-alert__content">
                            <h3 class="laca-alert__title">Không thể tải báo cáo</h3>
                            <p class="laca-alert__description">${escapeHtml(message)}</p>
                            <button type="button" class="button button-secondary laca-google-retry-btn">
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

        // 4. PageSpeed Insights Handler (U1 Instant Tab Switch & Single Panel Run)
        let activePsiController = null;

        function runPageSpeed(btn, strategy, forceRefresh) {
            if (!container || !ajaxUrl || !nonce) return;

            const psiCard = container.querySelector('#laca-google-psi-card, .laca-google-psi');
            if (!psiCard) return;

            const activeStrategy = strategy || currentPsiStrategy || 'mobile';
            currentPsiStrategy = activeStrategy;

            const targetPanel = psiCard.querySelector('.laca-google-psi__panel[data-strategy="' + activeStrategy + '"]');
            if (targetPanel) {
                targetPanel.querySelectorAll('[data-laca-psi-run]').forEach(function (b) {
                    b.disabled = true;
                });
            }
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="dashicons dashicons-update laca-spin" aria-hidden="true"></span> ' + escapeHtml('Đang kiểm tra…');
            }

            if (activePsiController) {
                activePsiController.abort();
            }
            activePsiController = new AbortController();
            const currentController = activePsiController;

            const timeoutId = setTimeout(function () {
                currentController.abort();
            }, 70000);

            const formData = new FormData();
            formData.append('action', 'laca_pagespeed_run');
            formData.append('nonce', nonce);
            formData.append('strategy', activeStrategy);
            formData.append('refresh', forceRefresh ? '1' : '0');
            if (isMock) {
                formData.append('laca_mock_google', '1');
            }

            // Check if mock error simulation is requested via URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('laca_mock_psi_error') === '1') {
                formData.append('laca_mock_psi_error', '1');
            }

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: currentController.signal,
            })
                .then(function (response) {
                    clearTimeout(timeoutId);
                    return response.json();
                })
                .then(function (res) {
                    if (res && res.success && res.data && res.data.html) {
                        const curTargetPanel = psiCard.querySelector('.laca-google-psi__panel[data-strategy="' + activeStrategy + '"]');
                        if (curTargetPanel) {
                            curTargetPanel.outerHTML = res.data.html;
                        } else {
                            psiCard.outerHTML = res.data.html;
                        }
                        bindPsiEvents();
                    } else {
                        const resData = (res && res.data) ? res.data : {};
                        const errMsg = (resData && resData.message) ? resData.message : 'Không thể hoàn thành kiểm tra PageSpeed.';
                        const is429 = Boolean(resData && (resData.status === 429 || resData.code === 'psi_error_429' || String(errMsg).includes('429') || String(errMsg).includes('hạn mức')));
                        const actionUrl = (resData && resData.actionUrl) ? resData.actionUrl : (is429 ? 'admin.php?page=laca-google-search-console#laca-psi-key' : '');
                        const actionLabel = (resData && resData.actionLabel) ? resData.actionLabel : (is429 ? 'Thêm PageSpeed API key' : '');
                        renderPsiError(errMsg, is429, actionUrl, actionLabel, activeStrategy);
                    }
                })
                .catch(function (err) {
                    clearTimeout(timeoutId);
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    renderPsiError('Lỗi kết nối khi gửi yêu cầu kiểm tra PageSpeed.', false, '', '', activeStrategy);
                });

            function renderPsiError(message, is429, actionUrl, actionLabel, targetStrat) {
                const currentCard = container.querySelector('#laca-google-psi-card, .laca-google-psi');
                if (!currentCard) return;

                // Remove existing panels so only 1 single retry button is present in card
                currentCard.querySelectorAll('.laca-google-psi__panel').forEach(function (p) {
                    p.remove();
                });

                let actionBtnHtml = `
                    <button type="button" class="button button-secondary button-small" data-laca-psi-run>
                        <span class="dashicons dashicons-update" aria-hidden="true"></span> Thử lại
                    </button>
                `;

                if (actionUrl) {
                    actionBtnHtml += `
                        <a href="${escapeHtml(actionUrl)}" class="button button-secondary button-small">
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> ${escapeHtml(actionLabel || 'Cấu hình API key')}
                        </a>
                    `;
                }

                const alertWrap = document.createElement('div');
                alertWrap.className = 'laca-google-psi__panel';
                alertWrap.dataset.strategy = targetStrat;
                alertWrap.innerHTML = `
                    <div class="laca-google-psi__alert laca-alert laca-alert--error" role="alert">
                        <div class="laca-google-psi__alert-body">
                            <span class="dashicons dashicons-no-alt laca-google-psi__alert-icon" aria-hidden="true"></span>
                            <div class="laca-google-psi__alert-text">
                                <h4 class="laca-google-psi__alert-title">Lỗi kiểm tra tốc độ</h4>
                                <p class="laca-google-psi__alert-message">${escapeHtml(message)}</p>
                                <div class="laca-google-psi__alert-actions">
                                    ${actionBtnHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                currentCard.appendChild(alertWrap);

                bindPsiEvents();
            }
        }

        function bindPsiEvents() {
            if (!container) return;
            const psiCard = container.querySelector('#laca-google-psi-card, .laca-google-psi');
            if (!psiCard) return;

            const tabBtns = Array.from(psiCard.querySelectorAll('.laca-google-psi__tab, .laca-google-psi-tab'));

            function switchPsiTab(targetStrategy) {
                currentPsiStrategy = targetStrategy;
                psiCard.dataset.activeStrategy = targetStrategy;

                // 1. Update tab buttons (U1: instant toggle, < 100ms, zero network)
                tabBtns.forEach(function (t) {
                    const isActive = (t.dataset.strategy === targetStrategy);
                    t.classList.toggle('button-primary', isActive);
                    t.classList.toggle('is-active', isActive);
                    t.classList.toggle('button-secondary', !isActive);
                    t.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    t.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    t.setAttribute('tabindex', isActive ? '0' : '-1');
                });

                // 2. Toggle panels visibility (zero AJAX)
                const panels = psiCard.querySelectorAll('.laca-google-psi__panel');
                panels.forEach(function (p) {
                    p.hidden = (p.dataset.strategy !== targetStrategy);
                });

                // 3. Update external link URL
                const extLink = psiCard.querySelector('#laca-psi-ext-link, .laca-google-psi__ext-link');
                if (extLink && extLink.href) {
                    try {
                        const u = new URL(extLink.href);
                        if (targetStrategy === 'desktop') {
                            u.searchParams.set('form_factor', 'desktop');
                        } else {
                            u.searchParams.delete('form_factor');
                        }
                        extLink.href = u.toString();
                    } catch (e) {
                        // Ignore URL parse error
                    }
                }

                // 4. Remember user preference
                try {
                    localStorage.setItem('laca_google_psi_strategy', targetStrategy);
                } catch (e) {
                    // Ignore storage quota error
                }

                // 5. Update strategy label for screen readers & audit
                const stratLabel = psiCard.querySelector('.laca-google-psi__strategy-label');
                if (stratLabel) {
                    stratLabel.textContent = (targetStrategy === 'desktop') ? 'Máy tính' : 'Di động';
                }
            }

            // Strategy Tab click and keyboard navigation (Left / Right arrows)
            tabBtns.forEach(function (tabBtn, idx) {
                tabBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetStrategy = tabBtn.dataset.strategy || 'mobile';
                    switchPsiTab(targetStrategy);
                });

                tabBtn.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                        e.preventDefault();
                        const nextIdx = (idx === 0) ? 1 : 0;
                        const nextTab = tabBtns[nextIdx];
                        if (nextTab) {
                            nextTab.focus();
                            switchPsiTab(nextTab.dataset.strategy || 'mobile');
                        }
                    }
                });
            });

            // Restore user choice from localStorage if present
            try {
                const savedStrategy = localStorage.getItem('laca_google_psi_strategy');
                if (savedStrategy && (savedStrategy === 'mobile' || savedStrategy === 'desktop')) {
                    switchPsiTab(savedStrategy);
                }
            } catch (e) {
                // Ignore
            }

            // PageSpeed run/retry buttons
            psiCard.querySelectorAll('[data-laca-psi-run]').forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    runPageSpeed(b, currentPsiStrategy, true);
                });
            });
        }

        // 5. 100 Queries Dialog Handler
        function initQueriesDialog() {
            const openBtn = container.querySelector('#laca-open-gsc-queries-btn, [data-laca-gsc-all]');
            const dialog = container.querySelector('#laca-gsc-dialog, #laca-gsc-queries-dialog');
            if (!openBtn || !dialog) return;

            const closeBtn = dialog.querySelector('#laca-gsc-queries-close');
            const searchInput = dialog.querySelector('#laca-gsc-queries-search');
            const countEl = dialog.querySelector('#laca-gsc-queries-count');
            const loadingEl = dialog.querySelector('#laca-gsc-queries-loading');
            const contentEl = dialog.querySelector('#laca-gsc-queries-content');

            let queriesData = [];
            let currentSortCol = 'clicks';
            let sortAsc = false;

            function renderQueriesTable(rows) {
                if (!contentEl) return;
                if (!rows || rows.length === 0) {
                    contentEl.innerHTML = '<div class="laca-table-empty">Không tìm thấy từ khoá nào phù hợp.</div>';
                    if (countEl) countEl.textContent = '0 từ khoá';
                    return;
                }

                if (countEl) {
                    countEl.textContent = rows.length + ' từ khoá';
                }

                let html = `
                    <div class="laca-google-dialog__table-wrap">
                        <table class="wp-list-table widefat striped laca-google-table laca-google-dialog__table">
                            <thead>
                                <tr>
                                    <th class="laca-col-sortable" data-col="query">
                                        Từ khoá ${currentSortCol === 'query' ? (sortAsc ? '▲' : '▼') : ''}
                                    </th>
                                    <th class="laca-col-right laca-col-sortable laca-col-w75" data-col="clicks">
                                        Clicks ${currentSortCol === 'clicks' ? (sortAsc ? '▲' : '▼') : ''}
                                    </th>
                                    <th class="laca-col-right laca-col-sortable laca-col-w85" data-col="impressions">
                                        Hiển thị ${currentSortCol === 'impressions' ? (sortAsc ? '▲' : '▼') : ''}
                                    </th>
                                    <th class="laca-col-right laca-col-sortable laca-col-w65" data-col="ctr">
                                        CTR ${currentSortCol === 'ctr' ? (sortAsc ? '▲' : '▼') : ''}
                                    </th>
                                    <th class="laca-col-right laca-col-sortable laca-col-w65" data-col="position">
                                        Vị trí ${currentSortCol === 'position' ? (sortAsc ? '▲' : '▼') : ''}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                rows.forEach(function (r) {
                    const qName = escapeHtml(r.query || '');
                    const qClicks = new Intl.NumberFormat().format(r.clicks || 0);
                    const qImpr = new Intl.NumberFormat().format(r.impressions || 0);
                    const qCtr = (parseFloat(r.ctr || 0)).toFixed(1) + '%';
                    const qPos = (parseFloat(r.position || 0)).toFixed(1);

                    html += `
                        <tr>
                            <td><strong>${qName}</strong></td>
                            <td class="laca-col-right laca-cell-bold">${qClicks}</td>
                            <td class="laca-col-right laca-cell-muted">${qImpr}</td>
                            <td class="laca-col-right">${qCtr}</td>
                            <td class="laca-col-right">${qPos}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                contentEl.innerHTML = html;

                // Bind sort handlers
                contentEl.querySelectorAll('.laca-col-sortable').forEach(function (th) {
                    th.addEventListener('click', function () {
                        const col = th.dataset.col;
                        if (currentSortCol === col) {
                            sortAsc = !sortAsc;
                        } else {
                            currentSortCol = col;
                            sortAsc = false;
                        }
                        applySortAndFilter();
                    });
                });
            }

            function applySortAndFilter() {
                const keyword = (searchInput ? searchInput.value : '').trim().toLowerCase();
                let filtered = queriesData.filter(function (row) {
                    if (!keyword) return true;
                    return String(row.query || '').toLowerCase().includes(keyword);
                });

                filtered.sort(function (a, b) {
                    let valA = a[currentSortCol];
                    let valB = b[currentSortCol];

                    if (typeof valA === 'string') {
                        valA = valA.toLowerCase();
                        valB = (valB || '').toLowerCase();
                        return sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }

                    valA = parseFloat(valA) || 0;
                    valB = parseFloat(valB) || 0;
                    return sortAsc ? valA - valB : valB - valA;
                });

                renderQueriesTable(filtered);
            }

            function fetchQueries() {
                if (queriesData.length > 0) {
                    applySortAndFilter();
                    return;
                }

                if (loadingEl) loadingEl.classList.remove('is-hidden');
                if (contentEl) contentEl.innerHTML = '';

                const formData = new FormData();
                formData.append('action', 'laca_gsc_queries');
                formData.append('nonce', nonce);
                formData.append('range', currentRange);
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
                    .then(function (res) {
                        if (loadingEl) loadingEl.classList.add('is-hidden');
                        if (res && res.success && res.data && Array.isArray(res.data.rows)) {
                            queriesData = res.data.rows;
                            applySortAndFilter();
                        } else {
                            const msg = (res && res.data && res.data.message) ? res.data.message : 'Không thể tải danh sách từ khoá.';
                            if (contentEl) {
                                contentEl.innerHTML = `<div class="laca-alert laca-alert--error"><div class="laca-alert__content"><p class="laca-alert__description">${escapeHtml(msg)}</p></div></div>`;
                            }
                        }
                    })
                    .catch(function () {
                        if (loadingEl) loadingEl.classList.add('is-hidden');
                        if (contentEl) {
                            contentEl.innerHTML = '<div class="laca-alert laca-alert--error"><div class="laca-alert__content"><p class="laca-alert__description">Lỗi kết nối khi tải danh sách từ khoá.</p></div></div>';
                        }
                    });
            }

            openBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openBtn.setAttribute('aria-expanded', 'true');
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', '');
                }
                fetchQueries();
            });

            function closeDialog() {
                if (typeof dialog.close === 'function') {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
                openBtn.setAttribute('aria-expanded', 'false');
                openBtn.focus();
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeDialog();
                });
            }

            dialog.addEventListener('cancel', function () {
                openBtn.setAttribute('aria-expanded', 'false');
                openBtn.focus();
            });

            dialog.addEventListener('close', function () {
                openBtn.setAttribute('aria-expanded', 'false');
                openBtn.focus();
            });

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    applySortAndFilter();
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
                    if (newRange !== currentRange || isReportUpdating) {
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
                    if (isReportUpdating) return;
                    stopRealtimePolling();
                    loadReport(currentRange, true);
                });
            }

            // PageSpeed events & tabs
            bindPsiEvents();

            // 100 Queries dialog
            initQueriesDialog();

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
