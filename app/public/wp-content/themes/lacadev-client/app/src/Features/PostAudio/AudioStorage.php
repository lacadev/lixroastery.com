<?php

namespace App\Features\PostAudio;

defined('ABSPATH') || exit;

/**
 * Ghép các đoạn MP3, lưu vào Media Library và gắn với bài viết.
 */
class AudioStorage
{
    /** Meta đánh dấu attachment do tính năng này tạo — chỉ xoá attachment có meta này. */
    public const OWNER_META = '_laca_post_audio_for';

    /** Giới hạn dung lượng 1 file tải về từ provider async (FPT). */
    private const MAX_DOWNLOAD_BYTES = 50 * MB_IN_BYTES;

    /**
     * Ghép các đoạn MP3 thành 1 file và lưu vào Media Library.
     *
     * @param string[] $audioChunks Dữ liệu nhị phân MP3 theo thứ tự đọc.
     * @return int|\WP_Error ID attachment mới.
     */
    public function saveMp3Chunks(int $postId, array $audioChunks, string $hash): int|\WP_Error
    {
        $audioChunks = array_values(array_filter($audioChunks, static fn($c) => is_string($c) && $c !== ''));
        if (empty($audioChunks)) {
            return new \WP_Error('empty_audio', __('Không có dữ liệu âm thanh để lưu.', 'laca'));
        }

        // Đoạn thứ 2 trở đi bỏ ID3 tag để trình phát không hiểu nhầm là file mới giữa chừng.
        foreach ($audioChunks as $i => $chunk) {
            if ($i > 0) {
                $audioChunks[$i] = self::stripId3v2($chunk);
            }
        }
        $fullBinary = implode('', $audioChunks);

        $post     = get_post($postId);
        $postSlug = $post ? sanitize_title($post->post_name ?: $post->post_title) : 'post';
        $filename = sanitize_file_name("audio-{$postSlug}-{$postId}-" . substr($hash, 0, 8) . '.mp3');

        $upload = wp_upload_bits($filename, null, $fullBinary);
        if (!empty($upload['error'])) {
            return new \WP_Error(
                'upload_error',
                sprintf(
                    /* translators: %s: thông báo lỗi khi ghi file vào thư mục uploads */
                    __('Lỗi lưu file: %s', 'laca'),
                    $upload['error']
                )
            );
        }

        $filePath = $upload['file'];

        $attachId = wp_insert_attachment([
            'post_mime_type' => 'audio/mpeg',
            'post_title'     => sprintf(
                /* translators: %s: tiêu đề bài viết */
                __('Audio bài viết: %s', 'laca'),
                get_the_title($postId)
            ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $filePath, $postId, true);

        if (is_wp_error($attachId) || !$attachId) {
            return new \WP_Error('attachment_failed', __('Không thể tạo attachment cho file audio.', 'laca'));
        }

        update_post_meta($attachId, self::OWNER_META, $postId);

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metaData = wp_generate_attachment_metadata($attachId, $filePath);
        if (is_array($metaData)) {
            wp_update_attachment_metadata($attachId, $metaData);
        }
        $durationSeconds = !empty($metaData['length']) ? (int) $metaData['length'] : 0;

        $oldAttachId = (int) get_post_meta($postId, '_laca_audio_id', true);

        update_post_meta($postId, '_laca_audio_id', $attachId);
        update_post_meta($postId, '_laca_audio_duration', $durationSeconds);

        $this->deleteOwnedAttachment($oldAttachId, $postId, $attachId);

        return $attachId;
    }

    /**
     * Tải 1 file MP3 từ URL async của FPT.AI.
     *
     * @return string|\WP_Error Dữ liệu nhị phân, hoặc WP_Error code 'download_pending' khi file chưa sẵn sàng.
     */
    public function downloadAsyncAudio(string $url): string|\WP_Error
    {
        if (!self::isAllowedAsyncUrl($url)) {
            return new \WP_Error('download_invalid_url', __('Đường dẫn audio trả về không hợp lệ.', 'laca'));
        }

        $response = wp_safe_remote_get($url, [
            'timeout'             => 30,
            'redirection'         => 2,
            'limit_response_size' => self::MAX_DOWNLOAD_BYTES,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('download_pending', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            // FPT trả 404 cho tới khi xử lý xong (5 giây – 2 phút).
            return new \WP_Error(
                'download_pending',
                sprintf(
                    /* translators: %d: mã HTTP */
                    __('Audio chưa sẵn sàng (HTTP %d).', 'laca'),
                    $code
                )
            );
        }

        $body = (string) wp_remote_retrieve_body($response);
        if (!self::looksLikeMp3($body)) {
            return new \WP_Error('download_pending', __('Dữ liệu tải về chưa phải file MP3.', 'laca'));
        }

        return $body;
    }

    /**
     * Xoá attachment audio cũ — chỉ khi chắc chắn do tính năng này tạo cho đúng bài này,
     * tránh xoá nhầm khi meta _laca_audio_id bị sao chép sang bài khác (Duplicate Post, Polylang).
     */
    public function deleteOwnedAttachment(int $attachId, int $postId, int $keepId = 0): void
    {
        if ($attachId <= 0 || $attachId === $keepId) {
            return;
        }

        $attachment = get_post($attachId);
        if (!$attachment instanceof \WP_Post || $attachment->post_type !== 'attachment') {
            return;
        }

        if ((int) get_post_meta($attachId, self::OWNER_META, true) !== $postId) {
            return;
        }

        wp_delete_attachment($attachId, true);
    }

    /**
     * Chỉ chấp nhận URL https thuộc tên miền fpt.ai (chống SSRF).
     */
    public static function isAllowedAsyncUrl(string $url): bool
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        return $host === 'fpt.ai' || str_ends_with($host, '.fpt.ai');
    }

    /**
     * Nhận diện MP3 qua ID3 tag hoặc frame sync.
     */
    public static function looksLikeMp3(string $data): bool
    {
        if (strlen($data) < 4) {
            return false;
        }

        if (str_starts_with($data, 'ID3')) {
            return true;
        }

        return ord($data[0]) === 0xFF && (ord($data[1]) & 0xE0) === 0xE0;
    }

    /**
     * Bỏ ID3v2 tag ở đầu dữ liệu MP3 (nếu có).
     */
    public static function stripId3v2(string $data): string
    {
        if (strlen($data) < 10 || !str_starts_with($data, 'ID3')) {
            return $data;
        }

        // Kích thước tag là số "synchsafe" 4 byte (mỗi byte dùng 7 bit).
        $size = ((ord($data[6]) & 0x7F) << 21)
            | ((ord($data[7]) & 0x7F) << 14)
            | ((ord($data[8]) & 0x7F) << 7)
            | (ord($data[9]) & 0x7F);

        $footer = (ord($data[5]) & 0x10) ? 10 : 0;

        return (string) substr($data, 10 + $size + $footer);
    }

    /**
     * Định dạng số giây thành phút:giây (125 -> 2:05).
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0:00';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
