<?php

namespace App\Features\LacaSeo\Sitemap;

defined('ABSPATH') || exit;

/**
 * Watches post/term/user/setting changes and automatically invalidates sitemap cache.
 */
class CacheWatcher
{
    /** @var string[] */
    private static array $clearTypes = [];
    private static bool $clearAll = false;

    public function register(): void
    {
        add_action('save_post', [$this, 'onSavePost'], 10, 2);
        add_action('transition_post_status', [$this, 'onTransitionPostStatus'], 10, 3);

        add_action('edited_terms', [$this, 'onInvalidateTerm'], 10, 2);
        add_action('clean_term_cache', [$this, 'onInvalidateTerm'], 10, 2);
        add_action('clean_object_term_cache', [$this, 'onInvalidateTerm'], 10, 2);

        add_action('delete_user', [$this, 'onInvalidateAuthor']);
        add_action('user_register', [$this, 'onInvalidateAuthor']);
        add_action('profile_update', [$this, 'onInvalidateAuthor']);

        add_action('shutdown', [__CLASS__, 'clearQueued']);
    }

    public function onSavePost(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        self::invalidate($post->post_type);
        self::invalidate('author');
    }

    public function onTransitionPostStatus(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($new_status === 'publish' || $old_status === 'publish') {
            self::invalidate($post->post_type);
        }
    }

    public function onInvalidateTerm(mixed $unused, string $taxonomy): void
    {
        self::invalidate($taxonomy);
    }

    public function onInvalidateAuthor(int $userId): void
    {
        update_user_meta($userId, 'last_update', time());
        self::invalidate('author');
    }

    public static function invalidate(string $type): void
    {
        if (!in_array('1', self::$clearTypes, true)) {
            self::$clearTypes[] = '1';
            self::$clearTypes[] = 'index';
        }
        if (!in_array($type, self::$clearTypes, true)) {
            self::$clearTypes[] = $type;
        }
    }

    public static function clearAll(): void
    {
        self::$clearAll = true;
    }

    public static function clearQueued(): void
    {
        if (self::$clearAll) {
            Cache::invalidateStorage();
            self::$clearAll = false;
            self::$clearTypes = [];
            return;
        }

        foreach (self::$clearTypes as $type) {
            Cache::invalidateStorage($type);
        }

        self::$clearTypes = [];
    }
}
