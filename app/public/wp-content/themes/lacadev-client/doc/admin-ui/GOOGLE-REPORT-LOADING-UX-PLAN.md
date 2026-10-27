# Plan — Báo cáo Google trên index.php: chuyển tab PSI tức thì, skeleton thật, trạng thái "Đang cập nhật"

> Người thực hiện: Antigravity → Claude review. HEAD `2c93d155`. P = `themes/lacadev-client`.
> File chính: `P/resources/scripts/admin/google-dashboard-report.js`, `P/app/src/Features/GoogleServices/GoogleServices.php` (~dòng 451 skeleton), `views/partials/google-report.php`, `views/partials/pagespeed-card.php`, `P/resources/styles/admin/laca-seo/index.scss` (~dòng 1584 `.laca-google-report-skeleton`).

## 1. Người dùng báo (ảnh production)
| # | Hiện tượng | Nguyên nhân (Claude đọc code) |
|---|---|---|
| U1 | Bấm tab Di động/Máy tính của PSI mất 2–3 giây | `bindPsiEvents()` (~dòng 343–360): mỗi lần đổi tab gọi AJAX `laca_pagespeed_run` `refresh=0` → bootstrap WordPress + đọc transient + render partial. Không có gì hiển thị ngay. |
| U2 | Lúc tải trang, khối GSC/GA gần như trống | Skeleton hiện có chỉ là 1 thanh + 2 khối trơn, nền gần trùng nền trang (ảnh: khung trắng nhạt, không thấy hình dạng nội dung) → người dùng tưởng trống. |
| U3 | Bấm "Làm mới" không thấy gì đang xảy ra | `loadReport(range, true)` chỉ thêm class `is-loading` lên container; nút không đổi; CSS `is-loading` gần như không có hiệu ứng nhìn thấy. Đổi khoảng ngày (7/14/28/90) cũng vậy. |

## 2. Yêu cầu

### 2.1 U1 — Đổi tab PSI không gọi mạng
- Server render **cả 2 panel** trong `pagespeed-card.php`: `<div class="laca-google-psi__panel" data-strategy="mobile">` và `…="desktop"` (panel không chọn có thuộc tính `hidden`). Mỗi panel đọc transient của strategy đó; chưa có cache → trạng thái rỗng riêng của panel + nút "Kiểm tra PageSpeed".
- Đổi tab = chỉ đổi `hidden` + `aria-selected`/`aria-pressed` (dùng `role="tablist"`/`tab`/`tabpanel`, phím ←/→ chuyển tab). **Không fetch khi đổi tab.** Ghi nhớ tab đang chọn theo user bằng `localStorage` (bọc try/catch).
- "Kiểm tra lại"/"Kiểm tra PageSpeed" chỉ chạy cho strategy của panel đang mở, kết quả AJAX chỉ thay **panel đó** (AJAX trả html của 1 panel), không render lại cả card.
- Đổi tab trong khi panel kia đang đo: vẫn chuyển ngay; panel đang đo giữ trạng thái đang đo của nó.
- Đo thời gian: từ click tab tới khi panel mới hiển thị **< 100 ms** (Puppeteer đo, xem nghiệm thu).

### 2.2 U2 — Skeleton giống bố cục thật
- Skeleton (cả bản PHP in sẵn ở `GoogleServices.php` ~451 và bản JS) dựng đúng khung của báo cáo thật: thanh công cụ (tiêu đề + 4 nút khoảng ngày + Làm mới), khối Phễu (4 ô), khối Key metrics (6 ô), 2 cột GSC / GA4, mỗi cột: tiêu đề, 4 ô KPI (nhãn ngắn + số to), 1 vùng biểu đồ, 1 bảng 5 dòng; khối PSI (tiêu đề + 5 ô).
- **Dùng chung 1 nguồn markup**: partial `views/partials/google-report-skeleton.php`; PHP include nó, JS không tự viết lại HTML skeleton mà lấy từ `<template id="laca-google-report-skeleton-tpl">` in sẵn trong trang.
- Hình khối nhìn thấy rõ: màu khối `var(--laca-bg-muted)` (hoặc token xám đậm hơn nền card 1 bậc) trên nền card trắng; hiệu ứng shimmer đơn sắc 1.4s; `@media (prefers-reduced-motion: reduce)` tắt shimmer. Chỉ token, không hex.
- A11y: vùng skeleton `aria-busy="true"` + 1 text ẩn "Đang tải báo cáo Google…"; khối thật thay vào thì `aria-busy="false"`.
- Responsive: skeleton xếp cột giống báo cáo thật ở 1440/1024/390 (không nhảy bố cục khi nội dung thật thay vào — CLS thấp: chiều cao skeleton xấp xỉ khối thật ±20%).

