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
$producer_name = getPostMeta('producer_name') ?: get_the_title();
$producer_story = getPostMeta('producer_story');
$first_cooperation_year = getPostMeta('first_cooperation_year');
$cooperation_model = getPostMeta('cooperation_model');
$annual_volume = getPostMeta('annual_volume');
$annual_volume_note = getPostMeta('annual_volume_note') ?: __('Annual volume', 'laca');
$google_maps_embed = getPostMeta('google_maps_embed');

// ── Flavor profile ───────────────────────────────────────────────────────
$flavor_profile = getPostMeta('flavor_profile');
$flavor_profile_content = getPostMeta('flavor_profile_content');
$sca_coffee_score = getPostMeta('sca_coffee_score');

// Danh sách spec hiện NGAY TRONG HERO (cạnh ảnh sản phẩm)
$hero_spec_fields = [
    'origin' => __('Origin', 'laca'),
    'region' => __('Region', 'laca'),
    'farm' => __('Farm', 'laca'),
    'variety' => __('Variety', 'laca'),
    'crop_year' => __('Crop Year', 'laca'),
    'altitude' => __('Altitude', 'laca'),
    'process' => __('Process', 'laca'),
    'net_weight' => __('Net Weight', 'laca'),
    'flavor_profile' => __('Flavor Profile', 'laca'),
    'sourcing' => __('Sourcing', 'laca'),
    'paid_for_producer' => __('Paid for Producer', 'laca'),
];

// Các field truy xuất nguồn gốc CÒN LẠI (hiện trong tab Producer)
$traceability_fields = [
    'cooperative' => __('Cooperative', 'laca'),
    'wet_mill' => __('Wet Mill', 'laca'),
    'factory' => __('Factory', 'laca'),
    'paid_for_exporter' => __('Paid for exporter', 'laca'),
    'fob_price' => __('FOB price', 'laca'),
    'ddp_price' => __('DDP price', 'laca'),
];
$flavor_grid = [
    'flavor_aroma' => __('Aroma', 'laca'),
    'flavor_hot' => __('Hot', 'laca'),
    'flavor_warm' => __('Warm', 'laca'),
    'flavor_cool' => __('Cool', 'laca'),
    'flavor_aftertaste' => __('Aftertaste', 'laca'),
    'flavor_acidity' => __('Acidity', 'laca'),
    'flavor_sweetness' => __('Sweetness', 'laca'),
    'flavor_mouthfeel' => __('Mouthfeel', 'laca'),
];

// ── Roasting profile ─────────────────────────────────────────────────────
$title_roasting_profile = getPostMeta('title_roasting_profile') ?: __('Roasting profile', 'laca');
$img_roasting_profile = getPostMeta('img_roasting_profile');
$roasting_story = getPostMeta('roasting_story') ?: $producer_story;
$roast_machine = getPostMeta('roast_machine');
$roast_level = getPostMeta('roast_level');
$end_air_temperature = getPostMeta('end_air_temperature');
$roasting_detail_fields = [
    'roasting_profile' => __('Roast Profile', 'laca'),
    'roast_duration' => __('Roast Duration', 'laca'),
    'drying_phase' => __('Drying Phase', 'laca'),
    'maillard_phase' => __('Maillard Phase', 'laca'),
    'development_phase' => __('Development Phase', 'laca'),
    'development_ratio' => __('Development Ratio', 'laca'),
    'charge_temperature' => __('Charge Temperature', 'laca'),
    'turning_point' => __('Turning Point', 'laca'),
    'dry_end_temperature' => __('Dry-end Temperature', 'laca'),
    'first_crack_temperature' => __('First Crack Temperature', 'laca'),
    'end_bean_temperature' => __('End Bean Temperature', 'laca'),
];

