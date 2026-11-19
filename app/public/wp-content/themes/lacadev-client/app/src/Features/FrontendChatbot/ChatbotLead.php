<?php

namespace App\Features\FrontendChatbot;

use App\Databases\ContactFormTable;
use App\Features\ContactForm\ContactFormEmailService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Khách để lại thông tin ngay trong khung chat → đi đúng luồng của form liên
 * hệ có sẵn: lưu Submission của form admin chọn (option laca_chatbot_lead_form),
 * gửi email theo mẫu của form đó, bắn hook laca_contact_form_submitted (Discord
 * Hub báo lead). Kèm tóm tắt cuộc chat để admin biết khách đã hỏi gì.
 *
 * REST: POST /wp-json/laca/v1/chatbot/lead (public, có honeypot + rate limit).
 */
class ChatbotLead
{
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_TTL = 600;
    public const HONEYPOT        = 'website';

    public static function register(): void
    {
        add_action('rest_api_init', static function (): void {
            register_rest_route('laca/v1', '/chatbot/lead', [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
        add_filter('laca_rest_public_routes', static function (array $routes): array {
            $routes[] = '/laca/v1/chatbot/lead';
            $routes[] = '/laca/v1/chatbot/feedback';
            return $routes;
        });
    }

    public static function handle(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $p = (array) $request->get_json_params();

        // Bot điền ô ẩn → "thành công" giả, không lưu/gửi.
        if (trim((string) ($p[self::HONEYPOT] ?? '')) !== '') {
            return new \WP_REST_Response(['ok' => true, 'message' => self::thanks()], 200);
        }

        $ip = class_exists(\App\Helpers\ClientIp::class) ? \App\Helpers\ClientIp::get() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (self::rateLimited($ip)) {
            return new \WP_Error('rate_limited', __('Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.', 'laca'), ['status' => 429]);
        }

        $name    = mb_substr(sanitize_text_field((string) ($p['name'] ?? '')), 0, 120);
        $contact = mb_substr(sanitize_text_field((string) ($p['contact'] ?? '')), 0, 120);
        $message = mb_substr(sanitize_textarea_field((string) ($p['message'] ?? '')), 0, 1000);
        $lang    = sanitize_key((string) ($p['lang'] ?? ''));
        $pageId  = absint($p['page_id'] ?? 0);

        $email = is_email($contact) ? sanitize_email($contact) : '';
        $phone = $email === '' ? preg_replace('/[^0-9+]/', '', $contact) : '';
        if ($name === '' || ($email === '' && strlen((string) $phone) < 8)) {
            return new \WP_Error('invalid', __('Vui lòng nhập tên và số điện thoại hoặc email hợp lệ.', 'laca'), ['status' => 422]);
        }

        $transcript = self::transcript($p['history'] ?? []);
        $pageUrl    = $pageId ? (string) get_permalink($pageId) : '';
        $fullMsg    = trim($message
            . ($pageUrl !== '' ? "\n\n" . sprintf(__('Trang đang xem: %s', 'laca'), $pageUrl) : '')
            . ($transcript !== '' ? "\n\n" . __('Tóm tắt cuộc chat:', 'laca') . "\n" . $transcript : ''));

        $form   = self::targetForm();
        $data   = self::mapToForm($form, $name, $email, (string) $phone, $fullMsg);
        $sent   = false;
        $subId  = 0;

        try {
            if ($form) {
                $subId = ContactFormTable::insertSubmission((int) $form['id'], $data, $ip);
                $sent  = ContactFormEmailService::sendAll($form, $data, $ip, $lang);
                // Discord Hub dựng lead từ $fields (tên/nhãn/kiểu) → khai các khoá chung.
                $fields = [
                    ['name' => 'name', 'label' => __('Tên', 'laca'), 'type' => 'text'],
                    ['name' => 'phone_number', 'label' => __('Điện thoại', 'laca'), 'type' => 'phone'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'message', 'label' => __('Nội dung', 'laca'), 'type' => 'textarea'],
                    ['name' => 'source', 'label' => __('Nguồn', 'laca'), 'type' => 'text'],
                ];
                do_action('laca_contact_form_submitted', $form, $fields, $data, $subId, $sent);
            } else {
                // Chưa chọn form → vẫn không mất lead: gửi thẳng email admin.
                $body = sprintf("%s: %s\n%s: %s\n\n%s", __('Tên', 'laca'), $name, __('Liên hệ', 'laca'), $email ?: $phone, $fullMsg);
                $sent = wp_mail((string) get_option('admin_email'), sprintf(__('[Chatbot] Liên hệ mới từ %s', 'laca'), $name), $body);
            }
        } catch (\Throwable $e) {
            error_log('[ChatbotLead] ' . $e->getMessage());
            return new \WP_Error('failed', __('Chưa gửi được thông tin. Vui lòng thử lại hoặc gọi trực tiếp cho chúng tôi.', 'laca'), ['status' => 500]);
        }

        if (!$sent && !$subId) {
            return new \WP_Error('failed', __('Chưa gửi được thông tin. Vui lòng thử lại hoặc gọi trực tiếp cho chúng tôi.', 'laca'), ['status' => 500]);
        }

        return new \WP_REST_Response(['ok' => true, 'message' => self::thanks()], 200);
    }

    private static function thanks(): string
    {
        return __('Cảm ơn bạn! Chúng tôi đã nhận thông tin và sẽ liên hệ lại sớm.', 'laca');
    }

    /** Form đích do admin chọn; null nếu chưa chọn / không tồn tại. */
    public static function targetForm(): ?array
    {
        $id = absint(get_option('laca_chatbot_lead_form', 0));
        if (!$id || !class_exists(ContactFormTable::class)) {
            return null;
        }
        $form = ContactFormTable::getForm($id);
        return $form ?: null;
    }

    /**
     * Ghép dữ liệu chat vào field của form theo TÊN + KIỂU (field các form
     * không đồng nhất: vd "phone_number" khai kiểu email). Luôn kèm khoá chung
     * name/phone_number/email/message để mẫu email kiểu [$name - $phone_number] đúng.
     */
    private static function mapToForm(?array $form, string $name, string $email, string $phone, string $message): array
    {
        $data = ['name' => $name, 'phone_number' => $phone, 'email' => $email, 'message' => $message, 'source' => 'Chatbot'];
        if (!$form) {
            return $data;
        }
        $fields = [];
        $walk = static function ($items) use (&$walk, &$fields): void {
            foreach ((array) $items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                if (!empty($it['name']) && !empty($it['type']) && !in_array($it['type'], ['content', 'step_break'], true)) {
                    $fields[] = $it;
                }
                foreach (['cols', 'fields', 'children', 'columns', 'rows'] as $k) {
                    if (!empty($it[$k])) {
                        $walk($it[$k]);
                    }
                }
            }
        };
        $walk(json_decode((string) ($form['fields'] ?? '[]'), true));

        $set = static function (array &$data, ?string $key, string $value): void {
            if ($key !== null && $value !== '' && empty($data[$key])) {
                $data[$key] = $value;
            }
        };
        $find = static function (callable $test) use ($fields): ?string {
            foreach ($fields as $f) {
                if ($test(strtolower((string) $f['name']), strtolower((string) $f['type']))) {
                    return (string) $f['name'];
                }
            }
            return null;
        };

        $set($data, $find(fn($n, $t) => (bool) preg_match('/(full_?name|contact_(name|person)|^name$|ho_ten|^ten)/', $n)), $name);
        $set($data, $find(fn($n, $t) => $t === 'email' && !str_contains($n, 'phone') || str_contains($n, 'email')), $email);
        $set($data, $find(fn($n, $t) => $t === 'phone' || $t === 'tel' || str_contains($n, 'phone')), $phone);
        $set($data, $find(fn($n, $t) => $t === 'textarea'), $message);

        return $data;
    }

    private static function transcript($history): string
    {
        if (!is_array($history)) {
            return '';
        }
        $lines = [];
        foreach (array_slice($history, -8) as $h) {
            if (!is_array($h)) {
                continue;
            }
            $who     = ($h['role'] ?? '') === 'assistant' ? 'Bot' : __('Khách', 'laca');
            $content = mb_substr(trim(sanitize_textarea_field((string) ($h['content'] ?? ''))), 0, 300);
            if ($content !== '') {
                $lines[] = "{$who}: {$content}";
            }
        }
        return implode("\n", $lines);
    }

    private static function rateLimited(string $ip): bool
    {
        $key   = 'laca_cbot_lead_' . substr(hash('sha256', $ip !== '' ? $ip : 'unknown'), 0, 32);
        $now   = time();
        $state = get_transient($key);
        if (!is_array($state) || (int) ($state['expires'] ?? 0) <= $now) {
            $state = ['count' => 0, 'expires' => $now + self::RATE_LIMIT_TTL];
        }
        if ((int) $state['count'] >= self::RATE_LIMIT_MAX) {
            return true;
        }
        $state['count']++;
        set_transient($key, $state, max(1, (int) $state['expires'] - $now));
        return false;
    }
}
