<?php
/**
 * Carbon Fields meta box "Mục lục & bài viết liên quan" cho post type
 * "journal" — điều khiển phần mục lục đầu bài và danh sách "Bài viết liên
 * quan" cuối bài (xem app/helpers/journal-single-render.php).
 *
 * @package LacaDevClientChild
 */

use Carbon_Fields\Container;
use Carbon_Fields\Field;

if (!defined('ABSPATH')) {
    exit;
}

add_action('carbon_fields_register_fields', function () {
    Container::make('post_meta', __('Mục lục & bài viết liên quan', 'laca'))
        ->set_context('normal')
        ->set_priority('default')
        ->where('post_type', '=', 'journal')
        ->add_fields([
            Field::make('checkbox', 'journal_hide_toc', __('Ẩn mục lục ở bài này', 'laca'))
                ->set_option_value('yes')
                ->set_help_text(__('Mục lục tự lấy từ các tiêu đề H2 nằm sau block đầu tiên của bài.', 'laca')),

            Field::make('radio', 'related_posts_mode', __('Bài viết liên quan', 'laca'))
                ->set_options([
                    'auto' => __('Tự động (cùng danh mục)', 'laca'),
                    'manual' => __('Thủ công (chọn tay)', 'laca'),
                ])
                ->set_width(33.33)
                ->set_default_value('auto'),

            Field::make('association', 'related_posts_manual', __('Chọn bài viết', 'laca'))
                ->set_types([
                    ['type' => 'post', 'post_type' => 'journal'],
                ])
                ->set_max(10)
                ->set_width(66.66)
                ->set_conditional_logic([
                    ['field' => 'related_posts_mode', 'value' => 'manual'],
                ]),
        ]);
});
