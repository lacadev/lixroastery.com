# Review Phase 3B — Vòng 2 (Claude)

## Đã đạt sau R1 (Claude tự đo)
- `scratchpad/p3b/cmp.php` 19 URL: chỉ còn 2 lệch đã chấp nhận (`og:description` có `<p>` ở RM; BreadcrumbList trang search chứa tham số force — artifact công cụ).
- Không còn đọc `rank_math_*` ngoài importer/notice/shim. Sitemap 0 khác, breadcrumbs 0 lệch, simulate-cutover PASS, `php -l` sạch, meta Laca vs RM 0 lệch giá trị.
⇒ Phần ảnh hưởng output frontend đã sẵn sàng cutover.

## Còn thiếu — P3B.2 Schema Generator (tab Schema trong metabox)
Hiện tại: 1 select loại + chỉ 2 ô (Headline, Description) cho MỌI loại; mỗi bài 1 schema. Spec yêu cầu form field theo đúng map field của RM.

Nguồn (chỉ đọc):
- Định nghĩa form/field từng loại: bundle `RM/includes/modules/schema/assets/js/schema-gutenberg.js` (+ `schema-template.js`) — trích như cách Claude trích analyzer
  (tìm theo tên field, vd `startDate`, `eventStatus`, `offers`, `performer`, `recipeIngredient`…).
- Định dạng dữ liệu đầu ra/hiển thị: `RM/includes/modules/schema/shortcode/*.php`, `class-frontend.php`, `class-db.php` (cấu trúc meta `rank_math_schema_{Type}` gồm `metadata` {title, type, shortcode, isPrimary, reviewLocation…} + các property schema.org).

Yêu cầu:
1. Mỗi loại free (Article/BlogPosting/NewsArticle, Book, Course, Event, JobPosting, Music, Product, Recipe, Restaurant, Video, Person, Service,
   SoftwareApplication) có đủ field như RM: kiểu input tương ứng (text, textarea, number, date/datetime, select với cùng choices, repeater cho nhóm lặp
   như offers/reviews/ingredients/steps…), giá trị mặc định dùng biến (`%seo_title%`, `%seo_description%`, `%url%`…) y RM.
2. Nhiều schema/bài; đặt 1 schema làm chính (`isPrimary`); xoá; "Dùng mặc định theo loại bài".
3. Lưu `_laca_seo_schema_{Type}` **đúng cấu trúc** `rank_math_schema_{Type}` (để importer/so sánh 1:1). Không đọc meta RM.
4. Kiểm chứng **KHÔNG ghi DB** (dán NGUYÊN VĂN):
   - Tạo bản sao `scratchpad/p3b/render.php` thành `render-schema.php` (để ngoài theme, trong /private/tmp) có thêm hook nạp trước WP load
     `get_post_metadata` trả cùng một bộ schema (Event đầy đủ field cho bài 908, Product có offers/review cho product 29) cho cả key
     `rank_math_schema_*` và `_laca_seo_schema_*` (đúng cấu trúc RM). So JSON-LD RM vs Laca ⇒ diffs=0 trên node schema.
   - Lưu qua UI: Puppeteer (cookie runtime) trên bài 908 chọn Event, điền field, bấm lưu; **chặn request lưu bằng `page.setRequestInterception`**
     (abort sau khi đọc payload) → in payload, chứng minh đúng cấu trúc `rank_math_schema_Event` → không có gì ghi vào DB.
   - Truy vấn đối chiếu meta Laca vs RM sau cùng: không có meta mới.
5. UI: theo style metabox Phase 2; repeater có nút thêm/xoá; 1440/900/390 không tràn; 0 lỗi console. Nhãn tiếng Việt + tooltip ngắn.
6. `php -l`, `yarn build`, `yarn lint`. Không xoá file, không cài package, không sửa plugins/, không commit.
