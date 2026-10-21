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

    /**
     * Get 28-day performance statistics.
     *
     * @param string $siteUrl
     * @param bool $forceRefresh
     * @return array|null
     */
    public static function getPerformanceSummary(string $siteUrl, bool $forceRefresh = false): ?array
    {
        $cacheKey = 'laca_gsc_perf_' . md5($siteUrl);
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

        $endDate   = date('Y-m-d', strtotime('-3 days'));
        $startDate = date('Y-m-d', strtotime('-31 days'));

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
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return null;
        }

        $parsed = self::parsePerformanceResponse($data);
        set_transient($cacheKey, $parsed, 12 * HOUR_IN_SECONDS);

        return $parsed;
    }

    /**
     * Parse raw searchanalytics response into structured metrics.
     *
     * @param array $data
     * @return array{totalClicks: int, totalImpressions: int, avgCtr: float, avgPosition: float, rows: array}
     */
    public static function parsePerformanceResponse(array $data): array
    {
        $rows = $data['rows'] ?? [];
        $totalClicks = 0;
        $totalImpressions = 0;
        $totalCtr = 0;
        $totalPosition = 0;
        $count = count($rows);

        foreach ($rows as $row) {
            $totalClicks += (int) ($row['clicks'] ?? 0);
            $totalImpressions += (int) ($row['impressions'] ?? 0);
            $totalCtr += (float) ($row['ctr'] ?? 0);
            $totalPosition += (float) ($row['position'] ?? 0);
        }

        return [
            'totalClicks'       => $totalClicks,
            'totalImpressions'  => $totalImpressions,
            'avgCtr'            => $count > 0 ? round(($totalCtr / $count) * 100, 2) : 0,
            'avgPosition'       => $count > 0 ? round($totalPosition / $count, 1) : 0,
            'rows'              => $rows,
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
