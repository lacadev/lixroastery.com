# Plan — Gom Google Search Console + Google Analytics về Laca Admin, gỡ khỏi Laca SEO, chuẩn bị chuyển sang tài khoản Google của khách

> Người thực hiện: Antigravity → Claude review. HEAD `e935cad8`. P = `themes/lacadev-client`. Gộp luôn task đang làm `d0113cc7`
> (hiện email tài khoản đang kết nối, `prompt=consent select_account`, form GA render đúng field-row) vào plan này.

## 0. Người dùng chốt
1. Cấu hình + trang Google Search Console và Google Analytics **chỉ ở Laca Admin → Dịch vụ Google**. **Gỡ khỏi Laca SEO** (menu "Search Console", tab
   "Google Analytics / GTM" trong Cài đặt chung, báo cáo Google trong Bảng điều khiển Laca SEO, code module Google trong namespace LacaSeo).
2. Chuyển toàn bộ dịch vụ Google sang **email của khách** (`xliiicoffeeeverywhere@gmail.com`), không dùng `mooms.dev@gmail.com` nữa. Phần chuyển quyền
   trên Google (GSC/GA/Cloud) do người dùng tự làm — code phải hỗ trợ: OAuth client mới, redirect URI mới, chọn đúng tài khoản, hiện email đang kết nối.
3. Báo cáo GSC + GA4 vẫn hiện ở đầu `wp-admin/index.php` (đã làm ở `e935cad8`).

## 1. Hiện trạng code Google nằm trong Laca SEO (Claude đã grep)
`P/app/src/Features/LacaSeo/`: `Modules/SearchConsole/{GscOAuth,GscClient,GscCrypto}.php`, `Modules/GoogleAnalytics/GaClient.php`, `Admin/GoogleDashboardService.php`,
`Admin/views/search-console.php`, `Admin/views/partials/google-report.php`, phần báo cáo trong `Admin/views/dashboard.php`, mục menu `search-console` trong
`Admin/AdminPage.php` (~dòng 174, 285), field GA trong `Fields/GeneralFields.php` (tab `analytics`), `Frontend/Tracking.php` (đăng ký trong `LacaSeo.php` dòng ~92 —
**chỉ chạy khi Rank Math tắt hoặc có force flag**), tham chiếu ở `Core/Modules.php`, `Guide/GuideData.php`, `ImportExport/Importer.php`.
Dùng GSC từ tính năng SEO (giữ tính năng, đổi nơi gọi): `Admin/ListScreens/PostList.php` (row action "Kiểm tra Index"), `Modules/AdminBar/AdminBarMenu.php` (gsc-inspect).
Laca Admin: `P/app/src/Settings/LacaAdmin/GoogleServicesPage.php`, `LacaAdminTabBar.php`, `LacaAdminMenuOrganizer.php`, `ReleaseChecklist.php`.
Dữ liệu: option `laca_seo_gsc` (credentials mã hoá), key trong `laca_seo_general`: `ga4_measurement_id, gtm_container_id, ga_anonymize_ip, ga_exclude_loggedin,
ga4_goal_events, ga4_property_id, gsc_site_url`.

## 2. Việc cần làm

### 2.1 Tách module Google ra khỏi Laca SEO
- Namespace mới `App\Features\GoogleServices\` tại `P/app/src/Features/GoogleServices/`: chuyển (git mv — được phép di chuyển vì người dùng yêu cầu gỡ khỏi Laca SEO)
  `GscOAuth`, `GscClient`, `GscCrypto`, `GaClient`, `GoogleDashboardService`, `Tracking`, partial `google-report.php`, view báo cáo GSC chi tiết.
  Một class `GoogleServices::boot()` đăng ký: trang Laca Admin (`GoogleServicesPage` có thể giữ ở `Settings/LacaAdmin` hoặc chuyển vào đây — chọn 1, ghi rõ),
  AJAX `laca_google_report`, khối báo cáo `index.php`, `Tracking` (frontend). Boot từ `P/app/hooks.php` hoặc nơi khởi tạo Laca Admin — **không phụ thuộc Laca SEO
  có bật hay Rank Math có active** (mã GA phải xuất được độc lập).
- **Dữ liệu riêng**: option mới `laca_google_services` (mảng: `ga4_measurement_id, gtm_container_id, ga_anonymize_ip, ga_exclude_loggedin, ga4_goal_events, ga4_property_id,
  gsc_site_url`) + `laca_google_oauth` (credentials mã hoá, thay `laca_seo_gsc`). Migration chạy 1 lần (`admin_init`, cờ `laca_google_services_migrated`): COPY giá trị từ
  `laca_seo_general`/`laca_seo_gsc` sang option mới; **không xoá** option cũ (để rollback). Form + lưu dùng option mới (nonce + `manage_options`, tự lưu chỉ khi đổi).
- Laca SEO gỡ: mục menu "Search Console" và `case 'search-console'` trong `AdminPage.php`; tab `analytics` trong `GeneralFields.php` (bỏ field); báo cáo Google trong
  `views/dashboard.php` (thay 1 Card nhỏ "Báo cáo Google đã chuyển sang Laca Admin → Dịch vụ Google" + link — hoặc bỏ hẳn, chọn cái gọn hơn); `Tracking` khỏi `LacaSeo.php`;
  cập nhật `Core/Modules.php`, `GuideData.php`, `Importer.php` (import Rank Math GA/GSC → ghi vào option mới); `PostList.php`/`AdminBarMenu.php` gọi class mới
  (`App\Features\GoogleServices\GscClient::inspectUrl`), ẩn nút nếu chưa kết nối GSC. Không để file rỗng/code chết: file view cũ không còn dùng thì để nội dung
  `<?php // moved to Features/GoogleServices` — KHÔNG xoá file (quy tắc dự án), liệt kê để người dùng tự xoá.
