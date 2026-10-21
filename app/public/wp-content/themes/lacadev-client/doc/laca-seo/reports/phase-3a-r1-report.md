# Báo cáo Nghiệm thu Laca SEO Phase 3A — Vòng R1 (Antigravity)

**Task ID:** `5cda0f64-3432-4580-b590-ab4b7d237cfe`  
**Ngày thực hiện:** 21/10/2026  
**Tham chiếu:** `wp-content/themes/lacadev-client/doc/laca-seo/REVIEW-P3A-R1.md`

---

## 1. Tóm tắt công việc đã thực hiện

### Mục A: Lỗi bắt buộc sửa
1. **Robots.txt (`Frontend/RobotsTxt.php` & `Sitemap/Sitemap.php` & `Fields/GeneralFields.php` & `RankMathImporter.php`):**
   - Sửa logic `Frontend/RobotsTxt.php`: Khi `blog_public = 0`, trả về nguyên trạng `$content` (không tự chèn `Disallow: /`). Chỉ đăng ký filter `robots_txt` khi `!is_admin() && !empty(trim(Settings::get('general.robots_txt_content')))`.
   - Chuyển directive `Sitemap:` về đúng module `Sitemap/Sitemap.php`: Đăng ký hook `robots_txt` priority `0`, tuân thủ điều kiện như Rank Math (`0 === (string)$is_public` và không trùng nếu chuỗi đã có `Sitemap:`).
   - Đặt default registry `robots_txt_content` về `''` (bỏ nhúng domain local).
   - Cập nhật `RankMathImporter.php`: Fallback gán default rỗng cho các field general thiếu trong Rank Math; gán `'off'` cho post types / taxonomies thiếu trong sitemap settings.
   - Đã chạy lại "Nhập cài đặt từ Rank Math" (`RankMathImporter::importSettings()`), giá trị `robots_txt_content` của LIX đã về `''`.

2. **Breadcrumbs (`Frontend/Breadcrumbs.php`):**
   - Port nhánh `canGenerate()` của Rank Math: Thêm `|| is_paged()`. Sửa lỗi URL `/journal-cat/find-the-origin/page/2/` bị rỗng. Kết quả đạt 19/19 URL.

3. **Template Tags (`app/helpers/template_tags.php`):**
   - Đã bổ sung 1 dòng comment WHY trên shims `rank_math_the_breadcrumbs` / `rank_math_get_breadcrumbs` giải thích lý do chỉ fallback khi plugin Rank Math không active.

4. **Multi-language Sitemap URL Rewrite (`Compat/Polylang.php` & `LacaSeo.php`):**
   - Đăng ký `Polylang` compat chạy ở cả frontend và admin.
   - Bổ sung filter `laca_seo/sitemap/xml_post_url` để chuyển URL trang chủ sang URL ngôn ngữ tương ứng (`/vi/`, `/en/`) giống Rank Math.

### Mục B: Viết lại và bổ sung công cụ nghiệm thu
1. `doc/laca-seo/tools/compare-breadcrumbs.php`:
   - Viết lại so HTML thực tế giữa `RankMath\Frontend\Breadcrumbs::get_breadcrumb()` và `App\Features\LacaSeo\Frontend\Breadcrumbs::getBreadcrumb()`. Nạp option `rank-math-options-general` đã sửa vào bộ nhớ trước khi WP nạp.
2. `doc/laca-seo/tools/compare-sitemap.php`:
   - Viết lại duyệt động toàn bộ sitemap con từ index của Rank Math (`page`, `product`), so sánh `loc`, `lastmod`, mảng ảnh (`image:loc`), thứ tự. Lệch ảnh = FAIL.
3. `doc/laca-seo/tools/simulate-cutover.php`:
   - Viết mới công cụ mô phỏng trạng thái sau khi tắt Rank Math trong bộ nhớ:
     - Hook `option_active_plugins` lọc bỏ Rank Math.
     - Hook `pre_option_rewrite_rules` trả rules sinh từ `$wp_rewrite->rewrite_rules()`.
     - Chạy từng URL trong tiến trình độc lập, bắt HTTP status, header redirect và output body.
     - Kiểm thử: `/sitemap_index.xml`, `/page-sitemap.xml`, `/product-sitemap.xml`, `/sitemap.xml` (301), `/wp-sitemap.xml` (301), `/main-sitemap.xsl`, `/robots.txt`, IndexNow `/{key}.txt`, `/llms.txt` (404 khi module tắt).

