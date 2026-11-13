<?php

namespace App\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xác minh chữ ký Ed25519 của manifest phát hành và SHA-256 của gói zip Parent Theme.
 */
class ReleaseVerifier
{
    /**
     * Danh sách khoá công khai Ed25519 được ghim cứng (key_id => base64 public key 32-byte).
     *
     * @var array<string, string>
     */
    public const PUBLIC_KEYS = [
        '32cd5560' => 'MHKaagma33vl5OHMbkRGWfJjxk2C6KEmDyF8GgfqVls=',
    ];

    /**
     * Lấy danh sách public key hợp lệ.
     * Filter `laca_release_public_keys` chỉ được phép THÊM khoá mới, không được ghi đè khoá ghim cứng.
     *
     * @return array<string, string>
     */
    public static function getPublicKeys(): array
    {
        $extra = apply_filters('laca_release_public_keys', []);
        if (!is_array($extra)) {
            return self::PUBLIC_KEYS;
        }

        $cleanExtra = [];
        foreach ($extra as $keyId => $pubKeyB64) {
            if (is_string($keyId) && $keyId !== '' && is_string($pubKeyB64) && $pubKeyB64 !== '') {
                $cleanExtra[$keyId] = $pubKeyB64;
            }
        }

        // Toán tử + giữ nguyên phần tử vế trái (PUBLIC_KEYS không thể bị ghi đè).
        return self::PUBLIC_KEYS + $cleanExtra;
    }

    /**
     * Tạo chuỗi chuẩn hoá (canonical string) để ký / xác minh chữ ký Ed25519.
     * Phải khớp tuyệt đối với tools/release/sign-manifest.php.
     */
    public static function canonicalString(object $m): string
    {
        $slug         = (string) ($m->slug ?? '');
        $version      = (string) ($m->version ?? '');
        $sha256       = strtolower((string) ($m->sha256 ?? ''));
        $size         = (string) ((int) ($m->size ?? 0));
        $downloadUrl  = (string) ($m->download_url ?? '');
        $requires     = (string) ($m->requires ?? '');
        $requiresPhp  = (string) ($m->requires_php ?? '');
        $childRebuild = !empty($m->child_rebuild_recommended) ? '1' : '0';

        return implode("\n", [
            'laca-release-v1',
            $slug,
            $version,
            $sha256,
            $size,
            $downloadUrl,
            $requires,
            $requiresPhp,
            $childRebuild,
        ]);
    }

    /**
     * Xác minh toàn vẹn và chữ ký Ed25519 của manifest schema >= 2.
     */
    public static function verifyManifest(object $m): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $schema = isset($m->schema) ? (int) $m->schema : 0;
        if ($schema < 2) {
            return false;
        }

        $requiredStrings = ['slug', 'version', 'download_url', 'sha256', 'requires', 'requires_php', 'key_id', 'signature'];
        foreach ($requiredStrings as $field) {
            if (!isset($m->{$field}) || !is_string($m->{$field}) || trim($m->{$field}) === '') {
                return false;
            }
        }

        if (!isset($m->size) || (int) $m->size <= 0) {
            return false;
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $m->sha256)) {
            return false;
        }

        $keys  = self::getPublicKeys();
        $keyId = (string) $m->key_id;
        if (!isset($keys[$keyId])) {
            return false;
        }

        $publicKey = base64_decode($keys[$keyId], true);
        $signature = base64_decode((string) $m->signature, true);

        if ($publicKey === false || $signature === false) {
            return false;
        }

        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $canonical = self::canonicalString($m);

        try {
            return sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey);
        } catch (\SodiumException|\Throwable $e) {
            return false;
        }
    }

    /**
     * Xác minh SHA-256 của file gói zip tải về so với sha256 trong manifest đã ký.
     */
    public static function verifyFile(string $path, string $sha256): bool
    {
        $expected = strtolower(trim($sha256));
        if ($expected === '' || !preg_match('/^[a-f0-9]{64}$/', $expected)) {
            return false;
        }

        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $actual = hash_file('sha256', $path);
        if ($actual === false) {
            return false;
        }

        return hash_equals($actual, $expected);
    }
}
