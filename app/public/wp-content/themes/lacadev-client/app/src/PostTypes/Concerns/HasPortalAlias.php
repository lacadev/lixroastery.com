<?php

namespace App\PostTypes\Concerns;

/**
 * Trait HasPortalAlias
 *
 * Lưu alias dễ nhớ cho Client Portal.
 * - Validate: chỉ chứa chữ thường/số/gạch ngang, tối thiểu 3 ký tự
 * - Unique: không được trùng với project khác
 * - Xóa cache cũ khi thay đổi alias
 *
 * Used by: App\PostTypes\Project
 */
trait HasPortalAlias
{
    public function savePortalAlias(int $postId): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['laca_pm_nonce'])) return;
        if (!wp_verify_nonce($_POST['laca_pm_nonce'], 'laca_project_manager')) return;
        if (!current_user_can('edit_post', $postId)) return;

        // Xóa cache cũ trước khi update
        $oldAlias = get_post_meta($postId, '_portal_alias', true);
        if ($oldAlias) {
            wp_cache_delete('laca_portal_key_' . md5($oldAlias), 'laca_portal');
        }

        if (!isset($_POST['_portal_alias'])) {
            return;
        }

        $alias = sanitize_text_field(trim($_POST['_portal_alias']));
        $alias = strtolower($alias);

        // Để trống → xóa alias
        if ($alias === '') {
            delete_post_meta($postId, '_portal_alias');
            return;
        }

        // Validate: chỉ chữ/số/gạch ngang, min 3 ký tự
        if (!preg_match('/^[a-z0-9\-]{3,60}$/', $alias)) {
            // Không hợp lệ → bỏ qua, giữ nguyên giá trị cũ
            return;
        }

        // Kiểm tra unique (loại trừ chính project này)
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_portal_alias' AND meta_value = %s AND post_id != %d
             LIMIT 1",
            $alias,
            $postId
        ));

        if ($existing) {
            // Trùng với project khác → không lưu
            return;
        }

        update_post_meta($postId, '_portal_alias', $alias);
        // Xóa cache mới để endpoint nhận ngay
        wp_cache_delete('laca_portal_key_' . md5($alias), 'laca_portal');
    }
}
