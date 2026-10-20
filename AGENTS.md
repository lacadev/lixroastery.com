# 🤖 ANTIGRAVITY AGENTS GUIDELINES - LACA DEV FRAMEWORK

> **Mục đích:** File quy chuẩn này được Antigravity tự động đọc và tuân thủ mỗi khi thực hiện tác vụ coding, chỉnh sửa hoặc bảo trì website trên toàn bộ dự án WordPress La Cà Dev.

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
* **Quy chuẩn Code Quality & Theme CI (BẮT BUỘC TRƯỚC KHI HOÀN TẤT TÁC VỤ)**:
  * Sau khi tạo/sửa block hay bất kỳ file JS/SCSS/PHP nào: BẮT BUỘC chạy `yarn lint` (hoặc `yarn lint-fix`) trong `lacadev-client-child` để đảm bảo 0 lỗi ESLint, Prettier, Stylelint, bảo đảm Theme CI trên GitHub Actions luôn PASS 100%.
  * BẮT BUỘC chạy `yarn build:blocks` để kiểm tra và biên dịch bundle không có lỗi cú pháp.

### 5. Frontend & UI/UX (BEM + Tailwind + Vanilla JS)
* Không dùng jQuery. Sử dụng 100% Vanilla JS ES6+ modules.
* SCSS đặt tên theo chuẩn BEM, không nesting quá 3 cấp, kết hợp utility classes của Tailwind CSS.
* Đảm bảo Accessibility (A11y): Form có `<label>`, icon button có `aria-label`, 1 thẻ `<h1>` duy nhất trên trang.
