<?php

namespace App\Features\LacaSeo\Guide;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Provides static content and live status checks for the SEO Optimization Guide screen.
 */
class GuideData
{
    /**
     * Get dynamic checklist of SEO health items.
     *
     * @return array<int, array{id:string,title:string,status:bool,desc:string,action_url:string,action_label:string}>
     */
    public static function getChecklist(): array
    {
        $blog_public = (bool) get_option('blog_public');
        $site_url = get_home_url();
        $is_https = str_starts_with($site_url, 'https://');
        $permalink_structure = get_option('permalink_structure');
        $has_clean_permalinks = !empty($permalink_structure) && str_contains($permalink_structure, '%postname%');

        $org_name = trim((string) Settings::get('titles.knowledgegraph_name', ''));
        $org_logo = Settings::get('titles.knowledgegraph_logo', '');
        $has_org_info = !empty($org_name) && !empty($org_logo);

        $homepage_title = trim((string) Settings::get('titles.homepage_title', ''));
        $homepage_desc = trim((string) Settings::get('titles.homepage_description', ''));
        $has_homepage_meta = !empty($homepage_title) && !empty($homepage_desc);

        $google_verify = trim((string) Settings::get('general.google_verify', ''));
        $has_search_console = !empty($google_verify);

        $ga4_id = trim((string) Settings::get('general.ga4_measurement_id', ''));
        $gtm_id = trim((string) Settings::get('general.gtm_container_id', ''));
        $has_analytics = !empty($ga4_id) || !empty($gtm_id);

        $disable_author = Settings::get('titles.disable_author_archives', 'off') === 'on';
        $disable_date = Settings::get('titles.disable_date_archives', 'on') === 'on';
        $thin_content_secured = $disable_author && $disable_date;

        return [
            [
                'id'           => 'blog_public',
                'title'        => 'Cho phép công cụ tìm kiếm thu thập dữ liệu (Search Engine Visibility)',
                'status'       => $blog_public,
                'desc'         => $blog_public ? 'Website đang ở chế độ công khai cho Google, Bing thu thập.' : 'Cảnh báo: Website đang chặn Googlebot (blog_public = 0).',
                'action_url'   => admin_url('options-reading.php'),
                'action_label' => 'Mở Cài đặt Đọc',
            ],
            [
                'id'           => 'https',
                'title'        => 'Chứng chỉ bảo mật HTTPS / SSL',
                'status'       => $is_https,
                'desc'         => $is_https ? 'Website đang chạy an toàn với giao thức HTTPS.' : 'Website chưa dùng HTTPS. Google ưu tiên các trang có SSL.',
                'action_url'   => admin_url('options-general.php'),
                'action_label' => 'Cài đặt Tổng quan',
            ],
            [
                'id'           => 'permalinks',
                'title'        => 'Đường dẫn tĩnh thân thiện (Clean Permalinks /%postname%/)',
                'status'       => $has_clean_permalinks,
                'desc'         => $has_clean_permalinks ? 'Cấu trúc URL chứa tiêu đề bài viết chuẩn SEO.' : 'Đường dẫn chưa tối ưu. Khuyên dùng cấu trúc Tiêu đề bài viết (%postname%).',
                'action_url'   => admin_url('options-permalink.php'),
                'action_label' => 'Cài đặt Đường dẫn tĩnh',
            ],
            [
                'id'           => 'knowledge_graph',
                'title'        => 'Khai báo Tổ chức / Doanh nghiệp & Logo (Knowledge Graph)',
                'status'       => $has_org_info,
                'desc'         => $has_org_info ? 'Đã thiết lập tên tổ chức và logo cho sơ đồ tri thức Google.' : 'Chưa nhập đầy đủ tên tổ chức hoặc tải logo thương hiệu.',
                'action_url'   => add_query_arg(['page' => 'laca-seo', 'view' => 'titles', 'tab' => 'local'], admin_url('admin.php')),
                'action_label' => 'Mở Local SEO',
            ],
            [
                'id'           => 'homepage_meta',
                'title'        => 'Tiêu đề và Mô tả SEO Trang chủ',
                'status'       => $has_homepage_meta,
                'desc'         => $has_homepage_meta ? 'Trang chủ đã có đầy đủ thẻ tiêu đề và đoạn trích mô tả.' : 'Trang chủ còn thiếu tiêu đề hoặc đoạn văn mô tả meta.',
                'action_url'   => add_query_arg(['page' => 'laca-seo', 'view' => 'titles', 'tab' => 'homepage'], admin_url('admin.php')),
                'action_label' => 'Mở Trang chủ',
            ],
            [
                'id'           => 'thin_content',
                'title'        => 'Chống trùng lặp nội dung mỏng (Tắt Author & Date Archives)',
                'status'       => $thin_content_secured,
                'desc'         => $thin_content_secured ? 'Đã tắt trang tác giả và ngày tháng để dồn sức mạnh SEO.' : 'Nên tắt trang lưu trữ tác giả và ngày tháng để tránh bị phạt lỗi thin content.',
                'action_url'   => add_query_arg(['page' => 'laca-seo', 'view' => 'titles', 'tab' => 'authors'], admin_url('admin.php')),
                'action_label' => 'Cấu hình Tác giả & Ngày',
            ],
            [
                'id'           => 'search_console',
                'title'        => 'Kết nối Google Search Console',
                'status'       => $has_search_console,
                'desc'         => $has_search_console ? 'Đã gắn mã xác thực Google Search Console.' : 'Chưa nhập mã xác minh Google Search Console.',
                'action_url'   => add_query_arg(['page' => 'laca-seo', 'view' => 'general', 'tab' => 'webmaster'], admin_url('admin.php')),
                'action_label' => 'Dán mã xác minh',
            ],
            [
                'id'           => 'analytics',
                'title'        => 'Theo dõi lưu lượng với Google Analytics / GTM',
                'status'       => $has_analytics,
                'desc'         => $has_analytics ? 'Đã tích hợp mã đo lường lưu lượng khách truy cập.' : 'Chưa cấu hình Google Analytics 4 hoặc Tag Manager.',
                'action_url'   => add_query_arg(['page' => 'laca-seo', 'view' => 'general', 'tab' => 'analytics'], admin_url('admin.php')),
                'action_label' => 'Mở Analytics',
            ],
        ];
    }

