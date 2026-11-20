<?php

namespace App\Features\FrontendChatbot;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chat trực tiếp khách ↔ nhân viên qua Telegram.
 *
 * - Nhóm Telegram bật "Chủ đề" (Topics): mỗi khách 1 topic, nhân viên trả lời
 *   ngay trong topic. Telegram đẩy tin về qua webhook (secret token) → lưu DB.
 * - Khung chat hỏi server vài giây/lần (poll) — shared hosting không chạy được
 *   websocket. Mỗi lần poll chỉ 2 truy vấn nhỏ theo khoá chính/index.
 * - Ngoài giờ làm việc hoặc quá N phút chưa ai trả lời → mời để lại thông tin.
 *
 * Mã phiên (32 hex, trình duyệt tự tạo) là "chìa khoá" của khách: chỉ ai có
 * mã mới đọc/gửi được tin của phiên đó. Không lưu IP.
 */
class ChatbotLive
{
    private const DB_VERSION     = '1';
    private const DB_VERSION_KEY = 'laca_chatbot_live_db_version';
    private const NS             = 'laca/v1';
    private const MAX_LEN        = 1000;

    // ── Cấu hình ──────────────────────────────────────────────────────────────

    public static function enabled(): bool
    {
        return get_option('laca_chatbot_live_enabled', '0') === '1' && self::token() !== '' && self::chatId() !== '';
    }

    private static function token(): string
    {
        $own = trim((string) get_option('laca_chatbot_live_token', ''));
        // Dùng lại bot của mục Thông báo nếu chưa nhập bot riêng.
        return $own !== '' ? $own : trim((string) get_option('_telegram_bot_token', ''));
    }

    private static function chatId(): string
    {
        return trim((string) get_option('laca_chatbot_live_chat_id', ''));
    }

    private static function secret(): string
    {
        $s = (string) get_option('laca_chatbot_live_secret', '');
        if ($s === '') {
            $s = wp_generate_password(40, false);
            update_option('laca_chatbot_live_secret', $s, false);
        }
        return $s;
    }

    private static function waitMinutes(): int
    {
        return max(1, min(60, (int) get_option('laca_chatbot_live_wait', 3)));
    }

    /** @return array{start:string,end:string,days:int[]} */
    private static function hours(): array
    {
        $days = array_values(array_filter(array_map('intval', explode(',', (string) get_option('laca_chatbot_live_days', '1,2,3,4,5,6,7'))), static fn($d) => $d >= 1 && $d <= 7));
        return [
            'start' => self::cleanTime((string) get_option('laca_chatbot_live_start', '08:00'), '08:00'),
            'end'   => self::cleanTime((string) get_option('laca_chatbot_live_end', '21:00'), '21:00'),
            'days'  => $days,
        ];
    }

