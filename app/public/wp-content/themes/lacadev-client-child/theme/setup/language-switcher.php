<?php
/**
 * Language switcher kiểu "hover để đổi" — tham khảo cách làm của xliiicoffee
 * (resources/styles/theme/layout/_header.scss .language-switcher): mặc định
 * chỉ hiện ngôn ngữ hiện tại, hover vào (hoặc focus, cho bàn phím) thì đổi
 * chỗ hiện ngôn ngữ còn lại — xử lý hoàn toàn bằng CSS (position: absolute +
 * opacity swap), không JS.
 *
 * Khác với theLanguageSwitcher() của parent theme (dùng hide_current: true —
 * chỉ IN RA ngôn ngữ còn lại, không có ngôn ngữ hiện tại để swap), hàm này
 * in ra CẢ 2 ngôn ngữ rồi để CSS lo ẩn/hiện. Đặt riêng ở child theme, không
 * sửa theLanguageSwitcher() dùng chung để tránh ảnh hưởng các site khác
 * đang dùng hàm đó.
 */

if (!defined('ABSPATH')) {
    exit;
}

function laca_language_switcher_hover(): void
{
    if (!function_exists('pll_the_languages')) {
        return;
    }

    $languages = pll_the_languages([
        'show_names'    => true,
        'show_flags'    => false,
        'hide_if_empty' => false,
        'raw'           => true,
    ]);

    if (empty($languages)) {
        return;
    }

    echo '<ul class="language-switcher">';
    foreach ($languages as $lang) {
        $class = !empty($lang['current_lang']) ? 'current-lang' : '';
        printf(
            '<li class="%1$s"><a href="%2$s" hreflang="%3$s">%4$s</a></li>',
            esc_attr($class),
            esc_url($lang['url']),
            esc_attr($lang['slug']),
            esc_html($lang['name'])
        );
    }
    echo '</ul>';
}
