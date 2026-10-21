<?php

namespace App\Features\LacaSeo\Admin\Metabox;

defined('ABSPATH') || exit;

/**
 * Lock modified date functionality for posts.
 * When _laca_seo_lock_modified_date is 'on', preserves post_modified and post_modified_gmt.
 * Fully compatible with Rank Math class-lock-modified-date.php
 */
class LockModifiedDate
{
    public function register(): void
    {
        add_filter('wp_insert_post_data', [$this, 'filterPostData'], 10, 2);
    }

    /**
     * Prevent post_modified and post_modified_gmt from changing if locked.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public function filterPostData(array $data, array $postarr): array
    {
        $postId = (int) ($postarr['ID'] ?? 0);
        if ($postId <= 0) {
            return $data;
        }

        // Check if lock flag is sent in POST with valid nonce, or fallback to existing post meta
        $locked = false;
        $hasValidNonce = isset($_POST[PostMetabox::NONCE_NAME]) && wp_verify_nonce($_POST[PostMetabox::NONCE_NAME], PostMetabox::NONCE_ACTION);

        if ($hasValidNonce) {
            $locked = (isset($_POST['laca_seo']['_laca_seo_lock_modified_date']) && wp_unslash($_POST['laca_seo']['_laca_seo_lock_modified_date']) === 'on')
                   || (isset($_POST['_laca_seo_lock_modified_date']) && wp_unslash($_POST['_laca_seo_lock_modified_date']) === 'on');
        } else {
            $existing = get_post_meta($postId, '_laca_seo_lock_modified_date', true);
            $locked = ($existing === 'on');
        }

        if ($locked) {
            $currentPost = get_post($postId);
            if ($currentPost) {
                $data['post_modified']     = $currentPost->post_modified;
                $data['post_modified_gmt'] = $currentPost->post_modified_gmt;
            }
        }

        return $data;
    }
}
