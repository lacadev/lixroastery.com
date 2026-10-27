# Plan — Nâng Dịch vụ Google (Laca Admin) theo Google Site Kit 1.190.0

> Người thực hiện: Antigravity → Claude review. HEAD `b8a285b5`. P = `themes/lacadev-client`. Module: `P/app/src/Features/GoogleServices/`, trang
> `P/app/src/Settings/LacaAdmin/GoogleServicesPage.php`, partial `GoogleServices/views/partials/google-report.php`, JS `P/resources/scripts/admin/google-dashboard-report.js`.
> Tham chiếu: plugin `app/public/wp-content/plugins/google-site-kit` (đang bật ở local, KHÔNG có trong git/production). Giấy phép Apache-2.0.

## 0. Người dùng chốt
1. Học theo Site Kit để hoàn thiện Dịch vụ Google trong Laca Admin ("clone" = tái hiện **tính năng + cách lấy dữ liệu**, không bê nguyên app React).
2. Khối báo cáo ở `wp-admin/index.php` **chỉ hiển thị khi đã thiết lập Google xong**. Chưa thiết lập → **không hiển thị gì** (không card "Thiết lập" ở index.php;
   lời mời thiết lập chỉ nằm ở Laca Admin → Dịch vụ Google).

## 1. Nguyên tắc
- PHP + Vanilla JS + Chart.js (đã có trong `C/package.json`). Không cài package, không React, không phụ thuộc plugin Site Kit.
- **Không copy code Site Kit**, chỉ viết lại theo hành vi. Nếu buộc phải chép một đoạn (vd regex nhận diện tag), đặt file `GoogleServices/NOTICE-site-kit.md`
  ghi nguồn + giấy phép Apache-2.0 + mô tả đã sửa gì, và comment trỏ tới NOTICE tại chỗ dùng.
- Mọi số hiển thị lấy từ API; thiếu dữ liệu thì `—`. Đơn sắc. Thay đổi bố cục mới → làm preview, gửi link, chờ người dùng chọn (mục 4.0).
- Tham chiếu hành vi Site Kit (đã khảo sát, xem file:dòng trong plugin):
  - Quyền: `includes/Core/Permissions/Permissions.php:153-167, 337-352`.
  - Widget WP Dashboard: `includes/Core/Admin/Dashboard.php:105-200`.
  - GA4 request helpers: `includes/Modules/Analytics_4/Report/RequestHelpers.php:67-81, 344-410`.
  - Data available: `includes/Core/Modules/Module_With_Data_Available_State_Trait.php:29-63`.
  - GSC: `includes/Modules/Search_Console/Datapoints/Search_Analytics_Trait.php:60-164`.
  - PageSpeed: `includes/Modules/PageSpeed_Insights.php:79-139`.
  - Tag: `includes/Modules/Analytics_4/Tag_Guard.php`, `Tag_Matchers.php`, `includes/Core/Tags/GTag.php:178-222`, `Analytics_4.php:1187-1244`.
  - WooCommerce events: `includes/Core/Conversion_Tracking/Conversion_Event_Providers/WooCommerce.php`.

## 2. G1 — Trạng thái thiết lập + điều kiện hiển thị (làm trước, commit riêng)
- `GoogleServices::setupState()` trả từng dịch vụ:
  - `gsc`: OAuth có scope webmasters + `gsc_site_url` đã lưu + lần gọi API gần nhất OK (không phải 401/403/404).
  - `ga`: OAuth có scope analytics + `ga4_property_id` đã lưu + `ga4_measurement_id` thuộc web stream của property đó (dữ liệu dataStreams đã có).
  - Kết quả cho mỗi dịch vụ: `complete | incomplete(<lý do>) | error(<mã>)`.
- index.php: render `#laca-google-report` **chỉ khi ít nhất 1 dịch vụ `complete`** và user có quyền xem (mục 2.1). Chỉ hiện cột/khu của dịch vụ `complete`.
  Dịch vụ còn lại ẩn hoàn toàn (không card thiết lập). Có `complete` nhưng API lỗi tạm thời → hiện lỗi trong card (giữ hành vi hiện tại).
