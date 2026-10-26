<?php
/**
 * Automated test suite for Google Services (Search Console + Analytics).
 *
 * Chạy: php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock" doc/admin-ui/tools/test-google-services.php
 *
 * @package LacaDevClient
 */

define('WP_USE_THEMES', false);
$wp_load = dirname(__DIR__, 6) . '/wp-load.php';
if (file_exists($wp_load)) {
    require_once $wp_load;
} else {
    require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
}

use App\Features\LacaSeo\Modules\SearchConsole\GscClient;
use App\Features\LacaSeo\Modules\SearchConsole\GscOAuth;
use App\Features\LacaSeo\Modules\SearchConsole\GscCrypto;
use App\Features\LacaSeo\Modules\GoogleAnalytics\GaClient;
use App\Features\LacaSeo\Frontend\Tracking;
use App\Features\LacaSeo\Core\Settings;
use App\Settings\LacaAdmin\GoogleServicesPage;
use App\Features\LacaSeo\Admin\GoogleDashboardService;

$pass = 0;
$fail = 0;

function runTest(string $name, callable $fn, &$pass, &$fail): void {
    try {
        $result = $fn();
        if ($result === true) {
            $pass++;
            echo "PASS {$name}\n";
        } else {
            $fail++;
            $msg = is_string($result) ? $result : 'assertion returned false';
            echo "FAIL {$name}: {$msg}\n";
        }
    } catch (\Throwable $e) {
        $fail++;
        echo "FAIL {$name}: Exception {$e->getMessage()}\n";
    }
}

