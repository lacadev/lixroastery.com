<?php

/**
 * Meta fields cho CPT: gallery
 * File được sinh tự động — có thể chỉnh sửa trực tiếp.
 * Thay đổi có hiệu lực ngay sau khi lưu (không cần compile).
 *
 * Tham khảo Carbon Fields API: https://docs.carbonfields.net
 */

add_action('carbon_fields_register_fields', function () {
    \Carbon_Fields\Container\Container::make('post_meta', __('Thông tin gallery', 'laca'))
        ->where('post_type', '=', 'gallery')
        ->add_fields([
            \Carbon_Fields\Field\Field::make('html', 'gallery_meta_intro', __('', 'laca'))
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Thông tin gallery</p><p style="margin:0;font-size:13px;color:#374151">Nhập các thông tin chi tiết và thư viện ảnh cho mục gallery/portfolio này (chủ đầu tư, diện tích, số tầng, hình ảnh...). Nội dung sẽ hiển thị ở trang chi tiết của mục này.</p></div>'),

            \Carbon_Fields\Field\Field::make('text', 'investor', __('Chủ đầu tư', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập tên chủ đầu tư'),
                \Carbon_Fields\Field\Field::make('text', 'floors', __('Số tầng', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập tên chủ đầu tư'),
                \Carbon_Fields\Field\Field::make('text', 'location', __('Địa điểm', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập địa điểm'),

                \Carbon_Fields\Field\Field::make('text', 'total_area', __('Tổng diện tích', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập tổng diện tích'),

                \Carbon_Fields\Field\Field::make('media_gallery', 'project_gallery', __('Thư viện ảnh dự án', 'laca')),
        ]);
});