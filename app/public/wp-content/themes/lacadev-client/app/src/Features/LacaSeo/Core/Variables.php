<?php

namespace App\Features\LacaSeo\Core;

defined('ABSPATH') || exit;

/**
 * Variables engine: replaces %variables% with dynamic context data.
 * Fully compatible with Rank Math replace variables, parameter syntax, and regex parser.
 */
class Variables
{
    /** @var array<string, array{name:string,desc:string,example:string,callback:callable}> */
    private static array $customVars = [];

    /**
     * Replace %variables% in a given string based on context object.
     *
     * @param string $string
     * @param mixed $object Post, Term, User, or null for global/current query
     * @return string
     */
    public static function replace(string $string, mixed $object = null): string
    {
        $string = wp_strip_all_tags($string);

        if (!str_contains($string, '%')) {
            return $string;
        }

        // Clean trailing separator shortcut matching Rank Math
        if (str_ends_with($string, ' %sep%')) {
            $string = substr($string, 0, -6);
        }

        // Identify context post/term/user/query
        $context = self::resolveContext($object);

        // Parse variables using Rank Math regex: /%(([a-z0-9_-]+)\(([^)]*)\)|[^\s]+)%/iu
        if (!preg_match_all('/%(([a-z0-9_-]+)\(([^)]*)\)|[^\s]+)%/iu', $string, $matches)) {
            return $string;
        }

        $replacements = [];
        $sep = (string) Settings::get('titles.title_separator', '-');

        foreach ($matches[1] as $index => $matchedInside) {
            $rawPlaceholder = $matches[0][$index];
            $hasArgs = !empty($matches[2][$index]) && isset($matches[3][$index]);
            $varId = $hasArgs ? $matches[2][$index] : $matchedInside;
            $argsRaw = $hasArgs ? $matches[3][$index] : '';

            $val = self::resolveVariableValue($varId, $argsRaw, $context, $sep);
            if ($val !== false && $val !== null) {
                $replacements[$rawPlaceholder] = (string) $val;
            } else {
                $replacements[$rawPlaceholder] = '';
            }
        }

        if (!empty($replacements)) {
            $string = str_replace(array_keys($replacements), array_values($replacements), $string);
        }

        // Deduplicate adjacent separators matching Rank Math
        if ($sep !== '') {
            $q_sep = preg_quote($sep, '`');
            $string = (string) preg_replace('`' . $q_sep . '(?:\s*' . $q_sep . ')*`u', $sep, $string);
        }

        // Normalize excess whitespace
        return trim((string) preg_replace('/\s+/', ' ', $string));
    }

    /**
     * Resolve the active context object.
     *
     * @param mixed $object
     * @return array{post:?\WP_Post,term:?\WP_Term,user:?\WP_User}
     */
    private static function resolveContext(mixed $object): array
    {
        $post = null;
        $term = null;
        $user = null;

        if ($object instanceof \WP_Post || (is_object($object) && isset($object->filename))) {
            $post = $object;
        } elseif (is_array($object) && isset($object['filename'])) {
            $post = (object) $object;
        } elseif ($object instanceof \WP_Term) {
            $term = $object;
        } elseif ($object instanceof \WP_User) {
            $user = $object;
        } else {
            $queried = get_queried_object();
            if ($queried instanceof \WP_Post) {
                $post = $queried;
            } elseif ($queried instanceof \WP_Term) {
                $term = $queried;
            } elseif ($queried instanceof \WP_User) {
                $user = $queried;
            } elseif (is_singular()) {
                $post = get_post();
            }
        }

        return ['post' => $post, 'term' => $term, 'user' => $user];
    }

