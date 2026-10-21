<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web.
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
// php render.php <path> <rm|laca>  → in ra <head> đã render (blog_public=1 + RM general đã sửa, chỉ trong bộ nhớ)
[$_, $path, $mode] = $argv;
$GLOBALS['wp_filter'] = [
  'pre_option_blog_public' => [10 => [['function' => fn() => '1', 'accepted_args' => 1]]],
  'pre_option_rank-math-options-general' => [10 => [['function' => function ($pre) {
      global $wpdb; $raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='rank-math-options-general'");
      $f = preg_replace_callback('!s:(\d+):"(.*?)";!s', fn($m) => 's:' . strlen($m[2]) . ':"' . $m[2] . '";', $raw);
      $v = @unserialize($f); return is_array($v) ? $v : $pre; }, 'accepted_args' => 1]]],
];
$q = parse_url($path, PHP_URL_QUERY) ?: '';
$_SERVER['HTTP_HOST'] = 'lixroastery.local'; $_SERVER['SERVER_NAME'] = 'lixroastery.local'; $_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $path; $_GET = []; parse_str($q, $_GET);
if ($mode === 'laca') { $_GET['laca_seo_force_frontend'] = '1'; $_SERVER['REQUEST_URI'] .= ($q ? '&' : '?') . 'laca_seo_force_frontend=1'; }
define('WP_USE_THEMES', false);
require dirname(__DIR__, 6) . '/wp-load.php';
wp();
ob_start(); do_action('template_redirect'); ob_end_clean();
ob_start(); wp_head(); echo ob_get_clean();
