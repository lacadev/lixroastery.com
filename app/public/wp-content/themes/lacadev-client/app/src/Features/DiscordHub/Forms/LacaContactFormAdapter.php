<?php

namespace App\Features\DiscordHub\Forms;

/**
 * Form liên hệ có sẵn của theme (Laca Admin → Form Liên Hệ, shortcode [laca_contact_form]).
 * Hook `laca_contact_form_submitted` bắn trong ContactFormAjaxHandler::handleSubmit()
 * SAU khi submission đã lưu DB — kể cả khi gửi email thất bại.
 */
final class LacaContactFormAdapter implements FormAdapter
{
    public function register(callable $emit): void
    {
        add_action(
            'laca_contact_form_submitted',
            static function ($form, $fields, $data, $submissionId, $emailSent) use ($emit) {
                $name = is_array($form) ? (string) ($form['name'] ?? '') : '';
                $lead = new Lead('Form Laca', '' !== $name ? $name : 'Form liên hệ');

                foreach ((array) $fields as $field) {
                    $key = (string) ($field['name'] ?? '');
                    if ('' === $key || !array_key_exists($key, (array) $data)) {
                        continue;
                    }
                    $lead->addField($key, (string) ($field['label'] ?? $key), (string) ($field['type'] ?? 'text'), $data[$key]);
                }

                $lead->entryId = $submissionId ? (string) (int) $submissionId : '';
                $lead->pageUrl = (string) wp_get_referer();
                if (!$emailSent) {
                    $lead->warning = 'Email thông báo GỬI THẤT BẠI — kiểm tra SMTP. Submission vẫn đã lưu trong Laca Admin → Form Liên Hệ.';
                }

                $emit($lead);
            },
            10,
            5
        );
    }
}
