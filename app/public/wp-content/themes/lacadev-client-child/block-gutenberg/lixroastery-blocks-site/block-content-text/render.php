<?php
if (!defined('ABSPATH')) {
    exit;
}

// content được nhập trực tiếp trong canvas qua RichText (edit.js) nên đã là
// HTML an toàn (RichText tự escape nội dung), chỉ cần lọc qua wp_kses_post().
//
// Section ngoài LUÔN container-fluid — độ rộng CỘT nội dung điều chỉnh riêng
// qua maxWidth (%) + contentAlign (giống hệt block Container), KHÁC với
// textAlign (chỉ canh chữ BÊN TRONG cột đó, không phải vị trí/độ rộng cột).
$max_width = max(10, min(100, (int) ($attributes['maxWidth'] ?? 50)));
$max_width_tablet = max(10, min(100, (int) ($attributes['maxWidthTablet'] ?? 100)));
$max_width_mobile = max(10, min(100, (int) ($attributes['maxWidthMobile'] ?? 100)));
$content_align = in_array($attributes['contentAlign'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlign']
    : 'center';
$margin_map = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];

$variant        = ($attributes['variant'] ?? 'normal') === 'quote' ? 'quote' : 'normal';
$text_align     = in_array($attributes['textAlign'] ?? 'left', ['left', 'center', 'right', 'justify'], true)
    ? $attributes['textAlign']
    : 'left';
$content        = wp_kses_post($attributes['content'] ?? '');

if (!$content) {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'block-content-text block-content-text--' . $variant . ' container-fluid',
]);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-content-text__maxwidth" style="--mw-pc: <?php echo esc_attr($max_width); ?>%; --mw-tablet: <?php echo esc_attr($max_width_tablet); ?>%; --mw-mobile: <?php echo esc_attr($max_width_mobile); ?>%; margin: <?php echo esc_attr($margin_map[$content_align]); ?>;">
        <div class="block-content-text__body block-content-text__body--<?php echo esc_attr($variant); ?>" style="text-align: <?php echo esc_attr($text_align); ?>;">
            <?php echo $content; ?>
        </div>
    </div>
</section>
