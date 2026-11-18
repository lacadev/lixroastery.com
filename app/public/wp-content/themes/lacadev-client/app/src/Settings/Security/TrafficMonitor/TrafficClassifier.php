<?php

namespace App\Settings\Security\TrafficMonitor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bộ phân loại truy cập thuần (static, không I/O).
 * DB chỉ lưu mã reason/category; dịch sang câu chữ qua reasonLabel()/categoryLabel() khi hiển thị.
 */
final class TrafficClassifier
{
    public static function safe_path(mixed $value): string
    {
        $path = wp_parse_url((string) $value, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $path);

        return substr(is_string($clean) && $clean !== '' ? $clean : '/', 0, 500);
    }

    public static function safe_referrer(mixed $value): string
    {
        $raw  = (string) $value;
        $host = wp_parse_url($raw, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $scheme = wp_parse_url($raw, PHP_URL_SCHEME);
        if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return '';
        }

        $path = self::safe_path($raw);

        return substr(strtolower($host) . $path, 0, 500);
    }

    /**
     * Nhận diện tên bot tự khai báo từ User-Agent (không xác minh danh tính thật).
     */
    public static function bot_name(string $ua): string
    {
        if ($ua === '') {
            return '';
        }

        $signatures = [
            'Googlebot'             => 'googlebot',
            'Google-InspectionTool' => 'google-inspectiontool',
            'GoogleOther'           => 'googleother',
            'Google AdsBot'         => 'adsbot-google',
            'Bingbot'               => 'bingbot',
            'Microsoft BingPreview' => 'bingpreview',
            'DuckDuckBot'           => 'duckduckbot',
            'YandexBot'             => 'yandexbot',
            'Baiduspider'           => 'baiduspider',
            'Applebot'              => 'applebot',
            'GPTBot'                => 'gptbot',
            'ChatGPT-User'          => 'chatgpt-user',
            'OAI-SearchBot'         => 'oai-searchbot',
            'ClaudeBot'             => 'claudebot',
            'Claude-User'           => 'claude-user',
            'Claude-SearchBot'      => 'claude-searchbot',
            'PerplexityBot'         => 'perplexitybot',
            'Bytespider'            => 'bytespider',
            'CCBot'                 => 'ccbot',
            'Meta-ExternalAgent'    => 'meta-externalagent',
            'FacebookBot'           => 'facebookexternalhit',
            'Facebook Crawler'      => 'facebot',
            'Twitterbot'            => 'twitterbot',
            'LinkedInBot'           => 'linkedinbot',
            'Pinterestbot'          => 'pinterestbot',
            'AhrefsBot'             => 'ahrefsbot',
            'SemrushBot'            => 'semrushbot',
            'MJ12bot'               => 'mj12bot',
            'DotBot'                => 'dotbot',
            'PetalBot'              => 'petalbot',
            'Screaming Frog'        => 'screaming frog',
            'WPScan'                => 'wpscan',
            'sqlmap'                => 'sqlmap',
            'Nikto'                 => 'nikto',
            'Nmap'                  => 'nmap',
            'Masscan'               => 'masscan',
            'Acunetix'              => 'acunetix',
            'Nessus'                => 'nessus',
            'Scrapy'                => 'scrapy',
            'Python Requests'       => 'python-requests',
            'cURL'                  => 'curl/',
            'Wget'                  => 'wget/',
        ];

        foreach ($signatures as $name => $token) {
            if (stripos($ua, $token) !== false) {
                return $name;
            }
        }

        if (preg_match('~(?:bot|crawler|spider)~i', $ua)) {
            return __('Bot khác (chưa rõ tên)', 'laca');
        }

        return '';
    }

    /**
     * Phân loại request thành [category, suspicious (0|1), reasonCode].
     *
     * @return array{0: string, 1: int, 2: string}
     */
    public static function classify(string $path, string $ua, string $method = 'GET'): array
    {
        $reason     = '';
        $suspicious = 0;
        $cat        = 'visitor';

        $p = strtolower($path);
        $u = strtolower($ua);

        if (preg_match('~(^|/)(\.env|\.git|wp-config\.php|phpmyadmin|adminer\.php|vendor/phpunit|\.htaccess)(/|$)~i', $p)) {
            $reason     = 'sensitive_probe';
            $suspicious = 1;
            $cat        = 'attack';
        } elseif (preg_match('~(^|/)(xmlrpc\.php|wp-login\.php)$~i', $p)) {
            $reason = 'login_endpoint';
            $cat    = 'watch';
        } elseif (preg_match('~(sqlmap|nikto|masscan|nmap|acunetix|nessus|wpscan)~i', $u)) {
            $reason     = 'scanner_ua';
            $suspicious = 1;
            $cat        = 'watch';
        } elseif (preg_match('~(googlebot|google-inspectiontool|googleother|adsbot-google|bingbot|bingpreview|duckduckbot|yandexbot|baiduspider|applebot)~i', $u)) {
            $cat    = 'searchbot';
            $reason = 'searchbot_claimed';
        } elseif (self::isBotUa($ua)) {
            $cat    = 'bot';
            $reason = 'bot_claimed';
        } elseif ($u === '') {
            $cat    = 'unknown';
            $reason = 'no_ua';
        }

        return [$cat, $suspicious, $reason];
    }

