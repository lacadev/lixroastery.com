<?php

namespace App\Settings\LacaAdmin;

use App\Settings\AdminSettings;

/**
 * Checklist phát hành production — khối đầu trang Laca Admin (chỉ Super User thấy).
 *
 * Mỗi mục: tiêu đề, hướng dẫn, link tới chỗ cài đặt. Mục `auto` tự kiểm từ cấu hình thật
 * (không tick tay được — trạng thái là kết quả đo); mục `manual` tick tay, lưu ai/lúc nào.
 * Mục `skippable` đánh dấu "Không áp dụng" được, không tính vào tiến độ.
 *
 * Dùng chung mọi site của theme. Child theme thêm/bớt mục qua filter
 * `laca_release_checklist_groups` (cùng cấu trúc với groups()).
 *
 * Không JS: accordion là <details>/<summary>, ô tick là nút submit form (admin-post + nonce).
 */
final class ReleaseChecklist
{
    private const STATE_OPTION = 'laca_release_checklist_state';
    private const ACTION = 'laca_release_checklist';
    public const DNS_HOOK = 'laca_release_checklist_dns';
    private const DNS_TRANSIENT = 'laca_rc_dns_';

    public function register(): void
    {
        add_action('all_admin_notices', [$this, 'render'], 20);
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
    }

    private static function canManage(): bool
    {
        $user = wp_get_current_user();
        return current_user_can('manage_options') && $user->exists()
            && in_array($user->user_login, AdminSettings::getSuperUserLogins(), true);
    }

    // ── Danh sách mục ─────────────────────────────────────────────────────────

