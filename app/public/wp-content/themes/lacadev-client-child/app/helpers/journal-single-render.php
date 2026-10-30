<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chi tiết bài Journal (gọi từ theme/single.php): mục lục tự động đầu bài +
 * phần cuối bài (bình chọn sao, tác giả, chia sẻ, Google News, bài viết liên
 * quan, tag). Câu chữ/tác giả/Google News lấy từ Theme Options → tab Journal,
 * bật/tắt mục lục + chọn bài liên quan ở meta box của từng bài
 * (theme/setup/journal_single_meta.php).
 */

const LACA_JOURNAL_TOC_SLOT = '<div class="laca-journal-toc-slot"></div>';

// Block danh sách (lưới bài viết/sản phẩm/đối tác) có h2 là tiêu đề section,
// không phải đề mục của bài — không đưa vào mục lục.
const LACA_JOURNAL_TOC_EXCLUDED_CLASSES = [
    'block-journal-grid__title',
    'block-product-grid__title',
    'block-partnership-grid__title',
];

if (!function_exists('laca_journal_text')) {
    /**
     * Câu chữ hiển thị: ưu tiên Theme Options theo ngôn ngữ, trống thì dùng
     * câu mặc định (câu gốc tiếng Việt, dịch qua theme/languages/*.po).
     */
    function laca_journal_text(string $key): string
    {
        $value = trim((string) getOption($key));
        if ($value !== '') {
            return $value;
        }

        $defaults = [
            'journal_toc_title' => __('Mục lục', 'laca'),
            'journal_share_label' => __('Chia sẻ', 'laca'),
            'journal_related_title' => __('Bài viết liên quan', 'laca'),
            'journal_author_prefix' => __('Thực hiện bởi:', 'laca'),
            'glossary_back_label' => __('Quay lại Thuật ngữ', 'laca'),
        ];

        return $defaults[$key] ?? '';
    }
}

if (!function_exists('laca_journal_parsed_blocks')) {
    function laca_journal_parsed_blocks(int $post_id): array
    {
        static $cache = [];
        if (!isset($cache[$post_id])) {
            $cache[$post_id] = parse_blocks((string) get_post_field('post_content', $post_id));
        }
        return $cache[$post_id];
    }
}

if (!function_exists('laca_journal_first_block_index')) {
    function laca_journal_first_block_index(array $blocks): ?int
    {
        foreach ($blocks as $i => $block) {
            if ($block['blockName'] !== null || trim((string) $block['innerHTML']) !== '') {
                return $i;
            }
        }
        return null;
    }
}

if (!function_exists('laca_journal_column_style')) {
    /**
     * Mục lục + phần cuối bài dùng đúng độ rộng cột của block đầu tiên trong
     * bài (thường là CTA Section mở bài) để thẳng hàng với nội dung — default
     * 50/100/100 khớp default maxWidth của các block lacadev.
     */
    function laca_journal_column_style(int $post_id): string
    {
        $blocks = laca_journal_parsed_blocks($post_id);
        $index = laca_journal_first_block_index($blocks);
        $attrs = [];
        if ($index !== null && strpos((string) $blocks[$index]['blockName'], 'lacadev/') === 0) {
            $attrs = $blocks[$index]['attrs'];
        }

        $pc = isset($attrs['maxWidth']) ? absint($attrs['maxWidth']) : 50;
        $tablet = isset($attrs['maxWidthTablet']) ? absint($attrs['maxWidthTablet']) : 100;
        $mobile = isset($attrs['maxWidthMobile']) ? absint($attrs['maxWidthMobile']) : 100;

        return sprintf('--mw-pc:%d%%;--mw-tablet:%d%%;--mw-mobile:%d%%;', $pc, $tablet, $mobile);
    }
}

