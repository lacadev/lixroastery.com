<?php

namespace App\Settings\Security\TrafficMonitor;

use App\Helpers\ClientIp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trang quản trị Giám sát truy cập (`admin.php?page=laca-traffic`) + các admin-post handlers + xuất CSV.
 */
final class TrafficAdminPage
{
    public const MENU_SLUG   = 'laca-traffic';
    public const PARENT_SLUG = 'laca-admin';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Admin-post handlers (cần đăng ký ở mọi request admin-post.php)
        add_action('admin_post_laca_tm_block_ip', [$this, 'handleBlockIp']);
        add_action('admin_post_laca_tm_block_selected', [$this, 'handleBlockSelected']);
        add_action('admin_post_laca_tm_block_url_page', [$this, 'handleBlockUrlPage']);
        add_action('admin_post_laca_tm_unblock_ip', [$this, 'handleUnblockIp']);
        add_action('admin_post_laca_tm_unblock_selected', [$this, 'handleUnblockSelected']);
        add_action('admin_post_laca_tm_toggle_trusted', [$this, 'handleToggleTrusted']);
        add_action('admin_post_laca_tm_import_lbm', [$this, 'handleImportPlugin']);
        add_action('admin_post_laca_tm_save_firewall', [$this, 'handleSaveFirewall']);
        add_action('admin_post_laca_tm_save_settings', [$this, 'handleSaveSettings']);
        add_action('admin_post_laca_tm_export_csv', [$this, 'handleExportCsv']);
        add_action('admin_post_laca_tm_purge', [$this, 'handlePurge']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Giám sát truy cập', 'laca'),
            __('Giám sát truy cập', 'laca'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, self::MENU_SLUG)) {
            return;
        }

        wp_enqueue_script('jquery');

        $inlineJs = <<<'JS'
(function(){
    document.addEventListener('click', function(e){
        var btn = e.target.closest ? e.target.closest('button[data-laca-confirm], a[data-laca-confirm]') : null;
        if (!btn) return;
        var msg = btn.getAttribute('data-laca-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    document.addEventListener('submit', function(e){
        var form = e.target;
        if (!form || !form.getAttribute) return;
        var msg = form.getAttribute('data-laca-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    function syncCheckGroup(group) {
        if (!group) return;
        var boxes = document.querySelectorAll('input[type="checkbox"][data-laca-check-group="' + group + '"]');
        var checked = 0;
        boxes.forEach(function(cb){ if (cb.checked) checked++; });
        document.querySelectorAll('[data-laca-check-count="' + group + '"]').forEach(function(el){
            el.textContent = String(checked);
        });
        document.querySelectorAll('[data-laca-check-submit="' + group + '"]').forEach(function(btn){
            btn.disabled = checked === 0;
        });
    }

    document.addEventListener('change', function(e){
        var target = e.target;
        if (!target || !target.getAttribute) return;
        var allGroup = target.getAttribute('data-laca-check-all');
        if (allGroup) {
            var checked = !!target.checked;
            document.querySelectorAll('input[type="checkbox"][data-laca-check-group="' + allGroup + '"]').forEach(function(cb){
                cb.checked = checked;
            });
            syncCheckGroup(allGroup);
            return;
        }
        var itemGroup = target.getAttribute('data-laca-check-group');
        if (itemGroup) {
            syncCheckGroup(itemGroup);
        }
    });
})();
JS;

        wp_add_inline_script('jquery', $inlineJs);
    }

    // =========================================================================
    // RENDER PAGE & TABS
    // =========================================================================

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'), 403);
        }

        if (!TrafficTable::isReady()) {
            TrafficTable::install();
        }

        $tabs = [
            'overview' => __('Tổng quan IP', 'laca'),
            'urls'     => __('Top URL', 'laca'),
            'blocked'  => __('IP đã chặn', 'laca'),
            'firewall' => __('Tường lửa request', 'laca'),
            'settings' => __('Cài đặt', 'laca'),
        ];

        $activeTab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
        if (!isset($tabs[$activeTab])) {
            $activeTab = 'overview';
        }

        $msgCode = isset($_GET['laca_tm_msg']) ? sanitize_key(wp_unslash($_GET['laca_tm_msg'])) : '';
        $msgNum  = isset($_GET['laca_tm_n']) ? absint($_GET['laca_tm_n']) : 0;
        $notice  = $msgCode !== '' ? $this->resolveNotice($msgCode, $msgNum) : null;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Giám sát truy cập', 'laca'); ?></h1>
            <p class="laca-page-intro">
                <?php echo esc_html__('Theo dõi lưu lượng truy cập đến PHP WordPress, nhận diện dấu hiệu dò quét, tường lửa đường dẫn/query độc hại và quản lý danh sách chặn IP/CIDR.', 'laca'); ?>
            </p>

            <?php if (TrafficSettings::isPluginActive()): ?>
                <div class="laca-alert laca-alert--warning laca-mb-3">
                    <div class="laca-alert__content">
                        <p class="laca-alert__description">
                            <?php echo esc_html__('Đang dùng plugin Lamvt Security — tắt plugin để dùng tính năng của theme. Ở chế độ này trang chỉ cho phép xem và nhập dữ liệu.', 'laca'); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (TrafficSettings::isKillSwitchActive()): ?>
                <div class="laca-alert laca-alert--warning laca-mb-3">
                    <div class="laca-alert__content">
                        <p class="laca-alert__description">
                            <?php echo esc_html__('Hằng số LACA_TM_DISABLE đang bật trong wp-config.php — toàn bộ bộ ghi nhận, tường lửa request và chặn IP đang tạm dừng.', 'laca'); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            $htaccessErr = get_transient('laca_tm_htaccess_error');
            if (is_string($htaccessErr) && $htaccessErr !== ''):
            ?>
                <div class="laca-alert laca-alert--danger laca-mb-3">
                    <div class="laca-alert__content">
                        <p class="laca-alert__description"><?php echo esc_html($htaccessErr); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($notice !== null): ?>
                <div class="laca-alert laca-alert--<?php echo esc_attr($notice['type']); ?> laca-mb-3">
                    <div class="laca-alert__content">
                        <p class="laca-alert__description"><?php echo esc_html($notice['text']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tabKey => $tabLabel): ?>
                    <a href="<?php echo esc_url($this->tabUrl($tabKey)); ?>"
                       class="nav-tab <?php echo $activeTab === $tabKey ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tabLabel); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="tab-content laca-security-tab-content">
                <?php
                switch ($activeTab) {
                    case 'overview':
                        $detailIp = isset($_GET['ip']) ? trim(sanitize_text_field(wp_unslash($_GET['ip']))) : '';
                        if ($detailIp !== '') {
                            $this->renderIpDetail($detailIp);
                        } else {
                            $this->renderOverviewTab();
                        }
                        break;
                    case 'urls':
                        $detailPath = isset($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';
                        if ($detailPath !== '' && str_starts_with($detailPath, '/') && strlen($detailPath) <= 500) {
                            $this->renderUrlDetail($detailPath);
                        } else {
                            $this->renderUrlsTab();
                        }
                        break;
                    case 'blocked':
                        $this->renderBlockedTab();
                        break;
                    case 'firewall':
                        $this->renderFirewallTab();
                        break;
                    case 'settings':
                        $this->renderSettingsTab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    // ── Tab 1: Overview ──────────────────────────────────────────────────────

    private function renderOverviewTab(): void
    {
        global $wpdb;

        $settings    = TrafficSettings::settings();
        $retention   = max(1, min(90, (int) ($settings['retention_days'] ?? 14)));
        $dayChoices  = $this->dayChoices($retention);
        $days        = isset($_GET['days']) ? absint($_GET['days']) : min(7, $retention);
        if (!in_array($days, $dayChoices, true)) {
            $days = $dayChoices[0];
        }

        $catLabels = TrafficClassifier::categoryLabels();
        $category  = isset($_GET['category']) ? sanitize_key(wp_unslash($_GET['category'])) : '';
        if ($category !== '' && !isset($catLabels[$category])) {
            $category = '';
        }

        $ipSearch = isset($_GET['ip_search'])
            ? substr(trim(sanitize_text_field(wp_unslash($_GET['ip_search']))), 0, 45)
            : '';

        $page    = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $visitsTable = TrafficTable::visitsTable();
        $startDay    = wp_date('Y-m-d', time() - ($days - 1) * DAY_IN_SECONDS);

        $whereSql  = 'WHERE day >= %s';
        $whereArgs = [$startDay];
        if ($ipSearch !== '') {
            $whereSql   .= ' AND ip LIKE %s';
            $whereArgs[] = '%' . $wpdb->esc_like($ipSearch) . '%';
        }

        $catCase = "CASE
            WHEN SUM(category = 'attack') > 0 THEN 'attack'
            WHEN SUM(category = 'watch') > 0 THEN 'watch'
            WHEN SUM(category = 'searchbot') > 0 THEN 'searchbot'
            WHEN SUM(category = 'bot') > 0 THEN 'bot'
            WHEN SUM(category = 'visitor') > 0 THEN 'visitor'
            ELSE 'unknown'
        END";

        $havingSql = '';
        $groupArgs = $whereArgs;
        if ($category !== '') {
            $havingSql   = "HAVING {$catCase} = %s";
            $groupArgs[] = $category;
        }

        $stats     = ['ips' => 0, 'hits' => 0, 'suspicious' => 0];
        $rows      = [];
        $blocklist = IpBlocklist::getBlocklist();

        if (TrafficTable::isReady()) {
            $cacheKey = 'ov_stats_' . md5(wp_json_encode([$days, $category, $ipSearch]));
            $cached   = wp_cache_get($cacheKey, TrafficTable::CACHE_GROUP);

            if (is_array($cached)) {
                $stats = $cached;
            } else {
                $subSql = "SELECT ip, SUM(hits) AS hits, SUM(suspicious) AS suspicious, {$catCase} AS cat
                           FROM {$visitsTable} {$whereSql}
                           GROUP BY ip {$havingSql}";
                $statRow = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT COUNT(*) AS ips, COALESCE(SUM(hits), 0) AS hits, COALESCE(SUM(suspicious), 0) AS suspicious
                         FROM ({$subSql}) AS grouped_visits",
                        $groupArgs
                    )
                );
                $stats = [
                    'ips'        => (int) ($statRow->ips ?? 0),
                    'hits'       => (int) ($statRow->hits ?? 0),
                    'suspicious' => (int) ($statRow->suspicious ?? 0),
                ];
                wp_cache_set($cacheKey, $stats, TrafficTable::CACHE_GROUP, 60);
            }

            $queryArgs = array_merge($groupArgs, [$perPage, $offset]);
            $rows      = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ip,
                            SUM(hits) AS hits,
                            SUM(suspicious) AS suspicious,
                            SUM(not_found) AS not_found,
                            MAX(last_seen) AS last_seen,
                            MAX(ua) AS ua,
                            MAX(reasons) AS reasons,
                            {$catCase} AS category
                     FROM {$visitsTable}
                     {$whereSql}
                     GROUP BY ip
                     {$havingSql}
                     ORDER BY hits DESC, ip ASC
                     LIMIT %d OFFSET %d",
                    $queryArgs
                )
            ) ?: [];
        }

