<?php

namespace App\Features\DiscordHub\Forms;

/**
 * WPForms (Lite & Pro) — `wpforms_process_complete($fields, $entry, $form_data, $entry_id)`.
 */
final class WPFormsAdapter implements FormAdapter
{
    public function register(callable $emit): void

    {
        add_action(
            'wpforms_process_complete',
            static function ($fields, $entry, $formData, $entryId) use ($emit) {
                $title = (string) ($formData['settings']['form_title'] ?? '');
                $lead  = new Lead('WPForms', '' !== $title ? $title : 'WPForms #' . (int) ($formData['id'] ?? 0));

                foreach ((array) $fields as $id => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $lead->addField(
                        'field_' . (string) ($field['id'] ?? $id),
                        (string) ($field['name'] ?? $id),
                        (string) ($field['type'] ?? 'text'),
                        $field['value'] ?? ''
                    );
                }

                $lead->entryId = $entryId ? (string) (int) $entryId : '';
                $lead->pageUrl = (string) wp_get_referer();

                $emit($lead);
            },
            10,
            4
        );
    }
}
