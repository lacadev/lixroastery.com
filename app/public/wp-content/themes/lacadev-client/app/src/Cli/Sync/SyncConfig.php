<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SyncConfig
 *
 * Quản lý đọc và thẩm định file cấu hình laca-sync.yml
 */
class SyncConfig
{
    private static ?array $cachedConfig = null;
    private static ?string $configPath = null;

    /**
     * Xác định đường dẫn file laca-sync.yml theo độ ưu tiên
     */
    public static function locateConfigFile(): ?string
    {
        $custom = getenv('LACA_SYNC_CONFIG');
        if ($custom && is_readable($custom)) {
            return realpath($custom) ?: $custom;
        }

        $candidates = [
            // Repo root trong cấu trúc Local by Flywheel: <site>/laca-sync.yml
            // ABSPATH = <site>/app/public/
            realpath(ABSPATH . '../../laca-sync.yml'),
            realpath(ABSPATH . '../laca-sync.yml'),
            realpath(ABSPATH . 'laca-sync.yml'),
            // Thư mục cha của repo nếu có
            dirname(ABSPATH, 3) . '/laca-sync.yml',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && is_readable($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Nạp cấu hình từ file laca-sync.yml
     */
    public static function load(bool $reload = false): array
    {
        if (self::$cachedConfig !== null && !$reload) {
            return self::$cachedConfig;
        }

        $filePath = self::locateConfigFile();
        if (!$filePath) {
            self::$configPath = null;
            self::$cachedConfig = self::getDefaults();
            return self::$cachedConfig;
        }

        self::$configPath = $filePath;
        $content = file_get_contents($filePath);
        $parsed = self::parseYaml($content);

        self::$cachedConfig = array_replace_recursive(self::getDefaults(), $parsed ?: []);
        return self::$cachedConfig;
    }

    /**
     * Lấy đường dẫn file cấu hình đang nạp
     */
    public static function getConfigPath(): ?string
    {
        if (self::$configPath === null) {
            self::load();
        }
        return self::$configPath;
    }

    /**
     * Kiểm tra cấu hình có tồn tại file hợp lệ không
     */
    public static function exists(): bool
    {
        return self::getConfigPath() !== null;
    }

    /**
     * Lấy cấu hình production
     */
    public static function getProduction(): array
    {
        $cfg = self::load();
        return $cfg['production'] ?? [];
    }

    /**
     * Lấy thông tin SSH đã phân tích (user, host, port)
     * Hỗ trợ dạng: "user@host:port" hoặc "user@host" hoặc "host-alias"
     */
    public static function parseSshTarget(string $sshTarget): array
    {
        $sshTarget = trim($sshTarget);
        $user = '';
        $host = '';
        $port = 22;

        if (str_contains($sshTarget, '@')) {
            [$user, $hostPart] = explode('@', $sshTarget, 2);
        } else {
            $hostPart = $sshTarget;
        }

        if (str_contains($hostPart, ':')) {
            [$host, $portPart] = explode(':', $hostPart, 2);
            $port = (int) $portPart ?: 22;
        } else {
            $host = $hostPart;
        }

        // Chặn tiêm tuỳ chọn vào ssh (host/user bắt đầu bằng "-" sẽ bị ssh hiểu là option, vd -oProxyCommand=…)
        if (!preg_match('/^[A-Za-z0-9._-]*$/', $user) || !preg_match('/^[A-Za-z0-9._-]+$/', $host)
            || str_starts_with($user, '-') || str_starts_with($host, '-')) {
            $user = '';
            $host = '';
        }

        return [
            'raw'  => $sshTarget,
            'user' => $user,
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     * Lệnh wp-cli trên server ghép vào chuỗi lệnh remote. KHÔNG bọc cả cụm trong nháy
     * (vd "php ~/bin/wp-cli.phar" phải là 2 token, "~" phải được shell mở rộng) → chỉ chấp nhận ký tự an toàn.
     * Trả về null nếu giá trị có ký tự lạ (chặn tiêm lệnh lên server).
     */
    public static function remoteWpCommand(string $wp): ?string
    {
        $wp = trim($wp) === '' ? 'wp' : trim($wp);
        if (!preg_match('#^[A-Za-z0-9_./~ =-]+$#', $wp)) {
            return null;
        }
        return preg_replace('/\s+/', ' ', $wp);
    }

    /**
     * Tên bảng an toàn để ghép vào lệnh shell.
     */
    public static function isSafeTableName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
    }

    /**
     * Lấy cấu hình local
     */
    public static function getLocal(): array
    {
        $cfg = self::load();
        $local = $cfg['local'] ?? [];
        if (empty($local['url'])) {
            $local['url'] = home_url();
        }
        return $local;
    }

    /**
     * Lấy cấu hình database
     */
    public static function getDbConfig(): array
    {
        $cfg = self::load();
        return $cfg['db'] ?? [];
    }

    /**
     * Lấy cấu hình files
     */
    public static function getFilesConfig(): array
    {
        $cfg = self::load();
        return $cfg['files'] ?? [];
    }

    /**
     * Lấy cấu hình media
     */
    public static function getMediaConfig(): array
    {
        $cfg = self::load();
        return $cfg['media'] ?? [];
    }

    /**
     * Parse nội dung YAML
     */
    public static function parseYaml(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        // Ưu tiên parser của WP-CLI nếu có
        if (class_exists('\WP_CLI\Utils') && method_exists('\WP_CLI\Utils', 'parse_yaml')) {
            try {
                $res = \WP_CLI\Utils\parse_yaml($content);
                if (is_array($res)) {
                    return $res;
                }
            } catch (\Throwable $e) {
                // Tiếp tục thử parser khác
            }
        }

        if (class_exists('\Spyc') && method_exists('\Spyc', 'YAMLLoadString')) {
            try {
                $res = \Spyc::YAMLLoadString($content);
                if (is_array($res)) {
                    return $res;
                }
            } catch (\Throwable $e) {
                // Tiếp tục fallback
            }
        }

        if (function_exists('yaml_parse')) {
            $res = @yaml_parse($content);
            if (is_array($res)) {
                return $res;
            }
        }

        // Fallback parser đơn giản cho cấu trúc 2 tầng của laca-sync.yml
        return self::simpleYamlParse($content);
    }

    /**
     * Fallback parser đơn giản đủ đọc cấu trúc laca-sync.yml chuẩn
     */
    private static function simpleYamlParse(string $content): array
    {
        $result = [];
        $lines = explode("\n", $content);
        $currentSection = null;
        $currentKey = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Đo mức thụt dòng
            $indent = strlen($line) - strlen(ltrim($line));

            // Section cấp 1 (indent = 0)
            if ($indent === 0 && str_contains($trimmed, ':')) {
                [$key, $val] = explode(':', $trimmed, 2);
                $currentSection = trim($key);
                $val = trim($val);
                if ($val === '') {
                    $result[$currentSection] = [];
                } else {
                    $result[$currentSection] = self::castValue($val);
                }
                $currentKey = null;
                continue;
            }

            // Thuộc tính bên trong section (indent >= 2)
            if ($currentSection !== null) {
                // Danh sách list item: - "value"
                if (str_starts_with($trimmed, '-')) {
                    $itemVal = trim(substr($trimmed, 1));
                    $itemVal = self::castValue($itemVal);
                    if ($currentKey !== null && isset($result[$currentSection][$currentKey]) && is_array($result[$currentSection][$currentKey])) {
                        $result[$currentSection][$currentKey][] = $itemVal;
                    } elseif ($currentKey === null && is_array($result[$currentSection])) {
                        $result[$currentSection][] = $itemVal;
                    }
                    continue;
                }

                // Key - value
                if (str_contains($trimmed, ':')) {
                    [$subKey, $subVal] = explode(':', $trimmed, 2);
                    $subKey = trim($subKey);
                    $subVal = trim($subVal);
                    $currentKey = $subKey;

                    if ($subVal === '') {
                        $result[$currentSection][$subKey] = [];
                    } else {
                        $result[$currentSection][$subKey] = self::castValue($subVal);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Ép kiểu chuỗi YAML cơ bản
     */
    private static function castValue(string $val): mixed
    {
        $trimmed = trim($val);
        // Bỏ dấu nháy kép hoặc nháy đơn
        if ((str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) ||
            (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))) {
            return substr($trimmed, 1, -1);
        }

        $lower = strtolower($trimmed);
        if ($lower === 'true' || $lower === 'yes' || $lower === 'on') {
            return true;
        }
        if ($lower === 'false' || $lower === 'no' || $lower === 'off') {
            return false;
        }
        if ($lower === 'null' || $trimmed === '~' || $trimmed === '') {
            return null;
        }
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        // Inline list: ["a", "b"]
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $inner = trim(substr($trimmed, 1, -1));
            if ($inner === '') {
                return [];
            }
            $items = array_map('trim', explode(',', $inner));
            return array_map([self::class, 'castValue'], $items);
        }

        return $trimmed;
    }

    /**
     * Cấu hình mặc định
     */
    public static function getDefaults(): array
    {
        return [
            'production' => [
                'ssh'  => '',
                'path' => '',
                'url'  => '',
                'wp'   => 'wp',
            ],
            'local' => [
                'url'                => '',
                'deactivate_plugins' => [
                    'litespeed-cache',
                    'wp-rocket',
                    'w3-total-cache',
                    'wordfence',
                    'updraftplus',
                    'all-in-one-wp-migration',
                ],
            ],
            'db' => [
                'exclude_tables'    => ['wf*', 'actionscheduler_logs', 'laca_project_logs'],
                'exclude_data_only' => ['laca_email_log', 'laca_login_log', 'laca_contact_submissions'],
                'anonymize'         => false,
            ],
            'files' => [
                'paths'   => ['wp-content/themes', 'wp-content/plugins', 'wp-content/mu-plugins'],
                'exclude' => ['node_modules', '.git', '*.log', 'cache', 'wflogs', 'upgrade', 'uploads'],
            ],
            'media' => [
                'proxy' => true,
            ],
        ];
    }
}

