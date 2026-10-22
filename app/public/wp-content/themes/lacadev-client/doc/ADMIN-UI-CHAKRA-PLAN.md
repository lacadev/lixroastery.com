# Plan — Đồng bộ toàn bộ giao diện admin theo ngôn ngữ thiết kế Chakra UI (v3)

> Người thực hiện: Antigravity (code + tự review + commit + push theo từng giai đoạn).
> Plan này THAY THẾ "PHẦN B" của `doc/laca-seo/ADMIN-FIX-UX-PLAN.md` (B1–B11 được gộp vào giai đoạn G5 bên dưới).
> Tham chiếu thiết kế: https://chakra-ui.com/docs/components/concepts/overview (chỉ lấy NGÔN NGỮ THIẾT KẾ — token, kích thước, trạng thái).
> **KHÔNG cài Chakra UI / React / Emotion / bất kỳ package nào.** Admin là PHP + SCSS + Vanilla JS; ta tái tạo Chakra bằng CSS custom properties + SCSS.

P = `app/public/wp-content/themes/lacadev-client` (parent, dùng chung nhiều site). C = `app/public/wp-content/themes/lacadev-client-child`.
Lệnh build chạy trong **C** (webpack của child build cả asset parent): `yarn build`, `yarn lint`. PHP: `php -l`.
PHP CLI + socket: xem `P/doc/laca-seo/tools/compare-head-full.php` (cách gọi binary Local).

---

## 0. Mục tiêu (người dùng chốt)
1. Toàn admin **một ngôn ngữ thiết kế**: field custom (Carbon Fields), trang Laca Admin/Laca Theme, Laca SEO, trang tự viết (Security, Contact Form, Email Log…), màn WP core (list table, postbox, nút, notice), **và panel cấu hình block trong Gutenberg (Inspector)** nhìn như cùng một sản phẩm.
2. Phong cách Chakra: đơn giản, gọn, phẳng, viền mảnh, chữ dễ đọc, trạng thái rõ (hover/focus/disabled/lỗi).
3. **Bỏ tính năng "Admin color"**. Dùng một tông màu mặc định nhẹ nhàng, dễ đọc, cố định trong code.

## 1. Bản đồ file (đọc đúng các file này — không dò lại toàn repo)

| Việc | File |
|---|---|
| Entry webpack (build ra `C/dist/styles/*.css`) | `C/resources/build/webpack/entry.js` — entry `admin` (P/resources/scripts/admin/index.js → import P/resources/styles/admin/index.scss), `editor`, `login`, `laca-seo`, `laca-seo-editor` |
| SCSS admin hiện có | `P/resources/styles/admin/index.scss` (229 dòng), `_admin-custom.scss` (627), `_admin-dashboard.scss` (515, có `--laca-*` cũ), `_admin-help.scss`, `_admin-column-style.scss`, `_contact-form.scss` (1245) |
| SCSS Laca SEO | `P/resources/styles/admin/laca-seo/index.scss`, `editor.scss` |
| SCSS editor (Gutenberg) | `P/resources/styles/editor/index.scss` (43 dòng) — **lưu ý**: file này còn được nạp làm `add_editor_style` (vào CANVAS iframe) ở `P/theme/setup/assets.php:453` |
| SCSS login | `P/resources/styles/login/index.scss` |
| Enqueue admin/editor | `P/theme/setup/assets.php` (dòng ~100–266 admin, ~440–455 editor); child: `C/theme/setup/assets.php:~95–101` (`admin-child.css`) |
| Admin color (BỎ) | `P/app/src/Settings/AdminSettings.php`: `applyAdminColorVariables()` (dòng ~100–118, gọi ở dòng 39), tab `ADMIN COLOR` 4 field `primary_color_ad/secondary_color_ad/bg_color_ad/text_color_ad` (dòng ~843–851); `P/theme/setup/assets.php` dòng ~240–264 (inline `--*-color-ad` + `lacaProjectCharts.primary`). Grep thêm `color-ad` trong P/resources, C/resources (20 chỗ dùng) |
| Dock menu Laca Admin (cột 270px) | `P/app/src/Settings/LacaAdmin/LacaAdminMenuOrganizer.php` (CSS inline trong PHP, dòng ~270+) |
| Inspector block dùng chung | `C/block-gutenberg/utils/inspector-panels.js` (ResponsiveRangeControl, tab PC/Tablet/Mobile, panel Bố cục/Căn lề) |
| PHP có nhiều `style="…"` (top) | AdminSettings.php 64, Security/SecurityManager.php 55, LacaSeo views/import-export.php 50, views/redirections.php 37, PostTypes/Concerns/BlockSyncSender.php 31, ContactForm/ContactFormManager.php 31, Security/TwoFactorAuth.php 30, BlockMarketplace.php 21, Widgets/BlockSyncWidget.php 19, QuickNotesWidget.php 17, EmailLog/EmailLogManager.php 16, LoginLog/LoginLogManager.php 15, DatabaseCleaner.php 14, ContactFormPopupSettings.php 14, views/guide.php 13, views/search-console.php 12, views/404-monitor.php 12, DashboardWidgets.php 10, Metabox/PostMetabox.php 10, DynamicCptMetaEditor.php 10 (tất cả dưới `P/app/src/`) |
| PHP có `<style>` inline | MaintenanceModeManager, AdminSettings, SecurityManager, AdminUxService, LacaAdminMenuOrganizer, ReleaseChecklist, LacaSeo ListScreens/PostList, DiscordHub/AdminPage, DynamicCPT/DynamicCptAdminPage, DynamicCptMetaEditor (dưới `P/app/src/`) |
| Công cụ nghiệm thu sẵn có | `P/doc/laca-seo/tools/ui-probe.js` (Laca SEO, KHÔNG sửa logic kiểm), `C/doc/tools/layout-audit.js` (frontend, phải giữ PASS) |

