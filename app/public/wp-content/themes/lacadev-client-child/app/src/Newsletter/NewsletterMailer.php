<?php

/**
 * Gửi email cảm ơn khi khách đăng ký Newsletter qua wp_mail() — tức qua SMTP
 * đã cấu hình ở Laca Admin → SMTP (ThemeSettings::useGmailSmtp()). Không cần
 * xác nhận: bấm Subscribe ở form là đã đăng ký, email chỉ để cảm ơn + có
 * link huỷ đăng ký.
 *
 * Website chỉ gửi email giao dịch này. Newsletter hàng loạt nên gửi từ
 * Mailchimp/Brevo: SMTP Gmail giới hạn ~500 thư/ngày và dễ vào spam.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterMailer
{
    /**
     * Gửi SAU khi đã trả phản hồi cho khách (SMTP mất 1–3 giây) — form hiện
     * "cảm ơn" ngay, không phải chờ.
     */
    public static function queueWelcome(array $subscriber): void
    {
        NewsletterSync::afterResponse(static function () use ($subscriber) {
            self::sendWelcome($subscriber);
        });
    }

    /**
     * @return bool true nếu wp_mail() nhận gửi thành công.
     */
    public static function sendWelcome(array $subscriber): bool
    {
        $lang = (string) $subscriber['lang'];
        $unsubscribe_url = NewsletterEndpoints::url('unsubscribe', $subscriber);

        $html = self::renderWelcome(
            NewsletterTexts::get('newsletter_mail_body', $lang),
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

        // Thư chỉ có HTML (thiếu bản chữ thường) bị Gmail/Outlook cộng điểm
        // spam — gắn thêm AltBody để PHPMailer gửi multipart/alternative.
        // Hook chỉ sống trong đúng lần gửi này, không ảnh hưởng email khác.
        $alt_body = self::renderPlainText(NewsletterTexts::get('newsletter_mail_body', $lang), $unsubscribe_url, NewsletterTexts::get('newsletter_page_unsubscribe_button', $lang));
        $set_alt_body = static function ($phpmailer) use ($alt_body) {
            $phpmailer->AltBody = $alt_body;
        };
        add_action('phpmailer_init', $set_alt_body);
        $sent = wp_mail($subscriber['email'], NewsletterTexts::get('newsletter_mail_subject', $lang), $html, $headers);
        remove_action('phpmailer_init', $set_alt_body);

        NewsletterSync::saveStatus('mail', $sent ? true : __('wp_mail() không gửi được email cảm ơn — kiểm tra cấu hình SMTP ở Laca Admin → SMTP.', 'laca'));

        return $sent;
    }

    private static function renderPlainText(string $body, string $unsubscribe_url, string $unsubscribe_label): string
    {
        $paragraphs = array_filter(array_map('trim', explode("\n", $body)));

        return get_bloginfo('name') . "\n\n"
            . implode("\n\n", $paragraphs) . "\n\n"
            . "--\n"
            . $unsubscribe_label . ': ' . $unsubscribe_url . "\n";
    }

    /**
     * Email dùng bảng + style inline: nhiều trình đọc mail (Gmail, Outlook)
     * bỏ <style> và không hỗ trợ flex/grid. Màu lấy theo $primaryColor /
     * $darkColor của site (_variables.scss) vì email không đọc được SCSS.
     */
    private static function renderWelcome(string $body, string $unsubscribe_url, string $unsubscribe_label, string $lang): string
    {
        $site_name = get_bloginfo('name');
        $home_url = ($lang !== '' && function_exists('pll_home_url')) ? pll_home_url($lang) : home_url('/');
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
                            <a href="<?php echo esc_url($home_url); ?>" style="color:#121212;text-decoration:none;"><?php echo esc_html($site_name); ?></a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#121212;">
                            <?php foreach ($paragraphs as $paragraph): ?>
                                <p style="margin:0 0 16px;"><?php echo esc_html($paragraph); ?></p>
                            <?php endforeach; ?>
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
