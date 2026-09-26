<?php
if (!defined('ABSPATH')) {
    exit;
}

// year/title/desc/content được nhập trực tiếp trong canvas qua RichText
// (edit.js) nên đã là HTML an toàn (RichText tự escape nội dung), chỉ cần
// lọc qua wp_kses_post()/esc_html() trước khi in ra tương ứng.
$container_type = ($attributes['containerType'] ?? 'container-fluid') === 'container' ? 'container' : 'container-fluid';
$items          = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

if (empty($items)) {
    return;
}

// containerType gắn thẳng vào section này (KHÔNG bọc thêm 1 div riêng) —
// giống class Bootstrap thật (.container/.container-fluid tự là khung
// ngoài cùng), tránh 1 lớp div thừa không cần thiết.
$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-timeline ' . $container_type]);
$prev_year     = null;
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-timeline__list">
        <?php foreach ($items as $item) :
            $year    = wp_kses_post($item['year'] ?? '');
            $title   = wp_kses_post($item['title'] ?? '');
            $desc    = wp_kses_post($item['desc'] ?? '');
            $content = wp_kses_post($item['content'] ?? '');

            if (!$title && !$desc && !$content) {
                continue;
            }

            // Các mốc cùng năm liên tiếp chỉ hiện năm ở mốc đầu tiên.
            $show_year = $year !== '' && $year !== $prev_year;
            $prev_year = $year;
        ?>
            <div class="block-timeline__row">
                <div class="block-timeline__year">
                    <?php if ($show_year) : ?>
                        <span><?php echo $year; ?></span>
                    <?php endif; ?>
                </div>
                <div class="block-timeline__meta">
                    <?php if ($title) : ?>
                        <h3 class="block-timeline__title"><?php echo $title; ?></h3>
                    <?php endif; ?>
                    <?php if ($desc) : ?>
                        <p class="block-timeline__desc"><?php echo $desc; ?></p>
                    <?php endif; ?>
                </div>
                <div class="block-timeline__content">
                    <?php if ($content) : ?>
                        <p><?php echo $content; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
