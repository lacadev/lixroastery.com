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
$align = in_array($attributes['contentAlign'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlign']
    : 'center';
$align_tablet = in_array($attributes['contentAlignTablet'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignTablet']
    : 'center';
$align_mobile = in_array($attributes['contentAlignMobile'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignMobile']
    : 'center';

$margin_map = [
    'left' => '0 auto 0 0',
    'center' => '0 auto',
    'right' => '0 0 0 auto',
];

if (trim($content) === '') {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'block-container',
    'style' => '--mw-pc:' . $max_width . '%;--mw-tablet:' . $max_width_tablet . '%;--mw-mobile:' . $max_width_mobile . '%;'
        . '--align-margin-pc:' . $margin_map[$align] . ';--align-margin-tablet:' . $margin_map[$align_tablet] . ';--align-margin-mobile:' . $margin_map[$align_mobile] . ';',
]);
?>

<section class="container-fluid">
    <div <?php echo $wrapper_attrs; ?>>
        <?php echo $content; ?>
    </div>
</section>