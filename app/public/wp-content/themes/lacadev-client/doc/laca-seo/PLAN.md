# LACA SEO — Kế hoạch triển khai (thay thế Rank Math SEO Free + Pro)

> Tài liệu sống: mỗi phase bắt đầu sẽ bổ sung mục "Spec chi tiết Phase N" ở cuối file.
> Nguồn tham chiếu 1:1: `wp-content/plugins/seo-by-rank-math` (Free 1.0.280, viết tắt **RM/**) và
> `wp-content/plugins/seo-by-rank-math-pro` (Pro 3.0.118, viết tắt **RMP/**). Khi plan ghi "giống RM",
> người code PHẢI mở đúng file RM được nêu để lấy id field, default, choices, logic — không đoán.

Theme parent: `wp-content/themes/lacadev-client` (**P/**). Child: `wp-content/themes/lacadev-client-child` (**C/**).
Mọi code Laca SEO nằm ở **parent** (dùng chung mọi site). Build asset chạy ở child (`yarn build`).

---

## 0. Quyết định đã chốt với chủ dự án

| # | Quyết định |
|---|---|
| D1 | Giao theo phase, mỗi phase: plan chi tiết → Antigravity code → Claude review → sửa → phase kế |
| D2 | **Thay hẳn Rank Math**. Thời điểm gỡ Rank Math trên LIX: **ngay sau khi Phase 3 xong** (đủ meta + sitemap + schema + breadcrumb + WooCommerce + IndexNow = toàn bộ module LIX đang dùng). Trước đó Rank Math vẫn bật trên production; Laca SEO tự tắt output frontend khi phát hiện Rank Math đang active (tránh trùng thẻ) |
| D3 | Tính năng phụ thuộc server ngoài → thay bản local (xem §2) |
| D4 | Mỗi field có **note** 3 dòng: *Tác dụng / Cách làm / Kết quả*. Có **trang Hướng dẫn tối ưu SEO**. Có **Export/Import JSON** để copy config giữa các site |

---

## 1. Ma trận tính năng (Rank Math → Laca SEO)

Ký hiệu: ✅ làm 1:1 · 🔁 làm bản thay thế local · ⏭ bỏ (lý do) · Phase = phase thực hiện.

| Rank Math | Laca SEO | Phase |
|---|---|---|
| Dashboard + bật/tắt module (`rank_math_modules`) | ✅ Trang Dashboard + Modules | 0 |
| Export/Import settings JSON (RM status) | ✅ + chọn phần + backup trước import + đổi domain khi import | 0 |
| Import từ Rank Math (settings + post/term/user meta + redirections) | ✅ (bắt buộc vì D2) | 1 |
| General: Links, Images (free), Breadcrumbs (settings), Webmaster, robots.txt, .htaccess, Others/RSS, Blocks(TOC title) | ✅ | 1 |
| Titles & Meta: Global, Local SEO (Knowledge Graph phần free), Social, Homepage, Authors, Misc, mọi post type, mọi taxonomy | ✅ | 1 |
| Variables engine (%title% %sep% …, full list RM/includes/replace-variables/*) | ✅ | 1 |
| Frontend head: title, description, robots + advanced robots, canonical, prev/next, OG, Twitter, Slack, webmaster verify | ✅ | 1 |
| Link attributes (nofollow/target blank), comments ugc + replytocom, RSS before/after, attachment redirect, strip category base, author base, disable author/date archive | ✅ | 1 |
| Polylang: chuỗi option dịch được + sitemap/hreflang tương thích | ✅ (yêu cầu bổ sung — LIX dùng en/vi) | 1 (strings) + 3 (sitemap) |
| Editor meta box: General (SERP preview desktop/mobile, focus keyword ≤5 (Pro 100), pillar, SEO score + checklist), Advanced (robots, canonical, breadcrumb title, redirect), Social (FB/Twitter), primary term | ✅ meta box PHP + vanilla JS (hoạt động cả Classic và Gutenberg — panel meta box); **không** làm sidebar React riêng | 2 |
| Content analysis (keywordInTitle… contentHasAssets, 23 test) | ✅ chạy JS local | 2 |
| Term meta + user meta SEO | ✅ | 2 |
| Post list: cột SEO Details/Title/Desc, filter, bulk actions, quick/bulk edit (Pro) | ✅ | 2 |
| CSV import/export meta (Pro) | ✅ | 2 |
| Sitemap XML (index, post type, taxonomy, author, image, XSL, cache, invalidation, tắt wp-sitemap core) | ✅ | 3 |
| HTML sitemap (shortcode + gắn page) | ✅ | 3 |
| News sitemap, Video sitemap (Pro) | ✅ | 3 |
| Schema/JSON-LD graph (Organization/Person, WebSite, WebPage, ImageObject, Breadcrumb, Author, per-post schema) | ✅ | 3 |
| Schema Generator per post: Article, Book, Course, Event, JobPosting, Music, Product, Recipe, Restaurant, Video, Person, Service, Software + Pro: Movie, FactCheck, Dataset, LocalBusiness(163 subtype), FAQPage, HowTo, PodcastEpisode; nhiều schema/bài; custom builder | ✅ (UI form theo field map RMP schemaFilters.js) | 3 (types chính) / 5 (Pro types, templates, display conditions, import from URL) |
| Blocks FAQ / HowTo / TOC | ✅ block Gutenberg trong P/ (theo chuẩn block lacadev) | 5 |
| Breadcrumbs (function, shortcode, BreadcrumbList) | ✅ + hàm tương thích `rank_math_the_breadcrumbs()` khi RM đã gỡ | 3 |
| WooCommerce SEO (free + Pro: GTIN, brand, variants, noindex hidden, product schema, OG product) | ✅ | 3 |
| Instant Indexing (IndexNow) | ✅ | 3 |
| llms.txt | ✅ | 3 |
| Redirections (+Pro: categories, schedule, query params, CSV, htaccess sync, auto-delete) + 404 Monitor (+Pro export) | ✅ | 4 |
| Local SEO Pro (multi locations CPT, shortcode/block, map, store locator, KML) | ✅ | 5 |
| Image SEO (free alt/title + Pro caption/description/case/replacements/avatar alt) | ✅ | 6 |
| Thumbnail overlays (Pro) | ✅ | 6 |
| Link Counter (free) | ✅ | 6 |
| Link Genius: broken link crawler, bulk anchor update + rollback, keyword maps auto-link (không AI) | ✅ | 6 |
| Role Manager (capabilities rank_math_* → laca_seo_*) | ✅ | 6 |
| SEO Analyzer sitewide | 🔁 test local (title/desc length, H1, alt, SSL, sitemap, robots, blog public, permalink, tagline, auto update, page size, speed qua PSI public API không key) | 6 |
| Status & Tools (DB tools, system status) + Setup Wizard + Admin bar menu + Dashboard widget | ✅ | 6 |
| Analytics / Search Console / GA4 / AdSense / Rank tracker / Email report | 🔁 chèn GA4/GTM Measurement ID (anonymize, bỏ qua user đăng nhập) + link nhanh GSC/PSI/Rich Results/FB Debugger trong admin bar | 1 (tracking code) |
| Content AI, AI Visibility, gợi ý keyword (api.rankmath.com), Search intent AI, AI link suggestions, Related posts AI | ⏭ cần server/credit Rank Math | — |
| License, Marketplace, plugin update, usage tracking, version-control rollback | ⏭ không áp dụng cho theme | — |
| Podcast feed (Pro) | ✅ (ưu tiên thấp) | 6 |
| BuddyPress, bbPress, Elementor, Divi, ACF, AMP, Web Stories | ⏭ site family không dùng; chừa hook để bổ sung | — |

---

## 2. Kiến trúc

### 2.1 Thư mục (PSR-4 `App\Features\LacaSeo\`, `P/app/src/Features/LacaSeo/`)

```
LacaSeo/
├── LacaSeo.php                 # boot: guard, đăng ký module, try/catch kiểu DiscordHub (không bao giờ làm sập site)
├── Core/
│   ├── Settings.php            # đọc/ghi option, Settings::get('titles.pt_post_title'), defaults từ Registry, cache static
│   ├── Modules.php             # danh sách module + trạng thái (option laca_seo_modules)
│   ├── Variables.php           # engine biến %...% (full list RM), API đăng ký biến
│   ├── Helper.php              # is_rank_math_active(), get_post_types/taxonomies "accessible", robots merge…
│   └── Capability.php          # (phase 6) caps laca_seo_*; phase 0-5 dùng manage_options
├── Fields/                     # REGISTRY khai báo field — nguồn DUY NHẤT cho UI + sanitize + default + export
│   ├── Field.php               # value object: id, type, label, default, choices, show_if, note{what,how,result}, translatable, sanitize
│   ├── GeneralFields.php
│   ├── TitlesFields.php        # sinh động field per post type / taxonomy
│   ├── SitemapFields.php       # (phase 3)
│   └── InstantIndexingFields.php (phase 3)
├── Admin/
│   ├── AdminPage.php           # menu laca-seo, router ?view=, enqueue asset riêng
│   ├── Renderer.php            # render field theo type (§2.4)
│   ├── SaveHandler.php         # admin-post laca_seo_save: nonce + cap + sanitize theo Field + update_option
│   ├── views/                  # template PHP từng màn (dashboard, settings, import-export, guide, tools…)
│   └── Notices.php             # cảnh báo: Rank Math đang bật, site noindex (blog_public=0)…
├── Frontend/
│   ├── Head.php                # hook wp_head prio 1, gỡ rel_canonical core, title filter
│   ├── Paper/                  # resolver theo ngữ cảnh: Singular, Blog, Home, Taxonomy, Author, Date, Search, NotFound, Archive, Shop
│   ├── OpenGraph/Facebook.php, Twitter.php, Slack.php, Image.php
│   ├── Robots.php              # filter wp_robots
│   ├── LinkAttributes.php, Comments.php, Rss.php, AttachmentRedirect.php, Rewrite.php, Webmaster.php, Tracking.php
│   └── RobotsTxt.php, Htaccess.php
├── Compat/Polylang.php         # đăng ký chuỗi option để dịch, đọc bản dịch theo ngôn ngữ hiện tại
├── ImportExport/
│   ├── Exporter.php, Importer.php   # JSON
│   └── RankMathImporter.php         # (phase 1)
└── Guide/GuideData.php         # nội dung trang Hướng dẫn + checklist trạng thái
```

Asset admin: entry webpack mới `laca-seo` trong `C/resources/build/webpack/entry.js` trỏ `P/resources/scripts/admin/laca-seo/index.js`
+ `P/resources/styles/admin/laca-seo/index.scss` → `dist/laca-seo.js`, `dist/styles/laca-seo.css`.
Chỉ enqueue trên màn `laca-seo*` (và màn soạn bài từ phase 2) — KHÔNG nhét vào `admin/index.js` (bundle chung mọi màn admin).

### 2.2 Lưu trữ — giữ nguyên "ngôn ngữ" của Rank Math để import/so sánh 1:1

| Rank Math | Laca SEO |
|---|---|
| `rank-math-options-general` | `laca_seo_general` |
| `rank-math-options-titles` | `laca_seo_titles` |
| `rank-math-options-sitemap` | `laca_seo_sitemap` |
| `rank-math-options-instant-indexing` | `laca_seo_instant_indexing` |
| `rank_math_modules` | `laca_seo_modules` |
| post/term/user meta `rank_math_{x}` | `_laca_seo_{x}` (cùng hậu tố, vd `_laca_seo_title`, `_laca_seo_robots`, `_laca_seo_primary_category`, `_laca_seo_schema_Article`) |
| bảng `rank_math_redirections`, `_404_logs`, … | `laca_seo_redirections`, `laca_seo_404_logs`, … (phase 4) |

- Key BÊN TRONG option giữ **y hệt id field của RM** (`strip_category_base`, `pt_post_title`, `robots_global`…), toggle lưu `'on'|'off'` như RM.
- Option `autoload`: general + titles = yes (dùng mỗi request frontend); còn lại no.
- Thêm option `laca_seo_version` (migration/DB version) và `laca_seo_backups` (tối đa 5 bản trước mỗi lần import).

### 2.3 Field registry (cốt lõi — bắt buộc tuân thủ)

Mỗi field khai báo 1 lần trong `Fields/*Fields.php`:

```php
Field::make('strip_category_base', 'toggle', 'Bỏ /category/ khỏi URL')
    ->default('off')
    ->note(
        what:   'Bỏ tiền tố /category/ trong URL chuyên mục.',
        how:    'Bật nếu muốn URL ngắn như /tin-tuc/. Sau khi bật vào Cài đặt › Đường dẫn tĩnh bấm Lưu.',
        result: 'URL chuyên mục ngắn, dễ đọc hơn; URL cũ tự 301 sang URL mới.'
    )
    ->showIf('other_field', 'on')        // phụ thuộc hiển thị (giống `dep` của RM)
    ->translatable();                    // chuỗi cần dịch theo Polylang (title template, label…)
```

- Renderer, SaveHandler (sanitize), Settings defaults, Exporter đều đọc từ registry → không bao giờ lệch nhau.
- Note: tiếng Việt, ngắn, cụ thể. *Kết quả* nói rõ thấy ở đâu (vd "xem bằng View Source › thẻ `<meta name=robots>`").
- Default lấy đúng giá trị **installer** của RM (`RM/includes/class-installer.php`) khi field default và installer khác nhau (vd `pt_post_title` = `%title% %sep% %sitename%`).

### 2.4 Kiểu field Renderer cần hỗ trợ

`toggle` (switch đẹp, lưu on/off), `text`, `textarea`, `code` (robots.txt/.htaccess — monospace, có nút "Khôi phục mặc định"), `number`, `select`, `radio`, `radio_inline` (separator chọn nhanh + ô custom), `multicheck`, `image` (wp.media, lưu url + `_id`), `variable_text` / `variable_textarea` (ô nhập + nút chèn biến dropdown + **preview kết quả thực** bằng AJAX render biến cho 1 bài mẫu), `robots` (multicheck index/noindex/nofollow/noarchive/noimageindex/nosnippet), `advanced_robots` (max-snippet, max-video-preview, max-image-preview), `group` (repeater: thêm/xoá/kéo thả — opening_hours, phone_numbers, additional_info, 404 exclude…), `address`, `html` (đoạn giải thích), `notice`.

### 2.5 Giao diện (khớp Laca Admin, responsive)

- Dùng token có sẵn `--laca-*` (`P/resources/styles/admin/_admin-dashboard.scss`) + màu admin `--primary-color-ad`. BEM `.laca-seo__*`, SCSS ≤ 3 cấp nest, vanilla JS (không jQuery, không React).
- Bố cục trang settings: thanh tab **dọc** bên trái (Links, Images, Breadcrumbs…) + nội dung phải dạng card; mỗi field 1 hàng: trái = label + note (icon ⓘ mở/đóng 3 dòng), phải = control. Nút "Lưu thay đổi" sticky dưới cùng + "Khôi phục mặc định tab này".
- Responsive: ≥1200 2 cột (tab | nội dung, field label/control ngang); 782–1199 tab chuyển thành thanh ngang cuộn; <782 (breakpoint mobile WP admin) label trên, control dưới, full width. Không tràn ngang.
- Ô tìm kiếm setting toàn trang (lọc field theo label/note) — hữu ích vì hàng trăm field.
- A11y: label for/id, switch là `<input type=checkbox role=switch>`, focus ring, aria-expanded cho note.
- Menu: thêm group **"SEO"** vào `LacaAdminMenuOrganizer::GROUPS` (`P/app/src/Settings/LacaAdmin/LacaAdminMenuOrganizer.php:59-125`) với item `laca-seo` + fallback label; trang con điều hướng bằng `?view=` bên trong (không tạo 10 submenu WP).

### 2.6 Màn hình (`admin.php?page=laca-seo&view=…`)

| view | Nội dung | Phase |
|---|---|---|
| `dashboard` | Trạng thái nhanh (site public?, sitemap OK?, Rank Math còn bật?, số bài thiếu meta), lưới module bật/tắt có note | 0 |
| `general` | General Settings (tab dọc theo RM) | 1 |
| `titles` | Titles & Meta | 1 |
| `sitemap` | Sitemap settings | 3 |
| `instant-indexing` | Submit URLs / History / Settings | 3 |
| `redirections`, `404` | List table + form | 4 |
| `schema-templates`, `locations` | (Pro) | 5 |
| `analyzer`, `links`, `roles`, `tools`, `wizard` | | 6 |
| `import-export` | Export/Import JSON, Import Rank Math, Backups | 0 / 1 |
| `guide` | Hướng dẫn tối ưu SEO + checklist trạng thái | 0 (khung) → cập nhật mỗi phase |

### 2.7 Tương thích & an toàn

- `Helper::isRankMathActive()` = `defined('RANK_MATH_VERSION')`. Khi true: **không** đăng ký bất kỳ hook frontend nào (head, sitemap, robots.txt, rewrite, schema, redirect); admin hiện notice "Rank Math đang bật — Laca SEO chỉ ở chế độ cấu hình/nhập liệu".
- Không bao giờ fatal: mọi module boot trong try/catch + `error_log`.
- Bảo mật: nonce mọi form/AJAX, `current_user_can('manage_options')` (phase 6 chuyển sang caps riêng), sanitize theo type, escape 100% output, `$wpdb->prepare`. `.htaccess`/robots.txt chỉ super user (`AdminSettings::getSuperUserLogins()`).
- `Features/DiscordHub/Monitors/SettingsMonitor.php`: thêm `laca_seo_` vào `IGNORE_PATTERN` (tránh spam thông báo mỗi lần lưu).
- Hiệu năng: option general/titles đọc 1 lần/request (static cache); không query trong vòng lặp; sitemap cache file/transient (phase 3).

---

## 3. Lộ trình phase & tiêu chí xong

| Phase | Nội dung | Xong khi |
|---|---|---|
| **0** | Nền tảng: boot, Settings/Modules/Registry/Renderer/SaveHandler, AdminPage + router + asset entry, Dashboard, Import/Export JSON + backup, khung trang Guide, menu group SEO, guard Rank Math | Lưu/đọc/export/import 1 field mẫu mỗi kiểu; UI responsive 3 breakpoint; build+lint sạch |
| **1** | General + Titles & Meta đầy đủ field; Variables; Frontend head + link attrs + RSS + rewrite + robots.txt/htaccess + webmaster + tracking GA4/GTM; Polylang strings; **RankMathImporter**; cập nhật code theme đang phụ thuộc RM (§5) | Script so sánh head (§4) cho ≥12 URL mẫu: Laca SEO ra cùng tập thẻ với Rank Math |
| **2** | Meta box bài/term/user + SERP preview + content analysis + primary term + list columns/filters/bulk/quick edit + CSV meta | Sửa meta trong editor → frontend đổi đúng; 23 test analysis khớp logic RM |
| **3** | Sitemap (XML/HTML/news/video/image), Schema graph + Schema Generator types chính, Breadcrumbs, WooCommerce SEO, IndexNow, llms.txt | So sánh sitemap + JSON-LD với RM trên các URL mẫu → **CUTOVER: tắt Rank Math trên LIX** |
| **4** | Redirections + 404 Monitor (+Pro) | CRUD, match types, export htaccess/nginx, log 404 |
| **5** | Schema Pro (templates + display conditions + types Pro + import URL + blocks FAQ/HowTo/TOC), Local SEO multi-location | |
| **6** | Image SEO, overlays, Link counter/Genius (không AI), Role manager, Analyzer local, Tools, Wizard, admin bar, dashboard widget, Podcast | |

---

## 4. Công cụ kiểm chứng "giống Rank Math 100%" (làm ở Phase 1, tái dùng các phase sau)

`P/doc/laca-seo/tools/compare-head.php` (chạy bằng PHP-CLI của Local, KHÔNG deploy):
1. Danh sách URL mẫu: trang chủ, 1 post, 1 page, 1 product, 1 journal, 1 glossary, category, product_cat, journal-cat, tag, author, `/?s=cafe`, 1 URL 404, 1 attachment; cả bản `/en/`.
2. Lấy `<head>` từng URL qua HTTP 2 lần: (A) Rank Math bật, Laca SEO tắt output; (B) bật biến môi trường/option dev `laca_seo_force_frontend=1` cho phép Laca SEO chạy dù RM còn active **và** tắt output RM bằng filter `rank_math/head` remove — hoặc chạy trên bản sao DB. Chuẩn hoá (bỏ comment credit, sắp attr), diff từng thẻ: title, description, robots, canonical, og:*, twitter:*, verify, JSON-LD (phase 3).
3. Xuất báo cáo bảng URL × thẻ: giống / khác (giá trị A vs B). Mục tiêu: 0 khác biệt ngoài comment credit.

---

## 5. Code theme hiện phụ thuộc Rank Math (sửa ở Phase 1 — đọc meta/option Laca SEO trước, fallback Rank Math)

| File | Việc |
|---|---|
| `P/theme/template-parts/breadcrumb.php:8-9`, `C/theme/template-parts/breadcrumb.php:12-13`, `C/theme/archive.php:28-29`, `P/app/helpers/template_tags.php:293` | Gọi `laca_seo_breadcrumbs()` (phase 3 mới có thật; phase 1 tạo wrapper: Laca SEO nếu có → Rank Math nếu còn → rỗng) |
| `P/app/src/Settings/LacaAdmin/ReleaseChecklist.php:569-593` | Kiểm tra cấu hình Laca SEO thay vì option Rank Math |
| `P/app/src/Settings/LacaTools/Management/ContentAuditService.php:239` | Thêm meta `_laca_seo_description` |
| `P/app/src/Settings/LacaTools/Management/DashboardWidgets.php:399-400` | Điểm SEO `_laca_seo_seo_score` |
| `P/app/src/Settings/LacaTools/AITranslationManager.php:203-229` | Dịch thêm meta `_laca_seo_*` |
| `P/theme/setup/seo.php` (đang tắt) | Xoá file + dòng require comment ở `P/theme/functions.php:146`; sửa `doc/THEME-DEVELOPMENT-GUIDE.md` §7.2 |
| `P/app/src/Settings/AdminSettings.php` `removeUnnecessaryMenus` | Không ẩn menu Laca SEO với admin thường (quyết định: admin khách được dùng Laca SEO) |

---

## 6. Quy ước chung cho người code (Antigravity)

- Đọc `P/doc/AI_CODING_INSTRUCTIONS.md`, `THEME-DEVELOPMENT-GUIDE.md`, `LACADEV_SIMILAR_BUGS_AUDIT.md` trước.
- Comment tiếng Việt, ngắn, chỉ WHY. Không debug code sót lại. Không commit/push.
- Mỗi phase xong: `php -l` mọi file PHP, `cd C && yarn build && yarn lint` (0 error), test bằng PHP-CLI Local (site-id `nan2TUtPY`, xem skill lacadev-theme §4), chụp màn hình admin 1440 + 900 + 390px.
- Báo cáo: file tạo/sửa, field đã làm (đếm theo tab so với RM), điểm lệch RM + lý do, kết quả kiểm chứng.

---

# Spec chi tiết Phase 0 + Phase 1

## P0.1 Boot
- `P/app/hooks.php`: thêm khối boot kiểu DiscordHub (`after_setup_theme` prio 20, try/catch) gọi `App\Features\LacaSeo\LacaSeo::boot()`.
- `LacaSeo::boot()`: khởi tạo Settings; nếu `is_admin()` → AdminPage, SaveHandler, Notices, ImportExport; nếu frontend và `!Helper::isRankMathActive()` (hoặc `laca_seo_force_frontend` dev) → các module frontend đang bật.
- `Modules`: danh sách id giống RM (`404-monitor, local-seo, redirections, rich-snippet, sitemap, link-counter, image-seo, instant-indexing, llms-txt, role-manager, seo-analysis, woocommerce, news-sitemap, video-sitemap, podcast`) + default bật như RM installer. Module chưa có code ở phase hiện tại hiển thị badge "Sắp có (Phase N)" và không cho bật.

## P0.2 Settings / Registry / Renderer / Save
- Theo §2.2–2.4. Save qua `admin-post.php?action=laca_seo_save` với hidden `option_group` (general|titles|…) + `tab`; redirect về đúng view/tab với notice.
- Toggle chưa tick vẫn phải lưu `'off'` (dùng hidden input trước checkbox).
- "Khôi phục mặc định tab": action `laca_seo_reset_tab` (confirm JS).

## P0.3 Import / Export (view `import-export`)
- **Export**: checkbox chọn phần (General, Titles & Meta, Sitemap, Instant Indexing, Modules; phase 4+ thêm Redirections) → tải `laca-seo-{domain}-{Ymd-His}.json`:
  ```json
  {"plugin":"laca-seo","version":"<theme version>","exported_at":"…","site_url":"https://…","data":{"general":{},"titles":{},"sitemap":{},"instant_indexing":{},"modules":[]}}
  ```
- **Import**: upload JSON (≤2MB, validate `plugin=laca-seo`, chỉ nhận key có trong registry, sanitize lại từng giá trị) → màn xác nhận liệt kê phần sẽ ghi đè + tuỳ chọn "Thay URL site cũ `site_url` bằng URL site này" (thay trong mọi chuỗi) + tuỳ chọn "Giữ nguyên webmaster verify / GA ID của site này" (mặc định BẬT — vì mã xác minh là riêng từng site). Ghi `laca_seo_backups` trước khi import.
- **Backups**: liệt kê 5 bản gần nhất, khôi phục / xoá / tải về.
- Note giải thích từng nút (tác dụng/cách làm/kết quả).

## P0.4 Dashboard + Guide (khung)
- Dashboard: 4 thẻ trạng thái (Hiển thị với công cụ tìm kiếm: `blog_public`; Rank Math: bật/tắt; Sitemap: link sitemap Laca SEO (phase 3) / RM; Bài thiếu meta description) + lưới module.
- Guide: trang tĩnh nhiều mục (accordion), mỗi mục: *Vì sao quan trọng / Làm gì / Kiểm tra kết quả ở đâu* + nút "Mở cài đặt này" deep-link `view=…&tab=…#field-id`. Các mục tối thiểu: 1) Trước khi bắt đầu (blog_public, permalink, HTTPS); 2) Knowledge Graph & logo; 3) Tiêu đề & mô tả (template, độ dài 50–60/150–160 ký tự); 4) Robots & noindex trang mỏng (tag, author, date, search, phân trang); 5) Social (OG image 1200×630); 6) Sitemap & Search Console; 7) Schema; 8) Breadcrumb; 9) Link nội bộ/ngoại; 10) Ảnh (alt); 11) Đa ngôn ngữ (Polylang); 12) WooCommerce; 13) Sau khi chuyển từ Rank Math (kiểm tra gì). Kèm **checklist tự động** (✅/⚠️) đọc trạng thái thật.

## P1.1 General Settings — tab và field (ĐÚNG id/default/choices theo RM, file nguồn bên cạnh)
| Tab | File RM | Field id |
|---|---|---|
| Links | `RM/includes/settings/general/links.php` | strip_category_base, attachment_redirect_urls, attachment_redirect_default, nofollow_external_links, nofollow_image_links, nofollow_domains, nofollow_exclude_domains, new_window_external_links (+ Pro `affiliate_link_prefixes`) |
| Images | `RM/includes/modules/image-seo/options.php` (+Pro fields chuyển Phase 6) | add_img_alt, img_alt_format, add_img_title, img_title_format |
| Breadcrumbs | `RM/includes/settings/general/breadcrumbs.php` | breadcrumbs, breadcrumbs_separator, breadcrumbs_home, breadcrumbs_home_label, breadcrumbs_home_link, breadcrumbs_prefix, breadcrumbs_archive_format, breadcrumbs_search_format, breadcrumbs_404_label, breadcrumbs_remove_post_title, breadcrumbs_ancestor_categories, breadcrumbs_hide_taxonomy_name, breadcrumbs_blog_page (settings phase 1, output phase 3) |
| Webmaster Tools | `RM/includes/settings/general/webmaster.php` | google_verify, bing_verify, baidu_verify, yandex_verify, pinterest_verify, norton_verify, custom_webmaster_tags |
| Analytics (thay thế) | — | ga4_measurement_id, gtm_container_id, ga_anonymize_ip, ga_exclude_loggedin (không trùng field script header/footer có sẵn ở C theme-options — note nhắc chỉ dùng 1 nơi) |
| Edit robots.txt | `RM/includes/modules/robots-txt/options.php` | robots_txt_content (+ notice: file robots.txt vật lý tồn tại / site không public) |
| Edit .htaccess | `RM/includes/settings/general/htaccess.php` | htaccess_content (super user; backup file trước khi ghi; validate không rỗng) |
| WooCommerce | `RM/includes/modules/woocommerce/views/options-general.php` | wc_remove_product_base, wc_remove_category_base, wc_remove_category_parent_slugs, wc_remove_generator, remove_shop_snippet_data, product_brand (logic output Phase 3) |
| Blocks | `RM/includes/modules/schema/blocks/views/options-general.php` | toc_block_title, toc_block_list_style, toc_block_exclude_headings |
| Others | `RM/includes/settings/general/others.php` | headless_support, rss_before_content, rss_after_content (bỏ support_rank_math, usage_tracking; frontend_seo_score* chuyển Phase 2) |
| 404 Monitor / Redirections / LLMS | (settings + logic Phase 4 / 3) | ẩn đến phase tương ứng |

## P1.2 Titles & Meta — tab và field
| Tab | File RM | Ghi chú |
|---|---|---|
| Global Meta | `RM/includes/settings/titles/global.php` | robots_global, advanced_robots_global, noindex_empty_taxonomies, title_separator, rewrite_title (chỉ khi theme không có title-tag — theme có nên ẩn), capitalize_titles, open_graph_image(+_id), twitter_card_type |
| Local SEO | `RM/includes/settings/titles/local.php` + `RM/includes/modules/local-seo/views/titles-options.php` | knowledgegraph_type, website_name, website_alternate_name, knowledgegraph_name, organization_description, knowledgegraph_logo(+_id), url, email, phone, local_address, local_address_format, local_business_type (choices `choices_business_types`), opening_hours_format, opening_hours(group), phone_numbers(group), price_range, additional_info(group), local_seo_about_page, local_seo_contact_page, maps_api_key, geo |
| Social Meta | `RM/includes/settings/titles/social.php` | social_url_facebook, facebook_author_urls, facebook_admin_id, facebook_app_id, facebook_secret(password), twitter_author_names, social_additional_profiles |
| Homepage | `RM/includes/settings/titles/homepage.php` | chỉ hiện khi front page = latest posts (giống RM); note giải thích khi dùng static page thì sửa meta ở chính trang đó |
| Authors | `RM/includes/settings/titles/author.php` | lưu ý `UserEnumerationGuard` (P) đang 404 `?author=` khi chưa đăng nhập — note cảnh báo |
| Misc Pages | `RM/includes/settings/titles/misc.php` | đủ field |
| Mỗi post type (post, page, product, journal, partnership, glossary, attachment…) | `RM/includes/settings/titles/post-types.php` | sinh động theo `get_post_types(public)` trừ type RM loại; field schema default (`_default_rich_snippet`…) lưu ngay (dùng Phase 3) |
| Mỗi taxonomy | `RM/includes/settings/titles/taxonomies.php` | sinh động; `remove_{tax}_snippet_data` |

## P1.3 Variables (`Core/Variables.php`)
- Toàn bộ biến trong `RM/includes/replace-variables/*` + WooCommerce `class-wc-vars.php` (danh sách đầy đủ ở báo cáo khảo sát, xem mục "Variables"). Cú pháp tham số `%date(F jS, Y)%`, `%categories(limit=3&separator= | &exclude=12)%`, `%customfield(x)%`, `%count(var)%`.
- Hàm public: `laca_seo_replace_vars($string, $object = null)`, `laca_seo_register_var($id, $args)`.
- AJAX `laca_seo_preview_vars` cho ô `variable_text` (render với bài mới nhất của post type tương ứng).

## P1.4 Frontend (chỉ chạy khi Rank Math không active)
- Copy hành vi `RM/includes/frontend/class-head.php` + `paper/*`: thứ tự thẻ, gỡ `rel_canonical`/`noindex` core, title qua `pre_get_document_title` prio 15 (theme có `title-tag`; `P/theme/layouts/app.php:18` dùng `wp_title()` → kiểm tra và chuyển sang để core in title-tag, tránh 2 thẻ title).
- Robots qua filter `wp_robots` (core) thay vì in tay — kết quả 1 thẻ duy nhất; gộp advanced robots.
- OG/Twitter/Slack theo `RM/includes/opengraph/*` (fallback ảnh: user → featured → ảnh đầu nội dung → global).
- Link attributes, comments, RSS, attachment redirect, rewrite (strip category base + flush rewrite khi đổi), author base, disable author/date archives, webmaster (chỉ front page), GA4/GTM.
- robots.txt qua filter `robots_txt` (chỉ khi blog_public), .htaccess: đọc/ghi file + backup `.htaccess.laca-seo-backup-{time}`.

## P1.5 Polylang
- Các field `->translatable()` (title/description template, homepage, breadcrumb label, org name/description…) đăng ký `pll_register_string('laca_seo: {id}', value, 'Laca SEO', multiline)`; khi đọc frontend dùng `pll__()` nếu Polylang active. Note của field nói rõ "Dịch tại Ngôn ngữ › Dịch chuỗi › nhóm Laca SEO".

## P1.6 RankMathImporter (view `import-export` → mục "Nhập từ Rank Math")
- Hiện khi phát hiện option `rank-math-options-*` hoặc meta `rank_math_*` trong DB (kể cả khi plugin đã tắt).
- Chọn: Settings (general/titles/sitemap/instant-indexing/modules), Post meta, Term meta, User meta, (phase 4) Redirections.
- Map: option key giữ nguyên; meta `rank_math_x` → `_laca_seo_x`; bỏ key không còn ý nghĩa (`rank_math_seo_score` vẫn copy; `rank_math_internal_links_processed`, `rank_math_analytic_object_id`, `rank_math_contentai_score` bỏ).
- Chạy theo batch AJAX (200 bản ghi/lượt, có progress bar), idempotent (chạy lại không nhân đôi), backup settings trước, báo cáo số bản ghi đã copy. Không xoá dữ liệu Rank Math.
- Nút "Kiểm tra sau nhập": chạy compare-head (§4) mini cho 5 URL và hiện kết quả.

## P1.7 Kiểm chứng Phase 1
- Tool §4 trên ≥14 URL (vi + en). Mọi khác biệt phải giải thích được trong báo cáo.
- Screenshot General/Titles ở 1440/900/390.
- Test Export ở site A → Import ở site B (giả lập bằng xoá option rồi import lại) → so sánh option giống hệt.

---

# Phụ lục A — Danh sách biến (Rank Math 1.0.280, `RM/includes/replace-variables/*`)

- Cơ bản: `%sep%` (`%separator%` alias) `%search_query%` `%count(varname)%` `%filename%` `%sitename%` `%sitedesc%` `%currentdate%` `%currentday%` `%currentmonth%` `%currentyear%` `%currenttime%` `%currenttime(F jS, Y)%` `%org_name%` `%org_logo%` `%org_url%`
- Bài viết: `%title%` `%parent_title%` `%excerpt%` `%excerpt_only%` `%seo_title%` `%seo_description%` `%url%` `%post_thumbnail%` `%date%` `%modified%` `%date(F jS, Y)%` `%modified(F jS, Y)%` `%category%` `%categories%` `%categories(limit=3&separator= | &exclude=12,23)%` `%primary_taxonomy_terms%` `%tag%` `%tags%` `%tags(limit=3&separator= | &exclude=12,23)%`
- Term: `%term%` `%term_description%` `%customterm(taxonomy)%` `%customterm_desc(taxonomy)%` + tự sinh `%term_{taxonomy}%` `%term_{taxonomy}_desc%`
- Tác giả: `%userid%` `%name%` `%post_author%` `%user_description%`
- Nâng cao: `%id%` `%focuskw%` `%keywords%` `%customfield(field-name)%` `%page%` `%pagenumber%` `%pagetotal%` `%pt_single%` `%pt_plural%`
- WooCommerce: `%wc_price%` `%wc_sku%` `%wc_shortdesc%` `%wc_brand%`
- Pro: `%randomword%` `%randomword_np%` `%imagealt%` `%imagetitle%` `%podcast_image%`
- RSS (chỉ trong rss_before/after): `%AUTHORLINK%` `%POSTLINK%` `%BLOGLINK%` `%BLOGDESCLINK%` `%FEATUREDIMAGE%`
- Lấy mô tả + ví dụ từng biến từ chính file RM (`get_vars()` / `register_replacement`) để hiện trong dropdown "Chèn biến".

# Phụ lục B — Meta key (post / term / user) ánh xạ từ Rank Math

`rank_math_{x}` → `_laca_seo_{x}` với x thuộc:
title, description, focus_keyword (phân tách dấu phẩy), pillar_content, robots (mảng), advanced_robots (mảng), canonical_url, breadcrumb_title,
facebook_title, facebook_description, facebook_image, facebook_image_id, facebook_enable_image_overlay, facebook_image_overlay, facebook_author,
twitter_use_facebook, twitter_card_type, twitter_title, twitter_description, twitter_image, twitter_image_id, twitter_enable_image_overlay, twitter_image_overlay,
twitter_player_url, twitter_player_size, twitter_player_stream, twitter_player_stream_ctype, twitter_app_description, twitter_app_country,
twitter_app_{iphone,ipad,googleplay}_{name,id,url}, seo_score, primary_{taxonomy}, dont_show_seo_score, lock_modified_date,
schema_{Type} (JSON), shortcode_schema_{id}, news_sitemap_robots, description_editor (term), media_exclude_sitemap (attachment).
Bỏ khi import: internal_links_processed, analytic_object_id, contentai_score, og_content_image (cache, tự sinh lại).
Meta riêng Woo Pro: `_rank_math_gtin_code` → `_laca_seo_gtin_code`.

# Phụ lục C — Dữ liệu thực tế site LIX (khảo sát 20/10/2026, DB local)
- Module RM đang bật: link-counter, analytics, seo-analysis, sitemap, rich-snippet, woocommerce, instant-indexing (+ content-ai, ai-visibility, marketplace, buddypress, bbpress, acf, web-stories không dùng).
- Không có bảng redirections / 404 logs. 7 meta key `rank_math_*` (182 dòng postmeta), 230 dòng termmeta.
- Polylang: en, vi. Post type public: post, page, attachment, product, journal, partnership, glossary. WooCommerce store đang "Coming soon".

---

# Trạng thái

| Phase | Trạng thái | Ghi chú |
|---|---|---|
| 0 + 1 | ✅ Xong 21/10/2026 sau 4 vòng review (REVIEW-P0P1-R1…R4) | compare-head 19/19 URL khớp RM (khác duy nhất: RM để lọt `<p>` trong og/twitter description — Laca strip); test-variables 55/55 khớp `RankMath\Helper::replace_vars()`; Export/Import HTTP thật OK; RM importer mô phỏng đúng hành vi "thiếu key" của RM (taxonomy ≡ rỗng; post type: title/description dùng mẫu mặc định, archive_* rỗng). Vòng 4 do Claude sửa trực tiếp (Antigravity treo). |
| 2 | ✅ Xong 21/10/2026 (commit 828afb02) sau 2 vòng review (REVIEW-P2-R1, R2) | Analyzer đối chiếu trên editor thật: diffs=0, điểm khớp tuyệt đối (gồm Classic editor product + term). Hoãn sang 3A: dịch thông điệp phân tích sang tiếng Việt, hiện 2 test product trong UI. |
| 3A | ✅ Xong 21/10/2026 sau review R1 (chờ commit) | Claude tự kiểm: sitemap 0 khác (mọi sitemap con, lastmod, ảnh, thứ tự), breadcrumbs HTML 19/19 (option RM general đã sửa nạp bộ nhớ), robots.txt giống RM, simulate-cutover 9/9. Đã khôi phục robots meta bài 866/908 bị test Phase 2 làm lệch (908 từng bị noindex). |
| 3B | ✅ Xong 21/10/2026 sau review R1, R2 (chờ commit) — Claude tự kiểm: head + JSON-LD 19/19 URL (blog_public=1 trong bộ nhớ) chỉ còn 2 lệch đã chấp nhận; schema Event/Product nạp bộ nhớ khớp RM từng node; không còn đọc `rank_math_*`. Còn lại: CUTOVER trên production theo `CUTOVER.md` (cần người dùng thực hiện). | Schema graph + Schema Generator types chính + WooCommerce SEO → CUTOVER tắt RM |
| 4 | ✅ Xong 21/10/2026 sau review R1 | test-redirections 45/45 (M1–M20 so bằng hàm RM compare_sources, export so RM), Claude chạy 2 lần: checksum 3 bảng + bài 866 + terms không đổi. Module mặc định tắt; bảng wp_laca_seo_redirections, _cache, _404_logs đã tạo. Đã xoá dòng rác test. Commit 82db1b10. |
| 5 | ✅ Xong 21/10/2026 sau review R1, R2 | test-phase5 37/37 so với RM Pro (module RM bật trong bộ nhớ, dữ liệu nạp filter/transaction, Z1 checksum); cmp 19 URL chỉ 2 lệch đã chấp nhận. Lưu ý: test-phase5 R1 gọi scratchpad/p3b/cmp.php của Claude (không nằm trong repo). |
| 6 | ✅ Xong 22/10/2026 sau review R1 | test-phase6: 24 ca so với RM Free/Pro + 2 LACAONLY (wizard, admin bar), 0 fail, chạy 2 lần DB không đổi. SA1–SA4 (phân tích nội dung trang chủ) là bản local — RM gọi API từ xa nên chỉ SA5 so trực tiếp được. |

Phase 3 tách 3A/3B vì khối lượng lớn. News/Video sitemap (Pro, module không bật trên LIX) dời sang Phase 5.

Lưu ý vận hành:
- Công cụ `doc/laca-seo/tools/*.php` chỉ chạy CLI (web trả 404).
- Trên site mới: Laca SEO > Sao lưu & Nhập/Xuất > Nhập từ Rank Math (chạy theo batch). Chạy lại được nhiều lần (idempotent), luôn backup trước.

---

# Spec chi tiết Phase 2 — SEO trong trình soạn thảo + danh sách bài

Mục tiêu: admin sửa SEO từng bài/term/tác giả ngay trong editor như Rank Math; danh sách bài có cột SEO, lọc, bulk/quick edit;
CSV meta (Pro). Frontend Phase 1 đã đọc `_laca_seo_*` — Phase 2 là nơi NHẬP dữ liệu đó. Rank Math vẫn active trên local → chỉ test
bằng cờ `?laca_seo_force=1` (local) như Phase 1.

## P2.0 Nguồn tham chiếu (phải đọc trước khi code)
- Meta box PHP: `RM/includes/admin/metabox/class-metabox.php`, `class-screen.php`, `class-post-screen.php`, `class-taxonomy-screen.php`, `class-user-screen.php`.
- UI + dữ liệu editor: `RM/assets/admin/js/rank-math-app.js`, `gutenberg.js`, `classic.js`, `gutenberg-primary-term.js`, `post-list.js` (minified nhưng đọc được).
- Content analysis: `RM/assets/admin/js/analyzer.js` (thư viện analyzer: class từng test, ngưỡng, điểm số, câu thông báo) + danh sách test bật/tắt theo
  ngữ cảnh trong `rank-math-app.js` (`keywordInTitle:!0,…`) và `class-post-screen.php::get_analysis` / power words / diacritics ở `class-screen.php`.
- Pro: `RMP/includes/admin/class-quick-edit.php`, `class-bulk-actions.php`, `class-post-filters.php`, `class-common.php` (giới hạn focus keyword 100),
  `RMP/includes/modules/status/admin/csv-import-export/*`.

## P2.1 Meta box bài viết (`Admin/Metabox/PostMetabox.php` + JS `resources/scripts/admin/laca-seo/metabox/*`)
- Đăng ký `add_meta_box('laca_seo_metabox', 'Laca SEO', …, $pt, 'normal', 'high')` cho post type có `pt_{pt}_add_meta_box = on` (registry đã có).
  Hoạt động ở cả Classic và Gutenberg (khu meta box dưới nội dung). KHÔNG làm sidebar plugin React.
- Asset riêng: entry webpack `laca-seo-editor` (JS + SCSS), chỉ enqueue ở `post.php`/`post-new.php` của post type đó, version `filemtime`.
- Tab (giữ thứ tự + nhãn như RM, tiếng Việt):
  1. **Chung**: SERP preview (desktop/mobile, chuyển qua lại) gồm tiêu đề SEO, URL (permalink), mô tả — render biến bằng AJAX `laca_seo_preview_vars`
     mở rộng nhận `post_id` (dùng chính bài đang sửa, nội dung chưa lưu lấy từ editor) ; ô **Tiêu đề SEO** + **Mô tả** (variable_text, chèn biến, đếm ký tự
     như settings, placeholder = mẫu của post type); **Từ khoá chính** dạng tag (Enter/phẩy để thêm, kéo thả thứ tự, tối đa 100 — Pro; từ khoá đầu là chính);
     **Pillar content** (checkbox); **Điểm SEO** (vòng tròn màu: <50 đỏ, 50–79 cam, ≥80 xanh — đúng ngưỡng RM) + danh sách kiểm tra 4 nhóm (P2.2);
     **Khoá ngày sửa** (`lock_modified_date`); **Ẩn điểm SEO ngoài frontend** (`dont_show_seo_score`).
  2. **Nâng cao**: robots (index/noindex, nofollow, noarchive, noimageindex, nosnippet — mặc định theo `pt_{pt}_custom_robots`/global, có nút "Dùng mặc định"),
     advanced robots (max-snippet, max-video-preview, max-image-preview), canonical URL, breadcrumb title. (Redirect của bài → Phase 4, chưa hiện.)
  3. **Schema**: placeholder "Phase 3" (chưa có chức năng) — hiện loại schema mặc định của post type đang áp dụng.
  4. **Mạng xã hội**: Facebook (tiêu đề, mô tả, ảnh qua wp.media — lưu url + id), Twitter (dùng dữ liệu Facebook on/off, card type summary_large_image /
     summary / app / player, tiêu đề, mô tả, ảnh, + nhóm field player & app hiện theo card type). Preview thẻ chia sẻ (khung FB/X đơn giản).
  Overlay ảnh (Pro) → Phase 6, không làm.
- **Primary term**: với mỗi taxonomy hierarchical được chọn ở `pt_{pt}_primary_taxonomy`, chọn term chính (Gutenberg: dropdown trong panel taxonomy như
  `gutenberg-primary-term.js`; Classic: link "Đặt làm chính" cạnh checkbox term). Lưu `_laca_seo_primary_{tax}`; frontend biến `%primary_taxonomy_terms%`
  + breadcrumb dùng term này.
- Lưu: hook `save_post` (bỏ qua autosave/revision), `wp_verify_nonce('laca_seo_save_meta')` + `current_user_can('edit_post', $id)`, sanitize theo kiểu
  (title/desc: `sanitize_text_field`; canonical: `esc_url_raw`; robots: whitelist; keyword: tối đa 100, trim, bỏ trùng). Giá trị rỗng → `delete_post_meta`
  (không lưu chuỗi rỗng). Gutenberg: meta box được WP lưu sau request REST — kiểm tra thật bằng Puppeteer.
- `lock_modified_date`: filter `wp_insert_post_data` giữ `post_modified` cũ khi bật (như RM `class-post-screen.php`).
- Điểm SEO lưu `_laca_seo_seo_score` (int) qua hidden input do JS điền trước khi submit.

## P2.2 Content analysis (JS, chạy local)
- Port **toàn bộ** test theo `analyzer.js`, giữ id: Basic SEO (keywordInTitle, keywordInMetaDescription, keywordInPermalink, keywordIn10Percent, keywordInContent,
  lengthContent), Additional (keywordInSubheadings, keywordInImageAlt, keywordDensity, lengthPermalink, linksHasExternals, linksNotAllExternals, linksHasInternal,
  keywordNotUsed), Title Readability (titleStartWithKeyword, titleSentiment, titleHasPowerWords, titleHasNumber), Content Readability (contentHasTOC,
  contentHasShortParagraphs, contentHasAssets). Bỏ `hasContentAI`. Giữ đúng ngưỡng, trọng số điểm, cách tính tổng điểm và cách chuẩn hoá tiếng Việt/dấu
  (diacritics) như RM. Danh sách power words/sentiment: lấy đúng danh sách RM (kèm bản tiếng Việt nếu RM có theo locale).
- Bật/tắt test theo ngữ cảnh đúng như RM (page bỏ một số test, term/user chỉ 5 test: keywordInTitle, keywordInMetaDescription, keywordInPermalink,
  keywordNotUsed, titleStartWithKeyword).
- `keywordNotUsed`: AJAX kiểm tra từ khoá đã dùng ở bài khác (meta `_laca_seo_focus_keyword`) — prepared query, cache.
- Nguồn nội dung: Gutenberg `wp.data.select('core/editor')` (title, content, slug, excerpt, featured image), Classic: tinyMCE/textarea; cập nhật realtime
  (debounce 500ms). Câu thông báo từng test tiếng Việt, ngắn, kèm gợi ý sửa (giống tinh thần note 3 dòng).
- Xuất `window.lacaSeoAnalysis = { score, results: { [testId]: { ok: bool, score: n } } }` để test so sánh.

## P2.3 Term meta + user meta
- Term edit screen cho taxonomy có `tax_{tax}_add_meta_box = on`; profile user khi `author_add_meta_box = on` (chỉ người có quyền edit user).
  Tab Chung (preview SERP, tiêu đề, mô tả, từ khoá, 5 test), Nâng cao (robots, advanced robots, canonical, breadcrumb title), Mạng xã hội.
  Term có thêm `description_editor` như RM (`rank_math_description_editor`). Lưu qua `edited_{tax}`/`create_{tax}` và `personal_options_update`/`edit_user_profile_update`
  với nonce + cap (`edit_term`, `edit_user`).

## P2.4 Danh sách bài / term (`Admin/ListScreens/*`)
- Cột **SEO** (điểm màu + từ khoá chính + badge Pillar/Noindex/Canonical), **Tiêu đề SEO**, **Mô tả SEO** cho post type có metabox; term list có cột tương tự.
  Ẩn được qua Screen Options. Không query trong vòng lặp (meta đã được WP prime cho list table — kiểm tra bằng Query Monitor hoặc đếm `$wpdb->num_queries`).
- Bộ lọc dropdown: Điểm tốt (≥80) / Khá (50–79) / Kém (<50) / Chưa có từ khoá / Noindex — dùng `meta_query` chuẩn.
- Bulk actions: Đặt noindex, index, nofollow, follow, Xoá canonical (Pro) — chạy qua `handle_bulk_actions-edit-{pt}` + nonce mặc định, notice kết quả.
- Quick edit + Bulk edit (Pro, theo `pt_{pt}_bulk_editing`: off / editing / readonly): tiêu đề SEO, mô tả, robots, từ khoá chính, canonical, primary term.
  Lưu qua `save_post` dùng chung sanitize của P2.1 (nonce riêng cho inline).
- (Link counter, schema column → Phase 3/6.)

## P2.5 CSV meta (Pro) — view Import/Export thêm mục "CSV meta bài viết & term"
- Export: chọn post type/taxonomy → CSV UTF-8 có BOM, cột như RM Pro (`id, object_type, slug, seo_title, seo_description, is_pillar_content,
  focus_keyword, seo_score, robots, advanced_robots, canonical_url, primary_term, social_facebook_title/description/thumbnail, social_twitter_…`),
  qua `admin_post` + nonce + cap, `ob_end_clean` trước khi gửi.
- Import: upload CSV (≤5MB, đuôi .csv), chạy batch AJAX 100 dòng/lượt có progress, chỉ cập nhật cột có trong file, khớp theo `id` (fallback `slug`+`object_type`),
  báo cáo số dòng cập nhật/lỗi (dòng nào, vì sao). Không tạo bài mới.

## P2.6 Settings đi kèm (đã có field ở Phase 1, giờ nối logic)
- `frontend_seo_score`, `frontend_seo_score_post_types`, `frontend_seo_score_template`, `frontend_seo_score_position` (General › Khác) + shortcode
  `[laca_seo_score]` — hiển thị badge điểm SEO ngoài frontend như RM `class-frontend-seo-score.php` (tôn trọng `dont_show_seo_score`). Chỉ chạy khi RM không active.

## P2.7 Nghiệm thu (dán NGUYÊN VĂN output)
1. **`doc/laca-seo/tools/compare-meta.php` (CLI)**: với 3 bài thật (1 journal, 1 product, 1 page) + 1 term product_cat: backup meta → ghi CÙNG bộ giá trị
   (title, description, robots noindex+nofollow, advanced robots, canonical, FB/Twitter title-desc-image, breadcrumb title) vào `rank_math_*` và `_laca_seo_*`
   → so `<head>` RM vs Laca (`?laca_seo_force=1`) → **khôi phục meta gốc**. Kỳ vọng 0 khác (ngoài strip `<p>`).
2. **`doc/laca-seo/tools/compare-analysis.js` (Puppeteer, cookie tạo bằng PHP-CLI lúc chạy, KHÔNG ghi cứng cookie/mật khẩu vào file)**: mở editor 5 bài thật, đặt cùng
   focus keyword cho RM và Laca, đọc kết quả RM từ store `wp.data.select('rank-math')` (hoặc object RM tương đương — tìm trong `rank-math-app.js`) và
   `window.lacaSeoAnalysis` → bảng `testId | RM | Laca`, điểm tổng RM vs Laca. Kỳ vọng mọi test cùng ok/fail, điểm lệch ≤ 1. KHÔNG bấm Lưu bài.
3. Puppeteer: sửa SEO 1 bài trong Gutenberg + 1 bài Classic (nếu có post type dùng classic) → Lưu → đọc lại meta bằng PHP-CLI đúng giá trị → khôi phục.
   Quick edit + bulk action noindex trên list → kiểm meta → khôi phục.
4. CSV: export → sửa 1 ô ở bản sao → import → meta đổi đúng → import lại bản gốc khôi phục.
5. `php -l`, `yarn build`, `yarn lint` 0 error; editor + list ở 1440/900/390 không tràn, 0 lỗi console (cả editor Gutenberg).
6. Mọi thay đổi dữ liệu thật trong test PHẢI khôi phục; không tạo bài/term mới; screenshot để NGOÀI theme; không commit/push.

---
# Spec chi tiết Phase 3A — Sitemap, Breadcrumbs, IndexNow, llms.txt

Nguyên tắc chung (như các phase trước):
- RM = `wp-content/plugins/seo-by-rank-math/` (chỉ ĐỌC). Port logic 1:1, id option/key giữ y RM. Mọi output frontend **chỉ chạy khi RM không active**,
  hoặc khi cờ dev `laca_seo_force_frontend` hợp lệ (cơ chế Phase 1: chỉ env local hoặc user `manage_options`).
- Không tạo bài/term/page mới, không xoá file, không cài package, không commit. Dữ liệu test phải khôi phục. Screenshot/script tạm để ngoài theme (`/private/tmp/...`).
- Mọi field có `note{what,how,result}` tiếng Việt như Phase 1. Asset admin tiếp tục dùng entry `laca-seo`.

## P3A.0 Nguồn tham chiếu
- Sitemap: `RM/includes/modules/sitemap/` (router, sitemap, generator, providers/{post-type,taxonomy,author}, image-parser, cache, cache-watcher,
  classifier, timezone, stylesheet + `sitemap-xsl.php`, redirect-core-sitemaps, admin, settings/*.php, html-sitemap/*).
- Breadcrumbs: `RM/includes/frontend/class-breadcrumbs.php`, shortcode trong `RM/includes/frontend/class-shortcodes.php`, settings breadcrumbs trong General (đã có field ở Phase 1).
- IndexNow: `RM/includes/modules/instant-indexing/` (+ views). llms.txt: `RM/includes/modules/llms/`.
- Dữ liệu LIX: `rank-math-options-sitemap` 16 key (items_per_page=200, include_images=on, html_sitemap=on display=shortcode, authors_sitemap=on,
  pt_post/page/product=on, pt_attachment=off, tax_category=on, tax_post_tag=off…); module bật: sitemap, instant-indexing; llms TẮT.

## P3A.1 Settings Sitemap (`Fields/SitemapFields.php`, option `laca_seo_sitemap`) + view `sitemap`
- Thay placeholder "Sắp có" bằng màn thật, tab: Chung · Sitemap HTML · Tác giả · mỗi post type · mỗi taxonomy (tên tab không trùng nhau, như Titles).
- Field 1:1 theo `RM/includes/modules/sitemap/settings/{general,html-sitemap,authors,post-types,taxonomies}.php` (id, type, default, choices, điều kiện hiển thị):
  `items_per_page, include_images, include_featured_image, exclude_posts, exclude_terms, html_sitemap, html_sitemap_display, html_sitemap_shortcode (hiển thị
  shortcode, không lưu), html_sitemap_page, html_sitemap_sort, html_sitemap_show_dates, html_sitemap_seo_titles, authors_sitemap, authors_html_sitemap,
  include_authors_without_posts, exclude_roles, exclude_users, pt_{pt}_sitemap, pt_{pt}_html_sitemap, pt_{pt}_image_customfields, tax_{tax}_sitemap,
  tax_{tax}_html_sitemap, tax_{tax}_include_empty`. Default per post type/taxonomy y như RM (đọc kỹ điều kiện default trong file).
- Viết `doc/laca-seo/tools/fieldcmp-sitemap.php` (CLI): liệt kê key registry vs key trong `rank-math-options-sitemap` + default RM → in bảng thiếu/thừa/khác default. Kỳ vọng 0 thiếu.
- Importer RM (Phase 1) đã chép option sitemap; xác nhận sau import `laca_seo_sitemap` khớp RM 16/16 key.
- Lưu settings → xoá cache sitemap + `flush_rewrite_rules()` khi đổi cấu trúc (như RM admin).

## P3A.2 XML Sitemap (`Sitemap/` : Router, Sitemap, Generator, Providers/{PostType,Taxonomy,Author}, ImageParser, Cache, CacheWatcher, Stylesheet)
- Rewrite y RM: `sitemap_index.xml`, `{type}-sitemap{n}.xml`, `{type}-sitemap.xsl`/`main-sitemap.xsl`; `sitemap.xml` → 301 `sitemap_index.xml`;
  redirect `wp-sitemap*.xml` core như `class-redirect-core-sitemaps.php`; tắt core sitemap (`wp_sitemaps_enabled` false) khi Laca sitemap chạy.
- Provider giữ đúng query/điều kiện loại trừ của RM: noindex (meta + settings robots post type/taxonomy), `exclude_posts/terms`, canonical khác URL, password,
  hidden product (Woo), số item mỗi trang, `lastmod` (timezone class), ảnh (image parser: content, gallery, featured theo `include_featured_image`,
  custom field theo `pt_{pt}_image_customfields`), author (exclude roles/users, without posts).
- Cache: transient tiền tố **`laca_seo_sitemap_`** (KHÔNG dùng `sitemap_` của RM), invalidation như cache-watcher (save/delete post, term, user, settings).
- XSL stylesheet: giống RM về cấu trúc bảng, đổi branding sang "Laca SEO" (đường link credit bỏ).
- `robots.txt`: thêm `Sitemap: {home}/sitemap_index.xml` đúng như RM (đối chiếu RobotsTxt Phase 1).
- Polylang: hành vi y RM trên LIX (RM có notice `multilingual_sitemap_notice`) — xác định bằng so sánh thực tế, không tự sáng tạo.
- **Khi RM còn active (để so sánh):** Laca KHÔNG đăng ký rewrite; chỉ phục vụ qua query var khi cờ force hợp lệ:
  `/?laca_seo_force_frontend=1&laca_sitemap=index` | `&laca_sitemap=post&laca_sitemap_n=1` | `&laca_sitemap=xsl` — URL bên trong XML vẫn là URL thật
  (`/post-sitemap.xml`…) để so sánh được.

## P3A.3 HTML Sitemap (`Sitemap/Html/*`)
- Port `html-sitemap/{class-sitemap,class-posts,class-terms,class-authors}.php`: shortcode `[laca_seo_html_sitemap]`; khi RM không active đăng ký thêm
  alias `[rank_math_html_sitemap]` (để nội dung cũ trên site không vỡ sau cutover). `html_sitemap_display=page` → gắn vào page `html_sitemap_page`.
- Markup + thứ tự + sort + show dates + SEO titles y RM; class CSS đổi tiền tố `laca-seo-` nhưng giữ cấu trúc (ghi chú nếu theme cần style).

## P3A.4 Breadcrumbs (`Frontend/Breadcrumbs.php`)
- Port toàn bộ `class-breadcrumbs.php` (home, 404, search, attachment, product, singular + ancestors + primary term (meta `_laca_seo_primary_{tax}` Phase 2),
  shop, post type archive, category/tag/tax + term ancestors, date, author, blog page, breadcrumb_title meta), settings breadcrumbs_* từ General.
- API: `laca_seo_get_breadcrumbs(array $args=[]): string`, `laca_seo_the_breadcrumbs()`, shortcode `[laca_seo_breadcrumb]`; khi RM không active định nghĩa
  thêm `rank_math_the_breadcrumbs()`, `rank_math_get_breadcrumbs()` (bọc `function_exists`, đăng ký sau `plugins_loaded`) + alias shortcode `[rank_math_breadcrumb]`.
- **Khi RM active và không force: `laca_seo_the_breadcrumbs()` gọi `rank_math_the_breadcrumbs()`** — wrapper `laca_seo_breadcrumbs()` trong
  `P/app/helpers/template_tags.php` ưu tiên Laca, nên nếu Laca tự render sẽ đổi giao diện live trước cutover.
- Markup HTML giống RM (cấu trúc, separator, aria, `last` item) — class đổi `laca-seo-breadcrumb`; kiểm theme CSS không phụ thuộc class RM (đã grep: không).
- BreadcrumbList JSON-LD để Phase 3B (schema graph), nhưng chuẩn bị `get_crumbs()` dùng chung.

## P3A.5 Instant Indexing / IndexNow (`Modules/InstantIndexing/*`, option `laca_seo_instant_indexing` đã import)
- Field 1:1 RM (`bing_post_types`, `indexnow_api_key` — sinh key mới khi trống, nút "Tạo key mới"), route `/{key}.txt` trả key (text/plain) như RM.
- Tự gửi khi publish/cập nhật post type đã chọn (logic `before_save_post`/`save_post` RM), bulk action "Gửi IndexNow" trên list, row action, trang gửi tay
  (textarea URL) + lịch sử 100 lần gần nhất (option riêng `laca_seo_indexnow_log`).
- **An toàn:** không gửi request thật khi `wp_get_environment_type()` là `local`/`development` hoặc host kết thúc `.local` — ghi log "bỏ qua (môi trường local)".
  Chỉ chạy khi RM không active (tránh gửi đôi). Có filter `laca_seo/indexnow/endpoint`.
- Key IndexNow là công khai (đặt ở `/{key}.txt`) nên lưu option là đúng; không coi là secret nhưng không log ra ngoài.

## P3A.6 llms.txt (`Modules/Llms/*`)
- Port `RM/includes/modules/llms/` (route `/llms.txt`, field `llms_post_types, llms_taxonomies, llms_limit, llms_extra_content`, default y RM).
  Module mặc định TẮT như RM (LIX đang tắt) — bật/tắt ở Dashboard modules.

## P3A.7 Việc hoãn từ Phase 2
1. Dịch toàn bộ thông điệp phân tích (text/empty/tooltip của 23 test) sang tiếng Việt tự nhiên, giữ đúng nghĩa và placeholder số (`%1$d`…); không đổi logic.
2. Hiện `hasProductSchema`, `isReviewEnabled` trong nhóm phù hợp của bảng phân tích (như RM: nhóm Basic/Additional — đối chiếu UI RM), counter nhóm đúng.

## P3A.8 Hướng dẫn & ghi chú
- Trang Hướng dẫn: thêm mục Sitemap (nộp Search Console/Bing), Breadcrumbs (bật + dùng shortcode/hàm), IndexNow, llms.txt; checklist trạng thái
  (sitemap truy cập được, robots.txt có dòng Sitemap, core sitemap đã tắt).

## P3A.9 Nghiệm thu (dán NGUYÊN VĂN output)
1. `doc/laca-seo/tools/compare-sitemap.php` (CLI, HTTP thật): lấy RM `sitemap_index.xml` + MỌI sitemap con (mọi trang n) vs Laca qua query var force →
   so tập `<loc>`, `<lastmod>`, số `image:image` mỗi URL; in bảng theo sitemap: số URL RM/Laca, thiếu/thừa, lastmod lệch. Kỳ vọng 0 khác.
   Kiểm thêm: XSL Laca trả 200 + content-type XML; `sitemap.xml` 301 (chỉ kiểm được khi RM tắt → mô phỏng bằng gọi hàm router trong CLI).
2. `doc/laca-seo/tools/compare-breadcrumbs.php` (CLI, HTTP): 19 URL của compare-head → trích breadcrumb RM vs Laca (force) → so text từng crumb + href
   (bỏ qua khác biệt tên class). Kỳ vọng 0 khác.
3. HTML sitemap: CLI `do_shortcode('[rank_math_html_sitemap]')` vs `do_shortcode('[laca_seo_html_sitemap]')` → so danh sách link + thứ tự. 0 khác.
4. IndexNow: CLI chặn HTTP bằng filter `pre_http_request`, gọi submit cho 2 URL → in payload Laca và payload RM (gọi API class RM tương ứng nếu được) → giống
   cấu trúc (host, key, keyLocation, urlList). Xác nhận môi trường local không gửi thật. Route `/{key}.txt` trả đúng key.
5. llms.txt: so output Laca với RM sinh từ cùng settings (gọi class RM trong CLI nếu tạo được instance; nếu không, giải thích và so theo code).
6. `fieldcmp-sitemap.php` output; `compare-head.php` vẫn 19/19 MATCH; `compare-analysis.js` ≥ 3 ca vẫn diffs=0 (sau khi dịch thông điệp).
7. `php -l` mọi file, `yarn build` (child), `yarn lint` 0 error; màn Sitemap settings 1440/900/390 không tràn, 0 lỗi console.
8. Báo cáo: file tạo/sửa, field theo tab so với RM, điểm lệch RM + lý do. Không commit.

---
# Spec chi tiết Phase 3B — Schema (JSON-LD), WooCommerce SEO, sẵn sàng CUTOVER

Khảo sát LIX (21/10/2026): KHÔNG có meta `rank_math_schema_*` (không có schema riêng từng bài), không có template schema (CPT `rank_math_schema` = 0),
nội dung/widget/option KHÔNG dùng block/shortcode RM. JSON-LD hiện tại = graph mặc định (Person+Organization, WebSite, ImageObject, WebPage/ItemPage,
Product/Article…) — đây là thứ PHẢI giữ y hệt sau cutover. `pt_{post,page}_default_rich_snippet=article`, `pt_product=product`, attachment off.
WooCommerce (General): wc_remove_product_base/category_base/category_parent_slugs = off, wc_remove_generator=on, noindex_hidden_products=on, gtin=gtin, show_gtin=off.

## P3B.0 Nguồn tham chiếu (chỉ đọc)
- Free: `RM/includes/modules/schema/` (class-jsonld, class-frontend, class-schema, class-db, class-opengraph, snippets/*, class-snippet-shortcode, shortcode/*).
- Pro: `RM-pro/includes/modules/schema/` (class-frontend, class-schema, class-post-type, class-taxonomy, class-display-conditions…) — chỉ phần ảnh hưởng output graph mặc định/per-post (template + display conditions để Phase 5).
- Woo: `RM/includes/modules/woocommerce/*`, `RM-pro/includes/modules/woocommerce/*`.

## P3B.1 JSON-LD graph (`Schema/` : JsonLd, Frontend, Snippets/*)
- Port 1:1 `class-jsonld.php` + snippets: Website, Publisher (Person/Organization theo `knowledgegraph_*`, logo, sameAs social), WebPage (WebPage/ItemPage/
  CollectionPage/SearchResultsPage/ProfilePage… đúng nhánh RM), PrimaryImage, Author, Singular (Article/BlogPosting/NewsArticle theo setting), Product
  (+ ProductWooCommerce, WC attributes, offers/price/availability/reviews/aggregateRating/brand/gtin), ProductsPage (shop/product_cat), Breadcrumbs
  (BreadcrumbList dùng chung `Breadcrumbs::getCrumbs()` của 3A, `hide_in_schema`).
- Thẻ `<script type="application/ld+json" class="laca-seo-schema">` cùng vị trí `wp_head` như RM. Chỉ chạy khi RM không active hoặc force hợp lệ.
- Per-post schema: lưu `_laca_seo_schema_{Type}` cùng cấu trúc `rank_math_schema_*` (importer đã map prefix — xác nhận); frontend đọc và hợp nhất vào graph y
  logic RM (`class-frontend.php` free + pro). Shortcode `[laca_seo_rich_snippet]` + alias `[rank_math_rich_snippet]` khi RM không active.
- Filter tương đương để dev mở rộng: `laca_seo/json_ld` (như `rank_math/json_ld`).

## P3B.2 Tab Schema trong metabox (thay placeholder Phase 2)
- Hiển thị schema đang áp dụng (mặc định theo post type hoặc riêng bài), cho chọn loại trong nhóm free: Article, Book, Course, Event, JobPosting, Music,
  Product, Recipe, Restaurant, Video, Person, Service, SoftwareApplication; form field theo đúng map field của RM (lấy từ mã RM, không tự nghĩ),
  lưu `_laca_seo_schema_{Type}` đúng định dạng RM. Nhiều schema/bài, xoá schema, "Dùng mặc định". UI theo style metabox Phase 2, 1440/900/390.
- Không làm: template schema, display conditions, import từ URL, custom builder (Phase 5).

## P3B.3 WooCommerce SEO (`Modules/WooCommerce/*`)
- Port free: remove product base / category base / parent slugs (rewrite + permalink watcher, chỉ khi bật — LIX đang off), remove generator, noindex
  hidden products (robots + sitemap), product OpenGraph (`product:price:amount`, `currency`, availability… như `class-opengraph.php`), biến WC
  (`class-wc-vars.php`) vào Variables engine, shop page trong sitemap (3A đã có — xác nhận dùng chung).
- Port Pro: GTIN/ISBN/MPN field trên product + variation (`gtin`, `show_gtin`, `gtin_label` hiển thị trên trang SP), brand (`product_brand` taxonomy
  setting), dữ liệu variant vào schema Product. Meta key theo RM (`_rank_math_gtin_code` → `_laca_seo_gtin_code`, importer đã có).
- Field Settings tương ứng đã có ở General (Phase 1) — nối logic, không đổi id.

## P3B.4 Kiểm tra robots/head khi site public (lỗ hổng phát hiện ở 3A)
- Local đang `blog_public=0` ⇒ RM và Laca đều ép noindex mọi trang ⇒ cột Robots của `compare-head.php` luôn "MATCH" (không phát hiện được bài 908 từng
  bị noindex). Viết `doc/laca-seo/tools/compare-head-cli.php`: mỗi URL **một tiến trình php**, hook nạp trước WP load:
  `pre_option_blog_public` = `1`, `pre_option_rank-math-options-general` = bản đã sửa (cách `scratchpad/p3/bc2.php`), rồi render `<head>` 2 lần:
  (A) RM active, (B) `laca_seo_force_frontend` (Laca) — mô phỏng request (REQUEST_URI, `$wp->main()`, bắt `wp_head`). So mọi thẻ như compare-head + **JSON-LD
  (chuẩn hoá: parse JSON, bỏ `class`, so từng node theo `@id`/`@type`)**. 19 URL. Kỳ vọng 0 khác (ngoài khác biệt đã chấp nhận `<p>` trong og desc).

## P3B.5 CUTOVER — tài liệu + công cụ (chưa tắt RM trên production)
- `doc/laca-seo/CUTOVER.md`: checklist cho production LIX: backup DB; Laca SEO > Nhập từ Rank Math (settings + meta, batch); chạy `compare-head-cli`/
  `compare-sitemap`/`simulate-cutover` trên bản sao; tắt RM Free+Pro; Lưu permalink (flush rewrite); kiểm `/sitemap_index.xml`, `/robots.txt`,
  view-source 5 URL mẫu (JSON-LD, canonical, robots), Search Console nộp lại sitemap; cách rollback (bật lại RM — Laca tự nhường).
- Mở rộng `simulate-cutover.php`: thêm kiểm `<head>` + JSON-LD của 5 URL mẫu ở trạng thái RM tắt (so với RM active, blog_public=1 trong bộ nhớ).

## P3B.6 Nghiệm thu (dán NGUYÊN VĂN output)
1. `compare-head-cli.php` 19 URL: 0 khác (gồm JSON-LD, robots khi public).
2. Schema per-post: trên 1 bài journal + 1 product (bản sao dữ liệu trong bộ nhớ hoặc ghi meta tạm rồi khôi phục): ghi cùng 1 schema (Article tuỳ chỉnh / Product
   có GTIN) vào `rank_math_schema_*` và `_laca_seo_schema_*` → so JSON-LD RM vs Laca → khôi phục. 0 khác.
3. WooCommerce: OG product + GTIN hiển thị (show_gtin bật tạm trong bộ nhớ) so với RM.
4. `simulate-cutover.php` mở rộng: PASS hết. `compare-sitemap`, `compare-breadcrumbs`, `compare-analysis` (≥3 ca) vẫn đạt.
5. `php -l`, `yarn build`, `yarn lint` 0 error; tab Schema 1440/900/390, 0 lỗi console.
6. KHÔNG xoá file, không cài package, không sửa plugins/, không tạo bài/term mới, khôi phục 100% dữ liệu test (kiểm lại bằng truy vấn so meta Laca vs RM
   như Claude đã làm), không commit.

---
# Spec chi tiết Phase 4 — Redirections + 404 Monitor (Free + Pro)

Khảo sát LIX 21/10/2026: module `redirections` và `404-monitor` của RM **đang TẮT**, bảng `wp_rank_math_redirections*` / `wp_rank_math_404_logs`
**không tồn tại** ⇒ không có dữ liệu cũ; Laca module mặc định TẮT như RM. Settings liên quan đã có trong `rank-math-options-general`:
`redirections_header_code=301, redirections_debug='', 404_monitor_mode=simple, 404_monitor_limit=100, 404_monitor_ignore_query_parameters=1`.
Vì không có dữ liệu thật ⇒ kiểm chứng bằng **so logic với chính hàm RM** (gọi class RM trong CLI) + test chức năng trong **transaction ROLLBACK**.

## P4.0 Nguồn (chỉ đọc) — đọc HẾT trước khi code
- Free: `RM/includes/modules/redirections/` (class-redirections, class-redirection, class-redirector, class-db, class-cache, class-watcher, class-debugger,
  class-metabox, class-admin, class-table, class-export, class-import-export, views/*), `RM/includes/modules/404-monitor/` (class-monitor, class-db,
  class-admin, class-table, views/*). Schema bảng: tìm `CREATE TABLE` trong `RM/includes/class-installer.php` (hoặc nơi tạo bảng tương ứng).
- Pro: `RM-pro/includes/modules/redirections/` (class-redirections, class-categories, class-schedule, csv-import-redirections/*),
  `RM-pro/includes/modules/404-monitor/class-monitor-pro.php`.
- Settings field: `RM/includes/modules/redirections/` + `404-monitor` options (tìm `add_field` id `redirections_*`, `404_monitor_*`). Đối chiếu với
  `Fields/GeneralFields.php` (Phase 1) — field nào RM có mà Laca thiếu thì bổ sung, đúng id/default/choices.

## P4.1 Kiến trúc (`App\Features\LacaSeo\Modules\Redirections\*`, `Modules\NotFoundMonitor\*`)
- Bảng riêng (tạo bằng `dbDelta`, chỉ khi bật module lần đầu hoặc khi chạy importer; cột/kiểu/index **y hệt** RM, đổi tiền tố):
  `{prefix}laca_seo_redirections`, `{prefix}laca_seo_redirections_cache`, `{prefix}laca_seo_404_logs`
  (+ Pro: taxonomy category cho redirection như `class-categories.php`, tên `laca_seo_redirection_category`; cột/meta lịch schedule như RM).
  Lưu version schema vào option `laca_seo_db_version` để nâng cấp sau. KHÔNG xoá bảng khi tắt module.
- Module key trong `Core/Modules.php`: `redirections`, `404-monitor` (đúng tên RM để importer `rank_math_modules` map 1:1). Mặc định tắt.
- Chỉ chạy redirect/log khi module bật **và** RM không active (hoặc force hợp lệ). Khi RM active: màn admin vẫn quản lý được dữ liệu Laca nhưng
  hiện notice "Rank Math đang xử lý chuyển hướng".

## P4.2 Redirections — chức năng 1:1 RM
1. Sources nhiều dòng; comparison: `exact`, `contains`, `start`, `end`, `regex`; cờ không phân biệt hoa thường (`ignore` case) đúng như RM; chuẩn hoá URL
   (bỏ domain, slash đầu/cuối, query string) **dùng đúng logic RM** (`class-redirector.php`/`class-db.php`, các hàm get/compare/match).
2. Header code: 301, 302, 307, 410, 451 (410/451 không cần destination). Destination hỗ trợ regex backreference `$1` như RM.
3. Trạng thái active/inactive/trash; hits + last_accessed cập nhật như RM; cache bảng `_cache` (URL→id) + invalidation như `class-cache.php`.
4. Thứ tự ưu tiên khi nhiều rule khớp: y RM (exact trước, rồi theo thứ tự RM xử lý — đọc kỹ `class-db.php`).
5. Fallback cho 404 (`redirections_fallback`: default/homepage/custom + `redirections_custom_url`) y RM.
6. Debug mode (`redirections_debug`): admin thấy trang trung gian thay vì redirect, như `class-debugger.php`.
7. Watcher: đổi slug post/term (đã publish) → tự tạo redirect cũ→mới (`redirections_post_redirect`), xoá post → gợi ý như RM `class-watcher.php`.
8. Metabox: field "Chuyển hướng" trong tab Nâng cao của PostMetabox/TermMetabox (Phase 2) như `class-metabox.php` (tạo/sửa redirect cho chính URL bài).
9. Admin màn `laca-seo&view=redirections`: list table (search, filter status, phân trang, bulk activate/deactivate/trash/delete-permanently),
   form thêm/sửa (nhiều source + comparison + case + header + destination + category + schedule Pro), xem nhanh hits.
10. Import/Export: export `.htaccess` (Apache) + Nginx đúng format `class-export.php`; JSON trong Import/Export chung của Laca (Phase 0) thêm mục
    "Redirections"; Pro CSV import theo `csv-import-redirections/*` (cột như RM), CSV export.
11. Pro: categories (lọc theo category), schedule (start/end → chỉ hoạt động trong khoảng), xử lý query param theo `RM-pro .../class-redirections.php`.
12. RankMathImporter: thêm `importRedirections()` + `import404Logs()` đọc bảng RM **nếu tồn tại** (LIX không có ⇒ trả 0, không lỗi), batch 500 dòng,
    giữ id/hits/created; idempotent (không nhân đôi khi chạy lại).

## P4.3 404 Monitor — 1:1 RM
1. Ghi log khi `is_404()` (bỏ admin, REST, cron, file tĩnh như RM): mode `simple` (url, hits, accessed) / `advanced` (thêm referer, user agent, ip theo RM).
2. `404_monitor_limit` (giới hạn số bản ghi, xoá cũ nhất như RM), `404_monitor_ignore_query_parameters`, `404_monitor_exclude` (pattern + comparison).
3. Admin `view=404-monitor`: list (search, sort hits/accessed), bulk delete, "Xoá toàn bộ log", nút "Tạo chuyển hướng" từ 1 hoặc nhiều URL (mở form
   redirections với source điền sẵn), export CSV (Pro), Pro auto-xoá log cũ nếu RM-pro có.
4. Dashboard Laca: thẻ số 404 tuần + số redirect active (khi module bật).

## P4.4 Bảo mật & hiệu năng (bắt buộc)
- Mọi action admin: nonce + `manage_options`; AJAX `check_ajax_referer`; mọi SQL `$wpdb->prepare`; regex người dùng nhập: kiểm hợp lệ trước khi lưu
  (`@preg_match` trả false ⇒ báo lỗi), giới hạn độ dài source 2000 ký tự.
- Redirect chạy sớm (`template_redirect` priority như RM hoặc `parse_request` theo RM), **1 query qua bảng cache** cho URL đã gặp; không query khi module tắt.
- Log 404: 1 INSERT … ON DUPLICATE KEY UPDATE (hoặc cách RM), không chạy cho bot đã loại trừ như RM; không log khi đang là request redirect.
- Escape mọi output list table; `wp_safe_redirect` không dùng cho destination ngoài domain (RM dùng `wp_redirect` — làm như RM, nhưng chặn header
  injection: bỏ ký tự xuống dòng trong destination).

## P4.5 Nghiệm thu — MỘT script tự kiểm, output gọn (để Claude chỉ cần chạy lại)
`doc/laca-seo/tools/test-redirections.php` (CLI, PHP_SAPI guard). Toàn bộ thao tác DB của test chạy trong `START TRANSACTION` … `ROLLBACK`
(bảng InnoDB; tạo bảng bằng dbDelta TRƯỚC transaction vì CREATE gây implicit commit — bảng rỗng là trạng thái hợp lệ của module tắt).
Mỗi ca in đúng 1 dòng `PASS|FAIL <mã ca> <mô tả ngắn> [expected → actual]`, cuối in `SUMMARY pass=N fail=M`. Không in gì khác. Các ca bắt buộc:
- **M1–M20 so khớp vs RM:** với 20 cặp (source, comparison, case, request URL) gồm: exact có/không slash cuối, exact có query string (ignore query bật/tắt),
  contains, start, end, regex có `$1`, regex sai cú pháp, hoa/thường, URL có ký tự Unicode/percent-encode, URL có `#`, nhiều source trong 1 rule,
  2 rule cùng khớp (ưu tiên). Kết quả match + destination cuối của Laca phải BẰNG kết quả khi gọi trực tiếp hàm so khớp của RM
  (require file class RM cần thiết trong CLI; nếu hàm RM phụ thuộc DB thì gọi hàm so sánh thuần của RM, ghi rõ hàm nào).
- **H1–H5 header:** 301/302/307 có Location đúng; 410/451 không Location, status đúng (chạy redirector trong tiến trình con, bắt `headers_list()`/status).
- **C1–C3 cache:** lần 2 cùng URL dùng cache (đếm query), sửa rule → cache bị xoá, rule inactive → không redirect.
- **W1–W2 watcher:** đổi slug bài 866 trong transaction → có redirect cũ→mới; rollback.
- **F1 fallback** homepage/custom khi 404; **D1 debug** admin thấy trang debug, khách bị redirect.
- **S1 schedule (Pro)** ngoài khoảng → không redirect; **K1 category** lọc đúng.
- **L1–L6 404 log:** simple vs advanced cột ghi đúng; ignore query gộp 1 dòng; exclude pattern không ghi; limit 3 → còn 3 dòng mới nhất; không ghi
  admin/REST.
- **E1–E2 export:** htaccess + nginx cho 3 rule mẫu = chuỗi mong đợi lấy từ `RM class-export.php` sinh với cùng dữ liệu (gọi hàm RM nếu được).
- **I1 importer:** bảng RM không tồn tại ⇒ 0, không lỗi; với fixture mảng 3 rule (định dạng hàng RM) ⇒ import 3, chạy lại vẫn 3.
- **X1 security:** destination chứa `\r\n` bị làm sạch; source regex > 2000 ký tự bị từ chối.
Sau ROLLBACK script tự đếm số dòng 3 bảng (phải = trước test) và in `PASS Z1 DB unchanged`.

Ngoài script: Puppeteer (cookie runtime, KHÔNG ghi file cookie) chụp 2 màn admin (redirections list+form, 404 list) ở 1440 và 390 → lưu ảnh trong
`/private/tmp/laca-p4/`, in `overflowX=0`, `console errors=[]`. **Không lưu dữ liệu qua UI** (chặn POST bằng request interception, kể cả REST
`/wp-json/` và `admin-ajax`; abort TẤT CẢ POST — bài học Phase 3B: lần trước để lọt REST save của Gutenberg).

## P4.6 Báo cáo — NGẮN GỌN (tiết kiệm token người review)
Chỉ gồm: (1) danh sách file tạo/sửa; (2) NGUYÊN VĂN output `test-redirections.php` (không chỉnh sửa, không tóm tắt lại); (3) 2 dòng Puppeteer;
(4) dòng cuối `php -l` (đếm file lỗi), `yarn build` (dòng compiled), `yarn lint` (dòng problems); (5) điểm lệch RM có chủ ý + lý do (≤5 dòng).
KHÔNG dán lại spec, KHÔNG mô tả dài dòng, KHÔNG tự sửa số liệu output.

Ràng buộc: không xoá file, không DROP/TRUNCATE/DELETE ngoài transaction test, không cài package, không sửa plugins/, không tạo bài/term, không commit.
Liệt kê mọi bảng đã tạo trong báo cáo.

---
# Spec chi tiết Phase 5 — Schema Pro, Blocks FAQ/HowTo/TOC, Local SEO, News & Video Sitemap

LIX hiện KHÔNG dùng các tính năng này (không có CPT `rank_math_schema`, không block `wp:rank-math/*`, module local-seo/news/video tắt) ⇒ module Laca
mặc định TẮT; kiểm chứng bằng **so với code RM chạy trên cùng dữ liệu nạp trong bộ nhớ** (filter, không ghi DB) như Phase 3B/4.

## P5.0 Nguồn (chỉ đọc, đọc HẾT trước khi code)
- Schema Pro: `RM-pro/includes/modules/schema/` (class-schema, class-post-type, class-taxonomy, class-display-conditions, class-frontend, class-parser,
  class-ajax, class-rest, class-video, class-video-schema-generator, class-media-rss, video/*, shortcode/{claimreview,dataset,faqpage,howto,movie,
  podcastepisode,…}.php, class-snippet-pro-shortcode) + bundle JS schema của Pro (`RM-pro/includes/modules/schema/assets/js/*`) để lấy map field.
- Blocks: `RM/includes/modules/schema/blocks/` (class-block, class-block-parser, faq/, howto/, toc/, schema/, views/options-general.php).
- Local SEO: `RM/includes/modules/local-seo/` + `RM-pro/includes/modules/local-seo/` (class-local-seo, class-frontend, class-admin, class-search,
  class-kml-file, class-location-shortcode, class-block-local-business, shortcodes/{address,map,opening-hours,store-locator}).
- News sitemap: `RM-pro/includes/modules/news-sitemap/*`. Video sitemap: `RM-pro/includes/modules/video-sitemap/*`.

## P5.1 Schema Pro (`Schema/Pro/*`)
1. Thêm loại Pro vào Schema Generator (P3B.2, `SchemaDefinitions`): ClaimReview (FactCheck), Dataset, FAQPage, HowTo, Movie, PodcastEpisode,
   LocalBusiness (đủ danh sách subtype RM), Custom (builder tự do key/value lồng nhau như RM). Field map lấy từ bundle JS Pro, không tự nghĩ.
2. **Template schema**: CPT riêng `laca_seo_schema_tpl` (ẩn menu, quản lý trong `laca-seo&view=schema-templates`), lưu cùng cấu trúc meta RM;
   **display conditions** y `class-display-conditions.php` (include/exclude theo: toàn site, post type, bài cụ thể, taxonomy/term, trang chủ, archive…)
   → frontend gộp template khớp điều kiện vào graph như RM. Metabox: "Chèn từ template".
3. **Import schema từ URL** (`class-parser.php`): nhập URL → lấy JSON-LD → chọn node để thêm. Chỉ cho `manage_options`, nonce, `wp_safe_remote_get`,
   timeout 10s, giới hạn 2MB, chặn IP nội bộ/localhost **trừ** host của chính site (để test local).
4. **Video schema tự động** (`class-video`, `video-schema-generator`, `video/*`): phát hiện YouTube/Vimeo/Dailymotion/TED/VideoPress/WordPress video
   trong nội dung → tạo VideoObject như RM (setting tự động theo RM). Media RSS (`class-media-rss.php`) nếu RM bật cho feed.
5. Shortcode Pro `[laca_seo_rich_snippet id="…"]` + alias RM khi RM tắt (như Phase 3B).
6. RankMathImporter: import CPT `rank_math_schema` → `laca_seo_schema_tpl` (giữ conditions), idempotent; LIX có 0 ⇒ trả 0.

## P5.2 Blocks FAQ / HowTo / TOC (Gutenberg, chuẩn block lacadev — skill lacadev-theme §2)
- Đặt ở PARENT `lacadev-client/app/block-gutenberg/` (dùng chung mọi site): `block-seo-faq`, `block-seo-howto`, `block-seo-toc`, đủ 6 file
  (block.json, index.js, edit.js, save.js, render.php, style.scss), panel Inspector theo chuẩn lacadev (Bố cục → Căn lề → nội dung → Giao diện).
  Kiểm build thực sự gom được block parent (nếu webpack blocks chỉ quét child thì sửa cấu hình tối thiểu, ghi rõ).
- Markup HTML + JSON-LD (FAQPage / HowTo) + TOC (danh sách heading, anchor, exclude headings theo setting `toc_block_*` đã có ở General) **y RM**.
- **Tương thích nội dung cũ**: khi RM tắt, đăng ký thêm block type `rank-math/faq-block`, `rank-math/howto-block`, `rank-math/toc-block` (server render
  bằng renderer Laca) để bài cũ ở các site khác không vỡ; parser schema đọc được cả block RM lẫn block Laca.

## P5.3 Local SEO (`Modules/LocalSeo/*`)
- Settings local (Titles › Local SEO — đã có field Phase 1) nối logic: LocalBusiness/Organization trong graph, opening hours, price range, geo, map.
- Pro multi-location: CPT `laca_seo_locations` (như `rank_math_locations`), mỗi location có schema LocalBusiness; shortcode/block hiển thị:
  address, opening hours, map (Google Maps API key — field setting, không ghi cứng), store locator (search theo khoảng cách như `class-search.php`).
  Alias shortcode RM `[rank_math_contact_info]`, `[rank_math_local]` khi RM tắt. KML file (`locations.kml`) + đưa vào sitemap như RM.
- Importer: CPT `rank_math_locations` → `laca_seo_locations` + meta.

## P5.4 News Sitemap & Video Sitemap (mở rộng Sitemap 3A)
- News: `news-sitemap.xml` (bài trong 48h, publication name/language, genres, exclude terms, post types chọn — field y RM `class-admin.php`), XSL riêng.
- Video: `video-sitemap.xml` từ VideoObject (P5.1.4) + metabox video (`class-video-metabox.php`), XSL riêng. Cả hai vào sitemap index khi bật.

## P5.5 Nghiệm thu — MỘT script `doc/laca-seo/tools/test-phase5.php` (CLI), output 1 dòng/ca + `SUMMARY`, như Phase 4
Mọi dữ liệu test nạp **trong bộ nhớ** (filter `get_post_metadata`, `posts_results`/`the_posts`, `pre_option_*`) — KHÔNG ghi DB; nếu bắt buộc ghi
(CPT template/location) thì trong transaction + chỉ xoá id do script tạo, và Z1 checksum `wp_posts`/`wp_postmeta`/`wp_options` liên quan trước/sau.
Mỗi ca so output Laca với RM chạy trên cùng dữ liệu (render `<head>`/shortcode/sitemap qua tiến trình con như `scratchpad/p3b/render.php`, `blog_public=1`
+ option RM general đã sửa nạp bộ nhớ):
- **T1–T8** mỗi loại Pro (ClaimReview, Dataset, FAQPage, HowTo, Movie, PodcastEpisode, LocalBusiness subtype, Custom): JSON-LD node RM == Laca.
- **DC1–DC6** display conditions: site-wide, post type, bài cụ thể, term, exclude, archive → template xuất hiện đúng trang như RM.
- **P1–P2** import từ URL: URL nội bộ site (trang có JSON-LD) → node đọc được; URL `127.0.0.1`/`10.x` khác host site → bị chặn.
- **V1–V3** video tự động: content có YouTube/Vimeo/WP video → VideoObject RM == Laca.
- **B1–B6** blocks: FAQ, HowTo, TOC — HTML render (chuẩn hoá class) và JSON-LD của block RM (`wp:rank-math/...`) so với RM; block Laca cho cùng JSON-LD;
  block RM render được khi RM tắt (mô phỏng `option_active_plugins` như simulate-cutover).
- **LS1–LS5** Local SEO: LocalBusiness graph, opening hours shortcode, address shortcode, KML, store locator search (2 location, tìm theo toạ độ) so RM.
- **N1–N3** news sitemap: bài <48h có, >48h không, exclude term. **VS1–VS2** video sitemap có entry + XSL 200.
- **R1** regression: `scratchpad/p3b/cmp.php` 19 URL vẫn chỉ 2 lệch đã chấp nhận (gọi script, in số URL lệch). **Z1** DB unchanged.
Puppeteer (abort MỌI POST, cookie runtime không ghi file, ảnh ở `/private/tmp/laca-p5/`): màn schema-templates, editor có 3 block, màn locations —
1440 và 390: in `overflowX` và `console errors`.

## P5.6 Báo cáo — NGẮN (như P4.6)
(1) file tạo/sửa; (2) NGUYÊN VĂN output test-phase5.php; (3) dòng Puppeteer; (4) dòng cuối php -l / yarn build / yarn build:blocks / yarn lint;
(5) lệch RM có chủ ý ≤5 dòng; (6) bảng/CPT/taxonomy mới đã đăng ký. Không dán lại spec, không sửa số liệu output.
Ràng buộc như Phase 4 (không xoá file, không cài package, không sửa plugins/, không tạo bài/term thật, không commit).

---
# Spec chi tiết Phase 6 — Image SEO, Thumbnail overlay, Link Counter/Genius, Role Manager, SEO Analyzer, Tools, Wizard, Admin bar, Widget, Podcast

Dữ liệu LIX liên quan: module RM bật `link-counter`, `seo-analysis`; bảng `wp_rank_math_internal_links` (4 dòng), `wp_rank_math_internal_meta` (105),
`wp_rank_math_link_genius_audit` (11), các bảng link_genius khác 0. Module image-seo, role-manager, podcast TẮT.
Nguyên tắc như Phase 4–5: port 1:1, module mặc định giống trạng thái RM trên site (importer `rank_math_modules`), chỉ chạy khi RM không active
(hoặc force), nghiệm thu so với RM chạy trên cùng dữ liệu nạp bộ nhớ / transaction, KHÔNG gọi dịch vụ ngoài khi test.

## P6.0 Nguồn (chỉ đọc, đọc hết phần liên quan trước khi code)
`RM/includes/modules/{image-seo,links,role-manager,seo-analysis,status,database-tools}/`, `RM/includes/admin/wizard/`,
`RM/includes/class-thumbnail-overlay.php`, admin bar + dashboard widget (tìm `admin_bar_menu`, `wp_dashboard_setup` trong `RM/includes/admin/`),
`RM-pro/includes/modules/{image-seo,link-genius,seo-analysis,podcast,status}/`.

## P6.1 Image SEO (free + Pro)
alt/title tự động theo template biến (`%filename%`, `%title%`…), Pro: caption/description, đổi case, find & replace, avatar alt — field id/default y
`image-seo/options.php` + Pro. Áp dụng khi render nội dung/ảnh đại diện y `class-add-attributes.php` (không ghi DB).

## P6.2 Thumbnail overlay (OG image)
Port `class-thumbnail-overlay.php`: endpoint tạo ảnh OG có overlay (play icon/GIF/custom) theo `facebook_image_overlay`/`twitter_image_overlay`
meta (đã có ở metabox Phase 2) — dùng GD như RM, cache file trong `uploads/laca-seo/overlays/` (tạo thư mục khi cần, không xoá file cũ).

## P6.3 Link Counter + Link Genius (không AI, không remote)
- Link counter: bảng `laca_seo_internal_links`, `laca_seo_internal_meta` (cột y RM), đếm khi lưu bài (`class-contentprocessor.php`), cột Links
  trên list bài (internal/external/incoming) như RM. Importer: chép bảng RM (4 + 105 dòng) idempotent.
- Link Genius (Pro, đọc `RM-pro/.../link-genius/`): broken link audit (chạy theo batch bằng WP-Cron/Action Scheduler sẵn có, HEAD request
  `wp_safe_remote_head` timeout 5s, chỉ khi người dùng bấm quét), bulk cập nhật anchor/URL có **snapshot + rollback**, keyword maps tự chèn link
  (không AI, theo rule người dùng nhập). Bảng y RM tiền tố `laca_seo_`; importer chép `link_genius_audit` (11) idempotent. Bỏ mọi phần gọi API rankmath.

## P6.4 Role Manager
Capabilities `laca_seo_*` tương ứng `rank_math_*` (liệt kê từ `class-capability-manager.php`), màn ma trận role × cap như RM. Admin (`manage_options`)
LUÔN có mọi cap (không thể tự khoá mình). Các màn/metabox Laca kiểm cap tương ứng thay cho `manage_options` cứng (giữ fallback admin).
Importer: map cap RM đang gán cho role → cap Laca.

## P6.5 SEO Analyzer toàn site (local)
Port `seo-analysis-tests.php` + `class-seo-analyzer.php` thành chạy LOCAL: fetch trang chủ của chính site (`wp_remote_get` home_url), chạy các test
RM (title/description length, H1, H2, alt ảnh, keyword, SSL, sitemap, robots.txt, blog_public, permalink, tagline, auto-update, page size, links,
favicon, …) — test cần dịch vụ ngoài (PSI) để tuỳ chọn, mặc định tắt. Kết quả lưu option như RM, màn báo cáo nhóm Basic/Advanced/Performance/Security
với hướng dẫn sửa tiếng Việt. Competitor analysis (Pro): cho nhập URL ngoài, `wp_safe_remote_get`, chặn IP nội bộ (dùng lại guard P5).

## P6.6 Tools (Status & Database tools)
System status (PHP/WP/DB/theme/plugin xung đột SEO), error log viewer (`class-error-log.php`, chỉ đọc), backup settings (dùng lại Importer backup Phase 0),
Database tools: xoá cache sitemap, xoá dữ liệu analyzer, đếm lại link, chuyển block Yoast/AIOSEO FAQ/HowTo/TOC → block Laca (`database-tools/*converter*`)
— mọi tool phá huỷ phải có xác nhận 2 bước + nonce.

## P6.7 Setup Wizard + Admin bar + Dashboard widget
- Wizard nhiều bước như `RM/includes/admin/wizard/` (your-site, import từ RM, sitemap, optimization, schema, role, redirection/404, ready) — dùng
  field registry sẵn có, chỉ ghi khi bấm Lưu từng bước; bỏ bước Search Console (cần OAuth) → thay bằng hướng dẫn + link.
- Admin bar menu "Laca SEO" (điểm SEO trang hiện tại, link nhanh GSC/PSI/Rich Results/FB Debugger như đã định ở PLAN §1), dashboard widget (số 404,
  redirect, bài thiếu meta, điểm trung bình) — chỉ cho user có cap.

## P6.8 Podcast (Pro, ưu tiên thấp)
Feed `/feed/podcast` theo `RM-pro/.../podcast/` (settings, iTunes tags, PodcastEpisode schema đã có P5). Module mặc định tắt.

## P6.9 Nghiệm thu — MỘT script `doc/laca-seo/tools/test-phase6.php`, 1 dòng/ca + `SUMMARY`, như Phase 5 (module RM bật trong bộ nhớ, dữ liệu filter /
transaction KHÔNG DDL bên trong, chỉ xoá id do script tạo, Z1 checksum bảng liên quan, chạy 2 lần):
- **IS1–IS4** Image SEO: alt/title/caption/case trên cùng HTML nội dung → RM == Laca.
- **TO1** overlay: cùng ảnh + overlay → kích thước + md5 ảnh xuất RM == Laca (hoặc lệch pixel ≤1% nếu GD khác bản, in tỉ lệ).
- **LC1–LC3** link counter: cùng `post_content` → số internal/external/incoming RM == Laca; importer chép 4/105 dòng, chạy lại không nhân đôi.
- **LG1–LG3** Link Genius: audit 3 URL (mock `pre_http_request`, KHÔNG mạng thật) → trạng thái RM == Laca; bulk update + rollback trả nội dung về đúng md5.
- **RM1–RM3** role: danh sách cap Laca map đủ cap RM; editor có/không cap đúng như RM; admin không mất cap.
- **SA1–SA5** analyzer: mock HTML trang chủ (`pre_http_request`) → từng test RM == Laca (pass/fail + giá trị).
- **DT1** converter Yoast FAQ → block Laca cho JSON-LD giống RM converter.
- **WZ1** wizard lưu 1 bước ghi đúng option; **AB1** admin bar có menu cho admin, không có cho subscriber; **PD1** podcast feed hợp lệ XML + tag iTunes RM == Laca.
- **R1** cmp.php 19 URL: đúng 2 URL lệch (2 và 1 chỗ); **R2** test-redirections + test-phase5 vẫn `fail=0`; **Z1** DB unchanged.
Puppeteer (abort MỌI POST, cookie runtime không ghi file, ảnh ở `/private/tmp/laca-p6/`): wizard, analyzer, role manager, link genius, status ở
1440/390 — in `overflowX` + danh sách response ≥500 + console errors (phải rỗng).

## P6.10 Báo cáo NGẮN như P5.6 (file, NGUYÊN VĂN output 2 lần chạy, Puppeteer, php -l/build/lint, lệch RM ≤5 dòng, bảng/CPT/cap mới).
Ràng buộc như cũ (không xoá file, không cài package, không sửa plugins/, không tạo dữ liệu thật ngoài transaction, không gọi mạng ngoài khi test, không commit).
