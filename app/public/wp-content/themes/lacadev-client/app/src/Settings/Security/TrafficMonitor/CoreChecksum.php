<?php

namespace App\Settings\Security\TrafficMonitor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Đối chiếu MD5 checksum các file WordPress Core với `api.wordpress.org`
 * và phát hiện file PHP lạ trong `wp-admin/` và `wp-includes/` (tối đa 500 file).
 */
final class CoreChecksum
{
    public const TRANSIENT_KEY = 'laca_core_checksum_result';

    /**
     * @return array{
     *   checked:int,
     *   modified:string[],
     *   missing:string[],
     *   unknown:string[],
     *   error:string,
     *   scanned_at:string
     * }
     */
    public static function scan(bool $force = false): array
    {
        if (!$force) {
            $cached = get_transient(self::TRANSIENT_KEY);
            if (is_array($cached) && isset($cached['checked'])) {
                return $cached;
            }
        }

        global $wp_version, $wp_local_package;
        $locale = (isset($wp_local_package) && is_string($wp_local_package) && $wp_local_package !== '')
            ? $wp_local_package
            : get_locale();

        $checksums = self::fetchChecksums((string) $wp_version, $locale);
        if (is_wp_error($checksums) && $locale !== 'en_US') {
            $fallback = self::fetchChecksums((string) $wp_version, 'en_US');
            if (!is_wp_error($fallback)) {
                $checksums = $fallback;
            }
        }

        if (is_wp_error($checksums)) {
            return [
                'checked'    => 0,
                'modified'   => [],
                'missing'    => [],
                'unknown'    => [],
                'error'      => $checksums->get_error_message(),
                'scanned_at' => current_time('mysql'),
            ];
        }

        $out = [
            'checked'    => 0,
            'modified'   => [],
            'missing'    => [],
            'unknown'    => [],
            'error'      => '',
            'scanned_at' => current_time('mysql'),
        ];

        foreach ($checksums as $relative => $expectedHash) {
            if (
                !is_string($relative)
                || !is_string($expectedHash)
                || str_contains($relative, '..')
                || str_contains($relative, '\\')
                || str_starts_with($relative, '/')
                || str_starts_with($relative, 'wp-content/')
            ) {
                continue;
            }

            $path = ABSPATH . $relative;
            if (!is_file($path)) {
                $out['missing'][] = $relative;
                continue;
            }

            if (is_link($path) || !is_readable($path)) {
                $out['modified'][] = $relative;
                continue;
            }

            $actual = @md5_file($path);
            $out['checked']++;
            if (!is_string($actual) || !hash_equals(strtolower($expectedHash), strtolower($actual))) {
                $out['modified'][] = $relative;
            }
        }

        foreach (['wp-admin', 'wp-includes'] as $dir) {
            $root = ABSPATH . $dir;
            if (!is_dir($root)) {
                continue;
            }

            try {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iter as $file) {
                    if ($file->isLink() || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                        continue;
                    }
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(ABSPATH)));
                    if (!isset($checksums[$relative])) {
                        $out['unknown'][] = $relative;
                        if (count($out['unknown']) >= 500) {
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                $out['error'] = sprintf(
                    /* translators: %s: thông báo lỗi hệ thống tệp */
                    __('Không thể đọc đầy đủ thư mục Core: %s', 'laca'),
                    $e->getMessage()
                );
                return $out;
            }
        }

        set_transient(self::TRANSIENT_KEY, $out, 15 * MINUTE_IN_SECONDS);

        return $out;
    }

    /**
     * @return array<string, string>|\WP_Error
     */
    private static function fetchChecksums(string $version, string $locale): array|\WP_Error
    {
        $url = add_query_arg(
            [
                'version' => $version,
                'locale'  => $locale,
            ],
            'https://api.wordpress.org/core/checksums/1.0/'
        );

        $response = wp_safe_remote_get($url, [
            'timeout'     => 18,
            'redirection' => 2,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error(
                'checksum_http_error',
                sprintf(
                    /* translators: %s: chi tiết lỗi kết nối */
                    __('Không thể kết nối máy chủ checksum WordPress.org: %s', 'laca'),
                    $response->get_error_message()
                )
            );
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error(
                'checksum_http_status',
                __('Máy chủ checksum WordPress.org không phản hồi thành công.', 'laca')
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['checksums']) || !is_array($body['checksums'])) {
            return new \WP_Error(
                'checksum_not_found',
                sprintf(
                    /* translators: 1: phiên bản WordPress, 2: mã ngôn ngữ */
                    __('Không tìm thấy bảng checksum chính thức cho WordPress %1$s (%2$s).', 'laca'),
                    $version,
                    $locale
                )
            );
        }

        return $body['checksums'];
    }
}
