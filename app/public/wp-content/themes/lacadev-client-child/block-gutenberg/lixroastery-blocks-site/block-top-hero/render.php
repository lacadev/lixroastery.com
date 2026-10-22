<?php
if (!defined('ABSPATH')) {
    exit;
}

$hero_image_url = esc_url($attributes['heroImageUrl'] ?? '');
// headline/description được nhập trực tiếp trong canvas qua RichText
// (edit.js) nên đã là HTML an toàn, chỉ cần lọc qua wp_kses_post().
$headline        = wp_kses_post($attributes['headline'] ?? '');
$description     = wp_kses_post($attributes['description'] ?? '');
$height_mode      = ($attributes['heightMode'] ?? 'custom') === 'full' ? 'full' : 'custom';
$min_height       = max(320, min(1200, intval($attributes['minHeight'] ?? 560)));
$full_bleed       = !empty($attributes['fullBleed']);

$pick = static function (string $key, array $allowed, string $default) use ($attributes): string {
    return in_array($attributes[$key] ?? '', $allowed, true) ? $attributes[$key] : $default;
};
$percent = static function (string $key, int $default, int $min, int $max) use ($attributes): int {
    return max($min, min($max, intval($attributes[$key] ?? $default)));
};

$hex_to_rgba = static function (string $hex, int $opacity_percent): string {
    $hex = preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '#000000';
    return sprintf(
        'rgba(%d,%d,%d,%s)',
        hexdec(substr($hex, 1, 2)),
        hexdec(substr($hex, 3, 2)),
        hexdec(substr($hex, 5, 2)),
        max(0, min(100, $opacity_percent)) / 100
    );
};

$overlay_from = $hex_to_rgba($attributes['overlayColor'] ?? '#0f1f13', $percent('overlayOpacity', 45, 0, 90));
if ($pick('overlayType', ['solid', 'gradient'], 'solid') === 'gradient') {
    $overlay_to = $hex_to_rgba($attributes['overlayColor2'] ?? '#000000', $percent('overlayOpacity2', 0, 0, 90));
    $direction = $pick('overlayDirection', ['0', '90', '180', '270'], '180');
} else {
    $overlay_to = $overlay_from;
    $direction = '180';
}
$overlay_style = 'background:linear-gradient(' . $direction . 'deg, ' . $overlay_from . ' 0%, ' . $overlay_to . ' 100%);';

// Vị trí/căn lề khung chữ theo từng màn hình: vị trí → class (khác thuộc
// tính CSS giữa top/center/bottom), khoảng cách + độ rộng + căn chữ → biến CSS.
$tiers = ['pc' => '', 'tablet' => 'Tablet', 'mobile' => 'Mobile'];
$vertical_defaults = ['pc' => 'bottom', 'tablet' => 'center', 'mobile' => 'center'];
$offset_y_defaults = ['pc' => 20, 'tablet' => 10, 'mobile' => 10];
$classes = ['block-top-hero', 'block-top-hero--' . $height_mode];
$content_vars = '';

if ($full_bleed) {
    $classes[] = 'block-top-hero--bleed';
}

// Hiệu ứng (JS: resources/scripts/theme/components/hero-effects.js).
// Parallax: ảnh phóng to sẵn đúng phần sẽ dịch chuyển để không lộ mép khi trôi.
$parallax_strength = 0;
if (!empty($attributes['parallax']) && $hero_image_url) {
    $parallax_strength = $percent('parallaxStrength', 30, 10, 50);
    $classes[] = 'block-top-hero--parallax';
}
if (!empty($attributes['zoomIn']) && $hero_image_url) {
    $classes[] = 'block-top-hero--zoom';
}
if (!empty($attributes['textReveal'])) {
    $classes[] = 'block-top-hero--reveal';
}
$darken_max = 0;
if (!empty($attributes['scrollDarken'])) {
    $darken_max = $percent('scrollDarkenMax', 60, 10, 90);
    $classes[] = 'block-top-hero--darken';
}

foreach ($tiers as $tier => $suffix) {
    $classes[] = 'block-top-hero--v-' . $tier . '-' . $pick('contentVertical' . $suffix, ['top', 'center', 'bottom'], $vertical_defaults[$tier]);
    $classes[] = 'block-top-hero--h-' . $tier . '-' . $pick('contentHorizontal' . $suffix, ['left', 'center', 'right'], 'center');

    $content_vars .= '--oy-' . $tier . ':' . $percent('offsetY' . $suffix, $offset_y_defaults[$tier], 0, 45) . '%;';
    $content_vars .= '--ox-' . $tier . ':' . $percent('offsetX' . $suffix, 5, 0, 45) . '%;';
    $content_vars .= '--ta-' . $tier . ':' . $pick('textAlign' . $suffix, ['left', 'center', 'right', 'justify'], 'center') . ';';

    // Không có default trong block.json — bỏ trống thì CSS giữ độ rộng cũ
    // theo breakpoint (60% / 80% laptop / 90% tablet-mobile).
    if (isset($attributes['textWidth' . $suffix])) {
        $content_vars .= '--tw-' . $tier . ':' . $percent('textWidth' . $suffix, 60, 20, 100) . '%;';
    }
}

