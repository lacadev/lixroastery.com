<?php

namespace App\Features\LacaSeo\Admin;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Handles admin notices for Laca SEO.
 */
class Notices
{
    public static function render(): void
    {
        // 0. Flash notices from transients
        $userId = get_current_user_id();
        $noticeKey = 'laca_seo_notice_' . $userId;
        $flash = get_transient($noticeKey);
        if ($flash && is_array($flash)) {
            delete_transient($noticeKey);
            $type = esc_attr($flash['type'] ?? 'info');
            $msg = esc_html($flash['message'] ?? '');
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . $msg . '</p></div>';
        }

        // 1. Notice if Rank Math is active
        if (Helper::isRankMathActive()) {
            echo '<div class="notice notice-warning laca-seo-alert">';
            echo '<p><strong><span class="dashicons dashicons-warning"></span> Chế độ an toàn:</strong> Plugin Rank Math đang kích hoạt trên website. Laca SEO đang hoạt động ở chế độ cấu hình/nhập liệu và <em>tự động tắt toàn bộ thẻ xuất ra ngoài frontend</em> để tránh xung đột trùng lặp thẻ.</p>';
            echo '</div>';
        }

        // Check if there are posts with Rank Math meta that do not have Laca SEO meta
        global $wpdb;
        // Chỉ đếm meta RM chưa có bản _laca_seo_ cùng hậu tố; bỏ các key importer cố ý
        // không chép (cache/analytics tự sinh lại) — nếu không notice báo sai mãi.
        $unimportedCount = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT pm.post_id)
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->postmeta} laca
                ON pm.post_id = laca.post_id
               AND laca.meta_key = CONCAT('_laca_seo_', SUBSTRING(pm.meta_key, 11))
            WHERE pm.meta_key LIKE 'rank\\_math\\_%'
              AND pm.meta_key NOT IN ('rank_math_internal_links_processed', 'rank_math_og_content_image', 'rank_math_analytic_object_id', 'rank_math_contentai_score')
              AND pm.meta_value <> ''
              AND laca.meta_id IS NULL
        ");

        if ($unimportedCount > 0) {
            $migrateUrl = esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'import-export'], admin_url('admin.php')));
            echo '<div class="notice notice-info laca-seo-alert">';
            echo '<p><strong><span class="dashicons dashicons-migrate"></span> Chưa đồng bộ dữ liệu:</strong> Còn <strong>' . esc_html((string) $unimportedCount) . '</strong> bài viết có dữ liệu SEO của Rank Math chưa được chuyển đổi sang Laca SEO. <a href="' . $migrateUrl . '" class="button button-small button-secondary" style="margin-left:8px;">Mở trang chuyển đổi ngay</a></p>';
            echo '</div>';
        }

        // 2. Notice if site is set to Discourage search engines from indexing this site
        if (!get_option('blog_public')) {
            echo '<div class="notice notice-error laca-seo-alert">';
            echo '<p><strong><span class="dashicons dashicons-hidden"></span> Cảnh báo quan trọng:</strong> Website đang bật tính năng <em>"Ngăn chặn các công cụ tìm kiếm đánh chỉ mục trang web này"</em> trong <a href="' . esc_url(admin_url('options-reading.php')) . '">Cài đặt › Đọc</a>. Mọi thẻ robots trên website sẽ bị ép thành <code>noindex</code> cho đến khi tắt tuỳ chọn này.</p>';
            echo '</div>';
        }

        // 3. Notice if physical robots.txt exists
        $robots_physical = ABSPATH . 'robots.txt';
        if (file_exists($robots_physical)) {
            echo '<div class="notice notice-info laca-seo-alert">';
            echo '<p><strong><span class="dashicons dashicons-info"></span> Thông báo tệp vật lý:</strong> Phát hiện tệp <code>robots.txt</code> vật lý tại thư mục gốc của hosting. Các cấu hình robots.txt ảo trong plugin có thể không có hiệu lực cho đến khi xoá hoặc chỉnh sửa trực tiếp tệp vật lý này.</p>';
            echo '</div>';
        }
    }
}