if (!function_exists('laca_journal_anchor_headings')) {
    /**
     * Gắn id cho mọi h2 (giữ id có sẵn) và trả về danh sách đề mục cho mục lục.
     *
     * @return array{0: string, 1: array<int, array{id: string, text: string}>}
     */
    function laca_journal_anchor_headings(string $html): array
    {
        $items = [];
        $used = [];

        $html = preg_replace_callback('/<h2\b([^>]*)>(.*?)<\/h2>/is', static function ($m) use (&$items, &$used) {
            $attrs = $m[1];

            if (preg_match('/\bclass=(["\'])([^"\']*)\1/i', $attrs, $class_match)) {
                $classes = preg_split('/\s+/', $class_match[2]);
                if (array_intersect($classes, LACA_JOURNAL_TOC_EXCLUDED_CLASSES)) {
                    return $m[0];
                }
            }

            $text = wp_strip_all_tags(preg_replace('/<br\s*\/?>/i', ' ', $m[2]));
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
            if ($text === '') {
                return $m[0];
            }

            if (preg_match('/\bid=(["\'])([^"\']+)\1/i', $attrs, $id_match)) {
                $id = $id_match[2];
            } else {
                $base = sanitize_title($text) ?: 'muc-luc';
                $id = $base;
                $n = 2;
                while (isset($used[$id])) {
                    $id = $base . '-' . $n++;
                }
                $attrs .= ' id="' . esc_attr($id) . '"';
            }

            $used[$id] = true;
            $items[] = ['id' => $id, 'text' => $text];

            return '<h2' . $attrs . '>' . $m[2] . '</h2>';
        }, $html);

        return [$html, $items];
    }
}