---

## 2. Danh sách file đã sửa / tạo mới

### Modified:
- `app/helpers/template_tags.php`
- `app/src/Features/LacaSeo/Compat/Polylang.php`
- `app/src/Features/LacaSeo/Core/Settings.php`
- `app/src/Features/LacaSeo/Fields/GeneralFields.php`
- `app/src/Features/LacaSeo/Frontend/Breadcrumbs.php`
- `app/src/Features/LacaSeo/Frontend/RobotsTxt.php`
- `app/src/Features/LacaSeo/ImportExport/RankMathImporter.php`
- `app/src/Features/LacaSeo/LacaSeo.php`
- `app/src/Features/LacaSeo/Sitemap/Providers/PostType.php`
- `app/src/Features/LacaSeo/Sitemap/Providers/Taxonomy.php`
- `app/src/Features/LacaSeo/Sitemap/Router.php`
- `app/src/Features/LacaSeo/Sitemap/Sitemap.php`
- `doc/laca-seo/tools/compare-breadcrumbs.php`
- `doc/laca-seo/tools/compare-sitemap.php`

### Added:
- `doc/laca-seo/tools/simulate-cutover.php`

---

## 3. Kết quả nghiệm thu (NGUYÊN VĂN OUTPUT)

### 3.1. `simulate-cutover.php`
```
========================================================================
 LACA SEO CUTOVER SIMULATION TOOL (Phase 3A - R1)
========================================================================

/sitemap_index.xml                     (Sitemap Index) ... [PASS] (2 sub-sitemaps match Rank Math index)
/page-sitemap.xml                      (Page Sub-sitemap) ... [PASS] (25 URLs, lastmod & images match 100%)
/product-sitemap.xml                   (Product Sub-sitemap) ... [PASS] (13 URLs, lastmod & images match 100%)
/sitemap.xml                           (Redirect /sitemap.xml -> /sitemap_index.xml) ... [PASS] (Redirect 301 -> http://lixroastery.local/sitemap_index.xml)
/wp-sitemap.xml                        (Redirect /wp-sitemap.xml -> /sitemap_index.xml) ... [PASS] (Redirect 301 -> http://lixroastery.local/sitemap_index.xml)
/main-sitemap.xsl                      (Main Sitemap XSL Stylesheet) ... [PASS] (Valid XSL stylesheet, 5162 bytes)
/robots.txt                            (Robots.txt Output) ... [PASS] (9 directives identical to Rank Math)
/3334f2373af24b28be374ed0b983b788.txt  (IndexNow API Key Endpoint) ... [PASS] (Content matches expected key)
/llms.txt                              (LLMS.txt (404 when module inactive)) ... [PASS] (HTTP 404 as expected when inactive)

========================================================================
 RESULT: ALL CUTOVER SIMULATION CHECKS PASSED (100% ROUTER & LOGIC PARITY)
========================================================================
```

### 3.2. `compare-sitemap.php`
```
========================================================================
 LACA SEO vs RANK MATH - SITEMAP COMPARISON TOOL (Phase 3A - R1)
========================================================================

INDEX RM: 2 sub-sitemaps | Laca: 2 (200 text/xml; charset=UTF-8)
  page-sitemap.xml -> OK
  product-sitemap.xml -> OK

## Sub-sitemap page-1 (RM: 25, Laca: 25) - Diffs: 0

## Sub-sitemap product-1 (RM: 13, Laca: 13) - Diffs: 0

XSL Stylesheet Laca: HTTP 200 text/xml; charset=UTF-8, Length: 5162 bytes

========================================================================
 RESULT: ALL SITEMAP CHECKS PASSED (100% PARITY WITH RANK MATH, 0 DIFFS)
========================================================================
```

### 3.3. `compare-breadcrumbs.php`
```
========================================================================
 LACA SEO vs RANK MATH - BREADCRUMBS COMPARISON TOOL (Phase 3A)
========================================================================

Frontpage (VI)           [PASS]
Frontpage (EN)           [PASS]
Single Page              [PASS]
Single Product           [PASS]
Single Journal           [PASS]
Single Journal (EN)      [PASS]
Shop Page                [PASS]

Warning: Attempt to read property "post_parent" on null in /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/plugins/seo-by-rank-math/includes/frontend/class-breadcrumbs.php on line 284

Warning: Attempt to read property "post_parent" on null in /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/plugins/seo-by-rank-math/includes/frontend/class-breadcrumbs.php on line 284
Shop Page (EN)           [PASS]
Journal Archive          [PASS]
Journal Page 2           [PASS]
Category Archive         [PASS]
Product Cat Archive      [PASS]
Journal Cat Archive      [PASS]
Journal Cat Page 2       [PASS]
Tag Archive              [PASS]
Attachment Page          [PASS]
Author Archive           [PASS]
Search Results           [PASS]
404 Not Found            [PASS]

========================================================================
 RESULT: ALL 19 BREADCRUMB TRAILS MATCH 100% (0 DIFFERENCES WITH RANK MATH)
========================================================================
```

