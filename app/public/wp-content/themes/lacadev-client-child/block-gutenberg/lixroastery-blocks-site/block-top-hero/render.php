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

// Nền: 1 ảnh (mặc định, giữ hành vi cũ) / slide nhiều ảnh / video. Thiếu dữ
// liệu cho chế độ đã chọn thì rơi về ảnh đơn để hero không bị trống.
$media_type = $pick('mediaType', ['image', 'slider', 'video'], 'image');
$slides = [];
if ($media_type === 'slider') {
    foreach ((array) ($attributes['slides'] ?? []) as $slide) {
        $slide_id  = intval($slide['id'] ?? 0);
        $slide_url = esc_url_raw($slide['url'] ?? '');
        if ($slide_id || $slide_url) {
            $slides[] = ['id' => $slide_id, 'url' => $slide_url];
        }
    }
    if (count($slides) < 2) {
        if ($slides) {
            $hero_image_url = $slides[0]['url'];
            $attributes['heroImageId'] = $slides[0]['id'];
        }
        $slides = [];
        $media_type = 'image';
    }
}
$video_url = '';
$video_source = $pick('videoSource', ['upload', 'youtube'], 'upload');
$youtube_url = trim((string) ($attributes['youtubeUrl'] ?? ''));
$youtube_id = '';
if ($media_type === 'video') {
    if ($video_source === 'upload') {
        $video_id  = intval($attributes['videoId'] ?? 0);
        $video_url = $video_id ? (string) wp_get_attachment_url($video_id) : '';
        if (!$video_url) {
            $video_url = (string) ($attributes['videoUrl'] ?? '');
        }
        if (!$video_url) {
            $media_type = 'image';
        }
    } elseif ($video_source === 'youtube') {
        if ($youtube_url && preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/|youtube\.com/shorts/)([a-zA-Z0-9_-]{11})%i', $youtube_url, $m)) {
            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $m[1])) {
                $youtube_id = $m[1];
            }
        }
        if (!$youtube_id) {
            $media_type = 'image';
        }
    }
}
$has_media = $hero_image_url || $slides || $video_url || ($media_type === 'video' && $video_source === 'youtube' && $youtube_id);

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
$layout_class = function_exists('laca_block_layout_class') ? laca_block_layout_class($attributes) : '';
$classes = ['block-top-hero', 'block-top-hero--' . $height_mode];
if ($layout_class) {
    $classes[] = $layout_class;
}
$content_vars = '';

if ($full_bleed) {
    $classes[] = 'block-top-hero--bleed';
}

