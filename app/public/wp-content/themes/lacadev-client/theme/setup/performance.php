<?php
/**
 * Advanced Performance Optimizations
 * 
 * Comprehensive performance enhancements for maximum PageSpeed Insights scores
 *
 * @package lacadev
 * @since 1.0.0
 */

class ThemePerformance
{

    /**
     * Initialize performance optimizations
     */
    public static function init()
    {
        // Core WordPress optimizations
        add_action('init', [self::class, 'remove_wordpress_bloat']);

        // KHÔNG tự set Cache-Control cho HTML và KHÔNG ob_start('ob_gzhandler'):
        // header/nén do web server (LiteSpeed) + plugin cache quản lý. Theme can
        // thiệp vào 2 tầng này từng làm trình duyệt giữ trang lỗi 1h và đưa dữ
        // liệu gzip nhị phân vào buffer của plugin cache.

        // Database optimizations
        add_action('init', [self::class, 'optimize_database_queries']);
        add_filter('posts_request', [self::class, 'optimize_sql_queries']);

        // Memory management
        add_action('wp_head', [self::class, 'optimize_memory_usage']);
        add_action('wp_footer', [self::class, 'cleanup_memory'], 999);

        // Image optimizations
        add_filter('wp_get_attachment_image_attributes', [self::class, 'optimize_images'], 10, 3);
        add_filter('the_content', [self::class, 'optimize_content_images']);

        // Service Worker
        add_action('wp_footer', [self::class, 'register_service_worker']);

        // Performance monitoring
        add_action('wp_footer', [self::class, 'add_performance_monitoring']);
    }

    /**
     * Remove WordPress bloat for better performance
     */
    public static function remove_wordpress_bloat()
    {
        // Remove unnecessary WordPress features
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        // wp_generator đã được remove trong security.php — không duplicate ở đây
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
        remove_action('wp_head', 'wp_shortlink_wp_head', 10);
        remove_action('wp_head', 'start_post_rel_link');
        remove_action('wp_head', 'index_rel_link');
        remove_action('wp_head', 'parent_post_rel_link');

        // Optimize heartbeat
        add_filter('heartbeat_settings', function ($settings) {
            $settings['interval'] = 120; // 2 minutes instead of 15 seconds
            return $settings;
        });

        // Ẩn thông báo lỗi đăng nhập CHI TIẾT (tránh dò email/username hợp
        // lệ) nhưng vẫn phải trả về 1 câu chung — __return_null() xoá trắng
        // hoàn toàn, khiến form "shake" báo có lỗi mà không hiển thị gì,
        // trông như bug thay vì cố ý ẩn thông tin.
        add_filter('login_errors', function () {
            return __('That key doesn\'t seem to work. Please check and try again.', 'laca');
        });
    }


    /**
     * Optimize database queries
     */
    public static function optimize_database_queries()
    {
        // Reduce post revisions
        if (!defined('WP_POST_REVISIONS')) {
            define('WP_POST_REVISIONS', 3);
        }

        // Optimize autosave interval
        if (!defined('AUTOSAVE_INTERVAL')) {
            define('AUTOSAVE_INTERVAL', 300); // 5 minutes
        }

        // Enable object caching
        if (function_exists('wp_cache_set')) {
            wp_cache_set('performance_optimized', true, 'theme', 3600);
        }
    }

    /**
     * Optimize SQL queries
     */
    public static function optimize_sql_queries($query)
    {
        // Add query optimization if needed
        // For production, we don't want to log slow queries per request here 
        // as it adds overhead. Use Query Monitor or server logs instead.
        return $query;
    }

    /**
     * Optimize memory usage
     */
    public static function optimize_memory_usage()
    {
        // Increase memory limit if needed
        if (function_exists('ini_get') && ini_get('memory_limit') < 256) {
            ini_set('memory_limit', '256M');
        }

        // Enable garbage collection
        if (function_exists('gc_enable')) {
            gc_enable();
        }
    }

