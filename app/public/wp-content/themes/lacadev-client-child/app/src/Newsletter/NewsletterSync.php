<?php

/**
 * Đồng bộ email Newsletter sang dịch vụ ngoài: Google Sheet (Apps Script),
 * Mailchimp hoặc Brevo — mỗi dịch vụ bật/tắt độc lập trong Theme Options.
 *
 * - Google Sheet: nhận MỌI lần đổi trạng thái (pending/subscribed/
 *   unsubscribed) — Apps Script cập nhật đúng dòng của email đó.
 * - Mailchimp/Brevo: chỉ thêm khi đã xác nhận (subscribed), gỡ khi huỷ.
 *   Website đã tự làm double opt-in nên Mailchimp thêm thẳng "subscribed",
 *   tránh khách nhận 2 email xác nhận.
 *
 * Chạy ở hook `shutdown` SAU khi đã trả phản hồi cho khách (finish request),
 * nên khách không phải chờ API ngoài. Không dùng WP-Cron vì site có thể tắt
 * wp-cron (Laca Admin có mục Cron URL) — khi đó sync sẽ không bao giờ chạy.
 * Kết quả lần gọi gần nhất của từng dịch vụ lưu vào option để trang admin
 * Newsletter hiển thị, admin biết ngay khi API key hết hạn/sai.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterSync
{
    private const STATUS_OPTION = 'lix_newsletter_sync_status';
    private const TIMEOUT = 8;

    /**
     * @param string $origin Provider gây ra thay đổi (webhook) — bỏ qua gọi
     *                       ngược lại chính provider đó.
     */
    public static function queue(string $status, ?array $subscriber, string $origin = ''): void
    {
        if (!$subscriber || !self::hasAnyService()) {
            return;
        }

        add_action('shutdown', static function () use ($status, $subscriber, $origin) {
            // Đóng kết nối với trình duyệt trước, rồi mới gọi API ngoài.
            if (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            } elseif (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            self::run($status, $subscriber, $origin);
        });
    }

    public static function hasAnyService(): bool
    {
        return self::sheetUrl() !== '' || self::provider() !== '';
    }

    public static function run(string $status, array $subscriber, string $origin = ''): void
    {
        if (self::sheetUrl() !== '') {
            self::saveStatus('sheet', self::pushSheet($status, $subscriber));
        }

        $provider = self::provider();
        if ($provider === '' || $provider === $origin || $status === NewsletterSubscribers::STATUS_PENDING) {
            return;
        }

        $subscribe = $status === NewsletterSubscribers::STATUS_SUBSCRIBED;
        if ($provider === 'mailchimp') {
            self::saveStatus('mailchimp', self::pushMailchimp($subscriber, $subscribe));
        } elseif ($provider === 'brevo') {
            self::saveStatus('brevo', self::pushBrevo($subscriber, $subscribe));
        }
    }

    /**
     * @return array<string, array{ok: bool, message: string, time: string}>
     */
    public static function statuses(): array
    {
        $status = get_option(self::STATUS_OPTION, []);
        return is_array($status) ? $status : [];
    }

    public static function provider(): string
    {
        $provider = (string) carbon_get_theme_option('newsletter_provider');
        return in_array($provider, ['mailchimp', 'brevo'], true) ? $provider : '';
    }

    /**
     * Chỉ trả URL khi đúng Apps Script — tránh ô cấu hình bị dùng để bắn
     * request tới host tuỳ ý từ server.
     */
    public static function sheetUrl(): string
    {
        $url = trim((string) carbon_get_theme_option('newsletter_sheet_webhook'));
        if ($url === '' || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || wp_parse_url($url, PHP_URL_HOST) !== 'script.google.com') {
            return '';
        }
        return $url;
    }

    /**
     * @param true|string $result true nếu thành công, chuỗi lỗi nếu thất bại.
     */
    public static function saveStatus(string $service, $result): void
    {
        $status = self::statuses();
        $status[$service] = [
            'ok' => $result === true,
            'message' => $result === true ? '' : sanitize_text_field((string) $result),
            'time' => current_time('mysql'),
        ];
        update_option(self::STATUS_OPTION, $status, false);
    }

    /**
     * @return true|string
     */
    private static function pushSheet(string $status, array $subscriber)
    {
        $response = wp_remote_post(self::sheetUrl(), [
            'timeout' => self::TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'secret' => (string) carbon_get_theme_option('newsletter_sheet_secret'),
                'email' => $subscriber['email'],
                'lang' => $subscriber['lang'],
                'source' => $subscriber['source_url'],
                'status' => $status,
                'created_at' => $subscriber['created_at'],
                'updated_at' => current_time('Y-m-d H:i:s'),
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        // Apps Script trả 302 sang googleusercontent rồi mới ra nội dung —
        // wp_remote_post tự theo redirect, body cuối cùng là 'ok'/'forbidden'.
        $body = trim(wp_remote_retrieve_body($response));
        if ($body === 'forbidden') {
            return __('Chuỗi bí mật không khớp với SECRET trong Apps Script.', 'laca');
        }
        if ($body !== 'ok') {
            return sprintf(
                /* translators: %d: mã HTTP */
                __('Apps Script trả về phản hồi lạ (HTTP %d) — kiểm tra lại URL và quyền truy cập "Bất kỳ ai".', 'laca'),
                (int) wp_remote_retrieve_response_code($response)
            );
        }

        return true;
    }

    /**
     * @return true|string
     */
    private static function pushMailchimp(array $subscriber, bool $subscribe)
    {
        $api_key = trim((string) carbon_get_theme_option('newsletter_mailchimp_key'));
        $list_id = trim((string) carbon_get_theme_option('newsletter_mailchimp_list'));

        // Data center nằm ở hậu tố của API key (vd "...-us21") — validate để
        // không ghép chuỗi tuỳ ý vào hostname.
        $dc = substr((string) strrchr($api_key, '-'), 1);
        if ($api_key === '' || $list_id === '' || !preg_match('/^[a-z]+\d+$/', $dc) || !preg_match('/^[a-z0-9]+$/i', $list_id)) {
            return __('Thiếu hoặc sai API key / Audience ID Mailchimp.', 'laca');
        }

        $url = sprintf('https://%s.api.mailchimp.com/3.0/lists/%s/members/%s', $dc, $list_id, md5($subscriber['email']));
        $args = [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('lix:' . $api_key),
                'Content-Type' => 'application/json',
            ],
        ];

        if ($subscribe) {
            // PUT theo hash email = "thêm hoặc cập nhật"; khách đã xác nhận
            // qua email của website nên đặt thẳng subscribed (kể cả người
            // từng huỷ rồi đăng ký lại).
            $body = [
                'email_address' => $subscriber['email'],
                'status' => 'subscribed',
                'status_if_new' => 'subscribed',
            ];
            if ($subscriber['lang'] !== '') {
                $body['language'] = $subscriber['lang'];
            }
            $response = wp_remote_request($url, $args + ['method' => 'PUT', 'body' => wp_json_encode($body)]);
        } else {
            $response = wp_remote_request($url, $args + ['method' => 'PATCH', 'body' => wp_json_encode(['status' => 'unsubscribed'])]);
            // Chưa từng có trên Mailchimp (vd huỷ khi còn pending) — không có gì để gỡ.
            if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 404) {
                return true;
            }
        }

        return self::checkJsonResponse($response, 'detail');
    }

    /**
     * @return true|string
     */
    private static function pushBrevo(array $subscriber, bool $subscribe)
    {
        $api_key = trim((string) carbon_get_theme_option('newsletter_brevo_key'));
        $list_id = absint(carbon_get_theme_option('newsletter_brevo_list'));

        if ($api_key === '' || !$list_id) {
            return __('Thiếu API key hoặc List ID Brevo.', 'laca');
        }

        $args = [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'api-key' => $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if ($subscribe) {
            // updateEnabled: email đã có trong Brevo thì chỉ thêm vào list.
            $response = wp_remote_post('https://api.brevo.com/v3/contacts', $args + [
                'body' => wp_json_encode([
                    'email' => $subscriber['email'],
                    'listIds' => [$list_id],
                    'updateEnabled' => true,
                ]),
            ]);
        } else {
            $response = wp_remote_request('https://api.brevo.com/v3/contacts/' . rawurlencode($subscriber['email']), $args + [
                'method' => 'PUT',
                'body' => wp_json_encode(['unlinkListIds' => [$list_id]]),
            ]);
            if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 404) {
                return true;
            }
        }

        return self::checkJsonResponse($response, 'message');
    }

    /**
     * @param array|WP_Error $response
     * @return true|string
     */
    private static function checkJsonResponse($response, string $error_key)
    {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return true;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $message = is_array($data) && !empty($data[$error_key]) ? (string) $data[$error_key] : '';

        return trim(sprintf('HTTP %d %s', $code, $message));
    }
}
