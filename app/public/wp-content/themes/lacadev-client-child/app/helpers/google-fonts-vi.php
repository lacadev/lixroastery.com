<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Danh sách Google Font có hỗ trợ subset tiếng Việt — bản PHP mirror của
 * block-gutenberg/utils/google-fonts-vi.js (dùng cho ComboboxControl ở
 * editor). Dùng ở đây để WHITELIST-VALIDATE giá trị attribute trước khi tin
 * (attribute lưu trong post_content, có thể bị chỉnh sửa bất thường qua REST
 * API) — không chỉ escape, vì escape không ngăn được 1 chuỗi tuỳ ý trở thành
 * tên font/URL enqueue ra cho mọi khách xem trang.
 *
 * ⚠️ Thêm/bớt font thì sửa CẢ 2 file (JS + PHP này) cho khớp.
 */
if (!function_exists('laca_google_fonts_vi_list')) {
    function laca_google_fonts_vi_list(): array
    {
        return [
            'Afacad',
            'Archivo',
            'Barlow',
            'Be Vietnam Pro',
            'Fira Sans',
            'IBM Plex Sans',
            'Inter',
            'Josefin Sans',
            'Lexend',
            'Lora',
            'Manrope',
            'Merriweather',
            'Montserrat',
            'Mulish',
            'Noto Sans',
            'Nunito',
            'Open Sans',
            'Oswald',
            'Playfair Display',
            'Plus Jakarta Sans',
            'Poppins',
            'PT Sans',
            'PT Serif',
            'Public Sans',
            'Quicksand',
            'Raleway',
            'Roboto',
            'Roboto Slab',
            'Rubik',
            'Source Sans 3',
            'Work Sans',
        ];
    }
}

if (!function_exists('laca_google_font_css_url')) {
    function laca_google_font_css_url(string $family): string
    {
        // Google Fonts css2 API dùng dấu "+" cho khoảng trắng trong tên font —
        // không dùng add_query_arg()/http_build_query() vì sẽ urlencode "+"
        // thành "%2B" làm sai URL.
        return 'https://fonts.googleapis.com/css2?family=' .
            str_replace(' ', '+', $family) .
            ':wght@400&display=swap';
    }
}
