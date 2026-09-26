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

$allowed_aligns = ['left', 'center', 'right', 'justify'];
$headline_align = in_array($attributes['headlineAlign'] ?? '', $allowed_aligns, true) ? $attributes['headlineAlign'] : 'center';
$description_align = in_array($attributes['descriptionAlign'] ?? '', $allowed_aligns, true) ? $attributes['descriptionAlign'] : 'center';
$button_align = in_array($attributes['buttonAlign'] ?? '', $allowed_aligns, true) ? $attributes['buttonAlign'] : 'center';

// "Căn đều" (justify) cho nút bấm hiểu là giãn nút full-width (nút chỉ có 1
// dòng nên text-align:justify không có tác dụng) — còn lại định vị nút
// trong hàng bằng flex justify-content, khớp logic ở edit.js.
$button_justify_map = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
$btn_wrap_style = $button_align === 'justify'
    ? 'display:flex;'
    : 'display:flex;justify-content:' . ($button_justify_map[$button_align] ?? 'center') . ';';
$btn_link_style = $button_align === 'justify'
    ? 'display:block;width:100%;text-align:center;'
    : 'display:inline-block;';

// ID cố định do edit.js sinh ra 1 lần khi tạo block — dùng để scope "Custom
// CSS nút bấm" (token __BUTTON__), tránh CSS đè lẫn nhau khi có nhiều CTA
// Section trên cùng 1 trang. Block tạo trước khi có tính năng này (chưa có
// blockId) thì bỏ qua custom CSS cho tới khi được mở/lưu lại trong editor.
$block_id = preg_match('/^[a-zA-Z0-9_-]+$/', $attributes['blockId'] ?? '') ? $attributes['blockId'] : '';
$button_custom_css = wp_strip_all_tags($attributes['buttonCustomCss'] ?? '');
$scoped_button_css = ($block_id && $button_custom_css)
    ? str_replace('__BUTTON__', '#' . $block_id . ' .block-cta-section__link', $button_custom_css)
    : '';

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

$wrapper_extra = ['class' => 'block-cta-section'];
if ($block_id) {
    $wrapper_extra['id'] = $block_id;
}
$wrapper_attrs = get_block_wrapper_attributes($wrapper_extra);
?>
<section <?php echo $wrapper_attrs; ?>
    style="background:<?php echo esc_attr($bg_rgba); ?>;color:<?php echo esc_attr($text_color); ?>;">
    <div class="container block-cta-section__inner">
        <?php if ($headline): ?>
            <h2 class="block-cta-section__headline" style="text-align:<?php echo esc_attr($headline_align); ?>"><?php echo $headline; ?></h2>
        <?php endif; ?>
        <?php if ($description): ?>
            <div class="block-cta-section__desc" style="text-align:<?php echo esc_attr($description_align); ?>"><?php echo $description; ?></div>
        <?php endif; ?>
        <?php if ($button_text && $button_link): ?>
            <?php if ($scoped_button_css): ?>
                <style><?php echo $scoped_button_css; ?></style>
            <?php endif; ?>
            <div class="block-cta-section__btn" style="<?php echo esc_attr($btn_wrap_style); ?>">
                <a class="block-cta-section__link" href="<?php echo $button_link ?: '#'; ?>"
                    style="<?php echo esc_attr($btn_link_style); ?>"
                    target="<?php echo esc_attr($button_target); ?>"
                    <?php echo $button_target === '_blank' ? 'rel="noopener noreferrer"' : ''; ?>>
                    <?php echo $button_text; ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>