    private static function isBotUa(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        if (preg_match('~(?:bot|crawler|spider|gptbot|chatgpt-user|oai-searchbot|claudebot|claude-user|claude-searchbot|perplexitybot|bytespider|ccbot|meta-externalagent|facebookexternalhit|facebot|twitterbot|linkedinbot|pinterestbot|ahrefsbot|semrushbot|mj12bot|dotbot|petalbot|screaming frog|wpscan|sqlmap|nikto|nmap|masscan|acunetix|nessus|scrapy|python-requests|curl/|wget/)~i', $ua)) {
            return true;
        }

        return false;
    }

    public static function risk_score(string $path, string $ua, string $method, string $category): int
    {
        $score = 0;
        if ($category === 'attack') {
            $score += 75;
        } elseif ($category === 'watch') {
            $score += 20;
        } elseif ($category === 'bot') {
            $score += 10;
        } elseif ($category === 'unknown') {
            $score += 10;
        }

        if (preg_match('~(sqlmap|nikto|masscan|nmap|acunetix|nessus|wpscan)~i', $ua)) {
            $score += 40;
        }

        if (strtoupper($method) === 'POST' && preg_match('~/(wp-login\.php|xmlrpc\.php)$~i', $path)) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Danh sách nhãn phân loại (đặt trong hàm để nạp sau textdomain).
     *
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'visitor'   => __('Khách thường', 'laca'),
            'bot'       => __('Bot tự khai báo', 'laca'),
            'searchbot' => __('Bot tìm kiếm (chưa xác minh)', 'laca'),
            'watch'     => __('Nghi ngờ', 'laca'),
            'attack'    => __('Dấu hiệu dò quét', 'laca'),
            'unknown'   => __('Không xác định', 'laca'),
        ];
    }

    public static function categoryLabel(string $category): string
    {
        $labels = self::categoryLabels();

        return $labels[$category] ?? __('Không xác định', 'laca');
    }

    /**
     * Dịch mã lý do lưu trong DB sang câu hiển thị theo ngôn ngữ hiện tại.
     */
    public static function reasonLabel(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        if (str_starts_with($code, 'fw_custom:')) {
            $param = substr($code, 10);
            /* translators: %s: tên tham số query tùy chỉnh bị phát hiện */
            return sprintf(__('Tường lửa: tham số tùy chỉnh (%s)', 'laca'), $param);
        }

        $map = [
            'sensitive_probe'   => __('Dò tệp/đường dẫn nhạy cảm', 'laca'),
            'login_endpoint'    => __('Truy cập điểm đăng nhập/XML-RPC (chưa chứng minh tấn công)', 'laca'),
            'scanner_ua'        => __('User-Agent công cụ quét (có thể giả mạo)', 'laca'),
            'searchbot_claimed' => __('Bot tìm kiếm tự khai báo, chưa xác minh danh tính', 'laca'),
            'bot_claimed'       => __('User-Agent tự khai báo bot/công cụ tự động', 'laca'),
            'no_ua'             => __('Không có User-Agent', 'laca'),
            'fast_crawl'        => __('Truy cập ít nhất 5 URL khác nhau trong 10 giây (tốc độ bất thường)', 'laca'),
            'fw_sensitive'      => __('Tường lửa: truy cập tệp nhạy cảm', 'laca'),
            'fw_upload_php'     => __('Tường lửa: thử thực thi PHP trong uploads', 'laca'),
            'fw_traversal'      => __('Tường lửa: dấu hiệu path traversal / truy cập file', 'laca'),
            'fw_sqli'           => __('Tường lửa: dấu hiệu SQL injection', 'laca'),
            'fw_xss'            => __('Tường lửa: dấu hiệu XSS', 'laca'),
            'fw_oversize'       => __('Tường lửa: query vượt giới hạn 8 KB', 'laca'),
        ];

        return $map[$code] ?? $code;
    }
}