    private static function cleanTime(string $v, string $fallback): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : $fallback;
    }

    /** Đang trong giờ làm việc (theo múi giờ của site)? */
    public static function isOpenNow(): bool
    {
        $h   = self::hours();
        $now = new \DateTimeImmutable('now', wp_timezone());
        if (!in_array((int) $now->format('N'), $h['days'], true)) {
            return false;
        }
        $t = $now->format('H:i');
        // Ca qua đêm (vd 20:00 → 02:00).
        return $h['start'] <= $h['end']
            ? ($t >= $h['start'] && $t < $h['end'])
            : ($t >= $h['start'] || $t < $h['end']);
    }

    // ── Đăng ký ───────────────────────────────────────────────────────────────

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('admin_init', [self::class, 'install']);
        add_action(ChatbotLog::CRON_HOOK, [self::class, 'purge']);
        add_action('admin_post_laca_chatbot_live_connect', [self::class, 'handleConnect']);
        add_filter('laca_rest_public_routes', static function (array $routes): array {
            foreach (['start', 'send', 'poll', 'end', 'telegram'] as $r) {
                $routes[] = '/laca/v1/chatbot/live/' . $r;
            }
            return $routes;
        });
    }

    public static function routes(): void
    {
        $session = [
            'required'          => true,
            'sanitize_callback' => static fn($v) => substr(preg_replace('/[^a-f0-9]/', '', strtolower((string) $v)), 0, 32),
            'validate_callback' => static fn($v) => (bool) preg_match('/^[a-f0-9]{32}$/i', (string) $v),
        ];
        $routes = [
            'start' => 'start',
            'send'  => 'send',
            'poll'  => 'poll',
            'end'   => 'end',
        ];
        foreach ($routes as $path => $cb) {
            register_rest_route(self::NS, '/chatbot/live/' . $path, [
                'methods'             => 'POST',
                'callback'            => [self::class, $cb],
                'permission_callback' => '__return_true',
                'args'                => ['session' => $session],
            ]);
        }
        register_rest_route(self::NS, '/chatbot/live/telegram', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── DB ────────────────────────────────────────────────────────────────────

    private static function tSessions(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_chatbot_live';
    }

    private static function tMessages(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_chatbot_live_msg';
    }

    public static function install(): void
    {
        if (get_option(self::DB_VERSION_KEY) === self::DB_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta('CREATE TABLE ' . self::tSessions() . " (
            session     CHAR(32) NOT NULL,
            thread_id   BIGINT(20) NOT NULL DEFAULT 0,
            page_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            lang        VARCHAR(10) NOT NULL DEFAULT '',
            status      VARCHAR(10) NOT NULL DEFAULT 'open',
            created_at  DATETIME NOT NULL,
            admin_at    DATETIME NULL DEFAULT NULL,
            updated_at  DATETIME NOT NULL,
            PRIMARY KEY  (session),
            KEY thread_id (thread_id),
            KEY created_at (created_at)
        ) {$charset};");
        dbDelta('CREATE TABLE ' . self::tMessages() . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session     CHAR(32) NOT NULL,
            sender      VARCHAR(10) NOT NULL DEFAULT 'user',
            name        VARCHAR(60) NOT NULL DEFAULT '',
            body        TEXT NOT NULL,
            created_at  DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session, id)
        ) {$charset};");
        update_option(self::DB_VERSION_KEY, self::DB_VERSION, false);
    }

    private static function getSession(string $session): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::tSessions() . ' WHERE session = %s', $session), ARRAY_A);
        return $row ?: null;
    }

    private static function addMessage(string $session, string $sender, string $name, string $body): int
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->insert(self::tMessages(), [
            'session'    => $session,
            'sender'     => $sender,
            'name'       => mb_substr($name, 0, 60),
            'body'       => mb_substr($body, 0, 4000),
            'created_at' => $now,
        ]);
        $wpdb->update(self::tSessions(), ['updated_at' => $now], ['session' => $session]);
        return (int) $wpdb->insert_id;
    }

    private static function close(string $session): void
    {
        global $wpdb;
        $wpdb->update(self::tSessions(), ['status' => 'closed', 'updated_at' => gmdate('Y-m-d H:i:s')], ['session' => $session]);
    }

    /** Xoá phiên + tin cũ hơn số ngày giữ nhật ký chatbot. */
    public static function purge(): void
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::tSessions())) !== self::tSessions()) {
            return;
        }
        $cut = gmdate('Y-m-d H:i:s', time() - ChatbotLog::days() * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            'DELETE m FROM ' . self::tMessages() . ' m JOIN ' . self::tSessions() . ' s ON s.session = m.session WHERE s.updated_at < %s',
            $cut
        ));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::tSessions() . ' WHERE updated_at < %s', $cut));
    }

    // ── REST: phía khách ──────────────────────────────────────────────────────

    public static function start(\WP_REST_Request $r): \WP_REST_Response|\WP_Error
    {
        if (!self::enabled()) {
            return new \WP_Error('live_disabled', __('Chat trực tiếp đang tắt.', 'laca'), ['status' => 503]);
        }
        self::install();
        $session = (string) $r->get_param('session');

        $existing = self::getSession($session);
        if ($existing && $existing['status'] === 'open') {
            return new \WP_REST_Response(['available' => true, 'status' => $existing['admin_at'] ? 'live' : 'waiting'], 200);
        }

        if (!self::isOpenNow()) {
            $h = self::hours();
            return new \WP_REST_Response([
                'available' => false,
                'message'   => sprintf(
                    /* translators: 1: giờ mở, 2: giờ đóng */
                    __('Hiện đang ngoài giờ làm việc (%1$s–%2$s). Bạn để lại thông tin, chúng tôi sẽ liên hệ lại sớm nhé!', 'laca'),
                    $h['start'],
                    $h['end']
                ),
            ], 200);
        }

        // Chỉ tính lượt khi tạo được chủ đề (lần lỗi không trừ lượt); admin thử không giới hạn.
        $isAdmin = current_user_can('manage_options');
        if (!$isAdmin && self::rateLimited('start', 4, 10 * MINUTE_IN_SECONDS, false)) {
            return new \WP_Error('rate_limited', __('Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.', 'laca'), ['status' => 429]);
        }

        $pageId = absint($r->get_param('page_id'));
        $lang   = sanitize_key((string) $r->get_param('lang'));
        $short  = substr($session, 0, 4);
        $title  = $pageId ? wp_strip_all_tags(get_the_title($pageId)) : '';
        [$guest, $contact] = self::guestIdentity($r);
        $label  = $guest !== '' ? $guest : '#' . $short;
        $topicName = mb_substr('💬 ' . $label . ($title !== '' ? ' · ' . $title : ''), 0, 120);

        $topic = self::tg('createForumTopic', ['chat_id' => self::chatId(), 'name' => $topicName]);
        if (is_wp_error($topic) || empty($topic['message_thread_id'])) {
            $why = is_wp_error($topic) ? $topic->get_error_message() : 'no thread id';
            self::rememberError('createForumTopic', $why);
            return new \WP_Error('live_failed', self::forAdmin(__('Chưa kết nối được nhân viên. Bạn để lại thông tin, chúng tôi sẽ liên hệ lại sớm nhé!', 'laca'), $why), ['status' => 502]);
        }
        $threadId = (int) $topic['message_thread_id'];
        if (!$isAdmin) {
            self::rateLimited('start', 4, 10 * MINUTE_IN_SECONDS, true);
        }

        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->replace(self::tSessions(), [
            'session'    => $session,
            'thread_id'  => $threadId,
            'page_id'    => $pageId,
            'lang'       => $lang,
            'status'     => 'open',
            'created_at' => $now,
            'admin_at'   => null,
            'updated_at' => $now,
        ]);

        $intro = '🆕 Khách ' . ($guest !== '' ? $guest . ' (#' . $short . ')' : '#' . $short) . ' cần hỗ trợ';
        if ($contact !== '') {
            $intro .= "\nLiên hệ: " . $contact;
        }
        if ($pageId) {
            $intro .= "\nĐang xem: " . get_permalink($pageId);
        }
        if ($lang !== '') {
            $intro .= "\nNgôn ngữ: " . $lang;
        }
        $transcript = self::transcript($r->get_param('history'));
        if ($transcript !== '') {
            $intro .= "\n\nĐã hỏi chatbot:\n" . $transcript;
        }
        $intro .= "\n\n↩️ Trả lời ngay trong chủ đề này. Gõ /end để kết thúc.";
        self::tg('sendMessage', ['chat_id' => self::chatId(), 'message_thread_id' => $threadId, 'text' => $intro]);

        return new \WP_REST_Response(['available' => true, 'status' => 'waiting'], 200);
    }

    public static function send(\WP_REST_Request $r): \WP_REST_Response|\WP_Error
    {
        $session = (string) $r->get_param('session');
        $row     = self::enabled() ? self::getSession($session) : null;
        if (!$row || $row['status'] !== 'open') {
            return new \WP_Error('live_closed', __('Cuộc chat với nhân viên đã kết thúc.', 'laca'), ['status' => 410]);
        }
        $text = trim(sanitize_textarea_field((string) $r->get_param('message')));
        if ($text === '') {
            return new \WP_Error('empty', __('Vui lòng nhập tin nhắn.', 'laca'), ['status' => 422]);
        }
        if (self::rateLimited('send_' . $session, 30, 10 * MINUTE_IN_SECONDS)) {
            return new \WP_Error('rate_limited', __('Bạn đã gửi quá nhiều tin nhắn. Vui lòng thử lại sau ít phút.', 'laca'), ['status' => 429]);
        }
        $text = mb_substr($text, 0, self::MAX_LEN);

        $sent = self::tg('sendMessage', [
            'chat_id'           => self::chatId(),
            'message_thread_id' => (int) $row['thread_id'],
            'text'              => '👤 ' . $text,
        ]);
        if (is_wp_error($sent)) {
            // Topic bị xoá/đóng phía Telegram → kết thúc phiên thay vì im lặng mất tin.
            if (stripos($sent->get_error_message(), 'thread') !== false || stripos($sent->get_error_message(), 'topic') !== false) {
                self::close($session);
                return new \WP_Error('live_closed', __('Cuộc chat với nhân viên đã kết thúc.', 'laca'), ['status' => 410]);
            }
            self::rememberError('sendMessage', $sent->get_error_message());
            return new \WP_Error('live_failed', self::forAdmin(__('Chưa gửi được tin nhắn. Vui lòng thử lại.', 'laca'), $sent->get_error_message()), ['status' => 502]);
        }

        return new \WP_REST_Response(['ok' => true, 'id' => self::addMessage($session, 'user', '', $text)], 200);
    }

    public static function poll(\WP_REST_Request $r): \WP_REST_Response
    {
        $session = (string) $r->get_param('session');
        $after   = absint($r->get_param('after'));
        $row     = self::getSession($session);
        if (!$row) {
            return self::noCache(new \WP_REST_Response(['status' => 'none', 'messages' => []], 200));
        }

        global $wpdb;
        $msgs = (array) $wpdb->get_results($wpdb->prepare(
            'SELECT id, sender, name, body FROM ' . self::tMessages() . ' WHERE session = %s AND id > %d AND sender <> %s ORDER BY id ASC LIMIT 50',
            $session,
            $after,
            'user'
        ), ARRAY_A);

        $status  = $row['status'] === 'closed' ? 'closed' : ($row['admin_at'] ? 'live' : 'waiting');
        $waited  = time() - (int) strtotime($row['created_at'] . ' UTC');
        return self::noCache(new \WP_REST_Response([
            'status'   => $status,
            'no_reply' => $status === 'waiting' && $waited >= self::waitMinutes() * MINUTE_IN_SECONDS,
            'messages' => array_map(static fn($m) => [
                'id'     => (int) $m['id'],
                'sender' => $m['sender'],
                // Khách thấy tên chung do admin đặt, không lộ tên Telegram của nhân viên.
                'name'   => $m['sender'] === 'admin' ? self::agentName() : '',
                'text'   => $m['body'],
            ], $msgs),
        ], 200));
    }

    public static function end(\WP_REST_Request $r): \WP_REST_Response
    {
        $session = (string) $r->get_param('session');
        $row     = self::getSession($session);
        if ($row && $row['status'] === 'open') {
            self::close($session);
            if (self::token() !== '' && self::chatId() !== '') {
                self::tg('sendMessage', ['chat_id' => self::chatId(), 'message_thread_id' => (int) $row['thread_id'], 'text' => '🔚 Khách đã kết thúc cuộc chat.']);
                self::tg('closeForumTopic', ['chat_id' => self::chatId(), 'message_thread_id' => (int) $row['thread_id']]);
            }
        }
        return new \WP_REST_Response(['ok' => true], 200);
    }

    // ── REST: webhook Telegram ────────────────────────────────────────────────

    public static function webhook(\WP_REST_Request $r): \WP_REST_Response
    {
        $given = (string) $r->get_header('x_telegram_bot_api_secret_token');
        if ($given === '' || !hash_equals(self::secret(), $given)) {
            return new \WP_REST_Response(['ok' => false], 403);
        }

        $msg = (array) (($r->get_json_params() ?: [])['message'] ?? []);
        if (!$msg || !empty($msg['from']['is_bot'])) {
            return new \WP_REST_Response(['ok' => true], 200);
        }
        $chatId = (string) ($msg['chat']['id'] ?? '');
        $text   = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));

        // Hỗ trợ cài đặt: gõ /chatid trong nhóm → bot trả Chat ID để dán vào admin.
        if (preg_match('#^/chatid(@\w+)?$#i', $text)) {
            self::tg('sendMessage', array_filter([
                'chat_id'           => $chatId,
                'message_thread_id' => $msg['message_thread_id'] ?? null,
                'text'              => 'Chat ID: ' . $chatId,
            ]));
            return new \WP_REST_Response(['ok' => true], 200);
        }

        if ($chatId !== self::chatId() || empty($msg['message_thread_id'])) {
            return new \WP_REST_Response(['ok' => true], 200);
        }

        global $wpdb;
        $threadId = (int) $msg['message_thread_id'];
        $row      = $wpdb->get_row($wpdb->prepare(
            'SELECT session, status, admin_at FROM ' . self::tSessions() . ' WHERE thread_id = %d ORDER BY created_at DESC LIMIT 1',
            $threadId
        ), ARRAY_A);
        if (!$row) {
            return new \WP_REST_Response(['ok' => true], 200);
        }
        $reply = static fn(string $t) => self::tg('sendMessage', ['chat_id' => $chatId, 'message_thread_id' => $threadId, 'text' => $t]);

        if ($row['status'] !== 'open') {
            $reply('ℹ️ Phiên chat này đã kết thúc, khách không còn nhận được tin.');
            return new \WP_REST_Response(['ok' => true], 200);
        }

        if (preg_match('#^/end(@\w+)?$#i', $text)) {
            self::close($row['session']);
            self::addMessage($row['session'], 'system', '', 'ended');
            $reply('🔚 Đã kết thúc cuộc chat.');
            self::tg('closeForumTopic', ['chat_id' => $chatId, 'message_thread_id' => $threadId]);
            return new \WP_REST_Response(['ok' => true], 200);
        }

        if ($text === '') {
            $reply('ℹ️ Hiện chỉ gửi được tin nhắn chữ tới khách.');
            return new \WP_REST_Response(['ok' => true], 200);
        }

        if (!$row['admin_at']) {
            $wpdb->update(self::tSessions(), ['admin_at' => gmdate('Y-m-d H:i:s')], ['session' => $row['session']]);
        }
        $name = trim((string) ($msg['from']['first_name'] ?? ''));
        self::addMessage($row['session'], 'admin', $name, mb_substr($text, 0, 4000));

        return new \WP_REST_Response(['ok' => true], 200);
    }

    /** Tên hiển thị cho khách thay tên Telegram của nhân viên. */
    public static function agentName(): string
    {
        $name = trim((string) get_option('laca_chatbot_live_agent_name', ''));
        return $name !== '' ? $name : __('Nhân viên tư vấn', 'laca');
    }

    /**
     * Tên + liên hệ của khách: tài khoản đang đăng nhập trước, sau đó tới tên
     * khách tự nhập / form "Để lại thông tin" trong phiên (trình duyệt gửi kèm).
     *
     * @return array{0:string,1:string}
     */
    private static function guestIdentity(\WP_REST_Request $r): array
    {
        $name    = mb_substr(trim(sanitize_text_field((string) $r->get_param('name'))), 0, 60);
        $contact = mb_substr(trim(sanitize_text_field((string) $r->get_param('contact'))), 0, 120);
        $user    = wp_get_current_user();
        if ($user && $user->exists()) {
            $name    = $name !== '' ? $name : trim($user->display_name);
            $contact = $contact !== '' ? $contact : (string) $user->user_email;
            if (function_exists('get_user_meta')) {
                $phone = trim((string) get_user_meta($user->ID, 'billing_phone', true));
                if ($phone !== '' && strpos($contact, $phone) === false) {
                    $contact .= ($contact !== '' ? ' · ' : '') . $phone;
                }
            }
        }
        return [$name, $contact];
    }

    // ── Telegram API ──────────────────────────────────────────────────────────

    /** @return array|true|\WP_Error Kết quả "result" của Telegram. */
    private static function tg(string $method, array $params)
    {
        $token = self::token();
        if ($token === '') {
            return new \WP_Error('no_token', 'Telegram bot token chưa cấu hình.');
        }
        $res = wp_remote_post('https://api.telegram.org/bot' . $token . '/' . $method, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($params),
            'timeout' => 10,
        ]);
        if (is_wp_error($res)) {
            return $res;
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($data['ok'])) {
            return new \WP_Error('telegram', (string) ($data['description'] ?? ('HTTP ' . wp_remote_retrieve_response_code($res))));
        }
        return $data['result'] ?? true;
    }

    // ── Admin: cài đặt + kết nối ──────────────────────────────────────────────

    /** Các dòng cài đặt chèn vào form của trang Chatbot. */
    public static function renderSettingsRows(): void
    {
        $h        = self::hours();
        $dayNames = [1 => __('T2', 'laca'), 2 => __('T3', 'laca'), 3 => __('T4', 'laca'), 4 => __('T5', 'laca'), 5 => __('T6', 'laca'), 6 => __('T7', 'laca'), 7 => __('CN', 'laca')];
        ?>
        <tr>
            <th><?php esc_html_e('Chat với nhân viên (Telegram)', 'laca'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="laca_chatbot_live_enabled" value="1" <?php checked(get_option('laca_chatbot_live_enabled', '0'), '1'); ?>>
                    <?php esc_html_e('Cho khách chuyển sang nhắn trực tiếp với nhân viên qua nhóm Telegram', 'laca'); ?>
                </label>
                <p>
                    <label for="laca-live-token"><?php esc_html_e('Bot Token', 'laca'); ?></label><br>
                    <input type="password" id="laca-live-token" name="laca_chatbot_live_token" class="regular-text" autocomplete="off" value="<?php echo esc_attr((string) get_option('laca_chatbot_live_token', '')); ?>" placeholder="123456:ABC…">
                </p>
                <p>
                    <label for="laca-live-chat"><?php esc_html_e('Chat ID của nhóm', 'laca'); ?></label><br>
                    <input type="text" id="laca-live-chat" name="laca_chatbot_live_chat_id" class="regular-text" value="<?php echo esc_attr(self::chatId()); ?>" placeholder="-100…">
                </p>
                <p>
                    <label for="laca-live-start"><?php esc_html_e('Giờ làm việc', 'laca'); ?></label>
                    <input type="time" id="laca-live-start" name="laca_chatbot_live_start" value="<?php echo esc_attr($h['start']); ?>">
                    –
                    <input type="time" name="laca_chatbot_live_end" aria-label="<?php esc_attr_e('Giờ kết thúc', 'laca'); ?>" value="<?php echo esc_attr($h['end']); ?>">
                </p>
                <p>
                    <?php foreach ($dayNames as $n => $label): ?>
                        <label><input type="checkbox" name="laca_chatbot_live_days[]" value="<?php echo esc_attr((string) $n); ?>" <?php checked(in_array($n, $h['days'], true)); ?>> <?php echo esc_html($label); ?></label>&nbsp;
                    <?php endforeach; ?>
                </p>
                <p>
                    <label for="laca-live-agent"><?php esc_html_e('Tên nhân viên hiển thị cho khách', 'laca'); ?></label><br>
                    <input type="text" id="laca-live-agent" name="laca_chatbot_live_agent_name" class="regular-text" maxlength="60" value="<?php echo esc_attr((string) get_option('laca_chatbot_live_agent_name', '')); ?>" placeholder="<?php esc_attr_e('Nhân viên tư vấn', 'laca'); ?>">
                </p>
                <p>
                    <label for="laca-live-wait"><?php esc_html_e('Chờ nhân viên tối đa', 'laca'); ?></label>
                    <input type="number" id="laca-live-wait" name="laca_chatbot_live_wait" min="1" max="60" class="small-text" value="<?php echo esc_attr((string) self::waitMinutes()); ?>">
                    <?php esc_html_e('phút — quá thời gian chưa ai trả lời thì mời khách để lại thông tin.', 'laca'); ?>
                </p>
                <p class="description">
                    <?php esc_html_e('Cách cài:', 'laca'); ?><br>
                    1. <?php esc_html_e('Tạo bot bằng @BotFather, dán Bot Token ở trên (để trống = dùng bot ở mục Thông báo → Telegram).', 'laca'); ?><br>
                    2. <?php esc_html_e('Tạo nhóm Telegram, bật "Chủ đề" (Topics), thêm bot làm quản trị viên có quyền "Quản lý chủ đề".', 'laca'); ?><br>
                    3. <?php esc_html_e('Gửi 1 tin bất kỳ trong nhóm, rồi Lưu cài đặt và bấm "Kết nối Telegram" bên phải — hệ thống tự tìm Chat ID nhóm và kiểm tra quyền của bot (hoặc gõ /chatid trong nhóm để bot trả Chat ID).', 'laca'); ?><br>
                    4. <?php esc_html_e('Mỗi khách là 1 chủ đề trong nhóm: trả lời ngay trong chủ đề, gõ /end để kết thúc.', 'laca'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    public static function saveSettings(array $post): void
    {
        update_option('laca_chatbot_live_enabled', isset($post['laca_chatbot_live_enabled']) ? '1' : '0');
        update_option('laca_chatbot_live_token', preg_replace('/[^A-Za-z0-9:_-]/', '', (string) wp_unslash($post['laca_chatbot_live_token'] ?? '')), false);
        update_option('laca_chatbot_live_chat_id', preg_replace('/[^0-9-]/', '', (string) wp_unslash($post['laca_chatbot_live_chat_id'] ?? '')));
        update_option('laca_chatbot_live_start', self::cleanTime((string) wp_unslash($post['laca_chatbot_live_start'] ?? ''), '08:00'));
        update_option('laca_chatbot_live_end', self::cleanTime((string) wp_unslash($post['laca_chatbot_live_end'] ?? ''), '21:00'));
        $days = array_values(array_unique(array_filter(array_map('absint', (array) ($post['laca_chatbot_live_days'] ?? [])), static fn($d) => $d >= 1 && $d <= 7)));
        update_option('laca_chatbot_live_days', implode(',', $days));
        update_option('laca_chatbot_live_agent_name', mb_substr(sanitize_text_field(wp_unslash($post['laca_chatbot_live_agent_name'] ?? '')), 0, 60));
        update_option('laca_chatbot_live_wait', max(1, min(60, absint($post['laca_chatbot_live_wait'] ?? 3))));
    }

    /** Nút "Kết nối Telegram" (cột phải trang Chatbot). */
    public static function renderConnectBox(string $nonceAction): void
    {
        $result = get_transient('laca_cbot_live_conn_' . get_current_user_id());
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:12px">
            <?php wp_nonce_field($nonceAction, '_nonce'); ?>
            <input type="hidden" name="action" value="laca_chatbot_live_connect">
            <button type="submit" class="button"><?php esc_html_e('Kết nối Telegram', 'laca'); ?></button>
        </form>
        <?php $last = get_option('laca_chatbot_live_last_error'); ?>
        <?php if (is_array($last) && (time() - (int) $last['time']) < 3 * DAY_IN_SECONDS): ?>
            <div class="notice notice-warning inline"><p><?php
                /* translators: 1: thời điểm, 2: thao tác Telegram, 3: lỗi */
                echo esc_html(sprintf(__('Lỗi Telegram gần nhất (%1$s, %2$s): %3$s', 'laca'), wp_date('d/m H:i', (int) $last['time']), (string) $last['method'], self::explain((string) $last['error'])));
            ?></p></div>
        <?php endif; ?>
        <?php if (is_array($result)): ?>
            <div class="notice <?php echo esc_attr($result['ok'] ? 'notice-success' : 'notice-error'); ?> inline">
                <?php foreach ((array) ($result['lines'] ?? [$result['msg'] ?? '']) as $line): ?>
                    <p><?php echo esc_html((string) $line); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }

    /**
     * "Kết nối Telegram": kiểm tra token, tự tìm Chat ID nhóm (nếu chưa nhập),
     * đặt webhook, kiểm tra nhóm có Chủ đề + bot có quyền Quản lý chủ đề, và
     * báo lỗi gần nhất Telegram gặp khi gửi tin về site.
     */
    public static function handleConnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(FrontendChatbotHandler::NONCE, '_nonce');

        $out = ['ok' => false, 'lines' => []];
        $say = static function (string $line) use (&$out): void {
            $out['lines'][] = $line;
        };

        $me = self::token() !== '' ? self::tg('getMe', []) : new \WP_Error('no_token', '');
        if (is_wp_error($me)) {
            $say(self::token() === ''
                ? __('Chưa có Bot Token — dán token BotFather gửi vào ô "Bot Token" rồi lưu.', 'laca')
                : sprintf(__('Bot Token không đúng: %s', 'laca'), $me->get_error_message()));
            self::finishConnect($out);
        }
        $botId = (int) $me['id'];
        $say(sprintf(
            /* translators: %s: username của bot */
            __('Bot đang dùng: @%s', 'laca') . (trim((string) get_option('laca_chatbot_live_token', '')) === '' ? ' ' . __('(lấy từ mục Thông báo → Telegram)', 'laca') : ''),
            (string) ($me['username'] ?? '')
        ));

        // Lỗi gần nhất khi Telegram gửi tin về site (trước khi đặt lại webhook).
        $info = self::tg('getWebhookInfo', []);
        if (is_array($info) && !empty($info['last_error_message']) && (time() - (int) ($info['last_error_date'] ?? 0)) < DAY_IN_SECONDS) {
            $say(sprintf(__('Lỗi gần nhất Telegram gặp khi gửi tin về site: %s', 'laca'), (string) $info['last_error_message']));
        }

        // Chưa có Chat ID → đọc tin mới nhất bot nhận được để tìm nhóm.
        if (self::chatId() === '') {
            self::tg('deleteWebhook', []);
            $found = self::findGroup((array) self::tg('getUpdates', ['limit' => 100, 'allowed_updates' => ['message', 'my_chat_member']]));
            if ($found) {
                update_option('laca_chatbot_live_chat_id', $found['id']);
                $say(sprintf(__('Đã tự tìm thấy nhóm "%1$s" — Chat ID %2$s (đã lưu).', 'laca'), $found['title'], $found['id']));
            }
        }

        $hook = self::tg('setWebhook', [
            'url'                  => rest_url('laca/v1/chatbot/live/telegram'),
            'secret_token'         => self::secret(),
            'allowed_updates'      => ['message'],
            'drop_pending_updates' => true,
        ]);
        if (is_wp_error($hook)) {
            $say(sprintf(__('Không đặt được webhook: %s', 'laca'), $hook->get_error_message()));
            self::finishConnect($out);
        }
        $say(__('Đã đặt webhook — Telegram sẽ gửi tin trong nhóm về website.', 'laca'));

        if (self::chatId() === '') {
            $say(__('Chưa tìm thấy nhóm: hãy chắc bot đã ở trong nhóm, gửi 1 tin bất kỳ trong nhóm rồi bấm "Kết nối Telegram" lần nữa.', 'laca'));
            self::finishConnect($out);
        }

        $chat = self::tg('getChat', ['chat_id' => self::chatId()]);
        if (is_wp_error($chat)) {
            $say(sprintf(__('Không đọc được nhóm (Chat ID %1$s): %2$s', 'laca'), self::chatId(), $chat->get_error_message()));
            self::finishConnect($out);
        }
        $title = (string) ($chat['title'] ?? '');
        if (empty($chat['is_forum'])) {
            $say(sprintf(__('Nhóm "%s" chưa bật Chủ đề (Topics).', 'laca'), $title));
            self::finishConnect($out);
        }
        $member = self::tg('getChatMember', ['chat_id' => self::chatId(), 'user_id' => $botId]);
        $isAdmin = is_array($member) && ($member['status'] ?? '') === 'administrator';
        if (!$isAdmin || empty($member['can_manage_topics'])) {
            $say(sprintf(__('Bot chưa là quản trị viên có quyền "Quản lý chủ đề" (Manage Topics) trong nhóm "%s".', 'laca'), $title));
            self::finishConnect($out);
        }

        $out['ok'] = true;
        delete_option('laca_chatbot_live_last_error');
        $say(sprintf(__('Sẵn sàng: nhóm "%s". Thử mở chatbot ngoài trang và bấm "Chat với nhân viên".', 'laca'), $title));
        self::finishConnect($out);
    }

    /** Nhóm (ưu tiên nhóm có Chủ đề) xuất hiện gần nhất trong các update. */
    private static function findGroup(array $updates): ?array
    {
        $best = null;
        foreach ($updates as $u) {
            $chat = $u['message']['chat'] ?? ($u['my_chat_member']['chat'] ?? null);
            if (!is_array($chat) || !in_array($chat['type'] ?? '', ['group', 'supergroup'], true)) {
                continue;
            }
            // Nhóm thường lên supergroup (khi bật Chủ đề) thì đổi Chat ID.
            $id = (string) ($u['message']['migrate_to_chat_id'] ?? $chat['id']);
            $cand = ['id' => $id, 'title' => (string) ($chat['title'] ?? ''), 'forum' => !empty($chat['is_forum'])];
            if ($best === null || $cand['forum'] || !$best['forum']) {
                $best = $cand;
            }
        }
        return $best;
    }

    private static function finishConnect(array $out): never
    {
        set_transient('laca_cbot_live_conn_' . get_current_user_id(), $out, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=' . FrontendChatbotHandler::MENU_SLUG));
        exit;
    }

    /** Lưu lỗi Telegram gần nhất để admin thấy ở trang cài đặt (không lưu token). */
    private static function rememberError(string $method, string $error): void
    {
        error_log('[ChatbotLive] ' . $method . ': ' . $error);
        update_option('laca_chatbot_live_last_error', ['time' => time(), 'method' => $method, 'error' => mb_substr($error, 0, 300)], false);
    }

    /** Admin đang đăng nhập thử chat → kèm lý do thật; khách chỉ thấy câu thân thiện. */
    private static function forAdmin(string $message, string $error): string
    {
        return current_user_can('manage_options') ? $message . ' [Admin: ' . self::explain($error) . ']' : $message;
    }

    /** Diễn giải các lỗi Telegram hay gặp khi cài đặt. */
    private static function explain(string $error): string
    {
        $map = [
            'not enough rights'      => __('bot chưa có quyền "Quản lý chủ đề" (Manage Topics) — vào Administrators, chọn bot, bật quyền này.', 'laca'),
            'chat is not a forum'    => __('nhóm chưa bật Chủ đề (Topics).', 'laca'),
            'chat not found'         => __('sai Chat ID hoặc bot chưa ở trong nhóm.', 'laca'),
            'bot was kicked'         => __('bot đã bị xoá khỏi nhóm.', 'laca'),
            'unauthorized'           => __('Bot Token sai hoặc đã bị thu hồi.', 'laca'),
            'group chat was upgraded' => __('nhóm đã đổi Chat ID — bấm "Kết nối Telegram" để cập nhật.', 'laca'),
        ];
        foreach ($map as $needle => $text) {
            if (stripos($error, $needle) !== false) {
                return $text . ' (' . $error . ')';
            }
        }
        return $error;
    }

    // ── Tiện ích ──────────────────────────────────────────────────────────────

    private static function transcript($history): string
    {
        if (!is_array($history)) {
            return '';
        }
        $lines = [];
        foreach (array_slice($history, -6) as $h) {
            if (!is_array($h)) {
                continue;
            }
            $who     = ($h['role'] ?? '') === 'assistant' ? '🤖' : '👤';
            $content = mb_substr(trim(sanitize_textarea_field((string) ($h['content'] ?? ''))), 0, 300);
            if ($content !== '') {
                $lines[] = "{$who} {$content}";
            }
        }
        return implode("\n", $lines);
    }

    /** @param bool $count false = chỉ kiểm tra, không cộng lượt. */
    private static function rateLimited(string $bucket, int $max, int $ttl, bool $count = true): bool
    {
        $ip  = class_exists(\App\Helpers\ClientIp::class) ? \App\Helpers\ClientIp::get() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'laca_cbot_live_' . substr(hash('sha256', $bucket . '|' . $ip), 0, 30);
        $n   = (int) get_transient($key);
        if ($n >= $max) {
            return true;
        }
        if ($count) {
            set_transient($key, $n + 1, $ttl);
        }
        return false;
    }

    private static function noCache(\WP_REST_Response $res): \WP_REST_Response
    {
        $res->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $res->header('X-LiteSpeed-Cache-Control', 'no-cache');
        return $res;
    }
}
