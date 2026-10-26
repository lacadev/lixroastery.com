<?php

namespace App\Features\LacaSeo\Admin;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\GeneralFields;
use App\Features\LacaSeo\Fields\TitlesFields;

defined('ABSPATH') || exit;

/**
 * Handles admin-post submissions for saving Laca SEO settings.
 * Validates nonce, capability, sanitizes against registry fields, updates options.
 */
class SaveHandler
{
    public function register(): void
    {
        add_action('admin_post_laca_seo_save', [$this, 'handleSave']);
        add_action('wp_ajax_laca_seo_save', [$this, 'handleSave']);
        add_action('admin_post_laca_seo_reset_tab', [$this, 'handleResetTab']);
    }

    /**
     * Handle saving settings form.
     */
    public function handleSave(): void
    {
        $isAjax = wp_doing_ajax() || !empty($_POST['is_ajax']);

        if ($isAjax) {
            if (!check_ajax_referer('laca_seo_save_action', 'laca_seo_nonce', false) && !wp_verify_nonce($_POST['laca_seo_nonce'] ?? '', 'laca_seo_save_action')) {
                wp_send_json_error(['message' => __('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.', 'lacadev')], 403);
            }
        } else {
            check_admin_referer('laca_seo_save_action', 'laca_seo_nonce');
        }

        if (!current_user_can('manage_options')) {
            if ($isAjax) {
                wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'lacadev')], 403);
            }
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
        }

        $group = sanitize_key($_POST['option_group'] ?? 'general');
        $tab = sanitize_key($_POST['tab'] ?? '');
        $view = sanitize_key($_POST['view'] ?? $group);

        $posted = $_POST['laca_seo'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        // Get current values
        $current = Settings::getGroup($group);

        // Get registry map for this group
        $registryMap = Settings::getRegistryMap();
        $groupRegistry = $registryMap[$group] ?? [];

        // Determine which fields belong to this tab
        $tabFields = $this->getFieldsForTab($group, $tab);

        foreach ($tabFields as $field) {
            $id = $field->getId();
            $rawValue = $posted[$id] ?? null;

            // Handle htaccess special permission check
            if ($id === 'htaccess_content') {
                $superUsers = \App\Settings\AdminSettings::getSuperUserLogins();
                $currentLogin = wp_get_current_user()->user_login;
                if (!in_array($currentLogin, $superUsers, true)) {
                    continue; // Skip editing htaccess if not superuser
                }

                // Backup .htaccess before writing
                $this->saveHtaccessFile((string) $rawValue);
            }

            // Handle robots.txt physical check
            if ($id === 'robots_txt_content') {
                $current[$id] = (string) $rawValue;
                continue;
            }

            // Sanitize according to registry field definition
            $current[$id] = $field->sanitize($rawValue);
        }

        // Persist
        Settings::updateGroup($group, $current);

        if ($group === 'sitemap') {
            if (class_exists(\App\Features\LacaSeo\Sitemap\Cache::class)) {
                \App\Features\LacaSeo\Sitemap\Cache::invalidateStorage();
            }
            flush_rewrite_rules();
        }

        if ($isAjax) {
            wp_send_json_success([
                'message'   => __('Đã tự động lưu cài đặt Laca SEO.', 'lacadev'),
                'timestamp' => current_time('H:i:s'),
            ]);
        }

        // Redirect back with success flag
        $redirectUrl = add_query_arg([
            'page'    => sanitize_key($_POST['page'] ?? 'laca-seo'),
            'view'    => $view,
            'tab'     => $tab,
            'updated' => '1',
        ], admin_url('admin.php'));

        if (!empty($_POST['redirect_to'])) {
            $customRedirect = esc_url_raw(wp_unslash($_POST['redirect_to']));
            if (wp_validate_redirect($customRedirect)) {
                $redirectUrl = add_query_arg(['updated' => '1'], $customRedirect);
            }
        }

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * Handle resetting a single tab to registry defaults.
     */
    public function handleResetTab(): void
    {
        check_admin_referer('laca_seo_reset_action', 'laca_seo_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
        }

        $group = sanitize_key($_POST['option_group'] ?? 'general');
        $tab = sanitize_key($_POST['tab'] ?? '');
        $view = sanitize_key($_POST['view'] ?? $group);

        $current = Settings::getGroup($group);
        $tabFields = $this->getFieldsForTab($group, $tab);

        foreach ($tabFields as $field) {
            $current[$field->getId()] = $field->getDefault();
        }

        Settings::updateGroup($group, $current);

        $redirectUrl = add_query_arg([
            'page'    => 'laca-seo',
            'view'    => $view,
            'tab'     => $tab,
            'reset'   => '1',
        ], admin_url('admin.php'));

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * Get fields belonging to a specific tab.
     *
     * @return Field[]
     */
    private function getFieldsForTab(string $group, string $tab): array
    {
        $all = match ($group) {
            'titles'           => TitlesFields::get(),
            'sitemap'          => \App\Features\LacaSeo\Fields\SitemapFields::get(),
            'instant_indexing' => \App\Features\LacaSeo\Fields\InstantIndexingFields::get(),
            default            => GeneralFields::get(),
        };
        return $all[$tab] ?? [];
    }

    /**
     * Backup and write .htaccess file safely according to rule C2.
     */
    private function saveHtaccessFile(string $content): void
    {
        $content = trim($content);
        if (empty($content)) {
            return;
        }

        // Must contain WordPress rewrite block
        if (!str_contains($content, '# BEGIN WordPress')) {
            wp_die(esc_html__('Lỗi an toàn: Tệp .htaccess bắt buộc phải có khối # BEGIN WordPress để tránh sập website.', 'lacadev'));
        }

        $htaccess_path = ABSPATH . '.htaccess';
        if (!file_exists($htaccess_path) || !is_writable($htaccess_path)) {
            wp_die(esc_html__('Lỗi: Tệp .htaccess không tồn tại hoặc không thể ghi (thiếu quyền ghi).', 'lacadev'));
        }

        $current_content = (string) file_get_contents($htaccess_path);
        if ($current_content === $content) {
            return; // No change
        }

        // Rotate backups - keep at most 5 backups
        $backup_files = glob(ABSPATH . '.htaccess.laca-seo-backup-*') ?: [];
        sort($backup_files);
        while (count($backup_files) >= 5) {
            $oldest = array_shift($backup_files);
            if ($oldest && file_exists($oldest)) {
                @unlink($oldest);
            }
        }

        $backup_path = ABSPATH . '.htaccess.laca-seo-backup-' . time();
        @copy($htaccess_path, $backup_path);

        $written = @file_put_contents($htaccess_path, $content);
        if ($written === false || file_get_contents($htaccess_path) !== $content) {
            // Restore from backup immediately
            if (file_exists($backup_path)) {
                @copy($backup_path, $htaccess_path);
            }
            wp_die(esc_html__('Lỗi: Ghi tệp .htaccess thất bại hoặc nội dung sau khi ghi không khớp. Đã khôi phục bản lưu trước đó.', 'lacadev'));
        }
    }
}