    /**
     * @return array<int,array{key:string,title:string,items:array<int,array>}>
     */
    public static function groups(): array
    {
        $sec = static fn (string $tab) => admin_url('admin.php?page=laca-security&tab=' . $tab);
        $dh = admin_url('admin.php?page=laca-discord-hub');

        $groups = [
            [
                'key' => 'laca',
                'title' => 'Laca Admin',
                'items' => [
                    ['id' => 'laca_brand', 'title' => 'Màu admin & logo trang đăng nhập', 'type' => 'manual',
                        'guide' => 'Tab ADMIN COLOR và LOGIN ngay bên dưới: chọn màu thương hiệu, tải logo cho trang đăng nhập.',
                        'url' => admin_url('admin.php?page=laca-admin'),
                        'check' => static function () {
                            $missing = array_keys(array_filter([
                                'màu admin' => '' === (string) get_option('_primary_color_ad'),
                                'logo đăng nhập' => !get_option('_login_logo'),
                            ]));
                            return [!$missing, $missing ? 'Chưa có ' . implode(', ', $missing) : 'Đã chọn màu và logo'];
                        }],
                    ['id' => 'smtp', 'title' => 'SMTP gửi mail & gửi thử', 'type' => 'manual', 'high' => true,
                        'guide' => 'Tab SMTP: nhập máy chủ mail của khách rồi gửi một form liên hệ thử. Email Log phải ghi "Đã gửi" — không có SMTP, mail lead hay rơi vào spam hoặc mất.',
                        'url' => admin_url('admin.php?page=laca-email-log'),
                        'check' => static function () {
                            global $wpdb;
                            if (get_option('_use_smtp') !== 'yes' || '' === (string) get_option('_smtp_host')) {
                                return [false, 'Chưa bật SMTP / thiếu máy chủ'];
                            }
                            $table = $wpdb->prefix . 'laca_email_log';
                            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                                return [false, 'SMTP đã bật · chưa có Email Log để xác nhận đã gửi được'];
                            }
                            // Lần gửi gần nhất phải thành công: SMTP đổi mật khẩu/bị khoá thì tự báo lại.
                            $last = $wpdb->get_row("SELECT status, sent_at FROM {$table} ORDER BY id DESC LIMIT 1"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng cố định
                            if (!$last) {
                                return [false, 'SMTP đã bật · chưa gửi mail nào — gửi thử 1 form liên hệ'];
                            }
                            $at = mysql2date('d/m/Y H:i', $last->sent_at);
                            return 'sent' === $last->status
                                ? [true, 'Gửi qua ' . get_option('_smtp_host') . ' · lần gần nhất thành công ' . $at]
                                : [false, 'Lần gửi gần nhất THẤT BẠI (' . $at . ') — xem Email Log'];
                        }],
                    ['id' => 'mail_domain', 'title' => 'Mail gửi đi bằng hộp thư tên miền riêng', 'type' => 'auto',
                        'guide' => 'Tab SMTP: dùng hộp thư theo tên miền (vd info@tenmien.com — email hosting hoặc Google Workspace) thay vì Gmail cá nhân. Mail tự động gửi bằng Gmail miễn phí hay bị chấm spam, giới hạn ~500 mail/ngày, và không hưởng SPF/DKIM/DMARC của tên miền.',
                        'url' => admin_url('admin.php?page=laca-admin'),
                        'check' => static function () {
                            if (get_option('_use_smtp') !== 'yes') {
                                return [false, 'Chưa bật SMTP — mail gửi bằng hàm mail() của hosting'];
                            }
                            $user = (string) get_option('_smtp_username');
                            $domain = strtolower((string) substr(strrchr($user, '@') ?: '', 1));
                            $free = ['gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.com.vn', 'outlook.com', 'hotmail.com', 'live.com', 'icloud.com'];
                            return in_array($domain, $free, true) ? [false, 'Đang gửi bằng ' . $user . ' (hộp thư miễn phí)'] : [true, 'Gửi bằng ' . $user];
                        }],
                    ['id' => 'maintenance_off', 'title' => 'Tắt chế độ bảo trì', 'type' => 'auto', 'high' => true,
                        'guide' => 'Tab ADMIN: bỏ chọn "Bảo trì". Khi bật, khách chỉ thấy trang bảo trì (HTTP 503).',
                        'url' => admin_url('admin.php?page=laca-admin'),
                        'check' => static fn () => get_option('_is_maintenance') === 'yes' ? [false, 'Đang BẬT bảo trì'] : [true, 'Đang tắt']],
                    ['id' => 'admin_email', 'title' => 'Email quản trị là hộp thư có người đọc', 'type' => 'auto', 'high' => true,
                        'guide' => 'Settings → General → Administration Email Address. WordPress gửi link khôi phục khi site lỗi nghiêm trọng (Recovery Mode) và cảnh báo bảo mật tới địa chỉ này — để email dev/test thì không ai nhận.',
                        'url' => admin_url('options-general.php'),
                        'check' => static function () {
                            $email = (string) get_option('admin_email');
                            $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
                            $bad = '' === $domain || preg_match('/(^|\.)(example\.(com|org|net)|test|local|localhost|invalid)$/', $domain);
                            return [!$bad && is_email($email), '' !== $email ? $email : 'Chưa đặt'];
                        }],
                    ['id' => 'timezone', 'title' => 'Múi giờ đúng (Asia/Ho_Chi_Minh)', 'type' => 'auto',
                        'guide' => 'Settings → General → Timezone: chọn "Ho Chi Minh" (theo thành phố, không theo UTC+7) — sai múi giờ thì bài hẹn giờ, đơn hàng, log và thông báo Discord lệch giờ.',
                        'url' => admin_url('options-general.php'),
                        'check' => static function () {
                            $tz = (string) get_option('timezone_string');
                            if ('' !== $tz) {
                                return [true, $tz];
                            }
                            $offset = (float) get_option('gmt_offset');
                            return [false, 'UTC' . ($offset >= 0 ? '+' : '') . $offset . ' (nên chọn theo thành phố để tự xử lý giờ chuẩn)'];
                        }],
                    ['id' => 'theme_info', 'title' => 'Thông tin liên hệ, mạng xã hội, logo website', 'type' => 'manual',
                        'guide' => 'Laca Theme: hotline, email, địa chỉ, link mạng xã hội, logo — kiểm tra lại header và footer ngoài trang.',
                        'url' => admin_url('admin.php?page=app-theme-options.php'),
                        // Tên field theo theme con của từng site; site khác tên field thì chỉ không tự đạt (vẫn tick tay).
                        'check' => static function () {
                            $has = static function (string $key): bool {
                                $value = function_exists('getOption') ? getOption($key) : null;
                                if (empty($value) && function_exists('carbon_get_theme_option')) {
                                    $value = carbon_get_theme_option($key);
                                }
                                return !empty($value);
                            };
                            $missing = array_keys(array_filter([
                                'logo' => !$has('logo') && !get_theme_mod('custom_logo'),
                                'hotline' => !$has('phone_number') && !$has('phone_numbers') && !$has('phone'),
                                'email' => !$has('email'),
                                'địa chỉ' => !$has('address') && !$has('address_locations'),
                            ]));
                            return [!$missing, $missing ? 'Chưa nhập ' . implode(', ', $missing) : 'Đủ logo, hotline, email, địa chỉ'];
                        }],
                ],
            ],
            [
                'key' => 'wpconfig',
                'title' => 'wp-config.php',
                'items' => [
                    ['id' => 'env_production', 'title' => 'WP_ENVIRONMENT_TYPE là production', 'type' => 'auto', 'high' => true,
                        'guide' => 'Sửa (hoặc xoá) dòng define( \'WP_ENVIRONMENT_TYPE\', … ) thành \'production\'. Để "local" thì Discord Hub chặn mọi thông báo và reCAPTCHA bị bỏ qua.',
                        'check' => static fn () => ['production' === wp_get_environment_type(), wp_get_environment_type()]],
                    ['id' => 'debug_off', 'title' => 'Tắt debug (WP_DEBUG, display_errors)', 'type' => 'auto', 'high' => true,
                        'guide' => 'define( \'WP_DEBUG\', false ); — debug bật có thể in lỗi kèm đường dẫn server ra cho khách.',
                        'check' => static function () {
                            $display = ini_get('display_errors');
                            $displayOn = $display && !in_array(strtolower((string) $display), ['off', '0', ''], true);
                            $debug = defined('WP_DEBUG') && WP_DEBUG;
                            return [!$debug && !$displayOn, ($debug ? 'WP_DEBUG đang bật' : 'WP_DEBUG tắt') . ' · display_errors ' . ($displayOn ? 'BẬT' : 'tắt')];
                        }],
                    ['id' => 'debug_log', 'title' => 'Tắt ghi debug.log', 'type' => 'auto',
                        'guide' => 'define( \'WP_DEBUG_LOG\', false ); sau khi kiểm tra xong. File debug.log phình to theo thời gian và có thể chứa đường dẫn, câu truy vấn, dữ liệu khách.',
                        'check' => static function () {
                            $log = defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false;
                            return $log ? [false, 'Đang ghi ' . (is_string($log) ? $log : 'wp-content/debug.log')] : [true, 'Không ghi'];
                        }],
                    ['id' => 'salts', 'title' => 'Salt key là chuỗi riêng của site', 'type' => 'auto', 'high' => true,
                        'guide' => 'Lấy bộ mới tại api.wordpress.org/secret-key/1.1/salt/ và thay 8 dòng AUTH_KEY…NONCE_SALT. Salt mặc định/dùng chung = cookie đăng nhập giả được.',
                        'check' => static function () {
                            $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'];
                            foreach ($keys as $k) {
                                $v = defined($k) ? (string) constant($k) : '';
                                if (strlen($v) < 32 || str_contains($v, 'put your unique phrase')) {
                                    return [false, $k . ' chưa đặt hoặc còn mặc định'];
                                }
                            }
                            return [true, 'Đã đặt riêng'];
                        }],
                    ['id' => 'file_edit', 'title' => 'Tắt Theme/Plugin File Editor', 'type' => 'auto',
                        'guide' => 'define( \'DISALLOW_FILE_EDIT\', true ); — code chỉ sửa qua Git, kẻ chiếm được admin không chèn mã độc từ trình duyệt được.',
                        'check' => static fn () => (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) ? [true, 'Đã tắt'] : [false, 'File Editor còn bật']],
                    ['id' => 'config_perms', 'title' => 'Quyền file wp-config.php 400/440/600', 'type' => 'auto',
                        'guide' => 'cPanel File Manager → wp-config.php → Permissions: 440 (hoặc 400/600 tuỳ hosting). Quyền 644 cho mọi tài khoản trên cùng server đọc được mật khẩu database và salt key.',
                        'check' => static function () {
                            $file = is_file(ABSPATH . 'wp-config.php') ? ABSPATH . 'wp-config.php' : dirname(ABSPATH) . '/wp-config.php';
                            if (!is_file($file)) {
                                return [false, 'Không tìm thấy wp-config.php'];
                            }
                            $perms = fileperms($file) & 0777;
                            return [0 === ($perms & 0004), sprintf('%o', $perms) . (($perms & 0004) ? ' (ai cũng đọc được)' : '')];
                        }],
                    ['id' => 'db_prefix', 'title' => 'Tiền tố database khác "wp_"', 'type' => 'auto', 'skippable' => true,
                        'guide' => 'Chỉ cản bot SQL injection đoán sẵn tên bảng — lợi ích thấp, rủi ro cao nếu đổi sai (phải đổi cả wp_user_roles, usermeta và wp-config cùng lúc). Làm khi site mới, có backup, giờ ít khách; hoặc đánh dấu không áp dụng.',
                        'check' => static function () {
                            global $wpdb;
                            return ['wp_' !== $wpdb->base_prefix, $wpdb->base_prefix];
                        }],
                    ['id' => 'real_cron', 'title' => 'Cron thật (cPanel) thay WP-Cron', 'type' => 'auto',
                        'guide' => 'Làm theo dòng "Lịch chạy (cron)" ở Discord Hub: thêm cron cPanel trước, thấy chạy rồi mới thêm DISABLE_WP_CRON.',
                        'url' => $dh,
                        'check' => static function () {
                            if (!(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)) {
                                return [false, 'Đang dùng WP-Cron theo lượt truy cập'];
                            }
                            $last = (int) get_option('laca_dh_cron_last_run', 0);
                            if ($last && time() - $last > 2 * HOUR_IN_SECONDS) {
                                return [false, 'Cron cPanel không chạy từ ' . wp_date('d/m H:i', $last)];
                            }
                            return [true, $last ? 'Chạy lần cuối ' . wp_date('d/m H:i', $last) : 'Đã bật'];
                        }],
                ],
            ],
            [
                'key' => 'security',
                'title' => 'Bảo mật & đăng nhập',
                'items' => [
                    ['id' => 'custom_login', 'title' => 'URL đăng nhập ẩn, khó đoán', 'type' => 'auto', 'high' => true,
                        'guide' => 'Đặt slug ngẫu nhiên (vd "cua-hang-7k3q"), tránh "tensite-login" — bot thử các kiểu đó đầu tiên.',
                        'url' => $sec('login'),
                        'check' => static function () {
                            $slug = (string) get_option('laca_login_slug', '');
                            return ('' !== $slug && get_option('laca_enable_custom_login', 0)) ? [true, 'Đã bật'] : [false, '/wp-login.php còn mở'];
                        }],
                    ['id' => 'twofa', 'title' => 'Bật 2FA cho tài khoản quản trị', 'type' => 'auto', 'high' => true,
                        'guide' => 'Lớp chặn duy nhất còn hiệu lực khi mật khẩu bị lộ. Bật rồi yêu cầu mọi admin quét mã.',
                        'url' => $sec('2fa'),
                        'check' => static fn () => get_option('laca_2fa_master_enabled', 0) ? [true, 'Đang bật'] : [false, 'Chưa bật']],
                    ['id' => 'no_admin_user', 'title' => 'Không có tài khoản tên "admin"', 'type' => 'auto', 'high' => true,
                        'guide' => 'Tạo tài khoản mới tên khác, chuyển bài viết sang rồi xoá "admin" — đây là username bot dò đầu tiên.',
                        'url' => admin_url('users.php'),
                        'check' => static fn () => username_exists('admin') ? [false, 'Còn user "admin"'] : [true, 'Không có']],
                    ['id' => 'registration', 'title' => 'Đăng ký tài khoản không cấp quyền cao', 'type' => 'auto', 'high' => true,
                        'guide' => 'Settings → General: nếu không cần cho khách tự đăng ký thì bỏ "Anyone can register"; nếu cần (WooCommerce), "New User Default Role" phải là Subscriber/Customer. Vai trò mặc định cao = ai đăng ký cũng thành biên tập viên/admin.',
                        'url' => admin_url('options-general.php'),
                        'check' => static function () {
                            $open = (bool) get_option('users_can_register');
                            $role = (string) get_option('default_role');
                            $safe = in_array($role, ['subscriber', 'customer'], true);
                            return [!$open || $safe, ($open ? 'Đang mở đăng ký' : 'Đăng ký đóng') . ' · vai trò mặc định: ' . $role];
                        }],
                    ['id' => 'xmlrpc', 'title' => 'Tắt XML-RPC', 'type' => 'auto',
                        'guide' => 'XML-RPC cho thử hàng trăm mật khẩu trong một request. Tắt trong Laca Admin → ADMIN.',
                        'check' => static fn () => apply_filters('xmlrpc_enabled', true) ? [false, 'Còn bật'] : [true, 'Đã tắt']],
                    ['id' => 'recaptcha', 'title' => 'reCAPTCHA chống spam (đăng nhập, form liên hệ)', 'type' => 'auto', 'skippable' => true,
                        'guide' => 'Tạo key reCAPTCHA v3 cho đúng tên miền production, nhập Site Key / Secret Key và tick Đăng nhập + Form liên hệ. Sau khi lưu: mở trang liên hệ, gửi thử 1 lần và đăng xuất/đăng nhập lại để chắc key đúng tên miền.',
                        'url' => admin_url('admin.php?page=laca-recaptcha'),
                        'check' => static function () {
                            if (!class_exists('Laca_Recaptcha')) {
                                return [false, 'Chưa nạp module reCAPTCHA'];
                            }
                            $c = \Laca_Recaptcha::config();
                            if ('' === $c['site_key'] || '' === $c['secret_key']) {
                                return [false, '' === $c['site_key'] ? 'Thiếu Site Key' : 'Thiếu Secret Key'];
                            }
                            $labels = ['login' => 'Đăng nhập', 'register' => 'Đăng ký', 'comment' => 'Bình luận', 'contact' => 'Form liên hệ'];
                            $on = array_values(array_intersect_key($labels, array_filter(array_intersect_key($c, $labels))));
                            $ok = $c['login'] && $c['contact'];
                            $detail = $on ? 'Đang bảo vệ: ' . implode(', ', $on) : 'Có key nhưng chưa bật form nào';
                            if (!$ok) {
                                $detail .= ' · cần bật ' . implode(' + ', array_values(array_intersect_key($labels, array_filter(['login' => !$c['login'], 'contact' => !$c['contact']]))));
                            }
                            return [$ok, $detail . sprintf(' · điểm tối thiểu %.1f', $c['score'])];
                        }],
                    ['id' => 'uploads_php', 'title' => 'Chặn chạy PHP trong thư mục uploads', 'type' => 'auto',
                        'guide' => 'Tạo file wp-content/uploads/.htaccess với nội dung: <FilesMatch "\.(php|phtml|phar|php[0-9])$"> Require all denied </FilesMatch> — kẻ tấn công lỡ upload được webshell cũng không chạy được (giám sát file vẫn báo).',
                        'check' => static function () {
                            $uploads = wp_get_upload_dir();
                            $ht = trailingslashit((string) $uploads['basedir']) . '.htaccess';
                            $content = is_file($ht) ? (string) file_get_contents($ht) : '';
                            $ok = '' !== $content && preg_match('/php/i', $content) && preg_match('/deny|denied|-\s*\[F/i', $content);
                            return [(bool) $ok, $ok ? 'Đã chặn' : ('' === $content ? 'Chưa có uploads/.htaccess' : '.htaccess có nhưng chưa chặn PHP')];
                        }],
                    ['id' => 'comments', 'title' => 'Bình luận: tắt hoặc phải duyệt', 'type' => 'auto', 'skippable' => true,
                        'guide' => 'Settings → Discussion: site không dùng bình luận thì bỏ "Allow people to submit comments"; có dùng thì bật "Comment must be manually approved" — chặn spam link độc hiện công khai.',
                        'url' => admin_url('options-discussion.php'),
                        'check' => static function () {
                            $open = 'open' === get_option('default_comment_status');
                            $moderated = (bool) get_option('comment_moderation');
                            return [!$open || $moderated, $open ? ($moderated ? 'Mở, phải duyệt' : 'Mở, KHÔNG cần duyệt') : 'Đã tắt'];
                        }],
                    ['id' => 'roles', 'title' => 'Mỗi tài khoản đúng vai trò', 'type' => 'manual', 'high' => true,
                        'guide' => 'Người chỉ viết bài → Editor, không phải Administrator. Xoá tài khoản không còn dùng, đổi mật khẩu mạnh cho tài khoản bàn giao.',
                        'url' => admin_url('users.php'),
                        // Code không biết ai "nên" là admin → không bao giờ tự đạt, chỉ hiện danh sách để soát.
                        'check' => static function () {
                            global $wpdb;
                            // Query thẳng: get_users() bị bộ lọc ẩn Super User (pre_user_query) cắt bớt → đếm thiếu.
                            $admins = $wpdb->get_col($wpdb->prepare(
                                "SELECT u.user_login FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = %s WHERE m.meta_value LIKE %s ORDER BY u.user_login LIMIT 30",
                                $wpdb->get_blog_prefix() . 'capabilities',
                                '%' . $wpdb->esc_like('"administrator"') . '%'
                            ));
                            return [false, sprintf('%d Administrator: %s', count($admins), implode(', ', $admins))];
                        }],
                ],
            ],
            [
                'key' => 'cache',
                'title' => 'Cache & hiệu năng',
                'items' => [
                    ['id' => 'lscache_on', 'title' => 'LiteSpeed Cache đang bật', 'type' => 'auto', 'high' => true,
                        'guide' => 'Cài và bật plugin LiteSpeed Cache, bật "Enable Cache" ở Cache → Cache.',
                        'url' => admin_url('admin.php?page=litespeed-cache'),
                        'check' => static function () {
                            if (!defined('LSCWP_V')) {
                                return [false, 'Chưa cài / chưa kích hoạt plugin'];
                            }
                            return get_option('litespeed.conf.cache') ? [true, 'Đang bật'] : [false, 'Plugin có nhưng cache đang tắt'];
                        }],
                    ['id' => 'home_cached', 'title' => 'Trang chủ thực sự được cache', 'type' => 'manual', 'high' => true,
                        'guide' => 'Mở trang chủ ở tab ẩn danh 2 lần → F12 → Network → bấm request đầu → header "x-litespeed-cache: hit". Thấy "no-cache" là có thành phần đang tắt cache, trang chủ chậm gấp 2–3 lần.'],
                    ['id' => 'optimize', 'title' => 'Không bật trùng tối ưu ảnh/CSS-JS của LiteSpeed', 'type' => 'auto',
                        'guide' => 'Theme đã tự: chuyển ảnh upload sang WebP + tạo cỡ mobile/tablet (srcset), lazy-load, CSS/JS build sẵn đã nén, defer JS, inline critical CSS. Vì vậy KHÔNG bật thêm trong LiteSpeed: CSS/JS Minify, CSS/JS Combine, JS Defer/Delay, Image WebP — xử lý 2 lần dễ vỡ giao diện. Server cần GD/Imagick hỗ trợ WebP để theme chuyển được ảnh.',
                        'url' => admin_url('admin.php?page=litespeed-page_optm'),
                        'check' => static function () {
                            $problems = [];
                            if (!function_exists('imagewebp')) {
                                $problems[] = 'server thiếu GD WebP (ảnh upload không được chuyển)';
                            }
                            if (defined('LSCWP_V')) {
                                $dupes = [
                                    'optm-css_min' => 'CSS Minify', 'optm-css_comb' => 'CSS Combine',
                                    'optm-js_min' => 'JS Minify', 'optm-js_comb' => 'JS Combine',
                                    'optm-js_defer' => 'JS Defer/Delay', 'img_optm-webp' => 'Image WebP',
                                ];
                                foreach ($dupes as $key => $label) {
                                    if (get_option('litespeed.conf.' . $key)) {
                                        $problems[] = 'LiteSpeed đang bật ' . $label;
                                    }
                                }
                            }
                            return $problems ? [false, implode(' · ', $problems)] : [true, 'Theme tự tối ưu, LiteSpeed không bật trùng'];
                        }],
                    ['id' => 'critical_css', 'title' => 'Có critical CSS trên server', 'type' => 'auto',
                        'guide' => 'GitHub Actions KHÔNG tạo được critical.css (cần site đang chạy). Trên máy: bật site Local, chạy "yarn critical" trong child theme, rồi upload dist/styles/critical.css lên hosting. Thiếu file này trang vẫn chạy nhưng hiển thị chậm hơn (chờ tải hết theme.css).',
                        'check' => static function () {
                            // style.css nằm trong theme/ → dist/ ở thư mục cha (giống assets.php).
                            $file = dirname(get_stylesheet_directory()) . '/dist/styles/critical.css';
                            return is_file($file) ? [true, 'Có (' . size_format((int) filesize($file)) . ', ' . wp_date('d/m/Y', (int) filemtime($file)) . ')'] : [false, 'Chưa có dist/styles/critical.css'];
                        }],
                    ['id' => 'object_cache', 'title' => 'Object cache (Redis / Memcached)', 'type' => 'auto', 'skippable' => true,
                        'guide' => 'Nếu hosting hỗ trợ: LiteSpeed Cache → Cache → Object, chọn Redis/LSMCD. Giảm truy vấn DB rõ rệt với WooCommerce.',
                        'check' => static fn () => wp_using_ext_object_cache() ? [true, 'Đang dùng'] : [false, 'Chưa bật']],
                ],
            ],
            [
                'key' => 'woocommerce',
                'title' => 'WooCommerce',
                'when' => static fn () => class_exists('WooCommerce'),
                'items' => [
                    ['id' => 'woo_coming_soon', 'title' => 'Tắt chế độ "Coming soon" của cửa hàng', 'type' => 'auto', 'high' => true,
                        'guide' => 'WooCommerce → Settings → Site visibility: chọn "Live". Để "Coming soon" thì khách chỉ thấy trang sắp ra mắt, không mua được hàng.',
                        'url' => admin_url('admin.php?page=wc-settings&tab=site-visibility'),
                        'check' => static fn () => 'yes' === get_option('woocommerce_coming_soon') ? [false, 'Đang ở chế độ Coming soon'] : [true, 'Live']],
                    ['id' => 'woo_pages', 'title' => 'Trang Cửa hàng / Giỏ hàng / Thanh toán / Tài khoản', 'type' => 'auto', 'high' => true,
                        'guide' => 'WooCommerce → Settings → Advanced → Page setup: gán đủ 4 trang và các trang đó phải đang xuất bản.',
                        'url' => admin_url('admin.php?page=wc-settings&tab=advanced'),
                        'check' => static function () {
                            $missing = [];
                            foreach (['shop' => 'Cửa hàng', 'cart' => 'Giỏ hàng', 'checkout' => 'Thanh toán', 'myaccount' => 'Tài khoản'] as $page => $label) {
                                $id = function_exists('wc_get_page_id') ? (int) wc_get_page_id($page) : 0;
                                if ($id <= 0 || 'publish' !== get_post_status($id)) {
                                    $missing[] = $label;
                                }
                            }
                            return $missing ? [false, 'Thiếu/chưa xuất bản: ' . implode(', ', $missing)] : [true, 'Đủ 4 trang'];
                        }],
                    ['id' => 'woo_payment_live', 'title' => 'Cổng thanh toán ở chế độ thật (Live)', 'type' => 'manual', 'high' => true,
                        'guide' => 'WooCommerce → Settings → Payments: PayPal tắt Sandbox và kết nối tài khoản Live; chuyển khoản/COD nhập đúng số tài khoản, hướng dẫn. Tắt mọi cổng không dùng.',
                        'url' => admin_url('admin.php?page=wc-settings&tab=checkout'),
                        'check' => [self::class, 'checkWooPayments']],
                    ['id' => 'woo_store_info', 'title' => 'Thông tin cửa hàng, tiền tệ, vận chuyển, email đơn hàng', 'type' => 'manual',
                        'guide' => 'Settings → General: địa chỉ, tiền tệ VND (0 số lẻ). Shipping: vùng và phí giao hàng. Emails: "New order" gửi tới email người xử lý đơn.',
                        'url' => admin_url('admin.php?page=wc-settings'),
                        'check' => static function () {
                            $missing = [];
                            if ('' === trim((string) get_option('woocommerce_store_address')) || '' === trim((string) get_option('woocommerce_store_city'))) {
                                $missing[] = 'địa chỉ cửa hàng';
                            }
                            $hasShipping = false;
                            if (class_exists('WC_Shipping_Zones')) {
                                foreach (\WC_Shipping_Zones::get_zones() as $zone) {
                                    $hasShipping = $hasShipping || !empty($zone['shipping_methods']);
                                }
                                $hasShipping = $hasShipping || (new \WC_Shipping_Zone(0))->get_shipping_methods(true);
                            }
                            if (!$hasShipping) {
                                $missing[] = 'phương thức vận chuyển';
                            }
                            $newOrder = (array) get_option('woocommerce_new_order_settings', []);
                            if ('no' === ($newOrder['enabled'] ?? 'yes')) {
                                $missing[] = 'email "New order" đang tắt';
                            }
                            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '?';
                            $recipient = (string) (($newOrder['recipient'] ?? '') ?: get_option('admin_email'));
                            return [!$missing, ($missing ? 'Thiếu: ' . implode(', ', $missing) . ' · ' : '') . 'tiền tệ ' . $currency . ' · đơn mới gửi tới ' . $recipient];
                        }],
                    ['id' => 'woo_test_order', 'title' => 'Đặt thử 1 đơn hàng thật từ đầu tới cuối', 'type' => 'manual', 'high' => true,
                        'guide' => 'Ở tab ẩn danh: thêm vào giỏ → thanh toán bằng cổng thật (số tiền nhỏ) → khách nhận email xác nhận, cửa hàng nhận email đơn mới → hoàn tiền/huỷ đơn thử.',
                        'check' => static function () {
                            if (!function_exists('wc_get_orders')) {
                                return [false, ''];
                            }
                            $ids = wc_get_orders(['limit' => 1, 'return' => 'ids', 'status' => ['processing', 'completed', 'on-hold', 'refunded', 'cancelled']]);
                            if (!$ids) {
                                return [false, 'Chưa có đơn hàng nào đi hết luồng thanh toán'];
                            }
                            $order = wc_get_order((int) $ids[0]);
                            return [true, sprintf('Đã có đơn #%s (%s)', $order->get_order_number(), wc_get_order_status_name($order->get_status()))];
                        }],
                    ['id' => 'woo_scheduler', 'title' => 'Hàng đợi tác vụ WooCommerce không bị ứ', 'type' => 'auto',
                        'guide' => 'Tools → Scheduled Actions → Pending. Nhiều tác vụ quá hạn = cron không chạy: email, đồng bộ tồn kho, gia hạn… bị treo. Xem mục "Cron thật".',
                        'url' => admin_url('admin.php?page=wc-status&tab=action-scheduler&status=pending'),
                        'check' => static function () {
                            global $wpdb;
                            $table = $wpdb->prefix . 'actionscheduler_actions';
                            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                                return [true, 'Chưa có hàng đợi'];
                            }
                            $late = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND scheduled_date_gmt < %s", gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            return [$late <= 20, $late . ' tác vụ quá hạn > 1 giờ'];
                        }],
                ],
            ],
            [
                'key' => 'polylang',
                'title' => 'Đa ngôn ngữ (Polylang)',
                'when' => static fn () => function_exists('pll_languages_list'),
                'items' => [
                    ['id' => 'pll_translations', 'title' => 'Đã dịch trang chính, menu và chuỗi giao diện', 'type' => 'manual',
                        'guide' => 'Languages → Translations (chuỗi theme/plugin), mỗi trang chính có bản dịch, mỗi ngôn ngữ có menu riêng. Kiểm tra chuyển ngôn ngữ ở header không ra trang 404.',
                        'url' => admin_url('admin.php?page=mlang_strings'),
                        'check' => [self::class, 'checkPolylang']],
                    // Không tự kiểm được: code không biết khách muốn ngôn ngữ nào làm gốc → tick tay, hiện giá trị hiện tại.
                    ['id' => 'pll_default', 'title' => 'Ngôn ngữ mặc định đúng', 'type' => 'manual',
                        'guide' => 'Hiện tại: ' . (function_exists('pll_default_language') ? (string) pll_default_language('name') : '?') . '. Languages: đặt ngôn ngữ chính của khách hàng làm mặc định — ngôn ngữ này nằm ở URL gốc và được Google ưu tiên.',
                        'url' => admin_url('admin.php?page=mlang')],
                ],
            ],
            [
                'key' => 'wordfence',
                'title' => 'Wordfence',
                'when' => static fn () => defined('WORDFENCE_VERSION'),
                'items' => [
                    ['id' => 'wf_firewall', 'title' => 'Firewall ở chế độ Extended Protection', 'type' => 'manual',
                        'guide' => 'Wordfence → Firewall → Optimize the Wordfence Firewall, làm theo hướng dẫn (tải .user.ini) tới khi báo "Extended Protection".',
                        'url' => admin_url('admin.php?page=WordfenceWAF'),
                        'check' => static function () {
                            // auto_prepend_file của Wordfence nạp trước WordPress mới là Extended Protection.
                            $extended = defined('WFWAF_AUTO_PREPEND') && WFWAF_AUTO_PREPEND && !(defined('WFWAF_SUBDIRECTORY_INSTALL') && WFWAF_SUBDIRECTORY_INSTALL);
                            return [$extended, $extended ? 'Extended Protection' : 'Basic Protection (chưa tối ưu)'];
                        }],
                    ['id' => 'wf_login', 'title' => 'Giới hạn đăng nhập & chặn username không tồn tại', 'type' => 'manual',
                        'guide' => 'All Options → Brute Force Protection: khoá sau 5 lần sai, "Immediately lock out invalid usernames".',
                        'url' => admin_url('admin.php?page=WordfenceOptions'),
                        'check' => static function () {
                            if (!class_exists('wfConfig')) {
                                return [false, ''];
                            }
                            $on = (bool) \wfConfig::get('loginSecurityEnabled');
                            $max = (int) \wfConfig::get('loginSec_maxFailures');
                            $invalid = (bool) \wfConfig::get('loginSec_lockInvalidUsers');
                            $ok = $on && $max > 0 && $max <= 10 && $invalid;
                            return [$ok, ($on ? 'Bật' : 'TẮT') . sprintf(' · khoá sau %d lần sai', $max) . ' · chặn username không tồn tại: ' . ($invalid ? 'có' : 'không')];
                        }],
                    ['id' => 'wf_alerts', 'title' => 'Email cảnh báo & lịch quét', 'type' => 'manual',
                        'guide' => 'All Options → Email Alert Preferences: email của bạn; Scan Scheduling: tự động hằng ngày.',
                        'url' => admin_url('admin.php?page=WordfenceOptions'),
                        'check' => static function () {
                            if (!class_exists('wfConfig')) {
                                return [false, ''];
                            }
                            $emails = trim((string) \wfConfig::get('alertEmails'));
                            $scan = (bool) \wfConfig::get('scheduledScansEnabled');
                            return ['' !== $emails && $scan, ('' !== $emails ? 'Gửi tới ' . $emails : 'Chưa có email nhận cảnh báo') . ' · quét tự động: ' . ($scan ? 'bật' : 'TẮT')];
                        }],
                    ['id' => 'wf_premium', 'title' => 'Nhập license key (nếu có)', 'type' => 'manual', 'skippable' => true,
                        'guide' => 'Dashboard → License: dán key Premium để có rule firewall và IP chặn theo thời gian thực.',
                        'url' => admin_url('admin.php?page=Wordfence'),
                        'check' => static fn () => class_exists('wfConfig') && \wfConfig::get('isPaid') ? [true, 'Premium'] : [false, 'Bản miễn phí']],
                ],
            ],
            [
                'key' => 'discord',
                'title' => 'Discord Hub & giám sát',
                'when' => static fn () => class_exists('\App\Features\DiscordHub\Settings'),
                'items' => [
                    ['id' => 'dh_webhooks', 'title' => 'Webhook Leads / Audit / Security đã kết nối', 'type' => 'auto', 'high' => true,
                        'guide' => 'Dán webhook từng kênh rồi bấm "Lưu & gửi thử" — dòng nào báo lỗi thì xử lý theo nội dung lỗi.',
                        'url' => $dh,
                        'check' => static function () {
                            $missing = [];
                            foreach (['leads', 'audit', 'security'] as $ch) {
                                if ('ok' !== \App\Features\DiscordHub\Settings::channelStatus($ch)['state']) {
                                    $missing[] = $ch;
                                } elseif ('' !== \App\Features\DiscordHub\DiscordWebhookSender::lastResult($ch)['error']) {
                                    $missing[] = $ch . ' (lỗi gửi)';
                                }
                            }
                            return $missing ? [false, 'Chưa xong: ' . implode(', ', $missing)] : [true, 'Đủ 3 kênh, gửi OK'];
                        }],
                    ['id' => 'dh_fim', 'title' => 'Giám sát file đã có baseline', 'type' => 'auto',
                        'guide' => 'Bật "Giám sát file" và bấm "Lưu & quét ngay" một lần để ghi nhận trạng thái gốc.',
                        'url' => $dh,
                        'check' => static function () {
                            $state = get_option('laca_dh_fim_state', []);
                            return !empty($state['at']) ? [true, 'Quét lần cuối ' . wp_date('d/m H:i', (int) $state['at'])] : [false, 'Chưa quét lần nào'];
                        }],
                    ['id' => 'dh_deploy', 'title' => 'Deploy key + GitHub Secrets', 'type' => 'manual', 'skippable' => true,
                        'guide' => 'Tạo deploy key ở Discord Hub, thêm LACA_DEPLOY_KEY và DISCORD_DEPLOY_WEBHOOK vào GitHub → Settings → Secrets. Bỏ qua nếu site không deploy bằng GitHub Actions.',
                        'url' => $dh,
                        // Lần deploy đầu tiên ký đúng HMAC chứng minh LACA_DEPLOY_KEY trên GitHub khớp key ở đây.
                        'check' => static function () {
                            $meta = \App\Features\DiscordHub\Settings::deployKeyMeta();
                            if (null === $meta) {
                                return [false, 'Chưa tạo deploy key'];
                            }
                            $last = (int) get_option(\App\Features\DiscordHub\FileWatch\DeployReceiver::LAST_DEPLOY_OPTION, 0);
                            return $last
                                ? [true, 'Đã nhận deploy hợp lệ từ GitHub lúc ' . wp_date('d/m/Y H:i', $last)]
                                : [false, 'Đã tạo key · chờ lần deploy kế tiếp từ GitHub Actions để xác nhận secrets đúng'];
                        }],
                ],
            ],
            [
                'key' => 'seo',
                'title' => 'SEO & nội dung',
                'items' => [
                    ['id' => 'blog_public', 'title' => 'Không chặn Google index', 'type' => 'auto', 'high' => true,
                        'guide' => 'Settings → Reading: BỎ chọn "Discourage search engines". Lỗi rất hay quên sau khi chuyển từ bản dev lên.',
                        'url' => admin_url('options-reading.php'),
                        'check' => static fn () => get_option('blog_public') ? [true, 'Cho phép index'] : [false, 'Đang CHẶN Google']],
                    ['id' => 'permalinks', 'title' => 'Đường dẫn tĩnh (permalink) đẹp', 'type' => 'auto',
                        'guide' => 'Settings → Permalinks: chọn "Post name".',
                        'url' => admin_url('options-permalink.php'),
                        'check' => static fn () => '' !== (string) get_option('permalink_structure') ? [true, (string) get_option('permalink_structure')] : [false, 'Đang dùng ?p=123']],
                    ['id' => 'sample_content', 'title' => 'Đã xoá nội dung mẫu', 'type' => 'auto',
                        'guide' => 'Xoá bài "Hello world!", trang "Sample Page" và bình luận mẫu.',
                        'url' => admin_url('edit.php'),
                        'check' => static function () {
                            $left = [];
                            if (get_page_by_path('hello-world', OBJECT, 'post')) {
                                $left[] = 'Hello world';
                            }
                            if (get_page_by_path('sample-page', OBJECT, 'page')) {
                                $left[] = 'Sample Page';
                            }
                            return $left ? [false, 'Còn: ' . implode(', ', $left)] : [true, 'Không còn'];
                        }],
                    ['id' => 'site_icon', 'title' => 'Có favicon (biểu tượng site)', 'type' => 'auto',
                        'guide' => 'Appearance → Customize → Site Identity → Site Icon (ảnh vuông ≥ 512px).',
                        'url' => admin_url('customize.php?autofocus[section]=title_tagline'),
                        'check' => static fn () => has_site_icon() ? [true, 'Đã có'] : [false, 'Chưa có']],
                    ['id' => 'privacy_page', 'title' => 'Có trang Chính sách bảo mật', 'type' => 'auto', 'skippable' => true,
                        'guide' => 'Settings → Privacy: tạo/chọn trang và gắn link ở footer. Site có form thu SĐT/email khách cần thông báo cách dùng dữ liệu (Nghị định 13/2023 về dữ liệu cá nhân).',
                        'url' => admin_url('options-privacy.php'),
                        'check' => static function () {
                            $id = (int) get_option('wp_page_for_privacy_policy');
                            return ($id && 'publish' === get_post_status($id)) ? [true, get_the_title($id)] : [false, 'Chưa có trang đang xuất bản'];
                        }],
                    ['id' => 'analytics', 'title' => 'Gắn Google Analytics / Tag Manager', 'type' => 'manual', 'skippable' => true,
                        'guide' => 'Gắn mã GA4 hoặc GTM (Laca Admin → Header scripts hoặc Rank Math → Analytics), kiểm tra Realtime có lượt truy cập của chính bạn.',
                        'check' => static function () {
                            $scripts = '';
                            if (function_exists('carbon_get_theme_option')) {
                                $scripts = (string) carbon_get_theme_option('crb_header_script') . (string) carbon_get_theme_option('crb_footer_script');
                            }
                            if (preg_match('/\b(G-[A-Z0-9]{6,}|GTM-[A-Z0-9]{4,}|UA-\d+-\d+)\b/', $scripts, $m)) {
                                return [true, 'Mã ' . $m[1] . ' trong Header/Footer scripts'];
                            }
                            $laca_ga = \App\Features\LacaSeo\Core\Settings::get('general.ga4_measurement_id', '');
                            $laca_gtm = \App\Features\LacaSeo\Core\Settings::get('general.gtm_container_id', '');
                            if (!empty($laca_ga) || !empty($laca_gtm)) {
                                return [true, 'Laca SEO chèn mã ' . ($laca_ga ?: $laca_gtm)];
                            }
                            $rm = (array) get_option('rank_math_google_analytic_options', []);
                            if (!empty($rm['install_code']) && !empty($rm['measurement_id'])) {
                                return [true, 'Rank Math chèn mã ' . $rm['measurement_id']];
                            }
                            if (defined('GOOGLESITEKIT_VERSION')) {
                                return [true, 'Site Kit by Google đang bật'];
                            }
                            return [false, 'Chưa thấy mã GA4/GTM'];
                        }],
                    ['id' => 'seo_plugin', 'title' => 'Sitemap, tắt author archive, Search Console', 'type' => 'manual',
                        'guide' => 'Laca SEO / Rank Math: bật Sitemap, Titles & Meta → Authors → tắt Author Archives (tránh lộ username), dán mã xác minh Google Search Console.',
                        'url' => admin_url('admin.php?page=laca-seo'),
                        'check' => static function () {
                            // Check Laca SEO first
                            if (class_exists('\App\Features\LacaSeo\Core\Settings')) {
                                $laca_author_disabled = \App\Features\LacaSeo\Core\Settings::get('titles.disable_author_archives', 'off') === 'on';
                                $laca_google_verify = !empty(\App\Features\LacaSeo\Core\Settings::get('general.google_verify', ''));
                                $laca_sitemap_on = \App\Features\LacaSeo\Core\Modules::isActive('sitemap');

                                $missing = array_keys(array_filter([
                                    'bật Sitemap' => !$laca_sitemap_on,
                                    'tắt Author Archives' => !$laca_author_disabled,
                                    'kết nối Search Console' => !$laca_google_verify,
                                ]));

                                if (!$missing) {
                                    return [true, 'Laca SEO: Sitemap bật · Author Archives tắt · đã kết nối Search Console'];
                                }
                            }

                            if (!class_exists('RankMath')) {
                                return [false, ''];
                            }
                            $titles = (array) get_option('rank-math-options-titles', []);
                            $general = (array) get_option('rank-math-options-general', []);
                            $missing = array_keys(array_filter([
                                'bật Sitemap' => !in_array('sitemap', (array) get_option('rank_math_modules', []), true),
                                'tắt Author Archives' => 'on' !== ($titles['disable_author_archives'] ?? ''),
                                // Kết nối Google trong Rank Math, hoặc dán mã xác minh Search Console.
                                'kết nối Search Console' => !get_option('rank_math_google_oauth_tokens') && empty($general['google_verify']),
                            ]));
                            return [!$missing, $missing ? 'Còn thiếu: ' . implode(', ', $missing) : 'Sitemap bật · Author Archives tắt · đã kết nối Search Console'];
                        }],
                ],
            ],
            [
                'key' => 'ops',
                'title' => 'Sao lưu & vận hành',
                'items' => [
                    ['id' => 'https', 'title' => 'Site chạy HTTPS', 'type' => 'auto', 'high' => true,
                        'guide' => 'Bật AutoSSL trong cPanel, đổi "WordPress Address" và "Site Address" sang https://.',
                        'url' => admin_url('options-general.php'),
                        'check' => static fn () => str_starts_with(home_url(), 'https://') ? [true, 'HTTPS'] : [false, 'Đang dùng http://']],
                    ['id' => 'backup', 'title' => 'Sao lưu tự động ra ngoài hosting', 'type' => 'manual', 'high' => true,
                        'guide' => 'JetBackup (cPanel) hoặc UpdraftPlus → Google Drive, chạy hằng ngày, giữ ≥ 7 bản. Thử khôi phục 1 lần để chắc bản sao lưu dùng được.',
                        // Chỉ đọc được UpdraftPlus; JetBackup nằm ở cPanel ngoài WordPress → tick tay.
                        'check' => static function () {
                            if (!defined('UPDRAFTPLUS_DIR')) {
                                return [false, 'Không thấy plugin sao lưu trong WordPress — dùng JetBackup thì tick tay'];
                            }
                            $remote = array_diff((array) get_option('updraft_service', []), ['', 'none']);
                            $interval = (string) get_option('updraft_interval_database', 'manual');
                            $last = (int) (((array) get_option('updraft_last_backup', []))['backup_time'] ?? 0);
                            $ok = $remote && 'manual' !== $interval;
                            return [$ok, sprintf('UpdraftPlus · lưu tới: %s · lịch DB: %s%s', $remote ? implode(', ', $remote) : 'chỉ trên hosting', $interval, $last ? ' · gần nhất ' . wp_date('d/m H:i', $last) : '')];
                        }],
                    ['id' => 'email_dns', 'title' => 'Tên miền có SPF và DMARC (mail không vào spam)', 'type' => 'auto', 'high' => true,
                        'guide' => 'SPF khai báo máy chủ nào được gửi mail @tên miền, DMARC dặn Gmail/Outlook xử lý mail giả mạo ra sao. Thiếu hoặc sai thì mail @tên miền dễ vào spam và dễ bị giả mạo. Sửa ở nơi quản lý DNS THẬT của tên miền (xem nameserver bằng: dig NS tenmien.com) — sửa ở chỗ khác không có tác dụng.',
                        'steps' => [
                            'SPF — mỗi tên miền CHỈ ĐƯỢC CÓ 1 bản ghi TXT bắt đầu bằng `v=spf1`. Có 2 bản là SPF hỏng (permerror), tương đương không có → gộp tất cả vào 1 bản rồi xoá bản thừa.',
                            'Thêm/sửa SPF: Type `TXT` · Name `@` (hoặc để trống / ghi tenmien.com) · TXT data `v=spf1 a mx include:<SPF của nhà cung cấp mail> ~all`. Gửi qua Google Workspace thì thêm `include:_spf.google.com`.',
                            'Thêm DMARC: Type `TXT` · Name `_dmarc` · TXT data `v=DMARC1; p=none; rua=mailto:<email nhận báo cáo>`. Chạy ổn 2–4 tuần thì nâng `p=quarantine`.',
                            'DKIM: cPanel → Email Deliverability → bấm Repair/Install cho tên miền (cPanel tự tạo bản ghi `default._domainkey`). Dùng email hosting/Google Workspace thì lấy bản ghi DKIM ở trang quản trị của họ.',
                            'Lưu ý: ô Name là TÊN bản ghi (@ hoặc _dmarc), không phải nội dung. Nội dung `v=spf1…` / `v=DMARC1…` dán vào ô TXT data. DNS cập nhật sau 5 phút – vài giờ; checklist tra lại 1 lần/ngày.',
                        ],
                        'check' => static function () {
                            $domain = self::mailDomain();
                            if (preg_match('/(^localhost$|\.(local|test|localhost|invalid)$)|^[^.]+$/', $domain)) {
                                return [false, $domain . ' là tên miền nội bộ — chỉ kiểm tra được trên site thật'];
                            }
                            // KHÔNG tra DNS lúc hiển thị trang: dns_get_record() không có timeout, DNS chậm là
                            // treo cả Laca Admin (đo được 120 giây với tên miền .local). Tra nền qua WP-Cron.
                            $cached = get_transient(self::DNS_TRANSIENT . md5($domain));
                            if (!is_array($cached) || !isset($cached['spf_count'])) {
                                if (!wp_next_scheduled(self::DNS_HOOK)) {
                                    wp_schedule_single_event(time(), self::DNS_HOOK);
                                }
                                return [false, 'Đang tra DNS nền — tải lại trang sau ít phút'];
                            }
                            $problems = [];
                            if (0 === $cached['spf_count']) {
                                $problems[] = 'thiếu SPF';
                            } elseif ($cached['spf_count'] > 1) {
                                $problems[] = 'có ' . $cached['spf_count'] . ' bản ghi SPF (phải gộp còn 1)';
                            }
                            if (!$cached['dmarc']) {
                                $problems[] = 'thiếu DMARC';
                            }
                            $detail = $domain . ($problems ? ': ' . implode(', ', $problems) : ' có SPF + DMARC hợp lệ')
                                . ' · DKIM (default): ' . ($cached['dkim'] ? 'có' : 'chưa thấy');
                            // Gửi SMTP bằng Gmail cá nhân thì mail của site mang tên miền gmail.com → DNS tên
                            // miền không ảnh hưởng mail site, chỉ bảo vệ hộp thư @tên miền.
                            $smtpUser = function_exists('carbon_get_theme_option') ? (string) carbon_get_theme_option('smtp_username') : '';
                            if (str_ends_with(strtolower($smtpUser), '@gmail.com')) {
                                $detail .= ' · Lưu ý: site đang gửi mail qua Gmail cá nhân nên bản ghi này chỉ bảo vệ hộp thư @' . $domain . ', không ảnh hưởng mail của web';
                            }
                            return [!$problems, $detail];
                        }],
                    ['id' => 'unused_code', 'title' => 'Xoá plugin/theme không dùng', 'type' => 'auto',
                        'guide' => 'Plugin/theme đã tắt vẫn nằm trên server và vẫn bị khai thác được nếu có lỗ hổng. Xoá hẳn những gì không dùng (giữ lại parent + child theme đang chạy).',
                        'url' => admin_url('plugins.php?plugin_status=inactive'),
                        'check' => static function () {
                            if (!function_exists('get_plugins')) {
                                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                            }
                            $inactive = array_diff(array_keys(get_plugins()), (array) get_option('active_plugins', []));
                            $themes = array_diff(array_keys(wp_get_themes()), array_unique([get_stylesheet(), get_template()]));
                            $parts = [];
                            if ($inactive) {
                                $parts[] = count($inactive) . ' plugin đang tắt';
                            }
                            if ($themes) {
                                $parts[] = 'theme thừa: ' . implode(', ', $themes);
                            }
                            return $parts ? [false, implode(' · ', $parts)] : [true, 'Không có'];
                        }],
                    ['id' => 'uptime', 'title' => 'Giám sát uptime từ bên ngoài', 'type' => 'manual', 'skippable' => true,
                        'guide' => 'Thêm site vào Uptime Kuma / BetterStack / UptimeRobot (kiểm tra 1–5 phút/lần, gửi cảnh báo về Discord). Discord Hub chỉ báo được khi site còn chạy PHP — site sập hẳn thì cần công cụ bên ngoài.'],
                    ['id' => 'php_version', 'title' => 'PHP 8.1 trở lên', 'type' => 'auto',
                        'guide' => 'cPanel → MultiPHP Manager: chọn PHP 8.2/8.3.',
                        'check' => static fn () => [version_compare(PHP_VERSION, '8.1', '>='), 'PHP ' . PHP_VERSION]],
                    ['id' => 'updates_fresh', 'title' => 'Không có bản cập nhật chờ quá 30 ngày', 'type' => 'auto',
                        'guide' => 'Xem tin "Tổng hợp tuần" ở kênh Discord Updates, sao lưu rồi cập nhật.',
                        'url' => admin_url('update-core.php'),
                        'check' => static function () {
                            $tracked = get_option('laca_dh_updates_notified', []);
                            $oldest = 0;
                            foreach ((array) $tracked as $info) {
                                if (is_array($info) && !empty($info['since'])) {
                                    $oldest = max($oldest, (int) floor((time() - (int) $info['since']) / DAY_IN_SECONDS));
                                }
                            }
                            return $oldest > 30 ? [false, 'Có bản chờ ' . $oldest . ' ngày'] : [true, $tracked ? 'Lâu nhất ' . $oldest . ' ngày' : 'Không có bản chờ'];
                        }],
                    ['id' => 'info_files', 'title' => 'Đã xoá readme.html / license.txt', 'type' => 'auto',
                        'guide' => 'Tự xoá khi admin vào wp-admin và sau mỗi lần cập nhật core. Còn file thì hosting chặn quyền ghi — xoá tay qua File Manager.',
                        'check' => static function () {
                            $left = class_exists('\App\Settings\Security\CoreInfoFileCleanup') ? \App\Settings\Security\CoreInfoFileCleanup::remaining() : [];
                            return $left ? [false, 'Còn: ' . implode(', ', $left)] : [true, 'Đã xoá'];
                        }],
                ],
            ],
        ];

