<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\I18n;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Settings;

/**
 * Sửa nội dung ĐANG CHẠY (đã xuất bản / riêng tư / đã lên lịch): tiêu đề, slug, nội dung,
 * ảnh đại diện, giá/kho WooCommerce, danh mục/thẻ… + xoá vĩnh viễn bài và media.
 * (Đổi trạng thái — tạo mới, xuất bản, vào thùng rác — đã do PostMonitor báo.)
 *
 * Gutenberg lưu nhiều lần liên tục → gom: bản chụp "trước khi sửa" giữ trong transient
 * 5 phút theo (bài, người sửa); tin gửi trễ 5 phút với dedupe_key, mỗi lần lưu thay payload
 * bằng diff MỚI NHẤT so với bản chụp gốc → 1 tin tổng hợp thay vì chục tin.
 */
final class ContentMonitor
{
    private const DEBOUNCE = 5 * MINUTE_IN_SECONDS;
    private const LIVE_STATUSES = ['publish', 'private', 'future'];

    /** meta_key => [nhãn VI, nhãn EN] */
    private const WATCHED_META = [
        '_thumbnail_id' => ['Ảnh đại diện', 'Featured image'],
        '_wp_page_template' => ['Template', 'Template'],
        '_regular_price' => ['Giá', 'Price'],
        '_sale_price' => ['Giá khuyến mãi', 'Sale price'],
        '_sku' => ['SKU', 'SKU'],
        '_stock' => ['Tồn kho', 'Stock'],
        '_stock_status' => ['Trạng thái kho', 'Stock status'],
    ];

    /** @var array<int,array> bản chụp post trước khi sửa trong request này */
    private array $before = [];
    /** @var array<int,array<string,mixed>> giá trị meta cũ (chỉ key bị đổi) */
    private array $beforeMeta = [];
    /** @var array<int,array<string,int[]>> term_taxonomy_id cũ theo taxonomy */
    private array $beforeTerms = [];
    /** @var array<int,bool> */
    private array $skip = [];

    public function register(): void
    {
        add_action('pre_post_update', [$this, 'capturePost'], 10, 1);
        add_action('update_post_meta', [$this, 'captureMeta'], 10, 3);
        add_action('add_post_meta', [$this, 'captureAddedMeta'], 10, 2);
        add_action('delete_post_meta', [$this, 'captureMeta'], 10, 3);
        add_action('set_object_terms', [$this, 'captureTerms'], 10, 6);
        add_action('wp_insert_post', [$this, 'markCreated'], 10, 3);
        add_action('transition_post_status', [$this, 'markStatusChange'], 10, 3);
        add_action('before_delete_post', [$this, 'onDeletePost'], 10, 2);
        add_action('delete_attachment', [$this, 'onDeleteAttachment'], 10, 1);
        add_action('shutdown', [$this, 'report'], 4);
    }

    // ── Chụp trạng thái trước ─────────────────────────────────────────────────

    /**
     * @param int $postId
     */
    public function capturePost($postId): void
    {
        $post = get_post((int) $postId);
        if ($post && $this->isWatched($post) && !isset($this->before[$post->ID])) {
            $this->before[$post->ID] = $this->snapshotPost($post);
        }
    }

    /**
     * Hook `update_post_meta` / `delete_post_meta` (trước khi ghi): ($meta_id(s), $object_id, $meta_key).
     *
     * @param mixed  $metaId
     * @param int    $postId
     * @param string $key
     */
    public function captureMeta($metaId, $postId, $key): void
    {
        $this->rememberMeta((int) $postId, (string) $key);
    }

    /**
     * @param int    $postId
     * @param string $key
     */
    public function captureAddedMeta($postId, $key): void
    {
        $this->rememberMeta((int) $postId, (string) $key);
    }

    private function rememberMeta(int $postId, string $key): void
    {
        if (!isset(self::WATCHED_META[$key]) || (isset($this->beforeMeta[$postId]) && array_key_exists($key, $this->beforeMeta[$postId]))) {
            return;
        }
        $post = get_post($postId);
        if ($post && $this->isWatched($post)) {
            $this->beforeMeta[$postId][$key] = (string) get_post_meta($postId, $key, true);
        }
    }

