<?php

namespace App\Features\FrontendChatbot;

use App\Settings\LacaTools\AITranslationHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FrontendChatbotHandler
 *
 * Chatbot công khai cho visitor — chỉ trả lời dựa trên nội dung website.
 *
 * Cơ chế RAG-lite:
 *   1. Nhận câu hỏi từ user
 *   2. Tìm kiếm nội dung liên quan trong DB (Posts, Pages, CPTs)
 *   3. Inject nội dung tìm được vào system prompt
 *   4. AI chỉ được phép trả lời dựa trên nội dung đó
 *
 * Tiết kiệm token: chỉ gửi excerpt/nội dung ngắn của bài liên quan,
 * không gửi toàn bộ nội dung website.
 *
 * REST endpoint: POST /wp-json/laca/v1/chatbot (public)
 * Admin settings: Laca Admin → Chatbot
 *
 * Options:
 *   laca_chatbot_enabled   — '1' | '0'
 *   laca_chatbot_greeting  — string
 *   laca_chatbot_name      — string (tên bot)
 *   laca_chatbot_color     — hex color
 *   laca_chatbot_pages     — 'all' | 'home' | comma-separated post IDs
 */
class FrontendChatbotHandler
{
    const MENU_SLUG       = 'laca-chatbot';
    const PARENT_SLUG     = 'laca-admin';
    const CAP             = 'manage_options';
    const NONCE           = 'laca_chatbot_settings';
    const RATE_LIMIT_KEY  = 'laca_cbot_rl_';
    const RATE_LIMIT_MAX  = 15;   // requests per hour per IP
    const MAX_RESULTS     = 5;    // số bài tìm được tối đa
    const MAX_CHARS       = 600;  // ký tự tối đa mỗi bài inject vào prompt
    const CANDIDATES      = 30;   // số bài lấy từ SQL để chấm điểm/lọc ngôn ngữ
    const HISTORY_TURNS   = 6;    // số tin (user+bot) gần nhất gửi kèm để nhớ ngữ cảnh
    const CACHE_TTL       = 12 * HOUR_IN_SECONDS;
    const CACHE_PREFIX    = 'laca_cbot_c_';

    public function init(): void
    {
        add_action('rest_api_init',        [$this, 'registerRoutes']);
        add_action('wp_enqueue_scripts',   [$this, 'enqueueAssets']);
        add_action('admin_menu',           [$this, 'registerMenu'], 20);
        add_action('admin_post_laca_chatbot_save', [$this, 'handleSave']);
        add_action('admin_post_laca_chatbot_test', [$this, 'handleTest']);
        ChatbotLog::register();
        ChatbotLead::register();
        ChatbotLive::register();
    }

