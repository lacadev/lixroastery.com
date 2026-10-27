<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Client for Google PageSpeed Insights API (v5).
 * Fetches Core Web Vitals (Field data from CrUX) and Lighthouse Lab data.
 * Does not require OAuth; accepts optional encrypted API Key to increase quota.
 */
class PageSpeedClient
{
    public const API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    public const OPTION_KEY   = 'laca_google_services';

    /**
     * Run PageSpeed test for a URL and strategy ('mobile' | 'desktop').
     *
     * @param string|null $targetUrl
     * @param string $strategy 'mobile' | 'desktop'
     * @param bool $forceRefresh
     * @return array<string, mixed>|\WP_Error
     */
    public static function run(?string $targetUrl = null, string $strategy = 'mobile', bool $forceRefresh = false): array|\WP_Error
    {
        $url = $targetUrl ?: home_url('/');
        $strategy = in_array(strtolower($strategy), ['mobile', 'desktop'], true) ? strtolower($strategy) : 'mobile';

        $cache429Key = 'laca_psi_429_' . md5($url . '_' . $strategy);
        $cached429 = get_transient($cache429Key);
        if ($cached429 instanceof \WP_Error) {
            return $cached429;
        }

        $cacheKey = 'laca_psi_' . md5($url . '_' . $strategy);
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $apiKey = self::getApiKey();

        $params = [
            'url'      => $url,
            'strategy' => $strategy,
            'locale'   => 'vi',
            'category' => 'performance',
        ];

        if (!empty($apiKey)) {
            $params['key'] = $apiKey;
        }

        $apiUrl = add_query_arg($params, self::API_ENDPOINT);

        $response = wp_remote_get($apiUrl, [
            'timeout' => 50,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($data) || !empty($data['error'])) {
            $rawMsg = is_array($data) && isset($data['error']['message']) ? (string) $data['error']['message'] : sprintf(__('Lỗi PageSpeed Insights (HTTP %d)', 'lacadev'), $code);
            $translatedMsg = self::translateErrorMessage($code, $rawMsg, is_array($data) ? $data : []);

            $errorData = [
                'status'      => $code,
                'raw_message' => $rawMsg,
                'data'        => $data,
                'link'        => admin_url('admin.php?page=laca-google-search-console#laca-psi-key'),
            ];

            $err = new \WP_Error('psi_error_' . $code, $translatedMsg, $errorData);

            if ($code === 429 || stripos($rawMsg, 'quota exceeded') !== false || stripos($rawMsg, 'resource_exhausted') !== false) {
                set_transient($cache429Key, $err, 10 * MINUTE_IN_SECONDS);
            }

            return $err;
        }

        $parsed = self::parseResponse($data, $url, $strategy);
        set_transient($cacheKey, $parsed, DAY_IN_SECONDS);

        return $parsed;
    }

    /**
     * Translate Google PageSpeed API errors into friendly Vietnamese messages.
     *
     * @param int $code
     * @param string $rawMessage
     * @param array<string, mixed> $data
     * @return string
     */
    public static function translateErrorMessage(int $code, string $rawMessage, array $data = []): string
    {
        $rawLower = strtolower($rawMessage);

        // 429 / Quota exceeded
        if ($code === 429 || str_contains($rawLower, 'quota exceeded') || str_contains($rawLower, 'resource_exhausted')) {
            $keyUrl = admin_url('admin.php?page=laca-google-search-console#laca-psi-key');
            return __('Đã hết hạn mức miễn phí của Google cho hôm nay. Thêm PageSpeed API key để có hạn mức riêng.', 'lacadev') . ' <a href="' . esc_url($keyUrl) . '">' . __('Thêm API key', 'lacadev') . '</a>';
        }

        // 400 API key invalid
        if (str_contains($rawLower, 'api key not valid') || str_contains($rawLower, 'api_key_invalid') || str_contains($rawLower, 'keyinvalid')) {
            return __('API key không hợp lệ — kiểm tra lại key.', 'lacadev');
        }

        // 403 API not enabled or wrong restriction
        if ($code === 403 || str_contains($rawLower, 'has not been used in project') || str_contains($rawLower, 'service_disabled') || str_contains($rawLower, 'permission_denied') || str_contains($rawLower, 'access_token_scope_insufficient')) {
            return __('PageSpeed Insights API chưa được bật trong project Google Cloud của key, hoặc key bị giới hạn sai API.', 'lacadev');
        }

        // 400 failed document request
        if (str_contains($rawLower, 'failed_document_request') || str_contains($rawLower, 'errored_document_request') || str_contains($rawLower, 'could not be loaded') || str_contains($rawLower, 'dns failure') || str_contains($rawLower, 'connect error')) {
            return __('Google không tải được trang này (trang lỗi hoặc chặn bot).', 'lacadev');
        }

        return sprintf(__('Lỗi PageSpeed Insights (HTTP %d)', 'lacadev'), $code);
    }

    /**
     * Parse raw response into structured Lab and Field metrics.
     *
     * @param array<string, mixed> $data
     * @param string $url
     * @param string $strategy
     * @return array<string, mixed>
     */
    public static function parseResponse(array $data, string $url, string $strategy): array
    {
        $loadingExp = $data['loadingExperience'] ?? [];
        $cruxMetrics = $loadingExp['metrics'] ?? [];

        // 1. Field Data (CrUX real user experience)
        $hasFieldData = !empty($cruxMetrics);
        $field = [
            'hasData'         => $hasFieldData,
            'overallCategory' => self::translateCategory((string) ($loadingExp['overall_category'] ?? '')),
            'metrics'         => [],
        ];

        if ($hasFieldData) {
            // LCP
            if (isset($cruxMetrics['LARGEST_CONTENTFUL_PAINT_MS'])) {
                $m = $cruxMetrics['LARGEST_CONTENTFUL_PAINT_MS'];
                $valSec = round(($m['percentile'] ?? 0) / 1000, 2);
                $field['metrics']['lcp'] = [
                    'label'         => __('LCP (Nội dung lớn nhất)', 'lacadev'),
                    'value'         => $valSec . 's',
                    'numericValue'  => $valSec,
                    'category'      => self::translateCategory($m['category'] ?? ''),
                    'categoryClass' => self::getCategoryClass($m['category'] ?? ''),
                ];
            }

            // INP
            if (isset($cruxMetrics['INTERACTION_TO_NEXT_PAINT'])) {
                $m = $cruxMetrics['INTERACTION_TO_NEXT_PAINT'];
                $valMs = (int) ($m['percentile'] ?? 0);
                $field['metrics']['inp'] = [
                    'label'         => __('INP (Tương tác đến phản hồi)', 'lacadev'),
                    'value'         => $valMs . 'ms',
                    'numericValue'  => $valMs,
                    'category'      => self::translateCategory($m['category'] ?? ''),
                    'categoryClass' => self::getCategoryClass($m['category'] ?? ''),
                ];
            }

            // CLS
            if (isset($cruxMetrics['CUMULATIVE_LAYOUT_SHIFT_SCORE'])) {
                $m = $cruxMetrics['CUMULATIVE_LAYOUT_SHIFT_SCORE'];
                $valScore = round(($m['percentile'] ?? 0) / 100, 2);
                $field['metrics']['cls'] = [
                    'label'         => __('CLS (Dịch chuyển bố cục)', 'lacadev'),
                    'value'         => (string) $valScore,
                    'numericValue'  => $valScore,
                    'category'      => self::translateCategory($m['category'] ?? ''),
                    'categoryClass' => self::getCategoryClass($m['category'] ?? ''),
                ];
            }
        }

        // 2. Lab Data (Lighthouse simulated audit)
        $lh = $data['lighthouseResult'] ?? [];
        $categories = $lh['categories'] ?? [];
        $perfScore = isset($categories['performance']['score']) ? (int) round($categories['performance']['score'] * 100) : 0;
        $audits = $lh['audits'] ?? [];

        $lab = [
            'score'         => $perfScore,
            'scoreCategory' => $perfScore >= 90 ? __('Tốt', 'lacadev') : ($perfScore >= 50 ? __('Cần cải thiện', 'lacadev') : __('Kém', 'lacadev')),
            'scoreClass'    => $perfScore >= 90 ? 'good' : ($perfScore >= 50 ? 'average' : 'poor'),
            'metrics'       => [],
        ];

        // LCP Lab
        if (isset($audits['largest-contentful-paint'])) {
            $lab['metrics']['lcp'] = [
                'label'        => __('LCP (Nội dung lớn nhất)', 'lacadev'),
                'displayValue' => (string) ($audits['largest-contentful-paint']['displayValue'] ?? ''),
                'score'        => (float) ($audits['largest-contentful-paint']['score'] ?? 0),
            ];
        }

        // CLS Lab
        if (isset($audits['cumulative-layout-shift'])) {
            $lab['metrics']['cls'] = [
                'label'        => __('CLS (Dịch chuyển bố cục)', 'lacadev'),
                'displayValue' => (string) ($audits['cumulative-layout-shift']['displayValue'] ?? ''),
                'score'        => (float) ($audits['cumulative-layout-shift']['score'] ?? 0),
            ];
        }

        // TBT Lab
        if (isset($audits['total-blocking-time'])) {
            $lab['metrics']['tbt'] = [
                'label'        => __('TBT (Thời gian chặn)', 'lacadev'),
                'displayValue' => (string) ($audits['total-blocking-time']['displayValue'] ?? ''),
                'score'        => (float) ($audits['total-blocking-time']['score'] ?? 0),
            ];
        }

        // FCP Lab
        if (isset($audits['first-contentful-paint'])) {
            $lab['metrics']['fcp'] = [
                'label'        => __('FCP (Nội dung đầu tiên)', 'lacadev'),
                'displayValue' => (string) ($audits['first-contentful-paint']['displayValue'] ?? ''),
                'score'        => (float) ($audits['first-contentful-paint']['score'] ?? 0),
            ];
        }

        return [
            'url'        => $url,
            'strategy'   => $strategy,
            'testedAt'   => current_time('mysql'),
            'testedDate' => current_time('d/m/Y H:i'),
            'field'      => $field,
            'lab'        => $lab,
        ];
    }

    /**
     * Map category string (FAST, AVERAGE, SLOW) to Vietnamese label.
     */
    public static function translateCategory(string $category): string
    {
        return match (strtoupper($category)) {
            'FAST'    => __('Tốt', 'lacadev'),
            'AVERAGE' => __('Cần cải thiện', 'lacadev'),
            'SLOW'    => __('Kém', 'lacadev'),
            default   => __('Không rõ', 'lacadev'),
        };
    }

    /**
     * Map category string to CSS class name ('good', 'average', 'poor').
     */
    public static function getCategoryClass(string $category): string
    {
        return match (strtoupper($category)) {
            'FAST'    => 'good',
            'AVERAGE' => 'average',
            'SLOW'    => 'poor',
            default   => 'unknown',
        };
    }

    /**
     * Get decrypted PageSpeed API key, or empty string if not configured.
     */
    public static function getApiKey(): string
    {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts) || empty($opts['pagespeed_api_key'])) {
            return '';
        }

