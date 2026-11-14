<?php

namespace App\PostTypes\Concerns;

use App\Helpers\Crypto;

/**
 * Trait HasEncryption
 *
 * Mã hóa / giải mã các field mật khẩu khi lưu/load qua Carbon Fields (AEAD v2 + lazy rehash).
 * Used by: App\PostTypes\Project
 */
trait HasEncryption
{
    /** @var string[] Danh sách meta key cần mã hóa (CF prefix _) */
    private array $encryptedFields = [
        '_domain_password',
        '_hosting_password',
        '_cpanel_password',
        '_ftp_password',
        '_db_password',
    ];

    // -------------------------------------------------------------------------

    public function encryptPasswordsOnSave($value, $name, $id, $type)
    {
        if (!in_array($name, $this->encryptedFields, true) || !is_string($value) || $value === '') {
            return $value;
        }

        if (!Crypto::isEncrypted($value)) {
            return Crypto::encrypt($value);
        }

        if (Crypto::needsRehash($value)) {
            $plain = Crypto::decrypt($value);
            if ($plain !== '') {
                $rehashed = Crypto::encrypt($plain);
                if ($rehashed !== '') {
                    return $rehashed;
                }
            }
        }

        return $value;
    }

    public function decryptPasswordsOnLoad($value, $name, $id, $type)
    {
        if (!is_string($value) || $value === '' || !in_array($name, $this->encryptedFields, true)) {
            return $value;
        }

        if (Crypto::isEncrypted($value)) {
            $decrypted = Crypto::decrypt($value);
            if ($decrypted !== '' && Crypto::needsRehash($value) && is_numeric($id) && (int) $id > 0) {
                $rehashed = Crypto::encrypt($decrypted);
                if ($rehashed !== '') {
                    update_post_meta((int) $id, $name, $rehashed);
                }
            }

            return $decrypted;
        }

        return $value;
    }
}
