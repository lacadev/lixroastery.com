<?php

namespace App\Features\LacaSeo\Modules\Redirections;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Watcher: automatically creates redirections when post or term slug changes.
 */
class Watcher
{
    private array $updatedPosts = [];
    private array $updatedTerms = [];

    public function register(): void
    {
        if (get_option('permalink_structure')) {
            if (Settings::get('general.redirections_post_redirect', 'off') === 'on') {
                add_action('pre_post_update', [$this, 'prePostUpdate'], 10, 1);
                add_action('post_updated', [$this, 'handlePostUpdate'], 10, 3);
                add_action('attachment_updated', [$this, 'handlePostUpdate'], 10, 3);
                add_action('edit_terms', [$this, 'preTermUpdate'], 10, 2);
                add_action('edited_term', [$this, 'handleTermUpdate'], 10, 3);
            }
        }

        add_action('deleted_post', [$this, 'invalidatePost'], 10, 1);
        add_action('pre_delete_term', [$this, 'invalidateTerm'], 10, 2);
        add_action('delete_user', [$this, 'invalidateAuthor'], 10, 1);
    }

    public function prePostUpdate(int $postId): void
    {
        $this->updatedPosts[$postId] = get_permalink($postId);
    }

    public function handlePostUpdate(int $postId, \WP_Post $post, \WP_Post $before): void
    {
        $accessible = array_keys(Helper::getAccessiblePostTypes());
        if (!in_array($post->post_type, $accessible, true)) {
            return;
        }

        $transition = "{$before->post_status}_to_{$post->post_status}";
        $beforePermalink = $this->updatedPosts[$postId] ?? false;
        $afterPermalink  = get_permalink($postId);

        if ($this->isWatchedTransition($transition, $post) && $this->hasPermalinkChanged($beforePermalink, $afterPermalink)) {
            $redirectionId = $this->createRedirection($beforePermalink, $afterPermalink, 301, $post->ID, 'post');

            do_action('laca_seo/redirection/post_updated', $redirectionId, $postId);
        }
    }

    public function preTermUpdate(int $termId, string $taxonomy): void
    {
        $this->updatedTerms[$termId] = get_term_link($termId, $taxonomy);
    }

    public function handleTermUpdate(int $termId, int $ttId, string $taxonomy): void
    {
        $accessible = array_keys(Helper::getAccessibleTaxonomies());
        if (!in_array($taxonomy, $accessible, true)) {
            return;
        }

        $beforePermalink = $this->updatedTerms[$termId] ?? false;
        $afterPermalink  = get_term_link($termId, $taxonomy);

        if ($beforePermalink && $beforePermalink !== $afterPermalink) {
            $redirectionId = $this->createRedirection($beforePermalink, $afterPermalink, 301, $termId, 'term');

            do_action('laca_seo/redirection/term_updated', $redirectionId, $termId);
        }
    }

    private function createRedirection(string $fromUrl, string $urlTo, int $headerCode, int $objectId, string $type): int
    {
        if (empty($fromUrl) || empty($urlTo)) {
            return 0;
        }

        $existing = $this->hasExistingRedirection($objectId, $type);
        if (false === $existing) {
            $redirection = Redirection::from([
                'url_to'      => $urlTo,
                'header_code' => $headerCode,
            ]);
        } else {
            $redirection = $existing;
        }

        $redirection->setNocache(true);
        $redirection->addSource($fromUrl, 'exact');
        $redirection->addDestination($urlTo);
        $redirectionId = $redirection->save();

        Cache::purgeByObjectId($objectId, $type);

        $path = wp_parse_url($fromUrl, PHP_URL_PATH);
        if ($path) {
            $path = Redirection::stripSubdirectory($path);
            Cache::add([
                'from_url'       => $path,
                'redirection_id' => $redirectionId,
                'object_id'      => $objectId,
                'object_type'    => $type,
                'is_redirected'  => 1,
            ]);
        }

        return $redirectionId ?: 0;
    }

    private function hasExistingRedirection(int $objectId, string $type): Redirection|false
    {
        $cache = Cache::getByObjectId($objectId, $type);
        if (!$cache) {
            return false;
        }

        return Redirection::create((int) $cache->redirection_id);
    }

    private function hasPermalinkChanged(mixed $before, mixed $after): bool
    {
        if (!$before || !$after) {
            return false;
        }

        $beforePath = wp_parse_url($before, PHP_URL_PATH);
        $afterPath  = wp_parse_url($after, PHP_URL_PATH);

        if ($beforePath === $afterPath) {
            return false;
        }

        $sitePath = wp_parse_url(home_url(), PHP_URL_PATH) ?: '/';
        if ($beforePath === $sitePath || $beforePath === '/') {
            return false;
        }

        return true;
    }

    private function isWatchedTransition(string $transition, \WP_Post $post): bool
    {
        return 'publish_to_publish' === $transition
            || ('inherit_to_inherit' === $transition && 'attachment' === $post->post_type);
    }

    public function invalidatePost(int $postId): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }
        Cache::purgeByObjectId($postId, 'post');
    }

    public function invalidateTerm(int $termId, string $taxonomy): void
    {
        Cache::purgeByObjectId($termId, 'term');
    }

    public function invalidateAuthor(int $userId): void
    {
        Cache::purgeByObjectId($userId, 'user');
    }
}
