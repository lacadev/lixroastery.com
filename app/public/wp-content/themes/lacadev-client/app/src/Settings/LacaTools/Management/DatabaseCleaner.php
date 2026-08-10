<?php

namespace App\Settings\LacaTools\Management;

/**
 * DatabaseCleaner
 *
 * Admin tool: Appearance > Dọn dẹp Database
 * Giúp admin dọn dẹp dữ liệu rác WordPress 1 click.
 *
 * Các mục dọn:
 *  1. Post revisions cũ (giữ 3 bản mới nhất per post)
 *  2. Auto-drafts
 *  3. Trashed posts
 *  4. Orphaned post meta
 *  5. Expired transients
 *  6. Spam & trashed comments
 */
class DatabaseCleaner
{
    const MENU_SLUG   = 'laca-db-cleaner';
    const PARENT_SLUG = 'laca-admin';
    const NONCE       = 'laca_db_cleaner';
    const CAP         = 'manage_options';

    public function register(): void
    {
        add_action('admin_menu',                [$this, 'registerMenu'], 20);
        add_action('wp_ajax_laca_db_clean',     [$this, 'handleClean']);
        add_action('wp_ajax_laca_db_analyze',   [$this, 'handleAnalyze']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            'Dọn dẹp Database',
            'Dọn dẹp DB',
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Không có quyền.');
        }
        ?>
        <div class="wrap">
            <h1>🧹 Dọn dẹp Database</h1>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">
                <p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Dọn dẹp Database</p>
                <p style="margin:0;font-size:13px;color:#374151">Xoá bớt dữ liệu rác (bản nháp, thùng rác, bình luận spam, bản chỉnh sửa cũ...) tích tụ theo thời gian để website chạy nhanh hơn. Đây là thao tác dọn dẹp và <strong>không thể hoàn tác</strong> — hãy đọc kỹ từng mục trước khi bấm "Dọn dẹp".</p>
            </div>
            <p style="color:#666">Xoá dữ liệu rác để database gọn nhẹ hơn. Mỗi thao tác không thể hoàn tác — hãy backup trước nếu cần.</p>

            <div id="laca-db-analyzer" style="margin:15px 0;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;display:none">
                <strong>📊 Phân tích hiện tại:</strong>
                <span id="laca-db-stats" style="margin-left:10px;color:#666">Đang tải...</span>
            </div>

            <table class="wp-list-table widefat fixed striped" style="max-width:800px">
                <thead>
                    <tr>
                        <th>Loại dữ liệu rác</th>
                        <th style="width:160px">Số lượng ước tính</th>
                        <th style="width:140px">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="laca-db-rows">
                    <?php
                    $items = [
                        ['key' => 'revisions',      'label' => '📝 Post Revisions cũ',           'desc' => 'Giữ lại 3 bản mới nhất mỗi bài'],
                        ['key' => 'autodrafts',     'label' => '🗒 Auto-drafts',                  'desc' => 'Bản nháp tự động chưa lưu'],
                        ['key' => 'trashed_posts',  'label' => '🗑 Bài trong Thùng rác',         'desc' => 'Posts/Pages đã xoá chờ dọn'],
                        ['key' => 'orphan_meta',    'label' => '🔗 Orphaned post meta',           'desc' => 'Meta không gắn với post nào'],
                        ['key' => 'transients',     'label' => '⏰ Expired transients',           'desc' => 'Cache tạm thời đã hết hạn'],
                        ['key' => 'spam_comments',  'label' => '💬 Spam & trashed comments',      'desc' => 'Bình luận spam và trong thùng rác'],
                    ];
                    foreach ($items as $item):
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($item['label']); ?></strong>
                            <br><small style="color:#999"><?php echo esc_html($item['desc']); ?></small>
                        </td>
                        <td>
                            <span class="laca-count-badge" data-key="<?php echo esc_attr($item['key']); ?>" style="color:#999">—</span>
                        </td>
                        <td>
                            <button class="button button-secondary laca-clean-btn"
                                data-key="<?php echo esc_attr($item['key']); ?>"
                                data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE)); ?>">
                                Dọn dẹp
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:16px">
                <button class="button button-primary" id="laca-clean-all-btn"
                    data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE)); ?>">
                    🧹 Dọn tất cả 1 lần
                </button>
                <button class="button" id="laca-analyze-btn"
                    data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE)); ?>"
                    style="margin-left:8px">
                    📊 Phân tích ngay
                </button>
            </p>
            <p id="laca-clean-msg" style="display:none;margin-top:10px;font-weight:600;color:#155724;background:#d4edda;padding:10px 14px;border-radius:4px;border-left:4px solid #28a745"></p>
        </div>

        <script>
        (function() {
            const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

            function doRequest(action, key, nonce) {
                return fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=' + encodeURIComponent(action) + '&key=' + encodeURIComponent(key || '') + '&_nonce=' + encodeURIComponent(nonce)
                }).then(r => r.json());
            }

            function showMsg(text, isError) {
                const el = document.getElementById('laca-clean-msg');
                el.textContent = text;
                el.style.display = 'block';
                el.style.color = isError ? '#721c24' : '#155724';
                el.style.background = isError ? '#f8d7da' : '#d4edda';
                el.style.borderColor = isError ? '#dc3545' : '#28a745';
            }

            function analyze(nonce) {
                document.getElementById('laca-db-analyzer').style.display = '';
                document.getElementById('laca-db-stats').textContent = 'Đang phân tích...';
                doRequest('laca_db_analyze', 'all', nonce).then(res => {
                    if (!res.success) return;
                    const data = res.data || {};
                    let total = 0;
                    document.querySelectorAll('.laca-count-badge').forEach(badge => {
                        const key = badge.dataset.key;
                        const cnt = data[key] || 0;
                        badge.textContent = cnt > 0 ? cnt + ' mục' : '✓ Sạch';
                        badge.style.color = cnt > 0 ? '#d9534f' : '#28a745';
                        badge.style.fontWeight = cnt > 0 ? '700' : '400';
                        total += cnt;
                    });
                    document.getElementById('laca-db-stats').textContent = 'Tổng ' + total + ' mục cần dọn.';
                });
            }

            document.getElementById('laca-analyze-btn').addEventListener('click', function() {
                analyze(this.dataset.nonce);
            });

            document.querySelectorAll('.laca-clean-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const key   = this.dataset.key;
                    const nonce = this.dataset.nonce;
                    const orig  = this.textContent;
                    this.disabled = true;
                    this.textContent = 'Đang dọn...';
                    doRequest('laca_db_clean', key, nonce).then(res => {
                        this.disabled = false;
                        this.textContent = orig;
                        if (res.success) {
                            showMsg('✓ ' + res.data.message);
                            const badge = document.querySelector('.laca-count-badge[data-key="' + key + '"]');
                            if (badge) {
                                badge.textContent = '✓ Sạch';
                                badge.style.color = '#28a745';
                                badge.style.fontWeight = '400';
                            }
                        } else {
                            showMsg(res.data || 'Lỗi.', true);
                        }
                    });
                });
            });

            document.getElementById('laca-clean-all-btn').addEventListener('click', function() {
                if (!confirm('Dọn tất cả mục rác? Thao tác không hoàn tác được.')) return;
                const nonce = this.dataset.nonce;
                this.disabled = true;
                this.textContent = '🧹 Đang dọn...';
                doRequest('laca_db_clean', 'all', nonce).then(res => {
                    this.disabled = false;
                    this.textContent = '🧹 Dọn tất cả 1 lần';
                    if (res.success) {
                        showMsg('✓ ' + res.data.message);
                        analyze(nonce);
                    } else {
                        showMsg(res.data || 'Lỗi.', true);
                    }
                });
            });

            analyze('<?php echo esc_js(wp_create_nonce(self::NONCE)); ?>');
        })();
        </script>
        <?php
    }

    public function handleAnalyze(): void
    {
        if (!current_user_can(self::CAP) || !check_ajax_referer(self::NONCE, '_nonce', false)) {
            wp_send_json_error('Không có quyền.', 403);
        }

        global $wpdb;

        $revParents = $wpdb->get_col("SELECT post_parent FROM {$wpdb->posts} WHERE post_type='revision'");
        $revsToDelete = 0;
        if ($revParents) {
            $counts = array_count_values($revParents);
            foreach ($counts as $count) {
                if ($count > 3) {
                    $revsToDelete += ($count - 3);
                }
            }
        }

        wp_send_json_success([
            'revisions'     => $revsToDelete,
            'autodrafts'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='auto-draft'"),
            'trashed_posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='trash'"),
            'orphan_meta'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL"),
            'transients'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%' AND option_name IN (SELECT REPLACE(option_name,'_transient_timeout_','_transient_') FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP())"),
            'spam_comments' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')"),
        ]);
    }

    public function handleClean(): void
    {
        if (!current_user_can(self::CAP) || !check_ajax_referer(self::NONCE, '_nonce', false)) {
            wp_send_json_error('Không có quyền.', 403);
        }

        $key = sanitize_key($_POST['key'] ?? '');
        $all = ($key === 'all');

        $total = 0;

        if ($all || $key === 'revisions')      $total += $this->cleanRevisions();
        if ($all || $key === 'autodrafts')     $total += $this->cleanAutoDrafts();
        if ($all || $key === 'trashed_posts')  $total += $this->cleanTrashedPosts();
        if ($all || $key === 'orphan_meta')    $total += $this->cleanOrphanMeta();
        if ($all || $key === 'transients')     $total += $this->cleanTransients();
        if ($all || $key === 'spam_comments')  $total += $this->cleanSpamComments();

        $label = $all ? 'Đã dọn tất cả' : 'Đã dọn';
        wp_send_json_success(['message' => "{$label} {$total} mục thành công.", 'count' => $total]);
    }

    private function cleanRevisions(): int
    {
        global $wpdb;
        $revisions = $wpdb->get_results(
            "SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type='revision' ORDER BY post_modified DESC"
        );

        $keepPerParent = [];
        $toDelete      = [];

        foreach ($revisions as $rev) {
            $parent = $rev->post_parent;
            $keepPerParent[$parent] = $keepPerParent[$parent] ?? 0;
            if ($keepPerParent[$parent] < 3) {
                $keepPerParent[$parent]++;
            } else {
                $toDelete[] = $rev->ID;
            }
        }

        foreach ($toDelete as $id) {
            wp_delete_post_revision($id);
        }

        return count($toDelete);
    }

    private function cleanAutoDrafts(): int
    {
        global $wpdb;
        $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status='auto-draft'");
        foreach ($ids as $id) {
            wp_delete_post((int) $id, true);
        }
        return count($ids);
    }

    private function cleanTrashedPosts(): int
    {
        global $wpdb;
        $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status='trash'");
        foreach ($ids as $id) {
            wp_delete_post((int) $id, true);
        }
        return count($ids);
    }

    private function cleanOrphanMeta(): int
    {
        global $wpdb;
        return (int) $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL"
        );
    }

    private function cleanTransients(): int
    {
        global $wpdb;
        $keys = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"
        );
        $count = 0;
        foreach ($keys as $timeoutKey) {
            $name = str_replace('_transient_timeout_', '', $timeoutKey);
            delete_transient($name);
            $count++;
        }
        return $count;
    }

    private function cleanSpamComments(): int
    {
        global $wpdb;
        $ids = $wpdb->get_col(
            "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')"
        );
        foreach ($ids as $id) {
            wp_delete_comment((int) $id, true);
        }
        return count($ids);
    }
}

