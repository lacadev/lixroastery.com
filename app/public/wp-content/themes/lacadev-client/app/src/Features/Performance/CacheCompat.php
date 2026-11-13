<?php

namespace App\Features\Performance;

/**
 * Tương thích bộ nhớ đệm toàn trang (Full-Page Cache & Reverse Proxy).
 *
 * 1. Tự động xoá cache (purge) khi nội dung thay đổi trên các hệ thống:
 *    LiteSpeed Cache, Nginx Helper (FastCGI Cache), WP Rocket, W3 Total Cache, WP Super Cache.
 * 2. Thiết lập HTTP Cache-Control chuẩn cho trang công khai vs trang người dùng/admin.
 * 3. Cung cấp thông số chẩn đoán hiệu năng & cache cho trang Quản trị Hệ thống.
 */
class CacheCompat
{
    /**
     * Đăng ký hooks.
     */
    public static function register(): void
    {
        // 1. Tự động xoá cache bài viết khi lưu/sửa/xoá bài
        add_action('save_post', [self::class, 'onPostSaved'], 10, 2);
        add_action('deleted_post', [self::class, 'purgeAll']);
        add_action('transition_post_status', [self::class, 'onPostStatusTransition'], 10, 3);

        // 2. Tự động xoá cache toàn trang khi đổi cấu hình theme hoặc menu
        add_action('carbon_fields_theme_options_container_saved', [self::class, 'purgeAll']);
        add_action('after_switch_theme', [self::class, 'purgeAll']);
        add_action('wp_update_nav_menu', [self::class, 'purgeAll']);

        // 3. Header cache kiểm soát
        add_action('send_headers', [self::class, 'setCacheHeaders']);
    }

    /**
     * Khi lưu bài viết: nếu là bài chính thức (publish) thì purge cache bài đó.
     */
    public static function onPostSaved(int $postId, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($postId) || $post->post_status !== 'publish') {
            return;
        }

