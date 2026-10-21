<?php

namespace App\Features\LacaSeo\Modules\Redirections;

defined('ABSPATH') || exit;

/**
 * Export redirections to Apache (.htaccess) and Nginx configurations.
 * Logic is 1:1 with Rank Math Export.
 */
class Export
{
    /**
     * Generate Apache .htaccess format.
     */
    public static function apache(array $items): array
    {
        $output = ['<IfModule mod_rewrite.c>'];

        foreach ($items as $item) {
            self::apacheItem($item, $output);
        }

        $output[] = '</IfModule>';
        return $output;
    }

    private static function apacheItem(array $item, array &$output): void
    {
        $headerCodeStr = (string) ($item['header_code'] ?? '301');
        $target = '410' === $headerCodeStr ? '- [G]' : sprintf('%s [R=%d,L]', self::encode2nd($item['url_to'] ?? ''), (int) $headerCodeStr);
        $sources = DB::getSources($item['sources'] ?? []);

        foreach ($sources as $from) {
            $url = $from['pattern'];
            if (('regex' !== $from['comparison'] && strpos($url, '?') !== false) || strpos($url, '&') !== false) {
                $urlParts = wp_parse_url($url);
                $url = $urlParts['path'] ?? '';
                $query = $urlParts['query'] ?? '';
                $output[] = sprintf('RewriteCond %%{QUERY_STRING} ^%s$', preg_quote($query, null));
            }

            $output[] = sprintf('%sRewriteRule %s %s', (self::isValidRegex($from) ? '' : '# '), self::getComparison($url, $from), $target);
        }
    }

    /**
     * Generate Nginx configuration format.
     */
    public static function nginx(array $items): array
    {
        $output = ['server {'];

        foreach ($items as $item) {
            self::nginxItem($item, $output);
        }

        $output[] = '}';
        return $output;
    }

    private static function nginxItem(array $item, array &$output): void
    {
        $target = self::encode2nd($item['url_to']);
        $sources = DB::getSources($item['sources'] ?? []);
        $headerCode = '301' === (string) $item['header_code'] ? 'permanent' : 'redirect';

        foreach ($sources as $from) {
            if (!self::isValidRegex($from)) {
                continue;
            }

            $output[] = self::normalizeNginxRedirect(self::getComparison($from['pattern'], $from), $target, $headerCode);
        }
    }

    private static function isValidRegex(array $source): bool
    {
        if ('regex' === ($source['comparison'] ?? '') && @preg_match('/' . $source['pattern'] . '/', '') === false) {
            return false;
        }

        return true;
    }

    private static function normalizeNginxRedirect(string $source, string $target, string $headerCode): string
    {
        $source = preg_replace("/[\r\n\t].*?$/s", '', $source);
        $source = preg_replace('/[^\PC\s]/u', '', $source);
        $target = preg_replace("/[\r\n\t].*?$/s", '', $target);
        $target = preg_replace('/[^\PC\s]/u', '', $target);

        return "    rewrite {$source} {$target} {$headerCode};";
    }

    public static function getComparison(string $url, array $from): string
    {
        $comparison = $from['comparison'] ?? 'exact';
        if ('regex' === $comparison) {
            return self::encodeRegex($from['pattern']);
        }

        $hash = [
            'exact'    => '^{url}/?$',
            'contains' => '^(.*){url}(.*)$',
            'start'    => '^{url}',
            'end'      => '{url}/?$',
        ];

        $url = preg_quote($url, null);
        return isset($hash[$comparison]) ? str_replace('{url}', $url, $hash[$comparison]) : $url;
    }

    public static function encode2nd(string $url): string
    {
        $url = rawurlencode($url);
        $url = str_replace('%2F', '/', $url);
        $url = str_replace('%3F', '?', $url);
        $url = str_replace('%3A', ':', $url);
        $url = str_replace('%3D', '=', $url);
        $url = str_replace('%26', '&', $url);
        $url = str_replace('%25', '%', $url);
        $url = str_replace('+', '%20', $url);
        $url = str_replace('%24', '$', $url);
        return $url;
    }

    public static function encodeRegex(string $url): string
    {
        $url = preg_replace('/[^a-zA-Z0-9\s](.*)[^a-zA-Z0-9\s][imsxeADSUXJu]*/', '$1', $url);
        $url = preg_replace("/[\r\n\t].*?$/s", '', $url);
        $url = preg_replace('/[^\PC\s]/u', '', $url);
        $url = str_replace(' ', '%20', $url);
        $url = str_replace('%24', '$', $url);
        $url = ltrim($url, '/');
        $url = preg_replace('@^\^/@', '^', $url);

        return $url;
    }
}
