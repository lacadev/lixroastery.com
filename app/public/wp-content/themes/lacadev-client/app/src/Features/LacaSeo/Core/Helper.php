<?php

namespace App\Features\LacaSeo\Core;

defined('ABSPATH') || exit;

/**
 * Helper utilities for Laca SEO.
 */
class Helper
{
    /**
     * Check if Rank Math (free or pro) is currently active.
     */
    public static function isRankMathActive(): bool
    {
        return defined('RANK_MATH_VERSION');
    }

    /**
     * Check if Laca SEO frontend output should run.
     * Output runs only when Rank Math is NOT active, OR when dev force flag is active.
     */
    public static function shouldRunFrontend(): bool
    {
        if (self::isForceFrontend()) {
            return true;
        }

        return !self::isRankMathActive();
    }

    /**
     * Dev flag to force Laca SEO frontend output even when Rank Math is active.
     * Allowed ONLY in local environment or for logged-in administrators with manage_options capability.
     */
    public static function isForceFrontend(): bool
    {
        if (defined('LACA_SEO_FORCE_FRONTEND') && LACA_SEO_FORCE_FRONTEND) {
            return true;
        }

        if (!empty($_GET['laca_seo_force_frontend'])) {
            $is_local = function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local';
            $is_admin = function_exists('current_user_can') && current_user_can('manage_options');

            if ($is_local || $is_admin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get accessible public post types for SEO.
     * Excludes non-public or internal types.
     *
     * @return array<string, \WP_Post_Type>
     */
    public static function getAccessiblePostTypes(): array
    {
        $post_types = get_post_types(['public' => true], 'objects');

        $exclude = [
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_navigation',
            'user_request',
            'custom_css',
            'customize_changeset',
        ];

        foreach ($exclude as $slug) {
            unset($post_types[$slug]);
        }

        return $post_types;
    }

    /**
     * Get accessible public taxonomies for SEO.
     *
     * @return array<string, \WP_Taxonomy>
     */
    public static function getAccessibleTaxonomies(): array
    {
        $taxonomies = get_taxonomies(['public' => true], 'objects');

        $exclude = [
            'nav_menu',
            'link_category',
            'post_format',
        ];

        foreach ($exclude as $slug) {
            unset($taxonomies[$slug]);
        }

        return $taxonomies;
    }

    /**
     * Standard choices for title & breadcrumb separators.
     *
     * @return array<string, string>
     */
    public static function choicesSeparator(): array
    {
        return [
            '-'  => '-',
            '–'  => '–',
            '—'  => '—',
            '·'  => '·',
            '•'  => '•',
            '*'  => '*',
            '|'  => '|',
            '~'  => '~',
            '«'  => '«',
            '»'  => '»',
            '<'  => '<',
            '>'  => '>',
        ];
    }

    /**
     * Standard choices for robots meta tags.
     *
     * @return array<string, string>
     */
    public static function choicesRobots(): array
    {
        return [
            'index'        => __('Index', 'laca'),
            'noindex'      => __('Noindex', 'laca'),
            'nofollow'     => __('Nofollow', 'laca'),
            'noarchive'    => __('Noarchive', 'laca'),
            'noimageindex' => __('No Image Index', 'laca'),
            'nosnippet'    => __('Nosnippet', 'laca'),
        ];
    }

    /**
     * Standard choices for redirection HTTP header codes.
     *
     * @return array<string, string>
     */
    public static function choicesRedirectionTypes(): array
    {
        return [
            '301' => __('301 Chuyển hướng vĩnh viễn (Permanent Move)', 'laca'),
            '302' => __('302 Chuyển hướng tạm thời (Temporary Move)', 'laca'),
            '307' => __('307 Chuyển hướng tạm thời chuẩn mới (Temporary Redirect)', 'laca'),
            '410' => __('410 Nội dung đã bị xoá vĩnh viễn (Content Deleted)', 'laca'),
            '451' => __('451 Nội dung không khả dụng vì lý do pháp lý (Unavailable For Legal Reasons)', 'laca'),
        ];
    }

    /**
     * Standard choices for source pattern comparison.
     *
     * @return array<string, string>
     */
    public static function choicesComparisonTypes(): array
    {
        return [
            'exact'    => __('Chính xác (Exact)', 'laca'),
            'contains' => __('Chứa (Contains)', 'laca'),
            'start'    => __('Bắt đầu bằng (Starts With)', 'laca'),
            'end'      => __('Kết thúc bằng (End With)', 'laca'),
            'regex'    => __('Biểu thức chính quy (Regex)', 'laca'),
        ];
    }

    /**
     * Standard phone types for Local SEO.
     *
     * @return array<string, string>
     */
    public static function choicesPhoneTypes(): array
    {
        return [
            'customer_support'    => __('Hỗ trợ khách hàng (Customer Support)', 'laca'),
            'technical_support'   => __('Hỗ trợ kỹ thuật (Technical Support)', 'laca'),
            'billing_support'     => __('Thanh toán (Billing)', 'laca'),
            'bill_payment'        => __('Chi trả (Bill Payment)', 'laca'),
            'sales'               => __('Kinh doanh (Sales)', 'laca'),
            'reservations'        => __('Đặt chỗ (Reservations)', 'laca'),
            'credit_card_support' => __('Thẻ tín dụng (Credit Card Support)', 'laca'),
            'emergency'           => __('Khẩn cấp (Emergency)', 'laca'),
            'baggage_tracking'    => __('Hành lý (Baggage Tracking)', 'laca'),
            'roadside_assistance' => __('Cứu hộ (Roadside Assistance)', 'laca'),
            'package_tracking'    => __('Tra cứu đơn (Package Tracking)', 'laca'),
        ];
    }

    /**
     * Additional organization info types for schema.
     *
     * @return array<string, string>
     */
    public static function choicesAdditionalOrgInfo(): array
    {
        return [
            ''              => __('— Chọn loại thông tin —', 'laca'),
            'taxID'         => __('Mã số thuế (Tax ID / VAT)', 'laca'),
            'vatID'         => __('Mã VAT (VAT ID)', 'laca'),
            'iso6523Code'   => __('Mã ISO 6523', 'laca'),
            'duns'          => __('Mã D-U-N-S', 'laca'),
            'leiCode'       => __('Mã LEI', 'laca'),
            'naics'         => __('Mã NAICS', 'laca'),
            'founder'       => __('Người sáng lập (Founder)', 'laca'),
            'foundingDate'  => __('Ngày thành lập (Founding Date - YYYY-MM-DD)', 'laca'),
            'dissolutionDate'=> __('Ngày giải thể (Dissolution Date)', 'laca'),
            'parentOrganization' => __('Công ty mẹ (Parent Organization URL/Name)', 'laca'),
        ];
    }

    /**
     * Schema rich snippet types for post types.
     *
     * @return array<string, string>
     */
    public static function choicesRichSnippetTypes(): array
    {
        return [
            'off'         => __('None', 'laca'),
            'article'     => __('Article', 'laca'),
            'book'        => __('Book', 'laca'),
            'course'       => __('Course', 'laca'),
            'creativework' => __('CreativeWork', 'laca'),
            'event'        => __('Event', 'laca'),
            'jobposting'  => __('Job Posting', 'laca'),
            'music'       => __('Music', 'laca'),
            'product'     => __('Product', 'laca'),
            'recipe'      => __('Recipe', 'laca'),
            'restaurant'  => __('Restaurant', 'laca'),
            'video'       => __('Video', 'laca'),
            'person'      => __('Person', 'laca'),
            'service'     => __('Service', 'laca'),
            'software'    => __('Software Application', 'laca'),
        ];
    }

    /**
     * Local business types.
     *
     * @return array<string, string>
     */
    public static function choicesBusinessTypes(): array
    {
        return [
            'Organization'           => __('Organization (Chung)', 'laca'),
            'LocalBusiness'          => __('LocalBusiness (Doanh nghiệp địa phương)', 'laca'),
            'AnimalShelter'          => __('Animal Shelter', 'laca'),
            'ArchiveOrganization'    => __('Archive Organization', 'laca'),
            'AutomotiveBusiness'     => __('Automotive Business', 'laca'),
            'ChildCare'              => __('Child Care', 'laca'),
            'Dentist'                => __('Dentist / Phòng khám nha khoa', 'laca'),
            'DryCleaningOrLaundry'   => __('Dry Cleaning or Laundry', 'laca'),
            'EmergencyService'       => __('Emergency Service', 'laca'),
            'EmploymentAgency'       => __('Employment Agency', 'laca'),
            'EntertainmentBusiness'  => __('Entertainment Business', 'laca'),
            'FinancialService'       => __('Financial Service', 'laca'),
            'FoodEstablishment'      => __('Food Establishment / Quán ăn - Nhà hàng', 'laca'),
            'Bakery'                 => __('Bakery / Tiệm bánh', 'laca'),
            'BarOrPub'               => __('Bar or Pub', 'laca'),
            'Brewery'                => __('Brewery / Xưởng bia', 'laca'),
            'CafeOrCoffeeShop'       => __('Cafe or Coffee Shop / Quán Cà phê - Xưởng rang', 'laca'),
            'FastFoodRestaurant'     => __('Fast Food Restaurant', 'laca'),
            'Restaurant'             => __('Restaurant / Nhà hàng', 'laca'),
            'Winery'                 => __('Winery / Hầm rượu', 'laca'),
            'GovernmentOffice'       => __('Government Office', 'laca'),
            'HealthAndBeautyBusiness'=> __('Health and Beauty Business / Spa - Thẩm mỹ', 'laca'),
            'BeautySalon'            => __('Beauty Salon', 'laca'),
            'DaySpa'                 => __('Day Spa', 'laca'),
            'HairSalon'              => __('Hair Salon', 'laca'),
            'HomeAndConstructionBusiness' => __('Home and Construction Business / Xây dựng', 'laca'),
            'InternetCafe'           => __('Internet Cafe', 'laca'),
            'LegalService'           => __('Legal Service / Luật sư', 'laca'),
            'Library'                => __('Library / Thư viện', 'laca'),
            'LodgingBusiness'        => __('Lodging Business / Khách sạn - Homestay', 'laca'),
            'Hotel'                  => __('Hotel', 'laca'),
            'Motel'                  => __('Motel', 'laca'),
            'MedicalBusiness'        => __('Medical Business / Y tế', 'laca'),
            'ProfessionalService'    => __('Professional Service / Dịch vụ chuyên nghiệp', 'laca'),
            'RadioStation'           => __('Radio Station', 'laca'),
            'RealEstateAgent'        => __('Real Estate Agent / Bất động sản', 'laca'),
            'RecyclingCenter'        => __('Recycling Center', 'laca'),
            'SelfStorage'            => __('Self Storage', 'laca'),
            'ShoppingCenter'         => __('Shopping Center', 'laca'),
            'SportsActivityLocation' => __('Sports Activity Location', 'laca'),
            'Store'                  => __('Store / Cửa hàng bán lẻ', 'laca'),
            'CoffeeStore'            => __('Coffee Store / Cửa hàng cà phê', 'laca'),
            'TelevisionStation'      => __('Television Station', 'laca'),
            'TouristInformationCenter' => __('Tourist Information Center', 'laca'),
        ];
    }

    /**
     * Choices for post types (slug => label).
     *
     * @return array<string, string>
     */
    public static function choicesPostTypes(): array
    {
        $choices = [];
        $pts = self::getAccessiblePostTypes();
        foreach ($pts as $slug => $obj) {
            $choices[$slug] = $obj->labels->name ?: $slug;
        }
        return $choices;
    }

    /**
     * Sanitize or format a Schema title nicely.
     */
    public static function sanitizeSchemaTitle(string $type): string
    {
        $types = [
            'Article'             => 'Article',
            'BlogPosting'         => 'Blog Posting',
            'NewsArticle'         => 'News Article',
            'Book'                => 'Book',
            'Course'              => 'Course',
            'Event'               => 'Event',
            'JobPosting'          => 'Job Posting',
            'Music'               => 'Music',
            'Person'              => 'Person',
            'Product'             => 'Product',
            'Recipe'              => 'Recipe',
            'Restaurant'          => 'Restaurant',
            'Service'             => 'Service',
            'SoftwareApplication' => 'Software Application',
            'VideoObject'         => 'Video',
        ];

        return $types[$type] ?? ucfirst($type);
    }
}