    /**
     * @param int    $postId
     * @param array  $terms
     * @param array  $ttIds
     * @param string $taxonomy
     * @param bool   $append
     * @param array  $oldTtIds
     */
    public function captureTerms($postId, $terms, $ttIds, $taxonomy, $append, $oldTtIds): void
    {
        $postId = (int) $postId;
        $post = get_post($postId);
        if (!$post || !$this->isWatched($post) || isset($this->beforeTerms[$postId][$taxonomy])) {
            return;
        }
        $tax = get_taxonomy((string) $taxonomy);
        if ($tax && $tax->public) {
            $this->beforeTerms[$postId][$taxonomy] = array_map('intval', (array) $oldTtIds);
        }
    }

    /**
     * @param int      $postId
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function markCreated($postId, $post, $update): void
    {
        if (!$update) {
            $this->skip[(int) $postId] = true; // bài mới: PostMonitor đã báo "tạo mới"
        }
    }

    /**
     * @param string   $new
     * @param string   $old
     * @param \WP_Post $post
     */
    public function markStatusChange($new, $old, $post): void
    {
        if ($new !== $old && $post instanceof \WP_Post) {
            $this->skip[$post->ID] = true; // đổi trạng thái: PostMonitor đã báo
        }
    }

    // ── Báo cáo ───────────────────────────────────────────────────────────────

