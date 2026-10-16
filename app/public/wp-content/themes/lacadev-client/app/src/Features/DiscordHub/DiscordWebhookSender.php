<?php

namespace App\Features\DiscordHub;

/**
 * Gửi embed từ hàng đợi lên Discord, tôn trọng rate-limit.
 *
 * Giới hạn của Discord mà class này xử lý:
 *  - Bucket theo webhook (~5 req / 2s): đọc header X-RateLimit-Remaining / Reset-After, chờ chủ động.
 *  - ~30 message / phút / kênh (không báo trước qua header): tự giới hạn MAX_MESSAGES_PER_MINUTE.
 *  - 429: tôn trọng retry_after, khoá kênh tới khi hết hạn, KHÔNG tính là lỗi.
 *  - 401/403/404 (webhook bị xoá/sai): khoá kênh 1h — liên tục bắn request lỗi sẽ chạm
 *    giới hạn 10.000 invalid request / 10 phút và IP hosting (thường là IP shared) bị Cloudflare chặn.
 *
 * Gộp tối đa 10 embed / message (≤ 6000 ký tự) → 25 msg/phút ≈ 250 sự kiện/phút/kênh.
 */
final class DiscordWebhookSender
{
    const MAX_EMBEDS_PER_MESSAGE  = 10;
    const MAX_CHARS_PER_MESSAGE   = 6000;
    const MAX_MESSAGES_PER_MINUTE = 25;
    const MAX_ATTEMPTS            = 6;
    const HTTP_TIMEOUT            = 8;
    const LEASE_SECONDS           = 60;
    const STATE_OPTION            = 'laca_discord_rate_state';

    /** @var array<string,array<string,float|int>> */
    private $state;

    /** @var callable|null Ghi log (CLI dùng để in tiến trình). */
    private $logger;

    public function __construct(?callable $logger = null)
    {
        $state        = get_option(self::STATE_OPTION, []);
        $this->state  = is_array($state) ? $state : [];
        $this->logger = $logger;
    }

    /**
     * Gửi hết hàng đợi của 1 kênh hoặc tới khi hết thời gian/bị rate-limit.
     */
    public function drainChannel(string $channel, float $deadline): void
    {
        $url = Settings::webhookUrl($channel);
        if (null === $url) {
            // Webhook đã bị gỡ khỏi wp-config → không bao giờ gửi được, dọn luôn.
            $dropped = Queue::dropChannel($channel);
            $this->log("Kênh {$channel} không còn webhook — bỏ {$dropped} sự kiện.");
            return;
        }

        while (microtime(true) < $deadline - 1) {
            if (!$this->waitForSlot($channel, $deadline)) {
                return;
            }

            $rows = Queue::claim($channel, self::MAX_EMBEDS_PER_MESSAGE, self::LEASE_SECONDS);
            if (!$rows) {
                return;
            }

            $batches = $this->pack($rows);
            foreach ($batches as $index => $batch) {
                if ($index > 0 && !$this->waitForSlot($channel, $deadline)) {
                    // Hết lượt gửi: trả các batch còn lại về hàng đợi, không tính là lỗi.
                    $rest = array_merge([], ...array_map(static function ($b) { return $b['ids']; }, array_slice($batches, $index)));
                    Queue::release($rest, time(), false);
                    return;
                }
                if (!$this->deliver($channel, $url, $batch)) {
                    // Kênh vừa bị khoá (429/webhook hỏng) → trả phần còn lại; vòng while sẽ
                    // chờ tại chỗ nếu khoá ngắn (waitForSlot), hoặc dừng để cron xử lý.
                    $rest = array_merge([], ...array_map(static function ($b) { return $b['ids']; }, array_slice($batches, $index + 1)));
                    Queue::release($rest, time(), false);
                    continue 2;
                }
            }
        }
    }

    /**
     * Thời điểm kênh được phép gửi lại (dùng để hẹn cron), 0 nếu đang mở.
     */
    public function blockedUntil(string $channel): int
    {
        $s     = $this->channelState($channel);
        $until = (float) $s['blocked_until'];
        if ((int) $s['bucket_remaining'] <= 0) {
            $until = max($until, (float) $s['bucket_reset_at']);
        }
        return $until > microtime(true) ? (int) ceil($until) : 0;
    }

