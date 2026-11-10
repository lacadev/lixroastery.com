<?php

namespace App\Features\PostAudio\Providers;

defined('ABSPATH') || exit;

/**
 * Interface cho các nhà cung cấp Text-to-Speech (TTS).
 */
interface TtsProviderInterface
{
    /**
     * Mã định danh của provider (ví dụ: 'google', 'fpt').
     */
    public function getId(): string;

    /**
     * Tên hiển thị của provider.
     */
    public function getName(): string;

    /**
     * Lấy danh sách giọng đọc theo mã ngôn ngữ.
     *
     * @param string $lang Mã ngôn ngữ ('vi', 'en', ...).
     * @return array<string, string> Mảng key => label giọng đọc.
     */
    public function getAvailableVoices(string $lang = 'vi'): array;

    /**
     * Tổng hợp văn bản thành âm thanh.
     *
     * @param string $text Nội dung văn bản cần đọc.
     * @param array $options Tuỳ chọn: voice, speed, lang...
     * @return array{success: bool, async: bool, audio_data: ?string, async_url: ?string, error: ?string}
     */
    public function synthesize(string $text, array $options = []): array;

    /**
     * Thử nghiệm kết nối API với một câu ngắn.
     *
     * @param string $apiKey API key cần kiểm tra (hoặc rỗng nếu dùng key đã lưu).
     * @param string $voice Giọng đọc thử nghiệm.
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $apiKey = '', string $voice = ''): array;
}

