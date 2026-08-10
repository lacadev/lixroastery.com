<?php

namespace App\Settings\LacaTools\Management;

/**
 * ContentAuditService
 *
 * Kiểm tra sức khỏe nội dung toàn site theo lịch cron hàng tuần.
 * Lưu kết quả vào wp_options để dashboard widget đọc.
 *
 * Danh sách check:
 *  1. Thiếu ảnh đại diện
 *  2. Nội dung quá ngắn
 *  3. Ảnh nội dung src rỗng
 *  4. Thiếu Meta Description (Yoast / Rank Math / SEOPress)
 *  5. Thiếu Tag hoặc Category (với post)
 *  6. Nội dung lỗi thời (publish > 2 năm, chưa cập nhật)
 *  7. Title quá ngắn (< 10 ký tự) hoặc quá dài (> 70 ký tự)
 *  8. URL chứa ký tự không phải ASCII (% encoded tiếng Việt)
 *  9. Ảnh trong content thiếu alt
 * 10. Tiêu đề trùng nhau
 * 11. Excerpt trùng nhau
 * 12. Draft cũ (> 30 ngày)
 * 13. Shortcode bị lỗi cú pháp
 * 14. Link nội bộ hỏng (404) — giới hạn 30 URL/lần, kết quả cache 24h
 */
