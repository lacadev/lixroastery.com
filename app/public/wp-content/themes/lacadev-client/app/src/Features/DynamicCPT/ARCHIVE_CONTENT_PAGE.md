# Trang nội dung Archive cho Dynamic CPT

Tính năng cho phép gắn 1 **Page thường** (soạn bằng Block Editor) làm phần nội
dung giới thiệu hiển thị ở đầu trang archive của bất kỳ Dynamic CPT nào — admin
tự soạn/sửa nội dung này y hệt sửa 1 Page bình thường, không cần đụng code.

Mô hình giống hệt cách WooCommerce dùng **Shop page**: trang Shop không có nội
dung thật trong CPT `product`, mà là 1 Page riêng được chọn trong
WooCommerce → Settings → Products, nội dung Page đó hiện ở đầu trang Shop,
phía trên danh sách sản phẩm.

## Cách dùng (cho người quản trị nội dung)

1. Tạo 1 Page bình thường (**Pages → Add New**), soạn nội dung tự do bằng
   Block Editor (tiêu đề, ảnh, text, block bất kỳ...). Publish Page đó.
2. Vào **Laca Admin → Custom Post Types**, chọn CPT cần gắn nội dung (ví dụ
   Glossary, Journal...) — bấm icon bút chì để sửa.
3. Ở mục **Archive**, field **"Trang nội dung Archive (tùy chọn)"** → chọn
   Page vừa tạo ở bước 1.
4. Bấm **Cập nhật**.
5. Vào URL archive của CPT đó (ví dụ `/glossary/`) để kiểm tra — nội dung
   Page sẽ hiện ngay dưới tiêu đề trang, phía trên danh sách bài viết.

Muốn gỡ bỏ: chọn lại **"— Không dùng —"** ở field trên rồi Cập nhật.

Muốn sửa nội dung sau này: chỉ cần sửa Page đó (Pages → sửa như Page bình
thường) — không cần vào lại màn hình Custom Post Types.

## Áp dụng cho CPT nào?

- **Tự động áp dụng** cho mọi Dynamic CPT **không có** file
  `archive-{slug}.php` riêng — tức là archive của CPT đó đang dùng chung
  `theme/archive.php` (fallback mặc định). Không cần làm gì thêm khi tạo CPT
  mới, chỉ cần chọn Page ở bước 3 phía trên.
- CPT nào đã có `archive-{slug}.php` viết tay riêng (ví dụ
  `archive-glossary.php` — có bố cục A-Z index riêng) thì **không** tự động
  có tính năng này, vì file đó không gọi qua `theme/archive.php`. Muốn bật
  cho CPT đó, dev cần thêm 1 dòng vào đúng vị trí muốn hiển thị trong file
  `archive-{slug}.php`:

  ```php
  <?php laca_render_dynamic_cpt_archive_intro(); ?>
  ```

## Chi tiết kỹ thuật (cho dev)

- Dữ liệu lưu trong field `archive_content_page_id` (post ID của Page),
  nằm trong config JSON của CPT đó ở option `laca_dynamic_cpts`.
- `DynamicCptManager::getArchiveContentPageId(string $postType): int` — đọc
  giá trị đã lưu cho 1 CPT, trả `0` nếu chưa cấu hình.
  (`app/src/Features/DynamicCPT/DynamicCptManager.php`)
- `laca_render_dynamic_cpt_archive_intro(string $post_type = '')` — hàm
  helper toàn cục, tự lấy post type hiện tại nếu không truyền tham số, in ra
  `the_content` (đã qua `apply_filters('the_content', ...)`, nên block nào
  cũng render đầy đủ) của Page đã gắn, bọc trong
  `<div class="dynamic-cpt-archive-intro">`. Không in gì nếu chưa cấu hình
  hoặc Page không tồn tại/chưa publish.
  (`app/helpers/template_tags.php`, load ở mọi request qua `app/helpers.php`)
- Được gọi sẵn trong `theme/archive.php` (child theme) ngay sau
  `template-parts/page-hero`, trước phần loop bài viết — đây là lý do tính
  năng tự động áp dụng cho mọi CPT dùng chung file này.

## Lưu ý

- Field chọn Page dùng `wp_dropdown_pages()` (hàm core WordPress) — liệt kê
  tất cả Page trong site, không lọc theo trạng thái publish ở dropdown,
  nhưng khi render sẽ tự bỏ qua nếu Page đã bị chuyển về draft/trash.
- Không giới hạn 1 Page chỉ được gắn cho 1 CPT — có thể dùng chung 1 Page
  cho nhiều CPT nếu muốn (không khuyến khích, dễ gây nhầm lẫn khi sửa).
