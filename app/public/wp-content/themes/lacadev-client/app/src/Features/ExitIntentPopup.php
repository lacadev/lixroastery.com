<?php

namespace App\Features;

/**
 * ExitIntentPopup
 *
 * Popup xuất hiện khi user có ý định rời trang (exit intent).
 * Cấu hình qua wp-options (hoặc Theme Settings).
 *
 * Triggers:
 *  - Exit intent: mouse ra ngoài viewport phía top (desktop)
 *  - Time on page: sau X giây (mobile & desktop)
 *  - Scroll depth: khi cuộn qua X%
 *
 * Cookie: không hiển thị lại trong 24h sau khi đóng hoặc submit.
 *
 * Shortcode nội dung: [laca_exit_popup_content]
 * Admin cấu hình qua: Appearance > Exit Intent Popup
 *
 * Options (wp_options):
 *   laca_popup_enabled      → '1' | '0'
 *   laca_popup_title        → string
 *   laca_popup_content      → HTML/shortcode string
 *   laca_popup_trigger      → 'exit' | 'time' | 'scroll'
 *   laca_popup_delay        → seconds (for 'time' trigger)
 *   laca_popup_scroll_pct   → percent (for 'scroll' trigger)
 *   laca_popup_cookie_hours → hours before re-show
 */
class ExitIntentPopup
{
    const MENU_SLUG   = 'laca-exit-popup';
    const PARENT_SLUG = 'laca-admin';
    const CAP         = 'manage_options';
    const NONCE       = 'laca_exit_popup_settings';

    public function init(): void
    {
        add_action('admin_menu',   [$this, 'registerMenu'], 20);
        add_action('admin_post_laca_popup_save', [$this, 'handleSave']);
        add_action('wp_footer',    [$this, 'renderPopup'], 30);
    }

    // ── Admin menu ────────────────────────────────────────────────────────────

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            'Exit Intent Popup',
            'Exit Popup',
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    // ── Settings page ─────────────────────────────────────────────────────────

