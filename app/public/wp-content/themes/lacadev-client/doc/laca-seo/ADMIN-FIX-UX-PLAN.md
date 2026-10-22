# Plan — Laca SEO admin: sửa lỗi chức năng + đối chiếu Rank Math + làm gọn UI

P = `themes/lacadev-client`, C = `themes/lacadev-client-child`, RM = `plugins/seo-by-rank-math`, RMP = `plugins/seo-by-rank-math-pro`.
Công cụ nghiệm thu có sẵn: `P/doc/laca-seo/tools/ui-probe.js` (KHÔNG sửa logic kiểm của nó; chỉ được thêm view mới vào mảng `VIEWS` nếu tạo view mới).
Baseline hiện tại: `SUMMARY pass=63 fail=95`.

## Bản đồ file (đọc đúng các file này — không dò lại)
- showIf JS: `P/resources/scripts/admin/laca-seo/index.js` hàm `initShowIfDependencies()` (dòng ~37–75).
- Markup field + toggle: `P/app/src/Features/LacaSeo/Fields/Field.php` (render, `showIf()`), view tab: `P/app/src/Features/LacaSeo/Admin/views/*.php`.
- Field registry: `P/app/src/Features/LacaSeo/Fields/{General,Titles,Sitemap,InstantIndexing,Llms}Fields.php`.
- Menu: `P/app/src/Features/LacaSeo/Admin/AdminPage.php::registerMenu()` (dòng ~31). Nhóm "seo" trong
  `P/app/src/Settings/LacaAdmin/LacaAdminMenuOrganizer.php` (dòng ~117–123 và label dòng ~505).
  Laca Admin = Carbon `set_page_menu_position(3)` (`P/app/src/Settings/AdminSettings.php:842`);
  Laca Theme = `set_page_menu_position(3.1)` slug `app-theme-options.php` (`C/theme/setup/theme-options.php:58`).
  Cơ chế ẩn menu khác cần soát: `AdminSettings::removeUnnecessaryMenus()`, `filterAdminMenuForExtraSuperUser()`,
  `maybeBlockDisallowedAdminPage()`, `P/app/src/Settings/LacaTools/Management/AdminUxService.php` (`simplifyMerchantAdmin`), `LacaAdmin/CptMenuGrouper.php`.
- GSC: callback xử lý ở đầu `P/app/src/Features/LacaSeo/Admin/views/search-console.php` (dòng ~44–81), OAuth `Modules/SearchConsole/GscOAuth.php`.
- SCSS admin: `P/resources/styles/admin/laca-seo/index.scss`, `editor.scss`; token dùng chung `P/resources/styles/admin/_admin-dashboard.scss` (`--laca-*`).

---

## PHẦN A — sửa chức năng (làm ngay)

### A1. showIf đọc sai input (lỗi gốc của "breadcrumbs không có cấu hình")
Toggle render 2 input cùng name: `input[type=hidden][value=off]` + checkbox. `querySelector` bắt trúng hidden → luôn 'off'.
Kết quả: mọi hàng `showIf(x,'on')` luôn ẩn (breadcrumbs 12 field, images, links, html sitemap, robots tùy chỉnh từng post type/taxonomy…),
mọi hàng `showIf(x,'off')` luôn hiện (authors, misc).
- Sửa: tìm control theo thứ tự checkbox → radio:checked → select → input khác, **bỏ qua `type=hidden`**. Gắn listener `change` + `input`.
- Phụ thuộc lồng: nếu master đang bị ẩn (chính nó có `data-show-if-field` và đang ẩn) thì hàng con cũng ẩn. Đánh giá lặp tới khi ổn định (tối đa 5 vòng).
- Hỗ trợ `data-show-if-value` nhiều giá trị phân tách bằng `,` (nếu `Field::showIf` cho phép mảng — đọc Field.php; nếu chưa có thì KHÔNG thêm).
- Server-side: hàng phụ thuộc được render `style="display:none"` sẵn → nếu Field.php tính trạng thái ban đầu bằng giá trị đã lưu thì giữ;
  nếu luôn `display:none` thì sửa để render đúng trạng thái theo giá trị đã lưu (tránh nháy).
- Field `breadcrumbs_*` đã đủ 13/13 so với RM (`RM/includes/settings/general/breadcrumbs.php`) — không thêm field, chỉ cần hiện ra.

### A2. Laca SEO thành menu cấp 1, giữa Laca Admin và Laca Theme
- `registerMenu()`: `add_menu_page('Laca SEO', 'Laca SEO', 'manage_options', 'laca-seo', [$this,'renderPage'], 'dashicons-search', '3.05')`.
  Giữ slug `laca-seo` (mọi link `admin.php?page=laca-seo…` hiện có vẫn chạy). Có thể thêm submenu cùng slug để tiêu đề submenu đầu là "Bảng điều khiển".