        return (array) apply_filters('laca_release_checklist_groups', $groups);
    }

    /**
     * Cổng thanh toán Live: chỉ tự đạt khi MỌI cổng đang bật đều xác định được là chạy thật —
     * cổng lạ (VNPay, MoMo…) không đọc được chế độ thì để tick tay, thà sót còn hơn báo đạt nhầm.
     */
    public static function checkWooPayments(): array
    {
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return [false, ''];
        }
        $live = [];
        $issues = [];
        foreach (WC()->payment_gateways()->payment_gateways() as $id => $gateway) {
            if ('yes' !== $gateway->enabled) {
                continue;
            }
            $name = wp_strip_all_tags((string) $gateway->get_method_title()) ?: $id;
            if ('bacs' === $id) {
                $accounts = array_filter((array) get_option('woocommerce_bacs_accounts', []), static fn ($a) => !empty($a['account_number']));
                $accounts ? $live[] = $name : $issues[] = $name . ': chưa nhập số tài khoản';
            } elseif (in_array($id, ['cod', 'cheque'], true)) {
                $live[] = $name;
            } elseif (str_starts_with($id, 'ppcp')) {
                $mode = self::paypalMode();
                'live' === $mode ? $live[] = $name : $issues[] = $name . ('sandbox' === $mode ? ': đang SANDBOX' : ': chưa kết nối tài khoản');
            } elseif ('yes' === $gateway->get_option('testmode') || 'yes' === $gateway->get_option('sandbox') || 'yes' === $gateway->get_option('test_mode')) {
                $issues[] = $name . ': đang chế độ test';
            } else {
                $issues[] = $name . ': không đọc được chế độ — kiểm tra tay';
            }
        }
        if (!$live && !$issues) {
            return [false, 'Chưa bật cổng thanh toán nào'];
        }
        return [!$issues, implode(' · ', array_merge($live ? ['Live: ' . implode(', ', $live)] : [], $issues))];
    }

    /**
     * @return string live | sandbox | unknown
     */
    private static function paypalMode(): string
    {
        // Tên option đổi qua các bản WooCommerce PayPal Payments (settings cũ → data-* mới): đọc cả hai.
        $data = array_merge(
            (array) get_option('woocommerce-ppcp-settings', []),
            (array) get_option('woocommerce-ppcp-data-general', []),
            (array) get_option('woocommerce-ppcp-data-common', [])
        );
        foreach (['sandbox_on', 'use_sandbox', 'is_sandbox'] as $key) {
            if (!empty($data[$key])) {
                return 'sandbox';
            }
        }
        $merchant = $data['merchant_id'] ?? $data['live_merchant_id'] ?? $data['merchant_email'] ?? '';
        return '' !== (string) $merchant || !empty($data['merchant_connected']) ? 'live' : 'unknown';
    }

    /**
     * Mỗi trang đang xuất bản có đủ bản dịch, và vị trí menu đã gán thì gán đủ mọi ngôn ngữ.
     * Chuỗi giao diện (Strings translations) không đếm được "đã dịch đúng" → không xét.
     */
    public static function checkPolylang(): array
    {
        if (!function_exists('pll_languages_list') || !function_exists('pll_get_post_translations')) {
            return [false, ''];
        }
        $langs = (array) pll_languages_list();
        if (count($langs) < 2) {
            return [true, 'Chỉ 1 ngôn ngữ'];
        }
        // Lấy post đầy đủ + cache term (bản dịch Polylang lưu dạng term) → get_post_status()/pll_get_post_translations()
        // trong vòng lặp đọc cache, không query từng trang.
        $pages = wp_list_pluck(get_posts([
            'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 200, 'lang' => '',
            'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => true,
        ]), 'ID');
        $seen = [];
        $untranslated = [];
        foreach ($pages as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $translations = (array) pll_get_post_translations($id);
            foreach ($translations as $tid) {
                $seen[$tid] = true;
            }
            $missing = array_diff($langs, array_keys(array_filter($translations, static fn ($tid) => 'publish' === get_post_status($tid))));
            if ($missing) {
                $untranslated[] = get_the_title($id) . ' (thiếu ' . strtoupper(implode(', ', $missing)) . ')';
            }
        }
        $menuIssues = [];
        $options = (array) get_option('polylang', []);
        foreach ((array) ($options['nav_menus'][get_stylesheet()] ?? []) as $location => $byLang) {
            $assigned = array_keys(array_filter((array) $byLang));
            if ($assigned && ($gap = array_diff($langs, $assigned))) {
                $menuIssues[] = 'menu ' . $location . ' thiếu ' . strtoupper(implode(', ', $gap));
            }
        }
        if (!$untranslated && !$menuIssues) {
            return [true, sprintf('%d trang đều có đủ %s', count($seen), strtoupper(implode('/', $langs)))];
        }
        $shown = array_slice($untranslated, 0, 3);
        $more = count($untranslated) - count($shown);
        return [false, implode(' · ', array_merge($shown, $more > 0 ? ['+' . $more . ' trang khác'] : [], $menuIssues))];
    }

    /**
     * Escape rồi đổi `...` thành <code> để giá trị cần copy (tên bản ghi, nội dung TXT) nổi bật.
     */
    private static function inlineCode(string $text): string
    {
        return (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', esc_html($text));
    }

    private static function mailDomain(): string
    {
        return (string) preg_replace('/^www\./', '', (string) wp_parse_url(home_url(), PHP_URL_HOST));
    }

    /**
     * Chạy nền (WP-Cron): tra bản ghi SPF/DMARC, lưu 1 ngày.
     */
    public static function refreshDns(): void
    {
        $domain = self::mailDomain();
        $txt = static function (string $host, string $prefix): int {
            $records = function_exists('dns_get_record') ? @dns_get_record($host, DNS_TXT) : [];
            $count = 0;
            foreach ((array) $records as $r) {
                if (str_starts_with(strtolower((string) ($r['txt'] ?? '')), $prefix)) {
                    $count++;
                }
            }
            return $count;
        };
        set_transient(self::DNS_TRANSIENT . md5($domain), [
            'spf_count' => $txt($domain, 'v=spf1'),
            'dmarc'     => $txt('_dmarc.' . $domain, 'v=dmarc1') > 0,
            'dkim'      => $txt('default._domainkey.' . $domain, 'v=dkim1') > 0,
        ], DAY_IN_SECONDS);
    }

    // ── Trạng thái ────────────────────────────────────────────────────────────

    private static function state(): array
    {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    /**
     * Gắn trạng thái hiện tại cho từng mục: done | todo | fail | skipped.
     *
     * @return array{groups:array,total:int,done:int,fail:int,todo:int,skipped:int}
     */
    public static function evaluate(): array
    {
        $state = self::state();
        $out = ['groups' => [], 'total' => 0, 'done' => 0, 'fail' => 0, 'todo' => 0, 'skipped' => 0];

        foreach (self::groups() as $group) {
            if (isset($group['when']) && is_callable($group['when']) && !call_user_func($group['when'])) {
                continue;
            }
            $items = [];
            foreach ((array) $group['items'] as $item) {
                $id = (string) $item['id'];
                $saved = $state[$id] ?? [];
                $item['meta'] = $saved;
                $item['detail'] = '';

                if (($saved['status'] ?? '') === 'skipped' && !empty($item['skippable'])) {
                    $item['status'] = 'skipped';
                } elseif ('auto' === $item['type'] && isset($item['check']) && is_callable($item['check'])) {
                    try {
                        [$ok, $detail] = call_user_func($item['check']);
                    } catch (\Throwable $e) {
                        [$ok, $detail] = [false, 'Không kiểm được: ' . $e->getMessage()];
                    }
                    $item['status'] = $ok ? 'done' : 'fail';
                    $item['detail'] = (string) $detail;
                } else {
                    $item['status'] = ($saved['status'] ?? '') === 'done' ? 'done' : 'todo';
                    // Mục tick tay có 'check': đọc được cấu hình thì tự đạt (hiện như mục tự kiểm);
                    // chưa đủ dấu hiệu thì giữ tick tay, kèm trạng thái đọc được để biết còn thiếu gì.
                    if (isset($item['check']) && is_callable($item['check'])) {
                        try {
                            [$ok, $detail] = call_user_func($item['check']);
                        } catch (\Throwable $e) {
                            [$ok, $detail] = [false, ''];
                        }
                        $item['detail'] = (string) $detail;
                        if ($ok) {
                            $item['status'] = 'done';
                            $item['type'] = 'auto';
                        }
                    }
                }

                $out[$item['status']]++;
                if ('skipped' !== $item['status']) {
                    $out['total']++;
                }
                $items[] = $item;
            }
            $group['items'] = $items;
            $out['groups'][] = $group;
        }
        return $out;
    }

    // ── Lưu thao tác tick ─────────────────────────────────────────────────────

    public function handle(): void
    {
        $isAjax = wp_doing_ajax() || !empty($_POST['is_ajax']);

        if (!self::canManage()) {
            if ($isAjax) {
                wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'laca')], 403);
            }
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'), '', ['response' => 403]);
        }

        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, self::ACTION)) {
            if ($isAjax) {
                wp_send_json_error(['message' => __('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.', 'laca')], 403);
            }
            check_admin_referer(self::ACTION);
        }

        $id = sanitize_key(wp_unslash($_POST['item'] ?? ''));
        $op = sanitize_key(wp_unslash($_POST['op'] ?? ''));

        $known = [];
        foreach (self::groups() as $group) {
            foreach ((array) $group['items'] as $item) {
                $known[(string) $item['id']] = $item;
            }
        }

        if (isset($known[$id])) {
            $item = $known[$id];
            $state = self::state();
            $stamp = ['by' => wp_get_current_user()->user_login, 'at' => time()];

            if ('done' === $op && 'manual' === $item['type']) {
                $state[$id] = ['status' => 'done'] + $stamp;
            } elseif ('skip' === $op && !empty($item['skippable'])) {
                $state[$id] = ['status' => 'skipped'] + $stamp;
            } elseif (in_array($op, ['undo', 'unskip'], true)) {
                unset($state[$id]);
            }
            update_option(self::STATE_OPTION, $state, false);
        }

        if ($isAjax) {
            $r = self::evaluate();
            $doneCount = $r['done'];
            $total = max(1, $r['total']);
            $percent = (int) round($doneCount / $total * 100);
            $allDone = $r['done'] === $r['total'];

            $highLeft = 0;
            $matchedItem = null;
            $groupStats = [];

            foreach ($r['groups'] as $group) {
                $gDone = count(array_filter($group['items'], static fn ($i) => 'done' === $i['status']));
                $gTotal = count(array_filter($group['items'], static fn ($i) => 'skipped' !== $i['status']));
                $groupStats[$group['key']] = [
                    'done' => $gDone,
                    'total' => $gTotal,
                    'is_complete' => $gDone === $gTotal,
                    'text' => sprintf('%d/%d', $gDone, $gTotal),
                ];

                foreach ($group['items'] as $it) {
                    if (!empty($it['high']) && in_array($it['status'], ['fail', 'todo'], true)) {
                        $highLeft++;
                    }
                    if ($it['id'] === $id) {
                        $matchedItem = $it;
                    }
                }
            }

            $itemHtml = '';
            if ($matchedItem) {
                ob_start();
                $this->renderItem($matchedItem, false);
                $itemHtml = ob_get_clean();
            }

            $summaryParts = [];
            if ($allDone) {
                $summaryHtml = esc_html__('Mọi mục đã đạt. Mục tự kiểm sẽ tự báo lại nếu cấu hình bị đổi.', 'laca');
            } else {
                if ($highLeft) {
                    $summaryParts[] = '<strong class="laca-rc__danger">' . esc_html(sprintf(__('%d mục quan trọng chưa xong', 'laca'), $highLeft)) . '</strong>';
                }
                $summaryParts[] = esc_html(sprintf(__('%d tự kiểm chưa đạt · %d chưa tick', 'laca'), $r['fail'], $r['todo']));
                if ($r['skipped']) {
                    $summaryParts[] = esc_html(sprintf(__(' · %d không áp dụng', 'laca'), $r['skipped']));
                }
                $summaryHtml = implode(' · ', $summaryParts);
            }

            wp_send_json_success([
                'id' => $id,
                'op' => $op,
                'item_html' => $itemHtml,
                'percent' => $percent,
                'done_count' => $doneCount,
                'total_count' => $r['total'],
                'count_text' => sprintf('%d / %d mục', $doneCount, $r['total']),
                'title_text' => $allDone ? __('Sẵn sàng production', 'laca') : __('Checklist phát hành production', 'laca'),
                'summary_html' => $summaryHtml,
                'all_done' => $allDone,
                'groups' => $groupStats,
            ]);
        }

        wp_safe_redirect(add_query_arg('rc', $id, admin_url('admin.php?page=laca-admin')) . '#rc-' . $id);
        exit;
    }

    // ── Giao diện ─────────────────────────────────────────────────────────────

    public function render(): void
    {
        if ('laca-admin' !== sanitize_key(wp_unslash($_GET['page'] ?? '')) || !self::canManage()) {
            return;
        }

        $r = self::evaluate();
        $doneCount = $r['done'];
        $total = max(1, $r['total']);
        $percent = (int) round($doneCount / $total * 100);
        $allDone = $r['done'] === $r['total'];

        // Mặc định đóng hết. Chỉ khi vừa tick/bỏ qua 1 mục (redirect kèm ?rc=) mới mở lại nhóm đó
        // để không mất chỗ đang làm.
        $openId = sanitize_key(wp_unslash($_GET['rc'] ?? ''));
        $highLeft = 0;
        foreach ($r['groups'] as $group) {
            foreach ($group['items'] as $item) {
                if (!empty($item['high']) && in_array($item['status'], ['fail', 'todo'], true)) {
                    $highLeft++;
                }
            }
        }
        ?>
        <section class="laca-rc<?php echo $allDone ? ' is-complete' : ''; ?>" aria-labelledby="laca-rc-title">
            <header class="laca-rc__head">
                <div class="laca-rc__head-row">
                    <h2 id="laca-rc-title" class="laca-rc__title"><?php echo esc_html($allDone ? 'Sẵn sàng production' : 'Checklist phát hành production'); ?></h2>
                    <span class="laca-rc__count"><?php echo esc_html(sprintf('%d / %d mục', $doneCount, $r['total'])); ?></span>
                </div>
                <div class="laca-rc__bar" role="progressbar" aria-label="Tiến độ checklist" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $percent); ?>">
                    <span style="width: <?php echo esc_attr((string) $percent); ?>% /* động */"></span>
                </div>
                <p class="laca-rc__summary">
                    <?php if ($allDone) : ?>
                        Mọi mục đã đạt. Mục tự kiểm sẽ tự báo lại nếu cấu hình bị đổi.
                    <?php else : ?>
                        <?php if ($highLeft) : ?>
                            <strong class="laca-rc__danger"><?php echo esc_html(sprintf('%d mục quan trọng chưa xong', $highLeft)); ?></strong> ·
                        <?php endif; ?>
                        <?php echo esc_html(sprintf('%d tự kiểm chưa đạt · %d chưa tick', $r['fail'], $r['todo'])); ?>
                        <?php if ($r['skipped']) : ?>
                            <?php echo esc_html(sprintf(' · %d không áp dụng', $r['skipped'])); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </header>

            <?php foreach ($r['groups'] as $group) :
                $gDone = count(array_filter($group['items'], static fn ($i) => 'done' === $i['status']));
                $gTotal = count(array_filter($group['items'], static fn ($i) => 'skipped' !== $i['status']));
                $gComplete = $gDone === $gTotal;
                $gOpen = '' !== $openId && in_array($openId, array_column($group['items'], 'id'), true);
                // name chung = accordion độc quyền do trình duyệt xử lý: mở nhóm này thì nhóm đang mở tự đóng.
                ?>
                <details class="laca-rc__group" data-group-key="<?php echo esc_attr($group['key']); ?>" name="laca-rc-group" <?php echo $gOpen ? 'open' : ''; ?>>
                    <summary class="laca-rc__group-head">
                        <span class="laca-rc__group-title"><?php echo esc_html($group['title']); ?></span>
                        <span class="laca-rc__group-count<?php echo $gComplete ? ' is-complete' : ''; ?>"><?php echo esc_html(sprintf('%d/%d', $gDone, $gTotal)); ?></span>
                        <?php echo self::icon('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </summary>
                    <ul class="laca-rc__list">
                        <?php foreach ($group['items'] as $item) {
                            $this->renderItem($item, false);
                        } ?>
                    </ul>
                </details>
            <?php endforeach; ?>
        </section>
        <?php
    }

    private function renderItem(array $item, bool $open): void
    {
        $status = $item['status'];
        $isAuto = 'auto' === $item['type'];
        $title = (string) $item['title'];
        $meta = (array) ($item['meta'] ?? []);
        ?>
        <li class="laca-rc__item is-<?php echo esc_attr($status); ?>" id="rc-<?php echo esc_attr($item['id']); ?>">
            <div class="laca-rc__mark">
                <?php if ($isAuto && 'skipped' !== $status) : ?>
                    <span class="laca-rc__circle is-<?php echo esc_attr($status); ?>" role="img" aria-label="<?php echo esc_attr('done' === $status ? 'Tự kiểm: đạt' : 'Tự kiểm: chưa đạt'); ?>">
                        <?php echo self::icon('done' === $status ? 'check' : 'x'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </span>
                <?php else :
                    $op = ['done' => 'undo', 'skipped' => 'unskip'][$status] ?? 'done';
                    $label = ['done' => 'Bỏ đánh dấu xong: ', 'skipped' => 'Bỏ "không áp dụng": '][$status] ?? 'Đánh dấu xong: ';
                    ?>
                    <?php $this->openForm($item['id'], $op); ?>
                        <button type="submit" class="laca-rc__tick" aria-label="<?php echo esc_attr($label . $title); ?>">
                            <span class="laca-rc__circle is-<?php echo esc_attr($status); ?> is-button"><?php echo 'todo' === $status ? '' : self::icon('skipped' === $status ? 'minus' : 'check'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <details class="laca-rc__detail" name="laca-rc-item" <?php echo $open ? 'open' : ''; ?>>
                <summary class="laca-rc__item-head">
                    <span class="laca-rc__item-text">
                        <span class="laca-rc__item-title"><?php echo esc_html($title); ?></span>
                        <?php if (!empty($item['high']) && in_array($status, ['fail', 'todo'], true)) : ?>
                            <span class="laca-rc__badge is-danger">Quan trọng</span>
                        <?php endif; ?>
                    </span>
                    <?php echo self::icon('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </summary>
                <div class="laca-rc__body">
                    <p class="laca-rc__guide"><?php echo esc_html((string) $item['guide']); ?></p>
                    <?php if (!empty($item['steps'])) : ?>
                        <ol class="laca-rc__steps">
                            <?php foreach ((array) $item['steps'] as $step) : ?>
                                <li><?php echo self::inlineCode((string) $step); // phpcs:ignore WordPress.Security.EscapeOutput -- escape bên trong ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    <?php if ('' !== $item['detail']) : ?>
                        <p class="laca-rc__state is-<?php echo esc_attr($status); ?>"><?php echo esc_html('Hiện tại: ' . $item['detail']); ?></p>
                    <?php endif; ?>
                    <?php
                    $canSkip = !empty($item['skippable']) && in_array($status, ['todo', 'fail'], true);
                    if (!empty($item['url']) || ('manual' === $item['type'] && 'todo' === $status) || $canSkip) :
                    ?>
                    <div class="laca-rc__actions">
                        <?php if (!empty($item['url'])) : ?>
                            <a class="button" href="<?php echo esc_url((string) $item['url']); ?>" target="_blank" rel="noopener">Mở cài đặt<span class="screen-reader-text"> (mở tab mới)</span></a>
                        <?php endif; ?>
                        <?php if ('manual' === $item['type'] && 'todo' === $status) : ?>
                            <?php $this->openForm($item['id'], 'done'); ?>
                                <button type="submit" class="button button-primary">Đánh dấu đã xong</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canSkip) : ?>
                            <?php $this->openForm($item['id'], 'skip'); ?>
                                <button type="submit" class="button-link laca-rc__skip">Không áp dụng cho site này</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($meta['by']) && in_array($status, ['done', 'skipped'], true)) : ?>
                        <p class="laca-rc__meta"><?php echo esc_html(sprintf('%s bởi %s · %s', 'skipped' === $status ? 'Đánh dấu không áp dụng' : 'Đánh dấu xong', $meta['by'], wp_date('d/m/Y H:i', (int) $meta['at']))); ?></p>
                    <?php endif; ?>
                </div>
            </details>
        </li>
        <?php
    }

    private function openForm(string $id, string $op): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-rc__form">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
            <input type="hidden" name="item" value="<?php echo esc_attr($id); ?>">
            <input type="hidden" name="op" value="<?php echo esc_attr($op); ?>">
            <?php wp_nonce_field(self::ACTION); ?>
        <?php
    }

    /**
     * Icon nét (lucide) inline — admin không có bộ icon chung ngoài dashicons.
     */
    private static function icon(string $name): string
    {
        $paths = [
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
            'minus' => '<path d="M5 12h14"/>',
            'chevron' => '<path d="m6 9 6 6 6-6"/>',
        ];
        $class = 'chevron' === $name ? 'laca-rc__chevron' : 'laca-rc__icon';
        return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
    }

    private function printStyles(): void
    {
        // Styles moved to resources/styles/admin/skins/_self-authored.scss
    }
}
