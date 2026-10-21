<?php

namespace App\Features\LacaSeo\Admin\ListScreens;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Custom bulk actions handler for Post Types.
 * Adds: Đặt noindex, Đặt index, Đặt nofollow, Đặt follow, Xoá canonical.
 */
class BulkActions
{
    public function register(): void
    {
        $postTypes = Helper::getAccessiblePostTypes();

        foreach ($postTypes as $slug => $obj) {
            $enabled = (string) Settings::get("titles.pt_{$slug}_add_meta_box", 'on');
            if ($enabled === 'on') {
                add_filter("bulk_actions-edit-{$slug}", [$this, 'addBulkActions']);
                add_filter("handle_bulk_actions-edit-{$slug}", [$this, 'handleBulkActions'], 10, 3);
            }
        }

        add_action('admin_notices', [$this, 'renderNotices']);
    }

    /**
     * Add bulk actions to dropdown.
     *
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function addBulkActions(array $actions): array
    {
        $actions['laca_seo_noindex']      = __('SEO: Đặt Noindex', 'lacadev');
        $actions['laca_seo_index']        = __('SEO: Đặt Index', 'lacadev');
        $actions['laca_seo_nofollow']     = __('SEO: Đặt Nofollow', 'lacadev');
        $actions['laca_seo_follow']       = __('SEO: Đặt Follow', 'lacadev');
        $actions['laca_seo_rem_canonical']= __('SEO: Xoá Canonical riêng', 'lacadev');

        return $actions;
    }

    /**
     * Handle execution of bulk actions.
     *
     * @param string $redirectTo
     * @param string $doAction
     * @param int[] $postIds
     * @return string
     */
    public function handleBulkActions(string $redirectTo, string $doAction, array $postIds): string
    {
        if (!str_starts_with($doAction, 'laca_seo_')) {
            return $redirectTo;
        }

        $count = 0;
        foreach ($postIds as $postId) {
            if (!current_user_can('edit_post', $postId)) {
                continue;
            }

            $robots = (array) get_post_meta($postId, '_laca_seo_robots', true);

            switch ($doAction) {
                case 'laca_seo_noindex':
                    if (!in_array('noindex', $robots, true)) {
                        $robots[] = 'noindex';
                    }
                    $robots = array_diff($robots, ['index']);
                    update_post_meta($postId, '_laca_seo_robots', array_values($robots));
                    $count++;
                    break;

                case 'laca_seo_index':
                    if (!in_array('index', $robots, true)) {
                        $robots[] = 'index';
                    }
                    $robots = array_diff($robots, ['noindex']);
                    update_post_meta($postId, '_laca_seo_robots', array_values($robots));
                    $count++;
                    break;

                case 'laca_seo_nofollow':
                    if (!in_array('nofollow', $robots, true)) {
                        $robots[] = 'nofollow';
                    }
                    update_post_meta($postId, '_laca_seo_robots', array_values($robots));
                    $count++;
                    break;

                case 'laca_seo_follow':
                    $robots = array_diff($robots, ['nofollow']);
                    update_post_meta($postId, '_laca_seo_robots', array_values($robots));
                    $count++;
                    break;

                case 'laca_seo_rem_canonical':
                    delete_post_meta($postId, '_laca_seo_canonical_url');
                    $count++;
                    break;
            }
        }

        return add_query_arg([
            'laca_seo_bulk_action' => $doAction,
            'laca_seo_bulk_count'  => $count,
        ], $redirectTo);
    }

    /**
     * Render success notice after bulk action completes.
     */
    public function renderNotices(): void
    {
        if (empty($_GET['laca_seo_bulk_action']) || !isset($_GET['laca_seo_bulk_count'])) {
            return;
        }

        $count = (int) $_GET['laca_seo_bulk_count'];
        $action = sanitize_key($_GET['laca_seo_bulk_action']);

        $label = match ($action) {
            'laca_seo_noindex'       => __('đặt Noindex', 'lacadev'),
            'laca_seo_index'         => __('đặt Index', 'lacadev'),
            'laca_seo_nofollow'      => __('đặt Nofollow', 'lacadev'),
            'laca_seo_follow'        => __('gỡ Nofollow', 'lacadev'),
            'laca_seo_rem_canonical' => __('xoá Canonical riêng', 'lacadev'),
            default                  => __('cập nhật', 'lacadev'),
        };

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(__('Laca SEO: Đã %s thành công cho %d bài viết.', 'lacadev'), $label, $count))
        );
    }
}