- Widget nhỏ "Tổng quan Laca SEO" không bị ảnh hưởng.
- Laca Admin → Dịch vụ Google thêm **khối "Trạng thái thiết lập"** dạng stepper 4 bước, mỗi bước ✓ / ! + nút hành động:
  1. Kết nối Google (email).
  2. Property Search Console.
  3. Property GA4 + mã đo lường khớp.
  4. Dữ liệu (đang thu thập / đã có).
- 2.1 Quyền xem báo cáo: mặc định `manage_options`; filter `laca/google/report_capability` (site nào muốn cho Editor xem thì đổi ở child). Thiết lập luôn cần `manage_options`.

## 3. G2 — Tầng dữ liệu theo cách Site Kit
1. **Khoảng ngày chung**: 7 / 14 / 28 / 90 ngày (mặc định 28), **kết thúc hôm qua**, kỳ so sánh = cùng số ngày liền trước. Dùng chung cho GSC + GA4 (1 hàm `DateRange::get($range)`).
   Lưu lựa chọn theo user (user meta `laca_google_range`, chỉ là tuỳ chọn hiển thị).
2. **GA4 request helper** (1 chỗ dựng body):
   - `metricAggregations: ["TOTAL"]` để lấy tổng chuẩn.
   - `keepEmptyRows: true` cho chuỗi theo ngày.
   - **`dimensionFilter hostName IN [domain, www.domain]`** cho mọi report, để không lẫn host khác — bài học vụ 43 Factory.
   - Không đặt `name` cho dateRanges.
3. **Gộp request**: dùng `properties/{id}:batchRunReports` (tối đa 5 report/lần) cho KPI + chuỗi ngày + top trang + kênh + thiết bị. Mục tiêu ≤ 3 request GA + ≤ 3 request GSC mỗi lần tải báo cáo
   (đo và ghi vào báo cáo).
4. **GSC**: `dataState: all`. Thêm filter `page contains <home_url host>` khi property là `sc-domain:` (tránh lẫn subdomain). Tổng lấy từ request không dimension (đã làm).
5. **Trạng thái dữ liệu** (giống Site Kit):
   - Sample report (GA4: `totalUsers × date` trên kỳ so sánh + kỳ hiện tại; GSC: dimension `date`).
   - Không có dòng nào → "Đang thu thập dữ liệu (Google cần 24–48 giờ)". Có dòng nhưng tổng = 0 → "Chưa có lượt truy cập trong khoảng này".
   - Đã có dữ liệu thì lưu option `laca_google_data_available_{gsc|ga}` để lần sau khỏi kiểm.
6. **Cache**:
   - Report: transient 1 giờ, key theo (service, property, range, loại report).
   - Lỗi 429: cache 10 phút, hiện "Google đang giới hạn truy vấn, thử lại sau ít phút". Lỗi khác không cache.
   - Nút Làm mới bỏ qua cache.

## 4. G3 — Dashboard index.php (giữ bố cục 2 cột B đã chọn, bổ sung theo Site Kit)
4.0 **Preview trước khi dựng**: trang ẩn `admin.php?page=laca-google-report-preview` (local, `manage_options`, dữ liệu mock rõ nhãn "DỮ LIỆU MẪU"), 2 phương án đặt các khối mới:
   - **A**: thêm một hàng "Chỉ số chính" (Key metrics) phía trên 2 cột.
   - **B**: Key metrics thành tab thứ 3 bên cạnh GSC/GA.
   Gửi link cho người dùng trong chat của bạn, DỪNG chờ chọn. Các phần G1, G2, G4, G5 làm song song, không chờ.

Nội dung (mỗi số có delta so kỳ trước, biểu đồ đơn sắc: kỳ hiện tại nét liền `#18181b`, kỳ trước nét đứt `#a1a1aa`):
- **Search funnel** (Site Kit `searchFunnelGA4`):
  - GSC Lượt hiển thị → Lượt click.
  - GA4 người dùng từ Tìm kiếm (`totalUsers` lọc `sessionDefaultChannelGroup = "Organic Search"`).
  - Kèm tỉ lệ tương tác của nhóm đó.
  - Biểu đồ click + hiển thị theo ngày.
