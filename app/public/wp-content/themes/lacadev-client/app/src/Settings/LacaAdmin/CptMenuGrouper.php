<?php

namespace App\Settings\LacaAdmin;

/**
 * Xếp mọi custom post type (CPT) đang hiển thị như 1 menu cấp cao nhất riêng
 * (show_in_menu === true) nằm liền kề ngay sau "Laca Theme" trong sidebar —
 * bất kể CPT đó được đăng ký qua App\Abstracts\AbstractPostType (CPT tĩnh,
 * ví dụ Service/Template) hay App\Features\DynamicCPT\DynamicCptManager (CPT
 * tạo qua admin UI). Không đụng tới CPT nào đã chủ động gắn show_in_menu vào
 * 1 trang cha cụ thể (dạng string, ví dụ Project → "Laca Projects").
 *
 * Cả 2 hệ đăng ký hiện tại đều hardcode menu_position = 25 giống nhau, nên
 * WordPress tự dồn từng CPT vào vị trí trống kế tiếp tùy thứ tự đăng ký —
 * thứ tự đó không ổn định (thay đổi mỗi khi thêm/sửa/xoá CPT), trông rải
 * rác. Đọc thẳng từ global $menu sau khi mọi menu đã đăng ký xong (hook
 * admin_menu, priority PHP_INT_MAX chạy cuối cùng) để biết chính xác "Laca
 * Theme" đang ở vị trí nào — không cần đoán/tính trước — rồi di chuyển toàn
 * bộ CPT top-level tới ngay sau đó, có chèn thêm 1 dòng phân cách
 * (wp-menu-separator) ở giữa để tách biệt "Laca Theme" với khối CPT.
 */
class CptMenuGrouper
{
    /**
     * Slug của trang "Laca Theme" (Carbon Fields, set_page_file() trong
     * theme/setup/theme-options.php) — điểm neo để xếp CPT nằm liền kề.
     */
    private const ANCHOR_MENU_SLUG = 'app-theme-options.php';

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'groupNearLacaTheme'], PHP_INT_MAX);
    }

    public function groupNearLacaTheme(): void
    {
        global $menu;

        $anchorKey = null;
        foreach ($menu as $key => $item) {
            if (($item[2] ?? '') === self::ANCHOR_MENU_SLUG) {
                $anchorKey = $key;
                break;
            }
        }

        if ($anchorKey === null) {
            return;
        }

        $cptSlugs = [];
        foreach (get_post_types(['_builtin' => false], 'objects') as $postType) {
            // So sánh strict true — loại các CPT đã chủ động gắn show_in_menu
            // vào 1 trang cha cụ thể (string), vì show_in_menu là chuỗi khác
            // rỗng cũng được PHP coi là "truthy" nếu so sánh lỏng (==).
            if ($postType->show_in_menu === true) {
                $cptSlugs[] = 'edit.php?post_type=' . $postType->name;
            }
        }

        if (empty($cptSlugs)) {
            return;
        }

        $moved = [];
        foreach ($menu as $key => $item) {
            if (in_array($item[2] ?? '', $cptSlugs, true)) {
                $moved[$item[2]] = $item;
                unset($menu[$key]);
            }
        }

        if (empty($moved)) {
            return;
        }

        // Chèn 1 dòng phân cách (giống style mặc định của WP core) ngay sau
        // "Laca Theme", trước khối CPT, để tách biệt trực quan 2 nhóm menu.
        $menu[(string) ((float) $anchorKey + 0.00005)] = ['', 'read', 'laca-theme-separator', '', 'wp-menu-separator'];

        // Giữ đúng thứ tự get_post_types() trả về (theo tên CPT, alphabet) để
        // vị trí luôn ổn định giữa các lần load trang.
        $i = 0;
        foreach ($cptSlugs as $slug) {
            if (!isset($moved[$slug])) {
                continue;
            }
            $i++;
            $menu[(string) ((float) $anchorKey + $i * 0.0001)] = $moved[$slug];
        }

        ksort($menu);
    }
}
