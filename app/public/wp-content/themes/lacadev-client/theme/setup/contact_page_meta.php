<?php
/**
 * Contact Page (template-contact.php) custom meta fields — hiển thị ngay
 * trên trang đang chọn "Contact Page" template (Page Attributes), thay vì
 * ở Theme Options chung, để mỗi trang/site tự nhập nội dung riêng.
 *
 * @package WPEmergeTheme
 */

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Field;

if (!defined('ABSPATH')) {
    exit;
}

Container::make('post_meta', __('Nội dung trang Liên hệ', 'laca'))
    ->where('post_template', '=', 'page_templates/template-contact.php')
    ->add_fields([
        Field::make('html', 'contact_page_meta_intro', __('', 'laca'))
            ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Nội dung trang Liên hệ</p><p style="margin:0;font-size:13px;color:#374151">Tiêu đề và mô tả hiển thị ở khối "Ping tôi tại đây" trên trang này.</p></div>'),

        Field::make('text', 'contact_page_heading', __('Tiêu đề', 'laca'))
            ->set_attribute('placeholder', 'Mặc định: "Ping tôi tại đây"'),
        Field::make('textarea', 'contact_page_description', __('Mô tả ngắn', 'laca'))
            ->set_attribute('placeholder', 'Mô tả ngắn dưới tiêu đề'),
    ]);
