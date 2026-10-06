# 🏛️ WORDPRESS ARCHITECTURE & CODING STANDARDS (LACA DEV FRAMEWORK)

## 1. Core Principles & Separation of Concerns
* **Parent Theme (`lacadev-client`)**: Framework core (WPEmerge MVC, PSR-4, Core Security, Logs, Updater, Global Helpers, Base Setup). Sửa core code LUÔN thực hiện trên Parent theme trước.
* **Child Theme (`lacadev-client-child`)**: Business logic của từng dự án client (Custom Post Types, Carbon Fields, Custom Gutenberg Blocks, Template Overrides, Child CSS/JS).
* **NO Procedural Spaghetti Code**: Tuyệt đối KHÔNG viết hàm xử lý logic nghiệp vụ dài bừa bãi vào `theme/functions.php`. `functions.php` chỉ dùng để boot ứng dụng.

## 2. File Organization & Namespaces (PSR-4 `App\`)
* **Logic phức tạp (> 50 dòng)**: Tạo OOP Class trong `app/src/` tương ứng:
  * `app/src/PostTypes/`: Đăng ký Custom Post Type (dùng thư viện `Extended CPTs`).
  * `app/src/Ajax/`: Các handler xử lý request AJAX (OOP, verify nonce, sanitize).
  * `app/src/Settings/`: Admin settings, Carbon Fields, background services.
  * `app/src/Models/`: Data models, query layers.
  * `app/src/Helpers/`: OOP Helper classes.
* **Hooks & Filters ngắn**: Khai báo trong `app/hooks.php` của parent hoặc child theme.
* **Helper functions**: Khai báo trong `app/helpers/` (được nạp tự động qua `app/helpers.php`).

## 3. Mandatory Theme Helpers
* **Render ảnh đại diện bài viết**: BẮT BUỘC dùng `theResponsivePostThumbnail('mobile|tablet|full', $attr)` để tự động tạo `srcset`, WebP và chống CLS.
* **Render ảnh từ Attachment ID**: Dùng `theResponsiveImage($attachment_id, 'size', $attr)`.
* **Render URL tĩnh trong theme**: Dùng `theAsset('images/name.png')` hoặc `theAsset('favicon/favicon-32x32.png')`.
* **Lấy Theme Option**: Dùng `getOption('option_name')` (tự động map ngôn ngữ Polylang/WPML).
* **Lấy Post Meta**: Dùng `getPostMeta('meta_key', $post_id)`.

## 4. Custom Post Types & Carbon Fields
* Custom Post Types: Dùng `register_extended_post_type()` với đầy đủ icon, supports, admin columns.
* Carbon Fields: Đăng ký trong hook `carbon_fields_register_fields`. Lưu ý: Không dùng `set_context('carbon_fields_after_title')` cho post type dùng Block Editor (Gutenberg) vì context đó chỉ chạy ở Classic Editor; hãy dùng context `'normal'` hoặc `'side'`.
