# Fix gấp — Dashboard GSC/GA4 đang hiển thị SỐ BỊA trộn với dữ liệu thật (commit 581bd797, đã lên production)

> Người thực hiện: Antigravity → Claude review. File chính: `P/app/src/Features/LacaSeo/Admin/GoogleDashboardService.php`,
> `P/app/src/Features/LacaSeo/Modules/GoogleAnalytics/GaClient.php`, `P/app/src/Features/LacaSeo/Modules/SearchConsole/GscClient.php`,
> `P/app/src/Features/LacaSeo/Admin/views/dashboard.php`, `P/app/src/Features/LacaSeo/Modules/DashboardWidget/DashboardWidget.php`.

## Lỗi (Claude đọc code)
Trong nhánh **dữ liệu thật** (`isMock => false`) của `GoogleDashboardService`:
- GSC: `clicks_delta '+12.4%'`, `impressions_delta '+18.2%'`, `ctr_delta '+0.5%'`, `position_delta '+1.8'` — viết cứng (dòng ~86–92).
- GA4: `users_delta '+14.1%'`, `sessions_delta '+9.8%'`, `avgEngagement_delta '+15s'`, `conversionRate '4.7%'`, `conversionRate_delta '+0.8%'`,
  `businessKpis` 186 lượt "Bấm Mua / Thêm giỏ", 62 lượt "Gửi Form / Đặt lịch workshop", "+18.5% so với kỳ trước", "6.5%…" — viết cứng (dòng ~164–185).
- `averageSessionDuration ?? 105` — thiếu dữ liệu thì bịa 105 giây.
- Badge "Dữ liệu mẫu" dùng màu vàng + inline style (sai quy ước đơn sắc, không inline style).
→ Người dùng kết nối thật sẽ thấy số bịa như số thật. **Tuyệt đối không được hiển thị số không đến từ API.**

## Yêu cầu sửa
1. **Nhánh thật không còn một hằng số hiển thị nào.** Mọi số/% phải tính từ response API; không có dữ liệu → hiện `—` và ẩn mũi tên ▲▼.
2. **So sánh kỳ trước thật**:
   - GA4 `runReport`: dùng 2 `dateRanges` (kỳ hiện tại: `{N}daysAgo`→`yesterday`; kỳ trước: `{2N}daysAgo`→`{N+1}daysAgo`) — response có dimension `dateRange`;
     tính delta % = (hiện − trước)/trước × 100, trước = 0 → `—`. Thời gian tương tác: delta theo giây.
   - GSC: gọi `searchAnalytics/query` thêm 1 lần cho kỳ trước (cùng độ trễ 3 ngày), không dimension → tổng click/impression/CTR/position kỳ trước.
     Position: giảm là tốt (hiển thị "▲ tốt hơn" khi số nhỏ đi).
   - Cache cả 2 kỳ cùng transient như hiện tại; không cache khi lỗi.
3. **Tỷ lệ chuyển đổi**: lấy metric GA4 `keyEvents` + `sessionKeyEventRate` (hoặc keyEvents/sessions). Property chưa cấu hình key event (keyEvents = 0 cả 2 kỳ) →
   ẩn ô hoặc hiện "Chưa cấu hình sự kiện chính trong GA4" + link hướng dẫn, KHÔNG hiện 0% hay số mẫu.
4. **Khối "Hành động mục tiêu"**: chỉ hiện khi người dùng cấu hình tên sự kiện trên trang `laca-google-analytics` (field mới `ga4_goal_events` trong `laca_seo_general`:
   danh sách `event_name|Nhãn hiển thị`, vd `add_to_cart|Thêm giỏ`, `generate_lead|Gửi form liên hệ`). Số liệu = `runReport` dimension `eventName`, metric
   `eventCount`, filter theo danh sách, 2 kỳ để có delta. Chưa cấu hình → không hiện khối (hoặc 1 dòng gợi ý cấu hình). Bỏ hẳn nhãn "Hạt cà phê"/"Workshop" cứng.
5. **Dữ liệu mẫu**: chỉ khi có tham số `?laca_mock_google=1` **và** `wp_get_environment_type()==='local'` **và** `manage_options`. Bỏ chế độ tự hiện mẫu khi local chưa kết nối
   (chưa kết nối → trạng thái rỗng + nút "Thiết lập" như production). Khi mẫu đang bật: banner đơn sắc rõ ràng ở đầu khu vực ("DỮ LIỆU MẪU — không phải số liệu thật"),
   class CSS (không inline style, không vàng). Hàm mock để riêng, không dùng chung đường code với nhánh thật.
6. WP Dashboard widget (`DashboardWidget.php`) dùng cùng dữ liệu → tự đúng theo; kiểm lại không còn số cứng.

## Nghiệm thu (Claude sẽ chạy lại)
1. Kiểm tĩnh — in ra 0 dòng:
   `grep -nE "'[+-][0-9]|=> (186|62|248|105)\b|'4\.7%'|Hạt cà phê|Workshop" P/app/src/Features/LacaSeo/Admin/GoogleDashboardService.php` — ngoại trừ bên trong hàm mock
   (đặt tên `getMockGscData`/`getMockGa4Data`, Claude sẽ loại khỏi grep theo phạm vi hàm).
2. Thêm vào `P/doc/admin-ui/tools/test-google-services.php` (mock `pre_http_request`, không gọi Google thật):
   - GSC kỳ hiện tại clicks=10, kỳ trước=5 → `clicks_delta` = "+100%"; kỳ trước=0 → `—`.
   - GA4 2 dateRanges: activeUsers 30 vs 20 → "+50%"; sessions 0 vs 0 → `—`; keyEvents 0 cả 2 kỳ → không có `conversionRate` số.
   - Không cấu hình `ga4_goal_events` → không có `businessKpis`; cấu hình `add_to_cart|Thêm giỏ` + response eventCount 7 vs 0 → value 7, delta `—`.
   - Render `views/dashboard.php` với response mock thật (không bật mock mode) vào buffer → HTML KHÔNG chứa "186", "12.4%", "14.1%", "4.7%", "Dữ liệu mẫu".
   - Không có `laca_mock_google` → `isMockMode()` false kể cả local chưa kết nối.
   In PASS/FAIL + SUMMARY. Không test giả.
3. `input-visibility-audit.js`, `autosave-audit.js`, `list-table-audit.js`, `admin-ui-audit.js` fail=0; `yarn lint` 0 error; `yarn build` OK; `php -l`.
4. Commit `fix(laca-seo): dashboard Google chỉ hiển thị số liệu thật — delta kỳ trước, key events, mục tiêu cấu hình được, mock chỉ khi yêu cầu` → push → `result` qua agent-bridge.
Ràng buộc như các plan trước (không cài package, không xoá file, không cửa sau, script có watchdog, đơn sắc).
