# Plan — Sửa lại Admin UI: đơn sắc, trả menu như cũ, xem trước & chọn phương án trước khi sửa

> Người thực hiện: Antigravity. Nối tiếp `doc/ADMIN-UI-CHAKRA-PLAN.md` (G0–G7 đã push, HEAD `82da5b5f`).
> P = `app/public/wp-content/themes/lacadev-client`, C = `app/public/wp-content/themes/lacadev-client-child`. Build trong C: `yarn build`, `yarn lint`.

## 0. Người dùng phản hồi (bắt buộc tuân thủ)
1. **Chỉ muốn đổi FIELD và MÀU NỀN.** Antigravity đã đổi cả bố cục menu trái → khó đọc (nhãn xuống 2 dòng "HD Sử / dụng", "Partnershi / p",
   mục chọn là khối xanh lớn, khoảng cách dãn). → **Trả menu trái + admin bar về đúng như trước G2.**
2. **Không dùng màu xanh.** Chỉ dùng: **trắng, đen, xám, xám đậm**.
3. Từ nay **mọi thay đổi giao diện phải có xem trước trên trình duyệt với nhiều phương án**, người dùng chọn xong mới áp dụng.
   Ví dụ sửa menu cột trái → hiện các phương án → người dùng chọn → mới sửa theo phương án đó.

## 1. Lỗi thấy trong ảnh người dùng (phải hết sau R1)
- Menu trái: nhãn bị ngắt dòng / cắt chữ, mục chọn khối xanh `#2563eb`, mục con xanh, nền trắng khác hẳn trước.
- Trang `admin.php?page=app-theme-options.php`: tab Carbon dính liền nhau không khoảng cách ("Branding | Thương hiệuContact | Liên hệ💡 Footer📰 Journal…"),
  khung "Actions" lệch cao, nút "Save Changes" xanh.
- Trang `edit.php?post_type=journal`: ô "Choose image" viền đứt nét xanh, checkbox lệch hàng với tiêu đề, link xanh, nút "Rate the plugin" xanh.
- Mọi nơi: link, nút chính, tab đang chọn đều xanh.

---

## R1 — Sửa ngay (an toàn, không cần chọn): đơn sắc + trả menu như cũ + sửa lỗi hiển thị
1. **Bảng màu đơn sắc** trong `P/resources/styles/admin/_tokens.scss` (nguồn duy nhất — không file khác chứa hex):
   | Token | Giá trị mới |
   |---|---|
   | `--laca-accent` | `#18181b` (đen dịu) |
   | `--laca-accent-hover` | `#3f3f46` |
   | `--laca-accent-subtle` | `#f4f4f5` |
   | `--laca-accent-muted` | `#e4e4e7` |
   | `--laca-accent-fg` | `#18181b` |
   | `--laca-focus-ring` | `#71717a` |
   | `--laca-info` / `-bg` | `#3f3f46` / `#f4f4f5` |
   | `--laca-success` / `-bg` | `#3f3f46` / `#f4f4f5` (đơn sắc — phân biệt bằng icon ✓) |
   | `--laca-warning` / `-bg` | `#27272a` / `#f4f4f5` (icon !) |
   | `--laca-danger` / `-bg` | `#18181b` / `#f4f4f5` (icon ✕, chữ đậm) |
   Nền, chữ, viền giữ: `#ffffff`, `#fafafa`, `#f4f4f5`, `#e4e4e7`, `#d4d4d8`, `#18181b`, `#52525b`, `#a1a1aa`.
   Link trong admin (`a`, `.row-title`, link list table, link Carbon): màu `--laca-fg`, gạch chân khi hover — không xanh.
   `lacaProjectCharts.primary` (P/theme/setup/assets.php) → `#18181b`. Grep toàn P + C (`resources/`, `app/`, `block-gutenberg/utils/`) mọi
   `#2563eb|#1d4ed8|#3b82f6|#eff6ff|#dbeafe|#1e40af|rgb(37, 99, 235)` → 0 kết quả (trừ frontend site không thuộc admin).
