<?php

namespace App\Features\GoogleServices;

use App\Settings\LacaAdmin\GoogleServicesPage;

defined('ABSPATH') || exit;

/**
 * Entity-level statistics for individual posts/pages/products/journals.
 * Provides:
 * 1. Post Editor Sidebar Metabox (GSC clicks/impr/ctr/pos + top 5 queries, GA4 views/users/duration).
 * 2. Frontend Admin Bar node with 4-metric quick view for singular content.
 */
class EntityStats
{
    public const SUPPORTED_POST_TYPES = ['post', 'page', 'product', 'journal'];
    public const CACHE_TTL = 3600; // 1 hour

    /**
     * Register hooks for EntityStats.
     */
    public static function register(): void
    {
        // Metabox in post editor
        add_action('add_meta_boxes', [self::class, 'registerMetabox']);

        // Default closed class for metabox
        add_filter('postbox_classes_post_laca_entity_stats_box', [self::class, 'setMetaboxClosed']);
        add_filter('postbox_classes_page_laca_entity_stats_box', [self::class, 'setMetaboxClosed']);
        add_filter('postbox_classes_product_laca_entity_stats_box', [self::class, 'setMetaboxClosed']);
        add_filter('postbox_classes_journal_laca_entity_stats_box', [self::class, 'setMetaboxClosed']);

        // Admin Bar on frontend
        add_action('admin_bar_menu', [self::class, 'addAdminBarNode'], 100);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAdminBarAssets']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueMetaboxAssets']);

        // AJAX endpoint
        add_action('wp_ajax_laca_google_entity_stats', [self::class, 'ajaxGetStats']);
    }

    /**
     * Check if entity stats can be shown.
     */
    public static function isAvailable(): bool
    {
        if (GoogleDashboardService::isMockMode()) {
            return true;
        }

        $state = GoogleServices::setupState();
        return !empty($state['is_any_complete']);
    }

    /**
     * Register metabox on supported post types.
     */
    public static function registerMetabox(): void
    {
        if (!self::isAvailable() || !GoogleServices::userCanViewReport()) {
            return;
        }

        global $post;
        if (!$post || !isset($post->post_status)) {
            return;
        }

        // Only published posts have meaningful external stats
        if ($post->post_status !== 'publish') {
            return;
        }

        foreach (self::SUPPORTED_POST_TYPES as $postType) {
            if (post_type_exists($postType)) {
                add_meta_box(
                    'laca_entity_stats_box',
                    __('Hiệu suất trang — 28 ngày', 'lacadev'),
                    [self::class, 'renderMetabox'],
                    $postType,
                    'side',
                    'default'
                );
            }
        }
    }

    /**
     * Add 'closed' class by default so the metabox doesn't slow down or clutter post editing.
     *
     * @param string[] $classes
     * @return string[]
     */
    public static function setMetaboxClosed(array $classes): array
    {
        if (!in_array('closed', $classes, true)) {
            $classes[] = 'closed';
        }
        return $classes;
    }

    /**
     * Render the metabox HTML shell.
     *
     * @param \WP_Post $post
     */
    public static function renderMetabox(\WP_Post $post): void
    {
        $permalink = get_permalink($post);
        $nonce = wp_create_nonce('laca_google_report_nonce');
        $ajaxUrl = admin_url('admin-ajax.php');
        ?>
        <div id="laca-entity-metabox" data-post-id="<?php echo esc_attr((string) $post->ID); ?>" data-url="<?php echo esc_url($permalink); ?>" data-ajax-url="<?php echo esc_url($ajaxUrl); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="laca-entity-loading" style="padding: 12px 0; text-align: center; color: var(--laca-fg-muted, #71717a); font-size: 13px;">
                <span class="dashicons dashicons-update spin" style="margin-right: 4px;" aria-hidden="true"></span>
                <?php esc_html_e('Đang tải dữ liệu 28 ngày...', 'lacadev'); ?>
            </div>
            <div class="laca-entity-content" style="display: none;"></div>
        </div>
        <script>
        (function() {
            var box = document.getElementById('laca-entity-metabox');
            if (!box) return;
            var loaded = false;

            function loadStats() {
                if (loaded) return;
                loaded = true;
                var url = box.getAttribute('data-url');
                var nonce = box.getAttribute('data-nonce');
                var ajaxUrl = box.getAttribute('data-ajax-url');
                var loadingEl = box.querySelector('.laca-entity-loading');
                var contentEl = box.querySelector('.laca-entity-content');

                var params = new URLSearchParams();
                params.append('action', 'laca_google_entity_stats');
                params.append('nonce', nonce);
                params.append('url', url);

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        renderStats(res.data, contentEl);
                        loadingEl.style.display = 'none';
                        contentEl.style.display = 'block';
                    } else {
                        loadingEl.innerHTML = '<span style="color:#ef4444;">' + (res && res.data && res.data.message ? res.data.message : 'Không thể tải dữ liệu.') + '</span>';
                    }
                })
                .catch(function() {
                    loadingEl.innerHTML = '<span style="color:#ef4444;">Lỗi kết nối khi tải số liệu.</span>';
                });
            }

            function renderStats(d, el) {
                var html = '';
                // GSC summary
                html += '<div style="margin-bottom: 12px;">';
                html += '<div style="font-weight: 600; font-size: 11px; text-transform: uppercase; color: var(--laca-fg-muted, #71717a); margin-bottom: 6px;">Google Search Console</div>';
                html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; text-align: center;">';
                html += '<div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 8px; border-radius: 6px;"><div style="font-size: 16px; font-weight: 700;">' + (d.gsc.clicks || 0) + '</div><div style="font-size: 11px; color: var(--laca-fg-muted, #71717a);">Lượt click</div></div>';
                html += '<div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 8px; border-radius: 6px;"><div style="font-size: 16px; font-weight: 700;">' + (d.gsc.impressions || 0) + '</div><div style="font-size: 11px; color: var(--laca-fg-muted, #71717a);">Hiển thị</div></div>';
                html += '<div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 8px; border-radius: 6px;"><div style="font-size: 16px; font-weight: 700;">' + (d.gsc.ctr || '0%') + '</div><div style="font-size: 11px; color: var(--laca-fg-muted, #71717a);">CTR</div></div>';
                html += '<div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 8px; border-radius: 6px;"><div style="font-size: 16px; font-weight: 700;">' + (d.gsc.position || '—') + '</div><div style="font-size: 11px; color: var(--laca-fg-muted, #71717a);">Vị trí TB</div></div>';
                html += '</div></div>';

                // GA4 summary
                html += '<div style="margin-bottom: 12px;">';
                html += '<div style="font-weight: 600; font-size: 11px; text-transform: uppercase; color: var(--laca-fg-muted, #71717a); margin-bottom: 6px;">Google Analytics 4</div>';
                html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; text-align: center;">';
                html += '<div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 8px; border-radius: 6px;"><div style="font-size: 16px; font-weight: 700;">' + (d.ga4.views || 0) + '</div><div style="font-size: 11px; color: var(--laca-fg-muted, #71717a);">Lượt xem</div></div>';
                html += '<div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 8px; border-radius: 6px;"><div style="font-size: 16px; font-weight: 700;">' + (d.ga4.users || 0) + '</div><div style="font-size: 11px; color: var(--laca-fg-muted, #71717a);">Người dùng</div></div>';
                html += '<div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 8px; border-radius: 6px; grid-column: span 2;"><div style="font-size: 14px; font-weight: 700;">' + (d.ga4.duration || '—') + '</div><div style="font-size: 11px; color: var(--laca-fg-muted, #71717a);">Tương tác TB</div></div>';
                html += '</div></div>';

                // Top queries
                if (d.gsc.topQueries && d.gsc.topQueries.length > 0) {
                    html += '<div>';
                    html += '<div style="font-weight: 600; font-size: 11px; text-transform: uppercase; color: var(--laca-fg-muted, #71717a); margin-bottom: 6px;">Top từ khóa (GSC)</div>';
                    html += '<ul style="margin: 0; padding: 0; list-style: none; font-size: 12px;">';
                    d.gsc.topQueries.forEach(function(q) {
                        html += '<li style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid var(--laca-border, #e4e4e7);">';
                        html += '<span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px;" title="' + q.query + '">' + q.query + '</span>';
                        html += '<strong style="color: var(--laca-fg, #18181b);">' + q.clicks + ' click</strong>';
                        html += '</li>';
                    });
                    html += '</ul></div>';
                }

                el.innerHTML = html;
            }

            // Lazy load when parent postbox is opened or visible
            var parentPostbox = box.closest('.postbox');
            if (parentPostbox) {
                if (!parentPostbox.classList.contains('closed')) {
                    loadStats();
                } else {
                    parentPostbox.addEventListener('click', function() {
                        setTimeout(function() {
                            if (!parentPostbox.classList.contains('closed')) {
                                loadStats();
                            }
                        }, 100);
                    });
                }
            } else {
                loadStats();
            }
        })();
        </script>
        <?php
    }

    /**
     * Enqueue metabox assets.
     */
    public static function enqueueMetaboxAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        if (!self::isAvailable() || !GoogleServices::userCanViewReport()) {
            return;
        }
    }

    /**
     * Add admin bar menu node on the frontend for singular content.
     *
     * @param \WP_Admin_Bar $wpAdminBar
     */
    public static function addAdminBarNode(\WP_Admin_Bar $wpAdminBar): void
    {
        if (is_admin() || !is_singular() || !is_user_logged_in()) {
            return;
        }

        if (!GoogleServices::userCanViewReport() || !self::isAvailable()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
            return;
        }

        $wpAdminBar->add_node([
            'id'    => 'laca-google-entity-stats',
            'title' => '<span class="ab-icon dashicons dashicons-chart-area" style="margin-top: 2px;"></span> <span class="ab-label">' . esc_html__('Google: 28 ngày', 'lacadev') . '</span>',
            'href'  => '#',
            'meta'  => [
                'title' => __('Xem nhanh số liệu 28 ngày qua từ Google Search Console & GA4', 'lacadev'),
                'class' => 'menupop',
            ],
        ]);

        $nonce = wp_create_nonce('laca_google_report_nonce');
        $ajaxUrl = admin_url('admin-ajax.php');
        $permalink = get_permalink($post);

        $dropdownHtml = '
            <div id="laca-adminbar-stats-container" data-url="' . esc_url($permalink) . '" data-ajax="' . esc_url($ajaxUrl) . '" data-nonce="' . esc_attr($nonce) . '" style="padding: 12px 16px; min-width: 240px; color: #333; background: #fff; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                <div class="laca-ab-loading" style="font-size: 12px; color: #666; text-align: center; padding: 6px 0;">' . esc_html__('Đang tải số liệu...', 'lacadev') . '</div>
                <div class="laca-ab-data" style="display: none;"></div>
            </div>
        ';

        $wpAdminBar->add_node([
            'id'     => 'laca-google-entity-stats-dropdown',
            'parent' => 'laca-google-entity-stats',
            'title'  => $dropdownHtml,
            'meta'   => [
                'class' => 'laca-adminbar-stats-item',
            ],
        ]);
    }

    /**
     * Enqueue Admin Bar interactive script on frontend.
     */
    public static function enqueueAdminBarAssets(): void
    {
        if (is_admin() || !is_singular() || !is_user_logged_in() || !is_admin_bar_showing()) {
            return;
        }

        if (!GoogleServices::userCanViewReport() || !self::isAvailable()) {
            return;
        }

        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            var barNode = document.getElementById('wp-admin-bar-laca-google-entity-stats');
            var container = document.getElementById('laca-adminbar-stats-container');
            if (!barNode || !container) return;

            var loaded = false;
            function loadBarStats() {
                if (loaded) return;
                loaded = true;
                var url = container.getAttribute('data-url');
                var ajaxUrl = container.getAttribute('data-ajax');
                var nonce = container.getAttribute('data-nonce');
                var loadEl = container.querySelector('.laca-ab-loading');
                var dataEl = container.querySelector('.laca-ab-data');

                var params = new URLSearchParams();
                params.append('action', 'laca_google_entity_stats');
                params.append('nonce', nonce);
                params.append('url', url);

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        var d = res.data;
                        var h = '<div style=\"display: grid; grid-template-columns: 1fr 1fr; gap: 8px; text-align: center;\">';
                        h += '<div style=\"background: #f4f4f5; padding: 6px; border-radius: 4px;\"><div style=\"font-size: 14px; font-weight: 700; color: #18181b;\">' + (d.gsc.clicks || 0) + '</div><div style=\"font-size: 10px; color: #71717a;\">Click</div></div>';
                        h += '<div style=\"background: #f4f4f5; padding: 6px; border-radius: 4px;\"><div style=\"font-size: 14px; font-weight: 700; color: #18181b;\">' + (d.gsc.impressions || 0) + '</div><div style=\"font-size: 10px; color: #71717a;\">Hiển thị</div></div>';
                        h += '<div style=\"background: #f4f4f5; padding: 6px; border-radius: 4px;\"><div style=\"font-size: 14px; font-weight: 700; color: #18181b;\">' + (d.ga4.views || 0) + '</div><div style=\"font-size: 10px; color: #71717a;\">Lượt xem</div></div>';
                        h += '<div style=\"background: #f4f4f5; padding: 6px; border-radius: 4px;\"><div style=\"font-size: 14px; font-weight: 700; color: #18181b;\">' + (d.ga4.users || 0) + '</div><div style=\"font-size: 10px; color: #71717a;\">Người dùng</div></div>';
                        h += '</div>';
                        dataEl.innerHTML = h;
                        loadEl.style.display = 'none';
                        dataEl.style.display = 'block';
                    } else {
                        loadEl.innerHTML = '<span style=\"color:#ef4444;\">Chưa có số liệu</span>';
                    }
                })
                .catch(function() {
                    loadEl.innerHTML = '<span style=\"color:#ef4444;\">Lỗi tải dữ liệu</span>';
                });
            }

            barNode.addEventListener('mouseenter', loadBarStats);
            barNode.addEventListener('click', loadBarStats);
        });
        ";

        wp_add_inline_script('admin-bar', $script);
    }

    /**
     * AJAX endpoint to fetch entity stats for a specific URL.
     */
    public static function ajaxGetStats(): void
    {
        $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? '';
        $valid = wp_verify_nonce($nonce, 'laca_google_report_nonce') || wp_verify_nonce($nonce, 'laca_seo_admin_nonce');
        if (!$valid) {
            wp_die('-1', 403);
        }

        if (!GoogleServices::userCanViewReport()) {
            wp_send_json_error(['message' => __('Bạn không có quyền xem báo cáo.', 'lacadev')], 403);
        }

        $targetUrl = esc_url_raw(wp_unslash($_REQUEST['url'] ?? ''));
        if (empty($targetUrl)) {
            wp_send_json_error(['message' => __('Thiếu tham số URL.', 'lacadev')], 400);
        }

        $cacheKey = 'laca_entity_stats_' . md5($targetUrl);
        $refresh = !empty($_REQUEST['refresh']);

        if (!$refresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                wp_send_json_success($cached);
            }
        }

        if (GoogleDashboardService::isMockMode()) {
            $mockData = self::getMockData($targetUrl);
            set_transient($cacheKey, $mockData, self::CACHE_TTL);
            wp_send_json_success($mockData);
        }

        $data = self::fetchLiveStats($targetUrl);
        set_transient($cacheKey, $data, self::CACHE_TTL);

        wp_send_json_success($data);
    }

    /**
     * Fetch live stats from GSC and GA4 for the given URL.
     *
     * @param string $url
     * @return array<string, mixed>
     */
    public static function fetchLiveStats(string $url): array
    {
        $range = DateRange::get('28d');
        $siteUrl = GscClient::getSiteUrl();

        $gscResult = [
            'clicks'      => 0,
            'impressions' => 0,
            'ctr'         => '0%',
            'position'    => '—',
            'topQueries'  => [],
        ];

        // 1. Fetch GSC data for this exact URL
        if (!empty($siteUrl) && GscOAuth::isConnected()) {
            // A. Totals for page equals $url
            $bodyTotals = [
                'startDate'            => $range['startDate'],
                'endDate'              => $range['endDate'],
                'dataState'            => 'all',
                'dimensionFilterGroups' => [
                    [
                        'filters' => [
                            [
                                'dimension'  => 'page',
                                'operator'   => 'equals',
                                'expression' => $url,
                            ],
                        ],
                    ],
                ],
            ];

            $resTotals = GscClient::searchAnalytics($siteUrl, $bodyTotals);
            if (!is_wp_error($resTotals) && !empty($resTotals['rows'][0])) {
                $row = $resTotals['rows'][0];
                $gscResult['clicks']      = (int) ($row['clicks'] ?? 0);
                $gscResult['impressions'] = (int) ($row['impressions'] ?? 0);
                $gscResult['ctr']         = round(((float) ($row['ctr'] ?? 0)) * 100, 1) . '%';
                $gscResult['position']    = round((float) ($row['position'] ?? 0), 1);
            }

            // B. Top 5 queries for this page
            $bodyQueries = [
                'startDate'            => $range['startDate'],
                'endDate'              => $range['endDate'],
                'dimensions'           => ['query'],
                'rowLimit'             => 5,
                'dataState'            => 'all',
                'dimensionFilterGroups' => [
                    [
                        'filters' => [
                            [
                                'dimension'  => 'page',
                                'operator'   => 'equals',
                                'expression' => $url,
                            ],
                        ],
                    ],
                ],
            ];

            $resQueries = GscClient::searchAnalytics($siteUrl, $bodyQueries);
            if (!is_wp_error($resQueries) && !empty($resQueries['rows'])) {
                foreach ($resQueries['rows'] as $qRow) {
                    $qKey = $qRow['keys'][0] ?? '';
                    if (!empty($qKey)) {
                        $gscResult['topQueries'][] = [
                            'query'  => $qKey,
                            'clicks' => (int) ($qRow['clicks'] ?? 0),
                        ];
                    }
                }
            }
        }

        // 2. Fetch GA4 data for this page path
        $ga4Result = [
            'views'    => 0,
            'users'    => 0,
            'duration' => '—',
        ];

        $opts = get_option(GoogleServices::OPTION_KEY, []);
        $propertyId = (string) ($opts['ga4_property_id'] ?? '');

        if (!empty($propertyId) && GscOAuth::isConnected()) {
            $parsedUrl = parse_url($url);
            $pagePath = $parsedUrl['path'] ?? '/';

            $gaBody = [
                'dateRanges' => [
                    [
                        'startDate' => $range['startDate'],
                        'endDate'   => $range['endDate'],
                    ],
                ],
                'dimensions' => [
                    ['name' => 'pagePath'],
                ],
                'metrics' => [
                    ['name' => 'screenPageViews'],
                    ['name' => 'activeUsers'],
                    ['name' => 'userEngagementDuration'],
                ],
                'dimensionFilter' => [
                    'andGroup' => [
                        'expressions' => [
                            [
                                'filter' => [
                                    'fieldName'    => 'pagePath',
                                    'stringFilter' => [
                                        'matchType' => 'EXACT',
                                        'value'     => $pagePath,
                                    ],
                                ],
                            ],
                            GaClient::getHostFilter(),
                        ],
                    ],
                ],
                'limit' => 1,
            ];

            $resGa = GaClient::queryReport($propertyId, $gaBody);
            if (!is_wp_error($resGa) && !empty($resGa['rows'][0]['metricValues'])) {
                $mv = $resGa['rows'][0]['metricValues'];
                $views = (int) ($mv[0]['value'] ?? 0);
                $users = (int) ($mv[1]['value'] ?? 0);
                $durSec = (float) ($mv[2]['value'] ?? 0);

                $ga4Result['views'] = $views;
                $ga4Result['users'] = $users;

                if ($users > 0 && $durSec > 0) {
                    $avgSec = (int) round($durSec / $users);
                    $m = (int) floor($avgSec / 60);
                    $s = $avgSec % 60;
                    $ga4Result['duration'] = "{$m}m {$s}s";
                }
            }
        }

        return [
            'url'  => $url,
            'gsc'  => $gscResult,
            'ga4'  => $ga4Result,
        ];
    }

    /**
     * Provide mock data for local development / testing.
     *
     * @param string $url
     * @return array<string, mixed>
     */
    public static function getMockData(string $url): array
    {
        return [
            'url' => $url,
            'gsc' => [
                'clicks'      => 42,
                'impressions' => 680,
                'ctr'         => '6.2%',
                'position'    => 4.8,
                'topQueries'  => [
                    ['query' => 'cà phê specialty đà nẵng', 'clicks' => 18],
                    ['query' => 'lix roastery', 'clicks' => 12],
                    ['query' => 'hạt cà phê arabica cầu đất', 'clicks' => 7],
                ],
            ],
            'ga4' => [
                'views'    => 128,
                'users'    => 95,
                'duration' => '1m 45s',
            ],
        ];
    }
}
