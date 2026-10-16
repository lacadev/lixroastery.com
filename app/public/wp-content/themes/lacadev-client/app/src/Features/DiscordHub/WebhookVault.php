<?php

namespace App\Features\DiscordHub;

/**
 * Mã hoá webhook URL trước khi lưu DB.
 *
 * Dùng AES-256-GCM (có xác thực) thay vì App\Helpers\Crypto (AES-CBC không MAC,
 * decrypt lỗi thì trả nguyên chuỗi): ciphertext bị sửa/đổi chỗ giữa các kênh
 * phải bị phát hiện, không âm thầm trả về rác.
 *
 * Khoá dẫn xuất từ AUTH_KEY + SECURE_AUTH_KEY trong wp-config.php → bản dump DB
 * (backup, SQL injection đọc wp_options) không đủ để lấy webhook. Đổi salt trong
 * wp-config sẽ làm webhook cũ không giải mã được → admin phải dán lại.
 */
final class WebhookVault
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v1:';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const PLACEHOLDER = 'put your unique phrase here';

    public static function isAvailable(): bool
    {
        return function_exists('openssl_encrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true)
            && null !== self::key();
    }

    /**
     * @param string $context Gắn vào AAD (vd tên kênh) để không thể bê ciphertext kênh này sang kênh khác.
     */
    public static function encrypt(string $plain, string $context): ?string
    {
        $key = self::key();
        if (null === $key || '' === $plain) {
            return null;
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, self::aad($context), self::TAG_LENGTH);

        return false === $cipher ? null : self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * @return string|null null nếu sai khoá, dữ liệu bị sửa, hoặc sai kênh.
     */
    public static function decrypt(string $blob, string $context): ?string
    {
        $key = self::key();
        if (null === $key || !str_starts_with($blob, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($blob, strlen(self::PREFIX)), true);
        if (false === $raw || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $plain = openssl_decrypt(
            substr($raw, self::IV_LENGTH + self::TAG_LENGTH),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::IV_LENGTH),
            substr($raw, self::IV_LENGTH, self::TAG_LENGTH),
            self::aad($context)
        );

        return false === $plain ? null : $plain;
    }

    private static function key(): ?string
    {
        $parts = [];
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY'] as $name) {
            if (defined($name) && is_string(constant($name)) && '' !== constant($name) && self::PLACEHOLDER !== constant($name)) {
                $parts[] = constant($name);
            }
        }

        // Không có salt thật thì từ chối mã hoá — khoá cố định trong code = không mã hoá.
        return $parts ? hash_hmac('sha256', 'laca-discord-hub|webhooks|v1', implode('|', $parts), true) : null;
    }

    private static function aad(string $context): string
    {
        return 'laca-discord-hub:' . $context;
    }
}