        $totalPages   = max(1, (int) ceil($stats['ips'] / $perPage));
        $pluginActive = TrafficSettings::isPluginActive();
        ?>
        <?php if ((int) ($settings['tracking'] ?? 0) !== 1): ?>
            <div class="laca-alert laca-alert--info laca-mb-3">
                <div class="laca-alert__content">
                    <p class="laca-alert__description">
                        <?php echo esc_html__('Ghi nhận lượt truy cập hiện đang TẮT (mặc định). Vào tab Cài đặt để bật khi cần theo dõi lưu lượng.', 'laca'); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- 4 ô thống kê đầu trang -->
        <div class="laca-mp-grid laca-mb-3">
            <div class="laca-card">
                <div class="laca-card__body">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php echo esc_html__('Tổng IP duy nhất', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html(number_format_i18n($stats['ips'])); ?></span>
                    </div>
                </div>
            </div>
            <div class="laca-card">
                <div class="laca-card__body">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php echo esc_html__('Tổng lượt truy cập', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html(number_format_i18n($stats['hits'])); ?></span>
                    </div>
                </div>
            </div>
            <div class="laca-card">
                <div class="laca-card__body">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php echo esc_html__('Lượt nghi ngờ', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html(number_format_i18n($stats['suspicious'])); ?></span>
                    </div>
                </div>
            </div>
            <div class="laca-card">
                <div class="laca-card__body">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php echo esc_html__('Quy tắc đang chặn', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html(number_format_i18n(count($blocklist))); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc -->
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="laca-log-filter-bar laca-mb-3">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
            <input type="hidden" name="tab" value="overview">

            <label class="screen-reader-text" for="laca-tm-days"><?php echo esc_html__('Khoảng thời gian', 'laca'); ?></label>
            <select id="laca-tm-days" name="days" class="laca-select">
                <?php foreach ($dayChoices as $d): ?>
                    <option value="<?php echo esc_attr((string) $d); ?>" <?php selected($days, $d); ?>>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: số ngày lọc thống kê */
                                _n('%d ngày gần nhất', '%d ngày gần nhất', $d, 'laca'),
                                $d
                            )
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="screen-reader-text" for="laca-tm-cat"><?php echo esc_html__('Phân loại', 'laca'); ?></label>
            <select id="laca-tm-cat" name="category" class="laca-select">
                <option value=""><?php echo esc_html__('Mọi phân loại', 'laca'); ?></option>
                <?php foreach ($catLabels as $catKey => $catName): ?>
                    <option value="<?php echo esc_attr($catKey); ?>" <?php selected($category, $catKey); ?>>
                        <?php echo esc_html($catName); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="screen-reader-text" for="laca-tm-ip-search"><?php echo esc_html__('Tìm địa chỉ IP', 'laca'); ?></label>
            <input type="search"
                   id="laca-tm-ip-search"
                   name="ip_search"
                   class="laca-input"
                   value="<?php echo esc_attr($ipSearch); ?>"
                   placeholder="<?php echo esc_attr__('Tìm theo địa chỉ IP…', 'laca'); ?>">

            <button type="submit" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lọc', 'laca'); ?></button>
        </form>

        <!-- Form chặn hàng loạt các IP đã chọn -->
        <?php if (!$pluginActive): ?>
            <form id="laca-tm-bulk-block-form"
                  method="post"
                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                  class="laca-log-filter-bar laca-mb-3"
                  data-laca-confirm="<?php echo esc_attr__('Chặn các địa chỉ IP đã chọn theo dải đã chọn?', 'laca'); ?>">
                <input type="hidden" name="action" value="laca_tm_block_selected">
                <input type="hidden" name="return_url" value="<?php echo esc_attr($this->currentAdminUrl()); ?>">
                <?php wp_nonce_field('laca_tm_block_selected'); ?>

                <span>
                    <?php echo esc_html__('Đã chọn:', 'laca'); ?>
                    <strong data-laca-check-count="overview-ips">0</strong>
                </span>

                <label class="screen-reader-text" for="laca-tm-bulk-prefix"><?php echo esc_html__('Độ dài prefix chặn', 'laca'); ?></label>
                <select id="laca-tm-bulk-prefix" name="prefix" class="laca-select">
                    <option value="32"><?php echo esc_html__('IP đơn (/32 · IPv6 /128)', 'laca'); ?></option>
                    <option value="24"><?php echo esc_html__('Dải IPv4 /24 (256 IP)', 'laca'); ?></option>
                    <option value="16"><?php echo esc_html__('Dải IPv4 /16 (65.536 IP)', 'laca'); ?></option>
                    <option value="8"><?php echo esc_html__('Dải IPv4 /8 (16.777.216 IP)', 'laca'); ?></option>
                    <option value="64"><?php echo esc_html__('Dải IPv6 /64', 'laca'); ?></option>
                </select>

                <label class="laca-check-group__item">
                    <input type="checkbox" class="laca-checkbox" name="confirm_wide" value="1">
                    <span><?php echo esc_html__('Tôi hiểu dải này rất rộng (bắt buộc với /8)', 'laca'); ?></span>
                </label>

                <button type="submit"
                        class="laca-btn laca-btn--danger laca-btn--sm"
                        data-laca-check-submit="overview-ips"
                        disabled>
                    <?php echo esc_html__('Chặn IP đã chọn', 'laca'); ?>
                </button>
            </form>
        <?php endif; ?>

        <!-- Bảng visits gộp theo IP -->
        <div class="laca-table-wrap">
            <table class="laca-table">
                <thead>
                    <tr>
                        <th>
                            <?php if (!$pluginActive): ?>
                                <input type="checkbox"
                                       class="laca-checkbox"
                                       data-laca-check-all="overview-ips"
                                       aria-label="<?php echo esc_attr__('Chọn tất cả IP trên trang', 'laca'); ?>">
                            <?php endif; ?>
                        </th>
                        <th><?php echo esc_html__('IP', 'laca'); ?></th>
                        <th><?php echo esc_html__('Phân loại', 'laca'); ?></th>
                        <th><?php echo esc_html__('Trạng thái chặn', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lượt', 'laca'); ?></th>
                        <th><?php echo esc_html__('Nghi ngờ', 'laca'); ?></th>
                        <th><?php echo esc_html__('404', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lần cuối', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lý do / User-Agent', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="9"><?php echo esc_html__('Chưa có dữ liệu truy cập trong khoảng thời gian đã chọn.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $ipStr     = (string) $r->ip;
                            $detailUrl = add_query_arg(
                                [
                                    'page' => self::MENU_SLUG,
                                    'tab'  => 'overview',
                                    'ip'   => $ipStr,
                                    'days' => $days,
                                ],
                                admin_url('admin.php')
                            );
                            $botName     = TrafficClassifier::bot_name((string) $r->ua);
                            $catBadge    = $this->categoryBadgeClass((string) $r->category);
                            $matchedCidr = IpBlocklist::matchingCidr($ipStr);
                            $isTrusted   = IpBlocklist::isTrusted($ipStr);
                            $reasonText  = TrafficClassifier::reasonLabel((string) $r->reasons);
                            $uaExcerpt   = wp_html_excerpt((string) $r->ua, 90, '…');
                        ?>
                            <tr>
                                <td>
                                    <?php if (!$pluginActive): ?>
                                        <input type="checkbox"
                                               class="laca-checkbox"
                                               name="ips[]"
                                               form="laca-tm-bulk-block-form"
                                               value="<?php echo esc_attr($ipStr); ?>"
                                               data-laca-check-group="overview-ips"
                                               aria-label="<?php
                                               /* translators: %s: địa chỉ IP trong bảng tổng quan */
                                               echo esc_attr(sprintf(__('Chọn IP %s', 'laca'), $ipStr));
                                               ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($detailUrl); ?>">
                                        <code class="laca-mono"><?php echo esc_html($ipStr); ?></code>
                                    </a>
                                </td>
                                <td>
                                    <span class="laca-badge <?php echo esc_attr($catBadge); ?>">
                                        <?php echo esc_html(TrafficClassifier::categoryLabel((string) $r->category)); ?>
                                    </span>
                                    <?php if ($botName !== ''): ?>
                                        <div class="laca-field__help">
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    /* translators: %s: tên bot đọc từ User-Agent */
                                                    __('%s (UA tự khai báo)', 'laca'),
                                                    $botName
                                                )
                                            );
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isTrusted): ?>
                                        <span class="laca-badge laca-badge--success"><?php echo esc_html__('Tin cậy', 'laca'); ?></span>
                                    <?php elseif ($matchedCidr !== null): ?>
                                        <span class="laca-badge laca-badge--danger"><?php echo esc_html__('Đã chặn', 'laca'); ?></span>
                                        <div><code class="laca-mono"><?php echo esc_html($matchedCidr); ?></code></div>
                                    <?php else: ?>
                                        <span class="laca-badge laca-badge--neutral"><?php echo esc_html__('Chưa chặn', 'laca'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html(number_format_i18n((int) $r->hits)); ?></strong></td>
                                <td><?php echo esc_html(number_format_i18n((int) $r->suspicious)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) $r->not_found)); ?></td>
                                <td><?php echo esc_html((string) $r->last_seen); ?></td>
                                <td>
                                    <?php if ($reasonText !== ''): ?>
                                        <div><strong><?php echo esc_html($reasonText); ?></strong></div>
                                    <?php endif; ?>
                                    <span title="<?php echo esc_attr((string) $r->ua); ?>">
                                        <?php echo esc_html($uaExcerpt !== '' ? $uaExcerpt : '—'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $this->renderPagination(
            $page,
            $totalPages,
            [
                'page'      => self::MENU_SLUG,
                'tab'       => 'overview',
                'days'      => $days,
                'category'  => $category,
                'ip_search' => $ipSearch,
            ]
        );
        ?>

        <p class="laca-field__help laca-mt-3">
            <?php echo esc_html__('Lưu ý: Các request được page cache hoặc CDN trả về trực tiếp không đi qua PHP WordPress nên không được ghi nhận tại đây. Không tự động chặn IP dựa trên điểm nghi vấn.', 'laca'); ?>
        </p>
        <?php
    }

    // ── Chi tiết IP (?tab=overview&ip=...) ───────────────────────────────────

    private function renderIpDetail(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            ?>
            <div class="laca-alert laca-alert--danger">
                <div class="laca-alert__content">
                    <p class="laca-alert__description"><?php echo esc_html__('Địa chỉ IP không hợp lệ.', 'laca'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        global $wpdb;
        $settings    = TrafficSettings::settings();
        $retention   = max(1, min(90, (int) ($settings['retention_days'] ?? 14)));
        $dayChoices  = $this->dayChoices($retention);
        $days        = isset($_GET['days']) ? absint($_GET['days']) : min(7, $retention);
        if (!in_array($days, $dayChoices, true)) {
            $days = $dayChoices[0];
        }

        $eventsTable = TrafficTable::eventsTable();
        $startTime   = wp_date('Y-m-d', time() - ($days - 1) * DAY_IN_SECONDS) . ' 00:00:00';

        $peakPerMin = 0;
        $errorCount = 0;
        $maxScore   = 0;
        $events     = [];

        if (TrafficTable::isReady()) {
            $peakPerMin = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(MAX(n), 0)
                     FROM (
                        SELECT COUNT(*) AS n
                        FROM {$eventsTable}
                        WHERE ip = %s AND visited_at >= %s
                        GROUP BY DATE_FORMAT(visited_at, '%%Y-%%m-%%d %%H:%%i')
                     ) AS per_minute",
                    $ip,
                    $startTime
                )
            );

            $agg = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT COUNT(CASE WHEN status_code >= 400 THEN 1 END) AS err_cnt,
                            COALESCE(MAX(score), 0) AS max_score
                     FROM {$eventsTable}
                     WHERE ip = %s AND visited_at >= %s",
                    $ip,
                    $startTime
                )
            );
            $errorCount = (int) ($agg->err_cnt ?? 0);
            $maxScore   = (int) ($agg->max_score ?? 0);

            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT visited_at, method, path, status_code, referrer, score, reason, action
                     FROM {$eventsTable}
                     WHERE ip = %s AND visited_at >= %s
                     ORDER BY visited_at DESC, id DESC
                     LIMIT 200",
                    $ip,
                    $startTime
                )
            ) ?: [];
        }

        $isV6         = str_contains($ip, ':');
        $matchedCidr  = IpBlocklist::matchingCidr($ip);
        $isTrusted    = IpBlocklist::isTrusted($ip);
        $pluginActive = TrafficSettings::isPluginActive();
        $backUrl      = add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'tab'  => 'overview',
                'days' => $days,
            ],
            admin_url('admin.php')
        );
        ?>
        <div class="laca-security-flex">
            <a href="<?php echo esc_url($backUrl); ?>" class="laca-btn laca-btn--outline laca-btn--sm">
                <?php echo esc_html__('← Quay lại danh sách IP', 'laca'); ?>
            </a>
            <h2>
                <?php echo esc_html__('Chi tiết IP:', 'laca'); ?>
                <code class="laca-mono"><?php echo esc_html($ip); ?></code>
            </h2>
            <?php if ($isTrusted): ?>
                <span class="laca-badge laca-badge--success"><?php echo esc_html__('IP tin cậy', 'laca'); ?></span>
            <?php elseif ($matchedCidr !== null): ?>
                <span class="laca-badge laca-badge--danger">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: quy tắc CIDR đang chặn IP */
                            __('Đang bị chặn bởi %s', 'laca'),
                            $matchedCidr
                        )
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- 3 chỉ số tóm tắt -->
        <div class="laca-mp-grid laca-mb-3">
            <div class="laca-card">
                <div class="laca-card__body">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php echo esc_html__('Đỉnh lượt / phút', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html(number_format_i18n($peakPerMin)); ?></span>
                    </div>
                </div>
            </div>
            <div class="laca-card">
                <div class="laca-card__body">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php echo esc_html__('Số lỗi HTTP 4xx / 5xx', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html(number_format_i18n($errorCount)); ?></span>
                    </div>
                </div>
            </div>
            <div class="laca-card">
                <div class="laca-card__body">
                    <div class="laca-stat">
                        <span class="laca-stat__label"><?php echo esc_html__('Điểm nghi vấn cao nhất', 'laca'); ?></span>
                        <span class="laca-stat__value"><?php echo esc_html($maxScore . '/100'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thao tác chặn / bỏ chặn / tin cậy -->
        <?php if (!$pluginActive): ?>
            <div class="laca-card laca-mb-3">
                <div class="laca-card__header">
                    <h3 class="laca-card__header-title"><?php echo esc_html__('Thao tác với địa chỉ IP này', 'laca'); ?></h3>
                </div>
                <div class="laca-card__body">
                    <div class="laca-security-flex">
                        <!-- Form chặn theo prefix -->
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-security-flex">
                            <input type="hidden" name="action" value="laca_tm_block_ip">
                            <input type="hidden" name="ip" value="<?php echo esc_attr($ip); ?>">
                            <input type="hidden" name="return_url" value="<?php echo esc_attr($this->currentAdminUrl()); ?>">
                            <?php wp_nonce_field('laca_tm_block_ip'); ?>

                            <?php if ($isV6):
                                /* translators: %s: địa chỉ IPv6 cần chặn */
                                $confirm128 = sprintf(__('Chặn IPv6 đơn %s/128?', 'laca'), $ip);
                                /* translators: %s: địa chỉ IPv6 cần chặn theo dải /64 */
                                $confirm64  = sprintf(__('Chặn dải IPv6 /64 chứa %s?', 'laca'), $ip);
                            ?>
                                <button type="submit" name="prefix" value="128" class="laca-btn laca-btn--danger laca-btn--sm"
                                        data-laca-confirm="<?php echo esc_attr($confirm128); ?>">
                                    <?php echo esc_html__('Chặn /128', 'laca'); ?>
                                </button>
                                <button type="submit" name="prefix" value="64" class="laca-btn laca-btn--outline laca-btn--sm"
                                        data-laca-confirm="<?php echo esc_attr($confirm64); ?>">
                                    <?php echo esc_html__('Chặn /64', 'laca'); ?>
                                </button>
                            <?php else:
                                /* translators: %s: địa chỉ IPv4 cần chặn đơn */
                                $confirm32 = sprintf(__('Chặn IP đơn %s/32?', 'laca'), $ip);
                                /* translators: %s: địa chỉ IPv4 cần chặn dải /24 */
                                $confirm24 = sprintf(__('Chặn dải /24 (256 IP) chứa %s?', 'laca'), $ip);
                                /* translators: %s: địa chỉ IPv4 cần chặn dải /16 */
                                $confirm16 = sprintf(__('Chặn dải /16 (65.536 IP) chứa %s?', 'laca'), $ip);
                                /* translators: %s: địa chỉ IPv4 cần chặn dải /8 */
                                $confirm8  = sprintf(__('Chặn dải /8 (hơn 16 triệu IP) chứa %s?', 'laca'), $ip);
                            ?>
                                <button type="submit" name="prefix" value="32" class="laca-btn laca-btn--danger laca-btn--sm"
                                        data-laca-confirm="<?php echo esc_attr($confirm32); ?>">
                                    <?php echo esc_html__('Chặn /32', 'laca'); ?>
                                </button>
                                <button type="submit" name="prefix" value="24" class="laca-btn laca-btn--outline laca-btn--sm"
                                        data-laca-confirm="<?php echo esc_attr($confirm24); ?>">
                                    <?php echo esc_html__('Chặn /24', 'laca'); ?>
                                </button>
                                <button type="submit" name="prefix" value="16" class="laca-btn laca-btn--outline laca-btn--sm"
                                        data-laca-confirm="<?php echo esc_attr($confirm16); ?>">
                                    <?php echo esc_html__('Chặn /16', 'laca'); ?>
                                </button>
                                <button type="submit" name="prefix" value="8" class="laca-btn laca-btn--outline laca-btn--sm"
                                        data-laca-confirm="<?php echo esc_attr($confirm8); ?>">
                                    <?php echo esc_html__('Chặn /8', 'laca'); ?>
                                </button>
                                <label class="laca-check-group__item">
                                    <input type="checkbox" class="laca-checkbox" name="confirm_wide" value="1">
                                    <span><?php echo esc_html__('Tôi hiểu dải này rất rộng', 'laca'); ?></span>
                                </label>
                            <?php endif; ?>
                        </form>

                        <?php if ($matchedCidr !== null):
                            /* translators: %s: quy tắc CIDR đang chặn */
                            $confirmUnblockCur = sprintf(__('Bỏ quy tắc chặn %s?', 'laca'), $matchedCidr);
                        ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="laca_tm_unblock_ip">
                                <input type="hidden" name="cidr" value="<?php echo esc_attr($matchedCidr); ?>">
                                <input type="hidden" name="return_url" value="<?php echo esc_attr($this->currentAdminUrl()); ?>">
                                <?php wp_nonce_field('laca_tm_unblock_ip'); ?>
                                <button type="submit" class="laca-btn laca-btn--outline laca-btn--sm"
                                        data-laca-confirm="<?php echo esc_attr($confirmUnblockCur); ?>">
                                    <?php echo esc_html__('Bỏ chặn quy tắc hiện tại', 'laca'); ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Nút đánh dấu / bỏ tin cậy -->
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="laca_tm_toggle_trusted">
                            <input type="hidden" name="ip" value="<?php echo esc_attr($ip); ?>">
                            <input type="hidden" name="return_url" value="<?php echo esc_attr($this->currentAdminUrl()); ?>">
                            <?php wp_nonce_field('laca_tm_toggle_trusted'); ?>
                            <button type="submit" class="laca-btn laca-btn--subtle laca-btn--sm">
                                <?php echo esc_html($isTrusted ? __('Bỏ khỏi IP tin cậy', 'laca') : __('Đánh dấu IP tin cậy', 'laca')); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 200 sự kiện gần nhất -->
        <h3><?php echo esc_html__('200 sự kiện gần nhất', 'laca'); ?></h3>
        <div class="laca-table-wrap">
            <table class="laca-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Thời gian', 'laca'); ?></th>
                        <th><?php echo esc_html__('Method', 'laca'); ?></th>
                        <th><?php echo esc_html__('Đường dẫn', 'laca'); ?></th>
                        <th><?php echo esc_html__('Status', 'laca'); ?></th>
                        <th><?php echo esc_html__('Nguồn (Referrer)', 'laca'); ?></th>
                        <th><?php echo esc_html__('Điểm', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lý do', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($events === []): ?>
                        <tr>
                            <td colspan="7"><?php echo esc_html__('Chưa ghi nhận sự kiện chi tiết cho địa chỉ IP này.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev):
                            $pathLink = add_query_arg(
                                [
                                    'page' => self::MENU_SLUG,
                                    'tab'  => 'urls',
                                    'path' => (string) $ev->path,
                                    'days' => $days,
                                ],
                                admin_url('admin.php')
                            );
                            $reasonLabel = TrafficClassifier::reasonLabel((string) $ev->reason);
                        ?>
                            <tr>
                                <td><?php echo esc_html((string) $ev->visited_at); ?></td>
                                <td><code class="laca-mono"><?php echo esc_html((string) $ev->method); ?></code></td>
                                <td>
                                    <a href="<?php echo esc_url($pathLink); ?>">
                                        <code class="laca-mono"><?php echo esc_html((string) $ev->path); ?></code>
                                    </a>
                                </td>
                                <td><?php echo esc_html((int) $ev->status_code > 0 ? (string) (int) $ev->status_code : '—'); ?></td>
                                <td>
                                    <?php if (!empty($ev->referrer)): ?>
                                        <code class="laca-mono"><?php echo esc_html((string) $ev->referrer); ?></code>
                                    <?php else: ?>
                                        <span class="laca-status-muted"><?php echo esc_html__('Trực tiếp / không rõ', 'laca'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html((string) (int) $ev->score); ?></td>
                                <td>
                                    <?php echo esc_html($reasonLabel !== '' ? $reasonLabel : '—'); ?>
                                    <?php if (!empty($ev->action)): ?>
                                        <span class="laca-badge <?php echo $ev->action === '403' ? 'laca-badge--danger' : 'laca-badge--warning'; ?>">
                                            <?php echo esc_html($ev->action === '403' ? '403' : __('Cảnh báo', 'laca')); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ── Tab 2: Top URLs & URL Detail ─────────────────────────────────────────

    private function renderUrlsTab(): void
    {
        global $wpdb;

        $settings   = TrafficSettings::settings();
        $retention  = max(1, min(90, (int) ($settings['retention_days'] ?? 14)));
        $dayChoices = $this->dayChoices($retention);
        $days       = isset($_GET['days']) ? absint($_GET['days']) : min(7, $retention);
        if (!in_array($days, $dayChoices, true)) {
            $days = $dayChoices[0];
        }

        $urlSearch = isset($_GET['url_search'])
            ? substr(trim(sanitize_text_field(wp_unslash($_GET['url_search']))), 0, 200)
            : '';

        $page    = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $total = 0;
        $rows  = [];

        if (TrafficTable::isReady()) {
            $eventsTable = TrafficTable::eventsTable();
            $startTime   = wp_date('Y-m-d', time() - ($days - 1) * DAY_IN_SECONDS) . ' 00:00:00';

            $whereSql  = 'WHERE visited_at >= %s';
            $whereArgs = [$startTime];
            if ($urlSearch !== '') {
                $whereSql   .= ' AND path LIKE %s';
                $whereArgs[] = '%' . $wpdb->esc_like($urlSearch) . '%';
            }

            $cacheKey = 'top_urls_' . md5(wp_json_encode([$days, $urlSearch, $page]));
            $cached   = wp_cache_get($cacheKey, TrafficTable::CACHE_GROUP);

            if (is_array($cached)) {
                $total = (int) ($cached['total'] ?? 0);
                $rows  = is_array($cached['rows'] ?? null) ? $cached['rows'] : [];
            } else {
                $total = (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(DISTINCT path) FROM {$eventsTable} {$whereSql}", $whereArgs)
                );

                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT path,
                                COUNT(*) AS hits,
                                COUNT(DISTINCT ip) AS unique_ips,
                                SUM(CASE WHEN status_code = 404 THEN 1 ELSE 0 END) AS not_found,
                                MAX(visited_at) AS last_seen
                         FROM {$eventsTable}
                         {$whereSql}
                         GROUP BY path
                         ORDER BY hits DESC, path ASC
                         LIMIT %d OFFSET %d",
                        array_merge($whereArgs, [$perPage, $offset])
                    )
                ) ?: [];

                wp_cache_set($cacheKey, ['total' => $total, 'rows' => $rows], TrafficTable::CACHE_GROUP, 60);
            }
        }

        $totalPages = max(1, (int) ceil($total / $perPage));
        ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="laca-log-filter-bar laca-mb-3">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
            <input type="hidden" name="tab" value="urls">

            <label class="screen-reader-text" for="laca-tm-url-days"><?php echo esc_html__('Khoảng thời gian', 'laca'); ?></label>
            <select id="laca-tm-url-days" name="days" class="laca-select">
                <?php foreach ($dayChoices as $d): ?>
                    <option value="<?php echo esc_attr((string) $d); ?>" <?php selected($days, $d); ?>>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: số ngày lọc thống kê */
                                _n('%d ngày gần nhất', '%d ngày gần nhất', $d, 'laca'),
                                $d
                            )
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="screen-reader-text" for="laca-tm-url-search"><?php echo esc_html__('Tìm đường dẫn', 'laca'); ?></label>
            <input type="search"
                   id="laca-tm-url-search"
                   name="url_search"
                   class="laca-input"
                   value="<?php echo esc_attr($urlSearch); ?>"
                   placeholder="<?php echo esc_attr__('Tìm đường dẫn (vd: wp-login.php)…', 'laca'); ?>">

            <button type="submit" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lọc', 'laca'); ?></button>
        </form>

        <div class="laca-table-wrap">
            <table class="laca-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Đường dẫn (bấm để xem IP)', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lượt truy cập', 'laca'); ?></th>
                        <th><?php echo esc_html__('Số IP duy nhất', 'laca'); ?></th>
                        <th><?php echo esc_html__('HTTP 404', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lần cuối', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('Chưa có dữ liệu đường dẫn trong khoảng thời gian đã chọn.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $pathUrl = add_query_arg(
                                [
                                    'page' => self::MENU_SLUG,
                                    'tab'  => 'urls',
                                    'path' => (string) $r->path,
                                    'days' => $days,
                                ],
                                admin_url('admin.php')
                            );
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($pathUrl); ?>">
                                        <code class="laca-mono"><?php echo esc_html((string) $r->path); ?></code>
                                    </a>
                                </td>
                                <td><strong><?php echo esc_html(number_format_i18n((int) $r->hits)); ?></strong></td>
                                <td><?php echo esc_html(number_format_i18n((int) $r->unique_ips)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) $r->not_found)); ?></td>
                                <td><?php echo esc_html((string) $r->last_seen); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $this->renderPagination(
            $page,
            $totalPages,
            [
                'page'       => self::MENU_SLUG,
                'tab'        => 'urls',
                'days'       => $days,
                'url_search' => $urlSearch,
            ]
        );
    }

    private function renderUrlDetail(string $path): void
    {
        global $wpdb;

        $settings   = TrafficSettings::settings();
        $retention  = max(1, min(90, (int) ($settings['retention_days'] ?? 14)));
        $dayChoices = $this->dayChoices($retention);
        $days       = isset($_GET['days']) ? absint($_GET['days']) : min(7, $retention);
        if (!in_array($days, $dayChoices, true)) {
            $days = $dayChoices[0];
        }

        $page    = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $total = 0;
        $rows  = [];

        if (TrafficTable::isReady()) {
            $eventsTable = TrafficTable::eventsTable();
            $startTime   = wp_date('Y-m-d', time() - ($days - 1) * DAY_IN_SECONDS) . ' 00:00:00';

            $catCase = "CASE
                WHEN SUM(category = 'attack') > 0 THEN 'attack'
                WHEN SUM(category = 'watch') > 0 THEN 'watch'
                WHEN SUM(category = 'searchbot') > 0 THEN 'searchbot'
                WHEN SUM(category = 'bot') > 0 THEN 'bot'
                WHEN SUM(category = 'visitor') > 0 THEN 'visitor'
                ELSE 'unknown'
            END";

            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT ip) FROM {$eventsTable} WHERE path = %s AND visited_at >= %s",
                    $path,
                    $startTime
                )
            );

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ip,
                            COUNT(*) AS hits,
                            SUM(CASE WHEN status_code = 404 THEN 1 ELSE 0 END) AS not_found,
                            MAX(score) AS max_score,
                            MAX(visited_at) AS last_seen,
                            MAX(ua) AS ua,
                            {$catCase} AS category
                     FROM {$eventsTable}
                     WHERE path = %s AND visited_at >= %s
                     GROUP BY ip
                     ORDER BY hits DESC, ip ASC
                     LIMIT %d OFFSET %d",
                    $path,
                    $startTime,
                    $perPage,
                    $offset
                )
            ) ?: [];
        }

        $totalPages   = max(1, (int) ceil($total / $perPage));
        $pluginActive = TrafficSettings::isPluginActive();
        $backUrl      = add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'tab'  => 'urls',
                'days' => $days,
            ],
            admin_url('admin.php')
        );
        ?>
        <div class="laca-security-flex">
            <a href="<?php echo esc_url($backUrl); ?>" class="laca-btn laca-btn--outline laca-btn--sm">
                <?php echo esc_html__('← Quay lại Top URL', 'laca'); ?>
            </a>
            <h2>
                <?php echo esc_html__('IP truy cập đường dẫn:', 'laca'); ?>
                <code class="laca-mono"><?php echo esc_html($path); ?></code>
            </h2>
        </div>

        <?php if (!$pluginActive && $rows !== []): ?>
            <form method="post"
                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                  class="laca-log-filter-bar laca-mb-3"
                  data-laca-confirm="<?php echo esc_attr__('Chặn toàn bộ các địa chỉ IP hiển thị trên trang hiện tại?', 'laca'); ?>">
                <input type="hidden" name="action" value="laca_tm_block_url_page">
                <input type="hidden" name="return_url" value="<?php echo esc_attr($this->currentAdminUrl()); ?>">
                <?php wp_nonce_field('laca_tm_block_url_page'); ?>
                <?php foreach ($rows as $r): ?>
                    <input type="hidden" name="ips[]" value="<?php echo esc_attr((string) $r->ip); ?>">
                <?php endforeach; ?>

                <label class="screen-reader-text" for="laca-tm-url-block-prefix"><?php echo esc_html__('Độ dài prefix chặn', 'laca'); ?></label>
                <select id="laca-tm-url-block-prefix" name="prefix" class="laca-select">
                    <option value="32"><?php echo esc_html__('IP đơn (/32 · IPv6 /128)', 'laca'); ?></option>
                    <option value="24"><?php echo esc_html__('Dải IPv4 /24 (256 IP)', 'laca'); ?></option>
                    <option value="16"><?php echo esc_html__('Dải IPv4 /16 (65.536 IP)', 'laca'); ?></option>
                    <option value="8"><?php echo esc_html__('Dải IPv4 /8 (16.777.216 IP)', 'laca'); ?></option>
                    <option value="64"><?php echo esc_html__('Dải IPv6 /64', 'laca'); ?></option>
                </select>

                <label class="laca-check-group__item">
                    <input type="checkbox" class="laca-checkbox" name="confirm_wide" value="1">
                    <span><?php echo esc_html__('Tôi hiểu dải này rất rộng', 'laca'); ?></span>
                </label>

                <button type="submit" class="laca-btn laca-btn--danger laca-btn--sm">
                    <?php echo esc_html__('Chặn tất cả IP (trang này)', 'laca'); ?>
                </button>
            </form>
        <?php endif; ?>

        <div class="laca-table-wrap">
            <table class="laca-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('IP', 'laca'); ?></th>
                        <th><?php echo esc_html__('Phân loại', 'laca'); ?></th>
                        <th><?php echo esc_html__('Trạng thái chặn', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lượt', 'laca'); ?></th>
                        <th><?php echo esc_html__('404', 'laca'); ?></th>
                        <th><?php echo esc_html__('Điểm max', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lần cuối', 'laca'); ?></th>
                        <th><?php echo esc_html__('User-Agent', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="8"><?php echo esc_html__('Không có địa chỉ IP nào truy cập đường dẫn này trong khoảng đã chọn.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $ipStr     = (string) $r->ip;
                            $detailUrl = add_query_arg(
                                [
                                    'page' => self::MENU_SLUG,
                                    'tab'  => 'overview',
                                    'ip'   => $ipStr,
                                    'days' => $days,
                                ],
                                admin_url('admin.php')
                            );
                            $matchedCidr = IpBlocklist::matchingCidr($ipStr);
                            $isTrusted   = IpBlocklist::isTrusted($ipStr);
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($detailUrl); ?>">
                                        <code class="laca-mono"><?php echo esc_html($ipStr); ?></code>
                                    </a>
                                </td>
                                <td>
                                    <span class="laca-badge <?php echo esc_attr($this->categoryBadgeClass((string) $r->category)); ?>">
                                        <?php echo esc_html(TrafficClassifier::categoryLabel((string) $r->category)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isTrusted): ?>
                                        <span class="laca-badge laca-badge--success"><?php echo esc_html__('Tin cậy', 'laca'); ?></span>
                                    <?php elseif ($matchedCidr !== null): ?>
                                        <span class="laca-badge laca-badge--danger"><?php echo esc_html($matchedCidr); ?></span>
                                    <?php else: ?>
                                        <span class="laca-badge laca-badge--neutral"><?php echo esc_html__('Chưa chặn', 'laca'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html(number_format_i18n((int) $r->hits)); ?></strong></td>
                                <td><?php echo esc_html(number_format_i18n((int) $r->not_found)); ?></td>
                                <td><?php echo esc_html((string) (int) $r->max_score); ?></td>
                                <td><?php echo esc_html((string) $r->last_seen); ?></td>
                                <td><?php echo esc_html(wp_html_excerpt((string) $r->ua, 80, '…')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $this->renderPagination(
            $page,
            $totalPages,
            [
                'page' => self::MENU_SLUG,
                'tab'  => 'urls',
                'path' => $path,
                'days' => $days,
            ]
        );
    }

    // ── Tab 3: Blocked IPs ───────────────────────────────────────────────────

    private function renderBlockedTab(): void
    {
        global $wpdb;

        $settings     = TrafficSettings::settings();
        $blocklist    = IpBlocklist::getBlocklist();
        $pluginActive = TrafficSettings::isPluginActive();

        // Mới thêm lên đầu
        uasort($blocklist, static fn(array $a, array $b): int => ($b['added'] ?? 0) <=> ($a['added'] ?? 0));

        $search = isset($_GET['blocked_search'])
            ? substr(trim(sanitize_text_field(wp_unslash($_GET['blocked_search']))), 0, 64)
            : '';

        if ($search !== '') {
            $blocklist = array_filter(
                $blocklist,
                static fn(array $meta, string $cidr): bool => stripos($cidr, $search) !== false || stripos((string) ($meta['note'] ?? ''), $search) !== false,
                ARRAY_FILTER_USE_BOTH
            );
        }

        $total      = count($blocklist);
        $page       = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $perPage    = 50;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $shown      = array_slice($blocklist, ($page - 1) * $perPage, $perPage, true);

        // Gom truy vấn visits gần nhất cho các IP đơn trên trang hiện tại (tránh N+1)
        $singleIps = [];
        $userIds   = [];
        foreach ($shown as $cidr => $meta) {
            [$net, $pfxStr] = array_pad(explode('/', $cidr, 2), 2, '32');
            $pfx            = (int) $pfxStr;
            $isV6           = str_contains($net, ':');
            if (($isV6 && $pfx === 128) || (!$isV6 && $pfx === 32)) {
                $singleIps[] = $net;
            }
            if (!empty($meta['by'])) {
                $userIds[] = (int) $meta['by'];
            }
        }

        $visitMap = [];
        if ($singleIps !== [] && TrafficTable::isReady()) {
            $singleIps    = array_values(array_unique($singleIps));
            $placeholders = implode(',', array_fill(0, count($singleIps), '%s'));
            $visitsTable  = TrafficTable::visitsTable();
            $visitRows    = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ip, category, ua, last_seen
                     FROM {$visitsTable}
                     WHERE ip IN ({$placeholders})
                     ORDER BY day DESC, last_seen DESC",
                    $singleIps
                )
            ) ?: [];
            foreach ($visitRows as $v) {
                if (!isset($visitMap[$v->ip])) {
                    $visitMap[$v->ip] = $v;
                }
            }
        }

        $userMap = [];
        if ($userIds !== []) {
            $users = get_users(['include' => array_values(array_unique($userIds)), 'fields' => ['ID', 'user_login']]);
            foreach ($users as $u) {
                $userMap[(int) $u->ID] = (string) $u->user_login;
            }
        }

        $syncOn    = (int) ($settings['htaccess_sync'] ?? 0) === 1;
        $canSync   = IpBlocklist::canSyncHtaccess();
        $canImport = IpBlocklist::canImportFromPlugin();
        ?>
        <!-- Trạng thái đồng bộ .htaccess + nút nhập từ plugin -->
        <div class="laca-card laca-mb-3">
            <div class="laca-card__body laca-card__header--flex">
                <div>
                    <strong><?php echo esc_html__('Chế độ thực thi danh sách chặn:', 'laca'); ?></strong>
                    <span class="laca-badge laca-badge--success"><?php echo esc_html__('Tầng PHP (luôn bật)', 'laca'); ?></span>
                    <?php if ($syncOn && $canSync): ?>
                        <span class="laca-badge laca-badge--accent"><?php echo esc_html__('Đồng bộ .htaccess: Đang bật', 'laca'); ?></span>
                    <?php elseif ($syncOn && !$canSync): ?>
                        <span class="laca-badge laca-badge--warning"><?php echo esc_html__('Đồng bộ .htaccess: Không khả dụng (máy chủ không phải Apache hoặc thiếu quyền ghi)', 'laca'); ?></span>
                    <?php else: ?>
                        <span class="laca-badge laca-badge--neutral"><?php echo esc_html__('Đồng bộ .htaccess: Đang tắt', 'laca'); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($canImport): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="laca_tm_import_lbm">
                        <?php wp_nonce_field('laca_tm_import_lbm'); ?>
                        <button type="submit"
                                class="laca-btn laca-btn--outline laca-btn--sm"
                                data-laca-confirm="<?php echo esc_attr__('Nhập quy tắc chặn và IP tin cậy từ Lamvt Security vào theme? Dữ liệu cũ của plugin sẽ được giữ nguyên.', 'laca'); ?>">
                            <?php echo esc_html__('Nhập từ Lamvt Security', 'laca'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tìm kiếm & Bỏ chặn hàng loạt -->
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="laca-log-filter-bar laca-mb-3">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
            <input type="hidden" name="tab" value="blocked">

            <label class="screen-reader-text" for="laca-tm-blocked-search"><?php echo esc_html__('Tìm CIDR hoặc ghi chú', 'laca'); ?></label>
            <input type="search"
                   id="laca-tm-blocked-search"
                   name="blocked_search"
                   class="laca-input"
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php echo esc_attr__('Tìm IP, CIDR hoặc ghi chú…', 'laca'); ?>">
            <button type="submit" class="laca-btn laca-btn--solid laca-btn--sm"><?php echo esc_html__('Lọc', 'laca'); ?></button>
        </form>

        <?php if (!$pluginActive && $shown !== []): ?>
            <form id="laca-tm-bulk-unblock-form"
                  method="post"
                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                  class="laca-log-filter-bar laca-mb-3"
                  data-laca-confirm="<?php echo esc_attr__('Bỏ chặn các quy tắc CIDR đã chọn?', 'laca'); ?>">
                <input type="hidden" name="action" value="laca_tm_unblock_selected">
                <input type="hidden" name="return_url" value="<?php echo esc_attr($this->currentAdminUrl()); ?>">
                <?php wp_nonce_field('laca_tm_unblock_selected'); ?>

                <span>
                    <?php echo esc_html__('Đã chọn:', 'laca'); ?>
                    <strong data-laca-check-count="blocked-cidrs">0</strong>
                </span>
                <button type="submit"
                        class="laca-btn laca-btn--outline laca-btn--sm"
                        data-laca-check-submit="blocked-cidrs"
                        disabled>
                    <?php echo esc_html__('Bỏ chặn mục đã chọn', 'laca'); ?>
                </button>
            </form>
        <?php endif; ?>

        <div class="laca-table-wrap">
            <table class="laca-table">
                <thead>
                    <tr>
                        <th>
                            <?php if (!$pluginActive && $shown !== []): ?>
                                <input type="checkbox"
                                       class="laca-checkbox"
                                       data-laca-check-all="blocked-cidrs"
                                       aria-label="<?php echo esc_attr__('Chọn tất cả quy tắc chặn trên trang', 'laca'); ?>">
                            <?php endif; ?>
                        </th>
                        <th><?php echo esc_html__('CIDR', 'laca'); ?></th>
                        <th><?php echo esc_html__('Số địa chỉ IPv4', 'laca'); ?></th>
                        <th><?php echo esc_html__('Ngày thêm', 'laca'); ?></th>
                        <th><?php echo esc_html__('Người thêm', 'laca'); ?></th>
                        <th><?php echo esc_html__('Ghi chú', 'laca'); ?></th>
                        <th><?php echo esc_html__('Phân loại / UA gần nhất', 'laca'); ?></th>
                        <th class="laca-col-right"><?php echo esc_html__('Thao tác', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($shown === []): ?>
                        <tr>
                            <td colspan="8"><?php echo esc_html__('Danh sách chặn hiện đang trống.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shown as $cidr => $meta):
                            [$net, $pfxStr] = array_pad(explode('/', $cidr, 2), 2, '32');
                            $pfx            = (int) $pfxStr;
                            $isV6           = str_contains($net, ':');
                            $ipv4Count      = !$isV6 ? number_format_i18n((int) pow(2, max(0, 32 - $pfx))) : '—';
                            $addedAt        = !empty($meta['added']) ? wp_date('Y-m-d H:i', (int) $meta['added']) : '—';
                            $byUser         = !empty($meta['by']) && isset($userMap[(int) $meta['by']])
                                ? $userMap[(int) $meta['by']]
                                : '—';
                            $isSingle       = ($isV6 && $pfx === 128) || (!$isV6 && $pfx === 32);
                            $visit          = ($isSingle && isset($visitMap[$net])) ? $visitMap[$net] : null;
                        ?>
                            <tr>
                                <td>
                                    <?php if (!$pluginActive):
                                        /* translators: %s: quy tắc CIDR trong bảng danh sách chặn */
                                        $ariaSelectCidr = sprintf(__('Chọn %s', 'laca'), $cidr);
                                    ?>
                                        <input type="checkbox"
                                               class="laca-checkbox"
                                               name="cidrs[]"
                                               form="laca-tm-bulk-unblock-form"
                                               value="<?php echo esc_attr($cidr); ?>"
                                               data-laca-check-group="blocked-cidrs"
                                               aria-label="<?php echo esc_attr($ariaSelectCidr); ?>">
                                    <?php endif; ?>
                                </td>
                                <td><code class="laca-mono"><?php echo esc_html($cidr); ?></code></td>
                                <td><?php echo esc_html($ipv4Count); ?></td>
                                <td><?php echo esc_html($addedAt); ?></td>
                                <td><?php echo esc_html($byUser); ?></td>
                                <td><?php echo esc_html($meta['note'] !== '' ? $meta['note'] : '—'); ?></td>
                                <td>
                                    <?php if ($visit !== null): ?>
                                        <span class="laca-badge <?php echo esc_attr($this->categoryBadgeClass((string) $visit->category)); ?>">
                                            <?php echo esc_html(TrafficClassifier::categoryLabel((string) $visit->category)); ?>
                                        </span>
                                        <div title="<?php echo esc_attr((string) $visit->ua); ?>">
                                            <?php echo esc_html(wp_html_excerpt((string) $visit->ua, 70, '…')); ?>
                                        </div>
                                    <?php elseif (!$isSingle): ?>
                                        <span class="laca-status-muted"><?php echo esc_html__('Dải mạng', 'laca'); ?></span>
                                    <?php else: ?>
                                        <span class="laca-status-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="laca-col-right">
                                    <?php if (!$pluginActive):
                                        /* translators: %s: quy tắc CIDR cần bỏ chặn */
                                        $confirmUnblockRow = sprintf(__('Bỏ chặn quy tắc %s?', 'laca'), $cidr);
                                    ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="laca_tm_unblock_ip">
                                            <input type="hidden" name="cidr" value="<?php echo esc_attr($cidr); ?>">
                                            <input type="hidden" name="return_url" value="<?php echo esc_attr($this->currentAdminUrl()); ?>">
                                            <?php wp_nonce_field('laca_tm_unblock_ip'); ?>
                                            <button type="submit"
                                                    class="laca-btn laca-btn--outline laca-btn--xs"
                                                    data-laca-confirm="<?php echo esc_attr($confirmUnblockRow); ?>">
                                                <?php echo esc_html__('Bỏ chặn', 'laca'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $this->renderPagination(
            $page,
            $totalPages,
            [
                'page'           => self::MENU_SLUG,
                'tab'            => 'blocked',
                'blocked_search' => $search,
            ]
        );
    }

    // ── Tab 4: Request Firewall ──────────────────────────────────────────────

    private function renderFirewallTab(): void
    {
        global $wpdb;

        $s            = TrafficSettings::settings();
        $pluginActive = TrafficSettings::isPluginActive();
        $fwEvents     = [];

        if (TrafficTable::isReady()) {
            $eventsTable = TrafficTable::eventsTable();
            $fwEvents    = $wpdb->get_results(
                "SELECT visited_at, ip, method, path, reason, action
                 FROM {$eventsTable}
                 WHERE action != ''
                 ORDER BY id DESC
                 LIMIT 100"
            ) ?: [];
        }

        $rules = [
            'fw_sensitive'  => __('Bảo vệ đường dẫn tệp nhạy cảm (.env, .git, wp-config.php.bak, phpinfo.php, .htaccess)', 'laca'),
            'fw_upload_php' => __('Chặn thực thi tệp PHP / PHTML / PHAR trong thư mục uploads', 'laca'),
            'fw_traversal'  => __('Phát hiện dấu hiệu path traversal (../, /etc/passwd, wp-config.php trong query)', 'laca'),
            'fw_sqli'       => __('Phát hiện mẫu SQL injection phổ biến (UNION SELECT, SLEEP, BENCHMARK, information_schema…)', 'laca'),
            'fw_xss'        => __('Phát hiện mẫu XSS trong query (<script>, javascript:, onerror=, <iframe>/<svg>)', 'laca'),
            'fw_custom'     => __('Kiểm tra danh sách tên tham số query tùy chỉnh bên dưới', 'laca'),
        ];
        ?>
        <div class="laca-settings-stack">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-card">
                <input type="hidden" name="action" value="laca_tm_save_firewall">
                <?php wp_nonce_field('laca_tm_save_firewall'); ?>

                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Cấu hình tường lửa request', 'laca'); ?></h2>
                    <p class="laca-card__header-description">
                        <?php echo esc_html__('Chạy độc lập với bộ ghi nhận truy cập. Khuyến nghị theo dõi ở chế độ Cảnh báo trước khi bật Chặn HTTP 403.', 'laca'); ?>
                    </p>
                </div>

                <div class="laca-card__body">
                    <div class="laca-field laca-field--narrow">
                        <label class="laca-field__label" for="laca-tm-fw-mode"><?php echo esc_html__('Chế độ xử lý', 'laca'); ?></label>
                        <select id="laca-tm-fw-mode" name="laca_tm[firewall_mode]" class="laca-select" <?php disabled($pluginActive); ?>>
                            <option value="alert" <?php selected($s['firewall_mode'], 'alert'); ?>>
                                <?php echo esc_html__('Chỉ ghi cảnh báo (mặc định an toàn)', 'laca'); ?>
                            </option>
                            <option value="block" <?php selected($s['firewall_mode'], 'block'); ?>>
                                <?php echo esc_html__('Chặn bằng HTTP 403', 'laca'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="laca-header-list laca-mt-3">
                        <?php foreach ($rules as $key => $label): ?>
                            <div class="laca-header-item">
                                <label class="laca-switch">
                                    <input type="checkbox"
                                           name="laca_tm[<?php echo esc_attr($key); ?>]"
                                           value="1"
                                           class="laca-switch__input"
                                           <?php checked(!empty($s[$key])); ?>
                                           <?php disabled($pluginActive); ?>>
                                    <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                    <span class="laca-switch__label"><?php echo esc_html($label); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-field__label" for="laca-tm-fw-custom-keys">
                            <?php echo esc_html__('Tên tham số query tùy chỉnh (phân cách bằng dấu phẩy)', 'laca'); ?>
                        </label>
                        <input type="text"
                               id="laca-tm-fw-custom-keys"
                               name="laca_tm[fw_custom_keys]"
                               class="laca-input laca-mono"
                               value="<?php echo esc_attr((string) $s['fw_custom_keys']); ?>"
                               placeholder="<?php echo esc_attr__('debug_cmd, eval_test', 'laca'); ?>"
                               <?php disabled($pluginActive); ?>>
                        <p class="laca-field__help">
                            <?php echo esc_html__('Chỉ chấp nhận ký tự a-z, 0-9, gạch dưới và gạch ngang (tối đa 40 tham số, mỗi tham số tối đa 64 ký tự). Không thêm các tham số tìm kiếm hoặc thanh toán hợp lệ.', 'laca'); ?>
                        </p>
                    </div>
                </div>

                <?php if (!$pluginActive): ?>
                    <div class="laca-card__footer">
                        <button type="submit" class="laca-btn laca-btn--solid laca-btn--sm">
                            <?php echo esc_html__('Lưu cấu hình tường lửa', 'laca'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <h3 class="laca-mt-3"><?php echo esc_html__('100 sự kiện tường lửa gần nhất', 'laca'); ?></h3>
        <div class="laca-table-wrap">
            <table class="laca-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Thời gian', 'laca'); ?></th>
                        <th><?php echo esc_html__('IP', 'laca'); ?></th>
                        <th><?php echo esc_html__('Method', 'laca'); ?></th>
                        <th><?php echo esc_html__('Đường dẫn', 'laca'); ?></th>
                        <th><?php echo esc_html__('Lý do', 'laca'); ?></th>
                        <th><?php echo esc_html__('Xử lý', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($fwEvents === []): ?>
                        <tr>
                            <td colspan="6"><?php echo esc_html__('Chưa có sự kiện tường lửa nào được ghi nhận.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fwEvents as $ev):
                            $evIp  = (string) $ev->ip;
                            $ipUrl = add_query_arg(
                                [
                                    'page' => self::MENU_SLUG,
                                    'tab'  => 'overview',
                                    'ip'   => $evIp,
                                ],
                                admin_url('admin.php')
                            );
                        ?>
                            <tr>
                                <td><?php echo esc_html((string) $ev->visited_at); ?></td>
                                <td>
                                    <?php if (filter_var($evIp, FILTER_VALIDATE_IP)): ?>
                                        <a href="<?php echo esc_url($ipUrl); ?>">
                                            <code class="laca-mono"><?php echo esc_html($evIp); ?></code>
                                        </a>
                                    <?php else: ?>
                                        <code class="laca-mono"><?php echo esc_html($evIp); ?></code>
                                    <?php endif; ?>
                                </td>
                                <td><code class="laca-mono"><?php echo esc_html((string) $ev->method); ?></code></td>
                                <td><code class="laca-mono"><?php echo esc_html((string) $ev->path); ?></code></td>
                                <td><?php echo esc_html(TrafficClassifier::reasonLabel((string) $ev->reason)); ?></td>
                                <td>
                                    <?php if ($ev->action === '403'): ?>
                                        <span class="laca-badge laca-badge--danger">403</span>
                                    <?php else: ?>
                                        <span class="laca-badge laca-badge--warning"><?php echo esc_html__('Cảnh báo', 'laca'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ── Tab 5: Settings & Tools ──────────────────────────────────────────────

    private function renderSettingsTab(): void
    {
        $s            = TrafficSettings::settings();
        $trustedText  = implode("\n", IpBlocklist::trustedIps());
        $canSync      = IpBlocklist::canSyncHtaccess();
        $pluginActive = TrafficSettings::isPluginActive();
        $myIp         = ClientIp::get();
        $rdapAlerts   = NetworkTypeScanner::getAlerts();
        $exportUrl    = wp_nonce_url(
            admin_url('admin-post.php?action=laca_tm_export_csv'),
            'laca_tm_export_csv'
        );
        ?>
        <div class="laca-settings-stack">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-card">
                <input type="hidden" name="action" value="laca_tm_save_settings">
                <?php wp_nonce_field('laca_tm_save_settings'); ?>

                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Cài đặt giám sát & cảnh báo', 'laca'); ?></h2>
                </div>

                <div class="laca-card__body">
                    <!-- Tracking -->
                    <div class="laca-field">
                        <label class="laca-switch">
                            <input type="checkbox"
                                   name="laca_tm[tracking]"
                                   value="1"
                                   class="laca-switch__input"
                                   <?php checked(!empty($s['tracking'])); ?>
                                   <?php disabled($pluginActive); ?>>
                            <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                            <span class="laca-switch__label"><?php echo esc_html__('Bật ghi nhận lượt truy cập công khai', 'laca'); ?></span>
                        </label>
                        <p class="laca-field__help">
                            <?php echo esc_html__('Mặc định tắt để không ghi DB ngoài ý muốn khi cập nhật theme hàng loạt. Request do page cache hoặc CDN trả về không đi qua PHP nên sẽ không được ghi nhận.', 'laca'); ?>
                        </p>
                    </div>

                    <!-- Retention -->
                    <div class="laca-field laca-field--spaced-top laca-field--narrow">
                        <label class="laca-field__label" for="laca-tm-retention">
                            <?php echo esc_html__('Thời gian lưu nhật ký (1–90 ngày)', 'laca'); ?>
                        </label>
                        <input type="number"
                               id="laca-tm-retention"
                               name="laca_tm[retention_days]"
                               class="laca-input"
                               min="1"
                               max="90"
                               value="<?php echo esc_attr((string) $s['retention_days']); ?>"
                               <?php disabled($pluginActive); ?>>
                    </div>

                    <!-- Digest Email -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-switch">
                            <input type="checkbox"
                                   name="laca_tm[digest_email]"
                                   value="1"
                                   class="laca-switch__input"
                                   <?php checked(!empty($s['digest_email'])); ?>
                                   <?php disabled($pluginActive); ?>>
                            <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                            <span class="laca-switch__label"><?php echo esc_html__('Gửi email tổng hợp cảnh báo mỗi giờ (khi có lượt nghi ngờ / dò quét mới)', 'laca'); ?></span>
                        </label>
                    </div>

                    <!-- .htaccess sync -->
                    <div class="laca-field laca-field--spaced-top">
                        <?php if ($canSync): ?>
                            <label class="laca-switch">
                                <input type="checkbox"
                                       name="laca_tm[htaccess_sync]"
                                       value="1"
                                       class="laca-switch__input"
                                       <?php checked(!empty($s['htaccess_sync'])); ?>
                                       <?php disabled($pluginActive); ?>>
                                <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                                <span class="laca-switch__label"><?php echo esc_html__('Đồng bộ danh sách chặn vào file .htaccess (Apache 2.4)', 'laca'); ?></span>
                            </label>
                            <p class="laca-field__help">
                                <?php echo esc_html__('Tự động sao lưu tối đa 5 bản .htaccess gần nhất trước khi ghi khối # BEGIN LACA SECURITY và tự khôi phục nếu xác minh thất bại.', 'laca'); ?>
                            </p>
                        <?php else: ?>
                            <p class="laca-field__help">
                                <?php echo esc_html__('Tuỳ chọn đồng bộ .htaccess đang ẩn vì máy chủ không chạy Apache hoặc file .htaccess không có quyền ghi. Chặn IP ở tầng PHP vẫn hoạt động bình thường trên mọi máy chủ.', 'laca'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Trusted IPs -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-field__label" for="laca-tm-trusted-ips">
                            <?php echo esc_html__('Danh sách IP tin cậy (mỗi dòng 1 IP, tối đa 200)', 'laca'); ?>
                        </label>
                        <textarea id="laca-tm-trusted-ips"
                                  name="trusted_ips"
                                  class="laca-textarea laca-mono"
                                  rows="4"
                                  placeholder="127.0.0.1"
                                  <?php disabled($pluginActive); ?>><?php echo esc_textarea($trustedText); ?></textarea>
                        <p class="laca-field__help">
                            <?php
                            if ($myIp !== '' && $myIp !== 'unknown') {
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: địa chỉ IP hiện tại của quản trị viên */
                                        __('IP tin cậy không bao giờ bị chặn ở tầng PHP và không thể thêm vào danh sách chặn. IP hiện tại của bạn: %s', 'laca'),
                                        $myIp
                                    )
                                );
                            } else {
                                echo esc_html__('IP tin cậy không bao giờ bị chặn ở tầng PHP và không thể thêm vào danh sách chặn.', 'laca');
                            }
                            ?>
                        </p>
                    </div>

                    <!-- RDAP (Pha 3) -->
                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-switch">
                            <input type="checkbox"
                                   name="laca_tm[rdap]"
                                   value="1"
                                   class="laca-switch__input"
                                   <?php checked(!empty($s['rdap'])); ?>
                                   <?php disabled($pluginActive); ?>>
                            <span class="laca-switch__track"><span class="laca-switch__thumb"></span></span>
                            <span class="laca-switch__label"><?php echo esc_html__('Bật quét nền loại mạng RDAP mỗi 15 phút (tối đa 3 IP/lần, chỉ cảnh báo)', 'laca'); ?></span>
                        </label>
                        <p class="laca-field__help">
                            <?php echo esc_html__('Gửi IP công cộng đã truy cập tới rdap.org ở nền để phát hiện mạng ALLOCATED PORTABLE (thường gặp ở hạ tầng máy chủ/VPS). Mặc định tắt.', 'laca'); ?>
                        </p>
                    </div>

                    <div class="laca-field laca-field--spaced-top">
                        <label class="laca-field__label" for="laca-tm-rdap-whitelist">
                            <?php echo esc_html__('Bỏ qua tra cứu RDAP cho các IP / CIDR sau (mỗi dòng 1 mục)', 'laca'); ?>
                        </label>
                        <textarea id="laca-tm-rdap-whitelist"
                                  name="laca_tm[rdap_whitelist]"
                                  class="laca-textarea laca-mono"
                                  rows="3"
                                  placeholder="8.8.8.0/24"
                                  <?php disabled($pluginActive); ?>><?php echo esc_textarea((string) $s['rdap_whitelist']); ?></textarea>
                    </div>

                    <!-- Kill-switch khẩn cấp -->
                    <div class="laca-alert laca-alert--info laca-mt-3">
                        <div class="laca-alert__content">
                            <p class="laca-alert__title"><?php echo esc_html__('Công tắc khẩn cấp (Kill-switch):', 'laca'); ?></p>
                            <p class="laca-alert__description">
                                <?php echo wp_kses_post(__('Nếu lỡ cấu hình chặn nhầm và không truy cập được trang quản trị, hãy thêm dòng <code>define(\'LACA_TM_DISABLE\', true);</code> vào file <code>wp-config.php</code> để tạm tắt toàn bộ bộ chặn IP và tường lửa request.', 'laca')); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <?php if (!$pluginActive): ?>
                    <div class="laca-card__footer">
                        <button type="submit" class="laca-btn laca-btn--solid laca-btn--sm">
                            <?php echo esc_html__('Lưu cài đặt', 'laca'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Xuất dữ liệu & Xoá toàn bộ dữ liệu -->
            <div class="laca-card">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php echo esc_html__('Dữ liệu nhật ký', 'laca'); ?></h2>
                    <p class="laca-card__header-description">
                        <?php echo esc_html__('Xuất thống kê IP 7 ngày gần nhất ra file CSV hoặc xoá toàn bộ dữ liệu trong hai bảng nhật ký.', 'laca'); ?>
                    </p>
                </div>
                <div class="laca-card__body laca-security-flex">
                    <a href="<?php echo esc_url($exportUrl); ?>" class="laca-btn laca-btn--outline laca-btn--sm">
                        <?php echo esc_html__('Xuất CSV (7 ngày)', 'laca'); ?>
                    </a>

                    <?php if (!$pluginActive): ?>
                        <form method="post"
                              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              data-laca-confirm="<?php echo esc_attr__('Xoá toàn bộ dữ liệu nhật ký truy cập và sự kiện? Thao tác này không thể hoàn tác.', 'laca'); ?>">
                            <input type="hidden" name="action" value="laca_tm_purge">
                            <?php wp_nonce_field('laca_tm_purge'); ?>
                            <button type="submit" class="laca-btn laca-btn--danger laca-btn--sm">
                                <?php echo esc_html__('Xoá toàn bộ dữ liệu', 'laca'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Nhật ký cảnh báo loại mạng RDAP -->
        <h3 class="laca-mt-3"><?php echo esc_html__('Nhật ký cảnh báo loại mạng RDAP (tối đa 200 bản ghi)', 'laca'); ?></h3>
        <div class="laca-table-wrap">
            <table class="laca-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Thời gian', 'laca'); ?></th>
                        <th><?php echo esc_html__('IP', 'laca'); ?></th>
                        <th><?php echo esc_html__('Loại mạng', 'laca'); ?></th>
                        <th><?php echo esc_html__('Hành động', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rdapAlerts === []): ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('Chưa có cảnh báo loại mạng RDAP nào.', 'laca'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rdapAlerts as $alert):
                            if (!is_array($alert)) {
                                continue;
                            }
                            $aIp   = (string) ($alert['ip'] ?? '');
                            $aTime = !empty($alert['time']) ? wp_date('Y-m-d H:i:s', (int) $alert['time']) : '—';
                        ?>
                            <tr>
                                <td><?php echo esc_html($aTime); ?></td>
                                <td><code class="laca-mono"><?php echo esc_html($aIp); ?></code></td>
                                <td><code class="laca-mono"><?php echo esc_html((string) ($alert['type'] ?? '')); ?></code></td>
                                <td><span class="laca-badge laca-badge--warning"><?php echo esc_html__('Cảnh báo', 'laca'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // ADMIN-POST HANDLERS
    // =========================================================================

    private function guardManageOptions(string $nonceAction, bool $allowWhenPluginActive = false): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'), 403);
        }

        check_admin_referer($nonceAction);

        if (!$allowWhenPluginActive && TrafficSettings::isPluginActive()) {
            $this->redirectWithNotice($this->tabUrl('overview'), 'plugin_active');
        }
    }

    public function handleBlockIp(): void
    {
        $this->guardManageOptions('laca_tm_block_ip');

        $ip          = isset($_POST['ip']) ? trim(sanitize_text_field(wp_unslash($_POST['ip']))) : '';
        $prefix      = isset($_POST['prefix']) ? absint($_POST['prefix']) : 32;
        $confirmWide = !empty($_POST['confirm_wide']);
        $returnUrl   = $this->validatedReturnUrl($this->tabUrl('overview'));

        $cidr = IpBlocklist::normalizeFromIpPrefix($ip, $prefix);
        if ($cidr === null) {
            $this->redirectWithNotice($returnUrl, 'invalid_cidr');
        }

        $res = IpBlocklist::block($cidr, '', $confirmWide);
        if (is_wp_error($res)) {
            $this->redirectWithNotice($returnUrl, $res->get_error_code());
        }

        $this->redirectWithNotice($returnUrl, 'blocked_ok', 1);
    }

    public function handleBlockSelected(): void
    {
        $this->guardManageOptions('laca_tm_block_selected');
        $this->processBulkIpBlock();
    }

    public function handleBlockUrlPage(): void
    {
        $this->guardManageOptions('laca_tm_block_url_page');
        $this->processBulkIpBlock();
    }

    private function processBulkIpBlock(): void
    {
        $returnUrl   = $this->validatedReturnUrl($this->tabUrl('overview'));
        $rawIps      = isset($_POST['ips']) && is_array($_POST['ips']) ? wp_unslash($_POST['ips']) : [];
        $prefix      = isset($_POST['prefix']) ? absint($_POST['prefix']) : 32;
        $confirmWide = !empty($_POST['confirm_wide']);

        if ($rawIps === [] || count($rawIps) > 50) {
            $this->redirectWithNotice($returnUrl, 'invalid_batch_size');
        }

        $cidrs = [];
        foreach ($rawIps as $rawIp) {
            if (!is_string($rawIp)) {
                $this->redirectWithNotice($returnUrl, 'invalid_cidr');
            }
            $ip = trim(sanitize_text_field($rawIp));
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->redirectWithNotice($returnUrl, 'invalid_cidr');
            }
            $isV6      = str_contains($ip, ':');
            $usePrefix = $prefix;
            if ($isV6 && !in_array($prefix, [128, 64], true)) {
                $usePrefix = 128;
            } elseif (!$isV6 && !in_array($prefix, [32, 24, 16, 8], true)) {
                $usePrefix = 32;
            }

            $norm = IpBlocklist::normalizeFromIpPrefix($ip, $usePrefix);
            if ($norm === null) {
                $this->redirectWithNotice($returnUrl, 'invalid_cidr');
            }
            $cidrs[] = $norm;
        }

        $res = IpBlocklist::blockMany($cidrs, '', $confirmWide);
        if (is_wp_error($res)) {
            $this->redirectWithNotice($returnUrl, $res->get_error_code());
        }

        $this->redirectWithNotice($returnUrl, 'blocked_ok', count($res['added']));
    }

    public function handleUnblockIp(): void
    {
        $this->guardManageOptions('laca_tm_unblock_ip');

        $cidr      = isset($_POST['cidr']) ? trim(sanitize_text_field(wp_unslash($_POST['cidr']))) : '';
        $returnUrl = $this->validatedReturnUrl($this->tabUrl('blocked'));

        $ok = IpBlocklist::unblock($cidr);
        $this->redirectWithNotice($returnUrl, $ok ? 'unblocked_ok' : 'unblock_none', $ok ? 1 : 0);
    }

    public function handleUnblockSelected(): void
    {
        $this->guardManageOptions('laca_tm_unblock_selected');

        $returnUrl = $this->validatedReturnUrl($this->tabUrl('blocked'));
        $rawCidrs  = isset($_POST['cidrs']) && is_array($_POST['cidrs']) ? wp_unslash($_POST['cidrs']) : [];

        if ($rawCidrs === [] || count($rawCidrs) > 50) {
            $this->redirectWithNotice($returnUrl, 'invalid_batch_size');
        }

        $clean = [];
        foreach ($rawCidrs as $c) {
            if (is_string($c)) {
                $clean[] = sanitize_text_field($c);
            }
        }

        $removed = IpBlocklist::unblockMany($clean);
        $this->redirectWithNotice($returnUrl, $removed > 0 ? 'unblocked_ok' : 'unblock_none', $removed);
    }

    public function handleToggleTrusted(): void
    {
        $this->guardManageOptions('laca_tm_toggle_trusted');

        $ip        = isset($_POST['ip']) ? trim(sanitize_text_field(wp_unslash($_POST['ip']))) : '';
        $returnUrl = $this->validatedReturnUrl($this->tabUrl('overview'));

        $res = IpBlocklist::toggleTrusted($ip);
        if (is_wp_error($res)) {
            $this->redirectWithNotice($returnUrl, $res->get_error_code());
        }

        $this->redirectWithNotice($returnUrl, $res ? 'trusted_added' : 'trusted_removed');
    }

    public function handleImportPlugin(): void
    {
        $this->guardManageOptions('laca_tm_import_lbm', true);

        $res   = IpBlocklist::importFromPlugin(get_current_user_id());
        $total = (int) $res['imported_blocks'] + (int) $res['imported_trusted'];

        $this->redirectWithNotice($this->tabUrl('blocked'), 'imported_lbm', $total);
    }

    public function handleSaveFirewall(): void
    {
        $this->guardManageOptions('laca_tm_save_firewall');

        $raw = isset($_POST['laca_tm']) && is_array($_POST['laca_tm'])
            ? wp_unslash($_POST['laca_tm'])
            : [];

        TrafficSettings::save($raw, 'firewall');
        TrafficDigest::syncSchedule();

        $this->redirectWithNotice($this->tabUrl('firewall'), 'saved');
    }

    public function handleSaveSettings(): void
    {
        $this->guardManageOptions('laca_tm_save_settings');

        $raw = isset($_POST['laca_tm']) && is_array($_POST['laca_tm'])
            ? wp_unslash($_POST['laca_tm'])
            : [];

        // Nếu không khả dụng .htaccess sync thì giữ tắt
        if (!IpBlocklist::canSyncHtaccess()) {
            $raw['htaccess_sync'] = 0;
        }

        TrafficSettings::save($raw, 'settings');

        $trustedRaw = isset($_POST['trusted_ips']) && is_string($_POST['trusted_ips'])
            ? (string) wp_unslash($_POST['trusted_ips'])
            : '';
        IpBlocklist::saveTrustedIps($trustedRaw);

        $syncRes = IpBlocklist::syncHtaccess();
        TrafficDigest::syncSchedule();
        NetworkTypeScanner::syncSchedule();

        if (is_wp_error($syncRes)) {
            $this->redirectWithNotice($this->tabUrl('settings'), $syncRes->get_error_code());
        }

        $this->redirectWithNotice($this->tabUrl('settings'), 'saved');
    }

    public function handleExportCsv(): void
    {
        $this->guardManageOptions('laca_tm_export_csv', true);

        global $wpdb;
        $rows = [];
        if (TrafficTable::isReady()) {
            $visitsTable = TrafficTable::visitsTable();
            $startDay    = wp_date('Y-m-d', time() - 6 * DAY_IN_SECONDS);

            $catCase = "CASE
                WHEN SUM(category = 'attack') > 0 THEN 'attack'
                WHEN SUM(category = 'watch') > 0 THEN 'watch'
                WHEN SUM(category = 'searchbot') > 0 THEN 'searchbot'
                WHEN SUM(category = 'bot') > 0 THEN 'bot'
                WHEN SUM(category = 'visitor') > 0 THEN 'visitor'
                ELSE 'unknown'
            END";

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ip,
                            {$catCase} AS category,
                            SUM(hits) AS hits,
                            SUM(suspicious) AS suspicious,
                            SUM(not_found) AS not_found,
                            MAX(last_seen) AS last_seen,
                            MAX(ua) AS ua,
                            MAX(reasons) AS reasons
                     FROM {$visitsTable}
                     WHERE day >= %s
                     GROUP BY ip
                     ORDER BY hits DESC
                     LIMIT 10000",
                    $startDay
                ),
                ARRAY_A
            ) ?: [];
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="laca-traffic-' . gmdate('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        // UTF-8 BOM cho Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            __('IP', 'laca'),
            __('Phân loại', 'laca'),
            __('Lượt truy cập', 'laca'),
            __('Nghi ngờ', 'laca'),
            __('404', 'laca'),
            __('Lần cuối', 'laca'),
            __('User-Agent', 'laca'),
            __('Lý do', 'laca'),
        ]);

        foreach ($rows as $row) {
            $csvRow = [
                (string) ($row['ip'] ?? ''),
                TrafficClassifier::categoryLabel((string) ($row['category'] ?? 'unknown')),
                (string) (int) ($row['hits'] ?? 0),
                (string) (int) ($row['suspicious'] ?? 0),
                (string) (int) ($row['not_found'] ?? 0),
                (string) ($row['last_seen'] ?? ''),
                (string) ($row['ua'] ?? ''),
                TrafficClassifier::reasonLabel((string) ($row['reasons'] ?? '')),
            ];

            foreach ($csvRow as &$cell) {
                if ($cell !== '' && preg_match('/^[=+\-@\t\r]/', $cell)) {
                    $cell = "'" . $cell;
                }
            }
            unset($cell);

            fputcsv($out, $csvRow);
        }

        fclose($out);
        exit;
    }

    public function handlePurge(): void
    {
        $this->guardManageOptions('laca_tm_purge');

        TrafficTable::purge();
        $this->redirectWithNotice($this->tabUrl('settings'), 'purged');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function tabUrl(string $tab): string
    {
        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'tab'  => $tab,
            ],
            admin_url('admin.php')
        );
    }

    private function currentAdminUrl(): string
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        if ($uri === '' || $host === '' || !str_starts_with($uri, '/')) {
            return $this->tabUrl('overview');
        }

        $full = set_url_scheme('http://' . $host . $uri);

        return remove_query_arg(['laca_tm_msg', 'laca_tm_n'], $full);
    }

    private function validatedReturnUrl(string $fallback): string
    {
        $raw = isset($_POST['return_url']) && is_string($_POST['return_url'])
            ? esc_url_raw(wp_unslash($_POST['return_url']))
            : '';

        $valid = wp_validate_redirect($raw, $fallback);

        return remove_query_arg(['laca_tm_msg', 'laca_tm_n'], $valid);
    }

    private function redirectWithNotice(string $url, string $code, int $num = 0): never
    {
        $args = ['laca_tm_msg' => sanitize_key($code)];
        if ($num > 0) {
            $args['laca_tm_n'] = $num;
        }

        wp_safe_redirect(add_query_arg($args, $url));
        exit;
    }

    /**
     * @return int[]
     */
    private function dayChoices(int $retention): array
    {
        $choices = [];
        foreach ([1, 7, 14, 30] as $d) {
            if ($d <= $retention) {
                $choices[] = $d;
            }
        }
        if (!in_array($retention, $choices, true) && $retention <= 30) {
            $choices[] = $retention;
            sort($choices);
        }

        return $choices !== [] ? $choices : [1];
    }

    private function categoryBadgeClass(string $cat): string
    {
        return match ($cat) {
            'attack'    => 'laca-badge--danger',
            'watch'     => 'laca-badge--warning',
            'searchbot' => 'laca-badge--accent',
            'bot'       => 'laca-badge--neutral',
            'visitor'   => 'laca-badge--success',
            default     => 'laca-badge--neutral',
        };
    }

    /**
     * @param array<string, int|string> $queryArgs
     */
    private function renderPagination(int $current, int $totalPages, array $queryArgs): void
    {
        if ($totalPages <= 1) {
            return;
        }

        $baseUrl = add_query_arg(array_merge($queryArgs, ['paged' => '%#%']), admin_url('admin.php'));
        $links   = paginate_links([
            'base'    => $baseUrl,
            'format'  => '',
            'current' => $current,
            'total'   => $totalPages,
        ]);

        if (is_string($links) && $links !== '') {
            echo '<div class="tablenav bottom"><div class="tablenav-pages laca-mt-2">' . wp_kses_post($links) . '</div></div>';
        }
    }

    /**
     * @return array{type:string, text:string}|null
     */
    private function resolveNotice(string $code, int $num): ?array
    {
        return match ($code) {
            'saved' => [
                'type' => 'success',
                'text' => __('Đã lưu cấu hình thành công.', 'laca'),
            ],
            'blocked_ok' => [
                'type' => 'success',
                'text' => $num > 0
                    ? sprintf(
                        /* translators: %d: số quy tắc chặn mới được thêm */
                        _n('Đã thêm %d quy tắc chặn mới.', 'Đã thêm %d quy tắc chặn mới.', $num, 'laca'),
                        $num
                    )
                    : __('Quy tắc chặn đã nằm trong danh sách hiện có.', 'laca'),
            ],
            'unblocked_ok' => [
                'type' => 'success',
                'text' => sprintf(
                    /* translators: %d: số quy tắc chặn được gỡ bỏ */
                    _n('Đã bỏ chặn %d quy tắc.', 'Đã bỏ chặn %d quy tắc.', max(1, $num), 'laca'),
                    max(1, $num)
                ),
            ],
            'unblock_none' => [
                'type' => 'warning',
                'text' => __('Không tìm thấy quy tắc cần bỏ chặn trong danh sách.', 'laca'),
            ],
            'trusted_added' => [
                'type' => 'success',
                'text' => __('Đã thêm địa chỉ IP vào danh sách tin cậy.', 'laca'),
            ],
            'trusted_removed' => [
                'type' => 'info',
                'text' => __('Đã gỡ địa chỉ IP khỏi danh sách tin cậy.', 'laca'),
            ],
            'imported_lbm' => [
                'type' => 'success',
                'text' => sprintf(
                    /* translators: %d: tổng số mục được nhập từ plugin Lamvt Security */
                    __('Đã nhập %d mục mới (quy tắc chặn + IP tin cậy) từ Lamvt Security.', 'laca'),
                    $num
                ),
            ],
            'purged' => [
                'type' => 'success',
                'text' => __('Đã xoá toàn bộ dữ liệu nhật ký truy cập.', 'laca'),
            ],
            'plugin_active' => [
                'type' => 'warning',
                'text' => __('Plugin Lamvt Security đang bật; hãy tắt plugin trước khi thay đổi cấu hình trên theme.', 'laca'),
            ],
            'confirm_wide_required' => [
                'type' => 'warning',
                'text' => __('Dải /8 bao phủ hơn 16 triệu địa chỉ IP. Vui lòng tích ô xác nhận trước khi chặn.', 'laca'),
            ],
            'private_or_reserved' => [
                'type' => 'danger',
                'text' => __('Từ chối chặn: không được phép chặn địa chỉ IP nội bộ (private) hoặc dải dự trữ (reserved).', 'laca'),
            ],
            'contains_current_ip' => [
                'type' => 'danger',
                'text' => __('Từ chối chặn: dải này chứa địa chỉ IP hiện tại của bạn.', 'laca'),
            ],
            'contains_server_ip' => [
                'type' => 'danger',
                'text' => __('Từ chối chặn: dải này chứa địa chỉ IP của máy chủ.', 'laca'),
            ],
            'contains_trusted_ip' => [
                'type' => 'danger',
                'text' => __('Từ chối chặn: dải này chứa địa chỉ IP đang nằm trong danh sách tin cậy.', 'laca'),
            ],
            'invalid_cidr', 'invalid_ip' => [
                'type' => 'danger',
                'text' => __('Địa chỉ IP hoặc định dạng CIDR không hợp lệ.', 'laca'),
            ],
            'invalid_batch_size' => [
                'type' => 'warning',
                'text' => __('Vui lòng chọn từ 1 đến 50 mục mỗi lần thao tác.', 'laca'),
            ],
            'blocklist_full' => [
                'type' => 'danger',
                'text' => __('Danh sách chặn đã đạt giới hạn tối đa 2.000 mục.', 'laca'),
            ],
            'trusted_full' => [
                'type' => 'danger',
                'text' => __('Danh sách IP tin cậy đã đạt giới hạn tối đa 200 mục.', 'laca'),
            ],
            'htaccess_unwritable', 'htaccess_invalid', 'htaccess_backup_failed', 'htaccess_verify_failed' => [
                'type' => 'danger',
                'text' => __('Đã lưu cài đặt trong DB nhưng không thể đồng bộ sang file .htaccess một cách an toàn.', 'laca'),
            ],
            default => null,
        };
    }
}
