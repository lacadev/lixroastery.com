<?php
/**
 * Đo và so sánh hiệu năng Rank Math vs Laca SEO (Phase 7 - P7.3).
 *
 * Chạy qua CLI:
 * /Users/lacadev/Library/Application\ Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php -d memory_limit=512M -d mysqli.default_socket="/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock" doc/laca-seo/tools/perf-compare.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$phpCli = '/Users/lacadev/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$mysqlSock = '/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
$workerScript = __DIR__ . '/perf-worker.php';

$urls = [
    '/',
    '/en/',
    '/manifesto/',
    '/product/aconchego-1-masterpieces-auction-2025/',
    '/journal/extremely-light-cua-lix-roastery/',
    '/journal/lix-roastery-at-cafe-show-hanoi-2026/',
    '/shop/',
    '/product-category/ca-phe-specialty/',
];

$iterations = 7; // Chạy 7 lần, bỏ lần đầu, lấy trung vị 6 lần sau

echo "========================================================================================\n";
echo " LACA SEO vs RANK MATH - PERFORMANCE BENCHMARK (Phase 7)\n";
echo "========================================================================================\n";
echo "Số URL: " . count($urls) . " | Lặp mỗi URL: {$iterations} lần (bỏ lần đầu lấy median)\n\n";

$results = [];

foreach ($urls as $url) {
    echo "Đang đo {$url} ... ";
    
    // Đo Mode RM
    $rmTimes = [];
    $rmQueries = [];
    $rmMems = [];
    for ($i = 0; $i < $iterations; $i++) {
        $cmd = escapeshellarg($phpCli) . ' -d memory_limit=512M -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($url) . ' rm 2>/dev/null';
        $out = json_decode((string) shell_exec($cmd), true);
        if ($i > 0 && is_array($out)) {
            $rmTimes[] = $out['time_ms'];
            $rmQueries[] = $out['queries'];
            $rmMems[] = $out['mem_mb'];
        }
    }

    // Đo Mode Laca
    $lacaTimes = [];
    $lacaQueries = [];
    $lacaMems = [];
    for ($i = 0; $i < $iterations; $i++) {
        $cmd = escapeshellarg($phpCli) . ' -d memory_limit=512M -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($url) . ' laca 2>/dev/null';
        $out = json_decode((string) shell_exec($cmd), true);
        if ($i > 0 && is_array($out)) {
            $lacaTimes[] = $out['time_ms'];
            $lacaQueries[] = $out['queries'];
            $lacaMems[] = $out['mem_mb'];
        }
    }

    sort($rmTimes);
    sort($lacaTimes);
    sort($rmQueries);
    sort($lacaQueries);

    $medianRmTime = $rmTimes[intval(count($rmTimes) / 2)] ?? 0;
    $medianLacaTime = $lacaTimes[intval(count($lacaTimes) / 2)] ?? 0;
    $medianRmQ = $rmQueries[intval(count($rmQueries) / 2)] ?? 0;
    $medianLacaQ = $lacaQueries[intval(count($lacaQueries) / 2)] ?? 0;

    $deltaPercent = $medianRmTime > 0 ? (($medianLacaTime - $medianRmTime) / $medianRmTime) * 100 : 0;
    $deltaQ = $medianLacaQ - $medianRmQ;

    $results[] = [
        'url'        => $url,
        'rm_time'    => $medianRmTime,
        'laca_time'  => $medianLacaTime,
        'delta_pct'  => $deltaPercent,
        'rm_queries' => $medianRmQ,
        'laca_queries'=> $medianLacaQ,
        'delta_q'    => $deltaQ,
    ];

    echo "Xong.\n";
}

echo "\n";
printf("%-45s | %10s | %10s | %8s | %7s | %7s | %6s\n", "URL", "Time RM", "Time Laca", "Δ%", "Q RM", "Q Laca", "ΔQ");
echo str_repeat("-", 108) . "\n";

$allDeltas = [];
$passQueryCount = 0;

foreach ($results as $r) {
    printf("%-45s | %8.2fms | %8.2fms | %+7.1f%% | %7d | %7d | %+6d\n",
        substr($r['url'], 0, 45),
        $r['rm_time'],
        $r['laca_time'],
        $r['delta_pct'],
        $r['rm_queries'],
        $r['laca_queries'],
        $r['delta_q']
    );
    $allDeltas[] = $r['delta_pct'];
    if ($r['delta_q'] <= 0) {
        $passQueryCount++;
    }
}

sort($allDeltas);
$medianDelta = $allDeltas[intval(count($allDeltas) / 2)] ?? 0;

echo str_repeat("=", 108) . "\n";
printf("TỔNG HỢP: Median Δ Time = %+0.2f%% | URLs có Queries Laca <= RM: %d/%d\n", $medianDelta, $passQueryCount, count($urls));

return [
    'results'         => $results,
    'median_delta'    => $medianDelta,
    'pass_query_count'=> $passQueryCount,
];
