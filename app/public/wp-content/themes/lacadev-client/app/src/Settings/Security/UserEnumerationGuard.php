<?php

namespace App\Settings\Security;

/**
 * Chặn dò username — bước đầu tiên của mọi cuộc brute-force (sự cố thật 16/10/2026: kẻ tấn
 * công thử đúng 3 username lộ qua /?author=1,2,3 → /author/{username}/).
 *
 *  - /?author=N (và ?author[]=N) với khách chưa đăng nhập → 404 thay vì chuyển hướng lộ slug.
 *  - oEmbed (/wp-json/oembed/...) bỏ author_name / author_url.
 * REST /wp/v2/users đã không trả user nào cho khách (WP chỉ liệt kê tác giả có bài công khai
 * và theme/plugin hiện tại đã lọc) — không đụng tới để tránh hỏng editor.
 */
final class UserEnumerationGuard
{
    public function register(): void
    {
        add_action('template_redirect', [$this, 'blockAuthorQuery'], 1);
        add_filter('oembed_response_data', [$this, 'stripOembedAuthor'], 10, 1);
    }

    public function blockAuthorQuery(): void
    {
        if (is_user_logged_in() || !isset($_GET['author'])) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        $template = get_404_template();
        if ($template) {
            include $template;
        }
        exit;
    }

    /**
     * @param array $data
     */
    public function stripOembedAuthor($data): array
    {
        $data = (array) $data;
        unset($data['author_name'], $data['author_url']);
        return $data;
    }
}
