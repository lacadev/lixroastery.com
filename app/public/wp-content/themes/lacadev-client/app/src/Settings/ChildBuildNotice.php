<?php

namespace App\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cảnh báo quản trị viên khi theme con được build với phiên bản parent cũ
 * và bản phát hành parent hiện tại khuyến nghị build lại theme con.
 */
class ChildBuildNotice
{
    public const MANIFEST_META_OPTION = 'laca_verified_manifest_meta';

    public function register(): void
    {
        add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function renderNotice(): void
    {
        if (!current_user_can('update_themes')) {
            return;
        }

        if (!is_child_theme()) {
            return;
        }

        $manifestMeta = get_option(self::MANIFEST_META_OPTION, []);
        if (!is_array($manifestMeta) || empty($manifestMeta['child_rebuild_recommended'])) {
            return;
        }

        $parentTheme   = wp_get_theme('lacadev-client/theme');
        $parentVersion = $parentTheme->exists()
            ? (string) $parentTheme->get('Version')
            : (string) wp_get_theme(get_template())->get('Version');

        if ($parentVersion === '') {
            return;
        }

        $metaFile      = get_stylesheet_directory() . '/../dist/build-meta.json';
        $builtVersion  = '';

        if (is_readable($metaFile)) {
            $decoded = json_decode((string) file_get_contents($metaFile), true);
            if (is_array($decoded) && !empty($decoded['parent_version'])) {
                $builtVersion = trim((string) $decoded['parent_version']);
            }
        }

        $needsRebuild = ($builtVersion === '') || version_compare($builtVersion, $parentVersion, '<');
        if (!$needsRebuild) {
            return;
        }

        $builtLabel = $builtVersion !== '' ? $builtVersion : __('chưa xác định', 'laca');
        $message    = sprintf(
            /* translators: 1: parent version child was built against, 2: current installed parent version */
            __('Theme con được build theo parent %1$s, parent hiện tại %2$s — cần build lại theme con (yarn build) và deploy.', 'laca'),
            $builtLabel,
            $parentVersion
        );

        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Đọc nội dung dist/build-meta.json của child theme (dùng cho CLI và kiểm chứng).
     *
     * @return array<string, mixed>|null
     */
    public static function readChildBuildMeta(): ?array
    {
        $metaFile = get_stylesheet_directory() . '/../dist/build-meta.json';
        if (!is_readable($metaFile)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($metaFile), true);
        return is_array($decoded) ? $decoded : null;
    }
}
