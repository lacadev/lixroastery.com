<?php
if (!defined('ABSPATH')) {
    exit;
}

// year/title/desc/content được nhập trực tiếp trong canvas qua RichText
// (edit.js) nên đã là HTML an toàn (RichText tự escape nội dung), chỉ cần
// lọc qua wp_kses_post()/esc_html() trước khi in ra tương ứng.
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
$margin_map = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];

$items = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

if (empty($items)) {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-timeline container-fluid']);
$prev_year     = null;
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-timeline__maxwidth" style="--mw-pc:<?php echo esc_attr($max_width); ?>%;--mw-tablet:<?php echo esc_attr($max_width_tablet); ?>%;--mw-mobile:<?php echo esc_attr($max_width_mobile); ?>%;margin:<?php echo esc_attr($margin_map[$content_align]); ?>;">
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
    </div>
</section>