- **GA4 tổng quan**:
  - Số liệu: `totalUsers`, `sessions`, `screenPageViews`, `engagementRate`, `averageSessionDuration`.
  - Biểu đồ `totalUsers × date`.
  - Ô "Đang hoạt động (30 phút)" (đã có).
- **Trang phổ biến** (top 10): `pagePath + pageTitle` với `screenPageViews`, `sessions`, `engagementRate`, `averageSessionDuration`. Bấm vào mở trang.
- **Từ khoá phổ biến** (top 10): `query` với clicks, impressions, ctr, position; nút "Xem tất cả" mở bảng 100 dòng (modal/trang con).
- **Key metrics cho shop** (bộ cố định, bật/tắt từng ô ở trang GA; mặc định bật 6 ô đầu):
  1. Khách mới / quay lại: `activeUsers × newVsReturning`.
  2. Nguồn truy cập hàng đầu: `totalUsers × sessionDefaultChannelGroup`.
  3. Thành phố hàng đầu: `totalUsers × city`, top 3.
  4. Số trang mỗi lượt: `screenPageViewsPerSession`.
  5. Thời lượng lượt truy cập: `averageSessionDuration`.
  6. Sản phẩm được xem nhiều: `screenPageViews × pagePath` lọc `pagePath BEGINS_WITH /product/` + `/en/product/`; chỉ khi có post type `product`.
  7. Nguồn chuyển đổi tốt nhất: `sessionKeyEventRate × channel`; chỉ khi property có key event.
  8. Nguồn dẫn tới Thêm giỏ / Mua: `eventCount` lọc `eventName = add_to_cart | purchase` × channel; chỉ khi đã phát hiện event trong 90 ngày.
  - Ô thiếu điều kiện thì ẩn, kèm 1 dòng gợi ý ở trang cài đặt (không ô rỗng trên dashboard).
- **PageSpeed / Web Vitals** (G4).

## 5. G4 — PageSpeed Insights
- Gọi `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=<home>&strategy=mobile|desktop&locale=vi`, không cần OAuth.
- Ô tuỳ chọn "PageSpeed API key" trên trang Dịch vụ Google: không bắt buộc, tăng quota; lưu mã hoá giống secret, không in ra HTML.
- Hiển thị:
  - Field data (CrUX, `loadingExperience`): LCP, INP, CLS kèm nhãn Tốt / Cần cải thiện / Kém.
  - Lab (Lighthouse): LCP, CLS, TBT + điểm Performance.
  - Tab Mobile / Desktop.
- Cache 24 giờ, nút Kiểm tra lại. Chạy AJAX vì PSI mất 10–30s. Có trạng thái đang đo. Timeout 60s.

## 6. G5 — Thống kê từng trang (entity)
- **Metabox** trong trình soạn bài/trang/sản phẩm/Journal (sidebar, đóng mặc định, chỉ khi đã setup): "Hiệu suất trang — 28 ngày".
  - GSC: clicks, impressions, CTR, vị trí; top 5 từ khoá của URL (filter `page equals <permalink>`).
  - GA4: lượt xem, người dùng, thời gian tương tác (filter `hostName IN …` + `pagePath = <path>`).
  - Tải AJAX khi mở metabox, cache 1 giờ theo URL. Bài chưa publish → ẩn.
- **Admin bar** ở frontend cho user có quyền xem báo cáo: mục "Google: 28 ngày" → dropdown 4 số (click, hiển thị, lượt xem, người dùng) cho trang hiện tại.
  - Tải AJAX khi hover/click, không chặn render.
  - Không xuất gì cho khách và không xuất khi chưa setup.
- **Cột "Kiểm tra Index"** ở danh sách bài giữ nguyên.

## 7. G6 — Tracking theo Site Kit
1. **Tag guard**: không in gtag ở trang 404 (tuỳ chọn, mặc định bật), preview/customizer, và request có `?laca_tagverify=1`.
2. **Loại trừ người đăng nhập**: thay việc không in tag bằng `window['ga-disable-<G-ID>'] = true` (giống Site Kit) — có 2 tuỳ chọn "Quản trị viên" (mặc định) / "Mọi người đã đăng nhập".
3. **Phát hiện tag trùng** (nút "Kiểm tra mã trên website" ở trang GA):
   - Fetch `home_url('/?laca_tagverify=1')` bằng `wp_remote_get`, regex tìm mọi `G-…`, `GT-…`, `GTM-…`, `UA-…`.
   - Liệt kê ID ngoài ID đang cấu hình kèm cảnh báo (vd do plugin khác hoặc Laca Theme → Scripts).
   - Lưu ý: plugin Site Kit đang bật ở local có thể tự chèn tag → kết quả kiểm sẽ chỉ ra.
