<?php
/**
 * Category custom meta fields.
 *
 * @package WPEmergeTheme
 */

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field;

if (!defined('ABSPATH')) {
    exit;
}

Container::make('term_meta', __('Layout Settings', 'laca'))
    ->where('term_taxonomy', 'IN', array('category', 'blog_cat'))
    ->add_fields(array(
        Field::make('html', 'layout_settings_info', '')
            ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Cài đặt bố cục</p><p style="margin:0;font-size:13px;color:#374151">Chọn cách hiển thị danh sách bài viết ở trang chuyên mục (archive) của danh mục này — dạng Card (bản tin) hoặc Staggered (so le).</p></div>'),

        Field::make('select', 'crb_archive_layout', __('Archive Layout', 'laca'))
            ->set_options(array(
                'card' => __('Card Layout (Bản tin)', 'laca'),
                'staggered' => __('Staggered Layout (So le)', 'laca'),
            ))
            ->set_default_value('card'),
    ));