- Bỏ nhóm `'seo'` + label `'laca-seo'` khỏi `LacaAdminMenuOrganizer` (nó không còn là con của laca-admin; kiểm organizer không lỗi khi thiếu nhóm).
- Soát MỌI cơ chế ẩn menu ở bản đồ file: administrator thường (không phải super user, kể cả extra super user) phải thấy + vào + **lưu** được.
  Mọi handler lưu/AJAX của Laca SEO (`admin_post_*`, `wp_ajax_laca_seo_*`, REST) kiểm `manage_options` — không kiểm login super user. Grep và báo danh sách.
- `ReleaseChecklist.php:585` link vẫn đúng. Admin bar node "Laca SEO" (nếu có) vẫn trỏ đúng.

### A3. GSC: "Lỗi 403 access_denied"
Lỗi do cấu hình Google Cloud (app OAuth đang ở chế độ Testing, tài khoản chưa nằm trong Test users) — không phải code. Code cần:
- Khi Google redirect về với `?laca_gsc_oauth=1&error=...` (không có `code`): verify `state` như nhánh thành công, xoá pending, hiện thông báo
  tiếng Việt theo mã lỗi: `access_denied` → "Google từ chối quyền truy cập. Nếu ứng dụng OAuth đang ở chế độ Testing, hãy thêm email của bạn vào
  Test users (Google Cloud → Google Auth Platform → Audience) hoặc bấm Publish app, rồi thử lại."; mã khác → hiện mã + mô tả đã escape.
- Thêm khối "Chuẩn bị Google Cloud" (thu gọn được, `<details>`) cạnh form Client ID: 1) bật **Google Search Console API**; 2) OAuth consent/Audience:
  thêm Test users hoặc Publish; 3) Client loại *Web application*, Authorized redirect URI = đúng `$redirectUri` đang hiện (có nút copy); 4) scope
  `webmasters.readonly`. Chuyển inline style của khối `<code>` hiện có sang class SCSS.

### A4. Đối chiếu Rank Math toàn bộ field cài đặt — script `P/doc/laca-seo/tools/test-rm-parity.php`
- Nguồn chuẩn (2 lớp, gộp):
  1) Parse tĩnh `'id' => '...'`, `'type'`, `'default'`, `'options'` của mọi `add_field` trong `RM/includes/settings/**/*.php`,
     `RM/includes/modules/**/views/*options*.php` / `**/options.php`, và tương tự ở RMP. Field có id động (`$post_type`, `$taxonomy`) → mở rộng theo
     post type/taxonomy thật của site giống RM (`Helper::get_accessible_post_types()` / taxonomies — dùng hàm WP tương đương, không gọi RM runtime).
  2) Key đã lưu trong option `rank-math-options-{general,titles,sitemap}` (general dùng bản đã sửa — xem `compare-head-full.php` cách nạp).
- So với registry Laca (nạp class Fields, lấy id/type/default/choices). In theo tab:
  `PASS|FAIL <tab> missing=[…] extra=[…] default≠=[…] choices≠=[…]` + `SUMMARY`.
- Danh sách bỏ qua có chủ đích: mảng `$SKIP = ['id' => 'lý do']` ở đầu script (vd field chỉ của RM cloud/analytics/content AI). Mỗi mục phải có lý do.
- Bổ sung field thiếu vào registry (cùng key RM, cùng default, nhãn tiếng Việt), và **nối logic frontend** cho field mới nếu field đó có tác dụng output
  (nếu chưa làm được thì đưa vào `$SKIP` với lý do "chưa hỗ trợ", KHÔNG thêm field chết).
- Không thay đổi output frontend hiện có: chạy lại `compare-head-full.php` phải vẫn `BAD URLS: 2` (đúng 2 URL đã biết).

### Nghiệm thu phần A
1. `node P/doc/laca-seo/tools/ui-probe.js` → mọi dòng `show-if`, `menu-order`, `access` PASS (các dòng `no-overflow`/`no-foreign-notice` thuộc phần B, chưa bắt buộc).
2. `LOGIN=<1 administrator thường có sẵn> node …/ui-probe.js` → `access` + `menu-order` PASS. Liệt kê user role administrator bằng `wp_get_users`/CLI;
   nếu không có admin thường nào thì báo lại, **không tạo user**.
