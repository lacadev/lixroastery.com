<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Settings;
use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\Notifier;

/**
 * Audit vòng đời bài viết qua transition_post_status.
 */
final class PostMonitor
{
    /** Trạng thái nội bộ, không phải hành động biên tập. */
    const IGNORED_STATUSES = ['auto-draft', 'inherit'];

    public function register(): void
    {
        add_action('transition_post_status', [$this, 'onTransition'], 10, 3);
    }

    /**
     * @param string   $new
     * @param string   $old
     * @param \WP_Post $post
     */
    public function onTransition($new, $old, $post): void
    {
        if ($new === $old || !$post instanceof \WP_Post) {
            return;
        }
        if (in_array($new, self::IGNORED_STATUSES, true) || wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }
        if (defined('WP_IMPORTING') && WP_IMPORTING) {
            return;
        }

        /**
         * Post type được audit — chọn trong Laca Admin → Discord Hub.
         */
        $types = (array) apply_filters('laca_discord_hub/audit_post_types', Settings::postTypes());
        if (!in_array($post->post_type, $types, true)) {
            return;
        }

        $embed = Embed::make(
            sprintf('📝 %s: %s → %s', $this->typeLabel($post->post_type), $this->statusLabel($old), $this->statusLabel($new)),
            $this->color($new)
        );

        $title = '' !== trim($post->post_title) ? $post->post_title : '(không tiêu đề)';
        $link  = 'publish' === $new ? get_permalink($post) : get_edit_post_link($post->ID, 'raw');

        $embed->description('**' . Embed::escape($title) . '**', true)
            ->url((string) $link)
            ->field('Tác giả', (string) get_the_author_meta('display_name', (int) $post->post_author))
            ->field('Thực hiện bởi', Context::actor())
            ->field('Nguồn', Context::origin())
            ->field('ID', (string) $post->ID);

        $edit = get_edit_post_link($post->ID, 'raw');
        if ($edit) {
            $embed->field('Quản trị', Embed::link('Mở trình sửa', $edit), true, true);
        }

        Notifier::send(Settings::CHANNEL_AUDIT, 'post.transition', $embed);
    }

    private function statusLabel(string $status): string
    {
        if ('new' === $status) {
            return 'Mới';
        }
        $object = get_post_status_object($status);
        return $object && !empty($object->label) ? (string) $object->label : $status;
    }

    private function typeLabel(string $type): string
    {
        $object = get_post_type_object($type);
        return $object ? (string) $object->labels->singular_name : $type;
    }

    private function color(string $status): int
    {
        switch ($status) {
            case 'publish':
                return Embed::COLOR_SUCCESS;
            case 'trash':
                return Embed::COLOR_DANGER;
            case 'pending':
            case 'future':
                return Embed::COLOR_WARNING;
            default:
                return Embed::COLOR_INFO;
        }
    }
}
