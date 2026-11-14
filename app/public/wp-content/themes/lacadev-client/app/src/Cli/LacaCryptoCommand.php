<?php

namespace App\Cli;

use App\Features\GoogleServices\GscCrypto;
use App\Helpers\Crypto;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LacaCryptoCommand
 *
 * Công cụ WP-CLI di trú dữ liệu mã hoá cũ (AES-256-CBC / laca:v1: / legacy GscCrypto)
 * sang định dạng AEAD v2 (`laca:v2:` / `laca:gsc:v2:`).
 */
class LacaCryptoCommand
{
    private const CRYPTO_TAGGED_OPTIONS = [
        '_smtp_password',
    ];

    private const GSC_OPTIONS = [
        'laca_google_oauth',
        'laca_seo_gsc',
    ];

    private const USERMETA_KEYS = [
        'laca_2fa_secret',
    ];

    private const POSTMETA_KEYS = [
        '_laca_key_tracker',
        '_laca_key_catalog',
        '_laca_key_remote',
        '_laca_key_portal',
        '_domain_password',
        '_hosting_password',
        '_cpanel_password',
        '_ftp_password',
        '_db_password',
    ];

    /**
     * Di trú toàn bộ dữ liệu mã hoá sang AEAD v2.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Chỉ kiểm tra khả năng giải mã và báo cáo số đếm, không ghi vào cơ sở dữ liệu.
     *
     * ## EXAMPLES
     *
     *     wp laca crypto migrate --dry-run
     *     wp laca crypto migrate
     *
     * @subcommand migrate
     */
    public function migrate(array $args, array $assocArgs): void
    {
        global $wpdb;

        $dryRun = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);

        $ProbeCipher = Crypto::encrypt('__laca_crypto_probe__');
        if ($ProbeCipher === '' || Crypto::decrypt($ProbeCipher) !== '__laca_crypto_probe__') {
            \WP_CLI::error(__('Thiếu AUTH_KEY hợp lệ hoặc LACA_CRYPTO_KEY (base64 32 byte) trong wp-config.php — dừng migration.', 'laca'));
        }

        $counts = [
            'migrated'          => 0,
            'already_v2'        => 0,
            'failed'            => 0,
            'plaintext_skipped' => 0,
        ];

        $modeLabel = $dryRun
            ? __('DRY-RUN (không ghi DB)', 'laca')
            : __('APPLY (ghi DB)', 'laca');
        /* translators: %s: chế độ chạy migration (DRY-RUN hoặc APPLY) */
        \WP_CLI::line(sprintf(__('Bắt đầu kiểm tra / di trú Crypto v2 — chế độ: %s', 'laca'), $modeLabel));

