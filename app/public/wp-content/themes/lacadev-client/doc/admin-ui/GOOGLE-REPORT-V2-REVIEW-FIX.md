# Review fix — Báo cáo Google v2 (commit 23eab0fe)

> Claude review 27/10/2026. Test `test-google-services.php` báo 36/36 và audit 31/31 nhưng **bỏ sót 4 lỗi thật** dưới đây → test đang kiểm dữ liệu tự nặn chứ không kiểm đường dữ liệu thật. Làm SAU task quét mã độc.

| # | Lỗi | Bằng chứng |
|---|---|---|
| R1 | **Key metrics không có đường dữ liệu thật**: `GoogleDashboardService::getGaData()` (bản thật, ~dòng 351–383) **không trả `keyMetrics`**; `GaClient` không có request nào cho `newVsReturning`, `city`, `screenPageViewsPerSession`, sản phẩm, `sessionKeyEventRate`, `add_to_cart/purchase`. → Production 8 ô luôn "—". | `grep -n "newVsReturning\|city\|screenPageViewsPerSession" GaClient.php` = 0 |
| R2 | **Mock và view lệch key**: mock trả `keyMetrics.new_vs_returning.subtext` (snake_case), view `google-report.php:189+` đọc `$kmData['newVsReturning']['sub']` → ngay cả mock cũng hiện "—" (ảnh `/private/tmp/admin-ui/google-v2/wp-dashboard-mock-1440.png`). | |
| R3 | **Phễu bước 3 luôn 0**: service trả `organicSearch.hasData`, view đọc `$organic['hasOrganic']` → luôn false → "Chưa ghi nhận…" kể cả khi có Organic (mock có 54% Organic mà vẫn 0). | ảnh mock 1440 |
| R4 | **Lỗi PSI in HTML thô + lặp**: message tiếng Việt chứa `<a href=…>` bị escape thành chữ, và câu lỗi hiện 2 lần (dòng chính + khung xám). | `/private/tmp/admin-ui/google-v2/wp-dashboard-psi-error.png` |

## Yêu cầu
1. **R1**: thêm vào `GaClient` 1 batch thứ 2 (≤ 5 report) cho Key metrics theo đúng GOOGLE-REPORT-V2-PLAN 2.4, có `hostName` filter + dateRanges hiện tại & kỳ trước; parse thành `keyMetrics` với **một** schema duy nhất (camelCase: `newVsReturning`, `topTrafficSource`, `topCities`, `pagesPerSession`, `sessionDuration`, `topProducts`, `bestConversionSource`, `addToCartSource`; mỗi ô `{title, value, sub, available:bool, reason?}`). Mock dùng **cùng** schema (tốt nhất: mock = raw response GA giả → đi qua cùng hàm parse thật). Ô 7/8 chỉ gọi khi đã phát hiện key event / event `add_to_cart|purchase` (kiểm 90 ngày, cache theo G2).
2. **R2/R3**: một tên key duy nhất giữa service ↔ view (`organicSearch.hasData`). Grep toàn bộ key view đọc từ `$ga[...]`/`$gsc[...]` và đối chiếu với key service trả — liệt kê bảng đối chiếu trong báo cáo.
3. **R4**: `WP_Error` của PSI trả `message` (text thuần) + `data.actionUrl` + `data.actionLabel`; JS/partial render link bằng `<a>` thật (esc_url), message chỉ hiện **một lần**.
4. **Test thật** (bắt buộc, sửa cả cách test):
   - Mock `pre_http_request` trả **raw JSON định dạng GA4 runReport/batchRunReports** (rows/dimensionValues/metricValues) cho batch Key metrics → render `google-report.php` thật → HTML chứa đúng giá trị từng ô (vd "Đà Nẵng"), không "—" ở ô có dữ liệu.
   - Raw GA có channel `Organic Search` → bước 3 phễu hiện đúng số users Organic; không có → 0 + ghi chú.
   - PSI 429 → HTML không chứa `&lt;a` và câu lỗi xuất hiện đúng 1 lần.
   - Puppeteer (thêm bước vào `wp-dashboard-report-audit.js`): ở mock, mỗi ô `.laca-google-km__value` (hoặc class tương ứng) **không** là "—" cho 6 ô mặc định; bước 3 phễu ≠ "0"; khối lỗi PSI không có chuỗi `<a href`.
5. Chạy lại toàn bộ test/audit, `php -l`, `yarn lint`, `yarn build`. Commit `fix(google): key metrics co du lieu that, pheu organic dung key, loi PSI khong in HTML tho` → push → result kèm NGUYÊN VĂN SUMMARY + ảnh mock 1440 mới.
