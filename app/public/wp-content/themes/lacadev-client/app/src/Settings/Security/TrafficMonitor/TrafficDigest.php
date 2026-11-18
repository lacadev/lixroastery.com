<?php

namespace App\Settings\Security\TrafficMonitor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron hàng giờ (`laca_tm_digest`) tổng hợp sự kiện watch/attack:
 * gửi email định kỳ, bắn action `laca_traffic_monitor_digest` cho DiscordHub, và hiển thị admin notice.
 */
final class TrafficDigest
{
    public const CRON_HOOK        = 'laca_tm_digest';
    public const OPT_LAST_ID      = 'laca_tm_digest_last_id';
    public const OPT_SUMMARY      = 'laca_tm_digest_summary';
    public const OPT_DISMISSED    = 'laca_tm_digest_dismissed';
    public const DISMISS_ACTION   = 'laca_tm_dismiss';

    public function register(): void
    {
        add_action('admin_init', [self::class, 'syncSchedule']);
        add_action(self::CRON_HOOK, [self::class, 'runDigest']);
        add_action('admin_notices', [self::class, 'renderAdminNotice']);
        add_action('admin_post_' . self::DISMISS_ACTION, [self::class, 'handleDismiss']);
    }

    public static function syncSchedule(): void
    {
        $s      = TrafficSettings::settings();
        $active = ((int) ($s['tracking'] ?? 0) === 1) || TrafficSettings::hasActiveFirewallRule($s);

        if ($active) {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
            }
        } else {
            if (wp_next_scheduled(self::CRON_HOOK)) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
            }
        }
    }

    public static function runDigest(): void
    {
        if (!TrafficTable::isReady()) {
            return;
        }

        global $wpdb;
        $eventsTable = TrafficTable::eventsTable();
        $lastId      = (int) get_option(self::OPT_LAST_ID, 0);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, ip, category, reason, visited_at
                 FROM {$eventsTable}
                 WHERE id > %d AND category IN ('watch', 'attack')
                 ORDER BY id ASC
                 LIMIT 500",
                $lastId
            )
        );

        if (!$rows) {
            return;
        }

        $maxId = 0;
        $ips   = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($id > $maxId) {
                $maxId = $id;
            }
            if (!empty($row->ip)) {
                $ips[(string) $row->ip] = true;
            }
        }

        update_option(self::OPT_LAST_ID, $maxId, false);

        $count     = count($rows);
        $ipCount   = count($ips);
        $sampleIps = array_slice(array_keys($ips), 0, 15);
        $summary   = [
            'count'      => $count,
            'ips'        => $ipCount,
            'sample_ips' => $sampleIps,
            'time'       => current_time('mysql'),
        ];

        update_option(self::OPT_SUMMARY, $summary, false);

        $settings = TrafficSettings::settings();
        if ((int) ($settings['digest_email'] ?? 0) === 1 && $count > 0) {
            $adminEmail = get_option('admin_email');
            if (is_string($adminEmail) && is_email($adminEmail)) {
                $siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
                $subject  = sprintf(
                    /* translators: 1: tên website, 2: số lượt truy cập nghi ngờ/tấn công */
                    __('[%1$s] Giám sát truy cập: %2$d lượt nghi ngờ/tấn công', 'laca'),
                    $siteName,
                    $count
                );

                $body = sprintf(
                    /* translators: 1: số lượt nghi ngờ/tấn công, 2: số lượng IP duy nhất, 3: danh sách IP mẫu, 4: URL trang quản trị */
                    __("Hệ thống Giám sát truy cập phát hiện %1\$d lượt thuộc nhóm Nghi ngờ / Dấu hiệu dò quét từ %2\$d địa chỉ IP trong đợt tổng hợp gần nhất.\n\nIP mẫu (tối đa 15):\n%3\$s\n\nXem chi tiết tại: %4\$s\n\nLưu ý: Phân loại chỉ dựa trên dấu hiệu kỹ thuật, cần kiểm tra nhật ký trước khi chặn. Email được tổng hợp tối đa mỗi giờ một lần.", 'laca'),
                    $count,
                    $ipCount,
                    implode("\n", $sampleIps),
                    admin_url('admin.php?page=laca-traffic')
                );

                wp_mail($adminEmail, $subject, $body);
            }
        }

        do_action('laca_traffic_monitor_digest', $summary);
    }

    public static function renderAdminNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $page          = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $isLacaPage    = $page !== '' && str_starts_with($page, 'laca-');
        $screen        = function_exists('get_current_screen') ? get_current_screen() : null;
        $isWpDashboard = is_object($screen) && ($screen->id ?? '') === 'dashboard';

        if (!$isLacaPage && !$isWpDashboard) {
            return;
        }

        $summary = get_option(self::OPT_SUMMARY, []);
        if (!is_array($summary) || empty($summary['time']) || empty($summary['count'])) {
            return;
        }

        if ((string) get_option(self::OPT_DISMISSED, '') === (string) $summary['time']) {
            return;
        }

        $viewUrl    = admin_url('admin.php?page=laca-traffic');
        $dismissUrl = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::DISMISS_ACTION),
            self::DISMISS_ACTION
        );
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__('Giám sát truy cập:', 'laca'); ?></strong>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: số lượt nghi ngờ/tấn công, 2: số IP */
                        __('%1$d lượt truy cập nghi ngờ/tấn công từ %2$d IP trong đợt tổng hợp gần nhất.', 'laca'),
                        (int) $summary['count'],
                        (int) ($summary['ips'] ?? 0)
                    )
                );
                ?>
                <a href="<?php echo esc_url($viewUrl); ?>"><?php echo esc_html__('Xem nhật ký', 'laca'); ?></a>
                |
                <a href="<?php echo esc_url($dismissUrl); ?>"><?php echo esc_html__('Ẩn thông báo', 'laca'); ?></a>
            </p>
        </div>
        <?php
    }

    public static function handleDismiss(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'), 403);
        }

        check_admin_referer(self::DISMISS_ACTION);

        $summary = get_option(self::OPT_SUMMARY, []);
        if (is_array($summary) && !empty($summary['time'])) {
            update_option(self::OPT_DISMISSED, (string) $summary['time'], false);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=laca-traffic'));
        exit;
    }
}