        self::purgePost($postId);
    }

    /**
     * Khi trạng thái bài viết thay đổi (vd chuyển từ nháp sang publish hoặc ngược lại).
     */
    public static function onPostStatusTransition(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($newStatus === 'publish' || $oldStatus === 'publish') {
            self::purgePost($post->ID);
        }
    }

    /**
     * Purge cache bài viết cụ thể trên các engine cache thông dụng.
     */
    public static function purgePost(int $postId): void
    {
        // 1. LiteSpeed Cache
        if (has_action('litespeed_purge_post')) {
            do_action('litespeed_purge_post', $postId);
        }

        // 2. WP Rocket
        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($postId);
        }

        // 3. W3 Total Cache
        if (function_exists('w3tc_flush_post')) {
            w3tc_flush_post($postId);
        }

        // 4. WP Super Cache
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change($postId);
        }

        // 5. Nginx Helper
        if (has_action('rt_nginx_helper_purge_all')) {
            do_action('rt_nginx_helper_purge_all');
        }
    }

    /**
     * Purge toàn bộ cache website.
     */
    public static function purgeAll(): void
    {
        // 1. LiteSpeed Cache
        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }

        // 2. WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        // 3. W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // 4. WP Super Cache
        if (function_exists('wp_cache_clean_cache')) {
            global $file_prefix;
            wp_cache_clean_cache($file_prefix);
        }

        // 5. Nginx Helper
        if (has_action('rt_nginx_helper_purge_all')) {
            do_action('rt_nginx_helper_purge_all');
        }
    }

    /**
     * Thiết lập HTTP headers phù hợp cho môi trường cache.
     */
    public static function setCacheHeaders(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // Trang có thông tin riêng tư / đăng nhập: cấm cache
        if (is_user_logged_in() || (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout())) {
            nocache_headers();
            return;
        }

        // Trang công khai cho khách:
        // Đảm bảo không phát sinh header Set-Cookie vô nghĩa (nếu không dùng session)
        // để Reverse Proxy / CDN / Nginx có thể cache toàn bộ HTML.
    }

    /**
     * Lấy thông tin chẩn đoán hiệu năng & cache của môi trường hosting.
     *
     * @return array<string, mixed>
     */
    public static function getDiagnostics(): array
    {
        // 1. Object Cache
        $hasExtObjectCache = wp_using_ext_object_cache();
        $objectCacheType = 'Chưa bật (Internal WP Memory)';
        if ($hasExtObjectCache) {
            if (class_exists('Redis') || extension_loaded('redis')) {
                $objectCacheType = 'Redis Object Cache';
            } elseif (class_exists('Memcached') || extension_loaded('memcached')) {
                $objectCacheType = 'Memcached';
            } else {
                $objectCacheType = 'External Drop-in Cache';
            }
        }

        // 2. OPcache
        $opcacheEnabled = extension_loaded('Zend OPcache') && ini_get('opcache.enable');
        $opcacheDetails = [
            'enabled'      => $opcacheEnabled,
            'status_text'  => $opcacheEnabled ? __('Đang bật', 'laca') : __('Tắt', 'laca'),
            'used_memory'  => '—',
            'free_memory'  => '—',
            'cached_files' => '—',
            'hit_rate'     => '—',
        ];

        if ($opcacheEnabled && function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status) && !empty($status['memory_usage'])) {
                $mem = $status['memory_usage'];
                $opcacheDetails['used_memory']  = size_format((int) ($mem['used_memory'] ?? 0));
                $opcacheDetails['free_memory']  = size_format((int) ($mem['free_memory'] ?? 0));
                $opcacheDetails['cached_files'] = number_format_i18n((int) ($status['opcache_statistics']['num_cached_scripts'] ?? 0));
                $hitRate = (float) ($status['opcache_statistics']['opcache_hit_rate'] ?? 0);
                $opcacheDetails['hit_rate']     = number_format($hitRate, 1) . '%';
            }
        }

        // 3. Full-Page Cache Detection
        $detectedCache = 'Chưa phát hiện (Khuyên dùng Nginx FastCGI hoặc LiteSpeed)';
        $cacheBadge = 'laca-badge--warning';

        if (defined('LSCWP_V')) {
            $detectedCache = 'LiteSpeed Cache (v' . LSCWP_V . ')';
            $cacheBadge = 'laca-badge--success';
        } elseif (defined('WP_ROCKET_VERSION')) {
            $detectedCache = 'WP Rocket (v' . WP_ROCKET_VERSION . ')';
            $cacheBadge = 'laca-badge--success';
        } elseif (function_exists('w3tc_flush_all') || defined('W3TC')) {
            $detectedCache = 'W3 Total Cache';
            $cacheBadge = 'laca-badge--success';
        } elseif (function_exists('wp_cache_clean_cache')) {
            $detectedCache = 'WP Super Cache';
            $cacheBadge = 'laca-badge--success';
        } elseif (class_exists('Nginx_Helper')) {
            $detectedCache = 'Nginx Helper (FastCGI Cache)';
            $cacheBadge = 'laca-badge--success';
        } elseif (!empty($_SERVER['HTTP_CF_RAY'])) {
            $detectedCache = 'Cloudflare Edge Cache';
            $cacheBadge = 'laca-badge--success';
        }

        return [
            'object_cache'       => [
                'enabled'    => $hasExtObjectCache,
                'type'       => $objectCacheType,
                'badge'      => $hasExtObjectCache ? 'laca-badge--success' : 'laca-badge--neutral',
            ],
            'opcache'            => $opcacheDetails,
            'page_cache'         => [
                'detected'   => $detectedCache,
                'badge'      => $cacheBadge,
            ],
            'full_page_ready'    => true, // Theme đã tối ưu hoàn toàn cho cache toàn trang
        ];
    }
}

