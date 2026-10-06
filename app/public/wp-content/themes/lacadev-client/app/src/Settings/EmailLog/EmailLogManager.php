<?php

namespace App\Settings\EmailLog;

/**
 * EmailLogManager
 *
 * Intercepts all wp_mail() calls to log them.
 * Admin page: Appearance > Email Log
 */
class EmailLogManager
{
    const MENU_SLUG   = 'laca-email-log';
    const PARENT_SLUG = 'laca-admin';
    const CAP         = 'manage_options';

    /**
     * Hàng đợi theo thứ tự gọi wp_mail() trong 1 request — mỗi lần gọi đẩy
     * thêm 1 "phiếu" mặc định status "sent"; nếu wp_mail_failed bắn ra (luôn
     * xảy ra NGAY trong chính lần gọi wp_mail() đó, trước khi lần gọi tiếp
     * theo diễn ra) thì sửa lại phiếu CUỐI CÙNG trong hàng đợi thành
     * "failed". Lúc này mới ghi hết vào DB qua shutdown, tránh ghi "sent"
     * cho mọi email kể cả khi gửi thất bại (bug thật đã gặp — EmailLog
     * trước đây luôn hiện "Đã gửi" dù SMTP lỗi, khiến không thể dùng log
     * này để chẩn đoán vì sao khách không nhận được mail).
     *
     * @var array<int, array{to:string,subject:string,source:string,status:string}>
     */
    private array $pendingLogs = [];

    public function init(): void
    {
        // Hook into wp_mail filter to intercept all outgoing mail
        add_filter('wp_mail', [$this, 'interceptMail'], 999);
        add_action('wp_mail_failed', [$this, 'markLastFailed']);

        // Admin menu
        add_action('admin_menu', [$this, 'registerMenu'], 20);
    }

    // ── Mail interception ─────────────────────────────────────────────────────

    public function interceptMail(array $args): array
    {
        $to      = is_array($args['to']) ? implode(', ', $args['to']) : (string) ($args['to'] ?? '');
        $subject = $args['subject'] ?? '';
        $source  = $this->detectSource();

        if (empty($this->pendingLogs)) {
            add_action('shutdown', [$this, 'flushPendingLogs']);
        }

        $this->pendingLogs[] = [
            'to'      => $to,
            'subject' => $subject,
            'source'  => $source,
            'status'  => 'sent',
        ];

        return $args;
    }

    /**
     * @param \WP_Error $error Lỗi PHPMailer — không dùng tới nội dung lỗi,
     *                         chỉ cần biết lần gửi NGAY TRƯỚC đó đã fail.
     */
    public function markLastFailed($error): void
    {
        $lastIndex = array_key_last($this->pendingLogs);
        if (null !== $lastIndex) {
            $this->pendingLogs[$lastIndex]['status'] = 'failed';
        }
    }

    public function flushPendingLogs(): void
    {
        foreach ($this->pendingLogs as $log) {
            EmailLogTable::log($log['to'], $log['subject'], $log['status'], $log['source']);
        }
        $this->pendingLogs = [];
    }

    private function detectSource(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if (str_contains($file, 'ContactFormEmailService')) return 'contact-form';
            if (str_contains($file, 'ProjectNotification'))     return 'project-alert';
            if (str_contains($file, 'woocommerce'))             return 'woocommerce';
        }
        return 'wordpress';
    }

    // ── Admin menu ────────────────────────────────────────────────────────────

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            'Email Log',
            'Email Log',
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    // ── Page ──────────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }

        $page    = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 30;
        $status  = sanitize_key($_GET['log_status'] ?? '');
        $logs    = EmailLogTable::getLogs($page, $perPage, $status);
        $total   = EmailLogTable::countLogs($status);
        $pages   = (int) ceil($total / $perPage);
        $pageUrl = admin_url('themes.php?page=' . self::MENU_SLUG);
        ?>
        <div class="wrap">
            <h1>📨 Email Log <span class="title-count"><?php echo esc_html($total); ?></span></h1>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">
                <p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Nhật ký email</p>
                <p style="margin:0;font-size:13px;color:#374151">Trang này ghi lại các email mà website đã gửi (ví dụ: email liên hệ, thông báo đơn hàng). Dùng để kiểm tra khi khách hàng báo không nhận được email — xem email đã thực sự được gửi đi từ site hay chưa.</p>
            </div>
            <p style="color:#666">Log mọi email gửi đi trong 90 ngày gần nhất. Tự động xoá sau 90 ngày.</p>

            <!-- Filter -->
            <ul class="subsubsub" style="margin-bottom:10px">
                <li><a href="<?php echo esc_url($pageUrl); ?>" <?php echo !$status ? 'class="current"' : ''; ?>>Tất cả <span class="count">(<?php echo EmailLogTable::countLogs(); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url($pageUrl . '&log_status=sent'); ?>" <?php echo $status === 'sent' ? 'class="current"' : ''; ?>>Đã gửi <span class="count">(<?php echo EmailLogTable::countLogs('sent'); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url($pageUrl . '&log_status=failed'); ?>" <?php echo $status === 'failed' ? 'class="current"' : ''; ?>>Thất bại <span class="count">(<?php echo EmailLogTable::countLogs('failed'); ?>)</span></a></li>
            </ul>

            <?php if (empty($logs)): ?>
                <div style="padding:30px;text-align:center;background:#f9f9f9;border:1px dashed #ddd;border-radius:4px">
                    <p>Chưa có email nào được ghi lại.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th>Gửi đến</th>
                            <th>Tiêu đề</th>
                            <th style="width:120px">Nguồn</th>
                            <th style="width:80px">Trạng thái</th>
                            <th style="width:160px">Thời gian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="color:#999"><?php echo esc_html($log['id']); ?></td>
                            <td><?php echo esc_html($log['to_email']); ?></td>
                            <td><?php echo esc_html($log['subject']); ?></td>
                            <td>
                                <span style="font-size:11px;background:#f0f0f1;padding:2px 7px;border-radius:10px">
                                    <?php echo esc_html($log['source'] ?: 'wordpress'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ('failed' === $log['status']): ?>
                                    <span style="color:#842029;font-size:11px;font-weight:700">✕ Thất bại</span>
                                <?php else: ?>
                                    <span style="color:#155724;font-size:11px;font-weight:700">✓ Đã gửi</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;color:#666">
                                <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($log['sent_at']))); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages" style="margin-top:10px">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <a href="<?php echo esc_url($pageUrl . ($status ? '&log_status=' . $status : '') . '&paged=' . $i); ?>"
                               class="button button-small <?php echo $i === $page ? 'button-primary' : ''; ?>">
                                <?php echo esc_html($i); ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
