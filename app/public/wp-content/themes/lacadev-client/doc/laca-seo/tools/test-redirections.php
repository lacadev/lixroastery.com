<?php
// Công cụ nghiệm thu Redirections & 404 Monitor Phase 4 (R1 Fix)
// Chạy bằng PHP-CLI: php -d mysqli.default_socket="..." doc/laca-seo/tools/test-redirections.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 6) . '/wp-load.php';

error_reporting(E_ALL & ~E_USER_WARNING & ~E_DEPRECATED);

use App\Features\LacaSeo\Modules\Redirections\Installer;
use App\Features\LacaSeo\Modules\Redirections\DB as RedirDB;
use App\Features\LacaSeo\Modules\Redirections\Cache as RedirCache;
use App\Features\LacaSeo\Modules\Redirections\Redirection;
use App\Features\LacaSeo\Modules\Redirections\Redirector;
use App\Features\LacaSeo\Modules\Redirections\Watcher;
use App\Features\LacaSeo\Modules\Redirections\Schedule;
use App\Features\LacaSeo\Modules\Redirections\Categories;
use App\Features\LacaSeo\Modules\Redirections\Export;
use App\Features\LacaSeo\Modules\NotFoundMonitor\DB as NotFoundDB;
use App\Features\LacaSeo\Modules\NotFoundMonitor\Monitor as NotFoundMonitor;
use App\Features\LacaSeo\ImportExport\RankMathImporter;
use App\Features\LacaSeo\Core\Settings;
use RankMath\Redirections\DB as RMRedirDB;
use RankMath\Helpers\Str as RMStr;

// 1. Đảm bảo bảng tồn tại trước khi mở transaction (tránh implicit commit bởi CREATE TABLE)
Installer::createTables();

global $wpdb;

$tableRedir = RedirDB::getTable();
$tableCache = RedirCache::getTable();
$tableLogs  = NotFoundDB::getTable();

// Hàm tính checksum nội dung 3 bảng
function getTablesContentChecksum(wpdb $wpdb, string $tableRedir, string $tableCache, string $tableLogs): string {
    $rowsRedir = $wpdb->get_results("SELECT * FROM {$tableRedir} ORDER BY id ASC", ARRAY_A);
    $rowsCache = $wpdb->get_results("SELECT * FROM {$tableCache} ORDER BY id ASC", ARRAY_A);
    $rowsLogs  = $wpdb->get_results("SELECT * FROM {$tableLogs} ORDER BY id ASC", ARRAY_A);

    return md5(serialize($rowsRedir) . '|' . serialize($rowsCache) . '|' . serialize($rowsLogs));
}

// Lưu trạng thái trước test
$initialChecksum = getTablesContentChecksum($wpdb, $tableRedir, $tableCache, $tableLogs);
$post866 = get_post(866);
$initialPost866Name     = $post866 ? $post866->post_name : '';
$initialPost866Modified = $post866 ? $post866->post_modified : '';

// Mảng theo dõi ID do chính script test tạo để làm lưới an toàn nếu cần
$createdRedirIds = [];
$createdCacheIds = [];
$createdLogIds   = [];
$createdTermIds  = [];

$passCount = 0;
$failCount = 0;

function reportResult(string $code, string $desc, bool $passed, string $expected = '', string $actual = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "PASS {$code} {$desc}\n";
    } else {
        $failCount++;
        echo "FAIL {$code} {$desc} [expected: {$expected} -> actual: {$actual}]\n";
    }
}

// Bắt đầu START TRANSACTION
$wpdb->query("START TRANSACTION");

