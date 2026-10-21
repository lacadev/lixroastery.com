# Review Phase 0 + 1 — Vòng 1 (Claude Code, 21/10/2026)

Kết luận: **CHƯA đạt**. Báo cáo "khớp 100%" chưa đúng thực tế. Dưới đây là lỗi đã kiểm chứng bằng script/HTTP thật,
kèm việc phải sửa và tiêu chí nghiệm thu đo được. Sửa theo thứ tự mức độ. Plan gốc: `doc/laca-seo/PLAN.md`.

## A. BLOCKER

### A1. Trang `admin.php?page=laca-seo` không mở được ("Sorry, you are not allowed to access this page")
- Nguyên nhân: `Admin/AdminPage.php::register()` gắn `admin_menu` priority 10, trước khi container Carbon Fields
  `laca-admin` (menu cha) được tạo → WP đăng ký hook `admin_page_laca-seo` thay vì `laca-admin_page_laca-seo`.
- Sửa: `add_action('admin_menu', [$this, 'registerMenu'], 20)` (giống mọi feature khác của theme). Kiểm tra lại
  `enqueueAssets()` khớp đúng hook mới.
- Hệ quả: screenshot trong báo cáo KHÔNG phải WP admin thật (không có sidebar WP, notice font Times, nút mặc định trình
  duyệt) → toàn bộ UI chưa được kiểm tra. Xoá `doc/laca-seo/screenshots/` khỏi theme (không commit ảnh vào theme).
- Nghiệm thu: đăng nhập admin, mở 5 view, chụp bằng Chrome thật trong WP admin ở 1440/900/390, `document.documentElement.scrollWidth - innerWidth === 0`, 0 lỗi console.
  Cách lấy cookie để chụp headless: PHP-CLI `wp_generate_auth_cookie($id, $exp, 'logged_in')` + `'auth'` rồi set cho puppeteer
  (puppeteer có sẵn ở `lacadev-client-child/node_modules`).

### A2. Export / Import / Backup / Import Rank Math xử lý POST ngay trong view
- `Admin/views/import-export.php:10-60` chạy khi trang đã in HTML → `Exporter::downloadJson()` gửi header sau output
  (download hỏng / lẫn HTML admin); import không có giới hạn file.
- Sửa: chuyển hết sang `admin_post_laca_seo_export`, `admin_post_laca_seo_import`, `admin_post_laca_seo_restore_backup`,
  `admin_post_laca_seo_delete_backup`, `admin_post_laca_seo_download_backup` trong 1 class `Admin/ImportExportHandler.php`
  (nonce + `current_user_can('manage_options')`), xong redirect về view kèm notice (transient theo user).
  Import: kiểm `$_FILES['import_file']['error'] === UPLOAD_ERR_OK`, size ≤ 2MB, đuôi `.json`, `json_decode` lỗi → báo; lọc
  `export_sections` theo whitelist; giữ màn **xác nhận** trước khi ghi đè (plan P0.3) — upload lưu tạm vào transient
  10 phút → màn xác nhận liệt kê phần sẽ ghi đè → bấm "Xác nhận nhập" mới ghi.
- Nghiệm thu: Export tải được file `.json` hợp lệ (mở được bằng `json_decode`, không lẫn HTML); xoá option `laca_seo_titles`
  → Import lại → `get_option` giống hệt trước khi xoá (so sánh `serialize`).

### A3. Rank Math importer chạy đồng bộ, chỉ 1000 meta đầu, không batch AJAX
- `views/import-export.php` gọi `importPostMetaBatch(0, 1000)` 1 lần trong request render.
- Sửa theo plan P1.6: endpoint AJAX `wp_ajax_laca_seo_rm_import_step` (nonce + cap) chạy từng bước
  settings → termmeta → usermeta → postmeta (200/lượt, trả `next_offset`, `done`), JS hiện progress bar, idempotent
  (dùng `update_post_meta`, không `add_`), backup settings trước bước đầu. Báo cáo cuối: số option key, số post/term/user meta đã copy.
- Option `rank-math-options-general` trên DB local đang hỏng (chỉ có key `0`) — importer phải phát hiện option không phải
  mảng hợp lệ / serialized hỏng → bỏ qua phần đó + hiện cảnh báo rõ, không ghi rác vào `laca_seo_general`.