        // 1. Options dùng Crypto tagged (_smtp_password)
        foreach (self::CRYPTO_TAGGED_OPTIONS as $optName) {
            $raw = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $optName
            ));
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $label = 'option:' . $optName;
            if (str_starts_with($raw, Crypto::TAG_V2)) {
                $counts['already_v2']++;
                continue;
            }

            if (!Crypto::isTagged($raw)) {
                $counts['plaintext_skipped']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::log(sprintf(__('[plaintext_skipped] %s (chưa gắn tag mã hoá, giữ nguyên)', 'laca'), $label));
                continue;
            }

            $plain = Crypto::decryptTagged($raw);
            if ($plain === null || $plain === '') {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Không giải mã được %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            $v2 = Crypto::encryptTagged($plain);
            if ($v2 === '' || Crypto::decryptTagged($v2) !== $plain) {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Mã hoá lại v2 không khớp cho %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            if ($dryRun) {
                $counts['migrated']++;
                continue;
            }

            $wpdb->update(
                $wpdb->options,
                ['option_value' => $v2],
                ['option_name' => $optName],
                ['%s'],
                ['%s']
            );
            wp_cache_delete($optName, 'options');
            wp_cache_delete('alloptions', 'options');

            $readBack = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $optName
            ));
            if (Crypto::decryptTagged($readBack) !== $plain) {
                $wpdb->update(
                    $wpdb->options,
                    ['option_value' => $raw],
                    ['option_name' => $optName],
                    ['%s'],
                    ['%s']
                );
                wp_cache_delete($optName, 'options');
                wp_cache_delete('alloptions', 'options');
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Đọc lại xác minh thất bại cho %s — đã khôi phục bản gốc.', 'laca'), $label));
                continue;
            }

            $counts['migrated']++;
        }

        // 2. Options dùng GscCrypto nguyên chuỗi (laca_google_oauth, laca_seo_gsc)
        foreach (self::GSC_OPTIONS as $optName) {
            $raw = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $optName
            ));
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $label = 'option:' . $optName;
            if (str_starts_with($raw, GscCrypto::TAG_V2)) {
                $counts['already_v2']++;
                continue;
            }

            if (!GscCrypto::isEncrypted($raw)) {
                $counts['plaintext_skipped']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::log(sprintf(__('[plaintext_skipped] %s (không phải envelope GscCrypto, giữ nguyên)', 'laca'), $label));
                continue;
            }

            $plain = GscCrypto::decrypt($raw, false);
            if (!is_string($plain) || $plain === '') {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Không giải mã được %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            $v2 = GscCrypto::encrypt($plain);
            if ($v2 === '' || GscCrypto::decrypt($v2, false) !== $plain) {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Mã hoá lại v2 không khớp cho %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            if ($dryRun) {
                $counts['migrated']++;
                continue;
            }

            $wpdb->update(
                $wpdb->options,
                ['option_value' => $v2],
                ['option_name' => $optName],
                ['%s'],
                ['%s']
            );
            wp_cache_delete($optName, 'options');
            wp_cache_delete('alloptions', 'options');

            $readBack = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $optName
            ));
            if (GscCrypto::decrypt($readBack, false) !== $plain) {
                $wpdb->update(
                    $wpdb->options,
                    ['option_value' => $raw],
                    ['option_name' => $optName],
                    ['%s'],
                    ['%s']
                );
                wp_cache_delete($optName, 'options');
                wp_cache_delete('alloptions', 'options');
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Đọc lại xác minh thất bại cho %s — đã khôi phục bản gốc.', 'laca'), $label));
                continue;
            }

            $counts['migrated']++;
        }

        // 3. Option mảng laca_google_services['pagespeed_api_key']
        $googleOpts = get_option('laca_google_services', null);
        if (is_array($googleOpts) && isset($googleOpts['pagespeed_api_key']) && is_string($googleOpts['pagespeed_api_key']) && $googleOpts['pagespeed_api_key'] !== '') {
            $raw   = $googleOpts['pagespeed_api_key'];
            $label = 'option:laca_google_services[pagespeed_api_key]';

            if (str_starts_with($raw, GscCrypto::TAG_V2)) {
                $counts['already_v2']++;
            } elseif (!GscCrypto::isEncrypted($raw)) {
                $counts['plaintext_skipped']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::log(sprintf(__('[plaintext_skipped] %s (không phải envelope GscCrypto, giữ nguyên)', 'laca'), $label));
            } else {
                $plain = GscCrypto::decrypt($raw, false);
                if (!is_string($plain) || $plain === '') {
                    $counts['failed']++;
                    /* translators: %s: định danh trường dữ liệu */
                    \WP_CLI::warning(sprintf(__('[failed] Không giải mã được %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                } else {
                    $v2 = GscCrypto::encrypt($plain);
                    if ($v2 === '' || GscCrypto::decrypt($v2, false) !== $plain) {
                        $counts['failed']++;
                        /* translators: %s: định danh trường dữ liệu */
                        \WP_CLI::warning(sprintf(__('[failed] Mã hoá lại v2 không khớp cho %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                    } elseif ($dryRun) {
                        $counts['migrated']++;
                    } else {
                        $updatedOpts                      = $googleOpts;
                        $updatedOpts['pagespeed_api_key'] = $v2;
                        update_option('laca_google_services', $updatedOpts, false);
                        wp_cache_delete('laca_google_services', 'options');

                        $verifyOpts = get_option('laca_google_services', []);
                        $readBack   = is_array($verifyOpts) ? (string) ($verifyOpts['pagespeed_api_key'] ?? '') : '';
                        if (GscCrypto::decrypt($readBack, false) !== $plain) {
                            update_option('laca_google_services', $googleOpts, false);
                            $counts['failed']++;
                            /* translators: %s: định danh trường dữ liệu */
                            \WP_CLI::warning(sprintf(__('[failed] Đọc lại xác minh thất bại cho %s — đã khôi phục bản gốc.', 'laca'), $label));
                        } else {
                            $counts['migrated']++;
                        }
                    }
                }
            }
        }

        // 4. Usermeta (laca_2fa_secret)
        $umetaPlaceholders = implode(',', array_fill(0, count(self::USERMETA_KEYS), '%s'));
        $umetaRows = $wpdb->get_results($wpdb->prepare(
            "SELECT umeta_id, user_id, meta_key, meta_value
             FROM {$wpdb->usermeta}
             WHERE meta_key IN ({$umetaPlaceholders}) AND meta_value != ''
             ORDER BY umeta_id DESC",
            ...self::USERMETA_KEYS
        )) ?: [];

        foreach ($umetaRows as $row) {
            $raw   = (string) $row->meta_value;
            $label = sprintf('usermeta:%s (user_id=%d, umeta_id=%d)', $row->meta_key, (int) $row->user_id, (int) $row->umeta_id);

            if (str_starts_with($raw, Crypto::TAG_V2)) {
                $counts['already_v2']++;
                continue;
            }

            if (!Crypto::isTagged($raw)) {
                $counts['plaintext_skipped']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::log(sprintf(__('[plaintext_skipped] %s (chưa gắn tag mã hoá, giữ nguyên)', 'laca'), $label));
                continue;
            }

            $plain = Crypto::decryptTagged($raw);
            if ($plain === null || $plain === '') {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Không giải mã được %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            $v2 = Crypto::encryptTagged($plain);
            if ($v2 === '' || Crypto::decryptTagged($v2) !== $plain) {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Mã hoá lại v2 không khớp cho %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            if ($dryRun) {
                $counts['migrated']++;
                continue;
            }

            $wpdb->update(
                $wpdb->usermeta,
                ['meta_value' => $v2],
                ['umeta_id' => (int) $row->umeta_id],
                ['%s'],
                ['%d']
            );
            wp_cache_delete((int) $row->user_id, 'user_meta');

            $readBack = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE umeta_id = %d LIMIT 1",
                (int) $row->umeta_id
            ));
            if (Crypto::decryptTagged($readBack) !== $plain) {
                $wpdb->update(
                    $wpdb->usermeta,
                    ['meta_value' => $raw],
                    ['umeta_id' => (int) $row->umeta_id],
                    ['%s'],
                    ['%d']
                );
                wp_cache_delete((int) $row->user_id, 'user_meta');
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Đọc lại xác minh thất bại cho %s — đã khôi phục bản gốc.', 'laca'), $label));
                continue;
            }

            $counts['migrated']++;
        }

        // 5. Postmeta (_laca_key_*, _domain_password, _hosting_password, _cpanel_password, _ftp_password, _db_password)
        $pmetaPlaceholders = implode(',', array_fill(0, count(self::POSTMETA_KEYS), '%s'));
        $pmetaRows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id, post_id, meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key IN ({$pmetaPlaceholders}) AND meta_value != ''
             ORDER BY meta_id DESC",
            ...self::POSTMETA_KEYS
        )) ?: [];

        foreach ($pmetaRows as $row) {
            $raw   = (string) $row->meta_value;
            $label = sprintf('postmeta:%s (post_id=%d, meta_id=%d)', $row->meta_key, (int) $row->post_id, (int) $row->meta_id);

            if (str_starts_with($raw, Crypto::TAG_V2)) {
                $counts['already_v2']++;
                continue;
            }

            if (!Crypto::isEncrypted($raw)) {
                $counts['plaintext_skipped']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::log(sprintf(__('[plaintext_skipped] %s (chưa mã hoá, giữ nguyên)', 'laca'), $label));
                continue;
            }

            $plain = Crypto::decrypt($raw);
            if ($plain === '') {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Không giải mã được %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            $v2 = Crypto::encrypt($plain);
            if ($v2 === '' || Crypto::decrypt($v2) !== $plain) {
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Mã hoá lại v2 không khớp cho %s — giữ nguyên giá trị cũ.', 'laca'), $label));
                continue;
            }

            if ($dryRun) {
                $counts['migrated']++;
                continue;
            }

            $wpdb->update(
                $wpdb->postmeta,
                ['meta_value' => $v2],
                ['meta_id' => (int) $row->meta_id],
                ['%s'],
                ['%d']
            );
            wp_cache_delete((int) $row->post_id, 'post_meta');

            $readBack = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d LIMIT 1",
                (int) $row->meta_id
            ));
            if (Crypto::decrypt($readBack) !== $plain) {
                $wpdb->update(
                    $wpdb->postmeta,
                    ['meta_value' => $raw],
                    ['meta_id' => (int) $row->meta_id],
                    ['%s'],
                    ['%d']
                );
                wp_cache_delete((int) $row->post_id, 'post_meta');
                $counts['failed']++;
                /* translators: %s: định danh trường dữ liệu */
                \WP_CLI::warning(sprintf(__('[failed] Đọc lại xác minh thất bại cho %s — đã khôi phục bản gốc.', 'laca'), $label));
                continue;
            }

            $counts['migrated']++;
        }

        \WP_CLI::line(sprintf(
            'migrated=%d already_v2=%d failed=%d plaintext_skipped=%d',
            $counts['migrated'],
            $counts['already_v2'],
            $counts['failed'],
            $counts['plaintext_skipped']
        ));

        if ($counts['failed'] > 0) {
            /* translators: %d: số bản ghi lỗi không thể di trú */
            \WP_CLI::error(sprintf(__('Migration hoàn tất với %d trường giải mã thất bại (đã giữ nguyên dữ liệu gốc).', 'laca'), $counts['failed']));
        }

        if ($dryRun) {
            \WP_CLI::success(__('Dry-run hoàn tất — không có thay đổi nào được ghi vào DB.', 'laca'));
        } else {
            \WP_CLI::success(__('Di trú Crypto v2 hoàn tất.', 'laca'));
        }
    }
}
