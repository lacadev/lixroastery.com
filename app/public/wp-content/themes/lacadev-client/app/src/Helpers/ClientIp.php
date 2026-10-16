<?php

namespace App\Helpers;

/**
 * IP thật của client — chống giả mạo.
 *
 * Header như X-Forwarded-For / CF-Connecting-IP do CLIENT tự gửi được → tin mù quáng thì kẻ
 * tấn công đổi IP tuỳ ý mỗi request (né đếm brute-force, đổ tội cho IP khác). Chỉ tin
 * CF-Connecting-IP khi kết nối thực sự đến từ dải IP của Cloudflare; còn lại dùng REMOTE_ADDR.
 * Tự nhận diện, không cần cấu hình — site đổi sang/bỏ Cloudflare vẫn đúng.
 */
final class ClientIp
{
    /** https://www.cloudflare.com/ips-v4 */
    private const CLOUDFLARE_V4 = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    ];

    /** https://www.cloudflare.com/ips-v6 */
    private const CLOUDFLARE_V6 = [
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public static function get(): string
    {
        $remote = self::remoteAddr();
        if ('' === $remote) {
            return 'unknown';
        }
        if (self::isCloudflare($remote) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $real = trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP'])));
            if (filter_var($real, FILTER_VALIDATE_IP)) {
                return $real;
            }
        }
        return $remote;
    }

    public static function isBehindCloudflare(): bool
    {
        $remote = self::remoteAddr();
        return '' !== $remote && self::isCloudflare($remote);
    }

    /**
     * Mã quốc gia (header CF-IPCountry) — chỉ khi request thật sự đi qua Cloudflare.
     */
    public static function country(): ?string
    {
        if (!self::isBehindCloudflare() || empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return null;
        }
        $code = strtoupper(sanitize_key(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY'])));
        return preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }

    private static function remoteAddr(): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))) : '';
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
    }

    public static function isCloudflare(string $ip): bool
    {
        $ranges = str_contains($ip, ':') ? self::CLOUDFLARE_V6 : self::CLOUDFLARE_V4;
        foreach ($ranges as $cidr) {
            if (self::inRange($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    private static function inRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if (false === $ipBin || false === $subnetBin || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (0 !== strncmp($ipBin, $subnetBin, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if (0 === $rest) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rest)) & 0xFF);
        return (($ipBin[$bytes] & $mask) === ($subnetBin[$bytes] & $mask));
    }
}
