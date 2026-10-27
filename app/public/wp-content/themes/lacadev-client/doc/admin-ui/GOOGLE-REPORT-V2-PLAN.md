# Plan — Báo cáo Google v2: biểu đồ, phễu đúng, Key metrics đủ, bảng 100 từ khoá, PSI Mobile/Desktop, SCSS đơn sắc, hướng dẫn + chi phí API

> Người thực hiện: Antigravity → Claude review. HEAD `1ee3f9b2`. P = `themes/lacadev-client`. G = `P/app/src/Features/GoogleServices`.
> Đọc trước: `GOOGLE-SITEKIT-PARITY-PLAN.md` (mục 3–7), `PAGESPEED-FIX-PLAN.md`. Plugin tham khảo: `plugins/google-site-kit` (chỉ đọc).

## 0. Người dùng chốt
1. GSC giữ `dataState: all` như hiện tại (số có thể lệch trang GSC 1–3 ngày gần nhất — chấp nhận). Không đổi.
2. PSI: hộp lỗi **thay thế** khối rỗng (không còn 2 nút cùng lúc), dịch lỗi sang tiếng Việt, thêm tab Mobile / Desktop.
3. Thêm biểu đồ, sửa phễu, làm đủ Key metrics cho shop, bảng 100 từ khoá, chuyển style sang SCSS, bỏ màu xanh.
4. Hướng dẫn bật PageSpeed Insights API + lấy key, và bảng chi phí/hạn mức các API đang dùng.

## 1. Lỗi/thiếu Claude đã xác định
| # | Vị trí | Vấn đề |
|---|---|---|
| V1 | `google-dashboard-report.js` `renderPsiError` | Chèn hộp lỗi nhưng giữ `.laca-google-psi__empty` → 2 nút "Thử lại" + "Kiểm tra PageSpeed". Hint lỗi có `style=""`. Message Google tiếng Anh. |
| V2 | `views/partials/google-report.php` ~dòng 100–112 | Phễu: không có kênh Organic → `$organicCount` lấy **tổng người dùng** (sai nghĩa). |
| V3 | cùng file, khối Key Metrics ~dòng 200–300 | Chỉ ~5 ô gần đúng; hardcode chuỗi mock `'798 mới • 452 quay lại'` trong view; thiếu: thành phố, sản phẩm xem nhiều, nguồn chuyển đổi tốt nhất, nguồn dẫn tới thêm giỏ/mua; chưa bật/tắt từng ô. |
| V4 | cùng file ~dòng 509 | "Xem tất cả" trỏ `admin.php?page=laca-seo&view=search-console` (URL cũ) + màu `--laca-accent, #2563eb`. |
| V5 | cùng file | Không có biểu đồ nào. Inline `style=""` dày đặc, hex fallback, màu xanh (ô realtime xanh lá). |
| V6 | PSI | Chỉ mobile. |

## 2. Yêu cầu

### 2.1 PSI (V1, V6)
- Khi lỗi: thay **toàn bộ thân khối** (giữ header) bằng Alert đơn sắc + 1 nút "Thử lại". Không `style=""` trong JS — dùng class `laca-google-psi__alert*` trong SCSS.
- Dịch lỗi ở PHP (`PageSpeedClient` trả `WP_Error` với message tiếng Việt, giữ mã gốc trong data):
  - 429 / "Quota exceeded" → "Đã hết hạn mức miễn phí của Google cho hôm nay. Thêm PageSpeed API key để có hạn mức riêng." + link tới ô nhập key (`admin.php?page=laca-google-search-console#laca-psi-key`, thêm `id="laca-psi-key"` cho card).
  - 400 API key not valid → "API key không hợp lệ — kiểm tra lại key."
  - 403 API chưa bật / key bị hạn chế sai → "PageSpeed Insights API chưa được bật trong project Google Cloud của key, hoặc key bị giới hạn sai API."
  - 400 lỗi tải URL (`FAILED_DOCUMENT_REQUEST`/`ERRORED_DOCUMENT_REQUEST`) → "Google không tải được trang này (trang lỗi hoặc chặn bot)."
  - Khác → "Lỗi PageSpeed Insights (HTTP …)". Lỗi 429 cache 10 phút như plan G2 (không gọi lại liên tục).
