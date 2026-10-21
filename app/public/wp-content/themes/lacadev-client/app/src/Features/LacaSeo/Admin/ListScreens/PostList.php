<?php

namespace App\Features\LacaSeo\Admin\ListScreens;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Custom columns, filters, quick edit, and bulk edit for Post List screens.
 */
class PostList
{
    public function register(): void
    {
        $postTypes = Helper::getAccessiblePostTypes();

        foreach ($postTypes as $slug => $obj) {
            $enabled = (string) Settings::get("titles.pt_{$slug}_add_meta_box", 'on');
            if ($enabled === 'on') {
                add_filter("manage_{$slug}_posts_columns", [$this, 'addColumns']);
                add_action("manage_{$slug}_posts_custom_column", [$this, 'renderColumn'], 10, 2);
            }
        }

        add_action('restrict_manage_posts', [$this, 'renderFilters']);
        add_filter('parse_query', [$this, 'handleFilters']);
        add_action('quick_edit_custom_box', [$this, 'renderQuickEdit'], 10, 2);
        add_action('bulk_edit_custom_box', [$this, 'renderBulkEdit'], 10, 2);
        add_action('save_post', [$this, 'handleBulkEditSave'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueListScripts']);
    }

    /**
     * Enqueue list inline edit scripts.
     */
    public function enqueueListScripts(string $hook): void
    {
        if ($hook !== 'edit.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || empty($screen->post_type)) {
            return;
        }

        $pt = $screen->post_type;
        $bulkMode = (string) Settings::get("titles.pt_{$pt}_bulk_editing", 'editing');
        if ($bulkMode === '0') {
            return;
        }

        // Inline script to populate quick edit fields from list data attributes
        add_action('admin_footer', function() use ($bulkMode) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                var $wp_inline_edit = inlineEditPost.edit;
                inlineEditPost.edit = function(id) {
                    $wp_inline_edit.apply(this, arguments);
                    var postId = 0;
                    if (typeof(id) === 'object') {
                        postId = parseInt(this.getId(id), 10);
                    }
                    if (postId > 0) {
                        var $row = $('#post-' + postId);
                        var $editRow = $('#edit-' + postId);
                        var title = $row.find('.laca-col-data-title').text();
                        var desc = $row.find('.laca-col-data-desc').text();
                        var kw = $row.find('.laca-col-data-kw').text();
                        var can = $row.find('.laca-col-data-canonical').text();

                        $editRow.find('input[name="laca_seo[_laca_seo_title]"]').val(title);
                        $editRow.find('textarea[name="laca_seo[_laca_seo_description]"]').val(desc);
                        $editRow.find('input[name="laca_seo[_laca_seo_focus_keyword]"]').val(kw);
                        $editRow.find('input[name="laca_seo[_laca_seo_canonical_url]"]').val(can);

                        <?php if ($bulkMode === 'readonly'): ?>
                        $editRow.find('.laca-seo-quick-edit-fields input, .laca-seo-quick-edit-fields textarea').prop('readonly', true);
                        <?php endif; ?>
                    }
                };
            });
            </script>
            <style>
            .column-laca_seo { width: 140px; }
            .column-laca_seo_title { width: 180px; }
            .column-laca_seo_desc { width: 220px; }
            .laca-list-score {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-weight: 700;
                font-size: 12px;
                padding: 2px 6px;
                border-radius: 4px;
            }
            .laca-list-score--good { background: #dcfce7; color: #15803d; }
            .laca-list-score--ok { background: #fef3c7; color: #b45309; }
            .laca-list-score--bad { background: #fee2e2; color: #b91c1c; }
            .laca-list-badge {
                display: inline-block;
                font-size: 10px;
                padding: 1px 4px;
                border-radius: 3px;
                background: #f1f5f9;
                color: #475569;
                font-weight: 600;
                margin-top: 3px;
                margin-right: 2px;
            }
            .laca-list-badge--pillar { background: #e0e7ff; color: #3730a3; }
            .laca-list-badge--noindex { background: #fee2e2; color: #991b1b; }
            .laca-list-kw { font-size: 12px; color: #475569; margin-top: 4px; word-break: break-word; }
            </style>
            <?php
        });
    }

    /**
     * Add SEO columns.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addColumns(array $columns): array
    {
        $new = [];
        foreach ($columns as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['laca_seo']       = __('SEO', 'lacadev');
                $new['laca_seo_title'] = __('Tiêu đề SEO', 'lacadev');
                $new['laca_seo_desc']  = __('Mô tả SEO', 'lacadev');
            }
        }
        if (!isset($new['laca_seo'])) {
            $new['laca_seo']       = __('SEO', 'lacadev');
            $new['laca_seo_title'] = __('Tiêu đề SEO', 'lacadev');
            $new['laca_seo_desc']  = __('Mô tả SEO', 'lacadev');
        }
        return $new;
    }

    /**
     * Render column content without N+1 query (post meta is primed by WP_Query).
     */
    public function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'laca_seo':
                $score = (int) get_post_meta($postId, '_laca_seo_seo_score', true);
                $kw = (string) get_post_meta($postId, '_laca_seo_focus_keyword', true);
                $isPillar = get_post_meta($postId, '_laca_seo_is_pillar_content', true) === 'on';
                $robots = (array) get_post_meta($postId, '_laca_seo_robots', true);
                $isNoindex = in_array('noindex', $robots, true);
                $canonical = (string) get_post_meta($postId, '_laca_seo_canonical_url', true);

                $ratingClass = match (true) {
                    $score >= 80 => 'good',
                    $score >= 50 => 'ok',
                    default      => 'bad',
                };

                echo '<div class="laca-list-seo-cell">';
                if ($score > 0) {
                    echo '<span class="laca-list-score laca-list-score--' . esc_attr($ratingClass) . '">' . esc_html($score) . '/100</span>';
                } else {
                    echo '<span class="laca-list-score laca-list-score--bad">N/A</span>';
                }

                if ($isPillar) {
                    echo ' <span class="laca-list-badge laca-list-badge--pillar">Pillar</span>';
                }
                if ($isNoindex) {
                    echo ' <span class="laca-list-badge laca-list-badge--noindex">Noindex</span>';
                }
                if ($canonical !== '') {
                    echo ' <span class="laca-list-badge" title="' . esc_attr($canonical) . '">Canonical</span>';
                }

                if ($kw !== '') {
                    $firstKw = explode(',', $kw)[0];
                    echo '<div class="laca-list-kw" title="' . esc_attr($kw) . '">' . esc_html($firstKw) . '</div>';
                }

                // Data holders for inline quick edit
                $rawTitle = (string) get_post_meta($postId, '_laca_seo_title', true);
                $rawDesc  = (string) get_post_meta($postId, '_laca_seo_description', true);
                echo '<span class="laca-col-data-title" style="display:none;">' . esc_html($rawTitle) . '</span>';
                echo '<span class="laca-col-data-desc" style="display:none;">' . esc_html($rawDesc) . '</span>';
                echo '<span class="laca-col-data-kw" style="display:none;">' . esc_html($kw) . '</span>';
                echo '<span class="laca-col-data-canonical" style="display:none;">' . esc_html($canonical) . '</span>';

                echo '</div>';
                break;

            case 'laca_seo_title':
                $customTitle = (string) get_post_meta($postId, '_laca_seo_title', true);
                if ($customTitle !== '') {
                    echo esc_html($customTitle);
                } else {
                    echo '<span style="color:#94a3b8;font-style:italic;">' . esc_html__('— Theo mẫu chung —', 'lacadev') . '</span>';
                }
                break;

            case 'laca_seo_desc':
                $customDesc = (string) get_post_meta($postId, '_laca_seo_description', true);
                if ($customDesc !== '') {
                    echo esc_html(wp_trim_words($customDesc, 15, '...'));
                } else {
                    echo '<span style="color:#94a3b8;font-style:italic;">' . esc_html__('— Theo mẫu chung —', 'lacadev') . '</span>';
                }
                break;
        }
    }

    /**
     * Filter dropdown in edit.php
     */
    public function renderFilters(string $postType): void
    {
        $enabled = (string) Settings::get("titles.pt_{$postType}_add_meta_box", 'on');
        if ($enabled !== 'on') {
            return;
        }

        $selected = sanitize_key($_GET['laca_seo_filter'] ?? '');
        ?>
        <select name="laca_seo_filter" id="filter-by-laca-seo">
            <option value=""><?php esc_html_e('Tất cả trạng thái SEO', 'lacadev'); ?></option>
            <option value="good" <?php selected($selected, 'good'); ?>><?php esc_html_e('SEO Điểm tốt (≥80)', 'lacadev'); ?></option>
            <option value="ok" <?php selected($selected, 'ok'); ?>><?php esc_html_e('SEO Điểm khá (50–79)', 'lacadev'); ?></option>
            <option value="bad" <?php selected($selected, 'bad'); ?>><?php esc_html_e('SEO Điểm kém (<50)', 'lacadev'); ?></option>
            <option value="no_kw" <?php selected($selected, 'no_kw'); ?>><?php esc_html_e('Chưa có từ khoá chính', 'lacadev'); ?></option>
            <option value="noindex" <?php selected($selected, 'noindex'); ?>><?php esc_html_e('Chỉ thị Noindex', 'lacadev'); ?></option>
        </select>
        <?php
    }

    /**
     * Handle filter query.
     */
    public function handleFilters(\WP_Query $query): void
    {
        global $pagenow;
        if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }

        $filter = sanitize_key($_GET['laca_seo_filter'] ?? '');
        if ($filter === '') {
            return;
        }

        $metaQuery = (array) $query->get('meta_query');

        switch ($filter) {
            case 'good':
                $metaQuery[] = [
                    'key'     => '_laca_seo_seo_score',
                    'value'   => 80,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
                break;

            case 'ok':
                $metaQuery[] = [
                    'key'     => '_laca_seo_seo_score',
                    'value'   => [50, 79],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ];
                break;

            case 'bad':
                $metaQuery[] = [
                    'relation' => 'OR',
                    [
                        'key'     => '_laca_seo_seo_score',
                        'value'   => 50,
                        'compare' => '<',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => '_laca_seo_seo_score',
                        'compare' => 'NOT EXISTS',
                    ],
                ];
                break;

            case 'no_kw':
                $metaQuery[] = [
                    'relation' => 'OR',
                    [
                        'key'     => '_laca_seo_focus_keyword',
                        'value'   => '',
                        'compare' => '=',
                    ],
                    [
                        'key'     => '_laca_seo_focus_keyword',
                        'compare' => 'NOT EXISTS',
                    ],
                ];
                break;

            case 'noindex':
                $metaQuery[] = [
                    'key'     => '_laca_seo_robots',
                    'value'   => 'noindex',
                    'compare' => 'LIKE',
                ];
                break;
        }

        $query->set('meta_query', $metaQuery);
    }

    /**
     * Render quick edit custom fields.
     */
    public function renderQuickEdit(string $columnName, string $postType): void
    {
        if ($columnName !== 'laca_seo') {
            return;
        }

        $bulkMode = (string) Settings::get("titles.pt_{$postType}_bulk_editing", 'editing');
        if ($bulkMode === '0') {
            return;
        }

        wp_nonce_field('laca_seo_quick_edit', '_laca_seo_quick_edit_nonce');
        ?>
        <fieldset class="inline-edit-col-right laca-seo-quick-edit-fields">
            <div class="inline-edit-col">
                <span class="title"><strong><?php esc_html_e('Cấu hình nhanh Laca SEO', 'lacadev'); ?></strong></span>
                <label>
                    <span class="title"><?php esc_html_e('Tiêu đề SEO', 'lacadev'); ?></span>
                    <span class="input-text-wrap"><input type="text" name="laca_seo[_laca_seo_title]" value=""></span>
                </label>
                <label>
                    <span class="title"><?php esc_html_e('Mô tả SEO', 'lacadev'); ?></span>
                    <span class="input-text-wrap"><textarea name="laca_seo[_laca_seo_description]" rows="2"></textarea></span>
                </label>
                <label>
                    <span class="title"><?php esc_html_e('Từ khoá chính', 'lacadev'); ?></span>
                    <span class="input-text-wrap"><input type="text" name="laca_seo[_laca_seo_focus_keyword]" value=""></span>
                </label>
                <label>
                    <span class="title"><?php esc_html_e('Canonical URL', 'lacadev'); ?></span>
                    <span class="input-text-wrap"><input type="url" name="laca_seo[_laca_seo_canonical_url]" value=""></span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Render bulk edit custom fields.
     */
    public function renderBulkEdit(string $columnName, string $postType): void
    {
        if ($columnName !== 'laca_seo') {
            return;
        }

        $bulkMode = (string) Settings::get("titles.pt_{$postType}_bulk_editing", 'editing');
        if ($bulkMode !== 'editing') {
            return;
        }
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <span class="title"><strong><?php esc_html_e('Sửa nhanh hàng loạt Laca SEO', 'lacadev'); ?></strong></span>
                <label>
                    <span class="title"><?php esc_html_e('Chỉ thị Robots', 'lacadev'); ?></span>
                    <select name="laca_seo_bulk_robots">
                        <option value=""><?php esc_html_e('— Không thay đổi —', 'lacadev'); ?></option>
                        <option value="index"><?php esc_html_e('Đặt thành Index', 'lacadev'); ?></option>
                        <option value="noindex"><?php esc_html_e('Đặt thành Noindex', 'lacadev'); ?></option>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Handle bulk edit save during save_post.
     *
     * @param int $postId
     * @param \WP_Post|null $post
     */
    public function handleBulkEditSave(int $postId, ?\WP_Post $post = null): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_REQUEST['bulk_edit']) || !check_admin_referer('bulk-posts')) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $action = sanitize_key($_REQUEST['laca_seo_bulk_robots'] ?? '');
        if (!in_array($action, ['index', 'noindex'], true)) {
            return;
        }

        $rawRobots = get_post_meta($postId, '_laca_seo_robots', true);
        $robots = is_array($rawRobots) ? $rawRobots : (empty($rawRobots) ? [] : [$rawRobots]);
        $robots = array_filter(array_map('strval', $robots));
        if ($action === 'noindex') {
            if (!in_array('noindex', $robots, true)) {
                $robots[] = 'noindex';
            }
            $robots = array_diff($robots, ['index']);
        } elseif ($action === 'index') {
            if (!in_array('index', $robots, true)) {
                $robots[] = 'index';
            }
            $robots = array_diff($robots, ['noindex']);
        }

        update_post_meta($postId, '_laca_seo_robots', array_values($robots));
    }
}
