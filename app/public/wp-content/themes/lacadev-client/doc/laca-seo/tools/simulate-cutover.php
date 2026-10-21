<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Laca SEO Cutover Simulation Tool (Phase 3A - R1)
 *
 * Simulates post-cutover state without touching the DB:
 * - Disables Rank Math via `option_active_plugins` filter
 * - Injects in-memory rewrite rules via `pre_option_rewrite_rules`
 * - Tests actual router and handlers for all SEO endpoints in isolated processes
 * - Compares with live Rank Math output
 */

$phpBinary = '/Users/lacadev/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$socket = '/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
$docRoot = dirname(__DIR__, 6);

echo "========================================================================\n";
echo " LACA SEO CUTOVER SIMULATION TOOL (Phase 3A - R1)\n";
echo "========================================================================\n\n";

$key = '3334f2373af24b28be374ed0b983b788';

$endpoints = [
    '/sitemap_index.xml' => [
        'type' => 'xml_sitemap',
        'desc' => 'Sitemap Index',
    ],
    '/page-sitemap.xml' => [
        'type' => 'xml_sitemap',
        'desc' => 'Page Sub-sitemap',
    ],
    '/product-sitemap.xml' => [
        'type' => 'xml_sitemap',
        'desc' => 'Product Sub-sitemap',
    ],
    '/sitemap.xml' => [
        'type' => 'redirect',
        'expected_status' => 301,
        'expected_location' => 'http://lixroastery.local/sitemap_index.xml',
        'desc' => 'Redirect /sitemap.xml -> /sitemap_index.xml',
    ],
    '/wp-sitemap.xml' => [
        'type' => 'redirect',
        'expected_status' => 301,
        'expected_location' => 'http://lixroastery.local/sitemap_index.xml',
        'desc' => 'Redirect /wp-sitemap.xml -> /sitemap_index.xml',
    ],
    '/main-sitemap.xsl' => [
        'type' => 'xsl',
        'desc' => 'Main Sitemap XSL Stylesheet',
    ],
    '/robots.txt' => [
        'type' => 'robots',
        'desc' => 'Robots.txt Output',
    ],
    "/{$key}.txt" => [
        'type' => 'exact',
        'expected' => $key,
        'desc' => 'IndexNow API Key Endpoint',
    ],
    '/llms.txt' => [
        'type' => 'status_404',
        'desc' => 'LLMS.txt (404 when module inactive)',
    ],
];

function runLacaProcess(string $path, string $phpBinary, string $socket, string $docRoot): array
{
    $code = '
$rules = null;
$redirectLoc = null;
$redirectStatus = null;
$GLOBALS["wp_filter"] = [
    "option_active_plugins" => [
        10 => [[
            "function" => function($plugins) {
                return array_values(array_filter((array)$plugins, fn($p) => !str_contains($p, "rank-math")));
            },
            "accepted_args" => 1,
        ]],
    ],
    "pre_option_rewrite_rules" => [
        10 => [[
            "function" => function($pre) use (&$rules) {
                if ($rules !== null) return $rules;
                return $pre;
            },
            "accepted_args" => 1,
        ]],
    ],
    "init" => [
        9999 => [[
            "function" => function() use (&$rules) {
                global $wp_rewrite;
                $rules = $wp_rewrite->rewrite_rules();
            },
            "accepted_args" => 0,
        ]],
    ],
    "wp_redirect" => [
        1 => [[
            "function" => function($location, $status) use (&$redirectLoc, &$redirectStatus) {
                $redirectLoc = $location;
                $redirectStatus = $status;
                return $location;
            },
            "accepted_args" => 2,
        ]],
        10 => [[
            "function" => function($location, $status) {
                header("Location: " . $location, true, $status);
                exit;
            },
            "accepted_args" => 2,
        ]],
    ],
    "redirect_canonical" => [
        10 => [[
            "function" => function($redirect_url, $requested_url) {
                if (str_contains($requested_url, "llms.txt")) {
                    return false;
                }
                return $redirect_url;
            },
            "accepted_args" => 2,
        ]],
    ],
];

register_shutdown_function(function() use (&$redirectLoc, &$redirectStatus) {
    $body = ob_get_clean();
    $status = $redirectStatus ?? http_response_code();
    $headers = headers_list();
    if ($redirectLoc !== null) {
        $headers[] = "Location: " . $redirectLoc;
    }
    echo "__JSON_START__" . json_encode([
        "status" => $status,
        "headers" => $headers,
        "body" => $body,
    ]) . "__JSON_END__";
});

$_SERVER["HTTP_HOST"] = "lixroastery.local";
$_SERVER["REQUEST_URI"] = ' . var_export($path, true) . ';
$_SERVER["REQUEST_METHOD"] = "GET";
define("WP_USE_THEMES", true);

ob_start();
require_once "wp-blog-header.php";
';

    $cmd = sprintf(
        '%s -d memory_limit=512M -d "mysqli.default_socket=%s" -r %s',
        escapeshellarg($phpBinary),
        $socket,
        escapeshellarg($code)
    );

    $raw = shell_exec("cd " . escapeshellarg($docRoot) . " && " . $cmd);
    if (preg_match('/__JSON_START__(.*?)__JSON_END__/s', (string)$raw, $m)) {
        $data = json_decode($m[1], true);
        if (is_array($data)) {
            return $data;
        }
    }

    return [
        'status' => 500,
        'headers' => [],
        'body' => $raw ?? '',
        'raw_error' => true,
    ];
}

