# Plan — Sửa lỗi nghiêm trọng trang Search Console, chọn property đúng, báo cáo GSC + GA4 trên WP Dashboard (index.php)

> Người thực hiện: Antigravity → Claude review. HEAD `2668f9e1`. P = `themes/lacadev-client`. Đơn sắc, không inline style, không hex ngoài `_tokens.scss`.

## 1. Lỗi đã xác định (Claude đọc code + chạy local)
| # | Lỗi người dùng thấy | Nguyên nhân |
|---|---|---|
| E1 | `laca-seo&view=search-console`: "There has been a critical error" ngay sau "Tổng lượt click" | Từ commit trước `GscClient::getPerformanceSummary()` trả `array|WP_Error`, nhưng `P/app/src/Features/LacaSeo/Admin/views/search-console.php` dòng 13 + 73–118 vẫn đọc `$perf['totalClicks']`… → khi Google trả lỗi (404 property) PHP fatal "Cannot use object of type WP_Error as array". |
| E2 | "Không tìm thấy property trong Google Search Console" | Property đang lưu là `https://lixroastery.com/` (ô text gõ tay); tài khoản Google có thể chỉ có Domain property `sc-domain:lixroastery.com` hoặc bản `www`. Trang không cho chọn từ danh sách property thật của tài khoản. |
| E3 | Thông báo lỗi đỏ nằm TRÊN header Laca SEO (ảnh 3) | Lỗi được in qua `admin_notices` toàn cục thay vì trong card GSC. |
| E4 | Báo cáo không có ở `wp-admin/index.php` | Widget `laca_seo_dashboard_widget` (`Modules/DashboardWidget/DashboardWidget.php`) có đăng ký nhưng nằm cuối trang (top ≈ 3324px, sau 13 widget khác) và chỉ có 3 số Redirect/404/Links — không chứa GSC/GA. Báo cáo đầy đủ chỉ ở `laca-seo` → Bảng điều khiển. |

## 2. Yêu cầu sửa

### 2.1 E1 — không bao giờ fatal khi Google lỗi
- `views/search-console.php`: bỏ đọc trực tiếp `$perf[...]`. Dùng `GoogleDashboardService::getGscData($range)` (đã xử lý `WP_Error` → mảng có `error`) và render bằng **partial dùng chung** với dashboard (2.4) — không nhân bản markup.
- Grep MỌI nơi gọi `GscClient::getPerformanceSummary`, `GscClient::getSitemaps`, `GaClient::runReport`, `GaClient::runGoalEventsReport`, `GaClient::*` trong `P/app/src` và xử lý
  `is_wp_error()` trước khi dùng như mảng. Liệt kê danh sách nơi gọi + cách xử lý trong báo cáo.
- `getSitemaps()` cũng phải trả `array|WP_Error` thống nhất (kiểm HTTP status, không cache lỗi).

### 2.2 E2 — chọn property từ danh sách thật
- Trang `laca-google-search-console`: thay ô text "Trang web / Property" bằng **select** lấy từ `GET https://www.googleapis.com/webmasters/v3/sites` (cache 1 giờ, nút "Tải lại danh sách"),
  mỗi dòng: `siteUrl` + `permissionLevel` (siteOwner/siteFullUser/siteRestrictedUser/siteUnverifiedUser — `siteUnverifiedUser` hiện "(chưa xác minh)" và disabled).
  Chưa chọn → tự chọn theo thứ tự `sc-domain:<host không www>` → `https://<host>/` → `https://www.<host>/` → `http://…` (logic `GscClient` đã có — dùng lại).
- Lưu bằng nonce + `manage_options` (cùng handler `admin_init`). Danh sách rỗng → Alert: "Tài khoản Google <email> chưa có property nào trong Search Console — thêm site tại
  search.google.com/search-console rồi bấm Tải lại" (email lấy từ token info `https://www.googleapis.com/oauth2/v3/tokeninfo` hoặc `userinfo` nếu có scope; không có thì bỏ email).
- Lỗi 403/404 trên dashboard: thông báo nêu property đang dùng + link "Chọn property khác" tới trang này.
- Hiển thị dòng "Đang kết nối bằng tài khoản: <email>" nếu lấy được.

### 2.3 E3 — vị trí thông báo lỗi
Lỗi GSC/GA chỉ hiển thị **bên trong card tương ứng** (GSC trái / GA phải), dạng Alert đơn sắc có icon ✕, mã HTTP và nút hành động. Không in qua `admin_notices`.

