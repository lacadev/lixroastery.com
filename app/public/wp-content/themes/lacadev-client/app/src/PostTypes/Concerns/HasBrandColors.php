<?php

namespace App\PostTypes\Concerns;

/**
 * Trait HasBrandColors
 *
 * Chuẩn hoá mã màu HEX cho field brand_colors:
 * - Tự động thêm '#' nếu thiếu
 * - Chuyển thành UPPERCASE
 * - Bỏ giá trị không hợp lệ
 *
 * Used by: App\PostTypes\Project
 */
trait HasBrandColors
{
    public function normalizeBrandColorsOnSave($value, $id, $name, $field)
    {
        if (strpos((string) $name, '_brand_colors|hex|') !== false) {
            return $this->normalizeHexColor((string) $value);
        }

        return $value;
    }

    public function normalizeBrandColorsOnLoad($value, $id, $name, $field)
    {
        if (strpos((string) $name, '_brand_colors|hex|') !== false) {
            $normalized = $this->normalizeHexColor((string) $value);
            return $normalized !== '' ? $normalized : $value;
        }

        return $value;
    }

    // -----------------------------------------------------------------------

    private function normalizeHexColor(string $hex): string
    {
        $hex = trim($hex);
        if ($hex === '') {
            return '';
        }

        if ($hex[0] !== '#') {
            $hex = '#' . $hex;
        }

        $hex = strtoupper($hex);
        if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $hex)) {
            return '';
        }

        return $hex;
    }
}
