<?php

namespace App\Settings\LacaTools;

class Security
{
    protected $currentUser;
    protected $errorMessage = '';

    public function __construct()
    {
        $this->currentUser = wp_get_current_user();
        $this->applyOptions();
    }

    private function isEnabled(string $name): bool
    {
        $val = get_option('_' . $name);
        if ($val === 'yes' || $val === '1' || $val === 1 || $val === true) {
            return true;
        }
        // Class được khởi tạo lúc nạp theme — trước khi Carbon Fields đăng ký field thì gọi carbon_get_* sẽ fatal
        if (did_action('carbon_fields_fields_registered') && function_exists('carbon_get_theme_option')) {
            return (bool) carbon_get_theme_option($name);
        }
        return false;
    }

    public function applyOptions(): void
    {
        if ($this->isEnabled('disable_rest_api')) {
            $this->disableRestApi();
        }

        if ($this->isEnabled('disable_xml_rpc')) {
            $this->disableXmlRpc();
        }

        if ($this->isEnabled('disable_wp_embed')) {
            $this->disableWpEmbed();
        }

        if ($this->isEnabled('disable_x_pingback')) {
            $this->disableXPingback();
        }

        if ($this->isEnabled('enable_remove_wordpress_bloat')) {
            $this->removeWordPressBloat();
        }

        if ($this->isEnabled('enable_optimize_database_queries')) {
            $this->optimizeDatabaseQueries();
        }

        if ($this->isEnabled('enable_optimize_memory_usage')) {
            $this->optimizeMemoryUsage();
        }
    }

    public function disableRestApi()
    {
        add_filter('rest_authentication_errors', function ($result) {
            if (true === $result || is_wp_error($result)) {
                return $result;
            }

            if (!is_user_logged_in()) {
                return new \WP_Error('rest_not_logged_in', __('You are not logged in', 'laca'), ['status' => 401]);
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
        add_action('init', function () {
            wp_deregister_script('wp-embed');
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
            remove_filter('the_content', [GLOBALS['wp_embed'] ?? null, 'autoembed'], 8);
        });
    }

    public function disableXPingback()
    {
        add_filter('wp_headers', function (array $headers): array {
            if (isset($headers['X-Pingback'])) {
                unset($headers['X-Pingback']);
            }
            return $headers;
        });
    }

    /**
     * Loại bỏ bloat WordPress: RSD link, wlwmanifest, shortlink, generator tag,
     * adjacent posts links, REST API link header.
     */
    public function removeWordPressBloat()
    {
        add_action('init', function () {
            remove_action('wp_head', 'rsd_link');
            remove_action('wp_head', 'wlwmanifest_link');
            remove_action('wp_head', 'wp_shortlink_wp_head');
            remove_action('wp_head', 'wp_generator');
            remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
            remove_action('wp_head', 'rest_output_link_wp_head', 10);
            remove_action('template_redirect', 'rest_output_link_header', 11);
        });
    }

    /**
     * Giới hạn post revision (giữ tối đa 3) và tăng autosave interval lên 5 phút.
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
     * Tăng memory limit lên 256MB và bật PHP garbage collection.
     */
    public function optimizeMemoryUsage()
    {
        if (function_exists('ini_get') && (int) ini_get('memory_limit') < 256) {
            @ini_set('memory_limit', '256M');
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
