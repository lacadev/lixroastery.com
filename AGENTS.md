# 🤖 ANTIGRAVITY AGENTS GUIDELINES - LACA DEV FRAMEWORK

> **Mục đích:** File quy chuẩn này được Antigravity tự động đọc và tuân thủ mỗi khi thực hiện tác vụ coding, chỉnh sửa hoặc bảo trì website trên toàn bộ dự án WordPress La Cà Dev.

---

## 📌 BỘ QUY TẮC CỐT LÕI (MANDATORY RULES)

### 1. Kiến trúc & Vị trí File (WPEmerge MVC / PSR-4 `App\`)
* **Parent Theme (`lacadev-client`)**: Core Framework, Security, Logs, Theme Updater, Base Setup.
* **Child Theme (`lacadev-client-child`)**: CPTs, Carbon Fields, Custom Gutenberg Blocks, Template Overrides, Child CSS/JS.
* **Không nhồi nhét code vào `functions.php`**: Logic nghiệp vụ lớn tạo Class trong `app/src/`, hook ngắn đặt ở `app/hooks.php`, helper đặt ở `app/helpers/`.
* **Luôn dùng Helper có sẵn**:
  * Ảnh đại diện bài viết: `theResponsivePostThumbnail('mobile|tablet|full', $attr)` (tự sinh WebP/srcset, chống CLS).
  * Ảnh từ ID: `theResponsiveImage($id, 'size', $attr)`.
  * Asset tĩnh: `theAsset('images/name.png')`.
  * Theme Options: `getOption('option_name')`.
  * Post Meta: `getPostMeta('meta_key')`.

### 2. Tối ưu Hiệu năng & Database (Zero N+1 Query)
* **Khử N+1 Query**: Luôn preload cache khi lặp qua bài viết/term (`update_post_meta_cache => true`, `update_post_term_cache => true`).
* **WP_Query**: Luôn set `'no_found_rows' => true` nếu không phân trang. Dùng Transient / Object Cache (`wp_cache_get/set`) cho các query tính toán nặng.
* **Asset Cache-Busting**: Truyền `filemtime($filepath)` vào version của style/script thay vì version tĩnh.

### 3. Tiêu chuẩn Bảo mật (Security First)
* **XSS Prevention (100% Escaping)**: Mọi dữ liệu in ra HTML BẮT BUỘC bọc qua `esc_html()`, `esc_url()`, `esc_attr()`, `wp_kses_post()`, `wp_json_encode()`.
* **CSRF Nonce**: Mọi form submit và AJAX handler BẮT BUỘC tạo và verify Nonce (`check_ajax_referer` hoặc `wp_verify_nonce`).
* **Input Sanitization**: Luôn làm sạch dữ liệu đầu vào (`sanitize_text_field`, `absint`, `sanitize_email`, `sanitize_key`, `wp_unslash`).
* **SQL Injection**: 100% câu SQL tùy biến dùng `$wpdb->prepare()`.

### 4. Custom Gutenberg Blocks (React + PHP Server-Side Render)
* Mỗi block gồm 6 file chuẩn tại `block-gutenberg/lixroastery-blocks-site/[block-name]/`: `block.json`, `index.js`, `edit.js`, `save.js`, `render.php`, `style.scss`.
* Sau khi tạo/sửa block: Chạy lệnh `yarn dev:blocks` hoặc `yarn build:blocks` để kiểm tra compile không lỗi cú pháp.

### 5. Frontend & UI/UX (BEM + Tailwind + Vanilla JS)
* Không dùng jQuery. Sử dụng 100% Vanilla JS ES6+ modules.
* SCSS đặt tên theo chuẩn BEM, không nesting quá 3 cấp, kết hợp utility classes của Tailwind CSS.
* Đảm bảo Accessibility (A11y): Form có `<label>`, icon button có `aria-label`, 1 thẻ `<h1>` duy nhất trên trang.
