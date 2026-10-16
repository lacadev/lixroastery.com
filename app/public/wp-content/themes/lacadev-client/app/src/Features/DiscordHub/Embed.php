<?php

namespace App\Features\DiscordHub;

/**
 * Builder cho Discord Embed. Mọi chuỗi đi qua đây đều được:
 *  - làm sạch UTF-8, bỏ HTML & ký tự điều khiển,
 *  - escape markdown (chặn masked-link phishing `[text](url)`, mention `<@id>`),
 *  - cắt theo đúng giới hạn của Discord (vượt giới hạn = HTTP 400, mất cả batch).
 *
 * Mention (@everyone/@here/<@id>) còn bị chặn ở tầng payload bằng `allowed_mentions`.
 */
final class Embed
{
    const COLOR_SUCCESS = 0x2ECC71;
    const COLOR_INFO    = 0x3498DB;
    const COLOR_WARNING = 0xF39C12;
    const COLOR_DANGER  = 0xE74C3C;
    const COLOR_LEAD    = 0xE67E22;

    const LIMIT_TITLE       = 256;
    const LIMIT_DESCRIPTION = 4096;
    const LIMIT_FIELDS      = 25;
    const LIMIT_FIELD_NAME  = 256;
    const LIMIT_FIELD_VALUE = 1024;
    const LIMIT_FOOTER      = 2048;
    const LIMIT_AUTHOR      = 256;

    /** @var array<string,mixed> */
    private $data;

    private function __construct(string $title, int $color)
    {
        $this->data = [
            'title'  => self::truncate(self::clean($title), self::LIMIT_TITLE),
            'color'  => $color,
            'fields' => [],
        ];
    }

    public static function make(string $title, int $color): self
    {
        return new self($title, $color);
    }

    /**
     * @param bool $trusted true = chuỗi do agent tự dựng (cho phép markdown), false = dữ liệu người dùng.
     */
    public function description(string $text, bool $trusted = false): self
    {
        $text = $trusted ? $text : self::escape($text);
        if ('' !== trim($text)) {
            $this->data['description'] = self::truncate($text, self::LIMIT_DESCRIPTION);
        }
        return $this;
    }

    public function url(string $url): self
    {
        $url = self::safeUrl($url);
        if ('' !== $url) {
            $this->data['url'] = $url;
        }
        return $this;
    }

    /**
     * @param mixed $value
     */
    public function field(string $name, $value, bool $inline = true, bool $trusted = false): self
    {
        if (count($this->data['fields']) >= self::LIMIT_FIELDS) {
            return $this;
        }
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', array_filter($value, 'is_scalar')));
        }
        $value = (string) $value;
        $value = $trusted ? $value : self::escape($value);
        if ('' === trim($value)) {
            $value = '—';
        }

        $this->data['fields'][] = [
            'name'   => self::truncate(self::clean($name), self::LIMIT_FIELD_NAME),
            'value'  => self::truncate($value, self::LIMIT_FIELD_VALUE),
            'inline' => $inline,
        ];
        return $this;
    }

    /**
     * Field dạng `inline code` — dùng cho IP, email, hash, username.
     */
    public function codeField(string $name, string $value, bool $inline = true): self
    {
        return $this->field($name, self::code($value), $inline, true);
    }

    public function toArray(): array
    {
        $embed           = $this->data;
        $embed['author'] = ['name' => self::truncate(self::clean(Settings::siteName()), self::LIMIT_AUTHOR)];
        $embed['footer'] = ['text' => self::truncate('Laca Discord Hub · ' . Settings::siteHost() . ' · ' . wp_get_environment_type(), self::LIMIT_FOOTER)];
        $embed['timestamp'] = gmdate('c');

        $home = self::safeUrl(home_url('/'));
        if ('' !== $home) {
            $embed['author']['url'] = $home;
        }
        if (empty($embed['fields'])) {
            unset($embed['fields']);
        }
        return $embed;
    }

    // ------------------------------------------------------------------
    // Sanitizers
    // ------------------------------------------------------------------

    /**
     * Chuẩn hoá text thô: UTF-8 hợp lệ, không HTML, không ký tự điều khiển.
     */
    public static function clean(string $text): string
    {
        $text = wp_check_invalid_utf8($text, true);
        $text = wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[^\P{C}\n\t]/u', '', $text);
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Escape markdown của Discord cho dữ liệu không tin cậy.
     */
    public static function escape(string $text): string
    {
        $text = self::clean($text);
        $text = (string) preg_replace('/([\\\\*_~`|>\[\]()<#:\-])/u', '\\\\$1', $text);
        // Phòng thủ chiều sâu cho hiển thị — việc chặn ping thật nằm ở allowed_mentions.
        return str_replace('@', "@\u{200B}", $text);
    }

    public static function code(string $text): string
    {
        $text = str_replace('`', "\u{02CB}", self::clean($text));
        $text = str_replace("\n", ' ', $text);
        return '' === $text ? '—' : '`' . self::truncate($text, 200) . '`';
    }

    /**
     * Link markdown do agent tự dựng (URL nội bộ đã esc_url_raw).
     */
    public static function link(string $label, string $url): string
    {
        $url = self::safeUrl($url);
        if ('' === $url) {
            return self::escape($label);
        }
        $label = str_replace(['[', ']'], ['(', ')'], self::clean($label));
        return '[' . self::truncate($label, 200) . '](' . str_replace(['(', ')'], ['%28', '%29'], $url) . ')';
    }

    public static function safeUrl(string $url): string
    {
        $url = esc_url_raw(trim($url), ['http', 'https']);
        return ('' !== $url && strlen($url) <= 2048) ? $url : '';
    }

    public static function truncate(string $text, int $limit): string
    {
        if (self::length($text) <= $limit) {
            return $text;
        }
        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $limit - 1, 'UTF-8') : substr($text, 0, $limit - 1);
        // Không để lại dấu "\" lẻ ở cuối (sẽ escape nhầm ký tự "…").
        return rtrim($cut, '\\') . '…';
    }

    public static function length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    /**
     * Tổng ký tự của 1 embed theo cách Discord tính (giới hạn 6000/message).
     */
    public static function size(array $embed): int
    {
        $total = self::length((string) ($embed['title'] ?? ''))
            + self::length((string) ($embed['description'] ?? ''))
            + self::length((string) ($embed['footer']['text'] ?? ''))
            + self::length((string) ($embed['author']['name'] ?? ''));
        foreach ((array) ($embed['fields'] ?? []) as $field) {
            $total += self::length((string) $field['name']) + self::length((string) $field['value']);
        }
        return $total;
    }
}
