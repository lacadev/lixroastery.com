<?php

namespace App\Features\LacaSeo\Modules\InstantIndexing;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Sitemap\Sitemap;

defined('ABSPATH') || exit;

/**
 * Instant Indexing module controller.
 */
class InstantIndexing
{
    /** @var int[] */
    private array $submitted = [];
    /** @var array<int, string> */
    private array $previousPostStatus = [];

    public function register(): void
    {
        // 1. Serve key file /{key}.txt
        add_action('init', [$this, 'serveApiKey'], 0);

        // 2. Post hooks & bulk actions
        $post_types = (array) Settings::get('instant_indexing.bing_post_types', ['post', 'page']);
        if (!empty($post_types)) {
            add_filter('wp_insert_post_data', [$this, 'beforeSavePost'], 10, 4);
        }

        foreach ($post_types as $pt) {
            add_action('save_post_' . $pt, [$this, 'onSavePost'], 10, 3);
            add_filter("bulk_actions-edit-{$pt}", [$this, 'registerBulkAction'], 11);
            add_filter("handle_bulk_actions-edit-{$pt}", [$this, 'handleBulkAction'], 10, 3);
        }

        // 3. Admin-post handler for manual URL submission
        add_action('admin_post_laca_seo_manual_indexnow', [$this, 'handleManualSubmission']);
    }

    public function serveApiKey(): void
    {
        $key = Api::get()->getKey();
        if (empty($key)) {
            return;
        }

        $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);

        if ($path === '/' . $key . '.txt') {
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Robots-Tag: noindex');
            status_header(200);
            echo esc_html($key);
            exit;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public function beforeSavePost(array $data, array $postarr, array $unsanitized, bool $update = false): array
    {
        if ($update && isset($postarr['ID'])) {
            $id = (int) $postarr['ID'];
            $this->previousPostStatus[$id] = (string) get_post_status($id);
        }
        return $data;
    }

    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        // Only run auto-submit if Rank Math is inactive (avoid double submissions)
        if (Helper::isRankMathActive() && !Helper::isForceFrontend()) {
            return;
        }

        if (in_array($postId, $this->submitted, true)) {
            return;
        }

        if (!in_array($post->post_status, ['publish', 'trash'], true)) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (!Sitemap::isObjectIndexable($postId, 'post')) {
            return;
        }

        $url = get_permalink($post);
        if (!$url) {
            return;
        }

        Api::get()->submit($url, false);
        $this->submitted[] = $postId;
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function registerBulkAction(array $actions): array
    {
        $actions['laca_seo_indexnow'] = 'Instant Indexing: Gửi tới IndexNow';
        return $actions;
    }

    /**
     * @param string $redirect
     * @param string $doaction
     * @param int[] $object_ids
     */
    public function handleBulkAction(string $redirect, string $doaction, array $object_ids): string
    {
        if ($doaction !== 'laca_seo_indexnow' || empty($object_ids)) {
            return $redirect;
        }

        if (!current_user_can('edit_posts')) {
            return $redirect;
        }

        $urls = [];
        foreach ($object_ids as $id) {
            $link = get_permalink($id);
            if ($link) {
                $urls[] = $link;
            }
        }

        if (!empty($urls)) {
            Api::get()->submit($urls, true);
        }

        return $redirect;
    }

    public function handleManualSubmission(): void
    {
        check_admin_referer('laca_seo_manual_indexnow', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $raw_urls = sanitize_textarea_field(wp_unslash($_POST['urls'] ?? ''));
        $urls = array_filter(array_map('trim', explode("\n", $raw_urls)));

        if (!empty($urls)) {
            Api::get()->submit($urls, true);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'laca-seo',
            'view' => 'instant-indexing',
            'tab'  => 'history',
        ], admin_url('admin.php')));
        exit;
    }
}
