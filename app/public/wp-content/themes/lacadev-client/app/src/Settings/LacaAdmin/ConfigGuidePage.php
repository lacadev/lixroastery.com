<?php

namespace App\Settings\LacaAdmin;

/**
 * Trang "Hướng dẫn cấu hình" — cách cấu hình Laca Admin + Laca SEO cho mọi
 * site dùng theme Laca, theo thứ tự nên làm. Nội dung bám đúng nhãn và giá
 * trị mặc định của từng tuỳ chọn (rà lại khi đổi tên field/trang).
 *
 * Hiện ở 2 nơi: Laca Admin → Bắt đầu (dock) và menu "HD Sử dụng" (chỉ admin).
 */
class ConfigGuidePage
{
    public const SLUG = 'laca-guide';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages'], 20);
    }

    public function addMenuPages(): void
    {
        add_submenu_page(
            'laca-admin',
            __('Hướng dẫn cấu hình', 'laca'),
            __('Hướng dẫn cấu hình', 'laca'),
            'manage_options',
            self::SLUG,
            [$this, 'render']
        );

        // Lối vào thứ 2 trong menu "HD Sử dụng" — slug là URL nên WP chỉ tạo
        // link, không đăng ký trang trùng.
        add_submenu_page(
            'lacadev-help',
            __('Hướng dẫn cấu hình', 'laca'),
            __('Hướng dẫn cấu hình', 'laca'),
            'manage_options',
            'admin.php?page=' . self::SLUG
        );
    }

    private static function url(string $query): string
    {
        return admin_url('admin.php?' . $query);
    }

    /**
     * Các bước khi dựng / bàn giao một site mới, theo đúng thứ tự.
     *
     * @return array<int,array{title:string,desc:string,url:string}>
     */
    private function quickStart(): array
    {
        return [
            ['title' => __('Bật chế độ bảo trì trong lúc dựng site', 'laca'), 'desc' => __('Khách vào chỉ thấy trang bảo trì. Nhớ tắt khi bàn giao.', 'laca'), 'url' => self::url('page=laca-admin')],
            ['title' => __('Nhập logo, thông tin liên hệ, footer', 'laca'), 'desc' => __('Menu Laca Theme (Branding, Contact, Footer). Footer và trang Liên hệ tự lấy từ đây.', 'laca'), 'url' => admin_url('admin.php?page=app-theme-options.php')],
            ['title' => __('Cấu hình Email (SMTP) và gửi thử', 'laca'), 'desc' => __('Không có SMTP, mail form liên hệ dễ vào spam hoặc mất.', 'laca'), 'url' => self::url('page=laca-smtp')],
            ['title' => __('Bảo vệ đăng nhập', 'laca'), 'desc' => __('URL đăng nhập riêng, giới hạn đăng nhập sai, 2FA cho admin.', 'laca'), 'url' => self::url('page=laca-security&tab=login')],
            ['title' => __('Gia cố WordPress', 'laca'), 'desc' => __('Tắt XML-RPC, WP-Embed, X-Pingback; giữ bật HTTP Security Headers.', 'laca'), 'url' => self::url('page=laca-security&tab=hardening')],
            ['title' => __('Kết nối Google', 'laca'), 'desc' => __('reCAPTCHA cho form, Google Analytics / GTM, Search Console.', 'laca'), 'url' => self::url('page=laca-recaptcha')],
            ['title' => __('Kết nối thông báo', 'laca'), 'desc' => __('Email / Zalo / Telegram cho sự kiện của site; Discord Hub (Super User) nhận lead và cảnh báo bảo mật.', 'laca'), 'url' => self::url('page=laca-project-notifications')],
            ['title' => __('Thiết lập Laca SEO', 'laca'), 'desc' => __('Tiêu đề & mô tả trang chủ, thông tin doanh nghiệp, ảnh chia sẻ, sitemap.', 'laca'), 'url' => self::url('page=laca-seo&view=titles')],
            ['title' => __('Bật các tối ưu hiệu năng an toàn', 'laca'), 'desc' => __('Tắt Emoji, Lazy Load ảnh, giới hạn revision, dọn database tự động.', 'laca'), 'url' => self::url('page=laca-tools')],
            ['title' => __('Hoàn thành Checklist phát hành', 'laca'), 'desc' => __('Mọi mục "quan trọng" phải đạt trước khi chạy thật.', 'laca'), 'url' => self::url('page=laca-dashboard')],
            ['title' => __('Tạo baseline giám sát file và tắt bảo trì', 'laca'), 'desc' => __('Sau lần deploy cuối: Bảo mật → Giám sát file → Tạo baseline, rồi tắt chế độ bảo trì.', 'laca'), 'url' => self::url('page=laca-security&tab=fim')],
        ];
    }

    /**
     * Hướng dẫn chi tiết theo từng nhóm menu.
     *
     * @return array<int,array{title:string,intro:string,items:array<int,array{title:string,url:string,steps:string[]}>}>
     */
    private function sections(): array
    {
        return [
            [
                'title' => __('Website', 'laca'),
                'intro' => __('Cấu hình nền tảng: bảo trì, đăng nhập, gửi email, khu quản trị cho khách.', 'laca'),
                'items' => [
                    [
                        'title' => __('Bảo trì & Khu quản trị', 'laca'),
                        'url'   => self::url('page=laca-admin'),
                        'steps' => [
                            __('"Bật chế độ bảo trì": bật khi đang dựng hoặc sửa lớn; khách chỉ thấy trang bảo trì, admin vẫn xem được site.', 'laca'),
                            __('"Tắt chức năng chỉnh sửa code": nên bật — chặn sửa file theme/plugin ngay trong admin.', 'laca'),
                            __('"Tắt chức năng sử dụng mật khẩu yếu": nên bật.', 'laca'),
                            __('"Ẩn menu bài viết / bình luận mặc định": bật nếu site không dùng Bài viết hoặc Bình luận của WordPress.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Trang đăng nhập', 'laca'),
                        'url'   => self::url('page=laca-login-page'),
                        'steps' => [
                            __('Tải "Login logo" (để trống sẽ dùng logo website).', 'laca'),
                            __('Sửa lời chào và nhãn ô nhập cho 2 ngôn ngữ nếu cần; để trống dùng câu mặc định.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Email (SMTP) & Nhật ký Email', 'laca'),
                        'url'   => self::url('page=laca-smtp'),
                        'steps' => [
                            __('Bật "Sử dụng SMTP để gửi mail", nhập máy chủ, cổng, mã hoá (587 + TLS hoặc 465 + SSL), email và mật khẩu.', 'laca'),
                            __('Dùng hộp thư theo tên miền (vd info@tenmien.com) thay vì Gmail cá nhân — Gmail miễn phí hay bị chấm spam và giới hạn khoảng 500 mail/ngày.', 'laca'),
                            __('Gửi thử một form liên hệ, rồi mở tab "Nhật ký Email": dòng mới phải ghi "Đã gửi".', 'laca'),
                            __('Tên miền nên có bản ghi SPF và DMARC (nhờ nhà cung cấp email/hosting) để mail không vào spam.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Giao diện admin cho khách', 'laca'),
                        'url'   => self::url('page=laca-management-settings'),
                        'steps' => [
                            __('Tab "Nội dung HD Sử dụng": viết hướng dẫn riêng cho khách của site này (hiện trong menu HD Sử dụng), điền số Zalo / email / website hỗ trợ.', 'laca'),
                            __('Tab "Vai trò Khách hàng": gán vai trò Khách hàng cho tài khoản của khách — sửa được nội dung và SEO nhưng ẩn Plugin, Giao diện, Người dùng, Cài đặt và Laca Admin.', 'laca'),
                            __('Tab "Dashboard Widget": chọn loại nội dung và số bài hiện trên Bảng tin.', 'laca'),
                        ],
                    ],
                ],
            ],
            [
                'title' => __('Bảo mật & tài khoản', 'laca'),
                'intro' => __('Làm đủ nhóm này trước khi site chạy thật.', 'laca'),
                'items' => [
                    [
                        'title' => __('Tường lửa & Gia cố → tab Đăng nhập', 'laca'),
                        'url'   => self::url('page=laca-security&tab=login'),
                        'steps' => [
                            __('Bật "URL đăng nhập tùy chỉnh", đặt slug khó đoán và LƯU LẠI ở nơi an toàn. Đường dẫn cũ sẽ trả 404.', 'laca'),
                            __('"Giới hạn đăng nhập sai": giữ mặc định 5 lần / 15 phút, khoá 30 phút; bấm "Thêm IP của tôi" để không tự khoá mình.', 'laca'),
                            __('"Honeypot form đăng nhập": giữ bật.', 'laca'),
                            __('"Bắt buộc xác thực 2 bước theo vai trò": chọn Administrator (sau khi đã bật 2FA toàn site).', 'laca'),
                            __('"Cảnh báo khi admin đăng nhập từ IP mới": giữ bật (gửi qua Discord).', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Tường lửa & Gia cố → tab Gia cố', 'laca'),
                        'url'   => self::url('page=laca-security&tab=hardening'),
                        'steps' => [
                            __('Bật "Tắt hoàn toàn XML-RPC", "Tắt WP-Embed", "Tắt Header X-Pingback", "Loại bỏ thành phần thừa".', 'laca'),
                            __('"Tắt REST API đối với khách vãng lai": chỉ bật khi đã thử lại form liên hệ, giỏ hàng/thanh toán và mọi tính năng dùng AJAX.', 'laca'),
                            __('HTTP Security Headers: giữ mặc định (tất cả bật). HSTS chỉ gửi khi site đã chạy HTTPS.', 'laca'),
                            __('Tab "Kiểm tra bảo mật": bấm "Chạy kiểm tra" và xử lý các mục chưa đạt.', 'laca'),
                            __('Tab "Giám sát file": "Tạo baseline" sau lần deploy cuối; mỗi tháng "So sánh với baseline".', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Giám sát truy cập', 'laca'),
                        'url'   => self::url('page=laca-traffic&tab=firewall'),
                        'steps' => [
                            __('Tab "Tường lửa request": để chế độ "Chỉ ghi cảnh báo" 1–2 tuần, xem không chặn nhầm khách thật rồi mới chuyển "Chặn bằng HTTP 403".', 'laca'),
                            __('Tab "Cài đặt": thêm IP văn phòng vào "Danh sách IP tin cậy"; bật "ghi nhận lượt truy cập công khai" nếu muốn xem thống kê IP.', 'laca'),
                            __('Tab "IP đã chặn": chặn IP / dải IP tấn công nhiều lần.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Xác thực 2 bước (2FA)', 'laca'),
                        'url'   => self::url('page=laca-2fa'),
                        'steps' => [
                            __('Bật "2FA toàn site", rồi mỗi admin vào Hồ sơ của mình để quét mã QR bằng ứng dụng xác thực.', 'laca'),
                            __('Bảng bên dưới cho biết tài khoản nào chưa bật — nhắc họ bật trước khi bắt buộc theo vai trò.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Super User & Nhật ký đăng nhập', 'laca'),
                        'url'   => self::url('page=laca-super-user'),
                        'steps' => [
                            __('Super User bị ẩn khỏi danh sách người dùng và không bị giới hạn — chỉ cấp cho kỹ thuật viên.', 'laca'),
                            __('Nhật ký đăng nhập liệt kê các lần đăng nhập sai; xem khi nghi bị dò mật khẩu.', 'laca'),
                        ],
                    ],
                ],
            ],
            [
                'title' => __('Nội dung', 'laca'),
                'intro' => __('Loại nội dung riêng và form liên hệ.', 'laca'),
                'items' => [
                    [
                        'title' => __('Custom Post Types', 'laca'),
                        'url'   => self::url('page=laca-dynamic-cpt'),
                        'steps' => [
                            __('Tạo loại nội dung riêng (dự án, dịch vụ…) không cần code. Đổi slug sau khi đã có bài sẽ làm đổi đường dẫn — cần tạo chuyển hướng 301.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Form liên hệ', 'laca'),
                        'url'   => self::url('page=laca-contact-forms'),
                        'steps' => [
                            __('Tạo form, kiểm tra email nhận; bật reCAPTCHA cho form (Dịch vụ Google) để chặn spam.', 'laca'),
                            __('Lead còn được gửi về kênh Discord "Leads" nếu đã kết nối.', 'laca'),
                        ],
                    ],
                ],
            ],
            [
                'title' => __('Kết nối', 'laca'),
                'intro' => __('Google, thông báo và LacaDev.', 'laca'),
                'items' => [
                    [
                        'title' => __('Dịch vụ Google', 'laca'),
                        'url'   => self::url('page=laca-recaptcha'),
                        'steps' => [
                            __('reCAPTCHA: tạo khoá v3 tại google.com/recaptcha, dán "Site Key" và "Secret Key"; giữ bật cho Đăng nhập, Đăng ký, Bình luận, Form liên hệ; "Điểm tối thiểu" 0.5.', 'laca'),
                            __('Google Analytics & GTM: nhập Client ID / Secret (OAuth), chọn property GA4 và điền Measurement ID hoặc GTM Container ID; giữ bật "Ẩn danh địa chỉ IP" và "Bỏ qua người dùng đăng nhập".', 'laca'),
                            __('Google Search Console: chọn đúng property của site để xem báo cáo ngay trong admin.', 'laca'),
                            __('Đăng nhập Google: chỉ bật khi đã có Client ID / Secret; dùng đúng "Redirect URI" hiển thị sẵn.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Thông báo (Zalo, Discord…)', 'laca'),
                        'url'   => self::url('page=laca-project-notifications'),
                        'steps' => [
                            __('Discord Hub (tab bên cạnh, chỉ Super User mở được): dán webhook cho các kênh Leads (form liên hệ), Audit (thay đổi nội dung), Security (cảnh báo bảo mật), Updates; bấm "Gửi thử".', 'laca'),
                            __('Giữ bật "Quét file code mỗi 15 phút" — cần kênh Security để nhận cảnh báo file bị sửa.', 'laca'),
                            __('Tab Zalo OA / Email / Telegram / Slack: chỉ bật kênh thật sự có người đọc; "Định tuyến thông báo" quyết định sự kiện nào gửi kênh nào.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Kết nối LacaDev', 'laca'),
                        'url'   => self::url('page=laca-block-sync'),
                        'steps' => [
                            __('Đồng bộ block và Site Tracker với hệ thống LacaDev — thường do kỹ thuật viên cấu hình, khách không cần sửa.', 'laca'),
                        ],
                    ],
                ],
            ],
            [
                'title' => __('Nâng cao', 'laca'),
                'intro' => __('Hiệu năng, thông tin máy chủ, sao chép cấu hình sang site khác.', 'laca'),
                'items' => [
                    [
                        'title' => __('Hiệu năng & Dọn dẹp', 'laca'),
                        'url'   => self::url('page=laca-tools'),
                        'steps' => [
                            __('An toàn nên bật: "Tắt Emoji của WordPress", "Tải ảnh chậm" cho ảnh đại diện và ảnh trong bài, "Số bản sửa (revision) giữ lại" = 5, "Kiểm soát Heartbeat" = Chậm (60 giây).', 'laca'),
                            __('"Bỏ jQuery Migrate": bật rồi thử lại menu, slider, form — tắt lại nếu có lỗi JavaScript.', 'laca'),
                            __('Không bật trùng tính năng mà LiteSpeed Cache đã làm (xoá chú thích HTML, nén CSS/JS, tối ưu ảnh) — bật 2 nơi dễ gây lỗi hiển thị.', 'laca'),
                            __('Tab "Dọn dẹp Database": bật "Dọn tự động" hằng tuần, giữ lại 5 revision mới nhất mỗi bài.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Thông tin hệ thống', 'laca'),
                        'url'   => self::url('page=laca-system'),
                        'steps' => [
                            __('Xem phiên bản PHP (nên từ 8.1), giới hạn bộ nhớ và các extension còn thiếu (fileinfo, sodium, intl…).', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Xuất / nhập cấu hình', 'laca'),
                        'url'   => self::url('page=laca-config'),
                        'steps' => [
                            __('Xuất cấu hình từ site đã chuẩn rồi nhập vào site mới để không phải cấu hình lại từ đầu.', 'laca'),
                            __('Chỉ chọn "Kèm mật khẩu và khoá API" khi tải về máy cá nhân an toàn — file chứa mật khẩu dạng rõ.', 'laca'),
                        ],
                    ],
                ],
            ],
            [
                'title' => __('Laca SEO', 'laca'),
                'intro' => __('Làm theo thứ tự trong menu Laca SEO: Thiết lập → Công cụ → Sao lưu.', 'laca'),
                'items' => [
                    [
                        'title' => __('Tiêu đề & Meta', 'laca'),
                        'url'   => self::url('page=laca-seo&view=titles'),
                        'steps' => [
                            __('Tab Trang chủ: viết "Mô tả tóm tắt SEO Trang chủ" (khoảng 150–160 ký tự) và kiểm tra tiêu đề.', 'laca'),
                            __('Tab Toàn site: tải "Ảnh đại diện mặc định khi chia sẻ" (1200×630px) — dùng khi bài không có ảnh.', 'laca'),
                            __('Tab Local SEO: điền tên doanh nghiệp, logo, email, điện thoại, địa chỉ (để Google hiển thị thông tin doanh nghiệp).', 'laca'),
                            __('Tab Mạng xã hội: Fanpage và các trang mạng xã hội khác (sameAs).', 'laca'),
                            __('Giữ mặc định noindex cho lưu trữ theo ngày, kết quả tìm kiếm nội bộ, trang tác giả và thẻ (tag).', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Cài đặt chung', 'laca'),
                        'url'   => self::url('page=laca-seo&view=general'),
                        'steps' => [
                            __('Xác minh Google / Bing: dán mã meta của Search Console và Bing Webmaster (bỏ qua nếu đã xác minh bằng DNS).', 'laca'),
                            __('Hình ảnh: bật tự thêm ALT cho ảnh thiếu nếu nội dung cũ chưa có alt.', 'laca'),
                            __('Breadcrumbs: bật nếu giao diện có hiển thị đường dẫn breadcrumb.', 'laca'),
                            __('Đường dẫn: giữ bật "Chuyển hướng trang đính kèm". robots.txt: để trống để dùng mặc định.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Sitemap', 'laca'),
                        'url'   => self::url('page=laca-seo&view=sitemap'),
                        'steps' => [
                            __('Sitemap nằm ở /sitemap_index.xml — gửi đường dẫn này trong Google Search Console.', 'laca'),
                            __('Bật thêm danh mục cần lên Google (vd danh mục sản phẩm); mặc định chỉ có danh mục bài viết.', 'laca'),
                            __('Nếu trang tác giả đang noindex, tắt "Bao gồm trang tác giả trong Sitemap" để không mâu thuẫn.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Công cụ', 'laca'),
                        'url'   => self::url('page=laca-seo&view=redirections'),
                        'steps' => [
                            __('Chuyển hướng và Theo dõi 404 tắt sẵn — bật module ở Bảng điều khiển Laca SEO khi cần.', 'laca'),
                            __('Trong Chuyển hướng → "Cài đặt": bật "Tự động tạo chuyển hướng khi đổi slug", giữ mã 301.', 'laca'),
                            __('Mỗi tuần xem Theo dõi lỗi 404 và tạo chuyển hướng 301 cho đường dẫn cũ còn được truy cập.', 'laca'),
                            __('Báo Google/Bing index ngay: tạo IndexNow API Key; bài và trang mới sẽ tự được gửi.', 'laca'),
                        ],
                    ],
                    [
                        'title' => __('Sao lưu & Hướng dẫn tối ưu SEO', 'laca'),
                        'url'   => self::url('page=laca-seo&view=guide'),
                        'steps' => [
                            __('Làm cho tới khi checklist trong "Hướng dẫn tối ưu SEO" đạt hết.', 'laca'),
                            __('Xuất bản sao lưu SEO trước khi đổi cấu hình lớn; nhập từ Rank Math khi chuyển site cũ sang.', 'laca'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int,array{title:string,steps:string[]}>
     */
    private function routines(): array
    {
        return [
            [
                'title' => __('Hằng tuần', 'laca'),
                'steps' => [
                    __('Nhật ký Email: mail form liên hệ vẫn "Đã gửi".', 'laca'),
                    __('Laca SEO → Theo dõi lỗi 404: tạo chuyển hướng cho link hỏng.', 'laca'),
                    __('Giám sát truy cập: xem IP lạ, chặn nếu tấn công nhiều lần.', 'laca'),
                ],
            ],
            [
                'title' => __('Hằng tháng', 'laca'),
                'steps' => [
                    __('Bảo mật → "Chạy kiểm tra", "So sánh với baseline", "Quét mã độc".', 'laca'),
                    __('Cập nhật WordPress, plugin; không để bản cập nhật chờ quá 30 ngày.', 'laca'),
                    __('Xuất cấu hình (Nâng cao → Xuất / nhập cấu hình) để có bản sao lưu cài đặt.', 'laca'),
                ],
            ],
        ];
    }

    /**
     * @return array<int,array{title:string,fix:string}>
     */
    private function troubleshooting(): array
    {
        return [
            ['title' => __('Bị khoá, không đăng nhập được', 'laca'), 'fix' => __('Thêm dòng define(\'LACA_SECURITY_BYPASS\', true); vào wp-config.php, đăng nhập, mở khoá IP rồi xoá dòng đó. Quên đường dẫn đăng nhập riêng: xoá option laca_login_slug trong database.', 'laca')],
            ['title' => __('Mail form liên hệ không tới', 'laca'), 'fix' => __('Xem Nhật ký Email: báo lỗi thì kiểm tra lại SMTP; "Đã gửi" mà khách không nhận thì kiểm tra thư rác và bản ghi SPF/DMARC của tên miền.', 'laca')],
            ['title' => __('Footer / thông tin liên hệ bị trống', 'laca'), 'fix' => __('Mở Laca Theme, bấm Lưu một lần, rồi LiteSpeed Cache → Purge All.', 'laca')],
            ['title' => __('Hosting báo mã độc và tự xoá file của theme', 'laca'), 'fix' => __('Không tải bản theme trên hosting về dùng lại; báo kỹ thuật viên để khôi phục từ git và thêm file vào danh sách bỏ qua của trình quét.', 'laca')],
        ];
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }
        ?>
        <div class="wrap laca-guide">
            <div class="laca-page-header">
                <div class="laca-page-header__content">
                    <h1 class="laca-page-header__title"><?php esc_html_e('Hướng dẫn cấu hình', 'laca'); ?></h1>
                    <p class="laca-page-header__description"><?php esc_html_e('Cách cấu hình Laca Admin và Laca SEO để website chạy ổn định, an toàn và chuẩn SEO. Áp dụng cho mọi site dùng theme Laca — làm theo thứ tự từ trên xuống.', 'laca'); ?></p>
                </div>
            </div>

            <section class="laca-card laca-guide__quickstart" aria-labelledby="laca-guide-quickstart">
                <div class="laca-card__header">
                    <h2 class="laca-card__title" id="laca-guide-quickstart"><?php esc_html_e('Bắt đầu nhanh: các bước khi dựng site mới', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body">
                    <ol class="laca-guide__steps">
                        <?php foreach ($this->quickStart() as $step): ?>
                            <li class="laca-guide__step">
                                <?php // Mở tab mới để vừa đọc hướng dẫn vừa cài đặt. ?>
                                <a class="laca-guide__step-link" href="<?php echo esc_url($step['url']); ?>" target="_blank" rel="noopener">
                                    <span class="laca-guide__step-title"><?php echo esc_html($step['title']); ?></span>
                                    <span class="laca-guide__step-desc"><?php echo esc_html($step['desc']); ?></span>
                                    <span class="screen-reader-text"><?php esc_html_e('(mở tab mới)', 'laca'); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </section>

            <?php foreach ($this->sections() as $section): ?>
                <section class="laca-card laca-guide__section">
                    <div class="laca-card__header">
                        <h2 class="laca-card__title"><?php echo esc_html($section['title']); ?></h2>
                        <p class="laca-card__desc"><?php echo esc_html($section['intro']); ?></p>
                    </div>
                    <div class="laca-card__body laca-guide__items">
                        <?php foreach ($section['items'] as $item): ?>
                            <details class="laca-guide__item">
                                <summary class="laca-guide__summary"><?php echo esc_html($item['title']); ?></summary>
                                <div class="laca-guide__detail">
                                    <ul class="laca-guide__list">
                                        <?php foreach ($item['steps'] as $line): ?>
                                            <li><?php echo esc_html($line); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a class="laca-btn laca-btn--outline laca-btn--sm" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
                                        <?php esc_html_e('Mở trang cài đặt', 'laca'); ?>
                                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('(mở tab mới)', 'laca'); ?></span>
                                    </a>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="laca-card laca-guide__section">
                <div class="laca-card__header">
                    <h2 class="laca-card__title"><?php esc_html_e('Bảo trì định kỳ', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body laca-guide__routines">
                    <?php foreach ($this->routines() as $routine): ?>
                        <div class="laca-guide__routine">
                            <h3 class="laca-guide__routine-title"><?php echo esc_html($routine['title']); ?></h3>
                            <ul class="laca-guide__list">
                                <?php foreach ($routine['steps'] as $line): ?>
                                    <li><?php echo esc_html($line); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="laca-card laca-guide__section">
                <div class="laca-card__header">
                    <h2 class="laca-card__title"><?php esc_html_e('Xử lý sự cố thường gặp', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body laca-guide__items">
                    <?php foreach ($this->troubleshooting() as $issue): ?>
                        <details class="laca-guide__item">
                            <summary class="laca-guide__summary"><?php echo esc_html($issue['title']); ?></summary>
                            <div class="laca-guide__detail">
                                <p><?php echo esc_html($issue['fix']); ?></p>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php
    }
}
