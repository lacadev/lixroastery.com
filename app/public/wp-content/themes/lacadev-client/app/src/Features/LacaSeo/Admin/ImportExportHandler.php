<?php

namespace App\Features\LacaSeo\Admin;

use App\Features\LacaSeo\ImportExport\Exporter;
use App\Features\LacaSeo\ImportExport\Importer;
use App\Features\LacaSeo\ImportExport\RankMathImporter;

defined('ABSPATH') || exit;

/**
 * Handles admin-post and AJAX actions for import, export, and migration.
 */
class ImportExportHandler
{
    public const PENDING_IMPORT_TRANSIENT = 'laca_seo_pending_import_';

    public function register(): void
    {
        add_action('admin_post_laca_seo_export', [$this, 'handleExport']);
        add_action('admin_post_laca_seo_upload_import', [$this, 'handleUploadImport']);
        add_action('admin_post_laca_seo_confirm_import', [$this, 'handleConfirmImport']);
        add_action('admin_post_laca_seo_cancel_import', [$this, 'handleCancelImport']);
        add_action('admin_post_laca_seo_restore_backup', [$this, 'handleRestoreBackup']);
        add_action('admin_post_laca_seo_delete_backup', [$this, 'handleDeleteBackup']);
        add_action('admin_post_laca_seo_download_backup', [$this, 'handleDownloadBackup']);

        // AJAX batch migration from Rank Math
        add_action('wp_ajax_laca_seo_rm_import_step', [$this, 'ajaxRmImportStep']);
    }

