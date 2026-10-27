# Hướng dẫn kết nối Google Search Console + Google Analytics 4 (Laca Admin → Dịch vụ Google)

> Nguồn nội dung cho khối hướng dẫn trên 2 trang `admin.php?page=laca-google-search-console` và `admin.php?page=laca-google-analytics`.
> Dùng chung cho mọi site dùng theme lacadev-client. `{SITE}` = `home_url()` của site (vd `https://lixroastery.com`).

## 0. Nguyên tắc vàng (đọc trước)
- **Một tài khoản Google duy nhất** cho cả 3 nơi: Search Console, Google Analytics, Google Cloud — nên là **email của khách hàng** (chủ website), không dùng email cá nhân của dev.
- Ở **mọi bước**, nhìn avatar góc phải trên màn hình Google để chắc đang đúng tài khoản. Trình duyệt đăng nhập nhiều tài khoản rất dễ chọn nhầm.
- Mã GA chỉ đặt **một nơi**: hoặc ô Measurement ID ở trang này, hoặc Laca Theme → Scripts. Không đặt cả hai.

## 1. Google Search Console — thêm và xác minh website
1. Mở https://search.google.com/search-console (đăng nhập email khách).
2. Bấm ô chọn property góc trái → **Thêm sản phẩm (Add property)**:
   - **Miền (Domain)**: nhập `lixroastery.com` → thêm bản ghi **TXT** Google đưa vào DNS ở nơi quản lý tên miền (gom cả www/http/https). DNS có thể mất vài phút–vài giờ.
   - **Tiền tố URL (URL prefix)**: nhập đúng `{SITE}/` → chọn xác minh **Thẻ HTML** → copy phần `content="…"` → dán vào **Laca SEO → Cài đặt chung → Webmaster Tools → Google**
     → Lưu → xoá cache (LiteSpeed) → quay lại bấm **Xác minh**.
3. Nếu website **đã được xác minh ở tài khoản khác** (vd email dev): đăng nhập tài khoản đó → https://search.google.com/search-console/users
   (chọn đúng property) → **Thêm người dùng** → email khách, quyền **Chủ sở hữu** (Owner). Khi email khách vào được thì gỡ tài khoản cũ.
4. Gửi sitemap: https://search.google.com/search-console/sitemaps → nhập `sitemap_index.xml` → Gửi.

## 2. Google Analytics 4
1. Mở https://analytics.google.com → **Quản trị (Admin)** (biểu tượng bánh răng góc dưới trái).
2. Quyền: **Quản lý quyền truy cập tài khoản (Account access management)** → thêm email khách vai trò **Quản trị viên (Administrator)**. Gỡ email cũ khi đã chuyển xong.
3. Lấy Measurement ID: **Luồng dữ liệu (Data streams)** → chọn luồng Web của website → copy **Mã đo lường** dạng `G-XXXXXXXXXX`.
   - Nếu mã đã chèn ở **Laca Theme → Scripts** thì để trống ô Measurement ID ở trang này (hệ thống cũng tự bỏ qua nếu trùng).
4. Mục tiêu chuyển đổi (tuỳ chọn): **Quản trị → Sự kiện (Events)** → bật **Đánh dấu là sự kiện chính (Mark as key event)** cho sự kiện cần đo
   (vd `purchase`, `add_to_cart`, `generate_lead`). Sau đó ghi vào ô "Mục tiêu chuyển đổi" mỗi dòng `tên_sự_kiện|Nhãn hiển thị` — tên phải **giống hệt** tên trong GA.

## 3. Google Cloud — tạo kết nối OAuth (để xem báo cáo trong WordPress)
1. Tạo project (đăng nhập email khách): https://console.cloud.google.com/projectcreate → đặt tên (vd `LIX Roastery Website`) → Tạo.
   Kiểm tra **tên project đang chọn ở thanh trên cùng** trước mọi bước sau.
2. Bật 3 API (bấm **Bật / Enable** ở từng trang):
   - https://console.cloud.google.com/apis/library/searchconsole.googleapis.com — Google Search Console API
   - https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com — Google Analytics Data API
   - https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com — Google Analytics Admin API
