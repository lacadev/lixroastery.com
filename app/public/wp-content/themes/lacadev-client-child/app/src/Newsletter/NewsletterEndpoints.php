<?php

/**
 * Link trong email Newsletter + webhook từ Mailchimp/Brevo.
 *
 * - `/?lix_newsletter=confirm&id=..&token=..`     → chỉ còn để link trong thư
 *   xác nhận CŨ (bản double opt-in đã bỏ) không bị lỗi — hiện trang cảm ơn.
 * - `/?lix_newsletter=unsubscribe&id=..&token=..` → GET hiện nút xác nhận,
 *   POST mới huỷ thật. Không huỷ ngay khi GET vì bộ quét link của Outlook/
 *   Gmail/antivirus tự "mở" mọi link trong thư — sẽ huỷ nhầm người dùng.
 *   POST `List-Unsubscribe=One-Click` (RFC 8058, nút "Huỷ đăng ký" của Gmail)
 *   cũng đi vào đây.
 * - `admin-post.php?action=lix_newsletter_webhook&provider=..&key=..` → nhận
 *   sự kiện huỷ từ Mailchimp/Brevo. Dùng admin-post thay vì REST API vì site
 *   có tuỳ chọn "Disable REST API" (Laca Admin) chặn REST cho khách.
 *
 * Token = HMAC(id + email + action) với salt của site: không cần lưu DB,
 * không đoán được để huỷ hộ người khác.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsletterEndpoints
{
    private const ACTIONS = ['confirm', 'unsubscribe'];
    private const WEBHOOK_KEY_OPTION = 'lix_newsletter_webhook_key';

    /** Đang render trang xác nhận/huỷ — footer ẩn form đăng ký cho đỡ lạc lõng. */
    public static bool $rendering = false;

    public function __construct()
    {
        add_action('template_redirect', [$this, 'handleRequest'], 1);
        add_action('admin_post_nopriv_lix_newsletter_webhook', [$this, 'handleWebhook']);
        add_action('admin_post_lix_newsletter_webhook', [$this, 'handleWebhook']);
    }

    public static function token(string $action, array $subscriber): string
    {
        return substr(hash_hmac('sha256', $action . '|' . $subscriber['id'] . '|' . $subscriber['email'], wp_salt('nonce')), 0, 32);
    }

    public static function url(string $action, array $subscriber): string
    {
        $lang = (string) $subscriber['lang'];
        // Trang chủ đúng ngôn ngữ của khách để header/footer hiện đúng ngôn ngữ.
        $base = ($lang !== '' && function_exists('pll_home_url')) ? pll_home_url($lang) : home_url('/');

        return add_query_arg([
            'lix_newsletter' => $action,
            'id' => (int) $subscriber['id'],
            'token' => self::token($action, $subscriber),
        ], $base);
    }

    public static function webhookUrl(string $provider): string
    {
        $key = (string) get_option(self::WEBHOOK_KEY_OPTION);
        if ($key === '') {
            $key = wp_generate_password(32, false);
            update_option(self::WEBHOOK_KEY_OPTION, $key, false);
        }

        return add_query_arg([
            'action' => 'lix_newsletter_webhook',
            'provider' => $provider,
            'key' => $key,
        ], admin_url('admin-post.php'));
    }

    public function handleRequest(): void
    {
        if (!isset($_GET['lix_newsletter'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_GET['lix_newsletter']));
        if (!in_array($action, self::ACTIONS, true)) {
            return;
        }

        // Trang có query string riêng từng người — tuyệt đối không cho
        // LiteSpeed Cache lưu lại.
        nocache_headers();
        do_action('litespeed_control_set_nocache', 'newsletter link');

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $subscriber = $id ? NewsletterSubscribers::find($id) : null;

        if (!$subscriber || !hash_equals(self::token($action, $subscriber), $token)) {
            $this->render('invalid', function_exists('pll_current_language') ? (string) pll_current_language() : '', $id, $token);
        }

        $lang = (string) $subscriber['lang'];

        if ($action === 'confirm') {
            // Link xác nhận cũ không được đăng ký lại email đã huỷ (bấm nhầm thư
            // cũ / bộ quét link tự mở) — muốn quay lại thì nhập lại form.
            $this->render($subscriber['status'] === NewsletterSubscribers::STATUS_UNSUBSCRIBED ? 'unsubscribed' : 'confirmed', $lang, $id, $token);
        }

        $is_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
        if (!$is_post) {
            $this->render($subscriber['status'] === NewsletterSubscribers::STATUS_UNSUBSCRIBED ? 'unsubscribed' : 'ask', $lang, $id, $token);
        }

        if ($subscriber['status'] !== NewsletterSubscribers::STATUS_UNSUBSCRIBED) {
            $subscriber = NewsletterSubscribers::setStatus($id, NewsletterSubscribers::STATUS_UNSUBSCRIBED);
            NewsletterSync::queue(NewsletterSubscribers::STATUS_UNSUBSCRIBED, $subscriber);
        }

        // Huỷ 1 chạm từ trình đọc mail: chỉ cần trả 200, không cần trang HTML.
        if (isset($_POST['List-Unsubscribe']) && $_POST['List-Unsubscribe'] === 'One-Click') {
            status_header(200);
            exit;
        }

        $this->render('unsubscribed', $lang, $id, $token);
    }

    /**
     * Mailchimp: Audience → Settings → Webhooks (gửi form-urlencoded, GET để
     * kiểm tra URL khi tạo). Brevo: Contacts → Settings → Webhooks (JSON).
     */
    public function handleWebhook(): void
    {
        nocache_headers();

        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $expected = (string) get_option(self::WEBHOOK_KEY_OPTION);
        if ($expected === '' || !hash_equals($expected, $key)) {
            status_header(403);
            exit;
        }

        $provider = isset($_GET['provider']) ? sanitize_key(wp_unslash($_GET['provider'])) : '';
        $is_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

        $email = '';
        if ($is_post && $provider === 'mailchimp') {
            $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
            // 'cleaned' = Mailchimp tự gỡ email chết (bounce) — coi như huỷ.
            if (in_array($type, ['unsubscribe', 'cleaned'], true) && isset($_POST['data']['email'])) {
                $email = sanitize_email(wp_unslash($_POST['data']['email']));
            }
        } elseif ($is_post && $provider === 'brevo') {
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($payload) && ($payload['event'] ?? '') === 'unsubscribed' && !empty($payload['email'])) {
                $email = sanitize_email((string) $payload['email']);
            }
        }

        if ($email !== '') {
            $subscriber = NewsletterSubscribers::findByEmail(strtolower($email));
            if ($subscriber && $subscriber['status'] !== NewsletterSubscribers::STATUS_UNSUBSCRIBED) {
                $subscriber = NewsletterSubscribers::setStatus((int) $subscriber['id'], NewsletterSubscribers::STATUS_UNSUBSCRIBED);
                // Nguồn huỷ chính là provider này — không gọi ngược lại nó.
                NewsletterSync::queue(NewsletterSubscribers::STATUS_UNSUBSCRIBED, $subscriber, $provider);
            }
        }

        // Luôn trả 200 cho request hợp lệ (kể cả sự kiện không quan tâm) để
        // Mailchimp/Brevo không tự tắt webhook vì "lỗi liên tục".
        status_header(200);
        echo 'ok';
        exit;
    }

    /**
     * @return never
     */
    private function render(string $state, string $lang, int $id, string $token): void
    {
        self::$rendering = true;
        status_header($state === 'invalid' ? 400 : 200);
        add_filter('wp_robots', 'wp_robots_no_robots');

        $messages = [
            'confirmed' => NewsletterTexts::get('newsletter_msg_success', $lang),
            'ask' => NewsletterTexts::get('newsletter_page_unsubscribe', $lang),
            'unsubscribed' => NewsletterTexts::get('newsletter_page_unsubscribed', $lang),
            'invalid' => NewsletterTexts::get('newsletter_page_invalid', $lang),
        ];
        $home_url = ($lang !== '' && function_exists('pll_home_url')) ? pll_home_url($lang) : home_url('/');

        get_header();
        ?>
        <main id="main-content" class="newsletter-page">
            <div class="container-fluid">
                <div class="newsletter-page__box">
                    <?php // h2 vì link luôn trỏ về trang chủ — header.php đã in <h1> tên site ở trang chủ. ?>
                    <h2 class="newsletter-page__title">Newsletter</h2>
                    <p class="newsletter-page__text"><?php echo esc_html($messages[$state]); ?></p>

                    <?php if ($state === 'ask'): ?>
                        <form method="post" action="<?php echo esc_url(add_query_arg(['lix_newsletter' => 'unsubscribe', 'id' => $id, 'token' => $token], $home_url)); ?>">
                            <button type="submit" class="newsletter-page__btn"><?php echo esc_html(NewsletterTexts::get('newsletter_page_unsubscribe_button', $lang)); ?></button>
                        </form>
                    <?php endif; ?>

                    <a class="newsletter-page__home" href="<?php echo esc_url($home_url); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a>
                </div>
            </div>
        </main>
        <?php
        get_footer();
        exit;
    }
}

new NewsletterEndpoints();