    /**
     * Cleanup memory at the end
     */
    public static function cleanup_memory()
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Optimize images with advanced attributes
     */
    public static function optimize_images($attr, $attachment, $size)
    {
        // Add loading and decoding attributes (không ghi đè nếu đã chỉ định loading hoặc fetchpriority="high")
        if (empty($attr['loading'])) {
            if (isset($attr['fetchpriority']) && $attr['fetchpriority'] === 'high') {
                $attr['loading'] = 'eager';
            } else {
                $attr['loading'] = 'lazy';
            }
        }
        if (empty($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }

        // Add better alt text if missing
        if (empty($attr['alt'])) {
            $attr['alt'] = get_the_title($attachment->ID) ?: 'Image';
        }

        // Add dimensions if missing
        if (empty($attr['width']) || empty($attr['height'])) {
            $image_meta = wp_get_attachment_metadata($attachment->ID);
            if (!empty($image_meta['width']) && !empty($image_meta['height'])) {
                $attr['width'] = $image_meta['width'];
                $attr['height'] = $image_meta['height'];
            }
        }

        return $attr;
    }

    /**
     * Optimize content images
     */
    public static function optimize_content_images($content)
    {
        // Add lazy loading to content images (không ghi đè nếu đã có loading hoặc fetchpriority="high")
        $content = preg_replace_callback('/<img([^>]+)>/i', function ($matches) {
            $tag = $matches[0];
            $attrs = $matches[1];

            // Nếu đã có loading="..." thì giữ nguyên
            if (stripos($attrs, 'loading=') !== false) {
                return $tag;
            }

            // Nếu có fetchpriority="high" thì gắn loading="eager"
            if (stripos($attrs, 'fetchpriority="high"') !== false || stripos($attrs, "fetchpriority='high'") !== false) {
                return '<img' . $attrs . ' loading="eager" decoding="async">';
            }

            return '<img' . $attrs . ' loading="lazy" decoding="async">';
        }, $content);

        // Add responsive images if missing
        $content = preg_replace_callback('/<img([^>]+)>/', function ($matches) {
            $img_tag = $matches[0];
            if (strpos($img_tag, 'srcset') === false) {
                // Try to add responsive images here if needed
                return $img_tag;
            }
            return $img_tag;
        }, $content);

        return $content;
    }


    /**
     * Register service worker for caching
     */
    public static function register_service_worker()
    {
        if (!is_admin() && !is_user_logged_in()) {
            // get_template_directory() luôn trỏ vào theme PARENT (lacadev-client)
            // — theme này KHÔNG có thư mục dist/. File dist/sw.js thật đã build
            // đúng chỉ nằm ở theme CHILD (get_stylesheet_directory()) — dùng
            // get_template_directory() khiến file_exists() luôn false, toàn bộ
            // Service Worker (cache/offline/PWA) chết hoàn toàn trên mọi site.
            $sw_path = get_stylesheet_directory() . '/dist/sw.js';

            // Only register if SW file exists
            if (!file_exists($sw_path)) {
                return;
            }

            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script(
                    'laca-sw-register',
                    get_template_directory_uri() . '/resources/scripts/theme/service-worker-register.js',
                    [],
                    wp_get_theme()->get('Version'),
                    true
                );
                wp_localize_script('laca-sw-register', 'swConfig', [
                    'swUrl' => get_stylesheet_directory_uri() . '/dist/sw.js',
                    'debug' => defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false',
                ]);
            });
        }
    }

    /**
     * Add performance monitoring (chỉ chạy khi WP_DEBUG = true)
     * Tắt hoàn toàn trên production để tránh expose metrics và console clutter
     */
    public static function add_performance_monitoring()
    {
        if (!is_admin() && defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script(
                    'laca-web-vitals',
                    get_template_directory_uri() . '/resources/scripts/theme/web-vitals.js',
                    [],
                    wp_get_theme()->get('Version'),
                    true
                );
            });
        }
    }
}

// Initialize advanced performance optimizations
ThemePerformance::init();