<?php

namespace App\Features\DiscordHub;

use App\Helpers\ClientIp;

/**
 * Thông tin ngữ cảnh của request hiện tại: IP, actor, kênh gọi (REST/AJAX/XML-RPC...).
 */
final class Context
{
    /**
     * IP thật của client — xem App\Helpers\ClientIp (chỉ tin header Cloudflare khi request thật sự đi qua Cloudflare).
     */
    public static function clientIp(): string
    {
        return ClientIp::get();
    }

    /**
     * Quốc gia từ Cloudflare (null nếu site không chạy sau Cloudflare).
     */
    public static function country(): ?string
    {
        return ClientIp::country();
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
        return I18n::t('Khách (chưa đăng nhập)', 'Guest (not logged in)');
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
