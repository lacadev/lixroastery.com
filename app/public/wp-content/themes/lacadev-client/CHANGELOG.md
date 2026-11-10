# Nhật ký thay đổi (Changelog)

## 3.7 (09/11/2026)
- **Trang đăng nhập theo giờ**: Nền phong cảnh núi đồi nhiều lớp, rừng thông, mây trôi, lều và lửa trại; mặt trời/mặt trăng đi theo vòng cung theo múi giờ của site, có 4 tông màu bình minh / ngày / hoàng hôn / đêm.
- **Form đăng nhập đổi theo ngày/đêm**: Card kính sáng ban ngày, kính tối buổi tối (logo trắng), màu nhấn xanh rừng / vàng lửa trại; chữ đủ tương phản, card gọn hơn.
- **Câu chữ trang đăng nhập dịch qua file .po**: Không còn ghi cứng tiếng Việt/Anh trong JS; ô ghi đè trong Theme Options vẫn được ưu tiên.
- **Lưu ý cập nhật**: Nền mới chỉ hiện sau khi build lại theme con (`yarn build`); site chưa build vẫn giữ nền sao cũ.

## 3.6 (09/11/2026)
- **Dashboard mới**: Khối "Bảng điều khiển" ở đầu trang gồm số liệu (bài viết, trang, form liên hệ chưa đọc, bảo trì), Việc cần làm và Thao tác nhanh; bỏ banner Welcome, widget Site Health tiếng Anh và các ô trống "Drag boxes here".
- **Thanh lưu dính đáy**: Các trang cài đặt (Laca Admin, Laca Theme, Tối ưu hóa…) bỏ card "Actions" bên phải; nút Lưu và trạng thái tự lưu nằm ở thanh dưới cùng.
- **Laca Theme dùng tab dọc**: 10 tab hiện hết một lúc thay vì xuống 2 dòng.
- **Danh sách bài viết**: Gộp 3 cột SEO thành 1 (điểm + tình trạng tiêu đề/mô tả + nút "Sửa SEO" mở Quick Edit); bảng hết cuộn ngang.
- **Kiểm tra bảo mật**: Mục chưa đạt ghi kết quả thật lên trước, kèm tiêu chí và cách sửa; điểm ghi 0/n cho mục chưa đạt.
- **Gọn hơn**: Bỏ emoji ở tiêu đề trang và widget; hộp mô tả lặp tiêu đề rút thành 1 dòng.
- **Admin tiếng Việt mặc định**: User chưa tự chọn ngôn ngữ thấy admin tiếng Việt khi site có gói ngôn ngữ vi (không đổi ngôn ngữ frontend).

## 3.5 (09/11/2026)
- **Giao diện Laca Admin gọn hơn**: Dock gom còn 6 nhóm (không còn nhóm chỉ 1 mục), checklist phát hành thu gọn mặc định, thanh tab con có tiêu đề nhóm ở trên, trang Tối ưu hóa dùng nhãn tiếng Việt.
- **Màu trạng thái**: Đạt / cảnh báo / lỗi có màu riêng (xanh lá / cam / đỏ) thay vì cùng tông đen; vòng điểm kiểm tra bảo mật hiển thị đúng.
- **Sửa lỗi hiển thị**: Chân trang không còn bị dock che; bỏ cột Categories bị trùng và ẩn sẵn cột ít dùng ở danh sách bài viết; logo widget "Giới thiệu" và admin bar không còn bị vỡ.
- **Hướng dẫn**: Gộp "Hướng dẫn" vào menu "HD Sử dụng"; tài khoản Khách hàng mở được trang HD Sử dụng.

## 3.4 (07/11/2026)
- **Menu "Quản lý người dùng"**: Xác thực 2 bước (2FA) và Login Log (nhật ký đăng nhập thất bại) chuyển thành trang riêng trong nhóm Quản lý người dùng của Laca Admin. Login Log trước đây đã ghi log nhưng bị ẩn khỏi menu.
- **Menu "Super User" riêng**: Quản lý Super User tách khỏi trang Bảo mật thành menu độc lập. Link cũ tới tab 2FA/Super User tự chuyển sang trang mới.
- **Yêu cầu hỗ trợ**: Chỉ người dùng có quyền sửa bài viết mới thấy nút và widget gửi yêu cầu hỗ trợ.

## 3.3 (03/11/2026)
- **Trung tâm hỗ trợ khách hàng**: Widget dashboard và nút truy cập nhanh trên admin bar cho phép khách gửi yêu cầu hỗ trợ (lỗi, nội dung, tính năng...) có ký số HMAC-SHA256, đính kèm ảnh chụp màn hình từ Media Library và theo dõi trực tiếp trạng thái phản hồi từ kỹ thuật viên.
- **Báo cáo vận hành hàng tháng**: Tự động tổng hợp số liệu bài viết, trang mới, sự kiện bảo mật và dữ liệu hiệu suất Google Analytics 4 / Search Console định kỳ gửi về trung tâm điều hành.
- **Vai trò "Khách hàng" (`laca_client`)**: Bổ sung vai trò phân quyền an toàn cho khách tự quản lý nội dung (bài viết, trang, media, menu, form, SEO) và tự động ẩn các khu vực kỹ thuật nhạy cảm (plugins, themes, users, tools, cài đặt hệ thống).
- **Trang hướng dẫn sử dụng trực quan**: Tích hợp trang tài liệu ngắn gọn cho khách ngay trong menu quản trị.
- **Giám sát sao lưu & Cập nhật plugin**: Tự động nhận diện sao lưu của các plugin phổ biến (UpdraftPlus, All-in-One WP Migration, v.v.) và ghi nhận nhật ký cập nhật plugin trong heartbeat gửi về Hub.

## 3.2 (03/11/2026)
- **Bảo mật & Xác thực đa tầng**: Tách riêng 4 API key theo mục đích sử dụng (Tracker, Catalog, Remote, Portal) và hỗ trợ xác thực chữ ký số HMAC-SHA256 chống giả mạo, replay attack.
- **Kênh cập nhật an toàn**: Tải gói theme cập nhật qua endpoint bảo vệ tại trung tâm điều hành `lacadev.com` thay vì link công khai.
- **Chuẩn hoá kết nối**: Tối ưu hoá đường dẫn canonical REST API (`/wp-json/*`), tăng tính ổn định khi đồng bộ block và cập nhật từ xa.
- **Tương thích ngược**: Hỗ trợ chuyển tiếp mượt mà cho các site khách chưa nâng cấp key mới qua cơ chế Legacy Authentication.

## 3.1 (12/10/2026)
- Cập nhật cơ chế đồng bộ Block Gutenberg và ghi nhận hoạt động tự động.
- Nâng cấp giao diện quản trị Laca Admin Settings.
