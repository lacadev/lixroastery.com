<?php

namespace App\Features\LacaSeo\Modules\GoogleAnalytics;

use App\Features\LacaSeo\Modules\SearchConsole\GscOAuth;

defined('ABSPATH') || exit;

/**
 * Client for Google Analytics 4 Admin and Data REST APIs:
 * - Admin API (accountSummaries, dataStreams) for auto-discovering Property IDs from Measurement IDs
 * - Data API (runReport, runRealtimeReport) for dashboard traffic and engagement metrics
 * Strictly validates HTTP response codes and never caches error responses.
 */
class GaClient
{
    public const ADMIN_API_BASE = 'https://analyticsadmin.googleapis.com/v1beta';
    public const DATA_API_BASE  = 'https://analyticsdata.googleapis.com/v1beta';

    /**
     * List all accessible GA4 properties across accounts.
     *
     * @param bool $forceRefresh
     * @return array<array{id: string, displayName: string, account: string, accountName: string}>|\WP_Error
     */
    public static function listProperties(bool $forceRefresh = false): array|\WP_Error
    {
        $cacheKey = 'laca_ga_props_list';
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google.', 'lacadev'));
        }

        $url = self::ADMIN_API_BASE . '/accountSummaries';
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
            $rawMsg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : '';
            if ($code === 403) {
                $msg = __('Tài khoản Google chưa có quyền truy cập Google Analytics hoặc chưa bật Google Analytics Admin API.', 'lacadev');
            } else {
                $msg = sprintf(__('Lỗi Google Analytics Admin (%d)%s', 'lacadev'), $code, $rawMsg ? ': ' . $rawMsg : '');
            }

