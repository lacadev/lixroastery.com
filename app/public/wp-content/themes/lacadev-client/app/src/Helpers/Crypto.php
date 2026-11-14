<?php

namespace App\Helpers;

/**
 * Tiện ích mã hoá xác thực AEAD (libsodium XChaCha20-Poly1305 / XSalsa20-Poly1305 secretbox)
 * cho dữ liệu nhạy cảm (key dự án, mật khẩu SMTP, 2FA secret, mật khẩu hạ tầng).
 *
 * - Ghi mới luôn dùng định dạng `laca:v2:` + base64(nonce[24] || secretbox(plain, nonce, keyV2)).
 * - Đọc tương thích ngược `laca:v1:` và AES-256-CBC cũ không tag để phục vụ migration.
 * - Không bao giờ mã hoá bằng fallback key ghi cứng; thiếu AUTH_KEY hợp lệ (và không có
 *   LACA_CRYPTO_KEY) sẽ từ chối mã hoá (trả chuỗi rỗng, ghi error_log + admin notice).
 */
class Crypto
{
    public const TAG_V2 = 'laca:v2:';
    public const TAG_V1 = 'laca:v1:';

    private const LEGACY_CIPHER_ALGO      = 'aes-256-cbc';
    private const PLACEHOLDER_AUTH_KEY    = 'put your unique phrase here';
    private const LEGACY_FALLBACK_PHRASE  = 'laca_fallback_secret_key_if_auth_key_missing';
    private const HKDF_INFO_V2            = 'laca-crypto-v2';

    private static bool $missingKeyNoticeRegistered = false;

    /**
     * Dẫn xuất khoá 32 byte cho AEAD v2.
     * Trả null nếu cấu hình thiếu AUTH_KEY hợp lệ và không có LACA_CRYPTO_KEY hợp lệ.
     *
     * Lưu ý thiết kế có chủ đích:
     * 1) Khi khai báo LACA_CRYPTO_KEY (base64 đúng 32 byte), Crypto dùng trực tiếp 32 byte này
     *    làm khoá secretbox chung; trong khi GscCrypto dẫn xuất thêm một lớp HKDF với info riêng
     *    ('laca-gsc-crypto-v2') để tách miền khoá (domain separation) giữa dữ liệu hệ thống và token Google/GSC.
     * 2) Khi dẫn xuất từ AUTH_KEY + SECURE_AUTH_SALT, nếu SECURE_AUTH_SALT chưa định nghĩa hoặc vẫn là
     *    chuỗi placeholder mặc định của WordPress thì chủ đích đặt $salt = '' (không trộn chuỗi công khai
     *    làm entropy giả, đồng thời vẫn cho phép site có AUTH_KEY hợp lệ hoạt động an toàn).
     */
    private static function getKeyV2(): ?string
    {
        if (defined('LACA_CRYPTO_KEY') && is_string(LACA_CRYPTO_KEY) && trim(LACA_CRYPTO_KEY) !== '') {
            $decoded = base64_decode(trim(LACA_CRYPTO_KEY), true);
            if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $decoded;
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
     * Ghi log lỗi cấu hình khoá (không chứa bí mật) và đăng ký thông báo trong wp-admin.
     */
    private static function reportInvalidKeyConfig(string $reason): void
    {
        error_log('[laca-crypto] Refusing to encrypt/decrypt v2 due to key configuration issue: ' . $reason);

        if (self::$missingKeyNoticeRegistered || !function_exists('add_action')) {
            return;
        }

        self::$missingKeyNoticeRegistered = true;
        add_action('admin_notices', static function (): void {
            if (function_exists('current_user_can') && !current_user_can('manage_options')) {
                return;
            }

            $message = function_exists('__')
                ? __('Không thể mã hoá dữ liệu nhạy cảm: thiếu AUTH_KEY hợp lệ hoặc LACA_CRYPTO_KEY (base64 32 byte) trong wp-config.php.', 'laca')
                : 'Missing valid AUTH_KEY or LACA_CRYPTO_KEY in wp-config.php.';
            $escaped = function_exists('esc_html')
                ? esc_html($message)
                : htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            echo '<div class="notice notice-error"><p>' . $escaped . '</p></div>';
        });
    }

    /**
     * Danh sách khoá AES-256-CBC cũ (CHỈ dùng để giải mã dữ liệu legacy khi đọc/migration).
     *
     * @return string[]
     */
    private static function getLegacyCbcKeys(): array
    {
        $keys = [];

        if (
            defined('AUTH_KEY')
            && is_string(AUTH_KEY)
            && AUTH_KEY !== ''
            && AUTH_KEY !== self::PLACEHOLDER_AUTH_KEY
        ) {
            $keys[] = substr(hash('sha256', AUTH_KEY), 0, 32);
        }

        $fallback = substr(hash('sha256', self::LEGACY_FALLBACK_PHRASE), 0, 32);
        if (!in_array($fallback, $keys, true)) {
            $keys[] = $fallback;
        }

        return $keys;
    }

    /**
     * Mã hoá dữ liệu sang định dạng AEAD `laca:v2:`.
     * Trả về chuỗi rỗng nếu đầu vào rỗng hoặc thiếu khoá cấu hình hợp lệ.
     */
    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        $key = self::getKeyV2();
        if ($key === null) {
            return '';
        }

        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plainText, $nonce, $key);

        return self::TAG_V2 . base64_encode($nonce . $cipher);
    }

