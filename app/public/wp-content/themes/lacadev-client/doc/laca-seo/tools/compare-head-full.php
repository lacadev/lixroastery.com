<?php
// So <head> + JSON-LD Rank Math vs Laca trên 19 URL (mỗi URL 1 tiến trình, blog_public=1 + option RM general đã sửa trong bộ nhớ).
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
$PHP = getenv('HOME') . '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$SOCK = getenv('HOME') . '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
$paths = ['/','/en/','/manifesto/','/product/aconchego-1-masterpieces-auction-2025/','/journal/extremely-light-cua-lix-roastery/','/journal/lix-roastery-at-cafe-show-hanoi-2026/','/shop/','/en/shop/','/journal/','/journal/page/2/','/category/uncategorized/','/product-category/ca-phe-specialty/','/journal-cat/find-the-origin/','/journal-cat/find-the-origin/page/2/','/tag/arabica/','/journal/lix-roastery-tai-cafe-show-hanoi-2026/cafe-show-4/','/author/lacadev/','/?s=cafe','/url-khong-ton-tai-404-test/'];
if (!empty($argv[1])) $paths = explode(',', $argv[1]);
function run($p, $m) { global $PHP, $SOCK; return shell_exec(escapeshellarg($PHP) . ' -d memory_limit=1024M -d error_reporting=0 -d display_errors=0 -d mysqli.default_socket=' . escapeshellarg($SOCK) . ' ' . escapeshellarg(__DIR__ . '/render-head.php') . ' ' . escapeshellarg($p) . ' ' . $m . ' 2>/dev/null'); }
function tags($h) {
  if (!is_string($h) || $h === '') return [[], []];
  $t = [];
  if (preg_match('~<title>(.*?)</title>~s', $h, $m)) $t['title'] = trim($m[1]);
  preg_match_all('~<meta\s+(?:name|property)="([^"]+)"\s+content="([^"]*)"~', $h, $mm, PREG_SET_ORDER);
  foreach ($mm as $x) { if (preg_match('~^(robots|description|og:|twitter:|article:|product:|fb:)~', $x[1])) $t[$x[1]][] = $x[2]; }
  preg_match_all('~<link rel="(canonical|prev|next)" href="([^"]+)"~', $h, $mm, PREG_SET_ORDER); foreach ($mm as $x) $t['link:' . $x[1]] = $x[2];
  $ld = []; preg_match_all('~<script type="application/ld\+json"[^>]*>(.*?)</script>~s', $h, $mm);
  foreach ($mm[1] as $j) { $d = json_decode($j, true); foreach (($d['@graph'] ?? [$d]) as $n) { $k = ($n['@id'] ?? '') . '|' . json_encode($n['@type'] ?? ''); $ld[$k] = $n; } }
  return [$t, $ld];
}
function norm($v) { if (is_array($v)) { if (array_keys($v) !== range(0, count($v) - 1)) ksort($v); return array_map('norm', $v); } return $v; }
if (isset($t['robots'])) {}
$bad = 0;
foreach ($paths as $p) {
  [$ta, $la] = tags(run($p, 'rm')); [$tb, $lb] = tags(run($p, 'laca'));
  $d = [];
  foreach (array_unique(array_merge(array_keys($ta), array_keys($tb))) as $k) {
    $a = $ta[$k] ?? null; $b = $tb[$k] ?? null;
    if ($k === 'robots') { $s = fn($x) => $x ? (function ($v) { $v = array_map('trim', explode(',', $v[0])); sort($v); return $v; })($x) : null; $a = $s($a); $b = $s($b); }
    if ($a !== $b) $d[] = "$k: RM=" . json_encode($a, 320) . " | Laca=" . json_encode($b, 320);
  }
  foreach (array_unique(array_merge(array_keys($la), array_keys($lb))) as $k) {
    $a = isset($la[$k]) ? norm($la[$k]) : null; $b = isset($lb[$k]) ? norm($lb[$k]) : null;
    if ($a !== $b) $d[] = "LD $k: RM=" . substr(json_encode($a, 320), 0, 300) . " | Laca=" . substr(json_encode($b, 320), 0, 300);
  }
  printf("%-55s RMnodes=%d Lacanodes=%d diffs=%d\n", $p, count($la), count($lb), count($d)); foreach ($d as $x) echo "   $x\n"; if ($d) $bad++;
}
echo "BAD URLS: $bad\n";
