<?php
/**
 * App Layout: layouts/app.php
 *
 * Template chi tiết sản phẩm WooCommerce — thay thế toàn bộ template mặc
 * định của WooCommerce (WC_Template_Loader ưu tiên single-{post_type}.php
 * của theme trước woocommerce/single-product.php).
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

global $product;
if (!is_a($product, 'WC_Product')) {
    $product = wc_get_product(get_the_ID());
}

$post_id = get_the_ID();

// ── Producer ─────────────────────────────────────────────────────────────
$producer_story         = getPostMeta('producer_story');
$first_cooperation_year = getPostMeta('first_cooperation_year');
$cooperation_model      = getPostMeta('cooperation_model');
$annual_volume          = getPostMeta('annual_volume');
$google_maps_embed      = getPostMeta('google_maps_embed');

$traceability_fields = [
    'origin'             => __('Origin', 'laca'),
    'region'             => __('Region', 'laca'),
    'farm'               => __('Farm', 'laca'),
    'cooperative'        => __('Cooperative', 'laca'),
    'wet_mill'           => __('Wet Mill', 'laca'),
    'factory'            => __('Factory', 'laca'),
    'variety'            => __('Variety', 'laca'),
    'crop_year'          => __('Crop year', 'laca'),
    'altitude'           => __('Altitude', 'laca'),
    'process'            => __('Process', 'laca'),
    'net_weight'         => __('Net weight', 'laca'),
    'sourcing'           => __('Sourcing', 'laca'),
    'paid_for_producer'  => __('Paid for producer', 'laca'),
    'paid_for_exporter'  => __('Paid for exporter', 'laca'),
    'fob_price'          => __('FOB price', 'laca'),
    'ddp_price'          => __('DDP price', 'laca'),
];

// ── Flavor profile ───────────────────────────────────────────────────────
$flavor_profile   = getPostMeta('flavor_profile');
$sca_coffee_score = getPostMeta('sca_coffee_score');
$flavor_grid = [
    'flavor_aroma'      => __('Aroma', 'laca'),
    'flavor_hot'        => __('Hot', 'laca'),
    'flavor_warm'       => __('Warm', 'laca'),
    'flavor_cool'       => __('Cool', 'laca'),
    'flavor_aftertaste' => __('Aftertaste', 'laca'),
    'flavor_acidity'    => __('Acidity', 'laca'),
    'flavor_sweetness'  => __('Sweetness', 'laca'),
    'flavor_mouthfeel'  => __('Mouthfeel', 'laca'),
];

// ── Roasting profile ─────────────────────────────────────────────────────
$title_roasting_profile = getPostMeta('title_roasting_profile') ?: __('Roasting profile', 'laca');
$img_roasting_profile   = getPostMeta('img_roasting_profile');
$roast_machine          = getPostMeta('roast_machine');
$roast_level            = getPostMeta('roast_level');
$end_air_temperature    = getPostMeta('end_air_temperature');
$roasting_detail_fields = [
    'roasting_profile'         => __('Roast Profile', 'laca'),
    'roast_duration'           => __('Roast Duration', 'laca'),
    'drying_phase'             => __('Drying Phase', 'laca'),
    'maillard_phase'           => __('Maillard Phase', 'laca'),
    'development_phase'        => __('Development Phase', 'laca'),
    'development_ratio'        => __('Development Ratio', 'laca'),
    'charge_temperature'       => __('Charge Temperature', 'laca'),
    'turning_point'            => __('Turning Point', 'laca'),
    'dry_end_temperature'      => __('Dry-end Temperature', 'laca'),
    'first_crack_temperature'  => __('First Crack Temperature', 'laca'),
    'end_bean_temperature'     => __('End Bean Temperature', 'laca'),
];

// ── Coffee journal — nội dung trên/dưới + các ô hình ảnh/tiêu đề/mô tả ───
$journal_content_top    = getPostMeta('journal_content_top');
$journal_items          = getPostMeta('journal_items');
$journal_items          = is_array($journal_items) ? $journal_items : [];
$journal_content_bottom = getPostMeta('journal_content_bottom');

// ── How to use + Water quality — dùng chung toàn site (Theme Options) ────
$how_to_use_title       = getOption('how_to_use_title');
$how_to_use_content     = getOption('how_to_use_content');
$water_quality_title    = getOption('water_quality_title');
$water_quality_items    = [
    'water_quality_calcium'    => __('Calcium Hardness', 'laca'),
    'water_quality_magnesium'  => __('Magnesium Hardness', 'laca'),
    'water_quality_alkalinity' => __('Total Alkalinity', 'laca'),
    'water_quality_sodium'     => __('Sodium', 'laca'),
];

// ── Reviews — carousel testimonial dùng chung toàn site ───────────────────
$reviews_list = getOption('reviews_list');
$reviews_list = is_array($reviews_list) ? $reviews_list : [];
?>

<article class="single-product-page">

    <nav class="single-product-page__nav">
        <a href="#producer"><?php _e('Producer', 'laca'); ?></a>
        <span>|</span>
        <a href="#flavor-profile"><?php _e('Flavor Profile', 'laca'); ?></a>
        <span>|</span>
        <a href="#roasting-profile"><?php _e('Roasting Profile', 'laca'); ?></a>
        <span>|</span>
        <a href="#coffee-journal"><?php _e('Coffee Journal', 'laca'); ?></a>
        <span>|</span>
        <a href="#how-to-use"><?php _e('How to Use', 'laca'); ?></a>
        <span>|</span>
        <a href="#reviews"><?php _e('Reviews', 'laca'); ?></a>
    </nav>

    <div class="container single-product-page__hero">
        <div class="single-product-page__gallery">
            <?php woocommerce_show_product_sale_flash(); ?>
            <?php woocommerce_show_product_images(); ?>
        </div>
        <div class="single-product-page__summary">
            <?php if ($subname = getPostMeta('subname')) : ?>
                <p class="single-product-page__subname"><?php echo esc_html($subname); ?></p>
            <?php endif; ?>
            <?php woocommerce_template_single_title(); ?>
            <?php woocommerce_template_single_price(); ?>
            <?php woocommerce_template_single_excerpt(); ?>
            <?php woocommerce_template_single_add_to_cart(); ?>
        </div>
    </div>

    <!-- ── PRODUCER ─────────────────────────────────────────────────────── -->
    <section id="producer" class="single-product-page__section single-product-page__section--dark-media">
        <div class="container">
            <h2 class="single-product-page__title"><?php the_title(); ?></h2>
            <?php if ($producer_story) : ?>
                <p class="single-product-page__desc"><?php echo esc_html($producer_story); ?></p>
            <?php endif; ?>

            <?php if ($first_cooperation_year || $cooperation_model || $annual_volume) : ?>
                <div class="single-product-page__stats">
                    <?php if ($first_cooperation_year) : ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($first_cooperation_year); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('First time cooperation', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($cooperation_model) : ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($cooperation_model); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('Cooperation model', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($annual_volume) : ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($annual_volume); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('Annual volume', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($google_maps_embed) : ?>
            <div class="single-product-page__media">
                <iframe src="<?php echo esc_url($google_maps_embed); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
        <?php endif; ?>

        <?php
        $has_traceability = false;
        foreach ($traceability_fields as $key => $label) {
            if (getPostMeta($key)) {
                $has_traceability = true;
                break;
            }
        }
        ?>
        <?php if ($has_traceability) : ?>
            <div class="container">
                <ul class="single-product-page__info-list">
                    <?php foreach ($traceability_fields as $key => $label) :
                        $value = getPostMeta($key);
                        if (!$value) {
                            continue;
                        }
                    ?>
                        <li><span><?php echo esc_html($label); ?></span><span><?php echo esc_html($value); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <!-- ── FLAVOR PROFILE ───────────────────────────────────────────────── -->
    <section id="flavor-profile" class="single-product-page__section">
        <div class="container">
            <h2 class="single-product-page__title"><?php _e('Flavor Profile', 'laca'); ?></h2>
            <?php if ($flavor_profile) : ?>
                <p class="single-product-page__desc"><?php echo esc_html($flavor_profile); ?></p>
            <?php endif; ?>
            <?php if ($sca_coffee_score) : ?>
                <p class="single-product-page__sca">SCA: <?php echo esc_html($sca_coffee_score); ?></p>
            <?php endif; ?>

            <div class="single-product-page__flavor-grid">
                <?php foreach ($flavor_grid as $key => $label) :
                    $value = getPostMeta($key);
                    if (!$value) {
                        continue;
                    }
                ?>
                    <div class="single-product-page__flavor-item">
                        <span class="single-product-page__flavor-label"><?php echo esc_html($label); ?></span>
                        <span class="single-product-page__flavor-value"><?php echo esc_html($value); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ── ROASTING PROFILE ─────────────────────────────────────────────── -->
    <section id="roasting-profile" class="single-product-page__section single-product-page__section--dark-media">
        <div class="container">
            <h2 class="single-product-page__title"><?php echo esc_html($title_roasting_profile); ?></h2>
            <?php if ($producer_story) : ?>
                <p class="single-product-page__desc"><?php echo esc_html($producer_story); ?></p>
            <?php endif; ?>

            <?php if ($roast_machine || $roast_level || $end_air_temperature) : ?>
                <div class="single-product-page__stats">
                    <?php if ($roast_machine) : ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($roast_machine); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('Roast Machine', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($roast_level) : ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($roast_level); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('Roast Level', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($end_air_temperature) : ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($end_air_temperature); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('End Temperature', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($img_roasting_profile) : ?>
            <div class="single-product-page__media">
                <?php echo wp_get_attachment_image($img_roasting_profile, 'full'); ?>
            </div>
        <?php endif; ?>

        <?php
        $has_roasting_detail = false;
        foreach ($roasting_detail_fields as $key => $label) {
            if (getPostMeta($key)) {
                $has_roasting_detail = true;
                break;
            }
        }
        ?>
        <?php if ($has_roasting_detail) : ?>
            <div class="container">
                <ul class="single-product-page__info-list">
                    <?php foreach ($roasting_detail_fields as $key => $label) :
                        $value = getPostMeta($key);
                        if (!$value) {
                            continue;
                        }
                    ?>
                        <li><span><?php echo esc_html($label); ?></span><span><?php echo esc_html($value); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <!-- ── COFFEE JOURNAL ───────────────────────────────────────────────── -->
    <?php if ($journal_content_top || $journal_content_bottom || !empty($journal_items)) : ?>
        <section id="coffee-journal" class="single-product-page__section">
            <div class="container">
                <h2 class="single-product-page__title"><?php _e('Coffee Journal', 'laca'); ?></h2>

                <?php if ($journal_content_top) : ?>
                    <div class="single-product-page__rich-text single-product-page__journal-content"><?php echo wp_kses_post($journal_content_top); ?></div>
                <?php endif; ?>

                <?php if (!empty($journal_items)) : ?>
                    <div class="single-product-page__journal-grid">
                        <?php foreach ($journal_items as $index => $item) :
                            $image = $item['image'] ?? 0;
                            $title = $item['title'] ?? '';
                            $desc  = $item['desc'] ?? '';
                            $link  = $item['link'] ?? '';
                            if (!$image && !$title && !$desc) {
                                continue;
                            }
                            // Xen kẽ chiều cao theo vị trí trong mỗi cặp cột (2 cột), khớp thiết kế nhỏ-to/to-nhỏ.
                            $is_tall = (bool) ((intdiv($index, 2) + $index) % 2);
                        ?>
                            <div class="single-product-page__journal-card<?php echo $is_tall ? ' single-product-page__journal-card--tall' : ''; ?>">
                                <?php if ($image) : ?>
                                    <div class="single-product-page__journal-thumb">
                                        <?php echo wp_get_attachment_image($image, 'medium_large', false, ['loading' => 'lazy']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($title) : ?>
                                    <h3 class="single-product-page__journal-title"><?php echo esc_html($title); ?></h3>
                                <?php endif; ?>
                                <?php if ($desc) : ?>
                                    <p class="single-product-page__journal-excerpt"><?php echo esc_html(wp_trim_words($desc, 20)); ?></p>
                                <?php endif; ?>
                                <?php if ($link) : ?>
                                    <a href="<?php echo esc_url($link); ?>" class="single-product-page__journal-link">» <?php _e('Bài viết chi tiết', 'laca'); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($journal_content_bottom) : ?>
                    <div class="single-product-page__rich-text single-product-page__journal-content single-product-page__journal-content--bottom"><?php echo wp_kses_post($journal_content_bottom); ?></div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ── HOW TO USE ───────────────────────────────────────────────────── -->
    <section id="how-to-use" class="single-product-page__section">
        <div class="container">
            <h2 class="single-product-page__title"><?php _e('How to Use', 'laca'); ?></h2>

            <?php if ($how_to_use_title || $how_to_use_content) : ?>
                <div class="single-product-page__how-to-use">
                    <?php if ($how_to_use_title) : ?>
                        <h3><?php echo esc_html($how_to_use_title); ?></h3>
                    <?php endif; ?>
                    <?php if ($how_to_use_content) : ?>
                        <div class="single-product-page__rich-text"><?php echo wp_kses_post($how_to_use_content); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            $has_water_quality = false;
            foreach ($water_quality_items as $key => $label) {
                if (getOption($key)) {
                    $has_water_quality = true;
                    break;
                }
            }
            ?>
            <?php if ($water_quality_title || $has_water_quality) : ?>
                <div class="single-product-page__water-quality">
                    <?php if ($water_quality_title) : ?>
                        <h3><?php echo esc_html($water_quality_title); ?></h3>
                    <?php endif; ?>
                    <div class="single-product-page__stats single-product-page__stats--4col">
                        <?php foreach ($water_quality_items as $key => $label) :
                            $value = getOption($key);
                            if (!$value) {
                                continue;
                            }
                        ?>
                            <div class="single-product-page__stat">
                                <span class="single-product-page__stat-label"><?php echo esc_html($label); ?></span>
                                <span class="single-product-page__stat-value single-product-page__stat-value--sm"><?php echo esc_html($value); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── REVIEWS ──────────────────────────────────────────────────────── -->
    <?php if (!empty($reviews_list)) : ?>
        <section id="reviews" class="single-product-page__section">
            <div class="container">
                <h2 class="single-product-page__title"><?php _e('Reviews', 'laca'); ?></h2>

                <div class="single-product-page__reviews-grid">
                    <?php foreach ($reviews_list as $review) :
                        $name    = $review['name'] ?? '';
                        $content = $review['content'] ?? '';
                        $link    = $review['link'] ?? '';
                        if (!$name && !$content) {
                            continue;
                        }
                        $tag = $link ? 'a' : 'div';
                    ?>
                        <<?php echo $tag; ?> <?php echo $link ? 'href="' . esc_url($link) . '" target="_blank" rel="noopener"' : ''; ?> class="single-product-page__review-card">
                            <div class="single-product-page__review-stars" aria-hidden="true">★★★★★</div>
                            <?php if ($name) : ?>
                                <h3 class="single-product-page__review-name"><?php echo esc_html($name); ?></h3>
                            <?php endif; ?>
                            <?php if ($content) : ?>
                                <p class="single-product-page__review-content"><?php echo esc_html(wp_trim_words($content, 25)); ?></p>
                            <?php endif; ?>
                        </<?php echo $tag; ?>>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

</article>
