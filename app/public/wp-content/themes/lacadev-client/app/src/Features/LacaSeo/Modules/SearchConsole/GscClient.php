<?php

namespace App\Features\LacaSeo\Modules\SearchConsole;

defined('ABSPATH') || exit;

/**
 * Client for Google Search Console REST APIs:
 * - searchanalytics.query (Performance metrics: clicks, impressions, CTR, position, queries, pages)
 * - sitemaps.list (Sitemaps status, submission & indexation)
 * - urlInspection.index.inspect (URL inspection & rich results)
 * Includes transient caching and standard JSON response parsing.
 */
class GscClient
{
    public const API_BASE = 'https://searchconsole.googleapis.com/v1';
    public const WEBMASTERS_API_BASE = 'https://www.googleapis.com/webmasters/v3';

    /**
     * Get list of verified sites from Google Search Console.
     *
     * @param bool $forceRefresh
     * @return array<string>|\WP_Error
     */
    public static function listSites(bool $forceRefresh = false): array|\WP_Error
    {
        $cacheKey = 'laca_gsc_sites_list';
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google Search Console.', 'lacadev'));
        }

        $url = self::WEBMASTERS_API_BASE . '/sites';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            $msg = $data['error']['message'] ?? __('Không thể lấy danh sách site từ Google Search Console.', 'lacadev');
            return new \WP_Error('http_' . $code, sprintf(__('Lỗi Google (%d): %s', 'lacadev'), $code, $msg), [
                'status' => $code,
                'data'   => $data,
            ]);
        }

        $sites = [];
        if (!empty($data['siteEntry']) && is_array($data['siteEntry'])) {
            foreach ($data['siteEntry'] as $entry) {
                if (!empty($entry['siteUrl'])) {
                    $sites[] = (string) $entry['siteUrl'];
                }
            }
        }

        // Cache 12 hours
        set_transient($cacheKey, $sites, 12 * HOUR_IN_SECONDS);
        return $sites;
    }

    /**
     * Automatically pick the best property matching the site host by priority:
     * 1) sc-domain:<host không www>
     * 2) https://<host>/
     * 3) https://www.<host>/
     * 4) http://...
     *
     * @param array<string> $sites
     * @param string|null $host
     * @return string|null
     */
    public static function selectBestSiteUrl(array $sites, ?string $host = null): ?string
    {
        if (empty($sites)) {
            return null;
        }

        if ($host === null || $host === '') {
            $host = home_url('/');
        }
        if (str_contains($host, '://')) {
            $parsedHost = wp_parse_url($host, PHP_URL_HOST);
            $host = is_string($parsedHost) ? $parsedHost : $host;
        }

        $cleanHost = strtolower(preg_replace('/^www\./i', '', trim($host, '/')));

        $candidates = [
            'sc-domain:' . $cleanHost,
            'https://' . $cleanHost . '/',
            'https://www.' . $cleanHost . '/',
            'http://' . $cleanHost . '/',
            'http://www.' . $cleanHost . '/',
            'https://' . $cleanHost,
            'https://www.' . $cleanHost,
        ];

        foreach ($candidates as $cand) {
            if (in_array($cand, $sites, true)) {
                return $cand;
            }
        }

        // Fallback: search case-insensitively or return first
        foreach ($sites as $site) {
            if (stripos($site, $cleanHost) !== false) {
                return $site;
            }
        }

        return $sites[0] ?? null;
    }

    /**
     * Get the active Search Console property URL, falling back to auto-discovered best match or home_url('/').
     */
    public static function getSiteUrl(): string
    {
        if (class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $saved = (string) \App\Features\LacaSeo\Core\Settings::get('general.gsc_site_url', '');
            if ($saved !== '') {
                return $saved;
            }
        }

        return home_url('/');
    }

    /**
     * Get performance statistics (7d, 28d, 90d).
     *
     * @param string $siteUrl
     * @param string $range
     * @param bool $forceRefresh
     * @return array|\WP_Error
     */
    public static function getPerformanceSummary(string $siteUrl, string $range = '28d', bool $forceRefresh = false): array|\WP_Error
    {
        $cacheKey = 'laca_gsc_perf_' . md5($siteUrl . '_' . $range);
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google Search Console.', 'lacadev'));
        }

        $days = match ($range) {
            '7d', '7'   => 7,
            '90d', '90' => 90,
            default     => 28,
        };

        // GSC data has a 2-3 day lag
        $endDate   = date('Y-m-d', strtotime('-3 days'));
        $startDate = date('Y-m-d', strtotime('-' . ($days + 2) . ' days'));

        $requestBody = [
            'startDate'  => $startDate,
            'endDate'    => $endDate,
            'dimensions' => ['query'],
            'rowLimit'   => 50,
        ];

        $url = self::API_BASE . '/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';
        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($requestBody),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            $rawMsg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : '';
            if ($code === 403) {
                $msg = __('Tài khoản chưa có quyền truy cập property này hoặc chưa bật Google Search Console API.', 'lacadev');
            } elseif ($code === 404) {
                $msg = __('Không tìm thấy property trong Google Search Console. Hãy kiểm tra lại property đã chọn.', 'lacadev');
            } else {
                $msg = sprintf(__('Lỗi kết nối Google Search Console (%d)%s', 'lacadev'), $code, $rawMsg ? ': ' . $rawMsg : '');
            }

            return new \WP_Error('http_' . $code, $msg, [
                'status' => $code,
                'data'   => $data,
            ]);
        }

        if (!is_array($data)) {
            return new \WP_Error('invalid_response', __('Phản hồi từ Google Search Console không hợp lệ.', 'lacadev'));
        }

        $parsed = self::parsePerformanceResponse($data);
        $parsed['startDate'] = $startDate;
        $parsed['endDate']   = $endDate;
        $parsed['siteUrl']   = $siteUrl;

        // Optionally fetch top landing pages
        $pages = self::getTopLandingPages($siteUrl, $range, $forceRefresh);
        if (is_array($pages)) {
            $parsed['pages'] = $pages;
        }

        // Cache 1 hour
        set_transient($cacheKey, $parsed, HOUR_IN_SECONDS);

        return $parsed;
    }

    /**
     * Get top organic landing pages from Google Search Console.
     *
     * @param string $siteUrl
     * @param string $range
     * @param bool $forceRefresh
     * @return array<array{url: string, fullUrl: string, clicks: int, impressions: int, ctr: float, position: float}>|\WP_Error
     */
    public static function getTopLandingPages(string $siteUrl, string $range = '28d', bool $forceRefresh = false): array|\WP_Error
    {
        $cacheKey = 'laca_gsc_pages_' . md5($siteUrl . '_' . $range);
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google Search Console.', 'lacadev'));
        }

        $days = match ($range) {
            '7d', '7'   => 7,
            '90d', '90' => 90,
            default     => 28,
        };

        $endDate   = date('Y-m-d', strtotime('-3 days'));
        $startDate = date('Y-m-d', strtotime('-' . ($days + 2) . ' days'));

        $requestBody = [
            'startDate'  => $startDate,
            'endDate'    => $endDate,
            'dimensions' => ['page'],
            'rowLimit'   => 15,
        ];

        $url = self::API_BASE . '/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';
        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($requestBody),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            $rawMsg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : '';
            return new \WP_Error('http_' . $code, sprintf(__('Lỗi GSC Pages (%d): %s', 'lacadev'), $code, $rawMsg));
        }

        $pages = [];
        $rows = $data['rows'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rawUrl = (string) ($row['keys'][0] ?? '');
                $displayPath = $rawUrl;
                if (str_contains($rawUrl, '://')) {
                    $parsed = wp_parse_url($rawUrl, PHP_URL_PATH);
                    if ($parsed) {
                        $displayPath = $parsed;
                    }
                }
                $pages[] = [
                    'url'         => $displayPath,
                    'fullUrl'     => $rawUrl,
                    'clicks'      => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr'         => round(((float) ($row['ctr'] ?? 0)) * 100, 2),
                    'position'    => round((float) ($row['position'] ?? 0), 1),
                ];
            }
        }

        set_transient($cacheKey, $pages, HOUR_IN_SECONDS);
        return $pages;
    }

    /**
     * Parse raw searchanalytics response into structured metrics.
     *
     * @param array $data
     * @return array{totalClicks: int, totalImpressions: int, avgCtr: float, avgPosition: float, rows: array, queries: array}
     */
    public static function parsePerformanceResponse(array $data): array
    {
        $rows = $data['rows'] ?? [];
        $totalClicks = 0;
        $totalImpressions = 0;
        $totalCtr = 0;
        $totalPosition = 0;
        $count = count($rows);

        $queries = [];
        foreach ($rows as $row) {
            $clicks      = (int) ($row['clicks'] ?? 0);
            $impressions = (int) ($row['impressions'] ?? 0);
            $ctr         = (float) ($row['ctr'] ?? 0);
            $position    = (float) ($row['position'] ?? 0);

            $totalClicks      += $clicks;
            $totalImpressions += $impressions;
            $totalCtr         += $ctr;
            $totalPosition    += $position;

            $queryKey = $row['keys'][0] ?? ($row['query'] ?? '');
            if ($queryKey !== '') {
                $queries[] = [
                    'query'       => $queryKey,
                    'clicks'      => $clicks,
                    'impressions' => $impressions,
                    'ctr'         => round($ctr * 100, 2),
                    'position'    => round($position, 1),
                ];
            }
        }

        return [
            'totalClicks'      => $totalClicks,
            'totalImpressions' => $totalImpressions,
            'avgCtr'           => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : ($count > 0 ? round(($totalCtr / $count) * 100, 2) : 0),
            'avgPosition'      => $count > 0 ? round($totalPosition / $count, 1) : 0,
            'rows'             => $rows,
            'queries'          => array_slice($queries, 0, 10),
        ];
    }

    /**
     * Get submitted sitemaps for site.
     *
     * @param string $siteUrl
     * @param bool $forceRefresh
     * @return array|null
     */
    public static function getSitemaps(string $siteUrl, bool $forceRefresh = false): ?array
    {
        $cacheKey = 'laca_gsc_sm_' . md5($siteUrl);
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return null;
        }

        $url = self::API_BASE . '/sites/' . urlencode($siteUrl) . '/sitemaps';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return null;
        }

        $parsed = self::parseSitemapsResponse($data);
        set_transient($cacheKey, $parsed, 6 * HOUR_IN_SECONDS);

        return $parsed;
    }

    /**
     * Parse raw sitemaps response.
     *
     * @param array $data
     * @return array{sitemaps: array, totalSubmitted: int, totalIndexed: int}
     */
    public static function parseSitemapsResponse(array $data): array
    {
        $sitemapList = $data['sitemap'] ?? [];
        $totalSubmitted = 0;
        $totalIndexed = 0;

        foreach ($sitemapList as $sm) {
            $contents = $sm['contents'] ?? [];
            foreach ($contents as $c) {
                $totalSubmitted += (int) ($c['submitted'] ?? 0);
                $totalIndexed   += (int) ($c['indexed'] ?? 0);
            }
        }

        return [
            'sitemaps'       => $sitemapList,
            'totalSubmitted' => $totalSubmitted,
            'totalIndexed'   => $totalIndexed,
        ];
    }

    /**
     * Inspect a specific URL.
     *
     * @param string $siteUrl
     * @param string $inspectionUrl
     * @param bool $forceRefresh
     * @return array|null
     */
    public static function inspectUrl(string $siteUrl, string $inspectionUrl, bool $forceRefresh = false): ?array
    {
        $cacheKey = 'laca_gsc_insp_' . md5($siteUrl . '_' . $inspectionUrl);
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return null;
        }

        $url = self::API_BASE . '/urlInspection/index:inspect';
        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode([
                'inspectionUrl' => $inspectionUrl,
                'siteUrl'       => $siteUrl,
            ]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return null;
        }

        $parsed = self::parseInspectionResponse($data);
        set_transient($cacheKey, $parsed, 24 * HOUR_IN_SECONDS);

        return $parsed;
    }

    /**
     * Parse raw urlInspection response.
     *
     * @param array $data
     * @return array{verdict: string, coverageState: string, lastCrawlTime: string, mobileUsability: string, richResultsVerdict: string, raw: array}
     */
    public static function parseInspectionResponse(array $data): array
    {
        $res = $data['inspectionResult'] ?? [];
        $indexStatus = $res['indexStatusResult'] ?? [];
        $mobileResult = $res['mobileUsabilityResult'] ?? [];
        $richResult   = $res['richResultsResult'] ?? [];

        return [
            'verdict'            => $indexStatus['verdict'] ?? 'NEUTRAL',
            'coverageState'      => $indexStatus['coverageState'] ?? 'UNKNOWN',
            'lastCrawlTime'      => $indexStatus['lastCrawlTime'] ?? '',
            'mobileUsability'    => $mobileResult['verdict'] ?? 'UNKNOWN',
            'richResultsVerdict' => $richResult['verdict'] ?? 'UNKNOWN',
            'raw'                => $res,
        ];
    }
}
