<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Service providing combined, formatted Google Search Console and GA4 data for Dashboard.
 * Supports live API queries with transient caching, error detection, and rich mock fallbacks.
 */
class GoogleDashboardService
{
    /**
     * Determines whether mock data should be displayed.
     * Active on local environment when not connected, or when explicit query parameter is set.
     */
    public static function isMockMode(): bool
    {
        $env = apply_filters('pre_wp_get_environment_type', null);
        if ($env === null && function_exists('wp_get_environment_type')) {
            $env = wp_get_environment_type();
        }

        if (
            !empty($_GET['laca_mock_google'])
            && $env === 'local'
            && current_user_can('manage_options')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get Google Search Console dataset for given range.
     *
     * @param string $range '7d' | '28d' | '90d'
     * @param bool $forceRefresh
     * @return array<string, mixed>
     */
    public static function getGscData(string $range = '28d', bool $forceRefresh = false): array
    {
        $isConnected = GscOAuth::isConnected();

        if (!$isConnected && !self::isMockMode()) {
            return [
                'connected' => false,
                'setup_url' => admin_url('admin.php?page=laca-google-search-console'),
            ];
        }

        if (self::isMockMode()) {
            return self::getMockGscData($range);
        }

        $siteUrl = GscClient::getSiteUrl();
        if (empty($siteUrl)) {
            return [
                'connected' => true,
                'error'     => __('Chưa chọn Property Google Search Console.', 'lacadev'),
                'setup_url' => admin_url('admin.php?page=laca-google-search-console'),
            ];
        }

        $result = GscClient::getPerformanceSummary($siteUrl, $range, $forceRefresh);
        if (is_wp_error($result)) {
            $status = (int) ($result->get_error_data()['status'] ?? 0);
            $msg = $result->get_error_message();
            if ($status === 404) {
                $msg = sprintf(
                    __('Không tìm thấy property "%s" trong Google Search Console. Hãy kiểm tra lại hoặc chọn property khác.', 'lacadev'),
                    $siteUrl
                );
            } elseif ($status === 403) {
                $email = GscOAuth::getConnectedEmail();
                $emailDisplay = !empty($email) ? $email : __('Chưa rõ email, hãy kết nối lại để hiển thị', 'lacadev');
                $msg = sprintf(
                    __('Tài khoản %s chưa có property này. Ngắt kết nối và đăng nhập bằng tài khoản sở hữu site, hoặc thêm %s vào Search Console → Cài đặt → Người dùng và quyền.', 'lacadev'),
                    $emailDisplay,
                    $emailDisplay
                );
            }
            return [
                'connected' => true,
                'error'     => $msg,
                'status'    => $status,
                'siteUrl'   => $siteUrl,
                'setup_url' => admin_url('admin.php?page=laca-google-search-console'),
            ];
        }

        $queries = $result['queries'] ?? [];
        $pages = $result['pages'] ?? [];

        $clicks = (int) ($result['totalClicks'] ?? 0);
        $prevClicks = isset($result['prevClicks']) && !empty($result['hasPrevData']) ? (int) $result['prevClicks'] : null;
        $clicksDelta = self::calcPercentDelta($clicks, $prevClicks);

        $impressions = (int) ($result['totalImpressions'] ?? 0);
        $prevImpressions = isset($result['prevImpressions']) && !empty($result['hasPrevData']) ? (int) $result['prevImpressions'] : null;
        $impressionsDelta = self::calcPercentDelta($impressions, $prevImpressions);

        $ctr = (float) ($result['avgCtr'] ?? 0);
        $prevCtr = isset($result['prevCtr']) && !empty($result['hasPrevData']) ? (float) $result['prevCtr'] : null;
        $ctrDelta = self::calcPointDelta($ctr, $prevCtr);

        $position = (float) ($result['avgPosition'] ?? 0);
        $prevPosition = isset($result['prevPosition']) && !empty($result['hasPrevData']) ? (float) $result['prevPosition'] : null;
        $positionDelta = self::calcPositionDelta($position, $prevPosition);

        $isZeroData  = ($clicks === 0 && $impressions === 0 && empty($queries) && empty($pages));
        $hasDataEver = (bool) get_option('laca_google_data_available_gsc', 0);
        $isCollecting = ($isZeroData && !$hasDataEver);

        return [
            'connected'         => true,
            'isMock'            => false,
            'siteUrl'           => $siteUrl,
            'clicks'            => $clicks,
            'clicks_delta'      => $clicksDelta,
            'impressions'       => $impressions,
            'impressions_delta' => $impressionsDelta,
            'ctr'               => $ctr,
            'ctr_delta'         => $ctrDelta,
            'position'          => $position,
            'position_delta'    => $positionDelta,
            'queries'           => $queries,
            'pages'             => $pages,
            'isCollecting'      => $isCollecting,
            'collectingNotice'  => $isCollecting ? __('Đang thu thập dữ liệu (Google cần 24–48 giờ)', 'lacadev') : null,
            'isZeroData'        => ($isZeroData && $hasDataEver),
            'zeroDataNotice'    => ($isZeroData && $hasDataEver) ? __('Chưa có lượt truy cập trong khoảng này', 'lacadev') : null,
            'dataDate'          => (string) ($result['dataDate'] ?? date('d/m', strtotime($result['endDate'] ?? '-1 day'))),
            'lagNotice'         => sprintf(__('Dữ liệu đến ngày %s', 'lacadev'), (string) ($result['dataDate'] ?? date('d/m', strtotime($result['endDate'] ?? '-1 day')))),
        ];
    }

    /**
     * Get Google Analytics 4 dataset for given range.
     *
     * @param string $range '7d' | '28d' | '90d'
     * @param bool $forceRefresh
     * @return array<string, mixed>
     */
    public static function getGa4Data(string $range = '28d', bool $forceRefresh = false): array
    {
        $isConnected = GscOAuth::isConnected();
        $hasGaScope  = GscOAuth::hasAnalyticsScope();
        $googleOpts  = get_option('laca_google_services', []);
        $propertyId  = '';
        if (is_array($googleOpts) && array_key_exists('ga4_property_id', $googleOpts)) {
            $propertyId = (string) $googleOpts['ga4_property_id'];
        } elseif (class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $propertyId = (string) \App\Features\LacaSeo\Core\Settings::get('general.ga4_property_id', '');
        }

        if ((!$isConnected || !$hasGaScope || empty($propertyId)) && !self::isMockMode()) {
            return [
                'connected' => false,
                'setup_url' => admin_url('admin.php?page=laca-google-analytics'),
            ];
        }

        if (self::isMockMode()) {
            return self::getMockGa4Data($range);
        }

        $result = GaClient::getBatchOverview($propertyId, $range, $forceRefresh);
        if (is_wp_error($result) || empty($result)) {
            $result = GaClient::runReport($propertyId, $range, $forceRefresh);
        }
        if (is_wp_error($result)) {
            $realtimeUsers = 0;
            $rt = GaClient::runRealtimeReport($propertyId, $forceRefresh);
            if (!is_wp_error($rt) && isset($rt['activeUsers'])) {
                $realtimeUsers = (int) $rt['activeUsers'];
            }
            return [
                'connected'     => true,
                'error'         => $result->get_error_message(),
                'status'        => (int) ($result->get_error_data()['status'] ?? 0),
                'setup_url'     => admin_url('admin.php?page=laca-google-analytics'),
                'realtimeUsers' => $realtimeUsers,
            ];
        }

        $realtimeUsers = 0;
        $realtimeResult = GaClient::runRealtimeReport($propertyId, $forceRefresh);
        if (!is_wp_error($realtimeResult) && isset($realtimeResult['activeUsers'])) {
            $realtimeUsers = (int) $realtimeResult['activeUsers'];
        }

        $summary = $result['summary'] ?? $result;
        $channels = $result['channels'] ?? [];
        $topPages = $result['topPages'] ?? [];

        // Format channels with share
        $totalChannelSessions = 0;
        foreach ($channels as $c) {
            $totalChannelSessions += (int) ($c['sessions'] ?? 0);
        }
        $formattedChannels = [];
        $palette = ['#18181b', '#52525b', '#a1a1aa', '#d4d4d8', '#e4e4e7'];
        $idx = 0;
        foreach ($channels as $c) {
            $sessions = (int) ($c['sessions'] ?? 0);
            $share = $totalChannelSessions > 0 ? round(($sessions / $totalChannelSessions) * 100) : 0;
            $formattedChannels[] = [
                'name'     => (string) ($c['sessionDefaultChannelGroup'] ?? $c['name'] ?? '—'),
                'sessions' => $sessions,
                'share'    => (int) $share,
                'color'    => $palette[$idx % count($palette)],
            ];
            $idx++;
        }

        $formattedTopPages = [];
        foreach ($topPages as $tp) {
            $views = (int) ($tp['screenPageViews'] ?? $tp['views'] ?? 0);
            $engagementSec = (float) ($tp['userEngagementDuration'] ?? 0);
            $pageUsers = (int) ($tp['activeUsers'] ?? 0);

            if (isset($tp['duration']) && $tp['duration'] !== '' && $tp['duration'] !== '1m 30s') {
                $duration = $tp['duration'];
            } elseif ($pageUsers > 0 && $engagementSec > 0) {
                $avgPageSec = (int) round($engagementSec / $pageUsers);
                $duration = self::formatDuration($avgPageSec);
            } else {
                $duration = '—';
            }

            $formattedTopPages[] = [
                'title'                  => (string) ($tp['pageTitle'] ?? $tp['title'] ?? 'Trang'),
                'path'                   => (string) ($tp['pagePath'] ?? $tp['path'] ?? '/'),
                'views'                  => $views,
                'screenPageViews'        => $views,
                'userEngagementDuration' => $engagementSec,
                'activeUsers'            => $pageUsers,
                'duration'               => $duration,
                'highlight'              => !empty($tp['highlight']),
            ];
        }

        $users = (int) ($summary['activeUsers'] ?? $summary['users'] ?? 0);
        $prevUsers = isset($summary['prevActiveUsers']) ? (int) $summary['prevActiveUsers'] : (isset($summary['prevUsers']) ? (int) $summary['prevUsers'] : null);
        $usersDelta = self::calcPercentDelta($users, $prevUsers);

        $sessions = (int) ($summary['sessions'] ?? 0);
        $prevSessions = isset($summary['prevSessions']) ? (int) $summary['prevSessions'] : null;
        $sessionsDelta = self::calcPercentDelta($sessions, $prevSessions);

        $avgSec = (int) round((float) ($summary['averageSessionDuration'] ?? $summary['avgSessionDuration'] ?? 0));
        $prevAvgSec = isset($summary['prevAverageSessionDuration']) ? (int) round((float) $summary['prevAverageSessionDuration']) : (isset($summary['prevAvgSessionDuration']) ? (int) round((float) $summary['prevAvgSessionDuration']) : null);
        $avgEngagementDelta = self::calcDurationDelta($avgSec, $prevAvgSec);

        $currKeyEvents = (int) ($summary['keyEvents'] ?? 0);
        $prevKeyEvents = isset($summary['prevKeyEvents']) ? (int) $summary['prevKeyEvents'] : null;

        // Conversion rate: null if no key events configured/recorded in both periods
        $conversionRate = null;
        $conversionRateDelta = '—';
        if ($currKeyEvents > 0 || ($prevKeyEvents !== null && $prevKeyEvents > 0)) {
            $convRate = $sessions > 0 ? round(($currKeyEvents / $sessions) * 100, 1) : 0.0;
            $conversionRate = $convRate . '%';
            $prevConvRate = ($prevSessions !== null && $prevSessions > 0) ? round(($prevKeyEvents / $prevSessions) * 100, 1) : null;
            $conversionRateDelta = self::calcPointDelta($convRate, $prevConvRate);
        }

        // Business KPIs from configured ga4_goal_events
        $businessKpis = [];
        $rawGoals = '';
        if (is_array($googleOpts) && array_key_exists('ga4_goal_events', $googleOpts)) {
            $rawGoals = (string) $googleOpts['ga4_goal_events'];
        } elseif (class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $rawGoals = (string) \App\Features\LacaSeo\Core\Settings::get('general.ga4_goal_events', '');
        }
        if ($rawGoals !== '') {
            $goalMap = [];
            foreach (explode("\n", $rawGoals) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = explode('|', $line, 2);
                $eName = trim($parts[0]);
                $eLabel = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : $eName;
                if ($eName !== '') {
                    $goalMap[$eName] = $eLabel;
                }
            }

            if (!empty($goalMap)) {
                $days = match ($range) {
                    '7d', '7'   => 7,
                    '90d', '90' => 90,
                    default     => 28,
                };
                $goalResult = GaClient::runGoalEventsReport($propertyId, $days, $goalMap, $forceRefresh);
                if (is_array($goalResult)) {
                    $businessKpis = $goalResult;
                }
            }
        }

        $totalUsers    = (int) ($summary['totalUsers'] ?? 0);
        $totalViews    = (int) ($summary['totalPageViews'] ?? 0);
        $totalSessions = (int) ($summary['totalSessions'] ?? 0);
        $isZeroData    = ($totalUsers === 0 && $totalViews === 0 && $totalSessions === 0 && empty($formattedTopPages));
        $hasDataEver   = (bool) get_option('laca_google_data_available_ga', 0);
        $isCollecting  = ($isZeroData && !$hasDataEver);

        return [
            'connected'            => true,
            'isMock'               => false,
            'propertyId'           => $propertyId,
            'users'                => $users,
            'users_delta'          => $usersDelta,
            'sessions'             => $sessions,
            'sessions_delta'       => $sessionsDelta,
            'avgEngagement'        => $avgSec > 0 ? self::formatDuration($avgSec) : '—',
            'avgEngagement_delta'  => $avgEngagementDelta,
            'conversionRate'       => $conversionRate,
            'conversionRate_delta' => $conversionRateDelta,
            'channels'             => $formattedChannels,
            'businessKpis'         => $businessKpis,
            'topPages'             => array_slice($formattedTopPages, 0, 5),
            'realtimeUsers'        => $realtimeUsers,
            'isCollecting'         => $isCollecting,
            'collectingNotice'     => $isCollecting ? __('Đang thu thập dữ liệu (Google cần 24–48 giờ)', 'lacadev') : null,
            'isZeroData'           => ($isZeroData && $hasDataEver),
            'zeroDataNotice'       => ($isZeroData && $hasDataEver) ? __('Chưa có lượt truy cập trong khoảng này', 'lacadev') : null,
        ];
    }

    /**
     * Calculate percentage difference between current and previous period.
     */
    public static function calcPercentDelta(int|float $current, int|float|null $previous): string
    {
        if ($previous === null || $previous <= 0) {
            return '—';
        }

        $diff = $current - $previous;
        $pct = round(($diff / $previous) * 100, 1);
        $sign = $pct > 0 ? '+' : '';

        return $sign . $pct . '%';
    }

    /**
     * Calculate percentage point difference (e.g. CTR, conversion rate).
     */
    public static function calcPointDelta(float $current, ?float $previous): string
    {
        if ($previous === null || $previous <= 0) {
            return '—';
        }

        $diff = round($current - $previous, 1);
        if ($diff == 0.0) {
            return '0%';
        }
        $sign = $diff > 0 ? '+' : '';

        return $sign . $diff . '%';
    }

    /**
     * Calculate position delta where a lower position number represents an improvement.
     */
    public static function calcPositionDelta(float $current, ?float $previous): string
    {
        if ($previous === null || $previous <= 0 || $current <= 0) {
            return '—';
        }

        $diff = round($previous - $current, 1);
        if ($diff == 0.0) {
            return '0';
        }

        $sign = $diff > 0 ? '+' : '';

        return $sign . number_format($diff, 1);
    }

    /**
     * Calculate duration delta formatted in seconds.
     */
    public static function calcDurationDelta(int $currentSec, ?int $previousSec): string
    {
        if ($previousSec === null || $previousSec <= 0) {
            return '—';
        }

        $diff = $currentSec - $previousSec;
        if ($diff === 0) {
            return '0s';
        }

        $sign = $diff > 0 ? '+' : '';

        return $sign . $diff . 's';
    }

    /**
     * Get combined dashboard report.
     *
     * @param string $range
     * @param bool $forceRefresh
     * @return array<string, mixed>
     */
    public static function getDashboardReport(string $range = '28d', bool $forceRefresh = false): array
    {
        return [
            'range' => $range,
            'gsc'   => self::getGscData($range, $forceRefresh),
            'ga4'   => self::getGa4Data($range, $forceRefresh),
        ];
    }

    /**
     * Format seconds into human readable duration, e.g. 1m 45s.
     */
    public static function formatDuration(int|float $seconds): string
    {
        $seconds = (int) round($seconds);
        if ($seconds <= 0) {
            return '0s';
        }

        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes <= 0) {
            return sprintf('%ds', $remainingSeconds);
        }

        if ($remainingSeconds === 0) {
            return sprintf('%dm 0s', $minutes);
        }

        return sprintf('%dm %02ds', $minutes, $remainingSeconds);
    }

    /**
     * Mock GSC data matching user specification for LIX Roastery.
     *
     * @param string $range
     * @return array<string, mixed>
     */
    private static function getMockGscData(string $range): array
    {
        $multipliers = match ($range) {
            '7d'    => 0.25,
            '90d'   => 3.2,
            default => 1.0,
        };

        $clicks = (int) round(1420 * $multipliers);
        $impressions = (int) round(28500 * $multipliers);

        return [
            'connected'         => true,
            'isMock'            => true,
            'siteUrl'           => 'sc-domain:lixroastery.com',
            'clicks'            => $clicks,
            'clicks_delta'      => '+12.4%',
            'impressions'       => $impressions,
            'impressions_delta' => '+18.2%',
            'ctr'               => 4.98,
            'ctr_delta'         => '+0.5%',
            'position'          => 11.2,
            'position_delta'    => '+1.8',
            'queries'           => [
                ['query' => 'ca phe dac san da nang', 'clicks' => (int) round(312 * $multipliers), 'impressions' => (int) round(4500 * $multipliers), 'ctr' => 6.9, 'position' => 3.1],
                ['query' => 'lix roastery', 'clicks' => (int) round(285 * $multipliers), 'impressions' => (int) round(1200 * $multipliers), 'ctr' => 23.7, 'position' => 1.0],
                ['query' => 'mua ca phe hat arabica', 'clicks' => (int) round(145 * $multipliers), 'impressions' => (int) round(3200 * $multipliers), 'ctr' => 4.5, 'position' => 5.4],
                ['query' => 'gia ca phe rang xay nguyen chat', 'clicks' => (int) round(98 * $multipliers), 'impressions' => (int) round(2100 * $multipliers), 'ctr' => 4.6, 'position' => 7.8],
                ['query' => 'workshop pha che ca phe', 'clicks' => (int) round(76 * $multipliers), 'impressions' => (int) round(1800 * $multipliers), 'ctr' => 4.2, 'position' => 4.2],
            ],
            'pages'             => [
                ['url' => '/san-pham/ca-phe-lix-specialty/', 'clicks' => (int) round(480 * $multipliers), 'impressions' => (int) round(7200 * $multipliers), 'ctr' => 6.67],
                ['url' => '/journal/nghe-thuat-rang-ca-phe/', 'clicks' => (int) round(310 * $multipliers), 'impressions' => (int) round(6100 * $multipliers), 'ctr' => 5.08],
                ['url' => '/ve-chung-toi/', 'clicks' => (int) round(195 * $multipliers), 'impressions' => (int) round(2400 * $multipliers), 'ctr' => 8.13],
                ['url' => '/workshop/', 'clicks' => (int) round(120 * $multipliers), 'impressions' => (int) round(1900 * $multipliers), 'ctr' => 6.32],
            ],
            'dataDate'          => date('d/m', strtotime('-1 day')),
            'lagNotice'         => sprintf(__('Dữ liệu đến ngày %s', 'lacadev'), date('d/m', strtotime('-1 day'))),
        ];
    }

    /**
     * Mock GA4 data matching user specification for LIX Roastery.
     *
     * @param string $range
     * @return array<string, mixed>
     */
    private static function getMockGa4Data(string $range): array
    {
        $multipliers = match ($range) {
            '7d'    => 0.25,
            '90d'   => 3.2,
            default => 1.0,
        };

        $users = (int) round(3840 * $multipliers);
        $sessions = (int) round(5260 * $multipliers);

        return [
            'connected'            => true,
            'isMock'               => true,
            'propertyId'           => '438291045',
            'realtimeUsers'        => 3,
            'users'                => $users,
            'users_delta'          => '+14.1%',
            'sessions'             => $sessions,
            'sessions_delta'       => '+9.8%',
            'avgEngagement'        => '1m 45s',
            'avgEngagement_delta'  => '+15s',
            'conversionRate'       => '4.7%',
            'conversionRate_delta' => '+0.8%',
            'keyEventsTotal'       => (int) round(248 * $multipliers),
            'channels'             => [
                ['name' => 'Organic Search', 'sessions' => (int) round(2840 * $multipliers), 'share' => 54, 'color' => '#18181b'],
                ['name' => 'Direct', 'sessions' => (int) round(1450 * $multipliers), 'share' => 27, 'color' => '#52525b'],
                ['name' => 'Social', 'sessions' => (int) round(620 * $multipliers), 'share' => 12, 'color' => '#a1a1aa'],
                ['name' => 'Referral', 'sessions' => (int) round(350 * $multipliers), 'share' => 7, 'color' => '#d4d4d8'],
            ],
            'businessKpis'         => [
                [
                    'title' => __('Bấm Mua / Thêm giỏ (Cà phê)', 'lacadev'),
                    'value' => (int) round(186 * $multipliers),
                    'delta' => '+18.5% so với kỳ trước',
                    'badge' => __('Hạt cà phê', 'lacadev'),
                ],
                [
                    'title' => __('Gửi Form / Đặt lịch workshop', 'lacadev'),
                    'value' => (int) round(62 * $multipliers),
                    'delta' => '6.5% tỷ lệ trên trang liên hệ',
                    'badge' => __('Workshop', 'lacadev'),
                ],
            ],
            'topPages'             => [
                ['title' => 'Trang chủ | LIX Roastery', 'path' => '/', 'views' => (int) round(4820 * $multipliers), 'duration' => '1m 15s'],
                ['title' => 'Cửa hàng Cà phê Đặc sản', 'path' => '/shop/', 'views' => (int) round(3450 * $multipliers), 'duration' => '2m 05s'],
                ['title' => 'Tạp chí: Nghệ thuật rang cà phê', 'path' => '/journal/', 'views' => (int) round(2180 * $multipliers), 'duration' => '2m 40s', 'highlight' => true],
                ['title' => 'Liên hệ & Đặt lịch Workshop', 'path' => '/contact/', 'views' => (int) round(950 * $multipliers), 'duration' => '1m 55s'],
            ],
        ];
    }
}
