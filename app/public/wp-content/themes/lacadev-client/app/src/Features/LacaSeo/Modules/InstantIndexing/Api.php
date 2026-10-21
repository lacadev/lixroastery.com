<?php

namespace App\Features\LacaSeo\Modules\InstantIndexing;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Client for IndexNow API (Bing, Yandex, etc.).
 */
class Api
{
    private string $apiUrl = 'https://api.indexnow.org/indexnow/';
    private string $apiKey = '';
    private int $lastCode = 0;
    private string $lastError = '';
    private string $lastResponse = '';
    public bool $isManual = true;

    public static function get(): self
    {
        static $instance;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    public function getKey(): string
    {
        if (!empty($this->apiKey)) {
            return $this->apiKey;
        }

        $key = (string) Settings::get('instant_indexing.indexnow_api_key', '');
        if (empty($key)) {
            $key = $this->resetKey();
        }

        $this->apiKey = apply_filters('laca_seo/instant_indexing/indexnow_key', $key);
        return $this->apiKey;
    }

    public function resetKey(): string
    {
        $new_key = str_replace('-', '', wp_generate_uuid4());
        $opts = Settings::getGroup('instant_indexing');
        $opts['indexnow_api_key'] = $new_key;
        Settings::updateGroup('instant_indexing', $opts);
        $this->apiKey = $new_key;
        return $new_key;
    }

    public function getHost(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($host)) {
            $host = 'localhost';
        }
        return apply_filters('laca_seo/instant_indexing/indexnow_host', (string) $host);
    }

    public function getKeyLocation(string $context = ''): string
    {
        $location = trailingslashit(home_url()) . $this->getKey() . '.txt';
        return apply_filters('laca_seo/instant_indexing/indexnow_key_location', $location, $context);
    }

    /**
     * Submit one or more URLs to IndexNow API.
     * Guard: Never send real requests in local/development environment.
     *
     * @param string|string[] $urls
     * @param bool|null $manual
     * @return bool
     */
    public function submit(string|array $urls, ?bool $manual = null): bool
    {
        $urls = (array) $urls;
        if ($manual !== null) {
            $this->isManual = $manual;
        }

        $payload_json = $this->getPayload($urls);

        // SAFETY RULE B2: Check local environment
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $is_local = (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), ['local', 'development'], true))
            || str_ends_with($host, '.local')
            || $host === 'localhost'
            || $host === '127.0.0.1';

        if ($is_local) {
            $this->lastCode = 200;
            $this->lastError = '';
            $this->lastResponse = 'Simulated 200 OK (local environment)';
            $this->log($urls, 200, 'Bỏ qua gửi thật (môi trường local/dev)');
            return true;
        }

        $endpoint = apply_filters('laca_seo/indexnow/endpoint', $this->apiUrl);

        $response = wp_remote_post($endpoint, [
            'body'    => $payload_json,
            'headers' => [
                'Content-Type'  => 'application/json; charset=utf-8',
                'User-Agent'    => 'LacaSEO/' . md5(home_url()),
                'X-Source-Info' => 'https://lacadev.com/seo/' . ($this->isManual ? '1' : ''),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $this->lastError = 'WP_Error: ' . $response->get_error_message();
            $this->log($urls, 0, $this->lastError);
            return false;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        $this->lastResponse = (string) wp_remote_retrieve_body($response);

        if (in_array($this->lastCode, [200, 202, 204], true)) {
            $this->log($urls, $this->lastCode, 'OK');
            return true;
        }

        $this->lastError = wp_remote_retrieve_response_message($response) ?: "HTTP {$this->lastCode}";
        $this->log($urls, $this->lastCode, $this->lastError);
        return false;
    }

    /**
     * Get JSON payload for IndexNow request.
     *
     * @param string[] $urls
     */
    public function getPayload(array $urls): string
    {
        return (string) wp_json_encode([
            'host'        => $this->getHost(),
            'key'         => $this->getKey(),
            'keyLocation' => $this->getKeyLocation('request_payload'),
            'urlList'     => array_values($urls),
        ]);
    }

    /**
     * @param string[] $urls
     */
    public function log(array $urls, int $status, string $message = ''): void
    {
        $log = get_option('laca_seo_indexnow_log', []);
        if (!is_array($log)) {
            $log = [];
        }

        $first_url = $urls[0] ?? '';
        $count = count($urls);
        if ($count > 1) {
            $first_url .= ' [+' . ($count - 1) . ']';
        }

        $log[] = [
            'url'               => $first_url,
            'status'            => $status,
            'manual_submission' => $this->isManual,
            'message'           => $message,
            'time'              => time(),
        ];

        // Keep last 100 entries
        $log = array_slice($log, -100);
        update_option('laca_seo_indexnow_log', $log, false);
    }
}
