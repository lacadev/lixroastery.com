<?php

namespace App\Settings\Security\TrafficMonitor;

use App\Helpers\ClientIp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quét nền loại mạng RDAP (`laca_tm_15min`, tối đa 3 tra cứu/lần, mặc định TẮT).
 * Kết quả khớp `ALLOCATED PORTABLE` lưu vào `laca_tm_rdap_alerts` (tối đa 200, autoload = no).
 */
final class NetworkTypeScanner
{
    public const SCHEDULE_NAME   = 'laca_tm_15min';
    public const CRON_HOOK       = 'laca_tm_rdap_scan';
    public const ALERTS_OPTION   = 'laca_tm_rdap_alerts';
    public const CACHE_PREFIX    = 'laca_tm_rdap_';
    public const LOCK_TRANSIENT  = 'laca_tm_rdap_lock';
    public const TARGET_TYPE     = 'ALLOCATED PORTABLE';

    public function register(): void
    {
        // Đăng ký cron_schedules ở init (sau khi textdomain 'laca' đã được nạp ở after_setup_theme)
        add_action('init', static function (): void {
            add_filter('cron_schedules', [self::class, 'registerSchedule']);
        }, 5);

        add_action('admin_init', [self::class, 'syncSchedule']);
        add_action(self::CRON_HOOK, [self::class, 'scan']);
    }

    /**
     * @param array<string, array{interval:int, display:string}> $schedules
     * @return array<string, array{interval:int, display:string}>
     */
    public static function registerSchedule(array $schedules): array
    {
        $schedules[self::SCHEDULE_NAME] = [
            'interval' => 900,
            'display'  => __('Mỗi 15 phút (Laca Giám sát truy cập)', 'laca'),
        ];

        return $schedules;
    }

    public static function syncSchedule(): void
    {
        $enabled = (int) (TrafficSettings::settings()['rdap'] ?? 0) === 1;

        if ($enabled) {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + 300, self::SCHEDULE_NAME, self::CRON_HOOK);
            }
        } else {
            if (wp_next_scheduled(self::CRON_HOOK)) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
            }
        }
    }

    /**
     * @return array<int, array{time:int, ip:string, type:string, action:string}>
     */
    public static function getAlerts(): array
    {
        $raw = get_option(self::ALERTS_OPTION, []);

        return is_array($raw) ? $raw : [];
    }

    public static function scan(): void
    {
        $settings = TrafficSettings::settings();
        if ((int) ($settings['rdap'] ?? 0) !== 1 || !TrafficTable::isReady()) {
            return;
        }

        if (get_transient(self::LOCK_TRANSIENT) !== false) {
            return;
        }
        set_transient(self::LOCK_TRANSIENT, 1, 3 * MINUTE_IN_SECONDS);

        try {
            global $wpdb;
            $visitsTable = TrafficTable::visitsTable();
            $since       = (new \DateTimeImmutable('now', wp_timezone()))
                ->modify('-1 day')
                ->format('Y-m-d H:i:s');

            $ips = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ip FROM {$visitsTable} WHERE last_seen >= %s GROUP BY ip ORDER BY MAX(last_seen) DESC LIMIT 80",
                    $since
                )
            );

            $whitelist = (string) ($settings['rdap_whitelist'] ?? '');
            $checked   = 0;

            foreach ((array) $ips as $ip) {
                $ip = (string) $ip;
                if (!self::isAllowed($ip, $whitelist)) {
                    continue;
                }

                $cacheKey = self::CACHE_PREFIX . md5($ip);
                if (get_transient($cacheKey) !== false) {
                    continue;
                }

                if ($checked >= 3) {
                    break;
                }
                $checked++;

                $result = self::lookup($ip);
                $ttl    = (($result['status'] ?? '') === 'verified' ? 7 : 1) * DAY_IN_SECONDS;
                set_transient($cacheKey, $result, $ttl);

                if (($result['status'] ?? '') !== 'verified' || ($result['type'] ?? '') !== self::TARGET_TYPE) {
                    continue;
                }

                $alerts = self::getAlerts();
                array_unshift($alerts, [
                    'time'   => time(),
                    'ip'     => $ip,
                    'type'   => $result['type'],
                    'action' => 'alert',
                ]);
                update_option(self::ALERTS_OPTION, array_slice($alerts, 0, 200), false);
            }
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    private static function isAllowed(string $ip, string $whitelist): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (trim($whitelist) === '') {
            return true;
        }

        foreach (preg_split('/\s+/', trim($whitelist)) ?: [] as $entry) {
            if ($entry !== '' && ClientIp::inCidr($ip, $entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status:string, type?:string}
     */
    private static function lookup(string $ip): array
    {
        $response = wp_safe_remote_get(
            'https://rdap.org/ip/' . rawurlencode($ip),
            [
                'timeout'             => 4,
                'redirection'         => 3,
                'limit_response_size' => 131072,
                'headers'             => [
                    'Accept' => 'application/rdap+json',
                ],
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return ['status' => 'unknown'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return ['status' => 'unknown'];
        }

        $type = isset($body['type']) && is_string($body['type'])
            ? strtoupper(trim($body['type']))
            : '';

        if ($type === '') {
            return ['status' => 'unknown'];
        }

        return [
            'status' => 'verified',
            'type'   => substr($type, 0, 80),
        ];
    }
}
