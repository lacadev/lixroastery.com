<?php

namespace App\PostTypes\Concerns;

/**
 * Trait HasCurrencyFormat
 *
 * - Lưu giá trị tiền tệ dưới dạng số nguyên (strip dấu chấm/phẩy)
 * - Hiển thị dạng 1.000.000 khi load lên admin
 * - Inject JS tự động recalc payment_status khi admin chỉnh giá
 *
 * Used by: App\PostTypes\Project
 */
trait HasCurrencyFormat
{
    /** @var string[] Top-level currency meta keys */
    private array $currencyFields = [
        'price_build',
        'price_maintenance_yearly',
        'domain_price',
        'hosting_price',
    ];

    // -----------------------------------------------------------------------

    public function formatCurrencyOnSave($value, $id, $name, $field)
    {
        // Top-level fields
        if (in_array($name, $this->currencyFields, true) && !empty($value)) {
            return preg_replace('/[^0-9]/', '', $value);
        }

        // Complex field: payment_history > pay_amount
        if (strpos($name, '_payment_history|pay_amount|') !== false && !empty($value)) {
            return preg_replace('/[^0-9]/', '', $value);
        }

        return $value;
    }

    public function formatCurrencyOnLoad($value, $id, $name, $field)
    {
        if (in_array($name, $this->currencyFields, true) && is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.');
        }

        if (strpos($name, '_payment_history|pay_amount|') !== false && is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.');
        }

        return $value;
    }

    public function addCurrencyFormatterScript(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'project') {
            return;
        }
        ?>
        <script>
        (function () {
            'use strict';

            /**
             * Chuyển chuỗi tiền tệ ("5.500.000" hoặc "5,500,000") thành số nguyên.
             */
            function parseCurrency(str) {
                if (!str) return 0;
                return parseInt(str.toString().replace(/[^0-9]/g, ''), 10) || 0;
            }

            /**
             * Tính và cập nhật payment_status dropdown dựa vào giá trị hiện tại
             * của price_build và tất cả pay_amount trong complex field.
             */
            function recalcPaymentStatus() {
                // CF input names (stable):
                // - carbon_fields_compact_input[_price_build]
                // - carbon_fields_compact_input[_payment_history][0][pay_amount], ...
                var buildInput = document.querySelector('input[name="carbon_fields_compact_input[_price_build]"]');
                if (!buildInput) return;

                var totalBuild = parseCurrency(buildInput.value);

                // Chỉ lấy pay_amount thuộc payment_history để tránh cộng nhầm field khác.
                var payAmountInputs = document.querySelectorAll(
                    'input[name^="carbon_fields_compact_input[_payment_history]"][name$="[pay_amount]"]'
                );

                var totalPaid = 0;
                payAmountInputs.forEach(function (input) {
                    totalPaid += parseCurrency(input.value);
                });

                // Xác định status mới
                var newStatus;
                if (totalBuild <= 0) {
                    return; // Chưa có giá build → không can thiệp
                } else if (totalPaid <= 0) {
                    newStatus = 'pending';
                } else if (totalPaid < totalBuild) {
                    newStatus = 'partial';
                } else {
                    newStatus = 'paid';
                }

                // CF select: name="carbon_fields_compact_input[_payment_status]"
                var statusSelect = document.querySelector('select[name="carbon_fields_compact_input[_payment_status]"]');
                if (statusSelect && statusSelect.value !== newStatus) {
                    // CF dùng React controlled component → set .value trực tiếp bị React reset.
                    // Phải dùng native setter để bypass React reconciliation.
                    var nativeSetter = Object.getOwnPropertyDescriptor(
                        window.HTMLSelectElement.prototype, 'value'
                    ).set;
                    nativeSetter.call(statusSelect, newStatus);

                    // Dispatch input + change để React nhận biết và cập nhật state
                    statusSelect.dispatchEvent(new Event('input',  { bubbles: true }));
                    statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            /**
             * Gắn event listeners cho tất cả inputs liên quan.
             * Dùng MutationObserver để bắt khi complex field thêm/xóa row.
             */
            function attachListeners() {
                var container = document.querySelector('.cf-container, #post-body, #cf-container');
                if (!container) container = document.body;

                // Lắng nghe input/change trên toàn bộ container (event delegation)
                container.addEventListener('input', function (e) {
                    var name = e.target.name || '';
                    var isRelevant = (
                        name.indexOf('price_build') !== -1 ||
                        name.indexOf('pay_amount') !== -1
                    );
                    if (isRelevant) {
                        recalcPaymentStatus();
                    }
                });

                // Debounce: tránh recalc quá nhiều khi CF đang render nhiều DOM node
                var debounceTimer;
                function debouncedRecalc() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(recalcPaymentStatus, 200);
                }

                // Xử lý khi complex field thêm/xóa row
                var observer = new MutationObserver(debouncedRecalc);
                observer.observe(container, { childList: true, subtree: true });

                // Chạy lần đầu sau khi CF load xong (CF dùng React/Vue nên cần delay nhỏ)
                setTimeout(recalcPaymentStatus, 800);
            }

            // Khởi động sau khi DOM sẵn sàng
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', attachListeners);
            } else {
                attachListeners();
            }
        })();
        </script>
        <?php
    }
}
