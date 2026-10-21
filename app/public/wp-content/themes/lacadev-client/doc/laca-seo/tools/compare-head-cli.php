<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web (file nằm trong thư mục theme công khai).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Laca SEO vs Rank Math Head & JSON-LD Comparison Tool (Phase 3B - Isolated Process per URL)
 *
 * Runs each URL in an isolated sub-process with in-memory pre_option_blog_public = 1
 * and repaired in-memory rank-math-options-general.
 * Compares full HTML <head> meta tags AND JSON-LD graph (node-by-node by @type and @id).
 */

$urls = [
    'Frontpage (VI)'       => '/',
    'Frontpage (EN)'       => '/en/',
    'Single Page'          => '/manifesto/',
    'Single Product'       => '/product/aconchego-1-masterpieces-auction-2025/',
    'Single Journal'       => '/journal/extremely-light-cua-lix-roastery/',
    'Single Journal (EN)'  => '/journal/lix-roastery-at-cafe-show-hanoi-2026/',
    'Shop Page'            => '/shop/',
    'Shop Page (EN)'       => '/en/shop/',
    'Journal Archive'      => '/journal/',
    'Journal Page 2'       => '/journal/page/2/',
    'Category Archive'     => '/category/uncategorized/',
    'Product Cat Archive'  => '/product-category/ca-phe-specialty/',
    'Journal Cat Archive'  => '/journal-cat/find-the-origin/',
    'Journal Cat Page 2'   => '/journal-cat/find-the-origin/page/2/',
    'Tag Archive'          => '/tag/arabica/',
    'Attachment Page'      => '/journal/lix-roastery-tai-cafe-show-hanoi-2026/cafe-show-4/',
    'Author Archive'       => '/author/lacadev/',
    'Search Results'       => '/?s=cafe',
    '404 Not Found'        => '/url-khong-ton-tai-404-test/',
];

$phpBinary = '/Users/lacadev/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$socket = '/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
$wpLoad = '/Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-load.php';

echo "========================================================================================\n";
echo " LACA SEO vs RANK MATH - HEAD & JSON-LD COMPARISON TOOL (Phase 3B - Isolated CLI)\n";
echo " In-Memory Overrides: blog_public=1, fixed rank-math-options-general\n";
echo " Total URLs: " . count($urls) . "\n";
echo "========================================================================================\n\n";

function runUrlProcess(string $phpBinary, string $socket, string $wpLoad, string $url, bool $forceLaca): array {
    $code = '
        $url = $argv[1];
        $forceLaca = (bool) $argv[2];

        $parsed = parse_url($url);
        $_SERVER["REQUEST_URI"] = $url;
        $_SERVER["QUERY_STRING"] = $parsed["query"] ?? "";
        $_GET = [];
        if (!empty($parsed["query"])) {
            parse_str($parsed["query"], $_GET);
        }
        $_SERVER["HTTP_HOST"] = "lixroastery.local";

        $GLOBALS["wp_filter"] = [
            "pre_option_blog_public" => [10 => [["function" => fn() => 1, "accepted_args" => 1]]],
            "pre_option_rank-math-options-general" => [10 => [["function" => function ($pre) {
                global $wpdb;
                $raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name=\"rank-math-options-general\"");
                $fixed = preg_replace_callback("!s:(\d+):\"(.*?)\";!s", fn($m) => "s:" . strlen($m[2]) . ":\"" . $m[2] . "\";", $raw);
                $v = @unserialize($fixed);
                return is_array($v) ? $v : $pre;
            }, "accepted_args" => 1]]],
        ];

        define("WP_USE_THEMES", false);
        if ($forceLaca) {
            define("LACA_SEO_FORCE_FRONTEND", true);
        }

        require "' . addslashes($wpLoad) . '";
        global $wp;
        $wp->main();

        ob_start();
        do_action("wp_head");
        $headHtml = ob_get_clean();

        $jsonLd = null;
        $pattern = $forceLaca
            ? "~<script type=\"application/ld\+json\" class=\"laca-seo-schema\"[^>]*>(.*?)</script>~s"
            : "~<script type=\"application/ld\+json\" class=\"rank-math-schema[^\"]*\"[^>]*>(.*?)</script>~s";

        if (preg_match($pattern, $headHtml, $m)) {
            $jsonLd = json_decode($m[1], true);
        }

        // Clean headHtml for tag extraction
        echo "---JSONLD_START---\n" . json_encode($jsonLd, JSON_UNESCAPED_SLASHES) . "\n---JSONLD_END---\n";
        echo "---HEAD_START---\n" . $headHtml . "\n---HEAD_END---\n";
    ';

    $cmd = sprintf(
        "%s -d memory_limit=512M -d \"mysqli.default_socket=%s\" -r %s %s %d",
        escapeshellarg($phpBinary),
        $socket,
        escapeshellarg($code),
        escapeshellarg($url),
        $forceLaca ? 1 : 0
    );

    $out = shell_exec($cmd) ?? '';

    $jsonLd = null;
    if (preg_match('/---JSONLD_START---\n(.*?)\n---JSONLD_END---/s', $out, $jm)) {
        $jsonLd = json_decode($jm[1], true);
    }

    $headHtml = '';
    if (preg_match('/---HEAD_START---\n(.*?)\n---HEAD_END---/s', $out, $hm)) {
        $headHtml = $hm[1];
    }

    return [
        'json' => $jsonLd,
        'head' => $headHtml,
    ];
}