    /**
     * Gửi 1 batch. Trả về false nếu kênh vừa bị khoá và cần dừng.
     *
     * @param array{ids:int[],embeds:array[],rows:array[]} $batch
     */
    private function deliver(string $channel, string $url, array $batch): bool
    {
        $response = $this->post($url, $batch['embeds']);
        $this->recordSend($channel, $response);

        if (is_wp_error($response)) {
            $this->recordError($channel, 'Máy chủ không kết nối được tới Discord: ' . $response->get_error_message() . ' — hosting có thể đang chặn kết nối ra ngoài tới discord.com.');
            $this->retryLater($batch['rows']);
            return true;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            Queue::delete($batch['ids']);
            $this->recordOk($channel);
            $this->log(sprintf('Đã gửi %d embed → %s', count($batch['ids']), $channel));
            return true;
        }

        if (429 === $code) {
            $retry = $this->retryAfter($response);
            $this->block($channel, $retry);
            Queue::release($batch['ids'], (int) floor(microtime(true) + $retry), false);
            $this->log(sprintf('429 trên kênh %s — chờ %.1fs', $channel, $retry));
            return false;
        }

        if (in_array($code, [401, 403, 404], true)) {
            [$message, $lockSeconds] = $this->explainRejection($code, (string) wp_remote_retrieve_body($response));
            $this->block($channel, $lockSeconds);
            Queue::release($batch['ids'], time() + $lockSeconds, true);
            $this->recordError($channel, $message);
            return false;
        }

        if (400 === $code) {
            // Payload không hợp lệ. Nếu batch nhiều embed, tách ra gửi lẻ để 1 embed lỗi không kéo theo cả batch.
            if (count($batch['rows']) > 1) {
                foreach ($batch['rows'] as $row) {
                    $single = [
                        'ids'    => [$row['id']],
                        'embeds' => [$row['embed']],
                        'rows'   => [$row],
                    ];
                    if (!$this->deliver($channel, $url, $single)) {
                        return false;
                    }
                }
                return true;
            }
            Queue::delete($batch['ids']);
            $this->recordError($channel, 'Discord từ chối nội dung tin (HTTP 400): ' . substr((string) wp_remote_retrieve_body($response), 0, 300));
            return true;
        }

        // 5xx và mã khác: lỗi tạm thời → backoff.
        $this->recordError($channel, "Discord trả HTTP {$code} — sẽ tự thử lại.");
        $this->retryLater($batch['rows']);
        return true;
    }

    /**
     * Phân biệt "webhook hỏng" (phải dán lại) với "Cloudflare của Discord chặn IP hosting"
     * (không phải lỗi webhook — dán lại vô ích). Shared hosting hay dính trường hợp 2.
     *
     * @return array{0:string,1:int} [thông báo cho admin, số giây khoá kênh]
     */
    private function explainRejection(int $code, string $body): array
    {
        $json = json_decode($body, true);
        $apiCode = is_array($json) ? (int) ($json['code'] ?? 0) : 0;

        if (404 === $code || 10015 === $apiCode) {
            return ['Webhook không còn tồn tại (đã bị xoá trên Discord). Tạo webhook mới và dán lại.', HOUR_IN_SECONDS];
        }
        if (401 === $code || 50027 === $apiCode) {
            return ['Sai token webhook. Sao chép lại Webhook URL từ Discord và dán lại.', HOUR_IN_SECONDS];
        }
        if (!is_array($json)) {
            $cf = preg_match('/error code:?\s*(\d{4})/i', $body, $m) ? ' (Cloudflare ' . $m[1] . ')' : '';
            return ["Cloudflare của Discord chặn IP máy chủ hosting{$cf} — không phải lỗi webhook. Liên hệ nhà cung cấp hosting về việc IP bị Discord/Cloudflare chặn.", 15 * MINUTE_IN_SECONDS];
        }

        return ['Discord từ chối (HTTP ' . $code . '): ' . substr((string) ($json['message'] ?? $body), 0, 200), HOUR_IN_SECONDS];
    }

    /**
     * Kết quả gần nhất của 1 kênh — để admin thấy lỗi thật thay vì chỉ "Đã kết nối".
     *
     * @return array{ok_at:int,error:string,error_at:int}
     */
    public static function lastResult(string $channel): array
    {
        $state = get_option(self::STATE_OPTION, []);
        $s = is_array($state) ? (array) ($state[$channel] ?? []) : [];
        return [
            'ok_at' => (int) ($s['last_ok_at'] ?? 0),
            'error' => (string) ($s['last_error'] ?? ''),
            'error_at' => (int) ($s['last_error_at'] ?? 0),
        ];
    }

