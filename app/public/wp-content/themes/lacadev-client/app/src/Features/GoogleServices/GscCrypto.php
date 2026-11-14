<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Mã hoá xác thực AEAD cho token & API key của Google Services (Search Console, Analytics, PageSpeed).
 *
 * - Ghi mới bằng libsodium secretbox với tiền tố `laca:gsc:v2:`.
 * - Đọc tương thích ngược định dạng JSON AES-256-GCM cũ (thử salt hiện tại trước, fallback cũ sau).
 * - Không bao giờ mã hoá bằng fallback key ghi cứng khi thiếu AUTH_KEY hợp lệ và LACA_CRYPTO_KEY.
 */
class GscCrypto
{
    public const TAG_V2 = 'laca:gsc:v2:';

    private const LEGACY_CIPHER        = 'aes-256-gcm';
    private const PLACEHOLDER_AUTH_KEY = 'put your unique phrase here';
    private const HKDF_INFO_V2         = 'laca-gsc-crypto-v2';

    private static bool $missingKeyNoticeRegistered = false;

    /**
     * Dẫn xuất khoá 32 byte cho AEAD v2.
     *
     * Lưu ý thiết kế có chủ đích:
     * 1) Kể cả khi dùng hằng LACA_CRYPTO_KEY (base64 32 byte), GscCrypto vẫn qua hash_hkdf() với
     *    HKDF_INFO_V2 ('laca-gsc-crypto-v2') để tách miền khoá (domain separation) độc lập hoàn toàn
     *    với App\Helpers\Crypto (vốn dùng trực tiếp 32 byte gốc của LACA_CRYPTO_KEY).
     * 2) Khi dẫn xuất từ AUTH_KEY + SECURE_AUTH_SALT, nếu SECURE_AUTH_SALT thiếu hoặc đang mang giá trị
     *    placeholder mặc định của WordPress thì chủ đích gán $salt = '' tương tự App\Helpers\Crypto.
     */
    private static function getKeyV2(): ?string
    {
        if (defined('LACA_CRYPTO_KEY') && is_string(LACA_CRYPTO_KEY) && trim(LACA_CRYPTO_KEY) !== '') {
            $decoded = base64_decode(trim(LACA_CRYPTO_KEY), true);
            if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return hash_hkdf('sha256', $decoded, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::HKDF_INFO_V2);
            }

            self::reportInvalidKeyConfig('invalid_laca_crypto_key');
            return null;
        }

        if (
            !defined('AUTH_KEY')
            || !is_string(AUTH_KEY)
            || trim(AUTH_KEY) === ''
            || AUTH_KEY === self::PLACEHOLDER_AUTH_KEY
        ) {
            self::reportInvalidKeyConfig('missing_auth_key');
            return null;
        }

        $salt = (defined('SECURE_AUTH_SALT') && is_string(SECURE_AUTH_SALT) && SECURE_AUTH_SALT !== self::PLACEHOLDER_AUTH_KEY)
            ? SECURE_AUTH_SALT
            : '';

