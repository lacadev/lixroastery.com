<?php

namespace App\Features\DiscordHub;

/**
 * Thông tin ngữ cảnh của request hiện tại: IP, actor, kênh gọi (REST/AJAX/XML-RPC...).
 */
final class Context
{
    /**
     * IP client. Mặc định chỉ tin REMOTE_ADDR; header proxy (X-Forwarded-For,
     * CF-Connecting-IP) bị giả mạo dễ dàng nên chỉ dùng khi được khai báo rõ
     * trong Laca Admin → Discord Hub.
     */
    public static function clientIp(): string
    {
        $header = Settings::ipHeader();
        if ('' !== $header) {
            if (!empty($_SERVER[$header])) {
                $candidate = trim(explode(',', sanitize_text_field(wp_unslash($_SERVER[$header])))[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
    }

    /**
     * Quốc gia lấy từ header Cloudflare (không gọi GeoIP API trong request —
     * việc đó thuộc về Bot ở Phase 3). Chỉ tin header khi site nằm sau Cloudflare.
     */
    public static function country(): ?string
    {
        if ('HTTP_CF_CONNECTING_IP' !== Settings::ipHeader()) {
            return null;
        }
        $code = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? strtoupper(sanitize_key(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY']))) : '';
        return preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }

    public static function userAgent(): string

    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return '' !== $ua ? $ua : '—';
    }

    /**
     * Ai đang thực hiện hành động (quan trọng cho audit: "ai nâng quyền ai").
     */
    public static function actor(): string
    {
        $user = wp_get_current_user();
        if ($user && $user->exists()) {
            return sprintf('%s (@%s, #%d)', $user->display_name, $user->user_login, $user->ID);
        }
        if (defined('WP_CLI') && WP_CLI) {
            return 'WP-CLI';
        }
        if (wp_doing_cron()) {
            return 'WP-Cron';
        }
        return 'Khách (chưa đăng nhập)';
    }

    /**
     * Kênh phát sinh sự kiện — vd user admin được tạo qua XML-RPC/REST là dấu hiệu bất thường.
     */
    public static function origin(): string
    {
        if (defined('WP_CLI') && WP_CLI) {
            return 'WP-CLI';
        }
        if (wp_doing_cron()) {
            return 'WP-Cron';
        }
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return 'XML-RPC';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'REST API';
        }
        if (wp_doing_ajax()) {
            return 'AJAX';
        }
        if (is_admin()) {
            return 'WP-Admin';
        }
        $script = isset($_SERVER['SCRIPT_NAME']) ? basename(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME']))) : '';
        return 'wp-login.php' === $script ? 'wp-login.php' : 'Frontend';
    }
}
