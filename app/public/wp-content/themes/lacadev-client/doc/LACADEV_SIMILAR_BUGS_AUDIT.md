# Đối chiếu lỗi đã sửa bên theme `lacadev` với `lacadev-client`

Ngày kiểm tra: 2026-07-17
Bối cảnh: theme `lacadev` (site hub) vừa được sửa 5 lỗi (custom login,
Tools Optimization/Security, rating-box crash). Site `lacadev-client` dùng
kiến trúc theme tương tự (fork/port cùng codebase gốc) — kiểm tra xem có
mắc lỗi tương tự không.

Site có 2 theme: `lacadev-client` (parent, `template`) và
`lacadev-client-child` (**active theme**, `stylesheet`) — xác nhận qua
`wp_options.template` / `wp_options.stylesheet`.

## Tóm tắt

| # | Lỗi | Có ở lacadev-client? | Mức độ | Đã sửa? |
|---|---|---|---|---|
| 1 | `CustomLoginManager` chờ nhầm hook `plugins_loaded` | **Có**, y hệt | Tiềm ẩn (tính năng đang tắt) | ✅ Đã sửa |
| 2 | Plugin `wps-hide-login` xung đột | Không có | — | Không áp dụng |
| 3 | So sánh boolean vs chuỗi `'yes'` | Không (dùng `get_option()` thô, không qua Carbon Fields) | — | Không áp dụng, nhưng phát hiện lỗi khác nặng hơn — xem bên dưới |
| 4 | XML-RPC gây Fatal Error | **Có** dòng code y hệt, nhưng đang chết (dead code) | Tiềm ẩn | ✅ Đã sửa |
| 5 | `rating-box.php` gọi `kk_star_ratings()` không kiểm tra | **Có, ĐANG CRASH THẬT trên site live** (qua child theme) | 🔴 Nghiêm trọng, đang live | ✅ Đã sửa |

Phát hiện thêm 1 vấn đề mới (không nằm trong 5 lỗi gốc): **toàn bộ tab
"Security" của Tools không hoạt động vì class không bao giờ được khởi tạo** —
xem mục 3 bên dưới. Vấn đề này **chưa sửa**, cần bạn quyết định.

---

## 1. `CustomLoginManager` — cùng lỗi hệt lacadev (đã sửa)

`app/src/Settings/Security/CustomLoginManager.php:34-37` (trước khi sửa):

```php
if ($this->enabled) {
    // Must run at plugins_loaded (priority 99) so rewrite rules are loaded first
    add_action('plugins_loaded', [$this, 'setupHooks'], 99);
}
```

Và `app/hooks.php:192-196` khởi tạo class này ở hook `init` priority 1 —
y hệt cấu trúc lỗi đã tìm thấy ở `lacadev`. Vì `plugins_loaded` luôn chạy
TRƯỚC `init`, callback không bao giờ được gọi trong request đó.

**Trạng thái hiện tại:** tính năng đang **tắt** (`laca_enable_custom_login`
chưa được set trong DB) nên chưa gây ảnh hưởng thực tế — nhưng nếu admin
bật lên sẽ gặp y hệt lỗi "không hoạt động được" như bên lacadev.

**Đã sửa:** gọi `$this->setupHooks()` trực tiếp trong constructor thay vì
chờ `plugins_loaded`.

**Lưu ý:** khác với lacadev, tôi CHƯA áp dụng kiến trúc port từ
`wps-hide-login` (đánh lừa REQUEST_URI qua `wp_loaded`) cho site này —
chỉ sửa đúng lỗi hook timing để tính năng hiện tại hoạt động được. Nếu
muốn nâng cấp kiến trúc giống lacadev, cần làm thêm.

## 2. Plugin `wps-hide-login` — không áp dụng

`wp-content/plugins/` của lacadev-client chỉ có `laca-self-ordering-kds` và
`laca-woo`. Không có `wps-hide-login` được cài — không có xung đột.

## 3. So sánh `=== 'yes'` — không áp dụng, nhưng phát hiện lỗi khác nặng hơn

`Optimize.php` và `Security.php` bên lacadev-client dùng **`get_option()`
trực tiếp** (không qua `carbon_get_theme_option()`):

```php
if (get_option('_disable_use_jquery_migrate') === 'yes') { ... }
```

`get_option()` trả về đúng chuỗi thô đã lưu trong DB (`'yes'`), không bị
Carbon Fields cast sang boolean như bên lacadev — nên **không mắc lỗi
này**.

### Nhưng phát hiện lỗi khác, nghiêm trọng hơn:

`App\Settings\LacaTools\Security` (`app/src/Settings/LacaTools/Security.php`)
**không bao giờ được khởi tạo ở đâu cả** trong toàn bộ theme. Chỉ có
`app/helpers.php:37` gọi `new \App\Settings\LacaTools\Optimize();` — **không
có dòng `new Security()` nào**.

Trong khi đó, `AdminSettings.php:857-880` vẫn hiển thị đầy đủ tab "Security"
trong Tools với 4 checkbox: Disable REST API, Disable XML RPC, Disable
Wp-Embed, Disable X-Pingback — admin tick vào các ô này **hoàn toàn không
có tác dụng gì**, vì code xử lý (`Security::disableRestApi()` v.v.) không
bao giờ được gọi.

