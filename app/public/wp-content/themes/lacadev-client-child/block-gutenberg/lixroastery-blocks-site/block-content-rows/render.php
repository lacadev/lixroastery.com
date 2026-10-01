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
$margin_map     = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];
$rows           = is_array($attributes['rows'] ?? null) ? $attributes['rows'] : [];

if (empty($rows) && !$main_title) {
    return;
}

// Section ngoài LUÔN container-fluid — độ rộng NỘI DUNG điều chỉnh riêng qua
// maxWidth (%) + contentAlign, giống hệt block Container.
$wrapper_attrs = get_block_wrapper_attributes(['class' => 'block-content-rows container-fluid']);
?>
<section <?php echo $wrapper_attrs; ?>>
    <div class="block-content-rows__maxwidth" style="--mw-pc:<?php echo esc_attr($max_width); ?>%;--mw-tablet:<?php echo esc_attr($max_width_tablet); ?>%;--mw-mobile:<?php echo esc_attr($max_width_mobile); ?>%;margin:<?php echo esc_attr($margin_map[$content_align]); ?>;">
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
                                    <p class="block-content-rows__quote-text"><?php echo $quote_text; ?></p>
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
