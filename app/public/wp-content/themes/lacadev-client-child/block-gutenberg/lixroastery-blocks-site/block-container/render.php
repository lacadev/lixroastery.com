<?php
if (!defined('ABSPATH')) {
    exit;
}

// Bọc các block mặc định (paragraph/heading/list...) vào 1 khung có thể tùy
// chỉnh kích thước tối đa (%) + căn lề — để khớp độ rộng với các block riêng
// của theme (mỗi block riêng đã tự set width qua containerType/__inner của
// chính nó). $content là HTML đã render sẵn của các block con (InnerBlocks),
// WordPress tự truyền vào khi block dùng render.php + InnerBlocks.
$max_width = max(10, min(100, (int) ($attributes['maxWidth'] ?? 50)));
$max_width_tablet = max(10, min(100, (int) ($attributes['maxWidthTablet'] ?? 100)));
$max_width_mobile = max(10, min(100, (int) ($attributes['maxWidthMobile'] ?? 100)));
$valid_aligns = ['left', 'center', 'right', 'justify'];
$align = in_array($attributes['contentAlign'] ?? '', $valid_aligns, true)
    ? $attributes['contentAlign']
    : 'center';
$align_tablet = in_array($attributes['contentAlignTablet'] ?? '', $valid_aligns, true)
    ? $attributes['contentAlignTablet']
    : 'center';
$align_mobile = in_array($attributes['contentAlignMobile'] ?? '', $valid_aligns, true)
    ? $attributes['contentAlignMobile']
    : 'center';

$margin_map = [
    'left'    => '0 auto 0 0',
    'center'  => '0 auto',
    'right'   => '0 0 0 auto',
    'justify' => '0 auto',
];

$text_align_pc     = $align === 'justify' ? 'justify' : 'inherit';
$text_align_tablet = $align_tablet === 'justify' ? 'justify' : 'inherit';
$text_align_mobile = $align_mobile === 'justify' ? 'justify' : 'inherit';

$heading_font_size_pc     = max(10, min(120, (int) ($attributes['headingFontSize'] ?? 34)));
$heading_font_size_tablet = max(10, min(120, (int) ($attributes['headingFontSizeTablet'] ?? 26)));
$heading_font_size_mobile = max(10, min(120, (int) ($attributes['headingFontSizeMobile'] ?? 18)));

$font_size_pc     = max(10, min(100, (int) ($attributes['fontSize'] ?? 16)));
$font_size_tablet = max(10, min(100, (int) ($attributes['fontSizeTablet'] ?? 15)));
$font_size_mobile = max(10, min(100, (int) ($attributes['fontSizeMobile'] ?? 14)));

$line_height_pc     = max(0.8, min(3.0, (float) ($attributes['lineHeight'] ?? 1.7)));
$line_height_tablet = max(0.8, min(3.0, (float) ($attributes['lineHeightTablet'] ?? 1.7)));
$line_height_mobile = max(0.8, min(3.0, (float) ($attributes['lineHeightMobile'] ?? 1.6)));

$spacing_pc     = max(0, min(150, (int) ($attributes['blockSpacing'] ?? 20)));
$spacing_tablet = max(0, min(150, (int) ($attributes['blockSpacingTablet'] ?? 16)));
$spacing_mobile = max(0, min(150, (int) ($attributes['blockSpacingMobile'] ?? 14)));

if (trim($content) === '') {
    return;
}

$style_vars = [
    '--mw-pc:' . $max_width . '%',
    '--mw-tablet:' . $max_width_tablet . '%',
    '--mw-mobile:' . $max_width_mobile . '%',
    '--align-margin-pc:' . $margin_map[$align],
    '--align-margin-tablet:' . $margin_map[$align_tablet],
    '--align-margin-mobile:' . $margin_map[$align_mobile],
    '--text-align-pc:' . $text_align_pc,
    '--text-align-tablet:' . $text_align_tablet,
    '--text-align-mobile:' . $text_align_mobile,
    '--h-fs-pc:' . $heading_font_size_pc . 'px',
    '--h-fs-tablet:' . $heading_font_size_tablet . 'px',
    '--h-fs-mobile:' . $heading_font_size_mobile . 'px',
    '--fs-pc:' . $font_size_pc . 'px',
    '--fs-tablet:' . $font_size_tablet . 'px',
    '--fs-mobile:' . $font_size_mobile . 'px',
    '--lh-pc:' . $line_height_pc,
    '--lh-tablet:' . $line_height_tablet,
    '--lh-mobile:' . $line_height_mobile,
    '--spacing-pc:' . $spacing_pc . 'px',
    '--spacing-tablet:' . $spacing_tablet . 'px',
    '--spacing-mobile:' . $spacing_mobile . 'px',
];

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'block-container',
    'style' => implode(';', $style_vars) . ';',
]);
?>

<section class="container-fluid">
    <div <?php echo $wrapper_attrs; ?>>
        <?php echo $content; ?>
    </div>
</section>