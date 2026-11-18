<?php

namespace App\Settings\Security\TrafficMonitor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Đọc/ghi cấu hình Giám sát truy cập & tường lửa request.
 * Option `laca_tm_settings` (autoload = no).
 */
final class TrafficSettings
{
    public const OPTION = 'laca_tm_settings';

    private static ?array $cache = null;

    /**
     * @return array<string, int|string>
     */
    public static function defaults(): array
    {
        return [
            'tracking'       => 0,
            'retention_days' => 14,
            'firewall_mode'  => 'alert',
            'fw_sensitive'   => 1,
            'fw_upload_php'  => 1,
            'fw_traversal'   => 1,
            'fw_sqli'        => 1,
            'fw_xss'         => 1,
            'fw_custom'      => 0,
            'fw_custom_keys' => '',
            'digest_email'   => 0,
            'htaccess_sync'  => 0,
            'rdap'           => 0,
            'rdap_whitelist' => '',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public static function settings(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = get_option(self::OPTION, []);
        $parsed = wp_parse_args(is_array($stored) ? $stored : [], self::defaults());

        self::$cache = self::sanitize($parsed);

        return self::$cache;
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    /**
     * Lưu cấu hình (hỗ trợ lưu theo phân vùng tab 'firewall' | 'settings' hoặc toàn bộ).
     *
     * @param array<string, mixed> $raw
     * @return array<string, int|string>
     */
    public static function save(array $raw, ?string $scope = null): array
    {
        $current = self::settings();
        $merged  = $current;

        if ($scope === 'firewall') {
            $merged['firewall_mode']  = $raw['firewall_mode'] ?? 'alert';
            $merged['fw_sensitive']   = !empty($raw['fw_sensitive']) ? 1 : 0;
            $merged['fw_upload_php']  = !empty($raw['fw_upload_php']) ? 1 : 0;
            $merged['fw_traversal']   = !empty($raw['fw_traversal']) ? 1 : 0;
            $merged['fw_sqli']        = !empty($raw['fw_sqli']) ? 1 : 0;
            $merged['fw_xss']         = !empty($raw['fw_xss']) ? 1 : 0;
            $merged['fw_custom']      = !empty($raw['fw_custom']) ? 1 : 0;
            $merged['fw_custom_keys'] = $raw['fw_custom_keys'] ?? '';
        } elseif ($scope === 'settings') {
            $merged['tracking']       = !empty($raw['tracking']) ? 1 : 0;
            $merged['retention_days'] = $raw['retention_days'] ?? $current['retention_days'];
            $merged['digest_email']   = !empty($raw['digest_email']) ? 1 : 0;
            $merged['htaccess_sync']  = !empty($raw['htaccess_sync']) ? 1 : 0;
            $merged['rdap']           = !empty($raw['rdap']) ? 1 : 0;
            $merged['rdap_whitelist'] = $raw['rdap_whitelist'] ?? '';
        } else {
            foreach ($raw as $key => $val) {
                if (array_key_exists($key, $merged)) {
                    $merged[$key] = $val;
                }
            }
        }

        $clean = self::sanitize($merged);
        update_option(self::OPTION, $clean, false);
        self::$cache = $clean;

        return $clean;
    }

    /**
     * @param array<string, mixed> $src
     * @return array<string, int|string>
     */
    public static function sanitize(array $src): array
    {
        $defaults = self::defaults();

        $boolKeys = [
            'tracking',
            'fw_sensitive',
            'fw_upload_php',
            'fw_traversal',
            'fw_sqli',
            'fw_xss',
            'fw_custom',
            'digest_email',
            'htaccess_sync',
            'rdap',
        ];

        $clean = $defaults;
        foreach ($boolKeys as $k) {
            $clean[$k] = !empty($src[$k]) ? 1 : 0;
        }

        $days = isset($src['retention_days']) ? (int) $src['retention_days'] : 14;
        $clean['retention_days'] = max(1, min(90, $days > 0 ? $days : 1));

        $clean['firewall_mode'] = (($src['firewall_mode'] ?? 'alert') === 'block') ? 'block' : 'alert';

        // Sanitize custom query keys: regex ^[a-z0-9_-]+$, <= 64 chars, max 40 keys
        $keysRaw = isset($src['fw_custom_keys']) && is_string($src['fw_custom_keys']) ? $src['fw_custom_keys'] : '';
        $parts   = preg_split('/[\s,]+/', strtolower($keysRaw)) ?: [];
        $keys    = array_filter(array_unique(array_map('trim', $parts)));
        $keys    = array_values(array_filter($keys, static function (string $key): bool {
            return strlen($key) <= 64 && (bool) preg_match('/^[a-z0-9_-]+$/D', $key);
        }));
        $clean['fw_custom_keys'] = implode(', ', array_slice($keys, 0, 40));

        // Sanitize RDAP whitelist: max 200 lines, valid IP or CIDR
        $wlRaw = isset($src['rdap_whitelist']) && is_string($src['rdap_whitelist']) ? $src['rdap_whitelist'] : '';
        $lines = preg_split('/\r\n|\r|\n/', $wlRaw) ?: [];
        $valid = [];
        foreach (array_slice($lines, 0, 200) as $line) {
            $line = trim($line);
            if ($line === '' || strlen($line) > 64) {
                continue;
            }
            $seg = explode('/', $line);
            if (count($seg) > 2 || !filter_var($seg[0], FILTER_VALIDATE_IP)) {
                continue;
            }
            $maxBits = str_contains($seg[0], ':') ? 128 : 32;
            if (count($seg) === 2 && (!ctype_digit($seg[1]) || (int) $seg[1] > $maxBits)) {
                continue;
            }
            $valid[] = $line;
        }
        $clean['rdap_whitelist'] = implode("\n", array_values(array_unique($valid)));

        return $clean;
    }

    public static function isKillSwitchActive(): bool
    {
        return defined('LACA_TM_DISABLE') && (bool) LACA_TM_DISABLE;
    }

    public static function isPluginActive(): bool
    {
        return defined('LBM_PATH');
    }

    /**
     * @param array<string, int|string>|null $s
     */
    public static function hasActiveFirewallRule(?array $s = null): bool
    {
        $s = $s ?? self::settings();

        return !empty($s['fw_sensitive'])
            || !empty($s['fw_upload_php'])
            || !empty($s['fw_traversal'])
            || !empty($s['fw_sqli'])
            || !empty($s['fw_xss'])
            || (!empty($s['fw_custom']) && trim((string) $s['fw_custom_keys']) !== '');
    }
}
