<?php

namespace App\Features\DiscordHub;

/**
 * Cấu hình Discord Hub (Laca Admin → Discord Hub).
 *
 * 2 option tách riêng:
 *  - OPTION (autoload): cấu hình thường + metadata kênh (đã cấu hình hay chưa, lúc nào, ai sửa).
 *    Mọi request chỉ đọc cái này để quyết định có queue sự kiện không — không giải mã.
 *  - SECRETS_OPTION (không autoload): webhook đã mã hoá, chỉ giải mã lúc thực sự gửi.
 */
final class Settings
{
    public const CHANNEL_LEADS = 'leads';
    public const CHANNEL_AUDIT = 'audit';
    public const CHANNEL_SECURITY = 'security';

    public const CHANNELS = [
        self::CHANNEL_LEADS => [
            'label' => 'Leads — form liên hệ',
            'hint' => 'Kênh #tensite-leads: khách gửi form (form Laca, CF7, Elementor, WPForms, Fluent Forms).',
        ],
        self::CHANNEL_AUDIT => [
            'label' => 'Audit — hoạt động',
            'hint' => 'Kênh #tensite-audit: bài viết đổi trạng thái, bình luận, user mới/xoá/đổi quyền.',
        ],
        self::CHANNEL_SECURITY => [
            'label' => 'Security — cảnh báo',
            'hint' => 'Kênh #tensite-security: brute-force, tạo/nâng quyền admin, spam tăng đột biến, lỗi nghiêm trọng (PHP fatal error).',
        ],
    ];

    public const OPTION = 'laca_discord_hub';
    public const SECRETS_OPTION = 'laca_discord_hub_webhooks';

    /**
     * Chỉ chấp nhận webhook Discord chính thức — agent POST dữ liệu lead/IP tới URL
     * này, URL lạ = rò rỉ dữ liệu khách ra ngoài.
     */
    public const WEBHOOK_PATTERN = '#^https://(?:(?:canary|ptb)\.)?discord(?:app)?\.com/api/(?:v\d+/)?webhooks/\d{17,20}/[A-Za-z0-9_\-]{20,}$#';

    public const IP_HEADERS = [
        '' => 'REMOTE_ADDR (mặc định, an toàn)',
        'HTTP_CF_CONNECTING_IP' => 'Cloudflare (CF-Connecting-IP)',
    ];

    private const DEFAULTS = [
        'enabled' => true,
        'site_name' => '',
        'ip_header' => '',
        'allow_non_production' => false,
        'post_types' => ['post'],
        'language' => 'both',
        'channels' => [],
    ];

    private static ?array $settings = null;

    /** @var array<string,string|null> */
    private static array $urls = [];

