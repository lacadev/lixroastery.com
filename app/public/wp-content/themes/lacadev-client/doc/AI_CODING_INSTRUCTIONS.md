# 🤖 AI CODING INSTRUCTIONS - LACA DEV THEME

> **Mục đích:** File tổng hợp toàn bộ tiêu chuẩn kiến trúc, quy tắc code và hướng dẫn vị trí file dành riêng cho AI Agent (Claude/ChatGPT) nhằm tạo Block, Custom Post Type, hoặc tính năng mới một cách chính xác nhất mà không cần quét toàn bộ Source Code. Kết hợp file này với dữ liệu từ **Grapuco** để code đúng ngữ cảnh, tiết kiệm Tokens.

---

## 1. TỔNG QUAN KIẾN TRÚC & VỊ TRÍ FILE (WPEmerge MVC)
Kiến trúc dự án bám sát chuẩn OOP, Module hóa và phân tách Frontend/Backend. KHÔNG code quy trình (procedural) hoặc ném mọi thứ vào `functions.php`.

> ⚠️ **KHÔNG có thư mục `Controllers/`**. Logic nhỏ đặt vào `app/hooks.php`, logic lớn tạo Class tại `app/src/` theo mục đích tương ứng bên dưới.
> ⚠️ **Đây là Child Theme (`lacadev-client`)**. Sửa core code LUÔN thực hiện trên Parent Theme (`lacadev`) trước, sau đó mới sync sang.

### `app/` — Core Backend
- **`app/routes/`**: Định nghĩa Routing trỏ tới các xử lý trong `app/src/`.
- **`app/hooks.php`**: Khai báo Action/Filter hooks ngắn. **KHÔNG đặt logic phức tạp ở đây.**
- **`app/config.php`**: Cấu hình chung của ứng dụng.
- **`app/helpers.php`**: Bootstrap load toàn bộ files trong `app/helpers/`.
- **`app/views.php`**: Đăng ký View templates với WPEmerge.
- **`app/helpers/`** *(13 files)*: Các hàm tiện ích gọi mọi nơi. Quan trọng nhất:
  - `theResponsivePostThumbnail('mobile|tablet|full', $attr)` — render ảnh bài viết tối ưu WebP/srcset.
  - `theResponsiveImage($id, 'size')` — render ảnh từ Media ID.
  - `theAsset('images/name.png')` — link tĩnh đến resources.
  - `getOption('option_name')` — lấy Theme Option (tự map theo ngôn ngữ WPML).

### `app/src/` — OOP Classes (PSR-4, namespace `App\`)
- **`Abstracts/`**: Base Classes để kế thừa (extend). Không tạo code trực tiếp ở đây.
- **`PostTypes/`**: Đăng ký Custom Post Type dùng thư viện `Extended CPTs`.
- **`Settings/`**: Carbon Fields Meta Boxes và các tính năng Admin panel.
- **`Models/`**: Query/Data-access layer truy vấn dữ liệu CPT.
- **`Validators/`**: Validate input từ form hoặc AJAX request.
- **`Widgets/`**: WordPress Sidebar Widgets tùy chỉnh.
- **`View/`**: View helpers/partials theo kiến trúc MVC.
- **`Routing/`**: Routing logic bổ sung nâng cao.
- **`Helpers/`**: Helper Classes dạng OOP (khác với `app/helpers/` dạng functions).
- **`Features/`** ❌ **KHÔNG TỒN TẠI Ở CHILD THEME**: Portal Client, Project Management, AI Tracking chỉ ở Parent và bị EXCLUDE khi sync.
- **`Databases/`** ❌ **KHÔNG TỒN TẠI Ở CHILD THEME**: Custom DB Tables chỉ ở Parent Theme.

### Theme Templates & Frontend
- **`theme/`**: File template chuẩn WP (`single.php`, `header.php`, `footer.php`, `archive.php`, ...).
- **`theme/setup/`**: Cấu hình WP core — Menu Walker, Theme Supports, reCAPTCHA, Security, SEO, Sidebars.
- **`theme/template-parts/`**: Các partial layout tái sử dụng (breadcrumb, page-hero, post-hero, loop-post, loop-service, share_box, ...).
- **`resources/scripts/` & `resources/styles/`**: Mã nguồn JS Vanilla và SCSS/Tailwind. Compile qua Webpack → `dist/`.
- **`block-gutenberg/`**: Mã nguồn Custom Gutenberg Blocks (mỗi block = 1 thư mục con).
- **`theme-server/`**: Cấu hình server-side đặc thù cho môi trường production của client.

---

## 2. QUY TẮC KHI THÊM MỚI CHỨC NĂNG (AI CẦN NHỚ RÕ)

### A. Quy tắc PHP & Backend
1. **Luôn dùng Helpers có sẵn cho Ảnh & Asset:**
   - Render ảnh bài viết: Dùng `theResponsivePostThumbnail('mobile|tablet|full', $attr)` ĐỂ TỐI ƯU WebP/SRCSET.
   - Render ảnh Custom/Media ID: Dùng `theResponsiveImage($id, 'size')`.
   - Render link tĩnh trong resources: Dùng `theAsset('images/name.png')`.
   - Lấy Cài đặt (Theme Options): Dùng `getOption('option_name')`. Tự động map theo ngôn ngữ hiện tại.
2. **Database Queries (Hiệu năng):**
   - Tránh N+1 Queries: Khi có vòng lặp, KHÔNG dùng `get_post_meta()` bên trong một cách lặp lại. Cache lại hoặc preload meta data.
   - Sử dụng `WP_Query` chuẩn với `'no_found_rows' => true` nếu không có phân trang.
