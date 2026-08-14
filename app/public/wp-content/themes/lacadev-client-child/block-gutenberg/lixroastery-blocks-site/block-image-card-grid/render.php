<?php
if (!defined('ABSPATH')) {
    exit;
}

$layout_mode  = ($attributes['layoutMode'] ?? 'grid') === 'checkerboard' ? 'checkerboard' : 'grid';
$columns      = max(2, min(4, intval($attributes['columns'] ?? 3)));
$aspect_ratio = preg_match('/^\d+:\d+$/', $attributes['aspectRatio'] ?? '') ? $attributes['aspectRatio'] : '3:4';
$items        = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];
$is_checkerboard = $layout_mode === 'checkerboard';

$bg_color = preg_match('/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '')
    ? $attributes['bgColor']
    : '#2f4a34';

if (empty($items)) {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-image-card-grid']);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="container-fluid">
        <div class="block-image-card-grid__grid<?php echo $is_checkerboard ? ' block-image-card-grid__grid--checkerboard' : ''; ?>" <?php echo $is_checkerboard ? '' : 'style="--icg-columns: ' . esc_attr($columns) . '; --icg-ratio: ' . esc_attr(str_replace(':', '/', $aspect_ratio)) . ';"'; ?>>
            <?php foreach ($items as $index => $item) :
                $image_url   = esc_url($item['imageUrl'] ?? '');
                $title       = esc_html($item['title'] ?? '');
                // desc cho phép format cơ bản (in đậm/nghiêng…) qua RichText.
                $desc        = wp_kses_post($item['desc'] ?? '');
                $link        = esc_url($item['link'] ?? '');
                $link_target = ($item['linkTarget'] ?? '_self') === '_blank' ? '_blank' : '_self';
                if (!$title && !$desc && !$image_url) {
                    continue;
                }
                $tag = $link ? 'a' : 'div';
                // Xen kẽ vuông (30%)/chữ nhật (70%) theo checkerboard 2 cột —
                // cùng công thức vị trí đã dùng ở Coffee Journal
                // (theme/single-product.php), chỉ đổi từ aspect-ratio độc
                // lập sang chia cột 30/70 + chiều cao bằng nhau trong 1 dòng.
                $is_tall  = $is_checkerboard && (bool) ((intdiv($index, 2) + $index) % 2);
                $col_span = $is_tall ? 6 : 4;
            ?>
                <div class="block-image-card-grid__card" <?php echo $is_checkerboard ? 'style="grid-column: span ' . $col_span . ';"' : ''; ?>>
                    <<?php echo $tag; ?> <?php echo $link ? 'href="' . $link . '" target="' . esc_attr($link_target) . '"' . ($link_target === '_blank' ? ' rel="noopener noreferrer"' : '') : ''; ?> class="block-image-card-grid__image<?php echo $is_tall ? ' block-image-card-grid__image--tall' : ''; ?>" style="<?php echo $image_url ? '' : 'background:' . esc_attr($bg_color) . ';'; ?>">
                        <?php if ($image_url) : ?>
                            <img src="<?php echo $image_url; ?>" alt="<?php echo $title; ?>" loading="lazy" />
                        <?php endif; ?>
                        <div class="block-image-card-grid__overlay"></div>
                        <div class="block-image-card-grid__content">
                            <?php if ($title) : ?>
                                <h3 class="block-image-card-grid__title"><?php echo $title; ?></h3>
                            <?php endif; ?>
                            <?php if ($desc) : ?>
                                <p class="block-image-card-grid__desc"><?php echo $desc; ?></p>
                            <?php endif; ?>
                        </div>
                    </<?php echo $tag; ?>>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
