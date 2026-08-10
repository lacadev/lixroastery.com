<?php

namespace App\Helpers;

/**
 * Utility class for encrypting and decrypting sensitive data (like passwords)
 * based on the WordPress Authentication Key or a custom secret.
 */
class Crypto
{
    private const CIPHER_ALGO = 'aes-256-cbc';

    /**
     * Lấy encryption key an toàn từ wp-config.php
     */
    private static function getKey(): string
    {
        // Sử dụng AUTH_KEY nếu có, nếu không thì dùng SECRET_KEY
        if (defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here') {
            return substr(hash('sha256', AUTH_KEY), 0, 32);
        }
        
        return substr(hash('sha256', 'laca_fallback_secret_key_if_auth_key_missing'), 0, 32);
    }

    /**
     * Mã hoá dữ liệu
     * Format trả về: base64(iv . encrypted_data)
     */
    public static function encrypt(string $plainText): string
    {
        if (empty($plainText)) {
            return '';
        }

        // Tạo IV an toàn (16 bytes cho AES)
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $iv = openssl_random_pseudo_bytes($ivLength);

        // Mã hoá
        $encrypted = openssl_encrypt(
            $plainText,
            self::CIPHER_ALGO,
            self::getKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            return '';
        }

        // Kết hợp IV và dữ liệu đã mã hoá, rồi base64
        return base64_encode($iv . $encrypted);
    }

    /**
     * Giải mã dữ liệu
     */
    public static function decrypt(string $encryptedText): string
    {
        if (empty($encryptedText)) {
            return '';
        }

        $decoded = base64_decode($encryptedText, true);
        if ($decoded === false) {
            return $encryptedText; // Fallback trả về nguyên bản nếu decode lỗi
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        
        // Cần đảm bảo độ dài chuỗi decoded dài hơn IV
        if (strlen($decoded) <= $ivLength) {
            return $encryptedText;
        }

        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER_ALGO,
            self::getKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : $encryptedText;
    }

    /**
     * Kiểm tra chuỗi có phải đã được mã hoá hay chưa (heuristic check)
     * Thường chuỗi Base64 dài và không chứa ký tự khoảng trắng
     */
    public static function isEncrypted(string $text): bool
    {
        if (empty($text) || strlen($text) < 32) {
            return false;
        }

        // Kiểm tra xem có chứa ký tự base64 hợp lệ không
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $text)) {
            return false;
        }

        // Thử decode
        $decoded = base64_decode($text, true);
        return $decoded !== false && strlen($decoded) > openssl_cipher_iv_length(self::CIPHER_ALGO);
    }
}
