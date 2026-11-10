<?php

namespace App\Features\PostAudio\Providers;

use App\Features\PostAudio\PostAudioSecurity;

defined('ABSPATH') || exit;

/**
 * Google Cloud Text-to-Speech (REST v1, trả MP3 base64 đồng bộ).
 */
class GoogleTtsProvider implements TtsProviderInterface
{
    private const ENDPOINT = 'https://texttospeech.googleapis.com/v1/text:synthesize';

    /** Giới hạn của Google là 5000 byte/request — chừa biên an toàn. */
    public const MAX_CHUNK_BYTES = 4500;

    public function getId(): string
    {
        return 'google';
    }

    public function getName(): string
    {
        return 'Google Cloud Text-to-Speech';
    }

    public function getAvailableVoices(string $lang = 'vi'): array
    {
        if (str_starts_with($lang, 'en')) {
            return [
                'en-US-Neural2-F'  => __('Tiếng Anh - Nữ (Neural2)', 'laca'),
                'en-US-Neural2-D'  => __('Tiếng Anh - Nam (Neural2)', 'laca'),
                'en-US-Standard-C' => __('Tiếng Anh - Nữ (Tiêu chuẩn)', 'laca'),
                'en-US-Standard-D' => __('Tiếng Anh - Nam (Tiêu chuẩn)', 'laca'),
            ];
        }

        return [
            'vi-VN-Neural2-A'  => __('Tiếng Việt - Nữ A (Neural2)', 'laca'),
            'vi-VN-Neural2-D'  => __('Tiếng Việt - Nam D (Neural2)', 'laca'),
            'vi-VN-Wavenet-A'  => __('Tiếng Việt - Nữ A (WaveNet)', 'laca'),
            'vi-VN-Wavenet-B'  => __('Tiếng Việt - Nam B (WaveNet)', 'laca'),
            'vi-VN-Wavenet-C'  => __('Tiếng Việt - Nữ C (WaveNet)', 'laca'),
            'vi-VN-Wavenet-D'  => __('Tiếng Việt - Nam D (WaveNet)', 'laca'),
            'vi-VN-Standard-A' => __('Tiếng Việt - Nữ A (Tiêu chuẩn)', 'laca'),
            'vi-VN-Standard-B' => __('Tiếng Việt - Nam B (Tiêu chuẩn)', 'laca'),
            'vi-VN-Standard-C' => __('Tiếng Việt - Nữ C (Tiêu chuẩn)', 'laca'),
            'vi-VN-Standard-D' => __('Tiếng Việt - Nam D (Tiêu chuẩn)', 'laca'),
        ];
    }

    public function synthesize(string $text, array $options = []): array
    {
        $apiKey = PostAudioSecurity::decrypt((string) get_option('laca_audio_google_api_key', ''));
        if ($apiKey === '') {
            return self::fail(__('Chưa cấu hình Google Cloud API Key trong cài đặt.', 'laca'));
        }

        $voiceName = (string) ($options['voice'] ?? 'vi-VN-Neural2-A');
        $rate      = (float) ($options['speed'] ?? 1.0);
        if ($rate < 0.25 || $rate > 4.0) {
            $rate = 1.0;
        }

        $response = $this->request($apiKey, $text, $voiceName, $rate, 45);
        if (is_wp_error($response)) {
            return self::fail($response->get_error_message());
        }

        return [
            'success'    => true,
            'async'      => false,
            'audio_data' => $response,
            'async_url'  => null,
            'error'      => null,
        ];
    }

    public function testConnection(string $apiKey = '', string $voice = ''): array
    {
        if ($apiKey === '') {
            $apiKey = PostAudioSecurity::decrypt((string) get_option('laca_audio_google_api_key', ''));
        }

        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => __('Vui lòng nhập Google Cloud API Key để kiểm tra.', 'laca'),
            ];
        }

        $result = $this->request(
            $apiKey,
            __('Xin chào, đây là âm thanh thử nghiệm từ hệ thống.', 'laca'),
            $voice !== '' ? $voice : 'vi-VN-Neural2-A',
            1.0,
            15
        );

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: thông báo lỗi từ Google */
                    __('Kết nối thất bại: %s', 'laca'),
                    $result->get_error_message()
                ),
            ];
        }

        return [
            'success' => true,
            'message' => __('Kết nối Google Cloud TTS thành công! API Key hợp lệ.', 'laca'),
        ];
    }

    /**
     * Gọi API, trả về dữ liệu MP3 nhị phân.
     * Key gửi qua header X-Goog-Api-Key thay vì query string để không lọt vào log URL.
     */
    private function request(string $apiKey, string $text, string $voiceName, float $rate, int $timeout): string|\WP_Error
    {
        $body = [
            'input'       => ['text' => $text],
            'voice'       => [
                'languageCode' => substr($voiceName, 0, 5),
                'name'         => $voiceName,
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate'  => $rate,
            ],
        ];

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type'   => 'application/json; charset=utf-8',
                'X-Goog-Api-Key' => $apiKey,
            ],
            'body'    => (string) wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = is_array($data) && !empty($data['error']['message'])
                ? (string) $data['error']['message']
                : sprintf(
                    /* translators: %d: mã HTTP */
                    __('Lỗi HTTP %d từ Google TTS API', 'laca'),
                    $code
                );
            return new \WP_Error('google_http', $msg);
        }

        $audio = is_array($data) && !empty($data['audioContent'])
            ? base64_decode((string) $data['audioContent'], true)
            : false;

        if ($audio === false || $audio === '') {
            return new \WP_Error('google_empty', __('Google TTS không trả về dữ liệu âm thanh.', 'laca'));
        }

        return $audio;
    }

    private static function fail(string $message): array
    {
        return [
            'success'    => false,
            'async'      => false,
            'audio_data' => null,
            'async_url'  => null,
            'error'      => $message,
        ];
    }
}