function runRmProcess(string $path, string $phpBinary, string $socket, string $docRoot): array
{
    $code = '
$raw = null;
$redirectLoc = null;
$redirectStatus = null;
$GLOBALS["wp_filter"] = [
    "pre_option_rank-math-options-general" => [
        10 => [[
            "function" => function ($pre) {
                global $wpdb;
                $raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name=\"rank-math-options-general\"");
                $fixed = preg_replace_callback("!s:(\d+):\"(.*?)\";!s", fn($m) => "s:" . strlen($m[2]) . ":\"" . $m[2] . "\";", $raw);
                $v = @unserialize($fixed);
                return is_array($v) ? $v : $pre;
            },
            "accepted_args" => 1,
        ]],
    ],
    "wp_redirect" => [
        1 => [[
            "function" => function($location, $status) use (&$redirectLoc, &$redirectStatus) {
                $redirectLoc = $location;
                $redirectStatus = $status;
                return $location;
            },
            "accepted_args" => 2,
        ]],
        10 => [[
            "function" => function($location, $status) {
                header("Location: " . $location, true, $status);
                exit;
            },
            "accepted_args" => 2,
        ]],
    ],
];

register_shutdown_function(function() use (&$redirectLoc, &$redirectStatus) {
    $body = ob_get_clean();
    $status = $redirectStatus ?? http_response_code();
    $headers = headers_list();
    if ($redirectLoc !== null) {
        $headers[] = "Location: " . $redirectLoc;
    }
    echo "__JSON_START__" . json_encode([
        "status" => $status,
        "headers" => $headers,
        "body" => $body,
    ]) . "__JSON_END__";
});

$_SERVER["HTTP_HOST"] = "lixroastery.local";
$_SERVER["REQUEST_URI"] = ' . var_export($path, true) . ';
$_SERVER["REQUEST_METHOD"] = "GET";
define("WP_USE_THEMES", true);

ob_start();
require_once "wp-blog-header.php";
';

    $cmd = sprintf(
        '%s -d memory_limit=512M -d "mysqli.default_socket=%s" -r %s',
        escapeshellarg($phpBinary),
        $socket,
        escapeshellarg($code)
    );

    $raw = shell_exec("cd " . escapeshellarg($docRoot) . " && " . $cmd);
    if (preg_match('/__JSON_START__(.*?)__JSON_END__/s', (string)$raw, $m)) {
        $data = json_decode($m[1], true);
        if (is_array($data)) {
            return $data;
        }
    }

    return [
        'status' => 500,
        'headers' => [],
        'body' => $raw ?? '',
        'raw_error' => true,
    ];
}

function parseUrlsAndImages(string $xml): array
{
    $urls = [];
    if (!preg_match_all('/<url>(.*?)<\/url>/s', $xml, $urlMatches)) {
        return [];
    }

    foreach ($urlMatches[1] as $block) {
        $loc = '';
        $lastmod = '';
        $images = [];

        if (preg_match('/<loc>(.*?)<\/loc>/', $block, $m)) {
            $loc = trim($m[1]);
        }
        if (preg_match('/<lastmod>(.*?)<\/lastmod>/', $block, $m)) {
            $lastmod = trim($m[1]);
        }
        if (preg_match_all('/<image:loc>(.*?)<\/image:loc>/', $block, $imgMatches)) {
            $images = array_map('trim', $imgMatches[1]);
        }

        if ($loc !== '') {
            $urls[$loc] = [
                'lastmod' => $lastmod,
                'images' => $images,
            ];
        }
    }
    return $urls;
}

