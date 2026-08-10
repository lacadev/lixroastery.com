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

        // Advanced caching
        add_action('template_redirect', [self::class, 'set_cache_headers']);

        // Database optimizations
        add_action('init', [self::class, 'optimize_database_queries']);
        add_filter('posts_request', [self::class, 'optimize_sql_queries']);

        // Memory management
        add_action('wp_head', [self::class, 'optimize_memory_usage']);
        add_action('wp_footer', [self::class, 'cleanup_memory'], 999);

        // Image optimizations
        add_filter('wp_get_attachment_image_attributes', [self::class, 'optimize_images'], 10, 3);
        add_filter('the_content', [self::class, 'optimize_content_images']);

        // Advanced compression
        add_action('template_redirect', [self::class, 'enable_compression']);

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

        // Remove login errors
        add_filter('login_errors', '__return_null');
    }

    /**
     * Set advanced cache headers
     */
    public static function set_cache_headers()
    {
        if (!is_admin() && !is_user_logged_in()) {
            // Cache static assets for 1 year
            if (preg_match('/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff|woff2|ttf|eot|ico)$/', $_SERVER['REQUEST_URI'])) {
                header('Cache-Control: public, max-age=31536000, immutable');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
                header('Pragma: public');
            }

            // Cache HTML for 1 hour
            else {
                header('Cache-Control: public, max-age=3600, must-revalidate');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
                header('Vary: Accept-Encoding');
            }
        }
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
        // Add loading and decoding attributes
        $attr['loading'] = 'lazy';
        $attr['decoding'] = 'async';

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
        // Add lazy loading to content images
        $content = preg_replace('/<img((?![^>]*loading)[^>]*)>/', '<img$1 loading="lazy" decoding="async">', $content);

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
     * Enable compression
     */
    public static function enable_compression()
    {
        if (!is_admin()) {
            // Enable gzip compression
            if (function_exists('gzencode') && !ob_get_contents()) {
                ob_start('ob_gzhandler');
            }
        }
    }

    /**
     * Register service worker for caching
     */
    public static function register_service_worker()
    {
        if (!is_admin() && !is_user_logged_in()) {
            $sw_path = get_template_directory() . '/dist/sw.js';

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
                    'swUrl' => get_template_directory_uri() . '/dist/sw.js',
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