    public function renderSettingsPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }

        $msg = sanitize_key($_GET['laca_msg'] ?? '');
        ?>
        <div class="wrap">
            <h1>💬 Exit Intent Popup</h1>
            <?php if ($msg === 'saved'): ?>
                <div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:700px">
                <?php wp_nonce_field(self::NONCE, '_nonce'); ?>
                <input type="hidden" name="action" value="laca_popup_save">

                <table class="form-table">
                    <tr>
                        <th>Bật popup</th>
                        <td>
                            <label>
                                <input type="checkbox" name="laca_popup_enabled" value="1"
                                    <?php checked(get_option('laca_popup_enabled'), '1'); ?>>
                                Hiển thị popup cho khách truy cập
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Tiêu đề</label></th>
                        <td>
                            <input type="text" name="laca_popup_title" class="large-text"
                                value="<?php echo esc_attr(get_option('laca_popup_title', '🎁 Nhận tư vấn miễn phí')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Nội dung</label></th>
                        <td>
                            <textarea name="laca_popup_content" class="large-text" rows="5"
                                placeholder="HTML hoặc shortcode, VD: [laca_contact_form id=&quot;1&quot;]"><?php echo esc_textarea(get_option('laca_popup_content', '')); ?></textarea>
                            <p class="description">Hỗ trợ HTML và shortcode. Dùng <code>[laca_contact_form id="X"]</code> để nhúng form liên hệ.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Trigger</label></th>
                        <td>
                            <select name="laca_popup_trigger">
                                <?php
                                $triggers = ['exit' => 'Exit Intent (mouse rời viewport)', 'time' => 'Sau X giây', 'scroll' => 'Cuộn qua X%'];
                                $current  = get_option('laca_popup_trigger', 'exit');
                                foreach ($triggers as $val => $label):
                                ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php selected($current, $val); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Delay (giây)</label></th>
                        <td>
                            <input type="number" name="laca_popup_delay" min="1" max="300"
                                value="<?php echo esc_attr(get_option('laca_popup_delay', 8)); ?>" style="width:80px">
                            <p class="description">Chỉ dùng khi trigger = "Sau X giây".</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Scroll % trigger</label></th>
                        <td>
                            <input type="number" name="laca_popup_scroll_pct" min="10" max="90" step="5"
                                value="<?php echo esc_attr(get_option('laca_popup_scroll_pct', 60)); ?>" style="width:80px"> %
                            <p class="description">Chỉ dùng khi trigger = "Cuộn qua X%".</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Cookie (giờ)</label></th>
                        <td>
                            <input type="number" name="laca_popup_cookie_hours" min="1" max="720"
                                value="<?php echo esc_attr(get_option('laca_popup_cookie_hours', 24)); ?>" style="width:80px"> giờ
                            <p class="description">Sau bao lâu sẽ hiển thị lại sau khi đóng.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Lưu cài đặt</button>
                </p>
            </form>
        </div>
        <?php
    }

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }
        check_admin_referer(self::NONCE, '_nonce');

        $fields = [
            'laca_popup_enabled'      => ['checkbox', '0'],
            'laca_popup_title'        => ['text', ''],
            'laca_popup_content'      => ['textarea', ''],
            'laca_popup_trigger'      => ['key', 'exit'],
            'laca_popup_delay'        => ['int', 8],
            'laca_popup_scroll_pct'   => ['int', 60],
            'laca_popup_cookie_hours' => ['int', 24],
        ];

        foreach ($fields as $key => [$type, $default]) {
            $raw = $_POST[$key] ?? $default;
            $val = match ($type) {
                'checkbox' => isset($_POST[$key]) ? '1' : '0',
                'text'     => sanitize_text_field($raw),
                'textarea' => wp_kses_post($raw),
                'key'      => sanitize_key($raw),
                'int'      => absint($raw),
                default    => sanitize_text_field($raw),
            };
            update_option($key, $val, false);
        }

        wp_redirect(admin_url('themes.php?page=' . self::MENU_SLUG . '&laca_msg=saved'));
        exit;
    }

    // ── Frontend popup ────────────────────────────────────────────────────────

    public function renderPopup(): void
    {
        if (is_admin() || !get_option('laca_popup_enabled')) {
            return;
        }

        $title       = get_option('laca_popup_title', '');
        $content     = do_shortcode(get_option('laca_popup_content', ''));
        $trigger     = get_option('laca_popup_trigger', 'exit');
        $delay       = (int) get_option('laca_popup_delay', 8);
        $scrollPct   = (int) get_option('laca_popup_scroll_pct', 60);
        $cookieHours = (int) get_option('laca_popup_cookie_hours', 24);

        if (!$content) {
            return;
        }
        ?>
        <div id="laca-exit-popup" class="laca-exit-popup" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($title); ?>" hidden>
            <div class="laca-exit-popup__overlay"></div>
            <div class="laca-exit-popup__box">
                <button class="laca-exit-popup__close" aria-label="Đóng">✕</button>
                <?php if ($title): ?>
                    <h3 class="laca-exit-popup__title"><?php echo esc_html($title); ?></h3>
                <?php endif; ?>
                <div class="laca-exit-popup__content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            </div>
        </div>

        <style>
        .laca-exit-popup { position: fixed; inset: 0; z-index: 99998; display: flex; align-items: center; justify-content: center; }
        .laca-exit-popup[hidden] { display: none; }
        .laca-exit-popup__overlay { position: absolute; inset: 0; background: rgba(0,0,0,.6); }
        .laca-exit-popup__box {
            position: relative; z-index: 1;
            background: #fff; border-radius: 10px;
            padding: 32px 28px; max-width: 520px; width: 92vw;
            max-height: 88vh; overflow-y: auto;
            box-shadow: 0 12px 48px rgba(0,0,0,.3);
            animation: lacaPopupIn .3s ease;
        }
        [data-theme="dark"] .laca-exit-popup__box { background: #1e1e1e; color: #f0f0f0; }
        @keyframes lacaPopupIn {
            from { transform: scale(.9) translateY(20px); opacity: 0; }
            to   { transform: scale(1) translateY(0);     opacity: 1; }
        }
        .laca-exit-popup__close {
            position: absolute; top: 12px; right: 14px;
            background: none; border: none; font-size: 22px;
            cursor: pointer; color: #aaa; line-height: 1;
        }
        .laca-exit-popup__close:hover { color: #333; }
        .laca-exit-popup__title { margin: 0 0 16px; font-size: 1.2rem; }
        .laca-exit-popup__content { font-size: 14px; }
        </style>

        <script>
        (function() {
            const COOKIE_KEY  = 'laca_popup_closed';
            const COOKIE_HOURS = <?php echo (int) $cookieHours; ?>;
            const TRIGGER     = '<?php echo esc_js($trigger); ?>';
            const DELAY_MS    = <?php echo (int) $delay * 1000; ?>;
            const SCROLL_PCT  = <?php echo (int) $scrollPct; ?>;

            function getCookie(name) {
                return document.cookie.split(';').some(c => c.trim().startsWith(name + '='));
            }
            function setCookie(name, hours) {
                const d = new Date();
                d.setTime(d.getTime() + hours * 3600 * 1000);
                document.cookie = name + '=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
            }

            if (getCookie(COOKIE_KEY)) return; // already closed recently

            const popup = document.getElementById('laca-exit-popup');
            if (!popup) return;

            function show() {
                popup.hidden = false;
                document.body.style.overflow = 'hidden';
            }

            function close() {
                popup.hidden = true;
                document.body.style.overflow = '';
                setCookie(COOKIE_KEY, COOKIE_HOURS);
            }

            popup.querySelector('.laca-exit-popup__close').addEventListener('click', close);
            popup.querySelector('.laca-exit-popup__overlay').addEventListener('click', close);
            document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

            if (TRIGGER === 'exit') {
                document.addEventListener('mouseleave', function handler(e) {
                    if (e.clientY <= 0) {
                        show();
                        document.removeEventListener('mouseleave', handler);
                    }
                });
                // Mobile fallback: time-based after 15s
                setTimeout(() => { if (popup.hidden) show(); }, 15000);

            } else if (TRIGGER === 'time') {
                setTimeout(show, DELAY_MS);

            } else if (TRIGGER === 'scroll') {
                window.addEventListener('scroll', function handler() {
                    const pct = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
                    if (pct >= SCROLL_PCT) {
                        show();
                        window.removeEventListener('scroll', handler);
                    }
                }, { passive: true });
            }
        })();
        </script>
        <?php
    }
}
