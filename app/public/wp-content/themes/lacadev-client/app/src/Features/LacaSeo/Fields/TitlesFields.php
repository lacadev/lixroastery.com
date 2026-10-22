<?php

namespace App\Features\LacaSeo\Fields;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Registry of all Titles & Meta fields (Global, Local, Social, Homepage, Authors, Misc, Post Types, Taxonomies).
 */
class TitlesFields
{
    /**
     * @return array<string, Field[]> Map of tab slug => array of Field objects
     */
    public static function get(): array
    {
        $tabs = [
            'global'   => self::getGlobalFields(),
            'local'    => self::getLocalFields(),
            'social'   => self::getSocialFields(),
            'homepage' => self::getHomepageFields(),
            'authors'  => self::getAuthorsFields(),
            'misc'     => self::getMiscFields(),
        ];

        // Dynamic Post Types tabs
        $post_types = Helper::getAccessiblePostTypes();
        foreach ($post_types as $pt_slug => $pt_obj) {
            $tabs['pt_' . $pt_slug] = self::getPostTypeFields($pt_slug, $pt_obj);
        }

        // Dynamic Taxonomies tabs
        $taxonomies = Helper::getAccessibleTaxonomies();
        foreach ($taxonomies as $tax_slug => $tax_obj) {
            $tabs['tax_' . $tax_slug] = self::getTaxonomyFields($tax_slug, $tax_obj);
        }

        return $tabs;
    }

    /**
     * @return Field[]
     */
    private static function getGlobalFields(): array
    {
        return [
            Field::make('robots_global', 'multicheck', 'Thẻ Robots Meta mặc định toàn site')
                ->default(['index'])
                ->tab('global')
                ->group('titles')
                ->choices(Helper::choicesRobots())
                ->note(
                    'Chỉ thị mặc định cho bot tìm kiếm áp dụng trên toàn bộ trang chưa có cấu hình riêng.',
                    'Khuyên dùng: Chọn "Index" để website được hiển thị trên Google; KHÔNG chọn "Noindex" trừ khi đang trong giai đoạn phát triển.',
                    'Xem mã nguồn bất kỳ trang nào sẽ thấy thẻ <meta name="robots" content="index, follow">.'
                ),

            Field::make('advanced_robots_global', 'advanced_robots', 'Advanced Robots Meta toàn site')
                ->default([
                    'max-snippet'       => -1,
                    'max-video-preview' => -1,
                    'max-image-preview' => 'large',
                ])
                ->tab('global')
                ->group('titles')
                ->note(
                    'Kiểm soát độ dài đoạn trích, thời lượng xem trước video và kích thước ảnh thu nhỏ trên Google.',
                    'Mặc định max-snippet:-1 (không giới hạn), max-image-preview:large để hình ảnh sản phẩm nổi bật trên Google Discover.',
                    'Thẻ robots xuất hiện max-snippet:-1, max-image-preview:large.'
                ),

            Field::make('noindex_empty_taxonomies', 'toggle', 'Noindex danh mục và thẻ chưa có bài viết')
                ->default('on')
                ->tab('global')
                ->group('titles')
                ->note(
                    'Tự động gắn noindex cho các chuyên mục hoặc thẻ rỗng (chưa có bài viết nào).',
                    'Bật để tránh Google phạt lỗi "nội dung mỏng" (thin content). Ngay khi có bài viết đầu tiên, trang sẽ tự chuyển sang index.',
                    'Các trang chuyên mục rỗng sẽ có thẻ <meta name="robots" content="noindex">.'
                ),

            Field::make('title_separator', 'radio_inline', 'Ký tự phân cách tiêu đề trang')
                ->default('-')
                ->tab('global')
                ->group('titles')
                ->choices(Helper::choicesSeparator())
                ->note(
                    'Ký tự phân cách giữa tên bài viết và tên website trong thẻ <title>.',
                    'Chọn ký tự phù hợp (ví dụ gạch ngang - hoặc gạch đứng |). Biến %sep% trong các mẫu sẽ dùng ký tự này.',
                    'Tiêu đề trang hiển thị trên tab trình duyệt và kết quả tìm kiếm (Ví dụ: "Cà phê Robusta - Lix Roastery").'
                ),

            Field::make('rewrite_title', 'toggle', 'Ghi đè tiêu đề trang (Rewrite Titles)')
                ->default('off')
                ->tab('global')
                ->group('titles')
                ->note(
                    'Cho phép Laca SEO ghi đè thẻ <title> nếu giao diện không hỗ trợ chuẩn title-tag của WordPress.',
                    'Mặc định theme chuẩn đã hỗ trợ title-tag nên tuỳ chọn này thường để Tắt.',
                    'Thẻ <title> được can thiệp ghi đè ở tầng output.'
                ),

            Field::make('capitalize_titles', 'toggle', 'Tự động viết hoa chữ cái đầu tiêu đề (Capitalize)')
                ->default('off')
                ->tab('global')
                ->group('titles')
                ->note(
                    'Tự động viết hoa chữ cái đầu của mỗi từ trong tiêu đề SEO khi hiển thị ra ngoài.',
                    'Thường dùng cho tiếng Anh; với tiếng Việt khuyên bạn để Tắt để tránh viết hoa sai ngữ pháp.',
                    'Tiêu đề xuất ra sẽ được tự động viết hoa từ đầu tiên.'
                ),

            Field::make('open_graph_image', 'image', 'Ảnh đại diện mặc định khi chia sẻ mạng xã hội (OpenGraph Thumbnail)')
                ->default('')
                ->tab('global')
                ->group('titles')
                ->note(
                    'Ảnh đại diện dự phòng hiển thị trên Facebook/Zalo khi bài viết hoặc trang không có ảnh đại diện riêng.',
                    'Tải lên ảnh có kích thước chuẩn 1200 x 630 pixels, bố cục hài hoà có logo thương hiệu.',
                    'Khi gửi link trang qua Facebook Messenger, Zalo sẽ xuất hiện tấm ảnh này.'
                ),

            Field::make('open_graph_image_id', 'hidden', 'ID Ảnh đại diện mặc định OpenGraph')
                ->default(0)
                ->tab('global')
                ->group('titles'),

            Field::make('twitter_card_type', 'select', 'Kiểu thẻ Twitter Card mặc định')
                ->default('summary_large_image')
                ->tab('global')
                ->group('titles')
                ->choices([
                    'summary_large_image' => __('Thẻ lớn kèm ảnh biểu ngữ lớn (Summary with Large Image)', 'lacadev'),
                    'summary_card'        => __('Thẻ nhỏ kèm ảnh vuông (Summary Card)', 'lacadev'),
                ])
                ->note(
                    'Kiểu hiển thị bài viết khi được chia sẻ lên Twitter/X.',
                    'Khuyên dùng "Summary Card with Large Image" để hình ảnh hiển thị to rõ, tăng tỷ lệ nhấp chuột.',
                    'Thẻ <meta name="twitter:card" content="summary_large_image"> được sinh trong <head>.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getLocalFields(): array
    {
        return [
            Field::make('knowledgegraph_type', 'radio_inline', 'Loại hình đại diện (Person hoặc Organization)')
                ->default('company')
                ->tab('local')
                ->group('titles')
                ->choices([
                    'company' => __('Doanh nghiệp / Tổ chức (Organization)', 'lacadev'),
                    'person'  => __('Cá nhân / Chuyên gia (Person)', 'lacadev'),
                ])
                ->note(
                    'Xác định website đại diện cho một tổ chức doanh nghiệp hay một cá nhân/blog cá nhân.',
                    'Chọn "Doanh nghiệp / Tổ chức" cho website bán hàng, công ty, xưởng rang; chọn "Cá nhân" cho portfolio blog.',
                    'Ảnh hưởng trực tiếp đến dữ liệu có cấu trúc Knowledge Graph Schema JSON-LD gửi tới Google.'
                ),

            Field::make('website_name', 'text', 'Tên website')
                ->default(get_bloginfo('name'))
                ->tab('local')
                ->group('titles')
                ->translatable()
                ->note(
                    'Tên chính thức của trang web xuất hiện trên bảng kết quả Google Search.',
                    'Nhập tên thương hiệu chuẩn của bạn (Ví dụ: "Lix Roastery").',
                    'Google sử dụng tên này thay cho URL thô trên kết quả tìm kiếm mobile.'
                ),

            Field::make('website_alternate_name', 'text', 'Tên website thay thế (Alternate Name)')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->translatable()
                ->note(
                    'Tên viết tắt hoặc tên gọi thông dụng khác của trang web (Ví dụ: "LIX Coffee").',
                    'Nhập nếu thương hiệu của bạn có tên viết tắt phổ biến.',
                    'Khai báo thuộc tính alternateName trong WebSite Schema.'
                ),

            Field::make('knowledgegraph_name', 'text', 'Tên Doanh nghiệp / Tổ chức')
                ->default(get_bloginfo('name'))
                ->tab('local')
                ->group('titles')
                ->translatable()
                ->note(
                    'Tên pháp nhân hoặc tên công ty hiển thị trong bảng tri thức Google Knowledge Panel.',
                    'Nhập tên công ty chính xác theo giấy phép đăng ký kinh doanh hoặc thương hiệu.',
                    'Dữ liệu tổ chức trong JSON-LD Schema có name chính xác.'
                ),

            Field::make('organization_description', 'textarea', 'Mô tả tóm tắt doanh nghiệp')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->showIf('knowledgegraph_type', 'company')
                ->translatable()
                ->note(
                    'Đoạn văn ngắn giới thiệu về lịch sử, sứ mệnh và lĩnh vực kinh doanh của tổ chức.',
                    'Viết từ 2-3 câu tóm tắt trọng tâm uy tín thương hiệu.',
                    'Đoạn văn được gắn vào trường description trong Organization Schema.'
                ),

            Field::make('knowledgegraph_logo', 'image', 'Logo thương hiệu chính thức')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Hình ảnh logo đại diện cho tổ chức trong kết quả tìm kiếm và sơ đồ tri thức.',
                    'Tải lên logo vuông hoặc ngang rõ nét (tối thiểu 112 x 112 pixels, chuẩn nét 512 x 512 pixels).',
                    'Google sử dụng logo này cho kết quả tìm kiếm thương hiệu và Schema Organization.'
                ),

            Field::make('knowledgegraph_logo_id', 'hidden', 'ID Logo thương hiệu chính thức')
                ->default(0)
                ->tab('local')
                ->group('titles'),

            Field::make('url', 'text', 'URL trang chủ chính thức')
                ->default(home_url())
                ->tab('local')
                ->group('titles')
                ->note(
                    'Đường dẫn URL đại diện chính thức của doanh nghiệp.',
                    'Mặc định là trang chủ website; đảm bảo sử dụng giao thức HTTPS.',
                    'URL chính thức trong Schema Organization.'
                ),

            Field::make('email', 'text', 'Email liên hệ chính')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Hộp thư điện tử liên hệ tiếp nhận thông tin khách hàng.',
                    'Nhập email tên miền công ty (ví dụ: contact@lixroastery.com).',
                    'Trường email xuất hiện trong Schema LocalBusiness / Organization.'
                ),

            Field::make('phone', 'text', 'Số điện thoại liên hệ')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Số điện thoại hotline chính thức hiển thị cho người tìm kiếm trên điện thoại.',
                    'Nhập kèm mã quốc gia (Ví dụ: +84-901-234-567).',
                    'Người dùng Google trên di động có thể bấm nút gọi trực tiếp từ kết quả tìm kiếm.'
                ),

            Field::make('local_address', 'address', 'Địa chỉ trụ sở')
                ->default([
                    'streetAddress'   => '',
                    'addressLocality' => '',
                    'addressRegion'   => '',
                    'postalCode'      => '',
                    'addressCountry'  => 'VN',
                ])
                ->tab('local')
                ->group('titles')
                ->note(
                    'Địa chỉ vật lý của văn phòng, showroom hoặc xưởng rang.',
                    'Điền rõ ràng số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố.',
                    'Xuất hiện trong thẻ PostalAddress của Schema doanh nghiệp.'
                ),

            Field::make('local_address_format', 'textarea', 'Định dạng địa chỉ hiển thị (Address Format)')
                ->default('{address} {locality}, {region} {postalcode}')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Định dạng dùng khi hiển thị địa chỉ liên hệ của doanh nghiệp.',
                    'Các biến có sẵn: {address}, {locality}, {region}, {postalcode}, {country}, {gps}.',
                    'Chuỗi địa chỉ được format chuẩn xác theo cấu hình.'
                ),

            Field::make('local_business_type', 'select', 'Loại hình kinh doanh (Business Type)')
                ->default('Organization')
                ->tab('local')
                ->group('titles')
                ->choices(Helper::choicesBusinessTypes())
                ->note(
                    'Phân loại chi tiết ngành nghề của doanh nghiệp theo chuẩn Schema.org.',
                    'Chọn ngành nghề phù hợp nhất (Ví dụ: "CafeOrCoffeeShop", "Store", "Bakery" hoặc "Organization").',
                    'Định danh @type trong Schema JSON-LD sẽ sử dụng đúng ngành nghề bạn chọn.'
                ),

            Field::make('price_range', 'text', 'Phân khúc giá (Price Range)')
                ->default('$$')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Mức giá dịch vụ/sản phẩm của doanh nghiệp theo quy ước số ký tự tiền tệ.',
                    'Nhập "$" (bình dân), "$$" (trung cấp), "$$$" (cao cấp) hoặc "50.000đ - 300.000đ".',
                    'Trường priceRange trong Schema LocalBusiness.'
                ),

