<?php

/**
 * Trang admin "Newsletter": xem danh sách email đăng ký ở footer theo trạng
 * thái, tìm kiếm, xoá và xuất CSV (mở được bằng Excel / Google Sheets).
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterAdminPage
{
    private const SLUG = 'lix-newsletter';
    private const PER_PAGE = 50;

    /**
     * Shop Manager (WooCommerce) cũng cần xem/xuất danh sách khách đăng ký —
     * manage_woocommerce có ở cả Administrator lẫn Shop Manager. Phần cấu
     * hình API key vẫn nằm ở Theme Options, chỉ Administrator vào được.
     */
    private function capability(): string
    {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }

    private function statusLabels(): array
    {
        return [
            NewsletterSubscribers::STATUS_SUBSCRIBED => __('Đã đăng ký', 'laca'),
            NewsletterSubscribers::STATUS_PENDING => __('Chờ xác nhận', 'laca'),
            NewsletterSubscribers::STATUS_UNSUBSCRIBED => __('Đã huỷ', 'laca'),
        ];
    }

    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_lix_newsletter_export', [$this, 'exportCsv']);
        add_action('admin_post_lix_newsletter_delete', [$this, 'deleteSubscriber']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Newsletter', 'laca'),
            __('Newsletter', 'laca'),
            $this->capability(),
            self::SLUG,
            [$this, 'render'],
            'dashicons-email-alt',
            58
        );
    }

    public function render(): void
    {
        if (!current_user_can($this->capability())) {
            return;
        }

        $labels = $this->statusLabels();
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        if (!isset($labels[$status])) {
            $status = '';
        }
        $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);

        $counts = NewsletterSubscribers::countByStatus();
        $total = NewsletterSubscribers::count($search, $status);
        $rows = NewsletterSubscribers::query($search, self::PER_PAGE, ($paged - 1) * self::PER_PAGE, $status);
        $total_pages = (int) ceil($total / self::PER_PAGE);

        $page_url = admin_url('admin.php?page=' . self::SLUG);
        $export_url = wp_nonce_url(add_query_arg(['action' => 'lix_newsletter_export', 'status' => $status], admin_url('admin-post.php')), 'lix_newsletter_export');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Newsletter — Email đăng ký', 'laca'); ?></h1>
            <a href="<?php echo esc_url($export_url); ?>" class="page-title-action">
                <?php echo $status
                    /* translators: %s: tên trạng thái */
                    ? esc_html(sprintf(__('Xuất CSV (%s)', 'laca'), $labels[$status]))
                    : esc_html__('Xuất CSV', 'laca'); ?>
            </a>
            <hr class="wp-header-end">

            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Đã xoá email.', 'laca'); ?></p></div>
            <?php endif; ?>

            <?php $this->renderSyncStatus(); ?>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url($page_url); ?>" class="<?php echo $status === '' ? 'current' : ''; ?>">
                        <?php esc_html_e('Tất cả', 'laca'); ?> <span class="count">(<?php echo esc_html(number_format_i18n($counts['all'])); ?>)</span>
                    </a> |
                </li>
                <?php
                $last = array_key_last($labels);
                foreach ($labels as $key => $label): ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('status', $key, $page_url)); ?>" class="<?php echo $status === $key ? 'current' : ''; ?>">
                            <?php echo esc_html($label); ?> <span class="count">(<?php echo esc_html(number_format_i18n($counts[$key])); ?>)</span>
                        </a><?php echo $key !== $last ? ' |' : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <?php if ($status): ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
                <?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="lix-newsletter-search"><?php esc_html_e('Tìm email', 'laca'); ?></label>
                    <input type="search" id="lix-newsletter-search" name="s" value="<?php echo esc_attr($search); ?>">
                    <?php submit_button(__('Tìm email', 'laca'), '', '', false); ?>
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Email', 'laca'); ?></th>
                        <th scope="col" style="width:120px"><?php esc_html_e('Trạng thái', 'laca'); ?></th>
                        <th scope="col" style="width:80px"><?php esc_html_e('Ngôn ngữ', 'laca'); ?></th>
                        <th scope="col"><?php esc_html_e('Trang đăng ký', 'laca'); ?></th>
                        <th scope="col" style="width:140px"><?php esc_html_e('Đăng ký lúc', 'laca'); ?></th>
                        <th scope="col" style="width:140px"><?php esc_html_e('Cập nhật trạng thái', 'laca'); ?></th>
                        <th scope="col" style="width:60px"><span class="screen-reader-text"><?php esc_html_e('Thao tác', 'laca'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7">
                                <?php echo ($search !== '' || $status !== '')
                                    ? esc_html__('Không tìm thấy email nào khớp.', 'laca')
                                    : esc_html__('Chưa có email nào đăng ký.', 'laca'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $delete_url = wp_nonce_url(
                                admin_url('admin-post.php?action=lix_newsletter_delete&id=' . absint($row['id'])),
                                'lix_newsletter_delete_' . absint($row['id'])
                            );
                            $changed_at = $row['status'] === NewsletterSubscribers::STATUS_UNSUBSCRIBED ? $row['unsubscribed_at'] : $row['confirmed_at'];
                            ?>
                            <tr>
                                <td><a href="mailto:<?php echo esc_attr($row['email']); ?>"><?php echo esc_html($row['email']); ?></a></td>
                                <td><?php echo esc_html($labels[$row['status']] ?? $row['status']); ?></td>
                                <td><?php echo esc_html($row['lang'] ? strtoupper($row['lang']) : '—'); ?></td>
                                <td>
                                    <?php if ($row['source_url']): ?>
                                        <a href="<?php echo esc_url($row['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(wp_make_link_relative($row['source_url'])); ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(mysql2date('H:i d/m/Y', $row['created_at'])); ?></td>
                                <td><?php echo esc_html($changed_at ? mysql2date('H:i d/m/Y', $changed_at) : '—'); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="submitdelete"
                                        onclick="return confirm('<?php echo esc_js(__('Xoá email này khỏi danh sách?', 'laca')); ?>');"><?php esc_html_e('Xoá', 'laca'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                        ]));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderSyncStatus(): void
    {
        // Email xác nhận luôn có (gửi qua SMTP của site); dịch vụ ngoài thì tuỳ cấu hình.
        $services = ['mail' => __('Email xác nhận (SMTP)', 'laca')];
        if (NewsletterSync::sheetUrl() !== '') {
            $services['sheet'] = 'Google Sheet';
        }
        if (NewsletterSync::provider() === 'mailchimp') {
            $services['mailchimp'] = 'Mailchimp';
        } elseif (NewsletterSync::provider() === 'brevo') {
            $services['brevo'] = 'Brevo';
        }

        $statuses = NewsletterSync::statuses();
        echo '<ul>';
        foreach ($services as $key => $label) {
            $status = $statuses[$key] ?? null;
            if (!$status) {
                $text = __('chưa có lần gửi/đồng bộ nào.', 'laca');
                $color = '#646970';
            } elseif ($status['ok']) {
                /* translators: %s: thời gian */
                $text = sprintf(__('lần gần nhất thành công lúc %s.', 'laca'), mysql2date('H:i d/m/Y', $status['time']));
                $color = '#00a32a';
            } else {
                /* translators: 1: thời gian, 2: thông báo lỗi */
                $text = sprintf(__('lỗi lúc %1$s — %2$s', 'laca'), mysql2date('H:i d/m/Y', $status['time']), $status['message']);
                $color = '#d63638';
            }

            printf(
                '<li><strong>%s:</strong> <span style="color:%s">%s</span></li>',
                esc_html($label),
                esc_attr($color),
                esc_html($text)
            );
        }
        echo '</ul>';
    }

    public function exportCsv(): void
    {
        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'), 403);
        }
        check_admin_referer('lix_newsletter_export');

        // Xuất theo đúng tab đang xem — vd chỉ "Đã đăng ký" để import vào Mailchimp/Brevo.
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletter-' . ($status ? $status . '-' : '') . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        // BOM để Excel nhận đúng UTF-8.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Email', 'Status', 'Language', 'Source', 'Subscribed at', 'Confirmed at', 'Unsubscribed at']);

        foreach (NewsletterSubscribers::query('', 0, 0, $status) as $row) {
            fputcsv($out, array_map([$this, 'csvSafe'], [
                $row['email'],
                $row['status'],
                $row['lang'],
                $row['source_url'],
                $row['created_at'],
                $row['confirmed_at'],
                $row['unsubscribed_at'],
            ]));
        }

        fclose($out);
        exit;
    }

    public function deleteSubscriber(): void
    {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'), 403);
        }
        check_admin_referer('lix_newsletter_delete_' . $id);

        if ($id) {
            NewsletterSubscribers::delete($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&deleted=1'));
        exit;
    }

    /**
     * Chặn CSV formula injection: ô bắt đầu bằng = + - @ bị Excel/Sheets
     * hiểu là công thức (email hợp lệ vẫn có thể bắt đầu bằng các ký tự này).
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}

if (is_admin()) {
    new NewsletterAdminPage();
}
