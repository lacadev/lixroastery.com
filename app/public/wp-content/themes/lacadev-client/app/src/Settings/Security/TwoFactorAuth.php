<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Two-Factor Authentication (TOTP — Google Authenticator compatible)
 *
 * Port từ Foxblock_TwoFactorAuth — options đổi sang laca_*.
 *
 * Options:  laca_2fa_master_enabled
 * User meta: laca_2fa_enabled, laca_2fa_verified, laca_2fa_secret, laca_2fa_backup_codes
 * Transients: laca_2fa_{token}  (TTL 5 phút)
 * AJAX: laca_2fa_get_secret, laca_2fa_verify_setup, laca_2fa_disable, laca_2fa_regen_backup
 */
class TwoFactorAuth
{
    private ?string $pendingToken = null;

    public function __construct()
    {
        // Login hooks — always active so the intercept works
        add_filter('authenticate', [$this, 'handleOtpStep'],      5,  3);
        add_filter('authenticate', [$this, 'interceptPostAuth'],  30, 3);
        add_action('login_form',   [$this, 'renderOtpField']);

        // Profile section
        add_action('show_user_profile',       [$this, 'renderProfileSection']);
        add_action('edit_user_profile',       [$this, 'renderProfileSection']);
        add_action('personal_options_update', [$this, 'saveProfileSection']);
        add_action('edit_user_profile_update',[$this, 'saveProfileSection']);

        // AJAX (logged-in)
        add_action('wp_ajax_laca_2fa_get_secret',    [$this, 'ajaxGetSecret']);
        add_action('wp_ajax_laca_2fa_verify_setup',  [$this, 'ajaxVerifySetup']);
        add_action('wp_ajax_laca_2fa_disable',        [$this, 'ajaxDisable']);
        add_action('wp_ajax_laca_2fa_regen_backup',  [$this, 'ajaxRegenBackup']);

        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    // ═══════════════════════════════════════════════════════════
    //  TOTP CORE
    // ═══════════════════════════════════════════════════════════

    public function generateSecret(int $length = 16): string
    {
        $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bytes   = random_bytes($length);
        $secret  = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $charset[ord($bytes[$i]) & 31];
        }
        return $secret;
    }

    private function base32Decode(string $secret): string
    {
        $charset  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret   = strtoupper(str_replace(' ', '', $secret));
        $buffer   = 0;
        $bitsLeft = 0;
        $result   = '';
        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($charset, $secret[$i]);
            if ($val === false) continue;
            $buffer    = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result   .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $result;
    }

