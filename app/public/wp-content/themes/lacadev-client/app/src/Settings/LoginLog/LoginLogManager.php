<?php

namespace App\Settings\LoginLog;

/**
 * LoginLogManager
 *
 * Ghi lại lý do thật của mỗi lần đăng nhập thất bại (qua hook wp_login_failed,
 * nhận được WP_Error gốc trước khi bị `login_errors` filter ẩn đi ở
 * theme/setup/performance.php) — cho phép admin kiểm tra chính xác lỗi khi
 * người khác báo không đăng nhập được, mà vẫn không lộ chi tiết ra ngoài.
 *
 * Admin page: Laca Admin > Login Log
 */
class LoginLogManager
{
    const MENU_SLUG   = 'laca-login-log';
    const PARENT_SLUG = 'laca-admin';
    const CAP         = 'manage_options';

    /**
     * Nhãn tiếng Việt dễ hiểu cho các mã lỗi WP_Error thường gặp — mã lạ
     * không có trong danh sách này vẫn hiển thị nguyên mã, không lỗi.
     */
    const ERROR_LABELS = [
        'invalid_username'   => 'Sai tên đăng nhập / email',
        'incorrect_password'  => 'Sai mật khẩu',
        'empty_username'      => 'Chưa nhập tên đăng nhập',
        'empty_password'       => 'Chưa nhập mật khẩu',
        'too_many_attempts'    => 'Vượt quá số lần thử (rate limit)',
        'invalid_email'        => 'Email không hợp lệ',
        'authentication_failed' => 'Xác thực thất bại',
    ];

    public function init(): void
    {
        add_action('wp_login_failed', [$this, 'logFailedAttempt'], 10, 2);
        add_action('admin_menu', [$this, 'registerMenu'], 20);
    }

    // ── Ghi log ──────────────────────────────────────────────────────────────

    public function logFailedAttempt(string $username, $error = null): void
    {
        $code    = 'unknown';
        $message = '';

        if ($error instanceof \WP_Error) {
            $code    = $error->get_error_code() ?: 'unknown';
            $message = $error->get_error_message();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        LoginLogTable::log($username, $ip, $code, $message);
    }

    // ── Admin menu ───────────────────────────────────────────────────────────

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            'Login Log',
            'Login Log',
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    private function errorLabel(string $code): string
    {
        return self::ERROR_LABELS[$code] ?? $code;
    }

    // ── Page ─────────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }

        $page      = max(1, absint($_GET['paged'] ?? 1));
        $perPage   = 30;
        $errorCode = sanitize_key($_GET['error_code'] ?? '');
        $logs      = LoginLogTable::getLogs($page, $perPage, $errorCode);
        $total     = LoginLogTable::countLogs($errorCode);
        $pages     = (int) ceil($total / $perPage);
        $pageUrl   = add_query_arg(['page' => self::MENU_SLUG], admin_url('admin.php'));
        $errorCodes = LoginLogTable::getDistinctErrorCodes();
        ?>
        <div class="wrap">
            <h1>🔐 Login Log <span class="title-count"><?php echo esc_html($total); ?></span></h1>
            <div class="laca-alert laca-alert--info laca-mb-3">
                <div class="laca-alert__title">🔧 Nhật ký đăng nhập thất bại</div>
                <div class="laca-alert__desc">Trang đăng nhập chỉ hiện 1 câu thông báo chung cho người dùng (để tránh dò tên đăng nhập hợp lệ), nhưng lý do thật vẫn được ghi lại ở đây — dùng để kiểm tra khi có người báo không đăng nhập được.</div>
            </div>
            <p class="laca-fg-muted">Log mọi lần đăng nhập thất bại trong 90 ngày gần nhất. Tự động xoá sau 90 ngày.</p>

            <!-- Filter -->
            <ul class="subsubsub laca-mb-3">
                <li><a href="<?php echo esc_url($pageUrl); ?>" <?php echo !$errorCode ? 'class="current"' : ''; ?>>Tất cả <span class="count">(<?php echo LoginLogTable::countLogs(); ?>)</span></a> <?php echo $errorCodes ? '|' : ''; ?></li>
                <?php foreach ($errorCodes as $i => $code) : ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('error_code', $code, $pageUrl)); ?>" <?php echo $errorCode === $code ? 'class="current"' : ''; ?>>
                            <?php echo esc_html($this->errorLabel($code)); ?>
                        </a>
                        <?php echo $i < count($errorCodes) - 1 ? '|' : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (empty($logs)) : ?>
                <div class="laca-log-empty">
                    <p>Chưa có lần đăng nhập thất bại nào được ghi lại.</p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="laca-col-id">ID</th>
                            <th>Tên đăng nhập / Email đã nhập</th>
                            <th class="laca-col-ip">IP</th>
                            <th class="laca-col-reason">Lý do</th>
                            <th class="laca-col-time">Thời gian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td class="laca-col-id"><?php echo esc_html($log['id']); ?></td>
                            <td><?php echo esc_html($log['username']); ?></td>
                            <td class="laca-col-ip"><?php echo esc_html($log['ip']); ?></td>
                            <td>
                                <span class="laca-status-danger">
                                    <?php echo esc_html($this->errorLabel($log['error_code'])); ?>
                                </span>
                            </td>
                            <td class="laca-col-time">
                                <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($log['attempted_at']))); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages laca-mt-2">
                        <?php for ($i = 1; $i <= $pages; $i++) : ?>
                            <a href="<?php echo esc_url(add_query_arg(array_filter(['error_code' => $errorCode, 'paged' => $i]), $pageUrl)); ?>"
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
