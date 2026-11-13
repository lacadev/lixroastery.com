<?php

namespace App\Settings\LacaTools\Management;

use App\Helpers\Crypto;
use App\Settings\LacaTools\Management\SiteConfig\ConfigRegistry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xuất/Nhập cấu hình theme — Laca Admin → Cấu hình chung → Sao lưu cấu hình.
 *
 * - Xuất JSON các option theme (laca_*, _ prefix Carbon Fields).
 * - Loại bỏ mọi secret (password, token, key, hmac) bằng blocklist + regex.
 * - Nhập: nonce + manage_options, chỉ nhận key nằm trong whitelist.
 *
 * @since 2026-11 (Plan Security Parity)
 */
class ConfigExportImport
{
    const NONCE_ACTION = 'laca_config_export_import';
    const CAP          = 'manage_options';

    /** Regex pattern phát hiện secret trong tên option */
    private const SECRET_PATTERN = '/(pass|secret|token|key|hmac|api_key|client_secret)/i';

    /** Option names cần chặn cứng (luôn loại khỏi export) */
    private const SECRET_BLOCKLIST = [
        '_smtp_password',
        '_recaptcha_secret_key',
        '_discord_bot_token',
        '_discord_webhook_url',
        'laca_2fa_secret',
    ];

    public function register(): void
    {
        add_action('wp_ajax_laca_config_export', [$this, 'handleExport']);
        add_action('wp_ajax_laca_config_import', [$this, 'handleImport']);
    }

    /**
     * AJAX: Xuất cấu hình ra JSON.
     */
    public function handleExport(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
        }
        check_ajax_referer(self::NONCE_ACTION, '_nonce');

        global $wpdb;

        // Lấy tất cả option có prefix laca_ hoặc _
        $options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE 'laca_%'
                OR option_name LIKE '\_%'
             ORDER BY option_name",
            ARRAY_A
        );

        $export = [];
        foreach ($options as $row) {
            $name = $row['option_name'];

            // Bỏ qua WP core internal (transients, cron, etc.)
            if (strpos($name, '_transient_') === 0 || strpos($name, '_site_transient_') === 0) {
                continue;
            }
            if (in_array($name, ['_wp_cron', '_wp_page_for_privacy_policy'], true)) {
                continue;
            }

            // Loại bỏ secrets
            if ($this->isSecretOption($name)) {
                continue;
            }

            // Chặn dứt khoát: chỉ xuất option hợp lệ trong Sổ đăng ký cấu hình
            if (!ConfigRegistry::isExportableOption($name) || ConfigRegistry::isNeverAllowed($name)) {
                continue;
            }

            $value = maybe_unserialize($row['option_value']);
            $export[$name] = $value;
        }

        wp_send_json_success([
            'config'    => $export,
            'site'      => get_bloginfo('url'),
            'theme'     => get_option('template'),
            'exported'  => current_time('mysql'),
            'count'     => count($export),
        ]);
    }

    /**
     * AJAX: Nhập cấu hình từ JSON.
     */
    public function handleImport(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
        }
        check_ajax_referer(self::NONCE_ACTION, '_nonce');

        $json = wp_unslash($_POST['config_json'] ?? '');
        $data = json_decode($json, true);

        if (!is_array($data) || empty($data['config']) || !is_array($data['config'])) {
            wp_send_json_error(esc_html__('Dữ liệu JSON không hợp lệ.', 'laca'));
        }

        $config   = $data['config'];
        $isDryRun = !empty($_POST['dry_run']) && $_POST['dry_run'] !== '0' && $_POST['dry_run'] !== 'false';
        $sentinel = new \stdClass();

        if ($isDryRun) {
            $items  = [];
            $counts = [
                'new'      => 0,
                'changed'  => 0,
                'same'     => 0,
                'rejected' => 0,
            ];

            foreach ($config as $name => $value) {
                $name = (string) $name;

                if (!$this->isAllowedOption($name) || $this->isSecretOption($name)) {
                    $counts['rejected']++;
                    $items[] = [
                        'option' => $name,
                        'status' => 'rejected',
                        'old'    => '—',
                        'new'    => '—',
                    ];
                    continue;
                }

                $current = get_option($name, $sentinel);
                if ($current === $sentinel) {
                    $status  = 'new';
                    $oldSumm = '';
                } elseif ($this->valuesEqual($current, $value)) {
                    $status  = 'same';
                    $oldSumm = $this->summarizeValue($current);
                } else {
                    $status  = 'changed';
                    $oldSumm = $this->summarizeValue($current);
                }

                $counts[$status]++;
                $items[] = [
                    'option' => $name,
                    'status' => $status,
                    'old'    => $oldSumm,
                    'new'    => $this->summarizeValue($value),
                ];
            }

            wp_send_json_success([
                'dry_run' => true,
                'items'   => $items,
                'counts'  => $counts,
            ]);
        }

        $applied = 0;
        $skipped = 0;

        foreach ($config as $name => $value) {
            $name = (string) $name;

            // Validation: chỉ nhận option có prefix hợp lệ
            if (!$this->isAllowedOption($name)) {
                $skipped++;
                continue;
            }

            // Không import secrets
            if ($this->isSecretOption($name)) {
                $skipped++;
                continue;
            }

            $current = get_option($name, $sentinel);
            if ($current !== $sentinel && $this->valuesEqual($current, $value)) {
                continue;
            }

            update_option($name, $value);
            $applied++;
        }

        // Ghi vào nhật ký thay đổi
        ConfigChangeLog::log(
            '_laca_config_import',
            /* translators: %d: number of options */
            sprintf(__('Import %d options', 'laca'), $applied),
            ''
        );

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: applied count, 2: skipped count */
                esc_html__('Đã áp dụng %1$d option, bỏ qua %2$d.', 'laca'),
                $applied,
                $skipped
            ),
            'applied' => $applied,
            'skipped' => $skipped,
        ]);
    }

    /**
     * So sánh giá trị hiện tại và giá trị sẽ nhập.
     */
    private function valuesEqual($current, $incoming): bool
    {
        if ($current === $incoming) {
            return true;
        }
        if (is_scalar($current) && is_scalar($incoming) && !is_bool($current) && !is_bool($incoming)) {
            return (string) $current === (string) $incoming;
        }
        return false;
    }

    /**
     * Tóm tắt giá trị option (tối đa 120 ký tự).
     */
    private function summarizeValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_array($value) || is_object($value)) {
            $json = (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE);
            return mb_strlen($json) > 120 ? mb_substr($json, 0, 117) . '...' : $json;
        }
        $str = (string) $value;
        return mb_strlen($str) > 120 ? mb_substr($str, 0, 117) . '...' : $str;
    }

    /**
     * Kiểm tra option có phải secret không.
     */
    private function isSecretOption(string $name): bool
    {
        if (in_array($name, self::SECRET_BLOCKLIST, true)) {
            return true;
        }
        return (bool) preg_match(self::SECRET_PATTERN, $name);
    }

    /**
     * Kiểm tra option có nằm trong whitelist Sổ đăng ký không.
     */
    private function isAllowedOption(string $name): bool
    {
        if (in_array($name, ['_wp_cron', '_wp_page_for_privacy_policy', ConfigChangeLog::LOG_OPTION], true)) {
            return false;
        }
        if (strpos($name, '_transient_') === 0 || strpos($name, '_site_transient_') === 0) {
            return false;
        }
        // Kiểm tra qua Sổ đăng ký cấu hình chuẩn
        return ConfigRegistry::isExportableOption($name) && !ConfigRegistry::isNeverAllowed($name);
    }
}