    public function generateTotp(string $secret, int $time = 0): string
    {
        if ($time === 0) $time = time();
        $step = (int) floor($time / 30);
        $key  = $this->base32Decode($secret);
        $msg  = pack('N*', 0) . pack('N*', $step);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$off])     & 0x7F) << 24) |
            ((ord($hash[$off + 1]) & 0xFF) << 16) |
            ((ord($hash[$off + 2]) & 0xFF) <<  8) |
            ( ord($hash[$off + 3]) & 0xFF)
        ) % 1000000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    public function verifyTotp(string $secret, string $code): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $now = time();
        for ($drift = -1; $drift <= 1; $drift++) {
            if (hash_equals($this->generateTotp($secret, $now + $drift * 30), $code)) {
                return true;
            }
        }
        return false;
    }

    // ═══════════════════════════════════════════════════════════
    //  BACKUP CODES
    // ═══════════════════════════════════════════════════════════

    public function generateBackupCodes(int $userId): array
    {
        $plain  = [];
        $hashed = [];
        for ($i = 0; $i < 5; $i++) {
            $code     = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $plain[]  = $code;
            $hashed[] = wp_hash_password($code);
        }
        update_user_meta($userId, 'laca_2fa_backup_codes', $hashed);
        return $plain;
    }

    private function verifyAndConsumeBackup(int $userId, string $code): bool
    {
        $codes = get_user_meta($userId, 'laca_2fa_backup_codes', true);
        if (!is_array($codes)) return false;
        foreach ($codes as $idx => $hash) {
            if (wp_check_password(strtoupper($code), $hash)) {
                unset($codes[$idx]);
                update_user_meta($userId, 'laca_2fa_backup_codes', array_values($codes));
                return true;
            }
        }
        return false;
    }

    // ═══════════════════════════════════════════════════════════
    //  LOGIN FLOW
    // ═══════════════════════════════════════════════════════════

    /** Priority 5 — xử lý bước OTP */
    public function handleOtpStep($user, $username, $password)
    {
        $token = sanitize_text_field($_POST['laca_2fa_token'] ?? '');
        if (empty($token)) return $user;

        $userId = (int) get_transient('laca_2fa_' . $token);
        if (!$userId) {
            return new \WP_Error(
                'laca_2fa_expired',
                '<strong>Lỗi:</strong> Phiên xác thực đã hết hạn. Vui lòng đăng nhập lại.'
            );
        }

        $otp    = trim(sanitize_text_field($_POST['laca_otp'] ?? ''));
        $secret = get_user_meta($userId, 'laca_2fa_secret', true);

        // Thử TOTP 6 chữ số
        if (preg_match('/^\d{6}$/', $otp) && $this->verifyTotp($secret, $otp)) {
            delete_transient('laca_2fa_' . $token);
            return get_user_by('id', $userId);
        }

        // Thử backup code 8 ký tự
        if (strlen($otp) === 8 && $this->verifyAndConsumeBackup($userId, $otp)) {
            delete_transient('laca_2fa_' . $token);
            return get_user_by('id', $userId);
        }

        return new \WP_Error(
            'laca_bad_otp',
            '<strong>Lỗi:</strong> Mã xác thực không đúng. Kiểm tra ứng dụng hoặc mã dự phòng.'
        );
    }

    /** Priority 30 — chặn login thành công khi 2FA bật */
    public function interceptPostAuth($user, $username, $password)
    {
        if (!($user instanceof \WP_User)) return $user;
        if (!empty($_POST['laca_2fa_token'])) return $user;

        $master   = get_option('laca_2fa_master_enabled', 0);
        $enabled  = get_user_meta($user->ID, 'laca_2fa_enabled', true);
        $verified = get_user_meta($user->ID, 'laca_2fa_verified', true);

        if (!$master || !$enabled || !$verified) return $user;

        $token = wp_generate_password(40, false, false);
        set_transient('laca_2fa_' . $token, $user->ID, 5 * MINUTE_IN_SECONDS);
        $this->pendingToken = $token;

        return new \WP_Error(
            'laca_2fa_required',
            'Tài khoản này đã bật 2FA. Vui lòng nhập mã từ ứng dụng Authenticator.'
        );
    }

    public function renderOtpField(): void
    {
        if (!$this->pendingToken) return;
        $token = esc_attr($this->pendingToken);
        ?>
        <input type="hidden" name="laca_2fa_token" value="<?php echo $token; ?>">
        <p style="margin-bottom:20px;">
            <label for="laca_otp">Mã xác thực (6 chữ số hoặc mã dự phòng)</label>
            <input id="laca_otp" type="text" name="laca_otp" class="input laca-2fa-login-input" inputmode="numeric"
                   autocomplete="one-time-code" maxlength="8" placeholder="000000" autofocus>
        </p>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            ['user_login','user_pass'].forEach(function(id){
                var el = document.getElementById(id);
                if (!el) return;
                el.removeAttribute('required');
                var wrap = el.closest('.user-pass-wrap') || el.closest('p');
                if (wrap) wrap.style.display = 'none'; // động
            });
            var forget = document.querySelector('.forgetmenot');
            if (forget) forget.style.display = 'none'; // động
            var otp = document.getElementById('laca_otp');
            if (otp) otp.focus();
        });
        </script>
        <?php
    }

    // ═══════════════════════════════════════════════════════════
    //  USER PROFILE
    // ═══════════════════════════════════════════════════════════

    public function renderProfileSection(\WP_User $user): void
    {
        if (!get_option('laca_2fa_master_enabled', 0)) return;

        $enabled    = (bool) get_user_meta($user->ID, 'laca_2fa_enabled', true);
        $verified   = (bool) get_user_meta($user->ID, 'laca_2fa_verified', true);
        $secret     = get_user_meta($user->ID, 'laca_2fa_secret', true);
        $backups    = get_user_meta($user->ID, 'laca_2fa_backup_codes', true);
        $backupCnt  = is_array($backups) ? count($backups) : 0;

        $issuer   = get_bloginfo('name');
        $label    = rawurlencode($issuer . ':' . $user->user_email);
        $otpauth  = 'otpauth://totp/' . $label . '?secret=' . ($secret ?: '') . '&issuer=' . rawurlencode($issuer);
        ?>
        <div id="laca-2fa-profile" class="laca-2fa-profile">
            <h2 style="margin:0 0 6px;font-size:17px;">🔐 Xác thực 2 bước (2FA)</h2>
            <p class="description" style="margin:0 0 20px;">Bảo vệ tài khoản bằng mã OTP từ Google/Microsoft Authenticator.</p>

            <?php if ($verified && $enabled): ?>
                <div class="laca-alert laca-alert--success" style="margin-bottom:16px;">
                    <span style="font-size:20px; line-height:1;">✓</span>
                    <div>
                        <strong class="laca-alert__title">2FA đang hoạt động</strong>
                        <div style="font-size:12px;">Còn <strong><?php echo $backupCnt; ?></strong> mã dự phòng.</div>
                    </div>
                </div>
                <div class="laca-security-flex">
                    <button type="button" id="laca-2fa-regen-backup" class="button button-secondary">Tạo lại mã dự phòng</button>
                    <button type="button" id="laca-2fa-disable-btn" class="button button-link-delete">Tắt 2FA</button>
                </div>
                <div id="laca-2fa-backup-display" class="laca-2fa-backup-box" style="display:none; /* động */">
                    <strong>Mã dự phòng (mỗi mã dùng 1 lần — lưu ngay!):</strong>
                    <div id="laca-2fa-codes-list" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;"></div>
                </div>
            <?php else: ?>
                <button type="button" id="laca-2fa-setup-btn" class="button button-primary">
                    <?php echo $enabled ? '▶ Hoàn tất cài đặt' : '+ Bật 2FA'; ?>
                </button>
                <div id="laca-2fa-setup-panel" style="display:none; /* động */ margin-top:20px;">
                    <ol style="font-size:13px;line-height:2;margin:0 0 16px 20px;">
                        <li>Cài <strong>Google Authenticator</strong> hoặc <strong>Microsoft Authenticator</strong></li>
                        <li>Quét mã QR bên dưới hoặc nhập thủ công secret key</li>
                        <li>Nhập mã 6 chữ số từ ứng dụng để xác nhận</li>
                    </ol>
                    <div class="laca-security-flex" style="align-items:flex-start; gap:24px;">
                        <div id="laca-qrcode" class="laca-2fa-qrcode"></div>
                        <div style="flex:1;min-width:220px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">SECRET KEY</label>
                            <code id="laca-secret-display" class="laca-2fa-secret"></code>
                            <input type="hidden" id="laca-2fa-secret-val">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">MÃ XÁC NHẬN (6 chữ số)</label>
                            <div style="display:flex;gap:8px;">
                                <input type="text" id="laca-2fa-verify-code" class="regular-text laca-2fa-code-input" inputmode="numeric"
                                       maxlength="6" placeholder="000000">
                                <button type="button" id="laca-2fa-confirm-btn" class="button button-primary">Xác nhận</button>
                            </div>
                            <div id="laca-2fa-verify-msg" style="margin-top:8px;font-size:13px;"></div>
                        </div>
                    </div>
                    <div id="laca-2fa-backup-first" class="laca-2fa-backup-box" style="display:none; /* động */">
                        <strong>2FA đã bật! Lưu các mã dự phòng (dùng khi mất điện thoại):</strong>
                        <div id="laca-2fa-backup-codes" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;"></div>
                        <p style="font-size:12px;margin:10px 0 0;">Sau khi tắt trang, bạn sẽ không thể xem lại các mã này.</p>
                    </div>
                </div>
            <?php endif; ?>
            <p style="margin:16px 0 0;font-size:12px;color:var(--laca-fg-subtle);">
                <?php wp_nonce_field('laca_2fa_nonce', 'laca_2fa_nonce'); ?>
            </p>
        </div>
        <script>
        window.laca2faConfig = {
            userId:    <?php echo (int) $user->ID; ?>,
            ajaxUrl:   <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce:     <?php echo wp_json_encode(wp_create_nonce('laca_2fa_nonce')); ?>,
            otpauth:   <?php echo wp_json_encode($otpauth); ?>,
            hasSecret: <?php echo wp_json_encode(!empty($secret)); ?>,
            issuer:    <?php echo wp_json_encode($issuer); ?>,
            email:     <?php echo wp_json_encode($user->user_email); ?>,
        };
        </script>
        <?php
    }

    public function saveProfileSection(int $userId): void
    {
        // All profile changes go via AJAX
    }

    // ═══════════════════════════════════════════════════════════
    //  AJAX
    // ═══════════════════════════════════════════════════════════

    public function ajaxGetSecret(): void
    {
        check_ajax_referer('laca_2fa_nonce', 'nonce');
        $userId = get_current_user_id();
        if (!$userId) wp_send_json_error('Unauthorized');

        $secret = get_user_meta($userId, 'laca_2fa_secret', true);
        if (empty($secret)) {
            $secret = $this->generateSecret();
            update_user_meta($userId, 'laca_2fa_secret', $secret);
        }

        $issuer   = get_bloginfo('name');
        $user     = get_user_by('id', $userId);
        $label    = rawurlencode($issuer . ':' . $user->user_email);
        $otpauth  = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);

        wp_send_json_success(['secret' => $secret, 'otpauth' => $otpauth]);
    }

    public function ajaxVerifySetup(): void
    {
        check_ajax_referer('laca_2fa_nonce', 'nonce');
        $userId = get_current_user_id();
        if (!$userId) wp_send_json_error('Unauthorized');

        $code   = sanitize_text_field($_POST['code'] ?? '');
        $secret = get_user_meta($userId, 'laca_2fa_secret', true);

        if (empty($secret)) wp_send_json_error('Chưa có secret key.');
        if (!$this->verifyTotp($secret, $code)) {
            wp_send_json_error('Mã không đúng. Kiểm tra đồng hồ điện thoại và thử lại.');
        }

        update_user_meta($userId, 'laca_2fa_enabled',  1);
        update_user_meta($userId, 'laca_2fa_verified', 1);
        $plainCodes = $this->generateBackupCodes($userId);
        wp_send_json_success(['backup_codes' => $plainCodes]);
    }

    public function ajaxDisable(): void
    {
        check_ajax_referer('laca_2fa_nonce', 'nonce');
        $userId = get_current_user_id();
        if (!$userId) wp_send_json_error('Unauthorized');

        delete_user_meta($userId, 'laca_2fa_enabled');
        delete_user_meta($userId, 'laca_2fa_verified');
        delete_user_meta($userId, 'laca_2fa_secret');
        delete_user_meta($userId, 'laca_2fa_backup_codes');

        wp_send_json_success('2FA đã được tắt thành công.');
    }

    public function ajaxRegenBackup(): void
    {
        check_ajax_referer('laca_2fa_nonce', 'nonce');
        $userId = get_current_user_id();
        if (!$userId) wp_send_json_error('Unauthorized');

        wp_send_json_success(['backup_codes' => $this->generateBackupCodes($userId)]);
    }

    // ═══════════════════════════════════════════════════════════
    //  ENQUEUE
    // ═══════════════════════════════════════════════════════════

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['profile.php', 'user-edit.php'], true)) return;
        if (!get_option('laca_2fa_master_enabled', 0)) return;

        // QR code từ plugin Foxblock nếu đang active, fallback CDN
        if (defined('FOXBLOCK_PLUGIN_URL')) {
            $qrSrc = FOXBLOCK_PLUGIN_URL . 'assets/js/qrcode.min.js';
        } else {
            $qrSrc = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        }
        wp_enqueue_script('laca-qrcode', $qrSrc, [], '1.0.0', true);
        // get_template_directory_uri() trỏ vào .../theme (style.css nằm
        // trong subfolder "theme/") — thiếu dirname() nên URL trước đây trỏ
        // sai thư mục (.../theme/resources/... thay vì .../resources/...),
        // giống quy ước dirname(get_template_directory_uri()) đã dùng đúng
        // ở DashboardWidgets.php/FrontendChatbotHandler.php.
        wp_enqueue_script('laca-2fa-profile', dirname(get_template_directory_uri()) . '/resources/scripts/admin/laca-2fa.js', ['laca-qrcode', 'jquery'], '1.0.0', true);
    }
}