function parseIndexSitemaps(string $xml): array
{
    $sitemaps = [];
    if (preg_match_all('/<sitemap>(.*?)<\/sitemap>/s', $xml, $matches)) {
        foreach ($matches[1] as $block) {
            $loc = '';
            $lastmod = '';
            if (preg_match('/<loc>(.*?)<\/loc>/', $block, $m)) {
                $loc = trim($m[1]);
            }
            if (preg_match('/<lastmod>(.*?)<\/lastmod>/', $block, $m)) {
                $lastmod = trim($m[1]);
            }
            if ($loc !== '') {
                $sitemaps[$loc] = $lastmod;
            }
        }
    }
    return $sitemaps;
}

$allPassed = true;

foreach ($endpoints as $path => $cfg) {
    echo sprintf("%-38s (%s) ... ", $path, $cfg['desc']);
    $laca = runLacaProcess($path, $phpBinary, $socket, $docRoot);

    if (!empty($laca['raw_error'])) {
        echo "[FAIL] Raw execution error in Laca process:\n" . substr($laca['body'], 0, 300) . "\n";
        $allPassed = false;
        continue;
    }

    if ($cfg['type'] === 'redirect') {
        $loc = '';
        foreach ($laca['headers'] as $h) {
            if (stripos($h, 'Location:') === 0) {
                $loc = trim(substr($h, 9));
            }
        }
        if ($laca['status'] === $cfg['expected_status'] && $loc === $cfg['expected_location']) {
            echo "[PASS] (Redirect {$laca['status']} -> {$loc})\n";
        } else {
            echo "[FAIL] Expected {$cfg['expected_status']} -> {$cfg['expected_location']}, got {$laca['status']} -> {$loc}\n";
            $allPassed = false;
        }
    } elseif ($cfg['type'] === 'exact') {
        $trimmed = trim($laca['body']);
        if ($trimmed === $cfg['expected']) {
            echo "[PASS] (Content matches expected key)\n";
        } else {
            echo "[FAIL] Expected '{$cfg['expected']}', got '{$trimmed}'\n";
            $allPassed = false;
        }
    } elseif ($cfg['type'] === 'status_404') {
        if ($laca['status'] === 404) {
            echo "[PASS] (HTTP 404 as expected when inactive)\n";
        } else {
            echo "[FAIL] Expected HTTP 404, got {$laca['status']}\n";
            $allPassed = false;
        }
    } elseif ($cfg['type'] === 'xsl') {
        if (str_contains($laca['body'], '<xsl:stylesheet') && str_contains($laca['body'], 'XML Sitemap - LIX ROASTERY')) {
            echo "[PASS] (Valid XSL stylesheet, " . strlen($laca['body']) . " bytes)\n";
        } else {
            echo "[FAIL] Invalid XSL stylesheet output\n";
            $allPassed = false;
        }
    } elseif ($cfg['type'] === 'robots') {
        $rm = runRmProcess($path, $phpBinary, $socket, $docRoot);
        // Compare non-comment non-empty lines
        $cleanLines = function($text) {
            $lines = array_map('trim', explode("\n", (string)$text));
            return array_values(array_filter($lines, fn($l) => $l !== '' && !str_starts_with($l, '#')));
        };
        $lacaLines = $cleanLines($laca['body']);
        $rmLines = $cleanLines($rm['body']);

        if ($lacaLines === $rmLines) {
            echo "[PASS] (" . count($lacaLines) . " directives identical to Rank Math)\n";
        } else {
            echo "[FAIL] Robots.txt lines mismatch with Rank Math\n";
            echo "--- Laca lines ---\n" . implode("\n", $lacaLines) . "\n";
            echo "--- RM lines ---\n" . implode("\n", $rmLines) . "\n";
            $allPassed = false;
        }
    } elseif ($cfg['type'] === 'xml_sitemap') {
        $rm = runRmProcess($path, $phpBinary, $socket, $docRoot);

        if ($path === '/sitemap_index.xml') {
            $lacaMaps = parseIndexSitemaps($laca['body']);
            $rmMaps = parseIndexSitemaps($rm['body']);

            if (array_keys($lacaMaps) === array_keys($rmMaps)) {
                echo "[PASS] (" . count($lacaMaps) . " sub-sitemaps match Rank Math index)\n";
            } else {
                echo "[FAIL] Sitemap index list mismatch\n";
                echo "Laca: " . implode(', ', array_keys($lacaMaps)) . "\n";
                echo "RM:   " . implode(', ', array_keys($rmMaps)) . "\n";
                $allPassed = false;
            }
        } else {
            $lacaUrls = parseUrlsAndImages($laca['body']);
            $rmUrls = runRmProcess($path, $phpBinary, $socket, $docRoot);
            $rmUrlsParsed = parseUrlsAndImages($rmUrls['body']);

            $diffs = [];
            if (array_keys($lacaUrls) !== array_keys($rmUrlsParsed)) {
                $diffs[] = 'URL list/order mismatch (Laca: ' . count($lacaUrls) . ', RM: ' . count($rmUrlsParsed) . ')';
            } else {
                foreach ($rmUrlsParsed as $url => $rmData) {
                    $lacaData = $lacaUrls[$url] ?? null;
                    if (!$lacaData) {
                        $diffs[] = "Missing URL in Laca: {$url}";
                        continue;
                    }
                    if ($lacaData['lastmod'] !== $rmData['lastmod']) {
                        $diffs[] = "lastmod mismatch for {$url}: Laca={$lacaData['lastmod']} vs RM={$rmData['lastmod']}";
                    }
                    if ($lacaData['images'] !== $rmData['images']) {
                        $diffs[] = "Images mismatch for {$url}: Laca=" . json_encode($lacaData['images']) . " vs RM=" . json_encode($rmData['images']);
                    }
                }
            }

            if (empty($diffs)) {
                echo "[PASS] (" . count($lacaUrls) . " URLs, lastmod & images match 100%)\n";
            } else {
                echo "[FAIL] Diffs found:\n  - " . implode("\n  - ", array_slice($diffs, 0, 5)) . "\n";
                $allPassed = false;
            }
        }
    }
}