// -------------------------------------------------------------
// Test 1: Chọn GSC property theo thứ tự ưu tiên
// -------------------------------------------------------------
runTest('GSC Property Selection: sc-domain priority over url', function () {
    $sites = ['https://lixroastery.com/', 'sc-domain:lixroastery.com'];
    $selected = GscClient::selectBestSiteUrl($sites, 'https://lixroastery.com');
    if ($selected !== 'sc-domain:lixroastery.com') {
        return "Expected 'sc-domain:lixroastery.com', got '{$selected}'";
    }

    $sites2 = ['https://www.lixroastery.com/'];
    $selected2 = GscClient::selectBestSiteUrl($sites2, 'https://lixroastery.com');
    if ($selected2 !== 'https://www.lixroastery.com/') {
        return "Expected 'https://www.lixroastery.com/', got '{$selected2}'";
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 2: HTTP 403 trả về WP_Error và KHÔNG lưu transient
// -------------------------------------------------------------
runTest('GSC Error Handling: HTTP 403 returns WP_Error and does NOT cache transient', function () {
    $testSite = 'https://test-403-error-domain.com/';
    $transientKey = 'laca_gsc_perf_' . md5($testSite . '_28d');
    delete_transient($transientKey);

    // Filter HTTP request to return 403
    $mockFilter = function ($preempt, $parsed_args, $url) {
        if (str_contains($url, 'searchanalytics/query')) {
            return [
                'response' => ['code' => 403, 'message' => 'Forbidden'],
                'body'     => json_encode([
                    'error' => [
                        'code'    => 403,
                        'message' => 'User does not have sufficient permission for site.',
                        'status'  => 'PERMISSION_DENIED',
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };

    add_filter('pre_http_request', $mockFilter, 10, 3);

    $oldCreds = GscOAuth::getCredentials();
    GscOAuth::saveCredentials([
        'access_token'  => 'mock-token',
        'refresh_token' => 'mock-refresh',
        'created_at'    => time(),
        'expires_in'    => 3600,
        'client_id'     => 'mock-client',
        'client_secret' => 'mock-secret',
        'scopes'        => [GscOAuth::SCOPE_GSC],
    ]);

    try {
        $result = GscClient::getPerformanceSummary($testSite, 28, false);
        $cached = get_transient($transientKey);

        remove_filter('pre_http_request', $mockFilter, 10);
        if ($oldCreds) {
            GscOAuth::saveCredentials($oldCreds);
        } else {
            GscOAuth::disconnect();
        }

        if (!is_wp_error($result)) {
            return 'Expected WP_Error on HTTP 403, got: ' . print_r($result, true);
        }

        if ($cached !== false) {
            return 'Transient was erroneously set on 403 error!';
        }

        return true;
    } catch (\Throwable $e) {
        remove_filter('pre_http_request', $mockFilter, 10);
        if ($oldCreds) {
            GscOAuth::saveCredentials($oldCreds);
        } else {
            GscOAuth::disconnect();
        }
        throw $e;
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 3: Parse response GSC mẫu hợp lệ
// -------------------------------------------------------------
runTest('GSC Response Parsing: Correct clicks, impressions, CTR, position, and rows', function () {
    $mockResponse = [
        'rows' => [
            [
                'keys'        => ['ca phe dac san'],
                'clicks'      => 45,
                'impressions' => 500,
                'ctr'         => 0.09,
                'position'    => 4.2,
            ],
            [
                'keys'        => ['rang ca phe'],
                'clicks'      => 15,
                'impressions' => 250,
                'ctr'         => 0.06,
                'position'    => 6.8,
            ],
        ],
    ];

    $parsed = GscClient::parsePerformanceResponse($mockResponse);
    if (($parsed['totalClicks'] ?? 0) !== 60) {
        return "Expected totalClicks = 60, got " . ($parsed['totalClicks'] ?? 'null');
    }
    if (($parsed['totalImpressions'] ?? 0) !== 750) {
        return "Expected totalImpressions = 750, got " . ($parsed['totalImpressions'] ?? 'null');
    }
    if (($parsed['avgCtr'] ?? 0) !== 8.0) { // (60 / 750) * 100 = 8.0%
        return "Expected avgCtr = 8.0, got " . ($parsed['avgCtr'] ?? 'null');
    }
    if (count($parsed['rows'] ?? []) !== 2) {
        return "Expected 2 rows, got " . count($parsed['rows'] ?? []);
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 4: Parse response GA4 mẫu hợp lệ
// -------------------------------------------------------------
runTest('GA4 Response Parsing: Correct users, sessions, pageviews, and top pages', function () {
    $mockGaResponse = [
        'dimensionHeaders' => [
            ['name' => 'pagePath'],
            ['name' => 'pageTitle'],
        ],
        'metricHeaders' => [
            ['name' => 'activeUsers'],
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
        ],
        'rows' => [
            [
                'dimensionValues' => [
                    ['value' => '/'],
                    ['value' => 'Trang chủ | LIX'],
                ],
                'metricValues' => [
                    ['value' => '120'],
                    ['value' => '180'],
                    ['value' => '450'],
                ],
            ],
            [
                'dimensionValues' => [
                    ['value' => '/shop/'],
                    ['value' => 'Cửa hàng | LIX'],
                ],
                'metricValues' => [
                    ['value' => '80'],
                    ['value' => '95'],
                    ['value' => '210'],
                ],
            ],
        ],
        'totals' => [
            [
                'metricValues' => [
                    ['value' => '200'],
                    ['value' => '275'],
                    ['value' => '660'],
                ],
            ],
        ],
    ];

    $parsed = GaClient::parseReportResponse($mockGaResponse);
    if (($parsed['users'] ?? 0) !== 200) {
        return "Expected users = 200, got " . ($parsed['users'] ?? 'null');
    }
    if (($parsed['sessions'] ?? 0) !== 275) {
        return "Expected sessions = 275, got " . ($parsed['sessions'] ?? 'null');
    }
    if (($parsed['pageviews'] ?? 0) !== 660) {
        return "Expected pageviews = 660, got " . ($parsed['pageviews'] ?? 'null');
    }
    if (count($parsed['rows'] ?? []) !== 2) {
        return "Expected 2 rows, got " . count($parsed['rows'] ?? []);
    }
    if (($parsed['rows'][0]['pagePath'] ?? '') !== '/') {
        return "Expected first row pagePath = '/', got " . ($parsed['rows'][0]['pagePath'] ?? 'null');
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 5: Tìm property GA theo measurementId từ mock dataStreams
// -------------------------------------------------------------
runTest('GA4 Property Auto-Discovery: Find property matching measurementId', function () {
    $mockFilter = function ($preempt, $parsed_args, $url) {
        // Mock accountSummaries.list
        if (str_contains($url, 'accountSummaries')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'accountSummaries' => [
                        [
                            'name'             => 'accountSummaries/1001',
                            'displayName'      => 'LIX Account',
                            'propertySummaries' => [
                                [
                                    'property'    => 'properties/111111',
                                    'displayName' => 'Site Alpha',
                                ],
                                [
                                    'property'    => 'properties/222222',
                                    'displayName' => 'Lix Roastery Main',
                                ],
                            ],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        // Mock dataStreams for property 111111
        if (str_contains($url, 'properties/111111/dataStreams')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'dataStreams' => [
                        [
                            'name'            => 'properties/111111/dataStreams/901',
                            'type'            => 'WEB_DATA_STREAM',
                            'webStreamData'   => ['measurementId' => 'G-OTHER999'],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        // Mock dataStreams for property 222222
        if (str_contains($url, 'properties/222222/dataStreams')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'dataStreams' => [
                        [
                            'name'            => 'properties/222222/dataStreams/902',
                            'type'            => 'WEB_DATA_STREAM',
                            'webStreamData'   => ['measurementId' => 'G-TARGET123'],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        return $preempt;
    };

    add_filter('pre_http_request', $mockFilter, 10, 3);

    $oldCreds = GscOAuth::getCredentials();
    GscOAuth::saveCredentials([
        'access_token'  => 'mock-token',
        'refresh_token' => 'mock-refresh',
        'created_at'    => time(),
        'expires_in'    => 3600,
        'client_id'     => 'mock-client',
        'client_secret' => 'mock-secret',
        'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
    ]);

    delete_transient('laca_ga_properties');
    delete_transient('laca_ga_streams_111111');
    delete_transient('laca_ga_streams_222222');

    try {
        $found = GaClient::findPropertyByMeasurementId('G-TARGET123', true);

        remove_filter('pre_http_request', $mockFilter, 10);
        if ($oldCreds) {
            GscOAuth::saveCredentials($oldCreds);
        } else {
            GscOAuth::disconnect();
        }

        if (!$found) {
            return 'Expected to find property, got null';
        }
        if (($found['propertyId'] ?? '') !== '222222') {
            return "Expected propertyId = '222222', got " . ($found['propertyId'] ?? 'null');
        }

        return true;
    } catch (\Throwable $e) {
        remove_filter('pre_http_request', $mockFilter, 10);
        update_option('laca_seo_gsc_credentials', $oldCreds, false);
        throw $e;
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 6: Chống đếm GA trùng khi header script chứa mã
// -------------------------------------------------------------
runTest('Anti-Double Counting: Tracking omits script when ID is in header scripts', function () {
    $duplicateId = 'G-XSYBCQ8DZC';
    $mockHeaderScript = "<!-- Global site tag -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id={$duplicateId}\"></script>";

    // Mock header script via Carbon option or direct option
    update_option('_crb_header_script', $mockHeaderScript);

    // Set duplicate ID in Settings
    $origSettings = Settings::getGroup('general');
    $testSettings = $origSettings;
    $testSettings['ga4_measurement_id'] = $duplicateId;
    $testSettings['ga_exclude_loggedin'] = 'off'; // ensure not excluded by user check
    Settings::updateGroup('general', $testSettings);

    try {
        $tracking = new Tracking();
        ob_start();
        $tracking->outputHeadTracking();
        $output = ob_get_clean();

        // Restore
        delete_option('_crb_header_script');
        Settings::updateGroup('general', $origSettings);

        $scriptTagCount = substr_count($output, 'googletagmanager.com/gtag/js');
        if ($scriptTagCount !== 0) {
            return "Expected 0 gtag/js script tags, found {$scriptTagCount}. Output:\n{$output}";
        }

        if (!str_contains($output, 'omitted: already embedded in Laca Theme Scripts')) {
            return "Expected omission note comment in output, got:\n{$output}";
        }

        return true;
    } catch (\Throwable $e) {
        delete_option('_crb_header_script');
        Settings::updateGroup('general', $origSettings);
        throw $e;
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 7: OAuth Redirect URI phải GIỐNG HỆT chuỗi gốc
// -------------------------------------------------------------
runTest('OAuth Redirect URI: Exactly matches canonical URI', function () {
    $expected = admin_url('admin.php?page=laca-seo&view=search-console&laca_gsc_oauth=1');
    $actual = GscOAuth::getRedirectUri();

    if ($actual !== $expected) {
        return "Expected '{$expected}', got '{$actual}'";
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 8: handleActions() connect_init tạo URL OAuth với 2 scopes và redirect_uri cũ
// -------------------------------------------------------------
runTest('GoogleServicesPage::handleActions: Initiates OAuth with 2 scopes and canonical redirect URI', function () {
    $oldUser = get_current_user_id();
    $adminUsers = get_users(['role' => 'administrator', 'number' => 1]);
    $adminId = !empty($adminUsers) ? $adminUsers[0]->ID : 1;
    wp_set_current_user($adminId);

    $testClientId = 'test-client-123.apps.googleusercontent.com';
    $testSecret   = 'test-secret-abc';

    $_POST = [
        'laca_google_connect_init' => '1',
        '_wpnonce'                 => wp_create_nonce('laca_google_connect_action'),
        'client_id'                => $testClientId,
        'client_secret'            => $testSecret,
        'return_to'                => GoogleServicesPage::PAGE_SEARCH_CONSOLE,
    ];
    $_REQUEST = array_merge($_REQUEST, $_POST);

    $redirectFilter = function ($url) {
        throw new \RuntimeException('REDIRECT_URL:' . $url);
    };
    add_filter('wp_redirect', $redirectFilter);

    try {
        $page = new GoogleServicesPage();
        $page->handleActions();
        return 'Expected wp_redirect to be called, but execution continued';
    } catch (\RuntimeException $e) {
        if (!str_starts_with($e->getMessage(), 'REDIRECT_URL:')) {
            throw $e;
        }

        $url = substr($e->getMessage(), strlen('REDIRECT_URL:'));

        if (!str_contains($url, 'https://accounts.google.com/o/oauth2/v2/auth')) {
            return "Expected Google OAuth auth endpoint, got: {$url}";
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $queryParams);

        if (($queryParams['client_id'] ?? '') !== $testClientId) {
            return "Expected client_id = '{$testClientId}', got '" . ($queryParams['client_id'] ?? '') . "'";
        }

        $expectedRedirectUri = GscOAuth::getRedirectUri();
        if (($queryParams['redirect_uri'] ?? '') !== $expectedRedirectUri) {
            return "Expected redirect_uri = '{$expectedRedirectUri}', got '" . ($queryParams['redirect_uri'] ?? '') . "'";
        }

        $scope = $queryParams['scope'] ?? '';
        if (!str_contains($scope, GscOAuth::SCOPE_GSC) || !str_contains($scope, GscOAuth::SCOPE_GA)) {
            return "Expected scope to contain both GSC and GA scopes, got: '{$scope}'";
        }

        return true;
    } finally {
        remove_filter('wp_redirect', $redirectFilter);
        wp_set_current_user($oldUser);
        $_POST = [];
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 9: Client secret trống thì dùng secret đã lưu trong option
// -------------------------------------------------------------
runTest('GoogleServicesPage::handleActions: Empty secret uses saved secret from credentials', function () {
    $oldUser = get_current_user_id();
    $adminUsers = get_users(['role' => 'administrator', 'number' => 1]);
    $adminId = !empty($adminUsers) ? $adminUsers[0]->ID : 1;
    wp_set_current_user($adminId);

    $savedSecret   = 'SAVED_SECRET_FROM_PRE_OPTION_xyz';
    $savedClientId = 'saved-client-id.apps.googleusercontent.com';

    $mockFilter = function ($preempt) use ($savedClientId, $savedSecret) {
        return GscCrypto::encrypt([
            'client_id'     => $savedClientId,
            'client_secret' => $savedSecret,
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockFilter);

    $_POST = [
        'laca_google_connect_init' => '1',
        '_wpnonce'                 => wp_create_nonce('laca_google_connect_action'),
        'client_id'                => $savedClientId,
        'client_secret'            => '', // Trống -> dùng secret đã lưu
        'return_to'                => GoogleServicesPage::PAGE_SEARCH_CONSOLE,
    ];
    $_REQUEST = array_merge($_REQUEST, $_POST);

    $redirectFilter = function ($url) {
        throw new \RuntimeException('REDIRECT_URL:' . $url);
    };
    add_filter('wp_redirect', $redirectFilter);

    try {
        $page = new GoogleServicesPage();
        $page->handleActions();
        return 'Expected wp_redirect, got none';
    } catch (\RuntimeException $e) {
        if (!str_starts_with($e->getMessage(), 'REDIRECT_URL:')) {
            throw $e;
        }

        $pending = get_option('laca_seo_gsc_pending', []);

        if (($pending['client_secret'] ?? '') !== $savedSecret) {
            return "Expected pending secret to match saved secret '{$savedSecret}', got: '" . ($pending['client_secret'] ?? '') . "'";
        }

        return true;
    } finally {
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockFilter);
        remove_filter('wp_redirect', $redirectFilter);
        wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);
        $_POST = [];
        delete_option('laca_seo_gsc_pending');
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 10: getStateReturnTo chống open redirect & chỉ cho phép 2 slug an toàn
// -------------------------------------------------------------
runTest('GscOAuth::getStateReturnTo: Sanitizes return_to against open redirect / evil URLs', function () {
    // 1. Evil URL
    $evilState = base64_encode(json_encode(['return_to' => 'https://evil.com/phishing']));
    $target1 = GscOAuth::getStateReturnTo($evilState);
    if ($target1 !== 'admin.php?page=laca-google-search-console') {
        return "Expected fallback 'admin.php?page=laca-google-search-console', got: '{$target1}'";
    }

    // 2. Allowed: laca-google-analytics
    $gaState = base64_encode(json_encode(['return_to' => 'laca-google-analytics']));
    $target2 = GscOAuth::getStateReturnTo($gaState);
    if ($target2 !== 'admin.php?page=laca-google-analytics') {
        return "Expected 'admin.php?page=laca-google-analytics', got: '{$target2}'";
    }

    // 3. Allowed with admin.php?page= prefix
    $gscState = base64_encode(json_encode(['return_to' => 'admin.php?page=laca-google-search-console']));
    $target3 = GscOAuth::getStateReturnTo($gscState);
    if ($target3 !== 'admin.php?page=laca-google-search-console') {
        return "Expected 'admin.php?page=laca-google-search-console', got: '{$target3}'";
    }

    // 4. Malformed/garbage state
    $badState = 'not-valid-base64-json!@#$';
    $target4 = GscOAuth::getStateReturnTo($badState);
    if ($target4 !== 'admin.php?page=laca-google-search-console') {
        return "Expected fallback for bad state, got: '{$target4}'";
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 11: HTML trang GSC KHÔNG in Client Secret đã giải mã
// -------------------------------------------------------------
runTest('GoogleServicesPage HTML: Client Secret is NEVER printed in rendered HTML', function () {
    $canarySecret = 'CANARY_NEVER_PRINT_GOCSPX_SECRET_888999';

    $mockFilter = function ($preempt) use ($canarySecret) {
        return GscCrypto::encrypt([
            'client_id'     => 'test-client-id.apps.googleusercontent.com',
            'client_secret' => $canarySecret,
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockFilter);

    try {
        $page = new GoogleServicesPage();
        ob_start();
        $page->renderSearchConsolePage();
        $html = ob_get_clean();

        if (str_contains($html, $canarySecret)) {
            return "Security violation: Decrypted client_secret was printed into HTML output!";
        }

        if (!str_contains($html, 'placeholder="Đã lưu — để trống để giữ nguyên"')) {
            return "Expected placeholder 'Đã lưu — để trống để giữ nguyên' when secret is present, got output:\n" . substr($html, 0, 500);
        }

        return true;
    } finally {
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockFilter);
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 12: GSC Clicks delta so sánh kỳ trước thật (API comparison)
// -------------------------------------------------------------
runTest('GSC Comparison Delta: clicks=10 vs prev=5 => +100%, prev=0 => —', function () {
    $delta1 = GoogleDashboardService::calcPercentDelta(10, 5);
    if ($delta1 !== '+100%') {
        return "Expected '+100%', got '{$delta1}'";
    }

    $delta2 = GoogleDashboardService::calcPercentDelta(10, 0);
    if ($delta2 !== '—') {
        return "Expected '—' when prev=0, got '{$delta2}'";
    }

    $delta3 = GoogleDashboardService::calcPercentDelta(10, null);
    if ($delta3 !== '—') {
        return "Expected '—' when prev=null, got '{$delta3}'";
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 13: GA4 2 dateRanges: activeUsers 30 vs 20 => +50%, sessions 0 vs 0 => —, keyEvents 0 => không có conversionRate số
// -------------------------------------------------------------
runTest('GA4 2 DateRanges Comparison: activeUsers +50%, sessions —, no conversionRate when keyEvents=0', function () {
    $mockReportData = [
        'totals' => [
            [
                'dimensionValues' => [['value' => 'date_range_0']],
                'metricValues'    => [
                    ['value' => '30'], // activeUsers
                    ['value' => '10'], // newUsers
                    ['value' => '0'],  // sessions
                    ['value' => '50'], // screenPageViews
                    ['value' => '120'], // averageSessionDuration
                    ['value' => '0.5'], // engagementRate
                    ['value' => '0'],  // keyEvents
                ],
            ],
            [
                'dimensionValues' => [['value' => 'date_range_1']],
                'metricValues'    => [
                    ['value' => '20'], // activeUsers
                    ['value' => '5'],  // newUsers
                    ['value' => '0'],  // sessions
                    ['value' => '40'], // screenPageViews
                    ['value' => '100'], // averageSessionDuration
                    ['value' => '0.4'], // engagementRate
                    ['value' => '0'],  // keyEvents
                ],
            ],
        ],
        'metricHeaders' => [
            ['name' => 'activeUsers'],
            ['name' => 'newUsers'],
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
            ['name' => 'averageSessionDuration'],
            ['name' => 'engagementRate'],
            ['name' => 'keyEvents'],
        ],
        'rows' => [],
    ];

    $parsed = GaClient::parseReportResponse($mockReportData);
    $usersDelta = GoogleDashboardService::calcPercentDelta($parsed['users'], $parsed['prevUsers']);
    if ($usersDelta !== '+50%') {
        return "Expected activeUsers delta '+50%', got '{$usersDelta}'";
    }

    $sessionsDelta = GoogleDashboardService::calcPercentDelta($parsed['sessions'], $parsed['prevSessions']);
    if ($sessionsDelta !== '—') {
        return "Expected sessions delta '—' when sessions=0, got '{$sessionsDelta}'";
    }

    // Test through GoogleDashboardService with mock HTTP response
    $savedOpt = get_option('laca_seo_general', []);
    $testOpt = $savedOpt;
    $testOpt['ga4_property_id'] = '123456789';
    update_option('laca_seo_general', $testOpt);
    Settings::clearCache();

    $oldCreds = GscOAuth::getCredentials();
    GscOAuth::saveCredentials([
        'access_token'  => 'mock-token',
        'refresh_token' => 'mock-refresh',
        'created_at'    => time(),
        'expires_in'    => 3600,
        'client_id'     => 'mock-client',
        'client_secret' => 'mock-secret',
        'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
    ]);

    $mockFilter = function ($preempt, $parsed_args, $url) use ($mockReportData) {
        if (str_contains($url, 'analyticsdata.googleapis.com') && str_contains($url, ':runReport')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode($mockReportData),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };
    add_filter('pre_http_request', $mockFilter, 10, 3);

    try {
        $gaData = GoogleDashboardService::getGa4Data('28d', true);

        if (($gaData['users_delta'] ?? '') !== '+50%') {
            return "Expected gaData users_delta '+50%', got '" . ($gaData['users_delta'] ?? '') . "'";
        }
        if (($gaData['sessions_delta'] ?? '') !== '—') {
            return "Expected gaData sessions_delta '—', got '" . ($gaData['sessions_delta'] ?? '') . "'";
        }
        if ($gaData['conversionRate'] !== null) {
            return "Expected conversionRate to be null when keyEvents=0, got: " . var_export($gaData['conversionRate'], true);
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        update_option('laca_seo_general', $savedOpt);
        Settings::clearCache();
        if ($oldCreds) {
            GscOAuth::saveCredentials($oldCreds);
        } else {
            GscOAuth::disconnect();
        }
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 14: ga4_goal_events: Không cấu hình => rỗng, cấu hình add_to_cart|Thêm giỏ => value 7, delta —
// -------------------------------------------------------------
runTest('GA4 Goal Events: Empty when unconfigured, parsed with real counts & delta — when prev=0', function () {
    $savedOpt = get_option('laca_seo_general', []);

    // 1. Without ga4_goal_events configured
    $testOpt = $savedOpt;
    $testOpt['ga4_property_id'] = '123456789';
    $testOpt['ga4_goal_events'] = '';
    update_option('laca_seo_general', $testOpt);
    Settings::clearCache();

    $oldCreds = GscOAuth::getCredentials();
    GscOAuth::saveCredentials([
        'access_token'  => 'mock-token',
        'refresh_token' => 'mock-refresh',
        'created_at'    => time(),
        'expires_in'    => 3600,
        'client_id'     => 'mock-client',
        'client_secret' => 'mock-secret',
        'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
    ]);

    $mockFilter = function ($preempt, $parsed_args, $url) {
        if (str_contains($url, 'analyticsdata.googleapis.com') && str_contains($url, ':runReport')) {
            $body = json_decode($parsed_args['body'] ?? '{}', true);
            $dimNames = array_column($body['dimensions'] ?? [], 'name');
            if (in_array('eventName', $dimNames, true)) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'dimensionHeaders' => [
                            ['name' => 'dateRange'],
                            ['name' => 'eventName'],
                        ],
                        'metricHeaders' => [
                            ['name' => 'eventCount'],
                        ],
                        'rows' => [
                            [
                                'dimensionValues' => [
                                    ['value' => 'date_range_0'],
                                    ['value' => 'add_to_cart'],
                                ],
                                'metricValues' => [
                                    ['value' => '7'],
                                ],
                            ],
                        ],
                    ]),
                    'headers'  => ['content-type' => 'application/json'],
                ];
            }

            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'totals' => [
                        [
                            'dimensionValues' => [['value' => 'date_range_0']],
                            'metricValues'    => [
                                ['value' => '50'], ['value' => '20'], ['value' => '60'],
                                ['value' => '120'], ['value' => '90'], ['value' => '0.6'], ['value' => '7'],
                            ],
                        ],
                        [
                            'dimensionValues' => [['value' => 'date_range_1']],
                            'metricValues'    => [
                                ['value' => '40'], ['value' => '15'], ['value' => '50'],
                                ['value' => '100'], ['value' => '80'], ['value' => '0.5'], ['value' => '0'],
                            ],
                        ],
                    ],
                    'metricHeaders' => [
                        ['name' => 'activeUsers'], ['name' => 'newUsers'], ['name' => 'sessions'],
                        ['name' => 'screenPageViews'], ['name' => 'averageSessionDuration'], ['name' => 'engagementRate'], ['name' => 'keyEvents'],
                    ],
                    'rows' => [],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };
    add_filter('pre_http_request', $mockFilter, 10, 3);

    try {
        // Case 1: unconfigured -> businessKpis is []
        $gaDataEmpty = GoogleDashboardService::getGa4Data('28d', true);
        if (!empty($gaDataEmpty['businessKpis'])) {
            return "Expected empty businessKpis when ga4_goal_events is empty, got: " . print_r($gaDataEmpty['businessKpis'], true);
        }

        // Case 2: configured with 'add_to_cart|Thêm giỏ'
        $testOpt['ga4_goal_events'] = "add_to_cart|Thêm giỏ";
        update_option('laca_seo_general', $testOpt);
        Settings::clearCache();

        $gaDataConfigured = GoogleDashboardService::getGa4Data('28d', true);
        $kpis = $gaDataConfigured['businessKpis'] ?? [];
        if (count($kpis) !== 1) {
            return "Expected 1 KPI item, got " . count($kpis);
        }
        if ($kpis[0]['title'] !== 'Thêm giỏ') {
            return "Expected KPI title 'Thêm giỏ', got '{$kpis[0]['title']}'";
        }
        if ($kpis[0]['value'] !== 7) {
            return "Expected KPI value 7, got " . var_export($kpis[0]['value'], true);
        }
        if ($kpis[0]['delta'] !== '—') {
            return "Expected KPI delta '—' when prev=0, got '{$kpis[0]['delta']}'";
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        update_option('laca_seo_general', $savedOpt);
        Settings::clearCache();
        if ($oldCreds) {
            GscOAuth::saveCredentials($oldCreds);
        } else {
            GscOAuth::disconnect();
        }
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 15: Render views/dashboard.php với response thật (không bật mock) KHÔNG chứa số bịa
// -------------------------------------------------------------
runTest('views/dashboard.php HTML: Never contains fabricated numbers (186, 12.4%, 14.1%, 4.7%, Dữ liệu mẫu)', function () {
    unset($_GET['laca_mock_google']);
    unset($_GET['laca_preview_google']);

    $savedOpt = get_option('laca_seo_general', []);
    $testOpt = $savedOpt;
    $testOpt['ga4_property_id'] = '123456789';
    $testOpt['gsc_site_url']    = 'https://lixroastery.com/';
    $testOpt['ga4_goal_events'] = '';
    update_option('laca_seo_general', $testOpt);
    Settings::clearCache();

    $oldCreds = GscOAuth::getCredentials();
    GscOAuth::saveCredentials([
        'access_token'  => 'mock-token',
        'refresh_token' => 'mock-refresh',
        'created_at'    => time(),
        'expires_in'    => 3600,
        'client_id'     => 'mock-client',
        'client_secret' => 'mock-secret',
        'scope'         => 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly',
    ]);

    $mockFilter = function ($preempt, $parsed_args, $url) {
        if (str_contains($url, 'searchanalytics/query')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'rows' => [
                        ['keys' => ['test query'], 'clicks' => 50, 'impressions' => 500, 'ctr' => 0.1, 'position' => 5.0],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        if (str_contains($url, 'analyticsdata.googleapis.com') && str_contains($url, ':runReport')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'totals' => [
                        [
                            'dimensionValues' => [['value' => 'date_range_0']],
                            'metricValues'    => [
                                ['value' => '50'], ['value' => '20'], ['value' => '60'],
                                ['value' => '120'], ['value' => '90'], ['value' => '0.6'], ['value' => '0'],
                            ],
                        ],
                    ],
                    'metricHeaders' => [
                        ['name' => 'activeUsers'], ['name' => 'newUsers'], ['name' => 'sessions'],
                        ['name' => 'screenPageViews'], ['name' => 'averageSessionDuration'], ['name' => 'engagementRate'], ['name' => 'keyEvents'],
                    ],
                    'rows' => [],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };
    add_filter('pre_http_request', $mockFilter, 10, 3);

    $oldUser = wp_get_current_user();
    $adminId = 1;
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    if (!empty($admins)) {
        $adminId = $admins[0]->ID;
    }
    wp_set_current_user($adminId);

    try {
        ob_start();
        $viewPath = dirname(__DIR__, 3) . '/app/src/Features/LacaSeo/Admin/views/dashboard.php';
        include $viewPath;
        $html = ob_get_clean();

        $forbidden = ['186', '12.4%', '14.1%', '4.7%', 'Dữ liệu mẫu'];
        foreach ($forbidden as $bad) {
            if (str_contains($html, $bad)) {
                return "Fabricated string '{$bad}' was found in rendered dashboard HTML!";
            }
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        update_option('laca_seo_general', $savedOpt);
        Settings::clearCache();
        if ($oldCreds) {
            GscOAuth::saveCredentials($oldCreds);
        } else {
            GscOAuth::disconnect();
        }
        wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 16: isMockMode() là false khi không có ?laca_mock_google=1 kể cả local chưa kết nối
// -------------------------------------------------------------
runTest('GoogleDashboardService::isMockMode: Strictly false without ?laca_mock_google=1 on local', function () {
    unset($_GET['laca_mock_google']);
    unset($_GET['laca_preview_google']);

    $oldUser = wp_get_current_user();
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    $adminId = !empty($admins) ? $admins[0]->ID : 1;
    wp_set_current_user($adminId);

    $oldCreds = GscOAuth::getCredentials();
    GscOAuth::disconnect();

    try {
        if (GoogleDashboardService::isMockMode() !== false) {
            return "Expected isMockMode() === false when disconnected on local without query parameter";
        }

        // With ?laca_mock_google=1 and manage_options on local
        $_GET['laca_mock_google'] = '1';
        if (GoogleDashboardService::isMockMode() !== true) {
            return "Expected isMockMode() === true when ?laca_mock_google=1 and user can manage_options on local";
        }

        // Without manage_options
        wp_set_current_user(0);
        if (GoogleDashboardService::isMockMode() !== false) {
            return "Expected isMockMode() === false when user cannot manage_options";
        }

        return true;
    } finally {
        unset($_GET['laca_mock_google']);
        wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);
        if ($oldCreds) {
            GscOAuth::saveCredentials($oldCreds);
        }
    }
}, $pass, $fail);

echo "SUMMARY pass={$pass} fail={$fail}\n";
exit($fail === 0 ? 0 : 1);