3. `php test-rm-parity.php` → SUMMARY, dán NGUYÊN VĂN. `compare-head-full.php` → `BAD URLS: 2`.
4. `yarn lint`, `yarn build` (trong P) 0 error; `php -l` mọi file PHP sửa.

---

## PHẦN B — làm gọn UI (CHỜ người dùng duyệt bảng dưới; chưa làm)

Đo hiện trạng (ui-probe, 1440/1024/390): nav chính 12 mục xuống 3 / 5 / 9 hàng; tab con tràn ngang ở 1024; sticky footer tràn ở 1024;
header actions tràn ở 390; bảng hướng dẫn tràn ở 390; notice plugin khác (kk Star Ratings, Simple Custom Post Order, Rank Math…) chen trên đầu trang;
156 mã màu hex cứng, bo góc lẫn 3/4/6/10/24px. Phong cách: flat, giữ màu chủ đạo `--laca-primary`.

| # | Trước | Sau | Chọn |
|---|---|---|---|
| B1 | Notice plugin khác chen giữa menu và header Laca SEO (6 khối) | Trên trang `laca-seo`: gỡ notice của bên thứ ba (giữ notice của core về lỗi lưu + notice của Laca SEO). Notice Laca SEO (chế độ an toàn, blog_public) hiển thị gọn 1 dòng trong khung trang, có link hành động | ✓ |
| B2 | Nav ngang 12 mục, xuống 3–9 hàng | ≥1024: sidebar dọc bên trái 220px, nhóm: **Tổng quan** · **Cài đặt** (Chung, Tiêu đề & Meta, Sitemap) · **Công cụ** (IndexNow, Chuyển hướng, 404, Search Console) · **Hệ thống** (Sao lưu/Nhập xuất, Hướng dẫn). <1024: 1 `<select>` "Đi tới…" + tiêu đề trang hiện tại | ✓ |
| B3 | Tab con (General 12 tab, Titles 20+ tab) xuống nhiều hàng / tràn | 1 hàng cuộn ngang (`overflow-x:auto`, không wrap, mờ mép khi còn nội dung), tab đang chọn tự cuộn vào giữa | ✓ |
| B4 | Header to, badge "PROFESSIONAL", nút tràn ở 390 | Header 1 dòng: tên trang hiện tại (h1) + mô tả 1 câu; nút phụ wrap xuống dưới ở <782 | ✓ |
| B5 | 156 hex cứng, bo 3/4/6/10/24px | Mọi màu qua `--laca-*` (thêm token còn thiếu vào `_admin-dashboard.scss`: success/warning/danger + nền nhạt); bo: control 6px, card 8px, pill 999px | ✓ |
| B6 | Hàng field không đều (nhãn/ô/ghi chú lệch) | Lưới 2 cột: nhãn 260px + control (tối đa 560px), ghi chú dưới control; <782 xếp 1 cột; khoảng cách hàng 16px, đường kẻ 1px giữa hàng | ✓ |
| B7 | Sticky footer `position:fixed` tràn ở 1024 | `position:sticky; bottom:0` trong khung form, rộng đúng khung; nút "Lưu thay đổi" + trạng thái "Có thay đổi chưa lưu" | ✓ |
| B8 | Bảng (`widefat`) ở Hướng dẫn, Chuyển hướng, 404 tràn ở 390 | Bọc khung cuộn ngang; bảng rỗng có trạng thái rỗng 1 câu + nút hành động | ✓ |
| B9 | Inline style rải trong view (search-console, import-export…) | Chuyển sang class BEM trong SCSS | ✓ |
| B10 | Sau khi lưu không rõ đã lưu | Thông báo "Đã lưu cài đặt" gọn đầu khung nội dung, tự ẩn 4s; lỗi thì giữ | ✓ |
| B11 | Metabox trong trình soạn bài (`editor.scss`, 61 hex) | Áp cùng token/bo góc như B5, không đổi bố cục | ✓ |

Nghiệm thu phần B: `ui-probe.js` → `SUMMARY fail=0` ở cả 3 bề rộng; ảnh 10 view × 3 bề rộng vào `/private/tmp/laca-seo-ui/`;
`yarn lint`/`yarn build` 0 error; frontend không đổi (`compare-head-full.php` → `BAD URLS: 2`).

## Ràng buộc chung
Không đổi DB/nội dung (test chỉ đọc, abort POST), không xoá file, không cài package, không mu-plugin, không sửa `plugins/`, không sửa tay `dist/`,
không commit/push. Không tạo test giả (hằng true, kiểm sự tồn tại file). Báo cáo ngắn: file sửa, NGUYÊN VĂN output các script, build/lint;
xong PHẢI gửi qua agent-bridge.
