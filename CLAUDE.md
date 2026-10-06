# 🤖 CLAUDE CODE GUIDELINES - LACA DEV WORDPRESS FRAMEWORK

> **Notice:** File này được Claude Code tự động đọc mỗi khi khởi động session trong dự án này. Hãy tuân thủ 100% các tiêu chuẩn kiến trúc, hiệu năng, bảo mật và quy trình phát triển bên dưới.

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

## 🛠️ CÁC LỆNH BUILD PHỔ BIẾN
* `yarn dev` : Chạy đồng thời dev theme và dev blocks
* `yarn dev:blocks` : Compile và watch Gutenberg blocks
* `yarn build` : Build production toàn bộ theme, blocks và critical CSS
* `yarn lint` : Kiểm tra lint JavaScript và SCSS