2. **Trả menu trái + admin bar về như trước G2**: xem `git diff 0c5b2840 HEAD -- P/resources/styles/admin` để biết rule nào G1–G7 đã thêm/xoá liên
   quan `#adminmenu`, `#adminmenuback`, `#adminmenuwrap`, `#wpadminbar`, `.wp-submenu`, `.wp-menu-name`, `body.folded`, `.awaiting-mod`.
   - Bỏ toàn bộ rule menu/admin bar mới trong `skins/_wp-chrome.scss` (giữ phần nền trang/font nếu có).
   - Khôi phục rule menu/admin bar cũ bị G7 xoá (lấy nguyên văn bằng `git show 0c5b2840:<đường dẫn file>`), nhưng màu nào trước đây đọc từ
     `--primary-color-ad`/`--secondary-color-ad`… thì đổi sang giá trị đơn sắc tương ứng (menu tối như cũ: nền `#18181b`/`#27272a`, chữ `#e4e4e7`,
     mục chọn nền `#3f3f46` chữ `#ffffff`). **Kích thước, padding, font-size, độ rộng menu, icon = y hệt trước G2.**
   - Nghiệm thu "menu như cũ" (đo, không đoán):
     a) Lấy CSS admin cũ: `git show 0c5b2840:app/public/wp-content/themes/lacadev-client-child/dist/styles/admin.css > /private/tmp/laca-old-admin.css`
        (nếu dist không được theo dõi ở commit đó thì `git show` các file SCSS admin cũ ra `/private/tmp/laca-old-scss/` và build bằng
        `C/node_modules/.bin/sass` với load-path tương ứng). KHÔNG dùng `git stash`/`checkout`/`worktree`.
     b) Puppeteer mở `edit.php?post_type=journal` ở 1440 và 1024; lần 1 gỡ `<link id="theme-admin-css-bundle-css">` rồi `page.addStyleTag({path: CSS cũ})`
        → ghi `getBoundingClientRect()` (top, height, width) của mọi `#adminmenu > li` + `.wp-menu-name` vào `P/doc/admin-ui/menu-before.json`;
        lần 2 với CSS R1 → so: mọi mục lệch top/height/width ≤ 1px. In `PASS|FAIL menu-geometry <mục> <w>`.
3. **Sửa lỗi hiển thị**: tab Carbon có khoảng cách (gap 4px, padding 8px 12px, 1 hàng cuộn ngang); khung "Actions" thẳng hàng với khung tab;
   ô "Choose image" list table viền xám `#d4d4d8` nét liền, radius 4px, không chữ xanh; checkbox list table căn giữa dọc với hàng.
4. Commit `style(admin-ui): R1 đơn sắc, trả menu như cũ, sửa tab Carbon` → push (sửa lỗi production đang thấy). Chạy nghiệm thu §5 trước.

---

## R2 — Hệ thống XEM TRƯỚC PHƯƠNG ÁN (Preview Switcher)
Mục tiêu: người dùng mở admin trên trình duyệt (local `http://lixroastery.local/wp-admin/…`), bấm qua lại các phương án, thấy ngay trên **màn
thật**, rồi chọn. Không ghi DB.

### 2.1 Cơ chế
- Class mới `P/app/src/Settings/LacaAdmin/UiPreview.php` (đăng ký cùng nơi `UiKitPage`). Chỉ chạy khi `current_user_can('manage_options')`.
- Lựa chọn lưu ở **cookie** `laca_ui_preview` (JSON `{"menu":"b","field":"a",…}`, path `/wp-admin`, hết hạn khi đóng trình duyệt) — KHÔNG ghi
  option/user meta. Đọc cookie (sanitize: chỉ chấp nhận khu vực và chữ cái có trong danh sách khai báo) → thêm class lên `<body>` qua filter
  `admin_body_class`: `laca-pv-menu-b laca-pv-field-a …`.
- CSS mỗi phương án nằm trong `P/resources/styles/admin/preview/_<khu-vực>.scss`, **mọi selector bắt đầu bằng `body.laca-pv-<khu-vực>-<chữ>`**.
  Phương án `a` của mọi khu vực = **trạng thái hiện tại (sau R1)**, không có CSS riêng. Không có cookie = trang như bình thường.
