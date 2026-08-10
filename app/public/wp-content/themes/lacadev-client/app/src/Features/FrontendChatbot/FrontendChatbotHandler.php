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
    const MAX_CHARS       = 500;  // ký tự tối đa mỗi bài inject vào prompt

    public function init(): void
    {
        add_action('rest_api_init',        [$this, 'registerRoutes']);
        add_action('wp_enqueue_scripts',   [$this, 'enqueueAssets']);
        add_action('admin_menu',           [$this, 'registerMenu'], 20);
        add_action('admin_post_laca_chatbot_save', [$this, 'handleSave']);
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
                    'validate_callback' => fn($v) => !empty(trim($v)) && mb_strlen(trim($v)) <= 500,
                ],
            ],
        ]);
    }

    public function handleMessage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!get_option('laca_chatbot_enabled')) {
            return new \WP_Error('disabled', 'Chatbot đang tắt.', ['status' => 503]);
        }

        // Rate limiting per IP
        $ip     = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rl_key = self::RATE_LIMIT_KEY . md5($ip);
        $count  = (int) get_transient($rl_key);

        if ($count >= self::RATE_LIMIT_MAX) {
            return new \WP_Error('rate_limited', 'Bạn đã gửi quá nhiều tin nhắn. Vui lòng thử lại sau ít phút.', ['status' => 429]);
        }
        set_transient($rl_key, $count + 1, HOUR_IN_SECONDS);

        $message = $request->get_param('message');

        // Tìm nội dung liên quan từ DB
        $results = $this->searchContent($message);

        // Xây dựng system prompt
        $system_prompt = $this->buildSystemPrompt($results);

        // Gọi AI
        $handler = new AITranslationHandler();
        $reply   = $handler->chat($message, $system_prompt);

        if (is_wp_error($reply)) {
            return new \WP_Error('ai_error', 'Không thể kết nối AI lúc này. Vui lòng thử lại.', ['status' => 500]);
        }

        if (empty(trim((string) $reply))) {
            return new \WP_Error('ai_empty', 'AI không trả về phản hồi. Kiểm tra API key.', ['status' => 500]);
        }

        // Sources để hiển thị dưới response
        $sources = array_map(fn($r) => [
            'title' => $r['title'],
            'url'   => $r['url'],
        ], array_slice($results, 0, 3));

        return new \WP_REST_Response([
            'reply'   => (string) $reply,
            'sources' => $sources,
        ], 200);
    }

    // ── Content Search (RAG-lite) ─────────────────────────────────────────────

    /**
     * Tìm bài viết liên quan đến câu hỏi người dùng.
     * Dùng LIKE trên title + excerpt + content.
     * Tất cả public post types được tìm.
     */
    private function searchContent(string $query): array
    {
        global $wpdb;

        $keywords = $this->extractKeywords($query);

        if (empty($keywords)) {
            // Fallback: lấy trang chủ + giới thiệu
            return $this->getFallbackContent();
        }

        // Tất cả public post types (trừ attachment)
        $types    = get_post_types(['public' => true, 'exclude_from_search' => false]);
        unset($types['attachment']);
        $type_sql = "'" . implode("','", array_map('esc_sql', array_keys($types))) . "'";

        // Xây WHERE — mỗi keyword match bất kỳ cột nào
        $conditions = [];
        foreach ($keywords as $kw) {
            $like          = '%' . $wpdb->esc_like($kw) . '%';
            $conditions[]  = $wpdb->prepare(
                '(post_title LIKE %s OR post_excerpt LIKE %s OR post_content LIKE %s)',
                $like, $like, $like
            );
        }

        $where = '(' . implode(' OR ', $conditions) . ')';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT ID, post_title, post_excerpt, post_content, post_type
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ({$type_sql})
             AND {$where}
             ORDER BY post_date DESC
             LIMIT " . self::MAX_RESULTS
        );

        if (empty($rows)) {
            return $this->getFallbackContent();
        }

        $results = [];
        foreach ($rows as $row) {
            // Ưu tiên excerpt, fallback sang content
            $raw = !empty(trim($row->post_excerpt))
                ? $row->post_excerpt
                : wp_strip_all_tags($row->post_content ?? '');

            $content = mb_substr(trim($raw), 0, self::MAX_CHARS);

            $results[] = [
                'title'   => $row->post_title,
                'content' => $content,
                'url'     => get_permalink($row->ID),
                'type'    => $row->post_type,
            ];
        }

        return $results;
    }

    /**
     * Tách keywords từ câu hỏi, bỏ stop words.
     */
    private function extractKeywords(string $query): array
    {
        $stop = [
            // Tiếng Việt
            'là', 'và', 'hay', 'hoặc', 'của', 'cho', 'với', 'về', 'trong', 'các',
            'những', 'có', 'không', 'được', 'bạn', 'tôi', 'mình', 'gì', 'nào',
            'như', 'thế', 'này', 'đó', 'ở', 'từ', 'đến', 'vì', 'để', 'khi',
            'thì', 'mà', 'nên', 'phải', 'cần', 'biết', 'hỏi', 'hãy', 'cho biết',
            // English
            'the', 'a', 'an', 'is', 'are', 'do', 'does', 'this', 'that', 'what',
            'how', 'who', 'where', 'when', 'why', 'can', 'could', 'would', 'should',
            'tell', 'me', 'about', 'please', 'help', 'know', 'want', 'need',
        ];

        $words    = preg_split('/[\s,\.!?;:]+/u', mb_strtolower(trim($query)));
        $keywords = array_filter(
            $words ?? [],
            fn($w) => mb_strlen($w) >= 2 && !in_array($w, $stop, true)
        );

        return array_values(array_unique($keywords));
    }

    /**
     * Fallback khi không tìm được gì: lấy trang chính (about, home...).
     */
    private function getFallbackContent(): array
    {
        $pages = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => 3,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        return array_map(fn($p) => [
            'title'   => $p->post_title,
            'content' => mb_substr(wp_strip_all_tags($p->post_excerpt ?: $p->post_content), 0, self::MAX_CHARS),
            'url'     => get_permalink($p->ID),
            'type'    => $p->post_type,
        ], $pages);
    }

    // ── System Prompt ─────────────────────────────────────────────────────────

    private function buildSystemPrompt(array $results): string
    {
        $site_name = get_bloginfo('name');
        $bot_name  = get_option('laca_chatbot_name', 'AI Assistant');

        $prompt  = "Bạn là {$bot_name} của website {$site_name}.\n";
        $prompt .= "NGUYÊN TẮC BẮT BUỘC:\n";
        $prompt .= "1. CHỈ trả lời dựa trên nội dung website được cung cấp bên dưới.\n";
        $prompt .= "2. KHÔNG bịa đặt, suy đoán, hoặc dùng kiến thức bên ngoài.\n";
        $prompt .= "3. Khi đề cập bài viết/trang, LUÔN kèm link URL.\n";
        $prompt .= "4. Nếu câu hỏi ngoài phạm vi website, lịch sự từ chối và gợi ý liên hệ trực tiếp.\n";
        $prompt .= "5. Trả lời ngắn gọn, thân thiện. Dùng ngôn ngữ của người hỏi (Việt/Anh).\n";
        $prompt .= "6. Tối đa 200 từ mỗi câu trả lời.\n\n";

        if (empty($results)) {
            $prompt .= "Không tìm thấy thông tin liên quan trên website. Hãy thông báo lịch sự và gợi ý người dùng liên hệ trực tiếp.\n";
            return $prompt;
        }

        $prompt .= "=== NỘI DUNG WEBSITE ===\n";
        foreach ($results as $i => $r) {
            $type_label = $this->getTypeLabel($r['type']);
            $prompt .= sprintf(
                "[%d] %s: \"%s\"\nLink: %s\nTóm tắt: %s\n\n",
                $i + 1,
                $type_label,
                $r['title'],
                $r['url'],
                $r['content'] ?: '(Không có mô tả)'
            );
        }
        $prompt .= "=== HẾT NỘI DUNG ===\n";
        $prompt .= "Chỉ dùng thông tin trên để trả lời. Không thêm gì ngoài phạm vi đó.\n";

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

        wp_enqueue_script(
            'laca-frontend-chatbot',
            dirname(get_template_directory_uri()) . '/resources/scripts/theme/components/frontend-chatbot.js',
            [],
            wp_get_theme()->get('Version'),
            true
        );

        wp_localize_script('laca-frontend-chatbot', 'lacaChatbot', [
            'endpoint' => esc_url(rest_url('laca/v1/chatbot')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'name'     => esc_html(get_option('laca_chatbot_name', 'AI Assistant')),
            'greeting' => esc_html(get_option('laca_chatbot_greeting', 'Xin chào! Tôi có thể giúp bạn tìm thông tin về ' . get_bloginfo('name') . '.')),
            'color'    => sanitize_hex_color(get_option('laca_chatbot_color', '#1d4ed8')) ?: '#1d4ed8',
            'placeholder' => esc_html(get_option('laca_chatbot_placeholder', 'Hỏi về dịch vụ, bài viết...')),
        ]);
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

            <?php if ($msg === 'saved'): ?>
                <div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt.</p></div>
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
                    </table>

                    <hr>
                    <h3>Cơ chế hoạt động</h3>
                    <div style="background:#f0f6fc;border-left:3px solid #0073aa;padding:12px 16px;border-radius:3px;font-size:13px;line-height:1.8">
                        <strong>RAG-lite — Tìm nội dung trước, hỏi AI sau:</strong><br>
                        1. User hỏi → hệ thống tách keywords từ câu hỏi<br>
                        2. Tìm kiếm trong DB (Posts, Pages, tất cả CPT public)<br>
                        3. Lấy tối đa <strong>5 bài</strong>, mỗi bài tối đa <strong>500 ký tự</strong><br>
                        4. Inject vào system prompt → AI chỉ được dùng nội dung đó<br>
                        5. AI trả lời kèm link nguồn bài viết<br>
                        <br>
                        <strong>Token tối ưu:</strong> ~2500 ký tự context mỗi request (thay vì toàn bộ site)<br>
                        <strong>Rate limit:</strong> 15 request/giờ/IP để tránh lạm dụng
                    </div>

                    <p class="submit" style="margin-top:16px">
                        <button type="submit" class="button button-primary button-large">Lưu cài đặt</button>
                    </p>
                </form>

                <!-- Live preview info -->
                <div>
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
        </div>
        <?php
    }

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }
        check_admin_referer(self::NONCE, '_nonce');

        update_option('laca_chatbot_enabled',     isset($_POST['laca_chatbot_enabled']) ? '1' : '0');
        update_option('laca_chatbot_name',        sanitize_text_field($_POST['laca_chatbot_name'] ?? 'AI Assistant'));
        update_option('laca_chatbot_greeting',    sanitize_textarea_field($_POST['laca_chatbot_greeting'] ?? ''));
        update_option('laca_chatbot_placeholder', sanitize_text_field($_POST['laca_chatbot_placeholder'] ?? ''));
        update_option('laca_chatbot_color',       sanitize_hex_color($_POST['laca_chatbot_color'] ?? '#1d4ed8') ?: '#1d4ed8');

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&laca_msg=saved'));
        exit;
    }
}