- Nghiệm thu: chạy import trên DB local → `laca_seo_titles` chứa đủ key RM đang lưu (107 key); 182 postmeta + 230 termmeta
  `rank_math_*` có bản `_laca_seo_*` tương ứng (trừ key bỏ theo Phụ lục B).

### A4. Frontend đọc fallback `rank_math_*` → che lỗi importer, sai mục tiêu "thay hẳn"
- `Frontend/Head.php` (title/description/canonical/robots) đọc `rank_math_*` khi thiếu `_laca_seo_*`.
- Sửa: BỎ toàn bộ fallback `rank_math_*` ở frontend. Laca SEO chỉ đọc dữ liệu của chính nó (dữ liệu có được nhờ importer).
  Dashboard hiện cảnh báo "Còn N bài có dữ liệu Rank Math chưa nhập" + nút tới importer.

## B. LỆCH RANK MATH (mục tiêu 1:1)

### B1. Registry thiếu field (đối chiếu key RM đang lưu trong `rank-math-options-titles`)
Thiếu, phải thêm (đúng id, default, choices, show_if theo file RM trong plan P1.2):
- Mỗi post type: `pt_{pt}_default_snippet_name`, `pt_{pt}_default_snippet_desc`, `pt_{pt}_default_article_type`,
  `pt_{pt}_link_suggestions`, `pt_{pt}_ls_use_fk`, `pt_{pt}_primary_taxonomy`, `pt_{pt}_bulk_editing`,
  `pt_{pt}_analyze_fields`, `pt_{pt}_facebook_image` (archive) — đối chiếu `RM/includes/settings/titles/post-types.php` (22 field id, kể cả field chỉ hiện theo điều kiện).
- Mỗi taxonomy: `tax_{tax}_bulk_editing`.
- Homepage: `homepage_facebook_title`, `homepage_facebook_description`, `homepage_facebook_image(+_id)` (+ notice `static_homepage_notice`).
- Local: `local_address_format`.
- Global: `rewrite_title` (ẩn khi theme có `title-tag`, nhưng vẫn có trong registry/export).
- Pro link: `affiliate_link_prefixes` (tab Links).
- Nghiệm thu: script so sánh (Claude sẽ chạy lại) `array_diff(keys(rank-math-options-titles), registry_ids) == []`
  (ngoại trừ post type `web-story`, `download` không tồn tại trên site — vẫn phải sinh nếu post type tồn tại).

### B2. Default sai so với installer RM (`RM/includes/class-installer.php` là chuẩn)
- `pt_{pt}_title` → `%title% %sep% %sitename%` (đang `%title% %page% %sep% %sitename%`).
- `pt_page_default_rich_snippet` → `article`; post `article` + `pt_post_default_article_type` = `BlogPosting`; product → `product`.
- `tax_post_tag_add_meta_box` → `off` (chỉ category = on).
- Rà lại TOÀN BỘ default với `create_misc_options()`/`get_*_defaults` của installer, kể cả: `tax_{tax}_title` = `%term% %sep% %sitename%`,
  `tax_post_tag/post_format/product_tag_custom_robots` = on + `[noindex]`, attachment custom robots on + `[noindex]`, `breadcrumbs` off,
  `html_sitemap` on, `404_monitor_ignore_query_parameters` on, `knowledgegraph_type` (company nếu Woo/EDD active lúc cài, ngược lại person).

### B3. Variables thiếu 15 biến + chưa hỗ trợ tham số
Thiếu: `%customterm(tax)%`, `%customterm_desc(tax)%`, `%filename%`, `%focuskw%`, `%keywords%`, `%org_logo%`, `%parent_title%`,
`%primary_taxonomy_terms%`, `%pt_single%`, `%pt_plural%`, và dạng tham số `%date(F jS, Y)%`, `%modified(...)%`, `%currenttime(...)%`,
`%categories(limit=3&separator= | &exclude=12,23)%`, `%tags(...)%`. Thêm cả `%term_{taxonomy}%`, `%term_{taxonomy}_desc%`, Woo `%wc_*%`.
- Port parser từ `RM/includes/replace-variables/class-manager.php` + `class-variable.php` (regex `%([a-z0-9_-]+)(\(([^)]*)\))?%`).
- Nghiệm thu: unit test PHP-CLI (`doc/laca-seo/tools/test-variables.php`) chạy 1 bài + 1 term mẫu, so kết quả Laca vs
  `RankMath\Helper::replace_vars()` (RM còn active trên local) cho MỌI biến → 0 khác biệt.

