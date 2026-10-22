# 📘 LACA DEV THEME - DOCUMENTATION & DEVELOPMENT GUIDE

> **Last Updated:** 23/01/2026
> **Theme Version:** 3.1.0
> **Purpose:** Hướng dẫn phát triển, quy chuẩn code và tài liệu kỹ thuật cho theme La Cà Dev.

---

## 📋 MỤC LỤC

1. [Tổng Quan Kiến Trúc](#1-tổng-quan-kiến-trúc)
2. [Quy Trình Phát Triển](#2-quy-trình-phát-triển)
3. [HTML Best Practices](#3-html-best-practices)
4. [PHP & WordPress Best Practices](#4-php--wordpress-best-practices)
5. [Performance Guidelines](#5-performance-guidelines)
6. [Security Guidelines](#6-security-guidelines)
7. [Thực Hiện Tính Năng Mới](#7-thực-hiện-tính-năng-mới)

---

## 1. TỔNG QUAN KIẾN TRÚC

### 1.1. Cấu Trúc Thư Mục

```
lacadev/
├── app/                          # Core theme logic
│   ├── config.php               # WP Emerge config
│   ├── helpers/                 # Helper functions
│   ├── routes/                  # Routing logic
│   └── src/                     # PSR-4 classes (App\*)
│       ├── Abstracts/          # Abstract classes
│       ├── Controllers/        # MVC Controllers
│       ├── Models/             # Data models
│       ├── PostTypes/          # Custom Post Types
│       ├── Settings/           # Admin settings (Carbon Fields)
│       └── View/               # View providers
├── theme/                       # Template files
│   ├── setup/                  # Theme setup modules (tách biệt từng chức năng)
│   └── *.php                   # Template files chuẩn WordPress
├── resources/                   # Raw assets
│   ├── scripts/                # JavaScript (ES6+ Modules)
│   ├── styles/                 # SCSS (Sass)
│   └── images/                 # Source Images
├── dist/                        # Compiled assets (Webpack output)
└── block-gutenberg/            # Custom Gutenberg blocks
```

### 1.2. Công Nghệ Sử Dụng

- **Backend:** WP Emerge (MVC), Carbon Fields (Fields/Options), PSR-4 Autoloading.
- **Frontend:** Webpack 5, Babel (ES6+), SCSS, BEM naming.
- **Libraries:** GSAP (Animation), Swiper (Slider), SweetAlert2 (Modal), Pristine.js (Validation).
- **Optimization:** WebP/AVIF auto-convert, Critical CSS, Service Worker.

---

## 2. QUY TRÌNH PHÁT TRIỂN

### Các lệnh Build (CLI)

```bash
# Cài đặt dependencies
yarn install
composer install

# Development (Watch mode + BrowserSync)
yarn start

# Production Build (Minify + Optimize)
yarn build

# Generate Critical CSS
yarn critical

# Generate POT file (Translation)
yarn i18n
```

---

## 3. HTML BEST PRACTICES

### 3.1. Document Structure

Luôn sử dụng **HTML5 DOCTYPE** và **Semantic Elements**.

```html
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <header role="banner">...</header>
    <nav role="navigation">...</nav>
    <main role="main">...</main>
    <aside role="complementary">...</aside>
    <footer role="contentinfo">...</footer>
    <?php wp_footer(); ?>
</body>
</html>
```

### 3.2. Images & Media

Sử dụng helper functions (xem phần [Responsive Images](#71-responsive-images)) để đảm bảo tối ưu.

**Chuẩn HTML cho ảnh:**
- Luôn có `alt` text.
- Luôn có `width` và `height` để tránh CLS.
- Sử dụng `loading="lazy"` cho ảnh dưới fold.
- Sử dụng `decoding="async"`.

```php
<img src="<?php echo esc_url($src); ?>" 
     alt="<?php echo esc_attr($alt); ?>" 
     width="1200" height="630" 
     loading="lazy" 
     decoding="async">
```

### 3.3. Accessibility (A11y)

- **Forms:** Tất cả input phải có `<label>` (có thể ẩn bằng class `.screen-reader-text` nhưng phải có trong DOM).
- **Buttons:** Nút icon phải có `aria-label`.
- **Links:** Link "Xem thêm" phải có `aria-label="Xem thêm về [Tiêu đề]"`.
- **Colors:** Đảm bảo độ tương phản (Contrast ratio >= 4.5:1).

### 3.4. Layout Container & Container-Fluid Guidelines (R1–R7)

Quy chuẩn cấu trúc container đảm bảo mép trái nội dung luôn thẳng hàng với logo header và không xung đột padding:

- **R1 — Template không bọc `the_content()`**: Template (`page.php`, `single.php`, custom page template...) không bọc `the_content()` trong `.container` hay `.container-fluid`. Vùng nội dung dùng wrapper `<div class="entry-content">` (không padding ngang).
- **R2 — Block custom tự lo lề ngang**:
  - Tràn viền theo setting của block, không theo class `alignfull` đã lưu trong nội dung.
  - Block thông thường hoặc block có setting tràn viền bị TẮT: Thẻ root nằm trong lề chuẩn đồng bộ header/footer (thẻ root `<section class="container-fluid [block-class]">` hoặc áp dụng `@mixin container-gutter($property: margin)` lên thẻ root không có `alignfull`).
  - Block tràn viền (full-bleed khi setting BẬT): Thẻ root `<section class="[block-class] alignfull">` (KHÔNG có `container-fluid`), nội dung văn bản bên trong bọc qua `<div class="container-fluid">`.
- **R3 — Core blocks trực tiếp trong `.entry-content`**: Tự động nhận lề qua `@mixin container-gutter`; `.alignwide` = max-width `$xl` căn giữa; `.alignfull` = tràn viền viewport 100vw.
- **R4 — Phần ngoài nội dung**: Header, footer, breadcrumb, page-hero, archive header... dùng đúng 1 lớp container, không lồng container trong container.
- **R5 — Bản vá container lồng hợp lệ**: Giữ `.container-fluid .container-fluid { padding: 0 !important; }` để triệt tiêu padding cộng dồn khi các block dạng container lồng nhau (như Tabs, Container).
- **R6 — Dùng chung Mixin**: Luôn dùng `@mixin container-gutter` trong `abstracts/_mixin.scss`, không hardcode kích thước padding.
- **R7 — Không chọn container/container-fluid để đổi độ rộng**: Không chọn container/container-fluid trong template/block để đổi độ rộng — luôn dùng `.container-fluid` + chế độ khung (site/trang/block qua `--laca-container-max`).

```html
<!-- ĐÚNG: Block thường -->
<section class="container-fluid block-banner">
    <div class="block-banner__inner">...</div>
</section>

<!-- ĐÚNG: Block tràn viền -->
<section class="block-hero alignfull">
    <img class="block-hero__bg" src="..." alt="">
    <div class="container-fluid block-hero__content">...</div>
</section>

<!-- SAI: Tránh lồng container ngoài template -->
<div class="container-fluid entry-content">
    <?php the_content(); ?>
</div>
```

---

## 4. PHP & WORDPRESS BEST PRACTICES

### 4.1. Naming Conventions

- **Functions:** `app_` (core) hoặc `laca_` (theme custom). Ví dụ: `laca_get_related_posts`.
- **Classes:** PascalCase (`AdminSettings`, `PostController`).
- **Variables:** snake_case (`$post_id`, `$user_meta`).
- **Constants:** UPPER_CASE (`THEME_VERSION`).

### 4.2. Database & Queries

**❌ Tránh N+1 Query:**
```php
// KHÔNG LÀM: Query trong loop
foreach ($posts as $post) {
    $meta = get_post_meta($post->ID, 'key', true); // Query database mỗi lần
}
```

**✅ Giải pháp (Preload):**
```php
// NÊN LÀM: Sử dụng update_post_caches hoặc query đúng cách
$query = new WP_Query([
    'posts_per_page' => 10,
    'no_found_rows' => true, // Tắt đếm tổng số dòng nếu không phân trang
    'update_post_meta_cache' => true,
    'update_post_term_cache' => true,
]);
```

### 4.3. Caching Pattern

Sử dụng **Helper Function** để cache query nặng:

```php
function laca_get_expensive_data() {
    $cache_key = 'laca_expensive_data_v1';
    $data = wp_cache_get($cache_key);
    
    if (false === $data) {
        $data = perform_expensive_calculation();
        wp_cache_set($cache_key, $data, '', 3600); // Cache 1 giờ
    }
    
    return $data;
}
```

---

## 5. PERFORMANCE GUIDELINES

### 5.1. Asset Optimization

- **CSS:** Cấu trúc SCSS trong `resources/styles/`. Build system sẽ tự động compile và minify.
- **JS:** Viết ES6 Module trong `resources/scripts/`. Webpack sẽ bundle và split code (vendors vs theme).
- **Fonts:** Preload critical fonts trong `header.php`.

### 5.2. Critical CSS

Theme sử dụng **Critical CSS** để inline style quan trọng vào `<head>`.
File output tại: `dist/styles/critical.css`.
Để regenerate: `yarn critical`.

### 5.3. Lazy Loading

Theme hỗ trợ **Native Lazy Loading**. Không sử dụng jQuery Lazyload plugins cũ.

---

## 6. SECURITY GUIDELINES

### 6.1. Escaping & Sanitization

**Quy tắc bất di bất dịch:** Escape mọi dữ liệu khi output ra HTML.

```php
// Output Text
echo esc_html($text);

// Output URL
echo esc_url($url);

// Output Attribute HTML
echo '<div class="' . esc_attr($class) . '">';

// Output HTML an toàn (cho phép br, b, strong...)
echo wp_kses_post($content);

// Output JS Data
echo 'var data = ' . wp_json_encode($array) . ';';
```

### 6.2. Nonce Verification

Luôn verify nonce khi xử lý Form submit hoặc AJAX request.

```php
// Tạo nonce trong HTML/PHP
wp_nonce_field('laca_action', 'laca_nonce');

// Verify trong xử lý PHP
if (!isset($_POST['laca_nonce']) || !wp_verify_nonce($_POST['laca_nonce'], 'laca_action')) {
    wp_die('Security check failed');
}
```

---

## 7. THỰC HIỆN TÍNH NĂNG MỚI

### 7.1. Responsive Images

Theme đã tích hợp hệ thống **Responsive Images** tự động. Sử dụng các hàm helper mới thay vì hàm WP gốc để tận dụng WebP và srcset.

**Các hàm có sẵn:**

| Hàm Cũ (WP/Core) | Hàm Mới (Theme Helper) | Ghi chú |
|------------------|------------------------|---------|
| `the_post_thumbnail()` | `theResponsivePostThumbnail('mobile'|'tablet'|'full')` | Tự động resize & webp |
| `get_post_meta(...)` (image) | `theResponsivePostMeta('meta_key', 'size')` | Cho Carbon Fields image |
| `wp_get_attachment_image()` | `theResponsiveImage($id, 'size')` | Cho attachment ID bất kỳ |
| `get_theme_mod(...)` | `theResponsiveOption('option_name', 'size')` | Cho Theme Options |

**Ví dụ sử dụng:**

```php
<!-- Thay vì: -->
<img src="<?php thePostThumbnailUrl(480, 360); ?>">

<!-- Hãy dùng: -->
<?php theResponsivePostThumbnail('mobile', ['class' => 'my-image', 'loading' => 'lazy']); ?>
```

**Các kích thước (Size):**
- `'mobile'` (~480px)
- `'tablet'` (~768px)
- `'full'` (Original)

### 7.2. Laca SEO & XML Sitemap

Hệ thống SEO toàn diện được tích hợp trong `app/src/Features/LacaSeo/` (thay thế Rank Math).
Quản lý cấu hình tại wp-admin › Laca Theme › SEO (`admin.php?page=laca-seo`).
Sơ đồ trang XML tự động cung cấp tại: `/sitemap_index.xml`.

### 7.3. Thêm Custom Post Types

Sử dụng thư viện `Extended CPTs`. Định nghĩa trong `app/src/PostTypes/`.

```php
namespace App\PostTypes;

class Service {
    public function __construct() {
        register_extended_post_type('service', [
            'menu_icon' => 'dashicons-hammer',
            'supports' => ['title', 'editor', 'thumbnail'],
            'admin_cols' => [
                'featured_image' => ['title' => 'Ảnh', 'featured_image' => 'thumbnail'],
                'updated' => ['title' => 'Cập nhật', 'post_date' => 'Y/m/d H:i']
            ],
            // ...
        ]);
    }
}
```

---
**Happy Coding! 🚀**