// Hiệu ứng (JS: resources/scripts/theme/components/hero-effects.js).
// Parallax: ảnh phóng to sẵn đúng phần sẽ dịch chuyển để không lộ mép khi trôi.
$parallax_strength = 0;
if (!empty($attributes['parallax']) && $has_media) {
    $parallax_strength = $percent('parallaxStrength', 30, 10, 50);
    $classes[] = 'block-top-hero--parallax';
}
if (!empty($attributes['zoomIn']) && $has_media) {
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
$media_class_suffix = ($media_type === 'video' && $video_source === 'youtube' && $youtube_id) ? 'youtube' : $media_type;
$classes[] = 'block-top-hero--media-' . $media_class_suffix;

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
    : (!$has_media ? 'min-height:400px;' : '');
if ($parallax_strength) {
    $section_style .= '--parallax-scale:' . (1 + $parallax_strength / 100) . ';';
}

// Đếm số lượng block hero trên cùng trang: chỉ block đầu tiên mới eager + fetchpriority="high"
static $lix_hero_instance = 0;
$lix_hero_instance++;
$is_first_hero = ($lix_hero_instance === 1);
$hero_loading = $is_first_hero ? 'eager' : 'lazy';
$hero_priority = $is_first_hero ? 'high' : 'auto';

$render_bg_image = static function (int $id, string $url, string $class, string $loading, string $priority): void {
    if ($id) {
        echo wp_get_attachment_image($id, 'full', false, [
            'class'         => $class,
            'alt'           => '',
            'loading'       => $loading,
            'fetchpriority' => $priority,
            'decoding'      => 'async',
            'sizes'         => '100vw',
        ]);
    } elseif ($url) {
        ?>
        <img src="<?php echo esc_url($url); ?>" alt="" class="<?php echo esc_attr($class); ?>" loading="<?php echo esc_attr($loading); ?>" fetchpriority="<?php echo esc_attr($priority); ?>" decoding="async" />
        <?php
    }
};

$slider_attrs = '';
if ($slides) {
    $interval = max(2, min(20, intval($attributes['slideInterval'] ?? 5)));
    $slider_attrs = ' data-hero-slider data-interval="' . esc_attr($interval * 1000) . '"'
        . (($attributes['slideAutoplay'] ?? true) ? ' data-autoplay' : '');
}
?>
<section <?php echo $wrapper_attrs; ?> style="<?php echo esc_attr($section_style); ?>"<?php if ($parallax_strength) : ?> data-parallax="<?php echo esc_attr($parallax_strength / 100); ?>"<?php endif; ?><?php if ($darken_max) : ?> data-darken="<?php echo esc_attr($darken_max / 100); ?>"<?php endif; ?><?php echo $slider_attrs; ?>>
    <?php if ($slides) : ?>
        <div class="block-top-hero__slides" role="group" aria-roledescription="carousel" aria-label="<?php esc_attr_e('Ảnh nổi bật', 'laca'); ?>">
            <?php
            foreach ($slides as $i => $slide) {
                // Chỉ ảnh đầu được ưu tiên tải (LCP); các ảnh sau tải trễ.
                $render_bg_image(
                    $slide['id'],
                    $slide['url'],
                    'block-top-hero__bg block-top-hero__slide' . ($i === 0 ? ' is-active' : ''),
                    $i === 0 ? $hero_loading : 'lazy',
                    $i === 0 ? $hero_priority : 'auto'
                );
            }
            ?>
        </div>
    <?php elseif ($media_type === 'video' && $video_source === 'youtube' && $youtube_id) : ?>
        <?php if ($hero_image_url) : ?>
            <?php $render_bg_image(intval($attributes['heroImageId'] ?? 0), $hero_image_url, 'block-top-hero__bg block-top-hero__yt-poster', $hero_loading, $hero_priority); ?>
        <?php endif; ?>
        <iframe class="block-top-hero__bg block-top-hero__yt" src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($youtube_id); ?>?autoplay=1&mute=1&loop=1&playlist=<?php echo esc_attr($youtube_id); ?>&controls=0&modestbranding=1&rel=0&playsinline=1" allow="autoplay; encrypted-media" title="<?php esc_attr_e('Video nền', 'laca'); ?>" tabindex="-1" aria-hidden="true"<?php echo $is_first_hero ? '' : ' loading="lazy"'; ?>></iframe>
    <?php elseif ($video_url) : ?>
        <?php // muted + playsinline bắt buộc để trình duyệt (nhất là iOS) cho tự phát. ?>
        <video class="block-top-hero__bg block-top-hero__video" autoplay muted playsinline<?php echo ($attributes['videoLoop'] ?? true) ? ' loop' : ''; ?> preload="metadata"<?php if ($hero_image_url) : ?> poster="<?php echo esc_url($hero_image_url); ?>"<?php endif; ?> aria-hidden="true">
            <source src="<?php echo esc_url($video_url); ?>" type="<?php echo esc_attr(wp_check_filetype($video_url)['type'] ?: 'video/mp4'); ?>" />
        </video>
    <?php else : ?>
        <?php $render_bg_image(intval($attributes['heroImageId'] ?? 0), $hero_image_url, 'block-top-hero__bg', $hero_loading, $hero_priority); ?>
    <?php endif; ?>
    <div class="block-top-hero__overlay" style="<?php echo esc_attr($overlay_style); ?>"></div>
    <?php if ($darken_max) : ?>
        <div class="block-top-hero__shade" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if ($full_bleed) : ?>
    <div class="container-fluid block-top-hero__content-wrap<?php echo $layout_class ? ' ' . esc_attr($layout_class) : ''; ?>">
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

    <?php if ($slides && !empty($attributes['slideShowArrows'])) : ?>
        <button type="button" class="block-top-hero__arrow block-top-hero__arrow--prev" data-hero-prev aria-label="<?php esc_attr_e('Ảnh trước', 'laca'); ?>"><span aria-hidden="true">&#8249;</span></button>
        <button type="button" class="block-top-hero__arrow block-top-hero__arrow--next" data-hero-next aria-label="<?php esc_attr_e('Ảnh tiếp theo', 'laca'); ?>"><span aria-hidden="true">&#8250;</span></button>
    <?php endif; ?>
    <?php if ($slides && ($attributes['slideShowDots'] ?? true)) : ?>
        <div class="block-top-hero__dots">
            <?php foreach ($slides as $i => $slide) : ?>
                <button type="button" class="block-top-hero__dot<?php echo $i === 0 ? ' is-active' : ''; ?>" data-hero-dot="<?php echo esc_attr($i); ?>" aria-label="<?php /* translators: %d: số thứ tự ảnh trong slide */ echo esc_attr(sprintf(__('Xem ảnh %d', 'laca'), $i + 1)); ?>"<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($video_url && $video_source === 'upload') : ?>
        <?php // Video tự phát > 5 giây phải có nút dừng (WCAG 2.2.2). ?>
        <button type="button" class="block-top-hero__video-toggle" data-hero-video-toggle aria-pressed="false" aria-label="<?php esc_attr_e('Tạm dừng video', 'laca'); ?>" data-label-pause="<?php esc_attr_e('Tạm dừng video', 'laca'); ?>" data-label-play="<?php esc_attr_e('Phát video', 'laca'); ?>">
            <span class="block-top-hero__video-icon" aria-hidden="true"></span>
        </button>
    <?php endif; ?>
</section>
