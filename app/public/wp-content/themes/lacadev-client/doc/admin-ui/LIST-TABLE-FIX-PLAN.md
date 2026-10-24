# Plan — Sửa trang danh sách admin: cuộn ngang TRONG bảng, cột tiêu đề cố định, hiển thị đúng giá trị, sửa nhanh

> Người thực hiện: Antigravity. Code hiện tại CHƯA commit: `P/app/src/Settings/LacaTools/Management/ListTableInlineEditor.php` (966 dòng),
> `P/resources/scripts/admin/list-table-inline.js`, `P/resources/styles/admin/_list-table-inline.scss`, + sửa `AbstractPostType.php`,
> `DynamicCptManager.php`, `ManagementExperience.php`, `admin/index.js`, `admin/index.scss`, `skins/_wp-core.scss`.
> SỬA TRÊN CODE ĐÓ (không viết lại từ đầu). P = `themes/lacadev-client`, C = `themes/lacadev-client-child`.

## 0. Người dùng yêu cầu (nguyên ý)
Trang danh sách (Products, Journal, Pages, mọi CPT) có nhiều cột → **bảng cuộn ngang được**, và **sửa nhanh ngay tại ô trong danh sách**.
Hiện tại chưa đúng, còn nhiều lỗi (ảnh người dùng: menu trái bị cắt mất khi cuộn, tiêu đề đè lên cột bên cạnh, cột danh mục hiện số).

## 1. Lỗi đã đo (công cụ `P/doc/admin-ui/tools/list-table-audit.js`, baseline `SUMMARY pass=75 fail=60`)
| # | Lỗi | Đo được |
|---|---|---|
| L1 | **Cả trang cuộn ngang**, không phải riêng bảng → kéo cả menu trái WP trôi mất | `docOver` product = 4000px, journal 869–1285, page 588–1370; 18/18 FAIL `page-no-hscroll` |
| L2 | Không có khung cuộn bao bảng | 18/18 FAIL `table-scroller`, `tablenav-outside-scroller` |
| L3 | Bảng product rộng 5259px (cột quá rộng) | `table.scrollWidth` |
| L4 | Cột taxonomy hiện **ID** ("27", "25") thay vì tên | FAIL `taxonomy-names` product (24 ô) — `getDisplayValue()` trả `getRawValue()` = danh sách ID |
| L5 | Cột "Hàng tồn kho" hiện mã thô `instock` | FAIL `stock-label` product |
| L6 | Tiêu đề dài tràn qua đường kẻ dọc của cột cố định, đè chữ cột sau (ảnh Journal) | sẽ được bắt bởi `sticky-title` khi có khung cuộn |
| L7 | Field riêng của LIX (`subname, origin, region, farm, variety, crop_year, altitude, process, net_weight…`) **viết cứng trong PARENT** (`getDefaultProductFields`, `getDefaultJournalFields`) — parent dùng chung nhiều site | đọc code |
Sửa nhanh mở/đóng được (9/9 PASS `inline-open-esc`) — giữ, chỉ hoàn thiện UX (§2.5).

⚠️ Chữ lỗi mã hoá kiểu "Ä iá» u khoáº£n" ở trang Pages/Journal **là dữ liệu DB local bị hỏng mã hoá** (đã biết từ trước, production không bị) —
KHÔNG sửa, KHÔNG chạy search-replace/convert DB.

## 2. Yêu cầu sửa

### 2.1 Khung cuộn ngang (L1, L2)
- Bọc **chỉ** `table.wp-list-table` trong `<div class="laca-table-scroll">` (JS chạy sớm nhất có thể; nếu đã có `outputEarlyHeadScript`/skeleton thì
  dùng nó để tránh nháy bố cục). `.tablenav` (bulk actions, filter, phân trang), `.subsubsub`, ô tìm kiếm **nằm ngoài** khung.
- `.laca-table-scroll { overflow-x: auto; max-width: 100%; }` + `#wpbody-content, .wrap { min-width: 0; }`; gỡ mọi `min-width`/`width` cố định
  đặt trên `.wrap`, `#posts-filter`, `#wpbody`, `body` khiến trang rộng ra. **`document.documentElement.scrollWidth` phải ≤ `innerWidth`** ở mọi bề rộng.
