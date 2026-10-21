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
            'index'        => __('Index', 'lacadev'),
            'noindex'      => __('Noindex', 'lacadev'),
            'nofollow'     => __('Nofollow', 'lacadev'),
            'noarchive'    => __('Noarchive', 'lacadev'),
            'noimageindex' => __('No Image Index', 'lacadev'),
            'nosnippet'    => __('Nosnippet', 'lacadev'),
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
            'customer_support'    => __('Hỗ trợ khách hàng (Customer Support)', 'lacadev'),
            'technical_support'   => __('Hỗ trợ kỹ thuật (Technical Support)', 'lacadev'),
            'billing_support'     => __('Thanh toán (Billing)', 'lacadev'),
            'bill_payment'        => __('Chi trả (Bill Payment)', 'lacadev'),
            'sales'               => __('Kinh doanh (Sales)', 'lacadev'),
            'reservations'        => __('Đặt chỗ (Reservations)', 'lacadev'),
            'credit_card_support' => __('Thẻ tín dụng (Credit Card Support)', 'lacadev'),
            'emergency'           => __('Khẩn cấp (Emergency)', 'lacadev'),
            'baggage_tracking'    => __('Hành lý (Baggage Tracking)', 'lacadev'),
            'roadside_assistance' => __('Cứu hộ (Roadside Assistance)', 'lacadev'),
            'package_tracking'    => __('Tra cứu đơn (Package Tracking)', 'lacadev'),
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
            ''              => __('— Chọn loại thông tin —', 'lacadev'),
            'taxID'         => __('Mã số thuế (Tax ID / VAT)', 'lacadev'),
            'vatID'         => __('Mã VAT (VAT ID)', 'lacadev'),
            'iso6523Code'   => __('Mã ISO 6523', 'lacadev'),
            'duns'          => __('Mã D-U-N-S', 'lacadev'),
            'leiCode'       => __('Mã LEI', 'lacadev'),
            'naics'         => __('Mã NAICS', 'lacadev'),
            'founder'       => __('Người sáng lập (Founder)', 'lacadev'),
            'foundingDate'  => __('Ngày thành lập (Founding Date - YYYY-MM-DD)', 'lacadev'),
            'dissolutionDate'=> __('Ngày giải thể (Dissolution Date)', 'lacadev'),
            'parentOrganization' => __('Công ty mẹ (Parent Organization URL/Name)', 'lacadev'),
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
            'off'         => __('None', 'lacadev'),
            'article'     => __('Article', 'lacadev'),
            'book'        => __('Book', 'lacadev'),
            'course'      => __('Course', 'lacadev'),
            'event'       => __('Event', 'lacadev'),
            'jobposting'  => __('Job Posting', 'lacadev'),
            'music'       => __('Music', 'lacadev'),
            'product'     => __('Product', 'lacadev'),
            'recipe'      => __('Recipe', 'lacadev'),
            'restaurant'  => __('Restaurant', 'lacadev'),
            'video'       => __('Video', 'lacadev'),
            'person'      => __('Person', 'lacadev'),
            'service'     => __('Service', 'lacadev'),
            'software'    => __('Software Application', 'lacadev'),
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
            'Organization'           => __('Organization (Chung)', 'lacadev'),
            'LocalBusiness'          => __('LocalBusiness (Doanh nghiệp địa phương)', 'lacadev'),
            'AnimalShelter'          => __('Animal Shelter', 'lacadev'),
            'ArchiveOrganization'    => __('Archive Organization', 'lacadev'),
            'AutomotiveBusiness'     => __('Automotive Business', 'lacadev'),
            'ChildCare'              => __('Child Care', 'lacadev'),
            'Dentist'                => __('Dentist / Phòng khám nha khoa', 'lacadev'),
            'DryCleaningOrLaundry'   => __('Dry Cleaning or Laundry', 'lacadev'),
            'EmergencyService'       => __('Emergency Service', 'lacadev'),
            'EmploymentAgency'       => __('Employment Agency', 'lacadev'),
            'EntertainmentBusiness'  => __('Entertainment Business', 'lacadev'),
            'FinancialService'       => __('Financial Service', 'lacadev'),
            'FoodEstablishment'      => __('Food Establishment / Quán ăn - Nhà hàng', 'lacadev'),
            'Bakery'                 => __('Bakery / Tiệm bánh', 'lacadev'),
            'BarOrPub'               => __('Bar or Pub', 'lacadev'),
            'Brewery'                => __('Brewery / Xưởng bia', 'lacadev'),
            'CafeOrCoffeeShop'       => __('Cafe or Coffee Shop / Quán Cà phê - Xưởng rang', 'lacadev'),
            'FastFoodRestaurant'     => __('Fast Food Restaurant', 'lacadev'),
            'Restaurant'             => __('Restaurant / Nhà hàng', 'lacadev'),
            'Winery'                 => __('Winery / Hầm rượu', 'lacadev'),
            'GovernmentOffice'       => __('Government Office', 'lacadev'),
            'HealthAndBeautyBusiness'=> __('Health and Beauty Business / Spa - Thẩm mỹ', 'lacadev'),
            'BeautySalon'            => __('Beauty Salon', 'lacadev'),
            'DaySpa'                 => __('Day Spa', 'lacadev'),
            'HairSalon'              => __('Hair Salon', 'lacadev'),
            'HomeAndConstructionBusiness' => __('Home and Construction Business / Xây dựng', 'lacadev'),
            'InternetCafe'           => __('Internet Cafe', 'lacadev'),
            'LegalService'           => __('Legal Service / Luật sư', 'lacadev'),
            'Library'                => __('Library / Thư viện', 'lacadev'),
            'LodgingBusiness'        => __('Lodging Business / Khách sạn - Homestay', 'lacadev'),
            'Hotel'                  => __('Hotel', 'lacadev'),
            'Motel'                  => __('Motel', 'lacadev'),
            'MedicalBusiness'        => __('Medical Business / Y tế', 'lacadev'),
            'ProfessionalService'    => __('Professional Service / Dịch vụ chuyên nghiệp', 'lacadev'),
            'RadioStation'           => __('Radio Station', 'lacadev'),
            'RealEstateAgent'        => __('Real Estate Agent / Bất động sản', 'lacadev'),
            'RecyclingCenter'        => __('Recycling Center', 'lacadev'),
            'SelfStorage'            => __('Self Storage', 'lacadev'),
            'ShoppingCenter'         => __('Shopping Center', 'lacadev'),
            'SportsActivityLocation' => __('Sports Activity Location', 'lacadev'),
            'Store'                  => __('Store / Cửa hàng bán lẻ', 'lacadev'),
            'CoffeeStore'            => __('Coffee Store / Cửa hàng cà phê', 'lacadev'),
            'TelevisionStation'      => __('Television Station', 'lacadev'),
            'TouristInformationCenter' => __('Tourist Information Center', 'lacadev'),
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
}
