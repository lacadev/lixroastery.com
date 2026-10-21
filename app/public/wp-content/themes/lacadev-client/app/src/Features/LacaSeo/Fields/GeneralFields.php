<?php

namespace App\Features\LacaSeo\Fields;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Registry of all General Settings fields (1:1 with Rank Math General).
 */
class GeneralFields
{
    /**
     * @return array<string, Field[]> Map of tab slug => array of Field objects
     */
    public static function get(): array
    {
        return [
            'links' => self::getLinksFields(),
            'images' => self::getImagesFields(),
            'breadcrumbs' => self::getBreadcrumbsFields(),
            'webmaster' => self::getWebmasterFields(),
            'analytics' => self::getAnalyticsFields(),
            'robots' => self::getRobotsTxtFields(),
            'htaccess' => self::getHtaccessFields(),
            'woocommerce' => self::getWooCommerceFields(),
            'blocks' => self::getBlocksFields(),
            'others' => self::getOthersFields(),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getLinksFields(): array
    {
        return [
            Field::make('strip_category_base', 'toggle', 'Bỏ /category/ khỏi URL')
                ->default('off')
                ->tab('links')
                ->group('general')
                ->note(
                    'Bỏ tiền tố /category/ trong URL chuyên mục bài viết.',
                    'Bật tuỳ chọn nếu muốn URL gọn như /tin-tuc/ thay vì /category/tin-tuc/. Sau khi đổi, vào Cài đặt › Đường dẫn tĩnh bấm Lưu.',
                    'URL chuyên mục bài viết ngắn gọn, thân thiện SEO hơn; URL cũ tự động 301 sang URL mới.'
                ),

            Field::make('attachment_redirect_urls', 'toggle', 'Chuyển hướng trang đính kèm (Attachment)')
                ->default('on')
                ->tab('links')
                ->group('general')
                ->note(
                    'Chuyển hướng URL trang media (ảnh/file) về bài viết chứa nó.',
                    'Bật tuỳ chọn để tránh Google lập chỉ mục hàng trăm trang trắng chỉ chứa 1 tấm ảnh duy nhất (thin content).',
                    'Người dùng hoặc bot khi vào link ảnh sẽ được đưa về đúng bài viết gốc.'
                ),

            Field::make('attachment_redirect_default', 'text', 'Chuyển hướng ảnh mồ côi (Orphan Attachments)')
                ->default(get_home_url())
                ->tab('links')
                ->group('general')
                ->showIf('attachment_redirect_urls', 'on')
                ->note(
                    'Đích đến cho các tệp ảnh tải trực tiếp vào Thư viện không thuộc bài viết nào.',
                    'Nhập URL đích muốn chuyển hướng tới (mặc định là trang chủ của website).',
                    'Ảnh mồ côi sẽ được 301 chuyển hướng về URL này thay vì báo 404.'
                ),

            Field::make('nofollow_external_links', 'toggle', 'Tự động thêm Nofollow cho link ngoài')
                ->default('off')
                ->tab('links')
                ->group('general')
                ->note(
                    'Tự động gắn thuộc tính rel="nofollow" cho mọi liên kết trỏ ra website khác trong nội dung.',
                    'Bật nếu bạn muốn bảo toàn sức mạnh trang (PageRank) và không muốn truyền tín hiệu tin cậy bừa bãi.',
                    'Kiểm tra mã nguồn thẻ <a> có thuộc tính rel="nofollow".'
                ),

            Field::make('nofollow_image_links', 'toggle', 'Nofollow liên kết đến tệp ảnh ngoài')
                ->default('off')
                ->tab('links')
                ->group('general')
                ->note(
                    'Gắn rel="nofollow" cho liên kết trỏ trực tiếp đến URL tệp hình ảnh bên ngoài.',
                    'Bật nếu website của bạn trỏ nhiều link tải ảnh nguồn ngoài.',
                    'Các thẻ <a> trỏ file ảnh ngoài sẽ có thuộc tính rel="nofollow".'
                ),

            Field::make('nofollow_domains', 'textarea', 'Danh sách tên miền chỉ định Nofollow')
                ->default('')
                ->tab('links')
                ->group('general')
                ->showIf('nofollow_external_links', 'on')
                ->note(
                    'Chỉ áp dụng nofollow khi domain đích nằm trong danh sách này (mỗi dòng một domain).',
                    'Nhập các domain bạn không muốn truyền PageRank, để trống để áp dụng cho TẤT CẢ domain ngoài.',
                    'Chỉ các link trỏ tới danh sách domain này mới nhận rel="nofollow".'
                ),

            Field::make('nofollow_exclude_domains', 'textarea', 'Danh sách tên miền ngoại trừ Nofollow')
                ->default('')
                ->tab('links')
                ->group('general')
                ->showIf('nofollow_external_links', 'on')
                ->note(
                    'Không bao giờ gắn nofollow đối với các domain uy tín nằm trong danh sách này (mỗi dòng một domain).',
                    'Nhập domain các site vệ tinh, đối tác hoặc mạng xã hội chính thức của bạn.',
                    'Các link trỏ tới domain này sẽ được giữ rel="follow" bình thường.'
                ),

            Field::make('new_window_external_links', 'toggle', 'Mở liên kết ngoài trong tab mới')
                ->default('on')
                ->tab('links')
                ->group('general')
                ->note(
                    'Tự động thêm thuộc tính target="_blank" cho các liên kết trỏ ra ngoài website.',
                    'Bật để giữ chân khách truy cập trên trang web của bạn khi họ click vào nguồn tham khảo.',
                    'Mọi link ra ngoài khi bấm vào sẽ mở trong cửa sổ/tab trình duyệt mới.'
                ),

            Field::make('affiliate_link_prefixes', 'textarea', 'Tiền tố liên kết tiếp thị liên kết (Affiliate Prefixes)')
                ->default('')
                ->tab('links')
                ->group('general')
                ->note(
                    'Danh sách các tiền tố URL chuyển hướng liên kết affiliate (mỗi dòng một tiền tố, ví dụ: /go/, /out/).',
                    'Nhập các đường dẫn bạn dùng để cloaking link tiếp thị liên kết.',
                    'Hệ thống tự động thêm thuộc tính rel="sponsored nofollow" cho các liên kết này.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getImagesFields(): array
    {
        return [
            Field::make('add_img_alt', 'toggle', 'Tự động thêm thuộc tính ALT cho ảnh thiếu')
                ->default('off')
                ->tab('images')
                ->group('general')
                ->note(
                    'Tự động điền thẻ alt cho các hình ảnh trong nội dung bài viết khi tải trang nếu tác giả quên nhập.',
                    'Bật để tăng điểm SEO On-Page và khả năng tiếp cận (Accessibility), không ghi đè vào CSDL.',
                    'Kiểm tra HTML thẻ <img> sẽ thấy thuộc tính alt="..." được sinh tự động theo mẫu.'
                ),

            Field::make('img_alt_format', 'variable_text', 'Cấu trúc định dạng ALT tự động')
                ->default(' %filename%')
                ->tab('images')
                ->group('general')
                ->showIf('add_img_alt', 'on')
                ->note(
                    'Mẫu sinh nội dung alt cho ảnh (hỗ trợ biến %filename%, %title%).',
                    'Khuyên dùng: "%title% %filename%" để kết hợp tên bài viết và tên tệp ảnh.',
                    'Thẻ alt của ảnh sẽ hiển thị theo đúng chuỗi đã thiết lập.'
                ),

            Field::make('add_img_title', 'toggle', 'Tự động thêm thuộc tính TITLE cho ảnh thiếu')
                ->default('off')
                ->tab('images')
                ->group('general')
                ->note(
                    'Tự động bổ sung thẻ title cho ảnh khi di chuột vào hình ảnh trong nội dung.',
                    'Bật tuỳ chọn nếu muốn hiện chú thích dạng tooltip khi độc giả rê chuột qua ảnh.',
                    'Thẻ <img> sẽ có thêm thuộc tính title="..." khi xem mã nguồn.'
                ),

            Field::make('img_title_format', 'variable_text', 'Cấu trúc định dạng TITLE ảnh tự động')
                ->default('%title% %count(title)%')
                ->tab('images')
                ->group('general')
                ->showIf('add_img_title', 'on')
                ->note(
                    'Mẫu sinh nội dung title cho hình ảnh (hỗ trợ %title%, %count(title)%).',
                    'Nhập định dạng bạn mong muốn xuất hiện khi di chuột.',
                    'Tooltip ảnh hiển thị nội dung theo đúng công thức.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getBreadcrumbsFields(): array
    {
        return [
            Field::make('breadcrumbs', 'toggle', 'Kích hoạt chức năng Breadcrumbs (Đường dẫn điều hướng)')
                ->default('off')
                ->tab('breadcrumbs')
                ->group('general')
                ->note(
                    'Bật tính năng tạo thanh điều hướng phân cấp Breadcrumbs cho website.',
                    'Bật nếu giao diện chưa có thanh breadcrumbs sẵn hoặc bạn muốn dùng chuẩn breadcrumb của theme.',
                    'Thanh breadcrumb hiển thị ở đầu các trang bài viết, danh mục, sản phẩm.'
                ),

            Field::make('breadcrumbs_separator', 'radio_inline', 'Ký tự phân cách')
                ->default('-')
                ->tab('breadcrumbs')
                ->group('general')
                ->choices(Helper::choicesSeparator())
                ->showIf('breadcrumbs', 'on')
                ->note(
                    'Biểu tượng hoặc ký tự ngăn cách giữa các cấp mục trong thanh điều hướng.',
                    'Chọn ký tự phù hợp phong cách thương hiệu (dấu gạch -, dấu gạch xiên /, dấu mũi tên »).',
                    'Ký tự phân cách hiển thị giữa các liên kết breadcrumbs.'
                ),

            Field::make('breadcrumbs_home', 'toggle', 'Hiện liên kết Trang chủ')
                ->default('on')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->note(
                    'Hiển thị mục đầu tiên trỏ về Trang chủ.',
                    'Bật để người dùng luôn có lối tắt nhanh quay lại trang chủ website.',
                    'Mục đầu tiên trên thanh breadcrumb là "Trang chủ" hoặc nhãn tuỳ biến.'
                ),

            Field::make('breadcrumbs_home_label', 'text', 'Nhãn Trang chủ')
                ->default('Home')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs_home', 'on')
                ->translatable()
                ->note(
                    'Chữ hiển thị đại diện cho trang chủ.',
                    'Nhập "Trang chủ" (tiếng Việt) hoặc "Home". Với đa ngôn ngữ, dịch trong Polylang.',
                    'Nhãn xuất hiện ở vị trí đầu tiên của đường dẫn điều hướng.'
                ),

            Field::make('breadcrumbs_home_link', 'text', 'URL Trang chủ')
                ->default(get_home_url())
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs_home', 'on')
                ->note(
                    'Đường dẫn khi click vào nhãn Trang chủ.',
                    'Mặc định là URL gốc website; đổi nếu bạn muốn trỏ sang trang cổng chào riêng.',
                    'Click vào nhãn trang chủ sẽ mở URL này.'
                ),

            Field::make('breadcrumbs_prefix', 'text', 'Tiền tố trước Breadcrumb')
                ->default('')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->translatable()
                ->note(
                    'Đoạn chữ hiển thị trước toàn bộ thanh điều hướng (ví dụ: "Bạn đang ở:").',
                    'Nhập đoạn tiền tố nếu muốn làm rõ ngữ cảnh điều hướng cho người xem.',
                    'Hiển thị trước mục Trang chủ trên thanh điều hướng.'
                ),

            Field::make('breadcrumbs_archive_format', 'variable_text', 'Định dạng trang Lưu trữ')
                ->default('Archives for %s')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->translatable()
                ->note(
                    'Mẫu chữ hiển thị cho các trang lưu trữ ngày tháng, định dạng bài (thay %s bằng tên mục).',
                    'Nhập ví dụ "Lưu trữ: %s" để hiển thị tự nhiên.',
                    'Breadcrumb trang lưu trữ hiển thị theo mẫu.'
                ),

            Field::make('breadcrumbs_search_format', 'variable_text', 'Định dạng trang Tìm kiếm')
                ->default('Results for %s')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->translatable()
                ->note(
                    'Mẫu chữ hiển thị cho trang kết quả tìm kiếm (thay %s bằng từ khoá tìm kiếm).',
                    'Nhập "Kết quả tìm kiếm cho \'%s\'".',
                    'Breadcrumb trang tìm kiếm hiển thị từ khoá khách hàng nhập.'
                ),

            Field::make('breadcrumbs_404_label', 'text', 'Nhãn trang lỗi 404')
                ->default('404 Error: page not found')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->translatable()
                ->note(
                    'Chữ hiển thị ở mục cuối khi người dùng truy cập trang không tồn tại.',
                    'Nhập "Lỗi 404: Không tìm thấy trang" hoặc câu thông báo tương tự.',
                    'Breadcrumb trang 404 hiển thị nhãn này.'
                ),

            Field::make('breadcrumbs_remove_post_title', 'toggle', 'Ẩn tiêu đề bài viết hiện tại')
                ->default('off')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->note(
                    'Ẩn tên bài viết ở cuối thanh breadcrumb để tránh lặp lại tiêu đề H1 ngay bên dưới.',
                    'Bật nếu tiêu đề bài quá dài khiến thanh điều hướng bị rớt dòng trên điện thoại.',
                    'Thanh breadcrumb chỉ dừng lại ở chuyên mục cha của bài viết.'
                ),

            Field::make('breadcrumbs_ancestor_categories', 'toggle', 'Hiện toàn bộ danh mục cha con')
                ->default('off')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->note(
                    'Hiển thị đầy đủ cây thư mục phân cấp cha › con trước khi đến bài viết.',
                    'Bật khi bạn có cấu trúc danh mục nhiều tầng muốn người xem hiểu rõ ngữ cảnh.',
                    'Thanh breadcrumb xuất hiện chuỗi: Trang chủ › Danh mục Cha › Danh mục Con › Bài viết.'
                ),

            Field::make('breadcrumbs_hide_taxonomy_name', 'toggle', 'Ẩn tiền tố Taxonomy')
                ->default('off')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->note(
                    'Ẩn chữ phân loại như "Chuyên mục:", "Thẻ:" trước tên term.',
                    'Bật để breadcrumb sạch sẽ, ngắn gọn hơn.',
                    'Chỉ hiện tên term (ví dụ "Cà phê Robusta" thay vì "Chuyên mục: Cà phê Robusta").'
                ),

            Field::make('breadcrumbs_blog_page', 'toggle', 'Hiện mục Trang Tin tức (Blog Page)')
                ->default('off')
                ->tab('breadcrumbs')
                ->group('general')
                ->showIf('breadcrumbs', 'on')
                ->note(
                    'Chèn liên kết trang Blog/Tin tức vào giữa Trang chủ và bài viết chi tiết.',
                    'Bật nếu bạn cài đặt Trang chủ tĩnh và có 1 trang riêng cho danh sách bài viết.',
                    'Đường dẫn sẽ có thêm mục: Trang chủ › Tin tức › Chuyên mục › Bài viết.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getWebmasterFields(): array
    {
        return [
            Field::make('google_verify', 'text', 'Google Search Console')
                ->default('')
                ->tab('webmaster')
                ->group('general')
                ->note(
                    'Mã xác minh quyền sở hữu trang web với Google Search Console.',
                    'Dán mã HTML code hoặc mã định danh từ Search Console vào đây (ví dụ: abcd1234efgh5678).',
                    'Kiểm tra view-source trang chủ sẽ thấy thẻ <meta name="google-site-verification" content="...">.'
                ),

            Field::make('bing_verify', 'text', 'Bing Webmaster Tools')
                ->default('')
                ->tab('webmaster')
                ->group('general')
                ->note(
                    'Mã xác thực với công cụ tìm kiếm Bing và Yahoo.',
                    'Lấy mã xác minh tại Bing Webmaster Tools và dán vào ô này.',
                    'Thẻ <meta name="msvalidate.01" content="..."> được in vào <head> trang chủ.'
                ),

            Field::make('baidu_verify', 'text', 'Baidu Webmaster Tools')
                ->default('')
                ->tab('webmaster')
                ->group('general')
                ->note(
                    'Mã xác minh với công cụ tìm kiếm Baidu (thị trường Trung Quốc).',
                    'Dán mã do Baidu cung cấp nếu website phục vụ khách hàng thị trường Hoa ngữ.',
                    'Thẻ <meta name="baidu-site-verification" content="..."> xuất hiện ở trang chủ.'
                ),

            Field::make('yandex_verify', 'text', 'Yandex Verification ID')
                ->default('')
                ->tab('webmaster')
                ->group('general')
                ->note(
                    'Mã xác minh với công cụ tìm kiếm Yandex (Nga và Đông Âu).',
                    'Lấy mã từ trang Yandex.Webmaster và dán vào đây.',
                    'Thẻ <meta name="yandex-verification" content="..."> xuất hiện ở trang chủ.'
                ),

            Field::make('pinterest_verify', 'text', 'Pinterest Verification ID')
                ->default('')
                ->tab('webmaster')
                ->group('general')
                ->note(
                    'Xác minh tài khoản doanh nghiệp Pinterest Claim Website.',
                    'Dán mã xác nhận từ Pinterest vào đây để mở khoá tính năng thống kê Rich Pins.',
                    'Thẻ <meta name="p:domain_verify" content="..."> xuất hiện ở trang chủ.'
                ),

            Field::make('norton_verify', 'text', 'Norton Safe Web Verification ID')
                ->default('')
                ->tab('webmaster')
                ->group('general')
                ->note(
                    'Xác minh độ an toàn của website với tổ chức bảo mật Norton.',
                    'Dán mã xác nhận quyền sở hữu từ Norton Safe Web.',
                    'Thẻ <meta name="norton-safeweb-site-verification" content="..."> xuất hiện ở trang chủ.'
                ),

            Field::make('custom_webmaster_tags', 'textarea', 'Thẻ Meta xác minh tuỳ biến khác')
                ->default('')
                ->tab('webmaster')
                ->group('general')
                ->note(
                    'Bổ sung các thẻ meta xác minh khác chưa có trong danh sách trên (mỗi dòng một thẻ).',
                    'Nhập toàn bộ thẻ dạng <meta name="..." content="...">.',
                    'Các thẻ meta tuỳ biến sẽ được in nguyên văn vào phần <head> trang chủ.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getAnalyticsFields(): array
    {
        return [
            Field::make('ga4_measurement_id', 'text', 'Google Analytics 4 Measurement ID')
                ->default('')
                ->tab('analytics')
                ->group('general')
                ->note(
                    'Mã đo lường GA4 (dạng G-XXXXXXXXXX) để theo dõi lưu lượng truy cập.',
                    'Dán mã đo lường lấy từ Google Analytics › Luồng dữ liệu (Data Stream).',
                    'Mã theo dõi gtag.js tự động chèn an toàn vào <head> frontend.'
                ),

            Field::make('gtm_container_id', 'text', 'Google Tag Manager Container ID')
                ->default('')
                ->tab('analytics')
                ->group('general')
                ->note(
                    'Mã bộ chứa Google Tag Manager (dạng GTM-XXXXXXX).',
                    'Dán mã GTM nếu bạn quản lý mã đo lường qua Tag Manager thay vì GA4 trực tiếp.',
                    'Script GTM được chèn vào <head> và thẻ noscript chèn vào đầu <body>.'
                ),

            Field::make('ga_anonymize_ip', 'toggle', 'Ẩn danh hoá địa chỉ IP (Anonymize IP)')
                ->default('on')
                ->tab('analytics')
                ->group('general')
                ->note(
                    'Ẩn danh địa chỉ IP của khách truy cập để tuân thủ quyền riêng tư (chuẩn GDPR/quốc tế).',
                    'Bật tuỳ chọn để tăng tính bảo mật thông tin và tuân thủ luật bảo vệ dữ liệu.',
                    'Gtag gửi thông số anonymize_ip: true tới máy chủ Google Analytics.'
                ),

            Field::make('ga_exclude_loggedin', 'toggle', 'Bỏ qua người dùng Quản trị đã đăng nhập')
                ->default('on')
                ->tab('analytics')
                ->group('general')
                ->note(
                    'Không ghi nhận lượt truy cập của thành viên ban quản trị để số liệu thống kê không bị ảo.',
                    'Bật để mọi thao tác duyệt web, kiểm tra giao diện của admin không bị tính vào view thật.',
                    'Khi bạn đang đăng nhập quản trị viên, mã theo dõi Google Analytics sẽ không xuất hiện.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getRobotsTxtFields(): array
    {
        return [
            Field::make('robots_txt_content', 'code', 'Nội dung tệp robots.txt')
                ->default('')
                ->tab('robots')
                ->group('general')
                ->sanitizeWith(function($content) {
                    $clean = (string) $content;
                    // Remove non-printable control chars except \r, \n, \t
                    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
                    // Limit max size to 50KB
                    if (strlen($clean) > 50 * 1024) {
                        $clean = substr($clean, 0, 50 * 1024);
                    }
                    return $clean;
                })
                ->note(
                    'Quy định chỉ dẫn cho các bọ tìm kiếm (Googlebot, Bingbot...) về các khu vực được phép thu thập.',
                    'Chỉnh sửa nội dung chỉ thị robots.txt ảo do theme sinh ra; có nút khôi phục mặc định an toàn.',
                    'Kiểm tra tại URL: /robots.txt để xem nội dung thực tế.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getHtaccessFields(): array
    {
        return [
            Field::make('htaccess_content', 'code', 'Nội dung tệp máy chủ .htaccess')
                ->default('')
                ->tab('htaccess')
                ->group('general')
                ->note(
                    'Tệp cấu hình máy chủ web Apache/LiteSpeed điều khiển rewrite URL, nén, bảo mật.',
                    'Chỉ Super User có quyền chỉnh sửa. Hệ thống tự động tạo tệp sao lưu trước mỗi lần lưu.',
                    'Tệp .htaccess tại thư mục gốc máy chủ sẽ được cập nhật.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getWooCommerceFields(): array
    {
        return [
            Field::make('wc_remove_product_base', 'toggle', 'Bỏ tiền tố /product/ hoặc /shop/ khỏi URL sản phẩm')
                ->default('off')
                ->tab('woocommerce')
                ->group('general')
                ->note(
                    'Rút gọn URL sản phẩm, chuyển /product/ca-phe-robusta thành /ca-phe-robusta.',
                    'Bật nếu muốn đường dẫn sản phẩm ngắn gọn, sang trọng. Lưu ý cần tránh trùng slug với Trang tĩnh.',
                    'Đường dẫn sản phẩm trên thanh địa chỉ không còn tiền tố /product/.'
                ),

            Field::make('wc_remove_category_base', 'toggle', 'Bỏ tiền tố /product-category/ khỏi danh mục')
                ->default('off')
                ->tab('woocommerce')
                ->group('general')
                ->note(
                    'Rút gọn URL danh mục sản phẩm, chuyển /product-category/ca-phe thành /ca-phe.',
                    'Bật để tối ưu độ sâu của URL trong mắt Googlebot.',
                    'URL danh mục sản phẩm ngắn gọn và thân thiện hơn.'
                ),

            Field::make('wc_remove_category_parent_slugs', 'toggle', 'Bỏ slug danh mục cha khỏi danh mục con')
                ->default('off')
                ->tab('woocommerce')
                ->group('general')
                ->note(
                    'Chỉ giữ lại slug danh mục con trực tiếp thay vì lồng ghép danh mục cha trong URL.',
                    'Bật nếu bạn muốn URL danh mục luôn ở cấp 1 (/ca-phe-hat thay vì /san-pham/ca-phe/ca-phe-hat).',
                    'Đường dẫn danh mục con trở nên ngắn nhất có thể.'
                ),

            Field::make('wc_remove_generator', 'toggle', 'Xoá thẻ meta generator WooCommerce')
                ->default('on')
                ->tab('woocommerce')
                ->group('general')
                ->note(
                    'Ẩn số phiên bản WooCommerce đang sử dụng trong mã nguồn HTML.',
                    'Bật để ngăn chặn tin tặc quét các lỗ hổng đã biết của phiên bản plugin cũ.',
                    'Mã nguồn không còn xuất hiện thẻ <meta name="generator" content="WooCommerce...">'
                ),

            Field::make('remove_shop_snippet_data', 'toggle', 'Gỡ bỏ Schema Markup trên trang Cửa hàng (Shop)')
                ->default('on')
                ->tab('woocommerce')
                ->group('general')
                ->note(
                    'Ngăn WooCommerce sinh Schema Product chung chung trên trang lưu trữ danh sách sản phẩm.',
                    'Bật để tránh bị cảnh báo lỗi Schema trong Google Search Console do trang lưu trữ thiếu giá bán cụ thể.',
                    'Trang Shop chỉ còn Schema WebPage/CollectionPage chuẩn xác.'
                ),

            Field::make('product_brand', 'select', 'Taxonomy đại diện cho Thương hiệu (Brand)')
                ->default('')
                ->tab('woocommerce')
                ->group('general')
                ->choices([
                    '' => __('— Chọn phân loại thương hiệu —', 'lacadev'),
                    'product_brand' => __('Product Brand (product_brand)', 'lacadev'),
                ])
                ->note(
                    'Chỉ định taxonomy nào trên website đang lưu tên thương hiệu/nhà sản xuất của sản phẩm.',
                    'Chọn taxonomy phù hợp (ví dụ product_brand) để hệ thống tự lấy điền vào Schema JSON-LD.',
                    'Thuộc tính brand trong Schema Product sẽ được tự động điền giá trị chuẩn.'
                ),

            Field::make('gtin', 'select', 'Mã định danh sản phẩm toàn cầu (GTIN)')
                ->default('gtin')
                ->tab('woocommerce')
                ->group('general')
                ->choices([
                    'gtin'   => __('GTIN', 'lacadev'),
                    'gtin8'  => __('GTIN-8', 'lacadev'),
                    'gtin12' => __('GTIN-12 (UPC)', 'lacadev'),
                    'gtin13' => __('GTIN-13 (EAN)', 'lacadev'),
                    'gtin14' => __('GTIN-14 (ITF-14)', 'lacadev'),
                    'isbn'   => __('ISBN (Sách)', 'lacadev'),
                    'mpn'    => __('MPN (Mã phụ tùng)', 'lacadev'),
                ])
                ->note(
                    'Chọn chuẩn mã vạch hoặc mã nhận diện sản phẩm sử dụng trong Schema.',
                    'Chọn loại GTIN phù hợp với dữ liệu sản phẩm của bạn (mặc định gtin).',
                    'Dữ liệu mã GTIN trong Schema Product sẽ được gán đúng thuộc tính Schema.org.'
                ),

            Field::make('show_gtin', 'toggle', 'Hiển thị GTIN trên trang sản phẩm')
                ->default('off')
                ->tab('woocommerce')
                ->group('general')
                ->note(
                    'Tự động hiển thị mã GTIN trong khối thông tin meta của sản phẩm (cạnh SKU).',
                    'Bật nếu bạn muốn hiển thị mã vạch/mã GTIN công khai cho khách hàng xem.',
                    'Nhãn GTIN và giá trị sẽ xuất hiện trong phần thông tin chi tiết sản phẩm.'
                ),

            Field::make('gtin_label', 'text', 'Nhãn hiển thị GTIN')
                ->default('GTIN:')
                ->tab('woocommerce')
                ->group('general')
                ->showIf('show_gtin', 'on')
                ->note(
                    'Tiền tố văn bản hiển thị trước mã GTIN trên trang sản phẩm.',
                    'Nhập nhãn tùy chỉnh (ví dụ: "Mã vạch:" hoặc "GTIN:").',
                    'Nhãn hiển thị trước giá trị mã GTIN.'
                ),

            Field::make('noindex_hidden_products', 'toggle', 'Đặt Noindex cho sản phẩm ẩn danh mục (Hidden Products)')
                ->default('on')
                ->tab('woocommerce')
                ->group('general')
                ->note(
                    'Tự động gắn thẻ robots noindex và loại khỏi XML Sitemap cho các sản phẩm có hiển thị danh mục là "Ẩn" (Hidden).',
                    'Bật để ngăn công cụ tìm kiếm lập chỉ mục các sản phẩm không hiển thị công khai trên gian hàng.',
                    'Sản phẩm ẩn sẽ có thẻ meta robots: noindex, nofollow.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getBlocksFields(): array
    {
        return [
            Field::make('toc_block_title', 'text', 'Tiêu đề khối Mục lục (Table of Contents)')
                ->default('Mục lục nội dung')
                ->tab('blocks')
                ->group('general')
                ->translatable()
                ->note(
                    'Tiêu đề mặc định hiển thị ở đầu khối Mục lục bài viết.',
                    'Nhập tiêu đề bạn mong muốn (ví dụ "Nội dung chính bài viết" hoặc "Mục lục").',
                    'Khối mục lục trong bài viết hiển thị tiêu đề này.'
                ),

            Field::make('toc_block_list_style', 'select', 'Kiểu danh sách mục lục')
                ->default('ul')
                ->tab('blocks')
                ->group('general')
                ->choices([
                    'ul'  => __('Danh sách dấu chấm (Unordered List)', 'lacadev'),
                    'ol'  => __('Danh sách đánh số thứ tự (Numbered List)', 'lacadev'),
                    'div' => __('Không dùng dấu chấm / số (Plain)', 'lacadev'),
                ])
                ->note(
                    'Kiểu định dạng thẻ danh sách hiển thị các mục con.',
                    'Chọn danh sách có thứ tự (1, 2, 3...) hoặc gạch đầu dòng.',
                    'Mục lục hiển thị theo đúng thẻ HTML tương ứng.'
                ),

            Field::make('toc_block_exclude_headings', 'multicheck', 'Bỏ qua các thẻ tiêu đề (Exclude Headings)')
                ->default([])
                ->tab('blocks')
                ->group('general')
                ->choices([
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                ])
                ->note(
                    'Chọn các cấp thẻ tiêu đề không muốn đưa vào mục lục (thông thường bỏ H1 và H5, H6).',
                    'Đánh dấu vào các thẻ cần loại bỏ để mục lục bài viết không bị quá dài.',
                    'Các thẻ được chọn sẽ không xuất hiện trong bảng mục lục tự động.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getOthersFields(): array
    {
        return [
            Field::make('headless_support', 'toggle', 'Hỗ trợ Headless CMS (REST API Meta)')
                ->default('off')
                ->tab('others')
                ->group('general')
                ->note(
                    'Bổ sung trường dữ liệu SEO đầy đủ vào API endpoint của WordPress REST API.',
                    'Bật nếu website của bạn dùng frontend rời như Next.js, Nuxt hoặc mobile app.',
                    'API REST trả về trường laca_seo_meta chứa toàn bộ title, description, og, schema.'
                ),

            Field::make('rss_before_content', 'textarea', 'Nội dung chèn trước bài viết trong nguồn RSS Feed')
                ->default('')
                ->tab('others')
                ->group('general')
                ->note(
                    'Đoạn văn bản hoặc HTML xuất hiện ở đầu mỗi bài trong RSS feed (hỗ trợ biến %AUTHORLINK%, %POSTLINK%).',
                    'Nhập thông điệp bản quyền hoặc lời chào cho độc giả theo dõi qua RSS Reader.',
                    'Người đọc RSS sẽ thấy nội dung này trước bài viết.'
                ),

            Field::make('rss_after_content', 'textarea', 'Nội dung chèn sau bài viết trong nguồn RSS Feed')
                ->default('')
                ->tab('others')
                ->group('general')
                ->note(
                    'Đoạn chữ chèn cuối mỗi bài trong RSS để chống sao chép và tạo backlink tự nhiên khi bị cào bài.',
                    'Ví dụ: "Bài viết %POSTLINK% xuất bản lần đầu tại %BLOGDESCLINK%."',
                    'Bảo vệ bản quyền bài viết khi các trang tin tự động lấy nguồn RSS của bạn.'
                ),

            Field::make('frontend_seo_score', 'toggle', 'Hiển thị huy hiệu điểm SEO ngoài Frontend')
                ->default('off')
                ->tab('others')
                ->group('general')
                ->note(
                    'Tự động hiển thị huy hiệu điểm SEO trên các bài viết ngoài giao diện người dùng.',
                    'Bật nếu bạn muốn công khai điểm số SEO của bài viết cho độc giả hoặc học viên.',
                    'Huy hiệu điểm SEO xuất hiện ở vị trí đã chọn trên bài viết.'
                ),

            Field::make('frontend_seo_score_post_types', 'multicheck', 'Loại nội dung hiển thị điểm SEO')
                ->default(['post'])
                ->tab('others')
                ->group('general')
                ->choices(Helper::choicesPostTypes())
                ->showIf('frontend_seo_score', 'on')
                ->note(
                    'Chọn các loại bài viết được phép hiển thị huy hiệu điểm SEO.',
                    'Tích chọn Post, Page hoặc Sản phẩm.',
                    'Chỉ các post type được chọn mới tự động chèn điểm SEO.'
                ),

            Field::make('frontend_seo_score_position', 'select', 'Vị trí hiển thị huy hiệu điểm SEO')
                ->default('bottom')
                ->tab('others')
                ->group('general')
                ->choices([
                    'top'    => __('Đầu bài viết (Above Content)', 'lacadev'),
                    'bottom' => __('Cuối bài viết (Below Content)', 'lacadev'),
                    'both'   => __('Cả đầu và cuối (Both)', 'lacadev'),
                    'custom' => __('Thủ công bằng Shortcode [laca_seo_score]', 'lacadev'),
                ])
                ->showIf('frontend_seo_score', 'on')
                ->note(
                    'Vị trí tự động chèn huy hiệu điểm SEO vào nội dung bài viết.',
                    'Chọn chèn ở đầu bài, cuối bài hoặc chỉ dùng shortcode [laca_seo_score].',
                    'Điểm SEO hiển thị tại vị trí đã thiết lập.'
                ),

            Field::make('frontend_seo_score_template', 'select', 'Kiểu hiển thị huy hiệu điểm SEO')
                ->default('circle')
                ->tab('others')
                ->group('general')
                ->choices([
                    'circle' => __('Vòng tròn (Circle)', 'lacadev'),
                    'square' => __('Hình vuông / Thẻ (Square)', 'lacadev'),
                ])
                ->showIf('frontend_seo_score', 'on')
                ->note(
                    'Mẫu giao diện huy hiệu điểm SEO hiển thị ngoài frontend.',
                    'Chọn kiểu vòng tròn hoặc kiểu thẻ vuông.',
                    'Huy hiệu xuất hiện theo kiểu dáng tương ứng.'
                ),
        ];
    }
}