### B4. Frontend Head lệch logic RM (port theo `RM/includes/frontend/class-head.php` + `paper/*`)
Viết lại theo kiến trúc plan §2.1: `Frontend/Paper/{Paper, Singular, Blog, Shop, Taxonomy, Author, Date, Search, Error404, Archive, Misc}.php`
mỗi class trả `title()`, `description()`, `robots()`, `advanced_robots()`, `canonical()` giống class RM tương ứng. Lỗi cụ thể hiện tại:
1. **Robots in 2 lần**: `outputRobots()` echo thẻ + `filterRobots()` sửa `wp_robots` (core cũng in) → bỏ echo, CHỈ dùng `wp_robots`.
2. Robots chỉ xét noindex/nofollow — thiếu noarchive, noimageindex, nosnippet; advanced robots đang hardcode thay vì đọc
   `advanced_robots_global` / per post type / per post meta `_laca_seo_advanced_robots`; thiếu homepage custom robots, date archive robots,
   `noindex_archive_subpages`, `noindex_paginated_pages`, `noindex_password_protected`, attachment, author custom robots; meta robots per post
   phải ghi đè hoàn toàn (giống RM: meta → custom pt → global).
3. Title thiếu: trang blog `is_home()` (static front page + page_for_posts), archive post type (`pt_{pt}_archive_title`), shop Woo, phân trang
   `%page%`; front page static PHẢI dùng meta của page → không có thì template `pt_page_title` (RM không dùng `homepage_title` khi front page là page).
4. Description: không fallback `get_bloginfo('description')` ở front page (RM không làm vậy); archive post type, author, date description.
5. Canonical: phân trang (`/page/2/`), archive post type, author, date, blog page, shop; giữ nguyên rule "noindex → không canonical" như RM.
6. Thiếu `rel="prev"/"next"` (RM priority 21).
7. Thứ tự thẻ + comment credit giống RM để compare-head chuẩn hoá được.

### B5. compare-head chưa đo đúng
- Phải chạy SAU khi import RM (A3) và SAU khi bỏ fallback (A4) — nếu không, so sánh vô nghĩa.
- Thêm so sánh: `rel prev/next`, `og:image*`, `article:*`, `twitter:label/data`, verify tags, và (để sẵn cho phase 3) JSON-LD.
- Không tự tạo bài test: dùng URL có sẵn (bài journal, product, page có thật). Có trang phân trang `/journal/page/2/` và `/category/x/page/2/`.
- Nghiệm thu: bảng URL × thẻ, mọi khác biệt còn lại phải là do RM có tính năng phase sau (ghi rõ) — không chấp nhận "Laca tốt hơn nên khác".

## C. AN TOÀN / CHẤT LƯỢNG

- C1. **Xoá dữ liệu test đã tạo trong DB**: post ID 1073 (`sample-post-seo-test`), 1074 (glossary `arabica-coffee`), 1075 (partnership `partner-farm-vietnam`) — `wp_delete_post($id, true)`. Không tạo nội dung giả nữa.
- C2. `.htaccess`: không ghi nếu nội dung rỗng / không đổi / thiếu khối `# BEGIN WordPress`; ghi xong kiểm `file_get_contents` khớp; giữ tối đa 5 backup (xoá bản cũ hơn); báo lỗi nếu không writable. Chỉ super user (đang đúng) + ẩn hẳn tab với người không phải super user.
- C3. `laca_seo_force_frontend`: chỉ nhận qua `$_GET` khi `wp_get_environment_type() === 'local'` HOẶC user đang đăng nhập có `manage_options`; bỏ đọc từ `get_option` (dễ quên bật trên production → trùng thẻ với RM).
- C4. `robots_txt_content` đang lưu thẳng không sanitize — sanitize: bỏ ký tự điều khiển, giới hạn 50KB.
- C5. Mọi sanitize theo type phải có test nhanh (đưa `<script>` vào text/textarea → lưu ra đã escape khi render).

## D. Kiểm tra trước khi báo xong
`php -l` toàn bộ; `cd lacadev-client-child && yarn build && yarn lint` (0 error); 5 view chụp trong WP admin thật 3 kích thước;
export→xoá→import khớp; RM import đủ số liệu A3; test-variables 0 khác; compare-head sau import; không còn bài test; không commit/push.
Báo cáo theo đúng mục A1…C5 (mỗi mục: đã sửa thế nào + bằng chứng).