$wrapper_attrs = get_block_wrapper_attributes(['class' => implode(' ', $classes)]);

if (!$full_bleed) {
    // Loại bỏ class alignfull / alignwide khỏi wrapper khi fullBleed tắt
    $wrapper_attrs = preg_replace('/\b(alignfull|alignwide)\b/', '', $wrapper_attrs);
    // Dọn dẹp khoảng trắng thừa trong class attribute nếu có
    $wrapper_attrs = preg_replace('/class="\s+/', 'class="', $wrapper_attrs);
    $wrapper_attrs = preg_replace('/\s+"/', '"', $wrapper_attrs);
    $wrapper_attrs = preg_replace('/\s{2,}/', ' ', $wrapper_attrs);
} else {
    // Đảm bảo có class alignfull trên root khi fullBleed bật
    if (!preg_match('/\balignfull\b/', $wrapper_attrs)) {
        if (preg_match('/class="([^"]*)"/', $wrapper_attrs)) {
            $wrapper_attrs = preg_replace('/class="([^"]*)"/', 'class="$1 alignfull"', $wrapper_attrs);
        } else {
            $wrapper_attrs .= ' class="alignfull"';
        }
    }
}

// Chế độ "custom": section có chiều cao cố định qua inline style, ảnh phủ
// kín bằng object-fit:cover. Chế độ "full": không set chiều cao — ảnh nằm
// trong luồng (height:auto), section tự co theo đúng kích thước ảnh gốc.
// Trường hợp "full" mà chưa có ảnh thì fallback 1 chiều cao tối thiểu để
// không bị sập xuống 0px.
$section_style = $height_mode === 'custom'
    ? 'min-height:' . $min_height . 'px;'
    : (!$hero_image_url ? 'min-height:400px;' : '');
if ($parallax_strength) {
    $section_style .= '--parallax-scale:' . (1 + $parallax_strength / 100) . ';';
}

// Đếm số lượng block hero trên cùng trang: chỉ block đầu tiên mới eager + fetchpriority="high"
static $lix_hero_instance = 0;
$lix_hero_instance++;
$is_first_hero = ($lix_hero_instance === 1);
$hero_loading = $is_first_hero ? 'eager' : 'lazy';
$hero_priority = $is_first_hero ? 'high' : 'auto';
?>
<section <?php echo $wrapper_attrs; ?> style="<?php echo esc_attr($section_style); ?>"<?php if ($parallax_strength) : ?> data-parallax="<?php echo esc_attr($parallax_strength / 100); ?>"<?php endif; ?><?php if ($darken_max) : ?> data-darken="<?php echo esc_attr($darken_max / 100); ?>"<?php endif; ?>>
    <?php
    $hero_image_id = intval($attributes['heroImageId'] ?? 0);
    if ($hero_image_id) {
        echo wp_get_attachment_image($hero_image_id, 'full', false, [
            'class'         => 'block-top-hero__bg',
            'alt'           => '',
            'loading'       => $hero_loading,
            'fetchpriority' => $hero_priority,
            'decoding'      => 'async',
            'sizes'         => '100vw',
        ]);
    } elseif ($hero_image_url) {
        ?>
        <img src="<?php echo $hero_image_url; ?>" alt="" class="block-top-hero__bg" loading="<?php echo esc_attr($hero_loading); ?>" fetchpriority="<?php echo esc_attr($hero_priority); ?>" decoding="async" />
        <?php
    }
    ?>
    <div class="block-top-hero__overlay" style="<?php echo esc_attr($overlay_style); ?>"></div>
    <?php if ($darken_max) : ?>
        <div class="block-top-hero__shade" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if ($full_bleed) : ?>
    <div class="container-fluid block-top-hero__content-wrap">
    <?php endif; ?>
        <div class="block-top-hero__content" style="<?php echo esc_attr($content_vars); ?>">
            <?php if ($headline) : ?>
                <h1 class="block-top-hero__headline"><?php echo $headline; ?></h1>
            <?php endif; ?>
            <?php if ($description) : ?>
                <div class="block-top-hero__desc"><?php echo $description; ?></div>
            <?php endif; ?>
        </div>
    <?php if ($full_bleed) : ?>
    </div>
    <?php endif; ?>
</section>
