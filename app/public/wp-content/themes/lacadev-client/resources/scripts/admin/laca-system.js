/**
 * laca-system.js
 *
 * Xử lý tương tác cho trang Laca Admin → Hệ thống & Dung lượng (laca-system):
 * - Sao chép thông tin hệ thống vào clipboard.
 * - Chuyển đổi giữa các tab con (Dung lượng chi tiết / File lớn).
 * - AJAX làm mới dung lượng.
 * - Quét file lớn (> 50MB) theo lô tránh timeout.
 * - Xoá an toàn các file lớn đã chọn.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const wrap = document.querySelector('.laca-system-wrap');
        if (!wrap) return;

        const config = window.lacaSystemData || {};

        // 1. Sao chép thông tin hệ thống
        const copyBtn = document.getElementById('laca-copy-system-info');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                const text = copyBtn.getAttribute('data-clipboard-text') || '';
                if (!text) return;

                const originalHtml = copyBtn.innerHTML;

                const doSuccess = function () {
                    copyBtn.innerHTML = '<span class="dashicons dashicons-yes" style="color:var(--laca-success);"></span> ' + (config.copiedText || 'Đã sao chép!');
                    copyBtn.classList.add('laca-btn--success');
                    setTimeout(function () {
                        copyBtn.innerHTML = originalHtml;
                        copyBtn.classList.remove('laca-btn--success');
                    }, 2500);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(doSuccess).catch(function () {
                        fallbackCopy(text, doSuccess);
                    });
                } else {
                    fallbackCopy(text, doSuccess);
                }
            });
        }

        function fallbackCopy(text, callback) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                if (callback) callback();
            } catch (err) {
                // Ignore
            }
            document.body.removeChild(textArea);
        }

        // 2. Chuyển đổi Sub-tab Dung lượng
        const tabBtns = wrap.querySelectorAll('[data-laca-subtab]');
        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.getAttribute('data-laca-subtab');
                tabBtns.forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');

                const detailPane = document.getElementById('laca-subtab-detail');
                const largePane  = document.getElementById('laca-subtab-large-files');

                if (target === 'detail') {
                    if (detailPane) detailPane.style.display = 'block';
                    if (largePane) largePane.style.display = 'none';
                } else {
                    if (detailPane) detailPane.style.display = 'none';
                    if (largePane) largePane.style.display = 'block';
                }
            });
        });

        // 3. Làm mới dung lượng qua AJAX
        wrap.addEventListener('click', function (e) {
            const refreshBtn = e.target.closest('[data-laca-storage-refresh]');
            if (!refreshBtn) return;

            const origText = refreshBtn.innerHTML;
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<span class="dashicons dashicons-update laca-spin"></span> ' + (config.calculating || 'Đang tính toán...');

            const body = new URLSearchParams({
                action: 'laca_storage_health_refresh',
                _nonce: config.storageNonce || ''
            });

            fetch(config.ajaxUrl || ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.data || 'Lỗi tính toán dung lượng.');
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = origText;
                }
            })
            .catch(function () {
                alert('Lỗi kết nối máy chủ.');
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = origText;
            });
        });

        // 4. Quét file lớn theo lô (Batch Scanner)
        const scanBtn = document.getElementById('laca-btn-scan-large-files');
        const progressBox = document.getElementById('laca-scan-progress-box');
        const progressBar = document.getElementById('laca-scan-progress-bar');
        const statusText  = document.getElementById('laca-scan-status-text');

        if (scanBtn) {
            scanBtn.addEventListener('click', function () {
                scanBtn.disabled = true;
                if (progressBox) progressBox.style.display = 'block';
                if (statusText) statusText.textContent = config.scanning || 'Đang khởi động quét...';
                if (progressBar) progressBar.style.width = '15%';

                runBatchScan(true);
            });
        }

        function runBatchScan(reset) {
            const body = new URLSearchParams({
                action: 'laca_storage_scan_batch',
                _nonce: config.scannerNonce || '',
                reset: reset ? '1' : '0'
            });

            fetch(config.ajaxUrl || ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    alert(res.data || 'Lỗi quét file lớn.');
                    if (scanBtn) scanBtn.disabled = false;
                    if (progressBox) progressBox.style.display = 'none';
                    return;
                }

                const data = res.data;
                if (data.status === 'scanning') {
                    if (statusText) statusText.textContent = data.message || 'Đang quét...';
                    if (progressBar) {
                        const curW = parseInt(progressBar.style.width, 10) || 20;
                        progressBar.style.width = Math.min(curW + 15, 85) + '%';
                    }
                    // Tiếp tục gọi batch kế tiếp
                    setTimeout(function () {
                        runBatchScan(false);
                    }, 200);
                } else if (data.status === 'done') {
                    if (progressBar) progressBar.style.width = '100%';
                    if (statusText) statusText.textContent = data.message || 'Hoàn tất!';
                    setTimeout(function () {
                        window.location.reload();
                    }, 600);
                }
            })
            .catch(function () {
                alert('Lỗi kết nối khi quét file.');
                if (scanBtn) scanBtn.disabled = false;
                if (progressBox) progressBox.style.display = 'none';
            });
        }

        // 5. Checkbox chọn file và Xoá file lớn
        const selectAllCb = document.getElementById('laca-select-all-deletable');
        const deleteBtn   = document.getElementById('laca-btn-delete-selected-files');

        function updateDeleteBtnState() {
            const checked = wrap.querySelectorAll('.laca-file-checkbox:checked');
            if (deleteBtn) {
                deleteBtn.disabled = checked.length === 0;
                const countBadge = checked.length > 0 ? ' (' + checked.length + ')' : '';
                deleteBtn.innerHTML = '<span class="dashicons dashicons-trash"></span> Xoá file đã chọn' + countBadge;
            }
        }

        if (selectAllCb) {
            selectAllCb.addEventListener('change', function () {
                const checkboxes = wrap.querySelectorAll('.laca-file-checkbox:not(:disabled)');
                checkboxes.forEach(function (cb) {
                    cb.checked = selectAllCb.checked;
                });
                updateDeleteBtnState();
            });
        }

        wrap.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('laca-file-checkbox')) {
                updateDeleteBtnState();
            }
        });

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                const checked = wrap.querySelectorAll('.laca-file-checkbox:checked');
                if (checked.length === 0) {
                    alert(config.selectAtLeastOne || 'Vui lòng chọn ít nhất một file để xoá.');
                    return;
                }

                let fileListSummary = '';
                const paths = [];
                checked.forEach(function (cb) {
                    paths.push(cb.value);
                    const name = cb.getAttribute('data-name') || cb.value;
                    const size = cb.getAttribute('data-size') || '';
                    fileListSummary += '\n• ' + name + (size ? ' (' + size + ')' : '');
                });

                const confirmMsg = (config.deleteConfirm || 'Bạn có chắc chắn muốn xoá các file đã chọn?')
                    + '\n' + fileListSummary;

                if (!confirm(confirmMsg)) {
                    return;
                }

                const origHtml = deleteBtn.innerHTML;
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<span class="dashicons dashicons-update laca-spin"></span> ' + (config.deleting || 'Đang xoá...');

                const body = new URLSearchParams({
                    action: 'laca_storage_delete_files',
                    _nonce: config.scannerNonce || ''
                });
                paths.forEach(function (p) {
                    body.append('paths[]', p);
                });

                fetch(config.ajaxUrl || ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        alert(res.data.message || 'Đã xoá các file đã chọn thành công.');
                        window.location.reload();
                    } else {
                        alert(res.data || 'Có lỗi xảy ra khi xoá file.');
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = origHtml;
                    }
                })
                .catch(function () {
                    alert('Lỗi kết nối khi gửi yêu cầu xoá.');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = origHtml;
                });
            });
        }
    });
})();