function parseHeadMeta(string $head): array {
    $tags = [
        'title'                  => '',
        'description'            => '',
        'robots'                 => '',
        'canonical'              => '',
        'og:locale'              => '',
        'og:locale:alternate'    => [],
        'og:type'                => '',
        'og:title'               => '',
        'og:description'         => '',
        'og:url'                 => '',
        'og:site_name'           => '',
        'og:updated_time'        => '',
        'og:image'               => '',
        'og:image:width'         => '',
        'og:image:height'        => '',
        'og:image:alt'           => '',
        'og:image:type'          => '',
        'article:published_time' => '',
        'article:modified_time'  => '',
        'product:brand'          => '',
        'product:price:amount'   => '',
        'product:price:currency' => '',
        'product:availability'   => '',
        'product:retailer_item_id' => '',
        'twitter:card'           => '',
        'twitter:title'          => '',
        'twitter:description'    => '',
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

    // og:locale:alternate can have multiple entries
    if (preg_match_all('/<meta[^>]+property=["\']og:locale:alternate["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['og:locale:alternate'] = $m[1];
    }

    $ogProps = [
        'og:locale', 'og:type', 'og:title', 'og:description', 'og:url', 'og:site_name',
        'og:updated_time', 'og:image', 'og:image:width', 'og:image:height', 'og:image:alt', 'og:image:type',
        'article:published_time', 'article:modified_time', 'product:brand', 'product:price:amount',
        'product:price:currency', 'product:availability', 'product:retailer_item_id'
    ];
    foreach ($ogProps as $prop) {
        $p1 = '/<meta[^>]+property=["\']' . preg_quote($prop, '/') . '["\'][^>]+content=["\']([^"\']*)["\']/is';
        $p2 = '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote($prop, '/') . '["\']/is';
        if (preg_match($p1, $head, $m) || preg_match($p2, $head, $m)) {
            $tags[$prop] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    $twProps = ['twitter:card', 'twitter:title', 'twitter:description'];
    foreach ($twProps as $prop) {
        $p1 = '/<meta[^>]+name=["\']' . preg_quote($prop, '/') . '["\'][^>]+content=["\']([^"\']*)["\']/is';
        $p2 = '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . preg_quote($prop, '/') . '["\']/is';
        if (preg_match($p1, $head, $m) || preg_match($p2, $head, $m)) {
            $tags[$prop] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    return $tags;
}

function normalizeNode(mixed $node): mixed {
    if (is_array($node)) {
        ksort($node);
        foreach ($node as $k => $v) {
            if ($k === '@id' && is_string($v)) {
                $node[$k] = preg_replace('/#schema-\d+$/', '#schema-custom', $v);
            } else {
                $node[$k] = normalizeNode($v);
            }
        }
    }
    return $node;
}

$allPassed = true;
$results = [];

printf("%-24s | %-10s | %-12s | %-10s | %-10s | %s\n", "URL Label", "Robots", "Canonical", "Meta Tags", "JSON-LD", "Overall");
echo str_repeat("-", 88) . "\n";

function normalizeRobots(string $s): string {
    if (trim($s) === '') {
        return '';
    }
    $parts = array_map('trim', explode(',', $s));
    sort($parts);
    return implode(', ', $parts);
}

foreach ($urls as $label => $path) {
    // 1. Run RM
    $rmRes = runUrlProcess($phpBinary, $socket, $wpLoad, $path, false);
    // 2. Run Laca
    $lacaRes = runUrlProcess($phpBinary, $socket, $wpLoad, $path, true);

    $rmMeta = parseHeadMeta($rmRes['head']);
    $lacaMeta = parseHeadMeta($lacaRes['head']);

    $robotsMatch = (normalizeRobots($rmMeta['robots']) === normalizeRobots($lacaMeta['robots']));
    $canonicalMatch = ($rmMeta['canonical'] === $lacaMeta['canonical']);

    // Compare all meta tags (allow known description <p> difference if any)
    $metaDiffs = [];
    foreach ($rmMeta as $key => $rmVal) {
        $lacaVal = $lacaMeta[$key] ?? '';
        if ($key === 'robots') {
            if (normalizeRobots($rmVal) !== normalizeRobots($lacaVal)) {
                $metaDiffs[$key] = ['RM' => $rmVal, 'LACA' => $lacaVal];
            }
        } elseif ($key === 'description' || $key === 'og:description' || $key === 'twitter:description') {
            $normRm = trim(strip_tags((string) $rmVal));
            $normLa = trim(strip_tags((string) $lacaVal));
            if ($normRm !== $normLa) {
                $metaDiffs[$key] = ['RM' => $rmVal, 'LACA' => $lacaVal];
            }
        } elseif (is_array($rmVal) || is_array($lacaVal)) {
            $a1 = is_array($rmVal) ? $rmVal : [$rmVal];
            $a2 = is_array($lacaVal) ? $lacaVal : [$lacaVal];
            sort($a1);
            sort($a2);
            if ($a1 !== $a2) {
                $metaDiffs[$key] = ['RM' => json_encode($a1), 'LACA' => json_encode($a2)];
            }
        } else {
            if ($rmVal !== $lacaVal) {
                $metaDiffs[$key] = ['RM' => $rmVal, 'LACA' => $lacaVal];
            }
        }
    }
    $metaMatch = empty($metaDiffs);

    // Compare JSON-LD graphs
    $rmGraph = $rmRes['json']['@graph'] ?? [];
    $lacaGraph = $lacaRes['json']['@graph'] ?? [];

    $jsonMatch = true;
    $jsonReason = '';

    if (count($rmGraph) !== count($lacaGraph)) {
        $jsonMatch = false;
        $jsonReason = sprintf("Node count mismatch: RM=%d vs LACA=%d", count($rmGraph), count($lacaGraph));
    } else {
        foreach ($rmGraph as $idx => $rmNode) {
            $lacaNode = $lacaGraph[$idx] ?? null;
            $normRm = normalizeNode($rmNode);
            $normLa = normalizeNode($lacaNode);
            if (json_encode($normRm) !== json_encode($normLa)) {
                $jsonMatch = false;
                $t = is_array($rmNode['@type'] ?? '') ? implode(',', $rmNode['@type']) : ($rmNode['@type'] ?? '');
                $jsonReason = "Diff in node {$idx} ({$t})";
                break;
            }
        }
    }

    $pass = $robotsMatch && $canonicalMatch && $metaMatch && $jsonMatch;
    if (!$pass) {
        $allPassed = false;
    }

    printf(
        "%-24s | %-10s | %-12s | %-10s | %-10s | %s\n",
        $label,
        $robotsMatch ? 'MATCH' : 'DIFF',
        $canonicalMatch ? 'MATCH' : 'DIFF',
        $metaMatch ? 'MATCH' : 'DIFF',
        $jsonMatch ? ('MATCH(' . count($rmGraph) . ')') : 'DIFF',
        $pass ? '[PASS]' : '[FAIL]'
    );

    if (!$pass) {
        if (!$robotsMatch) {
            echo "   Robots DIFF: RM='{$rmMeta['robots']}' vs LACA='{$lacaMeta['robots']}'\n";
        }
        if (!$canonicalMatch) {
            echo "   Canonical DIFF: RM='{$rmMeta['canonical']}' vs LACA='{$lacaMeta['canonical']}'\n";
        }
        if (!$metaMatch) {
            foreach ($metaDiffs as $k => $diff) {
                echo "   Meta {$k} DIFF: RM='{$diff['RM']}' vs LACA='{$diff['LACA']}'\n";
            }
        }
        if (!$jsonMatch) {
            echo "   JSON-LD DIFF: {$jsonReason}\n";
        }
    }
}

echo str_repeat("=", 88) . "\n";
if ($allPassed) {
    echo " RESULT: ALL " . count($urls) . " URLs 100% MATCH (Robots, Canonical, Meta, JSON-LD Graph)!\n";
    echo " Theme CI / Head Comparison: PASS\n";
} else {
    echo " RESULT: Some URLs have differences. Please inspect the log above.\n";
}
echo "========================================================================================\n";
