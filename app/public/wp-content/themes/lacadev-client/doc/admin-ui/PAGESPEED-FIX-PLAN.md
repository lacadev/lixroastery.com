# Plan — Sửa khối PageSpeed Insights trên wp-admin/index.php (G4 hỏng)

> Người thực hiện: Antigravity → Claude review. HEAD `c7aa5e58`. P = `themes/lacadev-client`. Đơn sắc, không inline style mới, không số bịa.

## 1. Lỗi đã xác định (Claude đọc code)
| # | Người dùng thấy | Nguyên nhân |
|---|---|---|
| P1 | Bấm "Kiểm tra PageSpeed" / "Chi tiết" → "Sorry, you are not allowed to access this page." | `views/partials/google-report.php` ~dòng 945 và ~1020 link tới `admin.php?page=laca-google-pagespeed`, nhưng **slug này chưa từng được đăng ký** (`GoogleServicesPage::registerPages()` chỉ có `laca-google-analytics`, `laca-google-search-console`). WP trả "not allowed" cho page lạ. |
| P2 | Không có cách nào chạy PSI | AJAX `wp_ajax_laca_pagespeed_run` (`GoogleServices::ajaxPageSpeedRun`) có sẵn nhưng **không JS nào gọi**; `resources/scripts/admin/google-dashboard-report.js` chỉ gọi `laca_google_report` và `laca_ga4_realtime`. |
| P3 | Số bịa | Fallback cứng: `'1.8s'`, `'85ms'`, `'0.02'`, `'94 điểm'`, `'94/100'`, nhãn `'TỐT'` mặc định. Vi phạm quy tắc "không hiển thị số bịa". |
| P4 | Sai dữ liệu khi site ít traffic | Khối chỉ render khi `$psiData['field']` khác rỗng và luôn đọc field (CrUX). Site nhỏ thường **không có CrUX** (`field.hasData = false`) → phải hiển thị dữ liệu Lab (Lighthouse) và ghi rõ "Chưa đủ dữ liệu người dùng thực (CrUX)". |
| P5 | Màu xanh lá, inline style | Thẻ metric dùng `#f0fdf4/#166534/#10b981…` và `style=""` dày đặc → trái quy tắc đơn sắc + không inline style. |

## 2. Yêu cầu sửa
1. **Bỏ link tới `laca-google-pagespeed`**. Không tạo trang mới. Nút "Chi tiết" → link ngoài `https://pagespeed.web.dev/report?url=<home_url urlencoded>` (`target="_blank" rel="noopener"`, có icon ngoài).
2. Nút "Kiểm tra PageSpeed" (trạng thái rỗng) và nút "Kiểm tra lại" (khi đã có dữ liệu) là `<button type="button" data-laca-psi-run>` → JS trong `google-dashboard-report.js` gọi `admin-ajax.php` `action=laca_pagespeed_run`, `nonce` = nonce `laca_google_report_nonce` đang dùng cho report, `strategy=mobile`, `refresh=1`.
   - Trong lúc chạy (PSI mất 15–50s): nút `disabled` + chữ "Đang kiểm tra… (có thể mất ~30 giây)"; `fetch` có `AbortController` timeout 70s.
   - Thành công → thay nội dung khối bằng HTML render từ server. Cách gọn nhất: tách phần thân khối PSI thành partial `views/partials/pagespeed-card.php` (nhận `$psiData`), AJAX trả `{ html }` render partial đó (vẫn kèm data). Không dựng HTML bằng JS.
   - Lỗi → hiện Alert đơn sắc trong khối với message của Google (vd 429 quota → gợi ý thêm API key ở Laca Admin → Dịch vụ Google), nút "Thử lại". Không `alert()`.
3. Partial hiển thị:
   - Nếu `field.hasData` → 3 thẻ LCP/INP/CLS từ CrUX (giá trị + `category` thật) + thẻ điểm Lighthouse.
   - Nếu không → dòng ghi chú "Chưa đủ dữ liệu người dùng thực (CrUX) cho URL này — hiển thị kết quả đo thử (Lighthouse)" + thẻ điểm Lighthouse + 4 thẻ lab LCP/CLS/TBT/FCP (`displayValue` thật).
   - Thiếu giá trị → `—`, **không fallback số**. Có dòng "Đo lúc <testedDate> · Di động".
   - Trạng thái tốt/trung bình/kém thể hiện bằng **chữ + chấm/viền đậm nhạt xám** (đơn sắc), không xanh/đỏ/vàng.
4. CSS: chuyển toàn bộ style của khối PSI sang SCSS admin hiện có của báo cáo Google (cùng file đang chứa `.laca-google-report` / `.laca-seo-card`), BEM `laca-google-psi__*`, màu lấy từ token `_tokens.scss`. Không `style=""` trong partial mới.
5. Quyền: `ajaxPageSpeedRun` giữ nonce + `userCanViewReport()`; `$targetUrl` chỉ chấp nhận URL cùng host với `home_url()` (nếu khác host → dùng `home_url('/')`) để endpoint không thành proxy gọi PSI cho site bất kỳ.

## 3. Nghiệm thu (Claude sẽ chạy lại)
1. `grep -rn "laca-google-pagespeed" P/app P/resources` = 0 dòng. `grep -nE "'(1\.8s|85ms|0\.02|94)" P/app/src/Features/GoogleServices/views` = 0 dòng.
2. `test-google-services.php` thêm (mock `pre_http_request` với JSON PSI **định dạng thật** của Google — có 1 mẫu có `loadingExperience.metrics`, 1 mẫu không có):
   - AJAX `laca_pagespeed_run` không nonce → `-1`/403; có nonce → `success` + `html` chứa giá trị đúng của mẫu; mẫu không CrUX → html có chữ "Chưa đủ dữ liệu người dùng thực" và điểm Lighthouse đúng mẫu.
   - Google trả 429 → `success:false` + message; `url=https://evil.example/` → request gửi đi có `url=home_url('/')`.
   - Partial với dữ liệu rỗng không in số nào ngoài `—`.
3. Puppeteer `wp-dashboard-report-audit.js` (chỉ được THÊM bước, không sửa logic cũ): ở `index.php?laca_mock_google=1` bấm `[data-laca-psi-run]` → trong 15s khối PSI có số liệu, không `pageerror`, không chuyển trang; link "Chi tiết" có `href` bắt đầu `https://pagespeed.web.dev/`.
4. `input-visibility-audit.js`, `autosave-audit.js`, `list-table-audit.js` fail=0; `yarn lint` 0 error; `yarn build` OK; `php -l` các file sửa.
5. Commit `fix(google): khoi PageSpeed chay qua AJAX, bo link trang khong ton tai, bo so bia` → push → `result` qua agent-bridge (NGUYÊN VĂN SUMMARY test/audit).

## 4. Ràng buộc
Không xoá file, không cài package, không in API key ra HTML/log, không cửa sau, script Puppeteer có watchdog + `try/finally`, lệnh > 5 phút tự dừng.