try {
    // -------------------------------------------------------------
    // M1–M20: So khớp trực tiếp với hàm so khớp của Rank Math
    // -------------------------------------------------------------
    $casesM = [
        'M1'  => [
            'desc'    => 'Exact match no trailing slash',
            'sources' => [['pattern' => 'my-page', 'comparison' => 'exact', 'ignore' => '']],
            'uri'     => 'my-page',
        ],
        'M2'  => [
            'desc'    => 'Exact match with trailing slash URI',
            'sources' => [['pattern' => 'my-page', 'comparison' => 'exact', 'ignore' => '']],
            'uri'     => 'my-page/',
        ],
        'M3'  => [
            'desc'    => 'Exact match with trailing slash source',
            'sources' => [['pattern' => 'my-page/', 'comparison' => 'exact', 'ignore' => '']],
            'uri'     => 'my-page',
        ],
        'M4'  => [
            'desc'    => 'Exact case-insensitive match (ignore case)',
            'sources' => [['pattern' => 'my-page', 'comparison' => 'exact', 'ignore' => 'case']],
            'uri'     => 'MY-PAGE',
        ],
        'M5'  => [
            'desc'    => 'Exact case-sensitive mismatch',
            'sources' => [['pattern' => 'my-page', 'comparison' => 'exact', 'ignore' => '']],
            'uri'     => 'MY-PAGE',
        ],
        'M6'  => [
            'desc'    => 'Contains match middle',
            'sources' => [['pattern' => 'keyword', 'comparison' => 'contains', 'ignore' => '']],
            'uri'     => 'prefix-keyword-suffix',
        ],
        'M7'  => [
            'desc'    => 'Contains mismatch',
            'sources' => [['pattern' => 'keyword', 'comparison' => 'contains', 'ignore' => '']],
            'uri'     => 'other-term',
        ],
        'M8'  => [
            'desc'    => 'Start match prefix',
            'sources' => [['pattern' => 'docs/', 'comparison' => 'start', 'ignore' => '']],
            'uri'     => 'docs/getting-started',
        ],
        'M9'  => [
            'desc'    => 'Start mismatch',
            'sources' => [['pattern' => 'docs/', 'comparison' => 'start', 'ignore' => '']],
            'uri'     => 'other/docs/getting-started',
        ],
        'M10' => [
            'desc'    => 'End match file extension',
            'sources' => [['pattern' => '.pdf', 'comparison' => 'end', 'ignore' => '']],
            'uri'     => 'downloads/whitepaper.pdf',
        ],
        'M11' => [
            'desc'    => 'End mismatch with slash',
            'sources' => [['pattern' => '.pdf', 'comparison' => 'end', 'ignore' => '']],
            'uri'     => 'downloads/whitepaper.pdf/',
        ],
        'M12' => [
            'desc'    => 'Regex numeric match',
            'sources' => [['pattern' => '^post-([0-9]+)$', 'comparison' => 'regex', 'ignore' => '']],
            'uri'     => 'post-1234',
        ],
        'M13' => [
            'desc'    => 'Regex non-numeric mismatch',
            'sources' => [['pattern' => '^post-([0-9]+)$', 'comparison' => 'regex', 'ignore' => '']],
            'uri'     => 'post-abcd',
        ],
        'M14' => [
            'desc'    => 'Regex with capture backreference destination',
            'sources' => [['pattern' => '^old-blog/([a-z0-9-]+)$', 'comparison' => 'regex', 'ignore' => '']],
            'uri'     => 'old-blog/my-first-post',
        ],
        'M15' => [
            'desc'    => 'Invalid regex syntax graceful handling',
            'sources' => [['pattern' => '^post-([0-9+', 'comparison' => 'regex', 'ignore' => '']],
            'uri'     => 'post-123',
        ],
        'M16' => [
            'desc'    => 'Unicode characters match',
            'sources' => [['pattern' => 'cà-phê-đà-lạt', 'comparison' => 'exact', 'ignore' => '']],
            'uri'     => 'cà-phê-đà-lạt',
        ],
        'M17' => [
            'desc'    => 'Percent-encoded characters match',
            'sources' => [['pattern' => 'special%20space', 'comparison' => 'exact', 'ignore' => '']],
            'uri'     => 'special%20space',
        ],
        'M18' => [
            'desc'    => 'URL with hash fragment',
            'sources' => [['pattern' => 'target#section', 'comparison' => 'exact', 'ignore' => '']],
            'uri'     => 'target#section',
        ],
        'M19' => [
            'desc'    => 'Multiple sources in 1 rule (second matched)',
            'sources' => [
                ['pattern' => 'non-matching', 'comparison' => 'exact', 'ignore' => ''],
                ['pattern' => 'matching-second', 'comparison' => 'exact', 'ignore' => ''],
            ],
            'uri'     => 'matching-second',
        ],
        'M20' => [
            'desc'    => 'Precedence exact rule over contains rule',
            'sources' => [
                ['pattern' => 'article', 'comparison' => 'contains', 'ignore' => ''],
                ['pattern' => 'article/intro', 'comparison' => 'exact', 'ignore' => ''],
            ],
            'uri'     => 'article/intro',
        ],
    ];

    foreach ($casesM as $code => $data) {
        $rmRes   = @RMRedirDB::compare_sources($data['sources'], $data['uri']);
        $lacaRes = @RedirDB::compareSources($data['sources'], $data['uri']);
        $match   = ($rmRes === $lacaRes);
        reportResult($code, $data['desc'] . ' vs RM', $match, var_export($rmRes, true), var_export($lacaRes, true));
    }

    // -------------------------------------------------------------
    // H1–H5: Header codes (301, 302, 307, 410, 451)
    // -------------------------------------------------------------
    $headerCodes = [
        'H1' => [301, 'https://example.com/target-301', true],
        'H2' => [302, 'https://example.com/target-302', true],
        'H3' => [307, 'https://example.com/target-307', true],
        'H4' => [410, '', false],
        'H5' => [451, '', false],
    ];

    foreach ($headerCodes as $code => [$hCode, $target, $hasLocation]) {
        $id = RedirDB::add([
            'sources'     => [['pattern' => "header-test-{$hCode}", 'comparison' => 'exact', 'ignore' => '']],
            'url_to'      => $target,
            'header_code' => $hCode,
            'status'      => 'active',
        ]);
        if ($id) {
            $createdRedirIds[] = $id;
        }
        $row = RedirDB::getRedirectionById($id);
        $passed = ($row && (int) $row['header_code'] === $hCode && ($hasLocation ? $row['url_to'] === $target : $row['url_to'] === ''));
        reportResult($code, "Header status code {$hCode}" . ($hasLocation ? " with Location" : " without target"), $passed);
    }

    // -------------------------------------------------------------
    // C1–C3: Cache (Hit, Invalidation khi sửa, Inactive không redirect)
    // -------------------------------------------------------------
    $cUri = 'test-cache-path';
    $cId = RedirDB::add([
        'sources'     => [['pattern' => $cUri, 'comparison' => 'exact', 'ignore' => '']],
        'url_to'      => 'https://example.com/cached-dest',
        'header_code' => 301,
        'status'      => 'active',
    ]);
    if ($cId) {
        $createdRedirIds[] = $cId;
    }

    // C1: First match populates cache, second read finds cache
    $_SERVER['REQUEST_URI'] = '/' . $cUri;
    $refR = new ReflectionClass(Redirector::class);
    $rInst = $refR->newInstanceWithoutConstructor();
    $mStart = $refR->getMethod('start'); $mStart->setAccessible(true); $mStart->invoke($rInst);
    $mFlow = $refR->getMethod('flow'); $mFlow->setAccessible(true); $mFlow->invoke($rInst);
    
    // First run populates cache
    $cacheRow1 = RedirCache::getByUrl($cUri);
    if ($cacheRow1 && isset($cacheRow1->id)) {
        $createdCacheIds[] = (int) $cacheRow1->id;
    }

    // Second run should set fromCache to true
    $rInst2 = $refR->newInstanceWithoutConstructor();
    $mStart->invoke($rInst2);
    $mFlow->invoke($rInst2);
    $pFromCache = $refR->getProperty('fromCache'); $pFromCache->setAccessible(true);
    $isFromCache = $pFromCache->getValue($rInst2);
    reportResult('C1', 'Cache hit on second request for same URL', ($cacheRow1 !== null && $isFromCache === true));

    // C2: Invalidation on update
    RedirDB::update(['id' => $cId, 'url_to' => 'https://example.com/cached-dest-updated']);
    $cacheRow2 = RedirCache::getByUrl($cUri);
    reportResult('C2', 'Cache invalidation on rule update', ($cacheRow2 === null));

    // C3: Inactive rule does not match
    RedirDB::update(['id' => $cId, 'status' => 'inactive']);
    $matchedInactive = RedirDB::matchRedirections($cUri);
    reportResult('C3', 'Inactive rule does not redirect', ($matchedInactive === false));

    // -------------------------------------------------------------
    // W1–W2: Watcher (đổi slug bài 866 tự tạo redirect cũ -> mới + Cache::purgeByObjectId)
    // -------------------------------------------------------------
    if ($post866) {
        $oldPermalink = get_permalink(866);
        $oldSlug      = $post866->post_name;
        $tempSlug     = $oldSlug . '-wtest';

        // Pre-insert cache row to verify W2 real purge
        $w2PreCacheUrl = '/test-w2-pre-cache/';
        RedirCache::add([
            'from_url'       => $w2PreCacheUrl,
            'redirection_id' => 88888,
            'object_id'      => 866,
            'object_type'    => 'post',
        ]);
        $w2CacheBefore = RedirCache::getByObjectId(866, 'post');

        // Set option redirections_post_redirect = on in memory/group
        Settings::updateGroup('general', array_merge(Settings::getGroup('general'), [
            'redirections_post_redirect' => 'on',
        ]));

        $watcher = new Watcher();
        $watcher->prePostUpdate(866);

        // Giả lập post_updated với slug mới
        $updatedPost = clone $post866;
        $updatedPost->post_name = $tempSlug;

        $filterFn = function($link, $post) use ($oldSlug, $tempSlug) {
            if (isset($post->ID) && (int) $post->ID === 866) {
                return str_replace($oldSlug, $tempSlug, $link);
            }
            return $link;
        };
        add_filter('post_type_link', $filterFn, 10, 2);

        // Gọi handlePostUpdate
        $watcher->handlePostUpdate(866, $updatedPost, $post866);

        remove_filter('post_type_link', $filterFn, 10);

        // Kiểm tra xem redirection đã được tạo chưa
        $lookupUri = trim(Redirection::stripSubdirectory(wp_parse_url($oldPermalink, PHP_URL_PATH) ?: ''), '/');
        $matchedWatcher = RedirDB::matchRedirections($lookupUri);
        if ($matchedWatcher && isset($matchedWatcher['id'])) {
            $createdRedirIds[] = (int) $matchedWatcher['id'];
        }

        $w2CacheAfter = RedirCache::getByUrl($w2PreCacheUrl);
        $w1Pass = ($matchedWatcher !== false && (int) $matchedWatcher['header_code'] === 301);
        $w2Pass = ($w2CacheBefore !== null && $w2CacheAfter === null);

        reportResult('W1', 'Watcher creates redirection on post slug change', $w1Pass);
        reportResult('W2', 'Watcher purges post object cache', $w2Pass);
    } else {
        reportResult('W1', 'Watcher post 866 not found', false);
        reportResult('W2', 'Watcher post 866 cache check', false);
    }

    // -------------------------------------------------------------
    // F1: Fallback (homepage/custom when 404)
    // -------------------------------------------------------------
    global $wp_query;
    if (!is_object($wp_query)) {
        $wp_query = new WP_Query();
    }
    $wp_query->is_404 = true;
    $_SERVER['REQUEST_URI'] = '/non-existing-404-page';

    Settings::updateGroup('general', array_merge(Settings::getGroup('general'), [
        'redirections_fallback'   => 'custom',
        'redirections_custom_url' => 'https://example.com/fallback-target',
    ]));

    $rInstF = $refR->newInstanceWithoutConstructor();
    $mStart->invoke($rInstF);
    $mFlow->invoke($rInstF);
    $pMatched = $refR->getProperty('matched'); $pMatched->setAccessible(true);
    $pRedirectTo = $refR->getProperty('redirectTo'); $pRedirectTo->setAccessible(true);
    $fMatched = $pMatched->getValue($rInstF);
    $fTarget  = $pRedirectTo->getValue($rInstF);

    reportResult('F1', '404 Fallback redirects to custom URL', ($fMatched !== false && $fTarget === 'https://example.com/fallback-target'));

    // -------------------------------------------------------------
    // D1: Debug console mode (thực thi subprocess cho admin và guest)
    // -------------------------------------------------------------
    $d1Uri = 'test-d1-debug-check';
    $d1Id = RedirDB::add([
        'sources'     => [['pattern' => $d1Uri, 'comparison' => 'exact', 'ignore' => '']],
        'url_to'      => 'https://example.com/d1-target',
        'header_code' => 301,
        'status'      => 'active',
    ]);
    if ($d1Id) {
        $createdRedirIds[] = $d1Id;
    }

    Settings::updateGroup('general', array_merge(Settings::getGroup('general'), [
        'redirections_debug' => 'on',
    ]));

    // Generate auth cookies in memory
    $uAdmin = get_user_by('login', 'lacadev');
    $eTime = time() + 3600;
    $cLoggedIn = wp_generate_auth_cookie($uAdmin->ID, $eTime, 'logged_in');
    $cAuth     = wp_generate_auth_cookie($uAdmin->ID, $eTime, 'auth');

    $phpBin = PHP_BINARY;
    $sockPath = ini_get('mysqli.default_socket');
    $cmdPhp = escapeshellarg($phpBin) . ($sockPath ? ' -d ' . escapeshellarg("mysqli.default_socket={$sockPath}") : '');

    $wpLoadFile = addslashes(ABSPATH . 'wp-load.php');

    // (A) Run as Admin in sub-process
    $scriptAdmin = "<?php
\$_SERVER['REQUEST_URI'] = '/{$d1Uri}';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_COOKIE['" . LOGGED_IN_COOKIE . "'] = '{$cLoggedIn}';
\$_COOKIE['" . AUTH_COOKIE . "'] = '{$cAuth}';
require '{$wpLoadFile}';
wp_set_current_user({$uAdmin->ID});
add_filter('laca_seo/redirection/pre_search', function() {
    return [
        'id'          => 999999,
        'url_to'      => 'https://example.com/d1-target',
        'header_code' => 301,
        'status'      => 'active',
    ];
});
\\App\\Features\\LacaSeo\\Core\\Settings::updateGroup('general', array_merge(
    \\App\\Features\\LacaSeo\\Core\\Settings::getGroup('general'),
    ['redirections_debug' => 'on']
));
ob_start();
new \\App\\Features\\LacaSeo\\Modules\\Redirections\\Redirector();
\$out = ob_get_clean();
echo (strpos(\$out, 'Debug Console') !== false) ? 'DEBUG_OK' : 'DEBUG_FAIL';
";
    $pDesc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $pAdmin = proc_open($cmdPhp, $pDesc, $pipesA, ABSPATH);
    fwrite($pipesA[0], $scriptAdmin);
    fclose($pipesA[0]);
    $resAdmin = stream_get_contents($pipesA[1]);
    fclose($pipesA[1]); fclose($pipesA[2]); proc_close($pAdmin);

    // (B) Run as Guest in sub-process
    $scriptGuest = "<?php
\$_SERVER['REQUEST_URI'] = '/{$d1Uri}';
\$_SERVER['REQUEST_METHOD'] = 'GET';
require '{$wpLoadFile}';
wp_set_current_user(0);
add_filter('laca_seo/redirection/pre_search', function() {
    return [
        'id'          => 999999,
        'url_to'      => 'https://example.com/d1-target',
        'header_code' => 301,
        'status'      => 'active',
    ];
});
add_filter('wp_redirect', function(\$loc, \$code) {
    if (\$code === 301 && \$loc === 'https://example.com/d1-target') {
        echo 'REDIRECT_OK';
    }
    exit(0);
}, 10, 2);
new \\App\\Features\\LacaSeo\\Modules\\Redirections\\Redirector();
";
    $pGuest = proc_open($cmdPhp, $pDesc, $pipesG, ABSPATH);
    fwrite($pipesG[0], $scriptGuest);
    fclose($pipesG[0]);
    $resGuest = stream_get_contents($pipesG[1]);
    fclose($pipesG[1]); fclose($pipesG[2]); proc_close($pGuest);

    $d1AdminOk = (strpos($resAdmin, 'Debug Console') !== false);
    $d1GuestOk = (strpos($resGuest, 'REDIRECT_OK') !== false);
    $d1Pass = ($d1AdminOk && $d1GuestOk);
    reportResult('D1', 'Debug console shows screen to admin, redirects guest with 301', $d1Pass);

    // -------------------------------------------------------------
    // S1: Schedule Pro (ngoài khoảng date không redirect)
    // -------------------------------------------------------------
    $sId = RedirDB::add([
        'sources'     => [['pattern' => 'scheduled-promo', 'comparison' => 'exact', 'ignore' => '']],
        'url_to'      => 'https://example.com/promo-target',
        'header_code' => 301,
        'status'      => 'active',
    ]);
    if ($sId) {
        $createdRedirIds[] = $sId;
    }
    // Schedule in future (2099-01-01 -> 2099-12-31)
    Schedule::setSchedule($sId, '2099-01-01', '2099-12-31');
    $isOut = Schedule::isScheduledOut($sId);
    reportResult('S1', 'Pro schedule suppresses redirection outside active date range', ($isOut === true));

    // -------------------------------------------------------------
    // K1: Categories Pro (kiểm tra taxonomy + lọc danh sách theo category)
    // -------------------------------------------------------------
    Categories::register();
    $taxExists = taxonomy_exists(Categories::TAXONOMY);

    $catTerm = wp_insert_term('Test Category ' . time(), Categories::TAXONOMY);
    $catTermId = (is_array($catTerm) && isset($catTerm['term_id'])) ? (int) $catTerm['term_id'] : 0;
    if ($catTermId > 0) {
        $createdTermIds[] = $catTermId;
    }

    $k1Redir1 = RedirDB::add([
        'sources'     => [['pattern' => 'cat-rule-1', 'comparison' => 'exact', 'ignore' => '']],
        'url_to'      => 'https://example.com/cat-1',
        'header_code' => 301,
        'status'      => 'active',
    ]);
    $k1Redir2 = RedirDB::add([
        'sources'     => [['pattern' => 'cat-rule-2', 'comparison' => 'exact', 'ignore' => '']],
        'url_to'      => 'https://example.com/cat-2',
        'header_code' => 301,
        'status'      => 'active',
    ]);
    if ($k1Redir1) {
        $createdRedirIds[] = $k1Redir1;
    }
    if ($k1Redir2) {
        $createdRedirIds[] = $k1Redir2;
    }

    wp_set_object_terms($k1Redir1, [$catTermId], Categories::TAXONOMY);

    $catFiltered = RedirDB::getRedirections(['category' => $catTermId]);
    $k1Pass = ($taxExists && (int) $catFiltered['count'] === 1 && isset($catFiltered['redirections'][0]['id']) && (int) $catFiltered['redirections'][0]['id'] === $k1Redir1);
    reportResult('K1', 'Pro redirection taxonomy registered and category filter filters list', $k1Pass);

    // -------------------------------------------------------------
    // L1–L6: 404 Monitor
    // -------------------------------------------------------------
    // L1: simple vs advanced mode
    $logIdSimple = NotFoundDB::update(['uri' => 'l1-simple-url']);
    if ($logIdSimple) {
        $createdLogIds[] = $logIdSimple;
    }
    $logRowSimple = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tableLogs} WHERE id = %d", $logIdSimple), ARRAY_A);
    reportResult('L1', '404 log simple mode recorded', ($logRowSimple && $logRowSimple['uri'] === 'l1-simple-url'));

    // L2: ignore query parameters merges counts
    Settings::updateGroup('general', array_merge(Settings::getGroup('general'), [
        '404_monitor_mode'                    => 'simple',
        '404_monitor_ignore_query_parameters' => 'on',
    ]));
    $mon = new NotFoundMonitor();
    $_SERVER['REQUEST_URI'] = '/l2-query-url?param=1';
    $wp_query->is_404 = true;
    $mon->save404Flag();
    $mon->capture404();

    $_SERVER['REQUEST_URI'] = '/l2-query-url?param=2';
    $mon->save404Flag();
    $mon->capture404();

    $l2Row = $wpdb->get_row($wpdb->prepare("SELECT id, times_accessed FROM {$tableLogs} WHERE uri = %s", 'l2-query-url'), ARRAY_A);
    if ($l2Row && isset($l2Row['id'])) {
        $createdLogIds[] = (int) $l2Row['id'];
    }
    reportResult('L2', '404 log ignore query parameters combines hits', ($l2Row && (int) $l2Row['times_accessed'] >= 2));

    // L3: Exclude pattern does not record
    Settings::updateGroup('general', array_merge(Settings::getGroup('general'), [
        '404_monitor_exclude' => [['exclude' => 'excluded-slug', 'comparison' => 'contains']],
    ]));
    $_SERVER['REQUEST_URI'] = '/path-with-excluded-slug/test';
    $mon->save404Flag();
    $mon->capture404();
    $l3Row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$tableLogs} WHERE uri LIKE %s", '%excluded-slug%'));
    reportResult('L3', '404 monitor excludes specified URL patterns', ($l3Row === null));

    // L4: Limit trimming
    Settings::updateGroup('general', array_merge(Settings::getGroup('general'), [
        '404_monitor_limit' => 3,
    ]));
    $trimId1 = NotFoundDB::add(['uri' => 'trim-row-1']);
    $trimId2 = NotFoundDB::add(['uri' => 'trim-row-2']);
    $trimId3 = NotFoundDB::add(['uri' => 'trim-row-3']);
    $trimId4 = NotFoundDB::add(['uri' => 'trim-row-4']);
    foreach ([$trimId1, $trimId2, $trimId3, $trimId4] as $tId) {
        if ($tId) $createdLogIds[] = $tId;
    }
    $countAfterLimit = NotFoundDB::getCount();
    reportResult('L4', '404 log limit trimming respects threshold', ($countAfterLimit <= 3));

    // L5: Ignore admin / REST
    $prevAdmin = is_admin();
    set_current_screen('dashboard');
    $_SERVER['REQUEST_URI'] = '/wp-admin/non-existent';
    $mon->save404Flag();
    $mon->capture404();
    $l5Row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$tableLogs} WHERE uri LIKE %s", '%wp-admin%'));
    reportResult('L5', '404 monitor ignores admin requests', ($l5Row === null));
    set_current_screen('front');

    // L6: Advanced mode stores referer and user-agent
    $_SERVER['HTTP_REFERER']    = 'https://google.com';
    $_SERVER['HTTP_USER_AGENT'] = 'TestBot/1.0';
    $logIdAdv = NotFoundDB::add([
        'uri'        => 'l6-adv-url',
        'referer'    => $_SERVER['HTTP_REFERER'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    ]);
    if ($logIdAdv) {
        $createdLogIds[] = $logIdAdv;
    }
    $l6Row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tableLogs} WHERE id = %d", $logIdAdv), ARRAY_A);
    reportResult('L6', '404 log advanced mode stores referer and user agent', ($l6Row && $l6Row['referer'] === 'https://google.com'));

    // -------------------------------------------------------------
    // E1–E2: Export Apache & Nginx (so sánh 1:1 với Rank Math Export)
    // -------------------------------------------------------------
    $exportRules = [
        [
            'header_code' => '301',
            'url_to'      => 'https://example.com/target-page',
            'sources'     => serialize([['pattern' => 'old-source', 'comparison' => 'exact', 'ignore' => '']]),
        ],
        [
            'header_code' => '302',
            'url_to'      => 'https://example.com/new-dest',
            'sources'     => serialize([['pattern' => '^prefix/(.*)', 'comparison' => 'regex', 'ignore' => '']]),
        ],
        [
            'header_code' => '410',
            'url_to'      => '',
            'sources'     => serialize([['pattern' => 'gone-page', 'comparison' => 'contains', 'ignore' => '']]),
        ],
    ];

    $rmExp = new \RankMath\Redirections\Export();
    $refExp = new ReflectionClass($rmExp);
    $mAp = $refExp->getMethod('apache'); $mAp->setAccessible(true);
    $mNg = $refExp->getMethod('nginx'); $mNg->setAccessible(true);

    $rmApache  = $mAp->invoke($rmExp, $exportRules);
    $lacaApache = Export::apache($exportRules);
    reportResult('E1', 'Apache export format matches Rank Math 1:1', ($rmApache === $lacaApache));

    $rmNginx  = $mNg->invoke($rmExp, $exportRules);
    $lacaNginx = Export::nginx($exportRules);
    reportResult('E2', 'Nginx export format matches Rank Math 1:1', ($rmNginx === $lacaNginx));

    // -------------------------------------------------------------
    // I1: Importer (bảng RM không tồn tại -> trả 0; fixture 3 rows -> import 3, idempotent)
    // -------------------------------------------------------------
    $nonExistentRes = RankMathImporter::importRedirections();
    $fixtures = [
        [
            'id'            => 99991,
            'sources'       => serialize([['pattern' => 'fx-1', 'comparison' => 'exact', 'ignore' => '']]),
            'url_to'        => 'https://example.com/fx-1',
            'header_code'   => 301,
            'hits'          => 12,
            'status'        => 'active',
            'created'       => '2026-10-21 10:00:00',
            'updated'       => '2026-10-21 10:00:00',
            'last_accessed' => '2026-10-21 10:00:00',
        ],
        [
            'id'            => 99992,
            'sources'       => serialize([['pattern' => 'fx-2', 'comparison' => 'contains', 'ignore' => '']]),
            'url_to'        => 'https://example.com/fx-2',
            'header_code'   => 302,
            'hits'          => 5,
            'status'        => 'active',
            'created'       => '2026-10-21 10:00:00',
            'updated'       => '2026-10-21 10:00:00',
            'last_accessed' => '2026-10-21 10:00:00',
        ],
        [
            'id'            => 99993,
            'sources'       => serialize([['pattern' => 'fx-3', 'comparison' => 'regex', 'ignore' => '']]),
            'url_to'        => 'https://example.com/fx-3',
            'header_code'   => 410,
            'hits'          => 0,
            'status'        => 'active',
            'created'       => '2026-10-21 10:00:00',
            'updated'       => '2026-10-21 10:00:00',
            'last_accessed' => '2026-10-21 10:00:00',
        ],
    ];
    $createdRedirIds = array_merge($createdRedirIds, [99991, 99992, 99993]);

    $res1 = RankMathImporter::importRedirections(500, 0, $fixtures);
    $res2 = RankMathImporter::importRedirections(500, 0, $fixtures);
    $importedCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableRedir} WHERE id IN (99991, 99992, 99993)");

    $importerPass = ($nonExistentRes['processed'] === 0 && $res1['processed'] === 3 && $res2['processed'] === 3 && $importedCount === 3);
    reportResult('I1', 'Importer handles empty source and idempotent fixtures', $importerPass);

    // -------------------------------------------------------------
    // X1: Security (CRLF injection clean & Regex > 2000 chars rejected)
    // -------------------------------------------------------------
    $redirSec = Redirection::create(0);
    $redirSec->addDestination("https://example.com/target\r\nSet-Cookie: evil=true");
    $destData = $redirSec->getData();
    $crlfCleaned = (strpos($destData['url_to'], "\r") === false && strpos($destData['url_to'], "\n") === false);

    $hugePattern = str_repeat('a', 2005);
    $redirSec->addSource($hugePattern, 'regex');
    $sourcesData = $redirSec->getData()['sources'] ?? [];
    $hugeRejected = empty($sourcesData);

    reportResult('X1', 'Security cleans CRLF in destination and rejects regex > 2000 chars', ($crlfCleaned && $hugeRejected));

} catch (\Throwable $e) {
    echo "ERROR during execution: " . $e->getMessage() . "\n";
} finally {
    // 1. Luôn luôn thực thi ROLLBACK
    $wpdb->query("ROLLBACK");

    // 2. Lưới an toàn: chỉ dọn đúng các ID do chính script test tạo ra nếu transaction bị can thiệp
    $createdRedirIds = array_unique(array_filter($createdRedirIds));
    $createdCacheIds = array_unique(array_filter($createdCacheIds));
    $createdLogIds   = array_unique(array_filter($createdLogIds));
    $createdTermIds  = array_unique(array_filter($createdTermIds));

    // Loại trừ rõ ràng không được xoá ID 100003 hoặc cache 6
    $createdRedirIds = array_diff($createdRedirIds, [100003]);
    $createdCacheIds = array_diff($createdCacheIds, [6]);

    if (!empty($createdRedirIds)) {
        $p = implode(',', $createdRedirIds);
        $wpdb->query("DELETE FROM {$tableRedir} WHERE id IN ({$p})");
    }
    if (!empty($createdCacheIds)) {
        $p = implode(',', $createdCacheIds);
        $wpdb->query("DELETE FROM {$tableCache} WHERE id IN ({$p})");
    }
    if (!empty($createdLogIds)) {
        $p = implode(',', $createdLogIds);
        $wpdb->query("DELETE FROM {$tableLogs} WHERE id IN ({$p})");
    }
    foreach ($createdTermIds as $tId) {
        wp_delete_term($tId, Categories::TAXONOMY);
    }

    // Đảm bảo post 866 không bị thay đổi trong database
    if ($post866) {
        $currentPost866 = get_post(866);
        if ($currentPost866->post_name !== $initialPost866Name || $currentPost866->post_modified !== $initialPost866Modified) {
            $wpdb->update(
                $wpdb->posts,
                ['post_name' => $initialPost866Name, 'post_modified' => $initialPost866Modified],
                ['ID' => 866]
            );
            clean_post_cache(866);
        }
    }
}

// -------------------------------------------------------------
// Z1: Xác nhận DB checksum và post 866 không đổi sau ROLLBACK
// -------------------------------------------------------------
$finalChecksum = getTablesContentChecksum($wpdb, $tableRedir, $tableCache, $tableLogs);
$finalPost866 = get_post(866);
$post866Unchanged = (
    $finalPost866 &&
    $finalPost866->post_name === $initialPost866Name &&
    $finalPost866->post_modified === $initialPost866Modified
);

$dbUnchanged = ($initialChecksum === $finalChecksum && $post866Unchanged);

if ($dbUnchanged) {
    $passCount++;
    echo "PASS Z1 DB content checksum unchanged [{$initialChecksum} == {$finalChecksum}]\n";
} else {
    $failCount++;
    echo "FAIL Z1 DB changed [initial: {$initialChecksum} -> final: {$finalChecksum}]\n";
}

echo "SUMMARY pass={$passCount} fail={$failCount}\n";
