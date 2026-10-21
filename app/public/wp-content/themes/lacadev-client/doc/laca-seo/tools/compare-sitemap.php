<?php
// Công cụ dev chạy bằng PHP-CLI — so sánh sitemap giữa Rank Math và Laca SEO (Phase 3A - R1)
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 6) . '/wp-load.php';

echo "========================================================================\n";
echo " LACA SEO vs RANK MATH - SITEMAP COMPARISON TOOL (Phase 3A - R1)\n";
echo "========================================================================\n\n";

function get_url(string $url): array {
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HEADER         => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
    ]);
    $b = curl_exec($c);
    $s = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $t = curl_getinfo($c, CURLINFO_CONTENT_TYPE);
    curl_close($c);
    return [$s, (string) $b, (string) $t];
}

function parse_sitemap(string $x): ?array {
    libxml_use_internal_errors(true);
    $d = simplexml_load_string($x);
    if (!$d) {
        return null;
    }
    $o = [];
    $ns = $d->getNamespaces(true);

    if ($d->getName() === 'sitemapindex') {
        foreach ($d->sitemap as $s) {
            $o[(string) $s->loc] = [
                'lastmod' => (string) $s->lastmod,
            ];
        }
        return $o;
    }

    foreach ($d->url as $u) {
        $im = [];
        if (isset($ns['image'])) {
            foreach ($u->children($ns['image'])->image as $i) {
                $im[] = (string) $i->loc;
            }
        }
        $o[(string) $u->loc] = [
            'lastmod' => (string) $u->lastmod,
            'img'     => $im,
        ];
    }
    return $o;
}

$host = rtrim(home_url(), '/');
[$sIndexRm, $bIndexRm] = get_url("{$host}/sitemap_index.xml");
$rmIdx = parse_sitemap($bIndexRm);

[$sIndexLa, $bIndexLa, $tIndexLa] = get_url("{$host}/?laca_seo_force_frontend=1&laca_sitemap=index");
$laIdx = parse_sitemap($bIndexLa);

echo "INDEX RM: " . count($rmIdx ?? []) . " sub-sitemaps | Laca: " . count($laIdx ?? []) . " ($sIndexLa $tIndexLa)\n";

if ($rmIdx === null || $laIdx === null) {
    echo "ERROR: Failed to parse sitemap index XML.\n";
    exit(1);
}

$indexMatches = true;
foreach ($rmIdx as $loc => $v) {
    $l = $laIdx[$loc] ?? null;
    $status = 'OK';
    if (!$l) {
        $status = 'MISSING IN LACA';
        $indexMatches = false;
    } elseif ($l['lastmod'] !== $v['lastmod']) {
        $status = 'LASTMOD DIFF (RM: ' . $v['lastmod'] . ', Laca: ' . $l['lastmod'] . ')';
        $indexMatches = false;
    }
    echo "  " . basename($loc) . " -> " . $status . "\n";
}

foreach (array_diff_key($laIdx, $rmIdx) as $loc => $v) {
    echo "  EXTRA in Laca index: {$loc}\n";
    $indexMatches = false;
}

$totalDiffs = 0;

foreach ($rmIdx as $loc => $v) {
    if (!preg_match('~/([^/]+?)-sitemap([0-9]+)?\.xml$~', $loc, $m)) {
        echo "Skip non-conforming sub-sitemap: {$loc}\n";
        continue;
    }

    $type = $m[1];
    $n = !empty($m[2]) ? (int) $m[2] : 1;

    [$rmStatus, $rmBody] = get_url($loc);
    [$laStatus, $laBody] = get_url("{$host}/?laca_seo_force_frontend=1&laca_sitemap={$type}&laca_sitemap_n={$n}");

    $R = parse_sitemap($rmBody);
    $L = parse_sitemap($laBody);

    if ($L === null) {
        echo "\n## {$type}-{$n}: Laca returned invalid XML (HTTP {$laStatus})\n";
        $totalDiffs++;
        continue;
    }

    if ($R === null) {
        echo "\n## {$type}-{$n}: Rank Math returned invalid XML (HTTP {$rmStatus})\n";
        $totalDiffs++;
        continue;
    }

    $diffs = [];
    foreach ($R as $u => $x) {
        if (!isset($L[$u])) {
            $diffs[] = "Missing URL: {$u}";
            continue;
        }
        if ($x['lastmod'] !== $L[$u]['lastmod']) {
            $diffs[] = "Lastmod diff: {$u} (RM: {$x['lastmod']}, Laca: {$L[$u]['lastmod']})";
        }
        if ($x['img'] !== $L[$u]['img']) {
            $diffs[] = "Images diff: {$u} (RM: " . count($x['img']) . " images, Laca: " . count($L[$u]['img']) . " images)";
        }
    }

    foreach (array_diff_key($L, $R) as $u => $x) {
        $diffs[] = "Extra URL in Laca: {$u}";
    }

    if (array_keys($R) !== array_keys(array_intersect_key($L, $R))) {
        $diffs[] = "URL ordering differs from Rank Math";
    }

    echo "\n## Sub-sitemap {$type}-{$n} (RM: " . count($R) . ", Laca: " . count($L) . ") - Diffs: " . count($diffs) . "\n";
    foreach (array_slice($diffs, 0, 10) as $d) {
        echo "   [DIFF] {$d}\n";
    }

    $totalDiffs += count($diffs);
}

// Check XSL Stylesheet
[$sXsl, $bodyXsl, $tXsl] = get_url("{$host}/?laca_seo_force_frontend=1&laca_sitemap=xsl");
echo "\nXSL Stylesheet Laca: HTTP {$sXsl} {$tXsl}, Length: " . strlen($bodyXsl) . " bytes\n";

echo "\n========================================================================\n";
if ($totalDiffs === 0 && $indexMatches) {
    echo " RESULT: ALL SITEMAP CHECKS PASSED (100% PARITY WITH RANK MATH, 0 DIFFS)\n";
} else {
    echo " RESULT: FAILED! Total differences found: {$totalDiffs}\n";
}
echo "========================================================================\n";
