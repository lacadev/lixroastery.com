<?php

namespace App\Features\PostAudio\Providers;

use App\Features\PostAudio\PostAudioSecurity;

defined('ABSPATH') || exit;

/**
 * FPT.AI Text-to-Speech v5 (https://docs.fpt.ai/docs/en/speech/api/text-to-speech).
 * API trả về link `async`; file MP3 sẵn sàng sau 5 giây – 2 phút nên phải poll.
 */
class FptAiProvider implements TtsProviderInterface
{
    private const ENDPOINT = 'https://api.fpt.ai/hmi/tts/v5';

    /** Giới hạn của FPT là 5000 ký tự/request — chừa biên an toàn. */
    public const MAX_CHUNK_CHARS = 4500;

    public function getId(): string
    {
        return 'fpt';
    }

    public function getName(): string
    {
        return 'FPT.AI Text to Speech';
    }

    public function getAvailableVoices(string $lang = 'vi'): array
    {
        if (!str_starts_with($lang, 'vi')) {
            return [];
        }

        return [
            'banmai'  => __('Ban Mai - Nữ miền Bắc', 'laca'),
            'thuminh' => __('Thu Minh - Nữ miền Bắc', 'laca'),
            'leminh'  => __('Lê Minh - Nam miền Bắc', 'laca'),
            'myan'    => __('Mỹ An - Nữ miền Trung', 'laca'),
            'giahuy'  => __('Gia Huy - Nam miền Trung', 'laca'),
            'lannhi'  => __('Lan Nhi - Nữ miền Nam', 'laca'),
            'linhsan' => __('Linh San - Nữ miền Nam', 'laca'),
        ];
    }

    public function synthesize(string $text, array $options = []): array
    {
        $apiKey = PostAudioSecurity::decrypt((string) get_option('laca_audio_fpt_api_key', ''));
        if ($apiKey === '') {
            return self::fail(__('Chưa cấu hình FPT.AI API Key trong cài đặt.', 'laca'));
        }

        $result = $this->request(
            $apiKey,
            $text,
            (string) ($options['voice'] ?? 'banmai'),
            $this->mapSpeed((string) ($options['speed'] ?? '1.0')),
            30
        );

        if (is_wp_error($result)) {
            return self::fail($result->get_error_message());
        }

        return [
            'success'    => true,
            'async'      => true,
            'audio_data' => null,
            'async_url'  => $result,
            'error'      => null,
        ];
    }

    public function testConnection(string $apiKey = '', string $voice = ''): array
    {
        if ($apiKey === '') {
            $apiKey = PostAudioSecurity::decrypt((string) get_option('laca_audio_fpt_api_key', ''));
        }

        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => __('Vui lòng nhập FPT.AI API Key để kiểm tra.', 'laca'),
            ];
        }

        $result = $this->request(
            $apiKey,
            __('Xin chào, đây là âm thanh thử nghiệm từ hệ thống.', 'laca'),
            $voice !== '' ? $voice : 'banmai',
            '0',
            15
        );

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: thông báo lỗi từ FPT.AI */
                    __('Kết nối FPT.AI thất bại: %s', 'laca'),
                    $result->get_error_message()
                ),
            ];
        }

        return [
            'success' => true,
            'message' => __('Kết nối FPT.AI TTS thành công! API Key hợp lệ.', 'laca'),
        ];
    }

    /**
     * Gửi text, trả về URL async của file MP3.
     */
    private function request(string $apiKey, string $text, string $voice, string $speed, int $timeout): string|\WP_Error
    {
        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => $timeout,
            'headers' => [
                'api_key' => $apiKey,
                'voice'   => $voice,
                'speed'   => $speed,
                'format'  => 'mp3',
            ],
            'body'    => $text,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code !== 200 || !is_array($data) || (int) ($data['error'] ?? 1) !== 0 || empty($data['async'])) {
            $msg = is_array($data) && !empty($data['message']) && is_string($data['message'])
                ? $data['message']
                : sprintf(
                    /* translators: %d: mã HTTP */
                    __('Lỗi HTTP %d từ FPT.AI TTS API', 'laca'),
                    $code
                );
            return new \WP_Error('fpt_error', $msg);
        }

        return (string) $data['async'];
    }

    /**
     * Ánh xạ hệ số tốc độ sang thang -3..+3 của FPT.AI.
     */
    private function mapSpeed(string $speed): string
    {
        return match ($speed) {
            '0.75'  => '-1',
            '1.25'  => '1',
            '1.5'   => '2',
            '2.0'   => '3',
            default => '0',
        };
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
