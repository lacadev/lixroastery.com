<?php
if (!defined('ABSPATH')) {
    exit;
}

// headline/description/buttonText được nhập trực tiếp trong canvas qua
// RichText (edit.js) nên đã là HTML an toàn (RichText tự escape nội dung),
// chỉ cần lọc qua wp_kses_post() trước khi in ra.
$headline = wp_kses_post($attributes['headline'] ?? '');
$description = wp_kses_post($attributes['description'] ?? '');
$button_text = wp_kses_post($attributes['buttonText'] ?? '');
$button_link = esc_url($attributes['buttonLink'] ?? '');
$button_target = ($attributes['buttonTarget'] ?? '_self') === '_blank' ? '_blank' : '_self';

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
        <?php if ($headline): ?>
            <h2 class="block-cta-section__headline"><?php echo $headline; ?></h2>
        <?php endif; ?>
        <?php if ($description): ?>
            <div class="block-cta-section__desc"><?php echo $description; ?></div>
        <?php endif; ?>
        <?php if ($button_text && $button_link): ?>
            <div class="block-cta-section__btn">
                <a class="block-cta-section__link" href="<?php echo $button_link ?: '#'; ?>"
                    target="<?php echo esc_attr($button_target); ?>"
                    <?php echo $button_target === '_blank' ? 'rel="noopener noreferrer"' : ''; ?>>
                    <?php echo $button_text; ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>