<?php

namespace App\Features\Dev;

use App\Cli\Sync\SyncConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LocalMediaProxy
 *
 * Cho phép môi trường local hiển thị đầy đủ hình ảnh từ uploads/
 * mà không cần phải tải hàng GB hình ảnh từ production về máy.
 *
 * Chỉ hoạt động khi:
 * 1. Môi trường là local hoặc development
 * 2. Có cấu hình laca_prod_url hoặc hằng LACA_PROD_URL
 * 3. URL local khác URL production
 * 4. Không bị tắt qua option laca_local_media_proxy = 'no'
 */
class LocalMediaProxy
{
    private static ?bool $isActive = null;
    private static ?string $prodUrl = null;
    private static ?string $localUrl = null;
    private static array $fileExistCache = [];

    /**
     * Khởi động hook
     */
    public static function boot(): void
    {
        if (!self::isActive()) {
            return;
        }

        add_filter('wp_get_attachment_url', [self::class, 'filterAttachmentUrl'], 10, 2);
        add_filter('wp_get_attachment_image_src', [self::class, 'filterAttachmentImageSrc'], 10, 4);
        add_filter('wp_calculate_image_srcset', [self::class, 'filterImageSrcset'], 10, 5);
        add_filter('the_content', [self::class, 'filterContentHtml'], 99);
        add_filter('render_block', [self::class, 'filterBlockHtml'], 99, 2);

        // Hiển thị chỉ báo trên admin bar
        add_action('admin_bar_menu', [self::class, 'addAdminBarIndicator'], 100);
    }

    /**
     * Kiểm tra xem tính năng có được kích hoạt không
     */
    public static function isActive(): bool
    {
        if (self::$isActive !== null) {
            return self::$isActive;
        }

        // 1. Kiểm tra môi trường
        if (function_exists('wp_get_environment_type')) {
            $env = wp_get_environment_type();
            if ($env !== 'local' && $env !== 'development') {
                self::$isActive = false;
                return false;
            }
        }

        // 2. Kiểm tra option tắt proxy
        if (get_option('laca_local_media_proxy') === 'no') {
            self::$isActive = false;
            return false;
        }

        // 3. Lấy URL production
        $prodUrl = self::getProductionUrl();
        if (empty($prodUrl)) {
            self::$isActive = false;
            return false;
        }

        // 4. So khớp URL local vs production (không được trùng nhau)
        $localUrl = rtrim(home_url(), '/');
        if (rtrim($prodUrl, '/') === $localUrl) {
            self::$isActive = false;
            return false;
        }

        self::$localUrl = $localUrl;
        self::$prodUrl = rtrim($prodUrl, '/');
        self::$isActive = true;

        return true;
    }

    /**
     * Đặt lại cache trạng thái (hữu ích khi cập nhật option trong runtime hoặc testing)
     */
    public static function resetState(): void
    {
        self::$isActive = null;
        self::$prodUrl = null;
        self::$localUrl = null;
        self::$fileExistCache = [];
    }

    /**
     * Lấy URL production từ hằng, option hoặc sync config
     */
    public static function getProductionUrl(): ?string
    {
        if (defined('LACA_PROD_URL') && LACA_PROD_URL) {
            return (string) LACA_PROD_URL;
        }

        $opt = get_option('laca_prod_url');
        if (!empty($opt) && is_string($opt)) {
            return $opt;
        }

        $cfgProd = SyncConfig::getProduction();
        if (!empty($cfgProd['url'])) {
            return $cfgProd['url'];
        }

        return null;
    }

    /**
     * Filter wp_get_attachment_url
     */
    public static function filterAttachmentUrl(string $url, int $attachmentId): string
    {
        if (empty($url) || !self::isActive()) {
            return $url;
        }

        $localFilePath = get_attached_file($attachmentId);
        if ($localFilePath && self::doesFileExist($localFilePath)) {
            return $url;
        }

        return self::proxyToProduction($url);
    }

    /**
     * Filter wp_get_attachment_image_src
     */
    public static function filterAttachmentImageSrc($image, $attachmentId, $size, $icon)
    {
        if (!is_array($image) || empty($image[0]) || !self::isActive()) {
            return $image;
        }

        $url = $image[0];
        $uploadDir = wp_upload_dir();
        $baseDir = wp_normalize_path($uploadDir['basedir']);
        $baseUrl = $uploadDir['baseurl'];

        // Kiểm tra xem file có ở local không
        if (str_starts_with($url, $baseUrl)) {
            $relPath = substr($url, strlen($baseUrl));
            $filePath = $baseDir . $relPath;

            if (!self::doesFileExist($filePath)) {
                $image[0] = self::proxyToProduction($url);
            }
        }

        return $image;
    }

