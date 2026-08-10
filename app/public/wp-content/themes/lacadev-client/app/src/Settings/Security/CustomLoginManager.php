<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Login URL
 *
 * Ẩn /wp-login.php và /wp-admin, phục vụ login qua slug tùy chỉnh.
 * Port từ Foxblock_CustomLogin — options đổi tên sang laca_*.
 *
 * Options:
 *   laca_login_slug          — slug tùy chỉnh (vd: "my-login")
 *   laca_enable_custom_login — 1 | 0
 */
class CustomLoginManager
{
    private string $slug;
    private bool   $enabled;

    public function __construct()
    {
        $raw  = get_option('laca_login_slug', '');
        $this->slug    = sanitize_title(trim((string) $raw, '/'));
        $this->enabled = (bool) get_option('laca_enable_custom_login', 0);

        if (empty($this->slug)) {
            $this->enabled = false;
        }

        if ($this->enabled) {
            // Class này được new() lên từ hook 'init' (xem hooks.php), tức là
            // đã QUA hook 'plugins_loaded' của request này — add_action vào
            // 'plugins_loaded' ở đây sẽ không bao giờ được gọi. Gọi trực tiếp.
            $this->setupHooks();
        }
    }

    public function setupHooks(): void
    {
        add_action('init',              [$this, 'interceptDefaultLogin']);
        add_action('template_redirect', [$this, 'processCustomLogin']);

        add_filter('site_url',         [$this, 'filterUrls'], 10, 4);
        add_filter('network_site_url', [$this, 'filterUrls'], 10, 3);
        add_filter('wp_redirect',      [$this, 'filterRedirect'], 10, 2);
    }

    public function interceptDefaultLogin(): void
    {
        $reqUri    = $_SERVER['REQUEST_URI'] ?? '';
        $path      = untrailingslashit(parse_url($reqUri, PHP_URL_PATH));
        $homePath  = untrailingslashit(parse_url(home_url(), PHP_URL_PATH));
        $relative  = str_replace($homePath, '', $path);
        $phpSelf   = basename($_SERVER['PHP_SELF'] ?? '');

        $isLoginPath = (
            $phpSelf === 'wp-login.php' ||
            str_contains($relative, '/wp-login') ||
            str_contains($relative, '/wp-signup')
        );

        if ($isLoginPath && !is_user_logged_in()) {
            if ($this->isCli()) return;
            $this->do404();
        }

        // Block unauthenticated /wp-admin silently
        if (($phpSelf === 'wp-admin' || str_starts_with($relative, '/wp-admin')) && !is_user_logged_in()) {
            if ($this->isCli()) return;
            if (str_contains($reqUri, 'admin-ajax.php') || str_contains($reqUri, 'admin-post.php')) return;
            $this->do404();
        }
    }

    public function processCustomLogin(): void
    {
        $reqUri   = $_SERVER['REQUEST_URI'] ?? '';
        $path     = untrailingslashit(parse_url($reqUri, PHP_URL_PATH));
        $homePath = untrailingslashit(parse_url(home_url(), PHP_URL_PATH));
        $relative = str_replace($homePath, '', $path);

        if ($relative === '/' . $this->slug) {
            global $pagenow, $error, $interim_login, $action, $user_login;
            $pagenow             = 'wp-login.php';
            $_SERVER['PHP_SELF'] = '/wp-login.php';

            status_header(200);
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }

    /** @param string $url @param string $path @param mixed $scheme @param mixed $blogId */
    public function filterUrls($url, $path, $scheme, $blogId = null): string
    {
        if (str_contains($url, 'wp-login.php')) {
            $url = preg_replace('/wp-login\\.php/', $this->slug, $url, 1);
        }
        return $url;
    }

    public function filterRedirect(string $location, int $status): string
    {
        if (!str_contains($location, 'wp-login.php')) {
            return $location;
        }

        $reqUri   = $_SERVER['REQUEST_URI'] ?? '';
        $path     = untrailingslashit(parse_url($reqUri, PHP_URL_PATH));
        $homePath = untrailingslashit(parse_url(home_url(), PHP_URL_PATH));
        $relative = str_replace($homePath, '', $path);

        if ($relative === '/' . $this->slug) {
            return preg_replace('/wp-login\\.php/', $this->slug, $location, 1);
        }

        // All other contexts → homepage (never reveal the slug)
        return home_url('/');
    }

    private function do404(): void
    {
        global $wp_query;
        if (!isset($wp_query)) {
            status_header(404);
            nocache_headers();
            exit;
        }
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        require get_404_template();
        exit;
    }

    private function isCli(): bool
    {
        return (defined('DOING_AJAX') && DOING_AJAX)
            || (defined('DOING_CRON') && DOING_CRON)
            || (defined('WP_CLI')    && WP_CLI);
    }
}
