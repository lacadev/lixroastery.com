<?php

namespace App\Features\DiscordHub\Forms;

use App\Features\DiscordHub\I18n;

/**
 * Contact Form 7.
 *
 * Dùng `wpcf7_submit` thay vì `wpcf7_mail_sent` như spec: mail_sent chỉ bắn khi gửi mail
 * THÀNH CÔNG — SMTP lỗi (rất phổ biến trên shared hosting) thì lead biến mất ở cả
 * email lẫn Discord. wpcf7_submit cho cả mail_sent và mail_failed.
 */
final class ContactForm7Adapter implements FormAdapter
{
    public function register(callable $emit): void
    {
        add_action(
            'wpcf7_submit',
            static function ($form, $result) use ($emit) {
                $status = is_array($result) && isset($result['status']) ? $result['status'] : '';
                if (!in_array($status, ['mail_sent', 'mail_failed'], true) || !class_exists('\WPCF7_Submission')) {
                    return;
                }
                $submission = \WPCF7_Submission::get_instance();
                if (!$submission) {
                    return;
                }

                $types = [];
                if (is_object($form) && method_exists($form, 'scan_form_tags')) {
                    foreach ((array) $form->scan_form_tags() as $tag) {
                        if (!empty($tag->name)) {
                            $types[$tag->name] = (string) $tag->basetype;
                        }
                    }
                }

                $lead = new Lead('Contact Form 7', is_object($form) && method_exists($form, 'title') ? (string) $form->title() : 'CF7');
                foreach ((array) $submission->get_posted_data() as $key => $value) {
                    $lead->addField((string) $key, (string) $key, $types[$key] ?? 'text', $value);
                }
                $lead->pageUrl = (string) $submission->get_meta('url');
                if ('mail_failed' === $status) {
                    $lead->warning = I18n::block('Email thông báo GỬI THẤT BẠI — Discord có thể là nơi duy nhất nhận được lead này. Kiểm tra SMTP.', 'Notification email FAILED — Discord may be the only place this lead arrived. Check SMTP.');
                }

                $emit($lead);
            },
            10,
            2
        );
    }
}