⚠️ Ngoài phạm vi: `wp-admin/` core, `plugins/`, frontend site (chỉ được đổi admin/editor/login). `add_editor_style` (canvas) KHÔNG được nhận style UI admin — style Inspector phải nằm ở bundle admin (ngoài iframe).

---

## 2. Design tokens (nguồn sự thật DUY NHẤT)

Tạo `P/resources/styles/admin/_tokens.scss`, khai báo trên `:root` (admin.css được nạp ở mọi trang admin + login nạp riêng `@use` file này).
**Không file SCSS admin nào khác được chứa mã màu hex/rgb** (trừ `_tokens.scss`). Mọi nơi dùng `var(--laca-…)`.

### 2.1 Màu — tông "Slate nhẹ + xanh dịu" (giá trị Chakra v3)
| Token | Giá trị | Dùng cho |
|---|---|---|
| `--laca-bg` | `#ffffff` | card, input, panel |
| `--laca-bg-subtle` | `#fafafa` (gray.50) | nền trang admin (`#wpwrap`, `#wpcontent`) |
| `--laca-bg-muted` | `#f4f4f5` (gray.100) | hover hàng, tab subtle, thead bảng |
| `--laca-bg-emphasized` | `#e4e4e7` (gray.200) | nút subtle hover, thanh tiến độ nền |
| `--laca-fg` | `#18181b` (gray.900) | chữ chính |
| `--laca-fg-muted` | `#52525b` (gray.600) | nhãn phụ, mô tả, helper text |
| `--laca-fg-subtle` | `#a1a1aa` (gray.400) | placeholder, icon mờ |
| `--laca-border` | `#e4e4e7` (gray.200) | viền card/input/bảng |
| `--laca-border-emphasized` | `#d4d4d8` (gray.300) | viền input hover |
| `--laca-accent` | `#2563eb` (blue.600) | nút chính, link, tab đang chọn, switch bật |
| `--laca-accent-hover` | `#1d4ed8` (blue.700) | hover nút chính |
| `--laca-accent-subtle` | `#eff6ff` (blue.50) | nền mục menu đang chọn, badge info |
| `--laca-accent-muted` | `#dbeafe` (blue.100) | hover mục menu |
| `--laca-accent-fg` | `#1e40af` (blue.800) | chữ trên nền accent-subtle |
| `--laca-focus-ring` | `#3b82f6` (blue.500) | outline focus |
| `--laca-success` / `-bg` | `#15803d` / `#f0fdf4` | |
| `--laca-warning` / `-bg` | `#c2410c` / `#fff7ed` | |
| `--laca-danger` / `-bg` | `#dc2626` / `#fef2f2` | |
| `--laca-info` / `-bg` | = accent-fg / accent-subtle | |

