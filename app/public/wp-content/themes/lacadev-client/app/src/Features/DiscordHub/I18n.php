<?php

namespace App\Features\DiscordHub;

/**
 * Ngôn ngữ của tin nhắn Discord (Laca Admin → Discord Hub → Ngôn ngữ thông báo).
 *
 * Không dùng __()/gettext: ngôn ngữ thông báo độc lập với locale của site
 * (site tiếng Việt vẫn có thể cần thông báo song ngữ cho dev/đối tác nước ngoài).
 */
final class I18n
{
    public const LANGUAGES = [
        'both' => 'Song ngữ (Tiếng Việt + English)',
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ];

    /**
     * Chuỗi ngắn (tiêu đề, tên field): song ngữ ghép trên 1 dòng "VI / EN".
     */
    public static function t(string $vi, string $en): string
    {
        return match (Settings::language()) {
            'vi' => $vi,
            'en' => $en,
            default => $vi === $en ? $vi : $vi . ' / ' . $en,
        };
    }

    /**
     * Đoạn văn (mô tả, cảnh báo): song ngữ xuống dòng, bản tiếng Anh in nghiêng.
     */
    public static function block(string $vi, string $en): string
    {
        return match (Settings::language()) {
            'vi' => $vi,
            'en' => $en,
            default => $vi . "\n*" . $en . '*',
        };
    }
}
