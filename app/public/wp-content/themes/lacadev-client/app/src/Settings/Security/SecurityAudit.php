<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Audit
 *
 * Đánh giá tổng thể bảo mật site: WP config, login, PHP, HTTP headers...
 * Port từ foxblock_security_audit_callback() trong Foxblock/admin/admin.php.
 */
class SecurityAudit
{
    public static function run(): array
    {
        $checks = [];
        $score  = 0;
        $max    = 0;

        $add = function (string $cat, string $id, string $title, string $status, string $desc, int $points, string $fix = '') use (&$checks, &$score, &$max) {
            if ($status === 'pass') $score += $points;
            if ($status !== 'info') $max   += $points;
            $checks[] = compact('cat', 'id', 'title', 'status', 'desc', 'points', 'fix');
        };

        // ── 1. WordPress Core ─────────────────────────────────────────────────
        $cat = 'WordPress Core';

        $debugOn = defined('WP_DEBUG') && WP_DEBUG;
        $add($cat, 'wp_debug', 'WP_DEBUG tắt trên môi trường production',
            $debugOn ? 'fail' : 'pass',
            $debugOn ? 'WP_DEBUG đang BẬT — lỗi nhạy cảm có thể bị hiển thị công khai.'
                     : 'WP_DEBUG đã tắt. An toàn.',
            6);

        $debugLog = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $add($cat, 'wp_debug_log', 'WP_DEBUG_LOG tắt',
            $debugLog ? 'warn' : 'pass',
            $debugLog ? 'Debug log đang ghi ra file — debug.log có thể truy cập công khai.'
                      : 'Debug logging không được bật.',
            4);

        $wpVer      = get_bloginfo('version');
        $coreUpdate = get_site_transient('update_core');
        $needsUpdate= false;
        if (isset($coreUpdate->updates) && is_array($coreUpdate->updates)) {
            foreach ($coreUpdate->updates as $u) {
                if ($u->response === 'upgrade') { $needsUpdate = true; break; }
            }
        }
        $add($cat, 'wp_version', 'WordPress đã cập nhật',
            $needsUpdate ? 'fail' : 'pass',
            $needsUpdate ? "WordPress $wpVer có bản cập nhật bảo mật. Cập nhật ngay!"
                         : "WordPress $wpVer là phiên bản mới nhất.",
            8, admin_url('update-core.php'));

        $fileEditOff = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $add($cat, 'file_edit', 'Trình soạn thảo code admin bị tắt',
            $fileEditOff ? 'pass' : 'warn',
            $fileEditOff ? 'DISALLOW_FILE_EDIT = true. Attacker không thể sửa plugin/theme qua admin.'
                         : 'Trình soạn thảo đang bật — nên tắt trong wp-config.php.',
            5);

        $isSsl = is_ssl();
        $add($cat, 'ssl', 'HTTPS / SSL đang hoạt động',
            $isSsl ? 'pass' : 'fail',
            $isSsl ? 'Website dùng HTTPS. Kết nối được mã hóa.'
                   : 'Website chưa dùng HTTPS — dữ liệu đăng nhập có thể bị đánh cắp.',
            8);

        $adminExists = username_exists('admin');
        $add($cat, 'admin_user', 'Không tồn tại user "admin" mặc định',
            $adminExists ? 'fail' : 'pass',
            $adminExists ? 'User "admin" vẫn tồn tại — đây là mục tiêu brute force phổ biến nhất.'
                         : 'Không có user tên "admin". Tốt.',
            7, admin_url('users.php'));

        global $wpdb;
        $dbSafe = ($wpdb->prefix !== 'wp_');
        $add($cat, 'db_prefix', 'Tiền tố database không phải "wp_"',
            $dbSafe ? 'pass' : 'warn',
            $dbSafe ? "Tiền tố database là \"{$wpdb->prefix}\" — tốt hơn mặc định."
                    : 'Tiền tố database vẫn là "wp_" — dễ bị nhắm mục tiêu hơn.',
            4);

        // ── 2. Custom Login (theme feature) ─────────────────────────────────────
        $cat = 'Bảo mật Đăng nhập';

        $loginSlug   = get_option('laca_login_slug', '');
        $loginHidden = !empty($loginSlug) && get_option('laca_enable_custom_login', 0);
        $add($cat, 'custom_login', 'URL đăng nhập ẩn (không phải /wp-login.php)',
            $loginHidden ? 'pass' : 'warn',
            $loginHidden ? "URL login tùy chỉnh: /$loginSlug"
                         : 'URL /wp-login.php mặc định còn active — dễ bị bot brute force tìm thấy.',
            6, admin_url('themes.php?page=laca-security'));

        $twoFaOn = get_option('laca_2fa_master_enabled', 0);
        $add($cat, '2fa', 'Xác thực 2 bước (2FA TOTP) được bật',
            $twoFaOn ? 'pass' : 'warn',
            $twoFaOn ? '2FA đang bật — tài khoản được bảo vệ kể cả khi mất mật khẩu.'
                     : '2FA chưa bật — nên bật để tăng cường bảo mật.',
            6, admin_url('themes.php?page=laca-security'));

        // ── 3. Access Control & Privacy ──────────────────────────────────────
        $cat = 'Kiểm soát truy cập & Riêng tư';

        // REST API
        $restRestricted = has_filter('rest_authentication_errors') || (defined('REST_RESTRICTED') && REST_RESTRICTED);
        $add($cat, 'rest_api', 'REST API bị hạn chế với khách',
            $restRestricted ? 'pass' : 'warn',
            $restRestricted ? 'REST API đã hạn chế cho người dùng chưa đăng nhập.'
                            : 'REST API công khai — có thể dùng để liệt kê user/nội dung.',
            5);

        // xmlrpc
        $xmlrpcOff = !apply_filters('xmlrpc_enabled', true);
        $add($cat, 'xmlrpc', 'XML-RPC tắt',
            $xmlrpcOff ? 'pass' : 'fail',
            $xmlrpcOff ? 'XML-RPC đã tắt hoàn toàn.'
                       : 'XML-RPC còn bật và thường bị nhắm mục tiêu brute force.',
            7);

        // Author enumeration
        $authorEnumBlocked = has_filter('author_request', function () {}) === false
            && get_option('laca_block_author_enum', 0);
        $add($cat, 'user_enum', 'Chặn liệt kê username (?author=)',
            $authorEnumBlocked ? 'pass' : 'warn',
            $authorEnumBlocked ? '?author=N đã bị chặn.'
                               : '?author=N có thể dùng để lộ tên đăng nhập.',
            5);

        // ── 4. Server & PHP ───────────────────────────────────────────────────
        $cat = 'Server & PHP';

        $phpVer   = phpversion();
        $phpMajor = (int) PHP_MAJOR_VERSION;
        $phpMinor = (int) PHP_MINOR_VERSION;
        $phpOk    = ($phpMajor > 8) || ($phpMajor === 8 && $phpMinor >= 1);
        $add($cat, 'php_version', 'PHP >= 8.1',
            $phpOk ? 'pass' : ($phpMajor >= 7 ? 'warn' : 'fail'),
            $phpOk ? "PHP $phpVer đang được hỗ trợ bảo mật."
                   : "PHP $phpVer không còn được hỗ trợ. Nên nâng cấp PHP 8.1+.",
            6);

        $dispErrors = ini_get('display_errors');
        $dispOff    = (!$dispErrors || in_array($dispErrors, ['Off', '0', ''], true));
        $add($cat, 'display_errors', 'display_errors tắt',
            $dispOff ? 'pass' : 'fail',
            $dispOff ? 'display_errors đã tắt trên server.'
                     : 'display_errors đang BẬT — lỗi PHP có thể lộ thông tin nhạy cảm.',
            5);

        // ── 5. HTTP Security Headers ──────────────────────────────────────────
        $cat = 'HTTP Security Headers';

        $respHeaders = [];
        $httpResp = wp_remote_get(trailingslashit(home_url()), [
            'timeout'   => 8,
            'sslverify' => false,
            'user-agent'=> 'LacaSecurityAudit/1.0',
        ]);
        if (!is_wp_error($httpResp)) {
            foreach (wp_remote_retrieve_headers($httpResp) as $name => $val) {
                $respHeaders[strtolower($name)] = is_array($val) ? implode(', ', $val) : $val;
            }
        }

        $has = fn($h) => !empty($respHeaders[strtolower($h)]);
        $val = fn($h) => $respHeaders[strtolower($h)] ?? '';

        $xfo = $has('x-frame-options');
        $add($cat, 'header_xfo', 'X-Frame-Options (chống Clickjacking)',
            $xfo ? 'pass' : 'fail',
            $xfo ? 'X-Frame-Options: ' . $val('x-frame-options') . '. Chống nhúng iframe.'
                 : 'Chưa có X-Frame-Options — website có thể bị nhúng trong iframe.',
            5);

        $xcto = $has('x-content-type-options');
        $add($cat, 'header_xcto', 'X-Content-Type-Options: nosniff',
            $xcto ? 'pass' : 'fail',
            $xcto ? 'X-Content-Type-Options: nosniff. Trình duyệt không đoán MIME type.'
                  : 'Chưa có X-Content-Type-Options — trình duyệt có thể hiểu sai loại file.',
            4);

        $hsts = $has('strict-transport-security');
        $add($cat, 'header_hsts', 'Strict-Transport-Security / HSTS',
            $hsts ? 'pass' : ($isSsl ? 'warn' : 'info'),
            $hsts ? 'HSTS: ' . $val('strict-transport-security') . '. Trình duyệt chỉ dùng HTTPS.'
                  : ($isSsl ? 'Website dùng HTTPS nhưng chưa có HSTS.'
                             : 'HSTS chỉ hoạt động sau khi bật HTTPS.'),
            5);

        $csp = $has('content-security-policy') || $has('content-security-policy-report-only');
        $add($cat, 'header_csp', 'Content-Security-Policy (CSP)',
            $csp ? 'pass' : 'warn',
            $csp ? 'CSP đã được cấu hình và kiểm soát nguồn script/style.'
                 : 'Chưa có CSP — rủi ro XSS cao hơn.',
            5);

        $rp = $has('referrer-policy');
        $add($cat, 'header_rp', 'Referrer-Policy',
            $rp ? 'pass' : 'warn',
            $rp ? 'Referrer-Policy: ' . $val('referrer-policy') . '.'
                : 'Chưa có Referrer-Policy — URL có thể bị gửi cho bên thứ ba.',
            3);

        // ── Tổng điểm ────────────────────────────────────────────────────────
        $finalScore = $max > 0 ? round(($score / $max) * 100) : 0;

        $byCategory = [];
        foreach ($checks as $c) {
            $byCategory[$c['cat']][] = $c;
        }

        return [
            'score'   => $finalScore,
            'pass'    => count(array_filter($checks, fn($c) => $c['status'] === 'pass')),
            'fail'    => count(array_filter($checks, fn($c) => $c['status'] === 'fail')),
            'warn'    => count(array_filter($checks, fn($c) => $c['status'] === 'warn')),
            'info'    => count(array_filter($checks, fn($c) => $c['status'] === 'info')),
            'total'   => count($checks),
            'groups'  => $byCategory,
            'php_ver' => phpversion(),
            'wp_ver'  => get_bloginfo('version'),
        ];
    }
}