    /**
     * Gỡ khoá kênh + cho sự kiện đang chờ gửi lại ngay — dùng khi admin vừa dán
     * webhook mới hoặc bấm gửi thử (khoá cũ có thể do webhook cũ hỏng).
     */
    public static function resetChannel(string $channel): void
    {
        $state = get_option(self::STATE_OPTION, []);
        if (is_array($state) && isset($state[$channel])) {
            unset($state[$channel]['blocked_until'], $state[$channel]['bucket_remaining'], $state[$channel]['bucket_reset_at']);
            update_option(self::STATE_OPTION, $state, false);
        }
        Queue::makeReady($channel);
    }

    private function recordOk(string $channel): void
    {
        $s = $this->channelState($channel);
        $s['last_ok_at'] = time();
        $s['last_error'] = '';
        $this->saveChannelState($channel, $s);
    }

    private function recordError(string $channel, string $message): void
    {
        // Thông báo lỗi có thể chứa URL request — không để token webhook lọt ra admin/log.
        $message = (string) preg_replace('#webhooks/\S+#', 'webhooks/***', $message);
        $s = $this->channelState($channel);
        $s['last_error'] = $message;
        $s['last_error_at'] = time();
        $this->saveChannelState($channel, $s);
        $this->fail("[{$channel}] {$message}");
    }

    /**
     * @return array|\WP_Error
     */
    private function post(string $url, array $embeds)
    {
        $payload = [
            'username'         => $this->username(),
            'allowed_mentions' => ['parse' => []],
            'embeds'           => $embeds,
        ];
        $avatar = function_exists('get_site_icon_url') ? Embed::safeUrl((string) get_site_icon_url(128)) : '';
        if ('' !== $avatar) {
            $payload['avatar_url'] = $avatar;
        }

        return wp_remote_post(
            $url,
            [
                'timeout'     => self::HTTP_TIMEOUT,
                'redirection' => 0,
                'blocking'    => true, // Cần đọc status/header rate-limit. Chạy SAU khi đã trả response cho user (xem Dispatcher).
                'user-agent'  => 'LacaDiscordHub/1.0 (+' . home_url('/') . ')',
                'headers'     => ['Content-Type' => 'application/json'],
                'body'        => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            ]
        );
    }

    /**
     * Discord từ chối username chứa "discord"/"clyde" (HTTP 400) và giới hạn 80 ký tự.
     */
    private function username(): string
    {
        $name = trim((string) preg_replace('/discord|clyde/i', '', Embed::clean(Settings::siteName())));
        return Embed::truncate('' !== $name ? $name : 'Laca Hub', 80);
    }

    /**
     * Gom các dòng thành message: ≤ 10 embed và ≤ 6000 ký tự mỗi message.
     *
     * @param array<int,array{id:int,payload:string,hits:int,attempts:int}> $rows
     * @return array<int,array{ids:int[],embeds:array[],rows:array[]}>
     */
    private function pack(array $rows): array
    {
        $batches = [];
        $current = ['ids' => [], 'embeds' => [], 'rows' => []];
        $chars   = 0;

        foreach ($rows as $row) {
            $embed = json_decode($row['payload'], true);
            if (!is_array($embed)) {
                Queue::delete([$row['id']]);
                continue;
            }
            if ($row['hits'] > 1) {
                $embed['title'] = Embed::truncate((string) ($embed['title'] ?? '') . ' (×' . $row['hits'] . ')', Embed::LIMIT_TITLE);
            }

            $size = Embed::size($embed);
            if ($current['ids'] && ($chars + $size > self::MAX_CHARS_PER_MESSAGE || count($current['ids']) >= self::MAX_EMBEDS_PER_MESSAGE)) {
                $batches[] = $current;
                $current   = ['ids' => [], 'embeds' => [], 'rows' => []];
                $chars     = 0;
            }

            $row['embed']        = $embed;
            $current['ids'][]    = $row['id'];
            $current['embeds'][] = $embed;
            $current['rows'][]   = $row;
            $chars              += $size;
        }

        if ($current['ids']) {
            $batches[] = $current;
        }
        return $batches;
    }

