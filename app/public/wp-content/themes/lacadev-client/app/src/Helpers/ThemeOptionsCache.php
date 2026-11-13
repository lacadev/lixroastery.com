<?php

namespace App\Helpers;

use App\Settings\LacaTools\Management\SiteConfig\ConfigRegistry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ThemeOptionsCache
 *
 * Tối ưu hoá truy vấn Carbon Fields Theme Options cho Frontend:
 * - Thay vì mỗi hàm carbon_get_theme_option() chạy 1 câu SQL trực tiếp không qua cache (gây 40+ query/request),
 *   toàn bộ giá trị đã định dạng được chụp thành 1 option duy nhất: `laca_theme_options_snapshot` (autoload yes).
 * - Trên frontend: đọc 0 query SQL bổ sung vì snapshot đã được nạp sẵn trong autoload options của WordPress.
 * - Tự động tái tạo bản chụp khi lưu theme options trong admin, sau khi nhập cấu hình hoặc chuyển đổi theme.
 *
 * @package App\Helpers
 */
class ThemeOptionsCache
{
    public const SNAPSHOT_OPTION = 'laca_theme_options_snapshot';
    public const REBUILD_FLAG    = 'laca_needs_cf_snapshot';

    /**
     * Cache trong bộ nhớ runtime PHP của request hiện tại.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $memoryCache = null;

    /**
     * Cờ đánh dấu tránh vòng lặp khi đang build snapshot.
     */
    private static bool $isBuilding = false;

    /**
     * Đăng ký các hook vòng đời để tự động đồng bộ snapshot.
     */
    public static function register(): void
    {
        // Khi lưu bất kỳ container theme options nào trong Carbon Fields
        add_action('carbon_fields_theme_options_container_saved', [self::class, 'onContainerSaved'], 20);

        // Sau khi chuyển đổi theme
        add_action('after_switch_theme', [self::class, 'buildSnapshot']);

        // Kiểm tra và tái tạo snapshot nếu có cờ yêu cầu từ frontend
        add_action('admin_init', [self::class, 'maybeRebuildFromFlag']);

        // Lắng nghe khi các option Carbon Fields bắt đầu bằng "_" bị sửa đổi trong admin
        add_action('updated_option', [self::class, 'onOptionUpdated'], 10, 3);
        add_action('deleted_option', [self::class, 'onOptionDeleted'], 10, 1);

        // Tối ưu hóa Scripts_Field để không query DB lúc print_scripts trên frontend
        self::optimizeScriptsField();
    }

    /**
     * Tối ưu hoá Carbon Fields Scripts_Field trên frontend.
     * Thay vì để print_scripts() chạy raw query $wpdb->options cho từng script,
     * ta unhook nó và echo trực tiếp từ snapshot cache.
     */
    public static function optimizeScriptsField(): void
    {
        if (is_admin()) {
            return;
        }

        add_action('wp', function (): void {
            foreach (['wp_head', 'wp_footer'] as $hook) {
                if (!isset($GLOBALS['wp_filter'][$hook])) {
                    continue;
                }
                foreach ($GLOBALS['wp_filter'][$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $idx => $callback) {
                        if (
                            isset($callback['function']) &&
                            is_array($callback['function']) &&
                            isset($callback['function'][1]) &&
                            $callback['function'][1] === 'print_scripts' &&
                            is_object($callback['function'][0]) &&
                            is_a($callback['function'][0], '\Carbon_Fields\Field\Scripts_Field')
                        ) {
                            $fieldObj = $callback['function'][0];
                            remove_action($hook, $callback['function'], $priority);
                            $fieldBaseName = method_exists($fieldObj, 'get_base_name') ? $fieldObj->get_base_name() : '';
                            if (!empty($fieldBaseName)) {
                                add_action($hook, function () use ($fieldBaseName): void {
                                    $val = ThemeOptionsCache::get($fieldBaseName, '');
                                    if (!empty($val)) {
                                        echo $val;
                                    }
                                }, $priority);
                            }
                        }
                    }
                }
            }
        }, 20);
    }

