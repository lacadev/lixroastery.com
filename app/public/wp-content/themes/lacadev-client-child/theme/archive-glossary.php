<?php
/**
 * App Layout: layouts/app.php
 *
 * Trang danh sách "Glossary" — hiển thị TẤT CẢ thuật ngữ trên 1 trang, sắp
 * xếp theo bảng chữ cái A-Z. Cột A-Z bên trái, click vào 1 chữ cái sẽ
 * scroll tới thuật ngữ đầu tiên bắt đầu bằng chữ cái đó (xem
 * resources/scripts/theme/components/glossary-nav.js).
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

$alphabet = range('A', 'Z');

$query = new WP_Query([
    'post_type'           => 'glossary',
    'posts_per_page'      => -1,
    'post_status'         => 'publish',
    'orderby'             => 'title',
    'order'               => 'ASC',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
]);

$terms = $query->posts;
wp_reset_postdata();

// Nhóm theo chữ cái đầu (viết hoa) — chỉ để biết chữ nào có/không có thuật
// ngữ, phục vụ làm mờ chữ cái rỗng ở cột A-Z bên trái.
$letters_with_entries = [];
foreach ($terms as $term) {
    $first_char = mb_strtoupper(mb_substr($term->post_title, 0, 1));
    $letters_with_entries[$first_char] = true;
}
?>

<section class="glossary-page">
    <div class="container-fluid">
        <h1 class="glossary-page__title"><?php esc_html_e('Glossary', 'laca'); ?></h1>

        <?php if (!empty($terms)) : ?>
            <div class="glossary-page__body">
                <nav class="glossary-page__nav" aria-label="<?php esc_attr_e('Bảng chữ cái', 'laca'); ?>">
                    <?php foreach ($alphabet as $letter) :
                        $has_entries = !empty($letters_with_entries[$letter]);
                    ?>
                        <a
                            href="#glossary-letter-<?php echo esc_attr($letter); ?>"
                            data-target="glossary-letter-<?php echo esc_attr($letter); ?>"
                            class="glossary-page__nav-link<?php echo $has_entries ? '' : ' glossary-page__nav-link--disabled'; ?>"
                            <?php echo $has_entries ? '' : 'aria-disabled="true" tabindex="-1"'; ?>
                        ><?php echo esc_html($letter); ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="glossary-page__list">
                    <?php
                    $prev_letter = null;
                    foreach ($terms as $term) :
                        $first_char = mb_strtoupper(mb_substr($term->post_title, 0, 1));
                        $is_first_of_letter = $first_char !== $prev_letter;
                        $prev_letter = $first_char;

                        $related_articles = carbon_get_post_meta($term->ID, 'related_articles');
                        $related_articles = is_array($related_articles) ? $related_articles : [];
                    ?>
                        <div
                            class="glossary-page__item"
                            <?php echo $is_first_of_letter ? 'id="glossary-letter-' . esc_attr($first_char) . '"' : ''; ?>
                        >
                            <h2 class="glossary-page__term"><?php echo esc_html($term->post_title); ?></h2>
                            <div class="glossary-page__content">
                                <div class="glossary-page__definition">
                                    <?php echo apply_filters('the_content', $term->post_content); ?>
                                </div>

                                <?php if (!empty($related_articles)) : ?>
                                    <div class="glossary-page__related">
                                        <p class="glossary-page__related-label"><?php esc_html_e('Related Articles:', 'laca'); ?></p>
                                        <ul class="glossary-page__related-list">
                                            <?php foreach ($related_articles as $related) :
                                                $related_id = $related['id'] ?? 0;
                                                if (!$related_id || 'publish' !== get_post_status($related_id)) {
                                                    continue;
                                                }
                                                $excerpt = wp_trim_words(get_the_excerpt($related_id), 16);
                                            ?>
                                                <li>
                                                    <a href="<?php echo esc_url(get_permalink($related_id)); ?>"><?php echo esc_html(get_the_title($related_id)); ?></a><?php if ($excerpt) : ?> - <?php echo esc_html($excerpt); ?><?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else : ?>
            <p class="glossary-page__empty"><?php esc_html_e('Chưa có thuật ngữ nào.', 'laca'); ?></p>
        <?php endif; ?>
    </div>
</section>