3. Màn hình đồng ý OAuth: https://console.cloud.google.com/auth/overview → **Bắt đầu (Get started)**:
   - Tên ứng dụng: tên website; Email hỗ trợ: email khách; Đối tượng (Audience): **Bên ngoài (External)**; Thông tin liên hệ: email khách → Tạo.
   - **Đối tượng (Audience)**: https://console.cloud.google.com/auth/audience → bấm **Xuất bản ứng dụng (Publish app)** → Xác nhận.
     (Nếu để chế độ Testing: phải thêm email khách vào **Người dùng thử nghiệm (Test users)**, và **kết nối sẽ tự hết hạn sau 7 ngày**.)
4. Tạo Client: https://console.cloud.google.com/auth/clients → **Tạo client (Create client)**:
   - Loại ứng dụng: **Ứng dụng web (Web application)**.
   - **URI chuyển hướng được phép (Authorized redirect URIs)** → Thêm URI → dán **chính xác** (dùng nút Sao chép trên trang):
     `{SITE}/wp-admin/admin.php?page=laca-google-search-console&laca_google_oauth=1`
     (Giai đoạn chuyển tiếp có thể thêm cả URI cũ: `{SITE}/wp-admin/admin.php?page=laca-seo&view=search-console&laca_gsc_oauth=1`.)
   - Tạo → copy **Client ID** và **Client Secret** (Secret chỉ hiện đầy đủ lúc tạo — lưu lại an toàn).
5. Về **Laca Admin → Dịch vụ Google → Google Search Console** → dán Client ID + Client Secret → **Đăng nhập & Cấp quyền với Google**
   → chọn **đúng email khách** → nếu thấy "Google chưa xác minh ứng dụng này": bấm **Nâng cao → Đi tới … (không an toàn)** (ứng dụng do chính bạn tạo)
   → **tick tất cả quyền** (Search Console + Analytics) → Tiếp tục.
6. Sau khi về trang: kiểm tra badge **"Đã kết nối OAuth: <email khách>"** → chọn property Search Console → Lưu. Trang Google Analytics tự tìm property GA4 theo Measurement ID.
7. Mở `wp-admin/index.php` → khối báo cáo → **Làm mới**.

## 3b. PageSpeed Insights API key (tuỳ chọn nhưng nên có)
1. Đăng nhập email khách, chọn đúng project (cùng project OAuth).
2. Bật API: https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com → Bật.
3. Tạo key: https://console.cloud.google.com/apis/credentials → **Tạo thông tin xác thực** → **Khoá API**.
4. Bấm vào key vừa tạo → **Hạn chế API**: chỉ chọn **PageSpeed Insights API**. Không đặt hạn chế IP/referrer (máy chủ hosting gọi từ IP có thể đổi → key bị từ chối).
5. Dán vào **Laca Admin → Dịch vụ Google → Search Console** → ô "PageSpeed API Key" (hoặc `#laca-psi-key`) → Lưu. Về `index.php` → Kiểm tra PageSpeed.