// ── Coffee journal — nội dung trên/dưới + các ô hình ảnh/tiêu đề/mô tả ───
$journal_content_top = getPostMeta('journal_content_top');
$journal_items = getPostMeta('journal_items');
// Bỏ item rỗng TRƯỚC khi chia hàng 2 ô, tránh ô trống phá nhịp nhỏ/to xen kẽ.
$journal_items = is_array($journal_items) ? array_values(array_filter($journal_items, fn($item) => !empty($item['image']) || !empty($item['title']) || !empty($item['desc']))) : [];
$journal_content_bottom = getPostMeta('journal_content_bottom');

// ── How to use + Water quality — dùng chung toàn site (Theme Options) ────
$how_to_use_title = getOption('how_to_use_title');
$how_to_use_content = getOption('how_to_use_content');
$water_quality_title = getOption('water_quality_title');
$water_quality_items = [
    'water_quality_calcium' => __('Calcium Hardness', 'laca'),
    'water_quality_magnesium' => __('Magnesium Hardness', 'laca'),
    'water_quality_alkalinity' => __('Total Alkalinity', 'laca'),
    'water_quality_sodium' => __('Sodium', 'laca'),
];

// ── Reviews — testimonial dùng chung toàn site ───────────────────────────
$reviews_list = getOption('reviews_list');
$reviews_list = is_array($reviews_list) ? $reviews_list : [];

// ── Điều kiện hiển thị các tab ──────────────────────────────────────────
$has_journal = !empty($journal_content_top) || !empty($journal_content_bottom) || !empty($journal_items);
$has_reviews = !empty($reviews_list);

$tabs = [
    'producer' => __('Producer', 'laca'),
    'flavor-profile' => __('Flavor Profile', 'laca'),
    'roasting-profile' => __('Roasting Profile', 'laca'),
];
if ($has_journal) {
    $tabs['coffee-journal'] = __('Coffee Journal', 'laca');
}
$tabs['how-to-use'] = __('How to Use', 'laca');
if ($has_reviews) {
    $tabs['reviews'] = __('Reviews', 'laca');
}
$tab_keys = array_keys($tabs);
$first_tab = reset($tab_keys);
?>