3. **Bảo mật (Cực kỳ quan trọng):**
   - Output HTML (XSS): Luôn bọc bằng `esc_html()`, `esc_url()`, `esc_attr()` hoặc `wp_kses_post()`. Data cho JS bọc bằng `wp_json_encode()`.
   - Xác thực: LUÔN verify Nonce cho Form và request AJAX (`check_ajax_referer` hoặc `wp_verify_nonce`). 
   - Làm sạch Input trước khi lưu DB thông qua `sanitize_text_field` / `absint`.
4. **Vị trí Viết Code Mới:**
   - Logic phức tạp trên 50 dòng: Tạo OOP Class ở `app/src/`. 
   - Các class dùng Autoloader chuẩn PSR-4 (`namespace App\...`).
   - Code hook/filter ngắn: Đặt vào `app/hooks.php`. Tuyệt đối không đặt code chức năng vào `functions.php` (chỉ dùng để boot ứng dụng).

### B. Quy tắc Frontend (CSS/JS/HTML)
1. **Tailwind CSS & SCSS:**
   - Sử dụng Tailwind CSS là chính.
   - Nếu viết SCSS Custom (VD: Pseudo classes, hiệu ứng BEM): Viết tách rời tại các thư mục `resources/styles/components`, `blocks`, hay `pages`.
   - KHÔNG nesting quá 3 cấp trong SCSS. Tránh khai báo Tag Selector body/h1 bừa bãi.
2. **Javascript (Vanilla):**
   - Hạn chế thư viện thừa. Tuyệt đối không dùng jQuery. Sử dụng chuần Vanilla JS ES6+ (VD: `document.querySelector`, `addEventListener`).
   - Tên file JS hỗ trợ Module, có setup sẵn Webpack build.
3. **HTML5 Semantic & SEO:**
   - Bắt buộc có các thẻ Semantic (`<main>`, `<article>`, `<header>`).
   - Form field luôn cần có `<label>` (có thể dùng `.screen-reader-text` nếu chỉ muốn ẩn). Buttons/Links icon bắt buộc có thuộc tính `aria-label`.

---

## 3. QUY TRÌNH & CẤU TRÚC CODE THƯỜNG GẶP

### 🔧 3.1. Hướng Dẫn Tạo Gutenberg Block
Mỗi Block mới phải được tạo một thư mục con riêng biệt tại `block-gutenberg/[tên-block]/`. Cấu trúc bắt buộc gồm:
1. `block.json`: Định nghĩa metadata, tên block, icon, attribute states (lưu dữ liệu block).
2. `index.js`, `edit.js`, `save.js`: (React Code) Định nghĩa trải nghiệm Kéo-Thả (Block Settings, InspectorControls) bên trong backend Editor.
3. `render.php`: Code render View thực tế HTML ở ngoài Web. (Giao diện React ở edit phải đồng nhất với file này). Trực tiếp gõ HTML và gọi PHP helpers ở đây.
4. `style.scss`: SCSS cục bộ của block (Tailwind CSS được phép include trực tiếp class html trên `render.php`).
*💡 Xem Code Block Mẫu: Tham chiếu `hero-block` để học render layout cơ bản, tham chiếu `tech-list-block` để học cách xử lý array (Repeaters) trong Block React.*

### 🛠 3.2. Hướng Dẫn Tạo Custom Post Type (CPT)
1. Tạo class PHP tại `app/src/PostTypes/[TenCpt].php`.
2. Định nghĩa hàm `__construct()` và dùng thư viện **Extended CPTs** (`register_extended_post_type(...)`). Định nghĩa dashboard CPT menu_icon, supports.
3. Đăng ký Class này trong Application khởi tạo (Provider/Bootstrap).
4. Viết Carbon Fields để cho admin nhập liệu tương ứng vào `app/src/Features/[FeatureName]/`.
5. Tạo giao diện trang chủ CPT (`archive-[cpt].php`) và bài viết CPT (`single-[cpt].php`) trong thư mục `theme/`.
6. Tách rời vòng lặp ra `theme/template-parts/loop-[cpt].php` để tái sử dụng.

### 🔄 3.3. Update Theme (Parent vs Child Theme)
- Sửa core code **LUÔN** thực hiện trên Parent Theme (`lacadev`).
- Child theme (`lacadev-child`) chỉ dành riêng cho việc cập nhật theo vòng đời từ Parent sang các site con qua Auto-updater của WordPress. Cập nhật được Build bằng script Bash `./build-release.sh <version> "Mô tả"` (trong folder Child).
- **Lưu Ý Đặc Biệt:** Chức năng Portal Client, Project Management, AI Tracking được code ở Parent Theme cho tool phân tích riêng, **Chức năng này không được bao gồm (Exclude)** lúc Sync script tạo Child Themes qua môi trường khách hàng thực sự.

---

## 4. SKILLS KHUYẾN CÁO (Dành riêng cho AI Workspace)
Khi thi hành task trên nền tảng Claude, AI nên tự chủ động đính kèm gọi Combo Skills để đảm bảo chất lượng:
- **Tạo Code Mới (PHP/React/HTML):** `@wp-block-development` (Cho Block), `@ui-ux-pro-max` & `@tailwind-design-system` (Cho Giao diện), `@php-pro` & `@javascript-pro` (Logic).
- **Đảm bảo Tốc độ tải (Web Vitals):** `@wp-performance`, `@web-performance-optimization`, `@database-optimizer` (Khử N+1 Query).
- **Kiểm định Security / Audit Code vừa viết:** `@security-auditor`, `@xss-html-injection`, `@sql-injection-testing`.
- **Kiểm định Accessibility/SEO:** `@seo-structure-architect`, `@schema-markup`, `@accessibility-compliance-accessibility-audit`.
- Lệnh phím tắt (Slash Commands): Dùng `/create-laca-block` hoặc `/review-legacy-theme` theo từng tình huống cụ thể.
