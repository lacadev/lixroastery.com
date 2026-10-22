# 🤖 CLAUDE CODE GUIDELINES - LACA DEV WORDPRESS FRAMEWORK

> **Notice:** File này được Claude Code tự động đọc mỗi khi khởi động session trong dự án này. Hãy tuân thủ 100% các tiêu chuẩn kiến trúc, hiệu năng, bảo mật và quy trình phát triển bên dưới.

---

## 🚧 0. SAFETY RULES (ƯU TIÊN CAO NHẤT — áp dụng cho cả Claude Code & Antigravity)

### Phạm vi được phép sửa
* Chỉ sửa file trong 2 theme của project này: `app/public/wp-content/themes/lacadev-client` và `app/public/wp-content/themes/lacadev-client-child`.
* **Không** sửa WordPress core (`wp-admin/`, `wp-includes/`, file gốc `wp-*.php`), `wp-config.php`, `.htaccess`, thư mục `plugins/` và `uploads/` — trừ khi người dùng yêu cầu rõ ràng.
* **Không** đụng đến project khác (vd. `../lacadev-client`, `../nhakhoathienphuoc`, `../phucdainam`) hay thư mục cha. Nếu sửa ở đây mà cần đồng bộ sang project khác → chỉ báo lại cho người dùng, không tự sửa.
* Không sửa tay file build trong `dist/` — chỉ để lệnh build sinh ra.

### Không phá huỷ dữ liệu
* Tuyệt đối không xoá file/thư mục (`rm`, `rm -rf`, `unlink`...). Cần bỏ code thì sửa nội dung hoặc đề xuất cho người dùng tự xoá. (Lệnh build tự dọn/ghi đè `dist/` thì được.)
* Git: không chạy `reset --hard`, `clean`, `checkout -- .`, `restore .`, `stash drop`, `push --force`, `rebase`, đổi branch khi còn thay đổi chưa commit. Không `commit`/`push` khi chưa được yêu cầu.
* Database: không chạy `wp db reset/drop/import`, `wp search-replace`, `DROP/TRUNCATE/DELETE` hay xoá post/option/user khi chưa hỏi người dùng. Đọc dữ liệu (`SELECT`, `wp ... list/get`) thì được.
* Không cài/gỡ/nâng cấp package (`yarn add/remove/upgrade`, `composer require/update`) hay plugin khi chưa hỏi. `yarn install` / `composer install` theo lockfile có sẵn thì được.

### Đọc trước khi sửa
* Luôn đọc file (và chỗ gọi tới hàm/class liên quan) trước khi sửa. Sửa tối thiểu, đúng chỗ — không reformat cả file, không đổi tên/di chuyển file ngoài phạm vi task.
* Không ghi đè cả file đang có bằng nội dung mới nếu chỉ cần sửa một đoạn.
* Không đưa secret/API key/mật khẩu vào code hoặc commit.

### Lệnh build/test
* Chỉ chạy lệnh build/lint/test bên trong thư mục theme của project này (`yarn build`, `yarn build:blocks`, `yarn lint`...). Không chạy lệnh ở thư mục cha hay project khác.
* Không bật watcher/dev server chạy nền (`yarn dev`, `yarn dev:blocks`) mà không tắt khi xong, tránh 2 agent cùng build đè nhau.
* Sau khi sửa PHP: chạy `php -l <file>`; sau khi sửa block/JS/SCSS: chạy `yarn lint` + `yarn build:blocks`. Lỗi thì báo đúng output, không giấu.

### Phối hợp Claude Code ↔ Antigravity
* Hai agent có thể làm việc cùng lúc: trước khi sửa một file, kiểm tra agent kia có đang sửa file đó không (git diff / tiến độ của agent kia). Không sửa file mà agent kia đang nhận.
* Không hoàn tác (revert) thay đổi chưa commit mà mình không tạo ra — hỏi người dùng trước.
* Khi bắt đầu/kết thúc một phần việc, báo lại danh sách file đã sửa.

### Khi không chắc
* Thao tác khó hoàn tác, ảnh hưởng production/dữ liệu thật, hoặc nằm ngoài phạm vi trên → **dừng lại và hỏi người dùng**.

---

## 📌 1. KIẾN TRÚC & QUY TẮC FILE (WPEmerge MVC / PSR-4 `App\`)
* **Parent Theme (`lacadev-client`)**: Framework core, bảo mật, logs, theme updater, base setup. Mọi sửa đổi core code PHẢI làm ở Parent theme trước.
* **Child Theme (`lacadev-client-child`)**: CPTs, Carbon Fields, Custom Gutenberg Blocks, Template Overrides, Child CSS/JS.
* **KHÔNG viết code procedural bừa bãi vào `functions.php`**:
  * Logic lớn (> 50 dòng) $\rightarrow$ tạo OOP Class trong `app/src/` (PSR-4 `namespace App\...`).
  * Hooks/Filters ngắn $\rightarrow$ khai báo trong `app/hooks.php`.
  * Helper functions $\rightarrow$ đặt trong `app/helpers/`.
* **Luôn sử dụng Helper có sẵn của theme**:
  * Ảnh đại diện bài viết: `theResponsivePostThumbnail('mobile|tablet|full', $attr)` (tự sinh WebP/srcset, chống CLS).
  * Ảnh từ ID: `theResponsiveImage($id, 'size', $attr)`.
  * Asset tĩnh: `theAsset('images/name.png')`.
  * Theme Options: `getOption('option_name')` (tự map đa ngôn ngữ WPML/Polylang).
  * Post Meta: `getPostMeta('meta_key', $post_id)`.

---

