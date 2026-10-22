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
$content_align_tablet = in_array($attributes['contentAlignTablet'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignTablet']
    : 'center';
$content_align_mobile = in_array($attributes['contentAlignMobile'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignMobile']
    : 'center';
$margin_map     = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];
$items          = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

if (empty($items)) {
    return;
}

// Cỡ chữ (px) theo từng màn hình — chỉ set biến khi admin đã chỉnh, bỏ trống
// thì CSS giữ cỡ mặc định của block (xem style.scss).
$grid_style = '--ctg-columns:' . $columns . ';';
foreach (['title', 'desc'] as $key) {
    foreach (['pc' => '', 'tablet' => 'Tablet', 'mobile' => 'Mobile'] as $tier => $suffix) {
        $attr = $key . 'FontSize' . $suffix;
        if (isset($attributes[$attr]) && is_numeric($attributes[$attr])) {
            $grid_style .= '--ctg-' . $key . '-' . $tier . ':' . max(10, min(120, (int) $attributes[$attr])) . 'px;';
        }
    }
}

// Section ngoài LUÔN container-fluid — độ rộng NỘI DUNG điều chỉnh riêng qua
// maxWidth (%) + contentAlign, giống hệt block Container.
$layout_class = laca_block_layout_class($attributes);
$wrapper_attrs = get_block_wrapper_attributes(['class' => trim('block-content-grid container-fluid ' . $layout_class)]);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-content-grid__maxwidth" style="--mw-pc:<?php echo esc_attr($max_width); ?>%;--mw-tablet:<?php echo esc_attr($max_width_tablet); ?>%;--mw-mobile:<?php echo esc_attr($max_width_mobile); ?>%;--align-margin-pc:<?php echo esc_attr($margin_map[$content_align]); ?>;--align-margin-tablet:<?php echo esc_attr($margin_map[$content_align_tablet]); ?>;--align-margin-mobile:<?php echo esc_attr($margin_map[$content_align_mobile]); ?>;">
        <div class="block-content-grid__grid" style="<?php echo esc_attr($grid_style); ?>">
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