Tương thích ngược (để code cũ không vỡ, KHÔNG dùng cho code mới): trong `_tokens.scss` alias
`--primary-color-ad: var(--laca-accent); --secondary-color-ad: var(--laca-fg); --bg-color-ad: var(--laca-bg-subtle); --text-color-ad: var(--laca-fg);`
và alias các `--laca-*` cũ trong `_admin-dashboard.scss` (`--laca-primary`, `--laca-text-main`, `--laca-text-muted`, `--laca-bg-soft`, `--laca-border`, `--laca-radius`, `--laca-shadow-sm`) về token mới; xoá khai báo cũ ở `_admin-dashboard.scss`.

### 2.2 Chữ
- Font: `--laca-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;` (Chakra fallback; đủ dấu tiếng Việt, không tải font mới). Áp cho `body.wp-admin`, login, Inspector. Bỏ việc ép Quicksand/font site trong UI admin (font site vẫn dùng trong CANVAS editor như cũ).
- Thang: `xs 12/16`, `sm 14/20` (mặc định UI), `md 16/24`, `lg 18/28` (tiêu đề card), `xl 20/28` (tiêu đề trang h1). Trọng lượng: 400 thường, 500 nhãn/nút, 600 tiêu đề.
- Nhãn field: **chữ thường đầu câu, 14px/500, màu fg** — KHÔNG UPPERCASE (Gutenberg đang uppercase 11px, xem ảnh người dùng gửi: "CĂN TIÊU ĐỀ", "NỘI DUNG NÚT" → đổi thành "Căn tiêu đề", "Nội dung nút").

### 2.3 Khoảng cách, bo góc, kích thước, bóng
- Spacing thang 4px: `--laca-space-1..10` = 4, 8, 12, 16, 20, 24, 32, 40, 48, 64px.
- Bo góc (Chakra v3): `--laca-radius-l1: 2px` (checkbox nhỏ), `--laca-radius-l2: 4px` (input, select, nút, badge, tab), `--laca-radius-l3: 6px` (card, panel, dropdown, alert, modal), `--laca-radius-full: 9999px` (switch, avatar, pill).
- Chiều cao control: `xs 32px` (control trong Inspector & bảng), `sm 36px` (mặc định form admin), `md 40px` (form lớn/login). Padding ngang input 12px, nút 14–16px.
- Bóng: card **không bóng** (Chakra card variant "outline": viền 1px). Chỉ lớp nổi (dropdown, popover, modal, sticky footer khi cuộn) dùng `--laca-shadow-md: 0 4px 8px rgba(24,24,27,.10), 0 0 1px rgba(24,24,27,.30)`.
- Focus: `:focus-visible { outline: 2px solid var(--laca-focus-ring); outline-offset: 2px; box-shadow: none; }` cho mọi control (ghi đè box-shadow focus xanh mặc định của WP).
- Chuyển động: `transition: background-color .15s, border-color .15s, color .15s;` — không animation khác.

---

## 3. Thư viện component CSS (Chakra → class BEM `laca-*`)

Thư mục `P/resources/styles/admin/components/` — mỗi component 1 partial, import trong `admin/index.scss` (và `laca-seo/index.scss` chỉ `@use` tokens nếu cần biến SCSS; biến CSS đã có sẵn từ admin.css). Mọi component có đủ trạng thái: mặc định, hover, focus-visible, active/selected, disabled, invalid (nếu là input).