- Tab **Di động / Máy tính** trong header khối (2 nút dạng segmented, `aria-pressed`). Mỗi strategy cache riêng (transient đã theo `url_strategy`). Đổi tab: có cache → render ngay từ server (AJAX `refresh=0`), chưa có → trạng thái rỗng của tab đó + nút Kiểm tra. Footer "Đo lúc … · Di động|Máy tính".

### 2.2 Biểu đồ (V5)
- SVG **render phía server** bằng 1 helper PHP `G/Chart.php::line(array $series, array $opts): string` (không cài thư viện). Đơn sắc: kỳ hiện tại nét liền `var(--laca-fg)`, kỳ trước nét đứt `var(--laca-fg-subtle)`; trục ngày dưới (≤ 6 nhãn), trục giá trị trái (0, giữa, max); `viewBox` co giãn theo chiều rộng; mỗi điểm có `<title>` "dd/mm: giá trị" để hover thấy số; `role="img"` + `aria-label` tóm tắt.
- Hai biểu đồ:
  1. Cột GSC: **Click và Lượt hiển thị theo ngày** (2 đường, 2 trục: trái click, phải hiển thị — hoặc 2 biểu đồ nhỏ chồng nhau, chọn cái dễ đọc hơn, ghi rõ trong báo cáo).
  2. Cột GA4: **Người dùng theo ngày**, kỳ hiện tại + kỳ trước.
- Dữ liệu: GSC dimension `date` (đã có sample report G2 — tái dùng, không thêm request nếu được); GA4 `totalUsers × date` trong `batchRunReports` sẵn có, `keepEmptyRows: true` để đủ ngày. Tổng request mỗi lần tải vẫn ≤ 3 GA + ≤ 3 GSC (đo, ghi số vào báo cáo).
- Chưa có dữ liệu → không vẽ trục rỗng; hiện câu trạng thái của G2.

### 2.3 Phễu tìm kiếm (V2)
Theo Site Kit `searchFunnelGA4`: Lượt hiển thị (GSC) → Lượt click (GSC) → Người dùng từ Tìm kiếm tự nhiên (GA4 `totalUsers` lọc `sessionDefaultChannelGroup = "Organic Search"`) → Tỉ lệ tương tác của nhóm đó (`engagementRate` cùng filter). Không có dòng Organic → hiện `0` + ghi chú "Chưa ghi nhận người dùng từ tìm kiếm tự nhiên trong khoảng này". **Tuyệt đối không fallback sang tổng người dùng.** Mỗi bước có delta kỳ trước.

### 2.4 Key metrics cho shop (V3)
Đúng 8 ô theo plan parity mục 4 (Khách mới/quay lại `newVsReturning`; Nguồn hàng đầu; Thành phố top 3 `city`; Trang/lượt `screenPageViewsPerSession`; Thời lượng `averageSessionDuration`; Sản phẩm xem nhiều `pagePath BEGINS_WITH /product/` + `/en/product/` kèm `pageTitle`; Nguồn chuyển đổi tốt nhất `sessionKeyEventRate × sessionDefaultChannelGroup` khi property có key event; Nguồn dẫn tới thêm giỏ/mua `eventCount` lọc `eventName IN [add_to_cart, purchase]` × channel khi có event trong 90 ngày).
- Bật/tắt từng ô ở trang Google Analytics (checkbox, option `laca_google_services[key_metrics]`, mặc định 6 ô đầu; autosave theo cơ chế có sẵn). Ô thiếu điều kiện → ẩn trên dashboard, trang cài đặt hiện lý do cạnh checkbox.
- Gom vào batch có sẵn (Key metrics có thể là batch thứ 2, tối đa 5 report/batch).
- Bỏ mọi chuỗi số trong view; mock lấy từ hàm mock trong service (đã có `isMockMode`), không viết tay trong view.

