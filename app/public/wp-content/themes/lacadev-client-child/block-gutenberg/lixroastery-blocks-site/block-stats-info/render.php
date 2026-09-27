<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

$columns = max(1, min(6, (int) ($attributes['columns'] ?? 4)));

// Căn lề riêng PC/Mobile — "Mobile" áp dụng từ $md trở xuống (khớp đúng
// breakpoint mà lưới cột (__grid) đang tự ép về 2 cột/1 cột bên dưới).
$allowed_aligns = ['left', 'center', 'right', 'justify'];
$title_align_pc = in_array($attributes['titleAlign'] ?? '', $allowed_aligns, true) ? $attributes['titleAlign'] : 'left';
$title_align_mobile = in_array($attributes['titleAlignMobile'] ?? '', $allowed_aligns, true) ? $attributes['titleAlignMobile'] : 'left';
$description_align_pc = in_array($attributes['descriptionAlign'] ?? '', $allowed_aligns, true) ? $attributes['descriptionAlign'] : 'left';
$description_align_mobile = in_array($attributes['descriptionAlignMobile'] ?? '', $allowed_aligns, true) ? $attributes['descriptionAlignMobile'] : 'left';

$number_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['numberColor'] ?? '')
    ? $attributes['numberColor']
    : '#1c2b1f';
$label_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['labelColor'] ?? '')
    ? $attributes['labelColor']
    : '#1c2b1f';
$description_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['descriptionColor'] ?? '')
    ? $attributes['descriptionColor']
    : '#6b7568';

$bg_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '')
    ? $attributes['bgColor']
    : '#ffffff';
$bg_opacity = max(0, min(100, intval($attributes['bgOpacity'] ?? 100)));
$r = hexdec(substr($bg_color, 1, 2));
$g = hexdec(substr($bg_color, 3, 2));
$b = hexdec(substr($bg_color, 5, 2));
$bg_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ($bg_opacity / 100) . ')';

if (empty($items)) {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-stats-info']);
?>
<section <?php echo $wrapper_attrs; ?> style="background:<?php echo esc_attr($bg_rgba); ?>;">
    <div class="container-fluid">
        <div class="block-stats-info__grid" style="
            --stats-columns: <?php echo esc_attr($columns); ?>;
            --stats-title-align-pc: <?php echo esc_attr($title_align_pc); ?>;
            --stats-title-align-mobile: <?php echo esc_attr($title_align_mobile); ?>;
            --stats-desc-align-pc: <?php echo esc_attr($description_align_pc); ?>;
            --stats-desc-align-mobile: <?php echo esc_attr($description_align_mobile); ?>;
        ">
            <?php foreach ($items as $item):
                $number = esc_html($item['number'] ?? '');
                $label = esc_html($item['label'] ?? '');
                // description cho phép format cơ bản (in đậm/nghiêng…) qua RichText.
                $description = wp_kses_post($item['description'] ?? '');
                if (!$number && !$label) {
                    continue;
                }
                ?>
                <div class="block-stats-info__item">
                    <div class="block-stats-info__number">
                        <?php echo $number; ?>
                    </div>
                    <?php if ($label): ?>
                        <div class="block-stats-info__label">
                            <?php echo $label; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($description): ?>
                        <p class="block-stats-info__desc">
                            <?php echo $description; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>