if (!function_exists('laca_journal_render_toc')) {
    function laca_journal_render_toc(array $items, string $column_style): string
    {
        if (empty($items)) {
            return '';
        }

        $title = laca_journal_text('journal_toc_title');

        ob_start();
        ?>
        <nav class="journal-toc" aria-label="<?php echo esc_attr($title); ?>">
            <div class="journal-toc__maxwidth" style="<?php echo esc_attr($column_style); ?>">
                <p class="journal-toc__title"><?php echo esc_html($title); ?></p>
                <ul class="journal-toc__list">
                    <?php foreach ($items as $item): ?>
                        <li class="journal-toc__item">
                            <a class="journal-toc__link"
                                href="#<?php echo esc_attr($item['id']); ?>"><?php echo esc_html($item['text']); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('laca_journal_render_content')) {
    /**
     * Thay cho the_content() ở single Journal: chèn mục lục ngay SAU block đầu
     * tiên (block mở bài chứa tiêu đề + sapo, tương đương vị trí mục lục dưới
     * tiêu đề/mô tả ở XLIII), đề mục lấy từ các h2 nằm sau vị trí đó.
     */
    function laca_journal_render_content(int $post_id): void
    {
        $raw = get_the_content(null, false, $post_id);
        $show_toc = !carbon_get_post_meta($post_id, 'journal_hide_toc');

        if ($show_toc) {
            $blocks = laca_journal_parsed_blocks($post_id);
            $slot_block = [
                'blockName' => null,
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => LACA_JOURNAL_TOC_SLOT,
                'innerContent' => [LACA_JOURNAL_TOC_SLOT],
            ];
            $index = has_blocks($raw) ? laca_journal_first_block_index($blocks) : null;

            if ($index === null) {
                $raw = LACA_JOURNAL_TOC_SLOT . $raw;
            } else {
                array_splice($blocks, $index + 1, 0, [$slot_block]);
                $raw = serialize_blocks($blocks);
            }
        }

        $html = apply_filters('the_content', $raw);
        $html = str_replace(']]>', ']]&gt;', $html);
        // KHÔNG wp_kses_post() lên output đã render — kses xoá <style>/<script>/<link>
        // mà block tự in ra (vd block Tabs in <style> ẩn panel → lộ CSS thành chữ)
        // và cắt cả inline style hợp lệ. Nội dung đã được kses lúc lưu bài với
        // user không có unfiltered_html, giống hệt the_content() của core.

        if ($html === '') {
            echo esc_html__('Dữ liệu đang được cập nhật', 'laca');
            return;
        }

        if ($show_toc && strpos($html, LACA_JOURNAL_TOC_SLOT) !== false) {
            [$before, $after] = explode(LACA_JOURNAL_TOC_SLOT, $html, 2);
            [$after, $items] = laca_journal_anchor_headings($after);
            $html = $before . laca_journal_render_toc($items, laca_journal_column_style($post_id)) . $after;
        }

        // Output chuẩn của the_content; mục lục tự escape từng giá trị.
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

if (!function_exists('laca_journal_share_links')) {
    function laca_journal_share_links(int $post_id): array
    {
        $url = rawurlencode(get_permalink($post_id));
        $title = rawurlencode(wp_strip_all_tags(get_the_title($post_id)));
        $image = rawurlencode((string) get_the_post_thumbnail_url($post_id, 'full'));

        // Icon Font Awesome Free (CC BY 4.0) — y hệt bộ icon chia sẻ của XLIII.
        return [
            'facebook' => [
                'label' => 'Facebook',
                'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
                'path' => 'M240 363.3L240 576L356 576L356 363.3L442.5 363.3L460.5 265.5L356 265.5L356 230.9C356 179.2 376.3 159.4 428.7 159.4C445 159.4 458.1 159.8 465.7 160.6L465.7 71.9C451.4 68 416.4 64 396.2 64C289.3 64 240 114.5 240 223.4L240 265.5L174 265.5L174 363.3L240 363.3z',
            ],
            'x' => [
                'label' => 'X',
                'url' => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
                'path' => 'M453.2 112L523.8 112L369.6 288.2L551 528L409 528L297.7 382.6L170.5 528L99.8 528L264.7 339.5L90.8 112L236.4 112L336.9 244.9L453.2 112zM428.4 485.8L467.5 485.8L215.1 152L173.1 152L428.4 485.8z',
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
                'path' => 'M196.3 512L103.4 512L103.4 212.9L196.3 212.9L196.3 512zM149.8 172.1C120.1 172.1 96 147.5 96 117.8C96 103.5 101.7 89.9 111.8 79.8C121.9 69.7 135.6 64 149.8 64C164 64 177.7 69.7 187.8 79.8C197.9 89.9 203.6 103.6 203.6 117.8C203.6 147.5 179.5 172.1 149.8 172.1zM543.9 512L451.2 512L451.2 366.4C451.2 331.7 450.5 287.2 402.9 287.2C354.6 287.2 347.2 324.9 347.2 363.9L347.2 512L254.4 512L254.4 212.9L343.5 212.9L343.5 253.7L344.8 253.7C357.2 230.2 387.5 205.4 432.7 205.4C526.7 205.4 544 267.3 544 347.7L544 512L543.9 512z',
            ],
            'pinterest' => [
                'label' => 'Pinterest',
                'url' => 'https://pinterest.com/pin/create/button/?url=' . $url . '&media=' . $image . '&description=' . $title,
                'path' => 'M332 70.5C229.4 70.5 128 138.9 128 249.6C128 320 167.6 360 191.6 360C201.5 360 207.2 332.4 207.2 324.6C207.2 315.3 183.5 295.5 183.5 256.8C183.5 176.4 244.7 119.4 323.9 119.4C392 119.4 442.4 158.1 442.4 229.2C442.4 282.3 421.1 381.9 352.1 381.9C327.2 381.9 305.9 363.9 305.9 338.1C305.9 300.3 332.3 263.7 332.3 224.7C332.3 158.5 238.4 170.5 238.4 250.5C238.4 267.3 240.5 285.9 248 301.2C234.2 360.6 206 449.1 206 510.3C206 529.2 208.7 547.8 210.5 566.7C213.9 570.5 212.2 570.1 217.4 568.2C267.8 499.2 266 485.7 288.8 395.4C301.1 418.8 332.9 431.4 358.1 431.4C464.3 431.4 512 327.9 512 234.6C512 135.3 426.2 70.5 332 70.5z',
            ],
        ];
    }
}

if (!function_exists('laca_journal_render_footer')) {
    function laca_journal_render_footer(int $post_id): void
    {
        $author_name = trim((string) getOption('journal_author_name'));
        $author_url = trim((string) getOption('journal_author_url'));
        $gg_news_url = trim((string) getOption('journal_gg_news_url'));
        $gg_news_image = absint(getOption('journal_gg_news_image'));
        $share_label = laca_journal_text('journal_share_label');
        $related = laca_journal_related_posts($post_id);
        $tags = get_the_terms($post_id, 'journal-tags');
        $has_rating = function_exists('kk_star_ratings');
        ?>
        <section class="journal-footer">
            <div class="journal-footer__maxwidth" style="<?php echo esc_attr(laca_journal_column_style($post_id)); ?>">
                <?php if ($has_rating || $author_name): ?>
                    <div class="journal-footer__row journal-footer__row--rating">
                        <?php if ($has_rating): ?>
                            <div class="journal-footer__rating">
                                <?php echo kk_star_ratings($post_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup của plugin. ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($author_name): ?>
                            <div class="journal-footer__author">
                                <?php echo esc_html(laca_journal_text('journal_author_prefix')); ?>
                                <?php if ($author_url): ?>
                                    <a class="journal-footer__author-link" href="<?php echo esc_url($author_url); ?>" target="_blank"
                                        rel="noopener"><?php echo esc_html($author_name); ?></a>
                                <?php else: ?>
                                    <span class="journal-footer__author-link"><?php echo esc_html($author_name); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="journal-footer__row journal-footer__row--share">
                    <div class="journal-footer__share">
                        <span class="journal-footer__share-label"><?php echo esc_html($share_label); ?></span>
                        <ul class="journal-footer__share-list">
                            <?php foreach (laca_journal_share_links($post_id) as $key => $network): ?>
                                <li class="journal-footer__share-item">
                                    <a class="journal-footer__share-link" href="<?php echo esc_url($network['url']); ?>"
                                        target="_blank" rel="noopener noreferrer" data-share-popup
                                        aria-label="<?php echo esc_attr($share_label . ' ' . $network['label']); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" aria-hidden="true"
                                            focusable="false">
                                            <path d="<?php echo esc_attr($network['path']); ?>" />
                                        </svg>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($gg_news_url && $gg_news_image): ?>
                        <a class="journal-footer__gg-news" href="<?php echo esc_url($gg_news_url); ?>" target="_blank"
                            rel="noopener">
                            <?php theResponsiveImage($gg_news_image, 'mobile', ['alt' => 'Google News', 'class' => 'journal-footer__gg-news-img']); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($related || ($tags && !is_wp_error($tags))): ?>
                    <div class="journal-footer__related">
                        <?php if ($related): ?>
                            <h2 class="journal-footer__related-title">
                                <?php echo esc_html(laca_journal_text('journal_related_title')); ?></h2>
                            <ul class="journal-footer__related-list">
                                <?php foreach ($related as $item): ?>
                                    <li class="journal-footer__related-item">
                                        &gt;&gt;&gt; <a class="journal-footer__related-link"
                                            href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($tags && !is_wp_error($tags)): ?>
                            <ul class="journal-footer__tags">
                                <?php foreach ($tags as $tag): ?>
                                    <li><a class="journal-footer__tag"
                                            href="<?php echo esc_url(get_term_link($tag)); ?>">#<?php echo esc_html($tag->name); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (get_post_type($post_id) === 'glossary'): ?>
                    <a class="journal-footer__back" href="<?php echo esc_url(get_post_type_archive_link('glossary')); ?>">
                        <?php echo esc_html(laca_journal_text('glossary_back_label')); ?>
                    </a>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('laca_journal_related_posts')) {
    /** @return WP_Post[] */
    function laca_journal_related_posts(int $post_id): array
    {
        if (get_post_type($post_id) === 'glossary') {
            return laca_glossary_related_posts($post_id);
        }

        $mode =carbon_get_post_meta($post_id, 'related_posts_mode') ?: 'auto';

        if ($mode === 'manual') {
            $ids = array_values(array_filter(array_map(
                static fn($item) => (int) ($item['id'] ?? 0),
                (array) carbon_get_post_meta($post_id, 'related_posts_manual')
            )));
            if (empty($ids)) {
                return [];
            }
            $args = [
                'post__in' => $ids,
                'orderby' => 'post__in',
                'posts_per_page' => count($ids),
            ];
        } else {
            $term_ids = wp_get_post_terms($post_id, 'journal-cat', ['fields' => 'ids']);
            if (is_wp_error($term_ids) || empty($term_ids)) {
                return [];
            }
            $args = [
                'post__not_in' => [$post_id],
                'posts_per_page' => 6,
                'tax_query' => [
                    ['taxonomy' => 'journal-cat', 'field' => 'term_id', 'terms' => $term_ids],
                ],
            ];
        }

        $query = new WP_Query($args + [
            'post_type' => 'journal',
            'post_status' => 'publish',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false,
            // Permalink journal có thể chứa danh mục — cần term cache, tránh N+1.
            'update_post_term_cache' => true,
        ]);

        return $query->posts;
    }
}

if (!function_exists('laca_glossary_related_posts')) {
    /**
     * Bài liên quan của 1 thuật ngữ: TẤT CẢ bài chọn ở field "Related
     * Articles" (nhiều loại nội dung — theme/setup/glossary_meta.php). Không
     * chọn thì trả rỗng → ẩn cả mục.
     *
     * @return WP_Post[]
     */
    function laca_glossary_related_posts(int $post_id): array
    {
        $ids = array_values(array_filter(array_map(
            static fn($item) => (int) ($item['id'] ?? 0),
            (array) carbon_get_post_meta($post_id, 'related_articles')
        )));
        if (empty($ids)) {
            return [];
        }

        $query = new WP_Query([
            'post_type' => 'any',
            'post__in' => $ids,
            'orderby' => 'post__in',
            'posts_per_page' => count($ids),
            // Bài do admin chọn tay — không để Polylang lọc mất bài khác
            // ngôn ngữ (vd sản phẩm "vi" trên trang "en").
            'lang' => '',
            'post_status' => 'publish',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => true,
        ]);

        return $query->posts;
    }
}
