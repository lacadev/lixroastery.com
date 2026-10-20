<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ảnh trong block/card — dùng chung để có srcset + sizes đúng bố cục + width/height.
 *
 * Block cũ lưu cả imageId lẫn imageUrl nhưng chỉ in imageUrl (bản gốc, có khi
 * >150KB/ảnh, không width/height): ảnh lazy không kích thước bị coi là nằm trong
 * màn hình đầu → tải ngay, tranh băng thông với ảnh LCP (PageSpeed mobile).
 *
 * @param int    $image_id ID attachment (0 nếu block chỉ có URL — dữ liệu cũ)
 * @param string $fallback_url URL dùng khi không có ID hợp lệ
 * @param string $alt    Text thuần (hàm tự escape)
 * @param string $sizes  Thuộc tính sizes theo bố cục, vd "(max-width: 768px) 100vw, 50vw"
 * @param array  $attr   Thuộc tính thêm (class, loading…)
 */
if (!function_exists('laca_block_image_html')) {
    function laca_block_image_html(int $image_id, string $fallback_url, string $alt, string $sizes, array $attr = []): string
    {
        $attr = array_merge([
            'alt'      => $alt,
            'loading'  => 'lazy',
            'decoding' => 'async',
            'sizes'    => $sizes,
        ], $attr);

        if ($image_id && wp_attachment_is_image($image_id)) {
            $html = wp_get_attachment_image($image_id, 'full', false, $attr);
            if ($html) {
                return $html;
            }
        }

        if ($fallback_url === '') {
            return '';
        }

        $extra = '';
        foreach (['class', 'loading', 'decoding'] as $key) {
            if (!empty($attr[$key])) {
                $extra .= ' ' . $key . '="' . esc_attr($attr[$key]) . '"';
            }
        }
        return '<img src="' . esc_url($fallback_url) . '" alt="' . esc_attr($alt) . '"' . $extra . ' />';
    }
}
