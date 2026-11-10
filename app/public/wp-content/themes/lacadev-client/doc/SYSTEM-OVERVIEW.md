# TÀI LIỆU TỔNG HỢP HỆ THỐNG (CLIENT THEME)

Tài liệu kiến trúc tổng thể toàn diện của hệ thống Lacadev (Hub & Client) được lưu trữ tập trung tại Hub:
👉 **Repo `lacadev/lacadev.com` → `app/public/wp-content/themes/lacadev/doc/SYSTEM-OVERVIEW.md`** (khác repo nên không dùng link tương đối)

## Tóm tắt vai trò của Client Theme (`lacadev-client`)

- **Theme cha độc lập**: Cung cấp bộ khung tính năng nền tảng cho site khách hàng (Tracker Client, Remote Update, Theme Updater tự động, Client Support Widget, bảo mật, Email Log).
- **Giao tiếp Hub 2 chiều**: Định kỳ gửi nhật ký FIM/plugins/heartbeat qua HMAC-SHA256 (`POST /wp-json/laca/v1/tracker/log`), nhận lệnh nâng cấp từ xa (`POST /wp-json/laca/v1/remote-update`), và tự động kéo bản cập nhật theme từ Hub (`GET /wp-json/laca/v1/theme-package/lacadev-client`).
- **Phân tách Theme con**: Mọi tùy biến giao diện, block Gutenberg và logic đặc thù từng khách hàng được đặt hoàn toàn tại child theme (`lacadev-client-child`), giúp việc nâng cấp theme cha diễn ra an toàn 100%.
