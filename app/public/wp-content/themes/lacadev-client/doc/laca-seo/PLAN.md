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
| 2 | ⏳ Chưa bắt đầu | Spec chi tiết sẽ thêm khi bắt đầu |

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