- Thanh cuộn: dùng scrollbar mặc định (luôn thấy được khi có thể cuộn: `scrollbar-gutter: stable` hoặc thanh mảnh 8px màu `#d4d4d8` trên nền `#f4f4f5`).
  Có thêm **thanh cuộn phụ phía trên bảng** (div rỗng cùng scrollWidth, đồng bộ `scrollLeft` 2 chiều) vì bảng dài — người dùng không phải kéo xuống
  cuối mới cuộn ngang được. Shift + lăn chuột cuộn ngang hoạt động tự nhiên.
- Hàng `.tablenav` không bị đẩy rộng (filter select co lại, wrap xuống dòng ở < 1024px).

### 2.2 Cột cố định bên trái (L6)
- Cố định 3 cột: checkbox (`.check-column`), ảnh (`.column-thumb`/`.column-image` nếu có), tiêu đề (`.column-title` / `.column-name`).
  `position: sticky; left: <tổng bề rộng các cột trước>; z-index: 2` cho cả `th` (z-index 3) và `td`.
- **Nền đặc** khớp màu hàng (hàng thường `#ffffff`, hàng xen kẽ `.alternate`/`:nth-child` đúng màu của nó, hover `#f4f4f5`, hàng được chọn) — không
  trong suốt, để chữ các cột cuộn qua không lộ bên dưới.
- Cột tiêu đề: rộng cố định 320px (≥ 1024px), 240px (< 1024px); chữ **xuống dòng** (`white-space: normal; overflow-wrap: anywhere;`), tối đa 3 dòng
  rồi `…` (line-clamp) — không bao giờ tràn ra ngoài ô. Row actions (Edit | Quick Edit | …) nằm trong ô, xuống dòng được.
- Đường phân cách: `box-shadow: 1px 0 0 #e4e4e7` ở cạnh phải cột tiêu đề; khi khung đã cuộn (`scrollLeft > 0`) thêm bóng mờ `4px 0 6px -4px rgba(0,0,0,.12)`.
  Bỏ đường kẻ dọc hiện tại đang vẽ sai vị trí (ảnh Journal).
- 390px: KHÔNG sticky (WP chuyển list table sang dạng xếp dọc từng hàng) — giữ hành vi responsive mặc định của WP, không cuộn ngang trang.

### 2.3 Bề rộng cột (L3)
- Cột thêm: `min-width` theo loại — text ngắn 140px, text dài (mô tả SEO, Tên phụ) 220px clamp 2 dòng, số 80px, select/trạng thái 130px,
  taxonomy 160px (clamp 2 dòng). Không cột nào > 280px. Ngày 140px.
- Mục tiêu: bảng product ≤ ~2400px ở 1440 (hiện 5259px).
- Cột ẩn qua "Screen Options" của WP vẫn hoạt động (đừng phá `manage_{screen}_columns` / hidden columns).

### 2.4 Giá trị hiển thị (L4, L5)
- `getDisplayValue()`:
  - `taxonomy` → **tên term** (`get_the_terms` một lần cho cả trang — preload qua `update_object_term_cache` / `update_post_term_cache`, không query
    trong vòng lặp), nối bằng ", ", rỗng → "—". Giá trị thô (ID) vẫn để trong `data-value` cho form sửa.
  - `stock_status` → nhãn của `wc_get_product_stock_status_options()` ("Còn hàng", "Hết hàng", "Đặt trước").
  - `select` → nhãn của option (đang đúng), `toggle` → "Bật"/"Tắt", meta mảng → hiển thị rỗng "—" (không sửa nhanh được, `data-editable="0"`).
- Preload meta cho cả trang: `update_meta_cache('post', $ids)` một lần (hook `the_posts` đã có — kiểm nó thật sự gọi), không `carbon_get_post_meta`
  từng ô nếu field là meta thường (Carbon lưu `_<key>`; đọc `get_post_meta($id, '_'.$key, true)` đã có cache). Đo: số query trang product trước/sau
  (`SAVEQUERIES` không bật được thì đếm `$wpdb->num_queries` qua `admin_footer` in comment HTML chỉ khi `WP_DEBUG` local) — ghi số vào báo cáo.

### 2.5 Sửa nhanh tại ô (giữ cơ chế hiện có, hoàn thiện)
- Click / Enter / Space trên ô → hiện control tại chỗ (input text, number, select, multi-select taxonomy có tìm kiếm, select trạng thái kho).
  **Enter / click ra ngoài = lưu**, **Esc = huỷ** (trả giá trị cũ), Tab = lưu và sang ô kế bên cùng hàng.