### 3.4. `compare-head.php` (19/19 URL)
```
========================================================================
 LACA SEO vs RANK MATH - EXPANDED HEAD COMPARISON TOOL (Phase 1)
========================================================================

Processing: Frontpage (VI) (http://lixroastery.local/)...
Processing: Frontpage (EN) (http://lixroastery.local/en/)...
Processing: Single Page (http://lixroastery.local/manifesto/)...
Processing: Single Product (http://lixroastery.local/product/aconchego-1-masterpieces-auction-2025/)...
Processing: Single Journal (http://lixroastery.local/journal/extremely-light-cua-lix-roastery/)...
Processing: Single Journal (EN) (http://lixroastery.local/journal/lix-roastery-at-cafe-show-hanoi-2026/)...
Processing: Shop Page (http://lixroastery.local/shop/)...
Processing: Shop Page (EN) (http://lixroastery.local/en/shop/)...
Processing: Journal Archive (http://lixroastery.local/journal/)...
Processing: Journal Page 2 (http://lixroastery.local/journal/page/2/)...
Processing: Category Archive (http://lixroastery.local/category/uncategorized/)...
Processing: Product Cat Archive (http://lixroastery.local/product-category/ca-phe-specialty/)...
Processing: Journal Cat Archive (http://lixroastery.local/journal-cat/find-the-origin/)...
Processing: Journal Cat Page 2 (http://lixroastery.local/journal-cat/find-the-origin/page/2/)...
Processing: Tag Archive (http://lixroastery.local/tag/arabica/)...
Processing: Attachment Page (http://lixroastery.local/journal/lix-roastery-tai-cafe-show-hanoi-2026/cafe-show-4/)...
Processing: Author Archive (http://lixroastery.local/author/lacadev/)...
Processing: Search Results (http://lixroastery.local/?s=cafe)...
Processing: 404 Not Found (http://lixroastery.local/url-khong-ton-tai-404-test/)...

========================================================================================================================
 COMPARISON SUMMARY TABLE
========================================================================================================================

Page / Type            | Title  | Desc   | Robots | Canon  | OG Typ | OG Tit | OG Img | Twit   | Loc   
------------------------------------------------------------------------------------------------
Frontpage (VI)         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Frontpage (EN)         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Single Page            | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Single Product         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Single Journal         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Single Journal (EN)    | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Shop Page              | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Shop Page (EN)         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Journal Archive        | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Journal Page 2         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Category Archive       | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Product Cat Archive    | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Journal Cat Archive    | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Journal Cat Page 2     | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Tag Archive            | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Attachment Page        | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Author Archive         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
Search Results         | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
404 Not Found          | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH  | MATCH 
```

### 3.5. `php -l`
```
No syntax errors detected in app/helpers/template_tags.php
No syntax errors detected in app/src/Features/LacaSeo/Frontend/RobotsTxt.php
No syntax errors detected in app/src/Features/LacaSeo/Frontend/Breadcrumbs.php
No syntax errors detected in app/src/Features/LacaSeo/Fields/GeneralFields.php
No syntax errors detected in app/src/Features/LacaSeo/ImportExport/RankMathImporter.php
No syntax errors detected in app/src/Features/LacaSeo/LacaSeo.php
No syntax errors detected in app/src/Features/LacaSeo/Compat/Polylang.php
No syntax errors detected in app/src/Features/LacaSeo/Sitemap/Sitemap.php
No syntax errors detected in app/src/Features/LacaSeo/Sitemap/Router.php
No syntax errors detected in app/src/Features/LacaSeo/Sitemap/Providers/PostType.php
No syntax errors detected in app/src/Features/LacaSeo/Sitemap/Providers/Taxonomy.php
No syntax errors detected in app/src/Features/LacaSeo/Modules/Llms/LlmsTxt.php
No syntax errors detected in doc/laca-seo/tools/compare-breadcrumbs.php
No syntax errors detected in doc/laca-seo/tools/compare-sitemap.php
No syntax errors detected in doc/laca-seo/tools/simulate-cutover.php
```

