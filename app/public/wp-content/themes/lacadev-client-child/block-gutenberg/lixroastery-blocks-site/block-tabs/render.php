<?php
if (!defined('ABSPATH')) {
    exit;
}

// wp_unique_id() để mỗi block instance có id JS riêng — tránh lỗi trùng id
// giữa nhiều instance của cùng 1 block trên 1 trang (đã gặp ở block-projects-slider).
$unique_id = wp_unique_id('lix-tabs-');

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'tabs-block']);

// Ẩn/hiện theo tab CHỈ áp dụng ở đây (render.php chỉ chạy ở frontend, không
// bao giờ được editor gọi tới) — không đặt rule này trong style.scss vì
// handle "style" load cả trong iframe editor, sẽ ẩn mất Tab Panel khi soạn.
$scoped_selector = '#' . $unique_id;
?>
<style>
    <?php echo esc_html($scoped_selector); ?> .tabs-block__panels > .tab-panel { display: none; }
    <?php echo esc_html($scoped_selector); ?> .tabs-block__panels > .tab-panel.is-active { display: block; }
</style>
<section <?php echo $wrapper_attrs; ?>>
    <div class="container-fluid tabs-block__inner" id="<?php echo esc_attr($unique_id); ?>">
        <div class="tabs-block__panels">
            <?php echo $content; ?>
        </div>
        <div class="tabs-block__footer">
            <a href="#" class="tabs-block__prev"><?php esc_html_e('Prev', 'laca'); ?></a>
            <a href="#" class="tabs-block__next"><?php esc_html_e('Next', 'laca'); ?></a>
        </div>
    </div>
</section>
<?php
$js = <<<JS
(function () {
    var root = document.getElementById('{$unique_id}');
    if (!root) return;
    var panelsWrap = root.querySelector('.tabs-block__panels');
    if (!panelsWrap) return;
    var panels = Array.prototype.slice.call(panelsWrap.children).filter(function (el) {
        return el.classList.contains('tab-panel');
    });
    if (!panels.length) return;

    var nav = document.createElement('nav');
    nav.className = 'tabs-block__nav';
    panels.forEach(function (panel, i) {
        var link = document.createElement('a');
        link.href = '#';
        link.className = 'tabs-block__nav-link';
        link.textContent = panel.getAttribute('data-tab-title') || ('Tab ' + (i + 1));
        link.addEventListener('click', function (e) {
            e.preventDefault();
            setActive(i);
        });
        nav.appendChild(link);
        if (i < panels.length - 1) {
            var sep = document.createElement('span');
            sep.className = 'tabs-block__nav-sep';
            sep.textContent = '|';
            nav.appendChild(sep);
        }
    });
    root.insertBefore(nav, panelsWrap);

    var navLinks = Array.prototype.slice.call(nav.querySelectorAll('.tabs-block__nav-link'));
    var activeIndex = 0;

    function setActive(index) {
        activeIndex = (index + panels.length) % panels.length;
        panels.forEach(function (panel, i) {
            panel.classList.toggle('is-active', i === activeIndex);
        });
        navLinks.forEach(function (link, i) {
            link.classList.toggle('is-active', i === activeIndex);
        });
    }

    var prevBtn = root.querySelector('.tabs-block__prev');
    var nextBtn = root.querySelector('.tabs-block__next');
    if (prevBtn) {
        prevBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setActive(activeIndex - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setActive(activeIndex + 1);
        });
    }

    setActive(0);
})();
JS;
// Đăng ký vào 'theme-js-bundle' — handle luôn tồn tại (đã xác nhận qua bug
// tương tự ở block-projects-slider), KHÔNG dùng
// handle riêng của block vì wp_add_inline_script() sẽ âm thầm không làm gì
// nếu handle đó chưa được register tại thời điểm gọi.
wp_add_inline_script('theme-js-bundle', $js);
