<?php

namespace App\Settings\LacaTools\Management\SiteConfig;

use App\Helpers\Crypto;
use App\Databases\ContactFormTable;
use App\Features\LacaSeo\ImportExport\Importer as SeoImporter;
use App\Settings\LacaTools\Management\ConfigChangeLog;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SiteConfigImporter
 *
 * Xử lý việc nhập bộ cấu hình website an toàn, qua 2 bước:
 * 1. Tải lên & Phân tích Xem trước (Dry Run) — không ghi bất kỳ dữ liệu nào vào database.
 * 2. Tự động sao lưu & Áp dụng thay đổi — thay domain, tải ảnh, thêm form mẫu, nhập SEO và cập nhật cron/rewrite rules.
 * 3. Hỗ trợ khôi phục an toàn về trạng thái trước khi nhập.
 *
 * @package App\Settings\LacaTools\Management\SiteConfig
 */
class SiteConfigImporter
{
    public const NONCE_ACTION    = 'laca_import_site_config_nonce';
    public const BACKUPS_OPTION  = 'laca_site_config_backups';
    public const MAX_BACKUPS     = 5;
    public const TRANSIENT_PREFIX = 'laca_import_preview_';

    public function register(): void
    {
        add_action('wp_ajax_laca_site_config_upload_preview', [$this, 'ajaxUploadPreview']);
        add_action('wp_ajax_laca_site_config_apply',          [$this, 'ajaxApplyConfig']);
        add_action('wp_ajax_laca_site_config_restore',        [$this, 'ajaxRestoreBackup']);
    }

    /**
     * AJAX: Bước 1 - Nhận file JSON, xác thực và trả về phân tích xem trước (Dry run).
     */
    public function ajaxUploadPreview(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTION, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền hoặc phiên làm việc hết hạn.', 'laca'), 403);
        }

        if (empty($_FILES['config_file']['tmp_name'])) {
            wp_send_json_error(esc_html__('Vui lòng chọn tệp cấu hình JSON để tải lên.', 'laca'), 400);
        }

        $file = $_FILES['config_file'];

