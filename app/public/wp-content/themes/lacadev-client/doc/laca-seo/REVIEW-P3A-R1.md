# Review Phase 3A — Vòng 1 (Claude)

Claude đã tự kiểm độc lập (script ở `/private/tmp/claude-501/-Users-lacadev-Documents-wordpress-lixroastery/e4577ea8-8f21-416f-8a77-913513a06e67/scratchpad/p3/`: `sm.php`, `bc2.php`, `misc.php`).

## Đạt
- **XML sitemap**: duyệt MỌI sitemap con từ index RM → `page` 25/25, `product` 13/13; `loc`, `lastmod`, danh sách ảnh, thứ tự: **0 khác**. Index lastmod khớp. XSL 200 `text/xml`.
- **Breadcrumbs** (so HTML thật, RM chạy với option general đã sửa nạp trong bộ nhớ): 18/19 URL khớp tuyệt đối. Laca gắn cả `laca-seo-breadcrumb rank-math-breadcrumb` — chấp nhận (tương thích CSS cũ).
- IndexNow route `/{key}.txt` khớp RM; gửi thật bị chặn ở local. HTML sitemap: cả 2 rỗng (LIX không bật `pt_*_html_sitemap`) — khớp. llms.txt 404 (module tắt như RM) — đúng.

## Lưu ý quan trọng về dữ liệu local
`rank-math-options-general` trong DB local bị hỏng serialize ⇒ RM runtime đọc **mặc định** (vd `breadcrumbs=false`), trong khi giá trị thật (importer sửa được) là `on`. Mọi phép so sánh qua HTTP liên quan settings General đều sai lệch. Phải so trong CLI với option đã sửa **nạp trong bộ nhớ trước khi WP load** (không ghi DB) — xem `bc2.php` (pre-initialized hook `pre_option_rank-math-options-general`).

## A. Lỗi bắt buộc sửa

1. **robots.txt sai so với RM (nghiêm trọng)** — `Frontend/RobotsTxt.php`:
   - Khi `blog_public=0` Laca trả `User-agent: *\nDisallow: /` (bỏ hết nội dung khác). RM (`modules/robots-txt/class-robots-txt.php:50-52`) trả nguyên `$content`. Local đang `blog_public=0`: RM ra nội dung bình thường, Laca ra `Disallow: /`.
   - RM chỉ hook `robots_txt` khi `!is_admin() && general.robots_txt_content` khác rỗng; public ⇒ trả đúng nội dung setting (không tự chèn gì).
   - Dòng `Sitemap:` do module sitemap thêm riêng: `modules/sitemap/class-sitemap-index.php:30-52` (filter `robots_txt` priority 0, điều kiện của RM) — port đúng điều kiện đó (module sitemap bật…), không chèn trong RobotsTxt.
   - Default registry `robots_txt_content` đang nhúng `get_home_url()` lúc build registry ⇒ giá trị lưu trên LIX hiện là chuỗi có `http://lixroastery.local/...` (RM KHÔNG có key này/không default). Đặt default `''` như RM; importer: key General thiếu trong RM ⇒ rỗng (giống cách Phase 1 xử lý Titles). Sau khi sửa, chạy lại "Nhập cài đặt từ Rank Math" (tự backup) để giá trị LIX về `''`, ghi rõ trong báo cáo.
2. **Breadcrumb `/journal-cat/find-the-origin/page/2/`**: RM `<span class="last">Home Page</span>`, Laca rỗng. Tìm nhánh RM xử lý (trang phân trang vượt số trang/404 trong term…) và port đúng.
3. `template_tags.php`: định nghĩa `laca_seo_the_breadcrumbs`/`laca_seo_get_breadcrumbs` + shim `rank_math_*` trong helper là được, nhưng shim chỉ được tạo khi RM **không** load (plugin load trước theme nên `function_exists` đúng) — giữ, thêm comment WHY 1 dòng.

## B. Công cụ nghiệm thu phải sửa (kết quả cũ không đủ tin)
1. `compare-breadcrumbs.php`: thay mô phỏng chỉ so mảng crumb bằng so **HTML** `RankMath\Frontend\Breadcrumbs::get_breadcrumb()` vs `Laca Breadcrumbs::getBreadcrumb()` với option RM đã sửa nạp trong bộ nhớ (cách `bc2.php`), chuẩn hoá class. Kỳ vọng 19/19.
2. `compare-sitemap.php`: lấy danh sách sitemap con **từ index RM** (không viết cứng), mọi trang n, so `loc` + `lastmod` + danh sách ảnh + thứ tự; lệch ảnh là FAIL (không phải INFO) — như `sm.php`.
3. **Mới — `doc/laca-seo/tools/simulate-cutover.php` (CLI)**: mô phỏng trạng thái SAU khi tắt RM mà không đụng DB: hook nạp trước khi WP load `option_active_plugins` (bỏ `seo-by-rank-math*`), option general RM đã sửa không cần (RM tắt). Rewrite: `pre_option_rewrite_rules` trả rules sinh trong bộ nhớ (`$wp_rewrite->rewrite_rules()` sau `init`). Mỗi URL chạy **một tiến trình php riêng** (vì handler `exit`), bắt output + status + header `Location`:
   `/sitemap_index.xml`, `/page-sitemap.xml`, `/product-sitemap.xml`, `/sitemap.xml` (301 → index), `/wp-sitemap.xml` (redirect như RM), `/main-sitemap.xsl`, `/robots.txt`, `/{indexnow_key}.txt`, `/llms.txt` (404 khi module tắt).
   So với output RM thật (chạy cùng URL khi RM active qua HTTP, robots dùng option đã sửa). Kỳ vọng: XML giống tập URL/lastmod/ảnh; robots giống từng dòng (trừ dòng comment credit RM).
   Đây là phép thử quan trọng nhất trước cutover: router thật (`sitemap_index.xml` → `laca_sitemap=1` vs nhánh force dùng `index`) chưa từng được chạy.

## C. Nghiệm thu
Dán NGUYÊN VĂN output: compare-sitemap (bản mới), compare-breadcrumbs (bản mới), simulate-cutover, compare-head (19/19), `php -l`, `yarn build`, `yarn lint`. Không xoá file, không cài package, không commit; khôi phục dữ liệu; script/ảnh tạm ngoài theme.