class ContentAuditService
{
    /** Số URL nội bộ tối đa kiểm tra mỗi lần audit (tránh timeout). */
    private const MAX_LINK_CHECKS = 30;

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'addWeeklyCronSchedule']);
        add_action('init', [$this, 'scheduleWeeklyDeepAudit']);
        add_action('lacadev_weekly_deep_audit', [$this, 'executeDeepAudit']);
    }

    // ─── Cron helpers ────────────────────────────────────────────────────────

    public function addWeeklyCronSchedule(array $schedules): array
    {
        if (!isset($schedules['weekly_midnight'])) {
            $schedules['weekly_midnight'] = [
                'interval' => 7 * 24 * 60 * 60,
                'display'  => __('Chủ nhật hàng tuần', 'laca'),
            ];
        }
        return $schedules;
    }

    public function scheduleWeeklyDeepAudit(): void
    {
        if (!wp_next_scheduled('lacadev_weekly_deep_audit')) {
            wp_schedule_event(strtotime('next sunday 00:00:00'), 'weekly_midnight', 'lacadev_weekly_deep_audit');
        }
    }

    // ─── Main audit ──────────────────────────────────────────────────────────

    public function executeDeepAudit(): void
    {
        if (get_transient('lacadev_deep_audit_lock')) {
            return;
        }
        set_transient('lacadev_deep_audit_lock', true, 2 * HOUR_IN_SECONDS);

        $post_types = $this->getDashboardPostTypes();
        $results    = [];

        global $wpdb;

        // ── 1. Thiếu ảnh đại diện ────────────────────────────────────────────
        $cpt_for_images = array_diff((array) $post_types, ['page']);
        if (!empty($cpt_for_images)) {
            $in  = implode(',', array_fill(0, count($cpt_for_images), '%s'));
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, p.post_title, p.post_type
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm
                    ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
                WHERE p.post_type IN ($in)
                  AND p.post_status = 'publish'
                  AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')
            ", $cpt_for_images));

            foreach ($rows as $p) {
                $this->addError($results, $p, '⚠️ Thiếu ảnh đại diện');
            }
        }

        // ── Lấy published posts để check nhiều thứ ────────────────────────────
        $in_all = implode(',', array_fill(0, count((array) $post_types), '%s'));
        $posts  = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_type, post_content, post_excerpt,
                   post_name, post_status, post_date, post_modified
            FROM {$wpdb->posts}
            WHERE post_type IN ($in_all)
              AND post_status = 'publish'
        ", $post_types));

        // Index duplicate titles & excerpts
        $title_map   = [];
        $excerpt_map = [];
        foreach ($posts as $p) {
            $title_map[$p->post_title][]     = $p->ID;
            if (!empty($p->post_excerpt)) {
                $excerpt_map[$p->post_excerpt][] = $p->ID;
            }
        }

        $two_years_ago    = strtotime('-2 years');
        $thirty_days_ago  = strtotime('-30 days');
        $internal_urls    = []; // thu thập để check 404 sau

        foreach ($posts as $p) {
            $word_count = str_word_count(strip_tags($p->post_content));

            // ── 2. Nội dung quá ngắn ─────────────────────────────────────────
            if ($word_count > 0 && $word_count < 100) {
                $this->addError($results, $p, '📝 Nội dung quá ngắn (< 100 từ)');
            }

            // ── 3. Ảnh nội dung src rỗng ─────────────────────────────────────
            if (preg_match('/<img[^>]+src=["\']\\s*["\']/', $p->post_content) ||
                strpos($p->post_content, 'src=""') !== false) {
                $this->addError($results, $p, '🖼️ Ảnh nội dung bị vỡ (src rỗng)');
            }

            // ── 7. Title quá ngắn hoặc quá dài ──────────────────────────────
            $title_len = mb_strlen($p->post_title);
            if ($title_len < 10) {
                $this->addError($results, $p, '🔤 Tiêu đề quá ngắn (< 10 ký tự)');
            } elseif ($title_len > 70) {
                $this->addError($results, $p, '🔤 Tiêu đề quá dài (> 70 ký tự)');
            }

            // ── 6. Nội dung lỗi thời ─────────────────────────────────────────
            $pub_ts = strtotime($p->post_date);
            $mod_ts = strtotime($p->post_modified);
            if ($pub_ts < $two_years_ago && $mod_ts < $two_years_ago) {
                $this->addError($results, $p, '📅 Nội dung lỗi thời (chưa cập nhật > 2 năm)');
            }

            // ── 8. URL có ký tự % (tiếng Việt không rewrite) ─────────────────
            if (preg_match('/%[0-9A-Fa-f]{2}/', $p->post_name)) {
                $this->addError($results, $p, '🔒 URL chứa ký tự đặc biệt (cần đổi slug)');
            }

            // ── 9. Ảnh thiếu alt ─────────────────────────────────────────────
            if (preg_match_all('/<img[^>]+>/i', $p->post_content, $img_matches)) {
                foreach ($img_matches[0] as $img_tag) {
                    if (!preg_match('/\balt\s*=/i', $img_tag) ||
                        preg_match('/alt\s*=\s*["\']["\']/', $img_tag)) {
                        $this->addError($results, $p, '🖼️ Ảnh trong nội dung thiếu alt text');
                        break; // 1 lỗi / bài là đủ
                    }
                }
            }

            // ── 10. Tiêu đề trùng ────────────────────────────────────────────
            if (count($title_map[$p->post_title] ?? []) > 1) {
                $this->addError($results, $p, '📄 Tiêu đề trùng với bài viết khác');
            }

            // ── 11. Excerpt trùng ────────────────────────────────────────────
            if (!empty($p->post_excerpt) && count($excerpt_map[$p->post_excerpt] ?? []) > 1) {
                $this->addError($results, $p, '🔁 Tóm tắt (excerpt) trùng bài khác');
            }

            // ── 13. Shortcode lỗi cú pháp ────────────────────────────────────
            if ($this->hasBrokenShortcode($p->post_content)) {
                $this->addError($results, $p, '🧩 Shortcode có thể bị lỗi cú pháp');
            }

            // Thu thập URL nội bộ để check 404
            $this->collectInternalUrls($p->post_content, $internal_urls);
        }

        // ── 4. Thiếu Meta Description ─────────────────────────────────────────
        $this->checkMissingMetaDesc($results, $post_types, $posts, $wpdb);

        // ── 5. Thiếu Taxonomy (tag/category với post) ────────────────────────
        $this->checkMissingTaxonomy($results, $posts);

        // ── 12. Draft cũ (> 30 ngày) ─────────────────────────────────────────
        $old_drafts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_type, post_date
            FROM {$wpdb->posts}
            WHERE post_status = 'draft'
              AND post_type NOT IN ('revision','auto-draft','nav_menu_item')
              AND post_date < %s
        ", date('Y-m-d H:i:s', $thirty_days_ago)));

        foreach ($old_drafts as $p) {
            $this->addError($results, $p, sprintf(
                '🗑️ Draft cũ (từ %s)',
                date('d/m/Y', strtotime($p->post_date))
            ));
        }

        // ── 14. Link nội bộ hỏng ─────────────────────────────────────────────
        $this->checkBrokenInternalLinks($results, $internal_urls, $posts);

        // ── Lưu kết quả ──────────────────────────────────────────────────────
        update_option('lacadev_deep_health_report', [
            'last_updated' => current_time('mysql'),
            'issues'       => array_values($results),
        ], false);

        delete_transient('lacadev_deep_audit_lock');
    }

    // ─── Helper: thêm lỗi không trùng ───────────────────────────────────────

    private function addError(array &$results, object $p, string $error): void
    {
        if (!isset($results[$p->ID])) {
            $results[$p->ID] = [
                'ID'         => $p->ID,
                'post_title' => $p->post_title,
                'post_type'  => $p->post_type,
                'errors'     => [],
            ];
        }
        if (!in_array($error, $results[$p->ID]['errors'], true)) {
            $results[$p->ID]['errors'][] = $error;
        }
    }

    // ─── Check 4: Thiếu Meta Description ─────────────────────────────────────

    private function checkMissingMetaDesc(array &$results, array $post_types, array $posts, \wpdb $wpdb): void
    {
        if (empty($posts)) return;

        $post_ids   = array_column($posts, 'ID');
        $id_in      = implode(',', array_map('absint', $post_ids));
        $meta_keys  = ['_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc'];
        $meta_in    = implode(',', array_fill(0, count($meta_keys), '%s'));

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id IN ($id_in)
              AND meta_key IN ($meta_in)
        ", $meta_keys));

        $has_desc = [];
        foreach ($rows as $r) {
            if (!empty(trim($r->meta_value))) {
                $has_desc[$r->post_id] = true;
            }
        }

        $post_map = [];
        foreach ($posts as $p) {
            $post_map[$p->ID] = $p;
        }

        foreach ($post_ids as $id) {
            if (!isset($has_desc[$id]) && isset($post_map[$id])) {
                $this->addError($results, $post_map[$id], '🔗 Thiếu Meta Description (SEO)');
            }
        }
    }

    // ─── Check 5: Thiếu Taxonomy ─────────────────────────────────────────────

    private function checkMissingTaxonomy(array &$results, array $posts): void
    {
        foreach ($posts as $p) {
            if ($p->post_type !== 'post') continue;

            $cats = get_the_category($p->ID);
            $tags = get_the_tags($p->ID);

            $uncategorized_only = (
                count($cats) === 1 &&
                isset($cats[0]->slug) &&
                $cats[0]->slug === 'uncategorized'
            );

            if (empty($cats) || $uncategorized_only) {
                $this->addError($results, $p, '🏷️ Chưa phân loại Category');
            }
            if (empty($tags)) {
                $this->addError($results, $p, '🏷️ Chưa có Tag');
            }
        }
    }

    // ─── Check 13: Shortcode lỗi ─────────────────────────────────────────────

    private function hasBrokenShortcode(string $content): bool
    {
        // Phát hiện [ không có ] tương ứng hoặc [/ không có [
        $open  = substr_count($content, '[');
        $close = substr_count($content, ']');
        if ($open !== $close) return true;

        // [shortcode với dấu nháy không đóng
        if (preg_match('/\[[^\]]*"[^\]]*$/', $content)) return true;

        return false;
    }

    // ─── Check 14: Thu thập URL nội bộ ───────────────────────────────────────

    private function collectInternalUrls(string $content, array &$urls): void
    {
        $home = home_url();
        if (!preg_match_all('/href=["\'](' . preg_quote($home, '/') . '[^"\']*)["\']/', $content, $m)) {
            return;
        }
        foreach ($m[1] as $url) {
            $clean = strtok($url, '#'); // bỏ fragment
            if ($clean && !isset($urls[$clean])) {
                $urls[$clean] = null;
            }
        }
    }

    private function checkBrokenInternalLinks(array &$results, array $internal_urls, array $posts): void
    {
        if (empty($internal_urls)) return;

        $home    = home_url();
        $checked = get_transient('lacadev_link_check_cache') ?: [];
        $count   = 0;

        foreach ($internal_urls as $url => $_) {
            if ($count >= self::MAX_LINK_CHECKS) break;

            // Dùng cache nếu đã check trong 24h
            if (isset($checked[$url])) {
                $status = $checked[$url];
            } else {
                $response = wp_remote_head($url, [
                    'timeout'    => 5,
                    'sslverify'  => false,
                    'user-agent' => 'LacaDevAudit/1.0',
                ]);
                $status = is_wp_error($response)
                    ? 0
                    : (int) wp_remote_retrieve_response_code($response);
                $checked[$url] = $status;
                $count++;
            }

            if ($status === 404) {
                // Tìm bài nào chứa link này
                foreach ($posts as $p) {
                    if (strpos($p->post_content, $url) !== false) {
                        $this->addError($results, $p, sprintf(
                            '🔗 Link nội bộ hỏng (404): %s',
                            esc_url($url)
                        ));
                    }
                }
            }
        }

        set_transient('lacadev_link_check_cache', $checked, DAY_IN_SECONDS);
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Trả về cached audit issues, chạy audit ngay nếu chưa có dữ liệu.
     */
    public function auditContentHealth(array $post_types): array
    {
        $report = get_option('lacadev_deep_health_report');

        if ($report && isset($report['issues'])) {
            return (array) $report['issues'];
        }

        if (!get_transient('lacadev_deep_audit_lock')) {
            $this->executeDeepAudit();
            $report = get_option('lacadev_deep_health_report');
            return (isset($report['issues']) && is_array($report['issues'])) ? $report['issues'] : [];
        }

        return [];
    }

    /**
     * Trả về post types dùng trong dashboard widgets.
     */
    public function getDashboardPostTypes(): array
    {
        $configured   = carbon_get_theme_option('dashboard_widget_post_types');
        $discoverable = get_post_types(['public' => true, 'show_in_menu' => true], 'names');

        $excluded = apply_filters('lacadev_dashboard_post_types_excluded', [
            'attachment', 'revision', 'nav_menu_item', 'custom_css',
            'customize_changeset', 'oembed_cache', 'user_request',
            'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation',
        ]);
        $available = array_diff($discoverable, $excluded);

        $types = !empty($configured) ? (array) $configured : array_values($available);

        foreach (['service', 'project'] as $core_cpt) {
            if (post_type_exists($core_cpt) && !in_array($core_cpt, $types, true)) {
                $types[] = $core_cpt;
            }
        }

        return array_values(apply_filters('lacadev_dashboard_post_types', $types));
    }
}
