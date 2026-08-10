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
     * Gửi toàn bộ emails sau submission
     *
     * @param array $form      Row từ ContactFormTable::getForm()
     * @param array $data      Associative array {field_name => value}
     * @param string $ip       IP address người gửi
     */
    public static function sendAll(array $form, array $data, string $ip): void
    {
        $systemVars = [
            'ip'   => $ip,
            'date' => date_i18n(get_option('date_format')),
            'time' => date_i18n(get_option('time_format')),
        ];

        $vars = array_merge($systemVars, $data);

        self::sendAdminEmail($form, $vars);
        self::sendCustomerEmail($form, $vars);
    }

    // -------------------------------------------------------------------------
    // Email tới Admin
    // -------------------------------------------------------------------------

    private static function sendAdminEmail(array $form, array $vars): void
    {
        $toEmail = !empty($form['notify_email'])
            ? $form['notify_email']
            : get_option('admin_email');

        $subject = self::interpolate($form['email_admin_subject'], $vars);
        $body    = self::interpolate($form['email_admin_body'], $vars);

        if (!$subject || !$body) {
            return;
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        wp_mail(
            sanitize_email($toEmail),
            wp_specialchars_decode($subject, ENT_QUOTES),
            $body,
            $headers
        );
    }

    // -------------------------------------------------------------------------
    // Email tới Khách hàng
    // -------------------------------------------------------------------------

    private static function sendCustomerEmail(array $form, array $vars): void
    {
        // Không gửi nếu subject rỗng (admin disable)
        if (empty($form['email_customer_subject'])) {
            return;
        }

        // Tìm email khách trong data (field name = email hoặc chứa 'email')
        $customerEmail = self::findEmailValue($vars);
        if (!$customerEmail || !is_email($customerEmail)) {
            return;
        }

        $subject = self::interpolate($form['email_customer_subject'], $vars);
        $body    = self::interpolate($form['email_customer_body'], $vars);

        if (!$subject || !$body) {
            return;
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        wp_mail(
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
     * @param string $template  Nội dung với $variable placeholders
     * @param array  $vars      Array ['variable_name' => 'value']
     * @return string
     */
    private static function interpolate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $key   = preg_replace('/[^a-z0-9_]/i', '', (string) $key);
            $value = is_array($value) ? implode(', ', $value) : (string) $value;
            $template = str_replace('$' . $key, $value, $template);
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
