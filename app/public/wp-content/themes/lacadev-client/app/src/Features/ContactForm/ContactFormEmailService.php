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
    public static function sendAll(array $form, array $data, string $ip, string $lang = ''): bool
    {
        $systemVars = [
            'ip'   => $ip,
            'date' => date_i18n(get_option('date_format')),
            'time' => date_i18n(get_option('time_format')),
        ];

        $vars = array_merge($systemVars, $data);

        $adminSent = self::sendAdminEmail($form, $vars);
        self::sendCustomerEmail($form, $vars, $lang);

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

        $styleSettings = is_array($form['style_settings'] ?? null)
            ? $form['style_settings']
            : (json_decode($form['style_settings'] ?? '{}', true) ?: []);

        $bodyTemplate = $form['email_admin_body'] ?? '';
        $mode = $styleSettings['email_admin_mode'] ?? (self::isHtmlDocument($bodyTemplate) ? 'html' : 'template');

        $subject = self::interpolate($form['email_admin_subject'] ?? '', $vars, false, $form);
        $body    = self::interpolate($bodyTemplate, $vars, true, $form);

        // Admin cố ý để trống subject/body (tắt tính năng) — không phải lỗi.
        if (!$subject || !$body) {
            return true;
        }

        // Tự động bọc vào layout chuẩn nếu ở chế độ Mẫu chuẩn hoặc không phải HTML thô đầy đủ
        if ($mode === 'template' || !self::isHtmlDocument($body)) {
            if (strip_tags($body, '<table><tr><td><th><tbody><thead><p><b><strong><i><em><a><ul><ol><li><h1><h2><h3><h4><br>') === $body) {
                $body = nl2br($body);
            }
            $body = self::wrapInEmailTemplate($body, $subject, $styleSettings);
        } elseif (strip_tags($body) === $body) {
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

    private static function sendCustomerEmail(array $form, array $vars, string $lang = ''): bool
    {
        $styleSettings = is_array($form['style_settings'] ?? null)
            ? $form['style_settings']
            : (json_decode($form['style_settings'] ?? '{}', true) ?: []);

        $subjectTemplate = $form['email_customer_subject'] ?? '';
        $bodyTemplate    = $form['email_customer_body'] ?? '';

        // Ưu tiên bản dịch email khách hàng theo ngôn ngữ hiện tại của form
        if ($lang !== '' && !empty($styleSettings['email_customer_i18n'][$lang])) {
            $i18n = $styleSettings['email_customer_i18n'][$lang];
            if (!empty($i18n['subject'])) {
                $subjectTemplate = $i18n['subject'];
            }
            if (!empty($i18n['body'])) {
                $bodyTemplate = $i18n['body'];
            }
        }

        // Không gửi nếu subject rỗng (admin disable)
        if (empty($subjectTemplate)) {
            return true;
        }

        // Tìm email khách trong data (field name = email hoặc chứa 'email')
        $customerEmail = self::findEmailValue($vars);
        if (!$customerEmail || !is_email($customerEmail)) {
            return true;
        }

        $mode = $styleSettings['email_customer_mode'] ?? (self::isHtmlDocument($bodyTemplate) ? 'html' : 'template');

        $subject = self::interpolate($subjectTemplate, $vars, false, $form);
        $body    = self::interpolate($bodyTemplate, $vars, true, $form);

        if (!$subject || !$body) {
            return true;
        }

        // Tự động bọc vào layout chuẩn nếu ở chế độ Mẫu chuẩn hoặc không phải HTML thô đầy đủ
        if ($mode === 'template' || !self::isHtmlDocument($body)) {
            if (strip_tags($body, '<table><tr><td><th><tbody><thead><p><b><strong><i><em><a><ul><ol><li><h1><h2><h3><h4><br>') === $body) {
                $body = nl2br($body);
            }
            $body = self::wrapInEmailTemplate($body, $subject, $styleSettings);
        } elseif (strip_tags($body) === $body) {
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
     * Kiểm tra xem chuỗi có phải tài liệu HTML hoàn chỉnh hay không
     */
    public static function isHtmlDocument(string $text): bool
    {
        $lower = strtolower($text);
        return str_contains($lower, '<!doctype') || str_contains($lower, '<html') || str_contains($lower, '<body');
    }

    /**
     * Tự động bọc nội dung vào khung email chuẩn đẹp
     */
    public static function wrapInEmailTemplate(string $content, string $title, array $styleSettings = []): string
    {
        $siteName     = get_bloginfo('name');
        $primaryColor = !empty($styleSettings['primary_color']) ? $styleSettings['primary_color'] : '#2271b1';

        return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
</head>
<body style="margin:0;padding:28px 12px;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;line-height:1.6">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td align="center">
        <div style="max-width:580px;width:100%;margin:0 auto;background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;overflow:hidden;text-align:left;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
          <div style="background:' . esc_attr($primaryColor) . ';padding:22px 28px;color:#ffffff">
            <h2 style="margin:0;font-size:18px;font-weight:700;letter-spacing:-0.2px;color:#ffffff">' . esc_html($title) . '</h2>
            <p style="margin:4px 0 0;font-size:13px;opacity:0.9;color:#ffffff">' . esc_html($siteName) . '</p>
          </div>
          <div style="padding:28px;font-size:14px;color:#334155;line-height:1.7">
            ' . $content . '
          </div>
          <div style="padding:14px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8">
            <span>' . esc_html($siteName) . ' &bull; Email tự động</span>
          </div>
        </div>
      </td>
    </tr>
  </table>
</body>
</html>';
    }

    /**
     * Tạo bảng HTML hiển thị toàn bộ các trường trong form ($all_fields)
     */
    public static function buildAllFieldsTable(array $form, array $vars, bool $isHtml = true): string
    {
        $rawFields = json_decode($form['fields'] ?? '[]', true) ?: [];
        $flatFields = [];

        // Hỗ trợ cả row-based lẫn flat structure
        if (!empty($rawFields) && isset($rawFields[0]['cols'])) {
            foreach ($rawFields as $row) {
                foreach ($row['cols'] ?? [] as $col) {
                    foreach ($col['fields'] ?? [] as $f) {
                        if (($f['type'] ?? '') !== 'content' && !empty($f['name'])) {
                            $flatFields[] = $f;
                        }
                    }
                }
            }
        } else {
            foreach ($rawFields as $f) {
                if (($f['type'] ?? '') !== 'content' && !empty($f['name'])) {
                    $flatFields[] = $f;
                }
            }
        }

        if (empty($flatFields)) {
            return '';
        }

        if (!$isHtml) {
            $lines = [];
            foreach ($flatFields as $f) {
                $name = $f['name'];
                $label = !empty($f['label']) ? $f['label'] : $name;
                $val = $vars[$name] ?? '';
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $lines[] = "- {$label}: {$val}";
            }
            return implode("\n", $lines);
        }

        $rowsHtml = '';
        foreach ($flatFields as $f) {
            $name = $f['name'];
            $label = !empty($f['label']) ? $f['label'] : $name;
            $val = $vars[$name] ?? '';

            if (is_array($val)) {
                $valHtml = implode(', ', array_map(fn($v) => esc_html((string)$v), $val));
            } elseif ($f['type'] === 'textarea') {
                $valHtml = nl2br(esc_html((string)$val));
            } else {
                $valHtml = esc_html((string)$val);
            }

            if ($valHtml === '') {
                $valHtml = '<span style="color:#94a3b8;font-style:italic">—</span>';
            }

            $rowsHtml .= '<tr>
              <td style="padding:10px 14px;background:#f8fafc;color:#475569;font-weight:600;width:38%;border-bottom:1px solid #e2e8f0;font-size:13px;vertical-align:top">' . esc_html($label) . '</td>
              <td style="padding:10px 14px;color:#1e293b;border-bottom:1px solid #e2e8f0;font-size:13px;vertical-align:top">' . $valHtml . '</td>
            </tr>';
        }

        return '<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:18px 0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden">
          <tbody>' . $rowsHtml . '</tbody>
        </table>';
    }

    /**
     * Thay thế $ten_bien trong template bằng giá trị thực tế
     *
     * @param string     $template Nội dung với $variable placeholders
     * @param array      $vars     Array ['variable_name' => 'value']
     * @param bool       $isHtml   Xử lý định dạng cho HTML email hay plain text (subject)
     * @param array|null $form     Form row để sinh bảng $all_fields
     * @return string
     */
    private static function interpolate(string $template, array $vars, bool $isHtml = true, ?array $form = null): string
    {
        // Xử lý biến thông minh $all_fields
        if (str_contains($template, '$all_fields') && $form !== null) {
            $allFieldsHtml = self::buildAllFieldsTable($form, $vars, $isHtml);
            $template = str_replace('$all_fields', $allFieldsHtml, $template);
        }

        // Sắp xếp theo ĐỘ DÀI TÊN BIẾN giảm dần trước khi thay thế
        uksort($vars, static fn($a, $b) => strlen((string) $b) <=> strlen((string) $a));

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
