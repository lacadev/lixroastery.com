/**
 * LacaDev Dashboard Content Tracker
 * Handles tab switching (select → div.laca-tab-content) and
 * client-side pagination for .laca-paged-list lists.
 * Enqueued by DashboardWidgets::enqueueDashboardScripts()
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initTrackers();
    });

    function initTrackers() {
        // A single dashboard page can have multiple tracker widget instances
        document.querySelectorAll('.laca-report-select').forEach(function (select) {
            const widget = select.closest('.inside') || select.parentElement;
            if (!widget) return;

            // Tab switching
            select.addEventListener('change', function () {
                widget.querySelectorAll('.laca-tab-content').forEach(function (tab) {
                    tab.classList.toggle('active', tab.id === select.value);
                });
            });

            // Pagination for each paged list inside this widget
            widget.querySelectorAll('.laca-paged-list').forEach(function (list) {
                const tab      = list.closest('.laca-tab-content');
                const perPage  = parseInt(tab ? tab.dataset.perPage : 5, 10) || 5;
                initPagination(list, perPage);
            });
        });
    }

    function initPagination(list, perPage) {
        const rows    = Array.from(list.querySelectorAll('li.laca-list-item'));
        const pageRow = list.querySelector('li.laca-pagination-row');
        const prevBtn = pageRow ? pageRow.querySelector('.laca-page-btn.prev') : null;
        const nextBtn = pageRow ? pageRow.querySelector('.laca-page-btn.next') : null;
        const info    = pageRow ? pageRow.querySelector('.laca-page-info')    : null;

        if (!rows.length || !pageRow) return;

        const total = Math.ceil(rows.length / perPage);
        let   page  = 1;

        function render() {
            rows.forEach(function (row, i) {
                const inPage = i >= (page - 1) * perPage && i < page * perPage;
                row.style.display = inPage ? '' : 'none';
            });

            if (prevBtn) prevBtn.disabled = page <= 1;
            if (nextBtn) nextBtn.disabled = page >= total;
            if (info)    info.textContent = 'Trang ' + page + ' / ' + total;
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (page > 1) { page--; render(); }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (page < total) { page++; render(); }
            });
        }

        render();
    }
}());