    /**
     * Lấy giá trị của một Theme Option từ Snapshot cache.
     *
     * @param string $name Tên field
     * @param mixed $default Giá trị mặc định nếu không tìm thấy
     * @return mixed
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        $snapshot = self::getSnapshot();

        if ($snapshot !== null) {
            // 1. Khớp chính xác tên field
            if (array_key_exists($name, $snapshot)) {
                return $snapshot[$name] !== null ? $snapshot[$name] : $default;
            }

            // 2. Fallback đa ngôn ngữ: nếu $name có hậu tố ngôn ngữ (vd: _vi, _en) mà không tìm thấy
            // thì thử tìm field gốc không có hậu tố
            $cleanKey = preg_replace('/_[a-z]{2}$/i', '', $name);
            if ($cleanKey !== $name && array_key_exists($cleanKey, $snapshot)) {
                return $snapshot[$cleanKey] !== null ? $snapshot[$cleanKey] : $default;
            }

            return $default;
        }

        // 3. Fallback an toàn nếu chưa có bản chụp (chỉ gọi carbon_get_theme_option nếu hàm tồn tại)
        if (function_exists('carbon_get_theme_option')) {
            $val = carbon_get_theme_option($name);
            return $val !== null && $val !== '' ? $val : $default;
        }

        return $default;
    }

    /**
     * Lấy mảng bản chụp snapshot hiện có.
     *
     * @return array<string, mixed>|null
     */
    public static function getSnapshot(): ?array
    {
        if (self::$memoryCache !== null) {
            return self::$memoryCache;
        }

        $snapshot = get_option(self::SNAPSHOT_OPTION, null);

        if (is_array($snapshot)) {
            self::$memoryCache = $snapshot;
            return self::$memoryCache;
        }

        // Nếu chưa có bản chụp mà đang ở ngữ cảnh Admin / Cron / CLI -> tạo ngay
        if (is_admin() || wp_doing_cron() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('WP_CLI') && WP_CLI)) {
            return self::buildSnapshot();
        }

        // Ở frontend nếu thiếu -> đặt cờ để admin/cron lần sau tạo lại (không làm chậm frontend)
        if (!get_transient(self::REBUILD_FLAG)) {
            set_transient(self::REBUILD_FLAG, 1, 12 * HOUR_IN_SECONDS);
        }

        return null;
    }

    /**
     * Xây dựng lại toàn bộ bản chụp Theme Options từ Carbon Fields.
     *
     * @return array<string, mixed>
     */
    public static function buildSnapshot(): array
    {
        if (self::$isBuilding) {
            return self::$memoryCache ?? [];
        }

        self::$isBuilding = true;
        $snapshot = [];

        if (class_exists(ConfigRegistry::class) && function_exists('carbon_get_theme_option')) {
            $carbonData = ConfigRegistry::getCarbonFieldsData();

            foreach ($carbonData as $sec => $data) {
                if (empty($data['fields']) || !is_array($data['fields'])) {
                    continue;
                }

                foreach ($data['fields'] as $baseName => $meta) {
                    $baseName = (string) $baseName;
                    $val = carbon_get_theme_option($baseName);
                    $snapshot[$baseName] = $val;
                }
            }
        }

        // Lưu bản chụp vào wp_options với autoload = 'yes' để nạp tự động 0 query
        update_option(self::SNAPSHOT_OPTION, $snapshot, 'yes');
        self::$memoryCache = $snapshot;
        delete_transient(self::REBUILD_FLAG);

        self::$isBuilding = false;
        return $snapshot;
    }

    /**
     * Xoá bản chụp.
     */
    public static function invalidate(): void
    {
        delete_option(self::SNAPSHOT_OPTION);
        self::$memoryCache = null;
    }

    /**
     * Hook khi lưu theme options container trong Carbon Fields.
     */
    public static function onContainerSaved(): void
    {
        self::buildSnapshot();
    }

    /**
     * Hook khi chuyển đổi theme.
     */
    public static function onThemeSwitched(): void
    {
        self::buildSnapshot();
    }

    /**
     * Tự động tái tạo nếu frontend báo thiếu bản chụp.
     */
    public static function maybeRebuildFromFlag(): void
    {
        if (get_transient(self::REBUILD_FLAG)) {
            self::buildSnapshot();
        }
    }

    /**
     * Lắng nghe option bắt đầu bằng "_" được cập nhật.
     */
    public static function onOptionUpdated(string $option, mixed $oldValue, mixed $newValue): void
    {
        if (self::$isBuilding || str_starts_with($option, '_transient') || str_starts_with($option, '_site_transient')) {
            return;
        }

        if (str_starts_with($option, '_') && (is_admin() || wp_doing_cron())) {
            $clean = ltrim($option, '_');
            if (class_exists(ConfigRegistry::class) && ConfigRegistry::isExportableOption($option)) {
                // Đánh dấu tái tạo ở cuối request
                add_action('shutdown', [self::class, 'buildSnapshot'], 999);
            }
        }
    }

    /**
     * Lắng nghe option bị xoá.
     */
    public static function onOptionDeleted(string $option): void
    {
        if (self::$isBuilding) {
            return;
        }

        if (str_starts_with($option, '_') && (is_admin() || wp_doing_cron())) {
            if (class_exists(ConfigRegistry::class) && ConfigRegistry::isExportableOption($option)) {
                add_action('shutdown', [self::class, 'buildSnapshot'], 999);
            }
        }
    }
}
