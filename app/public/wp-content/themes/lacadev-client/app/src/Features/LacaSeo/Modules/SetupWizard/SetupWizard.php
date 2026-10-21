<?php

namespace App\Features\LacaSeo\Modules\SetupWizard;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\ImportExport\RankMathImporter;

defined('ABSPATH') || exit;

/**
 * Setup Wizard for Laca SEO.
 * Allows step-by-step configuration of Site Identity, Sitemap, Optimization, Schema, and Role permissions.
 */
class SetupWizard
{
    public const WIZARD_PAGE = 'laca-seo-wizard';

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenu'], 99);
        add_action('admin_post_laca_seo_wizard_save_step', [$this, 'saveStep']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            null, // Hide from menu
            __('Cài đặt nhanh Laca SEO', 'lacadev'),
            __('Setup Wizard', 'lacadev'),
            'manage_options',
            self::WIZARD_PAGE,
            [$this, 'render']
        );
    }

    /**
     * Save wizard step data cleanly to Settings registry without corrupting other options.
     */
    public static function saveWizardStep(string $step, array $data): bool
    {
        switch ($step) {
            case 'your-site':
                $titles = Settings::getGroup('titles');
                if (isset($data['knowledgegraph_type'])) {
                    $titles['knowledgegraph_type'] = sanitize_text_field($data['knowledgegraph_type']);
                }
                if (isset($data['knowledgegraph_name'])) {
                    $titles['knowledgegraph_name'] = sanitize_text_field($data['knowledgegraph_name']);
                }
                Settings::updateGroup('titles', $titles);
                return true;

            case 'sitemap':
                if (isset($data['sitemap_enabled'])) {
                    $active = Modules::getActive();
                    if ($data['sitemap_enabled'] && !in_array('sitemap', $active, true)) {
                        $active[] = 'sitemap';
                    } elseif (!$data['sitemap_enabled']) {
                        $active = array_diff($active, ['sitemap']);
                    }
                    Modules::setActive($active);
                }
                return true;

            case 'optimization':
                $general = Settings::getGroup('general');
                if (isset($data['nofollow_external_links'])) {
                    $general['nofollow_external_links'] = $data['nofollow_external_links'] ? 'on' : 'off';
                }
                if (isset($data['new_window_external_links'])) {
                    $general['new_window_external_links'] = $data['new_window_external_links'] ? 'on' : 'off';
                }
                Settings::updateGroup('general', $general);
                return true;

            case 'schema':
                $titles = Settings::getGroup('titles');
                if (isset($data['pt_post_default_rich_snippet'])) {
                    $titles['pt_post_default_rich_snippet'] = sanitize_key($data['pt_post_default_rich_snippet']);
                }
                Settings::updateGroup('titles', $titles);
                return true;

            case 'import':
                if (!empty($data['run_import'])) {
                    RankMathImporter::importSettings();
                    RankMathImporter::importLinksData();
                }
                return true;
        }

        return false;
    }

    public function saveStep(): void
    {
        check_admin_referer('laca_seo_wizard_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_die(__('Bạn không có quyền thực hiện thao tác này.', 'lacadev'));
        }

        $step = sanitize_key($_POST['step'] ?? '');
        self::saveWizardStep($step, $_POST);

        $nextStep = sanitize_key($_POST['next_step'] ?? 'ready');
        wp_safe_redirect(admin_url('admin.php?page=' . self::WIZARD_PAGE . '&step=' . $nextStep));
        exit;
    }

    public function render(): void
    {
        $currentStep = sanitize_key($_GET['step'] ?? 'your-site');
        echo '<div class="wrap laca-seo-wizard-wrap">';
        echo '<h1>' . esc_html__('Trình hướng dẫn thiết lập Laca SEO', 'lacadev') . '</h1>';
        echo '<div class="wizard-content card" style="max-width: 700px; padding: 24px; margin-top: 20px;">';
        printf('<h2>%s: %s</h2>', esc_html__('Bước hiện tại', 'lacadev'), esc_html(strtoupper($currentStep)));
        echo '<p>' . esc_html__('Trình hướng dẫn giúp cấu hình trang web, sitemap, tối ưu SEO onpage và phân quyền dễ dàng.', 'lacadev') . '</p>';
        echo '</div>';
        echo '</div>';
    }
}