    /**
     * Resolve a single variable ID + arguments.
     *
     * @param string $id
     * @param string $argsRaw
     * @param array{post:?\WP_Post,term:?\WP_Term,user:?\WP_User} $context
     * @param string $sep
     * @return string|false
     */
    private static function resolveVariableValue(string $id, string $argsRaw, array $context, string $sep): string|false
    {
        $post = $context['post'];
        $term = $context['term'];
        $user = $context['user'];

        // Normalize arguments
        $parsedArgs = [];
        if ($argsRaw !== '') {
            $decoded = wp_specialchars_decode($argsRaw);
            if (str_contains($decoded, '=')) {
                $parsedArgs = wp_parse_args($decoded, []);
            } else {
                $parsedArgs = $decoded;
            }
        }

        // Check custom registered vars first
        if (isset(self::$customVars[$id])) {
            return (string) call_user_func(self::$customVars[$id]['callback'], $parsedArgs, $post ?: $term ?: $user);
        }

        switch (strtolower($id)) {
            // Basic & Separator
            case 'sep':
            case 'separator':
                return $sep;

            case 'sitename':
                return get_bloginfo('name');

            case 'sitedesc':
                return get_bloginfo('description');

            case 'currentdate':
                return date_i18n(get_option('date_format'));

            case 'currentday':
                return date_i18n('j');

            case 'currentmonth':
                return date_i18n('F');

            case 'currentyear':
                return date_i18n('Y');

            case 'currenttime':
                $format = is_string($parsedArgs) && $parsedArgs !== '' ? $parsedArgs : get_option('time_format');
                return date_i18n($format);

            case 'count':
                static $counters = [];
                $counterKey = is_string($parsedArgs) && $parsedArgs !== '' ? $parsedArgs : 'default';
                if (!isset($counters[$counterKey])) {
                    $counters[$counterKey] = 0;
                }
                return (string) (++$counters[$counterKey]);

            case 'search_query':
                return get_search_query();

            // Paging
            case 'page':
                return self::getPageWithContext($sep);

            case 'pagenumber':
                return self::getPageNumber();

            case 'pagetotal':
                return self::getPageTotal();

            // Post type labels
            case 'pt_single':
                return self::getPostTypeLabel('single', $post);

            case 'pt_plural':
                return self::getPostTypeLabel('plural', $post);

            // Organization / Local SEO
            case 'org_name':
                return (string) Settings::get('titles.knowledgegraph_name', get_bloginfo('name'));

            case 'org_logo':
                return (string) Settings::get('titles.knowledgegraph_logo', '');

            case 'org_url':
                return (string) Settings::get('titles.url', home_url());

            // Post & Singular Variables
            case 'id':
                if ($post) {
                    return (string) $post->ID;
                }
                return '';

            case 'title':
                if (is_post_type_archive() && !self::isShopPage()) {
                    $pt = self::getQueriedPostType();
                    $pto = get_post_type_object($pt);
                    return $pto ? $pto->labels->name : '';
                }
                if ($post && isset($post->post_title)) {
                    return stripslashes($post->post_title);
                }
                return '';


            case 'parent_title':
                if ($post && !empty($post->post_parent)) {
                    return get_the_title($post->post_parent);
                }
                return '';

            case 'excerpt':
                if ($post) {
                    if (post_password_required($post->ID)) {
                        return '';
                    }
                    if (!empty($post->post_excerpt)) {
                        return wp_strip_all_tags($post->post_excerpt);
                    }
                    return self::getPostContentExcerpt($post);
                }
                return '';

            case 'seo_title':
                if ($post) {
                    $customTitle = (string) get_post_meta($post->ID, '_laca_seo_title', true);
                    if ($customTitle !== '') {
                        return $customTitle;
                    }
                    return \App\Features\LacaSeo\Frontend\Paper\Paper::get_from_options("pt_{$post->post_type}_title", $post, '%title% %sep% %sitename%');
                }
                return '';

            case 'seo_description':
                if (is_singular() || is_category() || is_tag() || is_tax()) {
                    return \App\Features\LacaSeo\Frontend\Paper\Paper::get()->get_description();
                }
                if ($post) {
                    $customDesc = (string) get_post_meta($post->ID, '_laca_seo_description', true);
                    if ($customDesc !== '') {
                        return $customDesc;
                    }
                    return \App\Features\LacaSeo\Frontend\Paper\Paper::get_from_options("pt_{$post->post_type}_description", $post, '%excerpt%');
                }
                return '';

            case 'excerpt_only':
                if ($post && !empty($post->post_excerpt) && !post_password_required($post->ID)) {
                    return wp_strip_all_tags($post->post_excerpt);
                }
                return '';

            case 'url':
                if ($post) {
                    $customCanonical = get_post_meta($post->ID, '_laca_seo_canonical_url', true);
                    return !empty($customCanonical) ? $customCanonical : get_permalink($post);
                }
                return '';

            case 'date':
                $format = is_string($parsedArgs) && $parsedArgs !== '' ? $parsedArgs : get_option('date_format');
                if ($post && !empty($post->post_date)) {
                    return mysql2date($format, $post->post_date, true);
                }
                if (!empty(get_query_var('day'))) {
                    return get_the_date($format);
                }
                $month = single_month_title(' ', false);
                if (!empty($month)) {
                    return $month;
                }
                return !empty(get_query_var('year')) ? (string) get_query_var('year') : '';

            case 'modified':
                $format = is_string($parsedArgs) && $parsedArgs !== '' ? $parsedArgs : get_option('date_format');
                if ($post && !empty($post->post_modified)) {
                    $mod = strtotime($post->post_date) > strtotime($post->post_modified) ? $post->post_date : $post->post_modified;
                    return mysql2date($format, $mod, true);
                }
                return '';

            case 'category':
                if ($post) {
                    return self::getPostTerms($post->ID, 'category', true);
                }
                if ($term && $term->taxonomy === 'category') {
                    return $term->name;
                }
                return '';

            case 'categories':
                if ($post) {
                    $args = is_array($parsedArgs) ? $parsedArgs : [];
                    return self::getPostTerms($post->ID, 'category', false, $args);
                }
                return '';

            case 'tag':
                if ($post) {
                    return self::getPostTerms($post->ID, 'post_tag', true);
                }
                if ($term && $term->taxonomy === 'post_tag') {
                    return $term->name;
                }
                return '';

            case 'tags':
                if ($post) {
                    $args = is_array($parsedArgs) ? $parsedArgs : [];
                    return self::getPostTerms($post->ID, 'post_tag', false, $args);
                }
                return '';

            case 'primary_taxonomy_terms':
                if ($post) {
                    $mainTax = (string) Settings::get("titles.pt_{$post->post_type}_primary_taxonomy", '');
                    if ($mainTax !== '' && $mainTax !== 'off') {
                        $primaryTermId = (int) get_post_meta($post->ID, "_laca_seo_primary_{$mainTax}", true);
                        if ($primaryTermId > 0) {
                            $primaryTerm = get_term($primaryTermId, $mainTax);
                            if ($primaryTerm instanceof \WP_Term) {
                                return $primaryTerm->name;
                            }
                        }
                        $terms = wp_get_object_terms($post->ID, $mainTax, ['fields' => 'names']);
                        if (!is_wp_error($terms) && !empty($terms)) {
                            return implode(', ', $terms);
                        }
                    }
                }
                return '';

            case 'post_thumbnail':
                if ($post && has_post_thumbnail($post->ID)) {
                    $src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
                    return !empty($src[0]) ? $src[0] : '';
                }
                return '';

            case 'filename':
                $rawFilename = '';
                if (is_object($post) && !empty($post->filename)) {
                    $rawFilename = (string) $post->filename;
                } elseif ($post instanceof \WP_Post && $post->post_type === 'attachment') {
                    $rawFilename = (string) get_attached_file($post->ID);
                }
                if ($rawFilename !== '') {
                    $fn = pathinfo($rawFilename, PATHINFO_FILENAME);
                    $nameParts = explode('-', $fn);
                    if (str_contains(end($nameParts), 'x')) {
                        array_pop($nameParts);
                    }
                    $name = trim(str_replace('_', ' ', implode(' ', $nameParts)));
                    return $name !== '' ? $name : '';
                }
                return '';

            case 'imagealt':
                if (is_object($post) && isset($post->alttext)) {
                    return (string) $post->alttext;
                }
                return '';

            case 'imagetitle':
                if (is_object($post) && isset($post->titletext)) {
                    return (string) $post->titletext;
                }
                return '';

            case 'focuskw':
                if ($post) {
                    $kw = (string) get_post_meta($post->ID, '_laca_seo_focus_keyword', true);
                    $parts = explode(',', $kw);
                    return trim($parts[0]);
                }
                if ($term) {
                    $kw = (string) get_term_meta($term->term_id, '_laca_seo_focus_keyword', true);
                    $parts = explode(',', $kw);
                    return trim($parts[0]);
                }
                return '';

            case 'keywords':
                if ($post) {
                    return (string) get_post_meta($post->ID, '_laca_seo_focus_keyword', true);
                }
                if ($term) {
                    return (string) get_term_meta($term->term_id, '_laca_seo_focus_keyword', true);
                }
                return '';

            case 'customfield':
                $field = is_string($parsedArgs) ? trim($parsedArgs) : '';
                if ($post && $field !== '') {
                    $val = get_post_meta($post->ID, $field, true);
                    return is_scalar($val) ? (string) $val : '';
                }
                return '';

            case 'customterm':
                $tax = is_string($parsedArgs) ? trim($parsedArgs) : '';
                if ($post && $tax !== '') {
                    return self::getPostTerms($post->ID, $tax, true, [], 'name');
                }
                return '';

            case 'customterm_desc':
                $tax = is_string($parsedArgs) ? trim($parsedArgs) : '';
                if ($post && $tax !== '') {
                    return self::getPostTerms($post->ID, $tax, true, [], 'description');
                }
                return '';

            // Term Variables
            case 'term':
                if ($term) {
                    return $term->name;
                }
                if (is_category() || is_tag() || is_tax()) {
                    $q = get_queried_object();
                    if ($q instanceof \WP_Term) {
                        return $q->name;
                    }
                }
                return '';

            case 'term_description':
                $rawDesc = '';
                if ($term) {
                    $rawDesc = (string) get_term_field('description', $term->term_id, $term->taxonomy);
                } elseif (is_category() || is_tag() || is_tax()) {
                    $q = get_queried_object();
                    if ($q instanceof \WP_Term) {
                        $rawDesc = (string) get_term_field('description', $q->term_id, $q->taxonomy);
                    }
                }
                if ($rawDesc !== '') {
                    return self::truncateString($rawDesc, 160);
                }
                return '';

            // Author Variables
            case 'name':
            case 'post_author':
                if ($post) {
                    return (string) get_the_author_meta('display_name', (int) $post->post_author);
                }
                if ($user) {
                    return $user->display_name;
                }
                return '';

            case 'user_description':
                if ($post) {
                    return (string) get_the_author_meta('description', (int) $post->post_author);
                }
                if ($user) {
                    return (string) get_the_author_meta('description', $user->ID);
                }
                return '';

            case 'userid':
                if ($post) {
                    return (string) $post->post_author;
                }
                if ($user) {
                    return (string) $user->ID;
                }
                return '';

            // WooCommerce
            case 'wc_price':
                if ($post && $post->post_type === 'product' && function_exists('wc_get_product')) {
                    $product = wc_get_product($post->ID);
                    if ($product && method_exists($product, 'get_price')) {
                        return wp_strip_all_tags(wc_price($product->get_price()), true);
                    }
                }
                return '';

            case 'wc_sku':
                if ($post && $post->post_type === 'product' && function_exists('wc_get_product')) {
                    $product = wc_get_product($post->ID);
                    return $product ? (string) $product->get_sku() : '';
                }
                return '';

            case 'wc_shortdesc':
                if ($post && $post->post_type === 'product' && function_exists('wc_get_product')) {
                    $product = wc_get_product($post->ID);
                    return $product ? (string) $product->get_short_description() : '';
                }
                return '';

            case 'wc_brand':
                if ($post && $post->post_type === 'product') {
                    return \App\Features\LacaSeo\Modules\WooCommerce\WooCommerce::getBrand((int) $post->ID);
                }
                return '';

            default:
                // Check dynamic term_{taxonomy} or term_{taxonomy}_desc
                if (str_starts_with($id, 'term_')) {
                    if (str_ends_with($id, '_desc')) {
                        $tax = substr($id, 5, -5);
                        if ($post && taxonomy_exists($tax)) {
                            return self::getPostTerms($post->ID, $tax, true, [], 'description');
                        }
                    } else {
                        $tax = substr($id, 5);
                        if ($post && taxonomy_exists($tax)) {
                            return self::getPostTerms($post->ID, $tax, true, [], 'name');
                        }
                    }
                }
                return '';
        }
    }