    /**
     * Lỗi tạm thời: exponential backoff 30s → 1h, quá MAX_ATTEMPTS thì bỏ.
     *
     * @param array[] $rows
     */
    private function retryLater(array $rows): void
    {
        foreach ($rows as $row) {
            $attempt = $row['attempts'] + 1;
            if ($attempt >= self::MAX_ATTEMPTS) {
                Queue::delete([$row['id']]);
                $this->fail("Bỏ sự kiện #{$row['id']} sau {$attempt} lần gửi lỗi.");
                continue;
            }
            $delay = (int) min(HOUR_IN_SECONDS, 30 * (2 ** ($attempt - 1)) + wp_rand(0, 15));
            Queue::release([$row['id']], time() + $delay, true);
        }
    }

    // ------------------------------------------------------------------
    // Rate-limit state
    // ------------------------------------------------------------------

    /**
     * Chờ tới khi kênh được phép gửi. Chỉ ngủ nếu thời gian chờ ngắn và còn trong deadline,
     * ngược lại trả false để Dispatcher hẹn cron chạy lại.
     */
    private function waitForSlot(string $channel, float $deadline): bool
    {
        $s   = $this->channelState($channel);
        $now = microtime(true);

        if ($now - (float) $s['window_start'] >= 60) {
            $s['window_start'] = $now;
            $s['window_count'] = 0;
            $this->saveChannelState($channel, $s);
        }

        $until = (float) $s['blocked_until'];
        if ((int) $s['bucket_remaining'] <= 0) {
            $until = max($until, (float) $s['bucket_reset_at']);
        }
        if ((int) $s['window_count'] >= self::MAX_MESSAGES_PER_MINUTE) {
            $until = max($until, (float) $s['window_start'] + 60);
            $s['blocked_until'] = $until;
            $this->saveChannelState($channel, $s);
        }

        $wait = $until - $now;
        if ($wait <= 0) {
            return true;
        }
        if ($wait <= 3 && $now + $wait < $deadline - 2) {
            usleep((int) ($wait * 1000000) + 50000);
            return true;
        }
        return false;
    }

    /**
     * @param array|\WP_Error $response
     */
    private function recordSend(string $channel, $response): void
    {
        $s = $this->channelState($channel);
        $s['window_count'] = (int) $s['window_count'] + 1;

        if (!is_wp_error($response)) {
            $remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');
            $reset     = wp_remote_retrieve_header($response, 'x-ratelimit-reset-after');
            if ('' !== $remaining && is_numeric($remaining)) {
                $s['bucket_remaining'] = (int) $remaining;
                $s['bucket_reset_at']  = microtime(true) + (is_numeric($reset) ? (float) $reset : 2.0);
            }
        }
        $this->saveChannelState($channel, $s);
    }

    private function block(string $channel, float $seconds): void
    {
        $s                  = $this->channelState($channel);
        $s['blocked_until'] = max((float) $s['blocked_until'], microtime(true) + $seconds);
        $this->saveChannelState($channel, $s);
    }

    /**
     * @param array $response
     */
    private function retryAfter($response): float
    {
        $body  = json_decode((string) wp_remote_retrieve_body($response), true);
        $retry = is_array($body) && isset($body['retry_after']) ? (float) $body['retry_after'] : 0.0;
        if ($retry <= 0) {
            $header = wp_remote_retrieve_header($response, 'retry-after');
            $retry  = is_numeric($header) ? (float) $header : 5.0;
        }
        return min(max($retry, 1.0), HOUR_IN_SECONDS) + 0.5;
    }

    /**
     * @return array<string,float|int>
     */
    private function channelState(string $channel): array
    {
        $defaults = [
            'blocked_until'    => 0,
            'bucket_remaining' => 1,
            'bucket_reset_at'  => 0,
            'window_start'     => 0,
            'window_count'     => 0,
        ];
        return array_merge($defaults, (array) ($this->state[$channel] ?? []));
    }

    private function saveChannelState(string $channel, array $s): void
    {
        $this->state[$channel] = $s;
        update_option(self::STATE_OPTION, $this->state, false);
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            call_user_func($this->logger, $message);
        }
    }

    private function fail(string $message): void
    {
        error_log('[laca-discord-hub] ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        $this->log($message);
    }
}
