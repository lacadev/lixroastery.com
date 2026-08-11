<?php
if (!defined('ABSPATH')) {
    exit;
}

$hero_image_url = esc_url($attributes['heroImageUrl'] ?? '');
$headline_raw    = (string) ($attributes['headline'] ?? '');
$description     = wp_kses_post($attributes['description'] ?? '');
$height_mode      = ($attributes['heightMode'] ?? 'custom') === 'full' ? 'full' : 'custom';
$min_height       = max(320, min(1200, intval($attributes['minHeight'] ?? 560)));

$overlay_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['overlayColor'] ?? '')
    ? $attributes['overlayColor']
    : '#0f1f13';
$overlay_opacity = max(0, min(90, intval($attributes['overlayOpacity'] ?? 45))) / 100;
$r = hexdec(substr($overlay_color, 1, 2));
$g = hexdec(substr($overlay_color, 3, 2));
$b = hexdec(substr($overlay_color, 5, 2));
$overlay_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $overlay_opacity . ')';

// Giữ nguyên xuống dòng người dùng tự nhập trong textarea — mỗi dòng render
// riêng 1 <span>, không gộp lại thành 1 dòng duy nhất.
$headline_lines = array_filter(array_map('trim', explode("\n", $headline_raw)));

$section_class = 'block-top-hero block-top-hero--' . $height_mode;
$wrapper_attrs = get_block_wrapper_attributes(['class' => $section_class]);

// Chế độ "custom": section có chiều cao cố định qua inline style, ảnh phủ
// kín bằng object-fit:cover. Chế độ "full": không set chiều cao — ảnh nằm
// trong luồng (height:auto), section tự co theo đúng kích thước ảnh gốc.
// Trường hợp "full" mà chưa có ảnh thì fallback 1 chiều cao tối thiểu để
// không bị sập xuống 0px.
$section_style = $height_mode === 'custom'
    ? 'min-height:' . $min_height . 'px;'
    : (!$hero_image_url ? 'min-height:400px;' : '');
?>
<section <?php echo $wrapper_attrs; ?> style="<?php echo esc_attr($section_style); ?>">
    <?php if ($hero_image_url) : ?>
        <img src="<?php echo $hero_image_url; ?>" alt="" class="block-top-hero__bg" loading="eager" />
    <?php endif; ?>
    <div class="block-top-hero__overlay" style="background:linear-gradient(180deg, <?php echo esc_attr($overlay_rgba); ?> 0%, <?php echo esc_attr($overlay_rgba); ?> 100%);"></div>

    <div class="block-top-hero__content">
        <?php if (!empty($headline_lines)) : ?>
            <h1 class="block-top-hero__headline">
                <?php foreach ($headline_lines as $line) : ?>
                    <span><?php echo esc_html($line); ?></span>
                <?php endforeach; ?>
            </h1>
        <?php endif; ?>
        <?php if ($description) : ?>
            <div class="block-top-hero__desc"><?php echo $description; ?></div>
        <?php endif; ?>
    </div>
</section>
