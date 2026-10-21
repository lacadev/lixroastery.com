<?php

namespace App\Features\LacaSeo\Schema\Pro;

use DOMDocument;
use DOMXPath;
use WP_Error;

defined('ABSPATH') || exit;

class UrlParser
{
    /**
     * Get JSON-LD array from remote URL.
     * Enforces security: only manage_options, wp_safe_remote_get, timeout 10s, max 2MB,
     * blocks internal loopback IPs unless it is the current site's host.
     */
    public function fromUrl(string $url): array|WP_Error
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('rest_forbidden', __('Bạn không có quyền thực hiện thao tác này.', 'lacadev'), ['status' => 403]);
        }

        $parsedUrl = parse_url($url);
        $targetHost = $parsedUrl['host'] ?? '';
        $siteHost   = parse_url(home_url(), PHP_URL_HOST);

        // Security check for local/private IPs
        if (!empty($targetHost) && strtolower($targetHost) !== strtolower($siteHost)) {
            $ip = gethostbyname($targetHost);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return new WP_Error('blocked_ip', __('Không được phép truy cập địa chỉ IP nội bộ hoặc loopback.', 'lacadev'));
                }
            }
        }

        $response = wp_safe_remote_get($url, [
            'timeout'     => 10,
            'sslverify'   => false,
            'limit_response_size' => 2097152, // 2MB
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('body_not_found', __('Không tìm thấy nội dung HTML.', 'lacadev'));
        }

        return $this->fromHtml($body);
    }

    /**
     * Parse HTML string and extract all application/ld+json blocks.
     */
    public function fromHtml(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        $xpath = new DOMXPath($dom);
        $scripts = $xpath->query('//script[@type="application/ld+json"]');

        $json = [];
        if ($scripts) {
            foreach ($scripts as $script) {
                $content = trim($script->nodeValue ?? '');
                $decoded = json_decode($content, true);
                if ($decoded) {
                    $json[] = $decoded;
                }
            }
        }

        libxml_clear_errors();
        return $json;
    }
}
