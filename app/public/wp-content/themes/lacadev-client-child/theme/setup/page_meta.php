<?php
/**
 * Carbon Fields custom field cho post type "page" — cho phép admin tự chọn
 * ẩn breadcrumb ở từng Page riêng lẻ (mặc định vẫn hiện như trước).
 *
 * @package LacaDevClientChild
 */

use Carbon_Fields\Container;
use Carbon_Fields\Field;

if (!defined('ABSPATH')) {
    exit;
}

add_action('carbon_fields_register_fields', function () {
    // Context 'side' (KHÔNG dùng 'carbon_fields_after_title') — Page dùng
    // Block Editor, context đó chỉ render được ở Classic Editor (xem bug
    // tương tự đã gặp + đã sửa ở glossary_meta.php).
    Container::make('post_meta', __('Breadcrumb', 'laca'))
        ->set_context('side')
        ->set_priority('default')
        ->where('post_type', '=', 'page')
        ->add_fields([
            Field::make('checkbox', 'hide_breadcrumb', __('Ẩn breadcrumb trên trang này', 'laca'))
                ->set_option_value('yes')
                ->set_help_text(__('Mặc định breadcrumb vẫn hiện — tick vào đây nếu muốn ẩn riêng cho Page này.', 'laca')),
        ]);
});
