<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Settings;
use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\Forms\ContactForm7Adapter;
use App\Features\DiscordHub\Forms\ElementorFormsAdapter;
use App\Features\DiscordHub\Forms\FluentFormsAdapter;
use App\Features\DiscordHub\Forms\FormAdapter;
use App\Features\DiscordHub\Forms\LacaContactFormAdapter;
use App\Features\DiscordHub\Forms\Lead;
use App\Features\DiscordHub\Forms\WPFormsAdapter;
use App\Features\DiscordHub\Notifier;

/**
 * Form Adapter Factory: đăng ký mọi adapter, chuẩn hoá Lead → embed #leads.
 *
 * Thêm plugin form khác (Gravity Forms, form tự viết của theme...):
 *   add_filter('laca_discord_hub/form_adapters', fn($a) => [...$a, new MyAdapter()]);
 * hoặc bắn trực tiếp: do_action('laca_discord_hub/lead', $lead) với $lead là \App\Features\DiscordHub\Forms\Lead.
 */
final class FormMonitor
{
    /** Field kỹ thuật/nhạy cảm không bao giờ gửi đi. */
    const SKIP_KEY_PATTERN  = '/^(_|g-recaptcha|cf-turnstile|h-captcha|wpcf7|wpforms|fluentform)|(password|passwd|pwd|nonce|token|captcha|honeypot|card_?number|cvv|cvc|iban)/i';
    const SKIP_TYPES        = ['password', 'file', 'file-upload', 'upload', 'captcha', 'recaptcha', 'turnstile', 'honeypot', 'html', 'divider', 'step', 'submit', 'acceptance', 'quiz', 'credit-card', 'stripe', 'payment'];
    const MAX_EXTRA_FIELDS  = 15;

    const ROLE_PATTERNS = [
        'name'    => '/(^|[_\-\s])(name|ten|ho[_\-\s]?ten|full[_\-\s]?name|your[_\-\s]?name|họ tên|tên)([_\-\s]|$)/iu',
        'phone'   => '/(phone|tel|mobile|sdt|so[_\-\s]?dien[_\-\s]?thoai|điện thoại|số điện thoại|zalo)/iu',
        'email'   => '/e-?mail/i',
        'message' => '/(message|msg|content|noi[_\-\s]?dung|loi[_\-\s]?nhan|nội dung|lời nhắn|ghi chú|note|comment|textarea)/iu',
    ];

    public function register(): void
    {
        // init: child theme đã load xong nên kịp add filter laca_discord_hub/form_adapters. Submit form luôn xảy ra sau init.
        add_action('init', [$this, 'registerAdapters'], 1);
        add_action('laca_discord_hub/lead', [$this, 'onLead']);
    }

    public function registerAdapters(): void
    {
        $adapters = apply_filters(
            'laca_discord_hub/form_adapters',
            [
                new ContactForm7Adapter(),
                new ElementorFormsAdapter(),
                new WPFormsAdapter(),
                new FluentFormsAdapter(),
                new LacaContactFormAdapter(),
            ]
        );
        foreach ((array) $adapters as $adapter) {
            if ($adapter instanceof FormAdapter) {
                $adapter->register([$this, 'onLead']);
            }
        }
    }

    /**
     * @param Lead $lead
     */
    public function onLead($lead): void
    {
        if (!$lead instanceof Lead) {
            return;
        }

        $roles  = ['name' => null, 'phone' => null, 'email' => null, 'message' => null];
        $extras = [];

        foreach ($lead->fields as $field) {
            if (in_array($field['type'], self::SKIP_TYPES, true) || preg_match(self::SKIP_KEY_PATTERN, $field['key'])) {
                continue;
            }
            $role = $this->detectRole($field);
            if (null !== $role && null === $roles[$role]) {
                $roles[$role] = $field['value'];
                continue;
            }
            $extras[] = $field;
        }

        $embed = Embed::make('📬 Lead mới — ' . $lead->formName, Embed::COLOR_LEAD);

        if (null !== $roles['message']) {
            $embed->description($roles['message']);
        }
        $embed->field('Họ tên', (string) $roles['name'])
            ->field('Số điện thoại', null !== $roles['phone'] ? Embed::code($roles['phone']) : '—', true, true)
            ->field('Email', null !== $roles['email'] ? Embed::code($roles['email']) : '—', true, true);

        foreach (array_slice($extras, 0, self::MAX_EXTRA_FIELDS) as $field) {
            $embed->field($field['label'], $field['value'], Embed::length($field['value']) < 40);
        }
        if (count($extras) > self::MAX_EXTRA_FIELDS) {
            $embed->field('…', sprintf('còn %d field khác', count($extras) - self::MAX_EXTRA_FIELDS), false);
        }

        $page = Embed::safeUrl($lead->pageUrl);
        $embed->field('Nguồn trang', '' !== $page ? Embed::link($this->shortUrl($page), $page) : '—', false, true)
            ->field('Plugin', $lead->source)
            ->codeField('IP', Context::clientIp());

        if ('' !== $lead->entryId) {
            $embed->field('Entry ID', $lead->entryId);
        }
        if ('' !== $lead->warning) {
            $embed->field('⚠️ Cảnh báo', $lead->warning, false);
        }

        // Người dùng bấm submit 2 lần / bot gửi lại y hệt → gộp thành 1 tin (×N) khi còn đang chờ gửi.
        $fingerprint = md5($lead->source . '|' . $lead->formName . '|' . wp_json_encode($lead->fields));

        Notifier::send(Settings::CHANNEL_LEADS, 'lead.new', $embed, 'lead:' . $fingerprint);
    }

    /**
     * @param array{key:string,label:string,type:string,value:string} $field
     */
    private function detectRole(array $field): ?string
    {
        if ('email' === $field['type'] || is_email($field['value'])) {
            return 'email';
        }
        if ('tel' === $field['type'] || 'phone' === $field['type']) {
            return 'phone';
        }
        $haystack = $field['key'] . ' ' . $field['label'];
        foreach (self::ROLE_PATTERNS as $role => $pattern) {
            if (preg_match($pattern, $haystack)) {
                return $role;
            }
        }
        return 'textarea' === $field['type'] ? 'message' : null;
    }

    private function shortUrl(string $url): string
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return '' !== $path && '/' !== $path ? Embed::truncate(urldecode($path), 80) : Settings::siteHost();
    }
}
