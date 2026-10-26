<?php

namespace App\Settings\LacaAdmin;

use App\Features\LacaSeo\Admin\Renderer;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\GeneralFields;
use App\Features\LacaSeo\Frontend\Tracking;
use App\Features\LacaSeo\Modules\GoogleAnalytics\GaClient;
use App\Features\LacaSeo\Modules\SearchConsole\GscClient;
use App\Features\LacaSeo\Modules\SearchConsole\GscOAuth;

defined('ABSPATH') || exit;

/**
 * Manages Google Services pages under Laca Admin:
 * - Google Analytics & GTM (admin.php?page=laca-google-analytics)
 * - Google Search Console (admin.php?page=laca-google-search-console)
 *
 * Uses laca_seo_general as the single source of truth.
 */
class GoogleServicesPage
{
    public const PAGE_ANALYTICS = 'laca-google-analytics';
    public const PAGE_SEARCH_CONSOLE = 'laca-google-search-console';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerPages']);
        add_action('admin_init', [$this, 'handleActions']);
        add_action('admin_init', [$this, 'handleOAuthCallback']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerPages(): void
    {
        add_submenu_page(
            'laca-admin',
            __('Google Analytics & GTM', 'laca'),
            __('Google Analytics', 'laca'),
            'manage_options',
            self::PAGE_ANALYTICS,
            [$this, 'renderAnalyticsPage']
        );

        add_submenu_page(
            'laca-admin',
            __('Google Search Console', 'laca'),
            __('Search Console', 'laca'),
            'manage_options',
            self::PAGE_SEARCH_CONSOLE,
            [$this, 'renderSearchConsolePage']
        );
    }

    public function enqueueAssets(): void
    {
        $currentPage = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if ($currentPage !== self::PAGE_ANALYTICS && $currentPage !== self::PAGE_SEARCH_CONSOLE) {
            return;
        }

        wp_enqueue_media();

        $stylePath = get_theme_file_path('dist/styles/laca-seo.css');
        $styleUri  = get_theme_file_uri('dist/styles/laca-seo.css');
        if (file_exists($stylePath)) {
            wp_enqueue_style(
                'laca-seo-admin',
                $styleUri,
                ['dashicons'],
                (string) filemtime($stylePath)
            );
        }

        $scriptPath = get_theme_file_path('dist/laca-seo.js');
        $scriptUri  = get_theme_file_uri('dist/laca-seo.js');
        if (file_exists($scriptPath)) {
            wp_enqueue_script(
                'laca-seo-admin',
                $scriptUri,
                ['wp-i18n'],
                (string) filemtime($scriptPath),
                true
            );

            wp_localize_script('laca-seo-admin', 'lacaSeoData', [
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('laca_seo_admin_nonce'),
                'variables' => class_exists(\App\Features\LacaSeo\Core\Variables::class)
                    ? \App\Features\LacaSeo\Core\Variables::getAvailableList()
                    : [],
                'i18n'      => [
                    'confirmReset' => __('Bạn có chắc chắn muốn khôi phục mặc định toàn bộ cài đặt không?', 'laca'),
                    'saved'        => __('Đã lưu cài đặt thành công.', 'laca'),
                    'copied'       => __('Đã sao chép vào bộ nhớ tạm!', 'laca'),
                ],
            ]);
        }
    }

    /**
     * Handle actions: OAuth initiation, disconnect, and property selection.
     */
    public function handleActions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 1. Connect OAuth init
        if (isset($_POST['laca_google_connect_init'])) {
            check_admin_referer('laca_google_connect_action');

            $clientId     = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
            $clientSecret = sanitize_text_field(wp_unslash($_POST['client_secret'] ?? ''));
            $returnTo     = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_SEARCH_CONSOLE));

            if ($clientSecret === '') {
                $clientSecret = (string) (GscOAuth::getCredentials()['client_secret'] ?? '');
            }

            if ($clientId === '' || $clientSecret === '') {
                wp_safe_redirect(add_query_arg(['error' => urlencode(__('Vui lòng nhập đầy đủ Client ID và Client Secret.', 'laca'))], admin_url('admin.php?page=' . $returnTo)));
                exit;
            }

