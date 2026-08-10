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
        $this->cacheKey = 'lacadev_theme_update_' . md5($this->themeSlug);

        add_filter('pre_set_site_transient_update_themes', [$this, 'checkForUpdate']);
        add_filter('themes_api', [$this, 'themeInfo'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);
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
            return $cached ?: null;
        }

        $response = wp_remote_get($this->updateInfoUrl, [
            'timeout'    => 10,
            'user-agent' => 'LacaDev-ThemeUpdater/' . ($this->getCurrentVersion() ?: '1.0'),
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Cache thất bại ngắn hạn (30 phút) để không spam server
            set_transient($this->cacheKey, false, 30 * MINUTE_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if (!$data || empty($data->version)) {
            set_transient($this->cacheKey, false, 30 * MINUTE_IN_SECONDS);
            return null;
        }

        set_transient($this->cacheKey, $data, $this->cacheTtl);

        return $data;
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
