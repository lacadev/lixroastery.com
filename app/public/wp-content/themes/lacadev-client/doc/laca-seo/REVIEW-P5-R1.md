# Review Phase 5 — Vòng 1 (Claude)

## Không đạt: test-phase5.php KHÔNG so với Rank Math
`grep -c "RankMath\\" test-phase5.php` = **0**. Các ca chỉ tự kiểm code Laca, vài ca là kiểm hình thức:
- `VS2`: `file_exists(sitemap-xsl.php)`; `R1`: PASS khi output chứa chuỗi `BAD URLS:` (bất kể bao nhiêu URL lệch); `B6`: chỉ kiểm alias đã đăng ký;
- `T1–T8`: dựng mảng JSON-LD rồi kiểm Laca xuất lại mảng đó — không có output RM để so.
Spec P5.5 yêu cầu **mỗi ca so output Laca với RM trên cùng dữ liệu**. Viết lại toàn bộ theo cách dưới (đã dùng thành công ở Phase 3B).

## Cách so với RM (bắt buộc)
Mỗi ca chạy 2 tiến trình con `render` (mẫu: `/private/tmp/claude-501/-Users-lacadev-Documents-wordpress-lixroastery/e4577ea8-8f21-416f-8a77-913513a06e67/scratchpad/p3b/render.php`):
mode `rm` (RM active) và mode `laca` (force), cùng các hook nạp TRƯỚC khi WP load (`$GLOBALS['wp_filter']`):
- `pre_option_blog_public` = 1; `pre_option_rank-math-options-general` = bản đã sửa (như render.php);
- **bật module RM trong bộ nhớ**: `option_rank_math_modules` thêm `local-seo`, `news-sitemap`, `video-sitemap` (và module Laca tương ứng qua
  `pre_option_laca_seo_modules`/cách Laca đọc) — RM Pro sẽ tự chạy code thật của nó;
- dữ liệu ca: `get_post_metadata` (schema `rank_math_schema_{Type}` ↔ `_laca_seo_schema_{Type}` cùng nội dung), `the_posts`/`posts_results`
  (thay `post_content` bằng markup block `wp:rank-math/faq-block`… cho cả 2 mode), option local SEO RM ↔ Laca cùng giá trị.
- Template schema + display conditions + locations là CPT: tạo bản ghi RM (`rank_math_schema`, `rank_math_locations`) và Laca tương ứng trong
  `START TRANSACTION`…`ROLLBACK` (KHÔNG gọi dbDelta/DDL trong đó — bài học Phase 4), chỉ xoá id do script tạo ở `finally`, Z1 checksum
  `wp_posts`+`wp_postmeta`+`wp_term_relationships` (chỉ các id liên quan) trước/sau, chạy 2 lần.
So sánh: parse JSON-LD, chuẩn hoá (ksort đệ quy, bỏ `class`), so từng node theo `@id|@type` như `cmp.php`; HTML block/shortcode so text + href sau khi
bỏ tên class; sitemap so `loc`/`lastmod`/`news:*`/`video:*` từng URL (như `scratchpad/p3/sm.php`); KML so từng `Placemark`.
Ca PASS chỉ khi `RM == Laca` và RM output không rỗng (RM rỗng ⇒ FAIL "RM empty" để tránh PASS giả).
`R1`: PASS chỉ khi `cmp.php` in đúng `BAD URLS: 2` và 2 URL đó là `/journal-cat/find-the-origin/` + `/?s=cafe` (lệch đã chấp nhận).

## Lỗi khác
1. **UI tràn ngang**: Puppeteer báo `overflowX=163` — tìm màn/kích thước gây tràn (schema-templates / locations / editor block ở 390px), sửa CSS.
2. **Lỗi 500 trong console** (POST bị abort chỉ sinh `ERR_FAILED`, không sinh 500) ⇒ có request GET/REST trả 500: in URL từng request lỗi
   (`page.on('response')` status ≥ 500), sửa nguyên nhân phía PHP (xem `wp-content/debug.log` nếu bật).
3. Báo cáo theo P5.6, NGUYÊN VĂN output, 2 lần chạy test-phase5 liên tiếp, kèm danh sách URL lỗi 500 trước/sau khi sửa.
Ràng buộc như cũ (không xoá file, không cài package, không sửa plugins/, không tạo dữ liệu thật ngoài transaction, không commit).