4. **Sự kiện WooCommerce** (công tắc "Gửi sự kiện thương mại", mặc định tắt):
   - `add_to_cart` (AJAX + nút) và `purchase` ở trang cảm ơn: `transaction_id`, `value`, `currency`, `items`.
   - Chống bắn lại khi reload trang cảm ơn (order meta cờ đã gửi).
   - Chỉ bắn khi GA tag đang được in (không bị loại trừ).
5. **Consent mode**: bỏ qua đợt này (ghi chú lý do: khách VN, chưa có banner cookie).

## 8. Bỏ qua (ghi rõ trong báo cáo)
- Dashboard sharing / view-only theo role (thay bằng filter capability ở 2.1).
- Audience segmentation, custom dimensions, AdSense, Ads, Reader Revenue, Sign in with Google, Tag Manager setup, benchmarking, user input questionnaire.
- Email report: để đợt sau.

## 9. Nghiệm thu (Claude sẽ chạy lại)
1. `test-google-services.php` thêm test (mock raw response đúng format Google, không dựng mảng đã parse):
   - **setupState**: chưa OAuth / thiếu property / measurement không khớp stream / đủ → kết quả đúng.
   - **index.php**: chưa setup → HTML không có `#laca-google-report`. Chỉ GSC complete → chỉ có khu GSC.
   - **Body GA4**: có `metricAggregations` và `hostName` filter; `keepEmptyRows` cho report theo ngày; batchRunReports gom ≤ 5 report.
   - **Trạng thái dữ liệu**: không dòng → "Đang thu thập"; dòng toàn 0 → "Chưa có lượt truy cập"; lưu cờ data_available.
   - **Cache**: 429 cache 10 phút; 403 không cache.
   - **Key metrics**: ô 6 ẩn khi không có post type product; ô 7–8 ẩn khi không có key event / event.
   - **PSI**: parse field + lab từ response mẫu; API key không xuất hiện trong HTML.
   - **Entity**: filter `page equals` và `pagePath` đúng URL; metabox ẩn khi bài chưa publish.
   - **Tracking**: `?laca_tagverify=1` không in tag; user loại trừ nhận `ga-disable-<ID>`; trang 404 không in tag khi bật; purchase không bắn 2 lần.
   - **Đếm request API** mỗi lần tải báo cáo: GA ≤ 3, GSC ≤ 3.
2. `wp-dashboard-report-audit.js` cập nhật kỳ vọng mới (chưa setup → không có khối; **chỉ sửa kỳ vọng theo yêu cầu mục 0.2, không nới logic khác**) + puppeteer metabox + admin bar
   (mock local). `input-visibility-audit`, `autosave-audit`, `list-table-audit`, `head-snapshot` fail=0. `yarn lint` 0 error, `yarn build` OK, `php -l`.
3. Ảnh index.php, trang Dịch vụ Google (stepper), metabox, admin bar ở 1440/390 → `/private/tmp/admin-ui/sitekit/`.
4. Commit theo giai đoạn G1 → G6, push sau mỗi giai đoạn đạt. G3 phần bố cục chỉ dựng sau khi người dùng chọn A/B.
   Gửi `progress` mỗi giai đoạn, `result` cuối (NGUYÊN VĂN SUMMARY, số request đo được, danh sách bỏ qua).

## 10. Ràng buộc
Không cài package/plugin, không sửa `plugins/` (kể cả Site Kit — chỉ đọc), không xoá file, không ghi DB ngoài thao tác lưu cài đặt khi test (trả lại), không in token/secret/API key ra HTML/log,
không cửa sau, script có watchdog, lệnh > 5 phút tự dừng. Gợi ý cho người dùng (không tự làm): sau khi xong, **tắt plugin Site Kit ở local** để tránh chèn tag/báo cáo trùng.
