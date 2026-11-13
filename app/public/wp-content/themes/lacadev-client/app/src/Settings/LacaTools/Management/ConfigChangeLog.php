<?php

namespace App\Settings\LacaTools\Management;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nhật ký thay đổi cấu hình theme — vòng 200 mục, autoload = false.
 *
 * Hook `updated_option`/`added_option` cho các option thuộc theme (laca_*, _* Carbon Fields).
 * Ghi user, IP, thời gian, tên option. Giá trị cũ/mới chỉ ghi khi KHÔNG phải secret.
 *
 * @since 2026-11 (Plan Security Parity)
 */
class ConfigChangeLog
{
    /** Option key lưu nhật ký */
    const LOG_OPTION = 'laca_config_change_log';

    /** Số mục tối đa (ring buffer) */
    const MAX_ENTRIES = 200;

    /** Regex pattern phát hiện secret */
    private const SECRET_PATTERN = '/(pass|secret|token|key|hmac|api_key|client_secret)/i';

    /** Option trạng thái chạy nền (cron/queue/log/version) — không phải cấu hình, bỏ qua */
    private const RUNTIME_PATTERN = '/(_log$|_log_|queue|last_run|baseline|_version$|flushed|migrated|_sent_|_cleaned$|_installed$)/';

    /** Chống ghi lồng nhau khi update_option(LOG_OPTION) bắn lại updated_option */
    private static bool $writing = false;

    /** AJAX nonce action */
    const NONCE_ACTION = 'laca_config_changelog';

    public function register(): void
    {
        add_action('updated_option', [$this, 'onOptionUpdated'], 10, 3);
        add_action('added_option', [$this, 'onOptionAdded'], 10, 2);

        // AJAX: Xoá nhật ký
        add_action('wp_ajax_laca_config_changelog_clear', [$this, 'ajaxClear']);
    }

    /**
     * Hook `updated_option` — ghi nhật ký khi option thay đổi.
     */
    public function onOptionUpdated(string $option, $oldValue, $newValue): void
    {
        if (!$this->isTrackedOption($option)) {
            return;
        }

        // Không ghi khi giá trị không đổi
        if ($oldValue === $newValue) {
            return;
        }

        $isSecret = $this->isSecretOption($option);

        self::log(
            $option,
            $isSecret ? '***' : $this->summarizeValue($oldValue),
            $isSecret ? '***' : $this->summarizeValue($newValue)
        );
    }

    /**
     * Hook `added_option` — ghi nhật ký khi option mới được tạo.
     */
    public function onOptionAdded(string $option, $value): void
    {
        if (!$this->isTrackedOption($option)) {
            return;
        }

        $isSecret = $this->isSecretOption($option);

        self::log(
            $option,
            '',
            $isSecret ? '***' : $this->summarizeValue($value)
        );
    }

    /**
     * Ghi 1 mục vào nhật ký (ring buffer).
     */
    public static function log(string $option, string $oldSummary, string $newSummary): void
    {
        if (self::$writing) {
            return;
        }
        $log = get_option(self::LOG_OPTION, []);
        if (!is_array($log)) {
            $log = [];
        }

        $entry = [
            'time'   => current_time('mysql'),
            'user'   => function_exists('get_current_user_id') ? get_current_user_id() : 0,
            'ip'     => class_exists('\App\Helpers\ClientIp') ? \App\Helpers\ClientIp::get() : ($_SERVER['REMOTE_ADDR'] ?? 'cli'),
            'option' => $option,
            'old'    => $oldSummary,
            'new'    => $newSummary,
        ];

        // Thêm vào đầu mảng
        array_unshift($log, $entry);

        // Giới hạn ring buffer
        if (count($log) > self::MAX_ENTRIES) {
            $log = array_slice($log, 0, self::MAX_ENTRIES);
        }

        self::$writing = true;
        update_option(self::LOG_OPTION, $log, false); // autoload = false
        self::$writing = false;
    }

    /**
     * Lấy nhật ký.
     *
     * @return array
     */
    public static function getLog(): array
    {
        $log = get_option(self::LOG_OPTION, []);
        return is_array($log) ? $log : [];
    }

    /**
     * AJAX: Xoá nhật ký.
     */
    public function ajaxClear(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
        }
        check_ajax_referer(self::NONCE_ACTION, '_nonce');

        delete_option(self::LOG_OPTION);
        wp_send_json_success([
            'message' => esc_html__('Đã xoá nhật ký thay đổi cấu hình.', 'laca'),
        ]);
    }

    /**
     * Kiểm tra option có thuộc theme không.
     */
    private function isTrackedOption(string $name): bool
    {
        if ($name === self::LOG_OPTION || preg_match(self::RUNTIME_PATTERN, $name)) {
            return false;
        }
        // Chỉ ghi thay đổi do admin thực hiện — bỏ qua cron/frontend/request ẩn danh
        if (wp_doing_cron() || !function_exists('current_user_can') || !current_user_can('manage_options')) {
            return false;
        }
        // laca_* hoặc _ prefix (Carbon Fields theme options)
        if (strpos($name, 'laca_') === 0) {
            return true;
        }
        if (strpos($name, '_') === 0 && strpos($name, '_transient_') !== 0 && strpos($name, '_site_transient_') !== 0) {
            return true;
        }
        return false;
    }

    /**
     * Kiểm tra option có phải secret.
     */
    private function isSecretOption(string $name): bool
    {
        return (bool) preg_match(self::SECRET_PATTERN, $name);
    }

    /**
     * Tóm tắt giá trị option để ghi nhật ký (giới hạn 200 ký tự).
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
            $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
            return mb_strlen($json) > 200 ? mb_substr($json, 0, 197) . '...' : $json;
        }
        $str = (string) $value;
        return mb_strlen($str) > 200 ? mb_substr($str, 0, 197) . '...' : $str;
    }
}

