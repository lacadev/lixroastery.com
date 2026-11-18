<?php

namespace App\Settings\Security\TrafficMonitor;

use App\Helpers\ClientIp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ghi nhận request công khai vào `laca_tm_visits` và `laca_tm_events` khi `tracking = 1`.
 */
final class TrafficTracker
{
    /** @var string[]|null */
    private static ?array $wooExcludePaths = null;

    public function register(): void
    {
        if ((int) (TrafficSettings::settings()['tracking'] ?? 0) !== 1) {
            return;
        }

        add_action('init', [$this, 'track'], 2);
    }

    public function track(): void
    {
        if (!TrafficTable::isReady()) {
            return;
        }

        if (
            is_admin()
            || wp_doing_cron()
            || (function_exists('wp_doing_ajax') && wp_doing_ajax())
            || is_user_logged_in()
            || (defined('WP_CLI') && WP_CLI)
        ) {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : 'GET';
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
            return;
        }

        $ip = ClientIp::get();
        if ($ip === '' || $ip === 'unknown' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return;
        }

        $uri  = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = wp_parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        if (
            str_starts_with($path, '/wp-json/')
            || str_starts_with($path, '/wc-api/')
            || str_starts_with($path, '/wp-content/')
            || str_starts_with($path, '/wp-includes/')
            || preg_match('~/(cart|checkout|my-account|gio-hang|thanh-toan|tai-khoan)(/|$)~i', $path)
            || self::isExcludedWooPath($path)
        ) {
            return;
        }

        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255)
            : '';

        [$cat, $sus, $reason] = TrafficClassifier::classify($path, $ua, $method);
        $fwReason = RequestFirewall::hitReason();
        if ($fwReason !== null) {
            $cat    = 'attack';
            $sus    = 1;
            $reason = $fwReason;
        }

        global $wpdb;
        $visitsTable = TrafficTable::visitsTable();
        $eventsTable = TrafficTable::eventsTable();
        $now         = current_time('mysql');
        $day         = current_time('Y-m-d');
        $safePath    = TrafficClassifier::safe_path($uri);

        // Phát hiện quét nhanh: >= 5 đường dẫn khác nhau trong 10 giây qua index ip_time
        if ($cat !== 'attack') {
            $windowStart = (new \DateTimeImmutable($now, wp_timezone()))
                ->modify('-9 seconds')
                ->format('Y-m-d H:i:s');

            $recentPaths = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT path FROM {$eventsTable} WHERE ip = %s AND visited_at >= %s AND visited_at <= %s LIMIT 5",
                    $ip,
                    $windowStart,
                    $now
                )
            );
            $distinctPaths = array_unique(array_merge((array) $recentPaths, [$safePath]));
            if (count($distinctPaths) >= 5) {
                $cat    = 'watch';
                $sus    = 1;
                $reason = 'fast_crawl';
            }
        }

        // Upsert nguyên tử theo (day, ip), giữ ưu tiên attack > watch > searchbot > bot > visitor > unknown
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$visitsTable} (day, ip, category, hits, suspicious, not_found, last_seen, ua, reasons)
                 VALUES (%s, %s, %s, 1, %d, 0, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    hits = hits + 1,
                    suspicious = suspicious + VALUES(suspicious),
                    last_seen = VALUES(last_seen),
                    category = CASE
                        WHEN category = 'attack' OR VALUES(category) = 'attack' THEN 'attack'
                        WHEN category = 'watch' OR VALUES(category) = 'watch' THEN 'watch'
                        WHEN category = 'searchbot' OR VALUES(category) = 'searchbot' THEN 'searchbot'
                        WHEN category = 'bot' OR VALUES(category) = 'bot' THEN 'bot'
                        WHEN category = 'visitor' OR VALUES(category) = 'visitor' THEN 'visitor'
                        ELSE 'unknown'
                    END,
                    ua = VALUES(ua),
                    reasons = IF(VALUES(reasons) = '', reasons, VALUES(reasons))",
                $day,
                $ip,
                $cat,
                $sus,
                $now,
                $ua,
                $reason
            )
        );

        // Nếu RequestFirewall đã khớp ở init:1 thì event đã được firewall ghi (có rate-limit)
        if ($fwReason !== null) {
            return;
        }

        $ref = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';
        $wpdb->insert(
            $eventsTable,
            [
                'ip'          => $ip,
                'visited_at'  => $now,
                'path'        => $safePath,
                'referrer'    => TrafficClassifier::safe_referrer($ref),
                'category'    => $cat,
                'reason'      => substr($reason, 0, 255),
                'method'      => $method,
                'status_code' => 0,
                'ua'          => $ua,
                'score'       => TrafficClassifier::risk_score($path, $ua, $method, $cat),
                'action'      => '',
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );

        $eventId = (int) $wpdb->insert_id;
        if ($eventId > 0) {
            register_shutdown_function([self::class, 'finalizeEvent'], $eventId, $day, $ip);
        }
    }

    public static function finalizeEvent(int $eventId, string $day, string $ip): void
    {
        if ($eventId <= 0) {
            return;
        }

        global $wpdb;
        $status = (int) http_response_code();
        if ($status < 100 || $status > 599) {
            $status = 0;
        }

        $eventsTable = TrafficTable::eventsTable();
        $wpdb->update(
            $eventsTable,
            ['status_code' => $status],
            ['id' => $eventId],
            ['%d'],
            ['%d']
        );

        if ($status === 404) {
            $visitsTable = TrafficTable::visitsTable();
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$visitsTable} SET not_found = not_found + 1 WHERE day = %s AND ip = %s",
                    $day,
                    $ip
                )
            );
        }
    }

    private static function isExcludedWooPath(string $path): bool
    {
        if (!function_exists('wc_get_page_permalink')) {
            return false;
        }

        if (self::$wooExcludePaths === null) {
            self::$wooExcludePaths = [];
            $homePath = '/' . trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

            foreach (['cart', 'checkout', 'myaccount'] as $pageKey) {
                $url = wc_get_page_permalink($pageKey);
                if (!is_string($url) || $url === '') {
                    continue;
                }
                $pagePath = wp_parse_url($url, PHP_URL_PATH);
                if (!is_string($pagePath) || $pagePath === '') {
                    continue;
                }
                $normPage = '/' . trim($pagePath, '/');
                if ($normPage === '/' || $normPage === $homePath) {
                    continue;
                }
                self::$wooExcludePaths[] = strtolower($normPage);
            }

            self::$wooExcludePaths = array_values(array_unique(self::$wooExcludePaths));
        }

        if (self::$wooExcludePaths === []) {
            return false;
        }

        $norm = strtolower('/' . trim($path, '/'));
        foreach (self::$wooExcludePaths as $wcPath) {
            if ($norm === $wcPath || str_starts_with($norm, $wcPath . '/')) {
                return true;
            }
        }

        return false;
    }
}
