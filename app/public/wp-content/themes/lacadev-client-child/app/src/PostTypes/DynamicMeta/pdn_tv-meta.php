<?php

/**
 * Meta fields cho CPT: pdn_tv
 * File được sinh tự động — có thể chỉnh sửa trực tiếp.
 * Thay đổi có hiệu lực ngay sau khi lưu (không cần compile).
 *
 * Tham khảo Carbon Fields API: https://docs.carbonfields.net
 */

add_action('carbon_fields_register_fields', function () {
    \Carbon_Fields\Container\Container::make('post_meta', __('Thông tin PĐN TV', 'laca'))
        ->where('post_type', '=', 'pdn_tv')
        ->add_fields([
            \Carbon_Fields\Field\Field::make('html', 'pdn_tv_meta_intro', __('', 'laca'))
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Thông tin PĐN TV</p><p style="margin:0;font-size:13px;color:#374151">Dán đường link video Youtube để hiển thị video cho mục nội dung video/TV này.</p></div>'),

            \Carbon_Fields\Field\Field::make('text', 'ytb_url', __('Youtube url', 'laca')),
        ]);
});