## 4. Lỗi đã gặp và cách tránh
| Triệu chứng | Nguyên nhân | Cách xử lý / phòng tránh |
|---|---|---|
| Google báo **"Lỗi 403: access_denied — chưa hoàn tất quy trình xác minh"** khi đăng nhập | App OAuth đang **Testing** và email không có trong Test users | Publish app (mục 3.3) hoặc thêm email vào Test users |
| **"Tài khoản chưa có property nào"** / **HTTP 403 chưa có quyền với property** | Kết nối OAuth bằng **tài khoản Google khác** với tài khoản sở hữu website (từng xảy ra: kết nối bằng email khách nhưng property nằm ở email dev) | Xem badge email đang kết nối; bấm **Đổi tài khoản Google**, hoặc thêm email đang kết nối làm Chủ sở hữu trong Search Console (mục 1.3) |
| **redirect_uri_mismatch** | URI trong Google Cloud không khớp từng ký tự (http/https, www, thiếu tham số, dư khoảng trắng) | Dùng nút **Sao chép** trên trang, dán nguyên văn |
| Báo cáo báo **"API … has not been used in project … / is disabled"** | Chưa bật API, hoặc bật ở **project khác** với project chứa Client ID | Bật đủ 3 API trong **cùng project** (mục 3.2) |
| PageSpeed báo **"Quota exceeded … pagespeedonline"** / Lỗi 429 | Chưa có PageSpeed API key, dùng chung hạn mức miễn phí của Google và thường bị hết trong ngày | Tạo API key riêng cho PageSpeed Insights theo **mục 3b** |
| Kết nối **tự mất sau ~7 ngày** | App để chế độ Testing (refresh token hết hạn 7 ngày) | Publish app |
| Đổi Client ID/Secret xong báo chưa kết nối | Token cũ thuộc client cũ, hệ thống tự xoá | Bấm kết nối lại |
| Search Console hiện **0 / "Chưa có dữ liệu"** | Property mới; Search Console trễ **2–3 ngày** | Chờ vài ngày; kiểm tra đúng property đã chọn |
| GA4 **không có số** / không tìm thấy property | Tài khoản kết nối không có quyền với property GA4, hoặc Measurement ID không thuộc tài khoản đó | Thêm email kết nối vào GA (mục 2.2); kiểm tra Measurement ID (mục 2.3) |
| GA **đếm gấp đôi** lượt xem | Mã GA vừa ở Laca Theme → Scripts vừa ở ô Measurement ID | Chỉ giữ một nơi (hệ thống tự bỏ qua nếu trùng ID, nhưng nên dọn) |
| Tỷ lệ chuyển đổi / Mục tiêu không hiện | GA4 chưa đánh dấu sự kiện chính, hoặc tên sự kiện ghi sai | Mục 2.4 — tên sự kiện phải giống hệt GA |
| "Bỏ qua quản trị viên" không có tác dụng | Mã GA đang chèn thủ công qua Laca Theme → Scripts (tuỳ chọn chỉ áp cho mã chèn từ trang này) | Chuyển mã sang ô Measurement ID ở trang này |

## 5. Chi phí & hạn mức các API đang dùng

| API | Dùng cho | Chi phí | Hạn mức mặc định (tham khảo, có thể đổi) | Site này dùng thế nào |
|---|---|---|---|---|
| Search Console API | Báo cáo GSC, Kiểm tra index | Miễn phí | Search Analytics: theo QPM/QPD mỗi site & project; URL Inspection: **2.000 lượt/ngày, 600/phút mỗi property** | Báo cáo cache 1 giờ; Kiểm tra Index chỉ khi bấm |
| Google Analytics Data API (GA4) | Báo cáo GA4, Realtime | Miễn phí (property thường) | Core: **200.000 token/property/ngày, 40.000/giờ**; đa số request < 10 token | Gom batch, cache 1 giờ; realtime chỉ khi tab đang mở |
| Google Analytics Admin API | Danh sách property | Miễn phí | Hạn mức riêng, dư dùng | Cache 12 giờ + nút Tải lại |
| PageSpeed Insights API | Tốc độ trang | Miễn phí, **không cần bật thanh toán** | Có key: ~**25.000 lượt/ngày** mỗi project; không key: dùng chung hạn mức Google, **thường hết → lỗi 429** | Chỉ chạy khi bấm, cache 24 giờ/strategy |
| OAuth 2.0 | Đăng nhập Google | Miễn phí | — | — |

> **Ghi chú quan trọng:** **Không cần và không nên bật Billing** cho project này — mọi API trên miễn phí; vượt hạn mức chỉ bị từ chối tạm (429), không phát sinh tiền. Không dùng project này cho API trả phí (Maps, Places, Gemini…). Xem hạn mức thật tại https://console.cloud.google.com/apis/dashboard (chọn API → tab Hạn mức).
> Nguồn: https://developers.google.com/analytics/devguides/reporting/data/v1/quotas, https://developers.google.com/webmaster-tools/limits.
