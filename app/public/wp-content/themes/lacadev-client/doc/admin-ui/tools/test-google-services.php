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
use App\Features\LacaSeo\Modules\GoogleAnalytics\GaClient;
use App\Features\LacaSeo\Frontend\Tracking;
use App\Features\LacaSeo\Core\Settings;

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

echo "SUMMARY pass={$pass} fail={$fail}\n";
exit($fail === 0 ? 0 : 1);
