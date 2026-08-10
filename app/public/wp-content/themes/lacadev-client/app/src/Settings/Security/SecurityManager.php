<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SecurityManager
 *
 * Trang admin tổng hợp bảo mật (Appearance > 🔒 Bảo mật).
 * Đăng ký toàn bộ AJAX endpoints cho 6 tính năng bảo mật.
 *
 * Tabs:
 *   1. Kiểm tra bảo mật (Security Audit)
 *   2. Giám sát file (FIM)
 *   3. Quét mã độc (Malware Scanner)
 *   4. User ẩn (Hidden User Scanner)
 *   5. URL đăng nhập (Custom Login)
 *   6. 2FA
 *   7. Super User (đặt/bỏ quyền Administrator — tài khoản 'lacadev' được
 *      SuperUserGuard bảo vệ cứng, không hiển thị nút thao tác)
 */
class SecurityManager
{
    private const NONCE = 'laca_security_nonce';

    public function init(): void
    {
        add_action('admin_menu',            [$this, 'addMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // ── AJAX: Security Audit ───────────────────────────────────────────────
        add_action('wp_ajax_laca_security_audit',        [$this, 'ajaxAudit']);

        // ── AJAX: FIM ─────────────────────────────────────────────────────────
        add_action('wp_ajax_laca_fim_scan',              [$this, 'ajaxFimScan']);
        add_action('wp_ajax_laca_fim_update_baseline',   [$this, 'ajaxFimUpdateBaseline']);

        // ── AJAX: Malware Scanner ─────────────────────────────────────────────
        add_action('wp_ajax_laca_malware_init',          [$this, 'ajaxMalwareInit']);
        add_action('wp_ajax_laca_malware_chunk',         [$this, 'ajaxMalwareChunk']);
        add_action('wp_ajax_laca_malware_result',        [$this, 'ajaxMalwareResult']);

        // ── AJAX: Hidden User Scanner ─────────────────────────────────────────
        add_action('wp_ajax_laca_hidden_user_scan',      [$this, 'ajaxHiddenUserScan']);

        // ── AJAX: Custom Login Settings ───────────────────────────────────────
        add_action('wp_ajax_laca_save_login_settings',   [$this, 'ajaxSaveLoginSettings']);

        // ── AJAX: 2FA Master Toggle ────────────────────────────────────────────
        add_action('wp_ajax_laca_save_2fa_settings',     [$this, 'ajaxSave2faSettings']);

        // ── AJAX: Super User ─────────────────────────────────────────────────
        add_action('wp_ajax_laca_toggle_super_user',     [$this, 'ajaxToggleSuperUser']);
    }

    // ── Admin Menu ───────────────────────────────────────────────────────────

    public function addMenu(): void
    {
        add_submenu_page(
            'laca-admin',
            'Bảo mật',
            'Bảo mật',
            'manage_options',
            'laca-security',
            [$this, 'renderPage']
        );
    }

    // ── Enqueue ──────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, 'laca-security')) return;
        wp_add_inline_script('jquery', 'var lacaSecurity = ' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
        ]) . ';', 'before');
    }

    // ── Admin Page ────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        $activeTab = sanitize_key($_GET['tab'] ?? 'audit');
        $tabs = [
            'audit'   => '📊 Kiểm tra bảo mật',
            'fim'     => '🗂️ Giám sát file',
            'malware' => '🦠 Quét mã độc',
            'users'   => '👥 User ẩn',
            'login'   => '🔑 URL đăng nhập',
            '2fa'     => '📱 2FA TOTP',
            'superuser' => '👑 Super User',
        ];
        ?>
        <div class="wrap">
            <h1>🔒 Bảo mật</h1>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">
                <p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Bảo mật website</p>
                <p style="margin:0;font-size:13px;color:#374151">Trang này tập hợp các công cụ giúp bảo vệ site: kiểm tra điểm bảo mật tổng quan, giám sát file bị thay đổi, quét mã độc, phát hiện tài khoản admin ẩn, đổi URL đăng nhập để tránh dò mật khẩu, và bật xác thực 2 bước (2FA) cho tài khoản quản trị.</p>
            </div>
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-security', 'tab' => $key], admin_url('admin.php'))); ?>"
                       class="nav-tab <?php echo $activeTab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tab-content" style="background:#fff;padding:24px;border:1px solid #c3c4c7;border-top:0;margin-top:0;">
                <?php
                switch ($activeTab) {
                    case 'audit':   $this->renderAuditTab();   break;
                    case 'fim':     $this->renderFimTab();     break;
                    case 'malware': $this->renderMalwareTab(); break;
                    case 'users':   $this->renderUsersTab();   break;
                    case 'login':   $this->renderLoginTab();   break;
                    case '2fa':     $this->render2faTab();     break;
                    case 'superuser': $this->renderSuperUserTab(); break;
                }
                ?>
            </div>
        </div>
        <?php $this->renderInlineScripts(); ?>
        <?php
    }

    // ── Tab: Security Audit ───────────────────────────────────────────────────

    private function renderAuditTab(): void
    {
        ?>
        <h2>Kiểm tra bảo mật tổng quan</h2>
        <p>Đánh giá điểm bảo mật site theo nhiều hạng mục: WordPress Core, đăng nhập, server, HTTP headers...</p>
        <button id="btn-run-audit" class="button button-primary">▶ Chạy kiểm tra</button>
        <div id="audit-progress" style="display:none;margin-top:12px;color:#666;font-style:italic;">Đang phân tích...</div>
        <div id="audit-result" style="margin-top:20px;"></div>
        <?php
    }

    // ── Tab: FIM ─────────────────────────────────────────────────────────────

    private function renderFimTab(): void
    {
        $status = FileIntegrityMonitor::getStatus();
        ?>
        <h2>Giám sát toàn vẹn file (FIM)</h2>
        <?php if ($status['has_baseline']): ?>
            <p>Baseline cuối: <strong><?php echo esc_html($status['baseline_time']); ?></strong>
               (<?php echo number_format($status['file_count']); ?> file)</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                <button id="btn-fim-scan" class="button button-primary">🔍 So sánh với baseline</button>
                <button id="btn-fim-update" class="button button-secondary">🔄 Cập nhật baseline</button>
            </div>
        <?php else: ?>
            <p>Chưa có baseline. Nhấn <strong>Tạo baseline</strong> để ghi lại trạng thái file hiện tại.</p>
            <button id="btn-fim-scan" class="button button-primary">📸 Tạo baseline</button>
        <?php endif; ?>
        <div id="fim-progress" style="display:none;margin-top:12px;color:#666;font-style:italic;">Đang quét...</div>
        <div id="fim-result" style="margin-top:20px;"></div>
        <?php
    }

    // ── Tab: Malware Scanner ──────────────────────────────────────────────────

    private function renderMalwareTab(): void
    {
        ?>
        <h2>Quét mã độc hại</h2>
        <p>Phân tích PHP, JS, HTML, SVG tìm backdoor, shell, obfuscated code...</p>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
            <div>
                <strong>Loại file:</strong>
                <?php foreach (['php' => 'PHP', 'js' => 'JavaScript', 'html' => 'HTML'] as $ext => $lbl): ?>
                    <label style="margin-right:10px;">
                        <input type="checkbox" name="scan_ext[]" value="<?php echo $ext; ?>" <?php echo $ext === 'php' ? 'checked' : ''; ?>>
                        <?php echo $lbl; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button id="btn-malware-scan" class="button button-primary">🦠 Bắt đầu quét</button>
        </div>
        <div id="malware-progress" style="display:none;margin-top:12px;">
            <div id="malware-progress-bar" style="height:6px;background:#2271b1;width:0%;border-radius:3px;transition:width 0.3s;"></div>
            <div id="malware-progress-text" style="margin-top:6px;color:#666;font-size:13px;"></div>
        </div>
        <div id="malware-result" style="margin-top:20px;"></div>
        <?php
    }

    // ── Tab: Hidden Users ─────────────────────────────────────────────────────

    private function renderUsersTab(): void
    {
        ?>
        <h2>Quét User Ẩn</h2>
        <p>Phát hiện tài khoản admin/user trong database nhưng bị ẩn khỏi màn hình Người dùng wp-admin.</p>
        <button id="btn-user-scan" class="button button-primary">🔍 Quét ngay</button>
        <div id="user-scan-progress" style="display:none;margin-top:12px;color:#666;font-style:italic;">Đang quét...</div>
        <div id="user-scan-result" style="margin-top:20px;"></div>
        <?php
    }

    // ── Tab: Custom Login ─────────────────────────────────────────────────────

    private function renderLoginTab(): void
    {
        $slug    = get_option('laca_login_slug', '');
        $enabled = get_option('laca_enable_custom_login', 0);
        $homeUrl = trailingslashit(home_url());
        ?>
        <h2>Tùy chỉnh URL đăng nhập</h2>
        <p>Ẩn <code>/wp-login.php</code> và phục vụ trang đăng nhập qua URL tùy chỉnh. URL cũ trả 404.</p>
        <table class="form-table">
            <tr>
                <th>Bật tính năng</th>
                <td><label><input type="checkbox" id="laca-login-enabled" <?php checked($enabled, 1); ?>> Bật URL đăng nhập tùy chỉnh</label></td>
            </tr>
            <tr>
                <th>Slug đăng nhập</th>
                <td>
                    <span><?php echo esc_html($homeUrl); ?></span>
                    <input type="text" id="laca-login-slug" value="<?php echo esc_attr($slug); ?>"
                           style="width:200px;" placeholder="my-login">
                    <p class="description">⚠️ Lưu slug này cẩn thận — nếu quên sẽ không vào được admin!</p>
                </td>
            </tr>
        </table>
        <button id="btn-save-login" class="button button-primary">💾 Lưu cài đặt</button>
        <span id="login-save-msg" style="margin-left:10px;"></span>
        <div style="margin-top:20px;padding:14px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;">
            <strong>⚠️ Lưu ý quan trọng:</strong>
            <ul style="margin:8px 0 0 20px;">
                <li>Sau khi lưu, cài đặt chỉ có hiệu lực khi <strong>làm mới lại trang</strong> (do hook <code>plugins_loaded</code>).</li>
                <li>Ghi nhớ slug mới trước khi lưu. Nếu quên, vào DB xóa option <code>laca_login_slug</code>.</li>
                <li>URL hiện tại: <code><?php echo $slug ? esc_html($homeUrl . $slug) : '(chưa cài đặt)'; ?></code></li>
            </ul>
        </div>
        <?php
    }

    // ── Tab: 2FA ──────────────────────────────────────────────────────────────

    private function render2faTab(): void
    {
        $masterEnabled = get_option('laca_2fa_master_enabled', 0);
        ?>
        <h2>Xác thực 2 bước (2FA TOTP)</h2>
        <p>Bật 2FA toàn site — mỗi user có thể tự cài đặt trong trang Profile của mình.</p>
        <table class="form-table">
            <tr>
                <th>Bật 2FA toàn site</th>
                <td>
                    <label>
                        <input type="checkbox" id="laca-2fa-master" <?php checked($masterEnabled, 1); ?>>
                        Hiển thị tính năng 2FA trên trang Profile của tất cả user
                    </label>
                    <p class="description">Sau khi bật, mỗi user vào <a href="<?php echo admin_url('profile.php'); ?>">Hồ sơ cá nhân</a> để kích hoạt 2FA riêng.</p>
                </td>
            </tr>
        </table>
        <button id="btn-save-2fa" class="button button-primary">💾 Lưu cài đặt</button>
        <span id="2fa-save-msg" style="margin-left:10px;"></span>

        <hr style="margin:24px 0;">
        <h3>Trạng thái 2FA người dùng</h3>
        <?php
        $users = get_users(['role__in' => ['administrator', 'editor'], 'number' => 50]);
        if ($users): ?>
        <table class="wp-list-table widefat fixed striped" style="max-width:600px;">
            <thead><tr><th>User</th><th>Vai trò</th><th>2FA</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
                $enabled  = get_user_meta($u->ID, 'laca_2fa_enabled',  true);
                $verified = get_user_meta($u->ID, 'laca_2fa_verified', true);
                $status   = ($enabled && $verified) ? '<span style="color:green;">✓ Đã bật</span>' : '<span style="color:#999;">— Chưa bật</span>';
            ?>
                <tr>
                    <td><?php echo esc_html($u->user_login); ?> <small style="color:#666;"><?php echo esc_html($u->user_email); ?></small></td>
                    <td><?php echo esc_html(implode(', ', $u->roles)); ?></td>
                    <td><?php echo $status; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }

    // ── Tab: Super User ──────────────────────────────────────────────────────

    private function renderSuperUserTab(): void
    {
        $users        = get_users(['orderby' => 'registered', 'order' => 'ASC']);
        $superLogins  = \App\Settings\AdminSettings::getSuperUserLogins();
        ?>
        <h2>👑 Quản lý Super User</h2>
        <p>Super User được ẩn khỏi danh sách Người dùng đối với người khác, và không bị áp các giới hạn quản trị (chế độ bảo trì, ẩn menu, khoá cập nhật...) mà site áp dụng cho user thường.
           Tài khoản mặc định <code><?php echo esc_html(SuperUserGuard::PROTECTED_LOGIN); ?></code> luôn là Super User —
           không Super User nào khác có thể xoá hoặc đổi quyền (role) của tài khoản này.</p>
        <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
            <thead><tr><th>User</th><th>Email</th><th>Vai trò</th><th>Super User?</th><th>Hành động</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
                $isProtected = SuperUserGuard::isProtected($u->ID);
                $isSuper     = in_array($u->user_login, $superLogins, true);
            ?>
                <tr>
                    <td><strong><?php echo esc_html($u->user_login); ?></strong></td>
                    <td><?php echo esc_html($u->user_email); ?></td>
                    <td><span style="color:#666;"><?php echo esc_html(implode(', ', $u->roles)); ?></span></td>
                    <td>
                        <?php if ($isSuper): ?>
                            <span style="color:#7c3aed;font-weight:600;">👑 Super User</span>
                        <?php else: ?>
                            <span style="color:#999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isProtected): ?>
                            <span style="color:#999;">🔒 Mặc định — không thể đổi</span>
                        <?php elseif ($isSuper): ?>
                            <button class="button btn-toggle-super" data-user-id="<?php echo esc_attr($u->ID); ?>" data-do-action="demote">↩️ Bỏ Super User</button>
                        <?php else: ?>
                            <button class="button button-primary btn-toggle-super" data-user-id="<?php echo esc_attr($u->ID); ?>" data-do-action="promote">👑 Đặt Super User</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <span id="superuser-msg" style="margin-left:10px;"></span>
        <?php
    }

    // ── AJAX Handlers ────────────────────────────────────────────────────────

    private function checkNonce(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
    }

    public function ajaxAudit(): void
    {
        $this->checkNonce();
        wp_send_json_success(SecurityAudit::run());
    }

    // FIM

    public function ajaxFimScan(): void
    {
        $this->checkNonce();
        @set_time_limit(120);
        $result = FileIntegrityMonitor::compareBaseline();
        if (!empty($result['needs_init'])) {
            $init = FileIntegrityMonitor::createBaseline();
            wp_send_json_success([
                'is_init'   => true,
                'html'      => '<div class="notice notice-success inline"><p>✓ Baseline đã được tạo với <strong>' . number_format($init['total']) . '</strong> file.</p></div>',
                'base_time' => $init['time'],
                'total'     => 0,
            ]);
        }
        wp_send_json_success([
            'is_init'   => false,
            'html'      => $this->renderFimResult($result),
            'base_time' => $result['base_time'],
            'total'     => $result['total'],
        ]);
    }

    public function ajaxFimUpdateBaseline(): void
    {
        $this->checkNonce();
        @set_time_limit(120);
        $result = FileIntegrityMonitor::updateBaseline();
        wp_send_json_success([
            'message'   => '✓ Baseline đã cập nhật với ' . number_format($result['total']) . ' file.',
            'base_time' => $result['time'],
        ]);
    }

    private function renderFimResult(array $r): string
    {
        if ($r['total'] === 0) {
            return '<div class="notice notice-success inline"><p>✓ Không có thay đổi so với baseline <em>' . esc_html($r['base_time']) . '</em>.</p></div>';
        }
        ob_start();
        echo '<p>Phát hiện <strong>' . (int) $r['total'] . ' thay đổi</strong> so với baseline <em>' . esc_html($r['base_time']) . '</em>:</p>';
        foreach (['modified' => '🟡 Đã sửa', 'added' => '🟢 Mới thêm', 'deleted' => '🔴 Đã xóa'] as $key => $label) {
            if (empty($r[$key])) continue;
            echo '<h4 style="margin:12px 0 6px;">' . esc_html($label) . ' (' . count($r[$key]) . ')</h4>';
            echo '<table class="wp-list-table widefat striped"><thead><tr><th>File</th><th>Thời gian</th></tr></thead><tbody>';
            foreach ($r[$key] as $f) {
                echo '<tr><td><code>' . esc_html($f['path']) . '</code></td><td>' . esc_html($f['time']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        return ob_get_clean();
    }

    // Malware

    public function ajaxMalwareInit(): void
    {
        $this->checkNonce();
        $exts = isset($_POST['extensions']) ? array_map('sanitize_text_field', (array) $_POST['extensions']) : ['php'];
        if (empty($exts)) $exts = ['php'];
        $files  = MalwareScanner::getFileList($exts);
        $scanId = 'laca_scan_' . uniqid();
        set_transient($scanId . '_files',    $files,  600);
        set_transient($scanId . '_findings', [],      600);
        wp_send_json_success(['scan_id' => $scanId, 'total' => count($files)]);
    }

    public function ajaxMalwareChunk(): void
    {
        $this->checkNonce();
        $scanId   = sanitize_text_field($_POST['scan_id'] ?? '');
        $offset   = (int) ($_POST['offset'] ?? 0);
        $files    = get_transient($scanId . '_files');
        $findings = get_transient($scanId . '_findings');
        if ($files === false) wp_send_json_error('Phiên đã hết hạn. Vui lòng thử lại.');

        $chunk    = MalwareScanner::scanChunk($files, $offset, 50);
        $findings = array_merge($findings, $chunk);
        set_transient($scanId . '_findings', $findings, 600);

        $next  = $offset + 50;
        $total = count($files);
        wp_send_json_success([
            'done'        => $next >= $total,
            'next_offset' => $next,
            'scanned'     => min($next, $total),
            'total'       => $total,
            'findings'    => count($findings),
        ]);
    }

    public function ajaxMalwareResult(): void
    {
        $this->checkNonce();
        $scanId   = sanitize_text_field($_POST['scan_id'] ?? '');
        $findings = get_transient($scanId . '_findings');
        $files    = get_transient($scanId . '_files');
        if ($findings === false) wp_send_json_error('Phiên đã hết hạn.');

        $scanned = is_array($files) ? count($files) : 0;
        wp_send_json_success([
            'html'     => MalwareScanner::renderResults($findings, $scanned),
            'total'    => count($findings),
            'scan_id'  => $scanId,
        ]);
    }

    // Hidden Users

    public function ajaxHiddenUserScan(): void
    {
        $this->checkNonce();
        try {
            $scanner = new HiddenUserScanner();
            wp_send_json_success($scanner->scan());
        } catch (\Exception $e) {
            wp_send_json_error('Quét thất bại: ' . $e->getMessage());
        }
    }

    // Custom Login Settings

    public function ajaxSaveLoginSettings(): void
    {
        $this->checkNonce();
        $slug    = sanitize_title(trim(sanitize_text_field($_POST['slug'] ?? ''), '/'));
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        if ($enabled && empty($slug)) {
            wp_send_json_error('Slug không được để trống khi bật tính năng.');
        }
        update_option('laca_login_slug',          $slug);
        update_option('laca_enable_custom_login', $enabled);
        wp_send_json_success('Cài đặt đã lưu. Tải lại trang để áp dụng.');
    }

    // 2FA Master Setting

    public function ajaxSave2faSettings(): void
    {
        $this->checkNonce();
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        update_option('laca_2fa_master_enabled', $enabled);
        wp_send_json_success($enabled ? '2FA đã bật toàn site.' : '2FA đã tắt.');
    }

    // Super User

    public function ajaxToggleSuperUser(): void
    {
        $this->checkNonce();

        $userId   = (int) ($_POST['user_id'] ?? 0);
        $doAction = sanitize_key($_POST['do_action'] ?? '');

        if (!$userId || !in_array($doAction, ['promote', 'demote'], true)) {
            wp_send_json_error('Yêu cầu không hợp lệ.');
        }

        $user = get_userdata($userId);
        if (!$user) {
            wp_send_json_error('Không tìm thấy user.');
        }

        // Tài khoản mặc định (lacadev) không nằm trong option này — nó luôn
        // là Super User qua filter 'lacadev_super_user_logins' cứng trong
        // code, nên không thể thêm/bỏ qua UI này.
        if (SuperUserGuard::isProtected($userId)) {
            wp_send_json_error('Tài khoản mặc định luôn là Super User, không thể thay đổi qua đây.');
        }

        $extraLogins = get_option('laca_extra_super_user_logins', []);
        $extraLogins = is_array($extraLogins) ? $extraLogins : [];

        if ($doAction === 'promote') {
            $extraLogins[] = $user->user_login;
        } else {
            $extraLogins = array_diff($extraLogins, [$user->user_login]);
        }

        update_option('laca_extra_super_user_logins', array_values(array_unique($extraLogins)));

        wp_send_json_success($doAction === 'promote' ? 'Đã đặt làm Super User.' : 'Đã bỏ quyền Super User.');
    }

    // ── Inline JavaScript ─────────────────────────────────────────────────────

    private function renderInlineScripts(): void
    {
        ?>
        <style>
        .laca-scan-clean { padding:14px 18px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;color:#166534;font-size:14px; }
        .laca-scan-summary { padding:10px 16px;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;margin-bottom:12px; }
        #audit-result .audit-score-circle { display:inline-block;width:80px;height:80px;border-radius:50%;text-align:center;line-height:80px;font-size:22px;font-weight:700;border:4px solid;margin-right:16px; }
        </style>
        <script>
        (function($){
            var ajaxUrl = lacaSecurity.ajaxUrl;
            var nonce   = lacaSecurity.nonce;

            // ── Security Audit ───────────────────────────────────────────────
            $('#btn-run-audit').on('click', function(){
                $('#audit-progress').show();
                $('#audit-result').empty();
                $.post(ajaxUrl, { action:'laca_security_audit', nonce }, function(res){
                    $('#audit-progress').hide();
                    if (!res.success) { $('#audit-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    var d = res.data;
                    var color = d.score >= 80 ? '#16a34a' : d.score >= 50 ? '#d97706' : '#dc2626';
                    var verdict = d.score >= 80 ? 'Tốt' : d.score >= 50 ? 'Trung bình' : 'Yếu';
                    var html = '<div style="display:flex;align-items:center;margin-bottom:20px;">';
                    html += '<div class="audit-score-circle" style="color:'+color+';border-color:'+color+';">'+d.score+'</div>';
                    html += '<div><strong style="font-size:18px;">'+verdict+'</strong><br>';
                    html += '<span style="font-size:13px;color:#666;">Pass: '+d.pass+' | Fail: '+d.fail+' | Warning: '+d.warn+'</span></div></div>';

                    $.each(d.groups, function(cat, checks){
                        html += '<h3 style="margin:16px 0 8px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;">'+cat+'</h3>';
                        html += '<table class="wp-list-table widefat striped" style="margin-bottom:8px;"><tbody>';
                        $.each(checks, function(_, c){
                            var icon = c.status==='pass'?'✓':c.status==='fail'?'✗':c.status==='warn'?'⚠':'ℹ';
                            var col  = c.status==='pass'?'#166534':c.status==='fail'?'#dc2626':c.status==='warn'?'#b45309':'#1e40af';
                            html += '<tr><td style="width:24px;font-size:16px;color:'+col+';">'+icon+'</td>';
                            html += '<td><strong>'+c.title+'</strong><br><span style="font-size:12px;color:#555;">'+c.desc+'</span></td>';
                            html += '<td style="width:60px;text-align:right;font-size:12px;color:#999;">+'+c.points+'pt</td></tr>';
                        });
                        html += '</tbody></table>';
                    });
                    $('#audit-result').html(html);
                });
            });

            // ── FIM ──────────────────────────────────────────────────────────
            $('#btn-fim-scan').on('click', function(){
                $(this).prop('disabled',true).text('Đang quét...');
                $('#fim-progress').show();
                $('#fim-result').empty();
                $.post(ajaxUrl, { action:'laca_fim_scan', nonce }, function(res){
                    $('#btn-fim-scan').prop('disabled',false).text(res.data&&res.data.is_init?'📸 Tạo baseline':'🔍 So sánh với baseline');
                    $('#fim-progress').hide();
                    if (!res.success) { $('#fim-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    $('#fim-result').html(res.data.html);
                });
            });

            $('#btn-fim-update').on('click', function(){
                if (!confirm('Cập nhật baseline sẽ ghi đè trạng thái file hiện tại. Tiếp tục?')) return;
                $(this).prop('disabled',true).text('Đang cập nhật...');
                $.post(ajaxUrl, { action:'laca_fim_update_baseline', nonce }, function(res){
                    $('#btn-fim-update').prop('disabled',false).text('🔄 Cập nhật baseline');
                    alert(res.success ? res.data.message : 'Lỗi: ' + res.data);
                    if (res.success) location.reload();
                });
            });

            // ── Malware Scanner ───────────────────────────────────────────────
            var scanId = null;
            $('#btn-malware-scan').on('click', function(){
                var exts = [];
                $('input[name="scan_ext[]"]:checked').each(function(){ exts.push($(this).val()); });
                if (!exts.length) { alert('Chọn ít nhất 1 loại file.'); return; }
                $(this).prop('disabled',true).text('Đang quét...');
                $('#malware-progress').show();
                $('#malware-progress-bar').css('width','0%');
                $('#malware-progress-text').text('Đang khởi tạo...');
                $('#malware-result').empty();

                $.post(ajaxUrl, { action:'laca_malware_init', nonce, extensions: exts }, function(res){
                    if (!res.success) { finishScan('Lỗi: '+res.data); return; }
                    scanId = res.data.scan_id;
                    var total = res.data.total;
                    $('#malware-progress-text').text('Tìm thấy '+total+' file cần quét.');
                    scanChunk(0, total);
                });
            });

            function scanChunk(offset, total){
                $.post(ajaxUrl, { action:'laca_malware_chunk', nonce, scan_id:scanId, offset }, function(res){
                    if (!res.success) { finishScan('Lỗi khi quét: '+res.data); return; }
                    var d = res.data;
                    var pct = total > 0 ? Math.round((d.scanned/total)*100) : 100;
                    $('#malware-progress-bar').css('width', pct+'%');
                    $('#malware-progress-text').text(d.scanned+' / '+total+' file | Phát hiện: '+d.findings);
                    if (d.done) {
                        getResults();
                    } else {
                        scanChunk(d.next_offset, total);
                    }
                }).fail(function(){ finishScan('Lỗi kết nối.'); });
            }

            function getResults(){
                $.post(ajaxUrl, { action:'laca_malware_result', nonce, scan_id:scanId }, function(res){
                    finishScan(null);
                    if (!res.success) { $('#malware-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    $('#malware-result').html(res.data.html);
                });
            }

            function finishScan(errMsg){
                $('#btn-malware-scan').prop('disabled',false).text('🦠 Bắt đầu quét');
                if (errMsg) { $('#malware-progress').hide(); $('#malware-result').html('<p style="color:red;">'+errMsg+'</p>'); }
                else { $('#malware-progress').hide(); }
            }

            // ── Hidden Users ──────────────────────────────────────────────────
            $('#btn-user-scan').on('click', function(){
                $(this).prop('disabled',true).text('Đang quét...');
                $('#user-scan-progress').show();
                $('#user-scan-result').empty();
                $.post(ajaxUrl, { action:'laca_hidden_user_scan', nonce }, function(res){
                    $('#btn-user-scan').prop('disabled',false).text('🔍 Quét ngay');
                    $('#user-scan-progress').hide();
                    if (!res.success) { $('#user-scan-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    var d = res.data;
                    var html = renderUserScanResult(d);
                    $('#user-scan-result').html(html);
                });
            });

            function renderUserScanResult(d){
                var s = d.summary;
                var html = '<p>DB: <strong>'+s.db_total+'</strong> | Chuẩn: <strong>'+s.standard_query_total+'</strong> | Admin ẩn: <strong style="color:'+(s.hidden_admin_total?'#dc2626':'#16a34a')+';">'+s.hidden_admin_total+'</strong> | User ẩn: <strong>'+s.hidden_site_user_total+'</strong> | Nghi ngờ: <strong>'+s.suspicious_user_total+'</strong></p>';

                if (d.hidden_admins && d.hidden_admins.length) {
                    html += '<h3 style="color:#dc2626;">🚨 Admin ẩn ('+d.hidden_admins.length+')</h3>';
                    html += renderUserTable(d.hidden_admins);
                }
                if (d.suspicious_users && d.suspicious_users.length) {
                    html += '<h3 style="color:#d97706;">⚠️ User nghi ngờ ('+d.suspicious_users.length+')</h3>';
                    html += renderUserTable(d.suspicious_users);
                }
                if (!d.hidden_admins.length && !d.suspicious_users.length) {
                    html += '<div class="laca-scan-clean">✓ Không phát hiện user ẩn hoặc nghi ngờ.</div>';
                }
                if (d.hook_findings && d.hook_findings.length) {
                    html += '<h3>🔗 Hook callbacks ('+d.hook_findings.length+')</h3>';
                    html += '<table class="wp-list-table widefat striped"><thead><tr><th>Hook</th><th>Priority</th><th>Callback</th></tr></thead><tbody>';
                    $.each(d.hook_findings, function(_,h){ html += '<tr><td>'+h.hook+'</td><td>'+h.priority+'</td><td><code>'+h.callback+'</code></td></tr>'; });
                    html += '</tbody></table>';
                }
                return html;
            }

            function renderUserTable(users){
                var html = '<table class="wp-list-table widefat striped"><thead><tr><th>ID</th><th>Login</th><th>Email</th><th>Roles</th><th>Đăng ký</th><th>Flags</th></tr></thead><tbody>';
                $.each(users, function(_,u){
                    html += '<tr>';
                    html += '<td>'+u.id+'</td>';
                    html += '<td><strong>'+u.login+'</strong></td>';
                    html += '<td>'+u.email+'</td>';
                    html += '<td>'+u.roles_label+'</td>';
                    html += '<td>'+u.registered+'</td>';
                    html += '<td><small style="color:#666;">'+u.reasons.join('<br>')+'</small></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                return html;
            }

            // ── Custom Login ──────────────────────────────────────────────────
            $('#btn-save-login').on('click', function(){
                $(this).prop('disabled',true);
                $('#login-save-msg').text('Đang lưu...').css('color','#666');
                $.post(ajaxUrl, {
                    action: 'laca_save_login_settings',
                    nonce,
                    slug:    $('#laca-login-slug').val(),
                    enabled: $('#laca-login-enabled').is(':checked') ? 1 : 0,
                }, function(res){
                    $('#btn-save-login').prop('disabled',false);
                    if (res.success) { $('#login-save-msg').text('✓ '+res.data).css('color','green'); }
                    else             { $('#login-save-msg').text('✗ '+res.data).css('color','red'); }
                });
            });

            // ── 2FA Master ────────────────────────────────────────────────────
            $('#btn-save-2fa').on('click', function(){
                $(this).prop('disabled',true);
                $('#2fa-save-msg').text('Đang lưu...').css('color','#666');
                $.post(ajaxUrl, {
                    action:  'laca_save_2fa_settings',
                    nonce,
                    enabled: $('#laca-2fa-master').is(':checked') ? 1 : 0,
                }, function(res){
                    $('#btn-save-2fa').prop('disabled',false);
                    if (res.success) { $('#2fa-save-msg').text('✓ '+res.data).css('color','green'); }
                    else             { $('#2fa-save-msg').text('✗ '+res.data).css('color','red'); }
                });
            });

            // ── Super User ────────────────────────────────────────────────────
            $('.btn-toggle-super').on('click', function(){
                var $btn = $(this);
                var userId = $btn.data('user-id');
                var doAction = $btn.data('do-action');
                var confirmMsg = doAction === 'promote'
                    ? 'Đặt user này làm Super User (Administrator)?'
                    : 'Bỏ quyền Super User của user này?';
                if (!confirm(confirmMsg)) return;
                $btn.prop('disabled', true);
                $('#superuser-msg').text('Đang xử lý...').css('color', '#666');
                $.post(ajaxUrl, { action: 'laca_toggle_super_user', nonce, user_id: userId, do_action: doAction }, function(res){
                    if (res.success) {
                        $('#superuser-msg').text('✓ ' + res.data).css('color', 'green');
                        location.reload();
                    } else {
                        $btn.prop('disabled', false);
                        $('#superuser-msg').text('✗ ' + res.data).css('color', 'red');
                    }
                });
            });

        }(jQuery));
        </script>
        <?php
    }
}
