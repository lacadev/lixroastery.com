/**
 * Laca Dashboard interactions (Audit refresh, Storage recalculate, Smooth scroll)
 */
(function ($) {
    'use strict';

    function updateTodoBadge() {
        var count = $('#laca-dashboard-todo-container .laca-todo-item').length;
        var $badge = $('#laca-todo-badge');
        $badge.text(count);
        if (count > 0) {
            $badge.removeClass('laca-badge--neutral laca-badge--success').addClass('laca-badge--warning').show();
        } else {
            $badge.removeClass('laca-badge--warning').addClass('laca-badge--success').text('0').show();
        }
    }

    $(document).ready(function () {
        updateTodoBadge();

        // 1. Smooth scroll to todo list
        $(document).on('click', 'a[href="#laca-dashboard-todo"]', function (e) {
            e.preventDefault();
            var $target = $('#laca-dashboard-todo');
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 40
                }, 400);
            }
        });

        // 2. Refresh Audit AJAX
        $(document).on('click', '#btn-refresh-audit, .js-laca-run-audit-now', function (e) {
            e.preventDefault();
            var $btn = $(this);
            if ($btn.hasClass('is-loading')) return;

            var origHtml = $btn.html();
            $btn.addClass('is-loading').prop('disabled', true);
            $btn.find('.laca-btn-text').text(lacaDashboardData.checkingText || 'Đang kiểm tra…');
            $btn.find('.dashicons').addClass('laca-spin');

            $.post(lacaDashboardData.ajaxUrl, {
                action: 'laca_dashboard_refresh_audit',
                nonce:  lacaDashboardData.nonce
            }, function (res) {
                $btn.removeClass('is-loading').prop('disabled', false).html(origHtml);
                if (res.success && res.data) {
                    if (res.data.html_hero) {
                        $('#laca-dashboard-hero-container').html(res.data.html_hero);
                    }
                    if (res.data.html_todo) {
                        $('#laca-dashboard-todo-container').html(res.data.html_todo);
                    }
                    updateTodoBadge();
                }
            }).fail(function () {
                $btn.removeClass('is-loading').prop('disabled', false).html(origHtml);
                alert('Không thể làm mới kết quả kiểm tra. Vui lòng thử lại.');
            });
        });

        // 3. Storage Refresh AJAX
        $(document).on('click', '[data-laca-storage-refresh="1"]', function (e) {
            e.preventDefault();
            var $btn = $(this);
            if ($btn.hasClass('is-loading')) return;

            var origHtml = $btn.html();
            $btn.addClass('is-loading').prop('disabled', true);
            $btn.text(lacaDashboardData.calculatingText || 'Đang tính…');

            $.post(lacaDashboardData.ajaxUrl, {
                action: 'laca_storage_health_refresh',
                _nonce: lacaDashboardData.storageNonce
            }, function (res) {
                $btn.removeClass('is-loading').prop('disabled', false).html(origHtml);
                if (res.success && res.data && res.data.data) {
                    location.reload(); // Hoặc cập nhật khối
                }
            }).fail(function () {
                $btn.removeClass('is-loading').prop('disabled', false).html(origHtml);
                alert('Không thể tính toán dung lượng. Vui lòng thử lại.');
            });
        });
    });
})(jQuery);