    private function checkPermission(string $action, string $nonceName = 'nonce'): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
        }

        check_admin_referer($action, $nonceName);
    }

    private function redirectWithNotice(string $message, string $type = 'success'): void
    {
        $userId = get_current_user_id();
        set_transient('laca_seo_notice_' . $userId, [
            'type'    => $type,
            'message' => $message,
        ], 60);

        $url = add_query_arg(['page' => AdminPage::MENU_SLUG, 'view' => 'import-export'], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Handle JSON export download.
     */
    public function handleExport(): void
    {
        $this->checkPermission('laca_seo_export_action', 'laca_seo_export_nonce');

        $allowedSections = ['general', 'titles', 'sitemap', 'instant_indexing', 'modules'];
        $rawSections = $_POST['export_sections'] ?? ['general', 'titles', 'sitemap', 'modules'];
        $sections = [];
        if (is_array($rawSections)) {
            foreach ($rawSections as $sec) {
                $sec = sanitize_key($sec);
                if (in_array($sec, $allowedSections, true)) {
                    $sections[] = $sec;
                }
            }
        }

        if (empty($sections)) {
            $sections = ['general', 'titles'];
        }

        Exporter::downloadJson($sections);
        exit;
    }

    /**
     * Step 1: Upload JSON file, validate, save to temporary transient (10 mins), redirect to confirm preview.
     */
    public function handleUploadImport(): void
    {
        $this->checkPermission('laca_seo_import_action', 'laca_seo_import_nonce');

        if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->redirectWithNotice('Lỗi khi tải tệp lên hoặc không có tệp nào được chọn.', 'error');
        }

        $file = $_FILES['import_file'];
        if ($file['size'] > 2 * 1024 * 1024) {
            $this->redirectWithNotice('Kích thước tệp quá lớn (tối đa 2MB).', 'error');
        }

        $filename = sanitize_file_name($file['name']);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'json') {
            $this->redirectWithNotice('Định dạng tệp không hợp lệ. Vui lòng chọn tệp .json.', 'error');
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            $this->redirectWithNotice('Không thể đọc nội dung tệp tải lên.', 'error');
        }

        $payload = json_decode($content, true);
        if (!is_array($payload) || empty($payload['plugin']) || $payload['plugin'] !== 'laca-seo' || empty($payload['data'])) {
            $this->redirectWithNotice('Tệp JSON không đúng cấu trúc của Laca SEO.', 'error');
        }

        $userId = get_current_user_id();
        $importToken = wp_generate_password(24, false);

        set_transient(self::PENDING_IMPORT_TRANSIENT . $userId, [
            'token'                => $importToken,
            'payload'              => $payload,
            'replace_domain'       => !empty($_POST['replace_domain']),
            'preserve_credentials' => !empty($_POST['preserve_credentials']),
            'filename'             => $filename,
            'uploaded_at'          => current_time('mysql'),
        ], 600); // 10 minutes

        $this->redirectWithNotice('Tệp cấu hình hợp lệ. Vui lòng xem lại danh sách dữ liệu trước khi xác nhận ghi đè.', 'info');
    }

    /**
     * Step 2: Confirm import and overwrite settings after backing up.
     */
    public function handleConfirmImport(): void
    {
        $this->checkPermission('laca_seo_confirm_import_action', 'laca_seo_confirm_import_nonce');

        $userId = get_current_user_id();
        $pending = get_transient(self::PENDING_IMPORT_TRANSIENT . $userId);

        if (empty($pending) || !is_array($pending) || empty($pending['payload'])) {
            $this->redirectWithNotice('Phiên nhập dữ liệu đã hết hạn hoặc không tìm thấy. Vui lòng tải lại tệp.', 'error');
        }

        $submittedToken = sanitize_key($_POST['import_token'] ?? '');
        if ($submittedToken !== ($pending['token'] ?? '')) {
            $this->redirectWithNotice('Mã bảo mật nhập dữ liệu không hợp lệ.', 'error');
        }

        delete_transient(self::PENDING_IMPORT_TRANSIENT . $userId);

        $res = Importer::importJson(
            $pending['payload'],
            !empty($pending['replace_domain']),
            !empty($pending['preserve_credentials'])
        );

        if ($res['success']) {
            $this->redirectWithNotice($res['message'], 'success');
        } else {
            $this->redirectWithNotice($res['message'], 'error');
        }
    }

    /**
     * Cancel pending import.
     */
    public function handleCancelImport(): void
    {
        $this->checkPermission('laca_seo_cancel_import_action', 'laca_seo_cancel_import_nonce');
        $userId = get_current_user_id();
        delete_transient(self::PENDING_IMPORT_TRANSIENT . $userId);
        $this->redirectWithNotice('Đã huỷ phiên nhập cấu hình.', 'warning');
    }

    /**
     * Restore a backup snapshot.
     */
    public function handleRestoreBackup(): void
    {
        $this->checkPermission('laca_seo_backup_action', 'laca_seo_backup_nonce');
        $backupId = sanitize_key($_POST['backup_id'] ?? '');

        if (Importer::restoreBackup($backupId)) {
            $this->redirectWithNotice('Đã khôi phục thành công bản sao lưu ' . esc_html($backupId), 'success');
        } else {
            $this->redirectWithNotice('Không tìm thấy bản sao lưu để khôi phục.', 'error');
        }
    }

    /**
     * Delete a backup snapshot.
     */
    public function handleDeleteBackup(): void
    {
        $this->checkPermission('laca_seo_backup_action', 'laca_seo_backup_nonce');
        $backupId = sanitize_key($_POST['backup_id'] ?? '');

        $backups = get_option(Importer::BACKUPS_OPTION, []);
        if (is_array($backups)) {
            $filtered = array_values(array_filter($backups, static function ($b) use ($backupId) {
                return ($b['id'] ?? '') !== $backupId;
            }));
            update_option(Importer::BACKUPS_OPTION, $filtered, false);
            $this->redirectWithNotice('Đã xoá bản sao lưu ' . esc_html($backupId), 'success');
        }

        $this->redirectWithNotice('Không thể xoá bản sao lưu.', 'error');
    }

    /**
     * Download a backup snapshot as JSON file.
     */
    public function handleDownloadBackup(): void
    {
        $this->checkPermission('laca_seo_backup_action', 'laca_seo_backup_nonce');
        $backupId = sanitize_key($_GET['backup_id'] ?? ($_POST['backup_id'] ?? ''));

        $backups = get_option(Importer::BACKUPS_OPTION, []);
        $target = null;
        if (is_array($backups)) {
            foreach ($backups as $b) {
                if (($b['id'] ?? '') === $backupId) {
                    $target = $b;
                    break;
                }
            }
        }

        if (!$target || empty($target['data'])) {
            $this->redirectWithNotice('Không tìm thấy bản sao lưu để tải về.', 'error');
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $theme = wp_get_theme();
        $payload = [
            'plugin'      => 'laca-seo',
            'version'     => $theme->get('Version') ?: '1.0.0',
            'exported_at' => $target['time'] ?? current_time('mysql'),
            'site_url'    => get_home_url(),
            'backup_id'   => $backupId,
            'reason'      => $target['reason'] ?? '',
            'data'        => $target['data'],
        ];

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filename = sprintf('laca-seo-backup-%s-%s.json', $backupId, wp_date('Ymd-His'));

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen((string) $json));

        echo $json;
        exit;
    }

    /**
     * AJAX batch step for Rank Math migration.
     * Steps:
     * 1. 'settings' -> imports options, returns stats, next = 'termmeta'
     * 2. 'termmeta' -> imports termmeta, returns stats, next = 'usermeta'
     * 3. 'usermeta' -> imports usermeta, returns stats, next = 'postmeta', offset = 0
     * 4. 'postmeta' -> imports 200 postmeta records per call, returns stats & next_offset, done = true when finished
     */
    public function ajaxRmImportStep(): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        check_ajax_referer('laca_seo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $step = sanitize_key($_POST['step'] ?? 'settings');
        $offset = absint($_POST['offset'] ?? 0);
        $limit = 200;

        switch ($step) {
            case 'settings':
                $settings = RankMathImporter::importSettings();
                wp_send_json_success([
                    'step'      => 'settings',
                    'next_step' => 'termmeta',
                    'done'      => false,
                    'message'   => sprintf(
                        'Đã nhập cấu hình (%d keys Titles, %d keys General, %d modules)',
                        $settings['titles'] ?? 0,
                        $settings['general'] ?? 0,
                        $settings['modules'] ?? 0
                    ),
                    'stats'     => $settings,
                ]);
                break;

            case 'termmeta':
                $termsCount = RankMathImporter::importTermMeta();
                wp_send_json_success([
                    'step'      => 'termmeta',
                    'next_step' => 'usermeta',
                    'done'      => false,
                    'message'   => sprintf('Đã chuyển đổi %d bản ghi term meta', $termsCount),
                    'stats'     => ['terms' => $termsCount],
                ]);
                break;

            case 'usermeta':
                $usersCount = RankMathImporter::importUserMeta();
                wp_send_json_success([
                    'step'        => 'usermeta',
                    'next_step'   => 'postmeta',
                    'next_offset' => 0,
                    'done'        => false,
                    'message'     => sprintf('Đã chuyển đổi %d bản ghi user meta', $usersCount),
                    'stats'       => ['users' => $usersCount],
                ]);
                break;

            case 'postmeta':
                $res = RankMathImporter::importPostMetaBatch($offset, $limit);
                $newOffset = $offset + $res['processed'];
                $isDone = ($res['remaining'] === 0 || $res['processed'] === 0);

                wp_send_json_success([
                    'step'        => 'postmeta',
                    'next_step'   => $isDone ? 'done' : 'postmeta',
                    'offset'      => $offset,
                    'next_offset' => $newOffset,
                    'processed'   => $res['processed'],
                    'remaining'   => $res['remaining'],
                    'done'        => $isDone,
                    'message'     => sprintf('Đã xử lý %d post meta (còn lại %d)', $newOffset, $res['remaining']),
                ]);
                break;

            default:
                wp_send_json_error(['message' => 'Bước không hợp lệ']);
        }
    }
}
