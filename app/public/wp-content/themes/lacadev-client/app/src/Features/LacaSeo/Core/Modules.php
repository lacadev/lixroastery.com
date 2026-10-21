<?php

namespace App\Features\LacaSeo\Core;

defined('ABSPATH') || exit;

/**
 * Manages active SEO modules.
 */
class Modules
{
    public const OPTION_KEY = 'laca_seo_modules';

    /**
     * All available modules in Laca SEO.
     */
    public const DEFINITIONS = [
        'general' => [
            'id'          => 'general',
            'title'       => 'Cài đặt chung (General Settings)',
            'desc'        => 'Links, Images, Webmaster Tools, Analytics, robots.txt, .htaccess.',
            'phase'       => 1,
            'default'     => true,
            'core'        => true,
        ],
        'titles' => [
            'id'          => 'titles',
            'title'       => 'Tiêu đề & Thẻ Meta (Titles & Meta)',
            'desc'        => 'Robots global, OpenGraph, Twitter, bài viết, trang, sản phẩm, taxonomy.',
            'phase'       => 1,
            'default'     => true,
            'core'        => true,
        ],
        'sitemap' => [
            'id'          => 'sitemap',
            'title'       => 'Sơ đồ trang XML (Sitemap)',
            'desc'        => 'Sơ đồ trang XML tự động cập nhật cho Google, Bing.',
            'phase'       => 3,
            'default'     => true,
            'core'        => false,
        ],
        'rich-snippet' => [
            'id'          => 'rich-snippet',
            'title'       => 'Dữ liệu có cấu trúc (Schema / Rich Snippet)',
            'desc'        => 'JSON-LD Schema cho Article, Product, Organization, Breadcrumb, v.v.',
            'phase'       => 3,
            'default'     => true,
            'core'        => false,
        ],
        'woocommerce' => [
            'id'          => 'woocommerce',
            'title'       => 'WooCommerce SEO',
            'desc'        => 'Tối ưu URL, breadcrumb, schema sản phẩm, thương hiệu, loại bỏ base thừa.',
            'phase'       => 3,
            'default'     => true,
            'core'        => false,
        ],
        'instant-indexing' => [
            'id'          => 'instant-indexing',
            'title'       => 'Index tức thì (Instant Indexing / IndexNow)',
            'desc'        => 'Tự động gửi URL bài mới hoặc cập nhật tới Bing, Yandex ngay lập tức.',
            'phase'       => 3,
            'default'     => true,
            'core'        => false,
        ],
        'llms-txt' => [
            'id'          => 'llms-txt',
            'title'       => 'Tệp llms.txt (AI Crawlers)',
            'desc'        => 'Cung cấp tệp chuẩn cho các mô hình AI/LLM lập chỉ mục nội dung trang web.',
            'phase'       => 3,
            'default'     => true,
            'core'        => false,
        ],
        'redirections' => [
            'id'          => 'redirections',
            'title'       => 'Chuyển hướng (Redirections 301/302)',
            'desc'        => 'Quản lý danh sách URL chuyển hướng tự động và tuỳ biến.',
            'phase'       => 4,
            'default'     => true,
            'core'        => false,
        ],
        '404-monitor' => [
            'id'          => '404-monitor',
            'title'       => 'Theo dõi lỗi 404 (404 Monitor)',
            'desc'        => 'Ghi nhận các liên kết lỗi 404 mà người dùng hoặc bot truy cập.',
            'phase'       => 4,
            'default'     => true,
            'core'        => false,
        ],
        'local-seo' => [
            'id'          => 'local-seo',
            'title'       => 'Local SEO & Multi-locations',
            'desc'        => 'Thông tin doanh nghiệp địa phương, bản đồ, nhiều địa chỉ chi nhánh.',
            'phase'       => 5,
            'default'     => false,
            'core'        => false,
        ],
        'image-seo' => [
            'id'          => 'image-seo',
            'title'       => 'Tối ưu hình ảnh (Image SEO)',
            'desc'        => 'Tự động bổ sung thẻ alt, title thiếu cho hình ảnh trong nội dung bài viết.',
            'phase'       => 6,
            'default'     => false,
            'core'        => false,
        ],
        'link-counter' => [
            'id'          => 'link-counter',
            'title'       => 'Đếm liên kết (Link Counter)',
            'desc'        => 'Đếm số lượng internal link, external link trong mỗi bài viết.',
            'phase'       => 6,
            'default'     => true,
            'core'        => false,
        ],
        'role-manager' => [
            'id'          => 'role-manager',
            'title'       => 'Phân quyền SEO (Role Manager)',
            'desc'        => 'Kiểm soát vai trò thành viên nào được phép sửa cài đặt và meta SEO.',
            'phase'       => 6,
            'default'     => false,
            'core'        => false,
        ],
        'seo-analysis' => [
            'id'          => 'seo-analysis',
            'title'       => 'Phân tích SEO tổng thể (SEO Analyzer)',
            'desc'        => 'Kiểm tra nhanh các tiêu chuẩn kỹ thuật SEO toàn bộ website tại chỗ.',
            'phase'       => 6,
            'default'     => false,
            'core'        => false,
        ],
    ];

    /**
     * Get active module list.
     *
     * @return string[]
     */
    public static function getActive(): array
    {
        $saved = get_option(self::OPTION_KEY, null);

        if (!is_array($saved)) {
            // Default active
            $defaults = [];
            foreach (self::DEFINITIONS as $id => $def) {
                if ($def['default']) {
                    $defaults[] = $id;
                }
            }
            return $defaults;
        }

        return $saved;
    }

    /**
     * Check if a module is active.
     */
    public static function isActive(string $module): bool
    {
        // Core modules are always active
        if (isset(self::DEFINITIONS[$module]) && self::DEFINITIONS[$module]['core']) {
            return true;
        }

        return in_array($module, self::getActive(), true);
    }

    /**
     * Update active module list.
     *
     * @param string[] $modules
     */
    public static function setActive(array $modules): bool
    {
        $sanitized = [];
        foreach ($modules as $m) {
            $m = sanitize_key($m);
            if (isset(self::DEFINITIONS[$m])) {
                $sanitized[] = $m;
            }
        }

        // Ensure core modules
        if (!in_array('general', $sanitized, true)) {
            $sanitized[] = 'general';
        }
        if (!in_array('titles', $sanitized, true)) {
            $sanitized[] = 'titles';
        }

        return update_option(self::OPTION_KEY, array_values(array_unique($sanitized)));
    }
}
