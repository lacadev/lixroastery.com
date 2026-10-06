# ⚡ PERFORMANCE & DATABASE OPTIMIZATION GUIDELINES

## 1. Zero N+1 Query Policy
* **Preload Cache**: Khi duyệt qua danh sách post hoặc terms trong vòng lặp, KHÔNG gọi `get_post_meta()` hoặc `get_the_terms()` gây query DB lặp lại.
* **WP_Query Optimization**:
  * Luôn set `'no_found_rows' => true` cho các query không cần phân trang (giảm tải câu lệnh `SQL_CALC_FOUND_ROWS`).
  * Luôn bật `'update_post_meta_cache' => true` và `'update_post_term_cache' => true` khi loop có dùng meta/term.
  * Tắt post count không cần thiết (`'fields' => 'ids'` khi chỉ cần lấy danh sách ID).

## 2. Caching Strategy
* **Transient & Object Cache**: Các phép tính nặng, query phức tạp nhiều CPT/Taxonomy hoặc dữ liệu bên thứ 3 phải được cache qua `wp_cache_get()` / `wp_cache_set()` hoặc `get_transient()` / `set_transient()` với thời gian hết hạn hợp lý (ví dụ: `HOUR_IN_SECONDS`).
* **Cache Invalidation**: Hook vào `save_post`, `edit_term` để xóa cache tương ứng khi dữ liệu thay đổi.

## 3. Core Web Vitals & Image Optimization
* **Cumulative Layout Shift (CLS)**:
  * Mọi thẻ `<img>` và `<svg>` phải có kích thước `width` và `height` rõ ràng.
  * Sử dụng tỷ lệ khung hình cố định (aspect-ratio) trong CSS.
* **Largest Contentful Paint (LCP)**:
  * Ảnh đầu trang (Above-the-fold / Hero banner) KHÔNG được đặt `loading="lazy"`. Ưu tiên `fetchpriority="high"`.
  * Ảnh dưới nếp gấp (Below-the-fold) BẮT BUỘC có `loading="lazy"` và `decoding="async"`.
* **Font Preloading**: Preload critical fonts trong `header.php` để tránh hiện tượng nhấp nháy FOUT/FOIT.
* **Asset Cache-Busting**: Luôn truyền `filemtime($filepath)` vào version của `wp_enqueue_style()` và `wp_enqueue_script()` thay vì dùng version tĩnh (`1.0.0`).
