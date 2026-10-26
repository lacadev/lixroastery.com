# Plan — Dịch vụ Google: trang nhập GA/GSC trong Laca Admin, báo cáo GSC + GA đầy đủ trên Dashboard, ô nhập có viền

> Người thực hiện: Antigravity (code) → Claude review. P = `themes/lacadev-client`, C = `themes/lacadev-client-child`. Build trong C (`yarn build`, `yarn lint`).
> HEAD hiện tại: `865e337f`. Đơn sắc trắng/đen/xám (không xanh), token ở `P/resources/styles/admin/_tokens.scss`.

## 0. Người dùng yêu cầu (nguyên ý)
1. Khi nhập đủ key, **Google Search Console và Google Analytics hiển thị đầy đủ ở Dashboard**.
2. Phần nhập thông tin GA/GTM và GSC nằm ở **Laca Admin → Dịch vụ Google** (tab "Google Analytics & GTM", "Google Search Console") — hiện 2 tab này
   chỉ là link nhảy sang Laca SEO (`P/app/src/Settings/LacaAdmin/LacaAdminTabBar.php` dòng ~45–52).
3. Các ô input không nhìn thấy (không có viền) — ảnh: tab Google Analytics / GTM của Laca SEO, ô chỉ hiện khi đang focus.

## 1. Hiện trạng đã đo / đọc
- `P/doc/admin-ui/tools/input-visibility-audit.js` (Claude viết, KHÔNG sửa logic kiểm): `SUMMARY pass=5 fail=8` — mọi ô text của Laca SEO (`general` các tab,
  `titles`, `sitemap`, `search-console` client_id/secret, ô "Tìm kiếm cài đặt nhanh") không có viền khi chưa focus; 2 trang mới chưa tồn tại.
- GA/GTM: field `ga4_measurement_id`, `gtm_container_id`, `ga_anonymize_ip`, `ga_exclude_loggedin` trong `P/app/src/Features/LacaSeo/Fields/GeneralFields.php`
  (~dòng 512–545), lưu option `laca_seo_general`; xuất mã ở `P/app/src/Features/LacaSeo/Frontend/Tracking.php`.
- Người dùng ĐANG chèn GA `G-XSYBCQ8DZC` thủ công qua **Laca Theme → Scripts → Header Script** (production đã có 1 lần trong `<head>`). Nếu nhập thêm ID vào
  Laca SEO → GA bị đếm 2 lần.
- GSC: OAuth `P/app/src/Features/LacaSeo/Modules/SearchConsole/GscOAuth.php` (scope chỉ `webmasters.readonly`, redirect URI
  `admin.php?page=laca-seo&view=search-console&laca_gsc_oauth=1` — **đã khai báo trong Google Cloud của người dùng, KHÔNG được đổi URI**),
  `GscClient.php`, view `P/app/src/Features/LacaSeo/Admin/views/search-console.php`.
- Production GSC đã kết nối nhưng hiện **toàn 0** (ảnh). Lý do khả dĩ đọc từ code:
  (a) `siteUrl = home_url('/')` cứng = `https://lixroastery.com/`; nếu property trong GSC là **Domain property** (`sc-domain:lixroastery.com`) hoặc khác
  `www`/`http` → API trả lỗi 403/404; (b) `getPerformanceSummary()` **không kiểm HTTP status** — body lỗi bị parse thành 0 và **cache 12 giờ**;
  (c) dữ liệu GSC trễ 2–3 ngày, site mới cutover.
- Chart.js `^4.5.1` đã có trong `C/package.json` (đang dùng ở `P/resources/scripts/admin/project-charts.js`) — dùng lại, không cài thêm.

## 2. Phạm vi sửa

### 2.1 Ô nhập có viền (mọi trang admin)
- Text/url/email/number/password, select, textarea: nền `#ffffff`, viền `1px solid #d4d4d8`, radius 4px, cao 36px; hover viền `#a1a1aa`;
  focus viền `#18181b` + `outline: 2px solid #71717a; outline-offset: 2px`; disabled nền `#f4f4f5`. Ô tìm kiếm nhanh của Laca SEO cũng có viền.
