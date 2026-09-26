<?php
if (!defined('ABSPATH')) {
    exit;
}

$columns        = max(1, min(4, intval($attributes['columns'] ?? 2)));
$container_type = ($attributes['containerType'] ?? 'container-fluid') === 'container' ? 'container' : 'container-fluid';
$items          = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

if (empty($items)) {
    return;
}

// containerType gắn thẳng vào section này (KHÔNG bọc thêm 1 div riêng) —
// giống class Bootstrap thật (.container/.container-fluid tự là khung
// ngoài cùng), tránh 1 lớp div thừa không cần thiết.
$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-content-grid ' . $container_type]);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-content-grid__grid" style="--ctg-columns: <?php echo esc_attr($columns); ?>;">
        <?php foreach ($items as $item) :
            $title = esc_html($item['title'] ?? '');
            // desc cho phép format cơ bản (in đậm/nghiêng…) qua RichText.
            $desc  = wp_kses_post($item['desc'] ?? '');
            if (!$title && !$desc) {
                continue;
            }
        ?>
            <div class="block-content-grid__item">
                <hr class="block-content-grid__rule" />
                <?php if ($title) : ?>
                    <h3 class="block-content-grid__title"><?php echo $title; ?></h3>
                <?php endif; ?>
                <?php if ($desc) : ?>
                    <p class="block-content-grid__desc"><?php echo $desc; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
