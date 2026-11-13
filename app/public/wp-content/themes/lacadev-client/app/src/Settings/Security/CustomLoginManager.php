<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Login URL
 *
 * Ẩn /wp-login.php và /wp-admin, phục vụ login qua slug tùy chỉnh.
 * Kiến trúc port trực tiếp từ plugin WPS Hide Login (đã gỡ bỏ khỏi site vì
 * xung đột với tính năng này) — dùng cùng kỹ thuật "đánh lừa REQUEST_URI"
 * để request vào /wp-login.php thật đi qua pipeline 404 bình thường của
 * theme, thay vì set_404()/exit thủ công quá sớm (trước khi $wp_query sẵn sàng).
 *
 * Options:
 *   laca_login_slug          — slug tùy chỉnh (vd: "my-login")
 *   laca_enable_custom_login — 1 | 0
 */
class CustomLoginManager
{
    private string $slug;
    private bool   $enabled;
    private bool   $isRealLoginRequest = false;

    public function __construct()
    {
        $raw  = get_option('laca_login_slug', '');
        $this->slug    = sanitize_title(trim((string) $raw, '/'));
        $this->enabled = (bool) get_option('laca_enable_custom_login', 0);

        if (empty($this->slug)) {
            $this->enabled = false;
        }

        if ($this->enabled) {
            // Phải chạy ngay bây giờ (đang ở giữa 'init'), trước khi wp() parse
            // main query — giống thời điểm 'plugins_loaded' priority 9999 của WPS.
            $this->detectRequest();
            $this->setupHooks();
        }
    }

    private function setupHooks(): void
    {
        add_action('wp_loaded', [$this, 'onWpLoaded']);

        add_filter('site_url',         [$this, 'filterUrls'], 10, 4);
        add_filter('network_site_url', [$this, 'filterUrls'], 10, 3);
        add_filter('wp_redirect',      [$this, 'filterRedirect'], 10, 2);
        add_filter('login_url',        [$this, 'filterLoginUrl'], 10, 3);
    }

    /**
     * Phát hiện request nhắm vào wp-login.php/wp-signup thật hoặc vào slug tùy
     * chỉnh, rồi đánh dấu $pagenow tương ứng — KHÔNG tự render/redirect ở đây.
     */
    private function detectRequest(): void
    {
        global $pagenow;

        $rawUri = $_SERVER['REQUEST_URI'] ?? '';
        $path   = parse_url(rawurldecode($rawUri), PHP_URL_PATH) ?: '';

        $isRealLoginFile = str_contains($rawUri, 'wp-login.php')
            || str_contains($rawUri, 'wp-signup.php')
            || str_contains($rawUri, 'wp-register.php')
            || untrailingslashit($path) === site_url('wp-login', 'relative');

        if ($isRealLoginFile && !is_admin()) {
            // Đánh lừa REQUEST_URI thành 1 path chắc chắn không tồn tại, để
            // main query 404 tự nhiên qua pipeline theme bình thường ở wp_loaded.
            $this->isRealLoginRequest = true;
            $_SERVER['REQUEST_URI']   = $this->userTrailingslashit('/' . str_repeat('-/', 10));
            $pagenow                  = 'index.php';

            return;
        }

        if (untrailingslashit($path) === home_url($this->slug, 'relative')) {
            $_SERVER['SCRIPT_NAME'] = $this->slug;
            $pagenow                = 'wp-login.php';
        }
    }

    public function onWpLoaded(): void
    {
        global $pagenow;

        $path = parse_url(rawurldecode($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        if (is_admin() && !is_user_logged_in() && !$this->isCli() && $pagenow !== 'admin-post.php') {
            wp_safe_redirect($this->newRedirectUrl());
            exit;
        }

        if ($pagenow === 'wp-login.php'
            && $path !== $this->userTrailingslashit($path)
            && get_option('permalink_structure')
        ) {
            // Chuẩn hoá trailing slash cho URL login tuỳ chỉnh.
            wp_safe_redirect(
                $this->userTrailingslashit($this->newLoginUrl())
                . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')
            );
            exit;
        }

        if ($this->isRealLoginRequest) {
            $this->loadThemeTemplate();

            return;
        }

        if ($pagenow === 'wp-login.php') {
            if (is_user_logged_in() && empty($_REQUEST['action'])) {
                wp_safe_redirect(admin_url());
                exit;
            }

            require ABSPATH . 'wp-login.php';
            exit;
        }
    }

    /** Buộc WP đi qua pipeline template/404 bình thường của theme thay vì tự set_404(). */
    private function loadThemeTemplate(): void
    {
        global $pagenow;
        $pagenow = 'index.php';

        if (!defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', true);
        }

        wp();
        require ABSPATH . WPINC . '/template-loader.php';
        exit;
    }

    /** @param string $url @param string $path @param mixed $scheme @param mixed $blogId */
    public function filterUrls($url, $path, $scheme, $blogId = null): string
    {
        return $this->replaceLoginPhp($url);
    }

    public function filterRedirect(string $location, int $status): string
    {
        return $this->replaceLoginPhp($location);
    }

    public function filterLoginUrl(string $loginUrl, string $redirect, $forceReauth): string
    {
        // Tránh lộ wp-login.php thật qua login_url() trên trang 404.
        return is_404() ? '#' : $loginUrl;
    }

    private function replaceLoginPhp(string $url): string
    {
        if (!str_contains($url, 'wp-login.php')) {
            return $url;
        }

        // Không thay thế nếu referer đang là wp-login.php thật (truy cập trực
        // tiếp/biết URL cũ) — giữ hành vi gốc để không phá luồng đang dở.
        if (str_contains((string) wp_get_referer(), 'wp-login.php')) {
            return $url;
        }

        return preg_replace('/wp-login\\.php/', $this->slug, $url, 1);
    }

    private function useTrailingSlashes(): bool
    {
        return str_ends_with((string) get_option('permalink_structure'), '/');
    }

    private function userTrailingslashit(string $string): string
    {
        return $this->useTrailingSlashes() ? trailingslashit($string) : untrailingslashit($string);
    }

    private function newLoginUrl(?string $scheme = null): string
    {
        $url = home_url('/', $scheme);

        if (get_option('permalink_structure')) {
            return $this->userTrailingslashit($url . $this->slug);
        }

        return $url . '?' . $this->slug;
    }

    /** URL 404 để redirect khách vãng lai cố truy cập /wp-admin (giống default "404" slug của WPS). */
    private function newRedirectUrl(?string $scheme = null): string
    {
        $url = home_url('/', $scheme);

        if (get_option('permalink_structure')) {
            return $this->userTrailingslashit($url . '404');
        }

        return $url . '?404';
    }

    private function isCli(): bool
    {
        return (defined('DOING_AJAX') && DOING_AJAX)
            || (defined('DOING_CRON') && DOING_CRON)
            || (defined('WP_CLI')    && WP_CLI);
    }
}
