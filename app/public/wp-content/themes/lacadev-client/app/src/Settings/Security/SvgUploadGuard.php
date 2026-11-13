<?php

namespace App\Settings\Security;

use enshrined\svgSanitize\Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lọc SVG khi upload — chặn Stored XSS.
 *
 * - Chỉ cho phép user có `unsafe_filtered_html` (Super Admin / quản trị chính) upload SVG.
 * - Mọi file SVG (kể cả admin) đều qua Sanitizer trước khi lưu.
 * - File chứa <script>, onload=, <foreignObject>, javascript: sẽ bị loại bỏ hoặc chặn.
 *
 * @since 2026-11 (Plan Security Parity)
 */
class SvgUploadGuard
{
    public function register(): void
    {
        // Chỉ thêm SVG MIME cho user có quyền cao
        add_filter('upload_mimes', [$this, 'filterUploadMimes'], 20);

        // Sanitize trước khi WP lưu file
        add_filter('wp_handle_upload_prefilter', [$this, 'sanitizeSvgUpload'], 10);
        // media_handle_sideload (REST, import, block sync) không qua upload_prefilter
        add_filter('wp_handle_sideload_prefilter', [$this, 'sanitizeSvgUpload'], 10);

        // Sửa nhận diện filetype cho SVG (WP đôi khi chặn nhầm)
        add_filter('wp_check_filetype_and_ext', [$this, 'fixSvgFiletype'], 10, 5);
    }

    /**
     * Chỉ thêm SVG MIME cho user có cap `unsafe_filtered_html`.
     * Đồng thời GỠ svg khỏi danh sách cho user không đủ quyền.
     */
    private function canUploadSvg(): bool
    {
        return current_user_can('unsafe_filtered_html') || current_user_can('manage_options');
    }

    public function filterUploadMimes(array $mimes): array
    {
        if ($this->canUploadSvg()) {
            $mimes['svg']  = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        } else {
            unset($mimes['svg'], $mimes['svgz']);
        }

        return $mimes;
    }

    /**
     * Sanitize file SVG trước khi lưu — áp dụng cho MỌI user (kể cả admin).
     *
     * @param array $file $_FILES array element.
     * @return array
     */
    public function sanitizeSvgUpload(array $file): array
    {
        if (!$this->isSvgFile($file)) {
            return $file;
        }

        // Đọc nội dung file tạm
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || trim($content) === '') {
            $file['error'] = __('Không thể đọc file SVG.', 'laca');
            return $file;
        }

        if (!class_exists(Sanitizer::class)) {
            $file['error'] = __('Thiếu thư viện lọc SVG, không thể tải lên file SVG.', 'laca');
            return $file;
        }

        // Chạy sanitizer
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize($content);

        if ($clean === false || trim($clean) === '') {
            $file['error'] = __('File SVG chứa mã độc và đã bị chặn.', 'laca');
            return $file;
        }

        // Ghi đè file tạm bằng nội dung đã lọc
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($file['tmp_name'], $clean);

        return $file;
    }

    /**
     * Sửa nhận diện filetype cho SVG — WP đôi khi trả MIME sai
     * hoặc chặn nhầm file SVG hợp lệ (do getimagesize() không hỗ trợ SVG).
     *
     * @param array       $data     Values for the extension, mime type, and corrected filename.
     * @param string      $file     Full path to the file.
     * @param string      $filename The name of the file.
     * @param string[]    $mimes    Array of allowed mime types keyed by their file extension regex.
     * @param string|false $real_mime The actual mime type or false if the type cannot be determined.
     * @return array
     */
    public function fixSvgFiletype(array $data, string $file, string $filename, array $mimes, $real_mime): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (($ext === 'svg' || $ext === 'svgz') && $this->canUploadSvg()) {
            $data['ext']             = $ext;
            $data['type']            = 'image/svg+xml';
            $data['proper_filename'] = $filename;
        }

        return $data;
    }

    /**
     * Kiểm tra file có phải SVG không — dựa trên cả extension lẫn MIME.
     */
    private function isSvgFile(array $file): bool
    {
        // Kiểm tra extension
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === 'svg' || $ext === 'svgz') {
            return true;
        }

        // Kiểm tra MIME type
        $mime = $file['type'] ?? '';
        if ($mime === 'image/svg+xml') {
            return true;
        }

        return false;
    }
}