| Component | Class | Đặc tả |
|---|---|---|
| Button | `.laca-btn` + `--solid` (nền accent, chữ trắng), `--outline` (viền border, nền bg), `--subtle` (nền bg-muted), `--ghost` (trong suốt), `--danger`; size `--xs/--sm/--md` | cao theo 2.3, radius l2, chữ 14/500, icon 16px cách chữ 8px, disabled opacity .5 + cursor not-allowed |
| Input/Select/Textarea | `.laca-input`, `.laca-select`, `.laca-textarea` | nền bg, viền border, hover border-emphasized, focus ring, invalid viền danger; select có mũi tên SVG inline (data URI, màu fg-muted); textarea min-height 80px |
| Field | `.laca-field` > `.laca-field__label` (14/500) + control + `.laca-field__help` (12/16, fg-muted, cách control 6px) + `.laca-field__error` (12, danger) | khoảng cách giữa field 20px |
| Field row (trang cài đặt) | `.laca-field-row` | lưới `minmax(200px, 260px) 1fr`, gap 24px, padding 16px 0, viền dưới border; < 782px xếp 1 cột |
| Checkbox / Radio | `.laca-checkbox`, `.laca-radio` | 16px, radius l1 / full, checked nền accent |
| Switch | `.laca-switch` | track 36×20 radius full, bg-emphasized → accent khi bật, thumb trắng 16px |
| Segmented control | `.laca-segmented` | nền bg-muted, padding 2px, radius l2; mục chọn nền bg + bóng xs, chữ fg; dùng cho PC/Tablet/Mobile và nhóm lựa chọn ≤ 4 mục |
| Tabs | `.laca-tabs` variant `--line` (mặc định: viền dưới 1px border, tab active gạch 2px accent, chữ fg; tab thường fg-muted) và `--subtle` (tab active nền bg-muted) | 1 hàng, `overflow-x:auto`, không wrap, scrollbar ẩn, mờ mép khi tràn |
| Card | `.laca-card` > `__header` (padding 16 20, tiêu đề 16/600, mô tả 14 fg-muted) `__body` (20) `__footer` (16 20, viền trên) | nền bg, viền border, radius l3, không bóng |
| Badge | `.laca-badge` + `--neutral/--accent/--success/--warning/--danger` | cao 20px, chữ 12/500, radius l2, nền *-bg, chữ màu đậm (subtle variant) |
| Alert | `.laca-alert` + `--info/--success/--warning/--danger` | nền *-bg, viền 1px cùng tông nhạt, radius l3, icon 20px bên trái, tiêu đề 14/600 + mô tả 14 |
| Table | `.laca-table` | thead nền bg-muted chữ 12/500 fg-muted (không uppercase), hàng cao 44px, viền dưới border, hover bg-subtle; bọc `.laca-table-wrap { overflow-x:auto }` |
| Accordion / Panel | `.laca-accordion` | header 44px chữ 14/500, chevron xoay, nội dung padding 16 |
| Empty state | `.laca-empty` | icon 32px fg-subtle, tiêu đề 16/600, mô tả 14 fg-muted, 1 nút; căn giữa, padding 40 |
| Stat | `.laca-stat` | nhãn 14 fg-muted, số 24/600, phụ chú 12 |
| Progress | `.laca-progress` | cao 8px, nền bg-emphasized, thanh accent, radius full |
| Page header | `.laca-page-header` | h1 20/600 + mô tả 14 fg-muted + vùng nút bên phải (wrap xuống dòng < 782px) |
| Sticky action bar | `.laca-actionbar` | `position:sticky; bottom:0`, nền bg, viền trên, bóng md khi nổi; nút Lưu bên phải + chữ "Có thay đổi chưa lưu" bên trái |

Kèm **trang mẫu** (style guide sống) để review: `admin.php?page=laca-ui-kit` (đăng ký `add_submenu_page(null, …)` = trang ẩn, capability `manage_options`, class `P/app/src/Settings/LacaAdmin/UiKitPage.php`), hiển thị MỌI component × MỌI trạng thái (tĩnh, không logic). Đây là chuẩn để so khi chuyển từng màn.

---

## 4. "Skin" — áp ngôn ngữ trên vào giao diện có sẵn (không viết lại markup của WP/Carbon/Gutenberg)

Partial riêng trong `P/resources/styles/admin/skins/`. Selector cụ thể, **không dùng `!important` trừ khi cần đè style inline của WP/Carbon** (ghi chú lý do cạnh mỗi `!important`).

