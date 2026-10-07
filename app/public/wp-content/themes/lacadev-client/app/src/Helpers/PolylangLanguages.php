<?php

namespace App\Helpers;

/**
 * Trả về danh sách ngôn ngữ ĐANG BẬT thật trên site qua Polylang — dùng
 * chung cho mọi UI dịch AI (Form Builder, Block Editor...) để mỗi nơi không
 * phải tự viết lại logic đọc Polylang riêng.
 */
class PolylangLanguages
{
    /**
     * @return array<int, array{slug:string,name:string,is_default:bool}>
     *         Rỗng nếu Polylang tắt hoặc site chỉ có 1 ngôn ngữ (không có gì
     *         để dịch sang) — nơi gọi tự ẩn toàn bộ UI dịch khi mảng rỗng.
     */
    public static function getActive(): array
    {
        if (!function_exists('pll_languages_list') || !function_exists('pll_default_language')) {
            return [];
        }
        $slugs = pll_languages_list(['fields' => 'slug']);
        if (!is_array($slugs) || count($slugs) < 2) {
            return [];
        }
        // Gọi 'name' cùng thứ tự list_order với 'slug' ở trên nên ghép theo
        // index là đúng — Polylang không có API trả cặp slug+name 1 lần.
        $names = pll_languages_list(['fields' => 'name']);
        $default = pll_default_language();
        $languages = [];
        foreach ($slugs as $i => $slug) {
            $languages[] = [
                'slug' => $slug,
                'name' => $names[$i] ?? strtoupper($slug),
                'is_default' => $slug === $default,
            ];
        }
        return $languages;
    }
}
