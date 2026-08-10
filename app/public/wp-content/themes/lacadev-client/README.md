# ⚡ La Cà Dev Theme (v3.1)

Theme WordPress hiệu suất cao, "Zero jQuery", tối ưu hóa cho tốc độ và trải nghiệm người dùng.

## Tổng Quan Dự Án

La Cà Dev là một WordPress theme hiệu năng cao được xây dựng với kiến trúc hiện đại:
- **Zero jQuery** - Sử dụng Vanilla JavaScript để tối ưu hiệu năng
- **WPEmerge Framework** - Routing và controllers theo phong cách MVC cho WordPress
- **Modern Build System** - Webpack 5 với code splitting, Critical CSS và tối ưu hóa
- **PSR-4 Autoloading** - Cấu trúc namespace PHP chuẩn
- **Tập trung vào bảo mật** - Xác thực nonce toàn diện, security headers và sanitization input

## Các Lệnh Development

### Lệnh Cơ Bản
```bash
# Cài đặt ban đầu
composer install && yarn install

# Chế độ development (watch + hot reload tại localhost:3000)
yarn dev

# Build production (minify + optimize)
yarn build

# Tạo Critical CSS (sau khi sửa đổi Header/Hero sections)
yarn critical

# Build theme assets only (để debug)
yarn build:theme
```

### Lệnh Linting
```bash
# Kiểm tra tất cả code
yarn lint

# Kiểm tra từng phần cụ thể
yarn lint:styles     # Chỉ CSS/SCSS
yarn lint:scripts    # Chỉ JavaScript

# Tự động sửa lỗi linting
yarn lint-fix
yarn lint-fix:styles
yarn lint-fix:scripts
```

### Build Targets
- `yarn dev:theme` - Theme assets với webpack ở chế độ watch
- `yarn dev:blocks` - Gutenberg blocks với @wordpress/scripts
- `yarn build:blocks` - Build production cho Gutenberg blocks

## Kiến Trúc

### Cấu Trúc Thư Mục
```
lacadev/
├── app/                     # PHP Business Logic (PSR-4: App\)
│   ├── src/                 # Core application classes
│   │   ├── Abstracts/       # Base classes (AbstractPostType, AbstractTaxonomy)
│   │   ├── Controllers/     # Web, Admin, Ajax controllers
│   │   ├── PostTypes/       # Định nghĩa custom post type
│   │   ├── Routing/         # Route service providers
│   │   ├── Settings/        # Admin settings, tools (Optimize, Security)
│   │   └── View/            # View service provider
│   ├── routes/              # Định nghĩa routes
│   │   ├── web.php          # Frontend routes
│   │   ├── admin.php        # Admin panel routes
│   │   └── ajax.php         # AJAX endpoints
│   ├── helpers/             # Utility functions
│   ├── config.php           # WPEmerge configuration
│   └── hooks.php            # WordPress hooks registration
│
├── resources/               # Source Code (SỬA Ở ĐÂY)
│   ├── scripts/             # JavaScript modules
│   │   ├── theme/           # Frontend JS → dist/theme.js
│   │   ├── admin/           # Admin panel JS → dist/admin.js
│   │   ├── editor/          # Gutenberg editor → dist/editor.js
│   │   └── login/           # Login page → dist/login.js
│   ├── styles/              # SCSS source files
│   │   ├── theme/           # Frontend styles
│   │   ├── admin/           # Admin panel styles
│   │   ├── editor/          # Block editor styles
│   │   └── shared/          # Shared utilities/variables
│   └── build/               # Webpack configuration
│
├── dist/                    # Compiled Assets (KHÔNG SỬA)
│   ├── theme.js             # Main theme bundle (~12KB)
│   ├── vendors.js           # Third-party libraries (~685KB)
│   ├── admin.js             # Admin bundle
│   └── styles/              # Compiled CSS
│
├── theme/                   # WordPress Theme Wrapper
│   ├── setup/               # Modular setup files
│   │   ├── assets.php       # Asset enqueuing
│   │   ├── performance.php  # Performance optimizations
│   │   ├── security.php     # Security headers & hardening
│   │   ├── seo.php          # SEO meta tags, Schema.org
│   │   └── gutenberg-blocks.php
│   ├── template-parts/      # Reusable template components
│   ├── functions.php        # Theme bootstrap
│   └── *.php                # WordPress template files
│
└── block-gutenberg/         # React-based Gutenberg blocks
```

### WPEmerge Routing
Theme này sử dụng WPEmerge framework cho routing theo phong cách MVC:

- **Routes** được định nghĩa trong `app/routes/` (web.php, admin.php, ajax.php)
- **Controllers** trong `app/src/Controllers/` xử lý business logic
- **Views** được phục vụ từ thư mục `theme/` (WordPress templates chuẩn)
- **Middleware** được cấu hình trong `app/config.php`

Ví dụ cấu trúc route (hiện tại sử dụng `Route::all()` cho WordPress template hierarchy chuẩn):
```php
// app/routes/web.php
Route::all(); // Chuyển tất cả requests qua WPEmerge
```

### Webpack Entry Points
Bốn bundles riêng biệt được tạo ra:
1. **theme.js** - Frontend functionality (deferred, loads ở footer)
2. **admin.js** - Admin panel features
3. **editor.js** - Block editor enhancements
4. **login.js** - Login page customizations

Mỗi bundle tự động include SCSS tương ứng từ `resources/styles/`.