    /**
     * Get post terms formatted with limit, separator, exclude.
     */
    private static function getPostTerms(int $postId, string $taxonomy, bool $returnSingle = false, array $args = [], string $field = 'name'): string
    {
        $defaults = [
            'limit'     => 99,
            'separator' => ', ',
            'exclude'   => [],
        ];
        $args = wp_parse_args($args, $defaults);

        if (!empty($args['exclude'])) {
            $exclude = is_array($args['exclude']) ? $args['exclude'] : array_map('intval', explode(',', (string) $args['exclude']));
        } else {
            $exclude = [];
        }

        $terms = get_the_terms($postId, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        if (!empty($exclude)) {
            $terms = array_filter($terms, static function ($t) use ($exclude) {
                return !in_array($t->term_id, $exclude, true);
            });
        }

        if (empty($terms)) {
            return '';
        }

        $terms = array_values($terms);
        if ($returnSingle) {
            return (string) ($terms[0]->{$field} ?? '');
        }

        $limit = (int) $args['limit'];
        if ($limit > 0) {
            $terms = array_slice($terms, 0, $limit);
        }

        $values = wp_list_pluck($terms, $field);
        return implode($args['separator'], $values);
    }

    private static function getPostContentExcerpt(\WP_Post $post): string
    {
        if (empty($post->post_content)) {
            return '';
        }

        $content = strip_shortcodes($post->post_content);
        $content = preg_replace('/<!--[\s\S]*?-->/iu', '', $content);
        $paragraphs = explode("\n", wp_strip_all_tags($content));
        foreach ($paragraphs as $p) {
            $trimmed = trim($p);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return wp_trim_words($content, 30);
    }

    private static function getPostTypeLabel(string $type, ?\WP_Post $post): string
    {
        $pt = $post ? $post->post_type : self::getQueriedPostType();
        $pto = get_post_type_object($pt);
        if (!$pto) {
            return '';
        }

        if ($type === 'single') {
            return $pto->labels->singular_name ?? $pto->name;
        }

        return $pto->labels->name ?? $pto->name;
    }

    private static function getQueriedPostType(): string
    {
        $pt = get_post_type();
        if ($pt) {
            return $pt;
        }

        $qv = get_query_var('post_type');
        if (is_array($qv)) {
            return (string) reset($qv);
        }
        if (is_string($qv) && $qv !== '') {
            return $qv;
        }

        $obj = get_queried_object();
        if ($obj instanceof \WP_Post) {
            return $obj->post_type;
        }

        return 'post';
    }

    private static function isShopPage(): bool
    {
        return function_exists('is_shop') && is_shop();
    }

    private static function getPageNumber(): string
    {
        $page = (int) get_query_var('paged');
        if ($page < 1) {
            $page = (int) get_query_var('page');
        }
        return (string) max(1, $page);
    }

    private static function getPageTotal(): string
    {
        global $wp_query, $post;
        if (is_singular() && isset($post->post_content)) {
            return (string) (substr_count($post->post_content, '<!--nextpage-->') + 1);
        }
        return (string) max(1, (int) ($wp_query->max_num_pages ?? 1));
    }

    private static function getPageWithContext(string $sep): string
    {
        $page = (int) self::getPageNumber();
        $total = (int) self::getPageTotal();

        if ($total > 1 && $page > 1) {
            return sprintf($sep . ' ' . __('Page %1$d of %2$d', 'lacadev'), $page, $total);
        }

        return '';
    }

    private static function truncateString(string $str, int $length = 110, string $append = ''): string
    {
        $str = wp_strip_all_tags($str, true);
        $strlen = mb_strlen($str);
        $excerpt = mb_substr($str, 0, $length);

        // Remove part of an entity at the end
        $excerpt = preg_replace('/&[^;\s]{0,6}$/', '', $excerpt);
        if ($str !== $excerpt) {
            $lastSpace = mb_strrpos(trim($excerpt), ' ');
            if ($lastSpace !== false) {
                $excerpt = mb_substr($str, 0, $lastSpace);
            }
        }

        if ($strlen > $length) {
            $excerpt .= $append;
        }

        return $excerpt;
    }

    /**
     * Register a custom replacement variable.
     */
    public static function register(string $id, array $args, callable $callback): void
    {
        self::$customVars[$id] = [
            'name'     => $args['name'] ?? $id,
            'desc'     => $args['desc'] ?? '',
            'example'  => $args['example'] ?? '',
            'callback' => $callback,
        ];
    }

    /**
     * Get list of standard variables with description & example for dropdown inserter UI.
     *
     * @return array<int, array{id:string,name:string,desc:string,example:string}>
     */
    public static function getAvailableList(): array
    {
        return [
            ['id' => 'title', 'name' => 'Tiêu đề bài viết', 'desc' => 'Tiêu đề của bài viết hoặc trang hiện tại', 'example' => 'Cà phê Robusta'],
            ['id' => 'seo_title', 'name' => 'Tiêu đề SEO', 'desc' => 'Tiêu đề SEO tuỳ chỉnh hoặc mặc định của trang/bài viết', 'example' => 'Cà phê Robusta - Lix Roastery'],
            ['id' => 'sep', 'name' => 'Ký tự phân cách', 'desc' => 'Ký tự phân cách cài trong Titles & Meta', 'example' => '-'],
            ['id' => 'sitename', 'name' => 'Tên website', 'desc' => 'Tên trang web cài trong Cài đặt chung', 'example' => 'Lix Roastery'],
            ['id' => 'sitedesc', 'name' => 'Khẩu hiệu website', 'desc' => 'Khẩu hiệu của trang web', 'example' => 'Xưởng rang cà phê chất lượng cao'],
            ['id' => 'page', 'name' => 'Số trang hiện tại', 'desc' => 'Hiển thị "Trang 2 của 4" khi có phân trang', 'example' => '- Page 2 of 4'],
            ['id' => 'excerpt', 'name' => 'Đoạn trích tóm tắt', 'desc' => 'Tự động trích đoạn đầu bài viết', 'example' => 'Hạt cà phê chín mọng được thu hái thủ công...'],
            ['id' => 'seo_description', 'name' => 'Mô tả SEO', 'desc' => 'Mô tả SEO tuỳ chỉnh hoặc trích đoạn tự sinh', 'example' => 'Hạt cà phê chín mọng được thu hái thủ công...'],
            ['id' => 'date', 'name' => 'Ngày xuất bản', 'desc' => 'Ngày xuất bản của bài viết', 'example' => '20/10/2026'],
            ['id' => 'modified', 'name' => 'Ngày cập nhật', 'desc' => 'Ngày sửa đổi gần nhất của bài', 'example' => '20/10/2026'],
            ['id' => 'category', 'name' => 'Chuyên mục chính', 'desc' => 'Tên chuyên mục đầu tiên của bài', 'example' => 'Tin Tức'],
            ['id' => 'categories', 'name' => 'Tất cả chuyên mục', 'desc' => 'Danh sách chuyên mục cách nhau dấu phẩy', 'example' => 'Tin Tức, Cẩm Nang'],
            ['id' => 'tag', 'name' => 'Thẻ bài viết đầu tiên', 'desc' => 'Tên thẻ tag đầu tiên', 'example' => 'Robusta'],
            ['id' => 'tags', 'name' => 'Tất cả thẻ tags', 'desc' => 'Danh sách tag cách nhau dấu phẩy', 'example' => 'Robusta, Arabica'],
            ['id' => 'name', 'name' => 'Tên tác giả', 'desc' => 'Tên hiển thị của tác giả bài viết', 'example' => 'Admin'],
            ['id' => 'term', 'name' => 'Tên chuyên mục / term', 'desc' => 'Tên của danh mục đang xem', 'example' => 'Hạt Cà Phê'],
            ['id' => 'term_description', 'name' => 'Mô tả chuyên mục', 'desc' => 'Đoạn mô tả của danh mục đang xem', 'example' => 'Tổng hợp các loại hạt cà phê hảo hạng.'],
            ['id' => 'parent_title', 'name' => 'Tiêu đề trang cha', 'desc' => 'Tiêu đề của trang cấp cha nếu có', 'example' => 'Sản Phẩm'],
            ['id' => 'primary_taxonomy_terms', 'name' => 'Terms taxonomy chính', 'desc' => 'Các terms thuộc taxonomy chính của post type', 'example' => 'Cà phê hạt'],
            ['id' => 'focuskw', 'name' => 'Từ khoá chính', 'desc' => 'Từ khoá SEO trọng tâm đầu tiên', 'example' => 'cà phê nguyên chất'],
            ['id' => 'keywords', 'name' => 'Tất cả từ khoá', 'desc' => 'Toàn bộ từ khoá SEO', 'example' => 'cà phê, hạt arabica'],
            ['id' => 'org_name', 'name' => 'Tên tổ chức / doanh nghiệp', 'desc' => 'Tên cài trong Local SEO', 'example' => 'Lix Roastery'],
            ['id' => 'org_logo', 'name' => 'Logo tổ chức', 'desc' => 'URL logo cài trong Local SEO', 'example' => 'http://example.com/logo.png'],
            ['id' => 'search_query', 'name' => 'Từ khoá tìm kiếm', 'desc' => 'Từ khoá người dùng nhập khi tìm kiếm', 'example' => 'Cà phê Arabica'],
            ['id' => 'wc_price', 'name' => 'Giá sản phẩm (WooCommerce)', 'desc' => 'Giá bán hiện tại của sản phẩm', 'example' => '150000'],
            ['id' => 'wc_sku', 'name' => 'Mã SKU sản phẩm', 'desc' => 'Mã quản lý kho SKU sản phẩm', 'example' => 'LIX-ROB-01'],
            ['id' => 'wc_shortdesc', 'name' => 'Mô tả ngắn sản phẩm', 'desc' => 'Mô tả ngắn của sản phẩm WooCommerce', 'example' => 'Hương thơm hoa quả phong phú...'],
            ['id' => 'wc_brand', 'name' => 'Thương hiệu sản phẩm', 'desc' => 'Thương hiệu của sản phẩm WooCommerce', 'example' => 'Lix Roastery'],
        ];
    }
}
