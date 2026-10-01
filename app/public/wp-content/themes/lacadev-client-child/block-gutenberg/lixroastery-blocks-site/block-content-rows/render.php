<?php
if (!defined('ABSPATH')) {
    exit;
}

// mainTitle/subTitle/content/quote*... được nhập trực tiếp trong canvas qua
// RichText (edit.js) nên đã là HTML an toàn (RichText tự escape nội dung),
// chỉ cần lọc qua wp_kses_post()/esc_html() trước khi in ra tương ứng.
$main_title     = wp_kses_post($attributes['mainTitle'] ?? '');
$max_width      = max(10, min(100, (int) ($attributes['maxWidth'] ?? 50)));
$max_width_tablet = max(10, min(100, (int) ($attributes['maxWidthTablet'] ?? 100)));
$max_width_mobile = max(10, min(100, (int) ($attributes['maxWidthMobile'] ?? 100)));
$content_align  = in_array($attributes['contentAlign'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlign']
    : 'center';
$content_align_tablet = in_array($attributes['contentAlignTablet'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignTablet']
    : 'center';
$content_align_mobile = in_array($attributes['contentAlignMobile'] ?? '', ['left', 'center', 'right'], true)
    ? $attributes['contentAlignMobile']
    : 'center';
$margin_map     = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];
$rows           = is_array($attributes['rows'] ?? null) ? $attributes['rows'] : [];

if (empty($rows) && !$main_title) {
    return;
}

// Font chữ trích dẫn — mỗi đoạn "quote" có thể chọn 1 font KHÁC nhau, gom
// danh sách font (không trùng, đã whitelist-validate) để in <link> 1 LẦN
// cho mỗi font, tránh trùng request nếu nhiều đoạn dùng chung 1 font. Xem
// app/helpers/google-fonts-vi.php để biết lý do bắt buộc whitelist (không
// chỉ escape) trước khi dùng giá trị attribute trong URL/CSS.
$quote_fonts_needed = [];
foreach ($rows as $row) {
    if (($row['asideType'] ?? 'none') !== 'quote') {
        continue;
    }
    $fam = (string) ($row['quoteFontFamily'] ?? '');
    if (in_array($fam, laca_google_fonts_vi_list(), true) && !in_array($fam, $quote_fonts_needed, true)) {
        $quote_fonts_needed[] = $fam;
    }
}

// Section ngoài LUÔN container-fluid — độ rộng NỘI DUNG điều chỉnh riêng qua
// maxWidth (%) + contentAlign, giống hệt block Container.
$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-content-rows container-fluid']);
?>
<?php foreach ($quote_fonts_needed as $fam) : ?>
    <link rel="stylesheet" href="<?php echo esc_url(laca_google_font_css_url($fam)); ?>">
<?php endforeach; ?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-content-rows__maxwidth" style="--mw-pc:<?php echo esc_attr($max_width); ?>%;--mw-tablet:<?php echo esc_attr($max_width_tablet); ?>%;--mw-mobile:<?php echo esc_attr($max_width_mobile); ?>%;--align-margin-pc:<?php echo esc_attr($margin_map[$content_align]); ?>;--align-margin-tablet:<?php echo esc_attr($margin_map[$content_align_tablet]); ?>;--align-margin-mobile:<?php echo esc_attr($margin_map[$content_align_mobile]); ?>;">
        <?php if ($main_title) : ?>
            <h2 class="block-content-rows__main-title"><?php echo $main_title; ?></h2>
        <?php endif; ?>

        <?php foreach ($rows as $row) :
            $sub_title   = wp_kses_post($row['subTitle'] ?? '');
            $content     = wp_kses_post($row['content'] ?? '');
            $aside_type  = in_array($row['asideType'] ?? 'none', ['image', 'quote'], true) ? $row['asideType'] : 'none';
            $aside_images = is_array($row['asideImages'] ?? null) ? $row['asideImages'] : [];
            $quote_text   = wp_kses_post($row['quoteText'] ?? '');
            $quote_author = wp_kses_post($row['quoteAuthor'] ?? '');
            $quote_source = wp_kses_post($row['quoteSource'] ?? '');
            $quote_align  = in_array($row['quoteAlign'] ?? 'left', ['left', 'center', 'right', 'justify'], true)
                ? $row['quoteAlign']
                : 'left';
            $quote_font_family = (string) ($row['quoteFontFamily'] ?? '');
            if (!in_array($quote_font_family, laca_google_fonts_vi_list(), true)) {
                $quote_font_family = '';
            }
            $quote_font_size        = max(16, min(64, (int) ($row['quoteFontSize'] ?? 40)));
            $quote_font_size_tablet = max(16, min(64, (int) ($row['quoteFontSizeTablet'] ?? 32)));
            $quote_font_size_mobile = max(16, min(64, (int) ($row['quoteFontSizeMobile'] ?? 24)));
            $quote_text_style = 'text-align:' . esc_attr($quote_align) . ';'
                . '--quote-fs-pc:' . esc_attr($quote_font_size) . 'px;'
                . '--quote-fs-tablet:' . esc_attr($quote_font_size_tablet) . 'px;'
                . '--quote-fs-mobile:' . esc_attr($quote_font_size_mobile) . 'px;';
            if ($quote_font_family) {
                $quote_text_style .= '--quote-font-family:' . $quote_font_family . ';';
            }

            if (!$sub_title && !$content && $aside_type === 'none') {
                continue;
            }
        ?>
            <div class="block-content-rows__row">
                <div class="block-content-rows__content">
                    <?php if ($sub_title) : ?>
                        <h3 class="block-content-rows__sub-title"><?php echo $sub_title; ?></h3>
                    <?php endif; ?>
                    <?php if ($content) : ?>
                        <div class="block-content-rows__text"><?php echo $content; ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($aside_type !== 'none') : ?>
                    <div class="block-content-rows__aside">
                        <?php if ($aside_type === 'image') : ?>
                            <?php foreach ($aside_images as $img) :
                                $image_url = esc_url($img['imageUrl'] ?? '');
                                $img_title = wp_kses_post($img['title'] ?? '');
                                $img_desc  = wp_kses_post($img['desc'] ?? '');
                                $img_link  = esc_url($img['link'] ?? '');
                                if (!$image_url && !$img_title && !$img_desc) {
                                    continue;
                                }
                                $tag = $img_link ? 'a' : 'div';
                            ?>
                                <<?php echo $tag; ?> <?php echo $img_link ? 'href="' . $img_link . '"' : ''; ?> class="block-content-rows__image-card">
                                    <?php if ($image_url) : ?>
                                        <img src="<?php echo $image_url; ?>" alt="<?php echo esc_attr(wp_strip_all_tags($img_title)); ?>" loading="lazy" />
                                    <?php endif; ?>
                                    <?php if ($img_title) : ?>
                                        <h4 class="block-content-rows__image-title"><?php echo $img_title; ?></h4>
                                    <?php endif; ?>
                                    <?php if ($img_desc) : ?>
                                        <p class="block-content-rows__image-desc"><?php echo $img_desc; ?></p>
                                    <?php endif; ?>
                                </<?php echo $tag; ?>>
                            <?php endforeach; ?>
                        <?php elseif ($aside_type === 'quote') : ?>
                            <blockquote class="block-content-rows__quote">
                                <?php if ($quote_text) : ?>
                                    <p class="block-content-rows__quote-text" style="<?php echo $quote_text_style; ?>"><?php echo $quote_text; ?></p>
                                <?php endif; ?>
                                <?php if ($quote_author) : ?>
                                    <cite class="block-content-rows__quote-author"><?php echo $quote_author; ?></cite>
                                <?php endif; ?>
                                <?php if ($quote_source) : ?>
                                    <p class="block-content-rows__quote-source"><?php echo $quote_source; ?></p>
                                <?php endif; ?>
                            </blockquote>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
