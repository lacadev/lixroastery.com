<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Settings;
use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\Notifier;

/**
 * Bình luận mới + phát hiện spam tăng đột biến.
 *
 * Nút [Xoá]/[Spam] tương tác KHÔNG làm được bằng webhook thường (webhook không thuộc
 * application không gửi được interactive components) → Phase 1 gửi link quản trị,
 * Phase 3 Bot sẽ đăng message có button.
 */
final class CommentMonitor
{
    const SURGE_KEY = 'laca_dh_comment_surge';

    public function register(): void
    {
        add_action('comment_post', [$this, 'onCommentPost'], 10, 3);
    }

    /**
     * @param int        $commentId
     * @param int|string $approved 1, 0, 'spam', 'trash'
     * @param array      $data
     */
    public function onCommentPost($commentId, $approved, $data = []): void
    {
        if (1 !== (int) $approved) {
            $this->trackSurge();
        }
        // Spam đã bị Akismet/WP chặn: chỉ đếm, không thông báo từng cái.
        if ('spam' === $approved || 'trash' === $approved) {
            return;
        }

        $comment = get_comment((int) $commentId);
        if (!$comment) {
            return;
        }

        $types = (array) apply_filters('laca_discord_hub/comment_types', ['comment', 'review', '']);
        if (!in_array((string) $comment->comment_type, $types, true)) {
            return;
        }

        $isPending = 1 !== (int) $approved;
        $post      = get_post((int) $comment->comment_post_ID);
        $postTitle = $post ? get_the_title($post) : '#' . $comment->comment_post_ID;

        $embed = Embed::make(
            $isPending ? '💬 Bình luận mới — chờ duyệt' : '💬 Bình luận mới',
            $isPending ? Embed::COLOR_WARNING : Embed::COLOR_INFO
        )
            ->description(wp_trim_words((string) $comment->comment_content, 80, '…'))
            ->url((string) get_comment_link($comment))
            ->field('Tác giả', (string) $comment->comment_author)
            ->codeField('Email', (string) $comment->comment_author_email)
            ->codeField('IP', (string) $comment->comment_author_IP)
            ->field('Bài viết', $post ? Embed::link($postTitle, (string) get_permalink($post)) : Embed::escape($postTitle), false, true)
            ->field(
                'Quản trị',
                Embed::link('Duyệt / Sửa', admin_url('comment.php?action=editcomment&c=' . (int) $comment->comment_ID))
                    . ' · ' . Embed::link('Hàng chờ duyệt', admin_url('edit-comments.php?comment_status=moderated')),
                false,
                true
            );

        Notifier::send(Settings::CHANNEL_AUDIT, 'comment.new', $embed);
    }

    /**
     * Đếm bình luận chờ duyệt/spam trong cửa sổ cố định; vượt ngưỡng → 1 cảnh báo/cửa sổ.
     * (Read-modify-write transient có thể đếm hụt khi đồng thời cao — chấp nhận được cho mục đích cảnh báo.)
     */
    private function trackSurge(): void
    {
        $window    = Settings::threshold('comment_surge_window', 10 * MINUTE_IN_SECONDS);
        $threshold = Settings::threshold('comment_surge_count', 20);
        $now       = time();

        $state = get_transient(self::SURGE_KEY);
        if (!is_array($state) || $now - (int) $state['start'] >= $window) {
            $state = ['start' => $now, 'count' => 0];
        }
        $state['count']++;
        set_transient(self::SURGE_KEY, $state, max(1, $window - ($now - (int) $state['start'])));

        if ($threshold + 1 !== $state['count']) {
            return;
        }

        $embed = Embed::make('🚨 Bình luận spam tăng đột biến', Embed::COLOR_DANGER)
            ->description(sprintf('Hơn **%d** bình luận chờ duyệt/spam trong %d phút.', $threshold, (int) ($window / 60)), true)
            ->codeField('IP gần nhất', Context::clientIp())
            ->field('Quản trị', Embed::link('Hàng chờ duyệt', admin_url('edit-comments.php?comment_status=moderated')), true, true);

        Notifier::send(Settings::CHANNEL_SECURITY, 'comment.surge', $embed, 'comment-surge:' . $state['start']);
    }
}
