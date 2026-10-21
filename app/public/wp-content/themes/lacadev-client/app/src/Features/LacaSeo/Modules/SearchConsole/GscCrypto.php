<?php

namespace App\Features\LacaSeo\Modules\SearchConsole;

defined('ABSPATH') || exit;

/**
 * Handles AES-256-GCM encryption/decryption for Google Search Console tokens & credentials.
 * Key is derived from AUTH_KEY and SECURE_AUTH_SALT.
 */
class GscCrypto
{
    private const CIPHER = 'aes-256-gcm';

    /**
     * Get 256-bit encryption key derived from WordPress security salts.
     */
    public static function getKey(): string
    {
        $authKey = defined('AUTH_KEY') ? AUTH_KEY : 'default_laca_seo_auth_key';
        $salt    = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'default_laca_seo_secure_salt';

        return hash_hkdf('sha256', $authKey, 32, 'laca_seo_gsc_token_encryption', $salt);
    }

    /**
     * Encrypt an array or string payload into a serialized base64 envelope.
     *
     * @param mixed $data
     * @return string
     */
    public static function encrypt($data): string
    {
        $plaintext = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return base64_encode(json_encode([
            'c' => base64_encode($ciphertext),
            'i' => base64_encode($iv),
            't' => base64_encode($tag),
        ]));
    }

    /**
     * Decrypt the envelope back into original data (array or string).
     *
     * @param string $encryptedString
     * @param bool $asArray
     * @return mixed
     */
    public static function decrypt(string $encryptedString, bool $asArray = true)
    {
        if (empty($encryptedString)) {
            return null;
        }

        $decoded = json_decode(base64_decode($encryptedString), true);
        if (!is_array($decoded) || empty($decoded['c']) || empty($decoded['i']) || empty($decoded['t'])) {
            return null;
        }

        $ciphertext = base64_decode($decoded['c']);
        $iv         = base64_decode($decoded['i']);
        $tag        = base64_decode($decoded['t']);
        $key        = self::getKey();

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            return null;
        }

        if ($asArray) {
            $json = json_decode($decrypted, true);
            return is_array($json) ? $json : $decrypted;
        }

        return $decrypted;
    }
}