### 2.3 U3 — Trạng thái đang cập nhật khi Làm mới / đổi khoảng ngày
- Nút **Làm mới**: khi bấm → `disabled`, `aria-busy="true"`, icon xoay (dashicons-update + animation, tắt khi reduced-motion), chữ đổi "Đang cập nhật…". Xong → chữ trở lại "Làm mới" và dòng nhỏ cạnh thanh công cụ "Cập nhật lúc HH:MM" (giờ trình duyệt). Lỗi → nút trở lại + Alert lỗi như hiện tại.
- Nút khoảng ngày đang bấm: hiện trạng thái chọn ngay (không chờ data), các nút khoảng ngày khác `disabled` trong lúc tải.
- Nội dung cũ **giữ nguyên nhưng mờ** (`opacity: .5`, `pointer-events: none`) + thanh tiến trình mảnh không xác định (indeterminate) 2px chạy ở mép trên khối báo cáo. **Không** xoá nội dung thành skeleton khi Làm mới/đổi khoảng ngày (skeleton chỉ dùng cho lần tải đầu).
- Chống bấm liên tiếp: request đang chạy → bỏ qua click mới; đổi khoảng ngày trong lúc đang tải → `AbortController` huỷ request cũ, chạy request mới.
- Đảm bảo hiển thị trạng thái loading tối thiểu 300 ms (tránh nháy) khi dữ liệu trả từ cache quá nhanh.

## 3. Nghiệm thu (Claude sẽ chạy lại)
1. `wp-dashboard-report-audit.js` — chỉ THÊM bước, không sửa logic cũ (mock, 1440):
   - **U1**: tab Máy tính có panel trong DOM ngay khi tải trang; bấm tab → panel desktop hiện trong < 100 ms (`performance.now()` trước click → `waitForFunction` panel không `hidden`); **không** có request `admin-ajax.php` nào phát ra khi đổi tab (đếm request trong lúc bấm qua lại 4 lần = 0).
   - **U2**: chặn response `laca_google_report` 3 giây (request interception, `setTimeout` trước `continue`) → trong lúc chờ: `#laca-google-report [aria-busy="true"]` tồn tại, có ≥ 20 khối skeleton, mỗi khối có tương phản nền với card ≥ 1.15 (cùng hàm `lum` như `input-visibility-audit.js`), skeleton có 2 cột ở 1440 và 1 cột ở 390.
   - **U3**: chặn response 2 giây, bấm Làm mới → trong 100 ms nút có `disabled` + `aria-busy="true"` + text "Đang cập nhật"; nội dung cũ vẫn còn (số KPI cũ còn trong DOM) và có `opacity` < 1; có thanh tiến trình; sau khi trả → nút lại "Làm mới", có text "Cập nhật lúc". Bấm Làm mới 3 lần liên tiếp → chỉ 1 request.
   - Đổi khoảng 7 → 90 nhanh → request 7 ngày bị huỷ (aborted), kết quả cuối là 90 ngày.
2. `test-google-services.php`: render `pagespeed-card.php` với cache mobile có + desktop không → HTML có 2 panel, desktop là trạng thái rỗng, không chứa số bịa; AJAX PSI `strategy=desktop` trả html chỉ của panel desktop.
3. `input-visibility-audit.js`, `autosave-audit.js`, `list-table-audit.js` fail=0; `yarn lint` 0 error; `yarn build` OK; `php -l`. Grep: không `style="` mới, không hex ngoài `_tokens.scss`.
4. Ảnh: skeleton 1440 + 390, trạng thái đang Làm mới 1440 → `/private/tmp/admin-ui/google-loading/`.
5. Commit `feat(google): doi tab PSI tuc thi, skeleton dung bo cuc, trang thai dang cap nhat khi lam moi` → push → result kèm NGUYÊN VĂN SUMMARY.

## 4. Ràng buộc
Đơn sắc, không inline style, không cài package, không xoá file, Puppeteer có watchdog + `try/finally`, lệnh > 5 phút tự dừng. Kẹt → gửi progress, đừng im.
