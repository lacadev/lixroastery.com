<?php
/**
 * PWA (Progressive Web App) Support
 *
 * Thêm Web App Manifest, meta tags PWA, và offline fallback page.
 *
 * @package LacaDev
 * @since   2.0.0
 */

class ThemePWA
{
    /**
     * Khởi tạo các hooks PWA
     */
    public static function init(): void
    {
        // Serve manifest.json động
        add_action('init', [self::class, 'add_manifest_rewrite']);
        add_filter('query_vars', [self::class, 'add_query_vars']);
        add_action('template_redirect', [self::class, 'serve_manifest']);

        // Flush rewrite rules 1 lần sau khi theme activate/update
        add_action('after_switch_theme', [self::class, 'flush_once']);
        // Cũng flush nếu chưa từng flush cho PWA rule
        add_action('init', [self::class, 'maybe_flush_once'], 20);

        // Add PWA meta tags và manifest link vào <head>
        add_action('wp_head', [self::class, 'add_pwa_head_tags'], 1);
    }

    /**
     * Flush 1 lần khi activate theme
     */
    public static function flush_once(): void
    {
        \flush_rewrite_rules();
    }

    /**
     * Flush 1 lần nếu option chưa set (khi mới deploy pwa.php)
     */
    public static function maybe_flush_once(): void
    {
        if (!get_option('laca_pwa_rewrite_flushed')) {
            self::add_manifest_rewrite();
            \flush_rewrite_rules();
            update_option('laca_pwa_rewrite_flushed', true, false);
        }
    }

    /**
     * Thêm rewrite rule cho /manifest.json
     */
    public static function add_manifest_rewrite(): void
    {
        add_rewrite_rule('^manifest\.json$', 'index.php?laca_pwa_manifest=1', 'top');
    }

    public static function add_query_vars(array $vars): array
    {
        $vars[] = 'laca_pwa_manifest';
        return $vars;
    }

    /**
     * Trả về manifest.json động với dữ liệu từ WordPress
     */
    public static function serve_manifest(): void
    {
        if (!get_query_var('laca_pwa_manifest')) {
            return;
        }

        $siteUrl  = home_url();
        $siteName = get_bloginfo('name');
        $tagline  = get_bloginfo('description');

        // Lấy custom logo nếu có
        $iconUrl = '';
        if (has_custom_logo()) {
            $logoId  = get_theme_mod('custom_logo');
            $logoSrc = wp_get_attachment_image_url($logoId, 'thumbnail');
            if ($logoSrc) {
                $iconUrl = $logoSrc;
            }
        }

        // Fallback icon từ theme
        if (!$iconUrl) {
            $iconUrl = get_template_directory_uri() . '/dist/images/icon-192.png';
        }

        $manifest = [
            'name'             => $siteName,
            'short_name'       => substr($siteName, 0, 12),
            'description'      => $tagline,
            'start_url'        => $siteUrl . '/',
            'display'          => 'browser',
            'orientation'      => 'portrait-primary',
            'background_color' => '#ffffff',
            'theme_color'      => '#2563eb',
            'lang'             => get_locale(),
            'icons'            => [
                [
                    'src'   => $iconUrl,
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => str_replace('icon-192', 'icon-512', $iconUrl),
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                ],
            ],
            'screenshots'      => [],
            'shortcuts'        => [
                [
                    'name'        => 'Blog',
                    'url'         => $siteUrl . '/blog/',
                    'description' => 'Xem bài viết mới nhất',
                ],
            ],
            'prefer_related_applications' => false,
        ];

        // Thêm shortcut "Dịch vụ" nếu có trang dịch vụ
        $servicePage = get_page_by_path('dich-vu');
        if ($servicePage) {
            $manifest['shortcuts'][] = [
                'name'        => 'Dịch vụ',
                'url'         => get_permalink($servicePage->ID),
                'description' => 'Các dịch vụ của chúng tôi',
            ];
        }

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600'); // Cache 1 giờ
        echo wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Thêm PWA meta tags vào <head>
     */
    public static function add_pwa_head_tags(): void
    {
        // Chỉ thêm trên frontend
        if (is_admin()) {
            return;
        }

        $siteUrl   = home_url();
        $siteName  = get_bloginfo('name');
        $iconUrl   = get_template_directory_uri() . '/dist/images/icon-192.png';
        $themeColor = '#2563eb';
        ?>
        <!-- PWA Manifest -->
        <link rel="manifest" href="<?php echo esc_url($siteUrl . '/manifest.json'); ?>">

        <!-- PWA Meta Tags -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($siteName); ?>">
        <meta name="theme-color" content="<?php echo esc_attr($themeColor); ?>">

        <!-- Apple Touch Icon -->
        <link rel="apple-touch-icon" href="<?php echo esc_url($iconUrl); ?>">
        <?php
    }
}

// Khởi tạo
ThemePWA::init();
