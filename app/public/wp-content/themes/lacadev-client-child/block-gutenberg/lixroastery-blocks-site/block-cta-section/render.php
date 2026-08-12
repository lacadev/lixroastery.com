<?php
if (!defined('ABSPATH')) {
    exit;
}

$headline_1 = esc_html($attributes['headlineLine1'] ?? '');
$headline_2 = esc_html($attributes['headlineLine2'] ?? '');
$description = wp_kses_post($attributes['description'] ?? '');
$button_text = esc_html($attributes['buttonText'] ?? '');
$button_link = esc_url($attributes['buttonLink'] ?? '');

$text_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['textColor'] ?? '')
    ? $attributes['textColor']
    : '#1c2b1f';
$button_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['buttonColor'] ?? '')
    ? $attributes['buttonColor']
    : '#2f4a34';

$bg_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '')
    ? $attributes['bgColor']
    : '#ffffff';
$bg_opacity = max(0, min(100, intval($attributes['bgOpacity'] ?? 100)));
$r = hexdec(substr($bg_color, 1, 2));
$g = hexdec(substr($bg_color, 3, 2));
$b = hexdec(substr($bg_color, 5, 2));
$bg_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ($bg_opacity / 100) . ')';

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-cta-section']);
?>
<section <?php echo $wrapper_attrs; ?>
    style="background:<?php echo esc_attr($bg_rgba); ?>;color:<?php echo esc_attr($text_color); ?>;">
    <div class="container block-cta-section__inner">
        <?php if ($headline_1 || $headline_2): ?>
            <h2 class="block-cta-section__headline">
                <?php if ($headline_1): ?><span><?php echo $headline_1; ?></span><?php endif; ?>
                <?php if ($headline_2): ?><span><?php echo $headline_2; ?></span><?php endif; ?>
            </h2>
        <?php endif; ?>
        <?php if ($description): ?>
            <div class="block-cta-section__desc"><?php echo $description; ?></div>
        <?php endif; ?>
        <?php if ($button_text): ?>
            <div class="block-cta-section__btn">
                <a class="block-cta-section__link" href="<?php echo $button_link ?: '#'; ?>">
                    <?php echo $button_text; ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>