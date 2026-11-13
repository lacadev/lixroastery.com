<?php

namespace App\Settings\LacaTools\Management\SiteConfig;

use Carbon_Fields\Carbon_Fields;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ConfigRegistry
 *
 * Sổ đăng ký cấu hình website (Site Configuration Registry).
 * Đóng vai trò là DANH SÁCH TRẮNG (Whitelist) bắt buộc cho việc xuất và nhập cấu hình site:
 * - Tự động trích xuất các theme options từ Carbon Fields container repository thật.
 * - Khai báo danh sách các option cấu hình ngoài Carbon Fields (Security, Tools, Notifications, CPT, Maintenance...).
 * - Áp dụng danh sách cấm tuyệt đối (Blacklist cứng) cho danh tính site, kết nối hub, tracker và trạng thái runtime.
 *
 * @package App\Settings\LacaTools\Management\SiteConfig
 */
class ConfigRegistry
{
    /**
     * Danh sách các section cấu hình được hỗ trợ.
     */
    public const SECTION_THEME_OPTIONS      = 'theme_options';
    public const SECTION_SECURITY           = 'security';
    public const SECTION_TOOLS              = 'tools';
    public const SECTION_NOTIFICATIONS      = 'notifications';
    public const SECTION_CONTACT_FORMS      = 'contact_forms';
    public const SECTION_CUSTOM_POST_TYPES  = 'custom_post_types';
    public const SECTION_SEO                = 'seo';

    /**
     * Ánh xạ Carbon Fields Container ID sang Section tương ứng.
     */
    public const CONTAINER_SECTION_MAP = [
        'carbon_fields_container_laca_theme'           => self::SECTION_THEME_OPTIONS,
        'carbon_fields_container_laca_admin'           => self::SECTION_THEME_OPTIONS,
        'carbon_fields_container_toi_uu_hoa'           => self::SECTION_TOOLS,
        'carbon_fields_container_google_recaptcha'     => self::SECTION_SECURITY,
        'carbon_fields_container_lacadev_pm_bots'      => self::SECTION_NOTIFICATIONS,
        'carbon_fields_container_login_socials'        => self::SECTION_SECURITY,
        'carbon_fields_container_quan_tri_hd_su_dung'  => self::SECTION_THEME_OPTIONS,
    ];

    /**
     * Các container Carbon Fields liên quan đến danh tính hoặc kết nối hub BỊ BỎ QUA HOÀN TOÀN.
     */
    public const IGNORED_CONTAINERS = [
        'carbon_fields_container_dong_bo_block',
        'carbon_fields_container_site_tracker',
    ];

    /**
     * Danh sách option cấu hình NGOÀI Carbon Fields được phép xuất/nhập.
     */
    public const NON_CARBON_OPTIONS = [
        self::SECTION_SECURITY => [
            'laca_login_policy',
            'laca_login_guard_settings',
            'laca_login_honeypot',
            'laca_security_headers',
            'laca_2fa_master_enabled',
            'laca_enable_2fa',
            'laca_enable_custom_login',
            'laca_login_slug',
            '_disable_rest_api',
            '_disable_xml_rpc',
            '_disable_wp_embed',
            '_disable_x_pingback',
            '_enable_remove_wordpress_bloat',
            'laca_block_author_enum',
            'laca_malware_whitelist',
        ],
        self::SECTION_TOOLS => [
            'laca_db_cleaner_schedule',
        ],
        self::SECTION_NOTIFICATIONS => [
            'laca_discord_hub', // Chỉ phần config công khai (channels, alert rules), không gồm secrets
        ],
        self::SECTION_CUSTOM_POST_TYPES => [
            'laca_dynamic_cpts',
        ],
        self::SECTION_THEME_OPTIONS => [
            'laca_popup_enabled',
            'laca_popup_title',
            'laca_popup_content',
            'laca_popup_delay',
            'laca_popup_trigger',
            'laca_popup_scroll_pct',
            'laca_popup_cookie_hours',
            'laca_chatbot_enabled',
            'laca_chatbot_name',
            'laca_chatbot_greeting',
            'laca_chatbot_placeholder',
            'laca_chatbot_color',
            'laca_audio_enabled',
            'laca_audio_provider',
            'laca_audio_fpt_voice',
            'laca_audio_google_voice',
            'laca_audio_google_voice_en',
            'laca_audio_speed',
            'laca_audio_max_chars',
            'laca_audio_post_types',
            'laca_audio_fallback_speech',
            'laca_audio_fpt_api_key',     // secret
            'laca_audio_google_api_key',  // secret
            'laca_maintenance_mode',      // chú ý: khi nhập luôn rơi về 0
            'laca_maintenance_ip_whitelist',
        ],
    ];

    /**
     * Pattern regex phát hiện secret/mật khẩu/token/khoá API.
     */
    public const SECRET_PATTERN = '/(?:secret|token|password|api_key|private_key)/i';

