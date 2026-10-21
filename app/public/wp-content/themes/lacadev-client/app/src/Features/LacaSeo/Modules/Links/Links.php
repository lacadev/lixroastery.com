<?php

namespace App\Features\LacaSeo\Modules\Links;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;

defined('ABSPATH') || exit;

/**
 * Orchestrator for Link Counter module.
 * Hooks into save_post, manages columns in post list.
 */
class Links
{
    private ContentProcessor $processor;
    private Storage $storage;

    public function __construct()
    {
        $this->storage   = new Storage();
        $this->processor = new ContentProcessor($this->storage);
    }

    public function register(): void
    {
        Installer::createTables();

        if (!Modules::isActive('link-counter')) {
            return;
        }

        // Process links on post save
        add_action('save_post', [$this, 'onSavePost'], 10, 2);

        // Manage post list columns
        if (is_admin()) {
            add_filter('manage_posts_columns', [$this, 'addColumns']);
            add_action('manage_posts_custom_column', [$this, 'renderColumns'], 10, 2);
            add_filter('manage_pages_columns', [$this, 'addColumns']);
            add_action('manage_pages_custom_column', [$this, 'renderColumns'], 10, 2);
        }
    }

    public function onSavePost(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        if ($post->post_status !== 'publish' && $post->post_status !== 'future') {
            return;
        }

        $this->processor->process($postId, $post->post_content);
    }

    public function addColumns(array $columns): array
    {
        $columns['laca_seo_links'] = __('Liên kết', 'lacadev');
        return $columns;
    }

    public function renderColumns(string $column, int $postId): void
    {
        if ($column !== 'laca_seo_links') {
            return;
        }

        global $wpdb;
        $table = Storage::getMetaTable();
        $meta = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE object_id = %d", $postId), ARRAY_A);

        $internal = (int) ($meta['internal_link_count'] ?? 0);
        $external = (int) ($meta['external_link_count'] ?? 0);
        $incoming = (int) ($meta['incoming_link_count'] ?? 0);

        printf(
            '<span title="%s"><strong>%d</strong> int</span> | <span title="%s"><strong>%d</strong> ext</span> | <span title="%s"><strong>%d</strong> inc</span>',
            esc_attr__('Liên kết nội bộ trỏ đi', 'lacadev'),
            $internal,
            esc_attr__('Liên kết ngoài trỏ đi', 'lacadev'),
            $external,
            esc_attr__('Liên kết nội bộ trỏ tới bài này', 'lacadev'),
            $incoming
        );
    }
}