    // ── REST Route ────────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        register_rest_route('laca/v1', '/chatbot', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleMessage'],
            'permission_callback' => '__return_true',   // public endpoint
            'args' => [
                'message' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => fn($v) => is_string($v) && trim($v) !== '' && mb_strlen(trim($v)) <= 500,
                ],
                'lang' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'history' => [
                    'required' => false,
                ],
                'session' => [
                    'required'          => false,
                    'sanitize_callback' => fn($v) => substr(preg_replace('/[^a-f0-9]/', '', strtolower((string) $v)), 0, 32),
                ],
                'page_id' => [
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('laca/v1', '/chatbot/feedback', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => static function (\WP_REST_Request $r): \WP_REST_Response {
                $session = substr(preg_replace('/[^a-f0-9]/', '', strtolower((string) $r->get_param('session'))), 0, 32);
                $ok      = ChatbotLog::rate(absint($r->get_param('log_id')), $session, (int) $r->get_param('rating'));
                return new \WP_REST_Response(['ok' => $ok], 200);
            },
        ]);
    }

    public function handleMessage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!get_option('laca_chatbot_enabled')) {
            return new \WP_Error('disabled', __('Chatbot đang tắt.', 'laca'), ['status' => 503]);
        }

        // Rate limit theo IP THẬT — trước đây đọc thẳng X-Forwarded-For (khách
        // tự đặt được) nên bot đổi header là vượt giới hạn, đốt quota API.
        $ip     = class_exists(\App\Helpers\ClientIp::class) ? \App\Helpers\ClientIp::get() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $rl_key = self::RATE_LIMIT_KEY . md5($ip !== '' ? $ip : 'unknown');
        $count  = (int) get_transient($rl_key);

        if ($count >= self::RATE_LIMIT_MAX) {
            return new \WP_Error('rate_limited', __('Bạn đã gửi quá nhiều tin nhắn. Vui lòng thử lại sau ít phút.', 'laca'), ['status' => 429]);
        }
        set_transient($rl_key, $count + 1, HOUR_IN_SECONDS);

        $message = trim((string) $request->get_param('message'));
        $lang    = $this->resolveLang((string) $request->get_param('lang'));
        $history = $this->sanitizeHistory($request->get_param('history'));
        $session = (string) $request->get_param('session');
        $pageId  = absint($request->get_param('page_id'));
        $wantsLead = $this->hasLeadIntent($message);

        // 1. FAQ ghim do admin soạn → trả ngay, không gọi AI.
        $faq = ChatbotFaq::match($message, fn(string $t) => $this->extractKeywords($t));
        if ($faq !== null) {
            return $this->respond([
                'reply'   => $faq,
                'sources' => [],
            ], $session, $lang, $pageId, $message, true, 'faq', $wantsLead);
        }

        // 2. Câu hỏi độc lập lặp lại → cache.
        $cacheKey = self::CACHE_PREFIX . md5($lang . '|' . $pageId . '|' . mb_strtolower(preg_replace('/\s+/u', ' ', $message)));
        if (!$history) {
            $cached = get_transient($cacheKey);
            if (is_array($cached) && isset($cached['reply'])) {
                return $this->respond($cached, $session, $lang, $pageId, $message, !empty($cached['_found']), 'cache', $wantsLead);
            }
        }

        // 3. Trần số câu gọi AI mỗi ngày (chống đốt quota) → mời để lại thông tin.
        if ($this->dailyCapReached()) {
            return $this->respond([
                'reply'   => __('Hiện có nhiều người đang hỏi. Bạn để lại thông tin, chúng tôi sẽ liên hệ lại ngay nhé!', 'laca'),
                'sources' => [],
            ], $session, $lang, $pageId, $message, false, 'cap', true);
        }

        $results       = $this->searchContent($message, $lang, $pageId);
        $found         = $results || $this->isContactQuestion($message);
        $system_prompt = $this->buildSystemPrompt($results, $lang, $history);

        $handler = new AITranslationHandler();
        $reply   = $handler->chat($message, $system_prompt);
        $this->countAiCall();

        if (is_wp_error($reply) || trim((string) $reply) === '') {
            return new \WP_Error('ai_error', __('Không thể kết nối AI lúc này. Vui lòng thử lại.', 'laca'), ['status' => 500]);
        }

        // AI tự nói "không có thông tin" → tính là chưa trả lời được (vào báo cáo
        // "câu chưa trả lời được") và mời để lại thông tin.
        if ($found && $this->looksUnanswered((string) $reply)) {
            $found = false;
        }

        $payload = [
            'reply'   => (string) $reply,
            'sources' => $this->citedSources($results, (string) $reply),
            '_found'  => $found,
        ];
        if (!$history) {
            set_transient($cacheKey, $payload, self::CACHE_TTL);
        }

        return $this->respond($payload, $session, $lang, $pageId, $message, $found, 'ai', $wantsLead || !$found);
    }

    /** Ghi log + gắn log_id / gợi ý để lại thông tin rồi trả response. */
    private function respond(array $payload, string $session, string $lang, int $pageId, string $message, bool $found, string $source, bool $suggestLead): \WP_REST_Response
    {
        unset($payload['_found']);
        $payload['log_id']       = ChatbotLog::add($session, $lang, $pageId, $message, (string) $payload['reply'], $found, $source);
        $payload['suggest_lead'] = $suggestLead && get_option('laca_chatbot_lead_enabled', '1') === '1';
        // Chưa trả lời được / khách muốn đặt hàng → mời chat với nhân viên.
        $payload['suggest_live'] = ($suggestLead || !$found) && ChatbotLive::enabled();
        return new \WP_REST_Response($payload, 200);
    }

    /** Khách muốn đặt hàng / báo giá / hợp tác / được tư vấn → mời để lại thông tin. */
    private function hasLeadIntent(string $message): bool
    {
        return (bool) preg_match('/(đặt|order|báo giá|bao gia|giá sỉ|mua|buy|hợp tác|đối tác|partner|tư vấn|liên hệ|contact|quote|mẫu thử|sample|gọi lại|call me)/iu', $message);
    }

    private function looksUnanswered(string $reply): bool
    {
        return (bool) preg_match('/(không có (thông tin|mục|nội dung|dữ liệu)|chưa có (thông tin|nội dung)|không tìm thấy|not (available|found)|no information|doesn.t (have|contain))/iu', $reply);
    }

    /** Câu hỏi về thông tin liên hệ — trả được nhờ khối THÔNG TIN LIÊN HỆ. */
    private function isContactQuestion(string $message): bool
    {
        return (bool) preg_match('/(địa chỉ|ở đâu|giờ|mở cửa|số điện thoại|sđt|hotline|email|zalo|address|where|hours|open|phone)/iu', $message);
    }

    private function dailyCapReached(): bool
    {
        $cap = (int) get_option('laca_chatbot_daily_cap', 300);
        return $cap > 0 && (int) get_transient('laca_cbot_day_' . gmdate('Ymd')) >= $cap;
    }

    private function countAiCall(): void
    {
        $key = 'laca_cbot_day_' . gmdate('Ymd');
        set_transient($key, (int) get_transient($key) + 1, DAY_IN_SECONDS + HOUR_IN_SECONDS);
    }

    /**
     * Chỉ hiện nguồn mà câu trả lời thật sự nhắc tới (link hoặc tên bài) —
     * tránh hiện link không liên quan khi AI từ chối/không dùng bài nào.
     */
    private function citedSources(array $results, string $reply): array
    {
        $lower = mb_strtolower($reply);
        $out   = [];
        foreach ($results as $r) {
            $title = mb_strtolower(trim(wp_strip_all_tags($r['title'])));
            $short = mb_substr($title, 0, 40);
            if (mb_strpos($lower, mb_strtolower(untrailingslashit($r['url']))) !== false
                || ($short !== '' && mb_strpos($lower, $short) !== false)) {
                $out[] = ['title' => $r['title'], 'url' => $r['url']];
            }
            if (count($out) >= 3) {
                break;
            }
        }
        return $out;
    }

    /** Ngôn ngữ hợp lệ của Polylang (rỗng nếu không dùng Polylang). */
    private function resolveLang(string $lang): string
    {
        if (!function_exists('pll_languages_list')) {
            return '';
        }
        $list = (array) pll_languages_list(['fields' => 'slug']);
        if (in_array($lang, $list, true)) {
            return $lang;
        }
        return function_exists('pll_default_language') ? (string) pll_default_language() : '';
    }

    /**
     * Lịch sử hội thoại từ client: chỉ nhận role user/assistant, cắt ngắn để
     * không phình prompt.
     *
     * @return array<int,array{role:string,content:string}>
     */
    private function sanitizeHistory($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (array_slice($raw, -self::HISTORY_TURNS) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role    = ($item['role'] ?? '') === 'assistant' ? 'assistant' : (($item['role'] ?? '') === 'user' ? 'user' : '');
            $content = mb_substr(trim(sanitize_textarea_field((string) ($item['content'] ?? ''))), 0, 400);
            if ($role !== '' && $content !== '') {
                $out[] = ['role' => $role, 'content' => $content];
            }
        }
        return $out;
    }

    // ── Content Search (RAG-lite) ─────────────────────────────────────────────

    /**
     * Tìm bài liên quan, chấm điểm NGAY TRONG SQL (khớp tiêu đề nặng hơn nội
     * dung, cụm 2 từ liền nhau nặng hơn từ đơn) thay vì sắp theo ngày đăng —
     * trước đây câu nào cũng ra 5 bài mới nhất vì âm tiết tiếng Việt (cà, phê,
     * giờ…) khớp gần như mọi bài.
     */
    private function searchContent(string $query, string $lang = '', int $pageId = 0): array
    {
        global $wpdb;

        $keywords = $this->extractKeywords($query);
        if (!$keywords) {
            return [];
        }
        $phrases = $this->extractPhrases($query);

        $types = get_post_types(['public' => true, 'exclude_from_search' => false]);
        unset($types['attachment']);
        if (!$types) {
            return [];
        }
        $typeIn = implode(',', array_fill(0, count($types), '%s'));

        $scoreParts = [];
        $whereParts = [];
        $params     = [];
        foreach ($phrases as $ph) {
            $like = '%' . $wpdb->esc_like($ph) . '%';
            $scoreParts[] = '(post_title LIKE %s) * 12 + (post_content LIKE %s) * 3';
            array_push($params, $like, $like);
        }
        foreach ($keywords as $kw) {
            $like = '%' . $wpdb->esc_like($kw) . '%';
            $scoreParts[] = '(post_title LIKE %s) * 4 + (post_excerpt LIKE %s) * 2 + (post_content LIKE %s)';
            array_push($params, $like, $like, $like);
        }
        $whereParams = [];
        foreach ($keywords as $kw) {
            $like = '%' . $wpdb->esc_like($kw) . '%';
            $whereParts[] = '(post_title LIKE %s OR post_excerpt LIKE %s OR post_content LIKE %s)';
            array_push($whereParams, $like, $like, $like);
        }

        $sql = 'SELECT ID, post_title, post_excerpt, post_content, post_type, (' . implode(' + ', $scoreParts) . ') AS score'
            . " FROM {$wpdb->posts}"
            . " WHERE post_status = 'publish' AND post_password = '' AND post_type IN ({$typeIn})"
            . ' AND (' . implode(' OR ', $whereParts) . ')'
            . ' ORDER BY score DESC, post_date DESC LIMIT ' . (int) self::CANDIDATES;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- toàn bộ giá trị đi qua prepare() ở dưới.
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, array_keys($types), $whereParams)));
        if (!$rows) {
            return [];
        }

        // Cần khớp đủ nhiều từ khoá (câu dài) để bỏ kết quả chỉ trùng 1 âm tiết.
        $minHits = count($keywords) >= 3 ? (int) ceil(count($keywords) / 2) : 1;
        $results = [];
        foreach ($rows as $row) {
            if ($lang !== '' && function_exists('pll_get_post_language')) {
                $postLang = pll_get_post_language((int) $row->ID);
                if ($postLang && $postLang !== $lang) {
                    continue;
                }
            }

            $text  = $this->plainText($row->post_title . '. ' . $row->post_excerpt . ' ' . $row->post_content);
            $lower = mb_strtolower($text);
            $hits  = 0;
            foreach ($keywords as $kw) {
                if (mb_strpos($lower, $kw) !== false) {
                    $hits++;
                }
            }
            if ($hits < $minHits) {
                continue;
            }

            $results[(int) $row->ID] = $this->resultItem((int) $row->ID, $row->post_title, $row->post_type, $text, $lower, array_merge($phrases, $keywords));
            if (count($results) >= self::MAX_RESULTS) {
                break;
            }
        }

        // Bài khách ĐANG XEM khớp câu hỏi → đưa lên đầu (hỏi "bài này nói gì",
        // "sản phẩm này giá bao nhiêu" khi đang ở trang đó).
        if ($pageId > 0 && get_post_status($pageId) === 'publish' && !isset($results[$pageId])) {
            $post = get_post($pageId);
            // Bài có mật khẩu: không đưa nội dung cho AI (khách chưa nhập mật khẩu vẫn hỏi được).
            if ($post && $post->post_password === '' && is_post_type_viewable($post->post_type)) {
                $text  = $this->plainText($post->post_title . '. ' . $post->post_excerpt . ' ' . $post->post_content);
                $lower = mb_strtolower($text);
                foreach ($keywords as $kw) {
                    if (mb_strpos($lower, $kw) !== false) {
                        $results = [$pageId => $this->resultItem($pageId, $post->post_title, $post->post_type, $text, $lower, array_merge($phrases, $keywords))] + $results;
                        break;
                    }
                }
            }
        } elseif ($pageId > 0 && isset($results[$pageId])) {
            $results = [$pageId => $results[$pageId]] + $results;
        }

        return array_slice(array_values($results), 0, self::MAX_RESULTS);
    }

    private function resultItem(int $id, string $title, string $type, string $text, string $lower, array $needles): array
    {
        $content = $this->snippet($text, $lower, $needles);
        // Sản phẩm WooCommerce: kèm giá + tình trạng để trả lời "giá bao nhiêu", "còn hàng không".
        if ($type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($id);
            if ($product) {
                $price = wp_strip_all_tags(html_entity_decode((string) $product->get_price_html()));
                $stock = $product->is_in_stock() ? 'còn hàng' : 'hết hàng';
                $content = trim(($price !== '' ? "Giá: {$price}. " : '') . "Tình trạng: {$stock}. " . $content);
            }
        }
        return ['title' => $title, 'content' => $content, 'url' => get_permalink($id), 'type' => $type];
    }

    private function plainText(string $html): string
    {
        $text = wp_strip_all_tags(strip_shortcodes($html));
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Đoạn văn quanh chỗ khớp đầu tiên (thay vì luôn lấy N ký tự đầu bài). */
    private function snippet(string $text, string $lower, array $needles): string
    {
        $pos = null;
        foreach ($needles as $n) {
            $p = mb_strpos($lower, $n);
            if ($p !== false) {
                $pos = $p;
                break;
            }
        }
        $start = $pos === null ? 0 : max(0, $pos - 150);
        $out   = mb_substr($text, $start, self::MAX_CHARS);
        return ($start > 0 ? '…' : '') . $out . (mb_strlen($text) > $start + self::MAX_CHARS ? '…' : '');
    }

    /**
     * Tách keywords từ câu hỏi, bỏ stop words.
     */
    private function extractKeywords(string $query): array
    {
        $words = $this->tokenize($query);
        return array_values(array_unique(array_filter($words, fn($w) => mb_strlen($w) >= 2 && !in_array($w, self::stopWords(), true))));
    }

    /** Cụm 2 từ liền nhau (bỏ stop word) — "rang sáng", "agtron scale"… */
    private function extractPhrases(string $query): array
    {
        $words   = array_values(array_filter($this->tokenize($query), fn($w) => !in_array($w, self::stopWords(), true)));
        $phrases = [];
        for ($i = 0; $i < count($words) - 1; $i++) {
            $phrases[] = $words[$i] . ' ' . $words[$i + 1];
        }
        return array_slice(array_values(array_unique($phrases)), 0, 4);
    }

    /** @return string[] */
    private function tokenize(string $query): array
    {
        return array_values(array_filter((array) preg_split('/[\s,\.!?;:"\'()\[\]]+/u', mb_strtolower(trim($query)))));
    }

    /** @return string[] */
    private static function stopWords(): array
    {
        return [
            // Tiếng Việt
            'là', 'và', 'hay', 'hoặc', 'của', 'cho', 'với', 'về', 'trong', 'các',
            'những', 'có', 'không', 'được', 'bạn', 'tôi', 'mình', 'gì', 'nào',
            'như', 'thế', 'này', 'đó', 'ở', 'từ', 'đến', 'vì', 'để', 'khi',
            'thì', 'mà', 'nên', 'phải', 'cần', 'biết', 'hỏi', 'hãy', 'ạ', 'ơi',
            'shop', 'bên', 'em', 'anh', 'chị', 'muốn', 'xin', 'vui', 'lòng',
            // English
            'the', 'a', 'an', 'is', 'are', 'do', 'does', 'this', 'that', 'what',
            'how', 'who', 'where', 'when', 'why', 'can', 'could', 'would', 'should',
            'tell', 'me', 'about', 'please', 'help', 'know', 'want', 'need', 'you', 'your', 'of', 'to', 'in', 'for',
        ];
    }

    /**
     * Thông tin liên hệ từ Laca Theme (Contact) theo ngôn ngữ — luôn đưa vào
     * prompt để trả lời được câu cơ bản (địa chỉ, SĐT, giờ mở cửa…), vốn không
     * nằm trong bài viết nào nên trước đây chatbot không tìm được.
     *
     * @return array<string,string>
     */
    private function contactInfo(string $lang): array
    {
        // Trong request REST, Carbon Fields có thể chưa boot (carbon_get_theme_option
        // trả rỗng) → đọc thẳng option Carbon lưu dạng "_{tên}{lang}".
        $get = static function (string $name) use ($lang): string {
            foreach (array_unique([$name . $lang, $name . 'vi', $name . 'all', $name]) as $key) {
                $v = function_exists('carbon_get_theme_option') ? carbon_get_theme_option($key) : null;
                if (!is_string($v) || trim($v) === '') {
                    $v = get_option('_' . $key, '');
                }
                if (is_string($v) && trim($v) !== '') {
                    return trim(wp_strip_all_tags($v));
                }
            }
            return '';
        };

        return array_filter([
            'Địa chỉ'         => $get('address'),
            'Điện thoại'      => $get('phone_number'),
            'Email'           => $get('email'),
            'Giờ làm việc'    => $get('hour_working'),
            'Zalo'            => $get('zalo'),
            'Facebook'        => $get('facebook'),
        ]);
    }

    // ── System Prompt ─────────────────────────────────────────────────────────

    private function buildSystemPrompt(array $results, string $lang = '', array $history = []): string
    {
        $site_name = get_bloginfo('name');
        $bot_name  = get_option('laca_chatbot_name', 'AI Assistant');

        $prompt  = "Bạn là {$bot_name} của website {$site_name}.\n";
        $prompt .= "NGUYÊN TẮC BẮT BUỘC:\n";
        $prompt .= "1. CHỈ trả lời dựa trên THÔNG TIN LIÊN HỆ và NỘI DUNG WEBSITE bên dưới.\n";
        $prompt .= "2. KHÔNG bịa đặt, suy đoán, hoặc dùng kiến thức bên ngoài.\n";
        $prompt .= "3. Khi đề cập bài viết/trang, kèm link URL.\n";
        $prompt .= "4. Nếu không có thông tin, nói rõ là chưa có và gợi ý liên hệ trực tiếp (dùng thông tin liên hệ bên dưới).\n";
        $prompt .= "5. Trả lời ngắn gọn, thân thiện, bằng ĐÚNG ngôn ngữ người hỏi dùng (Việt/Anh).\n";
        $prompt .= "6. Tối đa 200 từ mỗi câu trả lời. Không dùng Markdown phức tạp.\n\n";

        $contact = $this->contactInfo($lang);
        if ($contact) {
            $prompt .= "=== THÔNG TIN LIÊN HỆ ===\n";
            foreach ($contact as $label => $value) {
                $prompt .= "{$label}: {$value}\n";
            }
            $prompt .= "\n";
        }

        if ($results) {
            $prompt .= "=== NỘI DUNG WEBSITE ===\n";
            foreach ($results as $i => $r) {
                $prompt .= sprintf(
                    "[%d] %s: \"%s\"\nLink: %s\nTrích đoạn: %s\n\n",
                    $i + 1,
                    $this->getTypeLabel($r['type']),
                    $r['title'],
                    $r['url'],
                    $r['content'] ?: '(Không có mô tả)'
                );
            }
            $prompt .= "=== HẾT NỘI DUNG ===\n";
        } else {
            $prompt .= "Không tìm thấy bài viết liên quan trên website cho câu hỏi này.\n";
        }

        if ($history) {
            $prompt .= "\n=== HỘI THOẠI TRƯỚC (để hiểu câu hỏi tiếp nối) ===\n";
            foreach ($history as $h) {
                $prompt .= ($h['role'] === 'user' ? 'Khách: ' : 'Bạn: ') . $h['content'] . "\n";
            }
        }

        return $prompt;
    }

    private function getTypeLabel(string $type): string
    {
        $labels = [
            'post'    => 'Bài viết',
            'page'    => 'Trang',
            'service' => 'Dịch vụ',
            'project' => 'Dự án',
        ];
        if (isset($labels[$type])) {
            return $labels[$type];
        }
        // Lấy label từ WP nếu có
        $obj = get_post_type_object($type);
        return $obj ? $obj->labels->singular_name : ucfirst($type);
    }

    // ── Frontend Assets ───────────────────────────────────────────────────────

    public function enqueueAssets(): void
    {
        if (get_option('laca_chatbot_enabled', '0') !== '1') {
            return;
        }

        $rel  = '/resources/scripts/theme/components/frontend-chatbot.js';
        $file = dirname(get_template_directory()) . $rel;
        wp_enqueue_script(
            'laca-frontend-chatbot',
            dirname(get_template_directory_uri()) . $rel,
            [],
            file_exists($file) ? (string) filemtime($file) : wp_get_theme()->get('Version'),
            true
        );

        $lang    = function_exists('pll_current_language') ? (string) pll_current_language() : '';
        $handoff = get_option('laca_chatbot_handoff', '1') === '1' ? $this->handoffLinks($lang) : [];

        wp_localize_script('laca-frontend-chatbot', 'lacaChatbot', [
            'endpoint'    => esc_url_raw(rest_url('laca/v1/chatbot')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'lang'        => $lang,
            'name'        => get_option('laca_chatbot_name', 'AI Assistant'),
            'greeting'    => get_option('laca_chatbot_greeting', '') ?: sprintf(
                /* translators: %s: site name */
                __('Xin chào! Tôi có thể giúp bạn tìm thông tin về %s.', 'laca'),
                get_bloginfo('name')
            ),
            'color'       => sanitize_hex_color(get_option('laca_chatbot_color', '#1d4ed8')) ?: '#1d4ed8',
            'placeholder' => get_option('laca_chatbot_placeholder', '') ?: __('Hỏi về dịch vụ, bài viết...', 'laca'),
            'handoff'     => $handoff,
            'pageId'      => (int) get_queried_object_id(),
            'suggestions' => ChatbotFaq::suggestions(),
            'leadEnabled' => get_option('laca_chatbot_lead_enabled', '1') === '1',
            'leadEndpoint'     => esc_url_raw(rest_url('laca/v1/chatbot/lead')),
            'feedbackEndpoint' => esc_url_raw(rest_url('laca/v1/chatbot/feedback')),
            'liveEnabled'      => ChatbotLive::enabled(),
            'liveEndpoint'     => esc_url_raw(rest_url('laca/v1/chatbot/live/')),
            // Câu chữ giao diện đi qua gettext (JS không ghi cứng chữ).
            'i18n'        => [
                'close'    => __('Đóng', 'laca'),
                'send'     => __('Gửi', 'laca'),
                'loading'  => __('Đang trả lời…', 'laca'),
                'error'    => __('Đã có lỗi xảy ra. Vui lòng thử lại.', 'laca'),
                'network'  => __('Không thể kết nối tới máy chủ. Vui lòng kiểm tra kết nối internet.', 'laca'),
                'contact'  => __('Cần người hỗ trợ?', 'laca'),
                'call'     => __('Gọi', 'laca'),
                'zalo'     => __('Zalo', 'laca'),
                'leadOpen'    => __('Để lại thông tin', 'laca'),
                'leadTitle'   => __('Để lại thông tin, chúng tôi sẽ liên hệ lại', 'laca'),
                'leadName'    => __('Tên của bạn', 'laca'),
                'leadContact' => __('Số điện thoại hoặc email', 'laca'),
                'leadMessage' => __('Bạn cần hỗ trợ gì?', 'laca'),
                'leadSubmit'  => __('Gửi thông tin', 'laca'),
                'leadCancel'  => __('Huỷ', 'laca'),
                'helpful'     => __('Câu trả lời hữu ích', 'laca'),
                'notHelpful'  => __('Câu trả lời chưa hữu ích', 'laca'),
                'thanks'      => __('Cảm ơn bạn đã đánh giá!', 'laca'),
                'liveOpen'        => __('Chat với nhân viên', 'laca'),
                'liveConnecting'  => __('Đã báo nhân viên. Bạn cứ nhắn, nhân viên sẽ trả lời ngay tại đây.', 'laca'),
                'liveNoReply'     => __('Nhân viên đang bận. Bạn có thể để lại thông tin để chúng tôi liên hệ lại, hoặc tiếp tục chờ tại đây.', 'laca'),
                'liveEnded'       => __('Cuộc chat với nhân viên đã kết thúc.', 'laca'),
                'liveEnd'         => __('Kết thúc', 'laca'),
                'liveStatus'      => __('Đang chat với nhân viên', 'laca'),
                'livePlaceholder' => __('Nhắn cho nhân viên…', 'laca'),
                'agent'           => __('Nhân viên', 'laca'),
                'newMessage'      => __('Có tin nhắn mới', 'laca'),
            ],
        ]);
    }

    /**
     * Nút chuyển sang người thật (gọi / Zalo) lấy từ Laca Theme → Contact.
     *
     * @return array<int,array{type:string,url:string}>
     */
    private function handoffLinks(string $lang): array
    {
        $info  = $this->contactInfo($lang);
        $links = [];
        if (!empty($info['Điện thoại'])) {
            $tel = preg_replace('/[^0-9+]/', '', $info['Điện thoại']);
            if ($tel !== '') {
                $links[] = ['type' => 'call', 'url' => 'tel:' . $tel];
            }
        }
        if (!empty($info['Zalo'])) {
            $zalo = $info['Zalo'];
            $url  = preg_match('#^https?://#i', $zalo) ? $zalo : 'https://zalo.me/' . preg_replace('/[^0-9]/', '', $zalo);
            $links[] = ['type' => 'zalo', 'url' => esc_url_raw($url)];
        }
        return $links;
    }

    /** Gọi thử AI 1 câu ngắn; trả '' nếu OK, ngược lại là thông báo lỗi. */
    private function testAi(): string
    {
        $reply = (new AITranslationHandler())->chat('Reply with exactly: OK', 'You are a connectivity test.');
        if (is_wp_error($reply)) {
            return $reply->get_error_message();
        }
        return trim((string) $reply) === '' ? __('AI không trả về phản hồi.', 'laca') : '';
    }

    public function handleTest(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE, '_nonce');

        $err = $this->testAi();
        set_transient('laca_cbot_test_' . get_current_user_id(), $err === '' ? 'ok' : $err, 5 * MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&laca_msg=tested'));
        exit;
    }

    // ── Admin Menu & Settings ─────────────────────────────────────────────────

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            'Chatbot Frontend',
            'Chatbot',
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }

        $msg     = sanitize_key($_GET['laca_msg'] ?? '');
        $enabled = get_option('laca_chatbot_enabled', '0');
        $name    = get_option('laca_chatbot_name', 'AI Assistant');
        $greeting = get_option('laca_chatbot_greeting', 'Xin chào! Tôi có thể giúp bạn tìm thông tin trên website.');
        $color   = get_option('laca_chatbot_color', '#1d4ed8');
        $placeholder = get_option('laca_chatbot_placeholder', 'Hỏi về dịch vụ, bài viết...');
        ?>
        <div class="wrap">
            <h1>Chatbot Frontend</h1>
            <p style="color:#666;margin-top:0">Chatbot công khai cho visitor — chỉ trả lời dựa trên nội dung website.</p>

            <?php
            $testResult = get_transient('laca_cbot_test_' . get_current_user_id());
            if ($msg === 'saved'): ?>
                <div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt.</p></div>
            <?php elseif ($msg === 'ai_failed'): ?>
                <div class="notice notice-error"><p><strong>Chưa bật được chatbot:</strong> không gọi được AI. <?php echo esc_html((string) $testResult); ?><br>Kiểm tra API key ở Laca Admin → AI &amp; API Key rồi lưu lại.</p></div>
            <?php elseif ($msg === 'tested'): ?>
                <?php if ($testResult === 'ok'): ?>
                    <div class="notice notice-success is-dismissible"><p>Kết nối AI hoạt động bình thường.</p></div>
                <?php else: ?>
                    <div class="notice notice-error"><p><strong>Kết nối AI lỗi:</strong> <?php echo esc_html((string) $testResult); ?></p></div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;max-width:1000px">
                <!-- Settings form -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE, '_nonce'); ?>
                    <input type="hidden" name="action" value="laca_chatbot_save">

                    <table class="form-table">
                        <tr>
                            <th>Bật Chatbot</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="laca_chatbot_enabled" value="1" <?php checked($enabled, '1'); ?>>
                                    Hiển thị chatbot cho visitor trên frontend
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Tên bot</th>
                            <td>
                                <input type="text" name="laca_chatbot_name" class="regular-text"
                                    value="<?php echo esc_attr($name); ?>" placeholder="AI Assistant">
                            </td>
                        </tr>
                        <tr>
                            <th>Lời chào</th>
                            <td>
                                <textarea name="laca_chatbot_greeting" class="large-text" rows="3"><?php echo esc_textarea($greeting); ?></textarea>
                                <p class="description">Tin nhắn đầu tiên khi user mở chatbot.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Placeholder</th>
                            <td>
                                <input type="text" name="laca_chatbot_placeholder" class="regular-text"
                                    value="<?php echo esc_attr($placeholder); ?>">
                                <p class="description">Gợi ý hiển thị trong ô nhập.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Màu chủ đạo</th>
                            <td>
                                <input type="color" name="laca_chatbot_color"
                                    value="<?php echo esc_attr($color); ?>">
                                <span style="color:#666;font-size:12px;margin-left:8px">Màu header và nút gửi</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Nút liên hệ</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="laca_chatbot_handoff" value="1" <?php checked(get_option('laca_chatbot_handoff', '1'), '1'); ?>>
                                    Hiện nút "Gọi" / "Zalo" trong khung chat
                                </label>
                                <p class="description">Lấy số điện thoại và Zalo ở Laca Theme → Contact.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Để lại thông tin</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="laca_chatbot_lead_enabled" value="1" <?php checked(get_option('laca_chatbot_lead_enabled', '1'), '1'); ?>>
                                    Cho khách để lại tên + SĐT/email ngay trong khung chat
                                </label>
                                <p>
                                    <label for="laca-cbot-lead-form">Lưu vào form:</label>
                                    <select id="laca-cbot-lead-form" name="laca_chatbot_lead_form">
                                        <option value="0"><?php echo esc_html('— Chưa chọn (chỉ gửi email admin) —'); ?></option>
                                        <?php foreach ((class_exists(\App\Databases\ContactFormTable::class) ? \App\Databases\ContactFormTable::getAllForms() : []) as $cf): ?>
                                            <option value="<?php echo esc_attr((string) $cf['id']); ?>" <?php selected((int) get_option('laca_chatbot_lead_form', 0), (int) $cf['id']); ?>><?php echo esc_html('#' . $cf['id'] . ' ' . $cf['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <p class="description">Lead lưu vào Submissions của form đã chọn, gửi email theo mẫu của form đó và báo kênh Discord Leads — kèm tóm tắt cuộc chat. Chatbot tự gợi ý khi khách hỏi đặt hàng, báo giá, hợp tác…</p>
                            </td>
                        </tr>
                        <?php ChatbotLive::renderSettingsRows(); ?>
                        <tr>
                            <th><label for="laca-cbot-suggestions">Câu hỏi gợi ý</label></th>
                            <td>
                                <textarea id="laca-cbot-suggestions" name="laca_chatbot_suggestions" class="large-text" rows="4"><?php echo esc_textarea((string) get_option('laca_chatbot_suggestions', '')); ?></textarea>
                                <p class="description">Mỗi dòng 1 câu (tối đa 6) — hiện thành nút bấm khi khách mở chat.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="laca-cbot-faq">FAQ ghim</label></th>
                            <td>
                                <textarea id="laca-cbot-faq" name="laca_chatbot_faq" class="large-text code" rows="8"><?php echo esc_textarea((string) get_option('laca_chatbot_faq', '')); ?></textarea>
                                <p class="description">Mỗi khối cách nhau 1 dòng trống. Dòng đầu là câu hỏi (nhiều cách hỏi tách bằng <code>|</code>), các dòng sau là câu trả lời. Khớp câu hỏi → trả ngay câu trả lời này, không gọi AI.<br>Ví dụ:<br><code>Giá sỉ | báo giá sỉ | mua số lượng lớn</code><br><code>Vui lòng để lại thông tin, bộ phận kinh doanh sẽ gửi báo giá trong 24 giờ.</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Nhật ký hội thoại</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="laca_chatbot_log_enabled" value="1" <?php checked(get_option('laca_chatbot_log_enabled', '1'), '1'); ?>>
                                    Lưu câu hỏi/trả lời để xem báo cáo bên dưới
                                </label>
                                <p>
                                    <label for="laca-cbot-log-days">Tự xoá sau</label>
                                    <input type="number" id="laca-cbot-log-days" name="laca_chatbot_log_days" min="1" max="365" class="small-text" value="<?php echo esc_attr((string) ChatbotLog::days()); ?>"> ngày
                                </p>
                                <p class="description">Không lưu IP — chỉ mã phiên ngẫu nhiên để gom các câu cùng một cuộc chat.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="laca-cbot-cap">Giới hạn câu AI / ngày</label></th>
                            <td>
                                <input type="number" id="laca-cbot-cap" name="laca_chatbot_daily_cap" min="0" class="small-text" value="<?php echo esc_attr((string) get_option('laca_chatbot_daily_cap', 300)); ?>">
                                <p class="description">Toàn site (0 = không giới hạn). Vượt mức → chatbot mời khách để lại thông tin thay vì gọi AI. FAQ và câu trả lời đã cache không tính.</p>
                            </td>
                        </tr>
                    </table>

                    <hr>
                    <h3>Cơ chế hoạt động</h3>
                    <div style="background:#f0f6fc;border-left:3px solid #0073aa;padding:12px 16px;border-radius:3px;font-size:13px;line-height:1.8">
                        <strong>RAG-lite — Tìm nội dung trước, hỏi AI sau:</strong><br>
                        1. Tách từ khoá + cụm 2 từ từ câu hỏi<br>
                        2. Tìm trong Posts, Pages, mọi CPT public — <strong>chấm điểm</strong> (khớp tiêu đề nặng hơn nội dung), chỉ lấy bài <strong>cùng ngôn ngữ</strong> trang đang xem<br>
                        3. Lấy tối đa <strong>5 bài</strong>, mỗi bài một <strong>đoạn quanh chỗ khớp</strong> (~600 ký tự)<br>
                        4. Luôn kèm <strong>thông tin liên hệ</strong> (Laca Theme → Contact) và 3 lượt hội thoại gần nhất<br>
                        5. AI chỉ trả lời dựa trên các nội dung đó, kèm link nguồn<br>
                        <br>
                        <strong>Tiết kiệm:</strong> câu hỏi trùng trả từ cache 12 giờ (xoá khi lưu cài đặt)<br>
                        <strong>Rate limit:</strong> 15 câu/giờ/IP (IP thật, không tin header do khách gửi)<br>
                        <strong>An toàn:</strong> chỉ bật được khi kết nối AI hoạt động
                    </div>

                    <p class="submit" style="margin-top:16px">
                        <button type="submit" class="button button-primary button-large">Lưu cài đặt</button>
                    </p>
                </form>

                <!-- Live preview info -->
                <div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:12px">
                        <?php wp_nonce_field(self::NONCE, '_nonce'); ?>
                        <input type="hidden" name="action" value="laca_chatbot_test">
                        <button type="submit" class="button">Kiểm tra kết nối AI</button>
                    </form>
                    <?php ChatbotLive::renderConnectBox(self::NONCE); ?>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)">
                        <div style="background:<?php echo esc_attr($color); ?>;padding:12px 16px;color:#fff;font-weight:600;font-size:14px">
                            ✦ <?php echo esc_html($name); ?>
                            <span style="float:right;width:8px;height:8px;background:#4ade80;border-radius:50%;display:inline-block;margin-top:3px"></span>
                        </div>
                        <div style="padding:16px;background:#f9f9f9;min-height:120px;font-size:13px;color:#555">
                            <div style="background:#e8f4ff;padding:8px 12px;border-radius:12px 12px 12px 2px;display:inline-block;max-width:80%">
                                <?php echo esc_html($greeting ?: 'Lời chào của bạn sẽ hiển thị ở đây...'); ?>
                            </div>
                        </div>
                        <div style="padding:10px;background:#fff;border-top:1px solid #eee;display:flex;gap:8px">
                            <input type="text" disabled placeholder="<?php echo esc_attr($placeholder); ?>"
                                style="flex:1;border:1px solid #ddd;border-radius:20px;padding:6px 12px;font-size:12px;outline:none">
                            <button disabled style="background:<?php echo esc_attr($color); ?>;border:none;border-radius:50%;width:32px;height:32px;color:#fff;cursor:pointer;font-size:14px">➤</button>
                        </div>
                    </div>
                    <p style="color:#666;font-size:12px;text-align:center;margin-top:8px">Preview (cần lưu để cập nhật)</p>
                </div>
            </div>

            <?php $this->renderReport(); ?>
        </div>
        <?php
    }

    /** Báo cáo nhật ký: thống kê 30 ngày, câu chưa trả lời được, hội thoại gần đây. */
    private function renderReport(): void
    {
        $stats      = ChatbotLog::stats(30);
        $unanswered = ChatbotLog::unanswered(20, 30);
        $recent     = ChatbotLog::recent(30);
        $aiToday    = (int) get_transient('laca_cbot_day_' . gmdate('Ymd'));
        $cap        = (int) get_option('laca_chatbot_daily_cap', 300);
        $tiles      = [
            [__('Câu hỏi', 'laca'), (string) $stats['total'], ''],
            [__('Cuộc chat', 'laca'), (string) $stats['sessions'], ''],
            [__('Chưa trả lời được', 'laca'), (string) $stats['unanswered'], ''],
            [__('Trả lời bằng FAQ', 'laca'), (string) $stats['faq'], ''],
            ['👍 / 👎', $stats['up'] . ' / ' . $stats['down'], ''],
            /* translators: %s: giới hạn câu gọi AI mỗi ngày */
            [__('Gọi AI hôm nay', 'laca'), (string) $aiToday, $cap ? sprintf(__('trên %s', 'laca'), number_format_i18n($cap)) : __('không giới hạn', 'laca')],
        ];
        $rating = ['-1' => '👎', '0' => '', '1' => '👍'];
        $time   = static fn(string $dt): string => $dt !== '' ? mysql2date('d/m H:i', $dt) : '';
        ?>
        <section class="laca-cbot-report">
            <h2 class="laca-cbot-report__title"><?php esc_html_e('Báo cáo 30 ngày', 'laca'); ?></h2>
            <?php if (!ChatbotLog::enabled()): ?>
                <p class="laca-cbot-report__note"><?php esc_html_e('Nhật ký đang tắt — bật "Nhật ký hội thoại" ở trên để có báo cáo.', 'laca'); ?></p>
            <?php endif; ?>

            <div class="laca-cbot-report__stats">
                <?php foreach ($tiles as [$label, $value, $help]): ?>
                    <div class="laca-card laca-cbot-report__stat">
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php echo esc_html($label); ?></span>
                            <span class="laca-stat__value"><?php echo esc_html($value); ?></span>
                            <?php if ($help !== ''): ?>
                                <span class="laca-stat__help"><?php echo esc_html($help); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="laca-cbot-report__grid">
                <div class="laca-card">
                    <div class="laca-card__header">
                        <h3 class="laca-card__header-title"><?php esc_html_e('Câu chưa trả lời được', 'laca'); ?></h3>
                        <p class="laca-card__header-description"><?php esc_html_e('Website chưa có nội dung cho các câu này — gợi ý viết bài hoặc thêm vào FAQ ghim.', 'laca'); ?></p>
                    </div>
                    <?php if (!$unanswered): ?>
                        <div class="laca-empty"><p class="laca-empty__description"><?php esc_html_e('Chưa có câu nào.', 'laca'); ?></p></div>
                    <?php else: ?>
                        <div class="laca-cbot-report__scroll">
                            <table class="laca-table">
                                <thead><tr>
                                    <th><?php esc_html_e('Câu hỏi', 'laca'); ?></th>
                                    <th class="laca-cbot-report__num"><?php esc_html_e('Số lần', 'laca'); ?></th>
                                    <th class="laca-cbot-report__time"><?php esc_html_e('Gần nhất', 'laca'); ?></th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($unanswered as $u): ?>
                                    <tr>
                                        <td><?php echo esc_html((string) $u['q']); ?></td>
                                        <td class="laca-cbot-report__num"><?php echo esc_html((string) $u['n']); ?></td>
                                        <td class="laca-cbot-report__time"><?php echo esc_html($time((string) $u['last_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="laca-card">
                    <div class="laca-card__header">
                        <h3 class="laca-card__header-title"><?php esc_html_e('Hội thoại gần đây', 'laca'); ?></h3>
                        <p class="laca-card__header-description"><?php esc_html_e('30 câu hỏi mới nhất. ⚠ = chưa trả lời được.', 'laca'); ?></p>
                    </div>
                    <?php if (!$recent): ?>
                        <div class="laca-empty"><p class="laca-empty__description"><?php esc_html_e('Chưa có hội thoại nào.', 'laca'); ?></p></div>
                    <?php else: ?>
                        <div class="laca-cbot-report__scroll">
                            <table class="laca-table">
                                <thead><tr>
                                    <th class="laca-cbot-report__time"><?php esc_html_e('Thời gian', 'laca'); ?></th>
                                    <th><?php esc_html_e('Câu hỏi / trả lời', 'laca'); ?></th>
                                    <th class="laca-cbot-report__num"><?php esc_html_e('Nguồn', 'laca'); ?></th>
                                    <th class="laca-cbot-report__num"><?php esc_html_e('Đánh giá', 'laca'); ?></th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($recent as $r): ?>
                                    <tr>
                                        <td class="laca-cbot-report__time"><?php echo esc_html($time((string) $r['created_at'])); ?></td>
                                        <td>
                                            <span class="laca-cbot-report__q"><?php echo esc_html((string) $r['question']); ?></span>
                                            <span class="laca-cbot-report__a"><?php echo esc_html(wp_trim_words((string) $r['answer'], 30)); ?></span>
                                        </td>
                                        <td class="laca-cbot-report__num laca-cbot-report__flag"><?php echo esc_html(((int) $r['found'] ? '' : '⚠ ') . $r['source']); ?></td>
                                        <td class="laca-cbot-report__num"><?php echo esc_html($rating[(string) $r['rating']] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
    }

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }
        check_admin_referer(self::NONCE, '_nonce');

        $wantEnabled = isset($_POST['laca_chatbot_enabled']);
        $msg         = 'saved';
        // Không cho bật khi AI chưa chạy được — tránh khách chỉ nhận lỗi.
        if ($wantEnabled) {
            $err = $this->testAi();
            if ($err !== '') {
                $wantEnabled = false;
                $msg         = 'ai_failed';
                set_transient('laca_cbot_test_' . get_current_user_id(), $err, 5 * MINUTE_IN_SECONDS);
            }
        }

        update_option('laca_chatbot_enabled',     $wantEnabled ? '1' : '0');
        update_option('laca_chatbot_handoff',     isset($_POST['laca_chatbot_handoff']) ? '1' : '0');
        update_option('laca_chatbot_lead_enabled', isset($_POST['laca_chatbot_lead_enabled']) ? '1' : '0');
        update_option('laca_chatbot_lead_form',   absint($_POST['laca_chatbot_lead_form'] ?? 0));
        update_option('laca_chatbot_suggestions', sanitize_textarea_field(wp_unslash($_POST['laca_chatbot_suggestions'] ?? '')));
        update_option('laca_chatbot_faq',         sanitize_textarea_field(wp_unslash($_POST['laca_chatbot_faq'] ?? '')));
        update_option('laca_chatbot_log_enabled', isset($_POST['laca_chatbot_log_enabled']) ? '1' : '0');
        update_option('laca_chatbot_log_days',    max(1, min(365, absint($_POST['laca_chatbot_log_days'] ?? 30))));
        update_option('laca_chatbot_daily_cap',   absint($_POST['laca_chatbot_daily_cap'] ?? 300));
        ChatbotLive::saveSettings($_POST);
        update_option('laca_chatbot_name',        sanitize_text_field(wp_unslash($_POST['laca_chatbot_name'] ?? 'AI Assistant')));
        update_option('laca_chatbot_greeting',    sanitize_textarea_field(wp_unslash($_POST['laca_chatbot_greeting'] ?? '')));
        update_option('laca_chatbot_placeholder', sanitize_text_field(wp_unslash($_POST['laca_chatbot_placeholder'] ?? '')));
        update_option('laca_chatbot_color',       sanitize_hex_color(wp_unslash($_POST['laca_chatbot_color'] ?? '#1d4ed8')) ?: '#1d4ed8');

        // Đổi cài đặt → câu trả lời cũ trong cache có thể không còn đúng.
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%',
            $wpdb->esc_like('_transient_timeout_' . self::CACHE_PREFIX) . '%'
        ));

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&laca_msg=' . $msg));
        exit;
    }
}
