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
    public const TRANSIENT_KEY = 'laca_security_audit_result';

    public static function getCached(): ?array
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        return is_array($cached) ? $cached : null;
    }

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

        $coreUpdate   = get_site_transient('update_core');
        $pluginUpdate = get_site_transient('update_plugins');
        $themeUpdate  = get_site_transient('update_themes');

        $hasCoreUpdate = false;
        if (isset($coreUpdate->updates) && is_array($coreUpdate->updates)) {
            foreach ($coreUpdate->updates as $u) {
                if (isset($u->response) && $u->response === 'upgrade') {
                    $hasCoreUpdate = true;
                    break;
                }
            }
        }

        $pendingPlugins = [];
        if (!empty($pluginUpdate->response) && is_array($pluginUpdate->response)) {
            foreach ($pluginUpdate->response as $pluginFile => $pData) {
                $pendingPlugins[] = is_object($pData) && !empty($pData->Name) ? $pData->Name : dirname($pluginFile);
            }
        }
        $pendingThemes = [];
        if (!empty($themeUpdate->response) && is_array($themeUpdate->response)) {
            foreach ($themeUpdate->response as $themeSlug => $tData) {
                $pendingThemes[] = is_object($tData) && !empty($tData->name) ? $tData->name : $themeSlug;
            }
        }

        $pendingItems = array_merge($pendingPlugins, $pendingThemes);

        if ($hasCoreUpdate) {
            $add($cat, 'pending_updates', __('Cập nhật hệ thống và gói mở rộng', 'laca'), 'fail',
                __('WordPress có bản cập nhật mới. Bạn nên nâng cấp ngay để vá các lỗ hổng bảo mật.', 'laca'),
                8, admin_url('update-core.php'));
        } elseif (!empty($pendingItems)) {
            $listStr = implode(', ', array_slice($pendingItems, 0, 10));
            $add($cat, 'pending_updates', __('Cập nhật hệ thống và gói mở rộng', 'laca'), 'warn',
                sprintf(__('Có %d plugin hoặc theme có bản cập nhật mới: %s.', 'laca'), count($pendingItems), $listStr),
                6, admin_url('update-core.php'));
        } else {
            $add($cat, 'pending_updates', __('Cập nhật hệ thống và gói mở rộng', 'laca'), 'pass',
                __('WordPress, các plugin và theme đều đang ở phiên bản mới nhất.', 'laca'),
                8);
        }

        // File nhạy cảm bị lộ
        $cachedSensitive = get_transient('laca_audit_sensitive_files');
        if (false === $cachedSensitive || !is_array($cachedSensitive)) {
            $candidates = [
                'wp-config.php.bak',
                'wp-config.bak',
                'wp-config.old',
                'wp-config.php~',
                'wp-config.php.save',
                'wp-config.php.swp',
                '.wp-config.php.swp',
                'wp-config.txt',
                '.env',
                'phpinfo.php',
                'info.php',
                'adminer.php',
                '.git/HEAD',
            ];
            foreach (['*.sql', '*.sql.gz', '*.zip', '*.tar.gz'] as $pattern) {
                $matches = glob(ABSPATH . $pattern);
                if (is_array($matches)) {
                    foreach ($matches as $m) {
                        $candidates[] = basename($m);
                    }
                }
            }
            $contentDir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
            if (file_exists($contentDir . '/debug.log')) {
                $candidates[] = 'wp-content/debug.log';
            }
            $matchesContent = glob($contentDir . '/*.sql');
            if (is_array($matchesContent)) {
                foreach ($matchesContent as $m) {
                    $candidates[] = 'wp-content/' . basename($m);
                }
            }

            $candidates = array_values(array_unique(apply_filters('laca_audit_sensitive_candidates', $candidates)));

            $foundPublic = [];
            $foundLocal  = [];
            $testedCount = 0;

            foreach ($candidates as $cand) {
                if ($testedCount >= 20) {
                    break;
                }
                $filePath = (str_starts_with($cand, 'wp-content/') || str_starts_with($cand, '/'))
                    ? (str_starts_with($cand, '/') ? $cand : ABSPATH . $cand)
                    : ABSPATH . $cand;

                if (file_exists($filePath)) {
                    $testedCount++;
                    $fileUrl = str_starts_with($cand, '/') ? home_url(basename($cand)) : home_url($cand);
                    $headResp = wp_remote_head($fileUrl, [
                        'timeout'     => 5,
                        'sslverify'   => false,
                        'redirection' => 0,
                        'user-agent'  => 'WordPress; laca-theme',
                    ]);
                    $code = is_wp_error($headResp) ? 0 : (int) wp_remote_retrieve_response_code($headResp);
                    $filename = basename($cand);
                    if ($code === 200) {
                        $foundPublic[] = $filename;
                    } else {
                        $foundLocal[] = $filename;
                    }
                }
            }

            $cachedSensitive = [
                'public' => $foundPublic,
                'local'  => $foundLocal,
            ];
            set_transient('laca_audit_sensitive_files', $cachedSensitive, HOUR_IN_SECONDS);
        }

        if (!empty($cachedSensitive['public'])) {
            $filesStr = implode(', ', array_slice($cachedSensitive['public'], 0, 10));
            $add($cat, 'sensitive_files', __('Không để lộ file nhạy cảm và file sao lưu', 'laca'), 'fail',
                sprintf(__('CẢNH BÁO NGUY HIỂM: Các file nhạy cảm đang có thể truy cập công khai từ bên ngoài: %s. Hãy xoá hoặc chặn truy cập ngay lập tức!', 'laca'), $filesStr),
                8);
        } elseif (!empty($cachedSensitive['local'])) {
            $filesStr = implode(', ', array_slice($cachedSensitive['local'], 0, 10));
            $add($cat, 'sensitive_files', __('Không để tồn tại file nhạy cảm và file sao lưu', 'laca'), 'warn',
                sprintf(__('Phát hiện file nhạy cảm trên máy chủ (hiện chưa thể tải trực tiếp từ ngoài web): %s. Nên xoá để tránh rủi ro.', 'laca'), $filesStr),
                8);
        } else {
            $add($cat, 'sensitive_files', __('Không tồn tại file nhạy cảm hoặc file sao lưu', 'laca'), 'pass',
                __('Không tìm thấy file cấu hình phụ, file sao lưu (.bak, .sql, .zip) hay log nhạy cảm trong thư mục web.', 'laca'),
                8);
        }

        $fileEditOff = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $add($cat, 'file_edit', 'Trình soạn thảo code admin bị tắt',
            $fileEditOff ? 'pass' : 'warn',
            $fileEditOff ? 'DISALLOW_FILE_EDIT = true. Attacker không thể sửa plugin/theme qua admin.'
                         : 'Trình soạn thảo đang bật — nên tắt trong wp-config.php.',
            5);

        // Chế độ quản lý file mã nguồn (DISALLOW_FILE_MODS)
        $fileModsOff = defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS;
        $modsDesc = $fileModsOff
            ? __('DISALLOW_FILE_MODS = true. Khóa hoàn toàn tính năng cài đặt/cập nhật và sửa file trong wp-admin (mô hình chủ dự án quản lý tập trung).', 'laca')
            : __('DISALLOW_FILE_MODS = false. Cho phép cập nhật plugin/theme trong wp-admin (mô hình khách tự quản lý). Đã có FileWatch và Laca Sync giám sát sai lệch.', 'laca');
        $add($cat, 'file_mods_mode', __('Chế độ quản lý file mã nguồn', 'laca'),
            $fileModsOff ? 'pass' : 'info',
            $modsDesc,
            0);

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
            6, admin_url('admin.php?page=laca-security&tab=login'));

        $twoFaOn = get_option('laca_2fa_master_enabled', 0);
        $add($cat, '2fa', 'Xác thực 2 bước (2FA TOTP) được bật',
            $twoFaOn ? 'pass' : 'warn',
            $twoFaOn ? '2FA đang bật — tài khoản được bảo vệ kể cả khi mất mật khẩu.'
                     : '2FA chưa bật — nên bật để tăng cường bảo mật.',
            6, admin_url('admin.php?page=laca-2fa'));

        $policyConfig = class_exists(LoginPolicy::class) ? LoginPolicy::config() : [];

        // 3. Username bị cấm đang tồn tại
        $bannedList = (array) ($policyConfig['banned_usernames'] ?? ['admin', 'administrator', 'root']);
        $existingBanned = [];
        foreach ($bannedList as $bUser) {
            if (username_exists($bUser)) {
                $existingBanned[] = $bUser;
            }
        }
        if (!empty($existingBanned)) {
            $add($cat, 'banned_username_exists', __('Không tồn tại tài khoản dùng username bị cấm', 'laca'), 'warn',
                sprintf(__('Phát hiện tài khoản đang sử dụng username cấm: %s. Kẻ tấn công thường nhắm mục tiêu vào các tên này.', 'laca'), implode(', ', $existingBanned)),
                6, admin_url('users.php'));
        } else {
            $add($cat, 'banned_username_exists', __('Không tồn tại tài khoản dùng username bị cấm', 'laca'), 'pass',
                __('Không có tài khoản nào sử dụng các tên đăng nhập bị cấm trong chính sách.', 'laca'),
                6);
        }

        // 4. 2FA cho quản trị viên
        $requireRoles = (array) ($policyConfig['require_2fa_roles'] ?? []);
        $adminUsers = get_users(['role' => 'administrator']);
        $unprotectedAdmins = 0;
        foreach ($adminUsers as $adminUser) {
            if (!get_user_meta($adminUser->ID, 'laca_2fa_enabled', true) || !get_user_meta($adminUser->ID, 'laca_2fa_verified', true)) {
                $unprotectedAdmins++;
            }
        }
        $adminEnforced = in_array('administrator', $requireRoles, true);
        if ($adminEnforced || $unprotectedAdmins === 0) {
            $add($cat, 'admin_2fa', __('2FA bắt buộc cho tài khoản quản trị viên', 'laca'), 'pass',
                __('Chính sách 2FA bắt buộc cho quản trị viên đang bật (hoặc toàn bộ quản trị viên đã kích hoạt 2FA).', 'laca'),
                6);
        } else {
            $add($cat, 'admin_2fa', __('2FA bắt buộc cho tài khoản quản trị viên', 'laca'), 'warn',
                sprintf(__('Có %d tài khoản quản trị viên chưa kích hoạt 2FA và chính sách chưa bắt buộc cho vai trò này.', 'laca'), $unprotectedAdmins),
                6, admin_url('admin.php?page=laca-security&tab=login'));
        }

        // 6. Kiểm mật khẩu bị lộ
        $breachedCheck = $policyConfig['breached_check'] ?? 'admins';
        if ($breachedCheck === 'off') {
            $add($cat, 'breached_check', __('Kiểm tra mật khẩu bị lộ (Have I Been Pwned)', 'laca'), 'warn',
                __('Tính năng kiểm tra mật khẩu bị lộ đang TẮT. Người dùng có thể đặt mật khẩu đã bị rò rỉ.', 'laca'),
                5, admin_url('admin.php?page=laca-security&tab=login'));
        } elseif ($breachedCheck === 'all') {
            $add($cat, 'breached_check', __('Kiểm tra mật khẩu bị lộ (Have I Been Pwned)', 'laca'), 'pass',
                __('Kiểm tra mật khẩu bị lộ đang BẬT cho toàn bộ người dùng.', 'laca'),
                5);
        } else {
            $add($cat, 'breached_check', __('Kiểm tra mật khẩu bị lộ (Have I Been Pwned)', 'laca'), 'pass',
                __('Kiểm tra mật khẩu bị lộ đang BẬT cho tài khoản Quản trị viên.', 'laca'),
                5);
        }

        // ── 3. Access Control & Privacy ──────────────────────────────────────
        $cat = 'Kiểm soát truy cập & Riêng tư';

        // 5. Application Passwords
        $appPasswordsDisabled = !empty($policyConfig['disable_app_passwords']);
        if ($appPasswordsDisabled) {
            $add($cat, 'app_passwords', __('Tắt Mật khẩu ứng dụng (Application Passwords)', 'laca'), 'pass',
                __('Mật khẩu ứng dụng đã được tắt trong Chính sách đăng nhập, giúp ngăn chặn xác thực trái phép qua API.', 'laca'),
                5);
        } else {
            global $wpdb;
            $hasAnyAppPass = (bool) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_application_passwords' AND meta_value != '' AND meta_value != 'a:0:{}'");
            if ($hasAnyAppPass) {
                $add($cat, 'app_passwords', __('Mật khẩu ứng dụng (Application Passwords)', 'laca'), 'pass',
                    __('Mật khẩu ứng dụng đang được sử dụng bởi các tài khoản hợp lệ.', 'laca'),
                    5);
            } else {
                $add($cat, 'app_passwords', __('Tắt Mật khẩu ứng dụng (Application Passwords)', 'laca'), 'warn',
                    __('Mật khẩu ứng dụng đang bật nhưng không có tài khoản nào sử dụng — nên tắt trong Chính sách đăng nhập để giảm bề mặt tấn công.', 'laca'),
                    5, admin_url('admin.php?page=laca-security&tab=login'));
            }
        }

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
        $authorEnumBlocked = class_exists(\App\Settings\Security\UserEnumerationGuard::class)
            || (has_filter('author_request', function () {}) === false && get_option('laca_block_author_enum', 0));
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

        $result = [
            'score'   => $finalScore,
            'pass'    => count(array_filter($checks, fn($c) => $c['status'] === 'pass')),
            'fail'    => count(array_filter($checks, fn($c) => $c['status'] === 'fail')),
            'warn'    => count(array_filter($checks, fn($c) => $c['status'] === 'warn')),
            'info'    => count(array_filter($checks, fn($c) => $c['status'] === 'info')),
            'total'   => count($checks),
            'groups'  => $byCategory,
            'php_ver' => phpversion(),
            'wp_ver'  => get_bloginfo('version'),
            'updated_at' => time(),
        ];

        set_transient(self::TRANSIENT_KEY, $result, DAY_IN_SECONDS);

        return $result;
    }
}
