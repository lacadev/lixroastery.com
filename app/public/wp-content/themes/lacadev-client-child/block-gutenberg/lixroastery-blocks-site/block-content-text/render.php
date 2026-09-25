<?php
if (!defined('ABSPATH')) {
    exit;
}

// content được nhập trực tiếp trong canvas qua RichText (edit.js) nên đã là
// HTML an toàn (RichText tự escape nội dung), chỉ cần lọc qua wp_kses_post().
$container_type = ($attributes['containerType'] ?? 'container') === 'container-fluid' ? 'container-fluid' : 'container';
$variant        = ($attributes['variant'] ?? 'normal') === 'quote' ? 'quote' : 'normal';
$text_align     = in_array($attributes['textAlign'] ?? 'left', ['left', 'center', 'right', 'justify'], true)
    ? $attributes['textAlign']
    : 'left';
$content        = wp_kses_post($attributes['content'] ?? '');

if (!$content) {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'block-content-text block-content-text--' . $variant,
]);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="<?php echo esc_attr($container_type); ?>">
        <div class="block-content-text__body block-content-text__body--<?php echo esc_attr($variant); ?>" style="text-align: <?php echo esc_attr($text_align); ?>;">
            <?php echo $content; ?>
        </div>
    </div>
</section>