    /**
     * Get guide articles list for the SEO Optimization Handbook.
     *
     * @return array<int, array{id:string,title:string,why:string,what:string,verify:string,deep_link:string}>
     */
    public static function getHandbook(): array
    {
        return [
            [
                'id'        => 'step1',
                'title'     => '1. Thiết lập nền tảng kỹ thuật trước khi SEO',
                'why'       => 'Google cần truy cập mượt mà, đường dẫn rõ nghĩa và kết nối bảo mật trước khi có thể xếp hạng bất kỳ bài viết nào.',
                'what'      => 'Đảm bảo bật HTTPS, kiểm tra Cài đặt Đọc không chặn bot, và đặt cấu trúc permalinks là /%postname%/.',
                'verify'    => 'Truy cập thử trang bằng trình duyệt ẩn danh, xem thanh địa chỉ có biểu tượng ổ khoá an toàn.',
                'deep_link' => admin_url('options-reading.php'),
            ],
            [
                'id'        => 'step2',
                'title'     => '2. Khai báo Sơ đồ Tri thức (Knowledge Graph) & Logo thương hiệu',
                'why'       => 'Giúp Google định danh chính xác pháp nhân doanh nghiệp, tăng độ tin cậy E-E-A-T và hiển thị bảng thông tin bên phải kết quả tìm kiếm.',
                'what'      => 'Vào Tiêu đề & Thẻ Meta › Local SEO: Chọn Doanh nghiệp, nhập tên công ty, số điện thoại, địa chỉ và tải lên logo sắc nét vuông (512x512).',
                'verify'    => 'Kiểm tra bằng công cụ Google Rich Results Test đối với trang chủ.',
                'deep_link' => add_query_arg(['page' => 'laca-seo', 'view' => 'titles', 'tab' => 'local'], admin_url('admin.php')),
            ],
            [
                'id'        => 'step3',
                'title'     => '3. Tối ưu Tiêu đề (Title) & Thẻ mô tả (Meta Description)',
                'why'       => 'Tiêu đề là yếu tố xếp hạng On-Page quan trọng nhất; thẻ mô tả quyết định tỷ lệ khách hàng click vào trang web của bạn.',
                'what'      => 'Viết tiêu đề dài 50-60 ký tự chứa từ khoá chính ở đầu. Viết mô tả 140-160 ký tự kèm lời kêu gọi hành động hấp dẫn.',
                'verify'    => 'Xem SERP preview trong trang soạn thảo bài viết hoặc view-source kiểm tra thẻ <title> và <meta name="description">.',
                'deep_link' => add_query_arg(['page' => 'laca-seo', 'view' => 'titles', 'tab' => 'global'], admin_url('admin.php')),
            ],
            [
                'id'        => 'step4',
                'title'     => '4. Kiểm soát chỉ mục Robots & Triệt tiêu nội dung mỏng',
                'why'       => 'Các trang tag rỗng, kết quả tìm kiếm nội bộ, lưu trữ tác giả 1 người hoặc lưu trữ theo ngày làm lãng phí ngân sách thu thập dữ liệu (crawl budget) của Google.',
                'what'      => 'Bật Noindex tìm kiếm nội bộ, Noindex tag rỗng, và vô hiệu hoá Author Archives, Date Archives.',
                'verify'    => 'Truy cập thử /author/... hoặc /2026/10/ để thấy trang tự động chuyển hướng 301 về trang chủ.',
                'deep_link' => add_query_arg(['page' => 'laca-seo', 'view' => 'titles', 'tab' => 'misc'], admin_url('admin.php')),
            ],
            [
                'id'        => 'step5',
                'title'     => '5. Tối ưu hình ảnh chia sẻ Mạng xã hội (OpenGraph & Twitter Cards)',
                'why'       => 'Khi khách hàng chia sẻ link qua Facebook, Zalo hay Twitter, một tấm ảnh thu nhỏ chuyên nghiệp sẽ thu hút lượt tương tác gấp 3 lần.',
                'what'      => 'Thiết lập ảnh đại diện mặc định kích thước 1200x630px trong tab Global Meta; bài viết chi tiết luôn đặt Ảnh đại diện (Featured Image).',
                'verify'    => 'Dán link trang vào công cụ Facebook Sharing Debugger để xem trước hình ảnh hiển thị.',
                'deep_link' => add_query_arg(['page' => 'laca-seo', 'view' => 'titles', 'tab' => 'global'], admin_url('admin.php')),
            ],
            [
                'id'        => 'step6',
                'title'     => '6. Đa ngôn ngữ (Polylang)',
                'why'       => 'Với website hỗ trợ nhiều ngôn ngữ (như Tiếng Việt và Tiếng Anh), các thẻ tiêu đề và mô tả cần được bản địa hoá chuẩn xác.',
                'what'      => 'Truy cập menu Ngôn ngữ › Dịch chuỗi, lọc nhóm "Laca SEO" để nhập bản dịch cho từng ngôn ngữ.',
                'verify'    => 'Chuyển đổi cờ ngôn ngữ trên trang chủ và xem mã nguồn thẻ title, description thay đổi tương ứng.',
                'deep_link' => admin_url('admin.php?page=mlang_strings&group=Laca+SEO'),
            ],
        ];
    }
}
