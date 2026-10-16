<?php

namespace App\Features\DiscordHub\Forms;

/**
 * Elementor Pro Forms — `elementor_pro/forms/new_record($record, $ajax_handler)`.
 */
final class ElementorFormsAdapter implements FormAdapter
{
    public function register(callable $emit): void

    {
        add_action(
            'elementor_pro/forms/new_record',
            static function ($record, $handler) use ($emit) {
                if (!is_object($record) || !method_exists($record, 'get')) {
                    return;
                }

                $name = method_exists($record, 'get_form_settings') ? (string) $record->get_form_settings('form_name') : '';
                $lead = new Lead('Elementor Pro', '' !== $name ? $name : 'Elementor Form');

                foreach ((array) $record->get('fields') as $id => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $lead->addField(
                        (string) ($field['id'] ?? $id),
                        (string) ($field['title'] ?? $id),
                        (string) ($field['type'] ?? 'text'),
                        $field['raw_value'] ?? ($field['value'] ?? '')
                    );
                }

                $meta          = (array) $record->get('meta');
                $lead->pageUrl = (string) ($meta['page_url']['value'] ?? '');

                $emit($lead);
            },
            10,
            2
        );
    }
}