echo "\n------------------------------------------------------------------------\n";
echo " 2. Sample URLs <head> & JSON-LD (Rank Math Disabled vs Active)\n";
echo "------------------------------------------------------------------------\n";

$sampleUrls = [
    'Frontpage (VI)' => '/',
    'Single Product' => '/product/aconchego-1-masterpieces-auction-2025/',
    'Single Journal' => '/journal/extremely-light-cua-lix-roastery/',
    'Shop Page'      => '/shop/',
    'Product Cat'    => '/product-category/ca-phe-specialty/',
];

function runCutoverHead(string $path, string $phpBinary, string $socket, string $docRoot, bool $disableRm): array
{
    $code = '
$url = ' . var_export($path, true) . ';
$disableRm = ' . ($disableRm ? 'true' : 'false') . ';

$parsed = parse_url($url);
$_SERVER["REQUEST_URI"] = $url;
$_SERVER["QUERY_STRING"] = $parsed["query"] ?? "";
$_GET = [];
if (!empty($parsed["query"])) {
    parse_str($parsed["query"], $_GET);
}
$_SERVER["HTTP_HOST"] = "lixroastery.local";
$_SERVER["REQUEST_METHOD"] = "GET";

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

if ($disableRm) {
    $GLOBALS["wp_filter"]["option_active_plugins"] = [
        10 => [[
            "function" => function($plugins) {
                return array_values(array_filter((array)$plugins, fn($p) => !str_contains($p, "rank-math")));
            },
            "accepted_args" => 1,
        ]],
    ];
}

define("WP_USE_THEMES", false);
require "wp-load.php";
global $wp;
$wp->main();

ob_start();
do_action("wp_head");
$head = ob_get_clean();

$pattern = $disableRm
    ? "~<script type=\"application/ld\+json\" class=\"laca-seo-schema\"[^>]*>(.*?)</script>~s"
    : "~<script type=\"application/ld\+json\" class=\"rank-math-schema[^\"]*\"[^>]*>(.*?)</script>~s";

$json = null;
if (preg_match($pattern, $head, $m)) {
    $json = json_decode($m[1], true);
}

echo "__HEAD_START__" . $head . "__HEAD_END__";
echo "__JSON_START__" . json_encode($json, JSON_UNESCAPED_SLASHES) . "__JSON_END__";
';

    $cmd = sprintf(
        '%s -d memory_limit=512M -d "mysqli.default_socket=%s" -r %s',
        escapeshellarg($phpBinary),
        $socket,
        escapeshellarg($code)
    );

    $raw = shell_exec("cd " . escapeshellarg($docRoot) . " && " . $cmd);

    $head = '';
    if (preg_match('/__HEAD_START__(.*?)__HEAD_END__/s', (string)$raw, $hm)) {
        $head = $hm[1];
    }

    $json = null;
    if (preg_match('/__JSON_START__(.*?)__JSON_END__/s', (string)$raw, $jm)) {
        $json = json_decode($jm[1], true);
    }

    return ['head' => $head, 'json' => $json];
}

