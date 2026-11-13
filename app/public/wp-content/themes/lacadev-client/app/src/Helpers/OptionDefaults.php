<?php

namespace App\Helpers;

/**
 * Khởi tạo giá trị mặc định cho các option thường dùng trên frontend và admin
 * với autoload = yes.
 *
 * Lý do:
 * - Khi get_option($name) được gọi cho một option chưa từng lưu vào wp_options,
 *   WordPress không tìm thấy trong cache alloptions nên bắt buộc phải chạy 1 câu
 *   SELECT riêng lẻ vào database trên MỖI request.
 * - Trang chủ và trang bài viết ban đầu bị tốn ~30-45 query vô nghĩa chỉ để đọc
 *   các option chưa tồn tại.
 * - Lớp này tạo sẵn các option đó với autoload = yes, giúp WordPress gom toàn bộ
 *   vào 1 câu SELECT alloptions duy nhất lúc bootstrap, đưa số query đọc option
 *   trên frontend về 0.
 */
class OptionDefaults
{
    public const VERSION = '2026-11.1';
    public const VERSION_OPTION = 'laca_option_defaults_version';

    /**
     * Đăng ký hook khởi tạo.
     */
    public static function register(): void
    {
        add_action('after_switch_theme', [self::class, 'ensureDefaults']);

        // Chỉ kiểm tra và chạy 1 lần ở admin nếu version thay đổi
        if (is_admin()) {
            add_action('admin_init', [self::class, 'maybeInit']);
        }
    }

    /**
     * Kiểm tra phiên bản và chạy khởi tạo nếu cần.
     */
    public static function maybeInit(): void
    {
        $current = get_option(self::VERSION_OPTION, '');
        if ($current !== self::VERSION) {
            self::ensureDefaults();
        }
    }

    /**
     * Danh sách option và giá trị mặc định tương ứng.
     *
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
    {
        return [
            // AdminSettings
            '_theme_info_date_started'          => '',
            '_theme_info_date_publish'          => '',
            'laca_extra_super_user_logins'      => [],
            '_disable_admin_confirm_email'      => false,
            '_disable_use_weak_password'        => false,
            '_hide_post_menu_default'           => false,
            '_hide_comment_menu_default'        => false,
            '_use_smtp'                         => false,
            '_hide_theme_editor'                => false,

            // Optimize
            '_disable_use_jquery_migrate'       => false,
            '_disable_gutenberg_css'            => false,
            '_disable_classic_css'              => false,
            '_disable_emoji'                    => false,
            '_enable_instant_page'              => false,
            '_enable_smooth_scroll'             => false,
            '_remove_comments'                  => false,
            '_enable_advanced_resource_hints'   => false,
            '_enable_optimize_images'           => false,
            '_enable_optimize_content_images'   => false,
            '_revision_limit'                   => 5,
            '_heartbeat_mode'                   => 'default',
            '_enable_post_view_counter'         => 'yes',
            '_enable_optimize_database_queries' => false,
            '_enable_optimize_memory_usage'     => false,

            // Security
            '_disable_rest_api'                 => false,
            '_disable_xml_rpc'                  => false,
            '_disable_wp_embed'                 => false,
            '_disable_x_pingback'               => false,
            '_enable_remove_wordpress_bloat'    => false,
            'laca_login_honeypot'               => ['enabled' => false, 'field_name' => 'laca_hp_check'],
            'laca_login_policy'                 => ['min_length' => 0, 'force_complexity' => false],
            'laca_login_slug'                   => '',
            'laca_security_headers'             => [],

            // Modules & Integrations
            'laca_discord_hub'                  => [],
            'laca_email_log_table_version'      => '1.0.0',
            'laca_login_log_table_version'      => '1.0.0',
            'carbon_custom_sidebars'            => [],
            'laca_dynamic_cpts'                 => '[]',
            'lacadev_block_category_map'        => [],
            'laca_maintenance_mode'             => '0',
            '_is_maintenance'                   => '',
            'laca_chatbot_enabled'              => 'off',
            'site_logo'                         => 0,
            'laca_popup_enabled'                => false,

            // Scripts fallback keys
            '_crb_header_script'                => '',
            'crb_header_script'                 => '',
            '_crb_footer_script'                => '',
            'crb_footer_script'                 => '',
        ];
    }

    /**
     * Danh sách option đã có sẵn trong DB nhưng cần đảm bảo autoload = yes.
     *
     * @return array<string>
     */
    public static function getAutoloadOptions(): array
    {
        return [
            'laca_google_services',
            'laca_seo_sitemap',
            'laca_seo_instant_indexing',
            'laca_theme_options_snapshot',
        ];
    }

    /**
     * Khởi tạo các option thiếu và bật autoload.
     */
    public static function ensureDefaults(): void
    {
        global $wpdb;

        $defaults = self::getDefaults();

        // 1. Lấy danh sách các option đã tồn tại trong DB trong 1 query
        $placeholders = implode(',', array_fill(0, count($defaults), '%s'));
        $existing = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
                array_keys($defaults)
            )
        );
        $existingMap = array_fill_keys($existing ?: [], true);

        // 2. Chỉ add_option cho các option chưa tồn tại
        foreach ($defaults as $name => $defaultValue) {
            if (!isset($existingMap[$name])) {
                // autoload = 'yes'
                add_option($name, $defaultValue, '', 'yes');
            }
        }

        // 3. Đảm bảo các option frontend quan trọng có autoload = yes
        $autoloadList = self::getAutoloadOptions();
        $autoloadPlaceholders = implode(',', array_fill(0, count($autoloadList), '%s'));
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET autoload = 'yes' WHERE option_name IN ($autoloadPlaceholders) AND autoload != 'yes'",
                $autoloadList
            )
        );

        // 4. Lưu version
        update_option(self::VERSION_OPTION, self::VERSION, 'yes');

        // 5. Xoá cache alloptions để WP nạp lại đầy đủ
        wp_cache_delete('alloptions', 'options');
    }
}
