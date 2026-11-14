<?php

namespace App\Settings\LacaTools\Management\SiteConfig;

use App\Helpers\Crypto;
use App\Databases\ContactFormTable;
use App\Features\LacaSeo\ImportExport\Exporter as SeoExporter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SiteConfigExporter
 *
 * Xử lý việc xuất bộ cấu hình website ra tệp JSON chuẩn (laca-site-config v1).
 * Tuân thủ nghiêm ngặt Sổ đăng ký cấu hình ConfigRegistry:
 * - Chỉ xuất các option nằm trong danh sách trắng.
 * - Tuyệt đối không bao giờ xuất danh tính site, khoá tracker/hub, trạng thái runtime.
 * - Mặc định loại trừ mật khẩu/secret; nếu người dùng chọn xuất thì giải mã an toàn và cảnh báo rõ.
 * - Trích xuất metadata ảnh/media đính kèm (URL gốc, filename, alt, title) mà không nhúng base64.
 * - Tích hợp exporter của Laca SEO.
 *
 * @package App\Settings\LacaTools\Management\SiteConfig
 */
class SiteConfigExporter
{
    public const NONCE_ACTION = 'laca_export_site_config_nonce';
    public const ACTION_DOWNLOAD = 'laca_export_site_config';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION_DOWNLOAD, [$this, 'handleExportDownload']);
    }

    /**
     * Tạo dữ liệu xuất cấu hình hoàn chỉnh.
     *
     * @param array<string, bool> $selectedSections Danh sách section được chọn xuất
     * @param bool $includeMedia Kèm thông tin ảnh/media
     * @param bool $includeSecrets Kèm mật khẩu/khoá API đã giải mã
     * @return array<string, mixed>
     */
    public static function generateExport(
        array $selectedSections = [],
        bool $includeMedia = true,
        bool $includeSecrets = false
    ): array {
        global $wpdb;

        // Mặc định chọn tất cả các section nếu không truyền cụ thể
        $allSections = [
            ConfigRegistry::SECTION_THEME_OPTIONS     => true,
            ConfigRegistry::SECTION_SECURITY          => true,
            ConfigRegistry::SECTION_TOOLS             => true,
            ConfigRegistry::SECTION_NOTIFICATIONS     => true,
            ConfigRegistry::SECTION_CONTACT_FORMS     => true,
            ConfigRegistry::SECTION_CUSTOM_POST_TYPES => true,
            ConfigRegistry::SECTION_SEO               => true,
        ];

        $sectionsToExport = empty($selectedSections) ? $allSections : array_intersect_key($allSections, array_filter($selectedSections));

        $theme = wp_get_theme();
        $mediaRegistry = [];
        $manualFields  = [];

        $payload = [
            'format'           => 'laca-site-config',
            'format_version'   => 1,
            'exported_at'      => current_time('c'),
            'source'           => [
                'home_url'      => get_home_url(),
                'theme'         => get_template(),
                'theme_version' => $theme->get('Version') ?: '1.0.0',
                'wp_version'    => get_bloginfo('version'),
                'locale'        => get_locale(),
            ],
            'includes_secrets' => $includeSecrets,
            'sections'         => [],
            'media'            => [],
            'manual_fields'    => [],
        ];

        $allowedBySection = ConfigRegistry::getAllowedOptionNamesBySection();

        // 1. Xuất các Section lưu trong wp_options (theme_options, security, tools, notifications, custom_post_types)
        foreach ($sectionsToExport as $sec => $active) {
            if (!$active || in_array($sec, [ConfigRegistry::SECTION_CONTACT_FORMS, ConfigRegistry::SECTION_SEO], true)) {
                continue;
            }

            $secOptions = [];
            $allowedOptionNames = $allowedBySection[$sec] ?? [];

            if (!empty($allowedOptionNames)) {
                // Tạo câu query LIKE an toàn để lấy cả option gốc và field complex
                $likeConditions = [];
                $params = [];
                foreach ($allowedOptionNames as $optName) {
                    $likeConditions[] = 'option_name = %s OR option_name LIKE %s';
                    $params[] = $optName;
                    $params[] = $wpdb->esc_like($optName . '|') . '%';
                }

                $whereSql = implode(' OR ', $likeConditions);
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $query = $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE {$whereSql}", $params);
                $rows = $wpdb->get_results($query, ARRAY_A) ?: [];

                foreach ($rows as $row) {
                    $optName = (string) $row['option_name'];
                    $rawVal  = maybe_unserialize($row['option_value']);

                    // Kiểm tra lại qua Sổ đăng ký
                    if (ConfigRegistry::isNeverAllowed($optName)) {
                        continue;
                    }

                    // Xử lý Secret
                    if (ConfigRegistry::isSecret($optName)) {
                        if (!$includeSecrets) {
                            continue; // Bỏ qua secret nếu không chọn xuất
                        }

                        // Khoá API Post Audio mã hoá bằng PostAudioSecurity (khoá riêng của site) → giải mã để site mới mã hoá lại
                        if (in_array($optName, ConfigRegistry::POST_AUDIO_ENCRYPTED, true) && is_string($rawVal) && $rawVal !== ''
                            && class_exists(\App\Features\PostAudio\PostAudioSecurity::class)) {
                            $plain = \App\Features\PostAudio\PostAudioSecurity::decrypt($rawVal);
                            if ($plain === '') {
                                $manualFields[] = [
                                    'option' => $optName,
                                    'label'  => self::getOptionLabel($optName),
                                    'reason' => 'secret_decrypt_failed',
                                ];
                                continue;
                            }
                            $rawVal = $plain;
                        }

                        // Nếu giá trị đã được mã hoá (laca:v2:/laca:v1:) -> tiến hành giải mã để xuất dạng đọc được
                        if (is_string($rawVal) && class_exists(Crypto::class) && Crypto::isTagged($rawVal)) {
                            $decrypted = Crypto::decryptTagged($rawVal);
                            if ($decrypted !== null) {
                                $rawVal = $decrypted;
                            } else {
                                // Giải mã lỗi -> bỏ field này và ghi nhận manual_fields
                                $manualFields[] = [
                                    'option' => $optName,
                                    'label'  => self::getOptionLabel($optName),
                                    'reason' => 'secret_decrypt_failed',
                                ];
                                continue;
                            }
                        }
                    }

                    // Discord: metadata kênh (đã cấu hình lúc/bởi ai) và deploy_key gắn với webhook mã hoá của site nguồn — không mang sang
                    if ($optName === 'laca_discord_hub' && is_array($rawVal)) {
                        unset($rawVal['channels'], $rawVal['deploy_key']);
                    }

                    // Xử lý Media Attachment nếu là field kiểu ảnh/file
                    if ($includeMedia) {
                        self::collectMediaFromOption($optName, $rawVal, $mediaRegistry);
                    }

                    // Ghi nhận field cần chọn lại thủ công (association)
                    $cleanBase = ltrim(explode('|', $optName)[0], '_');
                    if (ConfigRegistry::isManualField($cleanBase)) {
                        $manualFields[] = [
                            'option' => $optName,
                            'label'  => self::getOptionLabel($optName),
                            'reason' => 'page_or_post_association',
                        ];
                    }

                    $secOptions[$optName] = $rawVal;
                }
            }

            // Ghi đè maintenance_mode nếu có xuất (khi xuất giữ nguyên, nhưng sẽ luôn nhập ở trạng thái tắt)
            $payload['sections'][$sec] = $secOptions;
        }

        // 2. Xuất Form liên hệ mẫu (Contact Forms)
        if (!empty($sectionsToExport[ConfigRegistry::SECTION_CONTACT_FORMS])) {
            $payload['sections'][ConfigRegistry::SECTION_CONTACT_FORMS] = self::exportContactForms();
        }

        // 3. Xuất Laca SEO
        if (!empty($sectionsToExport[ConfigRegistry::SECTION_SEO]) && class_exists(SeoExporter::class)) {
            $seoSections = ['general', 'titles', 'sitemap', 'instant_indexing', 'modules', 'redirections'];
            $payload['sections'][ConfigRegistry::SECTION_SEO] = SeoExporter::generateExport($seoSections);
        }

        $payload['media']         = $mediaRegistry;
        $payload['manual_fields'] = $manualFields;

        return $payload;
    }

    /**
     * Xuất danh sách mẫu form liên hệ (chỉ cấu hình, không lấy submission).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function exportContactForms(): array
    {
        if (!class_exists(ContactFormTable::class)) {
            return [];
        }

        $allForms = ContactFormTable::getAllForms();
        $formsExport = [];

        foreach ($allForms as $form) {
            $fields = $form['fields'] ?? [];
            if (is_string($fields)) {
                $fields = json_decode($fields, true) ?: [];
            }

            $styleSettings = $form['style_settings'] ?? [];
            if (is_string($styleSettings)) {
                $styleSettings = json_decode($styleSettings, true) ?: [];
            }

            $formsExport[] = [
                'name'                 => (string) ($form['name'] ?? ''),
                'fields'               => $fields,
                'notify_email'         => (string) ($form['notify_email'] ?? ''),
                'email_admin_subject'  => (string) ($form['email_admin_subject'] ?? ''),
                'email_admin_body'     => (string) ($form['email_admin_body'] ?? ''),
                'email_user_subject'   => (string) ($form['email_user_subject'] ?? ''),
                'email_user_body'      => (string) ($form['email_user_body'] ?? ''),
                'style_settings'       => $styleSettings,
                'is_active'            => (int) ($form['is_active'] ?? 1),
            ];
        }

        return $formsExport;
    }

    /**
     * Thu thập thông tin media từ giá trị option (nếu là ID đính kèm).
     *
     * @param string $optName
     * @param mixed $value
     * @param array<int, array<string, mixed>> &$mediaRegistry
     */
    private static function collectMediaFromOption(string $optName, mixed $value, array &$mediaRegistry): void
    {
        $cleanBase = ltrim(explode('|', $optName)[0], '_');
        if (!ConfigRegistry::isMediaField($cleanBase)) {
            return;
        }

        $attachmentIds = [];
        if (is_numeric($value) && (int) $value > 0) {
            $attachmentIds[] = (int) $value;
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (is_numeric($item) && (int) $item > 0) {
                    $attachmentIds[] = (int) $item;
                }
            }
        }

        foreach ($attachmentIds as $attId) {
            if (isset($mediaRegistry[$attId])) {
                continue;
            }

            $url = wp_get_attachment_url($attId);
            if (!$url) {
                continue;
            }

            $filePath = get_attached_file($attId);
            $filename = $filePath ? basename($filePath) : basename($url);

            $mediaRegistry[$attId] = [
                'url'      => $url,
                'filename' => $filename,
                'alt'      => (string) get_post_meta($attId, '_wp_attachment_image_alt', true),
                'title'    => (string) get_the_title($attId),
                'mime'     => (string) get_post_mime_type($attId),
            ];
        }
    }

    /**
     * Lấy nhãn mô tả thân thiện cho một option name.
     */
    private static function getOptionLabel(string $optionName): string
    {
        $clean = ltrim($optionName, '_');
        return ucwords(str_replace(['_', '-'], ' ', $clean));
    }

    /**
     * Xử lý tải về tệp JSON trực tiếp qua admin-post.php.
     */
    public function handleExportDownload(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer(self::NONCE_ACTION, '_nonce')) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'), 403);
        }

        $includeMedia   = !empty($_POST['include_media']);
        $includeSecrets = !empty($_POST['include_secrets']);

        $selectedSections = [
            ConfigRegistry::SECTION_THEME_OPTIONS     => !empty($_POST['section_theme_options']),
            ConfigRegistry::SECTION_SECURITY          => !empty($_POST['section_security']),
            ConfigRegistry::SECTION_TOOLS             => !empty($_POST['section_tools']),
            ConfigRegistry::SECTION_NOTIFICATIONS     => !empty($_POST['section_notifications']),
            ConfigRegistry::SECTION_CONTACT_FORMS     => !empty($_POST['section_contact_forms']),
            ConfigRegistry::SECTION_CUSTOM_POST_TYPES => !empty($_POST['section_custom_post_types']),
            ConfigRegistry::SECTION_SEO               => !empty($_POST['section_seo']),
        ];

        while (ob_get_level()) {
            ob_end_clean();
        }

        $data = self::generateExport($selectedSections, $includeMedia, $includeSecrets);
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $host   = sanitize_title(wp_parse_url(get_home_url(), PHP_URL_HOST) ?: 'site');
        $date   = wp_date('Ymd-His');
        $suffix = $includeSecrets ? '-CO-MAT-KHAU' : '';
        $filename = sprintf('laca-site-config-%s-%s%s.json', $host, $date, $suffix);

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen((string) $json));

        echo $json;
        exit;
    }
}

