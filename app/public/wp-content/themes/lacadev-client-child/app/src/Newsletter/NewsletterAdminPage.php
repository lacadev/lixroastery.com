<?php

/**
 * Trang admin "Newsletter": xem danh sách email đăng ký ở footer, tìm kiếm,
 * xoá và xuất CSV (mở được bằng Excel / Google Sheets).
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

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $total = NewsletterSubscribers::count($search);
        $rows = NewsletterSubscribers::query($search, self::PER_PAGE, ($paged - 1) * self::PER_PAGE);
        $total_pages = (int) ceil($total / self::PER_PAGE);

        $export_url = wp_nonce_url(admin_url('admin-post.php?action=lix_newsletter_export'), 'lix_newsletter_export');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Newsletter — Email đăng ký', 'laca'); ?></h1>
            <a href="<?php echo esc_url($export_url); ?>" class="page-title-action"><?php esc_html_e('Xuất CSV', 'laca'); ?></a>
            <hr class="wp-header-end">

            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Đã xoá email.', 'laca'); ?></p></div>
            <?php endif; ?>

            <p>
                <?php
                printf(
                    /* translators: %s: số email */
                    esc_html__('Tổng cộng: %s email.', 'laca'),
                    '<strong>' . esc_html(number_format_i18n($total)) . '</strong>'
                );
                ?>
            </p>

            <?php $this->renderSyncStatus(); ?>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
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
                        <th scope="col" style="width:90px"><?php esc_html_e('Ngôn ngữ', 'laca'); ?></th>
                        <th scope="col"><?php esc_html_e('Trang đăng ký', 'laca'); ?></th>
                        <th scope="col" style="width:160px"><?php esc_html_e('Thời gian', 'laca'); ?></th>
                        <th scope="col" style="width:80px"><span class="screen-reader-text"><?php esc_html_e('Thao tác', 'laca'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5">
                                <?php echo $search !== ''
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
                            ?>
                            <tr>
                                <td><a href="mailto:<?php echo esc_attr($row['email']); ?>"><?php echo esc_html($row['email']); ?></a></td>
                                <td><?php echo esc_html($row['lang'] ? strtoupper($row['lang']) : '—'); ?></td>
                                <td>
                                    <?php if ($row['source_url']): ?>
                                        <a href="<?php echo esc_url($row['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(wp_make_link_relative($row['source_url'])); ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(mysql2date('H:i d/m/Y', $row['created_at'])); ?></td>
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
        $services = [];
        if (NewsletterSync::sheetUrl() !== '') {
            $services['sheet'] = 'Google Sheet';
        }
        if (NewsletterSync::provider() === 'mailchimp') {
            $services['mailchimp'] = 'Mailchimp';
        } elseif (NewsletterSync::provider() === 'brevo') {
            $services['brevo'] = 'Brevo';
        }

        if (empty($services)) {
            echo '<p>' . esc_html__('Chưa kết nối dịch vụ ngoài nào (Google Sheet / Mailchimp / Brevo) — cấu hình ở Laca Theme → Newsletter.', 'laca') . '</p>';
            return;
        }

        $statuses = NewsletterSync::statuses();
        echo '<ul>';
        foreach ($services as $key => $label) {
            $status = $statuses[$key] ?? null;
            if (!$status) {
                $text = __('đã kết nối, chưa có lần đồng bộ nào.', 'laca');
                $color = '#646970';
            } elseif ($status['ok']) {
                /* translators: %s: thời gian */
                $text = sprintf(__('đồng bộ thành công lúc %s.', 'laca'), mysql2date('H:i d/m/Y', $status['time']));
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

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletter-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        // BOM để Excel nhận đúng UTF-8.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Email', 'Language', 'Source', 'Subscribed at']);

        foreach (NewsletterSubscribers::query('', 0) as $row) {
            fputcsv($out, array_map([$this, 'csvSafe'], [$row['email'], $row['lang'], $row['source_url'], $row['created_at']]));
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
    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}

if (is_admin()) {
    new NewsletterAdminPage();
}