<article class="single-product-page">

    <!-- ── HERO ─────────────────────────────────────────────────────────── -->
    <div class="container-fluid single-product-page__hero">
        <div class="single-product-page__gallery">
            <?php woocommerce_show_product_sale_flash(); ?>
            <?php woocommerce_show_product_images(); ?>
        </div>
        <div class="single-product-page__summary">
            <?php if ($subname = getPostMeta('subname')): ?>
                <p class="single-product-page__subname"><?php echo esc_html($subname); ?></p>
            <?php endif; ?>
            <?php woocommerce_template_single_title(); ?>
            <?php woocommerce_template_single_price(); ?>
            <?php woocommerce_template_single_excerpt(); ?>

            <?php
            $has_hero_spec = false;
            foreach ($hero_spec_fields as $key => $label) {
                if (getPostMeta($key)) {
                    $has_hero_spec = true;
                    break;
                }
            }
            ?>
            <?php if ($has_hero_spec): ?>
                <ul class="single-product-page__info-list single-product-page__info-list--hero">
                    <?php foreach ($hero_spec_fields as $key => $label):
                        $value = getPostMeta($key);
                        if (!$value) {
                            continue;
                        }
                        ?>
                        <li>
                            <span class="single-product-page__spec-label"><?php echo esc_html($label); ?></span>
                            <span class="single-product-page__spec-value"><?php echo esc_html($value); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php woocommerce_template_single_add_to_cart(); ?>
        </div>
    </div>

    <!-- ── TABS NAV (Sau Hero) ─────────────────────────────────────────── -->
    <div class="single-product-page__tabs" role="tablist">
        <?php foreach ($tabs as $slug => $label):
            $is_active = ($slug === $first_tab);
            ?>
            <button type="button" role="tab" id="tab-<?php echo esc_attr($slug); ?>"
                class="single-product-page__tab<?php echo $is_active ? ' is-active' : ''; ?>"
                aria-controls="panel-<?php echo esc_attr($slug); ?>"
                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                tabindex="<?php echo $is_active ? '0' : '-1'; ?>">
                <?php echo esc_html($label); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- ── PANEL 1: PRODUCER ────────────────────────────────────────────── -->
    <div id="panel-producer" role="tabpanel" aria-labelledby="tab-producer" class="single-product-page__panel" <?php echo ($first_tab !== 'producer') ? ' hidden' : ''; ?>>
        <div class="container-fluid">
            <h2 class="single-product-page__title"><?php echo esc_html($producer_name); ?></h2>
            <?php if ($producer_story): ?>
                <div class="single-product-page__desc single-product-page__rich-text">
                    <?php echo wp_kses_post(wpautop($producer_story)); ?></div>
            <?php endif; ?>

            <?php if ($first_cooperation_year || $cooperation_model || $annual_volume): ?>
                <div class="single-product-page__stats">
                    <?php if ($first_cooperation_year): ?>
                        <div class="single-product-page__stat">
                            <span
                                class="single-product-page__stat-value"><?php echo esc_html($first_cooperation_year); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('First time cooperation', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($cooperation_model): ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($cooperation_model); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('Cooperation model', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($annual_volume): ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($annual_volume); ?></span>
                            <span class="single-product-page__stat-label"><?php echo esc_html($annual_volume_note); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($google_maps_embed): ?>
                <div class="single-product-page__media">
                    <iframe src="<?php echo esc_url($google_maps_embed); ?>"
                        title="<?php esc_attr_e('Google Maps', 'laca'); ?>" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
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
            <?php if ($has_traceability): ?>
                <ul class="single-product-page__info-list single-product-page__info-list--traceability">
                    <?php foreach ($traceability_fields as $key => $label):
                        $value = getPostMeta($key);
                        if (!$value) {
                            continue;
                        }
                        ?>
                        <li>
                            <span class="single-product-page__spec-label"><?php echo esc_html($label); ?></span>
                            <span class="single-product-page__spec-value"><?php echo esc_html($value); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── PANEL 2: FLAVOR PROFILE ──────────────────────────────────────── -->
    <div id="panel-flavor-profile" role="tabpanel" aria-labelledby="tab-flavor-profile"
        class="single-product-page__panel" <?php echo ($first_tab !== 'flavor-profile') ? ' hidden' : ''; ?>>
        <div class="container-fluid">
            <h2 class="single-product-page__title"><?php _e('Flavor Profile', 'laca'); ?></h2>
            <?php if ($flavor_profile_content): ?>
                <div class="single-product-page__desc single-product-page__rich-text">
                    <?php echo wp_kses_post($flavor_profile_content); ?></div>
            <?php elseif ($flavor_profile): ?>
                <div class="single-product-page__desc single-product-page__rich-text">
                    <?php echo wp_kses_post(wpautop($flavor_profile)); ?></div>
            <?php endif; ?>
            <?php if ($sca_coffee_score): ?>
                <p class="single-product-page__sca">SCA: <?php echo esc_html($sca_coffee_score); ?></p>
            <?php endif; ?>

            <div class="single-product-page__spec-grid">
                <?php foreach ($flavor_grid as $key => $label):
                    $value = getPostMeta($key);
                    if (!$value) {
                        continue;
                    }
                    ?>
                    <div class="single-product-page__spec-cell">
                        <span class="single-product-page__spec-title"><?php echo esc_html($label); ?></span>
                        <span class="single-product-page__spec-val"><?php echo esc_html($value); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── PANEL 3: ROASTING PROFILE ────────────────────────────────────── -->
    <div id="panel-roasting-profile" role="tabpanel" aria-labelledby="tab-roasting-profile"
        class="single-product-page__panel" <?php echo ($first_tab !== 'roasting-profile') ? ' hidden' : ''; ?>>
        <div class="container-fluid">
            <h2 class="single-product-page__title"><?php echo esc_html($title_roasting_profile); ?></h2>
            <?php if ($roasting_story): ?>
                <div class="single-product-page__desc single-product-page__rich-text">
                    <?php echo wp_kses_post(wpautop($roasting_story)); ?></div>
            <?php endif; ?>

            <?php if ($roast_machine || $roast_level || $end_air_temperature): ?>
                <div class="single-product-page__stats">
                    <?php if ($roast_machine): ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($roast_machine); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('Roast Machine', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($roast_level): ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($roast_level); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('Roast Level', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($end_air_temperature): ?>
                        <div class="single-product-page__stat">
                            <span class="single-product-page__stat-value"><?php echo esc_html($end_air_temperature); ?></span>
                            <span class="single-product-page__stat-label"><?php _e('End Temperature', 'laca'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($img_roasting_profile): ?>
                <div class="single-product-page__media single-product-page__media--roasting">
                    <?php
                    if (function_exists('theResponsiveImage')) {
                        theResponsiveImage($img_roasting_profile, 'full', ['class' => 'single-product-page__media-img', 'loading' => 'lazy']);
                    } else {
                        echo wp_get_attachment_image($img_roasting_profile, 'full', false, ['class' => 'single-product-page__media-img', 'loading' => 'lazy']);
                    }
                    ?>
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
            <?php if ($has_roasting_detail): ?>
                <ul class="single-product-page__info-list single-product-page__info-list--roasting">
                    <?php foreach ($roasting_detail_fields as $key => $label):
                        $value = getPostMeta($key);
                        if (!$value) {
                            continue;
                        }
                        ?>
                        <li>
                            <span class="single-product-page__spec-label"><?php echo esc_html($label); ?></span>
                            <span class="single-product-page__spec-value"><?php echo esc_html($value); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── PANEL 4: COFFEE JOURNAL ──────────────────────────────────────── -->
    <?php if ($has_journal): ?>
        <div id="panel-coffee-journal" role="tabpanel" aria-labelledby="tab-coffee-journal"
            class="single-product-page__panel" <?php echo ($first_tab !== 'coffee-journal') ? ' hidden' : ''; ?>>
            <div class="container-fluid">
                <h2 class="single-product-page__title"><?php _e('Coffee Journal', 'laca'); ?></h2>

                <?php if ($journal_content_top): ?>
                    <div class="single-product-page__desc single-product-page__rich-text single-product-page__journal-content">
                        <?php echo wp_kses_post($journal_content_top); ?></div>
                <?php endif; ?>

                <?php if (!empty($journal_items)):
                    $journal_rows = array_chunk($journal_items, 2);
                    ?>
                    <div class="single-product-page__journal-list">
                        <?php foreach ($journal_rows as $row_index => $row_items):
                            $is_reverse = (1 === $row_index % 2);
                            $row_class = 'single-product-page__journal-row' . ($is_reverse ? ' single-product-page__journal-row--reverse' : '');
                            ?>
                            <div class="<?php echo esc_attr($row_class); ?>">
                                <?php foreach ($row_items as $item):
                                    $image = $item['image'] ?? 0;
                                    $title = $item['title'] ?? '';
                                    $desc = $item['desc'] ?? '';
                                    $link = $item['link'] ?? '';
                                    $tag = $link ? 'a' : 'div';
                                    ?>
                                    <<?php echo $tag; ?>                 <?php echo $link ? 'href="' . esc_url($link) . '"' : ''; ?>
                                        class="single-product-page__journal-item">
                                        <div class="single-product-page__journal-media">
                                            <?php
                                            if ($image && function_exists('theResponsiveImage')) {
                                                theResponsiveImage($image, 'full', ['class' => 'single-product-page__journal-img', 'loading' => 'lazy']);
                                            } elseif ($image) {
                                                echo wp_get_attachment_image($image, 'full', false, ['class' => 'single-product-page__journal-img', 'loading' => 'lazy']);
                                            }
                                            ?>
                                        </div>
                                        <div class="single-product-page__journal-overlay"></div>
                                        <div class="single-product-page__journal-info">
                                            <?php if ($title): ?>
                                                <h3 class="single-product-page__journal-title"><?php echo esc_html($title); ?></h3>
                                            <?php endif; ?>
                                            <?php if ($desc): ?>
                                                <p class="single-product-page__journal-desc"><?php echo esc_html($desc); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </<?php echo $tag; ?>>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($journal_content_bottom): ?>
                    <div
                        class="single-product-page__desc single-product-page__rich-text single-product-page__journal-content single-product-page__journal-content--bottom">
                        <?php echo wp_kses_post($journal_content_bottom); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── PANEL 5: HOW TO USE ──────────────────────────────────────────── -->
    <div id="panel-how-to-use" role="tabpanel" aria-labelledby="tab-how-to-use" class="single-product-page__panel" <?php echo ($first_tab !== 'how-to-use') ? ' hidden' : ''; ?>>
        <div class="container-fluid">
            <h2 class="single-product-page__title"><?php _e('How to Use', 'laca'); ?></h2>

            <?php if ($how_to_use_title || $how_to_use_content): ?>
                <div class="single-product-page__how-to-use">
                    <?php if ($how_to_use_title): ?>
                        <h3 class="single-product-page__subheading"><?php echo esc_html($how_to_use_title); ?></h3>
                    <?php endif; ?>
                    <?php if ($how_to_use_content): ?>
                        <div class="single-product-page__desc single-product-page__rich-text">
                            <?php echo wp_kses_post($how_to_use_content); ?></div>
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
            <?php if ($water_quality_title || $has_water_quality): ?>
                <div class="single-product-page__water-quality">
                    <?php if ($water_quality_title): ?>
                        <h3 class="single-product-page__subheading"><?php echo esc_html($water_quality_title); ?></h3>
                    <?php endif; ?>
                    <div class="single-product-page__spec-grid">
                        <?php foreach ($water_quality_items as $key => $label):
                            $value = getOption($key);
                            if (!$value) {
                                continue;
                            }
                            ?>
                            <div class="single-product-page__spec-cell">
                                <span class="single-product-page__spec-title"><?php echo esc_html($label); ?></span>
                                <span class="single-product-page__spec-val"><?php echo esc_html($value); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── PANEL 6: REVIEWS ─────────────────────────────────────────────── -->
    <?php if ($has_reviews): ?>
        <div id="panel-reviews" role="tabpanel" aria-labelledby="tab-reviews" class="single-product-page__panel" <?php echo ($first_tab !== 'reviews') ? ' hidden' : ''; ?>>
            <div class="container-fluid">
                <h2 class="single-product-page__title"><?php _e('Reviews', 'laca'); ?></h2>

                <div class="single-product-page__reviews-grid">
                    <?php foreach ($reviews_list as $review):
                        $name = $review['name'] ?? '';
                        $content = $review['content'] ?? '';
                        $link = $review['link'] ?? '';
                        if (!$name && !$content) {
                            continue;
                        }
                        $tag = $link ? 'a' : 'div';
                        ?>
                        <<?php echo $tag; ?>
                            <?php echo $link ? 'href="' . esc_url($link) . '" target="_blank" rel="noopener"' : ''; ?>
                            class="single-product-page__review-card">
                            <div class="single-product-page__review-stars">
                                <img src="<?php echo esc_url(getImageAsset('review-stars.svg')); ?>" width="190" height="30"
                                    alt="" aria-hidden="true" />
                            </div>
                            <?php if ($name): ?>
                                <h3 class="single-product-page__review-name"><?php echo esc_html($name); ?></h3>
                            <?php endif; ?>
                            <?php if ($content): ?>
                                <p class="single-product-page__review-content"><?php echo esc_html($content); ?></p>
                            <?php endif; ?>
                        </<?php echo $tag; ?>>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── RELATED COFFEE BEANS ─────────────────────────────────────────── -->
    <?php
    if ($product instanceof WC_Product && function_exists('laca_render_single_product_related')) {
        laca_render_single_product_related($product);
    }
    ?>

</article>