- Đang lưu: ô mờ + spinner nhỏ; thành công: viền/nền xám `#f4f4f5` 1s + giá trị mới (dạng hiển thị §2.4); lỗi: viền `#18181b` 2px + tooltip lý do,
  giữ control mở. Không dùng màu xanh (người dùng chỉ muốn trắng/đen/xám).
- AJAX: giữ nonce + `current_user_can('edit_post')`; sanitize theo loại (text `sanitize_text_field`, number `floatval`/`absint`, taxonomy chỉ nhận
  term ID tồn tại trong taxonomy đó, stock status chỉ nhận key hợp lệ, select chỉ nhận key trong options). Taxonomy lưu bằng `wp_set_object_terms`;
  stock bằng `$product->set_stock_status()->save()`; meta Carbon bằng `carbon_set_post_meta` nếu field là Carbon, ngược lại `update_post_meta`.
  Sau khi lưu xoá cache liên quan (`clean_post_cache`, `wc_delete_product_transients` với product).
- Ô không sửa được (`data-editable="0"`) không có con trỏ tay/hover.

### 2.6 Tách field riêng site khỏi parent (L7)
- Parent chỉ giữ cơ chế + field **chung**: `menu_order`, trạng thái kho (khi là product), các taxonomy public của post type, field SEO Laca nếu có.
- Danh sách field của LIX (product: `subname, origin, region, farm, variety, crop_year, altitude, process, net_weight…`; journal: các field riêng) chuyển
  sang **child** qua filter: parent `apply_filters('laca/list_inline/fields', $fields, $post_type)`; child đăng ký trong `C/app/hooks.php` (hoặc 1 file
  helper nhỏ trong `C/app/helpers/`, theo quy ước có sẵn). Key/nhãn/loại giữ y như hiện tại.

### 2.7 Giao diện
Đơn sắc trắng/đen/xám theo `P/resources/styles/admin/_tokens.scss` (không hex ngoài tokens, không xanh). Ô "Choose image" giữ như hiện tại.

## 3. Nghiệm thu (dán NGUYÊN VĂN)
1. `node P/doc/admin-ui/tools/list-table-audit.js` → **`SUMMARY fail=0`** (KHÔNG sửa logic kiểm; nếu thấy check sai thì báo lý do, đừng tự sửa).
2. Thử lưu thật 1 lần cho mỗi loại control trên product id 29 (text `_subname`, taxonomy `product_cat`, stock) bằng Puppeteer KHÔNG abort POST:
   đổi giá trị → lưu → reload thấy giá trị mới → đổi lại về giá trị gốc → lưu → kiểm DB bằng PHP CLI đúng giá trị gốc. In
   `PASS|FAIL inline-save-<loại>` và `PASS|FAIL restored-<loại>`. Không để lại dữ liệu đã đổi.
3. `node P/doc/admin-ui/tools/admin-ui-audit.js` fail=0, `node P/doc/laca-seo/tools/ui-probe.js` fail=0.
4. `yarn lint` 0 error, `yarn build` OK (trong C), `php -l` mọi file PHP sửa.
5. Ảnh 1440 của product/journal/page: lúc đầu và sau khi cuộn khung sang phải 600px → `/private/tmp/admin-ui/list/`.

## 4. Ràng buộc
- Mọi script Puppeteer: cookie sinh lúc chạy (xem đầu `list-table-audit.js`), `try/finally browser.close()`, watchdog, goto `domcontentloaded`.
  **Không có và không thêm cửa sau đăng nhập** (`laca_dev_auth` đã bị gỡ). Lệnh > 5 phút không ra output → tự dừng.
- Không đổi DB ngoài bước 3.2 (đổi rồi trả lại), không xoá file, không cài package, không sửa `plugins/`, không sửa tay `dist/`.
- Xong: commit `fix(admin): bảng danh sách cuộn ngang trong khung, cột tiêu đề cố định, hiển thị tên danh mục/nhãn kho, field riêng LIX sang child`
  → push. Gửi `result` qua agent-bridge: file sửa, NGUYÊN VĂN SUMMARY các script, số query trước/sau, đường dẫn ảnh.