- Sửa ở skin/`laca-seo/index.scss` — tìm rule đang đặt `border: 0`/`none`/`transparent` cho input (đặc biệt `.laca-seo-field-row input`, `.laca-seo-search`) và gỡ.
- Ô dài (ID, key) không kéo hết chiều ngang: `max-width: 560px` trong lưới field.

### 2.2 Laca Admin → Dịch vụ Google: 2 trang thật
- Đăng ký 2 trang con (cùng cách các trang `laca-recaptcha`, `laca-login-socials` đang đăng ký — đọc `P/app/src/Settings/AdminSettings.php` + `LacaAdminMenuOrganizer.php`
  để làm giống, capability `manage_options`, nằm trong nhóm "Dịch vụ Google" của dock):
  - `admin.php?page=laca-google-analytics` — tab "Google Analytics & GTM".
  - `admin.php?page=laca-google-search-console` — tab "Google Search Console".
- `LacaAdminTabBar::TAB_GROUPS['google']`: thêm 2 slug vào `pages`, đổi `url` 2 tab sang 2 trang mới (không còn nhảy sang Laca SEO).
- **Một nguồn dữ liệu**: KHÔNG tạo option mới. Trang GA đọc/ghi đúng 4 key cũ trong `laca_seo_general` (render bằng Renderer/Field registry của Laca SEO,
  dùng cùng handler lưu + nonce + `manage_options`, cùng bộ tự lưu `laca-autosave.js` — chỉ lưu khi có thay đổi thật).
  Tab "Google Analytics / GTM" trong Laca SEO → Cài đặt chung thay bằng 1 dòng: "Đã chuyển sang Laca Admin → Dịch vụ Google" + link (không render form trùng).
- Trang GSC: chuyển phần kết nối (Client ID/Secret, nút Kết nối/Ngắt kết nối, hướng dẫn chuẩn bị Google Cloud) sang trang mới (tách partial dùng chung,
  không copy-paste). Trang `laca-seo&view=search-console` giữ lại phần **báo cáo** + link "Cài đặt kết nối" sang trang mới.
  **Redirect URI giữ nguyên** `…page=laca-seo&view=search-console&laca_gsc_oauth=1`: callback vẫn xử lý ở đó (verify state như cũ), xong thì
  `wp_safe_redirect` về `admin.php?page=laca-google-search-console&connected=1` (hoặc `&error=…`).
- **Chống đếm GA 2 lần**: trên trang GA, nếu Header/Footer Script của Laca Theme (đọc option Carbon chứa header script — tìm key trong `C/theme/setup/theme-options.php`)
  chứa `gtag/js?id=G-…` hoặc `GTM-…` → hiện Alert: "Đang có mã G-XXXX chèn thủ công ở Laca Theme → Scripts". `Tracking.php`: nếu ID nhập ở đây trùng ID đã có
  trong header script thì **không xuất thêm** (ghi chú lý do trong code). Không tự sửa header script của người dùng.

### 2.3 Kết nối Google một lần cho cả GSC + GA (OAuth)
- Thêm scope `https://www.googleapis.com/auth/analytics.readonly` vào cùng OAuth client (`GscOAuth::SCOPE` → mảng scope, `include_granted_scopes=true`,
  `prompt=consent` khi thiếu scope). Token cũ chỉ có scope GSC → trang GA hiện nút "Cấp thêm quyền Google Analytics" (đi lại luồng OAuth, cùng redirect URI).
  Lưu danh sách scope đã cấp (từ response token `scope`) để biết thiếu gì.
- Khối "Chuẩn bị Google Cloud" (có sẵn ở GSC) bổ sung: bật **Google Analytics Data API** và **Google Analytics Admin API**; app đang Testing thì email phải
  nằm trong Test users.
