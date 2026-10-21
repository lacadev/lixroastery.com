# Review Phase 0 + 1 — Vòng 3 (Claude Code, 21/10/2026)

Đã đạt (Claude tự kiểm chứng): Export qua HTTP → JSON hợp lệ (titles 285, general 86 key); registry khớp RM; default khớp;
**test-variables 55/55 khớp `RankMath\Helper::replace_vars()`**; notice không còn chen vào header; trang cuộn tự nhiên; 0 overflow, 0 lỗi console.
Còn lại (compare-head 19 URL, output thật):

## A. Lệch Rank Math

### A1. Quy tắc "RM thiếu key" áp SAI cho post type (do sửa B1 vòng 2)
- Bài Journal (vi + en): RM `… - LIX ROASTERY` (template mặc định), Laca `… – LIX ROASTERY` (title core WP).
- Thực tế RM: **post type** thiếu key `pt_{pt}_title` → vẫn dùng template mặc định (`%title% %sep% %sitename%`); chỉ **taxonomy** thiếu key mới
  rơi về title core. Đọc lại `RM/includes/frontend/paper/class-singular.php` + `class-taxonomy.php` + `class-paper.php::get_from_options`
  để làm đúng từng loại. Sửa importer: chỉ ghi `''` cho key **taxonomy** (title + description) thiếu; post type thiếu key → ghi default installer.

### A2. Archive post type (`/journal/`, `/journal/page/2/`) thiếu description
- RM: `Journal Archive - LIX ROASTERY` / `Journal Archive - Page 2 of 2 - LIX ROASTERY` (cả og/twitter description).
- Port logic description của `RM/includes/frontend/paper/class-archive.php` (fallback khi `pt_{pt}_archive_description` trống).

### A3. OG/Twitter của taxonomy thiếu key (`/product-category/ca-phe-specialty/`, `/journal-cat/find-the-origin/`)
- RM: `og:title`/`twitter:title` **rỗng**; `og:description`/`twitter:description` = mô tả term. Laca: in title core; og:description journal-cat rỗng
  nhưng twitter:description có → không nhất quán.
- Mimic RM `RM/includes/opengraph/class-opengraph.php` (`get_title`, `get_description`): title OG = title SEO đã tính (rỗng thì không in), description OG
  = description SEO → fallback mô tả term. Strip `<p>` là khác biệt duy nhất được chấp nhận (RM để lọt HTML).

### A4. `article:published_time` / `article:modified_time` thiếu ở trang chủ (static page) và page
- RM in cho mọi singular (kể cả page / front page). Port `RM/includes/opengraph/class-facebook.php` (`article_published_time` …) đúng điều kiện RM.

### A5. Slack enhanced data (`twitter:label1/data1…`) sai nội dung
- RM: front page/page `Time to read` / `Less than a minute`; bài có tác giả `Written by` / `{tên}` + `Time to read` / `{n} minutes`;
  author archive `Name` / `{tên}` (+ `Posts`); term `Items` / count; product `Price` / `Availability`. Laca đang in `Est. reading time` / `1 minutes`, author rỗng.
- Port nguyên `RM/includes/opengraph/class-slack.php` (label, cách tính thời gian đọc, chuỗi "Less than a minute", điều kiện bật `pt_*_slack_enhanced_sharing` /
  `author_slack_enhanced_sharing` / `tax_*_slack_enhanced_sharing`).

## B. Giao diện (ảnh WP admin thật 1440 — titles/pt_post)

1. **Dropdown "Chèn biến" mở sẵn tất cả** (chồng lên nhau, che form) và **mã biến hiện `undefined`** (JS đọc sai key — kiểm tra key JSON của
   danh sách biến truyền xuống JS). Đúng: đóng mặc định; mở khi bấm nút; đóng khi chọn/Esc/click ngoài; chỉ 1 dropdown mở 1 lúc; hiện `%title%` + tên + ví dụ;
   ô tìm trong dropdown lọc được; chọn → chèn tại vị trí con trỏ.
2. **Ô template quá hẹp** (chữ bị cắt): field kiểu `variable_text` chiếm full chiều rộng cột control; nút "Chèn biến" đặt dưới/bên phải gọn; preview +
   đếm ký tự hiện ngay dưới ô (đang bị dropdown che).
3. **Tab trái bị cắt chữ** ("Phân loại: Category (category - pos…"): cho xuống dòng (`white-space: normal`) hoặc tách nhãn 2 dòng (tên / slug nhỏ).
4. **`pt_{pt}_primary_taxonomy` là ô text** → `select` các taxonomy hierarchical của post type đó + "Không" (choices như RM `post-types.php`).
5. **`pt_{pt}_facebook_image` hiện cả khi post type không có archive** → chỉ hiện khi `has_archive` (đúng RM). Kiểm tra lại toàn bộ điều kiện hiển thị
   field per post type/taxonomy so với `RM/includes/settings/titles/post-types.php` & `taxonomies.php` (`dep`, `if (…)`).
6. **Icon ✓ trong nút Lưu rớt dưới chữ**: `display:inline-flex; align-items:center; gap:6px` (đã yêu cầu ở vòng 2, chưa sửa).

## C. Nghiệm thu
- compare-head (19 URL) → chỉ còn DIFF loại "strip `<p>`" ở og/twitter description; dán nguyên văn output.
- test-variables vẫn 55/55.
- Puppeteer WP admin thật: chụp `titles&tab=pt_post` 1440 + 390 SAU khi bấm "Chèn biến" 1 ô (dropdown mở đúng 1 cái, không `undefined`) và khi chưa bấm
  (không dropdown nào hiện). 0 overflow, 0 lỗi console.
- `php -l`, `yarn build`, `yarn lint` 0 error. Không commit/push, không tắt Rank Math.