- **Thanh xem trước** (chỉ hiện khi có cookie hoặc khi ở trang gallery): thanh cố định đáy màn hình, nền `#18181b`, chữ trắng, cao 48px:
  `Xem trước: [Khu vực ▾]  Phương án: (A) (B) (C)  ·  Mô tả 1 dòng của phương án đang xem  ·  [Đi tới màn mẫu]  [Thoát xem trước]`.
  Bấm phương án → JS (Vanilla, file `P/resources/scripts/admin/ui-preview.js`, import vào entry `admin`) ghi cookie + reload. Có phím tắt ← → đổi phương án.
- **Trang gallery** `admin.php?page=laca-ui-preview` (trang ẩn, `manage_options`): mỗi khu vực 1 Card gồm: tên, mô tả, danh sách phương án
  (chữ cái + tên + 2–3 dòng mô tả khác biệt) và nút **"Xem trên màn thật"** (đặt cookie khu vực đó + mở trang mẫu của khu vực).
  Kèm ảnh chụp mỗi phương án (Puppeteer chụp sẵn, lưu `P/resources/images/ui-preview/<khu-vực>-<chữ>.png`, ≤ 200KB/ảnh, 1440×900 crop vùng liên quan).
- Không còn khu vực nào chờ chọn thì thanh xem trước không hiện.

### 2.2 Các khu vực và phương án cần dựng (mỗi phương án đều đơn sắc trắng/đen/xám)
| Khu vực | Trang mẫu | Phương án |
|---|---|---|
| `menu` — menu trái WP | `edit.php?post_type=journal` | **A** như cũ (tối, sau R1). **B** nền trắng, chữ xám đậm `#27272a`, mục chọn nền `#f4f4f5` + vạch trái 3px `#18181b`, chữ 14px 1 dòng (ellipsis nếu dài), độ rộng 160px như WP. **C** nền xám đậm `#27272a`, chữ `#d4d4d8`, mục chọn nền `#18181b` chữ trắng, mục con nền `#18181b`, khoảng cách gọn hơn (cao mục 34px). |
| `field` — ô nhập (WP form, Carbon, Laca SEO, Inspector) | `admin.php?page=app-theme-options.php` | **A** sau R1 (viền xám 1px nền trắng). **B** "filled": nền `#f4f4f5`, không viền, focus nền trắng + viền `#18181b`. **C** viền dưới 1px (underline), nền trong suốt, nhãn nhỏ phía trên. |
| `tabs` — tab Carbon/Laca SEO/WP nav-tab | `admin.php?page=app-theme-options.php` | **A** gạch chân (line) sau R1. **B** nút viên (pill) nền `#f4f4f5`, tab chọn nền `#18181b` chữ trắng. **C** tab dọc bên trái (chỉ ≥ 1024px; < 1024px quay về A). |
| `bg` — nền trang + card | `index.php` (Dashboard) | **A** nền `#fafafa`, card trắng viền `#e4e4e7`. **B** nền `#f4f4f5`, card trắng không viền. **C** nền trắng, card trắng viền `#e4e4e7`, phân khối bằng khoảng trắng. |
| `dock` — cột menu Laca Admin | `admin.php?page=laca-admin` | **A** sau R1. **B** cột hẹp 200px, tiêu đề nhóm 11px xám, mục 13px, mục chọn chữ đậm + vạch trái. **C** bỏ cột dock, thay bằng 1 hàng tab nhóm phía trên nội dung (dropdown cho mục con). |
| `inspector` — panel cấu hình block | `post.php?post=101&action=edit` (chọn block CTA Section) | **A** sau R1. **B** nhãn 12px xám đậm, control cao 32px, khoảng cách field 12px (gọn). **C** như B nhưng PC/Tablet/Mobile dạng icon nhỏ cạnh nhãn thay vì thanh riêng. |

Mỗi phương án phải đúng ở 1440, 1024, 390 (mobile < 782px: menu WP dạng overlay mặc định của WP vẫn hoạt động).

