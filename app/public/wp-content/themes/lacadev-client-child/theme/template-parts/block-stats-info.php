<?php

/**
 * Template part for displaying Stats Info block in the footer.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

$footer_stats_enable = getOption('footer_stats_enable');
// Tắt nếu người dùng chủ động bỏ chọn (lưu 'no' hoặc '0')
if ($footer_stats_enable === 'no' || $footer_stats_enable === '0') {
    return;
}

$items = getOption('footer_stats_items');
if (empty($items) || !is_array($items)) {
    $items = [
        [
            'number'      => '+08',
            'label'       => __('NĂM KINH NGHIỆM TRONG NGÀNH CÀ PHÊ', 'laca'),
            'description' => __('Tám năm tích lũy kinh nghiệm trong tuyển chọn nguồn cà phê, cảm quan và dịch vụ đã trở thành nền tảng vận hành của LIX.', 'laca'),
            'link'        => '',
        ],
        [
            'number'      => '+20',
            'label'       => __('MẠNG LƯỚI ĐỐI TÁC TOÀN CẦU', 'laca'),
            'description' => __('Được xây dựng từ mạng lưới tám năm của XLIII Coffee, kết nối những mối quan hệ tin cậy xuyên suốt vùng trồng, hoạt động tìm nguồn và hợp tác trong ngành cà phê.', 'laca'),
            'link'        => '',
        ],
        [
            'number'      => '03',
            'label'       => __('HỆ THỐNG RANG CÀ PHÊ CỐT LÕI', 'laca'),
            'description' => __('Origin, Lab và Roastery vận hành như một hệ thống thống nhất, xuyên suốt từ khâu đánh giá cà phê nhân xanh đến rang sản xuất.', 'laca'),
            'link'        => '',
        ],
        [
            'number'      => '04',
            'label'       => __('ĐIỂM KIỂM SOÁT CHẤT LƯỢNG', 'laca'),
            'description' => __('Đánh giá cà phê nhân xanh, giám sát quá trình rang, phân loại sau rang và kiểm định cảm quan giúp duy trì tính nhất quán trong từng chu kỳ sản xuất.', 'laca'),
            'link'        => '',
        ],
    ];
}

$columns = (int) getOption('footer_stats_columns');
if ($columns < 1 || $columns > 6) {
    $columns = 4;
}

$intro_content  = getOption('footer_stats_intro_content');
$intro_btn_text = getOption('footer_stats_intro_btn_text');
$intro_btn_url  = getOption('footer_stats_intro_btn_url');

// Enqueue block style nếu đã được đăng ký và chưa được nạp
if (wp_style_is('block-block-stats-info', 'registered') && !wp_style_is('block-block-stats-info', 'enqueued')) {
    wp_enqueue_style('block-block-stats-info');
}
?>
<section class="block-stats-info block-stats-info--footer" data-aos="fade-up">
    <div class="container-fluid">
        <?php if (!empty($intro_content) || (!empty($intro_btn_text) && !empty($intro_btn_url))) : ?>
            <div class="block-stats-info__intro-wrap">
                <?php if (!empty($intro_content)) : ?>
                    <div class="block-stats-info__intro-content">
                        <?php echo wp_kses_post(wpautop($intro_content)); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($intro_btn_text) && !empty($intro_btn_url)) : ?>
                    <div class="block-stats-info__intro-btn">
                        <a href="<?php echo esc_url($intro_btn_url); ?>" class="block-stats-info__btn-link">
                            <?php echo esc_html($intro_btn_text); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="block-stats-info__maxwidth" style="--mw-pc:100%;--mw-tablet:100%;--mw-mobile:100%;--align-margin-pc:0 auto;--align-margin-tablet:0 auto;--align-margin-mobile:0 auto;">
            <div class="block-stats-info__grid" style="
                --stats-columns: <?php echo esc_attr((string) $columns); ?>;
                --stats-title-align-pc: left;
                --stats-title-align-tablet: left;
                --stats-title-align-mobile: left;
                --stats-desc-align-pc: justify;
                --stats-desc-align-tablet: justify;
                --stats-desc-align-mobile: justify;
            ">
                <?php foreach ($items as $item) :
                    $number = !empty($item['number']) ? esc_html($item['number']) : '';
                    $label  = !empty($item['label']) ? esc_html($item['label']) : '';
                    $desc   = !empty($item['description']) ? wp_kses_post($item['description']) : '';
                    $link   = !empty($item['link']) ? esc_url($item['link']) : '';

                    if (!$number && !$label) {
                        continue;
                    }
                ?>
                    <div class="block-stats-info__item">
                        <?php if ($link) : ?>
                            <a href="<?php echo $link; ?>" class="block-stats-info__link">
                        <?php endif; ?>

                        <div class="block-stats-info__number">
                            <?php echo $number; ?>
                        </div>

                        <?php if ($label) : ?>
                            <div class="block-stats-info__label">
                                <?php echo $label; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($desc) : ?>
                            <p class="block-stats-info__desc">
                                <?php echo $desc; ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($link) : ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
