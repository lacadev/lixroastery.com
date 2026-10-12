<?php

/**
 * Gửi email xác nhận đăng ký Newsletter (double opt-in) qua wp_mail() — tức
 * qua SMTP đã cấu hình ở Laca Admin → SMTP (ThemeSettings::useGmailSmtp()).
 *
 * Website chỉ gửi email giao dịch (xác nhận). Newsletter hàng loạt nên gửi
 * từ Mailchimp/Brevo: SMTP Gmail giới hạn ~500 thư/ngày và dễ vào spam.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterMailer
{
    /**
     * @return bool true nếu wp_mail() nhận gửi thành công.
     */
    public static function sendConfirmation(array $subscriber): bool
    {
        $lang = (string) $subscriber['lang'];
        $confirm_url = NewsletterEndpoints::url('confirm', $subscriber);
        $unsubscribe_url = NewsletterEndpoints::url('unsubscribe', $subscriber);

        $subject = NewsletterTexts::get('newsletter_mail_subject', $lang);
        $html = self::renderConfirmation(
            NewsletterTexts::get('newsletter_mail_body', $lang),
            NewsletterTexts::get('newsletter_mail_button', $lang),
            $confirm_url,
            $unsubscribe_url,
            NewsletterTexts::get('newsletter_page_unsubscribe_button', $lang),
            $lang
        );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            // Gmail/Outlook/Apple Mail hiện nút "Huỷ đăng ký" ngay cạnh tên
            // người gửi; -Post = huỷ 1 chạm (RFC 8058), xử lý ở
            // NewsletterEndpoints::handleRequest().
            'List-Unsubscribe: <' . esc_url_raw($unsubscribe_url) . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        $sent = wp_mail($subscriber['email'], $subject, $html, $headers);

        // Chỉ ghi mốc khi gửi được — gửi lỗi thì khách thử lại ngay được,
        // không bị chặn bởi giới hạn "5 phút/lần" của AJAX handler.
        if ($sent) {
            NewsletterSubscribers::markConfirmSent((int) $subscriber['id']);
        }
        NewsletterSync::saveStatus('mail', $sent ? true : __('wp_mail() không gửi được email xác nhận — kiểm tra cấu hình SMTP ở Laca Admin → SMTP.', 'laca'));

        return $sent;
    }

    /**
     * Email dùng bảng + style inline: nhiều trình đọc mail (Gmail, Outlook)
     * bỏ <style> và không hỗ trợ flex/grid. Màu lấy theo $primaryColor /
     * $darkColor của site (_variables.scss) vì email không đọc được SCSS.
     */
    private static function renderConfirmation(string $body, string $button, string $confirm_url, string $unsubscribe_url, string $unsubscribe_label, string $lang): string
    {
        $site_name = get_bloginfo('name');
        $paragraphs = array_filter(array_map('trim', explode("\n", $body)));

        ob_start();
        ?>
<!doctype html>
<html lang="<?php echo esc_attr(NewsletterTexts::lang($lang)); ?>">
<body style="margin:0;padding:0;background:#f9f9f9;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9f9f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-top:3px solid #3d5a45;">
                    <tr>
                        <td style="padding:32px 32px 8px;font-family:Arial,Helvetica,sans-serif;font-size:20px;letter-spacing:0.04em;text-transform:uppercase;color:#121212;">
                            <?php echo esc_html($site_name); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#121212;">
                            <?php foreach ($paragraphs as $paragraph): ?>
                                <p style="margin:0 0 16px;"><?php echo esc_html($paragraph); ?></p>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px;">
                            <a href="<?php echo esc_url($confirm_url); ?>" style="display:inline-block;padding:12px 24px;background:#3d5a45;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;text-decoration:none;">
                                <?php echo esc_html($button); ?>
                            </a>
                        </td>
                    </tr>
                </table>
                <p style="max-width:560px;margin:16px auto 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b6b6b;">
                    <?php echo esc_html($site_name); ?> ·
                    <a href="<?php echo esc_url($unsubscribe_url); ?>" style="color:#6b6b6b;"><?php echo esc_html($unsubscribe_label); ?></a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