### Chiến Lược Load Assets
- **Frontend**: `theme.js` load deferred (footer) để tối ưu hiệu năng
- **Admin**: `vendors.js` load blocking (head) để đảm bảo các thư viện như SweetAlert2 sẵn sàng trước `admin.js`
- **Critical CSS**: Tự động inline trong header để có First Contentful Paint nhanh

## Quy Trình Development Chính

### Khi Sửa Đổi Frontend UI
1. Sửa source files trong `resources/scripts/theme/` hoặc `resources/styles/theme/`
2. Chạy `yarn dev` để live reloading
3. Thay đổi tự động compile vào `dist/`
4. Nếu sửa đổi Header/Hero sections, chạy `yarn critical` để tạo lại Critical CSS

### Khi Thêm Custom Post Types
1. Tạo class mới trong `app/src/PostTypes/` extend `AbstractPostType`
2. Đăng ký trong `theme/functions.php`:
   ```php
   new \App\PostTypes\YourPostType();
   ```

### Khi Thêm Routes
1. Thêm route trong file phù hợp (`app/routes/web.php`, `admin.php`, `ajax.php`)
2. Tạo controller trong `app/src/Controllers/`
3. Theo docs WPEmerge routing: https://docs.wpemerge.com/#/framework/routing/methods

### Trước Khi Deploy Production
1. Chạy `yarn build` - Xóa console.log, minify code
2. Test admin panel hoạt động (kiểm tra biến bị mangled)
3. Xác minh Critical CSS đã cập nhật
4. Chạy linting: `yarn lint`
5. **KHÔNG commit** trừ khi được yêu cầu rõ ràng - để user review trước

## Chuẩn Code

### PHP
- Tuân theo WordPress Coding Standards
- Sử dụng PSR-4 autoloading (namespace `App\`)
- Tabs cho indentation (chuẩn WordPress)
- Luôn kiểm tra `ABSPATH` trong mọi file:
  ```php
  if (!defined('ABSPATH')) {
      exit;
  }
  ```
- Sử dụng nonce verification cho tất cả AJAX/form submissions
- Sanitize input, escape output

### JavaScript/CSS
- 2 spaces indentation (không dùng tabs)
- Tuân theo rules của `@wordpress/eslint-plugin`
- Không dùng jQuery - sử dụng Vanilla JS
- Console statements bị xóa trong production builds
- Globals được định nghĩa trong `.eslintrc.js`:
  - WordPress: `themeSearch`, `lacaDashboard`, `ajaxurl_params`
  - Libraries: `Swal` (SweetAlert2), `ScrollTrigger`, `SplitText` (GSAP)

## Cân Nhắc Về Bảo Mật

Theme này có các biện pháp bảo mật toàn diện:

- **HTTP Security Headers**: X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy
- **WordPress Hardening**: XML-RPC disabled, file editing disabled, version numbers hidden
- **Login Protection**: Rate limiting (5 lần thử trong 15 phút)
- **AJAX Protection**: Nonce verification trên tất cả AJAX endpoints
- **Input Sanitization**: Tất cả user input được sanitized với `sanitize_text_field()` hoặc tương tự

Khi thêm AJAX endpoints mới:
1. Luôn verify nonce: `check_ajax_referer('your_nonce_name')`
2. Kiểm tra user capabilities: `current_user_can('required_capability')`
3. Sanitize input: `sanitize_text_field($_POST['field'])`
4. Escape output: `esc_html()`, `esc_attr()`, `esc_url()`

## Ghi Chú Về Hiệu Năng

- **Critical CSS** inline trong header để FCP nhanh
- **Code splitting**: Vendors bundle tách riêng khỏi theme code
- **Image optimization**: Tự động qua webpack (JPEG 85%, PNG compressed)
- **Lazy loading**: Images lazy-loaded với `lazysizes.min.js`
- **Service Worker**: Có sẵn trong `resources/scripts/sw.js` cho advanced caching
- **Web Vitals Monitoring**: Theo dõi hiệu năng real-time (LCP, CLS, FID)

## Xử Lý Sự Cố

### Lỗi Admin JavaScript Sau Build
Nếu admin features bị lỗi sau `yarn build`, kiểm tra webpack mangling settings trong `resources/build/webpack.production.js`. Reserved globals bao gồm: `Swal`, `LacaDashboard`, `lacaDashboard`, `ajaxurl_params`, `adminI18n`.

### CSS Không Áp Dụng
1. Kiểm tra `yarn build` hoàn thành thành công
2. Xác minh `dist/styles/` chứa compiled CSS
3. Xóa browser cache và WordPress object cache
4. Kiểm tra console có lỗi 404 không

### BrowserSync Không Hoạt Động
Proxy URL mặc định là `localhost:3000`. Cập nhật `resources/build/browsersync.js` nếu WordPress local của bạn chạy trên URL khác.

## Yêu Cầu

- **Node.js**: v20+
- **Yarn**: Latest
- **PHP**: 7.4+
- **Composer**: Latest
- **WordPress**: Tương thích với phiên bản mới nhất

## Tài Nguyên Bổ Sung

- Theme documentation: README.md
- Phân tích chi tiết: THEME-ANALYSIS-REPORT.md
- Lộ trình cải thiện: TODO-IMPROVEMENTS.md
- HTML best practices: HTML-BEST-PRACTICES.md
---
*Author: La Cà Dev - Code giữa những chuyến đi*
Email: mooms.dev@gmail.com
Phone: 0989646766
website: https://lacadev.com