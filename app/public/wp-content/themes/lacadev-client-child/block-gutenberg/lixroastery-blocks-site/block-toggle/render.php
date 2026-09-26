<?php
if (!defined('ABSPATH')) {
    exit;
}

// title/description/question/answer được nhập trực tiếp trong canvas qua
// RichText (edit.js) nên đã là HTML an toàn (RichText tự escape nội dung),
// chỉ cần lọc qua wp_kses_post() trước khi in ra.
$container_type = ($attributes['containerType'] ?? 'container') === 'container-fluid' ? 'container-fluid' : 'container';
$title          = wp_kses_post($attributes['title'] ?? '');
$description    = wp_kses_post($attributes['description'] ?? '');
$items          = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

if (empty($items)) {
    return;
}

// wp_unique_id() để mỗi block instance có id JS riêng — tránh lỗi trùng id
// giữa nhiều instance của cùng 1 block trên 1 trang (đã gặp ở block-projects-slider).
$unique_id = wp_unique_id('lix-toggle-');

// containerType gắn thẳng vào section này (KHÔNG bọc thêm 1 div riêng) —
// giống class Bootstrap thật (.container/.container-fluid tự là khung
// ngoài cùng), tránh 1 lớp div thừa không cần thiết. "id" (dùng để scope JS
// + <style> thu gọn câu trả lời bên dưới) chuyển từ div con cũ sang thẳng
// section này.
$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'block-toggle ' . $container_type,
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
