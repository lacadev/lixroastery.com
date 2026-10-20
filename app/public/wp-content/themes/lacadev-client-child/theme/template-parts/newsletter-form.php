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

// Chưa từng lưu tab thì Carbon Fields trả default (true) → vẫn hiện. Ẩn trên
// chính trang xác nhận/huỷ đăng ký (NewsletterEndpoints) cho khỏi lạc lõng.
if (!getOption('newsletter_enable') || NewsletterEndpoints::$rendering) {
    return;
}

$nl_lang = function_exists('pll_current_language') ? (string) pll_current_language() : '';

$nl_title = NewsletterTexts::get('newsletter_title', $nl_lang);
$nl_placeholder = NewsletterTexts::get('newsletter_placeholder', $nl_lang);
$nl_button = NewsletterTexts::get('newsletter_button', $nl_lang);
// Admin xoá trắng ô này (getOption() rỗng) là ẩn dòng đồng ý; còn lại lấy qua
// NewsletterTexts để ra đúng ngôn ngữ.
$nl_consent = (string) getOption('newsletter_consent') !== '' ? NewsletterTexts::get('newsletter_consent', $nl_lang) : '';
$nl_privacy_url = (string) getOption('newsletter_privacy_url');

$nl_messages = [
    'subscribed' => NewsletterTexts::get('newsletter_msg_success', $nl_lang),
    'exists' => NewsletterTexts::get('newsletter_msg_exists', $nl_lang),
    'invalid' => NewsletterTexts::get('newsletter_msg_invalid', $nl_lang),
    'captcha' => NewsletterTexts::get('newsletter_msg_captcha', $nl_lang),
    'limit' => NewsletterTexts::get('newsletter_msg_limit', $nl_lang),
    'error' => NewsletterTexts::get('newsletter_msg_error', $nl_lang),
];

// Key reCAPTCHA truyền qua data-recaptcha-key trên form để JS lazy load khi người dùng tương tác
$nl_recaptcha_key = NewsletterAjaxHandler::recaptchaSiteKey();
?>
<section class="newsletter container-fluid" aria-labelledby="newsletter-title">
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
