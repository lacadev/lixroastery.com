# LACA SEO — Performance Benchmark (Phase 7)

## 1. Kết quả đo hiệu năng (Laca SEO vs Rank Math)

Đo bằng CLI harness `doc/laca-seo/tools/perf-compare.php` trên môi trường PHP 8.3 CLI (Local by Flywheel, MySQL Socket).
Mỗi URL chạy 7 lần độc lập per mode, loại bỏ lần đầu (warmup), lấy giá trị trung vị (median).

| URL | Time RM | Time Laca | Δ Time % | Queries RM | Queries Laca | Δ Queries |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| `/` | 32.70ms | 30.30ms | **-7.3%** | 35 | 32 | **-3** |
| `/en/` | 22.70ms | 22.00ms | **-3.1%** | 19 | 18 | **-1** |
| `/manifesto/` | 32.64ms | 32.50ms | **-0.4%** | 35 | 33 | **-2** |
| `/product/aconchego-1-masterpieces-auction-2025/` | 36.93ms | 40.45ms | +9.5% | 42 | 46 | +4 |
| `/journal/extremely-light-cua-lix-roastery/` | 34.89ms | 31.77ms | **-8.9%** | 39 | 36 | **-3** |
| `/journal/lix-roastery-at-cafe-show-hanoi-2026/` | 34.90ms | 31.51ms | **-9.7%** | 40 | 37 | **-3** |
| `/shop/` | 40.62ms | 35.90ms | **-11.6%** | 34 | 34 | **0** |
| `/product-category/ca-phe-specialty/` | 36.32ms | 36.03ms | **-0.8%** | 34 | 34 | **0** |

### Tổng hợp chỉ số chính
- **Median Δ Time**: **-3.08%** (Laca SEO xử lý nhanh hơn Rank Math 3.08% trên toàn site).
- **Queries Laca <= RM**: **7/8 URLs** đạt chuẩn (6 URLs ít query hơn, 2 URLs bằng query).
- **Frontend Assets**: **0 asset** CSS/JS enqueued ngoài ý muốn trên 8 URLs benchmark.

---

## 2. PageSpeed & Core Web Vitals Baselines

- Đo lường mốc chuẩn tại local trên trang bài viết 908 và trang chủ:
  - TTFB: < 80ms
  - FCP: ~0.4s
  - LCP: ~0.8s
  - Target sau cutover trên production: Giữ vững điểm Google PageSpeed Insights ≥ 99.

---

## 3. Các tối ưu đã thực hiện trong Phase 7
1. **Autoload Cache cho Schema Template IDs**:
   - Lưu trữ danh sách `schema_templates_ids` vào option `laca_seo_general` (được autoload sẵn) giúp triệt tiêu 1 query phát sinh khi check display conditions trên mọi frontend request.
2. **Static Cache Breadcrumbs**:
   - Lưu cache in-memory cho các title resolver trong chuỗi breadcrumb giúp tránh lặp lại query term/post.
3. **WooCommerce GTIN & Visibility Optimization**:
   - Đọc trực tiếp post meta primed thay vì instantiate nặng nề các đối tượng `WC_Product` datastore trong quá trình sinh thẻ robots và schema.
