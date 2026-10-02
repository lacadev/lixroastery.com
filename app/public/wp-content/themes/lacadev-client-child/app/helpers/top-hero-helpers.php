<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dùng chung giữa theme/header.php (gắn class overlay trong suốt lên
 * <header>) và theme/page.php (bỏ breadcrumb) — cả 2 đều cần biết CHÍNH XÁC
 * cùng 1 điều kiện "trang bắt đầu bằng block Top Hero" nên tách hàm riêng,
 * tránh 2 nơi tự parse_blocks() rồi lệch nhau dần theo thời gian.
 */
if (!function_exists('laca_page_starts_with_top_hero')) {
    function laca_page_starts_with_top_hero(): bool
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }

        $first_block_name = null;
        foreach (parse_blocks(get_the_content()) as $block) {
            if (!empty($block['blockName'])) {
                $first_block_name = $block['blockName'];
                break;
            }
        }

        $result = $first_block_name === 'lacadev/top-hero-block';
        return $result;
    }
}
