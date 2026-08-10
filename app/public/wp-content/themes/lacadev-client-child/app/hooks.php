<?php
/**
 * Child Theme Hooks
 *
 * Đăng ký các actions và filters riêng của child theme.
 * Parent theme hooks vẫn chạy bình thường — chỉ thêm/ghi đè ở đây.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// CHILD HOOKS — thêm hooks của bạn bên dưới
// =============================================================================

// ── AJAX pagination markup (matches parent thePagination() BEM) ──────────────
require_once CHILD_APP_DIR . 'helpers/ajax-pagination-markup.php';

// ── Gallery Archive AJAX Handler ─────────────────────────────────────────────
require_once CHILD_APP_DIR . 'src/Ajax/GalleryAjaxHandler.php';
require_once CHILD_APP_DIR . 'src/Ajax/PdnTvAjaxHandler.php';
require_once CHILD_APP_DIR . 'src/Ajax/ProjectAjaxHandler.php';

// Theme con KHÔNG tự động cập nhật — mỗi site khách có bộ file theme con
// khác nhau (theme-options.php, header/footer, archive-*, single-*,
// block-gutenberg...), không có "1 bản chuẩn" để phát tán hàng loạt như
// theme cha. Đã cố tình bỏ ThemeUpdater ở đây (từng đăng ký nhầm, xem audit
// lacadev-client-child) — chỉ theme cha (lacadev-client) mới nhận update tự
// động qua clients.lacadev.com. Block Marketplace vẫn là cách DUY NHẤT được
// phép ghi file vào theme con, và chỉ ghi đúng 1 block/lần khi admin duyệt.

// Ví dụ: ghi đè excerpt length của parent
// add_filter('excerpt_length', function($length) {
//     return 25;
// }, 1000);

// Ví dụ: thêm custom body class
// add_filter('body_class', function($classes) {
//     $classes[] = 'child-theme';
//     return $classes;
// });