    public static function all(): array
    {
        if (null === self::$settings) {
            $stored = get_option(self::OPTION, []);
            self::$settings = array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);
        }
        return self::$settings;
    }

    public static function save(array $settings): void
    {
        $clean = array_intersect_key(array_merge(self::DEFAULTS, $settings), self::DEFAULTS);
        update_option(self::OPTION, $clean, true);
        self::$settings = null;
    }

    public static function isEnabled(): bool
    {
        return (bool) self::all()['enabled'];
    }

    public static function hasChannel(string $channel): bool
    {
        return isset(self::all()['channels'][$channel]);
    }

    public static function hasAnyChannel(): bool
    {
        return [] !== array_intersect_key((array) self::all()['channels'], self::CHANNELS);
    }

    /**
     * Mặc định chỉ gửi ở production: site Local/staging clone DB sẽ mang theo webhook
     * (giải mã được nếu copy cả wp-config) → tránh bắn thông báo giả vào kênh thật.
     */
    public static function isEnvironmentAllowed(): bool
    {
        return 'production' === wp_get_environment_type() || (bool) self::all()['allow_non_production'];
    }

    public static function canSend(string $channel): bool
    {
        return self::isEnabled() && self::isEnvironmentAllowed() && self::hasChannel($channel);
    }

    /**
     * Giải mã webhook — chỉ gọi lúc gửi thật (Sender), không gọi trong request thường.
     */
    public static function webhookUrl(string $channel): ?string
    {
        if (array_key_exists($channel, self::$urls)) {
            return self::$urls[$channel];
        }

        $secrets = get_option(self::SECRETS_OPTION, []);
        $blob = is_array($secrets) ? (string) ($secrets[$channel] ?? '') : '';
        $url = '' !== $blob ? WebhookVault::decrypt($blob, $channel) : null;

        return self::$urls[$channel] = (null !== $url && preg_match(self::WEBHOOK_PATTERN, $url)) ? $url : null;
    }

    public static function isValidWebhook(string $url): bool
    {
        return (bool) preg_match(self::WEBHOOK_PATTERN, $url);
    }

    public static function storeWebhook(string $channel, string $url): bool
    {
        if (!isset(self::CHANNELS[$channel]) || !self::isValidWebhook($url)) {
            return false;
        }
        $blob = WebhookVault::encrypt($url, $channel);
        if (null === $blob) {
            return false;
        }

        $secrets = get_option(self::SECRETS_OPTION, []);
        $secrets = is_array($secrets) ? $secrets : [];
        $secrets[$channel] = $blob;
        update_option(self::SECRETS_OPTION, $secrets, false);

        $settings = self::all();
        $settings['channels'][$channel] = [
            'updated_at' => time(),
            'updated_by' => wp_get_current_user()->user_login ?: 'system',
        ];
        self::save($settings);
        unset(self::$urls[$channel]);
        DiscordWebhookSender::resetChannel($channel);
        return true;
    }

    public static function removeWebhook(string $channel): void
    {
        $secrets = get_option(self::SECRETS_OPTION, []);
        if (is_array($secrets) && isset($secrets[$channel])) {
            unset($secrets[$channel]);
            update_option(self::SECRETS_OPTION, $secrets, false);
        }

        $settings = self::all();
        unset($settings['channels'][$channel]);
        self::save($settings);
        unset(self::$urls[$channel]);
    }

    /**
     * Trạng thái kênh để hiển thị trong admin — KHÔNG bao giờ trả về URL.
     *
     * @return array{state:string,updated_at:int,updated_by:string}
     */
    public static function channelStatus(string $channel): array
    {
        $meta = self::all()['channels'][$channel] ?? null;
        if (!is_array($meta)) {
            return ['state' => 'empty', 'updated_at' => 0, 'updated_by' => ''];
        }
        return [
            'state' => null !== self::webhookUrl($channel) ? 'ok' : 'broken',
            'updated_at' => (int) ($meta['updated_at'] ?? 0),
            'updated_by' => (string) ($meta['updated_by'] ?? ''),
        ];
    }

    public static function siteName(): string
    {
        $name = trim((string) self::all()['site_name']);
        if ('' === $name) {
            $name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        }
        return '' !== trim($name) ? $name : self::siteHost();
    }

    public static function siteHost(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        return is_string($host) ? $host : 'wordpress';
    }

    public static function ipHeader(): string
    {
        $header = (string) self::all()['ip_header'];
        return isset(self::IP_HEADERS[$header]) ? $header : '';
    }

    public static function language(): string
    {
        $language = (string) self::all()['language'];
        return isset(I18n::LANGUAGES[$language]) ? $language : 'both';
    }

    /**
     * @return string[]
     */
    public static function postTypes(): array
    {
        return array_values(array_filter((array) self::all()['post_types'], 'is_string'));
    }

    /**
     * Ngưỡng cảnh báo, tuỳ biến qua filter `laca_discord_hub/threshold/{name}`.
     */
    public static function threshold(string $name, int $default): int
    {
        return max(1, (int) apply_filters("laca_discord_hub/threshold/{$name}", $default));
    }
}
