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
        if (function_exists('pll_register_string')) {
            add_action('init', [$this, 'registerStrings'], 30);
        }

        add_filter('laca_seo/sitemap/xml_post_url', [$this, 'fixFrontPageSitemapUrl'], 10, 2);
    }

    /**
     * Point the front page's sitemap entry at its language home URL, not its slug.
     */
    public function fixFrontPageSitemapUrl(string $url, $post): string
    {
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post') || !function_exists('PLL')) {
            return $url;
        }

        $page_on_front_id = (int) get_option('page_on_front');
        if (!$page_on_front_id || !isset($post->ID)) {
            return $url;
        }

        $lang = pll_get_post_language($post->ID);
        if (!$lang) {
            return $url;
        }

        if ((int) $post->ID === (int) pll_get_post($page_on_front_id, $lang)) {
            $pll = \PLL();
            if (isset($pll->links_model) && isset($pll->model)) {
                $langObj = $pll->model->get_language($lang);
                if ($langObj) {
                    return $pll->links_model->switch_language_in_link(trailingslashit(get_option('home')), $langObj);
                }
            }
        }

        return $url;
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

    /**
     * Collect Facebook locale codes for every language with a translation of the current page.
     * Ported from Rank Math Polylang integration: get_og_alternate_languages().
     *
     * @return string[]
     */
    public static function getAlternateLocales(): array
    {
        if (!function_exists('PLL') || !PLL() || !isset(PLL()->model) || !isset(PLL()->links)) {
            return [];
        }

        $alternates = [];

        foreach (PLL()->model->get_languages_list() as $language) {
            if (
                isset(PLL()->curlang) &&
                PLL()->curlang->slug !== $language->slug &&
                PLL()->links->get_translation_url($language) &&
                isset($language->facebook)
            ) {
                $alternates[] = $language->facebook;
            }
        }

        return array_unique($alternates);
    }
}

