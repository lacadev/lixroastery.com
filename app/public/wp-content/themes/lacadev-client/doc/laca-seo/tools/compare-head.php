<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web (file nằm trong thư mục theme công khai).
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}
/**
 * Laca SEO Head Comparison Tool (Expanded per B4)
 * 
 * Compares <head> outputs between Rank Math and Laca SEO across real existing URLs.
 * Run via Local PHP-CLI:
 * php -d "mysqli.default_socket=/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock" doc/laca-seo/tools/compare-head.php
 */

require_once dirname(__DIR__, 6) . '/wp-load.php';

// Real active URLs on site including required B4 additions
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

echo "========================================================================\n";
echo " LACA SEO vs RANK MATH - EXPANDED HEAD COMPARISON TOOL (Phase 1)\n";
echo "========================================================================\n\n";

function fetchHead(string $url): string {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) LacaSeoCompareTool/1.0');
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) {
        return '';
    }

    if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $matches)) {
        return $matches[1];
    }
    return '';
}

function parseSeoTags(string $head): array {
    $tags = [
        'title'                  => '',
        'description'            => '',
        'robots'                 => '',
        'canonical'              => '',
        'rel:prev'               => '',
        'rel:next'               => '',
        'og:locale'              => '',
        'og:type'                => '',
        'og:title'               => '',
        'og:description'         => '',
        'og:url'                 => '',
        'og:site_name'           => '',
        'og:image'               => '',
        'og:image:width'         => '',
        'og:image:height'        => '',
        'article:published_time' => '',
        'article:modified_time'  => '',
        'twitter:card'           => '',
        'twitter:title'          => '',
        'twitter:description'    => '',
        'twitter:label1'         => '',
        'twitter:data1'          => '',
        'google-site-verification' => '',
    ];

    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $m)) {
        $tags['title'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m) ||
        preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/is', $head, $m)) {
        $tags['description'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m) ||
        preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']robots["\']/is', $head, $m)) {
        $tags['robots'] = trim($m[1]);
    }

    if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)["\']/is', $head, $m) ||
        preg_match('/<link[^>]+href=["\']([^"\']*)["\'][^>]+rel=["\']canonical["\']/is', $head, $m)) {
        $tags['canonical'] = trim($m[1]);
    }

    if (preg_match('/<link[^>]+rel=["\']prev["\'][^>]+href=["\']([^"\']*)["\']/is', $head, $m) ||
        preg_match('/<link[^>]+href=["\']([^"\']*)["\'][^>]+rel=["\']prev["\']/is', $head, $m)) {
        $tags['rel:prev'] = trim($m[1]);
    }

    if (preg_match('/<link[^>]+rel=["\']next["\'][^>]+href=["\']([^"\']*)["\']/is', $head, $m) ||
        preg_match('/<link[^>]+href=["\']([^"\']*)["\'][^>]+rel=["\']next["\']/is', $head, $m)) {
        $tags['rel:next'] = trim($m[1]);
    }

    $ogProps = [
        'og:locale', 'og:type', 'og:title', 'og:description', 'og:url', 'og:site_name',
        'og:image', 'og:image:width', 'og:image:height', 'article:published_time', 'article:modified_time'
    ];
    foreach ($ogProps as $prop) {
        $pattern1 = '/<meta[^>]+property=["\']' . preg_quote($prop, '/') . '["\'][^>]+content=["\']([^"\']*)["\']/is';
        $pattern2 = '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote($prop, '/') . '["\']/is';
        if (preg_match($pattern1, $head, $m) || preg_match($pattern2, $head, $m)) {
            $tags[$prop] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    $twitterNames = ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:label1', 'twitter:data1', 'google-site-verification'];
    foreach ($twitterNames as $prop) {
        $pattern1 = '/<meta[^>]+name=["\']' . preg_quote($prop, '/') . '["\'][^>]+content=["\']([^"\']*)["\']/is';
        $pattern2 = '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . preg_quote($prop, '/') . '["\']/is';
        if (preg_match($pattern1, $head, $m) || preg_match($pattern2, $head, $m)) {
            $tags[$prop] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    return $tags;
}

$results = [];

foreach ($urls as $label => $url) {
    echo "Processing: $label ($url)...\n";

    // 1. Fetch Rank Math version (normal URL)
    $headRM = fetchHead($url);
    $tagsRM = parseSeoTags($headRM);

    // 2. Fetch Laca SEO version (with force parameter)
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    $lacaUrl = $url . $separator . 'laca_seo_force_frontend=1';
    $headLaca = fetchHead($lacaUrl);
    $tagsLaca = parseSeoTags($headLaca);

    // Compare each tag
    $urlDiff = [];
    foreach ($tagsRM as $k => $valRM) {
        $valLaca = $tagsLaca[$k] ?? '';

        if ($k === 'robots') {
            $partsRM = array_filter(array_map('trim', explode(',', $valRM)));
            sort($partsRM);
            $partsLaca = array_filter(array_map('trim', explode(',', $valLaca)));
            sort($partsLaca);
            $match = ($partsRM === $partsLaca);
        } elseif (in_array($k, ['canonical', 'og:url', 'rel:prev', 'rel:next', 'og:image'], true)) {
            $match = (rtrim($valRM, '/') === rtrim($valLaca, '/'));
        } else {
            $match = ($valRM === $valLaca);
        }

        $urlDiff[$k] = [
            'match' => $match,
            'rm'    => $valRM,
            'laca'  => $valLaca,
        ];
    }

    $results[$label] = [
        'url'  => $url,
        'diff' => $urlDiff,
    ];
}

echo "\n========================================================================================================================\n";
echo " COMPARISON SUMMARY TABLE\n";
echo "========================================================================================================================\n\n";

printf("%-22s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s\n", "Page / Type", "Title", "Desc", "Robots", "Canon", "OG Typ", "OG Tit", "OG Img", "Twit", "Loc");
echo str_repeat('-', 96) . "\n";

foreach ($results as $label => $data) {
    $d = $data['diff'];
    $fTitle  = $d['title']['match'] ? 'MATCH' : 'DIFF';
    $fDesc   = $d['description']['match'] ? 'MATCH' : 'DIFF';
    $fRobots = $d['robots']['match'] ? 'MATCH' : 'DIFF';
    $fCanon  = $d['canonical']['match'] ? 'MATCH' : 'DIFF';
    $fOgType = $d['og:type']['match'] ? 'MATCH' : 'DIFF';
    $fOgTit  = $d['og:title']['match'] ? 'MATCH' : 'DIFF';
    $fOgImg  = $d['og:image']['match'] ? 'MATCH' : 'DIFF';
    $fTwit   = $d['twitter:title']['match'] ? 'MATCH' : 'DIFF';
    $fLoc    = $d['og:locale']['match'] ? 'MATCH' : 'DIFF';

    printf("%-22s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s | %-6s\n", substr($label, 0, 22), $fTitle, $fDesc, $fRobots, $fCanon, $fOgType, $fOgTit, $fOgImg, $fTwit, $fLoc);
}

echo "\n\nDetailed breakdown of differences (if any):\n";
echo str_repeat('-', 96) . "\n";
$hasDiff = false;
foreach ($results as $label => $data) {
    foreach ($data['diff'] as $tag => $val) {
        if (!$val['match']) {
            $hasDiff = true;
            echo "[$label] Tag: $tag\n";
            echo "   Rank Math: {$val['rm']}\n";
            echo "   Laca SEO:  {$val['laca']}\n\n";
        }
    }
}

if (!$hasDiff) {
    echo "🎉 100% Match! All core tags are identical across tested URLs.\n";
}
