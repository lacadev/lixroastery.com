<?php

namespace App\Settings\LacaTools\Management;

use App\Settings\LacaTools\Management\SiteConfig\ConfigRegistry;
use App\Settings\LacaTools\Management\SiteConfig\SiteConfigExporter;
use App\Settings\LacaTools\Management\SiteConfig\SiteConfigImporter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trang quản trị Laca Admin → Cấu hình (slug: laca-config).
 *
 * Gồm 2 tab:
 * - Xuất / nhập (transfer): Xuất file JSON và nhập cấu hình có bước Xem trước (dry_run).
 * - Nhật ký thay đổi (log): Bảng lịch sử thay đổi cấu hình có lọc theo tên option và phân trang 20 dòng/trang.
 *
 * @package App\Settings\LacaTools\Management
 */
class ConfigPage
{
    const SLUG = 'laca-config';
    const CAP  = 'manage_options';
    const PER_PAGE = 20;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 20);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'laca-admin',
            esc_html__('Cấu hình', 'laca'),
            esc_html__('Cấu hình', 'laca'),
            self::CAP,
            self::SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $activeTab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'transfer';
        if (!in_array($activeTab, ['transfer', 'log'], true)) {
            $activeTab = 'transfer';
        }

        $baseUrl = admin_url('admin.php?page=' . self::SLUG);
        ?>
        <div class="wrap laca-config-wrap">
            <h1><?php esc_html_e('Cấu hình', 'laca'); ?></h1>
            <p class="description">
                <?php esc_html_e('Sao lưu, khôi phục cấu hình giao diện và theo dõi lịch sử thay đổi.', 'laca'); ?>
            </p>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'transfer', $baseUrl)); ?>"
                   class="nav-tab <?php echo $activeTab === 'transfer' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Xuất / nhập', 'laca'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'log', $baseUrl)); ?>"
                   class="nav-tab <?php echo $activeTab === 'log' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Nhật ký thay đổi', 'laca'); ?>
                </a>
            </nav>

            <div class="laca-config-tab-content">
                <?php
                if ($activeTab === 'log') {
                    $this->renderLogTab($baseUrl);
                } else {
                    $this->renderTransferTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function renderTransferTab(): void
    {
        $nonceImport = wp_create_nonce(SiteConfigImporter::NONCE_ACTION);
        $backups     = SiteConfigImporter::getBackups();

        $i18n = [
            'previewBtn'         => __('Tải lên & Xem trước', 'laca'),
            'previewing'         => __('Đang phân tích...', 'laca'),
            'selectFileErr'      => __('Vui lòng chọn file cấu hình .json trước.', 'laca'),
            'fileTooLarge'       => __('File vượt quá giới hạn 5MB.', 'laca'),
            'invalidJson'        => __('File không phải JSON hợp lệ.', 'laca'),
            'statusNew'          => __('Mới', 'laca'),
            'statusDiff'         => __('Khác', 'laca'),
            'statusSame'         => __('Giống', 'laca'),
            'statusIgnored'      => __('Bị bỏ qua', 'laca'),
            /* translators: %d: number of changes to apply */
            'applyBtnTpl'        => __('Áp dụng %d thay đổi', 'laca'),
            /* translators: %d: number of changes to apply */
            'confirmApply'       => __('Bạn có chắc muốn áp dụng %d thay đổi cấu hình này không? Hệ thống sẽ tự động tạo bản sao lưu trước khi ghi đè.', 'laca'),
            'applying'           => __('Đang áp dụng cấu hình...', 'laca'),
            'applyingSteps'      => [
                __('Đang sao lưu và cập nhật cài đặt...', 'laca'),
                __('Đang xử lý ảnh media và mẫu form...', 'laca'),
                __('Đang nhập cấu hình Laca SEO...', 'laca'),
                __('Đang đồng bộ cache và hệ thống...', 'laca'),
            ],
            'confirmRestore'     => __('Bạn có chắc muốn khôi phục website về bản sao lưu này không? Các tuỳ chọn sẽ được hoàn tác về giá trị cũ.', 'laca'),
            'restoring'          => __('Đang khôi phục...', 'laca'),
            'noDiffRows'         => __('Tất cả option trong file đều giống với cấu hình hiện tại.', 'laca'),
            /* translators: 1: new count, 2: changed count, 3: same count, 4: ignored count */
            'summaryTpl'         => __('Mới: %1$d · Khác: %2$d · Giống: %3$d · Bị bỏ qua: %4$d', 'laca'),
            'errorGeneric'       => __('Lỗi kết nối máy chủ.', 'laca'),
            'manualRemindHeader' => __('Các mục cần kiểm tra hoặc chọn lại thủ công:', 'laca'),
            /* translators: 1: source domain URL, 2: current site URL */
            'replaceDomainTpl'   => __('Thay domain cũ %1$s bằng domain site này %2$s', 'laca'),
            'warningsHeader'     => __('Lưu ý quan trọng trước khi áp dụng:', 'laca'),
            'appliedSuccess'     => __('Đã áp dụng thành công!', 'laca'),
            /* translators: 1: options count, 2: forms count */
            'appliedCountsTpl'   => __('Đã ghi: %1$d option · %2$d form liên hệ', 'laca'),
            'appliedSeoSuffix'   => __(' · Laca SEO', 'laca'),
            /* translators: 1: downloaded count, 2: reused count, 3: failed count */
            'appliedMediaTpl'    => __('Ảnh: %1$d tải mới, %2$d tái sử dụng, %3$d lỗi', 'laca'),
            'newLoginUrlLabel'   => __('Đường dẫn đăng nhập trang quản trị mới:', 'laca'),
            'restoreSuccess'     => __('Khôi phục thành công!', 'laca'),
        ];
        ?>
        <div class="laca-settings-stack">
            <!-- Card 1: Xuất bộ cấu hình -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <div class="laca-card__title"><?php esc_html_e('Xuất bộ cấu hình site', 'laca'); ?></div>
                    <p class="laca-card__desc">
                        <?php esc_html_e('Dùng khi dựng site mới: xuất ở site mẫu, nhập ở site mới. Chỉ xuất các thiết lập được chuẩn hoá, không kèm danh tính site hay trạng thái chạy nền.', 'laca'); ?>
                    </p>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="laca-site-cfg-export-form">
                    <input type="hidden" name="action" value="<?php echo esc_attr(SiteConfigExporter::ACTION_DOWNLOAD); ?>">
                    <?php wp_nonce_field(SiteConfigExporter::NONCE_ACTION, '_nonce'); ?>

                    <div class="laca-card__body">
                        <div class="laca-field">
                            <span class="laca-field__label"><?php esc_html_e('Chọn các phần cấu hình cần xuất:', 'laca'); ?></span>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:10px;margin-top:8px;">
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="section_theme_options" value="1" checked>
                                    <span><?php esc_html_e('Giao diện & Laca Theme', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="section_security" value="1" checked>
                                    <span><?php esc_html_e('Bảo mật & Đăng nhập', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="section_tools" value="1" checked>
                                    <span><?php esc_html_e('Công cụ & Tối ưu', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="section_notifications" value="1" checked>
                                    <span><?php esc_html_e('Thông báo (Discord/Zalo/PM)', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="section_contact_forms" value="1" checked>
                                    <span><?php esc_html_e('Mẫu Form liên hệ', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="section_custom_post_types" value="1" checked>
                                    <span><?php esc_html_e('Custom Post Types động', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="section_seo" value="1" checked>
                                    <span><?php esc_html_e('Laca SEO', 'laca'); ?></span>
                                </label>
                            </div>
                        </div>

                        <hr style="border:0;border-top:1px solid var(--laca-border, #e2e8f0);margin:16px 0;">

                        <div class="laca-field">
                            <span class="laca-field__label"><?php esc_html_e('Tuỳ chọn xuất nâng cao:', 'laca'); ?></span>
                            <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="include_media" value="1" checked>
                                    <span><?php esc_html_e('Kèm thông tin ảnh (logo, ảnh mặc định, favicon...)', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="include_secrets" value="1" id="laca-cfg-export-secrets-chk">
                                    <span><?php esc_html_e('Kèm mật khẩu và khoá API (SMTP, Discord, reCAPTCHA...)', 'laca'); ?></span>
                                </label>
                            </div>

                            <div id="laca-cfg-secret-warning" class="laca-alert laca-alert--danger" style="display:none;margin-top:12px;">
                                <?php esc_html_e('Cảnh báo bảo mật: File xuất sẽ chứa mật khẩu và khoá API dạng rõ. Chỉ tải về máy cá nhân an toàn và tuyệt đối không chia sẻ file này công khai.', 'laca'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="laca-card__footer">
                        <button type="submit" class="laca-btn laca-btn--primary" id="laca-cfg-export-submit-btn">
                            <span class="dashicons dashicons-download" style="margin-right:6px;vertical-align:middle;"></span>
                            <?php esc_html_e('Tải file cấu hình (.json)', 'laca'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card 2: Nhập bộ cấu hình -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <div class="laca-card__title"><?php esc_html_e('Nhập bộ cấu hình site', 'laca'); ?></div>
                    <p class="laca-card__desc">
                        <?php esc_html_e('Chọn file JSON bộ cấu hình đã xuất để xem trước các thay đổi trước khi áp dụng.', 'laca'); ?>
                    </p>
                </div>
                <div class="laca-card__body">
                    <div class="laca-field">
                        <label class="laca-field__label" for="laca-cfg-file">
                            <?php esc_html_e('File bộ cấu hình (.json, tối đa 5MB)', 'laca'); ?>
                        </label>
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <input type="file" id="laca-cfg-file" class="laca-input" accept=".json,application/json" style="flex:1;min-width:260px;">
                            <button type="button" class="laca-btn laca-btn--secondary" id="laca-cfg-preview-btn">
                                <span class="dashicons dashicons-search" style="margin-right:6px;vertical-align:middle;"></span>
                                <?php esc_html_e('Tải lên & Xem trước', 'laca'); ?>
                            </button>
                        </div>
                        <p class="laca-field__desc">
                            <?php esc_html_e('Hỗ trợ chuẩn laca-site-config v1, file xuất cũ của ConfigExportImport và file xuất Laca SEO.', 'laca'); ?>
                        </p>
                    </div>

                    <div id="laca-cfg-import-alert" style="display:none;margin-top:12px;"></div>

                    <!-- Hộp Xem Trước (Preview Box) -->
                    <div id="laca-cfg-preview-box" style="display:none;margin-top:20px;border-top:1px solid var(--laca-border, #e2e8f0);padding-top:16px;">
                        <!-- Danh sách cảnh báo rủi ro cao -->
                        <div id="laca-cfg-warnings-container" style="display:none;margin-bottom:16px;"></div>

                        <!-- Tuỳ chọn khi Áp dụng -->
                        <div class="laca-card" style="background:var(--laca-bg-alt, #f8fafc);border:1px solid var(--laca-border, #e2e8f0);margin-bottom:16px;">
                            <div class="laca-card__header" style="padding:12px 16px;">
                                <div class="laca-card__title" style="font-size:14px;"><?php esc_html_e('Tuỳ chọn khi áp dụng', 'laca'); ?></div>
                            </div>
                            <div class="laca-card__body" style="padding:12px 16px;display:flex;flex-direction:column;gap:10px;">
                                <label class="laca-checkbox" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="laca-opt-replace-domain" checked>
                                    <span id="laca-opt-replace-domain-label"><?php esc_html_e('Thay domain cũ bằng domain site này trong toàn bộ chuỗi cấu hình', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" id="laca-opt-media-wrap" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="laca-opt-import-media" checked>
                                    <span><?php esc_html_e('Tải ảnh (logo, ảnh mặc định...) về thư viện Media (tự bỏ qua nếu ảnh cùng tên đã có)', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" id="laca-opt-seo-creds-wrap" style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="laca-opt-preserve-creds" checked>
                                    <span><?php esc_html_e('Giữ thông tin xác thực Google của site này cho Laca SEO', 'laca'); ?></span>
                                </label>
                                <label class="laca-checkbox" id="laca-opt-secrets-wrap" style="display:none;align-items:center;gap:8px;">
                                    <input type="checkbox" id="laca-opt-import-secrets">
                                    <span style="color:var(--laca-danger, #e11d48);font-weight:500;">
                                        <?php esc_html_e('Nhập cả mật khẩu và khoá API (sẽ được mã hoá lại an toàn bằng AUTH_KEY của site này)', 'laca'); ?>
                                    </span>
                                </label>

                                <div style="margin-top:6px;padding-top:10px;border-top:1px dashed var(--laca-border, #cbd5e1);">
                                    <span style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">
                                        <?php esc_html_e('Xử lý khi mẫu form liên hệ bị trùng tên:', 'laca'); ?>
                                    </span>
                                    <div style="display:flex;gap:16px;flex-wrap:wrap;">
                                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                                            <input type="radio" name="form_duplicate_action" value="skip" checked>
                                            <span><?php esc_html_e('Bỏ qua (giữ form cũ)', 'laca'); ?></span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                                            <input type="radio" name="form_duplicate_action" value="overwrite">
                                            <span><?php esc_html_e('Ghi đè cấu hình form cũ', 'laca'); ?></span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                                            <input type="radio" name="form_duplicate_action" value="duplicate">
                                            <span><?php esc_html_e('Tạo thêm bản sao mới', 'laca'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Thanh trạng thái tóm tắt -->
                        <div class="laca-sched-status" style="justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <span id="laca-cfg-preview-summary" class="laca-badge laca-badge--neutral"></span>
                            <label class="laca-switch">
                                <input type="checkbox" class="laca-switch__input" id="laca-cfg-show-same">
                                <span class="laca-switch__track"></span>
                                <span class="laca-switch__label"><?php esc_html_e('Hiện dòng giống', 'laca'); ?></span>
                            </label>
                        </div>

                        <!-- Bảng chi tiết option -->
                        <div class="laca-table-wrap">
                            <table class="laca-table">
                                <thead>
                                    <tr>
                                        <th style="width:30%;"><?php esc_html_e('Option / Thiết lập', 'laca'); ?></th>
                                        <th style="width:30%;"><?php esc_html_e('Hiện tại', 'laca'); ?></th>
                                        <th style="width:30%;"><?php esc_html_e('Sẽ thành', 'laca'); ?></th>
                                        <th style="width:10%;text-align:center;"><?php esc_html_e('Trạng thái', 'laca'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="laca-cfg-preview-tbody"></tbody>
                            </table>
                        </div>

                        <div id="laca-cfg-ignored-box" style="display:none;margin-top:16px;">
                            <div class="laca-alert laca-alert--warning" style="display:block;">
                                <strong><?php esc_html_e('Các mục bị loại bỏ (không được phép ghi):', 'laca'); ?></strong>
                                <ul id="laca-cfg-ignored-list" style="margin:8px 0 0 16px;list-style:disc;"></ul>
                            </div>
                        </div>

                        <div style="margin-top:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <button type="button" class="laca-btn laca-btn--danger" id="laca-cfg-apply-btn" disabled>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %d: number of changes to apply */
                                        __('Áp dụng %d thay đổi', 'laca'),
                                        0
                                    )
                                );
                                ?>
                            </button>
                            <span id="laca-cfg-apply-progress" style="display:none;font-size:13px;color:var(--laca-text-muted, #64748b);"></span>
                        </div>
                    </div>

                    <!-- Hộp kết quả sau khi áp dụng -->
                    <div id="laca-cfg-result-box" style="display:none;margin-top:20px;"></div>
                </div>
            </div>

            <!-- Card 3: Bản sao lưu trước khi nhập -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <div class="laca-card__title"><?php esc_html_e('Bản sao lưu trước khi nhập', 'laca'); ?></div>
                    <p class="laca-card__desc">
                        <?php esc_html_e('Hệ thống tự động chụp bản sao lưu snapshot trước mỗi lần áp dụng để bạn có thể khôi phục lại bất kỳ lúc nào (lưu tối đa 5 bản gần nhất).', 'laca'); ?>
                    </p>
                </div>
                <div class="laca-card__body">
                    <div id="laca-cfg-restore-alert" style="display:none;margin-bottom:12px;"></div>

                    <div id="laca-cfg-backups-container">
                        <?php if (empty($backups)) : ?>
                            <div class="laca-empty">
                                <div class="laca-empty__title"><?php esc_html_e('Chưa có bản sao lưu nào.', 'laca'); ?></div>
                                <p class="laca-empty__desc"><?php esc_html_e('Bản sao lưu sẽ được tự động tạo khi bạn áp dụng một bộ cấu hình mới.', 'laca'); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="laca-table-wrap">
                                <table class="laca-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Thời gian', 'laca'); ?></th>
                                            <th><?php esc_html_e('Người thực hiện', 'laca'); ?></th>
                                            <th><?php esc_html_e('File nguồn / Site nguồn', 'laca'); ?></th>
                                            <th style="text-align:center;"><?php esc_html_e('Số mục đã lưu', 'laca'); ?></th>
                                            <th style="text-align:right;"><?php esc_html_e('Thao tác', 'laca'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $b) :
                                            $bId        = esc_attr($b['id'] ?? '');
                                            $timeStr    = esc_html($b['created_at'] ?? '');
                                            $userStr    = esc_html($b['user_login'] ?? __('Không rõ', 'laca'));
                                            $sourceFile = esc_html($b['source_file'] ?? '');
                                            $sourceSite = esc_html($b['source_site'] ?? '');
                                            $optCount   = is_array($b['options'] ?? null) ? count($b['options']) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo $timeStr; ?></td>
                                                <td><?php echo $userStr; ?></td>
                                                <td>
                                                    <strong><?php echo $sourceFile ?: __('(Tệp cấu hình)', 'laca'); ?></strong>
                                                    <?php if ($sourceSite) : ?>
                                                        <br><small style="color:var(--laca-text-muted, #64748b);"><code><?php echo $sourceSite; ?></code></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <span class="laca-badge laca-badge--neutral">
                                                        <?php
                                                        echo esc_html(
                                                            sprintf(
                                                                /* translators: %d: number of saved options */
                                                                __('%d mục', 'laca'),
                                                                $optCount
                                                            )
                                                        );
                                                        ?>
                                                    </span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <button type="button"
                                                            class="laca-btn laca-btn--secondary laca-btn--sm laca-cfg-restore-btn"
                                                            data-backup-id="<?php echo $bId; ?>">
                                                        <?php esc_html_e('Khôi phục', 'laca'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const ajaxUrl     = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const nonceImport = <?php echo wp_json_encode($nonceImport); ?>;
            const homeUrl     = <?php echo wp_json_encode(home_url()); ?>;
            const i18n        = <?php echo wp_json_encode($i18n); ?>;

            let currentToken      = '';
            let currentPayloadSrc = {};
            let previewItems      = [];
            let applyTotalCount   = 0;

            function showAlert(id, msg, type) {
                const el = document.getElementById(id);
                if (!el) return;
                el.style.display = 'flex';
                el.className = 'laca-alert laca-alert--' + (type || 'info');
                el.textContent = String(msg || '');
            }

            function resetPreviewButton(btn) {
                if (!btn) return;
                btn.replaceChildren();
                const icon = document.createElement('span');
                icon.className = 'dashicons dashicons-search';
                icon.style.marginRight = '6px';
                icon.style.verticalAlign = 'middle';
                btn.appendChild(icon);
                btn.appendChild(document.createTextNode(i18n.previewBtn));
            }

            function hideAlert(id) {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function formatVal(v) {
                if (v === null || v === undefined) return '∅';
                if (typeof v === 'boolean') return v ? 'true' : 'false';
                if (typeof v === 'object') {
                    try {
                        const s = JSON.stringify(v);
                        return s.length > 80 ? s.substring(0, 77) + '...' : s;
                    } catch (e) {
                        return '[Object]';
                    }
                }
                const str = String(v);
                return str.length > 80 ? str.substring(0, 77) + '...' : str;
            }

            // 1. Toggle cảnh báo khi chọn kèm mật khẩu ở Card Xuất
            const secretExportChk = document.getElementById('laca-cfg-export-secrets-chk');
            const secretExportWarn = document.getElementById('laca-cfg-secret-warning');
            if (secretExportChk && secretExportWarn) {
                secretExportChk.addEventListener('change', function() {
                    secretExportWarn.style.display = this.checked ? 'block' : 'none';
                });
            }

            // 2. Render bảng xem trước
            function renderPreviewTable() {
                const tbody = document.getElementById('laca-cfg-preview-tbody');
                const showSameEl = document.getElementById('laca-cfg-show-same');
                if (!tbody) return;

                const showSame = showSameEl ? showSameEl.checked : false;
                tbody.replaceChildren();

                const visible = previewItems.filter(item => showSame || item.status !== 'same');
                if (visible.length === 0) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 4;
                    td.style.textAlign = 'center';
                    td.style.padding = '20px';
                    td.textContent = i18n.noDiffRows;
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                    return;
                }

                visible.forEach(item => {
                    const tr = document.createElement('tr');

                    const tdOpt = document.createElement('td');
                    const codeOpt = document.createElement('code');
                    codeOpt.textContent = item.option;
                    tdOpt.appendChild(codeOpt);

                    const tdOld = document.createElement('td');
                    const codeOld = document.createElement('code');
                    codeOld.textContent = formatVal(item.current);
                    tdOld.appendChild(codeOld);

                    const tdNew = document.createElement('td');
                    const codeNew = document.createElement('code');
                    codeNew.textContent = formatVal(item.incoming);
                    tdNew.appendChild(codeNew);

                    const tdBadge = document.createElement('td');
                    tdBadge.style.textAlign = 'center';
                    const badge = document.createElement('span');

                    if (item.status === 'new') {
                        badge.className = 'laca-badge laca-badge--info';
                        badge.textContent = i18n.statusNew;
                    } else if (item.status === 'diff') {
                        badge.className = 'laca-badge laca-badge--warning';
                        badge.textContent = i18n.statusDiff;
                    } else if (item.status === 'ignored') {
                        badge.className = 'laca-badge laca-badge--danger';
                        badge.textContent = i18n.statusIgnored;
                    } else {
                        badge.className = 'laca-badge laca-badge--neutral';
                        badge.textContent = i18n.statusSame;
                    }

                    tdBadge.appendChild(badge);

                    tr.appendChild(tdOpt);
                    tr.appendChild(tdOld);
                    tr.appendChild(tdNew);
                    tr.appendChild(tdBadge);
                    tbody.appendChild(tr);
                });
            }

            const showSameToggle = document.getElementById('laca-cfg-show-same');
            if (showSameToggle) {
                showSameToggle.addEventListener('change', renderPreviewTable);
            }

            const fileInput = document.getElementById('laca-cfg-file');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    currentToken = '';
                    previewItems = [];
                    applyTotalCount = 0;
                    const box = document.getElementById('laca-cfg-preview-box');
                    const resBox = document.getElementById('laca-cfg-result-box');
                    if (box) box.style.display = 'none';
                    if (resBox) resBox.style.display = 'none';
                    hideAlert('laca-cfg-import-alert');
                });
            }

            // 3. Tải lên & Xem trước (Upload Preview AJAX)
            const previewBtn = document.getElementById('laca-cfg-preview-btn');
            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    const file = fileInput && fileInput.files ? fileInput.files[0] : null;
                    if (!file) {
                        showAlert('laca-cfg-import-alert', i18n.selectFileErr, 'danger');
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        showAlert('laca-cfg-import-alert', i18n.fileTooLarge, 'danger');
                        return;
                    }

                    hideAlert('laca-cfg-import-alert');
                    previewBtn.disabled = true;
                    previewBtn.textContent = i18n.previewing;

                    const formData = new FormData();
                    formData.append('action', 'laca_site_config_upload_preview');
                    formData.append('_nonce', nonceImport);
                    formData.append('config_file', file);

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(res => {
                        previewBtn.disabled = false;
                        resetPreviewButton(previewBtn);

                        if (res.success && res.data) {
                            currentToken      = res.data.token || '';
                            currentPayloadSrc = res.data.source || {};
                            const analysis    = res.data.analysis || {};
                            const summary     = analysis.summary || { new: 0, diff: 0, same: 0, ignored: 0 };

                            // Tổng số thay đổi sẽ ghi đè
                            applyTotalCount = (summary.new || 0) + (summary.diff || 0);

                            // Cập nhật nhãn thay domain
                            const srcUrl = currentPayloadSrc.home_url || '';
                            const domainLabelEl = document.getElementById('laca-opt-replace-domain-label');
                            if (domainLabelEl && srcUrl) {
                                domainLabelEl.replaceChildren();
                                const parts = String(i18n.replaceDomainTpl || '').split(/(%1\$s|%2\$s)/);
                                parts.forEach(part => {
                                    if (part === '%1$s') {
                                        const codeSrc = document.createElement('code');
                                        codeSrc.textContent = String(srcUrl);
                                        domainLabelEl.appendChild(codeSrc);
                                    } else if (part === '%2$s') {
                                        const codeHome = document.createElement('code');
                                        codeHome.textContent = String(homeUrl);
                                        domainLabelEl.appendChild(codeHome);
                                    } else if (part) {
                                        domainLabelEl.appendChild(document.createTextNode(part));
                                    }
                                });
                            }

                            // Ẩn/hiện tuỳ chọn media, SEO, secrets
                            const mediaWrap = document.getElementById('laca-opt-media-wrap');
                            if (mediaWrap) {
                                mediaWrap.style.display = analysis.has_media ? 'flex' : 'none';
                            }
                            const secretsWrap = document.getElementById('laca-opt-secrets-wrap');
                            if (secretsWrap) {
                                secretsWrap.style.display = analysis.has_secrets ? 'flex' : 'none';
                            }

                            // Cảnh báo rủi ro cao
                            const warnBox = document.getElementById('laca-cfg-warnings-container');
                            if (warnBox) {
                                warnBox.replaceChildren();
                                if (analysis.warnings && analysis.warnings.length > 0) {
                                    warnBox.style.display = 'block';
                                    const alertDiv = document.createElement('div');
                                    alertDiv.className = 'laca-alert laca-alert--warning';
                                    alertDiv.style.display = 'block';

                                    const strongEl = document.createElement('strong');
                                    strongEl.textContent = i18n.warningsHeader;
                                    alertDiv.appendChild(strongEl);

                                    const ulEl = document.createElement('ul');
                                    ulEl.style.margin = '8px 0 0 16px';
                                    ulEl.style.listStyle = 'disc';
                                    analysis.warnings.forEach(w => {
                                        const liEl = document.createElement('li');
                                        liEl.textContent = String(w ?? '');
                                        ulEl.appendChild(liEl);
                                    });
                                    alertDiv.appendChild(ulEl);
                                    warnBox.appendChild(alertDiv);
                                } else {
                                    warnBox.style.display = 'none';
                                }
                            }

                            // Gom danh sách items
                            previewItems = [];
                            if (analysis.by_section) {
                                Object.keys(analysis.by_section).forEach(secKey => {
                                    const secObj = analysis.by_section[secKey];
                                    if (secObj && Array.isArray(secObj.items)) {
                                        previewItems = previewItems.concat(secObj.items);
                                    }
                                });
                            }

                            // Danh sách ignored
                            const ignoredBox = document.getElementById('laca-cfg-ignored-box');
                            const ignoredList = document.getElementById('laca-cfg-ignored-list');
                            if (ignoredBox && ignoredList) {
                                ignoredList.replaceChildren();
                                if (analysis.ignored_items && analysis.ignored_items.length > 0) {
                                    ignoredBox.style.display = 'block';
                                    analysis.ignored_items.forEach(item => {
                                        const liEl = document.createElement('li');
                                        const codeEl = document.createElement('code');
                                        codeEl.textContent = String(item.option ?? '');
                                        liEl.appendChild(codeEl);
                                        liEl.appendChild(document.createTextNode(': ' + String(item.reason ?? '')));
                                        ignoredList.appendChild(liEl);
                                    });
                                } else {
                                    ignoredBox.style.display = 'none';
                                }
                            }

                            // Summary badge
                            const summaryEl = document.getElementById('laca-cfg-preview-summary');
                            if (summaryEl) {
                                summaryEl.textContent = i18n.summaryTpl
                                    .replace('%1$d', summary.new || 0)
                                    .replace('%2$d', summary.diff || 0)
                                    .replace('%3$d', summary.same || 0)
                                    .replace('%4$d', summary.ignored || 0);
                            }

                            // Apply button
                            const applyBtn = document.getElementById('laca-cfg-apply-btn');
                            if (applyBtn) {
                                applyBtn.textContent = i18n.applyBtnTpl.replace('%d', applyTotalCount);
                                applyBtn.disabled = applyTotalCount <= 0;
                            }

                            // Hiển thị hộp xem trước
                            const box = document.getElementById('laca-cfg-preview-box');
                            if (box) box.style.display = 'block';

                            renderPreviewTable();
                        } else {
                            showAlert('laca-cfg-import-alert', res.data || i18n.errorGeneric, 'danger');
                        }
                    })
                    .catch(() => {
                        previewBtn.disabled = false;
                        resetPreviewButton(previewBtn);
                        showAlert('laca-cfg-import-alert', i18n.errorGeneric, 'danger');
                    });
                });
            }

            // 4. Áp dụng thay đổi (Apply Config AJAX)
            const applyBtn = document.getElementById('laca-cfg-apply-btn');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    if (!currentToken || applyTotalCount <= 0) return;
                    if (!confirm(i18n.confirmApply.replace('%d', applyTotalCount))) return;

                    this.disabled = true;
                    this.textContent = i18n.applying;

                    const progEl = document.getElementById('laca-cfg-apply-progress');
                    if (progEl) {
                        progEl.style.display = 'inline';
                        progEl.textContent = i18n.applyingSteps[0];
                    }

                    const formDupVal = document.querySelector('input[name="form_duplicate_action"]:checked')?.value || 'skip';
                    const replaceDomVal = document.getElementById('laca-opt-replace-domain')?.checked ? '1' : '0';
                    const importMediaVal = document.getElementById('laca-opt-import-media')?.checked ? '1' : '0';
                    const preserveCredsVal = document.getElementById('laca-opt-preserve-creds')?.checked ? '1' : '0';
                    const importSecretsVal = document.getElementById('laca-opt-import-secrets')?.checked ? '1' : '0';

                    const body = new URLSearchParams({
                        action: 'laca_site_config_apply',
                        _nonce: nonceImport,
                        token: currentToken,
                        replace_domain: replaceDomVal,
                        import_media: importMediaVal,
                        preserve_credentials: preserveCredsVal,
                        import_secrets: importSecretsVal,
                        form_duplicate_action: formDupVal
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.disabled = false;
                        this.textContent = i18n.applyBtnTpl.replace('%d', 0);
                        if (progEl) progEl.style.display = 'none';

                        const resBox = document.getElementById('laca-cfg-result-box');
                        if (res.success && res.data) {
                            // Ẩn hộp xem trước
                            const box = document.getElementById('laca-cfg-preview-box');
                            if (box) box.style.display = 'none';

                            if (resBox) {
                                resBox.replaceChildren();

                                const successAlert = document.createElement('div');
                                successAlert.className = 'laca-alert laca-alert--success';
                                successAlert.style.display = 'block';
                                successAlert.style.marginBottom = '14px';

                                const msgStrong = document.createElement('strong');
                                msgStrong.textContent = String(res.data.message || i18n.appliedSuccess);
                                successAlert.appendChild(msgStrong);

                                const counts = res.data.counts || {};
                                const countsDiv = document.createElement('div');
                                countsDiv.style.marginTop = '8px';
                                countsDiv.style.fontSize = '13px';
                                let countsText = String(i18n.appliedCountsTpl || '')
                                    .replace('%1$d', counts.options || 0)
                                    .replace('%2$d', counts.forms || 0);
                                if (counts.seo) {
                                    countsText += i18n.appliedSeoSuffix;
                                }
                                countsDiv.textContent = countsText;
                                successAlert.appendChild(countsDiv);

                                if (counts.media && typeof counts.media === 'object') {
                                    const mediaDiv = document.createElement('div');
                                    mediaDiv.style.marginTop = '4px';
                                    mediaDiv.style.fontSize = '12px';
                                    mediaDiv.style.color = 'var(--laca-text-muted, #64748b)';
                                    mediaDiv.textContent = String(i18n.appliedMediaTpl || '')
                                        .replace('%1$d', counts.media.downloaded || 0)
                                        .replace('%2$d', counts.media.reused || 0)
                                        .replace('%3$d', counts.media.failed || 0);
                                    successAlert.appendChild(mediaDiv);
                                }
                                resBox.appendChild(successAlert);

                                // Cảnh báo URL đăng nhập mới nếu có
                                if (res.data.is_custom_login && res.data.new_login_slug) {
                                    const cleanSlug = String(res.data.new_login_slug).replace(/^\/+/, '');
                                    const newLoginUrl = homeUrl.replace(/\/+$/, '') + '/' + encodeURI(cleanSlug);
                                    const loginAlert = document.createElement('div');
                                    loginAlert.className = 'laca-alert laca-alert--warning';
                                    loginAlert.style.display = 'block';
                                    loginAlert.style.marginBottom = '14px';

                                    const loginStrong = document.createElement('strong');
                                    loginStrong.textContent = i18n.newLoginUrlLabel;
                                    loginAlert.appendChild(loginStrong);
                                    loginAlert.appendChild(document.createElement('br'));

                                    const loginLink = document.createElement('a');
                                    loginLink.href = newLoginUrl;
                                    loginLink.target = '_blank';
                                    loginLink.rel = 'noopener noreferrer';
                                    loginLink.style.fontSize = '15px';
                                    loginLink.style.fontWeight = '700';
                                    loginLink.textContent = newLoginUrl;
                                    loginAlert.appendChild(loginLink);

                                    resBox.appendChild(loginAlert);
                                }

                                // Danh sách cần chọn lại thủ công
                                if (res.data.manual_reminders && res.data.manual_reminders.length > 0) {
                                    const remCard = document.createElement('div');
                                    remCard.className = 'laca-card';
                                    remCard.style.border = '1px solid var(--laca-border, #e2e8f0)';
                                    remCard.style.marginBottom = '14px';

                                    const remHeader = document.createElement('div');
                                    remHeader.className = 'laca-card__header';
                                    const remTitle = document.createElement('div');
                                    remTitle.className = 'laca-card__title';
                                    remTitle.style.fontSize = '14px';
                                    remTitle.textContent = i18n.manualRemindHeader;
                                    remHeader.appendChild(remTitle);
                                    remCard.appendChild(remHeader);

                                    const remBody = document.createElement('div');
                                    remBody.className = 'laca-card__body';
                                    const remUl = document.createElement('ul');
                                    remUl.style.margin = '0 0 0 16px';
                                    remUl.style.listStyle = 'disc';

                                    res.data.manual_reminders.forEach(rem => {
                                        const remLi = document.createElement('li');
                                        const remStrong = document.createElement('strong');
                                        remStrong.textContent = String(rem.label || rem.option || '');
                                        remLi.appendChild(remStrong);
                                        remLi.appendChild(document.createTextNode(': ' + String(rem.reason || '')));
                                        remUl.appendChild(remLi);
                                    });

                                    remBody.appendChild(remUl);
                                    remCard.appendChild(remBody);
                                    resBox.appendChild(remCard);
                                }

                                resBox.style.display = 'block';
                            }

                            // Tải lại trang sau 3 giây để cập nhật lại danh sách sao lưu
                            setTimeout(() => {
                                window.location.reload();
                            }, 3500);
                        } else {
                            showAlert('laca-cfg-import-alert', res.data || i18n.errorGeneric, 'danger');
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.textContent = i18n.applyBtnTpl.replace('%d', applyTotalCount);
                        if (progEl) progEl.style.display = 'none';
                        showAlert('laca-cfg-import-alert', i18n.errorGeneric, 'danger');
                    });
                });
            }

            // 5. Khôi phục bản sao lưu (Restore Backup AJAX)
            document.querySelectorAll('.laca-cfg-restore-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const backupId = this.getAttribute('data-backup-id');
                    if (!backupId) return;

                    if (!confirm(i18n.confirmRestore)) return;

                    this.disabled = true;
                    const origText = this.textContent;
                    this.textContent = i18n.restoring;

                    const body = new URLSearchParams({
                        action: 'laca_site_config_restore',
                        _nonce: nonceImport,
                        backup_id: backupId
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.disabled = false;
                        this.textContent = origText;

                        if (res.success) {
                            showAlert('laca-cfg-restore-alert', res.data?.message || i18n.restoreSuccess, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showAlert('laca-cfg-restore-alert', res.data || i18n.errorGeneric, 'danger');
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.textContent = origText;
                        showAlert('laca-cfg-restore-alert', i18n.errorGeneric, 'danger');
                    });
                });
            });
        })();
        </script>
        <?php
    }

    private function renderLogTab(string $baseUrl): void
    {
        $allLogs = ConfigChangeLog::getLog();
        $search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged   = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        if ($search !== '') {
            $filtered = array_values(array_filter($allLogs, static function ($entry) use ($search): bool {
                $opt = (string) ($entry['option'] ?? '');
                return stripos($opt, $search) !== false;
            }));
        } else {
            $filtered = $allLogs;
        }

        $total      = count($filtered);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($paged > $totalPages) {
            $paged = $totalPages;
        }

        $offset  = ($paged - 1) * self::PER_PAGE;
        $entries = array_slice($filtered, $offset, self::PER_PAGE);

        $nonce = wp_create_nonce(ConfigChangeLog::NONCE_ACTION);
        $i18n  = [
            'confirmClear' => __('Bạn có chắc muốn xoá toàn bộ nhật ký thay đổi cấu hình không?', 'laca'),
            'clearing'     => __('Đang xoá...', 'laca'),
            'clearBtn'     => __('Xoá nhật ký', 'laca'),
            'emptyText'    => __('Chưa có thay đổi nào được ghi lại.', 'laca'),
            'errorGeneric' => __('Lỗi kết nối máy chủ.', 'laca'),
        ];
        ?>
        <div class="laca-settings-stack">
            <div class="laca-card">
                <div class="laca-card__header laca-card__header--flex">
                    <div>
                        <div class="laca-card__title"><?php esc_html_e('Nhật ký thay đổi cấu hình', 'laca'); ?></div>
                        <p class="laca-card__desc">
                            <?php esc_html_e('Tối đa 200 thay đổi gần nhất do quản trị viên thực hiện (không ghi giá trị nhạy cảm).', 'laca'); ?>
                        </p>
                    </div>
                    <?php if (!empty($allLogs)) : ?>
                        <button type="button" class="laca-btn laca-btn--danger laca-btn--sm" id="laca-cfg-clear-log-btn">
                            <?php esc_html_e('Xoá nhật ký', 'laca'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="laca-card__body">
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="laca-log-filter-bar">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                        <input type="hidden" name="tab" value="log">
                        <input type="search"
                               name="s"
                               class="laca-input"
                               value="<?php echo esc_attr($search); ?>"
                               placeholder="<?php esc_attr_e('Tìm theo tên option...', 'laca'); ?>">
                        <button type="submit" class="laca-btn laca-btn--secondary">
                            <?php esc_html_e('Tìm', 'laca'); ?>
                        </button>
                        <?php if ($search !== '') : ?>
                            <a href="<?php echo esc_url(add_query_arg('tab', 'log', $baseUrl)); ?>" class="laca-btn laca-btn--ghost">
                                <?php esc_html_e('Bỏ lọc', 'laca'); ?>
                            </a>
                        <?php endif; ?>
                    </form>

                    <div id="laca-cfg-log-alert" style="display:none;margin-top:12px;"></div>

                    <div id="laca-cfg-log-container" style="margin-top:14px;">
                        <?php if (empty($entries)) : ?>
                            <div class="laca-empty">
                                <div class="laca-empty__title"><?php esc_html_e('Chưa có thay đổi nào được ghi lại.', 'laca'); ?></div>
                            </div>
                        <?php else : ?>
                            <div class="laca-table-wrap">
                                <table class="laca-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Thời gian', 'laca'); ?></th>
                                            <th><?php esc_html_e('Người đổi', 'laca'); ?></th>
                                            <th><?php esc_html_e('IP', 'laca'); ?></th>
                                            <th><?php esc_html_e('Option', 'laca'); ?></th>
                                            <th><?php esc_html_e('Cũ → Mới', 'laca'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($entries as $row) :
                                            $userId = (int) ($row['user'] ?? 0);
                                            if ($userId === 0) {
                                                $userName = __('Hệ thống', 'laca');
                                            } else {
                                                $u = get_userdata($userId);
                                                $userName = $u ? $u->display_name : sprintf(
                                                    /* translators: %d: user ID */
                                                    __('User #%d', 'laca'),
                                                    $userId
                                                );
                                            }
                                            $oldFull = (string) ($row['old'] ?? '');
                                            $newFull = (string) ($row['new'] ?? '');
                                            $diffFull = ($oldFull !== '' ? $oldFull : '∅') . ' → ' . ($newFull !== '' ? $newFull : '∅');
                                            $diffShort = mb_strlen($diffFull) > 80
                                                ? mb_substr($diffFull, 0, 77) . '...'
                                                : $diffFull;
                                        ?>
                                            <tr>
                                                <td><?php echo esc_html((string) ($row['time'] ?? '')); ?></td>
                                                <td><?php echo esc_html($userName); ?></td>
                                                <td><code><?php echo esc_html((string) ($row['ip'] ?? '')); ?></code></td>
                                                <td><code><?php echo esc_html((string) ($row['option'] ?? '')); ?></code></td>
                                                <td>
                                                    <code title="<?php echo esc_attr($diffFull); ?>">
                                                        <?php echo esc_html($diffShort); ?>
                                                    </code>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($totalPages > 1) :
                                $pageLinks = paginate_links([
                                    'base'      => add_query_arg(
                                        array_filter([
                                            'page'  => self::SLUG,
                                            'tab'   => 'log',
                                            's'     => $search !== '' ? $search : null,
                                            'paged' => '%#%',
                                        ]),
                                        admin_url('admin.php')
                                    ),
                                    'format'    => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total'     => $totalPages,
                                    'current'   => $paged,
                                ]);
                                if ($pageLinks) :
                            ?>
                                <div class="tablenav bottom">
                                    <div class="tablenav-pages">
                                        <?php echo wp_kses_post($pageLinks); ?>
                                    </div>
                                </div>
                            <?php
                                endif;
                            endif;
                            ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const nonce   = <?php echo wp_json_encode($nonce); ?>;
            const i18n    = <?php echo wp_json_encode($i18n); ?>;

            const clearBtn = document.getElementById('laca-cfg-clear-log-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (!confirm(i18n.confirmClear)) return;

                    this.disabled = true;
                    this.textContent = i18n.clearing;

                    const body = new URLSearchParams({
                        action: 'laca_config_changelog_clear',
                        _nonce: nonce
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            const container = document.getElementById('laca-cfg-log-container');
                            if (container) {
                                container.replaceChildren();
                                const empty = document.createElement('div');
                                empty.className = 'laca-empty';
                                const title = document.createElement('div');
                                title.className = 'laca-empty__title';
                                title.textContent = i18n.emptyText;
                                empty.appendChild(title);
                                container.appendChild(empty);
                            }
                            this.remove();
                        } else {
                            this.disabled = false;
                            this.textContent = i18n.clearBtn;
                            const alertEl = document.getElementById('laca-cfg-log-alert');
                            if (alertEl) {
                                alertEl.style.display = 'flex';
                                alertEl.className = 'laca-alert laca-alert--danger';
                                alertEl.textContent = res.data || i18n.errorGeneric;
                            }
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.textContent = i18n.clearBtn;
                    });
                });
            }
        })();
        </script>
        <?php
    }
}
