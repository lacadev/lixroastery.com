<?php
/**
 * Test Settings Diff normalization for Discord Hub.
 *
 * Chạy: php -d mysqli.default_socket=... doc/admin-ui/tools/test-settings-diff.php
 *
 * @package LacaDevClient
 */

define('WP_USE_THEMES', false);
$wp_load = dirname(__DIR__, 6) . '/wp-load.php';
if (file_exists($wp_load)) {
    require_once $wp_load;
} else {
    require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
}

use App\Features\DiscordHub\Monitors\SettingsMonitor;

$cases = [
    ['name' => '(null, "")', 'a' => null, 'b' => '', 'expected_same' => true],
    ['name' => '(false, "no")', 'a' => false, 'b' => 'no', 'expected_same' => true],
    ['name' => '("0", 0)', 'a' => '0', 'b' => 0, 'expected_same' => true],
    ['name' => '(["a"], ["a"])', 'a' => ['a'], 'b' => ['a'], 'expected_same' => true],
    ['name' => '("a", "b")', 'a' => 'a', 'b' => 'b', 'expected_same' => false],
];

$pass = 0;
$fail = 0;

foreach ($cases as $c) {
    $actual_same = SettingsMonitor::isSameValue($c['a'], $c['b']);
    $ok = ($actual_same === $c['expected_same']);
    if ($ok) {
        $pass++;
        echo "PASS {$c['name']}\n";
    } else {
        $fail++;
        echo "FAIL {$c['name']}: expected same=" . ($c['expected_same'] ? 'true' : 'false') . ", got " . ($actual_same ? 'true' : 'false') . "\n";
    }
}

echo "SUMMARY pass={$pass} fail={$fail}\n";
exit($fail === 0 ? 0 : 1);
