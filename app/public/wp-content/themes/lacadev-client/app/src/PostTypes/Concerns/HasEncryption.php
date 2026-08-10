<?php

namespace App\PostTypes\Concerns;

/**
 * Trait HasEncryption
 *
 * Mã hóa / giải mã các field mật khẩu khi lưu/load qua Carbon Fields.
 * Used by: App\PostTypes\Project
 */
trait HasEncryption
{
    /** @var string[] Danh sách meta key cần mã hóa (CF prefix _) */
    private array $encryptedFields = [
        '_domain_password',
        '_hosting_password',
        '_ftp_password',
        '_db_password',
    ];

    // -------------------------------------------------------------------------

    public function encryptPasswordsOnSave($value, $name, $id, $type)
    {
        if (in_array($name, $this->encryptedFields, true) && !empty($value)) {
            if (!\App\Helpers\Crypto::isEncrypted($value)) {
                return \App\Helpers\Crypto::encrypt($value);
            }
        }
        return $value;
    }

    public function decryptPasswordsOnLoad($value, $name, $id, $type)
    {
        if (empty($value)) {
            return $value;
        }

        if (in_array($name, $this->encryptedFields, true) && \App\Helpers\Crypto::isEncrypted($value)) {
            return \App\Helpers\Crypto::decrypt($value);
        }

        return $value;
    }
}
