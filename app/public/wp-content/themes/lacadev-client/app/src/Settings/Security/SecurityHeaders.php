<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Headers có cấu hình — chuyển từ code trực tiếp trong security.php
 * thành class dùng chung với toggle bật/tắt từng header.
 *
 * Mặc định: TẤT CẢ headers BẬT (giữ đúng hành vi hiện tại của bản merge ở Phần 1).
 * Bỏ X-XSS-Protection (đã lỗi thời).
 *
 * @since 2026-11 (Plan Security Parity)
 */
class SecurityHeaders
{
    /** Option key lưu cấu hình toggle */
    const OPTION_KEY = 'laca_security_headers';

    /**
     * Cấu hình mặc định — tất cả BẬT.
     */
    private static function defaults(): array
    {
        return [
            'x_frame_options'       => true,
            'x_content_type'        => true,
            'referrer_policy'       => true,
            'permissions_policy'    => true,
            'hsts'                  => true,  // chỉ gửi khi is_ssl()
            'hsts_max_age'          => 31536000, // 1 năm
            'hsts_include_sub'      => false,
            'csp'                   => true,
            'remove_powered_by'     => true,
        ];
    }

    /**
     * Lấy cấu hình hiện tại (merge với defaults).
     */
    public static function getConfig(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return wp_parse_args($saved, self::defaults());
    }

    /**
     * Lưu cấu hình.
     */
    public static function saveConfig(array $config): void
    {
        update_option(self::OPTION_KEY, $config, false);
    }

    /**
     * Đăng ký hooks — thay thế code trong security.php.
     */
    public function register(): void
    {
        add_action('send_headers', [$this, 'sendHeaders']);

        // Nonce vẫn luôn sinh để script tag gắn nonce attribute
        add_filter('script_loader_tag', [$this, 'injectScriptNonce'], 10, 2);
        add_filter('wp_inline_script_attributes', [$this, 'injectInlineScriptNonce'], 10, 1);
    }

    /**
     * Gửi headers theo cấu hình toggle.
     */
    public function sendHeaders(): void
    {
        $config = self::getConfig();

        // Luôn sinh CSP nonce
        $nonce = base64_encode(random_bytes(16));
        if (!defined('LACA_CSP_NONCE')) {
            define('LACA_CSP_NONCE', $nonce);
        }

        // X-Frame-Options
        if (!empty($config['x_frame_options'])) {
            header('X-Frame-Options: SAMEORIGIN');
        }

        // X-Content-Type-Options
        if (!empty($config['x_content_type'])) {
            header('X-Content-Type-Options: nosniff');
        }

        // Referrer-Policy
        if (!empty($config['referrer_policy'])) {
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }

        // Bỏ X-XSS-Protection (đã lỗi thời, bị lạm dụng ngược)

        // Ẩn X-Powered-By
        if (!empty($config['remove_powered_by'])) {
            header_remove('X-Powered-By');
        }

        // HSTS — chỉ gửi khi HTTPS
        if (!empty($config['hsts']) && is_ssl()) {
            $maxAge  = max(0, (int) ($config['hsts_max_age'] ?? 31536000));
            $subDom  = !empty($config['hsts_include_sub']) ? '; includeSubDomains' : '';
            header("Strict-Transport-Security: max-age={$maxAge}{$subDom}");
        }

        // CSP — chỉ frontend
        if (!empty($config['csp']) && !is_admin()) {
            $csp  = "default-src 'self'; ";
            $csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://www.googletagmanager.com https://www.google-analytics.com https://images.dmca.com https://apis.google.com blob:; ";
            $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; ";
            $csp .= "font-src 'self' https://fonts.gstatic.com data:; ";
            $csp .= "connect-src 'self' https://www.google.com https://www.gstatic.com https://www.youtube.com https://www.google-analytics.com https://stats.g.doubleclick.net https://apis.google.com ws: wss:; ";

            $defaultFrameHosts = [
                'https://www.youtube.com',
                'https://www.youtube-nocookie.com',
                'https://player.vimeo.com',
                'https://docs.google.com',
                'https://www.google.com',
                'https://www.gstatic.com',
            ];
            $frameHosts    = apply_filters('laca_csp_frame_src', $defaultFrameHosts);
            $frameSrcStr   = implode(' ', array_unique(array_filter((array) $frameHosts)));
            $csp .= "frame-src 'self' {$frameSrcStr}; ";
            $csp .= "media-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; ";
            $csp .= "img-src 'self' data: https: http:; ";
            $csp .= "worker-src 'self' blob:; ";
            $csp .= "frame-ancestors 'self';";

            header("Content-Security-Policy: {$csp}");
        }

        // Permissions-Policy — chỉ frontend
        if (!empty($config['permissions_policy']) && !is_admin()) {
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        }
    }

    /**
     * Inject nonce vào script tags (giữ nguyên logic cũ).
     */
    public function injectScriptNonce(string $tag, string $handle): string
    {
        if (is_admin()) {
            return $tag;
        }
        if (defined('LACA_CSP_NONCE') && strpos($tag, 'nonce=') === false) {
            $tag = str_replace('<script ', '<script nonce="' . LACA_CSP_NONCE . '" ', $tag);
        }
        return $tag;
    }

    /**
     * Inject nonce vào inline script attributes.
     */
    public function injectInlineScriptNonce(array $attributes): array
    {
        if (is_admin()) {
            return $attributes;
        }
        if (defined('LACA_CSP_NONCE') && empty($attributes['nonce'])) {
            $attributes['nonce'] = LACA_CSP_NONCE;
        }
        return $attributes;
    }
}