    /**
     * Danh sách cứng các option được định danh là Secret.
     */
    public const SECRET_BLOCKLIST = [
        '_smtp_password',
        '_zalo_oa_access_token',
        '_zalo_oa_refresh_token',
        '_recaptcha_secret_key',
        'laca_audio_fpt_api_key',
        'laca_audio_google_api_key',
        '_telegram_bot_token',
        '_slack_webhook_url',
    ];

    /**
     * Danh sách các pattern bị CẤM TUYỆT ĐỐI xuất/nhập (Danh tính site & Trạng thái chạy nền).
     */
    public const NEVER_ALLOWED_PATTERNS = [
        // Danh tính site & tracker & kết nối hub
        'tracker',
        'project_id',
        '_laca_key_',
        'laca_key_',
        'laca_sync_key',
        'laca_catalog_key',
        'laca_client_config_paste',
        'known_login_ips',
        'theme_packages_dir',
        'theme_updater',
        'allow_legacy_auth',
        'block_sync',
        'catalog_source',

        // Trạng thái chạy nền, log, baseline, runtime
        'locked_ips',
        'baseline',
        'change_log',
        'transient',
        'role_version',
        'rewrite_flushed',
        'backups',
        'extra_super_user_logins',
        'default_form_id',
        'dh_cron',
        'fim_state',
        'rate_state',
        'queue_db',
        'files_db',
        'deep_health_report',
        'google_pending',
        'gsc_pending',
        'indexnow_log',
        'audit_result',
        'storage_health',
        'scan_state',
        'scan_result',

        // Core WordPress options nhạy cảm
        'siteurl',
        'home',
        'admin_email',
        'default_role',
        'users_can_register',
        'active_plugins',
        'template',
        'stylesheet',
        'blogname',
        'blogdescription',
        'cron',
        'rewrite_rules',
        'WPLANG',
        'nonce',
    ];

    /** Secret mã hoá bằng PostAudioSecurity (không phải Crypto laca:v1) — xuất phải giải mã, nhập phải mã hoá lại bằng khoá site mới */
    public const POST_AUDIO_ENCRYPTED = ['laca_audio_fpt_api_key', 'laca_audio_google_api_key'];

    /**
     * Option tuyệt đối cấm riêng biệt (chính xác tên).
     */
    public const NEVER_ALLOWED_EXACT = [
        'laca_discord_hub_webhooks', // webhook mã hoá bằng AUTH_KEY của site cũ
        'laca_site_config_backups',
        'laca_seo_backups',
        'laca_login_guard_locked_ips',
        'laca_file_baseline',
        'laca_file_baseline_time',
        'laca_extra_super_user_logins',
        '_laca_project_id',
        '_laca_tracker_endpoint',
        'laca_client_role_version',
        'laca_pwa_rewrite_flushed',
        'laca_cf_default_form_id',
        'laca_security_audit_result',
        'laca_storage_health',
        'laca_large_file_scan_state',
        'laca_large_file_scan_result',
        'siteurl',
        'home',
        'admin_email',
        'default_role',
        'users_can_register',
        'active_plugins',
        'template',
        'stylesheet',
    ];

