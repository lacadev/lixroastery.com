# Review Phase 0 + 1 — Vòng 2 (Claude Code, 21/10/2026)

Tiến bộ rõ: trang admin mở được, registry đủ field (chỉ thiếu post type web-story/download không tồn tại — OK), default khớp
installer, bỏ fallback rank_math_*, Paper/* đã có, bài test đã xoá, compare-head dùng URL thật.

**Lưu ý về báo cáo:** báo cáo vòng 2 liệt kê URL không tồn tại trên site (`/how-to-brew-v60-coffee/`, `/gioi-thieu/`,
`/product/ca-phe-dac-san-fine-robusta/`, `/journal-category/tin-tuc/`) và field không có trong Rank Math (`pt_{pt}_headline`,
`pt_{pt}_snippet_article_type`). Báo cáo phải chép NGUYÊN VĂN output lệnh đã chạy, không tóm tắt bằng số liệu tự viết.

Claude đã tự kiểm chứng bằng: so sánh registry ↔ option RM, compare-head (13 URL), Puppeteer trong WP admin thật, gọi
`admin-post.php?action=laca_seo_export` bằng HTTP. Còn các lỗi sau:

## A. BLOCKER

### A1. File Export tải về KHÔNG phải JSON hợp lệ
- HTTP thật: `POST admin-post.php action=laca_seo_export` → header đúng nhưng body bắt đầu bằng
  `<b>Warning</b>: foreach() argument must be of type array|object, null given in wp-admin/includes/plugin.php:1867`.
- Nguyên nhân gốc (code cũ của theme, áp dụng mọi admin-post/AJAX): `App\Settings\AdminSettings::hidePostMenuDefault()` và
  `hideCommentMenuDefault()` (`app/src/Settings/AdminSettings.php:822-835`) gọi `remove_menu_page()` trên `admin_init` — lúc đó
  `$menu` chưa có. Sửa: đổi sang `add_action('admin_menu', …, 999)`.
- Thêm phòng thủ ở mọi endpoint trả file/JSON của Laca SEO (export, download backup, AJAX importer): `while (ob_get_level()) ob_end_clean();`
  trước khi gửi header + body; `nocache_headers()`.
- Tên file dùng giờ site: `wp_date('Ymd-His')` (đang ra giờ UTC).
- Nghiệm thu: Claude sẽ gọi lại bằng curl có cookie đăng nhập; `json_decode` thành công, `data.titles` có ≥ 266 key.
  Kèm test upload-import qua HTTP thật (không chỉ CLI): upload file vừa export → màn xác nhận → xác nhận → option khớp.

## B. LỆCH RANK MATH (compare-head sau import — output thật)

| URL | Thẻ | Rank Math | Laca SEO | Việc |
|---|---|---|---|---|
| `/category/uncategorized/` | og:title, twitter:title | `Uncategorized - LIX ROASTERY` | **rỗng** | Lỗi: OG/Twitter title của taxonomy phải dùng title đã tính (giống `RM/includes/opengraph/class-opengraph.php::get_title`) |
| `/?s=cafe` | og:url | `http://lixroastery.local/search/cafe/` | `http://lixroastery.local` | Lỗi: og:url = URL trang hiện tại (search → `get_search_link()`), giống RM `class-opengraph.php` |
| `/product-category/ca-phe-specialty/` | title | `… – LIX ROASTERY` (en-dash) | `… - LIX ROASTERY` | Xem B1 |
| `/journal-cat/find-the-origin/` | description | rỗng | `%term_description%` | Xem B1 |

### B1. Hành vi khi option RM KHÔNG có key cho post type / taxonomy (taxonomy tạo sau khi RM lưu settings)
- `rank-math-options-titles` trên site không có `tax_product_cat_title`, `tax_journal-cat_description`, … → Rank Math trả rỗng →
  title rơi về title core WP (`wp_get_document_title`, separator mặc định `–`), description không in.
- Laca phải cho ra CÙNG kết quả sau khi import (mục tiêu: chuyển đổi không làm thay đổi SEO live):
  1. `RankMathImporter::importSettings()` với mỗi post type / taxonomy đang tồn tại mà RM thiếu key `_title`/`_description`/
     `_archive_title`/`_archive_description` → ghi giá trị **rỗng `''`** (không để registry default).
  2. Frontend: template rỗng → title = title core WP (không filter), description = không in (đúng RM).
  3. Dashboard + tab tương ứng: cảnh báo "Taxonomy/Post type X chưa có mẫu tiêu đề/mô tả — đang dùng tiêu đề mặc định WordPress" + nút
     "Dùng mẫu khuyến nghị" (điền registry default). Note field ghi rõ hành vi khi để trống.
- Site mới không có RM → giữ registry default như hiện tại.

### B2. Field địa chỉ không khớp key Rank Math → mất dữ liệu sau import
- `Admin/Renderer.php` case `address` dùng `street/locality/region/postcode/country`; RM lưu `streetAddress, addressLocality,
  addressRegion, postalCode, addressCountry` (`RM/includes/settings/titles/local.php` + CMB2 field type address). Đổi về đúng key RM ở
  Renderer, sanitize, Variables, (phase 3) schema. Bỏ `style=""` inline → class SCSS.

### B3. Variables
- Thiếu `%seo_title%`, `%seo_description%` (đang làm default của `pt_*_default_snippet_name/desc`). Port theo RM `class-advanced-variables.php`.
- `doc/laca-seo/tools/test-variables.php` phải test THÊM các dạng tham số: `%date(F jS, Y)%`, `%modified(d/m/Y)%`, `%currenttime(H:i)%`,
  `%categories(limit=2&separator= | )%`, `%tags(limit=1)%`, `%customterm(product_cat)%`, `%customterm_desc(product_cat)%`,
  `%customfield(_price)%`, `%term_product_cat%`, `%focuskw%`, `%pt_single%`, `%pt_plural%`, `%parent_title%`, `%primary_taxonomy_terms%`,
  `%org_logo%`, `%filename%`, `%count(title)%`, `%wc_price%`, `%wc_sku%` — trên 1 sản phẩm thật (ID 29) + 1 journal + 1 term product_cat.
  In bảng `biến | RM | Laca | OK/DIFF` (output nguyên văn vào báo cáo).

### B4. compare-head mở rộng
- Thêm URL: `/journal/` (archive CPT), `/journal/page/2/`, `/tag/<tag thật>/`, 1 attachment thật, `/en/journal/<bài thật bản EN>/`, `/en/shop/`.
- Thêm thẻ so sánh: `robots` (nguyên chuỗi content), `rel prev/next`, `og:image`, `og:image:width/height`, `og:locale`, `og:site_name`,
  `article:published_time`, `article:modified_time`, `twitter:label1/data1`, webmaster verify.
- Mục tiêu: 0 DIFF; mọi DIFF còn lại phải có trong bảng "được chấp nhận" với lý do: chỉ chấp nhận (a) RM để lọt HTML `<p>` vào og/twitter
  description (Laca strip — đúng hơn) và (b) tính năng thuộc phase sau.

## C. GIAO DIỆN (chụp trong WP admin thật, Claude đã xem 1440/900/390)

1. **Notice chen vào header**: notice của plugin khác (kk Star, Simple Custom Post Order, Rank Math) + notice Laca bị WP chèn vào trong
   khối header flex → bị bóp thành cột hẹp dài. Sửa: render `<h1 class="wp-heading-inline">` (ẩn trực quan nếu cần) + `<hr class="wp-header-end">`
   ĐẦU trang trước header Laca (WP chèn notice sau `.wp-header-end`), notice của Laca in trong 1 vùng `.laca-seo__notices` full width dưới header.
2. **Vùng settings bị giới hạn chiều cao + cuộn bên trong** (phần dưới trang trống, 2 thanh cuộn): bỏ mọi `height:100vh`/`overflow:auto` của
   khung chính; trang cuộn tự nhiên; cột tab trái `position: sticky; top: 32px+` (admin bar) và tự cuộn nếu dài.
3. **Thanh Lưu sticky đè cột tab trái**: thanh lưu chỉ rộng bằng cột nội dung (sticky bottom trong cột phải), không `position:fixed` full width.
   Icon ✓ trong nút đang rớt xuống dưới chữ → `display:inline-flex; align-items:center; gap`.
4. **Tab ngang (Dashboard/General/Titles/Sitemap/Import/Guide) bị cắt ở 1440** khi có dock Laca Admin: cho xuống dòng (`flex-wrap`) hoặc cuộn ngang
   có mũi tên/fade; ở 390 hiện dạng `<select>` hoặc cuộn ngang có fade.
5. **Tên tab trùng**: "Danh mục: Category" ×2, "Danh mục: Tag" ×2 (category vs product_cat, post_tag vs product_tag) → hiển thị
   `{label taxonomy} ({post type gắn với nó})`, vd "Danh mục sản phẩm (product_cat)"; post type cũng thêm slug nhỏ.
6. **Ô template thiếu "Chèn biến" + preview** (plan §2.4 `variable_text`/`variable_textarea`): các field title/description/format
   (`pt_*_title`, `*_description`, `homepage_*`, `*_archive_title`, `breadcrumbs_*_format`, `img_*_format`, `404_title`, `search_title`, `author_archive_title`,
   `date_archive_title`, `pt_*_default_snippet_*`) đổi type → nút "Chèn biến" mở dropdown có tìm kiếm (tên + mô tả + ví dụ, lấy từ Variables registry),
   bên dưới là preview kết quả thật qua AJAX `laca_seo_preview_vars` (debounce 400ms) + đếm ký tự (title 50–60, description 120–160 có màu).
7. **Renderer**: thêm type `radio`, `html`, `notice` (đang rơi vào default text input). Bỏ hết `style=""` inline trong Renderer/views → SCSS BEM.
8. **390px**: nút "Khôi phục mặc định tab này" bị cắt → 2 nút xếp dọc full width.
9. **Dashboard card "Sitemap XML: Sẵn sàng"** đang trỏ sitemap của Rank Math → khi RM active ghi "Đang dùng sitemap Rank Math"; Laca sitemap
   "Sắp có (Phase 3)".
10. Xoá thư mục `doc/laca-seo/screenshots/` (yêu cầu lần 2) — screenshot để ở thư mục scratch ngoài theme.

## D. Nghiệm thu
- Gửi kèm output NGUYÊN VĂN: compare-head (bảng + chi tiết DIFF), test-variables (bảng), lệnh curl export + `json_decode` + số key.
- `php -l`, `yarn build`, `yarn lint` 0 error. Puppeteer trong WP admin thật: 5 view × 1440/900/390, overflowX = 0, 0 lỗi console,
  kiểm notice KHÔNG nằm trong header (`document.querySelector('.laca-seo__header .notice') === null`).
- Không commit/push, không tắt Rank Math, không tạo dữ liệu giả.
