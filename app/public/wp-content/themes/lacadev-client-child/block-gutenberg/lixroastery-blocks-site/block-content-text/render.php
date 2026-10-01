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
$content_align_tablet = in_array($attributes['contentAlignTablet'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignTablet']
    : 'center';
$content_align_mobile = in_array($attributes['contentAlignMobile'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignMobile']
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

// Font chữ trích dẫn (chỉ variant "quote") — whitelist-validate trước khi
// tin, vì attribute lưu trong post_content và có thể bị chỉnh sửa bất
// thường qua REST API; không whitelist thì 1 chuỗi tuỳ ý có thể lọt vào
// URL/CSS gửi cho mọi khách xem trang (xem app/helpers/google-fonts-vi.php).
$quote_font_family = (string) ($attributes['quoteFontFamily'] ?? '');
if (!in_array($quote_font_family, laca_google_fonts_vi_list(), true)) {
    $quote_font_family = '';
}
$quote_font_size        = max(16, min(64, (int) ($attributes['quoteFontSize'] ?? 40)));
$quote_font_size_tablet = max(16, min(64, (int) ($attributes['quoteFontSizeTablet'] ?? 32)));
$quote_font_size_mobile = max(16, min(64, (int) ($attributes['quoteFontSizeMobile'] ?? 24)));

$quote_style = '';
if ($variant === 'quote') {
    $quote_style = 'text-align: ' . esc_attr($text_align) . ';'
        . ' --quote-fs-pc: ' . esc_attr($quote_font_size) . 'px;'
        . ' --quote-fs-tablet: ' . esc_attr($quote_font_size_tablet) . 'px;'
        . ' --quote-fs-mobile: ' . esc_attr($quote_font_size_mobile) . 'px;';
    if ($quote_font_family) {
        $quote_style .= " --quote-font-family: {$quote_font_family};";
    }
}

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'block-content-text block-content-text--' . $variant . ' container-fluid',
]);
?>
<?php if ($variant === 'quote' && $quote_font_family) : ?>
    <link rel="stylesheet" href="<?php echo esc_url(laca_google_font_css_url($quote_font_family)); ?>">
<?php endif; ?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-content-text__maxwidth" style="--mw-pc: <?php echo esc_attr($max_width); ?>%; --mw-tablet: <?php echo esc_attr($max_width_tablet); ?>%; --mw-mobile: <?php echo esc_attr($max_width_mobile); ?>%; --align-margin-pc:<?php echo esc_attr($margin_map[$content_align]); ?>;--align-margin-tablet:<?php echo esc_attr($margin_map[$content_align_tablet]); ?>;--align-margin-mobile:<?php echo esc_attr($margin_map[$content_align_mobile]); ?>;">
        <div class="block-content-text__body block-content-text__body--<?php echo esc_attr($variant); ?>" style="<?php echo $variant === 'quote' ? $quote_style : 'text-align: ' . esc_attr($text_align) . ';'; ?>">
            <?php echo $content; ?>
        </div>
    </div>
</section>
