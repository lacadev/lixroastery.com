# 🚀 LACA SEO CUTOVER PROCEDURE (PRODUCTION CHECKLIST)

> **Mục tiêu:** Chuyển giao hoàn toàn quyền điều khiển SEO trên website LIX Roastery từ plugin Rank Math (Free + Pro) sang **Laca SEO** (theme module tích hợp sẵn) với **0 gián đoạn (zero downtime)**, **0 sai lệch meta/sitemap/schema (100% parity)** và **0 rủi ro mất thứ hạng tìm kiếm**.

---

## 📋 TỔNG QUAN NGUYÊN TẮC AN TOÀN

1. **Auto-Yield (Tự động nhường):** Laca SEO frontend và rewrite endpoints được thiết kế theo cơ chế *safety-first*: khi Rank Math còn active (`defined('RANK_MATH_VERSION')`), Laca SEO tự động nhường hoàn toàn cho Rank Math (chỉ phục vụ dev khi có cờ `laca_seo_force_frontend=1` hoặc CLI).
2. **Instant Activation:** Khi tắt Rank Math, Laca SEO tự động kích hoạt ngay lập tức trên frontend mà không cần khởi động lại server.
3. **Rollback 1 Click:** Nếu xảy ra bất kỳ sự cố không mong muốn nào, quản trị viên chỉ cần kích hoạt lại plugin Rank Math, Laca SEO sẽ lập tức nhường lại quyền điều khiển.

---

## ⚠️ LƯU Ý RIÊNG CHO LIX (Claude bổ sung)

- **Triển khai code trước:** parent theme `lacadev-client` (có Laca SEO) + `dist/` của child phải lên production trước (theo quy trình release
  thường lệ của theme), Rank Math vẫn bật — Laca tự nhường nên không đổi gì ngoài frontend.