        return hash_hkdf(
            'sha256',
            AUTH_KEY . $salt,
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            self::HKDF_INFO_V2
        );
    }

    /**
     * Ghi log và thông báo admin khi thiếu khoá cấu hình hợp lệ.
     */
    private static function reportInvalidKeyConfig(string $reason): void
    {
        error_log('[laca-gsc-crypto] Refusing to encrypt/decrypt v2 due to key configuration issue: ' . $reason);

        if (self::$missingKeyNoticeRegistered || !function_exists('add_action')) {
            return;
        }

        self::$missingKeyNoticeRegistered = true;
        add_action('admin_notices', static function (): void {
            if (function_exists('current_user_can') && !current_user_can('manage_options')) {
                return;
            }

            $message = function_exists('__')
                ? __('Không thể mã hoá thông tin kết nối Google Services: thiếu AUTH_KEY hợp lệ hoặc LACA_CRYPTO_KEY (base64 32 byte) trong wp-config.php.', 'laca')
                : 'Missing valid AUTH_KEY or LACA_CRYPTO_KEY in wp-config.php.';
            $escaped = function_exists('esc_html')
                ? esc_html($message)
                : htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            echo '<div class="notice notice-error"><p>' . $escaped . '</p></div>';
        });
    }

    /**
     * Danh sách khoá AES-256-GCM cũ (CHỈ dùng cho nhánh giải mã legacy).
     *
     * @return string[]
     */
    private static function getLegacyKeys(): array
    {
        $candidates = [];

        $hasAuth = defined('AUTH_KEY') && is_string(AUTH_KEY) && AUTH_KEY !== '';
        $hasSalt = defined('SECURE_AUTH_SALT') && is_string(SECURE_AUTH_SALT) && SECURE_AUTH_SALT !== '';

        if ($hasAuth && $hasSalt) {
            $candidates[] = hash_hkdf('sha256', AUTH_KEY, 32, 'laca_seo_gsc_token_encryption', SECURE_AUTH_SALT);
        }

        $mixedAuth = $hasAuth ? AUTH_KEY : 'default_laca_seo_auth_key';
        $mixedSalt = $hasSalt ? SECURE_AUTH_SALT : 'default_laca_seo_secure_salt';
        $mixedKey  = hash_hkdf('sha256', $mixedAuth, 32, 'laca_seo_gsc_token_encryption', $mixedSalt);
        if (!in_array($mixedKey, $candidates, true)) {
            $candidates[] = $mixedKey;
        }

        $pureFallback = hash_hkdf(
            'sha256',
            'default_laca_seo_auth_key',
            32,
            'laca_seo_gsc_token_encryption',
            'default_laca_seo_secure_salt'
        );
        if (!in_array($pureFallback, $candidates, true)) {
            $candidates[] = $pureFallback;
        }

        return $candidates;
    }

    /**
     * Encrypt an array or string payload into an AEAD v2 envelope (`laca:gsc:v2:...`).
     *
     * @param mixed $data
     * @return string
     */
    public static function encrypt($data): string
    {
        $plaintext = is_string($data)
            ? $data
            : (function_exists('wp_json_encode')
                ? wp_json_encode($data, JSON_UNESCAPED_UNICODE)
                : json_encode($data, JSON_UNESCAPED_UNICODE));

        if (!is_string($plaintext) || $plaintext === '') {
            return '';
        }

        $key = self::getKeyV2();
        if ($key === null) {
            return '';
        }

        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return self::TAG_V2 . base64_encode($nonce . $cipher);
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
        if ($encryptedString === '') {
            return null;
        }

        if (str_starts_with($encryptedString, self::TAG_V2)) {
            $decrypted = self::openV2(substr($encryptedString, strlen(self::TAG_V2)));
        } else {
            $decrypted = self::openLegacyGcm($encryptedString);
        }

        if ($decrypted === null) {
            return null;
        }

        if ($asArray) {
            $json = json_decode($decrypted, true);
            return is_array($json) ? $json : $decrypted;
        }

        return $decrypted;
    }

    private static function openV2(string $encodedPayload): ?string
    {
        if ($encodedPayload === '') {
            return null;
        }

        $decoded = base64_decode($encodedPayload, true);
        $minLen  = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
        if ($decoded === false || strlen($decoded) < $minLen) {
            return null;
        }

        $key = self::getKeyV2();
        if ($key === null) {
            return null;
        }

        $nonce  = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        return $plain === false ? null : $plain;
    }

    private static function openLegacyGcm(string $encryptedString): ?string
    {
        $rawEnvelope = base64_decode($encryptedString, true);
        if ($rawEnvelope === false) {
            return null;
        }

        $decoded = json_decode($rawEnvelope, true);
        if (!is_array($decoded) || empty($decoded['c']) || empty($decoded['i']) || empty($decoded['t'])) {
            return null;
        }

        $ciphertext = base64_decode((string) $decoded['c'], true);
        $iv         = base64_decode((string) $decoded['i'], true);
        $tag        = base64_decode((string) $decoded['t'], true);
        if ($ciphertext === false || $iv === false || $tag === false) {
            return null;
        }

        foreach (self::getLegacyKeys() as $legacyKey) {
            $decrypted = openssl_decrypt(
                $ciphertext,
                self::LEGACY_CIPHER,
                $legacyKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        return null;
    }

    /**
     * Kiểm tra chuỗi lưu trữ có phải envelope của GscCrypto (v2 hoặc legacy GCM) hay không.
     */
    public static function isEncrypted(string $stored): bool
    {
        if ($stored === '') {
            return false;
        }

        if (str_starts_with($stored, self::TAG_V2)) {
            return true;
        }

        $rawEnvelope = base64_decode($stored, true);
        if ($rawEnvelope === false) {
            return false;
        }

        $decoded = json_decode($rawEnvelope, true);
        return is_array($decoded) && !empty($decoded['c']) && !empty($decoded['i']) && !empty($decoded['t']);
    }

    /**
     * Kiểm tra chuỗi đã lưu có cần mã hoá lại sang `laca:gsc:v2:` hay không.
     */
    public static function needsRehash(string $stored): bool
    {
        return $stored !== '' && !str_starts_with($stored, self::TAG_V2);
    }
}
