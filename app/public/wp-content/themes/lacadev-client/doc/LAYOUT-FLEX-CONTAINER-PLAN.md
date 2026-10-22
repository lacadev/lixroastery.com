# Plan — Khung linh hoạt (Fluid / Boxed) 3 tầng: site → trang → block

Mục tiêu: mọi site dùng chung parent theme có thể chọn khung **Fluid** (full rộng + lề chuẩn) hoặc **Boxed** (max-width `$xl`, căn giữa), và kết hợp
được theo từng trang / từng block. Không phải sửa markup block cũ. Kế thừa quy cách R1–R6 (CLAUDE.md mục 6): 1 lớp khung, không lồng.

## Bản đồ file (ĐỌC ĐÚNG CÁC FILE NÀY — không dò lại)
- Option site (tầng 1): `lacadev-client/app/src/Settings/AdminSettings.php` — container `Container::make('theme_options', __('Laca Admin', 'laca'))`
  ở **dòng ~840**: thêm field `layout_container_mode` vào đây (tab phù hợp, vd "Giao diện"/"Chung").
  ⚠️ `lacadev-client/theme/setup/theme-options.php` chỉ 11 dòng (rỗng) — KHÔNG phải nơi khai báo option, đừng đọc lại.
  `lacadev-client-child/theme/setup/theme-options.php` (579 dòng) là option riêng LIX — KHÔNG thêm vào đây.
- Meta trang (tầng 2): tạo class mới `lacadev-client/app/src/Settings/LayoutModeMeta.php` (Carbon `Container::make('post_meta', …)`), tham khảo
  cách khai báo trong `lacadev-client/app/src/Abstracts/AbstractPostType.php`. Đăng ký cùng nơi AdminSettings được khởi tạo.
- body_class: thêm filter trong `lacadev-client/app/hooks.php` (hiện chưa có `body_class`).
- Panel Bố cục dùng chung (tầng 3): `lacadev-client-child/block-gutenberg/utils/inspector-panels.js`.
- CSS: `lacadev-client-child/resources/styles/theme/layout/_general.scss` (.container / .container-fluid / .entry-content),
  mixin `lacadev-client-child/resources/styles/theme/abstracts/_mixin.scss` (`container-gutter`), biến `$xl` ở `_variables.scss` cùng thư mục.
- Helper PHP block: `lacadev-client-child/app/helpers/` (file mới `layout-mode.php`, được autoload như các helper khác — kiểm cách nạp helper hiện có).

## Thiết kế
1. **Biến CSS duy nhất**: `--laca-container-max` (`100%` = fluid, `$xl` từ `_variables.scss` = boxed).
   `.container-fluid { max-width: var(--laca-container-max, 100%); margin-inline: auto; @include container-gutter; }` — bỏ `max-width:100% !important`
   cũ (thay bằng biến). `.container` = bí danh boxed (giữ tương thích): `--laca-container-max: #{$xl}` cục bộ.
   Lưu ý: với boxed, gutter vẫn áp (padding) để màn nhỏ không dính mép; max-width tính cả padding hoặc content-box — chọn sao cho mép chữ boxed
   thẳng hàng với header boxed; ghi rõ lựa chọn.
2. **Tầng 1 — site** (parent, Theme Options Carbon Fields hiện có — tìm nơi đăng ký option chung, vd `P/app/src/Settings/...`): field
   `layout_container_mode` = `fluid` | `boxed` (mặc định `fluid` = hành vi hiện tại, LIX không đổi gì). Đọc qua `getOption()`. Xuất class
   `laca-layout-fluid|laca-layout-boxed` lên `<body>` (filter `body_class`) và rule `body.laca-layout-boxed { --laca-container-max: $xl }`.
   Header/footer/breadcrumb dùng `.container-fluid` nên tự theo.
3. **Tầng 2 — trang**: meta box nhỏ (Carbon post_meta, mọi post type public có giao diện) "Khung trang": `inherit` (mặc định) | `fluid` | `boxed`
   → ghi đè class body ở trang đó. Không tạo meta khi để inherit.
4. **Tầng 3 — block**: thêm control "Khung" vào panel **Bố cục** dùng chung (`C/block-gutenberg/utils/inspector-panels.js` hoặc nơi panel Bố cục
   đang định nghĩa — đọc skill lacadev-theme §2.3) cho MỌI block có root `section.container-fluid`: `inherit` | `fluid` | `boxed`; block có tràn viền
   (Top Hero `fullBleed`) giữ công tắc riêng, khi bật tràn viền thì control Khung áp cho phần chữ bên trong. Attribute chung `containerMode`
   (block.json mỗi block), render thêm class `is-layout-fluid|is-layout-boxed` lên root → `.container-fluid.is-layout-boxed { --laca-container-max: $xl }`.
   Tạo helper PHP dùng chung (vd `laca_block_layout_class($attributes)` trong `C/app/helpers/` hoặc P nếu block parent cũng dùng) — không copy-paste.
   Editor (`edit.js`) phản ánh đúng (dùng cùng class).
5. **maxWidth hiện có của block** (`.block-x__maxwidth` + `--mw-*`) giữ nguyên: đó là độ rộng nội dung BÊN TRONG khung, độc lập với Khung.
6. Ghi rule R7 vào `CLAUDE.md` mục 6 + `P/doc/THEME-DEVELOPMENT-GUIDE.md` 3.4: "Không chọn container/container-fluid trong template/block để
   đổi độ rộng — luôn dùng `.container-fluid` + chế độ khung (site/trang/block)". Cập nhật skill hướng dẫn nếu có file doc tương ứng trong theme.

## Nghiệm thu
1. Mở rộng `C/doc/tools/layout-audit.js` thêm chế độ ma trận (không ghi DB: nạp setting qua query var dev chỉ cho admin/local hoặc filter
   `pre_option`/`get_post_metadata` trong 1 mu-plugin? — KHÔNG được tạo mu-plugin; dùng tham số preview `?laca_layout=boxed` chỉ hoạt động khi
   `wp_get_environment_type()==='local'` hoặc user `manage_options`, để test):
   - site fluid (mặc định) — 12 URL hiện có × 4 breakpoint vẫn PASS y như trước (không hồi quy LIX).
   - site boxed — mép chữ = mép header boxed ±1px; nội dung không vượt `$xl`; 1440 thấy rõ khoảng trống 2 bên.
   - 1 trang override ngược với site; 1 block override `boxed` trong trang fluid (dùng block-content-rows hoặc block có sẵn trong bài 101, nạp attribute
     qua filter `render_block_data` trong tiến trình test — không sửa bài).
   In `PASS|FAIL <case> <url> <w>` + `SUMMARY`.
2. `yarn build`, `yarn build:blocks`, `yarn lint` 0 error; `php -l` file sửa. Ảnh 1440 site fluid vs boxed của `/manifesto/` ở /private/tmp/laca-flex/.
3. Block editor: chọn "Khung" trên 1 block hiển thị đúng trong editor (Puppeteer, abort MỌI POST, cookie runtime không ghi file).

## Ràng buộc
Code cơ chế ở PARENT (dùng chung site), block LIX ở child. Không đổi giao diện LIX mặc định (site = fluid). Không đổi DB/nội dung bài, không xoá file,
không cài package, không mu-plugin, không commit/push, không kết thúc lượt khi đang chờ lệnh. Báo cáo ngắn: file sửa, NGUYÊN VĂN output audit,
build/lint, ảnh; xong PHẢI gửi qua agent-bridge.
