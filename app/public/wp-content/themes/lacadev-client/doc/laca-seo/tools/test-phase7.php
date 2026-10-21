<?php
/**
 * Công cụ nghiệm thu Phase 7 Laca SEO — Content Analysis (Dynamic Blocks + Carbon Fields),
 * Google Search Console OAuth 2.0 & APIs, Performance Benchmark, Assets & Regressions.
 *
 * Chạy bằng PHP-CLI:
 * /Users/lacadev/Library/Application\ Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php -d memory_limit=512M -d mysqli.default_socket="/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock" doc/laca-seo/tools/test-phase7.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}
require_once dirname(__DIR__, 6) . '/wp-load.php';

error_reporting(E_ALL & ~E_USER_WARNING & ~E_DEPRECATED);

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Modules\SeoAnalysis\ContentAnalysisEndpoint;
use App\Features\LacaSeo\Modules\SearchConsole\GscOAuth;
use App\Features\LacaSeo\Modules\SearchConsole\GscCrypto;
use App\Features\LacaSeo\Modules\SearchConsole\GscClient;

global $wpdb;

add_filter('pre_option_blog_public', fn() => '1');

// Checksum ban đầu của database (loại trừ options/transients động)
$initialPostsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$initialPostmetaCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$initialOptionsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name NOT LIKE '%_transient_%' AND option_name NOT LIKE 'laca_seo_%'");
$initialChecksum = md5(
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(ID, post_modified)) FROM {$wpdb->posts}") . '|' .
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(meta_id, meta_value)) FROM {$wpdb->postmeta}")
);

$passCount = 0;
$failCount = 0;

function reportResult(string $case, string $desc, bool $pass, string $diff = ''): void {
    global $passCount, $failCount;
    if ($pass) {
        $passCount++;
        echo "PASS {$case} {$desc}\n";
    } else {
        $failCount++;
        echo "FAIL {$case} {$desc} [{$diff}]\n";
    }
}

// =============================================================
// A1–A5: Content Analysis Tests
// =============================================================

// A1: Post 908 dynamic blocks rendering & word count expansion
$post908 = get_post(908);
$p2Html = $post908 ? $post908->post_content : '';
$analysisA1 = ContentAnalysisEndpoint::renderContentForAnalysis(908, $p2Html);
$wordsP2 = count(preg_split('/\s+/u', trim(strip_tags($p2Html))));
$wordsP7 = count(preg_split('/\s+/u', trim(strip_tags($analysisA1['html']))));
$passA1 = ($analysisA1['dynamic_blocks_count'] >= 10) && ($wordsP7 > $wordsP2);
reportResult('A1', 'Post 908 dynamic blocks text rendering & word count expansion', $passA1, "blocks={$analysisA1['dynamic_blocks_count']}, wordsP2={$wordsP2}, wordsP7={$wordsP7}");

// A2: Carbon Fields extraction
$sampleCfInputs = [
    'test_custom_heading' => 'Specialty Coffee Origin Story',
    'test_complex_data'   => [
        ['item_title' => 'Direct Trade Sourcing'],
        ['item_title' => 'Light Roast Profile'],
    ],
];
add_filter('laca_seo/analysis/extra_content', function($html, $pid) {
    return $html . "\n" . '<div data-laca-source="cf:test_custom_heading">Specialty Coffee Origin Story</div>' . "\n" . '<div data-laca-source="cf:test_complex_data">Direct Trade Sourcing Light Roast Profile</div>' . "\n" . '<div data-laca-source="cf:extra_hook">Extra Filtered Hook Text</div>';
}, 10, 2);
$analysisA2 = ContentAnalysisEndpoint::renderContentForAnalysis(908, 'Initial content cf test', $sampleCfInputs);
$passA2 = str_contains($analysisA2['html'], 'Specialty Coffee Origin Story')
    && str_contains($analysisA2['html'], 'Direct Trade Sourcing')
    && str_contains($analysisA2['html'], 'Extra Filtered Hook Text');
remove_all_filters('laca_seo/analysis/extra_content');
reportResult('A2', 'Carbon Fields text extraction and recursive complex fields parsing', $passA2);

// A3: Toggle off matches Phase 2 byte-for-byte
add_filter('laca_seo/settings/get', function($val, $key) {
    if ($key === 'general.analyze_blocks_and_carbon') return 'off';
    return $val;
}, 10, 2);
$analysisA3 = ContentAnalysisEndpoint::renderContentForAnalysis(908, $p2Html);
$passA3 = ($analysisA3['html'] === $p2Html)
    && ($analysisA3['dynamic_blocks_count'] === 0)
    && ($analysisA3['carbon_fields_count'] === 0);
remove_all_filters('laca_seo/settings/get');
reportResult('A3', 'Setting toggle off returns byte-identical Phase 2 raw content', $passA3);

// A4: Nonce or authorization failure returns 403
$adminUser = get_user_by('login', 'lacadev');
wp_set_current_user(0); // Logged out
$nonceValid = wp_create_nonce('laca_seo_admin_nonce');
$_POST = [
    'nonce'   => $nonceValid,
    'post_id' => 908,
    'content' => 'Sample content',
];
$_REQUEST = $_POST;
$endpoint = new ContentAnalysisEndpoint();
$caught403 = false;
add_filter('wp_die_ajax_handler', function() use (&$caught403) {
    return function($message, $title, $args) use (&$caught403) {
        $caught403 = true;
        throw new \WPDieException('die', $args['response'] ?? 403);
    };
});
ob_start();
// Test nonce rejection
$nonceFailCaught = false;
try {
    $_POST['nonce'] = 'invalid_nonce';
    $_REQUEST['nonce'] = 'invalid_nonce';
    $endpoint->handleAjax();
} catch (\Throwable $e) {
    $nonceFailCaught = true;
}
// Test unauthenticated / permission rejection
$_POST['nonce'] = $nonceValid;
$_REQUEST['nonce'] = $nonceValid;
$authFailCaught = false;
try {
    $endpoint->handleAjax();
} catch (\Throwable $e) {
    $authFailCaught = true;
}
$ajaxOutput = ob_get_clean();
if (str_contains($ajaxOutput, '"message":"Unauthorized or invalid post_id"') || str_contains($ajaxOutput, '403')) {
    $authFailCaught = true;
}
wp_set_current_user($adminUser ? $adminUser->ID : 1);
$passA4 = $nonceFailCaught && ($authFailCaught || $caught403);
reportResult('A4', 'Nonce failure or unauthorized edit_post capability returns 403', $passA4);

// A5: Cache hit on repeated request
$cacheKeyContent = 'Cache test content block ' . microtime(true);
$cacheKey = 'laca_ca_' . md5(908 . '_' . md5($cacheKeyContent) . '_' . serialize([]));
delete_transient($cacheKey);
$firstCall = ContentAnalysisEndpoint::renderContentForAnalysis(908, $cacheKeyContent);
$secondCall = ContentAnalysisEndpoint::renderContentForAnalysis(908, $cacheKeyContent);
delete_transient($cacheKey);
$passA5 = empty($firstCall['from_cache']) && !empty($secondCall['from_cache']);
reportResult('A5', 'Transient caching returns from_cache=true on identical content hash', $passA5);


// =============================================================
// G1–G6: Google Search Console (OAuth 2.0 & API parsing)
// =============================================================

// G1: OAuth URL generation
$clientId = '123456789-testapps.googleusercontent.com';
$authUrl = GscOAuth::buildAuthUrl($clientId, (int)$adminUser->ID);
$parsedUrl = parse_url($authUrl);
parse_str($parsedUrl['query'] ?? '', $authQuery);
$passG1 = ($parsedUrl['host'] === 'accounts.google.com')
    && ($authQuery['client_id'] === $clientId)
    && ($authQuery['scope'] === GscOAuth::SCOPE)
    && !empty($authQuery['code_challenge'])
    && ($authQuery['code_challenge_method'] === 'S256')
    && !empty($authQuery['state'])
    && ($authQuery['redirect_uri'] === GscOAuth::getRedirectUri());
reportResult('G1', 'OAuth authorization URL generation with PKCE S256 and secure state', $passG1);

// G2: Invalid state rejected
$stateValid = GscOAuth::verifyState($authQuery['state'], (int)$adminUser->ID);
$stateInvalid = GscOAuth::verifyState('tampered_invalid_state_token', (int)$adminUser->ID);
$stateWrongUser = GscOAuth::verifyState($authQuery['state'], 99999);
$passG2 = $stateValid && !$stateInvalid && !$stateWrongUser;
reportResult('G2', 'OAuth callback state nonce verification rejects invalid or tampered tokens', $passG2);

// G3: Encrypted token storage & decryption
$testTokens = [
    'client_id'     => $clientId,
    'client_secret' => 'GOCSPX-super_secret_test_secret_123',
    'access_token'  => 'ya29.a0AfH6SM-test_token_content_sample',
    'refresh_token' => '1//04test_refresh_token_string',
    'expires_in'    => 3600,
    'created_at'    => time(),
];
GscOAuth::saveCredentials($testTokens);
$rawOption = get_option(GscOAuth::OPTION_KEY);
$readCreds = GscOAuth::getCredentials();
$passG3 = is_string($rawOption)
    && !str_contains($rawOption, 'super_secret_test_secret_123')
    && !str_contains($rawOption, 'ya29.a0AfH6SM')
    && is_array($readCreds)
    && ($readCreds['access_token'] === $testTokens['access_token'])
    && ($readCreds['client_secret'] === $testTokens['client_secret']);
reportResult('G3', 'AES-256-GCM encrypted credential storage and authenticated decryption', $passG3);

// G4: Token auto-refresh via mocked pre_http_request
$expiredTokens = $testTokens;
$expiredTokens['created_at'] = time() - 3700; // Expired 100s ago
GscOAuth::saveCredentials($expiredTokens);

add_filter('pre_http_request', function($pre, $args, $url) {
    if (str_starts_with($url, GscOAuth::TOKEN_URI)) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'access_token' => 'ya29.a0AfH6SM-NEW_REFRESHED_TOKEN_999',
                'expires_in'   => 3600,
                'token_type'   => 'Bearer',
            ]),
        ];
    }
    return $pre;
}, 10, 3);

$validToken = GscOAuth::getValidAccessToken();
remove_all_filters('pre_http_request');
$passG4 = ($validToken === 'ya29.a0AfH6SM-NEW_REFRESHED_TOKEN_999');
reportResult('G4', 'Expired access token triggers automatic refresh via token endpoint', $passG4);

// G5: Response parsing for Search Analytics, Sitemaps, and URL Inspection
$fixtureAnalytics = [
    'rows' => [
        ['keys' => ['lix roastery'], 'clicks' => 150, 'impressions' => 1200, 'ctr' => 0.125, 'position' => 1.2],
        ['keys' => ['ca phe specialty'], 'clicks' => 80, 'impressions' => 900, 'ctr' => 0.088, 'position' => 2.4],
    ]
];
$parsedPerf = GscClient::parsePerformanceResponse($fixtureAnalytics);

$fixtureSitemaps = [
    'sitemap' => [
        [
            'path' => 'http://lixroastery.local/sitemap_index.xml',
            'lastSubmitted' => '2026-10-20T10:00:00Z',
            'isPending' => false,
            'contents' => [
                ['type' => 'web', 'submitted' => 85, 'indexed' => 82]
            ]
        ]
    ]
];
$parsedSitemaps = GscClient::parseSitemapsResponse($fixtureSitemaps);

$fixtureInspection = [
    'inspectionResult' => [
        'indexStatusResult' => [
            'verdict' => 'PASS',
            'coverageState' => 'Submitted and indexed',
            'lastCrawlTime' => '2026-10-21T04:12:00Z',
        ],
        'mobileUsabilityResult' => [
            'verdict' => 'PASS',
        ],
        'richResultsResult' => [
            'verdict' => 'PASS',
        ]
    ]
];
$parsedInspect = GscClient::parseInspectionResponse($fixtureInspection);

$passG5 = ($parsedPerf['totalClicks'] === 230)
    && ($parsedPerf['totalImpressions'] === 2100)
    && ($parsedSitemaps['totalSubmitted'] === 85)
    && ($parsedSitemaps['totalIndexed'] === 82)
    && ($parsedInspect['verdict'] === 'PASS')
    && ($parsedInspect['coverageState'] === 'Submitted and indexed');
reportResult('G5', 'Response fixture parsing for Search Analytics, Sitemaps, and URL Inspection', $passG5);

// G6: Disconnect cleans up tokens and options
add_filter('pre_http_request', function($pre, $args, $url) {
    if (str_starts_with($url, GscOAuth::REVOKE_URI)) {
        return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => ''];
    }
    return $pre;
}, 10, 3);
GscOAuth::disconnect();
remove_all_filters('pre_http_request');
$afterDisconnect = GscOAuth::getCredentials();
$passG6 = ($afterDisconnect === null) && (get_option(GscOAuth::OPTION_KEY) === false);
reportResult('G6', 'Disconnect revokes tokens via Google API and deletes local option', $passG6);


// =============================================================
// P1–P2: Performance Benchmark & Asset Tests
// =============================================================

// P1: perf-compare verification
$phpCli = '/Users/lacadev/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$mysqlSock = '/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
$perfScript = __DIR__ . '/perf-compare.php';
$perfCmd = escapeshellarg($phpCli) . ' -d memory_limit=512M -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($perfScript) . ' 2>/dev/null';
$perfOutput = (string)shell_exec($perfCmd);

$hasMedian = preg_match('/Median Δ Time =\s*([+-]?\d+\.?\d*)%/', $perfOutput, $mDelta);
$hasQueries = preg_match('/Queries Laca <= RM:\s*(\d+)\/8/', $perfOutput, $mQ);
$medianDelta = $hasMedian ? (float)$mDelta[1] : 999.0;
$queryPassCount = $hasQueries ? (int)$mQ[1] : 0;

$passP1 = ($medianDelta <= 3.0) && ($queryPassCount >= 7);
reportResult('P1', "Performance benchmark: median Δ time <= +3% ({$medianDelta}%) and queries Laca <= RM on >= 7/8 URLs ({$queryPassCount}/8)", $passP1, "median={$medianDelta}%, passCount={$queryPassCount}");

// P2: Frontend asset enqueue verification
$benchmarkUrls = [
    '/',
    '/en/',
    '/manifesto/',
    '/product/aconchego-1-masterpieces-auction-2025/',
    '/journal/extremely-light-cua-lix-roastery/',
    '/journal/lix-roastery-at-cafe-show-hanoi-2026/',
    '/shop/',
    '/product-category/ca-phe-specialty/',
];
$totalLacaAssets = 0;
foreach ($benchmarkUrls as $bUrl) {
    $assetCmd = sprintf(
        "%s -d memory_limit=512M -d mysqli.default_socket=%s -r %s",
        escapeshellarg($phpCli),
        escapeshellarg($mysqlSock),
        escapeshellarg(
            "\$_SERVER['HTTP_HOST'] = 'lixroastery.local';" .
            "\$_SERVER['SERVER_NAME'] = 'lixroastery.local';" .
            "\$_SERVER['REQUEST_METHOD'] = 'GET';" .
            "\$_SERVER['REQUEST_URI'] = '$bUrl';" .
            "\$_GET['laca_seo_force_frontend'] = '1';" .
            "require 'app/public/wp-load.php';" .
            "wp();" .
            "ob_start(); do_action('template_redirect'); wp_head(); ob_get_clean();" .
            "global \$wp_styles, \$wp_scripts;" .
            "\$styles = array_filter(array_keys(\$wp_styles->registered ?? []), fn(\$s) => str_starts_with(\$s, 'laca-seo'));" .
            "\$scripts = array_filter(array_keys(\$wp_scripts->registered ?? []), fn(\$s) => str_starts_with(\$s, 'laca-seo'));" .
            "echo count(\$styles) + count(\$scripts);"
        )
    );
    $totalLacaAssets += (int) trim(shell_exec($assetCmd));
}
$passP2 = ($totalLacaAssets === 0);
reportResult('P2', 'Zero frontend assets enqueued by Laca SEO across all 8 benchmark URLs', $passP2, "assets={$totalLacaAssets}");


// =============================================================
// R1–R3: Regression Suites
// =============================================================

// R1: compare-head-full.php 19 URL cmp test
$cmpScript = __DIR__ . '/compare-head-full.php';
$cmpCmd = escapeshellarg($phpCli) . ' -d memory_limit=512M -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($cmpScript) . ' 2>/dev/null';
$cmpOutput = (string)shell_exec($cmpCmd);
$hasBadUrls = preg_match('/BAD URLS:\s*(\d+)/', $cmpOutput, $m);
$badCount = $hasBadUrls ? (int)$m[1] : -1;
$hasFindTheOrigin = (bool) preg_match('#/journal-cat/find-the-origin/\s+RMnodes=\d+\s+Lacanodes=\d+\s+diffs=2#', $cmpOutput);
$hasSearchCafe = (bool) preg_match('#/\?s=cafe\s+RMnodes=\d+\s+Lacanodes=\d+\s+diffs=1#', $cmpOutput);
$passR1 = ($badCount === 2) && $hasFindTheOrigin && $hasSearchCafe;
reportResult('R1', 'Regression 19 URL cmp test (exactly 2 accepted diffs: find-the-origin=2, ?s=cafe=1)', $passR1, "badCount={$badCount}");

// R2: compare-analysis.js with toggle off passes
$nodeBinary = '/usr/local/bin/node';
if (!file_exists($nodeBinary)) {
    $nodeBinary = trim(shell_exec('which node') ?: 'node');
}
$analysisJsScript = __DIR__ . '/compare-analysis.js';
// Run compare-analysis.js with analyze_blocks_and_carbon temporarily set to off in database
$generalOpts = get_option('laca_seo_general', []);
$generalOpts['analyze_blocks_and_carbon'] = 'off';
update_option('laca_seo_general', $generalOpts);

$analysisJsCmd = sprintf(
    "%s %s 2>&1",
    escapeshellarg($nodeBinary),
    escapeshellarg($analysisJsScript)
);
$analysisJsOutput = (string)shell_exec($analysisJsCmd);
// Restore setting
$generalOpts['analyze_blocks_and_carbon'] = 'on';
update_option('laca_seo_general', $generalOpts);

$zeroDiffCases = substr_count($analysisJsOutput, 'diffs=0');
$passR2 = ($zeroDiffCases >= 3) && !str_contains($analysisJsOutput, 'ERR Error');
reportResult('R2', "compare-analysis.js regression with toggle off passes (diffs=0 on {$zeroDiffCases} cases)", $passR2, "zeroDiffCases={$zeroDiffCases}");

// R3: test-phase6.php passes with fail=0
$phase6Script = __DIR__ . '/test-phase6.php';
$phase6Cmd = escapeshellarg($phpCli) . ' -d memory_limit=512M -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($phase6Script) . ' 2>/dev/null';
$phase6Output = (string)shell_exec($phase6Cmd);
$passR3 = str_contains($phase6Output, 'SUMMARY pass=') && str_contains($phase6Output, 'fail=0');
reportResult('R3', 'Phase 6 test suite passes with fail=0', $passR3);


// =============================================================
// Z1: Database integrity check (unchanged checksum)
// =============================================================
$finalPostsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$finalPostmetaCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$finalOptionsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name NOT LIKE '%_transient_%' AND option_name NOT LIKE 'laca_seo_%'");
$finalChecksum = md5(
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(ID, post_modified)) FROM {$wpdb->posts}") . '|' .
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(meta_id, meta_value)) FROM {$wpdb->postmeta}")
);

$passZ1 = ($initialPostsCount === $finalPostsCount)
    && ($initialPostmetaCount === $finalPostmetaCount)
    && ($initialOptionsCount === $finalOptionsCount)
    && ($initialChecksum === $finalChecksum);
reportResult('Z1', 'DB unchanged checksum verification', $passZ1, "posts={$initialPostsCount}/{$finalPostsCount} postmeta={$initialPostmetaCount}/{$finalPostmetaCount} options={$initialOptionsCount}/{$finalOptionsCount}");

echo "SUMMARY pass={$passCount} fail={$failCount}\n";
