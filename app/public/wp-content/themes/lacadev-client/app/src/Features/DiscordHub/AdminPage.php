<?php

namespace App\Features\DiscordHub;

use App\Settings\AdminSettings;

/**
 * Laca Admin → Discord Hub.
 *
 * Webhook URL là "mật khẩu" của kênh: ô nhập luôn rỗng, giá trị đã lưu KHÔNG bao giờ
 * được in ra HTML (kể cả dạng che một phần) — chỉ hiện trạng thái. Muốn đổi thì dán
 * URL mới từ Discord.
 */
final class AdminPage
{
    public const MENU_SLUG = 'laca-discord-hub';
    private const PARENT_SLUG = 'laca-admin';
    private const CAP = 'manage_options';
    private const ACTION = 'laca_discord_hub_save';
    private const NOTICE_KEY = 'laca_dh_notice_';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_post_' . self::ACTION, [$this, 'handleSave']);
    }

    /**
     * Chỉ Super User (mặc định 'lacadev') — admin của khách không được đổi nơi nhận lead/cảnh báo.
     * Super User "extra" bị chặn thêm qua AdminSettings::getExtraSuperUserDeniedLacaSlugs().
     */
    public static function currentUserCanManage(): bool
    {
        $user = wp_get_current_user();
        return current_user_can(self::CAP)
            && $user->exists()
            && in_array($user->user_login, AdminSettings::getSuperUserLogins(), true);
    }

    public function registerMenu(): void
    {
        if (!self::currentUserCanManage()) {
            return;
        }
        add_submenu_page(self::PARENT_SLUG, 'Discord Hub', 'Discord Hub', self::CAP, self::MENU_SLUG, [$this, 'renderPage']);
    }

    public static function testEmbed(string $channel): Embed
    {
        return Embed::make(I18n::t('✅ Discord Hub — kết nối thành công', '✅ Discord Hub — connected'), Embed::COLOR_SUCCESS)
            ->description(sprintf(I18n::block('Kênh **%1$s** đã nhận được thông báo từ website.', 'Channel **%1$s** is receiving notifications from the website.'), Embed::escape(Settings::CHANNELS[$channel]['label'] ?? $channel)), true)
            ->field(I18n::t('Người gửi thử', 'Sent by'), Context::actor())
            ->field('WordPress', get_bloginfo('version'))
            ->field('PHP', PHP_VERSION);
    }

    // ── Lưu ───────────────────────────────────────────────────────────────────

    public function handleSave(): void
    {
        if (!self::currentUserCanManage()) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'), '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);

        $notices = [];
        $settings = Settings::all();

        $settings['enabled'] = !empty($_POST['enabled']);
        $settings['allow_non_production'] = !empty($_POST['allow_non_production']);
        $settings['site_name'] = mb_substr(sanitize_text_field(wp_unslash($_POST['site_name'] ?? '')), 0, 80);

        $language = sanitize_key(wp_unslash($_POST['language'] ?? ''));
        $settings['language'] = isset(I18n::LANGUAGES[$language]) ? $language : 'both';

        $ipHeader = sanitize_text_field(wp_unslash($_POST['ip_header'] ?? ''));
        $settings['ip_header'] = isset(Settings::IP_HEADERS[$ipHeader]) ? $ipHeader : '';

        $postTypes = array_map('sanitize_key', (array) wp_unslash($_POST['post_types'] ?? []));
        $settings['post_types'] = array_values(array_filter($postTypes, 'post_type_exists'));

        Settings::save($settings);

        $pasted = (array) wp_unslash($_POST['webhook'] ?? []);
        $remove = (array) wp_unslash($_POST['remove'] ?? []);

        foreach (Settings::CHANNELS as $channel => $info) {
            if (!empty($remove[$channel])) {
                Settings::removeWebhook($channel);
                $notices[] = ['warning', sprintf('Đã xoá webhook kênh "%s".', $info['label'])];
                continue;
            }

            // Không sanitize_text_field: URL hợp lệ đã bị regex whitelist khoá chặt bên dưới.
            $url = trim((string) ($pasted[$channel] ?? ''));
            if ('' === $url) {
                continue;
            }
            if (!Settings::isValidWebhook($url)) {
                // Không in lại giá trị vừa dán (tránh lộ nếu dán nhầm thứ khác vào).
                $notices[] = ['error', sprintf('Kênh "%s": không phải Webhook URL Discord hợp lệ (phải bắt đầu bằng https://discord.com/api/webhooks/…). Chưa lưu.', $info['label'])];
                continue;
            }
            if (!WebhookVault::isAvailable()) {
                $notices[] = ['error', 'Không mã hoá được: wp-config.php thiếu AUTH_KEY/SECURE_AUTH_KEY hợp lệ, hoặc PHP thiếu OpenSSL. Chưa lưu webhook.'];
                continue;
            }
            $notices[] = Settings::storeWebhook($channel, $url)
                ? ['success', sprintf('Đã lưu (mã hoá) webhook kênh "%s".', $info['label'])]
                : ['error', sprintf('Kênh "%s": lưu thất bại.', $info['label'])];
        }

        $testChannel = sanitize_key(wp_unslash($_POST['test_channel'] ?? ''));
        if (isset(Settings::CHANNELS[$testChannel])) {
            $notices[] = $this->runTest($testChannel);
        } elseif (!$notices) {
            $notices[] = ['success', 'Đã lưu cài đặt.'];
        }

        set_transient(self::NOTICE_KEY . get_current_user_id(), $notices, MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page', self::MENU_SLUG, admin_url('admin.php')));
        exit;
    }

    /**
     * Gửi thử đồng bộ ngay trong request admin để báo kết quả thật (không đợi cron).
     *
     * @return array{0:string,1:string}
     */
    private function runTest(string $channel): array
    {
        $label = Settings::CHANNELS[$channel]['label'];

        if (!Settings::hasChannel($channel)) {
            return ['error', sprintf('Kênh "%s" chưa có webhook — dán Webhook URL rồi bấm Gửi thử lại.', $label)];
        }
        if (!Settings::isEnabled()) {
            return ['error', 'Discord Hub đang TẮT — bật lên rồi gửi thử lại.'];
        }
        if (!Settings::isEnvironmentAllowed()) {
            return ['error', sprintf('Môi trường hiện tại là "%s" — bật "Cho phép gửi khi không phải production" để thử trên site này.', wp_get_environment_type())];
        }
        if ('broken' === Settings::channelStatus($channel)['state']) {
            return ['error', sprintf('Kênh "%s": webhook đã lưu không giải mã được (salt trong wp-config đã đổi?). Dán lại Webhook URL.', $label)];
        }

        // Admin chủ động thử → gỡ khoá cũ (có thể do webhook cũ hỏng) để thử thật ngay.
        DiscordWebhookSender::resetChannel($channel);
        $start = time();
        Notifier::send($channel, 'test', self::testEmbed($channel));
        Dispatcher::flush();

        $result = DiscordWebhookSender::lastResult($channel);
        if ($result['error'] !== '' && $result['error_at'] >= $start) {
            return ['error', sprintf('Gửi thử kênh "%s" thất bại: %s', $label, $result['error'])];
        }
        if ($result['ok_at'] >= $start) {
            return ['success', sprintf('Đã gửi tin thử tới kênh "%s" — kiểm tra Discord.', $label)];
        }

        return ['warning', sprintf('Kênh "%s": tin thử đang chờ trong hàng đợi (đang bị giới hạn tốc độ), sẽ tự gửi sau.', $label)];
    }

    // ── Giao diện ─────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        if (!self::currentUserCanManage()) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'), '', ['response' => 403]);
        }

        $settings = Settings::all();
        $notices = get_transient(self::NOTICE_KEY . get_current_user_id()) ?: [];
        delete_transient(self::NOTICE_KEY . get_current_user_id());
        $env = wp_get_environment_type();
        $postTypes = get_post_types(['public' => true], 'objects');
        unset($postTypes['attachment']);
        $stats = Queue::stats();
        $nextFlush = wp_next_scheduled(Dispatcher::CRON_HOOK);
        $badges = [
            'ok' => ['laca-dh-badge--ok', 'Đã kết nối'],
            'broken' => ['laca-dh-badge--error', 'Không giải mã được'],
            'empty' => ['laca-dh-badge--empty', 'Chưa cấu hình'],
        ];
        ?>
        <div class="wrap laca-dh">
            <h1>Discord Hub</h1>
            <p class="description">Gửi lead, hoạt động và cảnh báo bảo mật của website về Discord. Mỗi kênh dùng 1 Webhook URL lấy từ Discord (Chỉnh sửa kênh → Tích hợp → Webhook → Sao chép URL Webhook).</p>

            <?php foreach ((array) $notices as $notice) : ?>
                <div class="notice notice-<?php echo esc_attr($notice[0]); ?> is-dismissible"><p><?php echo esc_html($notice[1]); ?></p></div>
            <?php endforeach; ?>

            <?php if (!Settings::isEnvironmentAllowed()) : ?>
                <div class="notice notice-error">
                    <p><strong>Đang KHÔNG gửi thông báo nào:</strong> <code>WP_ENVIRONMENT_TYPE</code> = <code><?php echo esc_html($env); ?></code> (không phải <code>production</code>).
                    Nếu đây là site thật, sửa dòng <code>define( 'WP_ENVIRONMENT_TYPE', ... )</code> trong <code>wp-config.php</code> thành <code>'production'</code> (hoặc xoá dòng đó); nếu là site thử nghiệm, tick "Cho phép gửi khi không phải production" bên dưới.</p>
                </div>
            <?php endif; ?>

            <?php if (!WebhookVault::isAvailable()) : ?>
                <div class="notice notice-error"><p>Không thể mã hoá webhook: <code>wp-config.php</code> chưa có <code>AUTH_KEY</code>/<code>SECURE_AUTH_KEY</code> hợp lệ hoặc PHP thiếu OpenSSL. Tạo salt mới tại api.wordpress.org/secret-key/1.1/salt/.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION); ?>

                <h2>Kênh Discord</h2>
                <p>Webhook được <strong>mã hoá</strong> trước khi lưu và <strong>không bao giờ hiển thị lại</strong>. Để đổi, chỉ cần dán URL mới; để trống = giữ nguyên.</p>

                <table class="widefat laca-dh-channels">
                    <thead>
                        <tr>
                            <th scope="col">Kênh</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Webhook URL</th>
                            <th scope="col"><span class="screen-reader-text">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (Settings::CHANNELS as $channel => $info) :
                            $status = Settings::channelStatus($channel);
                            [$badgeClass, $badgeText] = $badges[$status['state']];
                            $inputId = 'laca-dh-webhook-' . $channel;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($info['label']); ?></strong>
                                    <p class="description"><?php echo esc_html($info['hint']); ?></p>
                                </td>
                                <td>
                                    <span class="laca-dh-badge <?php echo esc_attr($badgeClass); ?>"><?php echo esc_html($badgeText); ?></span>
                                    <?php if ($status['updated_at']) : ?>
                                        <p class="description">
                                            <?php echo esc_html(sprintf('Cập nhật %s bởi %s', wp_date('d/m/Y H:i', $status['updated_at']), $status['updated_by'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php
                                    $last = DiscordWebhookSender::lastResult($channel);
                                    if ('' !== $last['error'] && $last['error_at'] >= $last['ok_at']) : ?>
                                        <p class="laca-dh-last laca-dh-last--error">
                                            <?php echo esc_html(sprintf('Lỗi lúc %s: %s', wp_date('d/m H:i', $last['error_at']), $last['error'])); ?>
                                        </p>
                                    <?php elseif ($last['ok_at']) : ?>
                                        <p class="laca-dh-last laca-dh-last--ok">
                                            <?php echo esc_html('Gửi thành công lúc ' . wp_date('d/m H:i', $last['ok_at'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <label for="<?php echo esc_attr($inputId); ?>" class="screen-reader-text"><?php echo esc_html('Webhook URL — ' . $info['label']); ?></label>
                                    <input type="password" id="<?php echo esc_attr($inputId); ?>" name="webhook[<?php echo esc_attr($channel); ?>]" value=""
                                        class="large-text code" autocomplete="new-password" spellcheck="false" data-1p-ignore data-lpignore="true"
                                        placeholder="<?php echo esc_attr('empty' === $status['state'] ? 'Dán Webhook URL từ Discord…' : 'Đã lưu — dán URL mới để thay thế'); ?>">
                                    <?php if ('empty' !== $status['state']) : ?>
                                        <label class="laca-dh-remove">
                                            <input type="checkbox" name="remove[<?php echo esc_attr($channel); ?>]" value="1">
                                            Xoá webhook kênh này
                                        </label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="submit" name="test_channel" value="<?php echo esc_attr($channel); ?>" class="button">Lưu &amp; gửi thử</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>Cài đặt</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Trạng thái</th>
                        <td>
                            <label><input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>> Bật Discord Hub</label>
                            <p class="description">Tắt = ngừng ghi nhận và gửi mọi sự kiện (webhook vẫn được giữ).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="laca-dh-site-name">Tên hiển thị</label></th>
                        <td>
                            <input type="text" id="laca-dh-site-name" name="site_name" class="regular-text" maxlength="80"
                                value="<?php echo esc_attr($settings['site_name']); ?>" placeholder="<?php echo esc_attr(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)); ?>">
                            <p class="description">Tên website hiện trên mỗi tin Discord. Để trống = dùng tên site.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="laca-dh-language">Ngôn ngữ thông báo</label></th>
                        <td>
                            <select id="laca-dh-language" name="language">
                                <?php foreach (I18n::LANGUAGES as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected(Settings::language(), $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Ngôn ngữ của tin nhắn gửi lên Discord. Song ngữ: tiêu đề và tên trường dạng "Tiếng Việt / English", đoạn mô tả có thêm dòng tiếng Anh in nghiêng.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="laca-dh-ip-header">IP thật của khách</label></th>
                        <td>
                            <select id="laca-dh-ip-header" name="ip_header">
                                <?php foreach (Settings::IP_HEADERS as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['ip_header'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Chỉ chọn Cloudflare khi site <strong>thật sự</strong> chạy sau Cloudflare — nếu không, kẻ tấn công giả được IP. Bật Cloudflare thì cảnh báo brute-force có thêm quốc gia.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Môi trường</th>
                        <td>
                            <p>Hiện tại: <code><?php echo esc_html($env); ?></code></p>
                            <label><input type="checkbox" name="allow_non_production" value="1" <?php checked($settings['allow_non_production']); ?>> Cho phép gửi khi không phải production</label>
                            <p class="description">Mặc định chỉ production mới gửi, để bản Local/staging clone từ site thật không bắn thông báo giả vào kênh thật.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Audit nội dung</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Loại nội dung được báo khi đổi trạng thái</legend>
                                <?php foreach ($postTypes as $type) : ?>
                                    <label class="laca-dh-check">
                                        <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, $settings['post_types'], true)); ?>>
                                        <?php echo esc_html($type->labels->singular_name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">Báo về kênh Audit khi nội dung đổi trạng thái (nháp → chờ duyệt → xuất bản → thùng rác).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Lưu thay đổi'); ?>
            </form>

            <h2>Hàng đợi gửi</h2>
            <p>
                <?php if ($stats) : ?>
                    <?php foreach ($stats as $channel => $count) : ?>
                        <span class="laca-dh-badge laca-dh-badge--empty"><?php echo esc_html(sprintf('%s: %d chờ gửi', $channel, $count)); ?></span>
                    <?php endforeach; ?>
                    <br><span class="description"><?php echo esc_html($nextFlush ? 'Lần gửi tiếp theo: ' . wp_date('d/m/Y H:i:s', $nextFlush) : 'Sẽ gửi ở request tiếp theo.'); ?></span>
                <?php else : ?>
                    Không có sự kiện nào đang chờ.
                <?php endif; ?>
            </p>
        </div>
        <style>
            .laca-dh-channels td { vertical-align: top; padding: 14px 10px; }
            .laca-dh-channels td:first-child { width: 28%; }
            .laca-dh-channels td:nth-child(2) { width: 18%; }
            .laca-dh-channels td:last-child { width: 1%; white-space: nowrap; }
            .laca-dh-channels .description { margin: 4px 0 0; }
            .laca-dh-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 20px; }
            .laca-dh-badge--ok { background: #edfaef; color: #00701a; }
            .laca-dh-badge--error { background: #fcf0f1; color: #b32d2e; }
            .laca-dh-badge--empty { background: #f0f0f1; color: #50575e; }
            .laca-dh-last { margin: 6px 0 0; font-size: 12px; }
            .laca-dh-last--error { color: #b32d2e; }
            .laca-dh-last--ok { color: #00701a; }
            .laca-dh-remove { display: inline-block; margin-top: 6px; color: #b32d2e; }
            .laca-dh-check { display: inline-block; margin: 0 16px 6px 0; }
            @media (max-width: 782px) {
                .laca-dh-channels thead { display: none; }
                .laca-dh-channels td { display: block; width: auto !important; }
            }
        </style>
        <?php
    }
}