- GA4 property: Measurement ID (`G-…`) ≠ Property ID (số). Sau khi có scope GA: gọi Admin API `accountSummaries.list` + `properties/{id}/dataStreams` để
  **tự tìm property có web stream measurementId = ID đã nhập** (hoặc ID tìm thấy trong header script); không thấy thì cho chọn từ danh sách property.
  Lưu `ga4_property_id` (key mới trong `laca_seo_general`, field ẩn/chỉ đọc + nút "Chọn lại").
- GSC property: gọi `GET https://www.googleapis.com/webmasters/v3/sites` → tự chọn property khớp site theo thứ tự `sc-domain:<host không www>` →
  `https://<host>/` → `https://www.<host>/` → `http://…`; lưu `gsc_site_url` (key mới), cho đổi bằng select. Thay mọi `home_url('/')` dùng làm siteUrl bằng giá trị này.

### 2.4 Báo cáo đầy đủ trên Dashboard
Vị trí: **Laca SEO → Bảng điều khiển** (`P/app/src/Features/LacaSeo/Admin/views/dashboard.php`) có 2 khu vực lớn; **WP Dashboard** (`index.php`) có 1 widget gọn
(`P/app/src/Features/LacaSeo/Modules/DashboardWidget/DashboardWidget.php`) — 4 số chính mỗi dịch vụ + link "Xem báo cáo đầy đủ".
Chỉ hiển thị khu vực khi dịch vụ đã cấu hình đủ; thiếu thì Card trạng thái rỗng 1 câu + nút "Thiết lập" → trang Dịch vụ Google tương ứng.
- Bộ chọn khoảng thời gian chung: 7 / 28 / 90 ngày (mặc định 28) + so sánh kỳ trước (▲/▼ % cạnh mỗi số).
- **Google Search Console**: 4 số (Lượt click, Lượt hiển thị, CTR TB, Vị trí TB); biểu đồ đường theo ngày (click + hiển thị, 2 trục); bảng Top 10 truy vấn
  (query, click, hiển thị, CTR, vị trí) và Top 10 trang; chia theo thiết bị; trạng thái sitemap (đã gửi, lỗi, số URL); dòng "Dữ liệu đến ngày dd/mm (GSC trễ 2–3 ngày)".
- **Google Analytics 4** (Data API `runReport`): 4–6 số (Người dùng, Người dùng mới, Phiên, Lượt xem trang `screenPageViews`, Thời gian tương tác TB,
  Tỉ lệ tương tác); biểu đồ đường người dùng/phiên theo ngày; Top 10 trang (đường dẫn + tiêu đề, lượt xem); Nguồn lưu lượng
  (`sessionDefaultChannelGroup`); Quốc gia/thành phố top 5; Thiết bị; **Người dùng đang hoạt động (realtime, `runRealtimeReport`)**, tự cập nhật 60s khi tab đang mở.
- Tải dữ liệu bằng **AJAX sau khi trang hiển thị** (khung skeleton trước) — trang Dashboard không chờ Google. Endpoint `wp_ajax_laca_google_report`
  (nonce + `manage_options`, tham số `service`, `range`).
- Cache transient theo (service, property, range): 1 giờ (realtime 60 giây). Nút "Làm mới" bỏ qua cache. **Không cache khi lỗi.**
- **Lỗi phải hiện đúng, không hiện 0**: kiểm HTTP status mọi lời gọi Google; lỗi → Alert nêu mã + thông điệp tiếng Việt (vd 403 "Tài khoản chưa có quyền với
  property này / chưa bật API", 404 "Không tìm thấy property — chọn lại", token hết hạn → "Kết nối lại"). Không có dữ liệu thật → trạng thái rỗng
  "Chưa có dữ liệu trong khoảng này", không phải bảng toàn 0.
- Biểu đồ Chart.js đơn sắc (đường `#18181b`, đường phụ `#a1a1aa`, lưới `#f4f4f5`), không màu xanh. Không có số thì không vẽ biểu đồ.
- Bố cục theo bố cục Card hiện có của Laca SEO; ở 1024px 4 số xếp 2×2, 390px 1 cột; bảng bọc khung cuộn ngang riêng (không cuộn ngang cả trang).

### 2.5 Xem trước trước khi áp dụng bố cục Dashboard (quy trình người dùng chốt)
Trước khi dựng thật phần 2.4, dựng **2–3 phương án bố cục** Dashboard dạng tĩnh (dữ liệu mẫu) trên hệ preview hiện có (nếu đã gỡ preview switcher ở
`a8b72f6f` thì tạo trang tĩnh ẩn `admin.php?page=laca-dashboard-preview` chỉ `manage_options`), ví dụ:
A) GSC và GA xếp dọc, mỗi khu 4 số + biểu đồ + 2 bảng; B) 2 cột song song (GSC trái, GA phải) + bảng gọn; C) Tab "Tìm kiếm | Truy cập" chung một khung.
Gửi link cho người dùng trong chat của bạn, DỪNG chờ chọn rồi mới dựng phương án được chọn. Các phần 2.1–2.3 làm trước, không cần chờ.

