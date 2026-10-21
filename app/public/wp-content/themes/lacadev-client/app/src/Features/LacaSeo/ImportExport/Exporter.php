<?php

namespace App\Features\LacaSeo\ImportExport;

use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Exports Laca SEO settings to a structured JSON file.
 */
class Exporter
{
    /**
     * Generate export payload array.
     *
     * @param string[] $sections
     * @return array<string, mixed>
     */
    public static function generateExport(array $sections): array
    {
        $theme = wp_get_theme();
        $payload = [
            'plugin'      => 'laca-seo',
            'version'     => $theme->get('Version') ?: '1.0.0',
            'exported_at' => current_time('mysql'),
            'site_url'    => get_home_url(),
            'data'        => [],
        ];

        if (in_array('general', $sections, true)) {
            $payload['data']['general'] = Settings::getGroup('general');
        }

        if (in_array('titles', $sections, true)) {
            $payload['data']['titles'] = Settings::getGroup('titles');
        }

        if (in_array('sitemap', $sections, true)) {
            $payload['data']['sitemap'] = get_option('laca_seo_sitemap', []);
        }

        if (in_array('instant_indexing', $sections, true)) {
            $payload['data']['instant_indexing'] = get_option('laca_seo_instant_indexing', []);
        }

        if (in_array('modules', $sections, true)) {
            $payload['data']['modules'] = Modules::getActive();
        }

        return $payload;
    }

    /**
     * Download export payload as JSON file attachment.
     */
    public static function downloadJson(array $sections): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $data = self::generateExport($sections);
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $domain = sanitize_title(wp_parse_url(get_home_url(), PHP_URL_HOST) ?: 'site');
        $filename = sprintf('laca-seo-%s-%s.json', $domain, wp_date('Ymd-His'));

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen((string) $json));

        echo $json;
        exit;
    }
}
