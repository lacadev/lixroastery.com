<?php
if (!defined('ABSPATH')) {
    exit;
}

$columns        = max(1, min(4, intval($attributes['columns'] ?? 2)));
$max_width      = max(10, min(100, (int) ($attributes['maxWidth'] ?? 50)));
$max_width_tablet = max(10, min(100, (int) ($attributes['maxWidthTablet'] ?? 100)));
$max_width_mobile = max(10, min(100, (int) ($attributes['maxWidthMobile'] ?? 100)));
$content_align  = in_array($attributes['contentAlign'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlign']
    : 'center';
$margin_map     = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];
$items          = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

if (empty($items)) {
    return;
}

// Section ngoài LUÔN container-fluid — độ rộng NỘI DUNG điều chỉnh riêng qua
// maxWidth (%) + contentAlign, giống hệt block Container.
$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-content-grid container-fluid']);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-content-grid__maxwidth" style="--mw-pc:<?php echo esc_attr($max_width); ?>%;--mw-tablet:<?php echo esc_attr($max_width_tablet); ?>%;--mw-mobile:<?php echo esc_attr($max_width_mobile); ?>%;margin:<?php echo esc_attr($margin_map[$content_align]); ?>;">
        <div class="block-content-grid__grid" style="--ctg-columns: <?php echo esc_attr($columns); ?>;">
            <?php foreach ($items as $item) :
                $title = esc_html($item['title'] ?? '');
                // desc cho phép format cơ bản (in đậm/nghiêng…) qua RichText.
                $desc  = wp_kses_post($item['desc'] ?? '');
                if (!$title && !$desc) {
                    continue;
                }
            ?>
                <div class="block-content-grid__item">
                    <hr class="block-content-grid__rule" />
                    <?php if ($title) : ?>
                        <h3 class="block-content-grid__title"><?php echo $title; ?></h3>
                    <?php endif; ?>
                    <?php if ($desc) : ?>
                        <p class="block-content-grid__desc"><?php echo $desc; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