### 2.4 E4 — báo cáo đầy đủ trên `wp-admin/index.php`
- Tách phần báo cáo GSC + GA4 của `views/dashboard.php` thành partial `P/app/src/Features/LacaSeo/Admin/views/partials/google-report.php` (bộ chọn 7/28/90 ngày + Làm mới,
  cột GSC trái, cột GA4 phải — đúng phương án B người dùng đã chọn). Laca SEO → Bảng điều khiển và index.php dùng chung partial này.
- index.php: render khối `#laca-google-report` **toàn chiều rộng, ở đầu trang Dashboard** (dưới tiêu đề "Dashboard", trên lưới widget): hook `admin_notices` chỉ khi
  `get_current_screen()->id === 'dashboard'` và user có `manage_options` (hoặc cap `site_analysis` của RoleManager), markup KHÔNG dùng class `notice` (để WP không di chuyển).
  Có nút thu gọn/mở (nhớ trạng thái theo user bằng `localStorage` — chỉ là tiện ích hiển thị).
- **Tải bằng AJAX** sau khi trang hiện (skeleton trước): endpoint có sẵn `wp_ajax_laca_google_report` (nonce + quyền) trả HTML partial theo `range`; index.php không chờ Google.
- Widget nhỏ "Tổng quan Laca SEO" giữ lại nhưng bỏ phần trùng GSC/GA (chỉ Redirect/404/Links/link Bảng điều khiển).
- Chưa kết nối GSC/GA → card trạng thái rỗng + nút "Thiết lập" tới trang Dịch vụ Google tương ứng (như dashboard Laca SEO).
- Responsive: 1440 hai cột, ≤ 1024 xếp dọc, 390 một cột; không cuộn ngang trang (bảng bọc khung cuộn riêng).

## 3. Nghiệm thu (Claude sẽ chạy lại)
1. `test-google-services.php` thêm (mock `pre_http_request`, không gọi Google thật, không test giả):
   - Render `views/search-console.php` khi searchAnalytics trả **404** và khi trả **403** → không exception/fatal (bọc `try { ob_start(); include } catch (\Throwable $e)` → FAIL nếu bắt được),
     HTML chứa thông báo lỗi tiếng Việt + link chọn property; không chứa "Tổng lượt click" với số 0.
   - `sites.list` trả `[sc-domain:lixroastery.com (siteOwner), https://lixroastery.com/ (siteUnverifiedUser)]` → select chọn sẵn `sc-domain:lixroastery.com`, option chưa xác minh disabled.
   - `sites.list` rỗng → HTML có hướng dẫn thêm property.
   - AJAX `laca_google_report` không nonce → 403/`-1`; có nonce + mock dữ liệu → trả HTML partial có 2 cột.
2. Puppeteer (script mới `P/doc/admin-ui/tools/wp-dashboard-report-audit.js`, theo mẫu cookie/watchdog của `list-table-audit.js`): mở `index.php?laca_mock_google=1` (local) ở 1440/1024/390:
   `#laca-google-report` tồn tại, `getBoundingClientRect().top` < 450 ở 1440, sau tối đa 15s có cả khu GSC và GA (text "Google Search Console" và "Google Analytics 4"),
   có banner "DỮ LIỆU MẪU"; không `pageerror`; `docOver ≤ 0`. Mở `index.php` (không mock, local chưa kết nối) → khối có 2 trạng thái rỗng "Thiết lập", không có số liệu.
   Mở `admin.php?page=laca-seo&view=search-console` → không có chữ "critical error".
3. `input-visibility-audit.js`, `autosave-audit.js`, `list-table-audit.js` fail=0; `yarn lint` 0 error; `yarn build` OK; `php -l` file sửa.
4. Commit `fix(laca-seo): trang Search Console không còn lỗi nghiêm trọng, chọn property từ danh sách, báo cáo GSC + GA4 trên WP Dashboard` → push → `result` qua agent-bridge
   (danh sách nơi gọi API đã xử lý WP_Error, NGUYÊN VĂN SUMMARY các test/audit, ảnh index.php 1440/390 ở `/private/tmp/admin-ui/google/`).

## 4. Ràng buộc
Không in secret/token ra HTML/log. Không cài package, không xoá file, không cửa sau đăng nhập, script Puppeteer có watchdog + `try/finally`, lệnh > 5 phút tự dừng.
Không đổi DB ngoài thao tác lưu qua UI khi test (trả lại như cũ). Đơn sắc, không inline style.
