<?php

namespace App\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LacaDev Theme Updater
 *
 * Tự động check phiên bản mới của theme (cha hoặc con) từ clients.lacadev.com
 * và hiện thông báo update trong WP Admin — giống plugin update.
 *
 * Cách hoạt động:
 *   1. WordPress gọi pre_set_site_transient_update_themes mỗi 12h
 *   2. Class này fetch info.json từ clients.lacadev.com
 *   3. So sánh version → nếu mới hơn, thêm vào queue update của WP
 *   4. Admin thấy thông báo, click "Cập nhật ngay" → WP tự download & cài
 */
class ThemeUpdater
{
    /** Cache TTL: 6 giờ */
    private int $cacheTtl = 6 * HOUR_IN_SECONDS;

    /** Cache transient key (tránh gọi API quá nhiều) — động theo từng theme */
    private string $cacheKey;

    /**
     * @param string $themeSlug     Slug đầy đủ của theme, PHẢI khớp với "stylesheet"
     *                              mà WordPress dùng nội bộ — vì theme này dùng cấu
     *                              trúc lồng cấp (root thật nằm ở thư mục con `theme/`),
     *                              slug đúng có dạng 'lacadev-client/theme', không phải
     *                              chỉ 'lacadev-client'.
     * @param string $updateInfoUrl URL file info.json đặt trên clients.lacadev.com.
     */
    public function __construct(
        private string $themeSlug,
        private string $updateInfoUrl,
    ) {
        $this->updateInfoUrl = (string) apply_filters('laca_theme_update_json_url', $this->updateInfoUrl);
        $this->cacheKey = 'lacadev_theme_update_' . md5($this->themeSlug);

        add_filter('pre_set_site_transient_update_themes', [$this, 'checkForUpdate']);
        add_filter('themes_api', [$this, 'themeInfo'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);
        // Ưu tiên 9: phải chạy trước Theme_Upgrader::check_package (ưu tiên 10).
        add_filter('upgrader_source_selection', [$this, 'allowNestedThemePackage'], 9, 4);
        add_filter('upgrader_source_selection', [$this, 'filterSourceSelection'], 10, 4);
        add_filter('upgrader_package_options', [$this, 'backupWholeThemeFolder']);
        add_filter('http_request_args', [$this, 'filterDownloadRequestArgs'], 10, 2);
        add_filter('http_request_host_is_external', [$this, 'allowLocalHubHost'], 10, 2);
        // Ưu tiên 9: gỡ current_after của core khi parent theme active trực tiếp để tránh switch_theme sai.
        add_filter('upgrader_post_install', [$this, 'preventFalseThemeSwitch'], 9, 3);
        // Hỗ trợ AJAX "Update now" trên themes.php khi slug có dấu gạch chéo.
        // admin-ajax.php gọi do_action('wp_ajax_' . $_POST['action']), với action là 'update-theme' (dấu gạch nối).
        add_action('wp_ajax_update-theme', [$this, 'handleAjaxThemeUpdate'], 0);
        add_action('wp_ajax_update_theme', [$this, 'handleAjaxThemeUpdate'], 0);
    }

    /**
     * Core wp_ajax_update_theme dùng preg_replace('/[^A-z0-9_\-]/', '', $slug)
     * làm mất dấu / trong 'lacadev-client/theme' thành 'lacadev-clienttheme'
     * → không tìm thấy update → báo "The theme is at the latest version".
     * Intercept tại priority 0 để xử lý đúng slug cho theme này.
     */
    public function handleAjaxThemeUpdate(): void
    {
        $rawSlug = isset($_POST['slug']) ? wp_unslash($_POST['slug']) : '';
        $cleanSlug = preg_replace('/[^A-z0-9_\-]/', '', $rawSlug);
        $expectedClean = preg_replace('/[^A-z0-9_\-]/', '', $this->themeSlug);

        if ($rawSlug !== $this->themeSlug && $cleanSlug !== $expectedClean) {
            return;
        }

        check_ajax_referer('updates');

        $status = [
            'update'     => 'theme',
            'slug'       => $this->themeSlug,
            'oldVersion' => '',
            'newVersion' => '',
        ];

        if (!current_user_can('update_themes')) {
            $status['errorMessage'] = __('Sorry, you are not allowed to update themes for this site.');
            wp_send_json_error($status);
        }

        $theme = wp_get_theme($this->themeSlug);
        if ($theme->exists()) {
            $status['oldVersion'] = $theme->get('Version');
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Theme_Upgrader($skin);
        $result = $upgrader->upgrade($this->themeSlug);

        if (is_wp_error($result)) {
            $status['errorCode'] = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error($status);
        } elseif ($result === false) {
            if ($skin->get_errors()->has_errors()) {
                $status['errorMessage'] = $skin->get_error_messages();
            } else {
                $status['errorMessage'] = $upgrader->strings['up_to_date'] ?? __('The theme is at the latest version.');
            }
            wp_send_json_error($status);
        }

        $updatedTheme = wp_get_theme($this->themeSlug);
        if ($updatedTheme->exists()) {
            $status['newVersion'] = $updatedTheme->get('Version');
        }

        wp_send_json_success($status);
    }

    /**
     * Cho phép request tới host local (vd .local) khi ở môi trường local
     */
    public function allowLocalHubHost(bool $allow, string $host): bool
    {
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local') {
            if (str_ends_with($host, '.local') || $host === 'lacadev.local') {
                return true;
            }
        }
        return $allow;
    }

    /**
     * WP sao lưu bản cũ vào upgrade-temp-backup/themes/{slug}. Slug lồng cấp
     * 'lacadev-client/theme' làm WP chuyển vào thư mục con chưa tồn tại → lỗi
     * "Không thể di chuyển phiên bản cũ vào thư mục upgrade-temp-backup".
     * Đổi slug sao lưu thành thư mục gốc để sao lưu (và khôi phục khi lỗi) cả theme.
     */
    public function backupWholeThemeFolder(array $options): array
    {
        $hookExtra = $options['hook_extra'] ?? [];
        if (($hookExtra['theme'] ?? '') !== $this->themeSlug || empty($hookExtra['temp_backup']['slug'])) {
            return $options;
        }

        $options['hook_extra']['temp_backup']['slug'] = strtok($this->themeSlug, '/');
        return $options;
    }

    /**
     * Theme dùng cấu trúc lồng cấp nên style.css nằm ở `theme/style.css`, không nằm ở gốc
     * gói zip. Theme_Upgrader::check_package() (dùng khi cập nhật ở Bảng tin → Cập nhật)
     * đòi style.css ở gốc nên luôn báo "thiếu style.css". Với đúng theme này thì bỏ bước
     * kiểm đó và tự kiểm style.css ở vị trí thật.
     */
    public function allowNestedThemePackage(mixed $source, mixed $remoteSource, mixed $upgrader, array $hookExtra = []): mixed
    {
        if (is_wp_error($source) || !($upgrader instanceof \Theme_Upgrader)) {
            return $source;
        }

        $theme = $hookExtra['theme'] ?? '';
        $themes = (array) ($hookExtra['themes'] ?? []);
        if ($theme !== $this->themeSlug && !in_array($this->themeSlug, $themes, true)) {
            return $source;
        }

        // 'lacadev-client/theme' → style.css nằm ở '<gốc gói>/theme/style.css'.
        $relStyle = basename($this->themeSlug) . '/style.css';

        // Cùng cách quy đổi đường dẫn với check_package (filesystem FTP/SSH dùng đường dẫn khác).
        global $wp_filesystem;
        $workingDir = (string) $source;
        if ($wp_filesystem) {
            $workingDir = str_replace($wp_filesystem->wp_content_dir(), trailingslashit(WP_CONTENT_DIR), $workingDir);
        }
        if (is_dir($workingDir) && !file_exists(trailingslashit($workingDir) . $relStyle)) {
            return new \WP_Error(
                'lacadev_theme_package_invalid',
                sprintf(
                    /* translators: %s: đường dẫn style.css trong gói cập nhật */
                    __('Gói cập nhật không hợp lệ: thiếu %s.', 'laca'),
                    $relStyle
                )
            );
        }

        remove_filter('upgrader_source_selection', [$upgrader, 'check_package']);
        return $source;
    }

    /**
     * WP core Theme_Upgrader::current_after() so sánh $theme ('lacadev-client/theme')
     * với $destination_name ('lacadev-client'). Nếu khác nhau, nó tự động gọi
     * switch_theme('lacadev-client') làm hỏng stylesheet của theme lồng cấp khi
     * parent theme đang active trực tiếp.
     * Ở ưu tiên 9, gỡ filter current_after của upgrader và tự tắt maintenance mode.
     */
    public function preventFalseThemeSwitch(mixed $response, array $hookExtra, array $result): mixed
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $theme = $hookExtra['theme'] ?? '';
        if ($theme !== $this->themeSlug) {
            return $response;
        }

        if (get_stylesheet() === $this->themeSlug) {
            global $wp_filter;
            if (isset($wp_filter['upgrader_post_install']->callbacks[10])) {
                foreach ($wp_filter['upgrader_post_install']->callbacks[10] as $id => $callback) {
                    if (is_array($callback['function']) && isset($callback['function'][1]) && $callback['function'][1] === 'current_after') {
                        $upgrader = $callback['function'][0];
                        if ($upgrader instanceof \Theme_Upgrader) {
                            remove_filter('upgrader_post_install', [$upgrader, 'current_after'], 10);
                            if (empty($upgrader->bulk)) {
                                $upgrader->maintenance_mode(false);
                            }
                            break;
                        }
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Hook vào WordPress update check — thêm thông tin update nếu có phiên bản mới
     */
    public function checkForUpdate(mixed $transient): mixed
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remoteInfo = $this->getRemoteInfo();

        if (!$remoteInfo || empty($remoteInfo->version)) {
            return $transient;
        }

        $currentVersion = $transient->checked[$this->themeSlug] ?? '';

        if (version_compare($remoteInfo->version, $currentVersion, '>')) {
            $transient->response[$this->themeSlug] = [
                'theme'       => $this->themeSlug,
                'new_version' => $remoteInfo->version,
                'url'         => $remoteInfo->details_url ?? 'https://lacadev.com',
                'package'     => $remoteInfo->download_url,
                'requires'    => $remoteInfo->requires ?? '6.0',
                'requires_php'=> $remoteInfo->requires_php ?? '8.0',
            ];
        }

        return $transient;
    }

    /**
     * Trả về thông tin theme cho màn hình "View version details"
     */
    public function themeInfo(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'theme_information' || empty($args->slug) || $args->slug !== $this->themeSlug) {
            return $result;
        }

        $remoteInfo = $this->getRemoteInfo();

        if (!$remoteInfo) {
            return $result;
        }

        return (object) [
            'name'          => $remoteInfo->name ?? 'LacaDev Child',
            'slug'          => $this->themeSlug,
            'version'       => $remoteInfo->version,
            'author'        => '<a href="https://lacadev.com">La Cà Dev</a>',
            'homepage'      => 'https://lacadev.com',
            'download_link' => $remoteInfo->download_url,
            'last_updated'  => $remoteInfo->last_updated ?? '',
            'requires'      => $remoteInfo->requires ?? '6.0',
            'requires_php'  => $remoteInfo->requires_php ?? '8.0',
            'sections'      => [
                'description' => $remoteInfo->description ?? 'LacaDev Child Theme — cập nhật tự động từ lacadev.com',
                'changelog'   => $remoteInfo->changelog ?? '',
            ],
        ];
    }

    /**
     * Xóa cache sau khi update xong để lần check sau luôn fresh
     */
    public function clearCache(mixed $upgrader, array $options): void
    {
        if ($options['type'] === 'theme' && in_array($this->themeSlug, (array) ($options['themes'] ?? []))) {
            delete_transient($this->cacheKey);
        }
    }

    /**
     * Fetch info.json từ $updateInfoUrl, có cache 6h
     */
    private function getRemoteInfo(): ?object
    {
        $cached = get_transient($this->cacheKey);

        if ($cached !== false) {
            if (is_array($cached) && !empty($cached['error'])) {
                return null;
            }
            return is_object($cached) ? $cached : null;
        }

        $response = wp_remote_get($this->updateInfoUrl, [
            'timeout'    => 10,
            'user-agent' => 'LacaDev-ThemeUpdater/' . ($this->getCurrentVersion() ?: '1.0'),
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Cache lỗi bằng marker array 1 giờ thay vì false (để không fetch lại mỗi request)
            set_transient($this->cacheKey, ['error' => true], HOUR_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if (!$data || empty($data->version)) {
            set_transient($this->cacheKey, ['error' => true], HOUR_IN_SECONDS);
            return null;
        }

        set_transient($this->cacheKey, $data, $this->cacheTtl);

        return $data;
    }

    /**
     * Đảm bảo thư mục giải nén được đổi tên về đúng slug theme (lacadev-client)
     *
     * @param string $source Path tới thư mục giải nén tạm
     * @param string $remoteSource Path tới thư mục chứa file nén
     * @param mixed $upgrader
     * @param array $hookExtra
     * @return string|\WP_Error
     */
    public function filterSourceSelection(string $source, string $remoteSource, mixed $upgrader, array $hookExtra = []): string|\WP_Error
    {
        $theme = $hookExtra['theme'] ?? '';
        if ($theme !== $this->themeSlug && $theme !== basename($this->themeSlug)) {
            return $source;
        }

        $targetDirName = basename($this->themeSlug);
        if (str_contains($this->themeSlug, '/')) {
            $parts         = explode('/', $this->themeSlug);
            $targetDirName = $parts[0];
        }

        $currentDirName = basename(rtrim($source, '/'));
        if ($currentDirName === $targetDirName) {
            return $source;
        }

        global $wp_filesystem;
        $newSource = trailingslashit(dirname(rtrim($source, '/'))) . $targetDirName . '/';

        if ($wp_filesystem && $wp_filesystem->move($source, $newSource)) {
            return $newSource;
        }

        return $source;
    }

    /**
     * Tự động ký HMAC vào request tải gói cập nhật theme từ Hub
     *
     * @param array<string,mixed> $args HTTP request arguments
     * @param string $url Target URL
     * @return array<string,mixed>
     */
    public function filterDownloadRequestArgs(array $args, string $url): array
    {
        $parsed = parse_url($url);
        $path   = $parsed['path'] ?? '';

        if (!str_contains($path, '/wp-json/laca/v1/theme-package/')) {
            return $args;
        }

        $trackerKey = LacaDevTrackerClient::getTrackerKey() ?: LacaDevTrackerClient::getSecretKey();
        $projectId  = LacaDevTrackerClient::getProjectId();

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = [];
        }

        if (!empty($trackerKey) && !empty($projectId)) {
            $sigHeaders = \App\Security\RequestSigner::sign(
                'GET',
                $path,
                '',
                $trackerKey,
                $projectId
            );
            $args['headers'] = array_merge($args['headers'], $sigHeaders);
        }

        // Chỉ gửi key thô (legacy) khi KHÔNG ký được — tránh lộ key trong header/log khi đã có chữ ký
        if (!empty($trackerKey) && empty($projectId)) {
            $args['headers']['X-Laca-Key'] = $trackerKey;
        }

        $args['timeout'] = max((int) ($args['timeout'] ?? 30), 300);

        return $args;
    }

    /**
     * Lấy version hiện tại của theme từ style.css
     */
    private function getCurrentVersion(): string
    {
        $themeData = wp_get_theme($this->themeSlug);
        return $themeData->get('Version') ?: '';
    }
}
