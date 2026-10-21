<?php

namespace App\Features\LacaSeo\Fields;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Registry of all XML & HTML Sitemap settings fields.
 */
class SitemapFields
{
    /**
     * @return array<string, Field[]> Map of tab slug => array of Field objects
     */
    public static function get(): array
    {
        $tabs = [
            'general'      => self::getGeneralFields(),
            'html-sitemap' => self::getHtmlSitemapFields(),
            'authors'      => self::getAuthorsFields(),
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
    private static function getGeneralFields(): array
    {
        return [
            Field::make('items_per_page', 'text', 'Số liên kết mỗi trang sitemap (Links Per Sitemap)')
                ->default('200')
                ->tab('general')
                ->group('sitemap')
                ->attributes(['type' => 'number', 'min' => '1', 'max' => '50000'])
                ->note(
                    'Số lượng liên kết tối đa hiển thị trên mỗi tệp sitemap con (phân trang sitemap).',
                    'Mặc định khuyến nghị 200, tối đa 50,000 theo chuẩn kỹ thuật của sitemaps.org.',
                    'Khi số lượng bài vượt ngưỡng này, sitemap sẽ tự động chia trang như post-sitemap1.xml, post-sitemap2.xml.'
                ),

            Field::make('include_images', 'toggle', 'Bao gồm hình ảnh trong Sitemap (Images in Sitemaps)')
                ->default('on')
                ->tab('general')
                ->group('sitemap')
                ->note(
                    'Đưa các thẻ khai báo hình ảnh trong nội dung bài viết vào sitemap XML.',
                    'Bật để giúp Google Image Search thu thập và xếp hạng hình ảnh nhanh chóng.',
                    'Mỗi URL trong sitemap sẽ có thêm các nhánh thẻ <image:image> kèm <image:loc>.'
                ),

            Field::make('include_featured_image', 'toggle', 'Bao gồm ảnh đại diện (Featured Images)')
                ->default('off')
                ->tab('general')
                ->group('sitemap')
                ->showIf('include_images', 'on')
                ->note(
                    'Bao gồm cả ảnh đại diện của bài viết ngay cả khi nó không xuất hiện trực tiếp trong nội dung bài.',
                    'Bật nếu giao diện bài viết hiển thị ảnh đại diện riêng bên ngoài khối bài viết.',
                    'Ảnh đại diện được tự động bổ sung vào danh sách ảnh của URL bài viết trong sitemap.'
                ),

            Field::make('exclude_posts', 'text', 'Loại trừ bài viết khỏi Sitemap (Exclude Posts)')
                ->default('')
                ->tab('general')
                ->group('sitemap')
                ->note(
                    'Nhập danh sách ID bài viết muốn loại trừ khỏi sitemap, cách nhau bằng dấu phẩy (vd: 12, 45, 89).',
                    'Dùng khi có bài viết hoặc trang riêng tư không muốn công cụ tìm kiếm lập chỉ mục.',
                    'Các ID bài này sẽ hoàn toàn biến mất khỏi sitemap XML và sitemap HTML.'
                ),

            Field::make('exclude_terms', 'text', 'Loại trừ chuyên mục/thẻ (Exclude Terms)')
                ->default('')
                ->tab('general')
                ->group('sitemap')
                ->note(
                    'Nhập danh sách ID term (chuyên mục, thẻ, thương hiệu...) cần loại bỏ khỏi sitemap, cách nhau bằng dấu phẩy.',
                    'Dùng để loại trừ các danh mục nội bộ, danh mục chưa hoàn thiện.',
                    'Các trang lưu trữ của term bị loại trừ sẽ không xuất hiện trong sitemap.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getHtmlSitemapFields(): array
    {
        $page_options = ['' => '— Chọn trang —'];
        if (is_admin()) {
            $pages = get_posts([
                'post_type'      => 'page',
                'posts_per_page' => 100,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true,
            ]);
            foreach ($pages as $p) {
                $page_options[(string) $p->ID] = $p->post_title ?: ('#' . $p->ID);
            }
        }

        return [
            Field::make('html_sitemap', 'toggle', 'Kích hoạt Sitemap HTML (HTML Sitemap)')
                ->default('off')
                ->tab('html-sitemap')
                ->group('sitemap')
                ->note(
                    'Tạo sơ đồ trang dạng HTML thân thiện cho người dùng truy cập trực quan trên website.',
                    'Bật để hỗ trợ liên kết nội bộ (internal linking) và giúp người dùng xem toàn bộ trang web.',
                    'Có thể hiển thị qua shortcode [laca_seo_html_sitemap] hoặc gắn tự động vào 1 trang Page.'
                ),

            Field::make('html_sitemap_display', 'radio_inline', 'Hình thức hiển thị (Display Format)')
                ->default('shortcode')
                ->tab('html-sitemap')
                ->group('sitemap')
                ->choices([
                    'shortcode' => 'Shortcode',
                    'page'      => 'Chèn vào trang Page',
                ])
                ->showIf('html_sitemap', 'on')
                ->note(
                    'Cách thức sitemap HTML xuất hiện trên giao diện người dùng.',
                    'Chọn "Shortcode" để tự do dán mã vào bất kỳ đâu; chọn "Chèn vào trang Page" để hệ thống tự gắn vào cuối trang đã chọn.',
                    'Giao diện sitemap HTML sẽ hiển thị tương ứng theo phương thức đã chọn.'
                ),

            Field::make('html_sitemap_shortcode', 'html', 'Mã Shortcode sitemap')
                ->default('[rank_math_html_sitemap]')
                ->tab('html-sitemap')
                ->group('sitemap')
                ->showIf('html_sitemap_display', 'shortcode')
                ->note(
                    'Sao chép mã shortcode: [laca_seo_html_sitemap] (hỗ trợ alias [rank_math_html_sitemap]).',
                    'Dán mã này vào nội dung trang sơ đồ website của bạn trong trình soạn thảo.',
                    'Sitemap HTML sẽ hiển thị ngay tại vị trí đặt shortcode.'
                ),

            Field::make('html_sitemap_page', 'select', 'Chọn trang Page hiển thị Sitemap')
                ->default('')
                ->tab('html-sitemap')
                ->group('sitemap')
                ->choices($page_options)
                ->showIf('html_sitemap_display', 'page')
                ->note(
                    'Trang tĩnh bạn muốn hệ thống tự động chèn cây sơ đồ sitemap HTML xuống dưới nội dung.',
                    'Chọn một trang Page chuyên biệt ví dụ "Sơ đồ website" hoặc "Sitemap".',
                    'Nội dung sitemap HTML sẽ hiển thị ở cuối bài viết của trang này.'
                ),

            Field::make('html_sitemap_sort', 'select', 'Thứ tự sắp xếp (Sort By)')
                ->default('published')
                ->tab('html-sitemap')
                ->group('sitemap')
                ->choices([
                    'published'    => 'Ngày đăng (Published Date)',
                    'modified'     => 'Ngày cập nhật (Modified Date)',
                    'alphabetical' => 'Theo bảng chữ cái A-Z (Alphabetical)',
                    'post_id'      => 'Theo ID bài viết (Post ID)',
                ])
                ->showIf('html_sitemap', 'on')
                ->note(
                    'Trật tự sắp xếp các liên kết bài viết và trang trong sitemap HTML.',
                    'Chọn kiểu sắp xếp phù hợp với trải nghiệm người dùng tìm kiếm bài.',
                    'Các danh sách bài viết trong sơ đồ HTML được sắp theo đúng tiêu chí.'
                ),

            Field::make('html_sitemap_show_dates', 'toggle', 'Hiển thị ngày tháng (Show Dates)')
                ->default('on')
                ->tab('html-sitemap')
                ->group('sitemap')
                ->showIf('html_sitemap', 'on')
                ->note(
                    'Hiển thị ngày đăng bài bên cạnh tiêu đề mỗi mục trong sitemap HTML.',
                    'Bật để độc giả và bot biết độ mới của từng bài viết.',
                    'Ngày đăng xuất hiện cạnh liên kết bài viết.'
                ),

            Field::make('html_sitemap_seo_titles', 'radio_inline', 'Tiêu đề hiển thị (Item Titles)')
                ->default('titles')
                ->tab('html-sitemap')
                ->group('sitemap')
                ->choices([
                    'titles'     => 'Tiêu đề bài viết gốc (Item Titles)',
                    'seo_titles' => 'Tiêu đề SEO tuỳ biến (SEO Titles)',
                ])
                ->showIf('html_sitemap', 'on')
                ->note(
                    'Lựa chọn in tiêu đề bài viết gốc hay tiêu đề SEO tuỳ biến trong sơ đồ HTML.',
                    'Khuyên dùng tiêu đề gốc để ngắn gọn, tự nhiên với độc giả.',
                    'Chữ hiển thị của liên kết sitemap HTML sẽ theo nguồn tiêu đề đã chọn.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getAuthorsFields(): array
    {
        $role_choices = [];
        $default_roles = [];
        if (is_admin() || function_exists('wp_roles')) {
            $roles = wp_roles()->roles;
            foreach ($roles as $slug => $r) {
                $role_choices[$slug] = translate_user_role($r['name']);
                if (!in_array($slug, ['administrator', 'editor', 'author'], true)) {
                    $default_roles[] = $slug;
                }
            }
        }

        return [
            Field::make('authors_sitemap', 'toggle', 'Bao gồm trang tác giả trong Sitemap XML')
                ->default('on')
                ->tab('authors')
                ->group('sitemap')
                ->note(
                    'Đưa các trang lưu trữ hồ sơ tác giả (/author/...) vào sitemap XML.',
                    'Bật nếu website có nhiều tác giả uy tín cần tăng thứ hạng E-E-A-T cho trang cá nhân.',
                    'Nhánh sitemap author-sitemap.xml sẽ xuất hiện trong sitemap_index.xml.'
                ),

            Field::make('authors_html_sitemap', 'toggle', 'Bao gồm tác giả trong Sitemap HTML')
                ->default('on')
                ->tab('authors')
                ->group('sitemap')
                ->note(
                    'Hiển thị danh sách tác giả trong sơ đồ sitemap HTML cho người xem.',
                    'Bật nếu muốn người đọc dễ dàng bấm vào xem trang hồ sơ bài viết của các tác giả.',
                    'Mục "Tác giả" sẽ xuất hiện trong sitemap HTML ngoài frontend.'
                ),

            Field::make('include_authors_without_posts', 'toggle', 'Bao gồm tác giả chưa có bài viết')
                ->default('off')
                ->tab('authors')
                ->group('sitemap')
                ->note(
                    'Đưa cả những người dùng chưa xuất bản bài viết nào vào sitemap tác giả.',
                    'Khuyên dùng: TẮT để tránh trang rỗng (thin content) bị Google đánh giá thấp.',
                    'Tác giả không có bài viết sẽ bị loại trừ hoàn toàn khỏi sitemap.'
                ),

            Field::make('exclude_roles', 'multicheck', 'Loại trừ vai trò thành viên (Exclude Roles)')
                ->default($default_roles)
                ->tab('authors')
                ->group('sitemap')
                ->choices($role_choices)
                ->note(
                    'Các vai trò thành viên KHÔNG đưa vào sitemap tác giả (mặc định loại trừ subscriber, customer, contributor).',
                    'Chọn các vai trò chỉ là khách mua hàng hoặc thành viên thường không viết bài công khai.',
                    'Chỉ có những tác giả thuộc vai trò cho phép mới có mặt trong sitemap.'
                ),

            Field::make('exclude_users', 'text', 'Loại trừ người dùng theo ID (Exclude Users)')
                ->default('')
                ->tab('authors')
                ->group('sitemap')
                ->note(
                    'Nhập danh sách ID người dùng cần loại trừ khỏi sitemap, cách nhau bằng dấu phẩy (vd: 1, 5).',
                    'Dùng khi muốn ẩn tài khoản quản trị hệ thống kỹ thuật khỏi sitemap công khai.',
                    'Các ID người dùng này sẽ không xuất hiện trong author sitemap.'
                ),
        ];
    }

    /**
     * @return Field[]
     */
    private static function getPostTypeFields(string $post_type, \WP_Post_Type $obj): array
    {
        $prefix = "pt_{$post_type}_";
        $label = $obj->labels->name ?: $post_type;
        $is_attachment = ($post_type === 'attachment');

        $fields = [
            Field::make($prefix . 'sitemap', 'toggle', "Bao gồm {$label} trong Sitemap XML")
                ->default($is_attachment ? 'off' : 'on')
                ->tab('pt_' . $post_type)
                ->group('sitemap')
                ->note(
                    "Đưa các bài viết thuộc loại nội dung {$label} vào sitemap XML để Google lập chỉ mục.",
                    'Khuyên dùng: BẬT cho bài viết, trang, sản phẩm; TẮT cho media tệp đính kèm.',
                    "Sitemap con {$post_type}-sitemap.xml sẽ được liệt kê trong sitemap_index.xml."
                ),

            Field::make($prefix . 'html_sitemap', 'toggle', "Bao gồm {$label} trong Sitemap HTML")
                ->default($is_attachment ? 'off' : 'on')
                ->tab('pt_' . $post_type)
                ->group('sitemap')
                ->note(
                    "Hiển thị danh mục {$label} trong sơ đồ trang HTML cho khách truy cập.",
                    'Bật để khách dễ dàng xem toàn bộ danh mục bài viết từ sitemap.',
                    "Khối danh sách {$label} xuất hiện trên trang sitemap HTML."
                ),
        ];

        if (!$is_attachment) {
            $fields[] = Field::make($prefix . 'image_customfields', 'textarea', 'Trường tuỳ biến chứa ảnh (Image Custom Fields)')
                ->default('')
                ->tab('pt_' . $post_type)
                ->group('sitemap')
                ->showIf($prefix . 'sitemap', 'on')
                ->note(
                    'Nhập tên các post meta chứa URL hình ảnh (mỗi dòng một tên trường) để đưa ảnh vào sitemap.',
                    'Dùng khi bạn tạo trường ảnh bằng ACF hoặc custom meta mà muốn Google Image thu thập.',
                    'Các ảnh trong custom fields được thêm vào thẻ <image:image> tương ứng của bài viết.'
                );
        }

        return $fields;
    }

    /**
     * @return Field[]
     */
    private static function getTaxonomyFields(string $taxonomy, \WP_Taxonomy $obj): array
    {
        $prefix = "tax_{$taxonomy}_";
        $label = $obj->labels->name ?: $taxonomy;
        $is_category = ($taxonomy === 'category');

        return [
            Field::make($prefix . 'sitemap', 'toggle', "Bao gồm {$label} trong Sitemap XML")
                ->default($is_category ? 'on' : 'off')
                ->tab('tax_' . $taxonomy)
                ->group('sitemap')
                ->note(
                    "Đưa các đường dẫn trang lưu trữ phân loại {$label} vào sitemap XML.",
                    'Khuyên dùng: BẬT cho chuyên mục chính (category, danh mục sản phẩm); TẮT cho thẻ tag ít bài.',
                    "Sitemap con {$taxonomy}-sitemap.xml sẽ được sinh trong sitemap_index.xml."
                ),

            Field::make($prefix . 'html_sitemap', 'toggle', "Bao gồm {$label} trong Sitemap HTML")
                ->default($is_category ? 'on' : 'off')
                ->tab('tax_' . $taxonomy)
                ->group('sitemap')
                ->note(
                    "Liệt kê danh sách {$label} trên sitemap HTML cho người xem.",
                    'Bật để tạo liên kết phân cấp rõ ràng trên sitemap giao diện.',
                    "Danh mục {$label} xuất hiện trong sơ đồ HTML."
                ),

            Field::make($prefix . 'include_empty', 'toggle', "Bao gồm {$label} rỗng chưa có bài")
                ->default('off')
                ->tab('tax_' . $taxonomy)
                ->group('sitemap')
                ->showIf($prefix . 'sitemap', 'on')
                ->note(
                    "Đưa cả các term thuộc {$label} chưa có bất kỳ bài viết nào vào sitemap.",
                    'Khuyên dùng: TẮT để tránh trang rỗng không có nội dung bị Google phạt.',
                    'Chỉ các term có bài viết (count > 0) mới xuất hiện trong sitemap.'
                ),
        ];
    }
}
