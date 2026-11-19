<?php

namespace App\Features\LacaSeo\Modules\ImageSeo;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Thumbnail Overlay Generator for OpenGraph social cards.
 * Generates overlay images (play, gif, custom) using GD matching Rank Math Thumbnail_Overlay.
 */
class ThumbnailOverlay
{
    public function register(): void
    {
        add_action('wp_ajax_laca_seo_overlay_thumb', [$this, 'generateOverlayThumbnail']);
        add_action('wp_ajax_nopriv_laca_seo_overlay_thumb', [$this, 'generateOverlayThumbnail']);

        if (!Helper::isRankMathActive()) {
            add_action('wp_ajax_rank_math_overlay_thumb', [$this, 'generateOverlayThumbnail']);
            add_action('wp_ajax_nopriv_rank_math_overlay_thumb', [$this, 'generateOverlayThumbnail']);
        }
    }

    /**
     * Get available choices for overlay images.
     */
    public static function getChoices(): array
    {
        $rmImgDir = WP_CONTENT_DIR . '/plugins/seo-by-rank-math/assets/admin/img/';
        // get_template_directory() của theme này là .../lacadev-client/theme
        // (cấu trúc WPEmerge) → resources/ nằm ở thư mục cha của nó.
        $themeImgDir = dirname(get_template_directory()) . '/resources/images/seo/';

        $playPath = file_exists($rmImgDir . 'icon-play.png') ? $rmImgDir . 'icon-play.png' : $themeImgDir . 'icon-play.png';
        $gifPath  = file_exists($rmImgDir . 'icon-gif.png') ? $rmImgDir . 'icon-gif.png' : $themeImgDir . 'icon-gif.png';

        $choices = [
            'play' => [
                'name'     => __('Play icon', 'laca'),
                'path'     => $playPath,
                'position' => 'middle_center',
            ],
            'gif'  => [
                'name'     => __('GIF icon', 'laca'),
                'path'     => $gifPath,
                'position' => 'middle_center',
            ],
        ];

        return apply_filters('laca_seo/social/overlay_images', $choices);
    }

    /**
     * Build secret hash for overlay URL.
     */
    public static function getSecret(int $thumbnailId, string $type): string
    {
        return md5($thumbnailId . $type . wp_salt('nonce'));
    }

    /**
     * Generate overlay image URL.
     */
    public static function getOverlayUrl(int $thumbnailId, string $type = 'play'): string
    {
        $action = Helper::isRankMathActive() ? 'rank_math_overlay_thumb' : 'laca_seo_overlay_thumb';
        return add_query_arg([
            'action' => $action,
            'id'     => $thumbnailId,
            'type'   => $type,
            'hash'   => self::getSecret($thumbnailId, $type),
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Ajax endpoint handler.
     */
    public function generateOverlayThumbnail(): void
    {
        $thumbnailId = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;
        $type = isset($_REQUEST['type']) ? sanitize_key($_REQUEST['type']) : 'play';
        $secret = isset($_REQUEST['hash']) ? sanitize_text_field($_REQUEST['hash']) : (isset($_REQUEST['secret']) ? sanitize_text_field($_REQUEST['secret']) : '');

        $choices = self::getChoices();
        if (!isset($choices[$type])) {
            die();
        }

        if (!hash_equals(self::getSecret($thumbnailId, $type), $secret)) {
            die();
        }

        $overlayPath = $choices[$type]['path'];
        $imagePath = get_attached_file($thumbnailId, true);
        if (!$imagePath || !file_exists($imagePath)) {
            die();
        }

        $position = $choices[$type]['position'] ?? 'middle_center';

        // Trước đây mỗi lượt gọi (bot mạng xã hội, crawler…) đều vẽ lại PNG
        // bằng GD → tốn CPU. Lưu file đã vẽ vào uploads; tên gắn mtime ảnh gốc
        // + icon nên đổi ảnh là tự vẽ lại, không cần xoá cache tay.
        $cacheFile = self::cachePath($thumbnailId, $type, $imagePath, $overlayPath);
        $png = ($cacheFile && is_readable($cacheFile)) ? (string) file_get_contents($cacheFile) : '';
        if ($png === '') {
            $png = self::renderOverlayGd($imagePath, $overlayPath, $position);
            if ($png !== '' && $cacheFile && wp_mkdir_p(dirname($cacheFile))) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                @file_put_contents($cacheFile, $png);
            }
        }

        header('Cache-Control: public, max-age=604800');
        if ($png === '') {
            // Thiếu icon overlay / GD lỗi → trả ẢNH GỐC thay vì phản hồi rỗng,
            // để ảnh chia sẻ mạng xã hội không bị vỡ.
            $mime = wp_check_filetype($imagePath)['type'] ?: 'image/jpeg';
            header('Content-Type: ' . $mime);
            readfile($imagePath);
            die();
        }
        header('Content-Type: image/png');
        echo $png; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dữ liệu ảnh nhị phân
        die();
    }

    /**
     * Đường dẫn file cache ảnh overlay trong uploads; '' nếu không xác định được.
     */
    private static function cachePath(int $thumbnailId, string $type, string $imagePath, string $overlayPath): string
    {
        $uploads = wp_upload_dir(null, false);
        if (empty($uploads['basedir']) || !empty($uploads['error'])) {
            return '';
        }
        $version = substr(md5((string) @filemtime($imagePath) . '|' . (string) @filemtime($overlayPath)), 0, 10);

        return trailingslashit($uploads['basedir']) . 'laca-seo-overlay/' . $thumbnailId . '-' . sanitize_key($type) . '-' . $version . '.png';
    }

    /**
     * Render overlay using GD and return binary PNG string.
     */
    public static function renderOverlayGd(string $imagePath, string $overlayPath, string $position = 'middle_center'): string
    {
        if (!file_exists($imagePath) || !file_exists($overlayPath)) {
            return '';
        }

        $imageCreate = self::getImageCreateMethod($imagePath);
        $overlayCreate = self::getImageCreateMethod($overlayPath);
        if (!$imageCreate || !$overlayCreate) {
            return '';
        }

        $image = @$imageCreate($imagePath);
        $stamp = @$overlayCreate($overlayPath);
        if (!$image || !$stamp) {
            return '';
        }

        $stampWidth  = imagesx($stamp);
        $stampHeight = imagesy($stamp);
        $imgWidth    = imagesx($image);

        if ($stampWidth > $imgWidth) {
            $stamp = imagescale($stamp, $imgWidth);
            $stampWidth = imagesx($stamp);
            $stampHeight = imagesy($stamp);
        }

        $top  = (int) round(abs(imagesy($image) - $stampHeight) / 2);
        $left = (int) round(abs($imgWidth - $stampWidth) / 2);

        imagecopy($image, $stamp, $left, $top, 0, 0, $stampWidth, $stampHeight);

        ob_start();
        imagepng($image);
        $output = (string) ob_get_clean();

        imagedestroy($image);
        imagedestroy($stamp);

        return $output;
    }

    private static function getImageCreateMethod(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpg') {
            $ext = 'jpeg';
        }
        // Ảnh tải lên của theme thường là WebP (tự chuyển đổi) — trước đây chỉ
        // nhận jpeg/png/gif nên overlay luôn thất bại với ảnh WebP.
        if (in_array($ext, ['jpeg', 'png', 'gif', 'webp'], true) && function_exists('imagecreatefrom' . $ext)) {
            return 'imagecreatefrom' . $ext;
        }
        return '';
    }
}
