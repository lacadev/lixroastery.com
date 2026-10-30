<?php

/**
 * Glossary Auto-Link
 *
 * Tự động bọc thuật ngữ Glossary (title của post type "glossary", không phân
 * biệt hoa/thường) trong nội dung bài viết thành link gạch chân chấm trỏ về
 * /glossary/#<slug>. Định nghĩa của các thuật ngữ đã link được in 1 lần ở
 * footer dạng <template>, JS (glossary-tooltip.js) đọc ra hiển thị tooltip
 * khi hover. Xem doc/GLOSSARY-TOOLTIP-PLAN.md.
 *
 * Chạy ở the_content priority 20 — SAU do_blocks (priority 9) nên áp dụng
 * được cho cả output của custom block.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

class GlossaryAutoLink
{
    private const VERSION_OPTION = 'laca_glossary_terms_ver';
    private const RELATED_VERSION_OPTION = 'laca_glossary_related_ver';

    /** Loại nội dung được quét để tự tìm bài liên quan (tin tức + journal). */
    private const RELATED_POST_TYPES = ['post', 'journal'];

    /** Số bài tối đa lưu cache cho mỗi thuật ngữ (= giới hạn ở trang chi tiết). */
    public const AUTO_RELATED_MAX = 10;

    /** @var array<int, int[]>|null Map term ID → ID bài tự động (cache theo ngôn ngữ). */
    private static ?array $autoRelated = null;
    private static bool $autoRelatedDirty = false;

    /**
     * Text nằm trong các thẻ này không bao giờ bị link (link lồng link,
     * heading, code, form control...).
     */
    private const SKIP_TAGS = [
        'a', 'button', 'script', 'style', 'textarea', 'select', 'option',
        'code', 'pre', 'kbd', 'svg', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'figcaption', 'label', 'template',
    ];

    private const VOID_TAGS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
        'meta', 'param', 'source', 'track', 'wbr',
    ];

    /** @var array<string, array> Thuật ngữ đã link trên trang, key = slug */
    private array $used = [];

    public function __construct()
    {
        add_filter('the_content', [$this, 'filterContent'], 20);
        add_action('wp_footer', [$this, 'printDefinitions']);

        add_action('save_post_glossary', [$this, 'flushCache']);
        add_action('deleted_post', [$this, 'maybeFlushCache'], 10, 2);
        add_action('trashed_post', [$this, 'maybeFlushCache']);
        add_action('untrashed_post', [$this, 'maybeFlushCache']);

        add_action('save_post', [$this, 'maybeFlushRelatedCache'], 10, 2);
        add_action('deleted_post', [$this, 'maybeFlushRelatedCache'], 10, 2);
        add_action('trashed_post', [$this, 'maybeFlushRelatedCache']);
        add_action('untrashed_post', [$this, 'maybeFlushRelatedCache']);
        add_action('shutdown', [self::class, 'saveAutoRelated']);
    }

    public function filterContent($content)
    {
        if (!is_string($content) || $content === '') {
            return $content;
        }

        if (
            is_admin()
            || is_feed()
            || wp_doing_ajax()
            || (defined('REST_REQUEST') && REST_REQUEST)
            || is_post_type_archive('glossary')
        ) {
            return $content;
        }

        $terms = $this->getTerms();
        if (empty($terms)) {
            return $content;
        }

        return $this->linkify($content, $terms, (int) get_the_ID());
    }

    /**
     * Bọc lần xuất hiện ĐẦU TIÊN của mỗi thuật ngữ trong $html thành link.
     * Hàm thuần (không gọi DB) để test được.
     *
     * @param string $html
     * @param array  $terms     Mỗi phần tử: ['id', 'title', 'slug', 'definition'], đã sắp theo độ dài title giảm dần.
     * @param int    $excludeId Bỏ qua thuật ngữ có id này (không tự link chính nó).
     * @return string
     */
    public function linkify(string $html, array $terms, int $excludeId = 0): string
    {
        $byTitle = [];
        foreach ($terms as $term) {
            if ((int) $term['id'] === $excludeId || $term['title'] === '') {
                continue;
            }
            $byTitle[mb_strtolower($term['title'])] = $term;
        }
        if (empty($byTitle)) {
            return $html;
        }

        // Title dài đứng trước để "Agtron Scale" thắng "Agtron".
        $titles = array_keys($byTitle);
        usort($titles, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $pattern = '/(?<![\p{L}\p{N}_])(' . implode('|', array_map(
            static fn($t) => preg_quote($t, '/'),
            $titles
        )) . ')(?![\p{L}\p{N}_])/iu';

        $tokens = preg_split('/(<!--.*?-->|<[^>]*>)/su', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return $html;
        }

        $archive = (string) get_post_type_archive_link('glossary');
        $linked  = [];
        // [tên thẻ, độ sâu] của phần tử đang chặn link (SKIP_TAGS hoặc .no-glossary).
        $skip = null;

        foreach ($tokens as $i => $token) {
            if ($token === '') {
                continue;
            }

            if ($token[0] === '<') {
                if (strncmp($token, '<!--', 4) === 0) {
                    continue;
                }
                if (!preg_match('/^<\s*(\/)?\s*([a-zA-Z][a-zA-Z0-9-]*)/', $token, $tag)) {
                    continue;
                }
                $isClose = $tag[1] === '/';
                $name    = strtolower($tag[2]);
                $isVoid  = in_array($name, self::VOID_TAGS, true) || substr(rtrim($token, "> \t\n"), -1) === '/';

                if ($skip !== null) {
                    if ($name === $skip[0] && !$isVoid) {
                        $skip[1] += $isClose ? -1 : 1;
                        if ($skip[1] === 0) {
                            $skip = null;
                        }
                    }
                } elseif (!$isClose && !$isVoid && (
                    in_array($name, self::SKIP_TAGS, true)
                    || preg_match('/\bclass\s*=\s*["\'][^"\']*\bno-glossary\b/i', $token)
                )) {
                    $skip = [$name, 1];
                }
                continue;
            }

            if ($skip !== null || count($linked) === count($byTitle)) {
                continue;
            }

            $replaced = preg_replace_callback($pattern, function ($m) use (&$linked, $byTitle, $archive) {
                $key = mb_strtolower($m[1]);
                if (isset($linked[$key]) || !isset($byTitle[$key])) {
                    return $m[0];
                }
                $linked[$key] = true;

                $term = $byTitle[$key];
                $this->used[$term['slug']] = $term;

                return sprintf(
                    '<a class="glossary-term" href="%s" target="_blank" rel="noopener" data-glossary="%s">%s</a>',
                    esc_url($archive . '#' . rawurlencode($term['slug'])),
                    esc_attr($term['slug']),
                    $m[1]
                );
            }, $token);

            if (is_string($replaced)) {
                $tokens[$i] = $replaced;
            }
        }

        return implode('', $tokens);
    }

    /**
     * In định nghĩa các thuật ngữ đã link trên trang (đọc bởi glossary-tooltip.js).
     */
    public function printDefinitions(): void
    {
        foreach ($this->used as $slug => $term) {
            printf(
                '<template id="glossary-def-%s"><div class="glossary-tip__title">%s</div>%s</template>' . "\n",
                esc_attr($slug),
                esc_html($term['title']),
                wp_kses_post($term['definition'])
            );
        }
    }

    /**
     * Danh sách thuật ngữ (cache transient theo ngôn ngữ, xoá khi glossary thay đổi).
     *
     * @return array
     */
    public function getTerms(): array
    {
        $cacheKey = 'laca_glossary_terms_v2_' .(int) get_option(self::VERSION_OPTION, 0) . '_' . self::currentLang();

        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // WP_Query thay vì get_posts(): get_posts() bật suppress_filters,
        // làm mất lọc ngôn ngữ của Polylang.
        $query = new WP_Query([
            'post_type'              => 'glossary',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $terms = [];
        foreach ($query->posts as $post) {
            $title = trim(wp_strip_all_tags($post->post_title));
            if ($title === '') {
                continue;
            }

            $terms[] = [
                'id'         => (int) $post->ID,
                'title'      => $title,
                'slug'       => urldecode($post->post_name),
                'definition' => self::summary($post),
            ];
        }
        wp_reset_postdata();

        usort($terms, static fn($a, $b) => mb_strlen($b['title']) <=> mb_strlen($a['title']));

        set_transient($cacheKey, $terms, 12 * HOUR_IN_SECONDS);

        return $terms;
    }

    /**
     * Tóm tắt của 1 thuật ngữ (dùng ở trang /glossary/ và tooltip): excerpt
     * nếu có, nếu không thì đoạn văn ĐẦU TIÊN của nội dung. Không render cả
     * the_content vì chi tiết thuật ngữ có thể chứa ảnh/block khác — và để
     * không đệ quy vào filter của chính class này.
     *
     * @param WP_Post $post
     * @return string HTML đã kses, bọc trong <p>.
     */
    public static function summary(WP_Post $post): string
    {
        if (has_excerpt($post)) {
            return wpautop(wp_kses_post($post->post_excerpt));
        }

        $content = strip_shortcodes($post->post_content);

        if (has_blocks($content)) {
            foreach (parse_blocks($content) as $block) {
                if ($block['blockName'] === 'core/paragraph' && trim(wp_strip_all_tags($block['innerHTML'])) !== '') {
                    return wp_kses_post(trim($block['innerHTML']));
                }
            }
            $content = '';
        }

        // Nội dung classic (không có block): lấy <p> đầu tiên sau wpautop.
        if (preg_match('/<p\b[^>]*>.*?<\/p>/is', wpautop($content), $m)
            && trim(wp_strip_all_tags($m[0])) !== ''
        ) {
            return wp_kses_post($m[0]);
        }

        return '';
    }

    /**
     * Bài viết (tin tức/journal) có chứa thuật ngữ — dùng khi thuật ngữ CHƯA
     * chọn "Related Articles" thủ công. Khớp nguyên từ, không phân biệt
     * hoa/thường, quét cả text trong attribute của custom block (giống
     * auto-link). Mới nhất trước, tối đa AUTO_RELATED_MAX bài; Polylang lọc
     * theo ngôn ngữ hiện tại.
     *
     * Cache gộp 1 transient/ngôn ngữ (map term ID → ID bài) để trang
     * /glossary/ không đọc transient riêng cho từng thuật ngữ; xoá khi bài
     * tin tức/journal/thuật ngữ thay đổi.
     *
     * @return int[]
     */
    public static function autoRelatedIds(WP_Post $term, int $limit = self::AUTO_RELATED_MAX): array
    {
        if (self::$autoRelated === null) {
            $cached = get_transient(self::autoRelatedCacheKey());
            self::$autoRelated = is_array($cached) ? $cached : [];
        }

        if (!isset(self::$autoRelated[$term->ID])) {
            self::$autoRelated[$term->ID] = self::findPostsUsingTerm($term);
            self::$autoRelatedDirty = true;
        }

        return array_slice(self::$autoRelated[$term->ID], 0, $limit);
    }

    /**
     * @return int[]
     */
    private static function findPostsUsingTerm(WP_Post $term): array
    {
        $title = trim(wp_strip_all_tags($term->post_title));
        if ($title === '') {
            return [];
        }

        // Lọc thô bằng LIKE (collation MySQL không phân biệt hoa/thường).
        // Attribute block lưu dạng JSON escape (\u00e0...) nên tìm thêm bản đó.
        $needles = array_unique([$title, trim((string) wp_json_encode($title), '"')]);
        $where = static function ($sql) use ($needles) {
            global $wpdb;
            $likes = array_map(
                static fn($n) => $wpdb->prepare("{$wpdb->posts}.post_content LIKE %s", '%' . $wpdb->esc_like($n) . '%'),
                $needles
            );
            return $sql . ' AND (' . implode(' OR ', $likes) . ')';
        };

        add_filter('posts_where', $where);
        $query = new WP_Query([
            'post_type'              => self::RELATED_POST_TYPES,
            'post_status'            => 'publish',
            'posts_per_page'         => 50,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        remove_filter('posts_where', $where);

        // Lọc tinh: khớp nguyên từ trên text thật (bỏ "Airflows", "xAirflow"...).
        $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote(mb_strtolower($title), '/') . '(?![\p{L}\p{N}_])/iu';
        $ids = [];
        foreach ($query->posts as $post) {
            if (preg_match($pattern, self::plainText($post->post_content))) {
                $ids[] = (int) $post->ID;
                if (count($ids) === self::AUTO_RELATED_MAX) {
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * Text thuần của post_content, GIỮ lại text trong attribute JSON của block
     * (custom block render phía server chỉ lưu nội dung trong attribute).
     */
    private static function plainText(string $content): string
    {
        $content = str_replace(['<!--', '-->'], ' ', $content);
        $content = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn($m) => mb_chr(hexdec($m[1]), 'UTF-8'),
            $content
        ) ?? $content;

        return html_entity_decode(wp_strip_all_tags($content), ENT_QUOTES, 'UTF-8');
    }

    public static function saveAutoRelated(): void
    {
        if (self::$autoRelatedDirty) {
            set_transient(self::autoRelatedCacheKey(), self::$autoRelated, 12 * HOUR_IN_SECONDS);
            self::$autoRelatedDirty = false;
        }
    }

    private static function autoRelatedCacheKey(): string
    {
        return 'laca_glossary_related_' . (int) get_option(self::RELATED_VERSION_OPTION, 0) . '_' . self::currentLang();
    }

    private static function currentLang(): string
    {
        $lang = function_exists('pll_current_language') ? (string) pll_current_language() : '';

        return sanitize_key($lang !== '' ? $lang : get_locale());
    }

    public function maybeFlushRelatedCache($postId, $post = null): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        $type = get_post_type($post ?: $postId);
        if ($type === 'glossary' || in_array($type, self::RELATED_POST_TYPES, true)) {
            update_option(self::RELATED_VERSION_OPTION, time(), false);
            self::$autoRelated = null;
            self::$autoRelatedDirty = false;
        }
    }

    public function flushCache(): void
    {
        // Đổi version → mọi transient cũ (mọi ngôn ngữ) tự hết hiệu lực.
        update_option(self::VERSION_OPTION, time(), false);
    }

    public function maybeFlushCache($postId, $post = null): void
    {
        if (get_post_type($post ?: $postId) === 'glossary') {
            $this->flushCache();
        }
    }
}

new GlossaryAutoLink();
