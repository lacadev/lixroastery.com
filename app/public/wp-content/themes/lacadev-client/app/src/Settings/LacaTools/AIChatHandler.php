<?php

namespace App\Settings\LacaTools;

/**
 * AIChatHandler
 *
 * REST endpoint cho AI Chat nội bộ trong Admin.
 * Reuses AITranslationHandler (đa-provider: Gemini, Groq, OpenAI, Anthropic, DeepSeek).
 *
 * Endpoint: POST /wp-json/laca/v1/ai/chat
 */
class AIChatHandler
{
    private ?AITranslationHandler $handler = null;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_enqueue_scripts', [$this, 'localizeScript'], 20);
    }

    /**
     * Lazy-init AITranslationHandler — chỉ tạo sau khi Carbon Fields đã boot.
     */
    private function getHandler(): AITranslationHandler
    {
        if ($this->handler === null) {
            $this->handler = new AITranslationHandler();
        }
        return $this->handler;
    }

    /**
     * Đăng ký REST route.
     */
    public function registerRoutes(): void
    {
        register_rest_route('laca/v1', '/ai/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleChat'],
            'permission_callback' => function () {
                return is_user_logged_in() && current_user_can('edit_posts');
            },
            'args' => [
                'message' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => fn($v) => !empty(trim($v)),
                ],
                'post_id' => [
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                    'default'           => 0,
                ],
                'context' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ],
            ],
        ]);
    }

    /**
     * Xử lý chat request.
     */
    public function handleChat(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $message = $request->get_param('message');
        $post_id = $request->get_param('post_id');
        $context = $request->get_param('context');

        // Xây dựng context từ post nếu có
        $post_context = '';
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $post_context = sprintf(
                    "Current post: \"%s\" (ID: %d, Type: %s, Status: %s)",
                    $post->post_title,
                    $post_id,
                    $post->post_type,
                    $post->post_status
                );
            }
        }
        if ($context) {
            $post_context .= ($post_context ? "\n" : '') . $context;
        }

        // System prompt cho chat assistant
        $system_prompt = $this->buildSystemPrompt($post_context);

        // Gọi AI handler với message làm "text" và system prompt riêng
        $reply = $this->getHandler()->chat($message, $system_prompt);

        // DEBUG: log để xác định lỗi (xoá sau khi fix)
        error_log('[AIChatHandler] reply type: ' . gettype($reply));
        if (is_wp_error($reply)) {
            error_log('[AIChatHandler] WP_Error: ' . $reply->get_error_message());
        } else {
            error_log('[AIChatHandler] reply length: ' . strlen((string)$reply));
        }

        if (is_wp_error($reply)) {
            return new \WP_Error(
                'ai_chat_error',
                $reply->get_error_message(),
                ['status' => 500]
            );
        }

        if (empty($reply)) {
            return new \WP_Error(
                'ai_chat_empty',
                'AI trả về phản hồi trống. Kiểm tra API key hoặc thử lại.',
                ['status' => 500]
            );
        }

        return new \WP_REST_Response([
            'reply'   => $reply,
            'post_id' => $post_id,
        ], 200);
    }

    /**
     * Inject nonce + endpoint vào admin scripts.
     */
    public function localizeScript(): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }

        $post_id    = (int) ($_GET['post'] ?? 0);
        $post_title = '';

        if ($post_id) {
            $post = get_post($post_id);
            $post_title = $post ? $post->post_title : '';
        }

        wp_localize_script('theme-admin-js-bundle', 'lacaAIChat', [
            'endpoint' => esc_url(rest_url('laca/v1/ai/chat')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'post_id'  => $post_id,
            'context'  => $post_title ? sprintf('Đang chỉnh sửa: %s', $post_title) : '',
        ]);
    }

    /**
     * Xây dựng system prompt cho chatbot assistant.
     */
    private function buildSystemPrompt(string $context = ''): string
    {
        $site_name = get_bloginfo('name');
        $date      = date_i18n('d/m/Y');

        $prompt  = "You are a helpful, concise AI assistant inside the WordPress admin panel of the website '{$site_name}'.\n";
        $prompt .= "Today is {$date}.\n";
        $prompt .= "You help with content writing, SEO, code, and WordPress-related questions.\n";
        $prompt .= "Reply in the SAME LANGUAGE as the user's message (Vietnamese or English).\n";
        $prompt .= "Be concise and direct. Use markdown when helpful (bold, lists, code blocks).\n";
        $prompt .= "Maximum 400 words per reply unless the user asks for more.\n";

        if ($context) {
            $prompt .= "\n--- Context ---\n{$context}\n--- End Context ---\n";
        }

        return $prompt;
    }
}