- URL cũ `admin.php?page=laca-seo&view=search-console` (không có `laca_gsc_oauth`) → `wp_safe_redirect` sang `admin.php?page=laca-google-search-console`.

### 2.2 OAuth cho tài khoản khách
- **Redirect URI mới** (thuộc Laca Admin): `admin.php?page=laca-google-search-console&laca_google_oauth=1`. Callback xử lý ở `admin_init` cho **cả URI mới và URI cũ**
  (`page=laca-seo&view=search-console&laca_gsc_oauth=1`) để không gãy kết nối cũ trong lúc chuyển. Trang hiển thị URI mới để copy (+ ghi chú "URI cũ vẫn chạy tạm thời").
- Scope: `webmasters.readonly`, `analytics.readonly`, `openid`, `email`. Tham số `prompt=consent select_account`, `access_type=offline`, `include_granted_scopes=true`.
- Lấy email từ `id_token` (decode payload) → lưu trong credentials mã hoá; hiện "Đã kết nối: <email>" + nút "Đổi tài khoản Google" (= ngắt + kết nối lại).
  Thông báo lỗi 403 / danh sách property rỗng nêu email + hướng dẫn (thêm email vào Search Console → Cài đặt → Người dùng và quyền, hoặc đổi tài khoản).
- Khối "Chuẩn bị Google Cloud" cập nhật cho người mới: tạo project dưới tài khoản khách, bật 3 API (Search Console API, Analytics Data API, Analytics Admin API),
  OAuth consent External (Testing → thêm email khách vào Test users, hoặc Publish), Client Web application + redirect URI mới.
- Đổi Client ID/Secret (OAuth client mới) → tự xoá token cũ (token thuộc client cũ không dùng được nữa), yêu cầu kết nối lại.

### 2.3 Trang GA (từ task d0113cc7)
Form "Cấu hình mã đo lường" render bằng field-row chuẩn (lưới nhãn + control, toggle, ghi chú dưới control), textarea Goal Events có ghi chú `event_name|Nhãn`.
Cảnh báo mã GA chèn thủ công ở Laca Theme → Scripts giữ nguyên logic.

## 3. Nghiệm thu (Claude sẽ chạy lại)
1. `test-google-services.php` cập nhật theo namespace mới + thêm: migration copy đúng 7 key + credentials (mock option qua `pre_option_*`, không ghi DB thật, hoặc
   ghi rồi khôi phục trong transaction tự viết); callback chấp nhận cả URI mới và cũ; URL auth có `select_account` + 4 scope + `redirect_uri` = URI mới; decode email từ
   id_token mẫu; đổi Client ID → token bị xoá; `Tracking` xuất gtag khi Laca SEO tắt/Rank Math active (giả lập bằng filter) — và không xuất khi trùng ID ở header script.
   Tất cả test cũ vẫn PASS.
2. Grep (in ra 0 dòng): `grep -rn "GscOAuth\|GscClient\|GaClient\|GoogleDashboardService" P/app/src/Features/LacaSeo` chỉ được còn import namespace mới ở
   `PostList.php`, `AdminBarMenu.php`, `Importer.php`; `grep -n "'analytics'" P/app/src/Features/LacaSeo/Fields/GeneralFields.php` = 0; `AdminPage.php` không còn `search-console`.
3. `wp-dashboard-report-audit.js`, `input-visibility-audit.js` (cập nhật danh sách URL: bỏ `laca-seo&view=general&tab=analytics`, `laca-seo&view=search-console` — **chỉ
   sửa danh sách URL**, không sửa logic kiểm), `autosave-audit.js` (trang `laca-seo-general` vẫn chạy), `list-table-audit.js`, `ui-probe.js` fail=0;
   `compare-head-full.php` `BAD URLS: 2`; `yarn lint` 0 error; `yarn build` OK; `php -l`.
4. Production-safe: `admin.php?page=laca-seo&view=search-console` redirect đúng; frontend production vẫn đúng 1 `gtag/js?id=G-XSYBCQ8DZC` (header script; Tracking không chèn trùng).
5. Commit `refactor(google): gom GSC + GA4 về Laca Admin, tách khỏi Laca SEO, OAuth hỗ trợ đổi tài khoản Google` → push → `result` qua agent-bridge
   (danh sách file chuyển/sửa, file để trống chờ người dùng xoá, NGUYÊN VĂN SUMMARY).

## 4. Ràng buộc
Không xoá file/option (chỉ copy), không cài package, không in secret/token ra HTML/log, không cửa sau, script có watchdog, đơn sắc, không inline style. Lệnh > 5 phút → tự dừng.
