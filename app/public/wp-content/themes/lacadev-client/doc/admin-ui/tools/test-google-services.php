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

use App\Features\GoogleServices\GscClient;
use App\Features\GoogleServices\GscOAuth;
use App\Features\GoogleServices\GscCrypto;
use App\Features\GoogleServices\GaClient;
use App\Features\GoogleServices\Tracking;
use App\Features\GoogleServices\GoogleDashboardService;
use App\Features\GoogleServices\GoogleServices;
use App\Features\GoogleServices\DateRange;
use App\Features\LacaSeo\Core\Settings;
use App\Settings\LacaAdmin\GoogleServicesPage;

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

    $mockOAuthFilter = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);

    try {
        $result = GscClient::getPerformanceSummary($testSite, 28, false);
        $cached = get_transient($transientKey);

        if (!is_wp_error($result)) {
            return 'Expected WP_Error on HTTP 403, got: ' . print_r($result, true);
        }

        if ($cached !== false) {
            return 'Transient was erroneously set on 403 error!';
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);
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

    $mockOAuthFilter = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);

    delete_transient('laca_ga_properties');
    delete_transient('laca_ga_streams_111111');
    delete_transient('laca_ga_streams_222222');

    try {
        $found = GaClient::findPropertyByMeasurementId('G-TARGET123', true);

        if (!$found) {
            return 'Expected to find property, got null';
        }
        if (($found['propertyId'] ?? '') !== '222222') {
            return "Expected propertyId = '222222', got " . ($found['propertyId'] ?? 'null');
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 6: Chống đếm GA trùng khi header script chứa mã
// -------------------------------------------------------------
runTest('Anti-Double Counting: Tracking omits script when ID is in header scripts', function () {
    $duplicateId = 'G-XSYBCQ8DZC';
    $mockHeaderScript = "<!-- Global site tag -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id={$duplicateId}\"></script>";

    $mockHeaderFilter = function () use ($mockHeaderScript) {
        return $mockHeaderScript;
    };
    $mockServicesFilter = function () use ($duplicateId) {
        return [
            'ga4_measurement_id'  => $duplicateId,
            'ga_exclude_loggedin' => 'off',
        ];
    };

    $mockGeneralEmpty6 = function () { return []; };
    add_filter('pre_option__crb_header_script', $mockHeaderFilter);
    add_filter('pre_option_laca_google_services', $mockServicesFilter);
    add_filter('pre_option_laca_seo_general', $mockGeneralEmpty6);

    try {
        $tracking = new Tracking();
        ob_start();
        $tracking->outputHeadTracking();
        $output = ob_get_clean();

        $scriptTagCount = substr_count($output, 'googletagmanager.com/gtag/js');
        if ($scriptTagCount !== 0) {
            return "Expected 0 gtag/js script tags, found {$scriptTagCount}. Output:\n{$output}";
        }

        if (!str_contains($output, 'omitted: already embedded in Laca Theme Scripts')) {
            return "Expected omission note comment in output, got:\n{$output}";
        }

        return true;
    } finally {
        remove_filter('pre_option__crb_header_script', $mockHeaderFilter);
        remove_filter('pre_option_laca_google_services', $mockServicesFilter);
        remove_filter('pre_option_laca_seo_general', $mockGeneralEmpty6);
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 7: OAuth Redirect URI phải GIỐNG HỆT chuỗi gốc
// -------------------------------------------------------------
runTest('OAuth Redirect URI: Exactly matches canonical and legacy URIs', function () {
    $expected = admin_url('admin.php?page=laca-google-search-console&laca_google_oauth=1');
    $actual = GscOAuth::getRedirectUri();

    if ($actual !== $expected) {
        return "Expected '{$expected}', got '{$actual}'";
    }

    $legacyExpected = admin_url('admin.php?page=laca-seo&view=search-console&laca_gsc_oauth=1');
    $legacyActual = GscOAuth::getLegacyRedirectUri();
    if ($legacyActual !== $legacyExpected) {
        return "Expected legacy '{$legacyExpected}', got '{$legacyActual}'";
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
        if (!str_contains($scope, GscOAuth::SCOPE_GSC) || !str_contains($scope, GscOAuth::SCOPE_GA) || !str_contains($scope, 'openid') || !str_contains($scope, 'email')) {
            return "Expected scope to contain GSC, GA, openid, and email scopes, got: '{$scope}'";
        }

        $prompt = $queryParams['prompt'] ?? '';
        if (!str_contains($prompt, 'consent') || !str_contains($prompt, 'select_account')) {
            return "Expected prompt to contain 'consent select_account', got: '{$prompt}'";
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
    $mockServicesFilter13 = function () {
        return [
            'ga4_property_id' => '123456789',
            'ga4_goal_events' => '',
        ];
    };
    $mockOAuthFilter13 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    $mockGeneralEmpty13 = function () { return []; };
    add_filter('pre_option_laca_google_services', $mockServicesFilter13);
    add_filter('pre_option_laca_seo_general', $mockGeneralEmpty13);
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter13);
    Settings::clearCache();

    $mockFilter = function ($preempt, $parsed_args, $url) use ($mockReportData) {
        if (str_contains($url, 'analyticsdata.googleapis.com')) {
            $reqData = json_decode($parsed_args['body'] ?? '{}', true);
            if (!empty($reqData['dateRanges']) && is_array($reqData['dateRanges'])) {
                foreach ($reqData['dateRanges'] as $dr) {
                    if (isset($dr['name']) && str_starts_with((string) $dr['name'], 'date_range_')) {
                        throw new \RuntimeException("DateRange name '{$dr['name']}' is not allowed. Names cannot begin with date_range_");
                    }
                }
            }
        }
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
        remove_filter('pre_option_laca_google_services', $mockServicesFilter13);
        remove_filter('pre_option_laca_seo_general', $mockGeneralEmpty13);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter13);
        Settings::clearCache();
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 14: ga4_goal_events: Không cấu hình => rỗng, cấu hình add_to_cart|Thêm giỏ => value 7, delta —
// -------------------------------------------------------------
runTest('GA4 Goal Events: Empty when unconfigured, parsed with real counts & delta — when prev=0', function () {
    $testGoogleOpts = [
        'ga4_property_id' => '123456789',
        'ga4_goal_events' => '',
    ];
    $mockServicesFilter14 = function () use (&$testGoogleOpts) {
        return $testGoogleOpts;
    };
    $mockOAuthFilter14 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    $mockGeneralEmpty14 = function () { return []; };
    add_filter('pre_option_laca_google_services', $mockServicesFilter14);
    add_filter('pre_option_laca_seo_general', $mockGeneralEmpty14);
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter14);
    Settings::clearCache();

    $mockFilter = function ($preempt, $parsed_args, $url) {
        if (str_contains($url, 'analyticsdata.googleapis.com')) {
            $reqData = json_decode($parsed_args['body'] ?? '{}', true);
            if (!empty($reqData['dateRanges']) && is_array($reqData['dateRanges'])) {
                foreach ($reqData['dateRanges'] as $dr) {
                    if (isset($dr['name']) && str_starts_with((string) $dr['name'], 'date_range_')) {
                        throw new \RuntimeException("DateRange name '{$dr['name']}' is not allowed. Names cannot begin with date_range_");
                    }
                }
            }
        }
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
        $testGoogleOpts['ga4_goal_events'] = "add_to_cart|Thêm giỏ";
        delete_transient('laca_ga4_report_28d');
        delete_transient('laca_ga4_prev_28d');
        delete_transient('laca_ga4_kpis_28d');

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
        remove_filter('pre_option_laca_google_services', $mockServicesFilter14);
        remove_filter('pre_option_laca_seo_general', $mockGeneralEmpty14);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter14);
        Settings::clearCache();
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 15: Render views/dashboard.php với response thật (không bật mock) KHÔNG chứa số bịa
// -------------------------------------------------------------
runTest('views/dashboard.php HTML: Never contains fabricated numbers (186, 12.4%, 14.1%, 4.7%, Dữ liệu mẫu)', function () {
    unset($_GET['laca_mock_google']);
    unset($_GET['laca_preview_google']);

    $mockServicesFilter15 = function () {
        return [
            'ga4_property_id' => '123456789',
            'gsc_site_url'    => 'https://lixroastery.com/',
            'ga4_goal_events' => '',
        ];
    };
    $mockOAuthFilter15 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    $mockGeneralEmpty15 = function () { return []; };
    add_filter('pre_option_laca_google_services', $mockServicesFilter15);
    add_filter('pre_option_laca_seo_general', $mockGeneralEmpty15);
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter15);
    Settings::clearCache();

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
        if (str_contains($url, 'analyticsdata.googleapis.com')) {
            $reqData = json_decode($parsed_args['body'] ?? '{}', true);
            if (!empty($reqData['dateRanges']) && is_array($reqData['dateRanges'])) {
                foreach ($reqData['dateRanges'] as $dr) {
                    if (isset($dr['name']) && str_starts_with((string) $dr['name'], 'date_range_')) {
                        throw new \RuntimeException("DateRange name '{$dr['name']}' is not allowed. Names cannot begin with date_range_");
                    }
                }
            }
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
        $range = '28d';
        $isForceRefresh = false;
        $viewPath = dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/partials/google-report.php';
        $report = GoogleDashboardService::getDashboardReport($range, $isForceRefresh);
        $gsc = $report['gsc'];
        $ga  = $report['ga4'];
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
        remove_filter('pre_option_laca_google_services', $mockServicesFilter15);
        remove_filter('pre_option_laca_seo_general', $mockGeneralEmpty15);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter15);
        Settings::clearCache();
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

// -------------------------------------------------------------
// Test 17: Render views/search-console.php khi searchAnalytics trả 404 và khi trả 403
// Không exception/fatal, có thông báo tiếng Việt + link chọn property; không "Tổng lượt click" với số 0
// -------------------------------------------------------------
runTest('views/search-console.php: Gracefully renders on 404 and 403 without fatal', function () {
    $oldUser = wp_get_current_user();
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    $adminId = !empty($admins) ? $admins[0]->ID : 1;
    wp_set_current_user($adminId);

    $mockOAuthFilter17 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GSC],
        ]);
    };
    $mockServicesFilter17 = function () {
        return [
            'gsc_site_url' => 'https://lixroastery.com/',
        ];
    };
    $mockGeneralEmpty17 = function () { return []; };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter17);
    add_filter('pre_option_laca_google_services', $mockServicesFilter17);
    add_filter('pre_option_laca_seo_general', $mockGeneralEmpty17);

    // Case A: 404
    $mockFilter404 = function ($preempt, $parsed_args, $url) {
        if (stripos($url, 'searchanalytics/query') !== false || stripos($url, 'sitemaps') !== false) {
            return [
                'response' => ['code' => 404, 'message' => 'Not Found'],
                'body'     => json_encode([
                    'error' => [
                        'code'    => 404,
                        'message' => 'Requested entity was not found.',
                        'status'  => 'NOT_FOUND',
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };

    add_filter('pre_http_request', $mockFilter404, 10, 3);
    try {
        ob_start();
        include dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/search-console.php';
        $html404 = ob_get_clean();
    } catch (\Throwable $e) {
        ob_end_clean();
        remove_filter('pre_http_request', $mockFilter404, 10);
        return "404 caused fatal/exception: " . $e->getMessage();
    }
    remove_filter('pre_http_request', $mockFilter404, 10);

    if (!str_contains($html404, '404') && !str_contains($html404, 'Không tìm thấy property')) {
        return "404 HTML missing Vietnamese 404 error explanation";
    }
    if (!str_contains($html404, 'Chọn property khác') && !str_contains($html404, 'page=laca-google-search-console')) {
        return "404 HTML missing link to choose another property";
    }
    if (str_contains($html404, 'Tổng lượt click') && str_contains($html404, '>0<')) {
        return "404 HTML unexpectedly shows 'Tổng lượt click' with 0";
    }

    // Case B: 403
    $mockFilter403 = function ($preempt, $parsed_args, $url) {
        if (stripos($url, 'searchanalytics/query') !== false || stripos($url, 'sitemaps') !== false) {
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

    add_filter('pre_http_request', $mockFilter403, 10, 3);
    try {
        ob_start();
        include dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/search-console.php';
        $html403 = ob_get_clean();
    } catch (\Throwable $e) {
        ob_end_clean();
        remove_filter('pre_http_request', $mockFilter403, 10);
        return "403 caused fatal/exception: " . $e->getMessage();
    }
    remove_filter('pre_http_request', $mockFilter403, 10);

    if (!str_contains($html403, '403') && !str_contains($html403, 'quyền truy cập')) {
        return "403 HTML missing 403 error explanation";
    }
    if (!str_contains($html403, 'Chọn property khác') && !str_contains($html403, 'page=laca-google-search-console')) {
        return "403 HTML missing link to choose another property";
    }
    if (str_contains($html403, 'Tổng lượt click') && str_contains($html403, '>0<')) {
        return "403 HTML unexpectedly shows 'Tổng lượt click' with 0";
    }

    // Teardown
    remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter17);
    remove_filter('pre_option_laca_google_services', $mockServicesFilter17);
    remove_filter('pre_option_laca_seo_general', $mockGeneralEmpty17);
    wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 18: sites.list trả [sc-domain:lixroastery.com (siteOwner), https://lixroastery.com/ (siteUnverifiedUser)]
// Select chọn sẵn sc-domain:lixroastery.com, option chưa xác minh disabled
// -------------------------------------------------------------
runTest('GoogleServicesPage: sites.list auto-selects sc-domain and disables unverified option', function () {
    $oldUser = wp_get_current_user();
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    $adminId = !empty($admins) ? $admins[0]->ID : 1;
    wp_set_current_user($adminId);

    $mockOAuthFilter18 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GSC],
        ]);
    };
    $mockServicesFilter18 = function () {
        return [];
    };
    $mockGeneralEmpty18 = function () { return []; };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter18);
    add_filter('pre_option_laca_google_services', $mockServicesFilter18);
    add_filter('pre_option_laca_seo_general', $mockGeneralEmpty18);
    delete_transient('laca_gsc_sites_list');

    $mockFilter = function ($preempt, $parsed_args, $url) {
        if (str_contains($url, 'webmasters/v3/sites')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'siteEntry' => [
                        [
                            'siteUrl'         => 'sc-domain:lixroastery.com',
                            'permissionLevel' => 'siteOwner',
                        ],
                        [
                            'siteUrl'         => 'https://lixroastery.com/',
                            'permissionLevel' => 'siteUnverifiedUser',
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };

    add_filter('pre_http_request', $mockFilter, 10, 3);
    try {
        ob_start();
        $page = new GoogleServicesPage();
        $page->renderSearchConsolePage();
        $html = ob_get_clean();
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        delete_transient('laca_gsc_sites_list');
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter18);
        remove_filter('pre_option_laca_google_services', $mockServicesFilter18);
        remove_filter('pre_option_laca_seo_general', $mockGeneralEmpty18);
        wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);
    }

    if (!str_contains($html, '<select name="gsc_site_url"')) {
        return "Expected <select name=\"gsc_site_url\"> not found in rendered HTML";
    }

    // Option sc-domain:lixroastery.com should be selected
    if (!preg_match('/<option\s+value="sc-domain:lixroastery\.com"[^>]*selected/i', $html)) {
        return "Expected sc-domain:lixroastery.com option to be selected";
    }

    // Option https://lixroastery.com/ should be disabled
    if (!preg_match('/<option\s+value="https:\/\/lixroastery\.com\/"[^>]*disabled/i', $html)) {
        return "Expected unverified site option to be disabled";
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 19: sites.list rỗng → HTML có hướng dẫn thêm property
// -------------------------------------------------------------
runTest('GoogleServicesPage: Empty sites.list displays guidance to add property in GSC', function () {
    $oldUser = wp_get_current_user();
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    $adminId = !empty($admins) ? $admins[0]->ID : 1;
    wp_set_current_user($adminId);

    $mockOAuthFilter19 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter19);
    delete_transient('laca_gsc_sites_list');

    $mockFilter = function ($preempt, $parsed_args, $url) {
        if (str_contains($url, 'webmasters/v3/sites')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode(['siteEntry' => []]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };

    add_filter('pre_http_request', $mockFilter, 10, 3);
    try {
        ob_start();
        $page = new GoogleServicesPage();
        $page->renderSearchConsolePage();
        $html = ob_get_clean();
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        delete_transient('laca_gsc_sites_list');
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter19);
        wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);
    }

    if (!str_contains($html, 'search.google.com/search-console') && !str_contains($html, 'chưa có property nào')) {
        return "Empty siteEntry HTML missing guidance to add property via search.google.com/search-console";
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 20: AJAX laca_google_report không nonce → 403/-1; có nonce + mock dữ liệu → trả HTML partial có 2 cột
// -------------------------------------------------------------
runTest('DashboardWidget::ajaxGoogleReport: Rejects without nonce and returns 2-col partial with nonce + mock', function () {
    $widget = new \App\Features\LacaSeo\Modules\DashboardWidget\DashboardWidget();

    add_filter('wp_doing_ajax', '__return_true');
    $dieFilter = function () {
        return function ($message, $title, $args) {
            $code = $args['response'] ?? 403;
            throw new \RuntimeException('DIE:' . $message . ':' . $code);
        };
    };
    add_filter('wp_die_ajax_handler', $dieFilter);

    $oldUser = wp_get_current_user();
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    $adminId = !empty($admins) ? $admins[0]->ID : 1;
    wp_set_current_user($adminId);

    try {
        // Case A: No nonce => check_ajax_referer terminates with -1 / 403
        $_POST = ['action' => 'laca_google_report'];
        $_REQUEST = $_POST;

        $caughtDie = null;
        try {
            ob_start();
            GoogleServices::ajaxGoogleReport();
            ob_end_clean();
        } catch (\RuntimeException $e) {
            $caughtDie = $e->getMessage();
        }

        if (!$caughtDie || (!str_contains($caughtDie, 'DIE:-1') && !str_contains($caughtDie, '403'))) {
            return "Expected AJAX call without nonce to trigger die -1/403, got '{$caughtDie}'";
        }

        // Case B: With valid nonce and mock data
        $_POST = [
            'action'            => 'laca_google_report',
            'nonce'             => wp_create_nonce('laca_google_report_nonce'),
            'range'             => '28d',
            'laca_mock_google'  => '1',
        ];
        $_REQUEST = $_POST;
        $_GET['laca_mock_google'] = '1';

        $jsonOutput = '';
        try {
            ob_start();
            GoogleServices::ajaxGoogleReport();
        } catch (\RuntimeException $e) {
            $jsonOutput = ob_get_clean();
        }

        $res = json_decode($jsonOutput, true);
        if (empty($res['success'])) {
            return "Expected AJAX response success=true, got output: " . substr($jsonOutput, 0, 200);
        }

        $html = $res['data']['html'] ?? '';
        if (!str_contains($html, 'Google Search Console') || !str_contains($html, 'Google Analytics 4')) {
            return "Expected partial to contain both Google Search Console and Google Analytics 4 columns";
        }

        if (!str_contains($html, 'laca-google-dual-grid')) {
            return "Expected partial to contain 2-column dual grid layout";
        }

        return true;
    } finally {
        remove_filter('wp_doing_ajax', '__return_true');
        remove_filter('wp_die_ajax_handler', $dieFilter);
        unset($_GET['laca_mock_google']);
        $_POST = [];
        $_REQUEST = [];
        wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 21: GSC Endpoints use correct Webmasters v3 and SearchConsole v1 URLs with rawurlencode
// searchAnalytics -> https://www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Flixroastery.com%2F/searchAnalytics/query
// sitemaps -> https://www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Flixroastery.com%2F/sitemaps
// urlInspection -> https://searchconsole.googleapis.com/v1/urlInspection/index:inspect
// Mock trả 404 cho mọi URL khác những URL đúng này.
// -------------------------------------------------------------
runTest('GSC Client: Calls exact canonical endpoints (Webmasters v3 vs SearchConsole v1) with rawurlencode', function () {
    $mockOAuthFilter21 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter21);

    $calledUrls = [];

    $expectedAnalytics = 'https://www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Flixroastery.com%2F/searchAnalytics/query';
    $expectedSitemaps  = 'https://www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Flixroastery.com%2F/sitemaps';
    $expectedInspect   = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    $mockFilter = function ($preempt, $parsed_args, $url) use (&$calledUrls, $expectedAnalytics, $expectedSitemaps, $expectedInspect) {
        $calledUrls[] = $url;

        if ($url === $expectedAnalytics) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'rows' => [
                        ['clicks' => 1, 'impressions' => 10, 'ctr' => 0.1, 'position' => 1.5],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        if ($url === $expectedSitemaps) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'sitemap' => [
                        ['path' => 'https://lixroastery.com/sitemap_index.xml', 'lastSubmitted' => '2026-10-25T00:00:00Z'],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        if ($url === $expectedInspect) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'inspectionResult' => [
                        'indexStatusResult' => ['verdict' => 'PASS'],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        // Mock returns 404 for ANY other URL
        return [
            'response' => ['code' => 404, 'message' => 'Not Found'],
            'body'     => json_encode(['error' => ['code' => 404, 'message' => 'Not Found']]),
            'headers'  => ['content-type' => 'application/json'],
        ];
    };

    add_filter('pre_http_request', $mockFilter, 10, 3);
    try {
        $perf = GscClient::getPerformanceSummary('https://lixroastery.com/', 28, true);
        if (is_wp_error($perf)) {
            return "searchAnalytics failed with error: " . $perf->get_error_message() . " (called: " . implode(', ', $calledUrls) . ")";
        }

        $sitemaps = GscClient::getSitemaps('https://lixroastery.com/', true);
        if (is_wp_error($sitemaps)) {
            return "sitemaps failed with error: " . $sitemaps->get_error_message() . " (called: " . implode(', ', $calledUrls) . ")";
        }

        $inspect = GscClient::inspectUrl('https://lixroastery.com/', 'https://lixroastery.com/about', true);
        if (is_wp_error($inspect)) {
            return "inspectUrl failed with error: " . $inspect->get_error_message() . " (called: " . implode(', ', $calledUrls) . ")";
        }
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter21);
    }

    if (!in_array($expectedAnalytics, $calledUrls, true)) {
        return "searchAnalytics URL mismatch: expected '{$expectedAnalytics}', called: " . json_encode($calledUrls);
    }
    if (!in_array($expectedSitemaps, $calledUrls, true)) {
        return "sitemaps URL mismatch: expected '{$expectedSitemaps}', called: " . json_encode($calledUrls);
    }
    if (!in_array($expectedInspect, $calledUrls, true)) {
        return "inspectUrl URL mismatch: expected '{$expectedInspect}', called: " . json_encode($calledUrls);
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 22: Decode email from Google JWT id_token payload
// -------------------------------------------------------------
runTest('GscOAuth::extractEmailFromIdToken: Decodes email from JWT payload without verification', function () {
    $payload = ['email' => 'xliiicoffeeeverywhere@gmail.com', 'sub' => '1234567890'];
    $fakeJwt = 'eyJhbGciOiJSUzI1NiJ9.' . rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=') . '.sig';
    $email = GscOAuth::extractEmailFromIdToken($fakeJwt);
    if ($email !== 'xliiicoffeeeverywhere@gmail.com') {
        return "Expected 'xliiicoffeeeverywhere@gmail.com', got '{$email}'";
    }

    $invalidJwt = 'invalid-jwt-token';
    if (GscOAuth::extractEmailFromIdToken($invalidJwt) !== null) {
        return "Expected null for invalid JWT";
    }

    return true;
}, $pass, $fail);

// -------------------------------------------------------------
// Test 23: Changing client_id purges old tokens
// -------------------------------------------------------------
runTest('GscOAuth::setPendingCredentials: Changing client_id purges old tokens', function () {
    $origCreds = GscOAuth::getCredentials();

    try {
        GscOAuth::saveCredentials([
            'client_id'     => 'initial-client-id.apps.googleusercontent.com',
            'client_secret' => 'old-secret',
            'access_token'  => 'tok-123',
            'refresh_token' => 'ref-123',
        ]);

        if (!GscOAuth::isConnected()) {
            return 'Precondition failed: should be connected';
        }

        // Set pending with different client_id
        GscOAuth::setPendingCredentials('new-client-id.apps.googleusercontent.com', 'new-secret');

        if (GscOAuth::isConnected()) {
            return 'Expected old tokens to be deleted when client_id changes';
        }

        return true;
    } finally {
        if ($origCreds) {
            GscOAuth::saveCredentials($origCreds);
        } else {
            GscOAuth::disconnect();
        }
        delete_option('laca_google_pending');
        delete_option('laca_seo_gsc_pending');
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 24: Migration copies options without deleting legacy options
// -------------------------------------------------------------
runTest('GoogleServices::migrate: Copies 7 settings keys without deleting legacy options', function () {
    $origMigrated = get_option('laca_google_services_migrated', false);
    $origGoogle   = get_option('laca_google_services', false);
    $origLegacy   = get_option('laca_seo_general', false);

    delete_option('laca_google_services_migrated');
    delete_option('laca_google_services');

    update_option('laca_seo_general', [
        'ga4_measurement_id'  => 'G-MIGRATE123',
        'gtm_container_id'    => 'GTM-MIGRATE456',
        'ga_anonymize_ip'     => 'off',
        'ga_exclude_loggedin' => 'on',
        'ga4_property_id'     => '987654321',
        'ga4_goal_events'     => 'purchase|Order',
        'gsc_site_url'        => 'https://migratedsite.com/',
        'other_seo_key'       => 'preserve_me',
    ]);

    try {
        GoogleServices::migrate();

        $migrated = get_option('laca_google_services');
        if (!is_array($migrated) || ($migrated['ga4_measurement_id'] ?? '') !== 'G-MIGRATE123') {
            return "Migration failed to copy ga4_measurement_id: " . json_encode($migrated);
        }
        if (($migrated['gsc_site_url'] ?? '') !== 'https://migratedsite.com/') {
            return "Migration failed to copy gsc_site_url";
        }

        $legacy = get_option('laca_seo_general');
        if (!is_array($legacy) || ($legacy['other_seo_key'] ?? '') !== 'preserve_me') {
            return "Legacy option laca_seo_general was corrupted or deleted during migration";
        }

        return true;
    } finally {
        if ($origMigrated !== false) {
            update_option('laca_google_services_migrated', $origMigrated);
        } else {
            delete_option('laca_google_services_migrated');
        }
        if ($origGoogle !== false) {
            update_option('laca_google_services', $origGoogle);
        } else {
            delete_option('laca_google_services');
        }
        if ($origLegacy !== false) {
            update_option('laca_seo_general', $origLegacy);
        } else {
            delete_option('laca_seo_general');
        }
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 25: Tracking runs independent of LacaSeo and outputs GA4/GTM scripts
// -------------------------------------------------------------
runTest('Tracking: Independent from LacaSeo and outputs GA4/GTM scripts', function () {
    $mockServicesFilter25 = function () {
        return [
            'ga4_measurement_id'  => 'G-STANDALONE',
            'gtm_container_id'    => 'GTM-STANDALONE',
            'ga_anonymize_ip'     => 'on',
            'ga_exclude_loggedin' => 'off',
        ];
    };
    add_filter('pre_option_laca_google_services', $mockServicesFilter25);

    try {
        ob_start();
        (new Tracking())->outputHeadTracking();
        $head = ob_get_clean();

        if (!str_contains($head, 'G-STANDALONE') || !str_contains($head, 'GTM-STANDALONE')) {
            return "Expected head tracking output to include G-STANDALONE and GTM-STANDALONE, got: " . substr($head, 0, 300);
        }

        ob_start();
        (new Tracking())->outputBodyTracking();
        $body = ob_get_clean();

        if (!str_contains($body, 'GTM-STANDALONE')) {
            return "Expected body tracking output to include GTM-STANDALONE noscript";
        }

        return true;
    } finally {
        remove_filter('pre_option_laca_google_services', $mockServicesFilter25);
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 26: GA4 DateRange Naming: Requests omit forbidden date_range_* names while response parsing handles date_range_0/1
// -------------------------------------------------------------
runTest('GA4 DateRange Naming: Requests omit forbidden date_range_* names while response parsing handles date_range_0/1', function () {
    $mockOAuthFilter26 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter26);

    $capturedRequests = [];
    $mockFilter = function ($preempt, $parsed_args, $url) use (&$capturedRequests) {
        if (str_contains($url, 'analyticsdata.googleapis.com')) {
            $reqBody = json_decode($parsed_args['body'] ?? '{}', true);
            $capturedRequests[] = [
                'url'  => $url,
                'body' => $reqBody,
            ];

            // Verify rule: GA4 API forbids names starting with date_range_
            if (!empty($reqBody['dateRanges']) && is_array($reqBody['dateRanges'])) {
                foreach ($reqBody['dateRanges'] as $dr) {
                    if (isset($dr['name']) && str_starts_with((string) $dr['name'], 'date_range_')) {
                        throw new \RuntimeException("DateRange name '{$dr['name']}' is not allowed. Names cannot begin with date_range_");
                    }
                }
            }

            // Return mock GA response with default date_range_0 / date_range_1
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'dimensionHeaders' => [
                        ['name' => 'dateRange'],
                        ['name' => 'date'],
                    ],
                    'metricHeaders' => [
                        ['name' => 'activeUsers'],
                        ['name' => 'newUsers'],
                        ['name' => 'sessions'],
                        ['name' => 'screenPageViews'],
                        ['name' => 'averageSessionDuration'],
                        ['name' => 'engagementRate'],
                        ['name' => 'keyEvents'],
                        ['name' => 'eventCount'],
                    ],
                    'totals' => [
                        [
                            'dimensionValues' => [['value' => 'date_range_0']],
                            'metricValues'    => [['value' => '150'], ['value' => '100'], ['value' => '200'], ['value' => '400'], ['value' => '80'], ['value' => '0.7'], ['value' => '15']],
                        ],
                        [
                            'dimensionValues' => [['value' => 'date_range_1']],
                            'metricValues'    => [['value' => '100'], ['value' => '70'], ['value' => '150'], ['value' => '300'], ['value' => '75'], ['value' => '0.6'], ['value' => '10']],
                        ],
                    ],
                    'rows' => [
                        [
                            'dimensionValues' => [['value' => 'date_range_0'], ['value' => 'purchase']],
                            'metricValues'    => [['value' => '5']],
                        ],
                        [
                            'dimensionValues' => [['value' => 'date_range_1'], ['value' => 'purchase']],
                            'metricValues'    => [['value' => '3']],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }
        return $preempt;
    };
    add_filter('pre_http_request', $mockFilter, 10, 3);

    try {
        $rep = GaClient::runReport('987654321', '28d', true);
        if (is_wp_error($rep)) {
            return "GaClient::runReport failed: " . $rep->get_error_message();
        }
        if (($rep['users'] ?? 0) !== 150) {
            return "Expected users=150, got " . ($rep['users'] ?? 'null');
        }

        $goals = GaClient::runGoalEventsReport('987654321', 28, ['purchase' => 'Đơn hàng'], true);
        if (is_wp_error($goals)) {
            return "GaClient::runGoalEventsReport failed: " . $goals->get_error_message();
        }
        if (empty($goals) || ($goals[0]['value'] ?? 0) !== 5) {
            return "Expected goals purchase value=5, got " . var_export($goals, true);
        }

        if (count($capturedRequests) < 2) {
            return "Expected at least 2 requests to analyticsdata, got " . count($capturedRequests);
        }

        // Explicitly assert every captured request has dateRanges without name starting with date_range_
        foreach ($capturedRequests as $req) {
            if (!empty($req['body']['dateRanges'])) {
                foreach ($req['body']['dateRanges'] as $dr) {
                    if (isset($dr['name']) && str_starts_with((string) $dr['name'], 'date_range_')) {
                        return "Forbidden dateRange name found in request: " . json_encode($dr);
                    }
                }
            }
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter26);
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 27: GA4 Raw API Response Pipeline: channels, top pages duration (300s/5 users = 1m 0s), no '1m 30s'
// -------------------------------------------------------------
runTest('GA4 Raw API Response Pipeline: channels, top pages duration (300s/5 users = 1m 0s), no 1m 30s', function () {
    $mockServicesFilter27 = function () {
        return [
            'ga4_property_id' => '438291045',
        ];
    };
    $mockOAuthFilter27 = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_laca_google_services', $mockServicesFilter27);
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter27);
    Settings::clearCache();

    $mockFilter = function ($preempt, $parsed_args, $url) {
        if (str_contains($url, 'analyticsdata.googleapis.com') && str_contains($url, ':runReport')) {
            $reqBody = json_decode($parsed_args['body'] ?? '{}', true);
            $dimNames = array_column($reqBody['dimensions'] ?? [], 'name');

            // 1. Top Pages sub-report: dimensions [pagePath, pageTitle]
            if (in_array('pagePath', $dimNames, true)) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'dimensionHeaders' => [
                            ['name' => 'pagePath'],
                            ['name' => 'pageTitle'],
                        ],
                        'metricHeaders' => [
                            ['name' => 'screenPageViews'],
                            ['name' => 'userEngagementDuration'],
                            ['name' => 'activeUsers'],
                        ],
                        'rows' => [
                            [
                                'dimensionValues' => [
                                    ['value' => '/san-pham/ca-phe-lix/'],
                                    ['value' => 'Cà phê Specialty LIX'],
                                ],
                                // 500 views, 300s total duration / 5 active users = 60s => '1m 0s'
                                'metricValues' => [
                                    ['value' => '500'],
                                    ['value' => '300'],
                                    ['value' => '5'],
                                ],
                            ],
                            [
                                'dimensionValues' => [
                                    ['value' => '/ve-chung-toi/'],
                                    ['value' => 'Về Chúng Tôi'],
                                ],
                                // 200 views, 130s total duration / 2 active users = 65s => '1m 05s'
                                'metricValues' => [
                                    ['value' => '200'],
                                    ['value' => '130'],
                                    ['value' => '2'],
                                ],
                            ],
                        ],
                    ]),
                    'headers' => ['content-type' => 'application/json'],
                ];
            }

            // 2. Channels sub-report: dimension [sessionDefaultChannelGroup]
            if (in_array('sessionDefaultChannelGroup', $dimNames, true)) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'dimensionHeaders' => [
                            ['name' => 'sessionDefaultChannelGroup'],
                        ],
                        'metricHeaders' => [
                            ['name' => 'sessions'],
                        ],
                        'rows' => [
                            [
                                'dimensionValues' => [['value' => 'Organic Search']],
                                'metricValues'    => [['value' => '150']],
                            ],
                            [
                                'dimensionValues' => [['value' => 'Direct']],
                                'metricValues'    => [['value' => '100']],
                            ],
                        ],
                    ]),
                    'headers' => ['content-type' => 'application/json'],
                ];
            }

            // 3. Devices sub-report: dimension [deviceCategory]
            if (in_array('deviceCategory', $dimNames, true)) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'dimensionHeaders' => [
                            ['name' => 'deviceCategory'],
                        ],
                        'metricHeaders' => [
                            ['name' => 'sessions'],
                        ],
                        'rows' => [
                            [
                                'dimensionValues' => [['value' => 'mobile']],
                                'metricValues'    => [['value' => '200']],
                            ],
                        ],
                    ]),
                    'headers' => ['content-type' => 'application/json'],
                ];
            }

            // 4. Main report: dimension [date] across 2 date ranges
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'dimensionHeaders' => [
                        ['name' => 'date'],
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
                    'totals' => [
                        [
                            'dimensionValues' => [['value' => 'date_range_0']],
                            'metricValues'    => [
                                ['value' => '200'],
                                ['value' => '150'],
                                ['value' => '250'],
                                ['value' => '800'],
                                ['value' => '75'],
                                ['value' => '0.75'],
                                ['value' => '10'],
                            ],
                        ],
                        [
                            'dimensionValues' => [['value' => 'date_range_1']],
                            'metricValues'    => [
                                ['value' => '180'],
                                ['value' => '120'],
                                ['value' => '220'],
                                ['value' => '700'],
                                ['value' => '85'],
                                ['value' => '0.70'],
                                ['value' => '8'],
                            ],
                        ],
                    ],
                    'rows' => [
                        [
                            'dimensionValues' => [['value' => '20261025']],
                            'metricValues'    => [
                                ['value' => '20'],
                                ['value' => '15'],
                                ['value' => '25'],
                                ['value' => '80'],
                                ['value' => '90'],
                                ['value' => '0.75'],
                                ['value' => '1'],
                            ],
                        ],
                    ],
                ]),
                'headers' => ['content-type' => 'application/json'],
            ];
        }

        return $preempt;
    };
    add_filter('pre_http_request', $mockFilter, 10, 3);

    try {
        $gaData = GoogleDashboardService::getGa4Data('28d', true);

        // Assert Channels: sessions and share are non-zero and accurately calculated
        if (empty($gaData['channels']) || count($gaData['channels']) < 2) {
            return "Expected 2 channels, got " . count($gaData['channels'] ?? []);
        }
        $c0 = $gaData['channels'][0];
        $c1 = $gaData['channels'][1];
        if ($c0['sessions'] !== 150 || $c0['share'] !== 60) {
            return "Expected channel 0 (Organic Search) sessions=150 share=60%, got sessions={$c0['sessions']} share={$c0['share']}%";
        }
        if ($c1['sessions'] !== 100 || $c1['share'] !== 40) {
            return "Expected channel 1 (Direct) sessions=100 share=40%, got sessions={$c1['sessions']} share={$c1['share']}%";
        }

        // Assert Top Pages: views non-zero and duration calculated correctly from 300s / 5 users = 60s ('1m 0s')
        if (empty($gaData['topPages']) || count($gaData['topPages']) < 2) {
            return "Expected 2 topPages, got " . count($gaData['topPages'] ?? []);
        }
        $p0 = $gaData['topPages'][0];
        $p1 = $gaData['topPages'][1];
        if ($p0['views'] !== 500) {
            return "Expected page 0 views=500, got " . var_export($p0['views'], true);
        }
        if ($p0['duration'] !== '1m 0s') {
            return "Expected page 0 duration '1m 0s' (300s/5users), got '" . ($p0['duration'] ?? 'null') . "'";
        }
        if ($p1['views'] !== 200) {
            return "Expected page 1 views=200, got " . var_export($p1['views'], true);
        }
        if ($p1['duration'] !== '1m 05s') {
            return "Expected page 1 duration '1m 05s' (130s/2users), got '" . ($p1['duration'] ?? 'null') . "'";
        }

        // Render partial with $gaData
        $gsc = ['connected' => false];
        $ga = $gaData;
        $range = '28d';
        ob_start();
        include dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/partials/google-report.php';
        $html = ob_get_clean();

        // Assert HTML: must NOT contain fake duration '1m 30s'
        if (str_contains($html, '1m 30s')) {
            return "HTML still contains fabricated duration '1m 30s'";
        }

        // Assert HTML contains computed duration and channel breakdown
        if (!str_contains($html, '1m 0s')) {
            return "HTML missing computed duration '1m 0s'";
        }
        if (!str_contains($html, 'Organic Search: 60%') || !str_contains($html, '(150)')) {
            return "HTML missing channel Organic Search 60% (150)";
        }
        if (!str_contains($html, 'Direct: 40%') || !str_contains($html, '(100)')) {
            return "HTML missing channel Direct 40% (100)";
        }

        // Test Edge Case: when totalChannelSessions = 0, hide allocation bar and show 'Chưa có dữ liệu kênh'
        $gaZeroChannels = $gaData;
        $gaZeroChannels['channels'] = [
            ['name' => 'Direct', 'sessions' => 0, 'share' => 0, 'color' => '#18181b'],
        ];
        $ga = $gaZeroChannels;
        ob_start();
        include dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/partials/google-report.php';
        $htmlZero = ob_get_clean();

        if (str_contains($htmlZero, 'title="Direct: 0%"')) {
            return "HTML with 0 channel sessions should not render progress bar segment";
        }
        if (!str_contains($htmlZero, 'Chưa có dữ liệu kênh')) {
            return "HTML with 0 channel sessions should display 'Chưa có dữ liệu kênh'";
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        remove_filter('pre_option_laca_google_services', $mockServicesFilter27);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter27);
        Settings::clearCache();
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 28: GA4 Refresh button bypasses cache, displays web streams, match badge, and host mismatch warning
// -------------------------------------------------------------
runTest('GA4 Admin: Refresh button bypasses cache, options display web stream & match badge, host mismatch warning', function () {
    $requestCounts = [
        'accountSummaries' => 0,
        'streams_100001'   => 0,
        'streams_100002'   => 0,
    ];

    $mockFilter = function ($preempt, $parsed_args, $url) use (&$requestCounts) {
        if (str_contains($url, 'accountSummaries')) {
            $requestCounts['accountSummaries']++;
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'accountSummaries' => [
                        [
                            'name'              => 'accountSummaries/55555',
                            'account'           => 'accounts/55555',
                            'displayName'       => 'LIX Roastery Google Account',
                            'propertySummaries' => [
                                [
                                    'property'     => 'properties/100001',
                                    'displayName'  => '43 Factory',
                                    'propertyType' => 'PROPERTY_TYPE_ORDINARY',
                                ],
                                [
                                    'property'     => 'properties/100002',
                                    'displayName'  => 'LIX Roastery Website',
                                    'propertyType' => 'PROPERTY_TYPE_ORDINARY',
                                ],
                            ],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        if (str_contains($url, 'properties/100001/dataStreams')) {
            $requestCounts['streams_100001']++;
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'dataStreams' => [
                        [
                            'name'          => 'properties/100001/dataStreams/888',
                            'type'          => 'WEB_DATA_STREAM',
                            'displayName'   => '43 Factory Web',
                            'webStreamData' => [
                                'measurementId' => 'G-43FACTORY1',
                                'defaultUri'    => 'https://43factory.coffee',
                            ],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        if (str_contains($url, 'properties/100002/dataStreams')) {
            $requestCounts['streams_100002']++;
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'dataStreams' => [
                        [
                            'name'          => 'properties/100002/dataStreams/999',
                            'type'          => 'WEB_DATA_STREAM',
                            'displayName'   => 'LIX Web Stream',
                            'webStreamData' => [
                                'measurementId' => 'G-LIXROAST01',
                                'defaultUri'    => 'https://lixroastery.com',
                            ],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        return $preempt;
    };
    add_filter('pre_http_request', $mockFilter, 10, 3);

    $mockOAuthFilter = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);

    // Initial clean transients
    GaClient::clearPropertiesCache();
    delete_transient('laca_ga_streams_100001');
    delete_transient('laca_ga_streams_100002');

    $currentMsId = 'G-LIXROAST01';
    $mockServicesFilter = function () use (&$currentMsId) {
        return [
            'ga4_property_id'    => '',
            'ga4_measurement_id' => $currentMsId,
        ];
    };
    add_filter('pre_option_laca_google_services', $mockServicesFilter);

    // Cap filter for manage_options
    $capFilter = function ($allcaps) {
        $allcaps['manage_options'] = true;
        return $allcaps;
    };
    add_filter('user_has_cap', $capFilter);

    try {
        // 1. First fetch - must hit API
        $props = GaClient::listProperties(false);
        if (count($props) !== 2) {
            return "Expected 2 properties, got " . count($props);
        }
        if ($requestCounts['accountSummaries'] !== 1) {
            return "Expected 1 accountSummaries request, got {$requestCounts['accountSummaries']}";
        }

        // 2. Second fetch without force - must use cache (0 additional HTTP requests)
        $cachedProps = GaClient::listProperties(false);
        if ($requestCounts['accountSummaries'] !== 1) {
            return "Expected cache hit (1 request), got {$requestCounts['accountSummaries']}";
        }

        // 3. Force refresh - must bypass cache and hit API again
        $refreshedProps = GaClient::listProperties(true);
        if ($requestCounts['accountSummaries'] !== 2) {
            return "Expected 2 accountSummaries requests after forceRefresh, got {$requestCounts['accountSummaries']}";
        }

        // 4. Render Analytics Page with matching Measurement ID (G-LIXROAST01 matching LIX Roastery Website)
        $page = new GoogleServicesPage();
        ob_start();
        $page->renderAnalyticsPage();
        $htmlMatching = ob_get_clean();

        // Assert refresh button exists with correct name and title
        if (!str_contains($htmlMatching, 'name="laca_refresh_ga_properties"')) {
            return "Analytics page missing refresh button 'laca_refresh_ga_properties'";
        }
        if (!str_contains($htmlMatching, 'Tải lại danh sách')) {
            return "Analytics page missing button text 'Tải lại danh sách'";
        }

        // Assert option has web stream label
        if (!str_contains($htmlMatching, 'luồng: LIX Web Stream G-LIXROAST01')) {
            return "Option missing stream label 'luồng: LIX Web Stream G-LIXROAST01'";
        }

        // Assert option has match badge for G-LIXROAST01
        if (!str_contains($htmlMatching, '✓ khớp mã đo lường')) {
            return "Option missing match badge '✓ khớp mã đo lường'";
        }

        // Assert NO host mismatch warning because LIX Roastery Website contains host/domain
        if (str_contains($htmlMatching, "kiểm tra lại có đúng website này không")) {
            return "Should NOT show host mismatch warning when property name matches host/domain";
        }

        // 5. Change Measurement ID to G-43FACTORY1 (belongs to '43 Factory', which does NOT contain host 'lixroastery.com')
        $currentMsId = 'G-43FACTORY1';
        Settings::clearCache();

        ob_start();
        $page->renderAnalyticsPage();
        $htmlMismatch = ob_get_clean();

        // Assert gentle warning appears for 43 Factory
        $expectedWarning = "Mã đo lường đang nằm trong property '43 Factory', kiểm tra lại có đúng website này không.";
        $decodedHtml = html_entity_decode($htmlMismatch, ENT_QUOTES, 'UTF-8');
        if (!str_contains($decodedHtml, $expectedWarning)) {
            return "Missing expected warning: '{$expectedWarning}' in HTML";
        }

        // 6. Test handleActions refresh action
        $_POST['laca_refresh_ga_properties'] = '1';
        $_POST['_wpnonce'] = wp_create_nonce('laca_refresh_ga_properties_action');

        $redirectUrl = null;
        $redirectFilter = function ($location) use (&$redirectUrl) {
            $redirectUrl = $location;
            throw new \Exception("REDIRECTED: " . $location);
        };
        add_filter('wp_redirect', $redirectFilter);

        try {
            $page->handleActions();
        } catch (\Exception $e) {
            // Expected redirect
        } finally {
            remove_filter('wp_redirect', $redirectFilter);
            unset($_POST['laca_refresh_ga_properties'], $_POST['_wpnonce']);
        }

        if (!$redirectUrl || !str_contains($redirectUrl, 'refreshed_ga_props=1')) {
            return "Expected redirect containing 'refreshed_ga_props=1', got '" . ($redirectUrl ?? 'null') . "'";
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);
        remove_filter('pre_option_laca_google_services', $mockServicesFilter);
        remove_filter('user_has_cap', $capFilter);
        GaClient::clearPropertiesCache();
        delete_transient('laca_ga_streams_100001');
        delete_transient('laca_ga_streams_100002');
        Settings::clearCache();
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 29: GSC totals without dimensions & dataState=all, GA4 Realtime active users & 24-48h notice
// -------------------------------------------------------------
runTest('GSC Totals (no dimensions, dataState=all), GA4 Realtime active users & 24-48h notice', function () {
    $totalsRequestBodies = [];
    $dimensionRequests = [];

    $mockFilter = function ($preempt, $parsed_args, $url) use (&$totalsRequestBodies, &$dimensionRequests) {
        // GSC searchAnalytics/query
        if (str_contains($url, 'searchAnalytics/query')) {
            $body = !empty($parsed_args['body']) ? json_decode($parsed_args['body'], true) : [];
            if (empty($body['dimensions'])) {
                $totalsRequestBodies[] = $body;
                // Return 1 click / 29 impressions / 19.89655 position
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'rows' => [
                            [
                                'clicks'      => 1,
                                'impressions' => 29,
                                'ctr'         => 0.03448,
                                'position'    => 19.89655,
                            ],
                        ],
                    ]),
                    'headers'  => ['content-type' => 'application/json'],
                ];
            }

            $dimensionRequests[] = $body;
            if ($body['dimensions'] === ['query']) {
                // Mock query rows total 0 click (simulating Google anonymizing queries)
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'rows' => [
                            [
                                'keys'        => ['lix roastery la ai'],
                                'clicks'      => 0,
                                'impressions' => 15,
                                'ctr'         => 0,
                                'position'    => 20.0,
                            ],
                        ],
                    ]),
                    'headers'  => ['content-type' => 'application/json'],
                ];
            }

            if ($body['dimensions'] === ['page']) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'rows' => [
                            [
                                'keys'        => ['https://lixroastery.com/journal/lix-roastery-la-ai/'],
                                'clicks'      => 1,
                                'impressions' => 29,
                                'ctr'         => 0.03448,
                                'position'    => 19.9,
                            ],
                        ],
                    ]),
                    'headers'  => ['content-type' => 'application/json'],
                ];
            }
        }

        // GA4 Realtime
        if (str_contains($url, ':runRealtimeReport')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'rows' => [
                        [
                            'metricValues' => [
                                ['value' => '3'],
                            ],
                        ],
                    ],
                    'rowCount' => 1,
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        // GA4 Standard Report
        if (str_contains($url, ':runReport')) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'rows' => [
                        [
                            'metricValues' => [
                                ['value' => '0'],
                                ['value' => '0'],
                                ['value' => '0'],
                                ['value' => '0'],
                            ],
                        ],
                    ],
                ]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        }

        return $preempt;
    };
    add_filter('pre_http_request', $mockFilter, 10, 3);

    $mockOAuthFilter = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token',
            'refresh_token' => 'mock-refresh',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GSC, GscOAuth::SCOPE_GA],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);

    $mockServicesFilter = function () {
        return [
            'gsc_property'       => 'https://lixroastery.com/',
            'ga4_property_id'    => '999888777',
            'ga4_measurement_id' => 'G-LIXTEST01',
        ];
    };
    add_filter('pre_option_laca_google_services', $mockServicesFilter);

    $capFilter = function ($allcaps) {
        $allcaps['manage_options'] = true;
        return $allcaps;
    };
    add_filter('user_has_cap', $capFilter);

    try {
        // Clear transients
        delete_transient('laca_gsc_perf_https_lixroastery_com_28d');
        delete_transient('laca_gsc_pages_https_lixroastery_com_28d');
        delete_transient('laca_ga_overview_999888777_28d');
        delete_transient('laca_ga_realtime_999888777');

        // 1. Fetch GSC Data
        $gsc = GoogleDashboardService::getGscData('28d', true);

        // Verify request bodies
        if (empty($totalsRequestBodies)) {
            return "Expected at least 1 GSC request without dimensions for totals";
        }
        $totalsReq = $totalsRequestBodies[0];
        if (isset($totalsReq['dimensions'])) {
            return "Expected totals request to NOT have 'dimensions', got: " . json_encode($totalsReq['dimensions']);
        }
        if (($totalsReq['dataState'] ?? '') !== 'all') {
            return "Expected totals request to have 'dataState' => 'all', got: " . ($totalsReq['dataState'] ?? 'none');
        }
        $expectedYesterday = date('Y-m-d', strtotime('-1 day'));
        if (($totalsReq['endDate'] ?? '') !== $expectedYesterday) {
            return "Expected totals request endDate to be yesterday ({$expectedYesterday}), got: " . ($totalsReq['endDate'] ?? 'none');
        }

        // Verify GSC totals extracted from aggregate request rather than query rows
        if (($gsc['clicks'] ?? 0) !== 1) {
            return "Expected GSC clicks to be 1, got " . ($gsc['clicks'] ?? 0);
        }
        if (($gsc['impressions'] ?? 0) !== 29) {
            return "Expected GSC impressions to be 29, got " . ($gsc['impressions'] ?? 0);
        }
        if (round((float) ($gsc['position'] ?? 0), 1) !== 19.9) {
            return "Expected GSC position to be 19.9, got " . ($gsc['position'] ?? 0);
        }

        // 2. Fetch GA4 Data
        $ga = GoogleDashboardService::getGa4Data('28d', true);

        if (($ga['users'] ?? -1) !== 0) {
            return "Expected GA4 standard report users to be 0, got " . ($ga['users'] ?? -1);
        }
        if (($ga['realtimeUsers'] ?? 0) !== 3) {
            return "Expected GA4 realtime users to be 3, got " . ($ga['realtimeUsers'] ?? 0);
        }

        // 3. Render HTML partial with users=0 and realtime=3 (Case A)
        ob_start();
        $isMock = false;
        $range = '28d';
        include dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/partials/google-report.php';
        $htmlCaseA = ob_get_clean();

        // GSC KPI values in HTML
        if (!preg_match('/>\s*1\s*<\/strong>/', $htmlCaseA) || !preg_match('/>\s*29\s*<\/strong>/', $htmlCaseA) || !str_contains($htmlCaseA, '#19.9')) {
            return "HTML missing GSC totals (1, 29, #19.9)";
        }
        if (!str_contains($htmlCaseA, 'Dữ liệu đến ngày')) {
            return "HTML missing 'Dữ liệu đến ngày'";
        }

        // Realtime card in HTML
        if (!str_contains($htmlCaseA, 'Đang hoạt động (30 phút qua)')) {
            return "HTML missing 'Đang hoạt động (30 phút qua)'";
        }
        if (!preg_match('/id="laca-ga4-realtime-count"[^>]*>\s*3\s*<\/strong>/', $htmlCaseA)) {
            return "HTML missing realtime count 3";
        }

        // 24-48h notice in HTML (should be PRESENT when users=0 and realtimeUsers=3)
        $expectedNotice = 'GA4 cần 24–48 giờ để xử lý báo cáo; số đang hoạt động là dữ liệu trực tiếp';
        if (!str_contains($htmlCaseA, $expectedNotice)) {
            return "HTML should contain 24-48h notice when report=0 and realtime>0";
        }

        // 4. Render HTML partial with users=50 and realtime=3 (Case B)
        $gaCaseB = $ga;
        $gaCaseB['users'] = 50;
        $ga = $gaCaseB;
        ob_start();
        include dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/partials/google-report.php';
        $htmlCaseB = ob_get_clean();

        if (str_contains($htmlCaseB, $expectedNotice)) {
            return "HTML should NOT contain 24-48h notice when standard report has users > 0";
        }
        if (!str_contains($htmlCaseB, 'Đang hoạt động (30 phút qua)')) {
            return "HTML Case B should still contain 'Đang hoạt động (30 phút qua)'";
        }

        // 5. Test AJAX action laca_ga4_realtime
        $dieFilter = function () {
            return function ($message = '', $title = '', $args = []) {
                throw new \RuntimeException('DIE:' . (is_scalar($message) ? $message : json_encode($message)));
            };
        };
        add_filter('wp_die_ajax_handler', $dieFilter);
        add_filter('wp_doing_ajax', '__return_true');

        $oldUser = wp_get_current_user();
        wp_set_current_user(1);

        $_POST = [
            'action' => 'laca_ga4_realtime',
            'nonce'  => wp_create_nonce('laca_google_report_nonce'),
        ];
        $_REQUEST = $_POST;

        $ajaxOutput = '';
        try {
            ob_start();
            GoogleServices::ajaxGa4Realtime();
        } catch (\RuntimeException $e) {
            $ajaxOutput = ob_get_clean();
        }

        $res = json_decode($ajaxOutput, true);
        if (empty($res['success']) || ($res['data']['activeUsers'] ?? null) !== 3) {
            return "Expected AJAX realtime success with activeUsers=3, got: " . substr($ajaxOutput, 0, 200);
        }

        return true;
    } finally {
        remove_filter('pre_http_request', $mockFilter, 10);
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);
        remove_filter('pre_option_laca_google_services', $mockServicesFilter);
        remove_filter('user_has_cap', $capFilter);
        remove_filter('wp_doing_ajax', '__return_true');
        if (isset($dieFilter)) {
            remove_filter('wp_die_ajax_handler', $dieFilter);
        }
        $_POST = [];
        $_REQUEST = [];
        if (isset($oldUser)) {
            wp_set_current_user($oldUser instanceof \WP_User ? $oldUser->ID : (int) $oldUser);
        }
        delete_transient('laca_gsc_perf_https_lixroastery_com_28d');
        delete_transient('laca_gsc_pages_https_lixroastery_com_28d');
        delete_transient('laca_ga_overview_999888777_28d');
        delete_transient('laca_ga_realtime_999888777');
        Settings::clearCache();
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 30: G1 — Setup State, index.php visibility condition, single complete column, capability filter
// -------------------------------------------------------------
runTest('G1: setupState (4 states + error), index.php hidden when unconfigured, single column when 1 complete, capability filter', function () {
    global $current_screen;
    $origScreen = $current_screen;

    $current_screen = \WP_Screen::get('dashboard');

    $capFilter = function ($allcaps) {
        $allcaps['manage_options'] = true;
        return $allcaps;
    };
    add_filter('user_has_cap', $capFilter);

    try {
        // --- 1. Test setupState: Case A - Chưa OAuth ---
        add_filter('pre_option_' . GscOAuth::OPTION_KEY, '__return_empty_string');
        add_filter('pre_option_' . GscOAuth::LEGACY_OPTION_KEY, '__return_empty_string');
        add_filter('pre_option_laca_google_services', '__return_empty_array');

        $stateNoAuth = GoogleServices::setupState();
        if ($stateNoAuth['connected'] !== false) {
            return "Expected connected=false when no OAuth credentials, got true";
        }
        if ($stateNoAuth['gsc']['status'] !== 'incomplete') {
            return "Expected GSC status=incomplete when no OAuth, got " . $stateNoAuth['gsc']['status'];
        }
        if ($stateNoAuth['ga']['status'] !== 'incomplete') {
            return "Expected GA status=incomplete when no OAuth, got " . $stateNoAuth['ga']['status'];
        }
        if ($stateNoAuth['is_any_complete'] !== false) {
            return "Expected is_any_complete=false when no OAuth";
        }

        // Test index.php: khi chưa setup -> renderDashboardNotice() KHÔNG in ra gì (không có #laca-google-report)
        ob_start();
        unset($_GET['laca_mock_google']);
        GoogleServices::renderDashboardNotice();
        $htmlNoSetup = ob_get_clean();
        if (str_contains($htmlNoSetup, 'id="laca-google-report"')) {
            return "Expected index.php to NOT contain #laca-google-report when unconfigured";
        }

        // --- 2. Test setupState: Case B - Có OAuth nhưng thiếu property ---
        $mockOAuth = function () {
            return GscCrypto::encrypt([
                'access_token'  => 'mock-token',
                'refresh_token' => 'mock-refresh',
                'created_at'    => time(),
                'expires_in'    => 3600,
                'scopes'        => [GscOAuth::SCOPE_GSC, GscOAuth::SCOPE_GA],
            ]);
        };
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, '__return_empty_string');
        add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuth);

        $stateNoProps = GoogleServices::setupState();
        if ($stateNoProps['connected'] !== true) {
            return "Expected connected=true with mock OAuth";
        }
        if ($stateNoProps['gsc']['status'] !== 'incomplete' || !str_contains($stateNoProps['gsc']['reason'], 'property')) {
            return "Expected GSC incomplete with reason mentioning property, got " . json_encode($stateNoProps['gsc']);
        }
        if ($stateNoProps['ga']['status'] !== 'incomplete' || !str_contains($stateNoProps['ga']['reason'], 'thuộc tính')) {
            return "Expected GA incomplete with reason mentioning thuộc tính, got " . json_encode($stateNoProps['ga']);
        }

        // --- 3. Test setupState: Case C - Measurement ID không khớp stream của property ---
        $mockServicesMismatch = function () {
            return [
                'gsc_property'       => 'https://lixroastery.com/',
                'ga4_property_id'    => '123456',
                'ga4_measurement_id' => 'G-WRONGID99',
            ];
        };
        remove_filter('pre_option_laca_google_services', '__return_empty_array');
        add_filter('pre_option_laca_google_services', $mockServicesMismatch);

        // Cache streams for property 123456 with different measurementId
        set_transient('laca_ga_streams_123456', [
            ['displayName' => 'Web Stream', 'measurementId' => 'G-CORRECTID1'],
        ], 3600);

        $stateMismatch = GoogleServices::setupState();
        if ($stateMismatch['gsc']['status'] !== 'complete') {
            return "Expected GSC complete when property is set, got " . $stateMismatch['gsc']['status'];
        }
        if ($stateMismatch['ga']['status'] !== 'incomplete' || !str_contains($stateMismatch['ga']['reason'], 'không thuộc luồng web')) {
            return "Expected GA incomplete with stream mismatch reason, got " . json_encode($stateMismatch['ga']);
        }

        // Test index.php khi CHỈ GSC COMPLETE:
        ob_start();
        $gsc = [
            'connected'   => true,
            'clicks'      => 10,
            'impressions' => 100,
            'ctr'         => 10.0,
            'position'    => 5.0,
            'queries'     => [],
            'pages'       => [],
        ];
        $ga = [
            'connected' => false,
            'users'     => 0,
        ];
        $range = '28d';
        include dirname(__DIR__, 3) . '/app/src/Features/GoogleServices/views/partials/google-report.php';
        $htmlOnlyGsc = ob_get_clean();

        if (!str_contains($htmlOnlyGsc, 'Google Search Console')) {
            return "HTML missing Google Search Console when GSC is complete";
        }
        if (str_contains($htmlOnlyGsc, 'Google Analytics 4') || str_contains($htmlOnlyGsc, 'Thiết lập Google Analytics')) {
            return "HTML should NOT contain Google Analytics 4 section when GA is incomplete";
        }
        if (!str_contains($htmlOnlyGsc, 'laca-google-single-grid')) {
            return "HTML should use single grid layout when only 1 service is complete";
        }

        // --- 4. Test setupState: Case D - Đủ (cả 2 dịch vụ complete) ---
        $mockServicesComplete = function () {
            return [
                'gsc_property'       => 'https://lixroastery.com/',
                'ga4_property_id'    => '123456',
                'ga4_measurement_id' => 'G-CORRECTID1',
            ];
        };
        remove_filter('pre_option_laca_google_services', $mockServicesMismatch);
        add_filter('pre_option_laca_google_services', $mockServicesComplete);

        $stateComplete = GoogleServices::setupState();
        if ($stateComplete['gsc']['status'] !== 'complete') {
            return "Expected GSC complete, got " . $stateComplete['gsc']['status'];
        }
        if ($stateComplete['ga']['status'] !== 'complete') {
            return "Expected GA complete when measurement matches stream, got " . json_encode($stateComplete['ga']);
        }
        if (!$stateComplete['is_all_complete']) {
            return "Expected is_all_complete=true";
        }

        // --- 5. Test setupState: Case E - Error code 403 on API ---
        set_transient('laca_gsc_last_error_code', 403, 3600);
        $stateErr = GoogleServices::setupState();
        if ($stateErr['gsc']['status'] !== 'error' || ($stateErr['gsc']['code'] ?? 0) !== 403) {
            return "Expected GSC status=error with code=403, got " . json_encode($stateErr['gsc']);
        }
        delete_transient('laca_gsc_last_error_code');

        // --- 6. Test Capability filter: laca/google/report_capability ---
        $customCapFilter = function ($cap) {
            return 'custom_view_analytics_cap';
        };
        add_filter('laca/google/report_capability', $customCapFilter);
        if (GoogleServices::getReportCapability() !== 'custom_view_analytics_cap') {
            return "Expected custom capability from filter, got " . GoogleServices::getReportCapability();
        }
        remove_filter('laca/google/report_capability', $customCapFilter);

        // --- 7. Test Admin Page Stepper rendering ---
        $page = new GoogleServicesPage();
        ob_start();
        $page->renderSetupStepper('laca-google-search-console');
        $htmlStepper = ob_get_clean();

        if (!str_contains($htmlStepper, 'Trạng thái thiết lập Dịch vụ Google')) {
            return "Stepper HTML missing title";
        }
        if (!str_contains($htmlStepper, 'Bước 1') || !str_contains($htmlStepper, 'Bước 2') || !str_contains($htmlStepper, 'Bước 3') || !str_contains($htmlStepper, 'Bước 4')) {
            return "Stepper HTML missing 4 steps";
        }

        return true;
    } finally {
        $current_screen = $origScreen;
        remove_filter('user_has_cap', $capFilter);
        if (isset($mockOAuth)) {
            remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuth);
        }
        if (isset($mockServicesComplete)) {
            remove_filter('pre_option_laca_google_services', $mockServicesComplete);
        }
        delete_transient('laca_ga_streams_123456');
        delete_transient('laca_gsc_last_error_code');
        delete_transient('laca_ga_last_error_code');
        Settings::clearCache();
    }
}, $pass, $fail);

// -------------------------------------------------------------
// Test 31: G2 Data Layer: DateRange, GA4 batchRunReports, hostName filter, GSC sc-domain filter, 429 caching, and data state notices
// -------------------------------------------------------------
runTest('G2: DateRange calculations, GA4 batch & hostName, GSC domain filter, 429 cache 10m, and data states', function () {
    // --- 1. DateRange Tests ---
    $ranges = ['7d' => 7, '14d' => 14, '28d' => 28, '90d' => 90];
    $expectedEnd = date('Y-m-d', strtotime('-1 day'));

    foreach ($ranges as $rKey => $days) {
        $info = DateRange::get($rKey);
        if ($info['days'] !== $days) {
            return "DateRange::get({$rKey}) expected days={$days}, got {$info['days']}";
        }
        if ($info['endDate'] !== $expectedEnd) {
            return "DateRange::get({$rKey}) expected endDate={$expectedEnd}, got {$info['endDate']}";
        }
        $expectedStart = date('Y-m-d', strtotime("-{$days} days"));
        if ($info['startDate'] !== $expectedStart) {
            return "DateRange::get({$rKey}) expected startDate={$expectedStart}, got {$info['startDate']}";
        }
        $expectedPrevEnd = date('Y-m-d', strtotime('-' . ($days + 1) . ' days'));
        if ($info['prevEndDate'] !== $expectedPrevEnd) {
            return "DateRange::get({$rKey}) expected prevEndDate={$expectedPrevEnd}, got {$info['prevEndDate']}";
        }
        $expectedPrevStart = date('Y-m-d', strtotime('-' . ($days * 2) . ' days'));
        if ($info['prevStartDate'] !== $expectedPrevStart) {
            return "DateRange::get({$rKey}) expected prevStartDate={$expectedPrevStart}, got {$info['prevStartDate']}";
        }
    }

    // User meta preference
    $testUid = 99999;
    DateRange::setUserRange('14d', $testUid);
    if (DateRange::getUserRange($testUid) !== '14d') {
        return "Expected saved user range '14d', got " . DateRange::getUserRange($testUid);
    }
    delete_user_meta($testUid, 'laca_google_range');

    // --- 2. GA4 Request Body Verification (metricAggregations, hostName filter, keepEmptyRows) ---
    $mockOAuthFilter = function () {
        return GscCrypto::encrypt([
            'access_token'  => 'mock-token-g2',
            'refresh_token' => 'mock-refresh-g2',
            'created_at'    => time(),
            'expires_in'    => 3600,
            'client_id'     => 'mock-client',
            'client_secret' => 'mock-secret',
            'scopes'        => [GscOAuth::SCOPE_GA, GscOAuth::SCOPE_GSC],
        ]);
    };
    add_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);

    $capturedGaBody = null;
    $capturedGscBody = null;
    $capturedBatchBody = null;

    $mockHttp = function ($preempt, $parsedArgs, $url) use (&$capturedGaBody, &$capturedGscBody, &$capturedBatchBody) {
        if (str_contains($url, 'analyticsdata.googleapis.com')) {
            if (str_contains($url, ':batchRunReports')) {
                $capturedBatchBody = json_decode($parsedArgs['body'] ?? '{}', true);
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'reports' => [
                            // Report 0: Overview
                            [
                                'dimensionHeaders' => [['name' => 'date']],
                                'metricHeaders'    => [['name' => 'activeUsers'], ['name' => 'newUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews'], ['name' => 'averageSessionDuration'], ['name' => 'engagementRate'], ['name' => 'keyEvents']],
                                'totals'           => [
                                    ['dimensionValues' => [['value' => 'date_range_0']], 'metricValues' => [['value' => '500'], ['value' => '300'], ['value' => '700'], ['value' => '2500'], ['value' => '120'], ['value' => '0.65'], ['value' => '15']]],
                                ],
                                'rows'             => [],
                            ],
                            // Report 1: Top Pages
                            [
                                'dimensionHeaders' => [['name' => 'pagePath'], ['name' => 'pageTitle']],
                                'metricHeaders'    => [['name' => 'screenPageViews'], ['name' => 'userEngagementDuration'], ['name' => 'activeUsers']],
                                'rows'             => [
                                    ['dimensionValues' => [['value' => '/product/arabica/'], ['value' => 'Arabica Coffee']], 'metricValues' => [['value' => '400'], ['value' => '600'], ['value' => '10']]],
                                ],
                            ],
                            // Report 2: Channels
                            [
                                'dimensionHeaders' => [['name' => 'sessionDefaultChannelGroup']],
                                'metricHeaders'    => [['name' => 'sessions']],
                                'rows'             => [
                                    ['dimensionValues' => [['value' => 'Organic Search']], 'metricValues' => [['value' => '450']]],
                                ],
                            ],
                            // Report 3: Devices
                            [
                                'dimensionHeaders' => [['name' => 'deviceCategory']],
                                'metricHeaders'    => [['name' => 'sessions']],
                                'rows'             => [
                                    ['dimensionValues' => [['value' => 'mobile']], 'metricValues' => [['value' => '550']]],
                                ],
                            ],
                        ],
                    ]),
                    'headers' => ['content-type' => 'application/json'],
                ];
            }

            if (str_contains($url, ':runReport')) {
                $body = json_decode($parsedArgs['body'] ?? '{}', true);
                if (!empty($body['dimensions']) && ($body['dimensions'][0]['name'] ?? '') === 'date') {
                    $capturedGaBody = $body;
                }
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'dimensionHeaders' => [['name' => 'date']],
                        'metricHeaders'    => [['name' => 'activeUsers'], ['name' => 'newUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews'], ['name' => 'averageSessionDuration'], ['name' => 'engagementRate'], ['name' => 'keyEvents']],
                        'totals'           => [
                            ['dimensionValues' => [['value' => 'date_range_0']], 'metricValues' => [['value' => '100'], ['value' => '60'], ['value' => '120'], ['value' => '400'], ['value' => '60'], ['value' => '0.6'], ['value' => '2']]],
                        ],
                        'rows'             => [],
                    ]),
                    'headers' => ['content-type' => 'application/json'],
                ];
            }
        }

        if (str_contains($url, 'searchAnalytics/query')) {
            $capturedGscBody = json_decode($parsedArgs['body'] ?? '{}', true);
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'rows' => [
                        ['clicks' => 50, 'impressions' => 1000, 'ctr' => 0.05, 'position' => 12.3],
                    ],
                ]),
                'headers' => ['content-type' => 'application/json'],
            ];
        }

        return $preempt;
    };
    add_filter('pre_http_request', $mockHttp, 10, 3);

    try {
        // Test GA4 runReport request body
        delete_transient('laca_ga_rep_' . md5('438291045_28d'));
        GaClient::runReport('438291045', '28d', true);

        if (!$capturedGaBody) {
            return "Expected GA4 runReport request to be captured";
        }
        if (($capturedGaBody['metricAggregations'] ?? []) !== ['TOTAL']) {
            return "Expected GA4 metricAggregations: ['TOTAL'], got " . json_encode($capturedGaBody['metricAggregations'] ?? null);
        }
        if (($capturedGaBody['keepEmptyRows'] ?? false) !== true) {
            return "Expected GA4 keepEmptyRows: true";
        }
        if (!isset($capturedGaBody['dimensionFilter']['filter']['fieldName']) || $capturedGaBody['dimensionFilter']['filter']['fieldName'] !== 'hostName') {
            return "Expected GA4 dimensionFilter on hostName, got " . json_encode($capturedGaBody['dimensionFilter'] ?? null);
        }

        // Test GA4 getBatchOverview
        delete_transient('laca_ga_batch_' . md5('438291045_28d'));
        $batchResult = GaClient::getBatchOverview('438291045', '28d', true);

        if (is_wp_error($batchResult)) {
            return "Expected getBatchOverview to succeed, got: " . $batchResult->get_error_message();
        }
        if (!$capturedBatchBody || empty($capturedBatchBody['requests'])) {
            return "Expected batchRunReports request with sub-requests";
        }
        if (count($capturedBatchBody['requests']) > 5) {
            return "batchRunReports exceeded max 5 requests: " . count($capturedBatchBody['requests']);
        }
        if (($batchResult['users'] ?? 0) !== 500) {
            return "Expected batch overview users=500, got " . ($batchResult['users'] ?? 0);
        }
        if (empty($batchResult['topPages']) || ($batchResult['topPages'][0]['views'] ?? 0) !== 400) {
            return "Expected topPages parsed from batch report 1";
        }
        if (empty($batchResult['channels']) || ($batchResult['channels'][0]['sessions'] ?? 0) !== 450) {
            return "Expected channels parsed from batch report 2";
        }

        // Test GSC sc-domain: filter
        delete_transient('laca_gsc_perf_' . md5('sc-domain:lixroastery.local_28d'));
        GscClient::getPerformanceSummary('sc-domain:lixroastery.local', '28d', true);

        if (!$capturedGscBody) {
            return "Expected GSC request to be captured";
        }
        if (empty($capturedGscBody['dimensionFilterGroups'])) {
            return "Expected dimensionFilterGroups for sc-domain: property";
        }
        $gscFilter = $capturedGscBody['dimensionFilterGroups'][0]['filters'][0] ?? [];
        if (($gscFilter['dimension'] ?? '') !== 'page' || ($gscFilter['operator'] ?? '') !== 'contains') {
            return "Expected page contains filter in GSC request, got " . json_encode($gscFilter);
        }

        // --- 3. Rate limiting 429 tests ---
        remove_filter('pre_http_request', $mockHttp, 10);

        $mock429 = function ($preempt, $parsedArgs, $url) {
            return [
                'response' => ['code' => 429, 'message' => 'Too Many Requests'],
                'body'     => json_encode(['error' => ['message' => 'Rate limit exceeded']]),
                'headers'  => ['content-type' => 'application/json'],
            ];
        };
        add_filter('pre_http_request', $mock429, 10, 3);

        delete_transient('laca_ga_429_limit');
        $ga429 = GaClient::runReport('438291045', '28d', true);

        if (!is_wp_error($ga429) || (int) ($ga429->get_error_data()['status'] ?? 0) !== 429) {
            return "Expected GA4 429 WP_Error on rate limit";
        }
        if (!get_transient('laca_ga_429_limit')) {
            return "Expected transient laca_ga_429_limit to be set on 429 response";
        }
        if (!str_contains($ga429->get_error_message(), 'Google đang giới hạn truy vấn')) {
            return "Expected friendly 429 notice in message, got: " . $ga429->get_error_message();
        }

        delete_transient('laca_ga_429_limit');
        remove_filter('pre_http_request', $mock429, 10);

        // --- 4. Data Collection State tests ---
        delete_option('laca_google_data_available_gsc');
        delete_option('laca_google_data_available_ga');

        // Test collecting notice when no data ever
        $mockZeroData = function ($preempt, $parsedArgs, $url) {
            if (str_contains($url, 'searchAnalytics/query')) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode(['rows' => []]),
                    'headers'  => ['content-type' => 'application/json'],
                ];
            }
            return $preempt;
        };
        add_filter('pre_http_request', $mockZeroData, 10, 3);

        delete_transient('laca_gsc_perf_' . md5('https://lixroastery.local/_28d'));
        $gscEmpty = GoogleDashboardService::getGscData('28d', true);

        if (empty($gscEmpty['isCollecting']) || empty($gscEmpty['collectingNotice'])) {
            return "Expected isCollecting=true and collectingNotice when site has never had data";
        }

        // Test zero data notice when site previously had data
        update_option('laca_google_data_available_gsc', 1);
        delete_transient('laca_gsc_perf_' . md5('https://lixroastery.local/_28d'));
        $gscZero = GoogleDashboardService::getGscData('28d', true);

        if (!empty($gscZero['isCollecting']) || empty($gscZero['isZeroData']) || empty($gscZero['zeroDataNotice'])) {
            return "Expected isZeroData=true and zeroDataNotice when data was previously available but current range is 0";
        }

        return true;
    } finally {
        remove_filter('pre_option_' . GscOAuth::OPTION_KEY, $mockOAuthFilter);
        remove_filter('pre_http_request', $mockHttp, 10);
        if (isset($mock429)) {
            remove_filter('pre_http_request', $mock429, 10);
        }
        if (isset($mockZeroData)) {
            remove_filter('pre_http_request', $mockZeroData, 10);
        }
        delete_transient('laca_ga_429_limit');
        delete_transient('laca_gsc_429_limit');
        delete_transient('laca_ga_rep_' . md5('438291045_28d'));
        delete_transient('laca_ga_batch_' . md5('438291045_28d'));
        delete_transient('laca_gsc_perf_' . md5('sc-domain:lixroastery.local_28d'));
        delete_transient('laca_gsc_perf_' . md5('https://lixroastery.local/_28d'));
    }
}, $pass, $fail);

echo "SUMMARY pass={$pass} fail={$fail}\n";
exit($fail === 0 ? 0 : 1);






