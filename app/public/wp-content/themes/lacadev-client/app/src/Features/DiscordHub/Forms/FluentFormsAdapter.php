<?php

namespace App\Features\DiscordHub\Forms;

/**
 * Fluent Forms. Bản 5.x bắn cả hook mới `fluentform/submission_inserted` lẫn hook cũ
 * (deprecated) `fluentform_submission_inserted` cho cùng 1 submission → chống trùng theo insertId.
 */
final class FluentFormsAdapter implements FormAdapter
{
    /** @var array<int,bool> */
    private $seen = [];

    public function register(callable $emit): void

    {
        $handler = function ($insertId, $formData, $form) use ($emit) {
            $insertId = (int) $insertId;
            if (isset($this->seen[$insertId])) {
                return;
            }
            $this->seen[$insertId] = true;

            $title = is_object($form) && isset($form->title) ? (string) $form->title : '';
            $lead  = new Lead('Fluent Forms', '' !== $title ? $title : 'Fluent Form');

            foreach ((array) $formData as $key => $value) {
                $key = (string) $key;
                // Bỏ field nội bộ: __fluent_form_embded_post_id, _fluentform_*_fluentformnonce, _wp_http_referer...
                if (0 === strpos($key, '_')) {
                    continue;
                }
                $lead->addField($key, $key, $this->guessType($key), $value);
            }

            $referer = (string) ($formData['_wp_http_referer'] ?? '');
            if ('' !== $referer) {
                $lead->pageUrl = 0 === strpos($referer, 'http') ? $referer : home_url($referer);
            }
            $lead->entryId = (string) $insertId;

            $emit($lead);
        };

        add_action('fluentform/submission_inserted', $handler, 10, 3);
        add_action('fluentform_submission_inserted', $handler, 10, 3);
    }

    private function guessType(string $key): string

    {
        if (false !== strpos($key, 'email')) {
            return 'email';
        }
        if (preg_match('/phone|tel|mobile/i', $key)) {
            return 'tel';
        }
        return 'text';
    }
}
