<?php

namespace App\Features\LacaSeo\Compat;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Handles Polylang multi-language compatibility for translatable SEO fields.
 */
class Polylang
{
    public function register(): void
    {
        if (!function_exists('pll_register_string')) {
            return;
        }

        add_action('init', [$this, 'registerStrings'], 30);
    }

    /**
     * Registers all translatable registry fields with Polylang String Translation.
     */
    public function registerStrings(): void
    {
        $registryMap = Settings::getRegistryMap();

        foreach ($registryMap as $group => $fields) {
            foreach ($fields as $field) {
                if ($field->isTranslatable()) {
                    $id = $field->getId();
                    $val = (string) Settings::get("{$group}.{$id}", $field->getDefault());
                    $multiline = ($field->getType() === 'textarea' || $field->getType() === 'variable_textarea');

                    \pll_register_string(
                        'laca_seo: ' . $id,
                        $val,
                        'Laca SEO',
                        $multiline
                    );
                }
            }
        }
    }

    /**
     * Helper to translate a string value in current language.
     */
    public static function translate(string $string): string
    {
        if (function_exists('pll__')) {
            return \pll__($string);
        }
        return $string;
    }
}
