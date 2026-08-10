<?php

namespace App\Settings;

/**
 * MaintenanceModeManager
 *
 * Toggle maintenance mode từ Admin Bar — 1 click, không cần SSH.
 *
 * - Admin bar: nút bật/tắt với AJAX (không reload trang)
 * - Khi bật: redirect mọi visitor đến theme/maintenance.php (HTTP 503)
 * - Whitelist: admin logged-in vẫn thấy site bình thường
 * - Tùy chọn: whitelist thêm IP cụ thể qua wp-option
 *
 * Option keys:
 *   laca_maintenance_mode  → '1' | '0'
 *   laca_maintenance_ip_whitelist → comma-separated IPs
 */
class MaintenanceModeManager
{
    const OPT_ACTIVE    = 'laca_maintenance_mode';
    const OPT_WHITELIST = 'laca_maintenance_ip_whitelist';
    const NONCE         = 'laca_maintenance_toggle';

    public function init(): void
    {
        // Frontend: redirect to maintenance if active
        add_action('template_redirect', [$this, 'maybeShowMaintenance'], 1);

        // Admin bar toggle button
        add_action('admin_bar_menu', [$this, 'addAdminBarNode'], 90);

        // Inline styles for admin bar node
        add_action('admin_head',   [$this, 'renderAdminBarStyle']);
        add_action('wp_head',      [$this, 'renderAdminBarStyle']);

        // AJAX toggle
        add_action('wp_ajax_laca_toggle_maintenance', [$this, 'handleAjaxToggle']);

        // Admin notices
        add_action('admin_notices', [$this, 'renderAdminNotice']);
    }

    // =========================================================================
    // MAINTENANCE GATE
    // =========================================================================

    public function maybeShowMaintenance(): void
    {
        if (!get_option(self::OPT_ACTIVE)) {
            return;
        }

        // Admins bypass
        if (current_user_can('manage_options')) {
            return;
        }

        // IP whitelist bypass
        $whitelist = array_filter(array_map('trim', explode(',', get_option(self::OPT_WHITELIST, ''))));
        if (!empty($whitelist)) {
            $clientIp = self::getClientIp();
            if (in_array($clientIp, $whitelist, true)) {
                return;
            }
        }

        // Serve maintenance template with 503
        http_response_code(503);
        header('Retry-After: 3600');

        $maintenanceFile = get_template_directory() . '/maintenance.php';
        if (file_exists($maintenanceFile)) {
            include $maintenanceFile;
        } else {
            echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>🛠 Đang bảo trì</h1><p>Website đang được nâng cấp. Vui lòng quay lại sau.</p></body></html>';
        }
        exit;
    }

    // =========================================================================
    // ADMIN BAR
    // =========================================================================

    public function addAdminBarNode(\WP_Admin_Bar $bar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $isActive = (bool) get_option(self::OPT_ACTIVE);
        $nonce    = wp_create_nonce(self::NONCE);

        $bar->add_node([
            'id'    => 'laca_maintenance',
            'title' => $isActive
                ? '<span class="laca-maint-dot laca-maint-dot--on"></span> Maintenance: BẬT'
                : '<span class="laca-maint-dot laca-maint-dot--off"></span> Maintenance: TẮT',
            'href'  => '#',
            'meta'  => [
                'class'   => 'laca-maint-toggle' . ($isActive ? ' laca-maint-active' : ''),
                'onclick' => "lacaToggleMaintenance(event, '{$nonce}'); return false;",
                'title'   => $isActive ? 'Click để TẮT maintenance mode' : 'Click để BẬT maintenance mode',
            ],
        ]);

        // Inline script for toggle
        $ajaxUrl = esc_js(admin_url('admin-ajax.php'));
        echo "
        <script>
        function lacaToggleMaintenance(e, nonce) {
            e.preventDefault();
            const node = e.currentTarget;
            node.style.opacity = '0.6';
            fetch('{$ajaxUrl}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=laca_toggle_maintenance&_nonce=' + nonce
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) window.location.reload();
                else alert(res.data || 'Lỗi khi toggle.');
            })
            .catch(() => { node.style.opacity='1'; alert('Lỗi kết nối.'); });
        }
        </script>
        ";
    }

    public function renderAdminBarStyle(): void
    {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        echo '
        <style>
        #wp-admin-bar-laca_maintenance > .ab-item { display:flex !important; align-items:center; gap:6px; }
        .laca-maint-dot { display:inline-block; width:9px; height:9px; border-radius:50%; flex-shrink:0; }
        .laca-maint-dot--on  { background:#f44336; box-shadow:0 0 0 2px rgba(244,67,54,.35); }
        .laca-maint-dot--off { background:#4caf50; box-shadow:0 0 0 2px rgba(76,175,80,.35); }
        #wp-admin-bar-laca_maintenance.laca-maint-active > .ab-item { color:#ffb3b3 !important; }
        </style>
        ';
    }

    // =========================================================================
    // AJAX TOGGLE
    // =========================================================================

    public function handleAjaxToggle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Không có quyền.', 403);
        }

        if (!check_ajax_referer(self::NONCE, '_nonce', false)) {
            wp_send_json_error('Nonce không hợp lệ.', 403);
        }

        $current = (bool) get_option(self::OPT_ACTIVE);
        update_option(self::OPT_ACTIVE, $current ? '0' : '1', false);

        wp_send_json_success(['active' => !$current]);
    }

    // =========================================================================
    // ADMIN NOTICE
    // =========================================================================

    public function renderAdminNotice(): void
    {
        if (!get_option(self::OPT_ACTIVE) || !current_user_can('manage_options')) {
            return;
        }
        echo '
        <div class="notice notice-warning" style="display:flex;align-items:center;gap:10px;padding:10px 15px">
            <span style="font-size:18px">🛠</span>
            <p style="margin:0"><strong>Maintenance Mode đang BẬT.</strong> Khách truy cập sẽ thấy trang bảo trì.
            Click nút "<strong>Maintenance: BẬT</strong>" trên thanh admin bar để tắt.</p>
        </div>
        ';
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private static function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }
}