        // Kiểm tra kích thước (tối đa 5 MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(esc_html__('Tệp cấu hình vượt quá dung lượng cho phép (tối đa 5 MB).', 'laca'), 400);
        }

        $rawContent = file_get_contents($file['tmp_name']);
        if (!$rawContent) {
            wp_send_json_error(esc_html__('Không thể đọc nội dung tệp đã tải lên.', 'laca'), 400);
        }

        $json = json_decode($rawContent, true);
        if (!is_array($json)) {
            wp_send_json_error(esc_html__('Tệp tải lên không phải là định dạng JSON hợp lệ.', 'laca'), 400);
        }

        // Chuẩn hoá payload nếu là file cũ của ConfigExportImport hoặc file Laca SEO
        $payload = self::normalizePayload($json);
        if ($payload === null) {
            wp_send_json_error(esc_html__('Định dạng tệp không được hỗ trợ. Vui lòng sử dụng tệp xuất từ Laca Admin.', 'laca'), 400);
        }

        // Tạo token và lưu tạm vào transient (30 phút)
        $token = wp_generate_password(24, false);
        $transientKey = self::TRANSIENT_PREFIX . get_current_user_id() . '_' . $token;
        set_transient($transientKey, [
            'payload'  => $payload,
            'filename' => sanitize_file_name($file['name']),
            'time'     => time(),
        ], 1800);

        // Phân tích Xem trước (Preview Analysis)
        $analysis = self::analyzePayload($payload);

        wp_send_json_success([
            'token'    => $token,
            'filename' => sanitize_file_name($file['name']),
            'source'   => $payload['source'] ?? [],
            'analysis' => $analysis,
        ]);
    }

    /**
     * Chuẩn hoá các định dạng tệp khác nhau về chuẩn laca-site-config v1.
     *
     * @param array $json
     * @return array|null
     */
    public static function normalizePayload(array $json): ?array
    {
        // 1. Đúng chuẩn laca-site-config
        if (($json['format'] ?? '') === 'laca-site-config') {
            return $json;
        }

        // 2. Tệp cũ của ConfigExportImport (có khoá config)
        if (isset($json['config']) && is_array($json['config'])) {
            $sections = [
                ConfigRegistry::SECTION_THEME_OPTIONS     => [],
                ConfigRegistry::SECTION_SECURITY          => [],
                ConfigRegistry::SECTION_TOOLS             => [],
                ConfigRegistry::SECTION_NOTIFICATIONS     => [],
                ConfigRegistry::SECTION_CUSTOM_POST_TYPES => [],
            ];

            foreach ($json['config'] as $optName => $val) {
                $sec = ConfigRegistry::getOptionSection((string) $optName) ?: ConfigRegistry::SECTION_THEME_OPTIONS;
                $sections[$sec][$optName] = $val;
            }

            return [
                'format'           => 'laca-site-config',
                'format_version'   => 1,
                'exported_at'      => $json['exported_at'] ?? current_time('c'),
                'source'           => [
                    'home_url'      => $json['site_url'] ?? '',
                    'theme'         => '',
                    'theme_version' => '',
                    'wp_version'    => '',
                    'locale'        => '',
                ],
                'includes_secrets' => false,
                'sections'         => $sections,
                'media'            => [],
                'manual_fields'    => [],
            ];
        }

        // 3. Tệp xuất của Laca SEO (có khoá plugin = laca-seo)
        if (($json['plugin'] ?? '') === 'laca-seo' && isset($json['data']) && is_array($json['data'])) {
            return [
                'format'           => 'laca-site-config',
                'format_version'   => 1,
                'exported_at'      => $json['exported_at'] ?? current_time('c'),
                'source'           => [
                    'home_url'      => $json['site_url'] ?? '',
                    'theme'         => '',
                    'theme_version' => $json['version'] ?? '',
                    'wp_version'    => '',
                    'locale'        => '',
                ],
                'includes_secrets' => false,
                'sections'         => [
                    ConfigRegistry::SECTION_SEO => $json,
                ],
                'media'            => [],
                'manual_fields'    => [],
            ];
        }

        return null;
    }

    /**
     * Phân tích các mục trong payload so sánh với database hiện tại.
     *
     * @param array $payload
     * @return array
     */
    public static function analyzePayload(array $payload): array
    {
        $sections = $payload['sections'] ?? [];
        $analysis = [
            'summary' => [
                'new'     => 0,
                'diff'    => 0,
                'same'    => 0,
                'ignored' => 0,
            ],
            'warnings'       => [],
            'by_section'     => [],
            'ignored_items'  => [],
            'has_media'      => !empty($payload['media']),
            'media_count'    => count($payload['media'] ?? []),
            'has_secrets'    => !empty($payload['includes_secrets']),
        ];

        // 1. Cảnh báo tổng quát
        $currentTheme = get_template();
        $sourceTheme  = (string) ($payload['source']['theme'] ?? '');
        if ($sourceTheme !== '' && $sourceTheme !== $currentTheme) {
            $analysis['warnings'][] = sprintf(
                /* translators: 1: source theme, 2: current theme */
                esc_html__('Tệp cấu hình được xuất từ giao diện "%1$s", website hiện tại đang dùng "%2$s". Các thiết lập trùng tên vẫn sẽ được áp dụng an toàn.', 'laca'),
                esc_html($sourceTheme),
                esc_html($currentTheme)
            );
        }

        if (!empty($payload['includes_secrets'])) {
            $analysis['warnings'][] = esc_html__('Tệp này chứa mật khẩu hoặc khoá API. Chỉ chọn áp dụng mật khẩu nếu bạn tin cậy nguồn gốc của tệp.', 'laca');
        }

        // 2. Duyệt qua từng section options
        foreach ($sections as $sec => $items) {
            if ($sec === ConfigRegistry::SECTION_CONTACT_FORMS) {
                $formCount = is_array($items) ? count($items) : 0;
                $analysis['by_section'][$sec] = [
                    'label' => esc_html__('Form liên hệ mẫu', 'laca'),
                    'count' => $formCount,
                    'items' => [],
                ];
                continue;
            }

            if ($sec === ConfigRegistry::SECTION_SEO) {
                $seoCount = !empty($items['data']) && is_array($items['data']) ? count($items['data']) : 0;
                $analysis['by_section'][$sec] = [
                    'label' => esc_html__('Cấu hình Laca SEO', 'laca'),
                    'count' => $seoCount,
                    'items' => [],
                ];
                continue;
            }

            if (!is_array($items)) {
                continue;
            }

            $secData = [
                'label'   => self::getSectionLabel($sec),
                'new'     => 0,
                'diff'    => 0,
                'same'    => 0,
                'ignored' => 0,
                'items'   => [],
            ];

            foreach ($items as $optName => $incomingVal) {
                // Kiểm tra blacklist cứng
                if (ConfigRegistry::isNeverAllowed((string) $optName)) {
                    $analysis['summary']['ignored']++;
                    $secData['ignored']++;
                    $analysis['ignored_items'][] = [
                        'option' => esc_html((string) $optName),
                        'reason' => esc_html__('Danh tính site hoặc trạng thái chạy nền (bị cấm xuất/nhập)', 'laca'),
                    ];
                    continue;
                }

                // Kiểm tra có thuộc Sổ đăng ký không
                $assignedSec = ConfigRegistry::getOptionSection((string) $optName);
                if ($assignedSec === null) {
                    $analysis['summary']['ignored']++;
                    $secData['ignored']++;
                    $analysis['ignored_items'][] = [
                        'option' => esc_html((string) $optName),
                        'reason' => esc_html__('Không thuộc danh sách trắng Sổ đăng ký cấu hình', 'laca'),
                    ];
                    continue;
                }

                // Cảnh báo nếu đổi login slug
                if ($optName === 'laca_login_slug') {
                    $analysis['warnings'][] = sprintf(
                        /* translators: %s: new login slug */
                        esc_html__('Chú ý: Đường dẫn đăng nhập quản trị sẽ đổi thành "%s". Hãy ghi nhớ đường dẫn này!', 'laca'),
                        esc_html((string) $incomingVal)
                    );
                }

                // So sánh với giá trị hiện tại
                $exists = (get_option($optName, '__NOT_EXIST__') !== '__NOT_EXIST__');
                $currentVal = $exists ? get_option($optName) : null;

                if (!$exists) {
                    $status = 'new';
                    $secData['new']++;
                    $analysis['summary']['new']++;
                } elseif (wp_json_encode($currentVal) === wp_json_encode($incomingVal)) {
                    $status = 'same';
                    $secData['same']++;
                    $analysis['summary']['same']++;
                } else {
                    $status = 'diff';
                    $secData['diff']++;
                    $analysis['summary']['diff']++;
                }

                $secData['items'][] = [
                    'option'  => $optName,
                    'status'  => $status,
                    'current' => $currentVal,
                    'incoming'=> $incomingVal,
                ];
            }

            $analysis['by_section'][$sec] = $secData;
        }

        return $analysis;
    }

    /**
     * AJAX: Bước 2 - Áp dụng cấu hình đã xem trước vào website.
     */
    public function ajaxApplyConfig(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTION, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền hoặc phiên làm việc hết hạn.', 'laca'), 403);
        }

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        if ($token === '') {
            wp_send_json_error(esc_html__('Mã phiên xem trước không hợp lệ.', 'laca'), 400);
        }

        $transientKey = self::TRANSIENT_PREFIX . get_current_user_id() . '_' . $token;
        $saved = get_transient($transientKey);
        if (!is_array($saved) || empty($saved['payload'])) {
            wp_send_json_error(esc_html__('Phiên xem trước đã hết hạn. Vui lòng tải lại tệp cấu hình.', 'laca'), 400);
        }

        $payload = $saved['payload'];
        $filename = $saved['filename'] ?? 'site-config.json';

        // Đọc các tuỳ chọn từ client
        $replaceDomain   = !empty($_POST['replace_domain']);
        $importMedia     = !empty($_POST['import_media']);
        $importSecrets   = !empty($_POST['import_secrets']);
        $formDuplicateAction = sanitize_key($_POST['form_duplicate_action'] ?? 'skip'); // skip | overwrite | duplicate

        $activeSections = [
            ConfigRegistry::SECTION_THEME_OPTIONS     => isset($_POST['apply_theme_options']) ? !empty($_POST['apply_theme_options']) : true,
            ConfigRegistry::SECTION_SECURITY          => isset($_POST['apply_security']) ? !empty($_POST['apply_security']) : true,
            ConfigRegistry::SECTION_TOOLS             => isset($_POST['apply_tools']) ? !empty($_POST['apply_tools']) : true,
            ConfigRegistry::SECTION_NOTIFICATIONS     => isset($_POST['apply_notifications']) ? !empty($_POST['apply_notifications']) : true,
            ConfigRegistry::SECTION_CONTACT_FORMS     => isset($_POST['apply_contact_forms']) ? !empty($_POST['apply_contact_forms']) : true,
            ConfigRegistry::SECTION_CUSTOM_POST_TYPES => isset($_POST['apply_custom_post_types']) ? !empty($_POST['apply_custom_post_types']) : true,
            ConfigRegistry::SECTION_SEO               => isset($_POST['apply_seo']) ? !empty($_POST['apply_seo']) : true,
        ];

        // 1. TẠO BẢN SAO LƯU TỰ ĐỘNG (Snapshot Backup)
        $backupId = self::createBackupSnapshot($payload, $filename);

        $sourceUrl  = $payload['source']['home_url'] ?? '';
        $currentUrl = get_home_url();

        // 2. XỬ LÝ TẢI MEDIA (Nếu được bật)
        $idMap = [];
        $mediaResult = [
            'downloaded' => 0,
            'reused'     => 0,
            'failed'     => 0,
        ];

        if ($importMedia && !empty($payload['media']) && is_array($payload['media'])) {
            $idMap = self::processMediaImport($payload['media'], $mediaResult);
        }

        $appliedCounts = [
            'options'      => 0,
            'forms'        => 0,
            'seo'          => 0,
            'media'        => $mediaResult['downloaded'] + $mediaResult['reused'],
        ];

        $manualReminders = [];

        // 3. GHI CÁC SECTION OPTIONS (theme_options, security, tools, notifications, cpt)
        $sections = $payload['sections'] ?? [];
        foreach ($sections as $sec => $items) {
            if (empty($activeSections[$sec]) || in_array($sec, [ConfigRegistry::SECTION_CONTACT_FORMS, ConfigRegistry::SECTION_SEO], true)) {
                continue;
            }

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $optName => $val) {
                // Kiểm tra an toàn bắt buộc
                if (ConfigRegistry::isNeverAllowed((string) $optName)) {
                    continue;
                }
                if (ConfigRegistry::getOptionSection((string) $optName) === null) {
                    continue;
                }

                // Kiểm tra Secret
                if (ConfigRegistry::isSecret((string) $optName)) {
                    if (!$importSecrets) {
                        $manualReminders[] = [
                            'option' => esc_html((string) $optName),
                            'reason' => esc_html__('Mật khẩu/khoá API không được nhập, cần điền lại thủ công', 'laca'),
                        ];
                        continue;
                    }
                }

                // Thay thế Domain nếu bật
                if ($replaceDomain && $sourceUrl !== '' && $sourceUrl !== $currentUrl) {
                    if (class_exists(SeoImporter::class)) {
                        $val = SeoImporter::deepReplaceString($sourceUrl, $currentUrl, $val);
                    }
                }

                // Thay thế ID Media nếu có trong map
                if (!empty($idMap)) {
                    $cleanBase = ltrim(explode('|', (string) $optName)[0], '_');
                    if (ConfigRegistry::isMediaField($cleanBase)) {
                        $val = self::replaceMediaIds($val, $idMap);
                    }
                }

                // Xử lý bảo vệ đặc thù: Maintenance mode LUÔN NHẬP Ở TRẠNG THÁI TẮT (0)
                if ($optName === 'laca_maintenance_mode') {
                    $val = 0;
                }

                // Mã hoá lại secret theo ĐÚNG cơ chế mà nơi đọc option đó dùng (đa số secret lưu thô; mã hoá bừa sẽ làm hỏng tính năng)
                if (ConfigRegistry::isSecret((string) $optName) && is_string($val) && $val !== '') {
                    if ($optName === '_smtp_password') {
                        if (class_exists(Crypto::class)) {
                            if (!Crypto::isTagged($val)) {
                                $encrypted = Crypto::encryptTagged($val);
                                if ($encrypted !== '') {
                                    $val = $encrypted;
                                }
                            } elseif (Crypto::needsRehash($val)) {
                                $plain = Crypto::decryptTagged($val);
                                if ($plain !== null) {
                                    $encrypted = Crypto::encryptTagged($plain);
                                    if ($encrypted !== '') {
                                        $val = $encrypted;
                                    }
                                }
                            }
                        }
                    } elseif (in_array($optName, ConfigRegistry::POST_AUDIO_ENCRYPTED, true)
                        && class_exists(\App\Features\PostAudio\PostAudioSecurity::class)) {
                        $val = \App\Features\PostAudio\PostAudioSecurity::encrypt($val);
                    }
                }

                // Discord: giữ metadata kênh + deploy_key của site đích (webhook thật không đi theo file)
                if ($optName === 'laca_discord_hub' && is_array($val)) {
                    $current = get_option('laca_discord_hub', []);
                    $current = is_array($current) ? $current : [];
                    unset($val['channels'], $val['deploy_key']);
                    foreach (['channels', 'deploy_key'] as $keepKey) {
                        if (array_key_exists($keepKey, $current)) {
                            $val[$keepKey] = $current[$keepKey];
                        }
                    }
                }

                // Ghi vào database
                update_option($optName, $val);
                $appliedCounts['options']++;
            }
        }

        // 4. GHI FORM LIÊN HỆ MẪU (Contact Forms)
        $createdFormIds = [];
        if (!empty($activeSections[ConfigRegistry::SECTION_CONTACT_FORMS]) && !empty($sections[ConfigRegistry::SECTION_CONTACT_FORMS]) && class_exists(ContactFormTable::class)) {
            $forms = (array) $sections[ConfigRegistry::SECTION_CONTACT_FORMS];
            foreach ($forms as $f) {
                $formName = sanitize_text_field((string) ($f['name'] ?? ''));
                if ($formName === '') {
                    continue;
                }

                // Kiểm tra trùng tên
                $existing = self::findFormByName($formName);
                if ($existing) {
                    if ($formDuplicateAction === 'skip') {
                        continue;
                    } elseif ($formDuplicateAction === 'overwrite') {
                        ContactFormTable::updateForm((int) $existing['id'], [
                            'fields'               => $f['fields'] ?? [],
                            'notify_email'         => $f['notify_email'] ?? '',
                            'email_admin_subject'  => $f['email_admin_subject'] ?? '',
                            'email_admin_body'     => $f['email_admin_body'] ?? '',
                            'email_user_subject'   => $f['email_user_subject'] ?? '',
                            'email_user_body'      => $f['email_user_body'] ?? '',
                            'style_settings'       => $f['style_settings'] ?? [],
                            'is_active'            => $f['is_active'] ?? 1,
                        ]);
                        $appliedCounts['forms']++;
                        continue;
                    } else { // duplicate
                        $formName .= ' (' . esc_html__('Bản sao', 'laca') . ')';
                    }
                }

                $newFormId = ContactFormTable::insertForm([
                    'name'                 => $formName,
                    'fields'               => $f['fields'] ?? [],
                    'notify_email'         => $f['notify_email'] ?? '',
                    'email_admin_subject'  => $f['email_admin_subject'] ?? '',
                    'email_admin_body'     => $f['email_admin_body'] ?? '',
                    'email_user_subject'   => $f['email_user_subject'] ?? '',
                    'email_user_body'      => $f['email_user_body'] ?? '',
                    'style_settings'       => $f['style_settings'] ?? [],
                    'is_active'            => $f['is_active'] ?? 1,
                ]);

                if ($newFormId) {
                    $createdFormIds[] = $newFormId;
                    $appliedCounts['forms']++;
                }
            }

            // Cập nhật lại backup snapshot để ghi nhận các form_id đã tạo
            if (!empty($createdFormIds)) {
                self::appendCreatedFormIdsToBackup($backupId, $createdFormIds);
            }
        }

        // 5. GHI LACA SEO
        if (!empty($activeSections[ConfigRegistry::SECTION_SEO]) && !empty($sections[ConfigRegistry::SECTION_SEO]) && class_exists(SeoImporter::class)) {
            $seoPayload = $sections[ConfigRegistry::SECTION_SEO];
            $seoRes = SeoImporter::importJson($seoPayload, $replaceDomain, true);
            if (!empty($seoRes['success'])) {
                $appliedCounts['seo'] = 1;
            }
        }

        // 6. HẬU XỬ LÝ & ĐỒNG BỘ HỆ THỐNG
        // Đồng bộ cron DatabaseCleaner
        self::syncDatabaseCleanerCron();

        // Flush rewrite rules
        flush_rewrite_rules(false);

        // Xoá transient cache kết quả
        delete_transient('laca_security_audit_result');
        delete_transient('laca_storage_health');
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Xoá transient xem trước tạm
        delete_transient($transientKey);

        // Ghi nhật ký vào ConfigChangeLog
        if (class_exists(ConfigChangeLog::class)) {
            ConfigChangeLog::log(
                'site_config_import',
                '',
                sprintf('Tệp %s — Đã áp dụng %d thiết lập', $filename, $appliedCounts['options'] + $appliedCounts['forms'])
            );
        }

        wp_send_json_success([
            'message'         => esc_html__('Đã áp dụng bộ cấu hình site thành công!', 'laca'),
            'counts'          => $appliedCounts,
            'backup_id'       => $backupId,
            'manual_reminders'=> $manualReminders,
            'new_login_slug'  => get_option('laca_login_slug', ''),
            'is_custom_login' => (bool) get_option('laca_enable_custom_login', 0),
        ]);
    }

    /**
     * Tự động sao lưu snapshot trước khi áp dụng cấu hình mới.
     *
     * @param array $payload
     * @param string $sourceFile
     * @return string ID của bản sao lưu
     */
    private static function createBackupSnapshot(array $payload, string $sourceFile): string
    {
        $backupId = 'backup_' . time() . '_' . wp_generate_password(6, false);
        $optionsSnapshot = [];

        $sections = $payload['sections'] ?? [];
        foreach ($sections as $sec => $items) {
            if (!is_array($items) || in_array($sec, [ConfigRegistry::SECTION_CONTACT_FORMS, ConfigRegistry::SECTION_SEO], true)) {
                continue;
            }

            foreach (array_keys($items) as $optName) {
                if (ConfigRegistry::isNeverAllowed((string) $optName)) {
                    continue;
                }

                $exists = (get_option($optName, '__NOT_EXIST__') !== '__NOT_EXIST__');
                $val    = $exists ? get_option($optName) : null;

                $optionsSnapshot[$optName] = [
                    'exists' => $exists,
                    'value'  => $val,
                ];
            }
        }

        $snapshot = [
            'id'            => $backupId,
            'created_at'    => current_time('mysql'),
            'user_id'       => get_current_user_id(),
            'user_login'    => wp_get_current_user()->user_login,
            'source_file'   => $sourceFile,
            'source_site'   => $payload['source']['home_url'] ?? '',
            'options'       => $optionsSnapshot,
            'forms_created' => [],
        ];

        $backups = get_option(self::BACKUPS_OPTION, []);
        if (!is_array($backups)) {
            $backups = [];
        }

        array_unshift($backups, $snapshot);
        $backups = array_slice($backups, 0, self::MAX_BACKUPS);

        update_option(self::BACKUPS_OPTION, $backups, false);

        return $backupId;
    }

    /**
     * Bổ sung danh sách Form ID đã tạo vào bản sao lưu để phục vụ khôi phục.
     */
    private static function appendCreatedFormIdsToBackup(string $backupId, array $formIds): void
    {
        $backups = get_option(self::BACKUPS_OPTION, []);
        if (!is_array($backups)) {
            return;
        }

        foreach ($backups as &$b) {
            if (($b['id'] ?? '') === $backupId) {
                $b['forms_created'] = $formIds;
                break;
            }
        }

        update_option(self::BACKUPS_OPTION, $backups, false);
    }

    /**
     * Xử lý tải ảnh media từ URL gốc hoặc tái sử dụng file cùng tên.
     *
     * @param array $mediaItems
     * @param array &$mediaResult
     * @return array<int, int> Map [old_id => new_id]
     */
    private static function processMediaImport(array $mediaItems, array &$mediaResult): array
    {
        global $wpdb;
        $idMap = [];

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $processedCount = 0;
        foreach ($mediaItems as $oldId => $item) {
            if ($processedCount >= 30) {
                break; // Giới hạn tối đa 30 ảnh/lần nhập
            }
            $processedCount++;

            $url      = (string) ($item['url'] ?? '');
            $filename = sanitize_file_name((string) ($item['filename'] ?? basename($url)));
            if ($url === '' || !str_starts_with($url, 'http')) {
                $mediaResult['failed']++;
                continue;
            }

            // 1. Tìm xem attachment cùng filename đã tồn tại trên site chưa
            $existingId = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
                '%' . $wpdb->esc_like($filename)
            ));

            if ($existingId) {
                $idMap[(int) $oldId] = (int) $existingId;
                $mediaResult['reused']++;
                continue;
            }

            // 2. Tải về qua download_url
            $tmpFile = download_url($url, 20);
            if (is_wp_error($tmpFile)) {
                $mediaResult['failed']++;
                continue;
            }

            $fileArray = [
                'name'     => $filename,
                'tmp_name' => $tmpFile,
            ];

            $newAttId = media_handle_sideload($fileArray, 0, $item['title'] ?? '');
            if (is_wp_error($newAttId)) {
                @unlink($tmpFile);
                $mediaResult['failed']++;
                continue;
            }

            if (!empty($item['alt'])) {
                update_post_meta($newAttId, '_wp_attachment_image_alt', sanitize_text_field($item['alt']));
            }

            $idMap[(int) $oldId] = (int) $newAttId;
            $mediaResult['downloaded']++;
        }

        return $idMap;
    }

    /**
     * Thay thế ID media cũ thành ID mới trong giá trị cấu hình.
     */
    private static function replaceMediaIds(mixed $val, array $idMap): mixed
    {
        if (is_numeric($val) && isset($idMap[(int) $val])) {
            return $idMap[(int) $val];
        }

        if (is_array($val)) {
            $newArr = [];
            foreach ($val as $k => $v) {
                $newArr[$k] = self::replaceMediaIds($v, $idMap);
            }
            return $newArr;
        }

        return $val;
    }

    /**
     * Tìm form theo tên.
     */
    private static function findFormByName(string $name): ?array
    {
        global $wpdb;
        $table = ContactFormTable::getFormsTable();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE name = %s LIMIT 1", $name), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Đồng bộ lịch cron cho DatabaseCleaner.
     */
    private static function syncDatabaseCleanerCron(): void
    {
        $config = get_option('laca_db_cleaner_schedule', []);
        $nextRun = wp_next_scheduled('laca_db_cleaner_scheduled');

        if (empty($config['enabled'])) {
            if ($nextRun) {
                wp_unschedule_event($nextRun, 'laca_db_cleaner_scheduled');
            }
        } else {
            $freq = ($config['frequency'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly';
            if ($nextRun) {
                wp_unschedule_event($nextRun, 'laca_db_cleaner_scheduled');
            }
            wp_schedule_event(time() + 3600, $freq, 'laca_db_cleaner_scheduled');
        }
    }

    /**
     * AJAX: Khôi phục website về trạng thái của bản sao lưu trước đó.
     */
    public function ajaxRestoreBackup(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTION, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền hoặc phiên làm việc hết hạn.', 'laca'), 403);
        }

        $backupId = sanitize_text_field(wp_unslash($_POST['backup_id'] ?? ''));
        if ($backupId === '') {
            wp_send_json_error(esc_html__('Mã bản sao lưu không hợp lệ.', 'laca'), 400);
        }

        $backups = get_option(self::BACKUPS_OPTION, []);
        if (!is_array($backups)) {
            wp_send_json_error(esc_html__('Không tìm thấy danh sách bản sao lưu.', 'laca'), 404);
        }

        $target = null;
        foreach ($backups as $b) {
            if (($b['id'] ?? '') === $backupId) {
                $target = $b;
                break;
            }
        }

        if (!$target) {
            wp_send_json_error(esc_html__('Không tìm thấy bản sao lưu được chọn.', 'laca'), 404);
        }

        // 1. Khôi phục toàn bộ Options
        $restoredCount = 0;
        if (!empty($target['options']) && is_array($target['options'])) {
            foreach ($target['options'] as $optName => $data) {
                if (ConfigRegistry::isNeverAllowed((string) $optName)) {
                    continue;
                }

                if (!empty($data['exists'])) {
                    update_option($optName, $data['value']);
                } else {
                    delete_option($optName);
                }
                $restoredCount++;
            }
        }

        // 2. Xoá các Form liên hệ do lần nhập đó tạo ra
        if (!empty($target['forms_created']) && is_array($target['forms_created']) && class_exists(ContactFormTable::class)) {
            foreach ($target['forms_created'] as $fId) {
                ContactFormTable::deleteForm((int) $fId);
            }
        }

        // 3. Khôi phục SEO nếu có
        if (class_exists(SeoImporter::class)) {
            $seoBackups = get_option(SeoImporter::BACKUPS_OPTION, []);
            if (!empty($seoBackups) && is_array($seoBackups)) {
                $latestSeo = $seoBackups[0]['id'] ?? '';
                if ($latestSeo) {
                    SeoImporter::restoreBackup($latestSeo);
                }
            }
        }

        // 4. Đồng bộ hệ thống
        self::syncDatabaseCleanerCron();
        flush_rewrite_rules(false);
        delete_transient('laca_security_audit_result');
        delete_transient('laca_storage_health');

        // Ghi nhật ký
        if (class_exists(ConfigChangeLog::class)) {
            ConfigChangeLog::log(
                'site_config_restore',
                '',
                sprintf('Đã khôi phục bản sao lưu %s (%s) — %d mục', $backupId, $target['source_file'] ?? '', $restoredCount)
            );
        }

        wp_send_json_success([
            'message'        => esc_html__('Đã khôi phục thành công website về trạng thái trước đó!', 'laca'),
            'restored_count' => $restoredCount,
        ]);
    }

    /**
     * Lấy danh sách các bản sao lưu đã lưu.
     */
    public static function getBackups(): array
    {
        $backups = get_option(self::BACKUPS_OPTION, []);
        return is_array($backups) ? $backups : [];
    }

    /**
     * Nhãn hiển thị cho section.
     */
    private static function getSectionLabel(string $section): string
    {
        return match ($section) {
            ConfigRegistry::SECTION_THEME_OPTIONS     => esc_html__('Giao diện & Laca Theme', 'laca'),
            ConfigRegistry::SECTION_SECURITY          => esc_html__('Bảo mật & Đăng nhập', 'laca'),
            ConfigRegistry::SECTION_TOOLS             => esc_html__('Công cụ & Tối ưu', 'laca'),
            ConfigRegistry::SECTION_NOTIFICATIONS     => esc_html__('Thông báo (Discord/Zalo/PM)', 'laca'),
            ConfigRegistry::SECTION_CONTACT_FORMS     => esc_html__('Mẫu Form liên hệ', 'laca'),
            ConfigRegistry::SECTION_CUSTOM_POST_TYPES => esc_html__('Custom Post Types động', 'laca'),
            ConfigRegistry::SECTION_SEO               => esc_html__('Laca SEO', 'laca'),
            default                                   => esc_html__('Cấu hình khác', 'laca'),
        };
    }
}
