<?php

namespace App\Features\LacaSeo\Sitemap;

defined('ABSPATH') || exit;

/**
 * Abstract class XML for Sitemap output and headers.
 */
abstract class AbstractXml
{
    protected ?string $httpProtocol = null;
    protected string $outputCharset = 'UTF-8';

    /**
     * Send file headers.
     *
     * @param array<string, mixed> $headers
     */
    protected function sendHeaders(array $headers = [], bool $isXsl = false): void
    {
        $defaults = [
            'X-Robots-Tag'  => 'noindex',
            'Content-Type'  => 'text/xml; charset=' . $this->getOutputCharset(),
            'Pragma'        => 'public',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Expires'       => '0',
        ];

        $headers = wp_parse_args($headers, $defaults);
        $headers = apply_filters('laca_seo/sitemap/http_headers', $headers, $isXsl);

        header($this->getProtocol() . ' 200 OK', true, 200);
        foreach ($headers as $header => $value) {
            header($header . ': ' . $value);
        }
    }

    protected function getProtocol(): string
    {
        if ($this->httpProtocol !== null) {
            return $this->httpProtocol;
        }

        $this->httpProtocol = !empty($_SERVER['SERVER_PROTOCOL']) ? sanitize_text_field((string) $_SERVER['SERVER_PROTOCOL']) : 'HTTP/1.1';
        return $this->httpProtocol;
    }

    protected function getOutputCharset(): string
    {
        return $this->outputCharset;
    }

    /**
     * Helper to indent new lines in XML output.
     */
    protected function newline(string $content, int $indent = 0): string
    {
        return str_repeat("\t", $indent) . $content . "\n";
    }

    protected function encodeUrlPath(string $path): string
    {
        $parts = explode('/', $path);
        foreach ($parts as $key => $part) {
            $parts[$key] = rawurlencode($part);
        }
        return implode('/', $parts);
    }

    protected function encodeUrlQuery(string $query): string
    {
        return str_replace(['%2F', '%3F'], ['/', '?'], $query);
    }
}
