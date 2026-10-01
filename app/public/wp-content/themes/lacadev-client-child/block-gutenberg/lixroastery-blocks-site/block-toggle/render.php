<?php
if (!defined('ABSPATH')) {
    exit;
}

// title/description/question/answer được nhập trực tiếp trong canvas qua
// RichText (edit.js) nên đã là HTML an toàn (RichText tự escape nội dung),
// chỉ cần lọc qua wp_kses_post() trước khi in ra.
//
// Section ngoài LUÔN container-fluid (nền/section full-bleed hết màn hình) —
// độ rộng NỘI DUNG bên trong điều chỉnh riêng qua maxWidth (%) + contentAlign,
// giống hệt block Container (xem block-container/render.php).
$max_width = max(10, min(100, (int) ($attributes['maxWidth'] ?? 50)));
$max_width_tablet = max(10, min(100, (int) ($attributes['maxWidthTablet'] ?? 100)));
$max_width_mobile = max(10, min(100, (int) ($attributes['maxWidthMobile'] ?? 100)));
$content_align = in_array($attributes['contentAlign'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlign']
    : 'center';
$content_align_tablet = in_array($attributes['contentAlignTablet'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignTablet']
    : 'center';
$content_align_mobile = in_array($attributes['contentAlignMobile'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignMobile']
    : 'center';
$margin_map = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];

$title          = wp_kses_post($attributes['title'] ?? '');
$description    = wp_kses_post($attributes['description'] ?? '');
$items          = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

if (empty($items)) {
    return;
}

// wp_unique_id() để mỗi block instance có id JS riêng — tránh lỗi trùng id
// giữa nhiều instance của cùng 1 block trên 1 trang (đã gặp ở block-projects-slider).
$unique_id = wp_unique_id('lix-toggle-');

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'block-toggle container-fluid',
    'id' => $unique_id,
]);

// Thu gọn câu trả lời CHỈ áp dụng ở đây (render.php chỉ chạy ở frontend,
// không bao giờ được editor gọi tới) — không đặt trong style.scss vì handle
// "style" load cả trong iframe editor, sẽ ẩn mất câu trả lời khi soạn.
$scoped_selector = '#' . $unique_id;
?>
<style>
    <?php echo esc_html($scoped_selector); ?> .block-toggle__answer { max-height: 0; overflow: hidden; transition: max-height .35s ease; }
</style>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-toggle__maxwidth" style="--mw-pc:<?php echo esc_attr($max_width); ?>%;--mw-tablet:<?php echo esc_attr($max_width_tablet); ?>%;--mw-mobile:<?php echo esc_attr($max_width_mobile); ?>%;--align-margin-pc:<?php echo esc_attr($margin_map[$content_align]); ?>;--align-margin-tablet:<?php echo esc_attr($margin_map[$content_align_tablet]); ?>;--align-margin-mobile:<?php echo esc_attr($margin_map[$content_align_mobile]); ?>;">
        <?php if ($title) : ?>
            <h2 class="block-toggle__title"><?php echo $title; ?></h2>
        <?php endif; ?>
        <?php if ($description) : ?>
            <p class="block-toggle__description"><?php echo $description; ?></p>
        <?php endif; ?>

        <div class="block-toggle__list">
            <?php foreach ($items as $item) :
                $question = wp_kses_post($item['question'] ?? '');
                $answer   = wp_kses_post($item['answer'] ?? '');

                if (!$question && !$answer) {
                    continue;
                }
            ?>
                <div class="block-toggle__item">
                    <button type="button" class="block-toggle__question" aria-expanded="false">
                        <span><?php echo $question; ?></span>
                        <span class="block-toggle__icon" aria-hidden="true"></span>
                    </button>
                    <div class="block-toggle__answer">
                        <?php if ($answer) : ?>
                            <p><?php echo $answer; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
$js = <<<JS
(function () {
    var root = document.getElementById('{$unique_id}');
    if (!root) return;
    var items = Array.prototype.slice.call(root.querySelectorAll('.block-toggle__item'));
    if (!items.length) return;

    function closeItem(item) {
        item.classList.remove('is-active');
        var question = item.querySelector('.block-toggle__question');
        var answer = item.querySelector('.block-toggle__answer');
        if (question) question.setAttribute('aria-expanded', 'false');
        if (answer) answer.style.maxHeight = '';
    }

    function openItem(item) {
        item.classList.add('is-active');
        var question = item.querySelector('.block-toggle__question');
        var answer = item.querySelector('.block-toggle__answer');
        if (question) question.setAttribute('aria-expanded', 'true');
        if (answer) answer.style.maxHeight = answer.scrollHeight + 'px';
    }

    items.forEach(function (item) {
        var question = item.querySelector('.block-toggle__question');
        if (!question) return;
        question.addEventListener('click', function () {
            var isActive = item.classList.contains('is-active');
            // Mỗi lần chỉ mở 1 mục — đóng hết các mục khác trước khi mở mục vừa bấm.
            items.forEach(closeItem);
            if (!isActive) {
                openItem(item);
            }
        });
    });
})();
JS;
// Đăng ký vào 'theme-js-bundle' — handle luôn tồn tại (đã xác nhận qua bug
// tương tự ở block-projects-slider), KHÔNG dùng handle riêng của block vì
// wp_add_inline_script() sẽ âm thầm không làm gì nếu handle đó chưa được
// register tại thời điểm gọi.
wp_add_inline_script('theme-js-bundle', $js);