## 3. Nghiệm thu (Claude sẽ tự chạy lại để review)
1. `node P/doc/admin-ui/tools/input-visibility-audit.js` → `SUMMARY fail=0` (2 trang mới phải mở được).
2. `node P/doc/admin-ui/tools/autosave-audit.js` thêm 2 trang mới bằng tham số? — script này chưa nhận tham số: chạy như cũ phải fail=0; tự kiểm thêm
   trang GA mới bằng tay (gõ không lưu, rời ô mới lưu) và ghi kết quả.
3. Test PHP CLI `P/doc/admin-ui/tools/test-google-services.php` (viết mới, KHÔNG gọi Google thật — mock bằng filter `pre_http_request`):
   - chọn GSC property: danh sách `[sc-domain:lixroastery.com, https://lixroastery.com/]` → chọn `sc-domain:lixroastery.com`; chỉ có `https://www.lixroastery.com/` → chọn nó.
   - response 403 → kết quả `WP_Error` (không phải mảng số 0) và KHÔNG set transient.
   - response GSC/GA mẫu hợp lệ → parse đúng tổng/CTR/vị trí, GA đúng users/sessions/pageviews, top pages.
   - tìm property GA theo measurementId từ dataStreams mẫu.
   - header script chứa `G-XSYBCQ8DZC` + ID nhập trùng → `Tracking` không xuất thêm (render vào buffer, đếm số `gtag/js` = 0 từ Tracking).
   In `PASS|FAIL` + `SUMMARY`. **Không test giả** (không hằng true, không chỉ kiểm hàm tồn tại).
4. OAuth: redirect URI tạo ra **giống hệt** chuỗi cũ (assert trong test 3).
5. `list-table-audit.js`, `admin-ui-audit.js`, `ui-probe.js` vẫn fail=0; `compare-head-full.php` `BAD URLS: 2`; `yarn lint` 0 error; `yarn build` OK; `php -l`.
6. Ảnh: 2 trang Dịch vụ Google mới, Dashboard (dữ liệu mock qua filter khi `wp_get_environment_type()==='local'` + tham số `?laca_mock_google=1`, chỉ `manage_options`)
   ở 1440/1024/390 → `/private/tmp/admin-ui/google/`.

## 4. Ràng buộc
Không lưu token/secret ra log/HTML (secret đã mã hoá bằng `GscCrypto` — giữ). Không cài package. Không xoá file. Không đổi DB ngoài thao tác lưu cài đặt qua UI
khi test (trả lại như cũ). Không cửa sau đăng nhập; script Puppeteer có watchdog + `try/finally`; lệnh > 5 phút → tự dừng.
Commit theo phần: (1) `fix(admin): ô nhập có viền` (2) `feat(admin): trang Dịch vụ Google GA/GSC trong Laca Admin, chống đếm GA trùng` (3) `feat(laca-seo):
báo cáo GSC + GA4 trên Dashboard` (sau khi người dùng chọn bố cục). Push sau mỗi commit đạt §3. Gửi `progress` sau mỗi commit, `result` cuối qua agent-bridge
(NGUYÊN VĂN SUMMARY, file sửa, link preview).
