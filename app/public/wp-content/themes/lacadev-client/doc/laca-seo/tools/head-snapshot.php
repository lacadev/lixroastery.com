<?php
// Công cụ regression snapshot cho <head> SEO của Laca SEO (19 URLs chuẩn).
// Chặn truy cập trực tiếp qua web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$PHP = getenv('HOME') . '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$SOCK = getenv('HOME') . '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';

$paths = [
    '/',
    '/en/',
    '/manifesto/',
    '/product/aconchego-1-masterpieces-auction-2025/',
    '/journal/extremely-light-cua-lix-roastery/',
    '/journal/lix-roastery-at-cafe-show-hanoi-2026/',
    '/shop/',
    '/en/shop/',
    '/journal/',
    '/journal/page/2/',
    '/category/uncategorized/',
    '/product-category/ca-phe-specialty/',
    '/journal-cat/find-the-origin/',
    '/journal-cat/find-the-origin/page/2/',
    '/tag/arabica/',
    '/journal/lix-roastery-tai-cafe-show-hanoi-2026/cafe-show-4/',
    '/author/lacadev/',
    '/?s=cafe',
    '/url-khong-ton-tai-404-test/',
];

$isSave = in_array('--save', $argv, true);
$customPaths = array_values(array_filter($argv, fn($a, $k) => $k > 0 && $a !== '--save', ARRAY_FILTER_USE_BOTH));
if (!empty($customPaths)) {
    $paths = explode(',', $customPaths[0]);
}

$goldenDir = __DIR__ . '/head-golden';
if (!is_dir($goldenDir)) {
    mkdir($goldenDir, 0755, true);
}

function pathToSlug(string $p): string {
    if ($p === '/') {
        return 'home';
    }
    $p = trim($p, '/');
    $p = str_replace('?s=', 'search-', $p);
    $p = preg_replace('~[^a-zA-Z0-9_\-]+~', '-', $p);
    return trim($p, '-');
}

function renderHead(string $p): string {
    global $PHP, $SOCK;
    $cmd = escapeshellarg($PHP)
        . ' -d memory_limit=1024M -d error_reporting=0 -d display_errors=0'
        . ' -d mysqli.default_socket=' . escapeshellarg($SOCK)
        . ' ' . escapeshellarg(__DIR__ . '/render-head.php')
        . ' ' . escapeshellarg($p)
        . ' laca 2>/dev/null';
    return (string) shell_exec($cmd);
}

function extractSeoHead(string $html): string {
    if (trim($html) === '') {
        return "<!-- EMPTY OR REDIRECT -->\n";
    }

    $out = [];
    if (preg_match('~<title>(.*?)</title>~s', $html, $tm)) {
        $out[] = '<title>' . trim($tm[1]) . '</title>';
    }

    if (preg_match('~<!-- / SEO by Laca SEO - Professional Framework -->.*?<!-- / Laca SEO -->~s', $html, $sm)) {
        $block = $sm[0];
        // Pretty-print JSON-LD script inside if present
        $block = preg_replace_callback('~<script type="application/ld\+json"[^>]*>(.*?)</script>~s', function ($m) {
            $data = json_decode($m[1], true);
            if (is_array($data)) {
                $pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return '<script type="application/ld+json" class="laca-seo-schema">' . "\n" . $pretty . "\n</script>";
            }
            return $m[0];
        }, $block);
        $out[] = trim($block);
    } else {
        // Fallback: extract any meta description, robots, canonical, og, twitter
        preg_match_all('~<(?:meta|link)[^>]+>~i', $html, $tags);
        foreach ($tags[0] as $tag) {
            if (preg_match('~(robots|description|canonical|og:|twitter:|article:|product:|fb:)~i', $tag)) {
                $out[] = $tag;
            }
        }
    }

    $result = implode("\n", $out) . "\n";

    // Normalizations: dynamic nonce, ver/timestamp
    $result = preg_replace('~nonce="[^"]+"~i', 'nonce="[nonce]"', $result);
    $result = preg_replace('~"nonce":\s*"[^"]+"~i', '"nonce": "[nonce]"', $result);
    $result = preg_replace('~_wpnonce=[a-zA-Z0-9]+~i', '_wpnonce=[nonce]', $result);
    $result = preg_replace('~([?&])ver=[a-zA-Z0-9_\.-]+~i', '$1ver=[ver]', $result);

    return $result;
}

function getShortDiff(string $golden, string $actual, int $maxLines = 8): string {
    $gLines = explode("\n", $golden);
    $aLines = explode("\n", $actual);
    $diff = [];
    $max = max(count($gLines), count($aLines));
    for ($i = 0; $i < $max; $i++) {
        $g = $gLines[$i] ?? '<EOF>';
        $a = $aLines[$i] ?? '<EOF>';
        if ($g !== $a) {
            $diff[] = '   L' . ($i + 1) . ' -: ' . substr($g, 0, 100);
            $diff[] = '   L' . ($i + 1) . ' +: ' . substr($a, 0, 100);
            if (count($diff) >= $maxLines * 2) {
                $diff[] = '   ... (diff truncated)';
                break;
            }
        }
    }
    return implode("\n", $diff);
}

if ($isSave) {
    echo "Saving snapshots to $goldenDir ...\n";
    $count = 0;
    foreach ($paths as $p) {
        $slug = pathToSlug($p);
        $file = "$goldenDir/$slug.txt";
        $html = renderHead($p);
        $seo = extractSeoHead($html);
        file_put_contents($file, $seo);
        printf("  [SAVED] %-50s -> %s.txt (%d bytes)\n", $p, $slug, strlen($seo));
        $count++;
    }
    echo "Done. Saved $count golden snapshots.\n";
    exit(0);
}

// Compare mode (default)
$pass = 0;
$fail = 0;

foreach ($paths as $p) {
    $slug = pathToSlug($p);
    $file = "$goldenDir/$slug.txt";
    if (!file_exists($file)) {
        printf("FAIL %-50s (Golden snapshot missing: %s.txt)\n", $p, $slug);
        $fail++;
        continue;
    }

    $golden = (string) file_get_contents($file);
    $actualHtml = renderHead($p);
    $actual = extractSeoHead($actualHtml);

    if ($golden === $actual) {
        printf("PASS %-50s\n", $p);
        $pass++;
    } else {
        printf("FAIL %-50s\n", $p);
        echo getShortDiff($golden, $actual) . "\n";
        $fail++;
    }
}

echo "\n================ SUMMARY ================\n";
printf("Total: %d | Pass: %d | Fail: %d\n", count($paths), $pass, $fail);
echo "SUMMARY pass={$pass} fail={$fail}\n";

exit($fail > 0 ? 1 : 0);
