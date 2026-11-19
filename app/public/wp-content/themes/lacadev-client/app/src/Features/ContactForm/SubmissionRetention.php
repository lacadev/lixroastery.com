<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * Giữ submission form liên hệ NGẮN HẠN: email mới là kênh chính, DB chỉ là
 * bản dự phòng khi mail lỗi → tự xoá bản cũ hơn N ngày (mặc định 90) để
 * không giữ dữ liệu cá nhân của khách mãi mãi.
 *
 * Đăng ký ở mọi ngữ cảnh (không chỉ admin) vì WP-Cron chạy ngoài admin.
 */
class SubmissionRetention
{
    public const OPTION        = 'laca_cf_retention_days';
    public const DEFAULT_DAYS  = 90;
    public const MAX_DAYS      = 3650;
    public const CRON_HOOK     = 'laca_cf_purge_submissions';
    public const ACTION_SAVE   = 'laca_cf_save_retention';
    // Cờ chạy 1 lần: submission cũ trước khi có cơ chế "xem là đã đọc" đều
    // đang is_read=0 (không ai bấm từng dòng) → đưa về đã đọc.
    private const LEGACY_FLAG  = 'laca_cf_legacy_read_done';
    private const NONCE_ACTION = 'laca_cf_retention';

    public function register(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'purge']);
        add_action('init', [self::class, 'schedule']);

        if (is_admin()) {
            add_action('admin_init', [self::class, 'markLegacyRead']);
            add_action('admin_post_' . self::ACTION_SAVE, [self::class, 'handleSave']);
        }
    }

    /** Số ngày giữ submission; 0 = không tự xoá. */
    public static function days(): int
    {
        $raw = get_option(self::OPTION, self::DEFAULT_DAYS);
        return max(0, min(self::MAX_DAYS, (int) $raw));
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function purge(): void
    {
        ContactFormTable::purgeOlderThan(self::days());
    }

    public static function markLegacyRead(): void
    {
        if (get_option(self::LEGACY_FLAG)) {
            return;
        }
        ContactFormTable::markAllReadEverywhere();
        update_option(self::LEGACY_FLAG, 1, false);
    }

    public static function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION);

        $days = max(0, min(self::MAX_DAYS, absint(wp_unslash($_POST['retention_days'] ?? self::DEFAULT_DAYS))));
        update_option(self::OPTION, $days, false);
        // Áp dụng ngay, không chờ lượt cron kế tiếp.
        self::purge();

        wp_safe_redirect(admin_url('admin.php?page=laca-contact-forms&laca_msg=retention_saved'));
        exit;
    }

    /** Ô cài đặt nhỏ ở trang danh sách form. */
    public static function renderSettingsForm(): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-cf-retention">
            <?php wp_nonce_field(self::NONCE_ACTION); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SAVE); ?>">
            <label for="laca-cf-retention-days"><?php esc_html_e('Tự xoá submission cũ hơn', 'laca'); ?></label>
            <input type="number" id="laca-cf-retention-days" name="retention_days" min="0" max="<?php echo esc_attr((string) self::MAX_DAYS); ?>" value="<?php echo esc_attr((string) self::days()); ?>" class="small-text">
            <span><?php esc_html_e('ngày (0 = không tự xoá). Email vẫn là kênh nhận chính.', 'laca'); ?></span>
            <button type="submit" class="button"><?php esc_html_e('Lưu', 'laca'); ?></button>
        </form>
        <?php
    }
}