### 3.6. `yarn lint`
```
yarn run v1.22.22
$ run-s -cl lint:scripts lint:styles
[lint:scripts] $ wp-scripts lint-js 'resources/scripts' 'block-gutenberg'
[lint:scripts] 
[lint:scripts] /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/block-gutenberg/lixroastery-blocks-site/block-cta-section/edit.js
[lint:scripts]   67:5  warning  React Hook useEffect has missing dependencies: 'attributes.__isPreview', 'attributes.blockId', 'clientId', and 'setAttributes'. Either include them or remove the dependency array. If 'setAttributes' changes too often, find the parent component that defines it and wrap that definition in useCallback  react-hooks/exhaustive-deps
[lint:scripts] 
[lint:scripts] /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/block-gutenberg/lixroastery-blocks-site/block-journal-grid/edit.js
[lint:scripts]   153:5  warning  React Hook useEffect has a missing dependency: 'setAttributes'. Either include it or remove the dependency array. If 'setAttributes' changes too often, find the parent component that defines it and wrap that definition in useCallback  react-hooks/exhaustive-deps
[lint:scripts] 
[lint:scripts] /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/block-gutenberg/lixroastery-blocks-site/block-partnership-grid/edit.js
[lint:scripts]   136:5  warning  React Hook useEffect has a missing dependency: 'setAttributes'. Either include it or remove the dependency array. If 'setAttributes' changes too often, find the parent component that defines it and wrap that definition in useCallback  react-hooks/exhaustive-deps
[lint:scripts] 
[lint:scripts] /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/block-gutenberg/utils/inspector-panels.js
[lint:scripts]   215:39  warning  'textdomain' is defined but never used  no-unused-vars
[lint:scripts] 
[lint:scripts] /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/resources/scripts/theme/components/cart-popup.js
[lint:scripts]   81:9  warning  Variables should not be assigned until just prior its first reference. An early return statement may leave this variable unused  @wordpress/no-unused-vars-before-return
[lint:scripts] 
[lint:scripts] /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/resources/scripts/theme/components/cpt-grid.js
[lint:scripts]   40:8  warning  Variables should not be assigned until just prior its first reference. An early return statement may leave this variable unused  @wordpress/no-unused-vars-before-return
[lint:scripts]   41:8  warning  Variables should not be assigned until just prior its first reference. An early return statement may leave this variable unused  @wordpress/no-unused-vars-before-return
[lint:scripts] 
[lint:scripts] /Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/resources/scripts/theme/components/mobile-menu.js
[lint:scripts]   31:3  warning  Expected an assignment or function call and instead saw an expression  no-unused-expressions
[lint:scripts] 
[lint:scripts] ✖ 8 problems (0 errors, 8 warnings)
[lint:scripts] 
[lint:styles ] $ stylelint 'resources/styles/**/*.{css,scss}'
✨  Done in 5.90s.
```

### 3.7. `yarn build`
```
yarn run v1.22.22
$ yarn build:theme && yarn build:blocks && yarn critical
...
webpack 5.109.2 compiled successfully in 4421 ms
...
Starting Critical CSS generation for 7 page types...
Extracting critical CSS [home] from: http://lixroastery.local/?lacadev_no_critical=1
✅ critical-home.css (32.2 KB)
⚠️ [shop] đang là trang Coming soon — giữ critical-shop.css cũ (đã cập nhật @font-face; đặt CRITICAL_COOKIE để sinh lại).
⚠️ [product] đang là trang Coming soon — giữ critical-product.css cũ (đã cập nhật @font-face; đặt CRITICAL_COOKIE để sinh lại).
Extracting critical CSS [journal] from: http://lixroastery.local/journal/?lacadev_no_critical=1
✅ critical-journal.css (32.0 KB)
Extracting critical CSS [single] from: http://lixroastery.local/journal/lix-roastery-at-cafe-show-hanoi-2026/?lacadev_no_critical=1
✅ critical-single.css (29.1 KB)
Extracting critical CSS [page-hero] from: http://lixroastery.local/manifesto/?lacadev_no_critical=1
✅ critical-page-hero.css (32.9 KB)
Extracting critical CSS [page] from: http://lixroastery.local/contact/?lacadev_no_critical=1
✅ critical-page.css (40.7 KB)
✨  Done in 61.95s.
```