    public function report(): void
    {
        $ids = array_unique(array_merge(array_keys($this->before), array_keys($this->beforeMeta), array_keys($this->beforeTerms)));
        foreach ($ids as $postId) {
            try {
                $this->reportPost((int) $postId);
            } catch (\Throwable $e) {
                error_log('[laca-discord-hub] ContentMonitor: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            }
        }
        // Tiến trình chạy lâu (WP-CLI, cron) có thể gọi lại → không báo trùng.
        $this->before = $this->beforeMeta = $this->beforeTerms = $this->skip = [];
    }

    private function reportPost(int $postId): void
    {
        if (isset($this->skip[$postId])) {
            return;
        }
        clean_post_cache($postId);
        $post = get_post($postId);
        if (!$post || !in_array($post->post_status, self::LIVE_STATUSES, true)) {
            return;
        }

        $current = $this->fullSnapshot($post);
        $userId = get_current_user_id();
        $key = 'laca_dh_edit_' . $postId . '_' . $userId;

        // Bản gốc = trước lần lưu ĐẦU TIÊN trong cửa sổ 5 phút.
        $original = get_transient($key);
        if (!is_array($original)) {
            $original = $current;
            $original['post'] = $this->before[$postId] ?? $current['post'];
            foreach ($this->beforeMeta[$postId] ?? [] as $metaKey => $value) {
                $original['meta'][$metaKey] = $value;
            }
            foreach ($this->beforeTerms[$postId] ?? [] as $taxonomy => $ttIds) {
                $original['terms'][$taxonomy] = $this->termNamesByTtIds($ttIds);
            }
            set_transient($key, $original, self::DEBOUNCE + MINUTE_IN_SECONDS);
        }

        $changes = $this->diff($original, $current);
        if (!$changes) {
            return;
        }

        $type = get_post_type_object($post->post_type);
        $typeLabel = $type ? $type->labels->singular_name : $post->post_type;
        $title = '' !== trim($post->post_title) ? $post->post_title : I18n::t('(không tiêu đề)', '(no title)');

        $links = [Embed::link(I18n::t('Xem', 'View'), (string) get_permalink($post))];
        $edit = get_edit_post_link($postId, 'raw');
        if ($edit) {
            $links[] = Embed::link(I18n::t('Sửa', 'Edit'), $edit);
        }
        $revisions = wp_get_post_revisions($postId, ['numberposts' => 1, 'fields' => 'ids']);
        if ($revisions) {
            $links[] = Embed::link(I18n::t('So sánh bản sửa', 'Compare revisions'), admin_url('revision.php?revision=' . (int) reset($revisions)));
        }

        $embed = Embed::make(sprintf('✏️ %s: %s', I18n::t('Sửa', 'Edited'), $typeLabel) . ' — ' . $title, Embed::COLOR_INFO)
            ->url((string) get_permalink($post))
            ->description(implode("\n", $changes), true)
            ->field(I18n::t('Người sửa', 'Edited by'), Context::actor())
            ->field(I18n::t('Nguồn', 'Origin'), Context::origin())
            ->field('ID', (string) $postId)
            ->field(I18n::t('Liên kết', 'Links'), implode(' · ', $links), false, true);

        Notifier::send(Settings::CHANNEL_AUDIT, 'content.edit', $embed, 'edit:' . $postId . ':' . $userId, self::DEBOUNCE, true);
    }

    /**
     * @param int      $postId
     * @param \WP_Post $post
     */
    public function onDeletePost($postId, $post = null): void
    {
        $post = $post instanceof \WP_Post ? $post : get_post((int) $postId);
        if (!$post || !$this->isWatched($post)) {
            return;
        }
        $type = get_post_type_object($post->post_type);
        $embed = Embed::make(
            sprintf('🗑️ %s: %s', I18n::t('Xoá vĩnh viễn', 'Permanently deleted'), $type ? $type->labels->singular_name : $post->post_type),
            Embed::COLOR_WARNING
        )
            ->description('**' . Embed::escape($post->post_title ?: '#' . $post->ID) . '**', true)
            ->field(I18n::t('Thực hiện bởi', 'Performed by'), Context::actor())
            ->field(I18n::t('Nguồn', 'Origin'), Context::origin())
            ->field('ID', (string) $post->ID);
        Notifier::send(Settings::CHANNEL_AUDIT, 'content.delete', $embed);
    }

    /**
     * @param int $attachmentId
     */
    public function onDeleteAttachment($attachmentId): void
    {
        $file = (string) get_post_meta((int) $attachmentId, '_wp_attached_file', true);
        $embed = Embed::make(I18n::t('🗑️ Xoá file media', '🗑️ Media file deleted'), Embed::COLOR_WARNING)
            ->codeField('File', '' !== $file ? $file : '#' . (int) $attachmentId, false)
            ->field(I18n::t('Thực hiện bởi', 'Performed by'), Context::actor())
            ->field(I18n::t('Nguồn', 'Origin'), Context::origin());
        Notifier::send(Settings::CHANNEL_AUDIT, 'media.delete', $embed);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isWatched(\WP_Post $post): bool
    {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post) || 'auto-draft' === $post->post_status) {
            return false;
        }
        $types = (array) apply_filters('laca_discord_hub/audit_post_types', Settings::postTypes());
        return in_array($post->post_type, $types, true);
    }

    private function snapshotPost(\WP_Post $post): array
    {
        return [
            'title' => (string) $post->post_title,
            'slug' => (string) $post->post_name,
            'content' => (string) $post->post_content,
            'excerpt' => (string) $post->post_excerpt,
            'author' => (int) $post->post_author,
            'date' => (string) $post->post_date,
            'parent' => (int) $post->post_parent,
            'menu_order' => (int) $post->menu_order,
        ];
    }

    private function fullSnapshot(\WP_Post $post): array
    {
        $meta = [];
        foreach (array_keys(self::WATCHED_META) as $key) {
            $meta[$key] = (string) get_post_meta($post->ID, $key, true);
        }
        $terms = [];
        foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy => $tax) {
            if ($tax->public) {
                $names = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'names']);
                $terms[$taxonomy] = is_array($names) ? $names : [];
            }
        }
        return ['post' => $this->snapshotPost($post), 'meta' => $meta, 'terms' => $terms];
    }

    /**
     * @param int[] $ttIds
     * @return string[]
     */
    private function termNamesByTtIds(array $ttIds): array
    {
        $names = [];
        foreach ($ttIds as $ttId) {
            $term = get_term_by('term_taxonomy_id', $ttId);
            if ($term) {
                $names[] = $term->name;
            }
        }
        return $names;
    }

    /**
     * @return string[] mỗi dòng 1 thay đổi (đã escape)
     */
    private function diff(array $old, array $new): array
    {
        $lines = [];
        $o = $old['post'];
        $n = $new['post'];

        if ($o['title'] !== $n['title']) {
            $lines[] = $this->line(I18n::t('Tiêu đề', 'Title'), $o['title'], $n['title']);
        }
        if ($o['slug'] !== $n['slug']) {
            $lines[] = $this->line('Slug', $o['slug'], $n['slug']);
        }
        if ($o['content'] !== $n['content']) {
            $before = mb_strlen(wp_strip_all_tags($o['content']));
            $after = mb_strlen(wp_strip_all_tags($n['content']));
            $lines[] = sprintf(
                '• **%s**: %s',
                I18n::t('Nội dung', 'Content'),
                sprintf(I18n::t('đã sửa (%1$s → %2$s ký tự)', 'edited (%1$s → %2$s chars)'), number_format_i18n($before), number_format_i18n($after))
            );
        }
        if ($o['excerpt'] !== $n['excerpt']) {
            $lines[] = sprintf('• **%s**: %s', I18n::t('Tóm tắt', 'Excerpt'), I18n::t('đã sửa', 'edited'));
        }
        if ($o['author'] !== $n['author']) {
            $lines[] = $this->line(I18n::t('Tác giả', 'Author'), $this->userName($o['author']), $this->userName($n['author']));
        }
        if ($o['date'] !== $n['date']) {
            $lines[] = $this->line(I18n::t('Ngày đăng', 'Publish date'), $o['date'], $n['date']);
        }
        if ($o['parent'] !== $n['parent']) {
            $lines[] = $this->line(I18n::t('Trang cha', 'Parent'), $o['parent'] ? get_the_title($o['parent']) : '—', $n['parent'] ? get_the_title($n['parent']) : '—');
        }
        if ($o['menu_order'] !== $n['menu_order']) {
            $lines[] = $this->line(I18n::t('Thứ tự', 'Order'), (string) $o['menu_order'], (string) $n['menu_order']);
        }

        foreach (self::WATCHED_META as $key => [$vi, $en]) {
            $a = (string) ($old['meta'][$key] ?? '');
            $b = (string) ($new['meta'][$key] ?? '');
            if ($a === $b) {
                continue;
            }
            if ('_thumbnail_id' === $key) {
                $lines[] = sprintf('• **%s**: %s', I18n::t($vi, $en), I18n::t('đã đổi', 'changed'));
                continue;
            }
            $lines[] = $this->line(I18n::t($vi, $en), $a, $b);
        }

        foreach ($new['terms'] as $taxonomy => $names) {
            $was = (array) ($old['terms'][$taxonomy] ?? []);
            $added = array_diff($names, $was);
            $removed = array_diff($was, $names);
            if (!$added && !$removed) {
                continue;
            }
            $tax = get_taxonomy($taxonomy);
            $parts = [];
            foreach ($added as $name) {
                $parts[] = '+' . Embed::escape($name);
            }
            foreach ($removed as $name) {
                $parts[] = '−' . Embed::escape($name);
            }
            $lines[] = sprintf('• **%s**: %s', Embed::escape($tax ? $tax->labels->name : $taxonomy), implode(', ', $parts));
        }

        return array_slice($lines, 0, 20);
    }

    private function line(string $label, string $old, string $new): string
    {
        $fmt = static fn ($v) => '' === trim($v) ? '—' : Embed::escape(Embed::truncate($v, 80));
        return sprintf('• **%s**: %s → %s', Embed::escape($label), $fmt($old), $fmt($new));
    }

    private function userName(int $id): string
    {
        $user = get_userdata($id);
        return $user ? $user->display_name : '#' . $id;
    }
}