## ⚡ 2. TỐI ƯU HIỆU NĂNG & DATABASE (Zero N+1 Query)
* **Khử triệt để N+1 Query**: Khi duyệt bài viết/term trong vòng lặp, luôn bật cache (`'update_post_meta_cache' => true`, `'update_post_term_cache' => true`).
* **WP_Query**: Luôn set `'no_found_rows' => true` nếu không phân trang. Dùng Transient / Object Cache (`wp_cache_get`/`set`) cho các query nặng.
* **Asset Cache-Busting**: Luôn truyền `filemtime($filepath)` vào version của style/script thay vì version tĩnh.

---

## 🛡️ 3. TIÊU CHUẨN BẢO MẬT (Security First)
* **XSS Prevention (100% Escaping)**: Mọi dữ liệu in ra HTML BẮT BUỘC bọc qua: `esc_html()`, `esc_url()`, `esc_attr()`, `wp_kses_post()`, `wp_json_encode()`.
* **CSRF Nonce**: Mọi form submit và AJAX handler BẮT BUỘC tạo và verify Nonce (`check_ajax_referer` hoặc `wp_verify_nonce`).
* **Input Sanitization**: Luôn làm sạch dữ liệu đầu vào (`sanitize_text_field`, `absint`, `sanitize_email`, `sanitize_key`, `wp_unslash`).
* **SQL Injection**: 100% câu SQL tùy biến dùng `$wpdb->prepare()`.

---

## 🧩 4. CUSTOM GUTENBERG BLOCKS (React + PHP Server Render)
* Mỗi block gồm 6 file chuẩn tại `block-gutenberg/lixroastery-blocks-site/[block-name]/`:
  * `block.json`, `index.js`, `edit.js`, `save.js`, `render.php`, `style.scss`.
* Sau khi tạo/sửa block: Luôn chạy lệnh `yarn dev:blocks` hoặc `yarn build:blocks` để kiểm tra compile không lỗi cú pháp.

---

## 🎨 5. FRONTEND & UI/UX (BEM + Tailwind + Vanilla JS)
* Không dùng jQuery. Sử dụng 100% Vanilla JS ES6+ modules.
* SCSS đặt tên theo chuẩn BEM, không nesting quá 3 cấp, kết hợp utility classes của Tailwind CSS.
* Đảm bảo Accessibility (A11y): Form có `<label>`, icon button có `aria-label`, 1 thẻ `<h1>` duy nhất trên trang.

---

## 📐 6. QUY CÁCH LAYOUT CONTAINER / CONTAINER-FLUID (R1–R7)
* **R1 — Template không bọc `the_content()`**: Template (`page.php`, `single.php`, custom page template...) không bọc `the_content()` trong `.container` hay `.container-fluid`. Vùng nội dung dùng wrapper `<div class="entry-content">` (không padding ngang).
* **R2 — Block custom tự lo lề ngang**:
  * Tràn viền theo setting của block, không theo class `alignfull` đã lưu trong nội dung.
  * Block thường hoặc block có setting tràn viền bị TẮT: Nằm trong lề chuẩn đồng bộ header/footer (Root `<section class="container-fluid [block-class]">` hoặc áp dụng `@mixin container-gutter($property: margin)` lên thẻ root không có `alignfull`).
  * Block tràn viền (hero, banner nền... khi setting tràn viền BẬT): Root `<section class="[block-class] alignfull">` (KHÔNG có class `container-fluid`), đặt nội dung chữ bên trong `<div class="container-fluid">`.
* **R3 — Core blocks trực tiếp nhận lề qua mixin**: Các thẻ core (p, h2, ul, ol, blockquote...) nằm trực tiếp trong `.entry-content` tự nhận lề qua `@mixin container-gutter`; `.alignwide` = max-width `$xl` + căn giữa; `.alignfull` = full viewport width.
* **R4 — Phần ngoài nội dung dùng đúng 1 lớp container**: Header, footer, breadcrumb, page-hero, archive header... dùng đúng 1 lớp `.container-fluid` (hoặc `.container` khi cần max-width `$xl`), không lồng container trong container.
* **R5 — Bản vá lồng hợp lệ**: Duy trì `.container-fluid .container-fluid { padding: 0 !important; }` để hỗ trợ các block dạng container (Tabs, Container) chứa block con mà không bị thụt lề lặp. Mép trái chữ luôn thẳng hàng với logo header ở mọi breakpoint.
* **R6 — Dùng chung `@mixin container-gutter`**: Không hardcode lề (100px / 50px / 20px / 15px), luôn dùng `@mixin container-gutter` trong `abstracts/_mixin.scss`.
* **R7 — Không chọn container/container-fluid để đổi độ rộng**: Không chọn container/container-fluid trong template/block để đổi độ rộng — luôn dùng `.container-fluid` + chế độ khung (site/trang/block qua `--laca-container-max`).

**Ví dụ markup:**
```html
<!-- ĐÚNG (Block thường): -->
<section class="container-fluid block-my-feature">...</section>

<!-- ĐÚNG (Block tràn viền full-bleed): -->
<section class="block-top-hero alignfull">
    <img class="block-top-hero__bg" src="..." alt="">
    <div class="container-fluid block-top-hero__content">...</div>
</section>

<!-- SAI: -->
<section class="container-fluid alignfull">...</section> <!-- Xung đột lề -->
<div class="entry-content container-fluid"><?php the_content(); ?></div> <!-- Lồng padding template -->
```

---

## 🛠️ CÁC LỆNH BUILD PHỔ BIẾN
* `yarn dev` : Chạy đồng thời dev theme và dev blocks
* `yarn dev:blocks` : Compile và watch Gutenberg blocks
* `yarn build` : Build production toàn bộ theme, blocks và critical CSS
* `yarn lint` : Kiểm tra lint JavaScript và SCSS