    /**
     * Kiểm tra xem một option name có bị cấm tuyệt đối hay không.
     */
    public static function isNeverAllowed(string $optionName): bool
    {
        $opt = strtolower(trim($optionName));

        if (in_array($opt, self::NEVER_ALLOWED_EXACT, true)) {
            return true;
        }

        foreach (self::NEVER_ALLOWED_PATTERNS as $pattern) {
            if (str_contains($opt, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kiểm tra xem một option name có phải là Secret hay không.
     */
    public static function isSecret(string $optionName): bool
    {
        $opt = strtolower(trim($optionName));

        if (in_array($opt, self::SECRET_BLOCKLIST, true)) {
            return true;
        }

        return (bool) preg_match(self::SECRET_PATTERN, $opt);
    }

    /**
     * Lấy toàn bộ thông tin fields đã đăng ký từ Carbon Fields theo từng Section.
     *
     * @return array<string, array{fields: array<string, string>, field_meta: array<string, array{type: string, section: string}>}>
     */
    public static function getCarbonFieldsData(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $sections = [
            self::SECTION_THEME_OPTIONS => ['fields' => [], 'field_meta' => []],
            self::SECTION_SECURITY      => ['fields' => [], 'field_meta' => []],
            self::SECTION_TOOLS         => ['fields' => [], 'field_meta' => []],
            self::SECTION_NOTIFICATIONS => ['fields' => [], 'field_meta' => []],
        ];

        if (!class_exists(Carbon_Fields::class)) {
            $cached = $sections;
            return $cached;
        }

        try {
            $repo = Carbon_Fields::resolve('container_repository');
            $containers = $repo->get_containers('theme_options');
        } catch (\Throwable $e) {
            $cached = $sections;
            return $cached;
        }

        foreach ($containers as $c) {
            $containerId = (string) $c->get_id();

            // Bỏ qua container danh tính / tracker / hub
            if (in_array($containerId, self::IGNORED_CONTAINERS, true)) {
                continue;
            }

            $section = self::CONTAINER_SECTION_MAP[$containerId] ?? self::SECTION_THEME_OPTIONS;
            if (!isset($sections[$section])) {
                $sections[$section] = ['fields' => [], 'field_meta' => []];
            }

            self::extractFieldsRecursive($c->get_fields(), $section, $sections);
        }

        $cached = $sections;
        return $cached;
    }

    /**
     * Đệ quy duyệt các field (kể cả field con của complex).
     *
     * @param array $fields
     * @param string $section
     * @param array &$sections
     */
    private static function extractFieldsRecursive(array $fields, string $section, array &$sections): void
    {
        foreach ($fields as $field) {
            $type = (string) $field->get_type();
            if (in_array($type, ['html', 'separator'], true)) {
                continue;
            }

            $baseName = (string) $field->get_base_name();
            if ($baseName === '') {
                continue;
            }

            // Bỏ qua nếu option name bị cấm
            if (self::isNeverAllowed($baseName) || self::isNeverAllowed('_' . $baseName)) {
                continue;
            }

            $sections[$section]['fields'][$baseName] = $type;
            $sections[$section]['field_meta'][$baseName] = [
                'type'    => $type,
                'section' => $section,
            ];

            if ($type === 'complex' && method_exists($field, 'get_fields')) {
                self::extractFieldsRecursive($field->get_fields(), $section, $sections);
            }
        }
    }

    /**
     * Lấy toàn bộ danh sách trắng các option name thuộc Sổ đăng ký (theo Section).
     *
     * @return array<string, array<int, string>> Mảng [section => [option_names...]]
     */
    public static function getAllowedOptionNamesBySection(): array
    {
        $carbonData = self::getCarbonFieldsData();
        $result = [];

        foreach ($carbonData as $sec => $data) {
            $result[$sec] = [];
            foreach (array_keys($data['fields']) as $baseName) {
                $optName = '_' . $baseName;
                if (!self::isNeverAllowed($optName)) {
                    $result[$sec][] = $optName;
                }
            }
        }

        // Gộp các Non-Carbon options
        foreach (self::NON_CARBON_OPTIONS as $sec => $opts) {
            if (!isset($result[$sec])) {
                $result[$sec] = [];
            }
            foreach ($opts as $optName) {
                if (!self::isNeverAllowed($optName) && !in_array($optName, $result[$sec], true)) {
                    $result[$sec][] = $optName;
                }
            }
        }

        return $result;
    }

    /**
     * Tra cứu xem một option name thuộc Section nào trong Sổ đăng ký.
     * Trả về tên section (string) hoặc null nếu không thuộc Sổ đăng ký.
     */
    public static function getOptionSection(string $optionName): ?string
    {
        if (self::isNeverAllowed($optionName)) {
            return null;
        }

        // 1. Kiểm tra trong Non-Carbon options
        foreach (self::NON_CARBON_OPTIONS as $sec => $opts) {
            if (in_array($optionName, $opts, true)) {
                return $sec;
            }
        }

        // 2. Kiểm tra trong Carbon Fields options
        $cleanName = ltrim($optionName, '_');
        $baseName  = explode('|', $cleanName)[0];

        $carbonData = self::getCarbonFieldsData();
        foreach ($carbonData as $sec => $data) {
            if (isset($data['fields'][$baseName])) {
                return $sec;
            }
        }

        return null;
    }

    /**
     * Kiểm tra xem một option có thể xuất/nhập được không (nằm trong Sổ đăng ký và không bị cấm).
     */
    public static function isExportableOption(string $optionName): bool
    {
        return self::getOptionSection($optionName) !== null;
    }

    /**
     * Lấy toàn bộ danh sách tên option hợp lệ có thể xuất/nhập.
     *
     * @return string[]
     */
    public static function getAllExportableOptions(): array
    {
        $all = [];
        $bySec = self::getAllowedOptionNamesBySection();
        foreach ($bySec as $sec => $opts) {
            if (is_array($opts)) {
                foreach ($opts as $opt) {
                    $all[] = $opt;
                }
            }
        }
        return array_values(array_unique($all));
    }

    /**
     * Kiểm tra xem một field Carbon Fields có phải là kiểu ảnh/media hay không.
     */
    public static function isMediaField(string $baseName): bool
    {
        $carbonData = self::getCarbonFieldsData();
        foreach ($carbonData as $data) {
            if (isset($data['fields'][$baseName])) {
                $type = $data['fields'][$baseName];
                return in_array($type, ['image', 'file', 'media_gallery'], true);
            }
        }
        return false;
    }

    /**
     * Kiểm tra xem một field có cần đánh dấu là "Cần chọn lại thủ công" khi nhập site mới không (ID bài/trang).
     */
    public static function isManualField(string $baseName): bool
    {
        $carbonData = self::getCarbonFieldsData();
        foreach ($carbonData as $data) {
            if (isset($data['fields'][$baseName])) {
                $type = $data['fields'][$baseName];
                return in_array($type, ['association'], true);
            }
        }
        return false;
    }
}