### 4.1 `_wp-chrome.scss` — khung WP
- Nền trang: `#wpwrap, #wpcontent, #wpbody` = bg-subtle; chữ fg; font `--laca-font`.
- Sidebar `#adminmenu`, `#adminmenuback`, `#adminmenuwrap`: **sáng** — nền bg, viền phải border; mục: chữ fg-muted 14/500, icon fg-subtle; hover nền bg-muted chữ fg; mục đang mở/chọn nền accent-subtle chữ accent-fg, icon accent; submenu nền bg (không đen), mục con 14/400 thụt 36px, mục chọn chữ accent-fg 500; separator = viền border. Badge cập nhật (`.awaiting-mod`, `.update-plugins`) → badge danger. Trạng thái `folded` và mobile (< 782px, `#adminmenu` dạng overlay) phải đúng.
- Admin bar `#wpadminbar`: giữ tối nhưng dùng `--laca-fg` (#18181b) thay #1d2327, chữ #e4e4e7, hover nền #27272a. (Admin bar xuất hiện cả ngoài frontend khi đăng nhập — đảm bảo không đè style frontend site.)
- `.wrap h1` 20/600, `.page-title-action` → kiểu `.laca-btn--outline --sm`.

### 4.2 `_wp-core.scss` — control/màn WP core
- `.button` → outline sm; `.button-primary` → solid; `.button-link-delete`, `.submitdelete` → chữ danger; `.button-small` → xs.
- `input[type=text|email|url|password|number|search|date|tel]`, `select`, `textarea` trong `.wp-admin` → như `.laca-input` (cao 36px, trừ `.small-text`, list-table filter dùng 32px).
- `.notice` → Alert subtle (notice-success/warning/error/info map đúng màu), `.notice-dismiss` căn giữa dọc.
- `.wp-list-table` → Table (thead bg-muted, không viền ngoài dày, hàng hover, `.row-actions` chữ 12).
- `.postbox`, `.meta-box-sortables .postbox` → Card (header `.postbox-header h2` 14/600).
- `.nav-tab-wrapper/.nav-tab` → Tabs line.
- `.form-table th` 14/500 fg, `td .description` → helper text.
- `.subsubsub` → link fg-muted, mục hiện tại fg 600.
- Dashboard widgets (`#dashboard-widgets .postbox`) → Card.

### 4.3 `_carbon.scss` — Carbon Fields (Laca Admin, Laca Theme, post meta, term meta)
Selector Carbon: `.carbon-theme-options`, `.carbon-container`, `.cf-container`, `.cf-container__tabs`, `.cf-container__tabs-item`, `.cf-container__fields`, `.cf-field`, `.cf-field__head`, `.cf-field__label`, `.cf-field__help`, `.cf-field__body`, `.cf-complex`, `.cf-complex__group`, `.cf-complex__group-head`, `.cf-color__toggle`, `.cf-media-gallery`, `#postbox-container-1 .postbox` (khung "Actions"). **Kiểm selector thật bằng DOM trên trang `admin.php?page=laca-admin` và `admin.php?page=app-theme-options.php` trước khi viết.**
- Tabs Carbon (đang là ô vuông xám như ảnh) → Tabs line, 1 hàng cuộn ngang.
- Lưới field: bỏ viền dọc giữa các cột (đang chia ô kẻ bảng), dùng gap 24px; mỗi field = `.laca-field` (nhãn 14/500 trên, control, help dưới).
- Color field: nút "Select a color" → swatch 28×28 radius l2 viền border + mã màu dạng input; nút xoá → `.laca-btn--ghost --xs` icon.
- Complex/repeater: mỗi group = Card thu gọn được, header 44px.
- Khung "Actions" + nút "Save Changes": → Card + `.laca-btn--solid`; trên màn ≥ 1280px khung này sticky top 48px.
- Thông báo lưu của Carbon → Alert success.

### 4.4 `_laca-dock.scss` — cột dock của Laca Admin (LacaAdminMenuOrganizer)
- Chuyển CSS inline trong PHP sang partial này (giữ hook/markup PHP, chỉ bỏ khối `<style>` và thêm class nếu thiếu).
- Cột 240px nền bg, viền phải border; tiêu đề nhóm 12/500 fg-subtle (sentence case, không uppercase), mục 14/400 fg-muted cao 32px radius l2, hover bg-muted, mục chọn nền accent-subtle chữ accent-fg 500 (thay khung viền xám như ảnh).
- < 1024px: dock thu thành `<select>` hoặc ẩn sau nút "Menu Laca Admin" — không chiếm 270px ở mobile (ảnh 390px hiện dock đẩy nội dung xuống dưới).

### 4.5 `_gutenberg-inspector.scss` — panel cấu hình block (nằm trong bundle ADMIN, KHÔNG trong editor-style canvas)
Phạm vi selector: `.interface-complementary-area`, `.block-editor-block-inspector`, `.edit-post-sidebar`, `.editor-sidebar` (kiểm selector thật trên WP 7.x local).
- `.components-panel__body-title button` → header accordion 14/600 fg, cao 48px; panel mở: nền bg; viền giữa panel border.
- `.components-base-control__label`, `.components-input-control__label`, `.components-toggle-group-control__label` (đang UPPERCASE 11px) → `text-transform:none; font-size:13px; font-weight:500; color: var(--laca-fg)`.
- `.components-text-control__input`, `.components-select-control__input`, `.components-input-control__container`, `textarea.components-textarea-control__input` → cao 32px, radius l2, viền border, focus ring.
- `.components-base-control__help` → 12/16 fg-muted.
- `.components-toggle-group-control` → Segmented control (§3).
- `.components-form-toggle` → Switch (§3).
- `.components-button.is-primary/.is-secondary/.is-tertiary` → solid/outline/ghost.
- Tab PC/Tablet/Mobile của `ResponsiveRangeControl`/responsive select trong `C/block-gutenberg/utils/inspector-panels.js` (đang 3 nút viền xanh rời rạc như ảnh) → đổi markup sang `ToggleGroupControl` của WP (hoặc giữ markup và thêm class `laca-segmented`) để ra đúng Segmented control. Đổi nhãn uppercase cứng trong JS (nếu có) sang sentence case.
- Không đổi vị trí/thứ tự panel (quy ước "Bố cục" → "Căn lề" → nội dung → "Giao diện" giữ nguyên).

### 4.6 `_login.scss` (sửa `P/resources/styles/login/index.scss`)
Trang đăng nhập: nền bg-subtle, form = Card 360px, input md 40px, nút solid full-width, link fg-muted. Không còn đọc màu admin color.

---

## 5. Các giai đoạn (mỗi giai đoạn: build → kiểm → tự review → commit → push)

**G0 — Commit Phần A còn đang chờ** (đã được Claude review): các file đang `M`/`??` trong `git status` liên quan Laca SEO (AdminPage, Renderer, views general/search-console/sitemap, Core/Settings, Fields/*, OpenGraph/Slack, LocalSeo/*, WooCommerce, LacaAdminMenuOrganizer, scripts/styles laca-seo, `doc/laca-seo/ADMIN-FIX-UX-PLAN.md`, `doc/laca-seo/tools/{test-rm-parity.php,ui-probe.js}`, `C/dist/*` do build sinh ra). Message: `fix(laca-seo): showIf đọc đúng toggle, menu cấp 1, GSC access_denied, đủ field Rank Math (Podcast/News/Video/Local/Image SEO)`. Kiểm `git log --stat -1 546ad456` xem các commit trước có commit `dist/` không — làm giống vậy. Push. Thêm file plan này vào commit G1.

**G1 — Token + component + UI kit + bỏ admin color**
- `_tokens.scss`, `components/*`, `UiKitPage.php` (đăng ký cùng chỗ khởi tạo các trang Laca Admin khác — đọc nơi khởi tạo LacaAdminMenuOrganizer).
- Bỏ admin color: xoá tab `ADMIN COLOR` + 4 field khỏi Container Carbon; xoá `applyAdminColorVariables()` và lời gọi; xoá khối inline `--*-color-ad` trong `P/theme/setup/assets.php`; `lacaProjectCharts.primary` = `'#2563eb'` (hằng, ghi chú = token accent). Login không còn in màu. **Không xoá option trong DB** (dữ liệu cũ vô hại).
- Grep `color-ad` trong P/C resources: chuyển sang token mới (alias chỉ để an toàn).

**G2 — Skin WP chrome + WP core** (§4.1, §4.2).

**G3 — Skin Carbon Fields + dock Laca Admin** (§4.3, §4.4). Kiểm cả trang Laca Theme (child `theme-options.php`, nhiều tab, complex field) và meta box Carbon trên trang sửa bài/term.

**G4 — Gutenberg Inspector** (§4.5) + build blocks (`yarn build:blocks`) nếu sửa `inspector-panels.js`.

**G5 — Laca SEO** (thay Phần B cũ): bỏ `--laca-seo-*` và mọi hex ở `laca-seo/index.scss`, `editor.scss` → token + component; nav chính 12 mục → sidebar dọc 220px nhóm Tổng quan / Cài đặt (Chung, Tiêu đề & Meta, Sitemap) / Công cụ (IndexNow, Chuyển hướng, 404, Search Console) / Hệ thống (Sao lưu & Nhập xuất, Hướng dẫn) ở ≥ 1024px, `<select>` "Đi tới…" ở < 1024px; tab con = Tabs line cuộn ngang; header = Page header (bỏ badge PROFESSIONAL); field row = §3; sticky footer = Action bar; bảng = Table wrap; trạng thái rỗng = Empty state; thông báo "Đã lưu cài đặt" tự ẩn 4s; trên trang `page=laca-seo` gỡ notice của plugin bên thứ ba (`remove_all_actions('admin_notices')` + `all_admin_notices` trong `current_screen` khi đúng trang, rồi in lại notice của chính Laca SEO trong khung trang dạng Alert); inline style trong views → class.

**G6 — Trang tự viết còn lại**: chuyển `style="…"` và `<style>` inline (danh sách §1) sang component. Thứ tự: SecurityManager + TwoFactorAuth → ContactForm (Manager, PopupSettings, `_contact-form.scss` thay hex bằng token) → EmailLog, LoginLog, DatabaseCleaner → DashboardWidgets, QuickNotesWidget, BlockSyncWidget, BlockSyncSender, BlockMarketplace → DynamicCPT (AdminPage, MetaEditor) → DiscordHub, ReleaseChecklist, AdminUxService (trang HD Sử dụng), MaintenanceModeManager (phần admin). Được giữ `style` chỉ cho giá trị động thật (vd `width:{$pct}%`, `display:none` ban đầu do JS bật) — ghi chú `// động`.
**Email HTML** (ContactFormEmailService) và trang `theme/maintenance.php`, `theme/offline.php` (hiển thị ngoài frontend) **không thuộc phạm vi** — không sửa.

**G7 — Dọn & tài liệu**: xoá code CSS chết trong `_admin-custom.scss`/`_admin-dashboard.scss` bị thay thế (sửa nội dung file, không xoá file), cập nhật `P/doc/THEME-DEVELOPMENT-GUIDE.md` thêm mục "Admin UI (Chakra tokens)": token, component, quy tắc "admin mới chỉ dùng class `laca-*` + token, không hex, không inline style". Thêm rule vào `CLAUDE.md` gốc dự án mục 5 (1–2 dòng).

---

## 6. Nghiệm thu (bắt buộc mỗi giai đoạn, dán NGUYÊN VĂN output vào báo cáo)

1. Viết `P/doc/admin-ui/tools/admin-ui-audit.js` (Puppeteer, cách đăng nhập + abort POST + cookie runtime giống `P/doc/laca-seo/tools/ui-probe.js` — **chép đúng phần đó, không lưu cookie ra file**). URL kiểm:
   `index.php`, `edit.php?post_type=page`, `post.php?post=101&action=edit` (Gutenberg — chọn 1 block CTA Section trong bài/trang có sẵn để mở Inspector; chỉ đọc, abort POST), `users.php`, `options-general.php`, `admin.php?page=laca-admin` (từng tab), `admin.php?page=app-theme-options.php`, `admin.php?page=laca-ui-kit`, các trang Laca Admin trong dock (Security, Email Log, Contact Forms, Dynamic CPT, Discord Hub, Tools, Dọn dẹp DB), `wp-login.php` (đã đăng xuất), mọi view `admin.php?page=laca-seo&view=…`. Bề rộng 1440 / 1024 / 390.
   **Chống treo (bắt buộc):** `goto` dùng `domcontentloaded` + `timeout:60000`, rồi chờ selector cụ thể ≤ 30s (KHÔNG `networkidle` ở trang editor —
   heartbeat/autosave không bao giờ idle); mỗi URL bọc try/catch → `FAIL timeout`; watchdog toàn cục 15 phút → `process.exit(2)`; `browser.close()` trong `finally`.
   Lệnh nào chạy > 15 phút không ra output → tự dừng, ghi FAIL, báo lại.
   Kiểm và in `PASS|FAIL <check> <url> <w> [chi tiết]` + `SUMMARY`:
   - `no-overflow`: `scrollWidth <= innerWidth` và không phần tử nào vượt mép phải (trừ trong khung `overflow-x:auto`).
   - `no-js-error`: không `pageerror`.
   - `tokens`: `getComputedStyle(document.documentElement).getPropertyValue('--laca-accent').trim() === '#2563eb'`.
   - `button-primary`: mọi `.button-primary`/`.laca-btn--solid` hiển thị có `background-color` = rgb(37, 99, 235) và chữ trắng.
   - `input-style`: input text/select hiển thị (ngoài canvas editor) có `border-radius` = 4px, chiều cao ∈ {32, 36, 40}.
   - `label-case`: trong Inspector, `.components-base-control__label` có `text-transform` = none.
   - `font`: `body` font-family bắt đầu bằng `-apple-system`.
   - `contrast`: lấy 20 phần tử chữ hiển thị đầu tiên trong `#wpbody-content`, tính tương phản với nền gần nhất ≥ 4.5 (chữ < 18px).
   - `no-admin-color`: HTML không chứa `--primary-color-ad:` dạng inline `<style>` và không còn field `primary_color_ad` trên trang laca-admin.
2. `node P/doc/laca-seo/tools/ui-probe.js` → `SUMMARY fail=0` (sau G5; trước G5 chỉ cần không tăng fail so với `fail=48`).
3. Kiểm tĩnh (in số đếm): `grep -rEo "#[0-9a-fA-F]{3,8}\b" P/resources/styles/admin --include=*.scss | grep -v _tokens.scss | wc -l` → **0**; số `style="` trong từng file §1 (trước → sau), mục tiêu chỉ còn giá trị động có ghi chú.
4. Frontend không đổi: `node C/doc/tools/layout-audit.js` vẫn 67/67 PASS; `compare-head-full.php` vẫn `BAD URLS: 2`.
5. `yarn lint` 0 error, `yarn build` (và `yarn build:blocks` nếu G4 sửa block utils) thành công; `php -l` mọi PHP sửa.
6. Ảnh chụp trước/sau 1440 + 390 của: laca-admin, app-theme-options.php, laca-seo general, Inspector CTA Section, users.php, login → `/private/tmp/admin-ui/<giai-đoạn>/`.

**Tự review trước mỗi commit** (ghi vào báo cáo dạng checklist ✓/✗): không test giả (hằng true, chỉ kiểm file tồn tại); không đổi DB; không sửa `plugins/`, `wp-admin/`, frontend; không `!important` thiếu ghi chú; không hex ngoài `_tokens.scss`; diff không chứa debug (`console.log`, `var_dump`, `error_log` tạm).

## 7. Commit & push
- Mỗi giai đoạn 1 commit, message tiếng Việt kiểu conventional: `style(admin-ui): G1 token + component Chakra, bỏ admin color` … ; kèm `dist/` nếu repo đang theo dõi dist (xem G0).
- Push lên `master` sau khi giai đoạn đó PASS toàn bộ §6. **Không** `push --force`, không rebase, không đổi branch. Push kích hoạt deploy production (GitHub Actions FTP) — nếu workflow deploy báo lỗi (`gh run list --limit 1`), dừng lại và báo, không push tiếp.
- Nếu một giai đoạn FAIL mà không sửa được trong 2 lượt: dừng, báo cáo nguyên văn lỗi, không commit giai đoạn đó.

## 8. Ràng buộc chung
Không cài/gỡ package (kể cả Chakra), không mu-plugin, không xoá file, không đổi DB/option/nội dung, không sửa tay `dist/`, không đổi frontend site, không đụng project khác (parent dùng chung: đổi ở đây sẽ tới site khác khi đồng bộ — đó là chủ đích, chỉ ghi chú trong báo cáo). Đọc file trước khi sửa; sửa đúng chỗ, không reformat cả file. Không kết thúc lượt khi lệnh còn chạy; không để watcher chạy nền.
Báo cáo mỗi giai đoạn NGẮN qua agent-bridge (`progress`), báo cáo cuối (`result`) gồm: commit hash từng giai đoạn, file sửa, NGUYÊN VĂN `SUMMARY` của admin-ui-audit / ui-probe / layout-audit / compare-head, đếm hex & inline style trước/sau, đường dẫn ảnh, việc còn tồn (nếu có).
