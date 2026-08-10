<?php

namespace App\Settings\LacaTools\Management;

/**
 * MediaService
 * Handles Media Library widget data, orphan filtering and used-ID collection.
 * Extracted from ManagementExperience (lines 580–701).
 */
class MediaService
{
    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'filterDetachedMedia']);
    }

    /**
     * Filters the media library to hide "used" media from the Detached view.
     */
    public function filterDetachedMedia(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'attachment') {
            return;
        }

        if ($query->get('detached') || $query->get('post_parent') === 0) {
            $used_ids = $this->getCommonlyUsedMediaIds();
            if (!empty($used_ids)) {
                $query->set('post__not_in', $used_ids);
            }
        }
    }

    /**
     * Collects media IDs that are used globally but have no post_parent.
     */
    public function getCommonlyUsedMediaIds(): array
    {
        global $wpdb;
        $used_ids = [];

        $option_keys = ['logo', 'logo_dark', 'default_image', 'site_icon'];
        foreach ($option_keys as $key) {
            $val = carbon_get_theme_option($key);
            if (!empty($val) && is_numeric($val)) {
                $used_ids[] = (int) $val;
            }
        }

        $site_icon = get_option('site_icon');
        if ($site_icon) {
            $used_ids[] = (int) $site_icon;
        }

        $meta_keys    = ['_thumbnail_id', 'about_image', 'quick_view_img', '_site_icon'];
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $meta_ids     = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value > 0",
            $meta_keys
        ));

        if (!empty($meta_ids)) {
            $used_ids = array_merge($used_ids, array_map('intval', $meta_ids));
        }

        return array_unique(array_filter($used_ids));
    }

    /**
     * Returns media stats: total files, total size (cached 1h), orphan count.
     */
    public function getMediaStats(): array
    {
        global $wpdb;

        $counts      = wp_count_attachments();
        $total_files = array_sum((array) $counts);

        $total_size_raw = get_transient('lacadev_media_total_size');
        if (false === $total_size_raw) {
            $upload_dir     = wp_upload_dir();
            $base_dir       = $upload_dir['basedir'];
            $files          = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'");
            $total_size_raw = 0;
            foreach ($files as $file) {
                $path = $base_dir . '/' . $file;
                if (file_exists($path)) {
                    $total_size_raw += @filesize($path);
                }
            }
            set_transient('lacadev_media_total_size', $total_size_raw, HOUR_IN_SECONDS);
        }

        $used_ids    = $this->getCommonlyUsedMediaIds();
        $query_args  = [
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'post_parent'    => 0,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ];
        if (!empty($used_ids)) {
            $query_args['post__not_in'] = $used_ids;
        }
        $orphans      = new \WP_Query($query_args);
        $orphan_count = $orphans->found_posts;

        return [
            'total_files'    => $total_files,
            'total_size'     => size_format($total_size_raw),
            'orphan_count'   => $orphan_count,
        ];
    }
}