            update_option('laca_seo_gsc_pending', [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ], false);

            $authUrl = GscOAuth::buildAuthUrl($clientId, null, $returnTo, GscOAuth::SCOPES);
            wp_redirect($authUrl);
            exit;
        }

        // 2. Grant additional scope (e.g. Analytics)
        if (isset($_POST['laca_google_grant_scope'])) {
            check_admin_referer('laca_google_grant_scope_action');

            $creds = GscOAuth::getCredentials();
            $clientId = $creds['client_id'] ?? '';
            $clientSecret = $creds['client_secret'] ?? '';
            $returnTo = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_ANALYTICS));

            if (empty($clientId) || empty($clientSecret)) {
                wp_safe_redirect(add_query_arg(['error' => urlencode(__('Chưa có thông tin Client ID. Vui lòng kết nối tài khoản Google trước.', 'laca'))], admin_url('admin.php?page=' . $returnTo)));
                exit;
            }

            update_option('laca_seo_gsc_pending', [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ], false);

            $authUrl = GscOAuth::buildAuthUrl($clientId, null, $returnTo, GscOAuth::SCOPES);
            wp_redirect($authUrl);
            exit;
        }

        // 3. Disconnect
        if (isset($_POST['laca_google_disconnect'])) {
            check_admin_referer('laca_google_disconnect_action');
            $returnTo = sanitize_text_field(wp_unslash($_POST['return_to'] ?? self::PAGE_SEARCH_CONSOLE));

            GscOAuth::disconnect();
            wp_safe_redirect(add_query_arg(['disconnected' => '1'], admin_url('admin.php?page=' . $returnTo)));
            exit;
        }

        // 4. Save GSC Site URL selection
        if (isset($_POST['laca_save_gsc_site_url'])) {
            check_admin_referer('laca_save_gsc_site_url_action');
            $selectedUrl = esc_url_raw(wp_unslash($_POST['gsc_site_url'] ?? ''));
            if (empty($selectedUrl) && !empty($_POST['gsc_site_url'])) {
                // Support sc-domain: format which is not a regular URL
                $selectedUrl = sanitize_text_field(wp_unslash($_POST['gsc_site_url']));
            }

            $current = Settings::getGroup('general');
            $current['gsc_site_url'] = $selectedUrl;
            Settings::updateGroup('general', $current);

            wp_safe_redirect(add_query_arg(['updated' => '1'], admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)));
            exit;
        }

        // 4b. Refresh GSC sites list
        if (isset($_POST['laca_refresh_gsc_sites'])) {
            check_admin_referer('laca_refresh_gsc_sites_action');
            delete_transient('laca_gsc_sites_list');
            wp_safe_redirect(add_query_arg(['refreshed' => '1'], admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)));
            exit;
        }

        // 5. Save GA4 Property ID selection
        if (isset($_POST['laca_save_ga4_property'])) {
            check_admin_referer('laca_save_ga4_property_action');
            $propertyId = sanitize_text_field(wp_unslash($_POST['ga4_property_id'] ?? ''));
            $measurementId = sanitize_text_field(wp_unslash($_POST['ga4_measurement_id'] ?? ''));

            $current = Settings::getGroup('general');
            $current['ga4_property_id'] = $propertyId;
            if (!empty($measurementId)) {
                $current['ga4_measurement_id'] = $measurementId;
            }
            Settings::updateGroup('general', $current);

            wp_safe_redirect(add_query_arg(['updated' => '1'], admin_url('admin.php?page=' . self::PAGE_ANALYTICS)));
            exit;
        }
    }

    /**
     * Handle OAuth callback from Google and legacy POST actions from Laca SEO Search Console view.
     * Runs early on admin_init before any HTML or headers are printed.
     */
    public function handleOAuthCallback(): void
    {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        $view = sanitize_key(wp_unslash($_GET['view'] ?? ''));

        if ($page !== 'laca-seo' || $view !== 'search-console') {
            return;
        }

        // 1. Legacy POST disconnect
        if (isset($_POST['laca_gsc_disconnect'])) {
            check_admin_referer('laca_gsc_disconnect_action');
            if (current_user_can('manage_options')) {
                GscOAuth::disconnect();
                wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE . '&disconnected=1'));
                exit;
            }
        }

        // 2. Legacy POST connect_init
        if (isset($_POST['laca_gsc_connect_init'])) {
            check_admin_referer('laca_gsc_connect_action');
            if (current_user_can('manage_options')) {
                $clientId     = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
                $clientSecret = sanitize_text_field(wp_unslash($_POST['client_secret'] ?? ''));

                if ($clientSecret === '') {
                    $clientSecret = (string) (GscOAuth::getCredentials()['client_secret'] ?? '');
                }

                if ($clientId === '' || $clientSecret === '') {
                    wp_safe_redirect(add_query_arg(['error' => urlencode(__('Vui lòng nhập đầy đủ Client ID và Client Secret.', 'laca'))], admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)));
                    exit;
                }

                update_option('laca_seo_gsc_pending', [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                ], false);

                $authUrl = GscOAuth::buildAuthUrl($clientId, null, self::PAGE_SEARCH_CONSOLE, GscOAuth::SCOPES);
                wp_redirect($authUrl);
                exit;
            }
        }

        // 3. OAuth callback from Google (redirect_uri points to admin.php?page=laca-seo&view=search-console&laca_gsc_oauth=1)
        if (isset($_GET['laca_gsc_oauth'])) {
            $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
            $returnTo = GscOAuth::getStateReturnTo($state);

            if (isset($_GET['error'])) {
                if (!GscOAuth::verifyState($state)) {
                    $connectError = __('Mã trạng thái bảo mật (State Nonce) không hợp lệ.', 'laca');
                } else {
                    delete_option('laca_seo_gsc_pending');
                    $errorCode = sanitize_text_field(wp_unslash($_GET['error']));
                    $errorDesc = sanitize_text_field(wp_unslash($_GET['error_description'] ?? ''));

                    if ($errorCode === 'access_denied') {
                        $connectError = __('Google từ chối quyền truy cập. Nếu ứng dụng OAuth đang ở chế độ Testing, hãy thêm email của bạn vào Test users (Google Cloud → Google Auth Platform → Audience) hoặc bấm Publish app, rồi thử lại.', 'laca');
                    } else {
                        $connectError = sprintf(
                            __('Lỗi kết nối từ Google (%s)%s.', 'laca'),
                            esc_html($errorCode),
                            $errorDesc !== '' ? ': ' . esc_html($errorDesc) : ''
                        );
                    }
                }
                wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
                exit;
            } elseif (isset($_GET['code'])) {
                $code = sanitize_text_field(wp_unslash($_GET['code']));
                if (!GscOAuth::verifyState($state)) {
                    $connectError = __('Mã trạng thái bảo mật (State Nonce) không hợp lệ.', 'laca');
                    wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
                    exit;
                }

                $pending = get_option('laca_seo_gsc_pending', []);
                delete_option('laca_seo_gsc_pending');

                if (!empty($pending['client_id']) && !empty($pending['client_secret'])) {
                    $exchange = GscOAuth::handleCallback($code, $pending['client_id'], $pending['client_secret']);
                    if ($exchange['success']) {
                        wp_safe_redirect(add_query_arg(['connected' => '1'], admin_url($returnTo)));
                        exit;
                    }

                    $connectError = $exchange['error'] ?? __('Lỗi xác thực OAuth từ Google.', 'laca');
                    wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
                    exit;
                }

                $connectError = __('Không tìm thấy Client ID hoặc Client Secret đang chờ xác thực.', 'laca');
                wp_safe_redirect(add_query_arg(['error' => urlencode($connectError)], admin_url($returnTo)));
                exit;
            }
        }
    }

    /**
     * Render Google Analytics & GTM page.
     */
    public function renderAnalyticsPage(): void
    {
        $isConnected = GscOAuth::isConnected();
        $hasGaScope  = GscOAuth::hasAnalyticsScope();
        $manualIds   = Tracking::findManualGoogleIds();

        $allFields   = GeneralFields::get();
        $tabFields   = $allFields['analytics'] ?? [];
        $currentOpts = Settings::getGroup('general');

        $currentPropId = (string) Settings::get('general.ga4_property_id', '');
        $currentMsId   = (string) Settings::get('general.ga4_measurement_id', '');

        // Auto-discover property if connected with GA scope and property ID is missing
        $gaProperties = [];
        if ($isConnected && $hasGaScope) {
            $gaPropertiesResult = GaClient::listProperties();
            $gaProperties = is_wp_error($gaPropertiesResult) ? [] : $gaPropertiesResult;
            if (empty($currentPropId) && !empty($currentMsId)) {
                $foundProp = GaClient::findPropertyByMeasurementId($currentMsId);
                if ($foundProp && !empty($foundProp['propertyId'])) {
                    $currentPropId = $foundProp['propertyId'];
                    $currentOpts['ga4_property_id'] = $currentPropId;
                    Settings::updateGroup('general', $currentOpts);
                }
            }
        }

        ?>
        <div class="wrap laca-admin-page laca-seo-view-general">
            <h1><?php echo esc_html__('Google Analytics & Tag Manager', 'laca'); ?></h1>

            <?php $this->renderNotices(); ?>

            <!-- Alert chống đếm trùng mã -->
            <?php if (!empty($manualIds)): ?>
                <div class="notice notice-warning inline" style="margin: 16px 0; padding: 12px 16px; border-left-color: #f59e0b;">
                    <p style="margin: 0; font-size: 13.5px; line-height: 1.6;">
                        <strong>⚠️ <?php esc_html_e('Cảnh báo phát hiện mã theo dõi thủ công:', 'laca'); ?></strong><br>
                        <?php
                        printf(
                            esc_html__('Đang có mã %s được chèn thủ công ở Laca Theme → Scripts (Header/Footer). Hệ thống sẽ tự động bỏ qua (không xuất thêm) nếu Measurement ID hoặc Container ID bên dưới trùng khớp để tránh đếm 2 lần.', 'laca'),
                            '<code>' . esc_html(implode(', ', $manualIds)) . '</code>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Khối kết nối Google OAuth cho GA4 Data API -->
            <div class="laca-seo-card" style="margin-bottom: 24px;">
                <div class="laca-seo-card__header laca-seo-header-flex">
                    <div>
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Đồng bộ Google Analytics 4 (Google Cloud OAuth)', 'laca'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Kết nối OAuth để tự động lấy số liệu lượt truy cập, người dùng hoạt động và xem báo cáo trực tiếp trên Dashboard.', 'laca'); ?></p>
                    </div>
                    <?php if ($isConnected && $hasGaScope): ?>
                        <span class="laca-badge" style="background: #10b981; color: #fff; padding: 4px 10px; border-radius: 9999px; font-weight: 500; font-size: 12px;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 14px; line-height: 1.3;"></span> <?php esc_html_e('Đã cấp quyền Analytics', 'laca'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="laca-seo-fields-container" style="padding: 20px;">
                    <?php if (!$isConnected): ?>
                        <div style="background: var(--laca-bg-subtle, #f4f4f5); padding: 16px; border-radius: 6px; margin-bottom: 16px;">
                            <p style="margin: 0 0 12px 0; font-size: 13.5px;">
                                <?php esc_html_e('Chưa kết nối tài khoản Google Cloud OAuth. Vui lòng thiết lập Client ID và Client Secret tại tab Search Console hoặc bấm nút bên dưới:', 'laca'); ?>
                            </p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SEARCH_CONSOLE)); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-google" style="margin-top: 3px;"></span> <?php esc_html_e('Cấu hình kết nối Google OAuth', 'laca'); ?>
                            </a>
                        </div>
                    <?php elseif (!$hasGaScope): ?>
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 16px; border-radius: 6px; margin-bottom: 16px;">
                            <p style="margin: 0 0 12px 0; font-size: 13.5px; color: #1e40af;">
                                <strong><?php esc_html_e('Tài khoản Google đã kết nối nhưng chưa có quyền đọc Google Analytics.', 'laca'); ?></strong><br>
                                <?php esc_html_e('Vui lòng cấp thêm quyền Google Analytics (scope: analytics.readonly) để lấy dữ liệu báo cáo.', 'laca'); ?>
                            </p>
                            <form method="post">
                                <?php wp_nonce_field('laca_google_grant_scope_action'); ?>
                                <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_ANALYTICS); ?>">
                                <button type="submit" name="laca_google_grant_scope" class="button button-primary">
                                    <span class="dashicons dashicons-shield" style="margin-top: 3px;"></span> <?php esc_html_e('Cấp thêm quyền Google Analytics', 'laca'); ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Đã kết nối & có quyền GA: Chọn thuộc tính GA4 -->
                        <form method="post" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                            <?php wp_nonce_field('laca_save_ga4_property_action'); ?>
                            <div style="flex: 1; min-width: 320px;">
                                <label for="ga4_property_select" style="display: block; font-weight: 600; margin-bottom: 6px;">
                                    <?php esc_html_e('Thuộc tính GA4 (Property) được chọn để lấy báo cáo:', 'laca'); ?>
                                </label>
                                <?php if (empty($gaProperties)): ?>
                                    <input type="text" name="ga4_property_id" id="ga4_property_select" class="regular-text" value="<?php echo esc_attr($currentPropId); ?>" placeholder="Ví dụ: 123456789">
                                    <p class="description"><?php esc_html_e('Nhập ID thuộc tính GA4 (dãy số dạng 123456789).', 'laca'); ?></p>
                                <?php else: ?>
                                    <select name="ga4_property_id" id="ga4_property_select" style="width: 100%; max-width: 560px;">
                                        <option value=""><?php esc_html_e('-- Chọn thuộc tính GA4 --', 'laca'); ?></option>
                                        <?php foreach ($gaProperties as $prop): ?>
                                            <option value="<?php echo esc_attr($prop['propertyId']); ?>" <?php selected($currentPropId, $prop['propertyId']); ?>>
                                                <?php echo esc_html($prop['displayName'] . ' (ID: ' . $prop['propertyId'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="laca_save_ga4_property" class="button button-secondary">
                                <?php esc_html_e('Lưu thuộc tính GA4', 'laca'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form cấu hình chi tiết GA4 & GTM (Dùng chung với laca_seo_general) -->
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-seo-form">
                <?php wp_nonce_field('laca_seo_save_action', 'laca_seo_nonce'); ?>
                <input type="hidden" name="action" value="laca_seo_save">
                <input type="hidden" name="option_group" value="general">
                <input type="hidden" name="tab" value="analytics">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_ANALYTICS); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_ANALYTICS)); ?>">

                <div class="laca-seo-card">
                    <div class="laca-seo-card__header">
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Cấu hình mã đo lường & Script chèn', 'laca'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Dữ liệu lưu vào cài đặt chung của theme (laca_seo_general), tự động tối ưu hóa và chống đếm lặp.', 'laca'); ?></p>
                    </div>

                    <div class="laca-seo-fields-container">
                        <?php
                        $displayFields = array_filter($tabFields, static function ($f) {
                            // Ẩn 2 field internal ra khỏi list field thường vì đã có selector riêng
                            return !in_array($f->getId(), ['ga4_property_id', 'gsc_site_url'], true);
                        });
                        foreach ($displayFields as $field):
                            $val = $currentOpts[$field->getId()] ?? $field->getDefault();
                            Renderer::renderFieldRow($field, $val);
                        endforeach;
                        ?>
                    </div>
                </div>

                <div class="laca-seo-sticky-footer" style="margin-top: 24px;">
                    <div class="laca-seo-sticky-footer__inner">
                        <button type="submit" class="button button-primary button-hero laca-seo-save-btn">
                            <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Lưu thay đổi', 'laca'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render Google Search Console page.
     */
    public function renderSearchConsolePage(): void
    {
        $isConnected    = GscOAuth::isConnected();
        $creds          = GscOAuth::getCredentials();
        $redirectUri    = GscOAuth::getRedirectUri();
        $savedSiteUrl   = (string) \App\Features\LacaSeo\Core\Settings::get('general.gsc_site_url', '');
        $connectedEmail = $isConnected ? GscOAuth::getConnectedEmail() : null;

        $sitesResult = $isConnected ? GscClient::listSites() : [];
        $sites = is_wp_error($sitesResult) ? [] : $sitesResult;

        $hasSavedInSites = false;
        foreach ($sites as $item) {
            $url = is_array($item) ? ($item['siteUrl'] ?? '') : (string) $item;
            if ($url === $savedSiteUrl) {
                $hasSavedInSites = true;
                break;
            }
        }

        if ($isConnected && (!$hasSavedInSites || empty($savedSiteUrl)) && !empty($sites)) {
            $siteUrl = GscClient::selectBestSiteUrl($sites);
            if (!empty($siteUrl)) {
                $currentOpts = Settings::getGroup('general');
                $currentOpts['gsc_site_url'] = $siteUrl;
                Settings::updateGroup('general', $currentOpts);
            } else {
                $siteUrl = GscClient::getSiteUrl();
            }
        } else {
            $siteUrl = !empty($savedSiteUrl) ? $savedSiteUrl : GscClient::getSiteUrl();
        }

        ?>
        <div class="wrap laca-admin-page laca-seo-view-search-console">
            <h1><?php echo esc_html__('Google Search Console', 'laca'); ?></h1>

            <?php $this->renderNotices(); ?>

            <!-- Khối kết nối Google OAuth -->
            <div class="laca-seo-card" style="margin-bottom: 24px;">
                <div class="laca-seo-card__header laca-seo-header-flex">
                    <div>
                        <h2 class="laca-seo-card__title"><?php esc_html_e('Kết nối Google OAuth 2.0', 'laca'); ?></h2>
                        <p class="laca-seo-card__subtitle"><?php esc_html_e('Kết nối dùng chung cho cả Google Search Console và Google Analytics 4.', 'laca'); ?></p>
                    </div>
                    <?php if ($isConnected): ?>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span class="laca-badge" style="background: #10b981; color: #fff; padding: 4px 10px; border-radius: 9999px; font-weight: 500; font-size: 12px;">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 14px; line-height: 1.3;"></span> <?php esc_html_e('Đã kết nối OAuth', 'laca'); ?>
                            </span>
                            <form method="post" style="margin: 0;">
                                <?php wp_nonce_field('laca_google_disconnect_action'); ?>
                                <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_SEARCH_CONSOLE); ?>">
                                <button type="submit" name="laca_google_disconnect" class="button button-secondary" onclick="return confirm('Bạn có chắc muốn ngắt kết nối Google OAuth không?');">
                                    <?php esc_html_e('Ngắt kết nối', 'laca'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="laca-seo-fields-container" style="padding: 20px;">
                    <?php if ($isConnected && !empty($connectedEmail)): ?>
                        <div style="font-size: 13px; color: var(--laca-fg-muted, #71717a); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-admin-users" style="font-size: 16px; width: 16px; height: 16px;"></span>
                            <span><?php printf(esc_html__('Đang kết nối bằng tài khoản: %s', 'laca'), '<strong>' . esc_html($connectedEmail) . '</strong>'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php $this->renderGoogleCloudPrepDetails($redirectUri); ?>

                    <?php if (!$isConnected): ?>
                        <form method="post" class="laca-seo-form-limit" style="margin-top: 20px;">
                            <?php wp_nonce_field('laca_google_connect_action'); ?>
                            <input type="hidden" name="return_to" value="<?php echo esc_attr(self::PAGE_SEARCH_CONSOLE); ?>">

                            <div class="laca-seo-field-group" style="margin-bottom: 16px;">
                                <label for="laca_google_client_id"><strong><?php esc_html_e('Google Client ID', 'laca'); ?></strong></label>
                                <input type="text" name="client_id" id="laca_google_client_id" class="large-text" required placeholder="xxxx.apps.googleusercontent.com" value="<?php echo esc_attr($creds['client_id'] ?? ''); ?>" style="max-width: 560px;">
                            </div>

                            <div class="laca-seo-field-group" style="margin-bottom: 20px;">
                                <label for="laca_google_client_secret"><strong><?php esc_html_e('Google Client Secret', 'laca'); ?></strong></label>
                                <?php // Không in secret ra HTML; để trống = giữ secret đã lưu. ?>
                                <input type="password" name="client_secret" id="laca_google_client_secret" class="large-text" autocomplete="new-password" <?php echo empty($creds['client_secret']) ? 'required placeholder="GOCSPX-xxxx"' : 'placeholder="' . esc_attr__('Đã lưu — để trống để giữ nguyên', 'laca') . '"'; ?> style="max-width: 560px;">
                            </div>

                            <button type="submit" name="laca_google_connect_init" class="button button-primary button-large">
                                <span class="dashicons dashicons-google" aria-hidden="true" style="margin-top: 3px;"></span> <?php esc_html_e('Đăng nhập & Cấp quyền với Google', 'laca'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Đã kết nối: Hiển thị Property Selector -->
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--laca-border, #e4e4e7);">
                            <?php if (empty($sites)): ?>
                                <div class="laca-alert laca-alert--warning" role="alert" style="margin-bottom: 16px;">
                                    <span class="dashicons dashicons-warning laca-alert__icon" aria-hidden="true"></span>
                                    <div class="laca-alert__content">
                                        <p style="margin: 0 0 10px 0;">
                                            <?php
                                            $accText = !empty($connectedEmail) ? ' ' . esc_html($connectedEmail) : '';
                                            printf(
                                                esc_html__('Tài khoản Google%s chưa có property nào trong Search Console — thêm site tại %s rồi bấm Tải lại.', 'laca'),
                                                $accText,
                                                '<a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a>'
                                            );
                                            ?>
                                        </p>
                                        <form method="post" style="margin: 0; display: inline-block;">
                                            <?php wp_nonce_field('laca_refresh_gsc_sites_action'); ?>
                                            <button type="submit" name="laca_refresh_gsc_sites" class="button button-secondary button-small">
                                                <span class="dashicons dashicons-update" style="margin-top: 2px;"></span> <?php esc_html_e('Tải lại danh sách', 'laca'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="post" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                                    <?php wp_nonce_field('laca_save_gsc_site_url_action'); ?>
                                    <div style="flex: 1; min-width: 320px;">
                                        <label for="gsc_site_url_select" style="display: block; font-weight: 600; margin-bottom: 6px;">
                                            <?php esc_html_e('Trang web / Property Search Console được chọn:', 'laca'); ?>
                                        </label>
                                        <select name="gsc_site_url" id="gsc_site_url_select" style="width: 100%; max-width: 560px;">
                                            <?php foreach ($sites as $siteEntry): ?>
                                                <?php
                                                $optUrl = is_array($siteEntry) ? ($siteEntry['siteUrl'] ?? '') : (string) $siteEntry;
                                                $perm   = is_array($siteEntry) ? ($siteEntry['permissionLevel'] ?? 'siteOwner') : 'siteOwner';
                                                $isUnverified = ($perm === 'siteUnverifiedUser');
                                                $label  = $optUrl . ($isUnverified ? ' (' . __('chưa xác minh', 'laca') . ')' : '');
                                                ?>
                                                <option value="<?php echo esc_attr($optUrl); ?>" <?php selected($siteUrl, $optUrl); ?> <?php disabled($isUnverified); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="laca_save_gsc_site_url" class="button button-secondary">
                                        <?php esc_html_e('Lưu Property', 'laca'); ?>
                                    </button>
                                    <button type="submit" name="laca_refresh_gsc_sites" class="button button-secondary" title="<?php esc_attr_e('Tải lại danh sách property từ Google', 'laca'); ?>">
                                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> <?php esc_html_e('Tải lại danh sách', 'laca'); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Link sang báo cáo chi tiết -->
                        <div style="margin-top: 24px; padding: 16px; background: var(--laca-bg-subtle, #f4f4f5); border-radius: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <strong style="font-size: 14px;"><?php esc_html_e('Báo cáo thứ hạng & từ khóa tìm kiếm', 'laca'); ?></strong>
                                <p style="margin: 4px 0 0 0; color: var(--laca-fg-muted, #71717a); font-size: 13px;">
                                    <?php printf(esc_html__('Xem biểu đồ lượt click, lượt hiển thị, CTR và top từ khóa cho %s', 'laca'), '<code>' . esc_html($siteUrl) . '</code>'); ?>
                                </p>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=laca-seo&view=search-console')); ?>" class="button button-primary">
                                <span class="dashicons dashicons-chart-area" style="margin-top: 3px;"></span> <?php esc_html_e('Xem Báo cáo Search Console', 'laca'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render common Google Cloud preparation instructions.
     */
    private function renderGoogleCloudPrepDetails(string $redirectUri): void
    {
        ?>
        <details class="laca-seo-prep-details" open style="border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; padding: 12px 16px; background: var(--laca-bg-card, #fff);">
            <summary class="laca-seo-prep-details__summary" style="cursor: pointer; font-weight: 600;">
                <span class="dashicons dashicons-info" style="color: var(--laca-accent, #2563eb);"></span>
                <?php esc_html_e('Hướng dẫn chuẩn bị Google Cloud Console (Bắt buộc)', 'laca'); ?>
            </summary>
            <div class="laca-seo-prep-details__content" style="margin-top: 12px; font-size: 13.5px; line-height: 1.6;">
                <ol style="margin-left: 20px; padding-left: 0;">
                    <li style="margin-bottom: 8px;">
                        <strong><?php esc_html_e('Bật các API Google:', 'laca'); ?></strong>
                        <?php esc_html_e('Trong Google Cloud Console › APIs & Services › Library, tìm và bật: "Google Search Console API", "Google Analytics Data API", và "Google Analytics Admin API".', 'laca'); ?>
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong><?php esc_html_e('OAuth Consent / Test Users:', 'laca'); ?></strong>
                        <?php esc_html_e('Nếu ứng dụng OAuth đang ở chế độ Testing, hãy thêm email tài khoản Google của bạn vào Test users (Audience) hoặc bấm Publish app để tránh lỗi 403 access_denied.', 'laca'); ?>
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong><?php esc_html_e('Tạo OAuth Client ID:', 'laca'); ?></strong>
                        <?php esc_html_e('Tại mục Credentials, tạo Client ID loại Web application. Nhập địa chỉ Authorized redirect URI sau (giữ nguyên):', 'laca'); ?>
                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
                            <code style="background: var(--laca-bg-subtle, #f4f4f5); padding: 4px 8px; border-radius: 4px; font-size: 12px; word-break: break-all;"><?php echo esc_html($redirectUri); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($redirectUri); ?>'); alert(window.lacaSeoData?.i18n?.copied || 'Đã sao chép!');">
                                <span class="dashicons dashicons-admin-page" style="margin-top: 2px;"></span> <?php esc_html_e('Sao chép', 'laca'); ?>
                            </button>
                        </div>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Phạm vi truy cập (Scopes):', 'laca'); ?></strong>
                        <code>webmasters.readonly</code> <?php esc_html_e('và', 'laca'); ?> <code>analytics.readonly</code>.
                    </li>
                </ol>
            </div>
        </details>
        <?php
    }

    private function renderNotices(): void
    {
        if (isset($_GET['connected'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã kết nối tài khoản Google thành công!', 'laca') . '</p></div>';
        }
        if (isset($_GET['disconnected'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Đã ngắt kết nối tài khoản Google.', 'laca') . '</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã lưu cài đặt thành công.', 'laca') . '</p></div>';
        }
        if (isset($_GET['refreshed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã làm mới danh sách property từ Google Search Console.', 'laca') . '</p></div>';
        }
        if (!empty($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(wp_unslash($_GET['error'])) . '</p></div>';
        }
    }
}