### 2.5 Bảng 100 từ khoá (V4)
- "Xem tất cả" mở **dialog** (`<dialog>` gốc, Esc/nút đóng, focus trả lại nút) tải AJAX mới `laca_gsc_queries` (nonce report + `userCanViewReport()`), `rowLimit: 100`, cùng range đang chọn, cache 1 giờ.
- Cột: Từ khoá, Click, Hiển thị, CTR, Vị trí; bấm tiêu đề cột để sắp xếp (client-side); ô lọc nhanh theo chữ; tiêu đề cột dính khi cuộn trong dialog. Rỗng/lỗi có trạng thái riêng.
- Bỏ link `laca-seo&view=search-console` khỏi partial.

### 2.6 SCSS + đơn sắc (V5)
- `google-report.php`, `pagespeed-card.php`, partial mới: **0** `style="` (trừ giá trị runtime như độ rộng thanh % — ghi chú `/* động */` bằng CSS custom property `style="--w:42%"`), **0** hex.
- Style vào `resources/styles/admin/laca-seo/index.scss` (hoặc tách `_google-report.scss` import vào đó), BEM `laca-google-*`, chỉ token `_tokens.scss`.
- Bỏ xanh: ô "Đang hoạt động" dùng nền `--laca-bg-subtle` + chấm `--laca-fg`; link dùng `--laca-fg` gạch chân khi hover. `grep -nE "#2563eb|#10b981|#166534|#f0fdf4|--laca-accent"` trong G = 0.

### 2.7 Hướng dẫn + chi phí API (yêu cầu 4)
Cập nhật **cả** `P/doc/GOOGLE-SERVICES-GUIDE.md` **và** nguồn hướng dẫn trong UI (`G/GuideContent.php`), cùng nội dung:
- **Mục mới "3b. PageSpeed Insights API key (tuỳ chọn nhưng nên có)"**:
  1. Đăng nhập email khách, chọn đúng project (cùng project OAuth).
  2. Bật API: https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com → Bật.
  3. Tạo key: https://console.cloud.google.com/apis/credentials → Tạo thông tin xác thực → Khoá API.
  4. Bấm key → **Hạn chế API: chỉ PageSpeed Insights API**. Không đặt hạn chế IP/referrer (máy chủ hosting gọi từ IP có thể đổi → key bị từ chối).
  5. Dán vào Laca Admin → Dịch vụ Google → Search Console → ô "PageSpeed API Key" → Lưu. Về `index.php` → Kiểm tra PageSpeed.
- **Mục mới "5. Chi phí & hạn mức các API đang dùng"** — bảng:

| API | Dùng cho | Chi phí | Hạn mức mặc định (tham khảo, có thể đổi) | Site này dùng thế nào |
|---|---|---|---|---|
| Search Console API | Báo cáo GSC, Kiểm tra index | Miễn phí | Search Analytics: theo QPM/QPD mỗi site & project; URL Inspection: **2.000 lượt/ngày, 600/phút mỗi property** | Báo cáo cache 1 giờ; Kiểm tra Index chỉ khi bấm |
| Google Analytics Data API (GA4) | Báo cáo GA4, Realtime | Miễn phí (property thường) | Core: **200.000 token/property/ngày, 40.000/giờ**; đa số request < 10 token | Gom batch, cache 1 giờ; realtime chỉ khi tab đang mở |
| Google Analytics Admin API | Danh sách property | Miễn phí | Hạn mức riêng, dư dùng | Cache 12 giờ + nút Tải lại |
| PageSpeed Insights API | Tốc độ trang | Miễn phí, **không cần bật thanh toán** | Có key: ~**25.000 lượt/ngày** mỗi project; không key: dùng chung hạn mức Google, **thường hết → lỗi 429** | Chỉ chạy khi bấm, cache 24 giờ/strategy |
| OAuth 2.0 | Đăng nhập Google | Miễn phí | — | — |

  Ghi chú dưới bảng: **Không cần và không nên bật Billing** cho project này — mọi API trên miễn phí; vượt hạn mức chỉ bị từ chối tạm (429), không phát sinh tiền. Không dùng project này cho API trả phí (Maps, Places, Gemini…). Xem hạn mức thật tại https://console.cloud.google.com/apis/dashboard (chọn API → tab Hạn mức). Nguồn: https://developers.google.com/analytics/devguides/reporting/data/v1/quotas, https://developers.google.com/webmaster-tools/limits.