    /**
     * Filter wp_calculate_image_srcset
     */
    public static function filterImageSrcset(array $sources, array $sizeArray, string $imageSrc, array $imageMeta, int $attachmentId): array
    {
        if (empty($sources) || !self::isActive()) {
            return $sources;
        }

        $uploadDir = wp_upload_dir();
        $baseDir = wp_normalize_path($uploadDir['basedir']);
        $baseUrl = $uploadDir['baseurl'];

        foreach ($sources as &$srcData) {
            $url = $srcData['url'] ?? '';
            if (empty($url) || !str_starts_with($url, $baseUrl)) {
                continue;
            }

            $relPath = substr($url, strlen($baseUrl));
            $filePath = $baseDir . $relPath;

            if (!self::doesFileExist($filePath)) {
                $srcData['url'] = self::proxyToProduction($url);
            }
        }

        return $sources;
    }

    /**
     * Filter HTML trong the_content
     */
    public static function filterContentHtml(string $content): string
    {
        if (empty($content) || !self::isActive() || !str_contains($content, '/wp-content/uploads/')) {
            return $content;
        }

        return self::replaceUploadUrlsInHtml($content);
    }

    /**
     * Filter HTML trong render_block
     */
    public static function filterBlockHtml(string $blockContent, array $block): string
    {
        if (empty($blockContent) || !self::isActive() || !str_contains($blockContent, '/wp-content/uploads/')) {
            return $blockContent;
        }

        return self::replaceUploadUrlsInHtml($blockContent);
    }

    /**
     * Thay thế các URL upload chưa có ở local thành URL production
     */
    private static function replaceUploadUrlsInHtml(string $html): string
    {
        $uploadDir = wp_upload_dir();
        $baseDir = wp_normalize_path($uploadDir['basedir']);
        $baseUrl = $uploadDir['baseurl'];

        return preg_replace_callback(
            '#(https?://[^"\'\s]+/wp-content/uploads/[^"\'\s\?]+)(\?[^"\'\s]*)?#i',
            function ($matches) use ($baseUrl, $baseDir) {
                $fullUrl = $matches[1];
                $query = $matches[2] ?? '';

                if (str_starts_with($fullUrl, $baseUrl)) {
                    $rel = substr($fullUrl, strlen($baseUrl));
                    $localPath = $baseDir . $rel;

                    if (!self::doesFileExist($localPath)) {
                        return self::proxyToProduction($fullUrl) . $query;
                    }
                }

                return $matches[0];
            },
            $html
        );
    }

    /**
     * Đổi host local thành host production
     */
    public static function proxyToProduction(string $url): string
    {
        if (empty(self::$prodUrl)) {
            return $url;
        }

        $localUrl = self::$localUrl ?: home_url();
        if (str_starts_with($url, $localUrl)) {
            return self::$prodUrl . substr($url, strlen($localUrl));
        }

        // Trường hợp URL tương đối /wp-content/uploads/...
        if (str_starts_with($url, '/')) {
            return self::$prodUrl . $url;
        }

        return $url;
    }

    /**
     * Kiểm tra file_exists với static caching
     */
    private static function doesFileExist(string $filePath): bool
    {
        $norm = wp_normalize_path($filePath);
        if (isset(self::$fileExistCache[$norm])) {
            return self::$fileExistCache[$norm];
        }

        $exists = file_exists($norm);
        self::$fileExistCache[$norm] = $exists;
        return $exists;
    }

    /**
     * Thêm node vào Admin Bar
     */
    public static function addAdminBarIndicator(\WP_Admin_Bar $wpAdminBar): void
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }

        $wpAdminBar->add_node([
            'id'    => 'laca-media-proxy',
            'title' => '<span class="ab-icon dashicons dashicons-images-alt2" style="top:2px;"></span> <span class="ab-label">' . esc_html__('Ảnh từ Production (Proxy)', 'laca') . '</span>',
            'href'  => admin_url('admin.php?page=laca-system-info'),
            'meta'  => [
                'title' => sprintf(__('Đang proxy ảnh chưa tải từ %s', 'laca'), self::$prodUrl),
            ],
        ]);
    }
}