    /**
     * Mã hoá có gắn prefix tag (`laca:v2:`).
     */
    public static function encryptTagged(string $plainText): string
    {
        return self::encrypt($plainText);
    }

    /**
     * Giải mã dữ liệu (hỗ trợ `laca:v2:`, `laca:v1:` và AES-256-CBC cũ không tag).
     * Trả về chuỗi rỗng `''` khi giải mã thất bại (KHÔNG trả lại ciphertext).
     */
    public static function decrypt(string $encryptedText): string
    {
        if ($encryptedText === '') {
            return '';
        }

        if (str_starts_with($encryptedText, self::TAG_V2)) {
            return self::openV2(substr($encryptedText, strlen(self::TAG_V2))) ?? '';
        }

        if (str_starts_with($encryptedText, self::TAG_V1)) {
            return self::openLegacyCbc(substr($encryptedText, strlen(self::TAG_V1))) ?? '';
        }

        return self::openLegacyCbc($encryptedText) ?? '';
    }

    /**
     * Giải mã chuỗi có prefix tag (`laca:v2:` hoặc `laca:v1:`).
     * Trả về `null` khi không có tag hợp lệ hoặc giải mã thất bại.
     */
    public static function decryptTagged(string $taggedText): ?string
    {
        if ($taggedText === '' || !self::isTagged($taggedText)) {
            return null;
        }

        if (str_starts_with($taggedText, self::TAG_V2)) {
            return self::openV2(substr($taggedText, strlen(self::TAG_V2)));
        }

        if (str_starts_with($taggedText, self::TAG_V1)) {
            return self::openLegacyCbc(substr($taggedText, strlen(self::TAG_V1)));
        }

        return null;
    }

    /**
     * Mở gói AEAD v2 (base64 của nonce[24] || secretbox).
     */
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

    /**
     * Giải mã AES-256-CBC cũ (thử khoá AUTH_KEY hiện tại trước, sau đó khoá fallback cũ).
     */
    private static function openLegacyCbc(string $encodedPayload): ?string
    {
        if ($encodedPayload === '') {
            return null;
        }

        $decoded = base64_decode($encodedPayload, true);
        if ($decoded === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(self::LEGACY_CIPHER_ALGO);
        if ($ivLength <= 0 || strlen($decoded) <= $ivLength) {
            return null;
        }

        $iv        = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);
        if (strlen($encrypted) % 16 !== 0) {
            return null;
        }

        foreach (self::getLegacyCbcKeys() as $legacyKey) {
            $decrypted = openssl_decrypt(
                $encrypted,
                self::LEGACY_CIPHER_ALGO,
                $legacyKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        return null;
    }

    /**
     * Kiểm tra chuỗi có prefix tag (`laca:v2:` hoặc `laca:v1:`) hay không.
     */
    public static function isTagged(string $text): bool
    {
        return str_starts_with($text, self::TAG_V2) || str_starts_with($text, self::TAG_V1);
    }

    /**
     * Kiểm tra chuỗi có phải dữ liệu đã mã hoá hay không (`laca:v2:`, `laca:v1:` hoặc AES-CBC base64 cũ).
     */
    public static function isEncrypted(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        if (self::isTagged($text)) {
            return true;
        }

        if (strlen($text) < 32 || !preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $text)) {
            return false;
        }

        $decoded = base64_decode($text, true);
        if ($decoded === false) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length(self::LEGACY_CIPHER_ALGO);
        return $ivLength > 0
            && strlen($decoded) > $ivLength
            && ((strlen($decoded) - $ivLength) % 16 === 0);
    }

    /**
     * Kiểm tra giá trị đã lưu có cần mã hoá lại sang `laca:v2:` hay không.
     */
    public static function needsRehash(string $stored): bool
    {
        return $stored !== '' && !str_starts_with($stored, self::TAG_V2);
    }
}
