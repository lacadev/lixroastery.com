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

    /** Option key cho cấu hình dọn dẹp tự động */
    const SCHEDULE_OPTION = 'laca_db_cleaner_schedule';

    /** Option key cho kết quả lần chạy gần nhất */
    const LAST_RUN_OPTION = 'laca_db_cleaner_last_run';

    /** Cron hook name */
    const CRON_HOOK = 'laca_db_cleaner_scheduled';

    /** Batch size cho xoá hàng loạt */
    const BATCH_SIZE = 500;

    /** Lock transient để chống chạy chồng */
    const LOCK_KEY = 'laca_db_cleaner_running';

    public function register(): void
    {
        add_action('admin_menu',                [$this, 'registerMenu'], 20);
        add_action('wp_ajax_laca_db_clean',     [$this, 'handleClean']);
        add_action('wp_ajax_laca_db_analyze',   [$this, 'handleAnalyze']);
        add_action('wp_ajax_laca_db_schedule',  [$this, 'handleScheduleSave']);

        // Cron handler
        add_action(self::CRON_HOOK, [$this, 'runScheduledClean']);

        // Huỷ cron khi đổi theme
        add_action('switch_theme', [__CLASS__, 'unscheduleCron']);

        // Đăng ký / cập nhật lịch cron theo option hiện tại
        $this->ensureCronSchedule();
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Dọn dẹp Database', 'laca'),
            __('Dọn dẹp DB', 'laca'),
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }

        $nonce     = wp_create_nonce(self::NONCE);
        $sched     = self::getScheduleConfig();
        $schedOn   = !empty($sched['enabled']);
        $schedFreq = ($sched['frequency'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly';
        $keepRevs  = max(1, min(50, (int) ($sched['keep_revisions'] ?? 5)));
        $schedItems = is_array($sched['items'] ?? null) ? $sched['items'] : [];

        $lastRun   = get_option(self::LAST_RUN_OPTION, []);
        $nextRunTs = $schedOn ? wp_next_scheduled(self::CRON_HOOK) : false;

        $items = [
            ['key' => 'revisions',     'label' => __('📝 Post Revisions cũ', 'laca'),      'desc' => __('Giữ lại 3 bản mới nhất mỗi bài', 'laca')],
            ['key' => 'autodrafts',    'label' => __('🗒 Auto-drafts', 'laca'),             'desc' => __('Bản nháp tự động chưa lưu', 'laca')],
            ['key' => 'trashed_posts', 'label' => __('🗑 Bài trong Thùng rác', 'laca'),    'desc' => __('Posts/Pages đã xoá chờ dọn', 'laca')],
            ['key' => 'orphan_meta',   'label' => __('🔗 Orphaned post meta', 'laca'),      'desc' => __('Meta không gắn với post nào', 'laca')],
            ['key' => 'transients',    'label' => __('⏰ Expired transients', 'laca'),      'desc' => __('Cache tạm thời đã hết hạn', 'laca')],
            ['key' => 'spam_comments', 'label' => __('💬 Spam & trashed comments', 'laca'), 'desc' => __('Bình luận spam và trong thùng rác', 'laca')],
        ];

        $i18n = [
            'analyzing'    => __('Đang phân tích…', 'laca'),
            'cleaning'     => __('Đang dọn…', 'laca'),
            'cleanBtn'     => __('Dọn dẹp', 'laca'),
            'cleanAllBtn'  => __('🧹 Dọn tất cả 1 lần', 'laca'),
            'runNowBtn'    => __('Chạy ngay', 'laca'),
            'saving'       => __('Đang lưu…', 'laca'),
            'saveBtn'      => __('Lưu', 'laca'),
            'cleanOk'      => __('✓ Sạch', 'laca'),
            /* translators: %d: số mục cần dọn */
            'itemCountTpl' => __('%d mục', 'laca'),
            /* translators: %d: tổng số mục cần dọn */
            'totalCountTpl'=> __('Tổng %d mục cần dọn.', 'laca'),
            /* translators: %s: thời gian chạy kế tiếp */
            'nextRunTpl'   => __('Lần chạy tới: %s', 'laca'),
            'confirmAll'   => __('Dọn tất cả mục rác? Thao tác không hoàn tác được.', 'laca'),
            'errorGeneric' => __('Đã xảy ra lỗi.', 'laca'),
        ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Dọn dẹp Database', 'laca'); ?></h1>
            <p class="laca-page-intro"><?php echo wp_kses_post(__('Xoá bớt dữ liệu rác (bản nháp, thùng rác, bình luận spam, bản chỉnh sửa cũ...) tích tụ theo thời gian để website chạy nhanh hơn. Đây là thao tác dọn dẹp và <strong>không thể hoàn tác</strong> — hãy đọc kỹ từng mục trước khi bấm "Dọn dẹp". Nên backup trước khi dọn.', 'laca')); ?></p>

            <div class="laca-settings-stack">
                <!-- Card 1: Dọn tự động (TRÊN danh sách dọn tay) -->
                <div class="laca-card">
                    <div class="laca-card__header">
                        <h2 class="laca-card__header-title"><?php echo esc_html__('Dọn tự động', 'laca'); ?></h2>
                        <p class="laca-card__header-description"><?php echo esc_html__('Tự động dọn các hạng mục đã chọn theo lịch định kỳ qua WP-Cron.', 'laca'); ?></p>
                    </div>
                    <div class="laca-card__body">
                        <div class="laca-field-row">
                            <div class="laca-field-row__label">
                                <label for="laca-db-sched-enabled"><?php echo esc_html__('Trạng thái lịch tự động', 'laca'); ?></label>
                            </div>
                            <div class="laca-field-row__control">
                                <label class="laca-switch">
                                    <input type="checkbox" id="laca-db-sched-enabled" class="laca-switch__input" <?php checked($schedOn); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><?php echo esc_html__('Bật dọn tự động', 'laca'); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="laca-field-row">
                            <div class="laca-field-row__label">
                                <label><?php echo esc_html__('Tần suất chạy', 'laca'); ?></label>
                            </div>
                            <div class="laca-field-row__control">
                                <div class="laca-segmented" id="laca-db-sched-freq" role="tablist">
                                    <button type="button"
                                        class="laca-segmented__item <?php echo $schedFreq === 'daily' ? 'is-active' : ''; ?>"
                                        data-freq="daily"
                                        aria-selected="<?php echo $schedFreq === 'daily' ? 'true' : 'false'; ?>">
                                        <?php echo esc_html__('Hằng ngày', 'laca'); ?>
                                    </button>
                                    <button type="button"
                                        class="laca-segmented__item <?php echo $schedFreq === 'weekly' ? 'is-active' : ''; ?>"
                                        data-freq="weekly"
                                        aria-selected="<?php echo $schedFreq === 'weekly' ? 'true' : 'false'; ?>">
                                        <?php echo esc_html__('Hằng tuần', 'laca'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="laca-field-row">
                            <div class="laca-field-row__label">
                                <label><?php echo esc_html__('Các hạng mục dọn', 'laca'); ?></label>
                            </div>
                            <div class="laca-field-row__control">
                                <div class="laca-checkbox-grid">
                                    <?php foreach ($items as $item): ?>
                                        <label class="laca-checkbox-label">
                                            <input type="checkbox"
                                                class="laca-checkbox js-laca-sched-item"
                                                value="<?php echo esc_attr($item['key']); ?>"
                                                <?php checked(in_array($item['key'], $schedItems, true)); ?>>
                                            <span><?php echo esc_html($item['label']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="laca-field-row">
                            <div class="laca-field-row__label">
                                <label for="laca-db-keep-revisions"><?php echo esc_html__('Giữ lại N revision mới nhất mỗi bài', 'laca'); ?></label>
                            </div>
                            <div class="laca-field-row__control">
                                <input type="number" id="laca-db-keep-revisions" class="laca-input laca-input-number-sm" min="1" max="50" value="<?php echo esc_attr((string) $keepRevs); ?>">
                            </div>
                        </div>

                        <div class="laca-sched-status laca-mt-3">
                            <span class="laca-badge laca-badge--neutral">
                                <?php
                                if (is_array($lastRun) && !empty($lastRun['time'])) {
                                    /* translators: 1: thời gian chạy cuối, 2: số mục đã xoá */
                                    echo esc_html(sprintf(__('Lần chạy cuối: %1$s — đã xoá %2$d mục', 'laca'), (string) $lastRun['time'], (int) ($lastRun['total'] ?? 0)));
                                } else {
                                    echo esc_html__('Chưa chạy lần nào', 'laca');
                                }
                                ?>
                            </span>
                            <span id="laca-db-next-run" class="laca-badge laca-badge--accent" style="<?php echo ($schedOn && $nextRunTs) ? '' : 'display:none;'; ?>">
                                <?php
                                if ($schedOn && $nextRunTs) {
                                    /* translators: %s: thời gian chạy kế tiếp */
                                    echo esc_html(sprintf(__('Lần chạy tới: %s', 'laca'), wp_date('H:i d/m/Y', (int) $nextRunTs)));
                                }
                                ?>
                            </span>
                        </div>

                        <div id="laca-db-sched-alert" class="laca-alert-slot"></div>
                    </div>
                    <div class="laca-card__footer">
                        <button type="button" id="laca-db-sched-run-now-btn" class="laca-btn laca-btn--outline laca-btn--sm" data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php echo esc_html__('Chạy ngay', 'laca'); ?>
                        </button>
                        <button type="button" id="laca-db-sched-save-btn" class="laca-btn laca-btn--solid laca-btn--sm" data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php echo esc_html__('Lưu', 'laca'); ?>
                        </button>
                    </div>
                </div>

                <!-- Card 2: Dọn dẹp thủ công -->
                <div class="laca-card">
                    <div class="laca-card__header laca-card__header--flex">
                        <div>
                            <h2 class="laca-card__header-title"><?php echo esc_html__('Dọn dẹp thủ công', 'laca'); ?></h2>
                            <p id="laca-db-analyzer" class="laca-card__header-description" style="display:none; /* động */">
                                <strong><?php echo esc_html__('📊 Phân tích hiện tại:', 'laca'); ?></strong>
                                <span id="laca-db-stats" class="laca-db-stats"><?php echo esc_html__('Đang tải…', 'laca'); ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="laca-card__body">
                        <div class="laca-table-wrap">
                            <table class="laca-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Loại dữ liệu rác', 'laca'); ?></th>
                                        <th class="laca-col-count"><?php echo esc_html__('Số lượng ước tính', 'laca'); ?></th>
                                        <th class="laca-col-right"><?php echo esc_html__('Thao tác', 'laca'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="laca-db-rows">
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($item['label']); ?></strong>
                                            <br><small class="laca-status-muted"><?php echo esc_html($item['desc']); ?></small>
                                        </td>
                                        <td>
                                            <span class="laca-badge laca-badge--neutral laca-count-badge" data-key="<?php echo esc_attr($item['key']); ?>">—</span>
                                        </td>
                                        <td class="laca-col-right">
                                            <button type="button" class="laca-btn laca-btn--outline laca-btn--xs laca-clean-btn"
                                                data-key="<?php echo esc_attr($item['key']); ?>"
                                                data-nonce="<?php echo esc_attr($nonce); ?>">
                                                <?php echo esc_html__('Dọn dẹp', 'laca'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="laca-clean-msg" class="laca-alert-slot"></div>
                    </div>
                    <div class="laca-card__footer">
                        <button type="button" class="laca-btn laca-btn--outline laca-btn--sm" id="laca-analyze-btn"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php echo esc_html__('📊 Phân tích ngay', 'laca'); ?>
                        </button>
                        <button type="button" class="laca-btn laca-btn--solid laca-btn--sm" id="laca-clean-all-btn"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php echo esc_html__('🧹 Dọn tất cả 1 lần', 'laca'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const i18n    = <?php echo wp_json_encode($i18n); ?>;

            function doRequest(params) {
                const body = new URLSearchParams(params);
                return fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(r => r.json());
            }

            function showAlert(slotId, text, isError) {
                const slot = document.getElementById(slotId);
                if (!slot) return;
                if (slot._timer) clearTimeout(slot._timer);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'laca-alert laca-mt-2 ' + (isError ? 'laca-alert--danger' : 'laca-alert--success');
                const contentDiv = document.createElement('div');
                contentDiv.className = 'laca-alert__content';
                const p = document.createElement('p');
                p.className = 'laca-alert__description';
                p.textContent = text;
                contentDiv.appendChild(p);
                alertDiv.appendChild(contentDiv);
                slot.innerHTML = '';
                slot.appendChild(alertDiv);
                if (!isError) {
                    slot._timer = setTimeout(() => { slot.innerHTML = ''; }, 4000);
                }
            }

            function analyze(nonce) {
                const analyzer = document.getElementById('laca-db-analyzer');
                const stats    = document.getElementById('laca-db-stats');
                if (analyzer) analyzer.style.display = '';
                if (stats) stats.textContent = i18n.analyzing;

                doRequest({ action: 'laca_db_analyze', key: 'all', _nonce: nonce }).then(res => {
                    if (!res.success) return;
                    const data = res.data || {};
                    let total = 0;
                    document.querySelectorAll('.laca-count-badge').forEach(badge => {
                        const key = badge.dataset.key;
                        const cnt = data[key] || 0;
                        badge.textContent = cnt > 0 ? i18n.itemCountTpl.replace('%d', cnt) : i18n.cleanOk;
                        badge.className = 'laca-badge laca-count-badge ' + (cnt > 0 ? 'laca-badge--warning' : 'laca-badge--success');
                        total += cnt;
                    });
                    if (stats) stats.textContent = i18n.totalCountTpl.replace('%d', total);
                });
            }

            const analyzeBtn = document.getElementById('laca-analyze-btn');
            if (analyzeBtn) {
                analyzeBtn.addEventListener('click', function() {
                    analyze(this.dataset.nonce);
                });
            }

            document.querySelectorAll('.laca-clean-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const key   = this.dataset.key;
                    const nonce = this.dataset.nonce;
                    this.disabled = true;
                    this.textContent = i18n.cleaning;
                    doRequest({ action: 'laca_db_clean', key: key, _nonce: nonce }).then(res => {
                        this.disabled = false;
                        this.textContent = i18n.cleanBtn;
                        if (res.success && res.data) {
                            showAlert('laca-clean-msg', res.data.message, false);
                            const badge = document.querySelector('.laca-count-badge[data-key="' + key + '"]');
                            if (badge) {
                                badge.textContent = i18n.cleanOk;
                                badge.className = 'laca-badge laca-count-badge laca-badge--success';
                            }
                        } else {
                            showAlert('laca-clean-msg', res.data || i18n.errorGeneric, true);
                        }
                    }).catch(() => {
                        this.disabled = false;
                        this.textContent = i18n.cleanBtn;
                        showAlert('laca-clean-msg', i18n.errorGeneric, true);
                    });
                });
            });

            function runCleanAll(triggerBtn, restoreLabel, alertSlotId) {
                if (!confirm(i18n.confirmAll)) return;
                const nonce = triggerBtn.dataset.nonce;
                triggerBtn.disabled = true;
                triggerBtn.textContent = i18n.cleaning;
                doRequest({ action: 'laca_db_clean', key: 'all', _nonce: nonce }).then(res => {
                    triggerBtn.disabled = false;
                    triggerBtn.textContent = restoreLabel;
                    if (res.success && res.data) {
                        showAlert(alertSlotId, res.data.message, false);
                        analyze(nonce);
                    } else {
                        showAlert(alertSlotId, res.data || i18n.errorGeneric, true);
                    }
                }).catch(() => {
                    triggerBtn.disabled = false;
                    triggerBtn.textContent = restoreLabel;
                    showAlert(alertSlotId, i18n.errorGeneric, true);
                });
            }

            const cleanAllBtn = document.getElementById('laca-clean-all-btn');
            if (cleanAllBtn) {
                cleanAllBtn.addEventListener('click', function() {
                    runCleanAll(this, i18n.cleanAllBtn, 'laca-clean-msg');
                });
            }

            const runNowBtn = document.getElementById('laca-db-sched-run-now-btn');
            if (runNowBtn) {
                runNowBtn.addEventListener('click', function() {
                    runCleanAll(this, i18n.runNowBtn, 'laca-db-sched-alert');
                });
            }

            // Segmented frequency control
            let currentFreq = <?php echo wp_json_encode($schedFreq); ?>;
            document.querySelectorAll('#laca-db-sched-freq .laca-segmented__item').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentFreq = this.dataset.freq;
                    document.querySelectorAll('#laca-db-sched-freq .laca-segmented__item').forEach(b => {
                        const active = b.dataset.freq === currentFreq;
                        b.classList.toggle('is-active', active);
                        b.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                });
            });

            // Save schedule
            const saveSchedBtn = document.getElementById('laca-db-sched-save-btn');
            if (saveSchedBtn) {
                saveSchedBtn.addEventListener('click', function() {
                    const nonce   = this.dataset.nonce;
                    const enabled = document.getElementById('laca-db-sched-enabled').checked ? 'yes' : 'no';
                    const keepRev = document.getElementById('laca-db-keep-revisions').value;
                    const body    = new URLSearchParams();
                    body.append('action', 'laca_db_schedule');
                    body.append('_nonce', nonce);
                    body.append('enabled', enabled);
                    body.append('frequency', currentFreq);
                    body.append('keep_revisions', keepRev);
                    document.querySelectorAll('.js-laca-sched-item:checked').forEach(cb => {
                        body.append('items[]', cb.value);
                    });

                    this.disabled = true;
                    this.textContent = i18n.saving;
                    fetch(ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString()
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.disabled = false;
                        this.textContent = i18n.saveBtn;
                        if (res.success && res.data) {
                            showAlert('laca-db-sched-alert', res.data.message, false);
                            const nextEl = document.getElementById('laca-db-next-run');
                            if (nextEl) {
                                if (res.data.next_run) {
                                    nextEl.textContent = i18n.nextRunTpl.replace('%s', res.data.next_run);
                                    nextEl.style.display = '';
                                } else {
                                    nextEl.style.display = 'none';
                                }
                            }
                        } else {
                            showAlert('laca-db-sched-alert', res.data || i18n.errorGeneric, true);
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.textContent = i18n.saveBtn;
                        showAlert('laca-db-sched-alert', i18n.errorGeneric, true);
                    });
                });
            }

            analyze(<?php echo wp_json_encode($nonce); ?>);
        })();
        </script>
        <?php
    }

    public function handleAnalyze(): void
    {
        if (!current_user_can(self::CAP) || !check_ajax_referer(self::NONCE, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
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
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
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

        $msg = $all
            ? sprintf(
                /* translators: %d: number of deleted items */
                esc_html__('Đã dọn tất cả %d mục thành công.', 'laca'),
                $total
            )
            : sprintf(
                /* translators: %d: number of deleted items */
                esc_html__('Đã dọn %d mục thành công.', 'laca'),
                $total
            );
        wp_send_json_success(['message' => $msg, 'count' => $total]);
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

    // ═══════════════════════════════════════════════════════════
    //  SCHEDULED CLEANUP (WP-Cron)
    // ═══════════════════════════════════════════════════════════

    /**
     * Lấy cấu hình dọn dẹp tự động.
     *
     * @return array {enabled: bool, frequency: 'daily'|'weekly', keep_revisions: int, items: string[]}
     */
    public static function getScheduleConfig(): array
    {
        $defaults = [
            'enabled'        => false,
            'frequency'      => 'weekly',
            'keep_revisions' => 5,
            'items'          => ['revisions', 'autodrafts', 'trashed_posts', 'spam_comments', 'transients', 'orphan_meta'],
        ];

        $saved = get_option(self::SCHEDULE_OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $defaults);
    }

    /**
     * Đảm bảo cron event đồng bộ với option.
     */
    private function ensureCronSchedule(): void
    {
        $config = self::getScheduleConfig();
        $nextRun = wp_next_scheduled(self::CRON_HOOK);

        if (!$config['enabled']) {
            if ($nextRun) {
                wp_unschedule_event($nextRun, self::CRON_HOOK);
            }
            return;
        }

        $recurrence = $config['frequency'] === 'daily' ? 'daily' : 'weekly';

        // Nếu đã có nhưng tần suất đổi → xoá rồi đặt lại
        if ($nextRun) {
            $currentSchedule = wp_get_schedule(self::CRON_HOOK);
            if ($currentSchedule !== $recurrence) {
                wp_unschedule_event($nextRun, self::CRON_HOOK);
                $nextRun = false;
            }
        }

        if (!$nextRun) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, $recurrence, self::CRON_HOOK);
        }
    }

    /**
     * Huỷ cron khi đổi theme.
     */
    public static function unscheduleCron(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    /**
     * Chạy dọn dẹp theo lịch — gọi bởi WP-Cron.
     */
    public function runScheduledClean(): void
    {
        // Lock chống chạy chồng (TTL 10 phút)
        if (get_transient(self::LOCK_KEY)) {
            return;
        }
        set_transient(self::LOCK_KEY, true, 10 * MINUTE_IN_SECONDS);

        $config = self::getScheduleConfig();
        if (!$config['enabled']) {
            delete_transient(self::LOCK_KEY);
            return;
        }

        $results = [];
        $totalDeleted = 0;

        foreach ($config['items'] as $key) {
            $count = $this->cleanItemBatched($key, $config);
            $results[$key] = $count;
            $totalDeleted += $count;
        }

        // Ghi kết quả
        update_option(self::LAST_RUN_OPTION, [
            'time'    => current_time('mysql'),
            'results' => $results,
            'total'   => $totalDeleted,
        ], false); // autoload = false

        delete_transient(self::LOCK_KEY);
    }

    /**
     * Dọn theo batch (500 mục/batch, nghỉ 100ms giữa các batch).
     */
    private function cleanItemBatched(string $key, array $config): int
    {
        $total = 0;

        switch ($key) {
            case 'revisions':
                $keepN = max(1, (int) ($config['keep_revisions'] ?? 5));
                $total = $this->cleanRevisionsBatched($keepN);
                break;
            case 'autodrafts':
                $total = $this->cleanAutoDraftsBatched();
                break;
            case 'trashed_posts':
                $total = $this->cleanTrashedPostsBatched();
                break;
            case 'orphan_meta':
                $total = $this->cleanOrphanMetaBatched();
                break;
            case 'transients':
                $total = $this->cleanTransients(); // transient API dọn nhanh
                break;
            case 'spam_comments':
                $total = $this->cleanSpamCommentsBatched();
                break;
        }

        return $total;
    }

    private function cleanRevisionsBatched(int $keepN = 5): int
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
            if ($keepPerParent[$parent] < $keepN) {
                $keepPerParent[$parent]++;
            } else {
                $toDelete[] = $rev->ID;
            }
        }

        $deleted = 0;
        foreach (array_chunk($toDelete, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $id) {
                wp_delete_post_revision($id);
                $deleted++;
            }
            usleep(100000); // 100ms
        }

        return $deleted;
    }

    private function cleanAutoDraftsBatched(): int
    {
        global $wpdb;
        $deleted = 0;

        do {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_status='auto-draft' AND post_date < %s LIMIT %d",
                    gmdate('Y-m-d H:i:s', strtotime('-7 days')),
                    self::BATCH_SIZE
                )
            );
            foreach ($ids as $id) {
                wp_delete_post((int) $id, true);
                $deleted++;
            }
            if (count($ids) === self::BATCH_SIZE) {
                usleep(100000);
            }
        } while (count($ids) === self::BATCH_SIZE);

        return $deleted;
    }

    private function cleanTrashedPostsBatched(): int
    {
        global $wpdb;
        $deleted = 0;

        do {
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_status='trash' AND post_modified < %s LIMIT %d",
                    gmdate('Y-m-d H:i:s', strtotime('-30 days')),
                    self::BATCH_SIZE
                )
            );
            foreach ($ids as $id) {
                wp_delete_post((int) $id, true);
                $deleted++;
            }
            if (count($ids) === self::BATCH_SIZE) {
                usleep(100000);
            }
        } while (count($ids) === self::BATCH_SIZE);

        return $deleted;
    }

    private function cleanOrphanMetaBatched(): int
    {
        global $wpdb;
        $total = 0;

        do {
            $count = (int) $wpdb->query(
                "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL LIMIT " . self::BATCH_SIZE
            );
            $total += $count;
            if ($count === self::BATCH_SIZE) {
                usleep(100000);
            }
        } while ($count === self::BATCH_SIZE);

        return $total;
    }

    private function cleanSpamCommentsBatched(): int
    {
        global $wpdb;
        $deleted = 0;

        do {
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash') LIMIT %d",
                    self::BATCH_SIZE
                )
            );
            foreach ($ids as $id) {
                wp_delete_comment((int) $id, true);
                $deleted++;
            }
            if (count($ids) === self::BATCH_SIZE) {
                usleep(100000);
            }
        } while (count($ids) === self::BATCH_SIZE);

        return $deleted;
    }

    /**
     * AJAX: Lưu cấu hình dọn dẹp tự động.
     */
    public function handleScheduleSave(): void
    {
        if (!current_user_can(self::CAP) || !check_ajax_referer(self::NONCE, '_nonce', false)) {
            wp_send_json_error(esc_html__('Không có quyền.', 'laca'), 403);
        }

        $enabled  = sanitize_key($_POST['enabled'] ?? 'no') === 'yes';
        $freq     = in_array($_POST['frequency'] ?? '', ['daily', 'weekly'], true) ? $_POST['frequency'] : 'weekly';
        $keepRevs = max(1, min(50, (int) ($_POST['keep_revisions'] ?? 5)));

        $allItems = ['revisions', 'autodrafts', 'trashed_posts', 'spam_comments', 'transients', 'orphan_meta'];
        $items    = [];
        foreach ((array) ($_POST['items'] ?? []) as $item) {
            $item = sanitize_key($item);
            if (in_array($item, $allItems, true)) {
                $items[] = $item;
            }
        }

        update_option(self::SCHEDULE_OPTION, [
            'enabled'        => $enabled,
            'frequency'      => $freq,
            'keep_revisions' => $keepRevs,
            'items'          => $items ?: $allItems,
        ], false);

        // Re-sync cron
        $this->ensureCronSchedule();
        $nextTs = $enabled ? wp_next_scheduled(self::CRON_HOOK) : false;

        wp_send_json_success([
            'message'  => $enabled
                ? sprintf(
                    /* translators: %s: frequency label */
                    esc_html__('Đã bật dọn dẹp tự động (%s).', 'laca'),
                    $freq === 'daily' ? esc_html__('hàng ngày', 'laca') : esc_html__('hàng tuần', 'laca')
                )
                : esc_html__('Đã tắt dọn dẹp tự động.', 'laca'),
            'next_run' => $nextTs ? wp_date('H:i d/m/Y', (int) $nextTs) : '',
        ]);
    }
}