### 2.3 Quy trình chờ người dùng chọn
1. Dựng R2 xong → chạy nghiệm thu §5 → **commit nhưng KHÔNG push** (`feat(admin-ui): R2 hệ thống xem trước phương án`).
2. Gửi cho người dùng (trong chat Antigravity) danh sách link xem trước, dạng:
   `Gallery: http://lixroastery.local/wp-admin/admin.php?page=laca-ui-preview` và mỗi khu vực 1 link "Xem trên màn thật".
3. **DỪNG, chờ người dùng trả lời** dạng `menu=B, field=A, tabs=B, bg=A, dock=B, inspector=A` (có thể chỉ chọn vài khu vực, có thể yêu cầu chỉnh
   một phương án rồi xem lại). Không tự chọn thay người dùng.

## R3 — Áp dụng lựa chọn
- Với mỗi khu vực đã chọn: chuyển CSS của phương án được chọn thành mặc định (bỏ tiền tố `body.laca-pv-…`, đặt vào skin tương ứng), xoá CSS các
  phương án không chọn khỏi `preview/_<khu-vực>.scss` (sửa nội dung file; file rỗng thì để comment "không còn khu vực chờ chọn"), xoá ảnh không cần —
  **không được xoá file bằng rm**: để lại và liệt kê cho người dùng tự xoá.
- Khu vực chưa chọn giữ trong preview.
- Giữ nguyên `UiPreview` + gallery để dùng cho các lần chỉnh sau (quy trình chuẩn từ nay: mọi thay đổi giao diện admin → thêm khu vực vào preview →
  người dùng chọn → áp dụng). Ghi quy trình này vào `P/doc/THEME-DEVELOPMENT-GUIDE.md` mục Admin UI.
- Nghiệm thu §5 → commit `style(admin-ui): R3 áp dụng phương án <danh sách>` → push.

## 5. Nghiệm thu (mỗi bước R1/R2/R3, dán NGUYÊN VĂN)
- `node P/doc/admin-ui/tools/admin-ui-audit.js` → `SUMMARY fail=0`. **Sửa các check màu** cho bảng màu mới: `tokens` = `--laca-accent` `#18181b`;
  `button-primary` nền rgb(24, 24, 27) chữ trắng; thêm check `no-blue`: không phần tử hiển thị nào (ngoài canvas editor/iframe) có `color`,
  `background-color`, `border-color` với kênh B − R > 60 và B > 150 (bắt mọi màu xanh dương); thêm check `menu-one-line` (R1): mọi
  `#adminmenu .wp-menu-name` cao ≤ 1 dòng (line-height) ở 1440 và 1024, trừ mục mà bản trước G2 cũng 2 dòng (đo theo `menu-before.json`).
  Chống treo như plan cũ (domcontentloaded, try/catch mỗi URL, watchdog 15 phút).
- R2: thêm check cho mỗi khu vực × phương án: đặt cookie → mở trang mẫu → `no-overflow`, `no-js-error`, `no-blue`, body có đúng class.
- `node P/doc/laca-seo/tools/ui-probe.js` fail=0; `node C/doc/tools/layout-audit.js` 67/67; `compare-head-full.php` `BAD URLS: 2`.
- `grep` hex ngoài `_tokens.scss` và `preview/` = 0 (preview được dùng token; nếu cần màu riêng thì thêm token).
- `yarn lint` 0 error, `yarn build` OK, `php -l` file PHP sửa.
- Ảnh trước/sau R1 tại `/private/tmp/admin-ui/R1/` (journal list, app-theme-options, laca-admin, dashboard, Inspector) 1440 + 390.

## 6. Ràng buộc
Không cài package, không mu-plugin, không xoá file, không ghi DB/option/user meta (preview chỉ dùng cookie), không sửa `plugins/`/`wp-admin/`/frontend,
không sửa tay `dist/`. Không `push --force`, không rebase. Lệnh > 15 phút không ra output → tự dừng, báo FAIL.
Báo cáo: R1 xong → `progress` (commit hash + SUMMARY các script). R2 xong → gửi link xem trước cho người dùng trong chat của bạn VÀ `progress` qua
agent-bridge, rồi chờ người dùng. R3 xong → `result` qua agent-bridge.
