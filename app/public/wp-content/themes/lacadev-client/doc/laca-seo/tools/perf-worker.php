<?php
/**
 * Worker script for measuring template execution time, memory and queries.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[$_, $path, $mode] = $argv;

define('SAVEQUERIES', true);
define('WP_USE_THEMES', true);

$GLOBALS['wp_filter'] = [
    'pre_option_blog_public' => [10 => [['function' => fn() => '1', 'accepted_args' => 1]]],
];

if ($mode === 'laca') {
    $GLOBALS['wp_filter']['option_active_plugins'] = [
        10 => [[
            'function' => function($plugins) {
                return array_values(array_filter((array)$plugins, fn($p) => !str_contains($p, 'rank-math')));
            },
            'accepted_args' => 1,
        ]],
    ];
}

$q = parse_url($path, PHP_URL_QUERY) ?: '';
$_SERVER['HTTP_HOST'] = 'lixroastery.local';
$_SERVER['SERVER_NAME'] = 'lixroastery.local';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $path;
$_GET = [];
parse_str($q, $_GET);

if ($mode === 'laca') {
    $_GET['laca_seo_force_frontend'] = '1';
}

require dirname(__DIR__, 6) . '/wp-load.php';
wp();

global $wpdb;
$tStart = hrtime(true);
$qStart = count($wpdb->queries ?? []);

ob_start();
// Execute template_redirect action
do_action('template_redirect');

// Capture SEO Head specifics
$tHead1 = hrtime(true);
$qHead1 = count($wpdb->queries ?? []);
wp_head();
$tHead2 = hrtime(true);
$qHead2 = count($wpdb->queries ?? []);

// Render template query if file exists
if (function_exists('get_query_template')) {
    $tpl = get_query_template('index');
    if ($tpl && file_exists($tpl)) {
        include $tpl;
    }
}
$html = ob_get_clean();

$tEnd = hrtime(true);
$qEnd = count($wpdb->queries ?? []);

$durationMs = ($tEnd - $tStart) / 1e6;
$queryCount = $qEnd - $qStart;
$seoHeadTime = ($tHead2 - $tHead1) / 1e6;
$seoHeadQueries = $qHead2 - $qHead1;
$memMb = memory_get_peak_usage(true) / 1024 / 1024;

echo json_encode([
    'time_ms'          => round($durationMs, 2),
    'queries'          => $queryCount,
    'mem_mb'           => round($memMb, 2),
    'seo_head_time'    => round($seoHeadTime, 2),
    'seo_head_queries' => $seoHeadQueries,
    'head_size'        => strlen($html),
]);