            Field::make('opening_hours_format', 'toggle', 'Định dạng giờ mở cửa 24h')
                ->default('off')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Định dạng hiển thị thời gian làm việc theo chuẩn 24 giờ hay 12 giờ sáng/chiều.',
                    'Bật nếu muốn dùng chuẩn 24:00 (ví dụ 08:00 - 22:00); tắt để dùng AM/PM.',
                    'Hiển thị trong thông tin liên hệ và Schema.'
                ),

            Field::make('opening_hours', 'group', 'Lịch giờ mở cửa theo các ngày trong tuần')
                ->default([
                    ['day' => 'Monday', 'time' => '08:00-17:00'],
                    ['day' => 'Tuesday', 'time' => '08:00-17:00'],
                    ['day' => 'Wednesday', 'time' => '08:00-17:00'],
                    ['day' => 'Thursday', 'time' => '08:00-17:00'],
                    ['day' => 'Friday', 'time' => '08:00-17:00'],
                    ['day' => 'Saturday', 'time' => '08:00-17:00'],
                    ['day' => 'Sunday', 'time' => '08:00-17:00'],
                ])
                ->tab('local')
                ->group('titles')
                ->note(
                    'Chi tiết khung giờ mở cửa đón khách của từng ngày trong tuần.',
                    'Nhập thời gian theo cú pháp: HH:MM-HH:MM (Ví dụ: 07:30-22:00).',
                    'Google hiển thị trạng thái "Đang mở cửa / Sắp đóng cửa" trên Maps và Search.'
                ),

            Field::make('phone_numbers', 'group', 'Danh sách số điện thoại hỗ trợ chuyên biệt')
                ->default([])
                ->tab('local')
                ->group('titles')
                ->note(
                    'Bổ sung nhiều số hotline phục vụ từng bộ phận (kinh doanh, kỹ thuật, khiếu nại).',
                    'Thêm từng dòng: chọn loại bộ phận và nhập số điện thoại tương ứng.',
                    'Khai báo contactPoint đa dạng trong Schema.'
                ),

            Field::make('additional_info', 'group', 'Thông tin bổ sung pháp lý (Mã số thuế, ngày thành lập...)')
                ->default([])
                ->tab('local')
                ->group('titles')
                ->note(
                    'Khai báo mã số thuế, mã doanh nghiệp, ngày thành lập tổ chức.',
                    'Bổ sung thông tin tăng độ tin cậy E-E-A-T với công cụ tìm kiếm.',
                    'Dữ liệu taxID, foundingDate xuất hiện trong Schema Organization.'
                ),

            Field::make('local_seo_about_page', 'select', 'Trang Giới thiệu (About Page)')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Chỉ định trang tĩnh chứa nội dung Giới thiệu về doanh nghiệp.',
                    'Chọn trang "Về chúng tôi" hoặc "Giới thiệu".',
                    'Schema Organization tự liên kết URL này vào thuộc tính about.'
                ),

            Field::make('local_seo_contact_page', 'select', 'Trang Liên hệ (Contact Page)')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Chỉ định trang tĩnh chứa thông tin Liên hệ và biểu mẫu gửi tin.',
                    'Chọn trang "Liên hệ" của bạn.',
                    'Liên kết trang liên hệ chính thức trong Schema.'
                ),

            Field::make('maps_api_key', 'text', 'Google Maps API Key')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->attributes(['type' => 'password'])
                ->note(
                    'Mã khoá API Google Maps Embed phục vụ hiển thị bản đồ địa chỉ chi nhánh.',
                    'Tạo API key trên Google Cloud Console với quyền Maps Embed API.',
                    'Khối bản đồ Google Map hiển thị chính xác vị trí doanh nghiệp.'
                ),

            Field::make('geo', 'text', 'Toạ độ địa lý GPS (Vĩ độ, Kinh độ)')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Toạ độ địa lý chính xác của cơ sở kinh doanh (Ví dụ: 10.776889, 106.700806).',
                    'Lấy toạ độ từ Google Maps bằng cách click chuột phải vào vị trí và copy toạ độ.',
                    'Khai báo geo: { latitude, longitude } trong Schema LocalBusiness.'
                ),

            Field::make('use_multiple_locations', 'toggle', 'Sử dụng nhiều địa điểm (Use Multiple Locations)')
                ->default('off')
                ->tab('local')
                ->group('titles')
                ->note(
                    'Kích hoạt quản lý nhiều chi nhánh/địa điểm kinh doanh của tổ chức thông qua Custom Post Type Locations.',
                    'Bật nếu doanh nghiệp có chuỗi cửa hàng, nhiều văn phòng hoặc nhiều cơ sở chi nhánh.',
                    'Hệ thống sẽ bật CPT Locations để thêm và quản lý từng địa chỉ riêng biệt.'
                ),

            Field::make('hide_opening_hours', 'toggle', 'Ẩn giờ mở cửa trong Schema')
                ->default('off')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Không xuất dữ liệu giờ mở cửa vào cấu trúc Schema chung của tổ chức.',
                    'Bật nếu mỗi chi nhánh có giờ làm việc hoàn toàn khác nhau.',
                    'Dữ liệu openingHoursSpecification sẽ được quản lý ở cấp từng chi nhánh.'
                ),

            Field::make('closed_label', 'text', 'Nhãn hiển thị khi đóng cửa (Closed Label)')
                ->default('Closed')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Văn bản hiển thị khi địa điểm đang trong giờ nghỉ/đóng cửa.',
                    'Nhập nhãn tùy chỉnh (ví dụ: "Đã đóng cửa" hoặc "Closed").',
                    'Hiển thị trong khung thông tin giờ làm việc.'
                ),

            Field::make('open_24_7', 'text', 'Nhãn mở cửa 24/7')
                ->default('Open 24/7')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Văn bản hiển thị khi địa điểm mở cửa liên tục suốt ngày đêm 24/7.',
                    'Nhập nhãn tùy chỉnh (ví dụ: "Mở cửa 24/7").',
                    'Hiển thị cạnh giờ mở cửa trên giao diện.'
                ),

            Field::make('open_24h', 'text', 'Nhãn mở cửa 24 giờ')
                ->default('Open 24h')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Văn bản hiển thị khi địa điểm mở cửa 24 giờ trong ngày.',
                    'Nhập nhãn phù hợp với phong cách website.',
                    'Hiển thị trong bảng giờ mở cửa.'
                ),

            Field::make('map_unit', 'select', 'Đơn vị đo khoảng cách (Measurement system)')
                ->default('kilometers')
                ->tab('local')
                ->group('titles')
                ->choices([
                    'kilometers' => 'Kilometers (km)',
                    'miles'      => 'Miles (dặm)',
                ])
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Đơn vị đo khoảng cách khi người dùng tìm kiếm địa điểm gần nhất.',
                    'Khuyên dùng Kilometers cho thị trường Việt Nam.',
                    'Khoảng cách tới các chi nhánh trên Store Locator hiển thị theo đơn vị này.'
                ),

            Field::make('map_style', 'select', 'Kiểu hiển thị bản đồ (Map Style)')
                ->default('hybrid')
                ->tab('local')
                ->group('titles')
                ->choices([
                    'hybrid'    => 'Hybrid (Kết hợp)',
                    'satellite' => 'Satellite (Vệ tinh)',
                    'roadmap'   => 'Roadmap (Bản đồ đường xá)',
                    'terrain'   => 'Terrain (Địa hình)',
                ])
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Kiểu lớp bản đồ Google Maps hiển thị trên trang tìm chi nhánh.',
                    'Chọn phong cách phù hợp với giao diện website của bạn.',
                    'Bản đồ sẽ hiển thị theo đúng phong cách đã chọn.'
                ),

            Field::make('limit_results', 'text', 'Số lượng địa điểm tối đa hiển thị')
                ->default('10')
                ->tab('local')
                ->group('titles')
                ->attributes(['type' => 'number', 'min' => 1])
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Giới hạn số địa điểm gần nhất được trả về khi người dùng tìm kiếm vị trí.',
                    'Mặc định 10 địa điểm gần nhất.',
                    'Giúp danh sách tìm kiếm gọn gàng và tải nhanh hơn.'
                ),

            Field::make('primary_country', 'select', 'Quốc gia hoạt động chính (Primary Country)')
                ->default('')
                ->tab('local')
                ->group('titles')
                ->choices([
                    ''   => __('— Chọn quốc gia —', 'lacadev'),
                    'VN' => 'Việt Nam (VN)',
                    'US' => 'Hoa Kỳ (US)',
                ])
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Quốc gia hoạt động chính của hệ thống chi nhánh.',
                    'Giúp tăng độ chuẩn xác khi định vị và gợi ý địa chỉ tìm kiếm.',
                    'Bộ lọc địa chỉ ưu tiên quốc gia này.'
                ),

            Field::make('route_label', 'text', 'Nhãn nút chỉ đường (Show Route label)')
                ->default('My Route')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Chữ hiển thị trên nút bấm dẫn đường tới chi nhánh trên bản đồ.',
                    'Nhập nhãn như "Chỉ đường" hoặc "Tìm đường đi".',
                    'Nút chỉ đường trên Store Locator sẽ mang nhãn này.'
                ),

            Field::make('enable_location_detection', 'toggle', 'Tự động phát hiện vị trí người dùng')
                ->default('off')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Yêu cầu trình duyệt chia sẻ toạ độ GPS của người dùng làm điểm bắt đầu tìm kiếm.',
                    'Bật để tự động tính khoảng cách tới cửa hàng gần người xem nhất.',
                    'Trình duyệt sẽ hiển thị thông báo xin quyền truy cập vị trí.'
                ),

            Field::make('same_organization_locations', 'toggle', 'Mọi chi nhánh thuộc cùng một pháp nhân tổ chức')
                ->default('off')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Khai báo tất cả các địa điểm đều là chi nhánh trực thuộc cùng một công ty pháp nhân.',
                    'Bật nếu các địa điểm là chi nhánh của bạn, không phải đối tác nhượng quyền riêng lẻ.',
                    'Schema của mỗi chi nhánh sẽ liên kết thuộc tính parentOrganization tới công ty chính.'
                ),

            Field::make('locations_enhanced_search', 'toggle', 'Tìm kiếm địa điểm nâng cao (Enhanced Search)')
                ->default('off')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Đưa các bài viết chi nhánh vào kết quả tìm kiếm chung trên toàn bộ website.',
                    'Bật nếu bạn muốn khách tìm kiếm từ khoá bất kỳ trên thanh search có thể thấy tên chi nhánh.',
                    'Các trang chi nhánh sẽ xuất hiện trong kết quả search thông thường.'
                ),

            Field::make('locations_post_type_base', 'text', 'Đường dẫn tĩnh cơ sở của địa điểm (Post Type Base)')
                ->default('locations')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Tiền tố URL của các bài viết chi nhánh (vd: domain.com/locations/chi-nhanh-da-nang/).',
                    'Nhập slug không dấu, viết liền có gạch ngang (mặc định: locations hoặc chi-nhanh).',
                    'Đường dẫn của từng chi nhánh sẽ có tiền tố này.'
                ),

            Field::make('locations_category_base', 'text', 'Đường dẫn danh mục địa điểm (Category Base)')
                ->default('locations-category')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Tiền tố URL của danh mục phân loại chi nhánh theo khu vực (vd: domain.com/locations-category/mien-trung/).',
                    'Mặc định: locations-category.',
                    'Đường dẫn danh mục chi nhánh sẽ nhận tiền tố này.'
                ),

            Field::make('locations_post_type_label', 'text', 'Tên hiển thị CPT Địa điểm (Post Type Name)')
                ->default('RM Locations')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Tên xuất hiện trên menu quản trị WordPress bên cột trái.',
                    'Nhập nhãn dễ nhận biết như "Chi nhánh" hoặc "Địa điểm".',
                    'Menu quản trị sẽ mang tên này.'
                ),

            Field::make('locations_post_type_plural_label', 'text', 'Tên CPT số nhiều (Plural Name)')
                ->default('RM Locations')
                ->tab('local')
                ->group('titles')
                ->showIf('use_multiple_locations', 'on')
                ->note(
                    'Tên ở dạng số nhiều cho danh sách bài viết chi nhánh.',
                    'Mặc định: RM Locations.',
                    'Xuất hiện trên tiêu đề trang quản lý danh sách địa điểm.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getSocialFields(): array
    {
        return [
            Field::make('social_url_facebook', 'text', 'Đường dẫn Trang Facebook Fanpage')
                ->default('')
                ->tab('social')
                ->group('titles')
                ->note(
                    'Đường dẫn đầy đủ tới trang Fanpage chính thức trên Facebook.',
                    'Dán URL đầy đủ dạng https://www.facebook.com/ten-trang/.',
                    'Thẻ article:publisher và thuộc tính sameAs trong Schema liên kết với Facebook.'
                ),

            Field::make('facebook_author_urls', 'text', 'Đường dẫn trang Facebook tác giả chính')
                ->default('')
                ->tab('social')
                ->group('titles')
                ->note(
                    'URL trang cá nhân Facebook của chủ sở hữu/tác giả bài viết.',
                    'Dán URL trang cá nhân Facebook để hiển thị quyền tác giả (Authorship).',
                    'Thẻ article:author xuất hiện khi chia sẻ bài viết lên Facebook.'
                ),

            Field::make('facebook_admin_id', 'text', 'Facebook Admin ID (ID người quản trị)')
                ->default('')
                ->tab('social')
                ->group('titles')
                ->note(
                    'Mã định danh người dùng Facebook cá nhân có quyền quản trị Fanpage.',
                    'Nhập dãy số ID tài khoản Facebook (dùng lookup-id để tìm).',
                    'Thẻ <meta property="fb:admins" content="..."> được in vào <head>.'
                ),

            Field::make('facebook_app_id', 'text', 'Facebook App ID (ID ứng dụng Facebook)')
                ->default('')
                ->tab('social')
                ->group('titles')
                ->note(
                    'Mã App ID ứng dụng do Meta for Developers cung cấp cho website của bạn.',
                    'Dán mã App ID để sử dụng tính năng Facebook Insights thống kê tương tác.',
                    'Thẻ <meta property="fb:app_id" content="..."> được in vào <head>.'
                ),

            Field::make('facebook_secret', 'text', 'Facebook App Secret')
                ->default('')
                ->tab('social')
                ->group('titles')
                ->attributes(['type' => 'password'])
                ->note(
                    'Khoá bí mật ứng dụng Facebook phục vụ xác thực bảo mật.',
                    'Lấy từ bảng điều khiển Meta for Developers.',
                    'Lưu trữ nội bộ an toàn để kết nối API khi cần.'
                ),

            Field::make('twitter_author_names', 'text', 'Tài khoản Twitter/X của tác giả')
                ->default('')
                ->tab('social')
                ->group('titles')
                ->note(
                    'Tên tài khoản Twitter/X (ví dụ: LixRoastery).',
                    'Nhập tên tài khoản không cần dấu @ phía trước.',
                    'Thẻ <meta name="twitter:creator" content="@ten"> và twitter:site được tạo.'
                ),

            Field::make('social_additional_profiles', 'textarea', 'Danh sách mạng xã hội bổ sung (sameAs)')
                ->default('')
                ->tab('social')
                ->group('titles')
                ->note(
                    'Các đường dẫn mạng xã hội khác như YouTube, Instagram, LinkedIn, TikTok (mỗi dòng một URL).',
                    'Nhập đầy đủ đường dẫn tới các tài khoản mạng xã hội của doanh nghiệp.',
                    'Khai báo đầy đủ trong mảng sameAs của Schema Tổ chức.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getHomepageFields(): array
    {
        return [
            Field::make('homepage_title', 'variable_text', 'Tiêu đề SEO Trang chủ')
                ->default('%sitename% %page% %sep% %sitedesc%')
                ->tab('homepage')
                ->group('titles')
                ->translatable()
                ->note(
                    'Tiêu đề xuất hiện trên tab trình duyệt và kết quả Google khi người dùng tìm kiếm trang chủ.',
                    'Sử dụng các biến %sitename%, %sitedesc%, %sep% hoặc gõ tiêu đề tuỳ biến hoàn chỉnh.',
                    'Thẻ <title> của trang chủ sẽ hiển thị theo đúng công thức này.'
                ),

            Field::make('homepage_description', 'variable_textarea', 'Mô tả tóm tắt SEO Trang chủ (Meta Description)')
                ->default('')
                ->tab('homepage')
                ->group('titles')
                ->translatable()
                ->note(
                    'Đoạn văn ngắn (tối ưu từ 140 - 160 ký tự) giới thiệu cô đọng nhất về thương hiệu của bạn.',
                    'Viết hấp dẫn, chứa từ khoá chính và lời kêu gọi hành động để tăng tỷ lệ nhấp chuột (CTR).',
                    'Thẻ <meta name="description" content="..."> của trang chủ.'
                ),

            Field::make('homepage_custom_robots', 'toggle', 'Tuỳ biến Robots Meta riêng cho Trang chủ')
                ->default('off')
                ->tab('homepage')
                ->group('titles')
                ->note(
                    'Bật nếu muốn thiết lập chỉ thị bot riêng cho trang chủ khác với cài đặt Chung toàn site.',
                    'Bật tuỳ chọn nếu bạn muốn áp dụng các chỉ thị nâng cao riêng cho trang chủ.',
                    'Mở rộng các trường lựa chọn robots bên dưới.'
                ),

            Field::make('homepage_robots', 'multicheck', 'Chỉ thị Robots cho Trang chủ')
                ->default(['index'])
                ->tab('homepage')
                ->group('titles')
                ->choices(Helper::choicesRobots())
                ->showIf('homepage_custom_robots', 'on')
                ->note(
                    'Chỉ thị robots riêng biệt áp dụng khi bot thu thập trang chủ.',
                    'Luôn giữ "Index" để trang chủ được Google xếp hạng.',
                    'Thẻ robots trang chủ hiển thị theo tuỳ chọn này.'
                ),

            Field::make('homepage_advanced_robots', 'advanced_robots', 'Advanced Robots Meta Trang chủ')
                ->default([
                    'max-snippet'       => -1,
                    'max-video-preview' => -1,
                    'max-image-preview' => 'large',
                ])
                ->tab('homepage')
                ->group('titles')
                ->showIf('homepage_custom_robots', 'on')
                ->note(
                    'Giới hạn đoạn trích và ảnh đại diện riêng cho trang chủ trên kết quả tìm kiếm.',
                    'Giữ nguyên mặc định để đạt hiệu quả hiển thị tối đa.',
                    'Chỉ thị advanced robots riêng cho trang chủ.'
                ),

            Field::make('homepage_facebook_title', 'text', 'Tiêu đề Trang chủ khi chia sẻ Facebook')
                ->default('')
                ->tab('homepage')
                ->group('titles')
                ->translatable()
                ->note(
                    'Tiêu đề xuất hiện khi trang chủ được chia sẻ trên Facebook và các mạng xã hội.',
                    'Để trống nếu muốn sử dụng Tiêu đề SEO mặc định của trang chủ.',
                    'Thẻ meta property="og:title" trên trang chủ.'
                ),

            Field::make('homepage_facebook_description', 'textarea', 'Mô tả Trang chủ khi chia sẻ Facebook')
                ->default('')
                ->tab('homepage')
                ->group('titles')
                ->translatable()
                ->note(
                    'Đoạn mô tả hiển thị dưới liên kết chia sẻ trang chủ trên Facebook.',
                    'Để trống nếu muốn dùng Mô tả SEO mặc định của trang chủ.',
                    'Thẻ meta property="og:description" trên trang chủ.'
                ),

            Field::make('homepage_facebook_image', 'image', 'Hình thu nhỏ Trang chủ cho Facebook (OG Image)')
                ->default('')
                ->tab('homepage')
                ->group('titles')
                ->note(
                    'Ảnh biểu ngữ riêng biệt xuất hiện khi trang chủ được chia sẻ.',
                    'Tải lên ảnh chuẩn kích thước 1200 x 630 pixels.',
                    'Thẻ meta property="og:image" trên trang chủ.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getAuthorsFields(): array
    {
        return [
            Field::make('disable_author_archives', 'toggle', 'Vô hiệu hoá trang Lưu trữ Tác giả (Author Archives)')
                ->default('off')
                ->tab('authors')
                ->group('titles')
                ->note(
                    'Tắt hoàn toàn trang danh sách bài viết theo tác giả (ví dụ /author/admin/).',
                    'Rất khuyên dùng với website 1 tác giả hoặc doanh nghiệp để tránh bị kẻ xấu dò quét username quản trị.',
                    'Khi truy cập link /author/... sẽ được tự động 301 chuyển hướng về Trang chủ.'
                ),

            Field::make('url_author_base', 'text', 'Tiền tố đường dẫn Tác giả (Author Base)')
                ->default('author')
                ->tab('authors')
                ->group('titles')
                ->showIf('disable_author_archives', 'off')
                ->note(
                    'Thay đổi chữ "author" trong đường dẫn lưu trữ tác giả (ví dụ thành "tac-gia" hoặc "chuyen-gia").',
                    'Nhập tiền tố mới không dấu, viết liền; sau khi đổi hãy bấm Lưu tại Cài đặt › Đường dẫn tĩnh.',
                    'URL tác giả đổi thành /tac-gia/ten-tac-gia/.'
                ),

            Field::make('author_custom_robots', 'toggle', 'Tuỳ biến Robots Meta cho trang Tác giả')
                ->default('on')
                ->tab('authors')
                ->group('titles')
                ->showIf('disable_author_archives', 'off')
                ->note(
                    'Áp dụng chỉ thị robots riêng cho các trang hồ sơ tác giả.',
                    'Bật tuỳ chọn để kiểm soát index/noindex cho trang tác giả.',
                    'Mở rộng các trường tuỳ chọn robots tác giả.'
                ),

            Field::make('author_robots', 'multicheck', 'Chỉ thị Robots cho trang Tác giả')
                ->default(['noindex'])
                ->tab('authors')
                ->group('titles')
                ->choices(Helper::choicesRobots())
                ->showIf('author_custom_robots', 'on')
                ->note(
                    'Chỉ thị index/noindex đối với các trang hồ sơ tác giả.',
                    'Khuyên dùng "Noindex" để tránh trùng lặp nội dung với trang chủ hoặc chuyên mục tin tức.',
                    'Thẻ <meta name="robots" content="noindex, follow"> xuất hiện trên trang tác giả.'
                ),

            Field::make('author_advanced_robots', 'advanced_robots', 'Advanced Robots Meta trang Tác giả')
                ->default([
                    'max-snippet'       => -1,
                    'max-video-preview' => -1,
                    'max-image-preview' => 'large',
                ])
                ->tab('authors')
                ->group('titles')
                ->showIf('author_custom_robots', 'on')
                ->note(
                    'Cấu hình nâng cao cho việc trích xuất nội dung trang hồ sơ tác giả.',
                    'Điều chỉnh nếu muốn quy định riêng kích thước hiển thị xem trước.',
                    'In vào thẻ robots của trang tác giả.'
                ),

            Field::make('author_archive_title', 'variable_text', 'Mẫu tiêu đề trang Tác giả')
                ->default('%name% %sep% %sitename% %page%')
                ->tab('authors')
                ->group('titles')
                ->showIf('disable_author_archives', 'off')
                ->translatable()
                ->note(
                    'Công thức sinh thẻ tiêu đề cho trang lưu trữ bài viết theo tác giả (hỗ trợ %name%, %sitename%).',
                    'Nhập định dạng tiêu đề bạn mong muốn (Ví dụ: "Tác giả %name% %sep% %sitename%").',
                    'Thẻ <title> trên trang tác giả hiển thị đúng định dạng.'
                ),

            Field::make('author_archive_description', 'variable_textarea', 'Mẫu mô tả trang Tác giả')
                ->default('')
                ->tab('authors')
                ->group('titles')
                ->showIf('disable_author_archives', 'off')
                ->translatable()
                ->note(
                    'Đoạn mô tả SEO cho trang hồ sơ tác giả; nếu để trống hệ thống sẽ lấy tiểu sử của tác giả đó.',
                    'Nhập mô tả tóm tắt nếu muốn áp dụng mẫu chung cho mọi tác giả.',
                    'Thẻ meta description trên trang tác giả.'
                ),

            Field::make('author_slack_enhanced_sharing', 'toggle', 'Bật chia sẻ nâng cao trên Slack')
                ->default('on')
                ->tab('authors')
                ->group('titles')
                ->showIf('disable_author_archives', 'off')
                ->note(
                    'Hiển thị thông tin tác giả và số lượng bài viết khi dán liên kết vào ứng dụng chat Slack.',
                    'Bật để tăng trải nghiệm tương tác khi chia sẻ link nội bộ trên Slack.',
                    'Thẻ twitter:label và twitter:data được sinh bổ sung.'
                ),

            Field::make('author_add_meta_box', 'toggle', 'Thêm khung SEO vào trang Quản lý Thành viên')
                ->default('on')
                ->tab('authors')
                ->group('titles')
                ->showIf('disable_author_archives', 'off')
                ->note(
                    'Hiển thị ô tuỳ chỉnh tiêu đề, mô tả và mạng xã hội cá nhân trong trang Chỉnh sửa Thành viên.',
                    'Bật để từng tác giả có thể tự cá nhân hoá đoạn giới thiệu và ảnh đại diện của mình.',
                    'Cuối trang Chỉnh sửa Hồ sơ thành viên xuất hiện khung cấu hình SEO.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getMiscFields(): array
    {
        return [
            Field::make('disable_date_archives', 'toggle', 'Vô hiệu hoá trang Lưu trữ Ngày tháng (Date Archives)')
                ->default('on')
                ->tab('misc')
                ->group('titles')
                ->note(
                    'Tắt toàn bộ các trang lưu trữ bài viết theo năm/tháng/ngày (ví dụ /2026/10/).',
                    'Rất khuyên dùng để triệt tiêu tình trạng trùng lặp nội dung nghiêm trọng và phân tán sức mạnh SEO.',
                    'Truy cập link lưu trữ ngày tháng sẽ tự động 301 chuyển hướng về Trang chủ.'
                ),

            Field::make('date_archive_title', 'variable_text', 'Mẫu tiêu đề trang Lưu trữ Ngày tháng')
                ->default('%date% %page% %sep% %sitename%')
                ->tab('misc')
                ->group('titles')
                ->showIf('disable_date_archives', 'off')
                ->translatable()
                ->note(
                    'Công thức đặt thẻ tiêu đề cho trang lưu trữ theo mốc thời gian.',
                    'Sử dụng các biến %date%, %sitename%, %page%.',
                    'Thẻ <title> trên trang lưu trữ ngày tháng.'
                ),

            Field::make('date_archive_description', 'variable_textarea', 'Mẫu mô tả trang Lưu trữ Ngày tháng')
                ->default('')
                ->tab('misc')
                ->group('titles')
                ->showIf('disable_date_archives', 'off')
                ->translatable()
                ->note(
                    'Mô tả SEO cho trang lưu trữ theo ngày tháng.',
                    'Nhập đoạn tóm tắt nếu bạn vẫn bật trang lưu trữ thời gian.',
                    'Thẻ meta description trên trang lưu trữ thời gian.'
                ),

            Field::make('date_archive_robots', 'multicheck', 'Chỉ thị Robots cho trang Lưu trữ Ngày tháng')
                ->default(['noindex'])
                ->tab('misc')
                ->group('titles')
                ->choices(Helper::choicesRobots())
                ->showIf('disable_date_archives', 'off')
                ->note(
                    'Chỉ thị cho bot tìm kiếm đối với các trang lưu trữ mốc thời gian.',
                    'Luôn khuyên đặt "Noindex" để tránh pha loãng thứ hạng tìm kiếm.',
                    'Thẻ robots trên trang ngày tháng có giá trị noindex.'
                ),

            Field::make('date_advanced_robots', 'advanced_robots', 'Advanced Robots trang Ngày tháng')
                ->default([
                    'max-snippet'       => -1,
                    'max-video-preview' => -1,
                    'max-image-preview' => 'large',
                ])
                ->tab('misc')
                ->group('titles')
                ->showIf('disable_date_archives', 'off')
                ->note(
                    'Cấu hình hiển thị trích dẫn cho trang lưu trữ thời gian.',
                    'Tuỳ chỉnh nếu cần thiết.',
                    'Áp dụng cho trang lưu trữ ngày tháng.'
                ),

            Field::make('404_title', 'variable_text', 'Tiêu đề trang Báo lỗi 404')
                ->default('Page Not Found %sep% %sitename%')
                ->tab('misc')
                ->group('titles')
                ->translatable()
                ->note(
                    'Tiêu đề xuất hiện trên tab trình duyệt khi khách truy cập vào liên kết hỏng không tồn tại.',
                    'Nhập "Không tìm thấy trang %sep% %sitename%" hoặc câu tương tự.',
                    'Thẻ <title> của trang lỗi 404.'
                ),

            Field::make('search_title', 'variable_text', 'Tiêu đề trang Kết quả Tìm kiếm')
                ->default('%search_query% %page% %sep% %sitename%')
                ->tab('misc')
                ->group('titles')
                ->translatable()
                ->note(
                    'Tiêu đề trang khi người dùng tìm kiếm từ khoá trên website (hỗ trợ biến %search_query%).',
                    'Ví dụ: "Tìm kiếm: %search_query% %sep% %sitename%".',
                    'Thẻ <title> trang kết quả tìm kiếm.'
                ),

            Field::make('noindex_search', 'toggle', 'Noindex trang Kết quả Tìm kiếm nội bộ')
                ->default('on')
                ->tab('misc')
                ->group('titles')
                ->note(
                    'Ngăn chặn Google lập chỉ mục các trang tìm kiếm nội bộ của website (/?s=...).',
                    'Bắt buộc BẬT theo tiêu chuẩn Google Webmaster Guidelines để chống spam từ khoá bẩn.',
                    'Trang tìm kiếm luôn có thẻ <meta name="robots" content="noindex, follow">.'
                ),

            Field::make('noindex_archive_subpages', 'toggle', 'Noindex các trang phân trang con (/page/2/, /page/3/...)')
                ->default('off')
                ->tab('misc')
                ->group('titles')
                ->note(
                    'Gắn noindex cho toàn bộ các trang từ trang 2 trở đi của danh mục, blog.',
                    'Chỉ bật nếu bạn muốn dồn toàn bộ sức mạnh cho trang số 1; thông thường nên để Tắt để bot crawl bài cũ.',
                    'Các trang /page/2/ sẽ có thẻ noindex.'
                ),

            Field::make('noindex_paginated_pages', 'toggle', 'Noindex trang con phân trang của bài viết đơn (Paginated Single Pages)')
                ->default('off')
                ->tab('misc')
                ->group('titles')
                ->note(
                    'Ngăn các trang phân trang con của bài viết đơn lẻ (<!--nextpage-->) xuất hiện trên kết quả tìm kiếm.',
                    'Bật nếu muốn bot chỉ lập chỉ mục trang đầu tiên của bài viết nhiều trang.',
                    'Các trang con phân trang của bài viết sẽ có thẻ noindex.'
                ),

            Field::make('noindex_password_protected', 'toggle', 'Noindex các trang/bài có cài Mật khẩu bảo vệ')
                ->default('off')
                ->tab('misc')
                ->group('titles')
                ->note(
                    'Tự động gắn thẻ noindex cho các bài viết hoặc trang yêu cầu nhập mật khẩu mới xem được nội dung.',
                    'Bật để tránh trang khoá mật khẩu vô tình xuất hiện trên kết quả Google Search.',
                    'Các bài có mật khẩu bảo vệ tự động mang chỉ thị noindex.'
                ),
        ];
    }

    /**
     * Dynamic fields generator for each post type.
     *
     * @return Field[]
     */
    private static function getPostTypeFields(string $pt_slug, \WP_Post_Type $pt_obj): array
    {
        $name = $pt_obj->labels->singular_name ?: $pt_slug;
        $tab_id = 'pt_' . $pt_slug;

        $fields = [];

        // Single Title
        $fields[] = Field::make("pt_{$pt_slug}_title", 'variable_text', "Tiêu đề SEO {$name} (Single {$name} Title)")
            ->default('%title% %sep% %sitename%')
            ->tab($tab_id)
            ->group('titles')
            ->translatable()
            ->note(
                "Mẫu tiêu đề mặc định cho các trang bài viết chi tiết thuộc loại {$name}.",
                "Sử dụng biến %title%, %sep%, %sitename% hoặc kết hợp tên chuyên mục %category%.",
                "Thẻ <title> của bài viết sẽ tự động điền theo công thức này nếu không nhập tiêu đề SEO riêng."
            );

        // Single Description
        $fields[] = Field::make("pt_{$pt_slug}_description", 'variable_textarea', "Mô tả SEO {$name} (Single {$name} Description)")
            ->default('%excerpt%')
            ->tab($tab_id)
            ->group('titles')
            ->translatable()
            ->note(
                "Mẫu mô tả tóm tắt mặc định khi bài viết chưa được nhập đoạn mô tả SEO riêng.",
                "Mặc định %excerpt% sẽ tự động trích đoạn đầu bài viết khoảng 150 ký tự.",
                "Thẻ <meta name=\"description\"> tự động điền nội dung."
            );

        // Archive Title (if post type has archive)
        if (!empty($pt_obj->has_archive)) {
            $fields[] = Field::make("pt_{$pt_slug}_archive_title", 'variable_text', "Tiêu đề trang Lưu trữ {$name} (Archive Title)")
                ->default('%title% %page% %sep% %sitename%')
                ->tab($tab_id)
                ->group('titles')
                ->translatable()
                ->note(
                    "Tiêu đề trang lưu trữ danh sách tất cả {$name}.",
                    "Cấu hình tiêu đề hiển thị trên Google cho trang lưu trữ chính.",
                    "Thẻ <title> của trang lưu trữ {$name}."
                );

            $fields[] = Field::make("pt_{$pt_slug}_archive_description", 'variable_textarea', "Mô tả trang Lưu trữ {$name} (Archive Description)")
                ->default('')
                ->tab($tab_id)
                ->group('titles')
                ->translatable()
                ->note(
                    "Đoạn văn tóm tắt giới thiệu trang lưu trữ danh sách {$name}.",
                    "Viết giới thiệu tổng quan giúp trang lưu trữ đạt thứ hạng cao.",
                    "Thẻ meta description của trang lưu trữ {$name}."
                );
        }

        // Schema Rich Snippet default
        $schema_default = match ($pt_slug) {
            'post', 'page' => 'article',
            'product', 'download' => 'product',
            default        => 'off',
        };

        $fields[] = Field::make("pt_{$pt_slug}_default_rich_snippet", 'select', "Loại Schema mặc định cho {$name}")
            ->default($schema_default)
            ->tab($tab_id)
            ->group('titles')
            ->choices(Helper::choicesRichSnippetTypes())
            ->note(
                "Cấu trúc Schema JSON-LD mặc định được gán cho các bài viết mới tạo của loại {$name}.",
                "Chọn loại phù hợp (ví dụ 'Article' cho bài viết tin tức, 'Product' cho sản phẩm).",
                "Mã JSON-LD Schema ở mã nguồn chân trang."
            );

        $fields[] = Field::make("pt_{$pt_slug}_default_article_type", 'select', 'Loại bài viết Article (Article Type)')
            ->default($pt_slug === 'post' ? 'BlogPosting' : 'Article')
            ->tab($tab_id)
            ->group('titles')
            ->showIf("pt_{$pt_slug}_default_rich_snippet", 'article')
            ->choices([
                'Article'     => __('Bài báo chung (Article)', 'lacadev'),
                'BlogPosting' => __('Bài viết Blog (BlogPosting)', 'lacadev'),
                'NewsArticle' => __('Tin tức báo chí (NewsArticle)', 'lacadev'),
            ])
            ->note(
                'Định danh chuyên sâu cho Schema Article của bài viết tin tức.',
                'Khuyên dùng "BlogPosting" cho blog hoặc "NewsArticle" nếu là báo chí.',
                'Thuộc tính @type trong Schema JSON-LD.'
            );

        $fields[] = Field::make("pt_{$pt_slug}_default_snippet_name", 'variable_text', "Tiêu đề Schema mặc định (Headline) {$name}")
            ->default('%seo_title%')
            ->tab($tab_id)
            ->group('titles')
            ->translatable()
            ->note(
                "Tiêu đề hiển thị trong Schema JSON-LD của {$name}.",
                "Mặc định %seo_title% để đồng bộ với tiêu đề SEO.",
                "Trường headline trong Schema."
            );

        $fields[] = Field::make("pt_{$pt_slug}_default_snippet_desc", 'variable_textarea', "Mô tả Schema mặc định {$name}")
            ->default('%seo_description%')
            ->tab($tab_id)
            ->group('titles')
            ->translatable()
            ->note(
                "Đoạn mô tả trong Schema JSON-LD của {$name}.",
                "Mặc định %seo_description% để lấy theo mô tả SEO.",
                "Trường description trong Schema."
            );

        // Custom Robots toggle
        $custom_robots_default = ($pt_slug === 'attachment') ? 'on' : 'off';
        $robots_default = ($pt_slug === 'attachment') ? ['noindex'] : ['index'];

        $fields[] = Field::make("pt_{$pt_slug}_custom_robots", 'toggle', "Tuỳ biến Robots Meta riêng cho {$name}")
            ->default($custom_robots_default)
            ->tab($tab_id)
            ->group('titles')
            ->note(
                "Bật nếu muốn áp dụng quy tắc robots riêng cho loại nội dung {$name}.",
                "Bật tuỳ chọn để mở rộng bảng cấu hình robots.",
                "Mở các lựa chọn robots bên dưới."
            );

        $fields[] = Field::make("pt_{$pt_slug}_robots", 'multicheck', "Chỉ thị Robots cho {$name}")
            ->default($robots_default)
            ->tab($tab_id)
            ->group('titles')
            ->choices(Helper::choicesRobots())
            ->showIf("pt_{$pt_slug}_custom_robots", 'on')
            ->note(
                "Chỉ thị robots mặc định cho toàn bộ các bài viết thuộc loại {$name}.",
                "Chọn 'Index' cho nội dung cần SEO; chọn 'Noindex' nếu là trang nội bộ/đính kèm.",
                "Thẻ meta robots trên từng bài viết {$name}."
            );

        $fields[] = Field::make("pt_{$pt_slug}_advanced_robots", 'advanced_robots', "Advanced Robots cho {$name}")
            ->default([
                'max-snippet'       => -1,
                'max-video-preview' => -1,
                'max-image-preview' => 'large',
            ])
            ->tab($tab_id)
            ->group('titles')
            ->showIf("pt_{$pt_slug}_custom_robots", 'on')
            ->note(
                "Kiểm soát độ dài đoạn trích và kích thước ảnh xem trước cho {$name}.",
                "Giữ mặc định để tối ưu hiển thị.",
                "Thẻ advanced robots của {$name}."
            );

        // Link Suggestions & Primary Taxonomy (for standard post types)
        if ($pt_slug !== 'attachment') {
            $fields[] = Field::make("pt_{$pt_slug}_link_suggestions", 'toggle', "Gợi ý liên kết nội bộ cho {$name}")
                ->default('on')
                ->tab($tab_id)
                ->group('titles')
                ->note(
                    "Bật hộp gợi ý liên kết nội bộ khi soạn thảo {$name}.",
                    "Bật để hệ thống tự đề xuất bài liên quan khi viết bài.",
                    "Khung Link Suggestions xuất hiện khi chỉnh sửa bài."
                );

            $fields[] = Field::make("pt_{$pt_slug}_ls_use_fk", 'radio_inline', "Tiêu đề gợi ý liên kết")
                ->default('titles')
                ->tab($tab_id)
                ->group('titles')
                ->choices([
                    'titles'          => __('Tiêu đề bài viết (Titles)', 'lacadev'),
                    'focus_keywords'  => __('Từ khoá chính (Focus Keywords)', 'lacadev'),
                ])
                ->note(
                    "Sử dụng từ khoá chính thay cho tiêu đề bài viết làm anchor text mặc định khi chèn link.",
                    "Chọn tiêu đề bài viết hoặc từ khoá chính.",
                    "Anchor text đề xuất khi chèn liên kết."
                );

            $tax_choices = ['off' => __('Không (None)', 'lacadev')];
            $taxonomies = get_object_taxonomies($pt_slug, 'objects');
            foreach ($taxonomies as $tax) {
                if (!empty($tax->hierarchical) && !empty($tax->public)) {
                    $tax_choices[$tax->name] = $tax->label ?: $tax->name;
                }
            }

            $primary_tax_default = match ($pt_slug) {
                'post'    => isset($tax_choices['category']) ? 'category' : 'off',
                'product' => isset($tax_choices['product_cat']) ? 'product_cat' : 'off',
                default   => 'off',
            };

            $fields[] = Field::make("pt_{$pt_slug}_primary_taxonomy", 'select', "Phân loại chính (Primary Taxonomy)")
                ->default($primary_tax_default)
                ->tab($tab_id)
                ->group('titles')
                ->choices($tax_choices)
                ->note(
                    "Tên phân loại taxonomy chính dùng cho tính năng Primary Term và Breadcrumb.",
                    "Ví dụ: Chuyên mục đối với bài viết, Danh mục sản phẩm đối với sản phẩm.",
                    "Xác định phân loại chính hiển thị trên Breadcrumbs."
                );

            if (!empty($pt_obj->has_archive)) {
                $fields[] = Field::make("pt_{$pt_slug}_facebook_image", 'image', "Hình ảnh Facebook mặc định cho {$name}")
                    ->default('')
                    ->tab($tab_id)
                    ->group('titles')
                    ->note(
                        "Hình ảnh chia sẻ Facebook đại diện cho loại nội dung {$name}.",
                        "Tải lên ảnh kích thước tối thiểu 1200 x 630 pixels.",
                        "Thẻ og:image dự phòng khi bài viết không có ảnh."
                    );
            }

            $fields[] = Field::make("pt_{$pt_slug}_bulk_editing", 'radio_inline', "Sửa nhanh hàng loạt (Bulk Editing)")
                ->default('editing')
                ->tab($tab_id)
                ->group('titles')
                ->choices([
                    '0'        => __('Tắt (Disabled)', 'lacadev'),
                    'editing'  => __('Bật chỉnh sửa (Enabled)', 'lacadev'),
                    'readonly' => __('Chỉ đọc (Read Only)', 'lacadev'),
                ])
                ->note(
                    "Thêm cột chỉnh sửa nhanh tiêu đề và mô tả SEO trong danh sách bài viết quản trị.",
                    "Bật để sửa nhanh SEO cho nhiều bài mà không cần mở từng bài.",
                    "Cột SEO xuất hiện trong bảng danh sách bài viết."
                );

            $fields[] = Field::make("pt_{$pt_slug}_analyze_fields", 'textarea', "Trường tuỳ biến phân tích SEO (Custom Fields)")
                ->default('')
                ->tab($tab_id)
                ->group('titles')
                ->note(
                    "Danh sách các custom field (mỗi dòng một tên field) cần đưa vào phân tích nội dung SEO.",
                    "Nhập tên custom field (ví dụ: subtitle, extra_content).",
                    "Bộ phân tích SEO sẽ tính cả từ ngữ trong các trường này."
                );
        } else {
            $fields[] = Field::make("pt_{$pt_slug}_bulk_editing", 'radio_inline', "Sửa nhanh hàng loạt (Bulk Editing)")
                ->default('editing')
                ->tab($tab_id)
                ->group('titles')
                ->choices([
                    '0'        => __('Tắt (Disabled)', 'lacadev'),
                    'editing'  => __('Bật chỉnh sửa (Enabled)', 'lacadev'),
                    'readonly' => __('Chỉ đọc (Read Only)', 'lacadev'),
                ]);
        }

        // Slack enhanced sharing
        $fields[] = Field::make("pt_{$pt_slug}_slack_enhanced_sharing", 'toggle', 'Bật chia sẻ nâng cao trên Slack')
            ->default(in_array($pt_slug, ['post', 'page', 'product'], true) ? 'on' : 'off')
            ->tab($tab_id)
            ->group('titles')
            ->note(
                "Hiển thị thông tin tác giả, thời gian đọc hoặc giá sản phẩm khi chia sẻ lên Slack.",
                "Bật để liên kết gửi trong Slack hiển thị thẻ giàu thông tin.",
                "Thẻ meta twitter:label và twitter:data."
            );

        // Add SEO Metabox
        $fields[] = Field::make("pt_{$pt_slug}_add_meta_box", 'toggle', "Hiển thị khung nhập liệu SEO khi soạn thảo {$name}")
            ->default($pt_slug === 'attachment' ? 'off' : 'on')
            ->tab($tab_id)
            ->group('titles')
            ->note(
                "Bật khung tuỳ biến tiêu đề, mô tả và xem trước SERP Google trong trang chỉnh sửa {$name}.",
                "Nên luôn BẬT để tác giả có thể chỉnh sửa SEO theo từng bài viết cụ thể.",
                "Khung Laca SEO xuất hiện ở cuối trang soạn thảo bài viết."
            );

        return $fields;
    }

    /**
     * Dynamic fields generator for each taxonomy.
     *
     * @return Field[]
     */
    private static function getTaxonomyFields(string $tax_slug, \WP_Taxonomy $tax_obj): array
    {
        $name = $tax_obj->labels->singular_name ?: $tax_slug;
        $tab_id = 'tax_' . $tax_slug;

        $custom_robots_default = in_array($tax_slug, ['post_tag', 'post_format', 'product_tag'], true) ? 'on' : 'off';
        $robots_default = in_array($tax_slug, ['post_tag', 'post_format', 'product_tag'], true) ? ['noindex'] : ['index'];
        $metabox_default = ($tax_slug === 'category') ? 'on' : 'off';

        return [
            Field::make("tax_{$tax_slug}_title", 'variable_text', "Tiêu đề trang Chuyên mục {$name}")
                ->default('%term% %sep% %sitename%')
                ->tab($tab_id)
                ->group('titles')
                ->translatable()
                ->note(
                    "Mẫu tiêu đề hiển thị trên Google cho các trang lưu trữ thuộc phân loại {$name}.",
                    "Sử dụng biến %term% để tự động lấy tên danh mục, kèm %sitename% và %sep%.",
                    "Thẻ <title> của trang danh mục {$name}."
                ),

            Field::make("tax_{$tax_slug}_description", 'variable_textarea', "Mô tả SEO trang Chuyên mục {$name}")
                ->default('%term_description%')
                ->tab($tab_id)
                ->group('titles')
                ->translatable()
                ->note(
                    "Mô tả meta mặc định cho các trang phân loại {$name}.",
                    "Mặc định %term_description% sẽ lấy đoạn mô tả bạn đã nhập khi tạo danh mục.",
                    "Thẻ <meta name=\"description\"> trên trang danh mục."
                ),

            Field::make("tax_{$tax_slug}_custom_robots", 'toggle', "Tuỳ biến Robots Meta riêng cho {$name}")
                ->default($custom_robots_default)
                ->tab($tab_id)
                ->group('titles')
                ->note(
                    "Bật nếu muốn thiết lập chỉ thị robots riêng cho toàn bộ phân loại {$name}.",
                    "Bật để điều chỉnh index hoặc noindex cho phân loại này.",
                    "Mở rộng các lựa chọn robots bên dưới."
                ),

            Field::make("tax_{$tax_slug}_robots", 'multicheck', "Chỉ thị Robots cho {$name}")
                ->default($robots_default)
                ->tab($tab_id)
                ->group('titles')
                ->choices(Helper::choicesRobots())
                ->showIf("tax_{$tax_slug}_custom_robots", 'on')
                ->note(
                    "Chỉ thị robots mặc định cho trang lưu trữ {$name}.",
                    "Với chuyên mục chính (category, product_cat) nên để 'Index'; với thẻ phụ ít bài có thể để 'Noindex'.",
                    "Thẻ meta robots trên trang danh mục {$name}."
                ),

            Field::make("tax_{$tax_slug}_advanced_robots", 'advanced_robots', "Advanced Robots cho {$name}")
                ->default([
                    'max-snippet'       => -1,
                    'max-video-preview' => -1,
                    'max-image-preview' => 'large',
                ])
                ->tab($tab_id)
                ->group('titles')
                ->showIf("tax_{$tax_slug}_custom_robots", 'on')
                ->note(
                    "Kiểm soát hiển thị đoạn trích và hình ảnh cho danh mục {$name}.",
                    "Giữ mặc định để tối ưu hiển thị.",
                    "Thẻ advanced robots của danh mục {$name}."
                ),

            Field::make("tax_{$tax_slug}_slack_enhanced_sharing", 'toggle', 'Bật chia sẻ nâng cao trên Slack')
                ->default('on')
                ->tab($tab_id)
                ->group('titles')
                ->note(
                    "Hiển thị tổng số lượng bài viết thuộc danh mục này khi gửi link qua Slack.",
                    "Bật để xem thống kê nhanh trên Slack.",
                    "Thẻ dữ liệu bổ sung twitter:data."
                ),

            Field::make("tax_{$tax_slug}_bulk_editing", 'radio_inline', "Sửa nhanh hàng loạt danh mục (Bulk Editing)")
                ->default(0)
                ->tab($tab_id)
                ->group('titles')
                ->choices([
                    0 => __('Tắt (Disabled)', 'lacadev'),
                    1 => __('Bật (Enabled)', 'lacadev'),
                ])
                ->note(
                    "Bật sửa nhanh tiêu đề và mô tả trong bảng danh sách phân loại {$name}.",
                    "Giúp cập nhật nhanh SEO cho hàng loạt danh mục.",
                    "Cột chỉnh sửa SEO xuất hiện trong bảng danh mục."
                ),

            Field::make("tax_{$tax_slug}_add_meta_box", 'toggle', "Hiển thị khung SEO khi chỉnh sửa {$name}")
                ->default($metabox_default)
                ->tab($tab_id)
                ->group('titles')
                ->note(
                    "Hiển thị ô tuỳ chỉnh tiêu đề và mô tả SEO riêng biệt ở từng mục phân loại {$name}.",
                    "Bật để tối ưu On-Page sâu cho từng danh mục sản phẩm/tin tức quan trọng.",
                    "Khung cài đặt SEO xuất hiện trong trang Chỉnh sửa Danh mục."
                ),


            Field::make("remove_{$tax_slug}_snippet_data", 'toggle', "Gỡ bỏ Schema Markup trên trang {$name}")
                ->default(in_array($tax_slug, ['product_cat', 'product_tag'], true) ? 'on' : 'off')
                ->tab($tab_id)
                ->group('titles')
                ->note(
                    "Gỡ bỏ Schema không cần thiết trên trang lưu trữ danh mục để tránh báo lỗi trùng lặp.",
                    "Nên bật đối với danh mục sản phẩm để tránh lỗi cảnh báo thiếu giá từ Google Search Console.",
                    "Schema trên trang danh mục được tinh gọn chuẩn xác."
                ),
        ];
    }
}
