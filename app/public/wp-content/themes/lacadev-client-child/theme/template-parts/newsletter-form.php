<?php

/**
 * Form đăng ký Newsletter cố định ở footer (theme/footer.php).
 * Xử lý: NewsletterAjaxHandler; JS: resources/scripts/theme/components/newsletter-form.js.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

// Chưa từng lưu tab thì Carbon Fields trả default (true) → vẫn hiện.
if (!getOption('newsletter_enable')) {
    return;
}

$nl_text = static function (string $key, string $fallback): string {
    $value = getOption($key);
    return is_string($value) && trim($value) !== '' ? $value : $fallback;
};

$nl_title = $nl_text('newsletter_title', 'Connect with our mission - Join the sustainable circle');
$nl_placeholder = $nl_text('newsletter_placeholder', 'Your Email Address');
$nl_button = $nl_text('newsletter_button', 'Subscribe');
$nl_consent = (string) getOption('newsletter_consent');
$nl_privacy_url = (string) getOption('newsletter_privacy_url');

$nl_messages = [
    'subscribed' => $nl_text('newsletter_msg_success', 'Thank you for joining the circle!'),
    'exists' => $nl_text('newsletter_msg_exists', 'This email is already subscribed.'),
    'invalid' => $nl_text('newsletter_msg_invalid', 'Please enter a valid email address.'),
    'captcha' => $nl_text('newsletter_msg_captcha', 'We could not verify your request. Please reload the page and try again.'),
    'limit' => $nl_text('newsletter_msg_limit', 'Too many attempts. Please try again in a few minutes.'),
    'error' => $nl_text('newsletter_msg_error', 'Something went wrong. Please try again.'),
];

$nl_lang = function_exists('pll_current_language') ? (string) pll_current_language() : '';

// Laca_Recaptcha (parent) đọc key qua getOption() — có hậu tố ngôn ngữ — nên
// có thể không enqueue script dù key đã nhập. Tự enqueue cùng handle để không
// nạp trùng; footer.php chạy trước wp_footer() nên script in_footer vẫn kịp in.
$nl_recaptcha_key = NewsletterAjaxHandler::recaptchaSiteKey();
if ($nl_recaptcha_key !== '' && !wp_script_is('google-recaptcha', 'enqueued')) {
    wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($nl_recaptcha_key), [], null, true);
}
?>
<section class="newsletter" aria-labelledby="newsletter-title">
    <h2 id="newsletter-title" class="newsletter__title"><?php echo esc_html($nl_title); ?></h2>

    <form class="newsletter__form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" novalidate
        data-newsletter-form data-messages="<?php echo esc_attr(wp_json_encode($nl_messages)); ?>"
        <?php if ($nl_recaptcha_key !== ''): ?>data-recaptcha-key="<?php echo esc_attr($nl_recaptcha_key); ?>"
        data-recaptcha-action="<?php echo esc_attr(NewsletterAjaxHandler::RECAPTCHA_ACTION); ?>"<?php endif; ?>>
        <div class="newsletter__field">
            <label for="newsletter-email" class="screen-reader-text"><?php echo esc_html($nl_placeholder); ?></label>
            <input type="email" id="newsletter-email" name="email" class="newsletter__input"
                placeholder="<?php echo esc_attr($nl_placeholder); ?>" autocomplete="email" maxlength="190" required
                aria-describedby="newsletter-msg">
            <button type="submit" class="newsletter__btn"><?php echo esc_html($nl_button); ?></button>
        </div>

        <?php // Honeypot chặn bot — ẩn khỏi người dùng và trình đọc màn hình. ?>
        <div class="newsletter__hp" aria-hidden="true">
            <label for="newsletter-website">Website</label>
            <input type="text" id="newsletter-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <input type="hidden" name="action" value="<?php echo esc_attr(NewsletterAjaxHandler::ACTION); ?>">
        <input type="hidden" name="lang" value="<?php echo esc_attr($nl_lang); ?>">
        <?php // Không dùng wp_nonce_field(): nó gắn id="nonce", dễ trùng id với form khác trên cùng trang. ?>
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce(NewsletterAjaxHandler::NONCE_ACTION)); ?>">

        <p id="newsletter-msg" class="newsletter__msg" role="status" aria-live="polite" hidden></p>

        <?php if ($nl_consent !== ''): ?>
            <p class="newsletter__consent">
                <?php echo esc_html($nl_consent); ?>
                <?php if ($nl_privacy_url !== ''): ?>
                    <a href="<?php echo esc_url($nl_privacy_url); ?>"><?php esc_html_e('Privacy Policy', 'laca'); ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </form>
</section>
