<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

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
        <div class="block-stats-info__grid">
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