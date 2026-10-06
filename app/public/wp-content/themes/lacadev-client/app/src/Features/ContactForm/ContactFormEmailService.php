<?php

namespace App\Features\ContactForm;

/**
 * ContactFormEmailService
 *
 * Xử lý gửi email sau khi có submission:
 *   1. Email thông báo tới admin / notify_email
 *   2. Email xác nhận tới khách hàng (nếu có field email và subject không rỗng)
 *
 * Variable syntax trong template: $ten_bien (khớp với field name hoặc biến hệ thống)
 * Biến hệ thống: $ip, $date, $time
 */
class ContactFormEmailService
{
    /**
     * Gửi toàn bộ emails sau submission.
     *
     * @param array  $form Row từ ContactFormTable::getForm()
     * @param array  $data Associative array {field_name => value}
     * @param string $ip   IP address người gửi
     * @return bool        true nếu email báo ADMIN gửi thành công (hoặc admin
     *                      cố ý tắt tính năng này) — đây là email QUAN TRỌNG
     *                      nhất (để admin biết có submission mới), nên dùng
     *                      làm kết quả chung trả lên popup cho người gửi.
     *                      Email xác nhận cho KHÁCH chỉ là phụ (nice-to-have)
     *                      — lỗi riêng email đó không nên báo "thất bại" cho
     *                      người gửi trong khi tin nhắn của họ đã tới nơi.
     */
    public static function sendAll(array $form, array $data, string $ip): bool
    {
        $systemVars = [
            'ip'   => $ip,
            'date' => date_i18n(get_option('date_format')),
            'time' => date_i18n(get_option('time_format')),
        ];

        $vars = array_merge($systemVars, $data);

        $adminSent = self::sendAdminEmail($form, $vars);
        self::sendCustomerEmail($form, $vars);

        return $adminSent;
    }

    // -------------------------------------------------------------------------
    // Email tới Admin
    // -------------------------------------------------------------------------

    private static function sendAdminEmail(array $form, array $vars): bool
    {
        $toEmail = !empty($form['notify_email'])
            ? $form['notify_email']
            : get_option('admin_email');

        $subject = self::interpolate($form['email_admin_subject'] ?? '', $vars, false);
        $body    = self::interpolate($form['email_admin_body'] ?? '', $vars, true);

        // Admin cố ý để trống subject/body (tắt tính năng) — không phải lỗi.
        if (!$subject || !$body) {
            return true;
        }

        if (strip_tags($body) === $body) {
            $body = nl2br($body);
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        // Gắn Reply-To về email của khách nếu có
        $customerEmail = self::findEmailValue($vars);
        if ($customerEmail && is_email($customerEmail)) {
            $customerName = !empty($vars['name']) ? sanitize_text_field((string) $vars['name']) : '';
            if ($customerName) {
                $headers[] = 'Reply-To: ' . $customerName . ' <' . $customerEmail . '>';
            } else {
                $headers[] = 'Reply-To: ' . $customerEmail;
            }
        }

        return wp_mail(
            sanitize_email($toEmail),
            wp_specialchars_decode($subject, ENT_QUOTES),
            $body,
            $headers
        );
    }

    // -------------------------------------------------------------------------
    // Email tới Khách hàng
    // -------------------------------------------------------------------------

    private static function sendCustomerEmail(array $form, array $vars): bool
    {
        // Không gửi nếu subject rỗng (admin disable)
        if (empty($form['email_customer_subject'])) {
            return true;
        }

        // Tìm email khách trong data (field name = email hoặc chứa 'email')
        $customerEmail = self::findEmailValue($vars);
        if (!$customerEmail || !is_email($customerEmail)) {
            return true;
        }

        $subject = self::interpolate($form['email_customer_subject'] ?? '', $vars, false);
        $body    = self::interpolate($form['email_customer_body'] ?? '', $vars, true);

        if (!$subject || !$body) {
            return true;
        }

        if (strip_tags($body) === $body) {
            $body = nl2br($body);
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . get_option('admin_email'),
        ];

        return wp_mail(
            sanitize_email($customerEmail),
            wp_specialchars_decode($subject, ENT_QUOTES),
            $body,
            $headers
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Thay thế $ten_bien trong template bằng giá trị thực tế
     *
     * @param string $template Nội dung với $variable placeholders
     * @param array  $vars     Array ['variable_name' => 'value']
     * @param bool   $isHtml   Xử lý định dạng cho HTML email hay plain text (subject)
     * @return string
     */
    private static function interpolate(string $template, array $vars, bool $isHtml = true): string
    {
        foreach ($vars as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/i', '', (string) $key);
            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                $formattedValue = $isHtml
                    ? implode(', ', array_map(fn($v) => esc_html((string) $v), $value))
                    : implode(', ', array_map('strval', $value));
            } else {
                $formattedValue = (string) $value;
                if ($isHtml) {
                    if (!in_array($key, ['ip', 'date', 'time'], true)) {
                        $formattedValue = nl2br(esc_html($formattedValue));
                    } else {
                        $formattedValue = esc_html($formattedValue);
                    }
                }
            }

            $template = str_replace('$' . $key, $formattedValue, $template);
        }

        return $template;
    }

    /**
     * Tìm giá trị email trong submission data.
     * Ưu tiên: key chính xác là 'email', sau đó key chứa chữ 'email'.
     */
    private static function findEmailValue(array $vars): string
    {
        // Ưu tiên key chính xác
        if (!empty($vars['email']) && is_email($vars['email'])) {
            return $vars['email'];
        }

        // Tìm key chứa 'email'
        foreach ($vars as $key => $value) {
            if (str_contains(strtolower($key), 'email') && is_string($value) && is_email($value)) {
                return $value;
            }
        }

        return '';
    }
}
