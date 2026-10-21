<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Laca SEO vs Rank Math Breadcrumbs Comparison Tool (Phase 3A - R1)
 *
 * Compares exact HTML output of Rank Math vs Laca SEO Breadcrumbs
 * with repaired in-memory Rank Math General settings.
 */

// Nạp option RM general đã sửa vào bộ nhớ (không ghi DB) trước khi WP load
$raw = null;
$GLOBALS['wp_filter'] = [
    'pre_option_rank-math-options-general' => [
        10 => [[
            'function' => function ($pre) {
                global $wpdb;
                $raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='rank-math-options-general'");
                $fixed = preg_replace_callback('!s:(\d+):"(.*?)";!s', fn($m) => 's:' . strlen($m[2]) . ':"' . $m[2] . '";', $raw);
                $v = @unserialize($fixed);
                return is_array($v) ? $v : $pre;
            },
            'accepted_args' => 1,
        ]],
    ],
];

$_SERVER['HTTP_HOST'] = 'lixroastery.local';
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 6) . '/wp-load.php';

echo "========================================================================\n";
echo " LACA SEO vs RANK MATH - BREADCRUMBS COMPARISON TOOL (Phase 3A)\n";
echo "========================================================================\n\n";

$urls = [
    'Frontpage (VI)'       => home_url('/'),
    'Frontpage (EN)'       => home_url('/en/'),
    'Single Page'          => home_url('/manifesto/'),
    'Single Product'       => home_url('/product/aconchego-1-masterpieces-auction-2025/'),
    'Single Journal'       => home_url('/journal/extremely-light-cua-lix-roastery/'),
    'Single Journal (EN)'  => home_url('/journal/lix-roastery-at-cafe-show-hanoi-2026/'),
    'Shop Page'            => home_url('/shop/'),
    'Shop Page (EN)'       => home_url('/en/shop/'),
    'Journal Archive'      => home_url('/journal/'),
    'Journal Page 2'       => home_url('/journal/page/2/'),
    'Category Archive'     => home_url('/category/uncategorized/'),
    'Product Cat Archive'  => home_url('/product-category/ca-phe-specialty/'),
    'Journal Cat Archive'  => home_url('/journal-cat/find-the-origin/'),
    'Journal Cat Page 2'   => home_url('/journal-cat/find-the-origin/page/2/'),
    'Tag Archive'          => home_url('/tag/arabica/'),
    'Attachment Page'      => home_url('/journal/lix-roastery-tai-cafe-show-hanoi-2026/cafe-show-4/'),
    'Author Archive'       => home_url('/author/lacadev/'),
    'Search Results'       => home_url('/?s=cafe'),
    '404 Not Found'        => home_url('/url-khong-ton-tai-404-test/'),
];

function setupUrlEnvironment(string $url): void {
    $parsed = wp_parse_url($url);
    $path = $parsed['path'] ?? '/';
    $query = $parsed['query'] ?? '';

    $_SERVER['REQUEST_URI'] = $path . ($query ? '?' . $query : '');
    $_SERVER['QUERY_STRING'] = $query;
    $_SERVER['HTTP_HOST'] = $parsed['host'] ?? 'lixroastery.local';
    $_GET = [];
    if (!empty($query)) {
        parse_str($query, $_GET);
    }

    $GLOBALS['wp'] = new WP();
    $GLOBALS['wp_query'] = new WP_Query();
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

    $GLOBALS['wp']->parse_request();
    $GLOBALS['wp_query']->query($GLOBALS['wp']->query_vars);
    $GLOBALS['wp']->register_globals();
}

$normalize = function ($h): string {
    return trim((string) preg_replace(
        ['~class="[^"]*breadcrumb[^"]*"~', '~\s+~'],
        ['class="X"', ' '],
        (string) $h
    ));
};

$all_matched = true;
$passed_count = 0;
$total_count = count($urls);

foreach ($urls as $label => $url) {
    setupUrlEnvironment($url);

    $rm_html = (new \RankMath\Frontend\Breadcrumbs())->get_breadcrumb();
    $la_html = (new \App\Features\LacaSeo\Frontend\Breadcrumbs())->getBreadcrumb();

    $norm_rm = $normalize($rm_html);
    $norm_la = $normalize($la_html);

    $match = ($norm_rm === $norm_la);

    if ($match) {
        $passed_count++;
        printf("%-24s [PASS]\n", $label);
    } else {
        $all_matched = false;
        printf("%-24s [FAIL]\n", $label);
        echo "   Rank Math: " . substr($norm_rm, 0, 300) . "\n";
        echo "   Laca SEO : " . substr($norm_la, 0, 300) . "\n";
    }
}

echo "\n========================================================================\n";
if ($all_matched) {
    echo " RESULT: ALL {$total_count} BREADCRUMB TRAILS MATCH 100% (0 DIFFERENCES WITH RANK MATH)\n";
} else {
    echo " RESULT: FAILED! {$passed_count}/{$total_count} matched.\n";
}
echo "========================================================================\n";
