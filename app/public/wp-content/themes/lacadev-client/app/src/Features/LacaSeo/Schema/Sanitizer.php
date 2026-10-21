<?php

namespace App\Features\LacaSeo\Schema;

defined('ABSPATH') || exit;

class Sanitizer
{
    /**
     * Sanitize schema data array preserving types and nesting.
     * Matches Rank Math's sanitization rules.
     *
     * @param array $schema
     * @param string $type
     * @return array
     */
    public static function sanitizeSchema(array $schema, string $type = ''): array
    {
        $sanitized = [];
        $currentType = $schema['@type'] ?? $type;

        foreach ($schema as $key => $value) {
            $cleanKey = sanitize_text_field($key);

            if (is_array($value)) {
                $sanitized[$cleanKey] = self::sanitizeSchema($value, (string) $currentType);
            } elseif (is_bool($value)) {
                $sanitized[$cleanKey] = (bool) $value;
            } elseif (is_int($value)) {
                $sanitized[$cleanKey] = (int) $value;
            } elseif (is_float($value)) {
                $sanitized[$cleanKey] = (float) $value;
            } elseif (is_string($value)) {
                if ($currentType === 'JobPosting' && $cleanKey === 'description') {
                    $sanitized[$cleanKey] = wp_kses($value, [
                        'br' => [],
                        'p'  => [],
                        'ul' => [],
                        'li' => [],
                    ]);
                } elseif (in_array($cleanKey, ['description', 'instructionsSingleField'], true)) {
                    $sanitized[$cleanKey] = sanitize_textarea_field($value);
                } elseif ($cleanKey === 'url' || str_ends_with(strtolower($cleanKey), 'url')) {
                    $sanitized[$cleanKey] = esc_url_raw(trim($value));
                } else {
                    $sanitized[$cleanKey] = sanitize_text_field($value);
                }
            } else {
                $sanitized[$cleanKey] = $value;
            }
        }

        return $sanitized;
    }
}