**Đây là vấn đề tôi CHƯA sửa** vì đây là quyết định chức năng (bật một tính
năng bảo mật trước giờ chưa từng chạy) chứ không đơn thuần là sửa lỗi —
cần bạn xác nhận có muốn kích hoạt tab Security này không (thêm dòng
`new \App\Settings\LacaTools\Security();` vào `helpers.php`) trước khi tôi
thực hiện, vì nó sẽ thay đổi hành vi site (tắt REST API cho khách, tắt
X-Pingback, v.v.) — không nên tự ý bật.

## 4. XML-RPC Fatal Error — cùng lỗi, nhưng đang là dead code (đã sửa phòng ngừa)

`Security::disableXmlRpc()` (dòng 45-51, trước khi sửa) có cùng dòng nguy
hiểm như bên lacadev:

```php
add_filter( 'wp_xmlrpc_server_class', '__return_false' );
```

**Khác biệt quan trọng:** vì `Security` class không bao giờ được khởi tạo
(mục 3), method này **chưa từng được gọi** — hiện KHÔNG có rủi ro crash
thực tế. Nhưng nếu sau này ai đó wire `Security` lên (ví dụ khi implement
mục 3), lỗi crash y hệt lacadev sẽ xuất hiện ngay.

**Đã sửa phòng ngừa:** gỡ dòng `wp_xmlrpc_server_class`, chỉ giữ
`xmlrpc_enabled => __return_false` (đủ để tắt XML-RPC an toàn).

## 5. `rating-box.php` / `kk_star_ratings()` — 🔴 ĐANG CRASH THẬT TRÊN SITE LIVE (đã sửa)

Theme **đang active** là `lacadev-client-child` (xác nhận qua
`wp_options.stylesheet = lacadev-client-child/theme`). File
`lacadev-client-child/theme/single.php:60` gọi:

```php
<?php get_template_part( 'template-parts/rating-box' ); ?>
```

Và `lacadev-client-child/theme/template-parts/rating-box.php` gọi thẳng
`kk_star_ratings()` không kiểm tra tồn tại — plugin **KK Star Ratings**
không có trong `wp-content/plugins/` của site này.

**Đã verify bằng test thật:** `curl http://lacadev-client.local/hello-world/`
→ **500**, trang "There has been a critical error on this website"
(48504 byte) — **mọi bài viết trên site live đều đang bị lỗi 500 ngay lúc
audit này**.

Theme cha `lacadev-client` cũng có 1 bản `rating-box.php` y hệt, nhưng
**không có nơi nào gọi `get_template_part('template-parts/rating-box')`**
trong theme cha — bản đó là dead code, không gây ảnh hưởng (nhưng đã sửa
luôn cho nhất quán, phòng trường hợp sau này có người gọi tới).

**Đã sửa cả 2 file** (parent + active child theme) — bọc
`function_exists('kk_star_ratings')`:

```php
<div class="rating-stars">
    <?php if (function_exists('kk_star_ratings')) : ?>
        <?php echo kk_star_ratings(); ?>
    <?php endif; ?>
</div>
```

**⚠️ Chưa verify lại được bằng HTTP sau khi sửa** — lúc kiểm tra lại thì
toàn bộ dịch vụ Local (nginx/php-fpm) của cả `lacadev.local` lẫn
`lacadev-client.local` đột ngột dừng hẳn (connection refused), không phải
do thao tác sửa file gây ra. Cần bạn khởi động lại Local rồi test lại theo
hướng dẫn bên dưới.

### Cần bạn quyết định thêm

Giống bên lacadev: đây chỉ là fix "không crash nữa", chưa khôi phục tính
năng đánh giá sao. Nếu vẫn cần dùng, phải cài + active plugin "KK Star
Ratings" trên site lacadev-client.

---

## Cách test lại sau khi khởi động lại Local

```bash
curl -I http://lacadev-client.local/hello-world/   # phải là 200, không phải 500
```
Mở 1 bài viết bất kỳ trên trình duyệt — trang phải hiển thị đầy đủ, phần
"rating-box" hiện icon + câu kêu gọi nhưng không có ô sao đánh giá (do
plugin chưa cài).

Nếu sau này bật tính năng Custom Login (`laca_enable_custom_login = 1`),
test theo đúng hướng dẫn đã áp dụng cho lacadev: truy cập slug đã cấu hình
phải phục vụ trang login, `/wp-login.php` phải trả 404.

## File đã thay đổi

- `app/src/Settings/Security/CustomLoginManager.php` — fix hook `plugins_loaded` (mục 1).
- `app/src/Settings/LacaTools/Security.php` — gỡ filter XML-RPC gây crash (mục 4).
- `theme/template-parts/rating-box.php` (theme cha) — thêm `function_exists()` guard (mục 5).
- `../lacadev-client-child/theme/template-parts/rating-box.php` (theme con, **đang active**) — thêm `function_exists()` guard (mục 5, fix quan trọng nhất).

## Việc chưa làm, cần bạn quyết định

- [ ] Có muốn kích hoạt tab "Security" của Tools không (thêm
      `new \App\Settings\LacaTools\Security();` vào `helpers.php`)? Hiện
      toàn bộ 4 toggle ở đó không có tác dụng gì.
- [ ] Có cần cài plugin KK Star Ratings để khôi phục tính năng đánh giá sao không?