- Thêm vào bảng "Lỗi đã gặp": PSI "Quota exceeded … pagespeedonline" → chưa có key → mục 3b.

## 3. Nghiệm thu (Claude sẽ chạy lại)
1. Grep (0 dòng): `grep -n 'style="' G/views/partials/google-report.php G/views/partials/pagespeed-card.php | grep -v '/\* động \*/'`; `grep -rnE "#[0-9a-fA-F]{3,8}\b|--laca-accent" G/views`; `grep -rn "laca-seo&view=search-console\|798 mới" G`; `grep -n "style=" P/resources/scripts/admin/google-dashboard-report.js`.
2. `test-google-services.php` thêm (mock `pre_http_request` định dạng **thật** của Google, không chỉnh mock cho khớp parser):
   - Phễu: GA trả không có dòng Organic → html phễu có `0` + ghi chú, **không** chứa tổng người dùng ở vị trí đó.
   - Key metrics: mỗi ô render đúng số mock; ô 6 ẩn khi không có post type product (filter giả lập); ô 7/8 ẩn khi không có key event/add_to_cart; tắt ô ở option → ô biến mất.
   - `Chart::line` với chuỗi 28 điểm → SVG hợp lệ (parse bằng `DOMDocument`), đúng số `<title>`, không hex; chuỗi rỗng → chuỗi rỗng.
   - AJAX `laca_gsc_queries`: không nonce → `-1`; có nonce → request gửi Google có `rowLimit=100` và đúng range; html/JSON 100 dòng.
   - PSI: 429 → message tiếng Việt + link `#laca-psi-key`, gọi lần 2 trong 10 phút không phát request; `strategy=desktop` → request có `strategy=desktop`, cache key khác mobile.
   - Đếm request: tải báo cáo đầy đủ (range 28) ≤ 3 GA + ≤ 3 GSC.
3. `wp-dashboard-report-audit.js` (chỉ THÊM bước): ở mock — có ≥ 2 `svg[role="img"]` trong `#laca-google-report`; bấm "Xem tất cả" → dialog mở, có bảng ≥ 10 dòng, Esc đóng, focus về nút; bấm tab Máy tính ở PSI → footer có "Máy tính"; giả lập PSI lỗi (mock flag `laca_mock_psi_error=1`, chỉ local + manage_options) → trong khối PSI chỉ có **1** nút `[data-laca-psi-run]`; không màu xanh: mọi phần tử trong `#laca-google-report` có `color`/`background-color`/`border-color` với độ bão hoà HSL ≤ 10%.
4. `input-visibility-audit.js`, `autosave-audit.js`, `list-table-audit.js` fail=0; `yarn lint` 0 error; `yarn build` OK; `php -l` mọi file sửa.
5. Ảnh 1440 + 390 của `index.php?laca_mock_google=1` vào `/private/tmp/admin-ui/google-v2/`.
6. Commit `feat(google): bao cao v2 - bieu do, pheu dung, key metrics du, bang 100 tu khoa, PSI mobile/desktop, SCSS don sac, huong dan chi phi API` → push → `result` (NGUYÊN VĂN SUMMARY, số request đo được, đường dẫn ảnh).

## 4. Ràng buộc
Không xoá file, không cài package (biểu đồ tự vẽ SVG), không in secret/key ra HTML/log, không số bịa (thiếu → "—"), không cửa sau, Puppeteer có watchdog + `try/finally`, lệnh > 5 phút tự dừng. Không đổi `dataState`. Kẹt → gửi progress, đừng im.
