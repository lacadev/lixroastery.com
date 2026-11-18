<?php

namespace App\Settings\Security\TrafficMonitor;

use App\Helpers\ClientIp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tường lửa request ở tầng ứng dụng: bảo vệ đường dẫn nhạy cảm, PHP trong uploads,
 * và mẫu query độc hại (traversal / SQLi / XSS / tham số tùy chỉnh / query > 8 KB).
 * Chạy ở `init` priority 1 kể cả khi `tracking = 0`, miễn có ít nhất 1 rule bật.
 */
final class RequestFirewall
{
    private static ?string $hitReason = null;

    public static function hitReason(): ?string
    {
        return self::$hitReason;
    }

    public function register(): void
    {
        $settings = TrafficSettings::settings();
        if (!TrafficSettings::hasActiveFirewallRule($settings)) {
            return;
        }

        add_action('init', [$this, 'inspect'], 1);
    }

    public function inspect(): void
    {
        self::$hitReason = null;

        if (
            is_admin()
            || is_user_logged_in()
            || wp_doing_cron()
            || (defined('WP_CLI') && WP_CLI)
        ) {
            return;
        }

        $settings   = TrafficSettings::settings();
        $uri        = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $rawPath    = wp_parse_url($uri, PHP_URL_PATH);
        $path       = is_string($rawPath) && $rawPath !== '' ? rawurldecode($rawPath) : '/';
        $isRest     = str_starts_with($path, '/' . rest_get_url_prefix() . '/');
        $reasonCode = '';

        // 1. Kiểm tra đường dẫn nhạy cảm & PHP trong uploads (bỏ qua đường dẫn REST API)
        if (!$isRest) {
            if (
                !empty($settings['fw_sensitive'])
                && preg_match('~(?:^|/)(?:\.env(?:\.[^/]*)?|\.git(?:/|$)|wp-config\.php\.(?:bak|old|save|txt)|phpinfo\.php|\.htaccess(?:\.bak)?)(?:/|$)~i', $path)
            ) {
                $reasonCode = 'fw_sensitive';
            } elseif (
                !empty($settings['fw_upload_php'])
                && preg_match('~/(?:wp-content/)?uploads/[^?]*\.(?:php[0-9]?|phtml|phar)$~i', $path)
            ) {
                $reasonCode = 'fw_upload_php';
            }
        }

        // 2. Kiểm tra query string (bỏ qua AJAX)
        if (
            $reasonCode === ''
            && !(function_exists('wp_doing_ajax') && wp_doing_ajax())
            && !empty($_SERVER['QUERY_STRING'])
        ) {
            $query = (string) wp_unslash($_SERVER['QUERY_STRING']);
            $hasQueryRule = !empty($settings['fw_traversal'])
                || !empty($settings['fw_sqli'])
                || !empty($settings['fw_xss'])
                || (!empty($settings['fw_custom']) && trim((string) $settings['fw_custom_keys']) !== '');

            if ($hasQueryRule) {
                if (strlen($query) > 8192) {
                    $reasonCode = 'fw_oversize';
                } else {
                    $reasonCode = self::findQueryReason($query, $settings);
                }
            }
        }

        if ($reasonCode === '') {
            return;
        }

        self::$hitReason = $reasonCode;

        $ip = ClientIp::get();
        if ($ip === 'unknown' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }

        $mode      = (($settings['firewall_mode'] ?? 'alert') === 'block') ? 'block' : 'alert';
        $actionVal = ($mode === 'block') ? '403' : 'alert';

        self::recordEvent($ip, $uri, $path, $reasonCode, $actionVal);

        do_action('laca_traffic_firewall_hit', $ip, $reasonCode, $mode);

        if ($mode !== 'block') {
            return;
        }

        status_header(403);
        nocache_headers();
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo esc_html__('403 Không được phép truy cập', 'laca');
        exit;
    }

    /**
     * @param array<string, int|string> $settings
     */
    public static function findQueryReason(string $query, array $settings): string
    {
        $decoded = html_entity_decode($query, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        for ($i = 0; $i < 2; $i++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        $lower = strtolower($decoded);

        if (
            !empty($settings['fw_traversal'])
            && preg_match('~(?:\.\.[/\\\\]|%2e%2e|(?:/|\\\\)etc(?:/|\\\\)passwd|wp-config\.php)~i', $lower)
        ) {
            return 'fw_traversal';
        }

        if (
            !empty($settings['fw_sqli'])
            && preg_match('~\bunion\s+(?:all\s+)?select\b|\b(?:sleep|benchmark)\s*\(|\b(?:or|and)\s+[\'\"]?\d+[\'\"]?\s*=\s*[\'\"]?\d+|information_schema\.|\b(?:updatexml|extractvalue)\s*\(~i', $lower)
        ) {
            return 'fw_sqli';
        }

        if (
            !empty($settings['fw_xss'])
            && preg_match('~<\s*/?\s*script\b|\bon(?:error|load|mouseover)\s*=|javascript\s*:|<\s*(?:iframe|svg)\b~i', $lower)
        ) {
            return 'fw_xss';
        }

        if (!empty($settings['fw_custom']) && trim((string) $settings['fw_custom_keys']) !== '') {
            $params = [];
            parse_str($query, $params);
            $blocked = array_map('trim', explode(',', (string) $settings['fw_custom_keys']));
            foreach (array_keys($params) as $key) {
                $kLower = strtolower((string) $key);
                if (in_array($kLower, $blocked, true)) {
                    return 'fw_custom:' . substr($kLower, 0, 64);
                }
            }
        }

        return '';
    }

    private static function recordEvent(string $ip, string $uri, string $rawPath, string $reasonCode, string $actionVal): void
    {
        if (!TrafficTable::isReady()) {
            return;
        }

        // Rate-limit ghi DB: tối đa 1 bản ghi / IP / reason / 60s
        $rateKey = 'laca_tm_fw_' . md5($ip . '|' . $reasonCode);
        if (get_transient($rateKey) !== false) {
            return;
        }
        set_transient($rateKey, 1, 60);

        global $wpdb;
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? substr(strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))), 0, 8)
            : 'GET';
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255)
            : '';
        $ref = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';

        $wpdb->insert(
            TrafficTable::eventsTable(),
            [
                'ip'          => $ip !== '' ? $ip : '0.0.0.0',
                'visited_at'  => current_time('mysql'),
                'path'        => TrafficClassifier::safe_path($uri),
                'referrer'    => TrafficClassifier::safe_referrer($ref),
                'category'    => 'attack',
                'reason'      => substr($reasonCode, 0, 255),
                'method'      => $method !== '' ? $method : 'GET',
                'status_code' => $actionVal === '403' ? 403 : 0,
                'ua'          => $ua,
                'score'       => TrafficClassifier::risk_score($rawPath, $ua, $method, 'attack'),
                'action'      => $actionVal,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );
    }
}