- **Làm trên staging trước:** sao chép DB production sang staging, chạy toàn bộ Bước 2–5 ở đó. DB local có option `rank-math-options-general`
  và nội dung bài bị lỗi mã hoá (mất `\` trong `<`, chữ Việt vỡ) nên kết quả local KHÔNG đại diện cho production.
- **Công cụ `doc/laca-seo/tools/*.php` chỉ chạy bằng PHP-CLI** (web trả 404). Nếu hosting không có CLI thì chạy trên staging.
- **Khác biệt đã chấp nhận** (không tính là lỗi ở Bước 3): `og:description`/`twitter:description` của term — RM để lọt `<p>`, Laca bỏ.
  Mọi khác biệt khác phải bằng 0.
- **Cache:** LIX dùng **LiteSpeed Cache** → sau khi tắt RM: LiteSpeed › Purge All (gồm cả CSS/JS đã tối ưu). Có Cloudflare thì purge thêm.
- **IndexNow:** sau cutover Laca tự gửi IndexNow khi publish/cập nhật post type đã chọn (dùng lại key hiện có của RM, đường dẫn `/{key}.txt` không đổi).
- **Rollback có giới hạn:** bật lại RM sẽ quay về dữ liệu `rank_math_*` cũ — mọi chỉnh sửa SEO làm trong Laca SAU cutover sẽ không có bên RM.
  Vì vậy sau cutover nên theo dõi 1–2 tuần (Search Console: Coverage, Sitemaps, Rich results) trước khi chỉnh SEO hàng loạt.
- **Không gỡ (delete) Rank Math** cho tới khi ổn định: chỉ Deactivate — dữ liệu RM còn nguyên là đường lui.

---

## 🛠️ QUY TRÌNH THỰC HIỆN CUTOVER (5 BƯỚC CHUẨN)

### BƯỚC 1: SAO LƯU DỮ LIỆU (PRE-CUTOVER BACKUP)
* [ ] **Sao lưu Database đầy đủ:**
  ```bash
  wp db export backup_pre_cutover_$(date +%Y%m%d_%H%M%S).sql
  ```
  *(Đảm bảo các bảng `wp_options`, `wp_postmeta`, `wp_termmeta`, `wp_usermeta` được lưu trữ an toàn).*
* [ ] **Sao lưu file `.htaccess` / Nginx config và `wp-config.php`** nếu có tùy biến rewrite.

---

### BƯỚC 2: NHẬP & ĐỒNG BỘ DỮ LIỆU TỪ RANK MATH
* [ ] Truy cập WP Admin: **Laca Admin > SEO > Nhập / Xuất (Import/Export)**.
* [ ] Nhấp tab **Nhập từ Rank Math**:
  - [x] Nhập **Cài đặt SEO (Settings)**: Hệ thống sẽ ánh xạ toàn bộ `rank-math-options-general`, `rank-math-options-titles`, `rank-math-options-sitemap`, `rank-math-options-instant-indexing` sang `laca_seo_*`.
  - [x] Nhập **Metadata (Posts, Terms, Users, Schemas)**: Chạy batch đồng bộ toàn bộ postmeta (`_laca_seo_*`), termmeta, usermeta và per-post custom schema (`_laca_seo_schema_*`).
* [ ] **Xác nhận số lượng bản ghi:** Kiểm tra log hiển thị 100% dữ liệu đã được import mà không có lỗi.

---

### BƯỚC 3: KIỂM TRA MÔ PHỎNG TRÊN STAGING / BẢN SAO BỘ NHỚ
Trước khi tắt Rank Math thật, chạy bộ công cụ CLI xác thực toàn diện:
```bash
# 1. So sánh cài đặt Sitemap:
php -d memory_limit=512M doc/laca-seo/tools/fieldcmp-sitemap.php

# 2. So sánh Sitemap XML thực tế:
php -d memory_limit=512M doc/laca-seo/tools/compare-sitemap.php

# 3. So sánh Breadcrumbs:
php -d memory_limit=512M doc/laca-seo/tools/compare-breadcrumbs.php

# 4. So sánh 19 Canonical URLs (<head> tags, robots, canonical, JSON-LD Graph):
php -d memory_limit=512M doc/laca-seo/tools/compare-head-cli.php

# 5. Mô phỏng Cutover hoàn chỉnh (tắt Rank Math trong bộ nhớ, kiểm tra 5 URLs mẫu + endpoints):
php -d memory_limit=512M doc/laca-seo/tools/simulate-cutover.php
```
*Yêu cầu bắt buộc:* Mọi script đều phải trả về kết quả **PASS / MATCH 100% (0 diffs)**.

---

### BƯỚC 4: THỰC THI CUTOVER CHÍNH THỨC
* [ ] **Tắt Rank Math Free & Pro:**
  - Vào **Plugins > Installed Plugins**.
  - Deactivate cả **Rank Math SEO PRO** và **Rank Math SEO**.
  *(Hoặc qua WP-CLI: `wp plugin deactivate seo-by-rank-math-pro seo-by-rank-math`)*
* [ ] **Flush Rewrite Rules (Làm mới đường dẫn tĩnh):**
  - Vào **Cài đặt > Đường dẫn tĩnh (Settings > Permalinks)** bấm **Lưu thay đổi (Save Changes)**.
  *(Hoặc qua WP-CLI: `wp rewrite flush`)*
* [ ] **Xóa cache toàn trang:**
  - Nếu có plugin caching (WP Rocket, Redis Object Cache, Nginx FastCGI Cache, Cloudflare), tiến hành Purge All Cache.

---

### BƯỚC 5: HẬU KIỂM TRA (POST-CUTOVER VERIFICATION)
Kiểm tra trực tiếp trên trình duyệt hoặc qua curl:
1. **XML Sitemap Index & Sub-sitemaps:**
   - Truy cập `https://lixroastery.com/sitemap_index.xml` (phải trả về HTTP 200, hiển thị XSL stylesheet đẹp mắt, danh sách sub-sitemap đầy đủ).
   - Truy cập `https://lixroastery.com/page-sitemap.xml` và `product-sitemap.xml`.
   - Kiểm tra redirect 301: `https://lixroastery.com/sitemap.xml` -> `/sitemap_index.xml`.
2. **Robots.txt:**
   - Truy cập `https://lixroastery.com/robots.txt` (đảm bảo có directive `Sitemap: https://lixroastery.com/sitemap_index.xml`).
3. **5 URLs Mẫu Trực Tiếp (View Source):**
   - Trang chủ: `https://lixroastery.com/`
   - Sản phẩm: `https://lixroastery.com/product/aconchego-1-masterpieces-auction-2025/`
   - Bài viết Journal: `https://lixroastery.com/journal/extremely-light-cua-lix-roastery/`
   - Cửa hàng: `https://lixroastery.com/shop/`
   - Danh mục sản phẩm: `https://lixroastery.com/product-category/ca-phe-specialty/`
   *Xác nhận:*
   - Thẻ `<title>` và `<meta name="description">` hiển thị chuẩn xác.
   - Thẻ `<meta name="robots" content="...">` đầy đủ chỉ thị (`index, follow, max-snippet:-1, max-video-preview:-1, max-image-preview:large`).
   - Thẻ `<link rel="canonical" href="...">` chính xác.
   - Khối `<script type="application/ld+json" class="laca-seo-schema">` chứa đầy đủ @graph schema entities (WebSite, WebPage/ItemPage, Organization/Person, Product, BreadcrumbList, v.v.).
4. **Google Search Console & Bing Webmaster Tools:**
   - Vào Google Search Console > Sitemaps.
   - Gửi lại URL: `sitemap_index.xml` để Google kích hoạt thu thập dữ liệu qua engine mới.

---

## 🔄 KẾ HOẠCH HOÀN TÁC (ROLLBACK PLAN)

Trong trường hợp phát sinh lỗi kỹ thuật nghiêm trọng không thể xử lý ngay:
1. Vào **Plugins > Installed Plugins**, kích hoạt lại **Rank Math SEO** và **Rank Math SEO PRO**.
   *(Hoặc qua WP-CLI: `wp plugin activate seo-by-rank-math seo-by-rank-math-pro`)*
2. Laca SEO sẽ tự động nhận diện hằng số `RANK_MATH_VERSION` và lập tức nhường lại toàn bộ quyền điều khiển frontend và sitemap cho Rank Math.
3. Flush rewrite rules: `wp rewrite flush`.
4. Toàn bộ site quay trở về trạng thái vận hành cũ 100% an toàn.
