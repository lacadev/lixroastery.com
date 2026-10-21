# Review Phase 3B — Vòng 1 (Claude)

Claude tự so `<head>` + JSON-LD trong CLI, mỗi URL một tiến trình, `blog_public=1` + option RM general đã sửa nạp bộ nhớ:
`/private/tmp/claude-501/-Users-lacadev-Documents-wordpress-lixroastery/e4577ea8-8f21-416f-8a77-913513a06e67/scratchpad/p3b/{render.php,cmp.php}`
(`php cmp.php` — so title, robots, description, mọi `og:*`/`twitter:*`/`article:*`/`product:*`, canonical/prev/next và từng node JSON-LD theo `@id|@type`).

## Đạt
JSON-LD: số node và nội dung từng node khớp RM trên 19/19 URL (trừ BreadcrumbList trang search chứa tham số force — artifact của công cụ, chấp nhận). Robots/canonical khớp khi public.

## A. Lệch so với RM (công cụ `compare-head-cli.php` của bạn không bắt được — nó không so hết thẻ OG)
1. **`og:updated_time` thiếu** (/, /manifesto/, 2 journal): port `publish_date()` trong `RM/includes/opengraph/class-facebook.php:307-317` (chỉ xuất khi modified > published) cùng `article:published_time/modified_time` nếu RM có.
2. **`og:image:alt` thiếu** (product): port phần image alt trong RM OpenGraph Image (`RM/includes/opengraph/class-image.php`).
3. **`og:locale:alternate` thiếu** (vi_VN trên hầu hết URL): đến từ tích hợp Polylang của RM `RM/includes/3rdparty/polylang/class-polylang.php` — port vào `Compat/Polylang.php` (và mọi xử lý khác trong file đó: canonical/sitemap/breadcrumb… nếu có ảnh hưởng output).
4. `og:description` có `<p>` ở RM vs Laca strip — đã chấp nhận từ Phase 1, giữ nguyên.
Sau khi sửa: rà toàn bộ `RM/includes/opengraph/*.php` + `RM/includes/3rdparty/polylang/` xem còn thẻ nào chưa port.

## B. Cấm đọc dữ liệu `rank_math_*` (quy tắc từ Phase 1) — đang vi phạm ở:
- `Sitemap/Sitemap.php:73,110,134`, `Sitemap/Providers/PostType.php:132,226,262`, `Providers/Taxonomy.php:142`, `Providers/Author.php:133`, `Sitemap/Html/Posts.php:99`
  (JOIN/đọc `rank_math_robots`, `rank_math_canonical_url`, `rank_math_title`) ⇒ sửa robots trong Laca sẽ không có tác dụng vì RM meta cũ vẫn chi phối.
- `Schema/Db.php:11,46` (fallback `rank_math_schema_*`), `Schema/Snippets/Singular.php:64` (`rank_math_rich_snippet`).
- `Modules/WooCommerce/WooCommerce.php:68` (`_rank_math_gtin_code`) — dùng `_laca_seo_gtin_code` (importer đã map).
- `Admin/views/instant-indexing.php:132` (`rank_math_indexnow_log`) — dùng `laca_seo_indexnow_log`.
- `Admin/views/dashboard.php:20` (JOIN `rank_math_description`).
Chỉ `ImportExport/*`, `Admin/Notices.php` (đếm dữ liệu chưa chuyển) và các chỗ kiểm `isRankMathActive()`/shim hàm-shortcode được phép nhắc `rank_math_`. Sau khi sửa: chạy lại `RankMathImporter` meta (idempotent) nếu cần và xác nhận output không đổi.
- `WooCommerce.php:132` dùng jQuery cho sự kiện variation của Woo (`found_variation` là jQuery event) — chấp nhận nhưng đổi tên field JS `rank_math_gtin` → `laca_seo_gtin` và ghi comment WHY 1 dòng.

## C. Nghiệm thu
1. Chạy `scratchpad/p3b/cmp.php` (của Claude) — dán NGUYÊN VĂN output: mọi URL diffs=0 ngoại trừ `og:description <p>` và BreadcrumbList search (đã chấp nhận). Sửa `compare-head-cli.php` để so đủ mọi thẻ như `cmp.php`.
2. `grep -rn "rank_math_" app/src/Features/LacaSeo` — dán output, chỉ còn các chỗ được phép ở mục B.
3. `simulate-cutover.php`, `compare-sitemap.php`, `compare-breadcrumbs.php` vẫn PASS.
4. Truy vấn đối chiếu meta Laca vs RM (post + term): dán kết quả (ngoài 3 meta thừa đã biết ở 908/866 — chờ người dùng quyết, không được xoá).
5. `php -l`, `yarn build`, `yarn lint`. Không xoá file/meta, không cài package, không commit.
