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
        add_action('admin_init',            [$this, 'redirectLegacyTabs']);
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
        add_action('wp_ajax_laca_malware_whitelist_add', [$this, 'ajaxMalwareWhitelistAdd']);

        // ── AJAX: Hidden User Scanner ─────────────────────────────────────────
        add_action('wp_ajax_laca_hidden_user_scan',      [$this, 'ajaxHiddenUserScan']);

        // ── AJAX: Custom Login Settings ───────────────────────────────────────
        add_action('wp_ajax_laca_save_login_settings',       [$this, 'ajaxSaveLoginSettings']);
        add_action('wp_ajax_laca_save_login_guard_settings', [$this, 'ajaxSaveLoginGuardSettings']);
        add_action('wp_ajax_laca_save_login_policy',         [$this, 'ajaxSaveLoginPolicy']);
        add_action('wp_ajax_laca_login_notify_test',         [$this, 'ajaxLoginNotifyTest']);

        // ── AJAX: 2FA Master Toggle ────────────────────────────────────────────
        add_action('wp_ajax_laca_save_2fa_settings',         [$this, 'ajaxSave2faSettings']);

        // ── AJAX: Security Hardening Settings ──────────────────────────────────
        add_action('wp_ajax_laca_save_hardening_settings',   [$this, 'ajaxSaveHardeningSettings']);
        add_action('wp_ajax_laca_save_security_headers',     [$this, 'ajaxSaveSecurityHeaders']);
        add_action('wp_ajax_laca_check_security_headers',    [$this, 'ajaxCheckSecurityHeaders']);

        // ── AJAX: Super User ─────────────────────────────────────────────────
        add_action('wp_ajax_laca_toggle_super_user',         [$this, 'ajaxToggleSuperUser']);
    }

    // ── Admin Menu ───────────────────────────────────────────────────────────

    public const PAGE_2FA        = 'laca-2fa';
    public const PAGE_SUPER_USER = 'laca-super-user';

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

        // 2FA thuộc nhóm "Quản lý người dùng", Super User là menu riêng —
        // tách khỏi trang Bảo mật (xem LacaAdminMenuOrganizer::GROUPS).
        add_submenu_page(
            'laca-admin',
            __('Xác thực 2 bước (2FA)', 'laca'),
            __('Xác thực 2 bước (2FA)', 'laca'),
            'manage_options',
            self::PAGE_2FA,
            [$this, 'renderTwoFactorPage']
        );

        add_submenu_page(
            'laca-admin',
            __('Super User', 'laca'),
            __('Super User', 'laca'),
            'manage_options',
            self::PAGE_SUPER_USER,
            [$this, 'renderSuperUserPage']
        );
    }

    /**
     * Link cũ ?page=laca-security&tab=2fa|superuser → chuyển sang trang mới.
     */
    public function redirectLegacyTabs(): void
    {
        if (($_GET['page'] ?? '') !== 'laca-security') {
            return;
        }
        $map = ['2fa' => self::PAGE_2FA, 'superuser' => self::PAGE_SUPER_USER];
        $tab = sanitize_key($_GET['tab'] ?? '');
        if (isset($map[$tab])) {
            wp_safe_redirect(admin_url('admin.php?page=' . $map[$tab]));
            exit;
        }
    }

    public function renderTwoFactorPage(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Xác thực 2 bước (2FA)', 'laca') . '</h1>';
        echo '<div class="tab-content laca-security-tab-content">';
        $this->render2faTab();
        echo '</div></div>';
        $this->renderInlineScripts();
    }

    public function renderSuperUserPage(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Super User', 'laca') . '</h1>';
        echo '<div class="tab-content laca-security-tab-content">';
        $this->renderSuperUserTab();
        echo '</div></div>';
        $this->renderInlineScripts();
    }

    // ── Enqueue ──────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, 'laca-security')
            && !str_contains($hook, self::PAGE_2FA)
            && !str_contains($hook, self::PAGE_SUPER_USER)) {
            return;
        }
        wp_add_inline_script('jquery', 'var lacaSecurity = ' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
            'i18n'    => [
                'saving'        => __('Đang lưu…', 'laca'),
                'save'          => __('Lưu', 'laca'),
                'saveSettings'  => __('Lưu cài đặt', 'laca'),
                'saveHardening' => __('Lưu thiết lập bảo vệ', 'laca'),
                'unlock'        => __('Mở khoá', 'laca'),
                'unlocking'     => __('Đang mở khoá…', 'laca'),
                'testing'       => __('Đang gửi…', 'laca'),
                'testNotify'    => __('Gửi thử', 'laca'),
                'checking'      => __('Đang kiểm tra…', 'laca'),
                'checkHeaders'  => __('Kiểm tra header', 'laca'),
                'colHeader'     => __('Header', 'laca'),
                'colValue'      => __('Giá trị', 'laca'),
                'colStatus'     => __('Trạng thái', 'laca'),
                'statusSent'    => __('Đang gửi', 'laca'),
                'statusMissing' => __('Không có', 'laca'),
                'netError'      => __('Lỗi kết nối máy chủ.', 'laca'),
            ],
        ]) . ';', 'before');
    }

    // ── Admin Page ────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        $activeTab = sanitize_key($_GET['tab'] ?? 'audit');
        $tabs = [
            'audit'     => __('Kiểm tra bảo mật', 'laca'),
            'fim'       => __('Giám sát file', 'laca'),
            'malware'   => __('Quét mã độc', 'laca'),
            'users'     => __('User ẩn', 'laca'),
            'login'     => __('Đăng nhập', 'laca'),
            'hardening' => __('Gia cố', 'laca'),
        ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Bảo mật', 'laca'); ?></h1>
            <p class="laca-page-intro"><?php echo esc_html__('Trang này tập hợp các công cụ giúp bảo vệ site: kiểm tra điểm bảo mật tổng quan, giám sát file bị thay đổi, quét mã độc, phát hiện tài khoản admin ẩn, bảo vệ đăng nhập và gia cố hệ thống. Xác thực 2 bước (2FA) và Super User nằm ở menu riêng.', 'laca'); ?></p>
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-security', 'tab' => $key], admin_url('admin.php'))); ?>"
                       class="nav-tab <?php echo $activeTab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tab-content laca-security-tab-content">
                <?php
                switch ($activeTab) {
                    case 'audit':     $this->renderAuditTab();     break;
                    case 'fim':       $this->renderFimTab();       break;
                    case 'malware':   $this->renderMalwareTab();   break;
                    case 'users':     $this->renderUsersTab();     break;
                    case 'login':     $this->renderLoginTab();     break;
                    case 'hardening': $this->renderHardeningTab(); break;
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
        <div id="audit-progress" class="laca-security-progress" style="display:none; /* động */">Đang phân tích...</div>
        <div id="audit-result" class="laca-security-result"></div>
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
            <div class="laca-security-flex">
                <button id="btn-fim-scan" class="button button-primary">So sánh với baseline</button>
                <button id="btn-fim-update" class="button button-secondary">Cập nhật baseline</button>
            </div>
        <?php else: ?>
            <p>Chưa có baseline. Nhấn <strong>Tạo baseline</strong> để ghi lại trạng thái file hiện tại.</p>
            <button id="btn-fim-scan" class="button button-primary">Tạo baseline</button>
        <?php endif; ?>
        <div id="fim-progress" class="laca-security-progress" style="display:none; /* động */">Đang quét...</div>
        <div id="fim-result" class="laca-security-result"></div>
        <?php
    }

    // ── Tab: Malware Scanner ──────────────────────────────────────────────────

    private function renderMalwareTab(): void
    {
        ?>
        <h2>Quét mã độc hại</h2>
        <p>Phân tích PHP, JS, HTML, SVG tìm backdoor, shell, obfuscated code...</p>
        <div class="laca-security-flex">
            <div>
                <strong>Loại file:</strong>
                <?php foreach (['php' => 'PHP', 'js' => 'JavaScript', 'html' => 'HTML'] as $ext => $lbl): ?>
                    <label style="margin-right:10px; /* động */">
                        <input type="checkbox" name="scan_ext[]" value="<?php echo $ext; ?>" <?php echo $ext === 'php' ? 'checked' : ''; ?>>
                        <?php echo $lbl; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button id="btn-malware-scan" class="button button-primary">Bắt đầu quét</button>
        </div>
        <div id="malware-progress" class="laca-security-progress" style="display:none; /* động */">
            <div class="laca-progress" style="margin-top:12px;">
                <div id="malware-progress-bar" class="laca-progress__bar" style="width:0%; /* động */"></div>
            </div>
            <div id="malware-progress-text" class="laca-progress-text"></div>
        </div>
        <div id="malware-result" class="laca-security-result"></div>
        <?php
    }

    // ── Tab: Hidden Users ─────────────────────────────────────────────────────

    private function renderUsersTab(): void
    {
        ?>
        <h2>Quét User Ẩn</h2>
        <p>Phát hiện tài khoản admin/user trong database nhưng bị ẩn khỏi màn hình Người dùng wp-admin.</p>
        <button id="btn-user-scan" class="button button-primary">Quét ngay</button>
        <div id="user-scan-progress" class="laca-security-progress" style="display:none; /* động */">Đang quét...</div>
        <div id="user-scan-result" class="laca-security-result"></div>
        <?php
    }

    // ── Tab: Custom Login & Login Protection ─────────────────────────────────

    private function renderLoginTab(): void
    {
        $lockedIps   = LoginGuard::getLockedIps();
        $lockedCount = count($lockedIps);
        $unlockNonce = wp_create_nonce('laca_login_guard_nonce');

        $slug    = get_option('laca_login_slug', '');
        $enabled = get_option('laca_enable_custom_login', 0);
        $homeUrl = trailingslashit(home_url());

        $lgConfig       = LoginGuard::config();
        $maxFails       = max(3, min(20, (int) ($lgConfig['max_fails'] ?? 5)));
        $windowMinutes  = max(1, (int) round(((int) ($lgConfig['window'] ?? (15 * MINUTE_IN_SECONDS))) / MINUTE_IN_SECONDS));
        $lockoutMinutes = max(1, (int) round(((int) ($lgConfig['lockout'] ?? (30 * MINUTE_IN_SECONDS))) / MINUTE_IN_SECONDS));
        $strikesForLong = max(1, (int) ($lgConfig['strikes_for_long'] ?? 3));
        $longLockHours  = max(1, (int) round(((int) ($lgConfig['long_lockout'] ?? DAY_IN_SECONDS)) / HOUR_IN_SECONDS));
        $whitelistText  = implode("\n", (array) ($lgConfig['whitelist_ips'] ?? []));

        $myIp = class_exists(\App\Helpers\ClientIp::class)
            ? \App\Helpers\ClientIp::get()
            : sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($myIp === 'unknown') {
            $myIp = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        }

        $honeypotOn = get_option('laca_login_honeypot', 'yes') === 'yes';

        $policy             = class_exists(LoginPolicy::class) ? LoginPolicy::config() : [];
        $allRoles           = function_exists('wp_roles') ? wp_roles()->get_names() : [];
        $is2faMasterEnabled = (int) get_option('laca_enable_2fa', 0) === 1;

        $hasDiscord     = class_exists(\App\Features\DiscordHub\Settings::class);
        $discordStatus  = $hasDiscord
            ? \App\Features\DiscordHub\Settings::channelStatus(\App\Features\DiscordHub\Settings::CHANNEL_SECURITY)
            : ['state' => 'empty'];
        $discordCanSend = $hasDiscord
            && \App\Features\DiscordHub\Settings::canSend(\App\Features\DiscordHub\Settings::CHANNEL_SECURITY)
            && ($discordStatus['state'] ?? '') === 'ok';
        $discordUrl     = admin_url('admin.php?page=laca-discord-hub');
        ?>
        <div class="laca-settings-stack">
            <!-- 1. Card: IP đang bị khoá -->
            <div class="laca-card">
                <div class="laca-card__header laca-card__header--flex">
                    <div>
                        <h2 class="laca-card__header-title">
                            <?php echo esc_html__('IP đang bị khoá', 'laca'); ?>
                            <span id="laca-locked-ip-badge" class="laca-badge laca-badge--danger" style="<?php echo $lockedCount > 0 ? '' : 'display:none;'; ?>"><?php echo (int) $lockedCount; ?></span>
                        </h2>
                        <p class="laca-card__header-description"><?php echo esc_html__('Danh sách địa chỉ IP đang bị khoá tạm thời do đăng nhập sai quá ngưỡng.', 'laca'); ?></p>
                    </div>
                </div>
                <div class="laca-card__body">
                    <div id="laca-locked-ips-alert" class="laca-alert-slot"></div>

                    <div id="laca-locked-ips-empty" class="laca-empty" style="<?php echo $lockedCount > 0 ? 'display:none;' : ''; ?>">
                        <p class="laca-empty__description"><?php echo esc_html__('Không có IP nào đang bị khoá.', 'laca'); ?></p>
                    </div>

                    <div id="laca-locked-ips-wrap" class="laca-table-wrap" style="<?php echo $lockedCount > 0 ? '' : 'display:none;'; ?>">
                        <table class="laca-table" id="laca-locked-ips-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('IP', 'laca'); ?></th>
                                    <th><?php echo esc_html__('Khoá đến', 'laca'); ?></th>
                                    <th><?php echo esc_html__('Còn lại', 'laca'); ?></th>
                                    <th class="laca-col-right"><?php echo esc_html__('Thao tác', 'laca'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lockedIps as $ip => $until):
                                    $untilTs    = (int) $until;
                                    $remSeconds = max(1, $untilTs - time());
                                    if ($remSeconds >= HOUR_IN_SECONDS) {
                                        /* translators: %d: số giờ còn lại */
                                        $remLabel = sprintf(__('%d giờ', 'laca'), (int) ceil($remSeconds / HOUR_IN_SECONDS));
                                    } else {
                                        /* translators: %d: số phút còn lại */
                                        $remLabel = sprintf(__('%d phút', 'laca'), max(1, (int) ceil($remSeconds / MINUTE_IN_SECONDS)));
                                    }
                                ?>
                                <tr data-ip="<?php echo esc_attr($ip); ?>">
                                    <td><code class="laca-mono"><?php echo esc_html($ip); ?></code></td>
                                    <td><?php echo esc_html(wp_date('H:i d/m', $untilTs)); ?></td>
                                    <td><?php echo esc_html($remLabel); ?></td>
                                    <td class="laca-col-right">
                                        <button type="button"
                                            class="laca-btn laca-btn--outline laca-btn--xs js-laca-unlock-ip"
                                            data-ip="<?php echo esc_attr($ip); ?>"
                                            data-nonce="<?php echo esc_attr($unlockNonce); ?>">
                                            <?php echo esc_html__('Mở khoá', 'laca'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 2. Card: Tùy chỉnh URL đăng nhập (giữ nguyên chức năng, đặt vị trí 2) -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Tùy chỉnh URL đăng nhập', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php echo wp_kses_post(__('Ẩn <code>/wp-login.php</code> và phục vụ trang đăng nhập qua URL tùy chỉnh. URL cũ trả 404.', 'laca')); ?></p>
                </div>
                <div class="laca-card__body">
                    <table class="form-table">
                        <tr>
                            <th><?php echo esc_html__('Bật tính năng', 'laca'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="laca-login-enabled" <?php checked($enabled, 1); ?>>
                                    <?php echo esc_html__('Bật URL đăng nhập tùy chỉnh', 'laca'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('Slug đăng nhập', 'laca'); ?></th>
                            <td>
                                <span><?php echo esc_html($homeUrl); ?></span>
                                <input type="text" id="laca-login-slug" value="<?php echo esc_attr($slug); ?>"
                                       class="laca-login-slug-input" placeholder="<?php echo esc_attr__('my-login', 'laca'); ?>">
                                <p class="description"><?php echo esc_html__('⚠️ Lưu slug này cẩn thận — nếu quên sẽ không vào được admin!', 'laca'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <button id="btn-save-login" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lưu cài đặt', 'laca'); ?></button>
                    <span id="login-save-msg" class="laca-ml-2"></span>
                    <div id="laca-login-slug-alert" class="laca-alert-slot"></div>
                    <div class="laca-alert laca-alert--warning" style="margin-top:20px;">
                        <div class="laca-alert__content">
                            <p class="laca-alert__title"><?php echo esc_html__('⚠️ Lưu ý quan trọng:', 'laca'); ?></p>
                            <ul style="margin:8px 0 0 20px;">
                                <li><?php echo wp_kses_post(__('Sau khi lưu, cài đặt chỉ có hiệu lực khi <strong>làm mới lại trang</strong> (do hook <code>plugins_loaded</code>).', 'laca')); ?></li>
                                <li><?php echo wp_kses_post(__('Ghi nhớ slug mới trước khi lưu. Nếu quên, vào DB xóa option <code>laca_login_slug</code>.', 'laca')); ?></li>
                                <li><?php echo esc_html__('URL hiện tại:', 'laca'); ?> <code><?php echo $slug ? esc_html($homeUrl . $slug) : esc_html__('(chưa cài đặt)', 'laca'); ?></code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Card: Giới hạn đăng nhập sai -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Giới hạn đăng nhập sai', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php echo esc_html__('Tự động khoá IP khi đăng nhập sai nhiều lần liên tiếp (áp dụng cho wp-login.php, URL tuỳ chỉnh, WooCommerce và XML-RPC).', 'laca'); ?></p>
                </div>
                <div class="laca-card__body">
                    <div class="laca-grid-2">
                        <div class="laca-field">
                            <label class="laca-field__label" for="laca-lg-max-fails"><?php echo esc_html__('Số lần sai tối đa (3–20)', 'laca'); ?></label>
                            <input type="number" id="laca-lg-max-fails" class="laca-input" min="3" max="20" value="<?php echo esc_attr((string) $maxFails); ?>">
                        </div>
                        <div class="laca-field">
                            <label class="laca-field__label" for="laca-lg-window"><?php echo esc_html__('Trong khoảng (phút)', 'laca'); ?></label>
                            <input type="number" id="laca-lg-window" class="laca-input" min="1" max="1440" value="<?php echo esc_attr((string) $windowMinutes); ?>">
                        </div>
                        <div class="laca-field">
                            <label class="laca-field__label" for="laca-lg-lockout"><?php echo esc_html__('Khoá trong (phút)', 'laca'); ?></label>
                            <input type="number" id="laca-lg-lockout" class="laca-input" min="1" max="1440" value="<?php echo esc_attr((string) $lockoutMinutes); ?>">
                        </div>
                        <div class="laca-field">
                            <label class="laca-field__label" for="laca-lg-strikes"><?php echo esc_html__('Khoá dài sau N lần bị khoá', 'laca'); ?></label>
                            <input type="number" id="laca-lg-strikes" class="laca-input" min="1" max="20" value="<?php echo esc_attr((string) $strikesForLong); ?>">
                        </div>
                        <div class="laca-field">
                            <label class="laca-field__label" for="laca-lg-long-lockout"><?php echo esc_html__('Thời gian khoá dài (giờ)', 'laca'); ?></label>
                            <input type="number" id="laca-lg-long-lockout" class="laca-input" min="1" max="720" value="<?php echo esc_attr((string) $longLockHours); ?>">
                        </div>
                    </div>

                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-field__label" for="laca-lg-whitelist"><?php echo esc_html__('IP luôn được phép', 'laca'); ?></label>
                        <textarea id="laca-lg-whitelist" class="laca-textarea laca-mono" rows="4" placeholder="127.0.0.1"><?php echo esc_textarea($whitelistText); ?></textarea>
                        <div class="laca-field__help">
                            <?php echo esc_html__('Mỗi dòng 1 địa chỉ IP (IPv4 hoặc IPv6). Các dòng sai định dạng sẽ tự động bị bỏ qua khi lưu.', 'laca'); ?>
                        </div>
                        <?php if ($myIp && filter_var($myIp, FILTER_VALIDATE_IP)): ?>
                            <div class="laca-field-actions laca-mt-2">
                                <button type="button" id="btn-add-my-ip" class="laca-btn laca-btn--outline laca-btn--xs" data-ip="<?php echo esc_attr($myIp); ?>">
                                    <?php
                                    /* translators: %s: địa chỉ IP hiện tại của admin */
                                    echo esc_html(sprintf(__('Thêm IP của tôi (%s)', 'laca'), $myIp));
                                    ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="laca-login-guard-alert" class="laca-alert-slot"></div>
                </div>
                <div class="laca-card__footer">
                    <button type="button" id="btn-save-login-guard" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lưu', 'laca'); ?></button>
                </div>
            </div>

            <!-- 4. Card: Chống bot -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Chống bot', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body">
                    <label class="laca-switch">
                        <input type="checkbox" id="laca-login-honeypot" class="laca-switch__input" <?php checked($honeypotOn); ?>>
                        <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                        <span class="laca-switch__label"><?php echo esc_html__('Honeypot form đăng nhập', 'laca'); ?></span>
                    </label>
                    <p class="laca-field__help"><?php echo esc_html__('Chèn trường ẩn vào form đăng nhập để tự động phát hiện và từ chối bot điền tự động, hoạt cả khi không bật reCAPTCHA.', 'laca'); ?></p>
                    <div id="laca-honeypot-alert" class="laca-alert-slot"></div>
                </div>
                <div class="laca-card__footer">
                    <button type="button" id="btn-save-honeypot" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lưu', 'laca'); ?></button>
                </div>
            </div>

            <!-- Card: Chính sách đăng nhập -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Chính sách đăng nhập', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php echo esc_html__('Quy định nghiêm ngặt cho quá trình đăng nhập và bảo vệ tài khoản (chống brute-force nâng cao, mật khẩu lộ, ép 2FA).', 'laca'); ?></p>
                </div>
                <div class="laca-card__body">
                    <!-- 1. lock_invalid_usernames -->
                    <div class="laca-field">
                        <label class="laca-switch">
                            <input type="checkbox" id="laca-lp-lock-invalid" class="laca-switch__input" <?php checked(!empty($policy['lock_invalid_usernames'])); ?>>
                            <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                            <span class="laca-switch__label"><?php echo esc_html__('Khoá IP ngay khi đăng nhập bằng username không tồn tại', 'laca'); ?></span>
                        </label>
                        <p class="laca-field__help"><?php echo esc_html__('Ngăn kẻ tấn công dùng từ điển dò username. Nếu tên người dùng không tồn tại trong hệ thống, IP sẽ bị khoá ngay ở lần thử đầu tiên.', 'laca'); ?></p>
                    </div>

                    <!-- 2. banned_usernames -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-field__label" for="laca-lp-banned-users"><?php echo esc_html__('Danh sách username cấm dùng', 'laca'); ?></label>
                        <textarea id="laca-lp-banned-users" class="laca-textarea laca-mono" rows="3" placeholder="admin&#10;administrator&#10;root"><?php echo esc_textarea(implode("\n", (array) ($policy['banned_usernames'] ?? []))); ?></textarea>
                        <p class="laca-field__help"><?php echo esc_html__('Mỗi dòng một username. Đăng nhập bằng tên trong danh sách này sẽ bị khoá IP ngay lập tức. Người dùng mới cũng không thể đăng ký bằng các tên này.', 'laca'); ?></p>
                    </div>

                    <!-- 3. require_2fa_roles + grace days -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-field__label"><?php echo esc_html__('Bắt buộc xác thực 2 bước (2FA) theo vai trò', 'laca'); ?></label>
                        <?php if (!$is2faMasterEnabled): ?>
                            <div class="laca-alert laca-alert--warning" style="margin-bottom: 12px;">
                                <span><?php echo esc_html__('Tính năng Xác thực 2 bước tổng thể đang tắt (xem tab Xác thực 2 bước). Bật 2FA để chính sách này có hiệu lực.', 'laca'); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="laca-check-group">
                            <?php foreach ($allRoles as $roleSlug => $roleName): ?>
                                <label class="laca-check-group__item">
                                    <input type="checkbox" class="laca-checkbox js-laca-lp-role" value="<?php echo esc_attr($roleSlug); ?>" <?php checked(in_array($roleSlug, (array) ($policy['require_2fa_roles'] ?? []), true)); ?>>
                                    <span><?php echo esc_html(translate_user_role($roleName)); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="laca-field laca-field--narrow">
                            <label class="laca-field__label" for="laca-lp-grace-days"><?php echo esc_html__('Thời gian ân hạn (ngày)', 'laca'); ?></label>
                            <input type="number" id="laca-lp-grace-days" class="laca-input" min="0" max="30" value="<?php echo esc_attr((string) ($policy['require_2fa_grace_days'] ?? 3)); ?>">
                            <p class="laca-field__help"><?php echo esc_html__('Số ngày cho phép tài khoản đăng nhập sau khi tạo trước khi bị ép buộc phải cấu hình 2FA (0 = bắt buộc ngay).', 'laca'); ?></p>
                        </div>
                    </div>

                    <!-- 4. lostpw_max_per_hour -->
                    <div class="laca-field laca-field--spaced-top laca-field--narrow">
                        <label class="laca-field__label" for="laca-lp-lostpw"><?php echo esc_html__('Giới hạn yêu cầu quên mật khẩu (lần/giờ/IP)', 'laca'); ?></label>
                        <input type="number" id="laca-lp-lostpw" class="laca-input" min="0" max="100" value="<?php echo esc_attr((string) ($policy['lostpw_max_per_hour'] ?? 5)); ?>">
                        <p class="laca-field__help"><?php echo esc_html__('Số lần tối đa một địa chỉ IP có thể gửi yêu cầu đặt lại mật khẩu trong 1 giờ. Nhập 0 để tắt giới hạn.', 'laca'); ?></p>
                    </div>

                    <!-- 5. disable_app_passwords -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-switch">
                            <input type="checkbox" id="laca-lp-disable-app-pw" class="laca-switch__input" <?php checked(!empty($policy['disable_app_passwords'])); ?>>
                            <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                            <span class="laca-switch__label"><?php echo esc_html__('Tắt Mật khẩu ứng dụng (Application Passwords)', 'laca'); ?></span>
                        </label>
                        <p class="laca-field__help"><?php echo esc_html__('Vô hiệu hoá tính năng Mật khẩu ứng dụng của WordPress để đóng hoàn toàn bề mặt tấn công xác thực qua REST API.', 'laca'); ?></p>
                    </div>

                    <!-- 6. breached_check -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-field__label"><?php echo esc_html__('Kiểm tra mật khẩu bị lộ (Have I Been Pwned)', 'laca'); ?></label>
                        <?php $bCheck = $policy['breached_check'] ?? 'admins'; ?>
                        <div class="laca-segmented" id="laca-lp-breached" role="tablist">
                            <button type="button" class="laca-segmented__item <?php echo $bCheck === 'off' ? 'is-active' : ''; ?>" data-value="off">
                                <?php echo esc_html__('Tắt', 'laca'); ?>
                            </button>
                            <button type="button" class="laca-segmented__item <?php echo $bCheck === 'admins' ? 'is-active' : ''; ?>" data-value="admins">
                                <?php echo esc_html__('Chỉ Quản trị viên', 'laca'); ?>
                            </button>
                            <button type="button" class="laca-segmented__item <?php echo $bCheck === 'all' ? 'is-active' : ''; ?>" data-value="all">
                                <?php echo esc_html__('Mọi người dùng', 'laca'); ?>
                            </button>
                        </div>
                        <input type="hidden" id="laca-lp-breached-val" value="<?php echo esc_attr($bCheck); ?>">
                        <p class="laca-field__help"><?php echo esc_html__('Sử dụng mô hình k-Anonymity (chỉ gửi 5 ký tự đầu của hash SHA-1) để kiểm tra mật khẩu trong cơ sở dữ liệu Have I Been Pwned mà không làm lộ mật khẩu thực tế.', 'laca'); ?></p>
                    </div>

                    <!-- 7. new_ip_alert -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-switch">
                            <input type="checkbox" id="laca-lp-new-ip-alert" class="laca-switch__input" <?php checked(!empty($policy['new_ip_alert'])); ?>>
                            <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                            <span class="laca-switch__label"><?php echo esc_html__('Cảnh báo khi admin đăng nhập từ IP mới', 'laca'); ?></span>
                        </label>
                        <p class="laca-field__help"><?php echo esc_html__('Gửi thông báo Discord khi tài khoản quản trị đăng nhập thành công từ một địa chỉ IP chưa từng xuất hiện trong 10 phiên gần nhất.', 'laca'); ?></p>
                    </div>

                    <!-- 8. Emergency Bypass Note -->
                    <div class="laca-alert laca-alert--info laca-mt-3">
                        <div class="laca-alert__content">
                            <p class="laca-alert__title"><?php echo esc_html__('Lối thoát khẩn cấp (Emergency Bypass):', 'laca'); ?></p>
                            <p class="laca-field__help" style="margin-top:4px;">
                                <?php echo wp_kses_post(__('Nếu bạn hoặc quản trị viên bị khoá ngoài trang quản trị do cấu hình sai hoặc quên IP, hãy thêm dòng sau vào file <code>wp-config.php</code>:<br><code>define(\'LACA_SECURITY_BYPASS\', true);</code><br>Khi hằng số này được bật, toàn bộ cơ chế khoá IP, kiểm tra username cấm và ép buộc chuyển hướng sẽ được vô hiệu hoá tạm thời.', 'laca')); ?>
                            </p>
                        </div>
                    </div>

                    <div id="laca-login-policy-alert" class="laca-alert-slot"></div>
                </div>
                <div class="laca-card__footer">
                    <button type="button" id="btn-save-login-policy" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lưu chính sách', 'laca'); ?></button>
                </div>
            </div>

            <!-- 5. Card: Thông báo đăng nhập -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Thông báo đăng nhập', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php echo esc_html__('Gửi cảnh báo qua Discord (kênh Security) khi phát hiện khoá IP hoặc tấn công dò mật khẩu.', 'laca'); ?></p>
                </div>
                <div class="laca-card__body">
                    <?php
                    $alertClass = 'laca-alert--warning';
                    $alertText  = esc_html__('Chưa cấu hình kênh Security trên Discord Hub', 'laca');
                    $state      = $discordStatus['state'] ?? 'empty';

                    if ($discordCanSend || $state === 'ok') {
                        $alertClass = 'laca-alert--success';
                        $alertText  = esc_html__('Đã kết nối kênh Security', 'laca');
                    } elseif ($state === 'broken') {
                        $alertClass = 'laca-alert--danger';
                        $alertText  = esc_html__('Webhook kênh Security bị lỗi, cần nhập lại', 'laca');
                    }
                    ?>
                    <div class="laca-alert <?php echo esc_attr($alertClass); ?> laca-discord-status-alert">
                        <span><?php echo $alertText; ?></span>
                        <a href="<?php echo esc_url($discordUrl); ?>" class="laca-link-inline"><?php echo esc_html__('Cấu hình Discord Hub →', 'laca'); ?></a>
                    </div>
                    <div id="laca-login-notify-alert" class="laca-alert-slot"></div>
                </div>
                <div class="laca-card__footer">
                    <button type="button" id="btn-test-login-notify" class="laca-btn laca-btn--outline laca-btn--sm">
                        <?php echo esc_html__('Gửi thử', 'laca'); ?>
                    </button>
                </div>
            </div>
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
        <button id="btn-save-2fa" class="button button-primary">Lưu cài đặt</button>
        <span id="2fa-save-msg" class="laca-ml-2"></span>

        <hr style="margin:24px 0;">
        <h3>Trạng thái 2FA người dùng</h3>
        <?php
        $users = get_users(['role__in' => ['administrator', 'editor'], 'number' => 50]);
        if ($users): ?>
        <table class="wp-list-table widefat fixed striped laca-security-table-sm">
            <thead><tr><th>User</th><th>Vai trò</th><th>2FA</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
                $enabled  = get_user_meta($u->ID, 'laca_2fa_enabled',  true);
                $verified = get_user_meta($u->ID, 'laca_2fa_verified', true);
                $status   = ($enabled && $verified) ? '<span class="laca-status-ok">✓ Đã bật</span>' : '<span class="laca-status-muted">— Chưa bật</span>';
            ?>
                <tr>
                    <td><?php echo esc_html($u->user_login); ?> <small class="laca-user-email"><?php echo esc_html($u->user_email); ?></small></td>
                    <td><?php echo esc_html(implode(', ', $u->roles)); ?></td>
                    <td><?php echo $status; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }

    // ── Tab: Hardening ────────────────────────────────────────────────────────

    private function renderHardeningTab(): void
    {
        $disableRestApi   = get_option('_disable_rest_api') === 'yes';
        $disableXmlRpc    = get_option('_disable_xml_rpc') === 'yes';
        $disableWpEmbed   = get_option('_disable_wp_embed') === 'yes';
        $disableXPingback = get_option('_disable_x_pingback') === 'yes';
        $removeBloat      = get_option('_enable_remove_wordpress_bloat') === 'yes';

        $hdrConfig        = SecurityHeaders::getConfig();
        $hstsMaxAge       = (int) ($hdrConfig['hsts_max_age'] ?? 31536000);
        ?>
        <div class="laca-settings-stack">
            <!-- Card 1: Thiết lập bảo vệ hệ thống (đang có) -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Thiết lập bảo vệ hệ thống', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php echo esc_html__('Vô hiệu hóa các endpoint và tính năng mặc định dễ bị khai thác tấn công nếu website không cần dùng đến.', 'laca'); ?></p>
                </div>
                <div class="laca-card__body">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('REST API', 'laca'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="laca-opt-disable-rest" <?php checked($disableRestApi); ?>>
                                    <strong><?php echo esc_html__('Tắt REST API đối với khách vãng lai', 'laca'); ?></strong>
                                </label>
                                <p class="description"><?php echo esc_html__('Chỉ người dùng đã đăng nhập mới có thể truy vấn REST API. Ngăn chặn quét thông tin người dùng và bài viết ngoài luồng.', 'laca'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('XML-RPC', 'laca'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="laca-opt-disable-xmlrpc" <?php checked($disableXmlRpc); ?>>
                                    <strong><?php echo esc_html__('Tắt hoàn toàn XML-RPC (xmlrpc.php)', 'laca'); ?></strong>
                                </label>
                                <p class="description"><?php echo esc_html__('XML-RPC thường bị bot lạm dụng để dò mật khẩu (brute-force) hoặc tấn công DDoS. Khuyến cáo tắt nếu không dùng app WordPress trên điện thoại.', 'laca'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('WP-Embed', 'laca'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="laca-opt-disable-wpembed" <?php checked($disableWpEmbed); ?>>
                                    <strong><?php echo esc_html__('Tắt WP-Embed', 'laca'); ?></strong>
                                </label>
                                <p class="description"><?php echo esc_html__('Tắt script wp-embed.js và các liên kết oEmbed khám phá nếu website không cần nhúng nội dung sang các blog khác.', 'laca'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('X-Pingback', 'laca'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="laca-opt-disable-pingback" <?php checked($disableXPingback); ?>>
                                    <strong><?php echo esc_html__('Tắt Header X-Pingback', 'laca'); ?></strong>
                                </label>
                                <p class="description"><?php echo esc_html__('Gỡ bỏ header thông báo pingback giúp tránh spam trackback và các cuộc tấn công khuếch đại pingback.', 'laca'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('WordPress Bloat', 'laca'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="laca-opt-remove-bloat" <?php checked($removeBloat); ?>>
                                    <strong><?php echo esc_html__('Loại bỏ thành phần thừa (Bloat) của WordPress', 'laca'); ?></strong>
                                </label>
                                <p class="description"><?php echo esc_html__('Gỡ bỏ emoji scripts, RSD link, wlwmanifest và thẻ generator version WordPress trong HTML header để tăng tính bảo mật và giảm tải.', 'laca'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <div id="laca-hardening-alert" class="laca-alert-slot"></div>
                </div>
                <div class="laca-card__footer">
                    <span id="hardening-save-msg" class="laca-ml-2"></span>
                    <button id="btn-save-hardening" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lưu thiết lập bảo vệ', 'laca'); ?></button>
                </div>
            </div>

            <!-- Card 2: HTTP Security Headers -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('HTTP Security Headers', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php echo esc_html__('Bật hoặc tắt từng HTTP header bảo mật gửi kèm phản hồi từ website.', 'laca'); ?></p>
                </div>
                <div class="laca-card__body">
                    <div class="laca-header-list">
                        <!-- X-Frame-Options -->
                        <div class="laca-header-item">
                            <div class="laca-header-item__main">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-hdr-x-frame" class="laca-switch__input" <?php checked(!empty($hdrConfig['x_frame_options'])); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><strong>X-Frame-Options</strong></span>
                                </label>
                                <p class="laca-field__help"><?php echo esc_html__('Chặn nhúng trang vào iframe trên website khác (SAMEORIGIN) để chống clickjacking.', 'laca'); ?></p>
                            </div>
                        </div>

                        <!-- X-Content-Type-Options -->
                        <div class="laca-header-item">
                            <div class="laca-header-item__main">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-hdr-x-content-type" class="laca-switch__input" <?php checked(!empty($hdrConfig['x_content_type'])); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><strong>X-Content-Type-Options</strong></span>
                                </label>
                                <p class="laca-field__help"><?php echo esc_html__('Ngăn trình duyệt tự suy đoán kiểu MIME của tài nguyên (nosniff).', 'laca'); ?></p>
                            </div>
                        </div>

                        <!-- Referrer-Policy -->
                        <div class="laca-header-item">
                            <div class="laca-header-item__main">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-hdr-referrer" class="laca-switch__input" <?php checked(!empty($hdrConfig['referrer_policy'])); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><strong>Referrer-Policy</strong></span>
                                </label>
                                <p class="laca-field__help"><?php echo esc_html__('Chỉ gửi thông tin nguồn (referrer) đầy đủ khi điều hướng cùng tên miền (strict-origin-when-cross-origin).', 'laca'); ?></p>
                            </div>
                        </div>

                        <!-- Permissions-Policy -->
                        <div class="laca-header-item">
                            <div class="laca-header-item__main">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-hdr-permissions" class="laca-switch__input" <?php checked(!empty($hdrConfig['permissions_policy'])); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><strong>Permissions-Policy</strong></span>
                                </label>
                                <p class="laca-field__help"><?php echo esc_html__('Tắt quyền truy cập vị trí, micro và camera mặc định trên trình duyệt.', 'laca'); ?></p>
                            </div>
                        </div>

                        <!-- HSTS -->
                        <div class="laca-header-item">
                            <div class="laca-header-item__main">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-hdr-hsts" class="laca-switch__input" <?php checked(!empty($hdrConfig['hsts'])); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><strong>Strict-Transport-Security (HSTS)</strong></span>
                                </label>
                                <p class="laca-field__help"><?php echo esc_html__('Bắt buộc trình duyệt kết nối bằng HTTPS. Chỉ gửi khi site chạy HTTPS.', 'laca'); ?></p>
                                <div class="laca-header-item__sub">
                                    <label class="laca-inline-label" for="laca-hdr-hsts-max-age">
                                        <span><?php echo esc_html__('Thời hạn:', 'laca'); ?></span>
                                        <select id="laca-hdr-hsts-max-age" class="laca-select laca-select--xs laca-select-inline">
                                            <option value="15552000" <?php selected($hstsMaxAge, 15552000); ?>><?php echo esc_html__('6 tháng', 'laca'); ?></option>
                                            <option value="31536000" <?php selected($hstsMaxAge, 31536000); ?>><?php echo esc_html__('1 năm', 'laca'); ?></option>
                                            <option value="63072000" <?php selected($hstsMaxAge, 63072000); ?>><?php echo esc_html__('2 năm', 'laca'); ?></option>
                                        </select>
                                    </label>
                                    <label class="laca-switch">
                                        <input type="checkbox" id="laca-hdr-hsts-sub" class="laca-switch__input" <?php checked(!empty($hdrConfig['hsts_include_sub'])); ?>>
                                        <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                        <span class="laca-switch__label"><?php echo esc_html__('Áp dụng cho subdomain', 'laca'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- CSP -->
                        <div class="laca-header-item">
                            <div class="laca-header-item__main">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-hdr-csp" class="laca-switch__input" <?php checked(!empty($hdrConfig['csp'])); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><strong>Content-Security-Policy (CSP)</strong></span>
                                </label>
                                <p class="laca-field__help"><?php echo esc_html__('Kiểm soát các nguồn tải script, style, ảnh và iframe hợp lệ trên frontend.', 'laca'); ?></p>
                                <div class="laca-alert laca-alert--warning laca-mt-2">
                                    <div class="laca-alert__content">
                                        <p class="laca-alert__description"><?php echo wp_kses_post(__('Nếu video/iframe/script bên ngoài bị chặn, thêm domain qua filter <code>laca_csp_frame_src</code> hoặc tắt CSP.', 'laca')); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ẩn X-Powered-By -->
                        <div class="laca-header-item">
                            <div class="laca-header-item__main">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-hdr-remove-powered" class="laca-switch__input" <?php checked(!empty($hdrConfig['remove_powered_by'])); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><strong><?php echo esc_html__('Ẩn X-Powered-By', 'laca'); ?></strong></span>
                                </label>
                                <p class="laca-field__help"><?php echo esc_html__('Gỡ bỏ header X-Powered-By để tránh lộ phiên bản PHP của máy chủ.', 'laca'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div id="laca-sec-headers-alert" class="laca-alert-slot"></div>
                    <div id="laca-sec-headers-check-result" class="laca-mt-3" style="display:none;"></div>
                </div>
                <div class="laca-card__footer">
                    <button type="button" id="btn-check-sec-headers" class="laca-btn laca-btn--outline laca-btn--sm"><?php echo esc_html__('Kiểm tra header', 'laca'); ?></button>
                    <button type="button" id="btn-save-sec-headers" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lưu', 'laca'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    // ── Tab: Super User ──────────────────────────────────────────────────────

    private function renderSuperUserTab(): void
    {
        $users        = get_users(['orderby' => 'registered', 'order' => 'ASC']);
        $superLogins  = \App\Settings\AdminSettings::getSuperUserLogins();
        ?>
        <h2>Quản lý Super User</h2>
        <p>Super User được ẩn khỏi danh sách Người dùng đối với người khác, và không bị áp các giới hạn quản trị (chế độ bảo trì, ẩn menu, khoá cập nhật...) mà site áp dụng cho user thường.
           Tài khoản mặc định <code><?php echo esc_html(SuperUserGuard::PROTECTED_LOGIN); ?></code> luôn là Super User —
           không Super User nào khác có thể xoá hoặc đổi quyền (role) của tài khoản này.</p>
        <table class="wp-list-table widefat fixed striped laca-security-table-md">
            <thead><tr><th>User</th><th>Email</th><th>Vai trò</th>
                <th title="Kiểm tra trực tiếp user_can($user, 'manage_options') — khác với cột Vai trò (chỉ đọc TÊN role, không chắc role đó còn thực sự giữ quyền manage_options hay đã bị tước bởi nguyên nhân khác).">manage_options?</th>
                <th>Super User?</th><th>Hành động</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
                $isProtected    = SuperUserGuard::isProtected($u->ID);
                $isSuper        = in_array($u->user_login, $superLogins, true);
                $hasManageOptions = user_can($u, 'manage_options');
                // Dump thô wp_capabilities — "Vai trò" chỉ đọc TÊN role lấy
                // từ đây, nhưng không kiểm tra value có thực sự "true" hay
                // record có bị rỗng/lỗi/lẫn role khác hay không. Hiện trực
                // tiếp để đối chiếu khi cột manage_options? báo "Không"
                // nhưng Vai trò vẫn ghi "administrator".
                $rawCaps = get_user_meta($u->ID, $GLOBALS['wpdb']->prefix . 'capabilities', true);
            ?>
                <tr>
                    <td><strong><?php echo esc_html($u->user_login); ?></strong></td>
                    <td><?php echo esc_html($u->user_email); ?></td>
                    <td>
                        <span class="laca-user-roles"><?php echo esc_html(implode(', ', $u->roles)); ?></span>
                        <br><code class="laca-security-code" title="Giá trị thô usermeta wp_capabilities — đối chiếu khi cột manage_options? không khớp với Vai trò hiển thị."><?php echo esc_html(wp_json_encode($rawCaps)); ?></code>
                    </td>
                    <td>
                        <?php if ($hasManageOptions): ?>
                            <span class="laca-status-ok">✓ Có</span>
                        <?php else: ?>
                            <span class="laca-status-danger">✕ Không</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isSuper): ?>
                            <span class="laca-status-super">Super User</span>
                        <?php else: ?>
                            <span class="laca-status-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isProtected): ?>
                            <span class="laca-status-muted">Mặc định — không thể đổi</span>
                        <?php elseif ($isSuper): ?>
                            <button class="button btn-toggle-super" data-user-id="<?php echo esc_attr($u->ID); ?>" data-do-action="demote">Bỏ Super User</button>
                        <?php else: ?>
                            <button class="button button-primary btn-toggle-super" data-user-id="<?php echo esc_attr($u->ID); ?>" data-do-action="promote">Đặt Super User</button>
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
            echo '<h4 class="laca-section-label" style="margin-top:12px; margin-bottom:6px; /* động */">' . esc_html($label) . ' (' . count($r[$key]) . ')</h4>';
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
        $stats  = [
            'core_matched'     => 0,
            'plugins_matched'  => [],
            'core_unavailable' => false,
        ];
        set_transient($scanId . '_files',    $files,  600);
        set_transient($scanId . '_findings', [],      600);
        set_transient($scanId . '_stats',    $stats,  600);
        wp_send_json_success(['scan_id' => $scanId, 'total' => count($files)]);
    }

    public function ajaxMalwareChunk(): void
    {
        $this->checkNonce();
        $scanId   = sanitize_text_field($_POST['scan_id'] ?? '');
        $offset   = (int) ($_POST['offset'] ?? 0);
        $files    = get_transient($scanId . '_files');
        $findings = get_transient($scanId . '_findings');
        $stats    = get_transient($scanId . '_stats');
        if ($files === false) wp_send_json_error('Phiên đã hết hạn. Vui lòng thử lại.');

        if (!is_array($stats)) {
            $stats = [];
        }

        $size     = 100;
        $chunkRes = MalwareScanner::scanChunkWithStats($files, $offset, $size, $stats);
        $findings = array_merge($findings, $chunkRes['findings']);
        set_transient($scanId . '_findings', $findings, 600);
        set_transient($scanId . '_stats',    $chunkRes['stats'], 600);

        $next  = $offset + $size;
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
        $stats    = get_transient($scanId . '_stats');
        if ($findings === false) wp_send_json_error('Phiên đã hết hạn.');

        $scanned = is_array($files) ? count($files) : 0;
        if (!is_array($stats)) {
            $stats = [];
        }

        wp_send_json_success([
            'html'     => MalwareScanner::renderResults($findings, $scanned, $stats),
            'total'    => count($findings),
            'scan_id'  => $scanId,
        ]);
    }

    public function ajaxMalwareWhitelistAdd(): void
    {
        $this->checkNonce();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện thao tác này.', 'laca'));
        }

        $file = sanitize_text_field($_POST['file'] ?? '');
        if (empty($file)) {
            wp_send_json_error(__('Đường dẫn file không hợp lệ.', 'laca'));
        }

        $base = wp_normalize_path(ABSPATH);
        $fullPath = $base . ltrim($file, '/');
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            wp_send_json_error(__('File không tồn tại trên hệ thống.', 'laca'));
        }

        $hash = @hash_file('sha256', $fullPath);
        if (!$hash) {
            wp_send_json_error(__('Không thể tạo mã băm cho file.', 'laca'));
        }

        $whitelist = get_option('laca_malware_whitelist', []);
        if (!is_array($whitelist)) {
            $whitelist = [];
        }

        $whitelist[ltrim($file, '/')] = $hash;
        update_option('laca_malware_whitelist', $whitelist);

        wp_send_json_success([
            'message' => __('Đã thêm vào danh sách an toàn.', 'laca'),
            'file'    => $file,
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
            wp_send_json_error(__('Slug không được để trống khi bật tính năng.', 'laca'));
        }
        if ($enabled && $this->isForbiddenLoginSlug($slug)) {
            wp_send_json_error(__('Slug trùng với query var hệ thống của WordPress, vui lòng chọn slug khác.', 'laca'));
        }
        update_option('laca_login_slug',          $slug);
        update_option('laca_enable_custom_login', $enabled);
        wp_send_json_success(__('Cài đặt đã lưu. Tải lại trang để áp dụng.', 'laca'));
    }

    /** Chặn các slug trùng public/private query var — nếu không site sẽ tự khoá truy cập admin. */
    private function isForbiddenLoginSlug(string $slug): bool
    {
        if (str_contains($slug, 'wp-login') || str_contains($slug, 'wp-admin')) {
            return true;
        }

        $wp = new \WP();

        return in_array($slug, array_merge($wp->public_query_vars, $wp->private_query_vars), true);
    }

    public function ajaxSaveLoginPolicy(): void
    {
        $this->checkNonce();

        $lockInvalid = !empty($_POST['lock_invalid_usernames']) && $_POST['lock_invalid_usernames'] === 'yes';

        $rawBanned = isset($_POST['banned_usernames']) ? (string) wp_unslash($_POST['banned_usernames']) : '';
        $bannedLines = preg_split('/\r\n|\r|\n/', $rawBanned) ?: [];
        $banned = [];
        foreach ($bannedLines as $l) {
            $u = sanitize_user(trim($l));
            if ($u !== '') {
                $banned[] = $u;
            }
        }
        $banned = array_values(array_unique($banned));

        $rawRoles = isset($_POST['require_2fa_roles']) && is_array($_POST['require_2fa_roles']) ? $_POST['require_2fa_roles'] : [];
        $roles = [];
        $allRoles = function_exists('wp_roles') ? array_keys(wp_roles()->get_names()) : [];
        foreach ($rawRoles as $r) {
            $rSan = sanitize_key($r);
            if (in_array($rSan, $allRoles, true)) {
                $roles[] = $rSan;
            }
        }

        $graceDays = max(0, min(30, (int) ($_POST['require_2fa_grace_days'] ?? 3)));
        $lostpwMax = max(0, min(100, (int) ($_POST['lostpw_max_per_hour'] ?? 5)));
        $disableAppPw = !empty($_POST['disable_app_passwords']) && $_POST['disable_app_passwords'] === 'yes';

        $bCheck = sanitize_key(wp_unslash($_POST['breached_check'] ?? 'admins'));
        if (!in_array($bCheck, ['off', 'admins', 'all'], true)) {
            $bCheck = 'admins';
        }

        $newIpAlert = !empty($_POST['new_ip_alert']) && $_POST['new_ip_alert'] === 'yes';

        $newConfig = [
            'lock_invalid_usernames' => $lockInvalid,
            'banned_usernames'       => $banned,
            'require_2fa_roles'      => $roles,
            'require_2fa_grace_days' => $graceDays,
            'lostpw_max_per_hour'    => $lostpwMax,
            'disable_app_passwords'  => $disableAppPw,
            'breached_check'         => $bCheck,
            'new_ip_alert'           => $newIpAlert,
        ];

        update_option(LoginPolicy::OPTION_KEY, $newConfig);

        wp_send_json_success([
            'message'          => __('Đã lưu chính sách đăng nhập thành công.', 'laca'),
            'banned_usernames' => $banned,
        ]);
    }

    // 2FA Master Setting

    public function ajaxSave2faSettings(): void
    {
        $this->checkNonce();
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        update_option('laca_2fa_master_enabled', $enabled);
        wp_send_json_success($enabled ? '2FA đã bật toàn site.' : '2FA đã tắt.');
    }

    // Security Hardening Settings

    public function ajaxSaveHardeningSettings(): void
    {
        $this->checkNonce();
        $disableRestApi   = !empty($_POST['disable_rest']) ? 'yes' : 'no';
        $disableXmlRpc    = !empty($_POST['disable_xmlrpc']) ? 'yes' : 'no';
        $disableWpEmbed   = !empty($_POST['disable_wpembed']) ? 'yes' : 'no';
        $disableXPingback = !empty($_POST['disable_pingback']) ? 'yes' : 'no';
        $removeBloat      = !empty($_POST['remove_bloat']) ? 'yes' : 'no';

        update_option('_disable_rest_api', $disableRestApi);
        update_option('_disable_xml_rpc', $disableXmlRpc);
        update_option('_disable_wp_embed', $disableWpEmbed);
        update_option('_disable_x_pingback', $disableXPingback);
        update_option('_enable_remove_wordpress_bloat', $removeBloat);

        wp_send_json_success('Đã lưu thiết lập bảo vệ hệ thống thành công.');
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

    // Login Guard & Honeypot Settings

    public function ajaxSaveLoginGuardSettings(): void
    {
        $this->checkNonce();

        $scope = sanitize_key(wp_unslash($_POST['save_scope'] ?? 'all'));

        if ($scope === 'honeypot') {
            $hp = (sanitize_key(wp_unslash($_POST['honeypot'] ?? 'no')) === 'yes') ? 'yes' : 'no';
            update_option('laca_login_honeypot', $hp);
            wp_send_json_success([
                'message' => __('Đã lưu cài đặt chống bot.', 'laca'),
            ]);
        }

        $maxFails   = max(3, min(20, (int) ($_POST['max_fails'] ?? 5)));
        $windowMin  = max(1, min(1440, (int) ($_POST['window_minutes'] ?? 15)));
        $lockoutMin = max(1, min(1440, (int) ($_POST['lockout_minutes'] ?? 30)));
        $strikes    = max(1, min(20, (int) ($_POST['strikes_for_long'] ?? 3)));
        $longHours  = max(1, min(720, (int) ($_POST['long_lockout_hours'] ?? 24)));

        $rawWhitelist = isset($_POST['whitelist_ips']) ? (string) wp_unslash($_POST['whitelist_ips']) : '';
        $lines        = preg_split('/\r\n|\r|\n/', $rawWhitelist) ?: [];
        $validIps     = [];
        $rejectedIps  = [];

        foreach ($lines as $line) {
            $trimmed = trim(sanitize_text_field($line));
            if ($trimmed === '') {
                continue;
            }
            if (filter_var($trimmed, FILTER_VALIDATE_IP)) {
                $validIps[] = $trimmed;
            } else {
                $rejectedIps[] = $trimmed;
            }
        }

        $validIps = array_values(array_unique($validIps));

        update_option(LoginGuard::SETTINGS_OPTION, [
            'max_fails'        => $maxFails,
            'window'           => $windowMin * MINUTE_IN_SECONDS,
            'lockout'          => $lockoutMin * MINUTE_IN_SECONDS,
            'strikes_for_long' => $strikes,
            'long_lockout'     => $longHours * HOUR_IN_SECONDS,
            'whitelist_ips'    => $validIps,
        ]);

        if (isset($_POST['honeypot'])) {
            $hp = (sanitize_key(wp_unslash($_POST['honeypot'])) === 'yes') ? 'yes' : 'no';
            update_option('laca_login_honeypot', $hp);
        }

        if ($rejectedIps !== []) {
            $message = sprintf(
                /* translators: %s: danh sách dòng IP không hợp lệ bị bỏ qua */
                __('Đã lưu cài đặt. Đã bỏ qua các dòng IP không hợp lệ: %s', 'laca'),
                implode(', ', $rejectedIps)
            );
        } else {
            $message = __('Đã lưu cài đặt giới hạn đăng nhập sai.', 'laca');
        }

        wp_send_json_success([
            'message'      => $message,
            'valid_ips'    => $validIps,
            'rejected_ips' => $rejectedIps,
        ]);
    }

    // Login Notification Test (Discord Hub — Security channel)

    public function ajaxLoginNotifyTest(): void
    {
        $this->checkNonce();

        if (!class_exists(\App\Features\DiscordHub\Settings::class)
            || !class_exists(\App\Features\DiscordHub\Notifier::class)) {
            wp_send_json_error(__('Discord Hub không khả dụng.', 'laca'));
        }

        $channel = \App\Features\DiscordHub\Settings::CHANNEL_SECURITY;
        $status  = \App\Features\DiscordHub\Settings::channelStatus($channel);

        if (!\App\Features\DiscordHub\Settings::canSend($channel) || ($status['state'] ?? '') !== 'ok') {
            wp_send_json_error(__('Kênh Security trên Discord Hub chưa được cấu hình hoặc chưa cho phép gửi ở môi trường hiện tại.', 'laca'));
        }

        \App\Features\DiscordHub\DiscordWebhookSender::resetChannel($channel);
        $start = time();

        $embed = \App\Features\DiscordHub\Embed::make(
            __('[Thử] 🔒 Thông báo bảo mật đăng nhập', 'laca'),
            \App\Features\DiscordHub\Embed::COLOR_INFO
        )
            ->description(__('[Thử] Đây là tin nhắn kiểm tra cảnh báo đăng nhập từ trang Bảo mật.', 'laca'), true)
            ->field(__('Người gửi thử', 'laca'), \App\Features\DiscordHub\Context::actor())
            ->codeField('IP', \App\Features\DiscordHub\Context::clientIp())
            ->field(__('Kênh', 'laca'), 'Security');

        \App\Features\DiscordHub\Notifier::send($channel, 'login.test', $embed);
        \App\Features\DiscordHub\Dispatcher::flush();

        $result = \App\Features\DiscordHub\DiscordWebhookSender::lastResult($channel);
        if (($result['error'] ?? '') !== '' && ($result['error_at'] ?? 0) >= $start) {
            /* translators: %s: chi tiết lỗi từ Discord */
            wp_send_json_error(sprintf(__('Gửi thử thất bại: %s', 'laca'), $result['error']));
        }

        wp_send_json_success([
            'message' => __('Đã gửi tin thử [Thử] tới kênh Security trên Discord.', 'laca'),
        ]);
    }

    // Security Headers Save & Check

    public function ajaxSaveSecurityHeaders(): void
    {
        $this->checkNonce();

        $allowedAges = [15552000, 31536000, 63072000];
        $maxAge      = (int) ($_POST['hsts_max_age'] ?? 31536000);
        if (!in_array($maxAge, $allowedAges, true)) {
            $maxAge = 31536000;
        }

        $config = [
            'x_frame_options'    => !empty($_POST['x_frame_options']),
            'x_content_type'     => !empty($_POST['x_content_type']),
            'referrer_policy'    => !empty($_POST['referrer_policy']),
            'permissions_policy' => !empty($_POST['permissions_policy']),
            'hsts'               => !empty($_POST['hsts']),
            'hsts_max_age'       => $maxAge,
            'hsts_include_sub'   => !empty($_POST['hsts_include_sub']),
            'csp'                => !empty($_POST['csp']),
            'remove_powered_by'  => !empty($_POST['remove_powered_by']),
        ];

        SecurityHeaders::saveConfig($config);

        wp_send_json_success([
            'message' => __('Đã lưu cấu hình HTTP Security Headers.', 'laca'),
        ]);
    }

    public function ajaxCheckSecurityHeaders(): void
    {
        $this->checkNonce();

        $response = wp_remote_head(home_url('/'), [
            'timeout'   => 10,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $headerNames = [
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
            'Strict-Transport-Security',
            'Content-Security-Policy',
            'X-Powered-By',
        ];

        $rows = [];
        foreach ($headerNames as $name) {
            $val = wp_remote_retrieve_header($response, strtolower($name));
            if (is_array($val)) {
                $val = implode(', ', $val);
            }
            $val     = trim((string) $val);
            $present = ($val !== '');
            $short   = mb_strlen($val) > 120 ? mb_substr($val, 0, 117) . '...' : $val;

            $rows[] = [
                'header'  => $name,
                'value'   => $val,
                'short'   => $short,
                'present' => $present,
            ];
        }

        wp_send_json_success([
            'rows' => $rows,
        ]);
    }

    // ── Inline JavaScript ─────────────────────────────────────────────────────

    private function renderInlineScripts(): void
    {
        ?>
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
                    if (!res.success) { $('#audit-result').html('<p class="laca-status-danger">'+res.data+'</p>'); return; }
                    var d = res.data;
                    var color = d.score >= 80 ? 'var(--laca-success)' : d.score >= 50 ? 'var(--laca-warning)' : 'var(--laca-danger)';
                    var verdict = d.score >= 80 ? 'Tốt' : d.score >= 50 ? 'Trung bình' : 'Yếu';
                    var html = '<div class="laca-security-flex" style="margin-bottom:20px;">';
                    html += '<div class="laca-audit-score-circle" style="color:'+color+';border-color:'+color+';">'+d.score+'</div>';
                    html += '<div><strong style="font-size:18px;">'+verdict+'</strong><br>';
                    html += '<span style="font-size:13px;color:var(--laca-fg-muted);">Đạt: '+d.pass+' · Lỗi: '+d.fail+' · Cảnh báo: '+d.warn+'</span></div></div>';

                    $.each(d.groups, function(cat, checks){
                        html += '<h3 style="margin:16px 0 8px;border-bottom:1px solid var(--laca-border);padding-bottom:6px;">'+cat+'</h3>';
                        html += '<table class="wp-list-table widefat striped" style="margin-bottom:8px;"><tbody>';
                        $.each(checks, function(_, c){
                            var icon = c.status==='pass'?'✓':c.status==='fail'?'✗':c.status==='warn'?'⚠':'ℹ';
                            var col  = c.status==='pass'?'var(--laca-success)':c.status==='fail'?'var(--laca-danger)':c.status==='warn'?'var(--laca-warning)':'var(--laca-accent-fg)';
                            html += '<tr><td style="width:24px;font-size:16px;color:'+col+';">'+icon+'</td>';
                            // Mục chưa đạt: dòng chính là kết quả thật (desc), tiêu chí lùi xuống dòng phụ —
                            // tránh đọc "HTTPS đang hoạt động" cạnh dấu ✗ rồi hiểu ngược.
                            var ok = c.status === 'pass' || c.status === 'info';
                            var main = ok ? c.title : c.desc;
                            var sub  = ok ? c.desc : 'Tiêu chí: ' + c.title + (c.fix ? ' — Cách sửa: ' + c.fix : '');
                            html += '<td><strong>'+main+'</strong><br><span style="font-size:12px;color:var(--laca-fg-muted);">'+sub+'</span></td>';
                            html += '<td style="width:60px;text-align:right;font-size:12px;color:var(--laca-fg-subtle);">'+(c.status==='pass' ? '+'+c.points+'đ' : c.status==='info' ? '' : '0/'+c.points+'đ')+'</td></tr>';
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

            $(document).on('click', '.laca-btn-whitelist', function(e){
                e.preventDefault();
                var $btn = $(this);
                var file = $btn.data('file');
                if (!file) return;
                if (!confirm('Bạn có chắc muốn đánh dấu an toàn cho file: ' + file + '?\nFile sẽ được bỏ qua trong các lần quét sau (trừ khi nội dung thay đổi).')) {
                    return;
                }
                $btn.prop('disabled', true).text('Đang lưu...');
                $.post(ajaxUrl, {
                    action: 'laca_malware_whitelist_add',
                    nonce: nonce,
                    file: file
                }, function(res){
                    if (res.success) {
                        var $tr = $btn.closest('tr');
                        $tr.addClass('laca-scan-row-whitelisted');
                        $btn.replaceWith('<span class="laca-scan-whitelisted"><span class="dashicons dashicons-yes-alt"></span> Đã an toàn</span>');
                    } else {
                        alert(res.data || 'Lỗi khi lưu.');
                        $btn.prop('disabled', false).text('Đánh dấu an toàn');
                    }
                }).fail(function(){
                    alert('Lỗi kết nối.');
                    $btn.prop('disabled', false).text('Đánh dấu an toàn');
                });
            });

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

            var i18n = lacaSecurity.i18n || {};

            function showAlert($slot, type, text) {
                if (!$slot || !$slot.length) return;
                var timer = $slot.data('alertTimer');
                if (timer) clearTimeout(timer);
                var safeText = $('<div>').text(text || '').html();
                $slot.html(
                    '<div class="laca-alert laca-alert--' + type + ' laca-mt-2">' +
                    '<div class="laca-alert__content"><p class="laca-alert__description">' + safeText + '</p></div>' +
                    '</div>'
                ).show();
                if (type === 'success') {
                    var t = setTimeout(function(){ $slot.fadeOut(200); }, 4000);
                    $slot.data('alertTimer', t);
                }
            }

            // ── Custom Login ──────────────────────────────────────────────────
            $('#btn-save-login').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.saving);
                $('#login-save-msg').empty();
                $.post(ajaxUrl, {
                    action: 'laca_save_login_settings',
                    nonce:  nonce,
                    slug:    $('#laca-login-slug').val(),
                    enabled: $('#laca-login-enabled').is(':checked') ? 1 : 0,
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.saveSettings);
                    if (res.success) {
                        showAlert($('#laca-login-slug-alert'), 'success', res.data);
                    } else {
                        showAlert($('#laca-login-slug-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.saveSettings);
                    showAlert($('#laca-login-slug-alert'), 'danger', i18n.netError);
                });
            });

            // ── Unlock IP (LoginGuard) ────────────────────────────────────────
            $(document).on('click', '.js-laca-unlock-ip', function(){
                var $btn      = $(this);
                var ip        = $btn.data('ip');
                var itemNonce = $btn.data('nonce');
                if (!ip) return;

                $btn.prop('disabled', true).text(i18n.unlocking);
                $.post(ajaxUrl, {
                    action: 'laca_login_guard_unlock',
                    _nonce: itemNonce,
                    ip:     ip
                }, function(res){
                    if (res.success) {
                        $btn.closest('tr').remove();
                        var remaining = $('#laca-locked-ips-table tbody tr').length;
                        var $badge    = $('#laca-locked-ip-badge');
                        $badge.text(remaining);
                        if (remaining <= 0) {
                            $badge.hide();
                            $('#laca-locked-ips-wrap').hide();
                            $('#laca-locked-ips-empty').show();
                        }
                        showAlert($('#laca-locked-ips-alert'), 'success', res.data && res.data.message ? res.data.message : '');
                    } else {
                        $btn.prop('disabled', false).text(i18n.unlock);
                        showAlert($('#laca-locked-ips-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.unlock);
                    showAlert($('#laca-locked-ips-alert'), 'danger', i18n.netError);
                });
            });

            // ── Add My IP to Whitelist ────────────────────────────────────────
            $('#btn-add-my-ip').on('click', function(){
                var ip = $(this).data('ip');
                if (!ip) return;
                var $ta   = $('#laca-lg-whitelist');
                var cur   = $.trim($ta.val());
                var lines = cur ? cur.split(/\r?\n/).map(function(s){ return $.trim(s); }) : [];
                if (lines.indexOf(ip) === -1) {
                    lines.push(ip);
                    $ta.val(lines.join('\n'));
                }
            });

            // ── Save Login Guard Limits & Whitelist ───────────────────────────
            $('#btn-save-login-guard').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.saving);
                $.post(ajaxUrl, {
                    action:             'laca_save_login_guard_settings',
                    nonce:              nonce,
                    save_scope:         'limits',
                    max_fails:          $('#laca-lg-max-fails').val(),
                    window_minutes:     $('#laca-lg-window').val(),
                    lockout_minutes:    $('#laca-lg-lockout').val(),
                    strikes_for_long:   $('#laca-lg-strikes').val(),
                    long_lockout_hours: $('#laca-lg-long-lockout').val(),
                    whitelist_ips:      $('#laca-lg-whitelist').val(),
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.save);
                    if (res.success && res.data) {
                        if ($.isArray(res.data.valid_ips)) {
                            $('#laca-lg-whitelist').val(res.data.valid_ips.join('\n'));
                        }
                        var hasRejected = $.isArray(res.data.rejected_ips) && res.data.rejected_ips.length > 0;
                        showAlert($('#laca-login-guard-alert'), hasRejected ? 'warning' : 'success', res.data.message);
                    } else {
                        showAlert($('#laca-login-guard-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.save);
                    showAlert($('#laca-login-guard-alert'), 'danger', i18n.netError);
                });
            });

            // ── Save Honeypot ─────────────────────────────────────────────────
            $('#btn-save-honeypot').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.saving);
                $.post(ajaxUrl, {
                    action:     'laca_save_login_guard_settings',
                    nonce:      nonce,
                    save_scope: 'honeypot',
                    honeypot:   $('#laca-login-honeypot').is(':checked') ? 'yes' : 'no',
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.save);
                    if (res.success && res.data) {
                        showAlert($('#laca-honeypot-alert'), 'success', res.data.message);
                    } else {
                        showAlert($('#laca-honeypot-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.save);
                    showAlert($('#laca-honeypot-alert'), 'danger', i18n.netError);
                });
            });

            // ── Segmented Control: Breached Password ──────────────────────────
            $('#laca-lp-breached .laca-segmented__item').on('click', function(){
                $('#laca-lp-breached .laca-segmented__item').removeClass('is-active');
                $(this).addClass('is-active');
                $('#laca-lp-breached-val').val($(this).data('value'));
            });

            // ── Save Login Policy ─────────────────────────────────────────────
            $('#btn-save-login-policy').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.saving);
                var selectedRoles = [];
                $('.js-laca-lp-role:checked').each(function(){
                    selectedRoles.push($(this).val());
                });
                $.post(ajaxUrl, {
                    action:                 'laca_save_login_policy',
                    nonce:                  nonce,
                    lock_invalid_usernames: $('#laca-lp-lock-invalid').is(':checked') ? 'yes' : 'no',
                    banned_usernames:       $('#laca-lp-banned-users').val(),
                    require_2fa_roles:      selectedRoles,
                    require_2fa_grace_days: $('#laca-lp-grace-days').val(),
                    lostpw_max_per_hour:    $('#laca-lp-lostpw').val(),
                    disable_app_passwords:  $('#laca-lp-disable-app-pw').is(':checked') ? 'yes' : 'no',
                    breached_check:         $('#laca-lp-breached-val').val(),
                    new_ip_alert:           $('#laca-lp-new-ip-alert').is(':checked') ? 'yes' : 'no',
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.save);
                    if (res.success && res.data) {
                        if ($.isArray(res.data.banned_usernames)) {
                            $('#laca-lp-banned-users').val(res.data.banned_usernames.join('\n'));
                        }
                        showAlert($('#laca-login-policy-alert'), 'success', res.data.message);
                    } else {
                        showAlert($('#laca-login-policy-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.save);
                    showAlert($('#laca-login-policy-alert'), 'danger', i18n.netError);
                });
            });

            // ── Test Login Notification ───────────────────────────────────────
            $('#btn-test-login-notify').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.testing);
                $.post(ajaxUrl, {
                    action: 'laca_login_notify_test',
                    nonce:  nonce,
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.testNotify);
                    if (res.success && res.data) {
                        showAlert($('#laca-login-notify-alert'), 'success', res.data.message);
                    } else {
                        showAlert($('#laca-login-notify-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.testNotify);
                    showAlert($('#laca-login-notify-alert'), 'danger', i18n.netError);
                });
            });

            // ── 2FA Master ────────────────────────────────────────────────────
            $('#btn-save-2fa').on('click', function(){
                $(this).prop('disabled',true);
                $('#2fa-save-msg').text(i18n.saving).css('color','#666');
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

            // ── Hardening Settings ─────────────────────────────────────────────
            $('#btn-save-hardening').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.saving);
                $('#hardening-save-msg').empty();
                $.post(ajaxUrl, {
                    action: 'laca_save_hardening_settings',
                    nonce: nonce,
                    disable_rest:     $('#laca-opt-disable-rest').is(':checked') ? 1 : 0,
                    disable_xmlrpc:   $('#laca-opt-disable-xmlrpc').is(':checked') ? 1 : 0,
                    disable_wpembed:  $('#laca-opt-disable-wpembed').is(':checked') ? 1 : 0,
                    disable_pingback: $('#laca-opt-disable-pingback').is(':checked') ? 1 : 0,
                    remove_bloat:     $('#laca-opt-remove-bloat').is(':checked') ? 1 : 0,
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.saveHardening);
                    if (res.success) {
                        showAlert($('#laca-hardening-alert'), 'success', res.data);
                    } else {
                        showAlert($('#laca-hardening-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.saveHardening);
                    showAlert($('#laca-hardening-alert'), 'danger', i18n.netError);
                });
            });

            // ── HTTP Security Headers Save & Check ────────────────────────────
            $('#btn-save-sec-headers').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.saving);
                $.post(ajaxUrl, {
                    action:             'laca_save_security_headers',
                    nonce:              nonce,
                    x_frame_options:    $('#laca-hdr-x-frame').is(':checked') ? 1 : 0,
                    x_content_type:     $('#laca-hdr-x-content-type').is(':checked') ? 1 : 0,
                    referrer_policy:    $('#laca-hdr-referrer').is(':checked') ? 1 : 0,
                    permissions_policy: $('#laca-hdr-permissions').is(':checked') ? 1 : 0,
                    hsts:               $('#laca-hdr-hsts').is(':checked') ? 1 : 0,
                    hsts_max_age:       $('#laca-hdr-hsts-max-age').val(),
                    hsts_include_sub:   $('#laca-hdr-hsts-sub').is(':checked') ? 1 : 0,
                    csp:                $('#laca-hdr-csp').is(':checked') ? 1 : 0,
                    remove_powered_by:  $('#laca-hdr-remove-powered').is(':checked') ? 1 : 0,
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.save);
                    if (res.success && res.data) {
                        showAlert($('#laca-sec-headers-alert'), 'success', res.data.message);
                    } else {
                        showAlert($('#laca-sec-headers-alert'), 'danger', res.data);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.save);
                    showAlert($('#laca-sec-headers-alert'), 'danger', i18n.netError);
                });
            });

            $('#btn-check-sec-headers').on('click', function(){
                var $btn    = $(this);
                var $result = $('#laca-sec-headers-check-result');
                $btn.prop('disabled', true).text(i18n.checking);
                $.post(ajaxUrl, {
                    action: 'laca_check_security_headers',
                    nonce:  nonce,
                }, function(res){
                    $btn.prop('disabled', false).text(i18n.checkHeaders);
                    if (!res.success || !res.data || !$.isArray(res.data.rows)) {
                        $result.hide().empty();
                        showAlert($('#laca-sec-headers-alert'), 'danger', res.data);
                        return;
                    }
                    var html = '<div class="laca-table-wrap"><table class="laca-table"><thead><tr>' +
                        '<th>' + $('<div>').text(i18n.colHeader).html() + '</th>' +
                        '<th>' + $('<div>').text(i18n.colValue).html() + '</th>' +
                        '<th>' + $('<div>').text(i18n.colStatus).html() + '</th>' +
                        '</tr></thead><tbody>';
                    $.each(res.data.rows, function(_, r){
                        var hName  = $('<div>').text(r.header || '').html();
                        var valFull = $('<div>').text(r.value || '').html();
                        var valShort = $('<div>').text(r.short || '—').html();
                        var badge  = r.present
                            ? '<span class="laca-badge laca-badge--success">' + $('<div>').text(i18n.statusSent).html() + '</span>'
                            : '<span class="laca-badge laca-badge--neutral">' + $('<div>').text(i18n.statusMissing).html() + '</span>';
                        html += '<tr>' +
                            '<td><code class="laca-mono">' + hName + '</code></td>' +
                            '<td><code class="laca-mono laca-code-truncate" title="' + valFull + '">' + (r.present ? valShort : '—') + '</code></td>' +
                            '<td>' + badge + '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table></div>';
                    $result.html(html).show();
                }).fail(function(){
                    $btn.prop('disabled', false).text(i18n.checkHeaders);
                    showAlert($('#laca-sec-headers-alert'), 'danger', i18n.netError);
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
