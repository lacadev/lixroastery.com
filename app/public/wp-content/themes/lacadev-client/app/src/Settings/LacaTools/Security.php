<?php

namespace App\Settings\LacaTools;

class Security
{
    protected $currentUser;

	protected $errorMessage = '';

    public function __construct()
    {
        $this->currentUser = wp_get_current_user();

        // Enhance website security
        if (get_option('_disable_rest_api') === 'yes') {
            $this->disableRestApi();
        }

        if (get_option('_disable_wp_embed') === 'yes') {
            $this->disableWpEmbed();
        }

        if (get_option('_disable_x_pingback') === 'yes') {
            $this->disableXPingback();
        }
    }

    public function disableRestApi()
    {
        add_filter( 'rest_authentication_errors', function( $result ) {
            if ( true === $result || is_wp_error( $result ) ) {
                return $result;
            }

            // Check if the user is logged in
            if ( ! is_user_logged_in() ) {
                // "\WP_Error" (có dấu \) — thiếu dấu \ sẽ khiến PHP tìm class
                // "App\Settings\LacaTools\WP_Error" (không tồn tại, vì file
                // này nằm trong namespace App\Settings\LacaTools) thay vì
                // class gốc, gây Fatal Error ngay khi filter này chạy.
                return new \WP_Error( 'rest_not_logged_in',  __('You are not logged in', 'laca'), array( 'status' => 401 ) );
            }

            return $result;
        });
    }

    public function disableXmlRpc()
    {
        // KHÔNG filter 'wp_xmlrpc_server_class' => false — core xmlrpc.php
        // gọi thẳng `new $wp_xmlrpc_server_class()`, trả false sẽ gây
        // Fatal Error khi có request tới /xmlrpc.php.
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('pre_update_option_enable_xmlrpc', '__return_false');
        add_filter('pre_option_enable_xmlrpc', '__return_zero');
    }

    public function disableWpEmbed()
    {
        add_action('init', function() {
            wp_deregister_script('wp-embed');
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
        });
    }

    public function disableXPingback()
    {
        add_filter('wp_headers', function($headers) {
            if (isset($headers['X-Pingback'])) {
                unset($headers['X-Pingback']);
            }
            return $headers;
        });
    }

    /**
     * Giới hạn số lượng post revision và tăng autosave interval
     */
    public function optimizeDatabaseQueries()
    {
        if (!defined('WP_POST_REVISIONS')) {
            define('WP_POST_REVISIONS', 3);
        }
        if (!defined('AUTOSAVE_INTERVAL')) {
            define('AUTOSAVE_INTERVAL', 300); // 5 phút
        }
        if (function_exists('wp_cache_set')) {
            wp_cache_set('performance_optimized', true, 'theme', 3600);
        }
    }

    /**
     * Log các truy vấn SQL chậm để phát hiện truy vấn bất thường
     */
    public function optimizeSqlQueries($query)
    {
        if (strpos($query, 'SELECT') === 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $start_time = microtime(true);
                register_shutdown_function(function () use ($start_time, $query) {
                    $execution_time = microtime(true) - $start_time;
                    if ($execution_time > 0.5) {
                        error_log("Slow query detected: {$execution_time}s - {$query}");
                    }
                });
            }
        }
        return $query;
    }

    /**
     * Tăng memory limit và bật garbage collection
     */
    public function optimizeMemoryUsage()
    {
        if (function_exists('ini_get') && ini_get('memory_limit') < 256) {
            ini_set('memory_limit', '256M');
        }
        if (function_exists('gc_enable')) {
            gc_enable();
        }
    }

    /**
     * Dọn dẹp bộ nhớ cuối trang
     */
    public function cleanupMemory()
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

}