            return new \WP_Error('http_' . $code, $msg, [
                'status' => $code,
                'data'   => $data,
            ]);
        }

        $properties = [];
        $summaries = $data['accountSummaries'] ?? [];
        if (is_array($summaries)) {
            foreach ($summaries as $account) {
                $accountName = (string) ($account['displayName'] ?? '');
                $accountRef  = (string) ($account['name'] ?? '');
                $propSummaries = $account['propertySummaries'] ?? [];
                if (is_array($propSummaries)) {
                    foreach ($propSummaries as $prop) {
                        $propRef = (string) ($prop['property'] ?? '');
                        $numericId = str_replace('properties/', '', $propRef);
                        if ($numericId !== '') {
                            $properties[] = [
                                'id'          => $numericId,
                                'propertyId'  => $numericId,
                                'displayName' => (string) ($prop['displayName'] ?? $numericId),
                                'account'     => $accountRef,
                                'accountName' => $accountName,
                            ];
                        }
                    }
                }
            }
        }

        set_transient($cacheKey, $properties, 12 * HOUR_IN_SECONDS);
        return $properties;
    }

    /**
     * Get data streams for a specific property.
     *
     * @param string $propertyId
     * @param bool $forceRefresh
     * @return array<array{id: string, name: string, type: string, displayName: string, measurementId: string, defaultUri: string}>|\WP_Error
     */
    public static function getDataStreams(string $propertyId, bool $forceRefresh = false): array|\WP_Error
    {
        $propertyId = preg_replace('/\D/', '', $propertyId);
        if ($propertyId === '') {
            return new \WP_Error('invalid_property', __('Mã Property ID không hợp lệ.', 'lacadev'));
        }

        $cacheKey = 'laca_ga_streams_' . $propertyId;
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google.', 'lacadev'));
        }

        $url = self::ADMIN_API_BASE . '/properties/' . $propertyId . '/dataStreams';
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
            $rawMsg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : '';
            return new \WP_Error('http_' . $code, sprintf(__('Lỗi lấy luồng dữ liệu GA4 (%d): %s', 'lacadev'), $code, $rawMsg), [
                'status' => $code,
                'data'   => $data,
            ]);
        }

        $streams = [];
        $rawStreams = $data['dataStreams'] ?? [];
        if (is_array($rawStreams)) {
            foreach ($rawStreams as $stream) {
                $webData = $stream['webStreamData'] ?? [];
                $streams[] = [
                    'id'            => (string) ($stream['name'] ?? ''),
                    'name'          => (string) ($stream['name'] ?? ''),
                    'type'          => (string) ($stream['type'] ?? 'WEB_DATA_STREAM'),
                    'displayName'   => (string) ($stream['displayName'] ?? ''),
                    'measurementId' => (string) ($webData['measurementId'] ?? ''),
                    'defaultUri'    => (string) ($webData['defaultUri'] ?? ''),
                ];
            }
        }

        set_transient($cacheKey, $streams, 12 * HOUR_IN_SECONDS);
        return $streams;
    }

    /**
     * Search accessible properties to find one whose web stream has the matching Measurement ID.
     *
     * @param string $targetMeasurementId
     * @return array{id: string, displayName: string, measurementId: string}|null
     */
    public static function findPropertyByMeasurementId(string $targetMeasurementId, bool $forceRefresh = false): ?array
    {
        $targetMeasurementId = trim(strtoupper($targetMeasurementId));
        if ($targetMeasurementId === '') {
            return null;
        }

        $props = self::listProperties($forceRefresh);
        if (is_wp_error($props) || empty($props)) {
            return null;
        }

        foreach ($props as $prop) {
            $streams = self::getDataStreams($prop['id'], $forceRefresh);
            if (is_wp_error($streams) || empty($streams)) {
                continue;
            }

            foreach ($streams as $stream) {
                if (strcasecmp($stream['measurementId'] ?? '', $targetMeasurementId) === 0) {
                    return [
                        'id'            => $prop['id'],
                        'propertyId'    => $prop['id'],
                        'displayName'   => $prop['displayName'],
                        'measurementId' => $stream['measurementId'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Run GA4 report for the given property and range (7d, 28d, 90d).
     *
     * @param string $propertyId
     * @param string $range
     * @param bool $forceRefresh
     * @return array|\WP_Error
     */
    public static function runReport(string $propertyId, string $range = '28d', bool $forceRefresh = false): array|\WP_Error
    {
        $propertyId = preg_replace('/\D/', '', $propertyId);
        if ($propertyId === '') {
            return new \WP_Error('invalid_property', __('Chưa chọn Property ID của Google Analytics 4.', 'lacadev'));
        }

        $cacheKey = 'laca_ga_rep_' . md5($propertyId . '_' . $range);
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google hoặc phiên đã hết hạn.', 'lacadev'));
        }

        $days = match ($range) {
            '7d', '7'   => 7,
            '90d', '90' => 90,
            default     => 28,
        };

        $prevDays    = 2 * $days;
        $prevEndDays = $days + 1;

        // Query metrics with date dimension across 2 date ranges for real comparison
        $requestBody = [
            'dateRanges' => [
                ['name' => 'date_range_0', 'startDate' => "{$days}daysAgo", 'endDate' => 'yesterday'],
                ['name' => 'date_range_1', 'startDate' => "{$prevDays}daysAgo", 'endDate' => "{$prevEndDays}daysAgo"],
            ],
            'dimensions' => [
                ['name' => 'date'],
            ],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'newUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'engagementRate'],
                ['name' => 'keyEvents'],
            ],
            'orderBys' => [
                ['dimension' => ['dimensionName' => 'date'], 'desc' => false],
            ],
            'limit' => 250,
        ];

        $url = self::DATA_API_BASE . '/properties/' . $propertyId . ':runReport';
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
                $msg = __('Tài khoản Google chưa có quyền truy cập Property này hoặc chưa bật Google Analytics Data API.', 'lacadev');
            } elseif ($code === 404) {
                $msg = __('Không tìm thấy GA4 Property. Vui lòng chọn lại property.', 'lacadev');
            } else {
                $msg = sprintf(__('Lỗi Google Analytics (%d)%s', 'lacadev'), $code, $rawMsg ? ': ' . $rawMsg : '');
            }

            return new \WP_Error('http_' . $code, $msg, [
                'status' => $code,
                'data'   => $data,
            ]);
        }

        if (!is_array($data)) {
            return new \WP_Error('invalid_response', __('Phản hồi từ Google Analytics không hợp lệ.', 'lacadev'));
        }

        $parsed = self::parseReportResponse($data);
        $parsed['propertyId'] = $propertyId;
        $parsed['range']      = $range;

        // Fetch top pages in background or secondary query
        $topPages = self::runDimensionReport($propertyId, $days, ['pagePath', 'pageTitle'], 'screenPageViews', 10);
        if (is_array($topPages)) {
            $parsed['topPages'] = $topPages;
        }

        // Fetch channels breakdown
        $channels = self::runDimensionReport($propertyId, $days, ['sessionDefaultChannelGroup'], 'sessions', 5);
        if (is_array($channels)) {
            $parsed['channels'] = $channels;
        }

        // Fetch device breakdown
        $devices = self::runDimensionReport($propertyId, $days, ['deviceCategory'], 'sessions', 5);
        if (is_array($devices)) {
            $parsed['devices'] = $devices;
        }

        // Cache 1 hour
        set_transient($cacheKey, $parsed, HOUR_IN_SECONDS);

        return $parsed;
    }

    /**
     * Run a sub-report for dimensions like pagePath, channel, device.
     *
     * @param string $propertyId
     * @param int $days
     * @param string[] $dimensionNames
     * @param string $metricName
     * @param int $limit
     * @return array<array<string,mixed>>|null
     */
    private static function runDimensionReport(string $propertyId, int $days, array $dimensionNames, string $metricName, int $limit = 10): ?array
    {
        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return null;
        }

        $dimensions = array_map(static fn($d) => ['name' => $d], $dimensionNames);
        $body = [
            'dateRanges' => [
                ['startDate' => "{$days}daysAgo", 'endDate' => 'yesterday'],
            ],
            'dimensions' => $dimensions,
            'metrics'    => [
                ['name' => $metricName],
            ],
            'orderBys' => [
                ['metric' => ['metricName' => $metricName], 'desc' => true],
            ],
            'limit' => $limit,
        ];

        $url = self::DATA_API_BASE . '/properties/' . $propertyId . ':runReport';
        $response = wp_remote_post($url, [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($body),
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['rows'])) {
            return [];
        }

        $results = [];
        foreach ($data['rows'] as $row) {
            $dimValues = array_map(static fn($v) => (string) ($v['value'] ?? ''), $row['dimensionValues'] ?? []);
            $metricVal = (int) ($row['metricValues'][0]['value'] ?? 0);

            $item = [
                'metric' => $metricVal,
            ];
            foreach ($dimensionNames as $i => $name) {
                $item[$name] = $dimValues[$i] ?? '';
            }
            $results[] = $item;
        }

        return $results;
    }

    /**
     * Parse raw GA4 Data API runReport response.
     *
     * @param array $data
     * @return array{users: int, newUsers: int, sessions: int, pageViews: int, avgSessionDuration: float, engagementRate: float, byDate: array, topPages: array, channels: array, devices: array}
     */
    public static function parseReportResponse(array $data): array
    {
        $rows = $data['rows'] ?? [];

        $metricIndexes = [];
        if (!empty($data['metricHeaders']) && is_array($data['metricHeaders'])) {
            foreach ($data['metricHeaders'] as $idx => $header) {
                $name = $header['name'] ?? '';
                if ($name !== '') {
                    $metricIndexes[$name] = $idx;
                }
            }
        }

        $usersIdx      = $metricIndexes['activeUsers'] ?? $metricIndexes['totalUsers'] ?? 0;
        $newUsersIdx   = $metricIndexes['newUsers'] ?? 1;
        $sessionsIdx   = $metricIndexes['sessions'] ?? 2;
        $pageViewsIdx  = $metricIndexes['screenPageViews'] ?? 3;
        $durationIdx   = $metricIndexes['averageSessionDuration'] ?? 4;
        $engagementIdx = $metricIndexes['engagementRate'] ?? 5;
        $keyEventsIdx  = $metricIndexes['keyEvents'] ?? $metricIndexes['conversions'] ?? null;

        $hasPrevData = false;
        $currTotals = null;
        $prevTotals = null;

        if (!empty($data['totals']) && is_array($data['totals'])) {
            if (count($data['totals']) >= 2) {
                $hasPrevData = true;
                $assigned = false;
                foreach ($data['totals'] as $tot) {
                    $dimVals = array_map(static fn($v) => (string) ($v['value'] ?? ''), $tot['dimensionValues'] ?? []);
                    if (in_array('date_range_1', $dimVals, true)) {
                        $prevTotals = $tot['metricValues'] ?? null;
                        $assigned = true;
                    } elseif (in_array('date_range_0', $dimVals, true)) {
                        $currTotals = $tot['metricValues'] ?? null;
                        $assigned = true;
                    }
                }
                if (!$assigned) {
                    $currTotals = $data['totals'][0]['metricValues'] ?? null;
                    $prevTotals = $data['totals'][1]['metricValues'] ?? null;
                }
            } else {
                $currTotals = $data['totals'][0]['metricValues'] ?? null;
            }
        }

        $totalUsers       = 0;
        $totalNewUsers    = 0;
        $totalSessions    = 0;
        $totalPageViews   = 0;
        $totalAvgDuration = 0.0;
        $totalEngagement  = 0.0;
        $totalKeyEvents   = 0;

        $prevUsers       = 0;
        $prevNewUsers    = 0;
        $prevSessions    = 0;
        $prevPageViews   = 0;
        $prevAvgDuration = 0.0;
        $prevEngagement  = 0.0;
        $prevKeyEvents   = 0;

        if (is_array($currTotals) && !empty($currTotals)) {
            $totalUsers       = (int) ($currTotals[$usersIdx]['value'] ?? 0);
            $totalNewUsers    = (int) ($currTotals[$newUsersIdx]['value'] ?? 0);
            $totalSessions    = (int) ($currTotals[$sessionsIdx]['value'] ?? 0);
            $totalPageViews   = (int) ($currTotals[$pageViewsIdx]['value'] ?? 0);
            $totalAvgDuration = round((float) ($currTotals[$durationIdx]['value'] ?? 0), 1);
            $totalEngagement  = round(((float) ($currTotals[$engagementIdx]['value'] ?? 0)) * 100, 2);
            $totalKeyEvents   = $keyEventsIdx !== null ? (int) ($currTotals[$keyEventsIdx]['value'] ?? 0) : 0;
        }

        if (is_array($prevTotals) && !empty($prevTotals)) {
            $prevUsers       = (int) ($prevTotals[$usersIdx]['value'] ?? 0);
            $prevNewUsers    = (int) ($prevTotals[$newUsersIdx]['value'] ?? 0);
            $prevSessions    = (int) ($prevTotals[$sessionsIdx]['value'] ?? 0);
            $prevPageViews   = (int) ($prevTotals[$pageViewsIdx]['value'] ?? 0);
            $prevAvgDuration = round((float) ($prevTotals[$durationIdx]['value'] ?? 0), 1);
            $prevEngagement  = round(((float) ($prevTotals[$engagementIdx]['value'] ?? 0)) * 100, 2);
            $prevKeyEvents   = $keyEventsIdx !== null ? (int) ($prevTotals[$keyEventsIdx]['value'] ?? 0) : 0;
        }

        if ($currTotals === null) {
            $dateRangeDimIdx = null;
            if (!empty($data['dimensionHeaders']) && is_array($data['dimensionHeaders'])) {
                foreach ($data['dimensionHeaders'] as $idx => $hdr) {
                    if (($hdr['name'] ?? '') === 'dateRange') {
                        $dateRangeDimIdx = $idx;
                        break;
                    }
                }
            }

            $currCount = 0;
            $currSumDuration = 0.0;
            $currSumEngagement = 0.0;

            $prevCount = 0;
            $prevSumDuration = 0.0;
            $prevSumEngagement = 0.0;

            foreach ($rows as $row) {
                $mv = $row['metricValues'] ?? [];
                $dimVals = array_map(static fn($v) => (string) ($v['value'] ?? ''), $row['dimensionValues'] ?? []);
                $isPrev = false;

                if ($dateRangeDimIdx !== null) {
                    $isPrev = (($dimVals[$dateRangeDimIdx] ?? '') === 'date_range_1');
                } else {
                    foreach ($dimVals as $dv) {
                        if ($dv === 'date_range_1') {
                            $isPrev = true;
                            break;
                        }
                    }
                }

                if ($isPrev) {
                    $hasPrevData = true;
                    $prevCount++;
                    $prevUsers        += (int) ($mv[$usersIdx]['value'] ?? 0);
                    $prevNewUsers     += (int) ($mv[$newUsersIdx]['value'] ?? 0);
                    $prevSessions     += (int) ($mv[$sessionsIdx]['value'] ?? 0);
                    $prevPageViews    += (int) ($mv[$pageViewsIdx]['value'] ?? 0);
                    $prevSumDuration  += (float) ($mv[$durationIdx]['value'] ?? 0);
                    $prevSumEngagement += (float) ($mv[$engagementIdx]['value'] ?? 0);
                    if ($keyEventsIdx !== null) {
                        $prevKeyEvents += (int) ($mv[$keyEventsIdx]['value'] ?? 0);
                    }
                } else {
                    $currCount++;
                    $totalUsers        += (int) ($mv[$usersIdx]['value'] ?? 0);
                    $totalNewUsers     += (int) ($mv[$newUsersIdx]['value'] ?? 0);
                    $totalSessions     += (int) ($mv[$sessionsIdx]['value'] ?? 0);
                    $totalPageViews    += (int) ($mv[$pageViewsIdx]['value'] ?? 0);
                    $currSumDuration   += (float) ($mv[$durationIdx]['value'] ?? 0);
                    $currSumEngagement += (float) ($mv[$engagementIdx]['value'] ?? 0);
                    if ($keyEventsIdx !== null) {
                        $totalKeyEvents += (int) ($mv[$keyEventsIdx]['value'] ?? 0);
                    }
                }
            }

            $totalAvgDuration = $currCount > 0 ? round($currSumDuration / $currCount, 1) : 0.0;
            $totalEngagement  = $currCount > 0 ? round(($currSumEngagement / $currCount) * 100, 2) : 0.0;

            if ($prevCount > 0) {
                $prevAvgDuration = round($prevSumDuration / $prevCount, 1);
                $prevEngagement  = round(($prevSumEngagement / $prevCount) * 100, 2);
            }
        }

        $byDate = [];
        $topPages = [];
        foreach ($rows as $row) {
            $dimVals = array_map(static fn($v) => (string) ($v['value'] ?? ''), $row['dimensionValues'] ?? []);
            $mv = $row['metricValues'] ?? [];

            // Filter out date_range_1 rows from byDate/topPages charts so charts show current period
            if (in_array('date_range_1', $dimVals, true)) {
                continue;
            }

            // Extract date or pagePath
            $nonRangeVals = array_values(array_filter($dimVals, static fn($v) => $v !== 'date_range_0' && $v !== 'date_range_1'));
            $firstVal = $nonRangeVals[0] ?? '';

            if ($firstVal !== '' && preg_match('/^\d{8}$/', $firstVal)) {
                $date = $firstVal;
                $formattedDate = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
                $byDate[] = [
                    'date'      => $formattedDate,
                    'users'     => (int) ($mv[$usersIdx]['value'] ?? 0),
                    'sessions'  => (int) ($mv[$sessionsIdx]['value'] ?? 0),
                    'pageViews' => (int) ($mv[$pageViewsIdx]['value'] ?? 0),
                    'pageviews' => (int) ($mv[$pageViewsIdx]['value'] ?? 0),
                ];
            } else {
                $topPages[] = [
                    'pagePath'  => $firstVal,
                    'pageTitle' => $nonRangeVals[1] ?? $firstVal,
                    'users'     => (int) ($mv[$usersIdx]['value'] ?? 0),
                    'sessions'  => (int) ($mv[$sessionsIdx]['value'] ?? 0),
                    'pageViews' => (int) ($mv[$pageViewsIdx]['value'] ?? 0),
                    'pageviews' => (int) ($mv[$pageViewsIdx]['value'] ?? 0),
                ];
            }
        }

        return [
            'users'              => $totalUsers,
            'newUsers'           => $totalNewUsers,
            'sessions'           => $totalSessions,
            'pageViews'          => $totalPageViews,
            'pageviews'          => $totalPageViews,
            'avgSessionDuration' => $totalAvgDuration,
            'engagementRate'     => $totalEngagement,
            'keyEvents'          => $totalKeyEvents,

            'prevUsers'              => $prevUsers,
            'prevNewUsers'           => $prevNewUsers,
            'prevSessions'           => $prevSessions,
            'prevPageViews'          => $prevPageViews,
            'prevAvgSessionDuration' => $prevAvgDuration,
            'prevEngagementRate'     => $prevEngagement,
            'prevKeyEvents'          => $prevKeyEvents,
            'hasPrevData'            => $hasPrevData,

            'summary'            => [
                'activeUsers'            => $totalUsers,
                'newUsers'               => $totalNewUsers,
                'sessions'               => $totalSessions,
                'screenPageViews'        => $totalPageViews,
                'averageSessionDuration' => $totalAvgDuration,
                'engagementRate'         => $totalEngagement,
                'keyEvents'              => $totalKeyEvents,

                'prevActiveUsers'            => $prevUsers,
                'prevSessions'               => $prevSessions,
                'prevAverageSessionDuration' => $prevAvgDuration,
                'prevKeyEvents'              => $prevKeyEvents,
                'hasPrevData'                => $hasPrevData,
            ],

            'byDate'             => $byDate,
            'topPages'           => $topPages,
            'rows'               => $topPages,
            'channels'           => [],
            'devices'            => [],
        ];
    }

    /**
     * Run goal events report for configured business events across 2 date ranges.
     *
     * @param string $propertyId
     * @param int $days
     * @param array<string, string> $goalEvents Map of event_name => display_label
     * @param bool $forceRefresh
     * @return array<array{title: string, value: int, delta: string, badge: string}>|\WP_Error
     */
    public static function runGoalEventsReport(string $propertyId, int $days, array $goalEvents, bool $forceRefresh = false): array|\WP_Error
    {
        $propertyId = preg_replace('/\D/', '', $propertyId);
        if ($propertyId === '' || empty($goalEvents)) {
            return [];
        }

        $cacheKey = 'laca_ga_goals_' . md5($propertyId . '_' . $days . '_' . serialize($goalEvents));
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google hoặc phiên đã hết hạn.', 'lacadev'));
        }

        $prevDays    = 2 * $days;
        $prevEndDays = $days + 1;

        $requestBody = [
            'dateRanges' => [
                ['name' => 'date_range_0', 'startDate' => "{$days}daysAgo", 'endDate' => 'yesterday'],
                ['name' => 'date_range_1', 'startDate' => "{$prevDays}daysAgo", 'endDate' => "{$prevEndDays}daysAgo"],
            ],
            'dimensions' => [
                ['name' => 'eventName'],
            ],
            'metrics' => [
                ['name' => 'eventCount'],
            ],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'eventName',
                    'inListFilter' => [
                        'values' => array_keys($goalEvents),
                    ],
                ],
            ],
            'limit' => 100,
        ];

        $url = self::DATA_API_BASE . '/properties/' . $propertyId . ':runReport';
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
            return new \WP_Error('http_' . $code, sprintf(__('Lỗi truy vấn sự kiện mục tiêu GA4 (%d): %s', 'lacadev'), $code, $rawMsg));
        }

        if (!is_array($data)) {
            return new \WP_Error('invalid_response', __('Phản hồi sự kiện GA4 không hợp lệ.', 'lacadev'));
        }

        $dateRangeIdx = null;
        $eventNameIdx = null;
        if (!empty($data['dimensionHeaders']) && is_array($data['dimensionHeaders'])) {
            foreach ($data['dimensionHeaders'] as $idx => $hdr) {
                $name = $hdr['name'] ?? '';
                if ($name === 'dateRange') {
                    $dateRangeIdx = $idx;
                } elseif ($name === 'eventName') {
                    $eventNameIdx = $idx;
                }
            }
        }
        if ($eventNameIdx === null) {
            $eventNameIdx = 0;
        }

        $currCounts = [];
        $prevCounts = [];
        foreach ($goalEvents as $eName => $eLabel) {
            $currCounts[$eName] = 0;
            $prevCounts[$eName] = 0;
        }

        $rows = $data['rows'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $dimVals = array_map(static fn($v) => (string) ($v['value'] ?? ''), $row['dimensionValues'] ?? []);
                $count   = (int) ($row['metricValues'][0]['value'] ?? 0);

                $dateRange = $dateRangeIdx !== null ? ($dimVals[$dateRangeIdx] ?? 'date_range_0') : 'date_range_0';
                $eventName = $dimVals[$eventNameIdx] ?? '';

                if (!isset($goalEvents[$eventName])) {
                    foreach ($dimVals as $dv) {
                        if (isset($goalEvents[$dv])) {
                            $eventName = $dv;
                            break;
                        }
                    }
                }

                if (isset($goalEvents[$eventName])) {
                    if ($dateRange === 'date_range_1') {
                        $prevCounts[$eventName] += $count;
                    } else {
                        $currCounts[$eventName] += $count;
                    }
                }
            }
        }

        $kpis = [];
        foreach ($goalEvents as $eName => $eLabel) {
            $curr = $currCounts[$eName] ?? 0;
            $prev = $prevCounts[$eName] ?? 0;

            if ($prev <= 0) {
                $delta = '—';
            } else {
                $diff = $curr - $prev;
                $pct = round(($diff / $prev) * 100, 1);
                $sign = $pct > 0 ? '+' : '';
                $delta = $sign . $pct . '% vs kỳ trước';
            }

            $kpis[] = [
                'title' => $eLabel,
                'value' => $curr,
                'delta' => $delta,
                'badge' => $eName,
            ];
        }

        set_transient($cacheKey, $kpis, HOUR_IN_SECONDS);
        return $kpis;
    }

    /**
     * Run realtime active users report (last 30 minutes).
     *
     * @param string $propertyId
     * @param bool $forceRefresh
     * @return array{activeUsers: int}|\WP_Error
     */
    public static function runRealtimeReport(string $propertyId, bool $forceRefresh = false): array|\WP_Error
    {
        $propertyId = preg_replace('/\D/', '', $propertyId);
        if ($propertyId === '') {
            return new \WP_Error('invalid_property', __('Chưa chọn Property ID của Google Analytics 4.', 'lacadev'));
        }

        $cacheKey = 'laca_ga_realtime_' . $propertyId;
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = GscOAuth::getValidAccessToken();
        if (!$token) {
            return new \WP_Error('no_token', __('Chưa kết nối tài khoản Google.', 'lacadev'));
        }

        $url = self::DATA_API_BASE . '/properties/' . $propertyId . ':runRealtimeReport';
        $response = wp_remote_post($url, [
            'timeout' => 8,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode([
                'metrics' => [
                    ['name' => 'activeUsers'],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            return new \WP_Error('http_' . $code, sprintf(__('Lỗi Google Analytics Realtime (%d)', 'lacadev'), $code), [
                'status' => $code,
                'data'   => $data,
            ]);
        }

        $activeUsers = 0;
        if (!empty($data['rows'][0]['metricValues'][0]['value'])) {
            $activeUsers = (int) $data['rows'][0]['metricValues'][0]['value'];
        }

        $result = ['activeUsers' => $activeUsers];
        // Cache 60 seconds
        set_transient($cacheKey, $result, 60);

        return $result;
    }
}
