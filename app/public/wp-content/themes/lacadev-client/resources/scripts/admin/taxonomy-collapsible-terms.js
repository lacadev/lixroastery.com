/**
 * Thu gọn/mở rộng danh mục con trên màn edit-tags.php của MỌI taxonomy
 * phân cấp (hierarchical) — chỉ là lớp UI thuần client-side (không đổi dữ
 * liệu/thứ tự term), bật theo yêu cầu người dùng để dễ nhìn/dễ kéo-thả sắp
 * xếp (qua plugin riêng) khi danh mục có nhiều cấp con.
 *
 * WP core render mỗi term là 1 <tr class="level-N"> phẳng (không lồng
 * nhau thật), tên cấp con có tiền tố "— " ngay trong text — dựa vào class
 * "level-N" để xác định quan hệ cha/con, không có cấu trúc DOM lồng thật.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.querySelector('.wp-list-table.tags, .wp-list-table.widefat.fixed.striped');
        if (!table) return;

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody > tr'));
        if (!rows.length) return;

        function getLevel(row) {
            var m = row.className.match(/level-(\d+)/);
            return m ? parseInt(m[1], 10) : 0;
        }

        var storageKey = 'laca_tax_collapsed_' + (window.lacaTaxSlug || 'default');
        var collapsed = {};
        try {
            collapsed = JSON.parse(localStorage.getItem(storageKey) || '{}');
        } catch (e) {
            collapsed = {};
        }

        function saveState() {
            try {
                localStorage.setItem(storageKey, JSON.stringify(collapsed));
            } catch (e) {
                // Private window / storage bị chặn — bỏ qua, chỉ mất tính
                // năng nhớ trạng thái giữa các lần tải trang, không lỗi gì.
            }
        }

        rows.forEach(function (row, idx) {
            var level = getLevel(row);
            var children = [];
            for (var j = idx + 1; j < rows.length; j++) {
                var childLevel = getLevel(rows[j]);
                if (childLevel <= level) break;
                children.push(rows[j]);
            }
            if (!children.length) return;

            var nameLink = row.querySelector('.column-name a.row-title')
                || row.querySelector('td.name a')
                || row.querySelector('.column-name a');
            if (!nameLink) return;

            var rowKey = row.id || ('laca-tax-row-' + idx);
            var isCollapsed = !!collapsed[rowKey];

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'laca-tax-toggle';
            toggle.setAttribute('aria-label', 'Thu gọn/Mở rộng danh mục con');
            toggle.style.cssText = 'background:none;border:none;cursor:pointer;padding:0 6px 0 0;margin-right:4px;font-size:11px;line-height:1;color:#2271b1;vertical-align:middle';

            function applyState() {
                children.forEach(function (child) {
                    child.style.display = isCollapsed ? 'none' : '';
                });
                toggle.textContent = isCollapsed ? '▶' : '▼';
                toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            }

            applyState();

            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                isCollapsed = !isCollapsed;
                collapsed[rowKey] = isCollapsed;
                saveState();
                applyState();
            });

            nameLink.parentNode.insertBefore(toggle, nameLink);
        });

        // Nút "Thu gọn tất cả" / "Mở rộng tất cả" tiện lợi khi danh mục có
        // nhiều cấp con (vd 43 items như taxonomy Journal) — chèn ngay phía
        // trên bảng.
        var allToggleButtons = Array.prototype.slice.call(table.querySelectorAll('.laca-tax-toggle'));
        if (allToggleButtons.length) {
            var bar = document.createElement('p');
            bar.style.cssText = 'margin:8px 0';
            var collapseAllBtn = document.createElement('button');
            collapseAllBtn.type = 'button';
            collapseAllBtn.className = 'button button-small';
            collapseAllBtn.textContent = 'Thu gọn tất cả';
            var expandAllBtn = document.createElement('button');
            expandAllBtn.type = 'button';
            expandAllBtn.className = 'button button-small';
            expandAllBtn.style.marginLeft = '6px';
            expandAllBtn.textContent = 'Mở rộng tất cả';

            collapseAllBtn.addEventListener('click', function () {
                allToggleButtons.forEach(function (btn) {
                    if (btn.getAttribute('aria-expanded') === 'true') btn.click();
                });
            });
            expandAllBtn.addEventListener('click', function () {
                allToggleButtons.forEach(function (btn) {
                    if (btn.getAttribute('aria-expanded') === 'false') btn.click();
                });
            });

            bar.appendChild(collapseAllBtn);
            bar.appendChild(expandAllBtn);
            table.parentNode.insertBefore(bar, table);
        }
    });
})();
