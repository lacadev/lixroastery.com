<?php

namespace App\Features\PostAudio;

defined('ABSPATH') || exit;

/**
 * Xử lý mã hoá/giải mã API key bằng AES-256-GCM dựa trên salt của WordPress.
 * Đảm bảo API key không bao giờ lưu dưới dạng bản rõ (plaintext) trong database.
 */
class PostAudioSecurity
{
    private const CIPHER = 'aes-256-gcm';

    /**
     * Lấy encryption key 256-bit tạo từ AUTH_KEY và SECURE_AUTH_SALT.
     */
    private static function getKey(): string
    {
        $authKey = defined('AUTH_KEY') ? AUTH_KEY : 'default_laca_post_audio_auth_key';
        $salt    = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'default_laca_post_audio_secure_salt';

        return hash_hkdf('sha256', $authKey, 32, 'laca_post_audio_api_key_encryption', $salt);
    }

    /**
     * Mã hoá chuỗi (API key).
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::getKey();
        $iv  = openssl_random_pseudo_bytes((int) openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            return '';
        }

        return base64_encode((string) wp_json_encode([
            'c' => base64_encode($ciphertext),
            'i' => base64_encode($iv),
            't' => base64_encode($tag),
        ]));
    }

    /**
     * Giải mã chuỗi đã mã hoá về plaintext.
     */
    public static function decrypt(string $encryptedString): string
    {
        if (empty($encryptedString)) {
            return '';
        }

        $decodedRaw = base64_decode($encryptedString, true);
        if ($decodedRaw === false) {
            return '';
        }

        $decoded = json_decode($decodedRaw, true);
        if (!is_array($decoded) || empty($decoded['c']) || empty($decoded['i']) || empty($decoded['t'])) {
            return '';
        }

        $ciphertext = base64_decode((string) $decoded['c'], true);
        $iv         = base64_decode((string) $decoded['i'], true);
        $tag        = base64_decode((string) $decoded['t'], true);
        $key        = self::getKey();

        if ($ciphertext === false || $iv === false || $tag === false) {
            return '';
        }

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Che bớt API key khi hiển thị trong admin (vd: ••••1234). Chưa có key thì trả chuỗi rỗng.
     */
    public static function maskKey(string $key): string
    {
        $len = mb_strlen($key);
        if ($len === 0) {
            return '';
        }

        $visibleCount = min(4, (int) floor($len / 3));
        return '••••' . ($visibleCount > 0 ? mb_substr($key, -$visibleCount) : '');
    }
}