        $raw = (string) $opts['pagespeed_api_key'];
        $decrypted = GscCrypto::decrypt($raw);

        return is_string($decrypted) ? trim($decrypted) : '';
    }

    /**
     * Save PageSpeed API key (encrypted with GscCrypto).
     */
    public static function setApiKey(string $key): void
    {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }

        $cleanKey = trim($key);
        if ($cleanKey === '') {
            unset($opts['pagespeed_api_key']);
        } else {
            $opts['pagespeed_api_key'] = GscCrypto::encrypt($cleanKey);
        }

        update_option(self::OPTION_KEY, $opts, false);
    }

    /**
     * Check if API key is configured.
     */
    public static function hasApiKey(): bool
    {
        return self::getApiKey() !== '';
    }

    /**
     * Provide realistic mock data for local development and tests.
     *
     * @param string $url
     * @param string $strategy
     * @return array<string, mixed>
     */
    public static function getMockData(string $url = '', string $strategy = 'mobile'): array
    {
        $targetUrl = $url ?: home_url('/');
        $isDesktop = strtolower($strategy) === 'desktop';

        return [
            'url'        => $targetUrl,
            'strategy'   => $isDesktop ? 'desktop' : 'mobile',
            'testedAt'   => current_time('mysql'),
            'testedDate' => current_time('d/m/Y H:i'),
            'field'      => [
                'hasData'         => true,
                'overallCategory' => __('Tốt', 'lacadev'),
                'metrics'         => [
                    'lcp' => [
                        'label'         => __('LCP (Nội dung lớn nhất)', 'lacadev'),
                        'value'         => $isDesktop ? '1.2s' : '1.8s',
                        'numericValue'  => $isDesktop ? 1.2 : 1.8,
                        'category'      => __('Tốt', 'lacadev'),
                        'categoryClass' => 'good',
                    ],
                    'inp' => [
                        'label'         => __('INP (Tương tác đến phản hồi)', 'lacadev'),
                        'value'         => $isDesktop ? '45ms' : '85ms',
                        'numericValue'  => $isDesktop ? 45 : 85,
                        'category'      => __('Tốt', 'lacadev'),
                        'categoryClass' => 'good',
                    ],
                    'cls' => [
                        'label'         => __('CLS (Dịch chuyển bố cục)', 'lacadev'),
                        'value'         => '0.02',
                        'numericValue'  => 0.02,
                        'category'      => __('Tốt', 'lacadev'),
                        'categoryClass' => 'good',
                    ],
                ],
            ],
            'lab'        => [
                'score'         => $isDesktop ? 96 : 88,
                'scoreCategory' => $isDesktop ? __('Tốt', 'lacadev') : __('Cần cải thiện', 'lacadev'),
                'scoreClass'    => $isDesktop ? 'good' : 'average',
                'metrics'       => [
                    'lcp' => [
                        'label'        => __('LCP (Nội dung lớn nhất)', 'lacadev'),
                        'displayValue' => $isDesktop ? '1.4 s' : '2.1 s',
                        'score'        => $isDesktop ? 0.98 : 0.88,
                    ],
                    'cls' => [
                        'label'        => __('CLS (Dịch chuyển bố cục)', 'lacadev'),
                        'displayValue' => '0.02',
                        'score'        => 0.99,
                    ],
                    'tbt' => [
                        'label'        => __('TBT (Thời gian chặn)', 'lacadev'),
                        'displayValue' => $isDesktop ? '40 ms' : '120 ms',
                        'score'        => $isDesktop ? 0.99 : 0.89,
                    ],
                    'fcp' => [
                        'label'        => __('FCP (Nội dung đầu tiên)', 'lacadev'),
                        'displayValue' => $isDesktop ? '0.8 s' : '1.2 s',
                        'score'        => $isDesktop ? 0.99 : 0.94,
                    ],
                ],
            ],
        ];
    }
}
