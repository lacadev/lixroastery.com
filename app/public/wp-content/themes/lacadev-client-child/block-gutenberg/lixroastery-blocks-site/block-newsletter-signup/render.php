<?php
if (!defined('ABSPATH')) {
    exit;
}

$heading     = esc_html($attributes['heading'] ?? '');
$placeholder = esc_attr($attributes['placeholder'] ?? '');
$button_text = esc_html($attributes['buttonText'] ?? '');

// wp_unique_id() để mỗi block instance có id JS riêng — tránh lỗi trùng id
// giữa nhiều instance của cùng 1 block trên 1 trang (đã gặp ở block-projects-slider).
$form_id = wp_unique_id('laca-newsletter-');

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-newsletter-signup']);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="container-fluid">
        <hr class="block-newsletter-signup__rule" />
        <form id="<?php echo esc_attr($form_id); ?>" class="block-newsletter-signup__form">
            <?php wp_nonce_field('laca_newsletter_subscribe', 'nonce'); ?>
            <?php if ($heading) : ?>
                <p class="block-newsletter-signup__heading"><?php echo $heading; ?></p>
            <?php endif; ?>
            <div class="block-newsletter-signup__field">
                <input type="email" name="email" class="block-newsletter-signup__input" placeholder="<?php echo $placeholder; ?>" required />
                <button type="submit" class="block-newsletter-signup__submit"><?php echo $button_text; ?></button>
            </div>
            <p class="block-newsletter-signup__msg" role="status" aria-live="polite"></p>
        </form>
        <hr class="block-newsletter-signup__rule" />
    </div>
</section>
<?php
$ajax_url = esc_url(admin_url('admin-ajax.php'));
$js = <<<JS
(function () {
    var form = document.getElementById('{$form_id}');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = form.querySelector('.block-newsletter-signup__msg');
        var btn = form.querySelector('.block-newsletter-signup__submit');
        var email = form.querySelector('input[name="email"]').value;
        var nonce = form.querySelector('input[name="nonce"]').value;
        btn.disabled = true;
        msg.textContent = '';
        fetch('{$ajax_url}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=laca_newsletter_subscribe&nonce=' + encodeURIComponent(nonce) + '&email=' + encodeURIComponent(email),
        })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (res.success) {
                    msg.style.color = 'green';
                    msg.textContent = res.data;
                    form.reset();
                } else {
                    msg.style.color = 'red';
                    msg.textContent = res.data;
                }
            })
            .catch(function () {
                btn.disabled = false;
                msg.style.color = 'red';
                msg.textContent = 'Có lỗi xảy ra, vui lòng thử lại.';
            });
    });
})();
JS;
// Đăng ký vào 'theme-js-bundle' — handle luôn tồn tại (đã xác nhận qua bug
// tương tự ở block-projects-slider), KHÔNG đăng ký vào handle riêng của
// block vì wp_add_inline_script() sẽ âm thầm không làm gì nếu handle đó
// chưa được register tại thời điểm gọi.
wp_add_inline_script('theme-js-bundle', $js);