function parseMetaTags(string $head): array
{
    $tags = [];
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $m)) {
        $tags['title'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['canonical'] = trim($m[1]);
    }
    $props = ['robots', 'description', 'og:title', 'og:description', 'og:url', 'og:type', 'og:site_name', 'twitter:card', 'twitter:title', 'twitter:description'];
    foreach ($props as $p) {
        if (preg_match('/<meta[^>]+(?:name|property)=["\']' . preg_quote($p, '/') . '["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m) ||
            preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:name|property)=["\']' . preg_quote($p, '/') . '["\']/is', $head, $m)) {
            $tags[$p] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
    return $tags;
}

function normalizeNodeCutover(mixed $node): mixed {
    if (is_array($node)) {
        ksort($node);
        foreach ($node as $k => $v) {
            if ($k === '@id' && is_string($v)) {
                $node[$k] = preg_replace('/#schema-\d+$/', '#schema-custom', $v);
            } else {
                $node[$k] = normalizeNodeCutover($v);
            }
        }
    }
    return $node;
}

foreach ($sampleUrls as $label => $path) {
    echo sprintf("%-20s (%s) ... ", $label, $path);
    $rm = runCutoverHead($path, $phpBinary, $socket, $docRoot, false);
    $laca = runCutoverHead($path, $phpBinary, $socket, $docRoot, true);

    $rmTags = parseMetaTags($rm['head']);
    $lacaTags = parseMetaTags($laca['head']);

    $diffs = [];
    foreach ($rmTags as $k => $v) {
        $lv = $lacaTags[$k] ?? '';
        if ($k === 'robots') {
            $rParts = array_map('trim', explode(',', $v)); sort($rParts);
            $lParts = array_map('trim', explode(',', $lv)); sort($lParts);
            if ($rParts !== $lParts) {
                $diffs[] = "Robots mismatch: RM={$v} vs Laca={$lv}";
            }
        } elseif ($k === 'description' || $k === 'og:description' || $k === 'twitter:description') {
            if (trim(strip_tags($v)) !== trim(strip_tags($lv))) {
                $diffs[] = "{$k} mismatch: RM={$v} vs Laca={$lv}";
            }
        } else {
            if ($v !== $lv) {
                $diffs[] = "{$k} mismatch: RM={$v} vs Laca={$lv}";
            }
        }
    }

    $rmGraph = $rm['json']['@graph'] ?? [];
    $lacaGraph = $laca['json']['@graph'] ?? [];

    if (count($rmGraph) !== count($lacaGraph)) {
        $diffs[] = "JSON-LD graph count mismatch: RM=" . count($rmGraph) . " vs Laca=" . count($lacaGraph);
    } else {
        foreach ($rmGraph as $idx => $rmNode) {
            $lNode = $lacaGraph[$idx] ?? null;
            if (!$lNode || normalizeNodeCutover($rmNode) !== normalizeNodeCutover($lNode)) {
                $type = $rmNode['@type'] ?? 'Unknown';
                if (is_array($type)) $type = implode('/', $type);
                $diffs[] = "JSON-LD node {$idx} ({$type}) diff";
            }
        }
    }

    if (empty($diffs)) {
        echo "[PASS] (Head meta & JSON-LD " . count($lacaGraph) . " nodes match RM)\n";
    } else {
        echo "[FAIL]\n  - " . implode("\n  - ", array_slice($diffs, 0, 5)) . "\n";
        $allPassed = false;
    }
}

echo "\n========================================================================\n";
if ($allPassed) {
    echo " RESULT: ALL CUTOVER SIMULATION CHECKS PASSED (100% ROUTER, HEAD & GRAPH PARITY)\n";
} else {
    echo " RESULT: CUTOVER SIMULATION FAILED - SEE DETAILS ABOVE\n";
}
echo "========================================================================\n";
