<?php

namespace App\Features\PostAudio;

use App\Features\PostAudio\Providers\TtsProviderInterface;
use App\Features\PostAudio\Providers\GoogleTtsProvider;
use App\Features\PostAudio\Providers\FptAiProvider;

defined('ABSPATH') || exit;

/**
 * Tính năng "Nghe bài viết".
 *
 * - Khi bài publish/cập nhật: xếp job WP-Cron (trễ 30 giây) tạo MP3 qua Google Cloud TTS hoặc FPT.AI.
 * - Hash (text + provider + giọng + tốc độ) giống lần trước thì không gọi lại API.
 * - Frontend: trình phát chèn đầu nội dung; chưa có MP3 thì dùng Web Speech API của trình duyệt.
 */
class PostAudioManager
{
    public const MENU_SLUG  = 'laca-post-audio';
    public const NONCE_SAVE = 'laca_post_audio_save_nonce';
    public const NONCE_AJAX = 'laca_post_audio_ajax_nonce';

    private const SPEEDS = ['0.75', '1.0', '1.25', '1.5', '2.0'];

    /** Trễ job để request lưu meta box thứ 2 của Gutenberg kịp chạy xong (vd tick "Tắt cho bài này"). */
    private const QUEUE_DELAY = 30;

    /** Job "processing" quá lâu (PHP timeout giữa chừng) thì cho phép xếp lại. */
    private const STALE_AFTER = 15 * MINUTE_IN_SECONDS;

    /** Số lần poll tối đa link async FPT (~12 phút tổng). */
    private const MAX_POLL_ATTEMPTS = 15;

    /** @var array<string, TtsProviderInterface> */
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            'google' => new GoogleTtsProvider(),
            'fpt'    => new FptAiProvider(),
        ];
    }

    public function init(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu'], 25);
        add_action('admin_post_laca_post_audio_save_settings', [$this, 'handleSaveSettings']);
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post', [$this, 'savePostMetaBox'], 10, 2);

        add_action('wp_ajax_laca_post_audio_test_connection', [$this, 'ajaxTestConnection']);
        add_action('wp_ajax_laca_post_audio_regenerate', [$this, 'ajaxRegenerate']);

        // wp_after_insert_post chạy sau khi meta/terms của REST request đã lưu xong.
        add_action('wp_after_insert_post', [$this, 'onAfterInsertPost'], 20, 2);
        add_action('laca_post_audio_generate_event', [$this, 'handleBackgroundGeneration'], 10, 2);
        add_action('laca_post_audio_poll_event', [$this, 'handleBackgroundPoll'], 10, 3);

        // Ưu tiên 20: sau wpautop (10) để markup trình phát không bị chèn <p>/<br>.
        add_filter('the_content', [$this, 'prependAudioPlayer'], 20);
        add_shortcode('laca_post_audio', [$this, 'renderShortcode']);
    }

    // =========================================================================
    // CẤU HÌNH
    // =========================================================================

    private function isEnabled(): bool
    {
        return get_option('laca_audio_enabled', '0') === '1';
    }

    /** @return string[] */
    private function allowedPostTypes(): array
    {
        return array_values(array_filter((array) get_option('laca_audio_post_types', ['post']), 'is_string'));
    }

    private function speedSetting(): string
    {
        $speed = (string) get_option('laca_audio_speed', '1.0');
        return in_array($speed, self::SPEEDS, true) ? $speed : '1.0';
    }

    private function activeProvider(): TtsProviderInterface
    {
        $id = (string) get_option('laca_audio_provider', 'google');
        return $this->providers[$id] ?? $this->providers['google'];
    }

    /**
     * Mã ngôn ngữ nội dung bài, dạng 'vi', 'en'...
     * Có Polylang thì theo ngôn ngữ của bài; không thì mặc định tiếng Việt (ngôn ngữ gốc của nội dung
     * các site) — không dùng get_locale() vì nhiều site đặt locale en_US nhưng viết bài tiếng Việt.
     */
    private function postLanguage(int $postId): string
    {
        $lang = 'vi';
        if (function_exists('pll_get_post_language')) {
            $pllLang = pll_get_post_language($postId, 'slug');
            if (is_string($pllLang) && $pllLang !== '') {
                $lang = $pllLang;
            }
        }

        $lang = (string) apply_filters('laca_post_audio_language', $lang, $postId);

        return strtolower(substr($lang, 0, 2));
    }

    /**
     * Giọng đọc cho bài theo provider + ngôn ngữ. Trả '' nếu provider không hỗ trợ ngôn ngữ đó.
     */
    private function resolveVoice(int $postId, TtsProviderInterface $provider): string
    {
        $lang   = $this->postLanguage($postId);
        $voices = $provider->getAvailableVoices($lang);
        if (empty($voices)) {
            return '';
        }

        $option = match (true) {
            $provider->getId() === 'fpt'  => 'laca_audio_fpt_voice',
            str_starts_with($lang, 'en') => 'laca_audio_google_voice_en',
            default                       => 'laca_audio_google_voice',
        };

        $saved = (string) get_option($option, '');
        // Giọng hợp lệ của provider cho đúng ngôn ngữ; giá trị cũ/sai thì dùng giọng đầu danh sách.
        return isset($voices[$saved]) ? $saved : (string) array_key_first($voices);
    }

    // =========================================================================
    // TRANG CÀI ĐẶT
    // =========================================================================

    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'laca-admin',
            __('Nghe bài viết (Audio)', 'laca'),
            __('Nghe bài viết', 'laca'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $provider       = $this->activeProvider()->getId();
        $speed          = $this->speedSetting();
        $postTypes      = $this->allowedPostTypes();
        $maxChars       = (int) get_option('laca_audio_max_chars', 10000);
        $googleVoice    = (string) get_option('laca_audio_google_voice', 'vi-VN-Neural2-A');
        $googleVoiceEn  = (string) get_option('laca_audio_google_voice_en', 'en-US-Neural2-F');
        $fptVoice       = (string) get_option('laca_audio_fpt_voice', 'banmai');
        $availableTypes = get_post_types(['public' => true], 'objects');
        unset($availableTypes['attachment']);

        $keys = [
            'google' => PostAudioSecurity::maskKey(PostAudioSecurity::decrypt((string) get_option('laca_audio_google_api_key', ''))),
            'fpt'    => PostAudioSecurity::maskKey(PostAudioSecurity::decrypt((string) get_option('laca_audio_fpt_api_key', ''))),
        ];

        $speedLabels = [
            '0.75' => __('Chậm', 'laca'),
            '1.0'  => __('Bình thường', 'laca'),
            '1.25' => __('Hơi nhanh', 'laca'),
            '1.5'  => __('Nhanh', 'laca'),
            '2.0'  => __('Rất nhanh', 'laca'),
        ];
        ?>
        <div class="wrap laca-admin-page">
            <h1><?php esc_html_e('Cài đặt Nghe bài viết', 'laca'); ?></h1>

            <?php if (isset($_GET['laca_msg']) && $_GET['laca_msg'] === 'saved') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Đã lưu cấu hình thành công.', 'laca'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE_SAVE, '_laca_audio_nonce'); ?>
                <input type="hidden" name="action" value="laca_post_audio_save_settings">

                <h2><?php esc_html_e('Cấu hình chung', 'laca'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Kích hoạt tính năng', 'laca'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="laca_audio_enabled" value="1" <?php checked($this->isEnabled()); ?>>
                                <?php esc_html_e('Bật tạo và phát audio bài viết', 'laca'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Tự động tạo file MP3 khi xuất bản hoặc cập nhật nội dung bài viết. Bài có mật khẩu sẽ không được tạo audio.', 'laca'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Dự phòng giọng trình duyệt', 'laca'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="laca_audio_fallback_speech" value="1" <?php checked(get_option('laca_audio_fallback_speech', '1'), '1'); ?>>
                                <?php esc_html_e('Dùng giọng đọc của trình duyệt khi bài viết chưa có file MP3', 'laca'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Loại nội dung áp dụng', 'laca'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($availableTypes as $typeKey => $typeObj) : ?>
                                    <label style="margin-right:1em">
                                        <input type="checkbox" name="laca_audio_post_types[]" value="<?php echo esc_attr($typeKey); ?>" <?php checked(in_array($typeKey, $postTypes, true)); ?>>
                                        <?php echo esc_html($typeObj->labels->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="laca_audio_max_chars"><?php esc_html_e('Giới hạn ký tự mỗi bài', 'laca'); ?></label></th>
                        <td>
                            <input type="number" id="laca_audio_max_chars" name="laca_audio_max_chars" value="<?php echo esc_attr((string) $maxChars); ?>" min="500" max="100000" step="500" class="small-text">
                            <p class="description"><?php esc_html_e('Bài dài hơn giới hạn này sẽ không gọi API, tránh phát sinh chi phí lớn ngoài ý muốn.', 'laca'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="laca_audio_speed"><?php esc_html_e('Tốc độ đọc khi tạo file', 'laca'); ?></label></th>
                        <td>
                            <select id="laca_audio_speed" name="laca_audio_speed">
                                <?php foreach ($speedLabels as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($speed, $value); ?>>
                                        <?php echo esc_html($value . 'x — ' . $label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Nhà cung cấp', 'laca'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($this->providers as $id => $p) : ?>
                                    <label style="margin-right:1.5em">
                                        <input type="radio" name="laca_audio_provider" value="<?php echo esc_attr($id); ?>" class="laca-audio-provider" <?php checked($provider, $id); ?>>
                                        <?php echo esc_html($p->getName()); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php foreach ($this->providers as $id => $p) : ?>
                    <div class="laca-audio-provider-section" data-provider="<?php echo esc_attr($id); ?>" <?php echo $provider === $id ? '' : 'hidden'; ?>>
                        <h2>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: tên nhà cung cấp TTS */
                                __('Cấu hình %s', 'laca'),
                                $p->getName()
                            ));
                            ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="laca_audio_<?php echo esc_attr($id); ?>_api_key"><?php esc_html_e('API Key', 'laca'); ?></label></th>
                                <td>
                                    <input type="password" name="laca_audio_<?php echo esc_attr($id); ?>_api_key" id="laca_audio_<?php echo esc_attr($id); ?>_api_key" value="" class="regular-text" autocomplete="new-password"
                                        placeholder="<?php echo esc_attr($keys[$id] !== '' ? __('Để trống để giữ key đã lưu', 'laca') : __('Nhập API Key', 'laca')); ?>">
                                    <button type="button" class="button laca-audio-test" data-provider="<?php echo esc_attr($id); ?>"><?php esc_html_e('Kiểm tra kết nối', 'laca'); ?></button>
                                    <p class="description">
                                        <?php
                                        if ($keys[$id] !== '') {
                                            echo esc_html(sprintf(
                                                /* translators: %s: API key đã che, vd ••••1234 */
                                                __('Đã lưu key: %s. Key được mã hoá trước khi lưu.', 'laca'),
                                                $keys[$id]
                                            ));
                                        } else {
                                            esc_html_e('Chưa có key. Key được mã hoá trước khi lưu.', 'laca');
                                        }
                                        ?>
                                    </p>
                                    <?php if ($keys[$id] !== '') : ?>
                                        <label><input type="checkbox" name="laca_audio_<?php echo esc_attr($id); ?>_api_key_delete" value="1"> <?php esc_html_e('Xoá key đã lưu', 'laca'); ?></label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                            $voiceFields = $id === 'google'
                                ? [
                                    ['laca_audio_google_voice', __('Giọng tiếng Việt', 'laca'), $p->getAvailableVoices('vi'), $googleVoice],
                                    ['laca_audio_google_voice_en', __('Giọng tiếng Anh (bài Polylang tiếng Anh)', 'laca'), $p->getAvailableVoices('en'), $googleVoiceEn],
                                ]
                                : [
                                    ['laca_audio_fpt_voice', __('Giọng đọc', 'laca'), $p->getAvailableVoices('vi'), $fptVoice],
                                ];
                            foreach ($voiceFields as [$name, $label, $voices, $current]) :
                                ?>
                                <tr>
                                    <th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
                                    <td>
                                        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>">
                                            <?php foreach ($voices as $vKey => $vLabel) : ?>
                                                <option value="<?php echo esc_attr($vKey); ?>" <?php selected($current, $vKey); ?>><?php echo esc_html($vLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php $this->renderKeyGuide($id); ?>
                    </div>
                <?php endforeach; ?>

                <div id="laca-audio-test-result" class="notice inline" hidden><p></p></div>

                <p class="description">
                    <?php esc_html_e('Trình phát được chèn tự động ở đầu bài. Muốn đặt ở vị trí khác: dùng shortcode [laca_post_audio] và tắt chèn tự động bằng filter laca_post_audio_auto_prepend.', 'laca'); ?>
                </p>

                <?php submit_button(__('Lưu cài đặt', 'laca')); ?>
            </form>
        </div>

        <script>
        ( function () {
            const i18n = {
                testing: '<?php echo esc_js(__('Đang kiểm tra kết nối API…', 'laca')); ?>',
                failed: '<?php echo esc_js(__('Kết nối thất bại.', 'laca')); ?>'
            };
            const resultBox = document.getElementById( 'laca-audio-test-result' );
            const showResult = ( type, message ) => {
                resultBox.hidden = false;
                resultBox.className = 'notice inline notice-' + type;
                // textContent: thông báo lỗi lấy từ API bên ngoài, không chèn HTML.
                resultBox.querySelector( 'p' ).textContent = message;
            };

            document.querySelectorAll( '.laca-audio-provider' ).forEach( ( radio ) => {
                radio.addEventListener( 'change', () => {
                    document.querySelectorAll( '.laca-audio-provider-section' ).forEach( ( section ) => {
                        section.hidden = section.dataset.provider !== radio.value;
                    } );
                } );
            } );

            document.querySelectorAll( '.laca-audio-test' ).forEach( ( btn ) => {
                btn.addEventListener( 'click', () => {
                    const provider = btn.dataset.provider;
                    const keyInput = document.getElementById( 'laca_audio_' + provider + '_api_key' );
                    const voiceSelect = document.getElementById( provider === 'google' ? 'laca_audio_google_voice' : 'laca_audio_fpt_voice' );
                    const data = new URLSearchParams( {
                        action: 'laca_post_audio_test_connection',
                        provider,
                        api_key: keyInput ? keyInput.value : '',
                        voice: voiceSelect ? voiceSelect.value : '',
                        _nonce: '<?php echo esc_js(wp_create_nonce(self::NONCE_AJAX)); ?>'
                    } );

                    showResult( 'info', i18n.testing );
                    fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
                        .then( ( res ) => res.json() )
                        .then( ( res ) => showResult( res.success ? 'success' : 'error', res.data && res.data.message ? res.data.message : i18n.failed ) )
                        .catch( () => showResult( 'error', i18n.failed ) );
                } );
            } );
        } )();
        </script>
        <?php
    }

    /**
     * Hướng dẫn lấy API key cho từng nhà cung cấp (link tới trang chính thức).
     */
    private function renderKeyGuide(string $providerId): void
    {
        $link = static fn(string $url, string $label): string => sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($url),
            esc_html($label)
        );

        if ($providerId === 'google') {
            $steps = [
                sprintf(
                    /* translators: %s: link tới Google Cloud Console */
                    __('Đăng nhập %s, tạo hoặc chọn 1 project.', 'laca'),
                    $link('https://console.cloud.google.com/projectselector2/home/dashboard', 'Google Cloud Console')
                ),
                sprintf(
                    /* translators: %s: link tới trang Billing */
                    __('Gắn tài khoản thanh toán cho project tại %s (bắt buộc; chỉ tính phí khi vượt hạn mức miễn phí).', 'laca'),
                    $link('https://console.cloud.google.com/billing', __('trang Billing', 'laca'))
                ),
                sprintf(
                    /* translators: %s: link bật Cloud Text-to-Speech API */
                    __('Bật %s.', 'laca'),
                    $link('https://console.cloud.google.com/apis/library/texttospeech.googleapis.com', 'Cloud Text-to-Speech API')
                ),
                sprintf(
                    /* translators: %s: link trang Credentials */
                    __('Vào %s → Create credentials → API key.', 'laca'),
                    $link('https://console.cloud.google.com/apis/credentials', __('trang Credentials', 'laca'))
                ),
                __('Bấm vào key vừa tạo → API restrictions → chọn "Restrict key" và chỉ tick Cloud Text-to-Speech API, để key bị lộ cũng không dùng được cho dịch vụ khác.', 'laca'),
                __('Copy key, dán vào ô API Key ở trên, bấm "Kiểm tra kết nối" rồi "Lưu cài đặt".', 'laca'),
            ];
            $more = sprintf(
                /* translators: 1, 2: link tới tài liệu tham khảo */
                __('Xem thêm: %1$s · %2$s', 'laca'),
                $link('https://cloud.google.com/text-to-speech/pricing', __('Bảng giá & hạn mức miễn phí', 'laca')),
                $link('https://cloud.google.com/text-to-speech/docs/before-you-begin', __('Tài liệu hướng dẫn', 'laca'))
            );
        } else {
            $steps = [
                sprintf(
                    /* translators: %s: link tới FPT.AI Console */
                    __('Đăng ký tài khoản FPT ID và đăng nhập %s.', 'laca'),
                    $link('https://console.fpt.ai', 'FPT.AI Console')
                ),
                __('Bật (toggle) Text to Speech API, bấm Confirm.', 'laca'),
                __('Nếu chưa có project: nhập tên project và bấm Create.', 'laca'),
                __('Nhập tên key và bấm Create để tạo API key.', 'laca'),
                __('Copy key, dán vào ô API Key ở trên, bấm "Kiểm tra kết nối" rồi "Lưu cài đặt".', 'laca'),
                __('Tính phí theo số ký tự; xem hạn mức miễn phí còn lại ở Dashboard, mua thêm bằng nút Buy (VNPay QR, thẻ ATM, thẻ quốc tế…).', 'laca'),
            ];
            $more = sprintf(
                /* translators: 1, 2: link tới tài liệu tham khảo */
                __('Xem thêm: %1$s · %2$s', 'laca'),
                $link('https://docs.fpt.ai/docs/en/console/documentation/console/', __('Hướng dẫn FPT.AI Console', 'laca')),
                $link('https://docs.fpt.ai/docs/en/speech/api/text-to-speech', __('Tài liệu API Text to Speech', 'laca'))
            );
        }

        $allowed = ['a' => ['href' => [], 'target' => [], 'rel' => []]];
        ?>
        <details class="laca-audio-key-guide">
            <summary><strong><?php esc_html_e('Cách lấy API key', 'laca'); ?></strong></summary>
            <ol>
                <?php foreach ($steps as $step) : ?>
                    <li><?php echo wp_kses($step, $allowed); ?></li>
                <?php endforeach; ?>
            </ol>
            <p class="description"><?php echo wp_kses($more, $allowed); ?></p>
        </details>
        <?php
    }

    public function handleSaveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }

        check_admin_referer(self::NONCE_SAVE, '_laca_audio_nonce');

        update_option('laca_audio_enabled', isset($_POST['laca_audio_enabled']) ? '1' : '0');
        update_option('laca_audio_fallback_speech', isset($_POST['laca_audio_fallback_speech']) ? '1' : '0');

        $provider = sanitize_key(wp_unslash($_POST['laca_audio_provider'] ?? 'google'));
        update_option('laca_audio_provider', isset($this->providers[$provider]) ? $provider : 'google');

        $speed = sanitize_text_field(wp_unslash($_POST['laca_audio_speed'] ?? '1.0'));
        update_option('laca_audio_speed', in_array($speed, self::SPEEDS, true) ? $speed : '1.0');

        $maxChars = absint($_POST['laca_audio_max_chars'] ?? 10000);
        update_option('laca_audio_max_chars', min(100000, max(500, $maxChars)));

        $publicTypes = get_post_types(['public' => true]);
        $rawTypes    = array_map('sanitize_key', (array) wp_unslash($_POST['laca_audio_post_types'] ?? []));
        $cleanTypes  = array_values(array_intersect($rawTypes, $publicTypes));
        update_option('laca_audio_post_types', $cleanTypes ?: ['post']);

        $voiceOptions = [
            'laca_audio_google_voice'    => $this->providers['google']->getAvailableVoices('vi'),
            'laca_audio_google_voice_en' => $this->providers['google']->getAvailableVoices('en'),
            'laca_audio_fpt_voice'       => $this->providers['fpt']->getAvailableVoices('vi'),
        ];
        foreach ($voiceOptions as $option => $voices) {
            $value = sanitize_text_field(wp_unslash($_POST[$option] ?? ''));
            if (isset($voices[$value])) {
                update_option($option, $value);
            }
        }

        // Ô key để trống = giữ key cũ; muốn xoá phải tick ô "Xoá key đã lưu".
        foreach (array_keys($this->providers) as $id) {
            $option = "laca_audio_{$id}_api_key";
            if (!empty($_POST["{$option}_delete"])) {
                update_option($option, '', false);
                continue;
            }

            $key = trim(sanitize_text_field(wp_unslash($_POST[$option] ?? '')));
            if ($key !== '') {
                update_option($option, PostAudioSecurity::encrypt($key), false);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&laca_msg=saved'));
        exit;
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public function ajaxTestConnection(): void
    {
        check_ajax_referer(self::NONCE_AJAX, '_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Không có quyền thực hiện.', 'laca')], 403);
        }

        $providerId = sanitize_key(wp_unslash($_POST['provider'] ?? ''));
        $provider   = $this->providers[$providerId] ?? null;
        if (!$provider) {
            wp_send_json_error(['message' => __('Nhà cung cấp không tồn tại.', 'laca')]);
        }

        $inputKey = trim(sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')));
        $voice    = sanitize_text_field(wp_unslash($_POST['voice'] ?? ''));
        if (!isset($provider->getAvailableVoices('vi')[$voice])) {
            $voice = '';
        }

        $result = $provider->testConnection($inputKey, $voice);
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        }
        wp_send_json_error(['message' => $result['message']]);
    }

    public function ajaxRegenerate(): void
    {
        check_ajax_referer(self::NONCE_AJAX, '_nonce');

        $postId = absint($_POST['post_id'] ?? 0);
        if (!$postId || !current_user_can('edit_post', $postId)) {
            wp_send_json_error(['message' => __('Không có quyền chỉnh sửa bài viết này.', 'laca')], 403);
        }

        delete_post_meta($postId, '_laca_audio_disabled');

        $reason = $this->queueAudioGeneration($postId, true);
        if ($reason !== '') {
            wp_send_json_error(['message' => $reason]);
        }

        wp_send_json_success(['message' => __('Đã đưa yêu cầu tạo lại audio vào hàng đợi xử lý ngầm.', 'laca')]);
    }

    // =========================================================================
    // META BOX
    // =========================================================================

    public function registerMetaBox(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        foreach ($this->allowedPostTypes() as $type) {
            add_meta_box('laca_post_audio_metabox', __('Nghe bài viết', 'laca'), [$this, 'renderMetaBox'], $type, 'side');
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $status   = (string) get_post_meta($post->ID, '_laca_audio_status', true);
        $audioId  = (int) get_post_meta($post->ID, '_laca_audio_id', true);
        $duration = (int) get_post_meta($post->ID, '_laca_audio_duration', true);
        $errorMsg = (string) get_post_meta($post->ID, '_laca_audio_error', true);
        $disabled = get_post_meta($post->ID, '_laca_audio_disabled', true) === '1';
        $audioUrl = $audioId ? wp_get_attachment_url($audioId) : '';

        $statusLabels = [
            'done'       => __('Đã có MP3', 'laca'),
            'pending'    => __('Đang chờ trong hàng đợi', 'laca'),
            'processing' => __('Đang xử lý…', 'laca'),
            'failed'     => __('Tạo thất bại', 'laca'),
        ];
        $statusLabel = $statusLabels[$status] ?? __('Chưa có audio', 'laca');

        wp_nonce_field('laca_audio_metabox_save', '_laca_audio_metabox_nonce');
        ?>
        <p><strong><?php esc_html_e('Trạng thái:', 'laca'); ?></strong> <?php echo esc_html($statusLabel); ?></p>

        <?php if ($status === 'done' && $audioUrl) : ?>
            <audio controls preload="none" src="<?php echo esc_url($audioUrl); ?>" style="width:100%"></audio>
            <p class="description">
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: thời lượng audio dạng phút:giây */
                    __('Thời lượng: %s', 'laca'),
                    AudioStorage::formatDuration($duration)
                ));
                ?>
            </p>
        <?php endif; ?>

        <?php if ($status === 'failed' && $errorMsg !== '') : ?>
            <p class="description"><strong><?php esc_html_e('Lỗi:', 'laca'); ?></strong> <?php echo esc_html($errorMsg); ?></p>
        <?php endif; ?>

        <?php if ($post->post_password !== '') : ?>
            <p class="description"><?php esc_html_e('Bài có mật khẩu nên không tạo audio.', 'laca'); ?></p>
        <?php endif; ?>

        <p>
            <label>
                <input type="checkbox" name="_laca_audio_disabled" value="1" <?php checked($disabled); ?>>
                <?php esc_html_e('Tắt nghe bài viết cho bài này', 'laca'); ?>
            </label>
        </p>

        <?php if ($post->post_status === 'publish') : ?>
            <p>
                <button type="button" class="button" id="laca-audio-regenerate" data-post-id="<?php echo (int) $post->ID; ?>"><?php esc_html_e('Tạo lại audio', 'laca'); ?></button>
            </p>
            <p id="laca-audio-meta-msg" class="description" aria-live="polite"></p>
            <script>
            ( function () {
                const btn = document.getElementById( 'laca-audio-regenerate' );
                const msg = document.getElementById( 'laca-audio-meta-msg' );
                const failed = '<?php echo esc_js(__('Gửi yêu cầu thất bại.', 'laca')); ?>';
                btn.addEventListener( 'click', () => {
                    btn.disabled = true;
                    msg.textContent = '<?php echo esc_js(__('Đang gửi yêu cầu…', 'laca')); ?>';
                    const data = new URLSearchParams( {
                        action: 'laca_post_audio_regenerate',
                        post_id: btn.dataset.postId,
                        _nonce: '<?php echo esc_js(wp_create_nonce(self::NONCE_AJAX)); ?>'
                    } );
                    fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
                        .then( ( res ) => res.json() )
                        .then( ( res ) => { msg.textContent = res.data && res.data.message ? res.data.message : failed; } )
                        .catch( () => { msg.textContent = failed; } )
                        .finally( () => { btn.disabled = false; } );
                } );
            } )();
            </script>
        <?php endif; ?>
        <?php
    }

    public function savePostMetaBox(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['_laca_audio_metabox_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_laca_audio_metabox_nonce'])), 'laca_audio_metabox_save')) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || !current_user_can('edit_post', $postId)) {
            return;
        }

        if (!empty($_POST['_laca_audio_disabled'])) {
            update_post_meta($postId, '_laca_audio_disabled', '1');
        } else {
            delete_post_meta($postId, '_laca_audio_disabled');
        }
    }

    // =========================================================================
    // TẠO AUDIO
    // =========================================================================

    public function onAfterInsertPost(int $postId, \WP_Post $post): void
    {
        if ($post->post_status !== 'publish' || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $this->queueAudioGeneration($postId);
    }

    /**
     * Lý do bài không được tạo audio ('' nếu được phép).
     */
    private function generationBlocker(int $postId): string
    {
        if (!$this->isEnabled()) {
            return __('Tính năng Nghe bài viết đang tắt.', 'laca');
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
            return __('Chỉ tạo audio cho bài đã xuất bản.', 'laca');
        }

        if (!in_array($post->post_type, $this->allowedPostTypes(), true)) {
            return __('Loại nội dung này chưa bật Nghe bài viết.', 'laca');
        }

        // File trong uploads ai cũng tải được → không tạo audio cho bài có mật khẩu.
        if ($post->post_password !== '') {
            return __('Bài có mật khẩu nên không tạo audio.', 'laca');
        }

        if (get_post_meta($postId, '_laca_audio_disabled', true) === '1') {
            return __('Bài này đã tắt Nghe bài viết.', 'laca');
        }

        return '';
    }

    /**
     * Tính hash và (nếu cần) xếp job tạo audio.
     *
     * @return string Lý do không xếp job ('' nếu đã xếp hoặc không cần tạo lại).
     */
    public function queueAudioGeneration(int $postId, bool $force = false): string
    {
        $blocker = $this->generationBlocker($postId);
        if ($blocker !== '') {
            return $blocker;
        }

        $job = $this->buildJob($postId);
        if (is_wp_error($job)) {
            $this->markFailed($postId, $job->get_error_message());
            return $job->get_error_message();
        }

        $hash           = $job['hash'];
        $existingHash   = (string) get_post_meta($postId, '_laca_audio_hash', true);
        $existingStatus = (string) get_post_meta($postId, '_laca_audio_status', true);

        if (!$force && $existingHash === $hash) {
            $audioId = (int) get_post_meta($postId, '_laca_audio_id', true);
            if ($existingStatus === 'done' && $audioId > 0 && get_post($audioId)) {
                return '';
            }
            // Gutenberg lưu 2 request liên tiếp — job cùng nội dung đang chờ/chạy thì không xếp thêm.
            if ($existingStatus === 'pending' || ($existingStatus === 'processing' && !$this->isStale($postId))) {
                return '';
            }
        }

        update_post_meta($postId, '_laca_audio_hash', $hash);
        update_post_meta($postId, '_laca_audio_status', 'pending');
        delete_post_meta($postId, '_laca_audio_error');
        delete_post_meta($postId, '_laca_audio_fpt_urls');

        wp_schedule_single_event(time() + self::QUEUE_DELAY, 'laca_post_audio_generate_event', [$postId, $hash]);

        return '';
    }

    /**
     * Chuẩn bị dữ liệu cho 1 lần tạo audio.
     *
     * @return array{text: string, provider: TtsProviderInterface, voice: string, speed: string, hash: string}|\WP_Error
     */
    private function buildJob(int $postId): array|\WP_Error
    {
        $text = (new TextExtractor())->extract($postId);
        if ($text === '') {
            return new \WP_Error('empty', __('Nội dung bài viết rỗng.', 'laca'));
        }

        $maxChars = (int) get_option('laca_audio_max_chars', 10000);
        $length   = mb_strlen($text);
        if ($length > $maxChars) {
            return new \WP_Error('too_long', sprintf(
                /* translators: 1: số ký tự của bài, 2: giới hạn trong cài đặt */
                __('Bài viết có %1$s ký tự, vượt quá giới hạn cấu hình (%2$s ký tự).', 'laca'),
                number_format_i18n($length),
                number_format_i18n($maxChars)
            ));
        }

        $provider = $this->activeProvider();
        $voice    = $this->resolveVoice($postId, $provider);
        if ($voice === '') {
            return new \WP_Error('unsupported_lang', sprintf(
                /* translators: 1: mã ngôn ngữ của bài, 2: tên nhà cung cấp */
                __('Ngôn ngữ "%1$s" chưa được %2$s hỗ trợ.', 'laca'),
                $this->postLanguage($postId),
                $provider->getName()
            ));
        }

        $speed = $this->speedSetting();

        return [
            'text'     => $text,
            'provider' => $provider,
            'voice'    => $voice,
            'speed'    => $speed,
            'hash'     => md5($text . '|' . $provider->getId() . '|' . $voice . '|' . $speed),
        ];
    }

    private function isStale(int $postId): bool
    {
        $started = (int) get_post_meta($postId, '_laca_audio_started', true);
        return $started > 0 && (time() - $started) > self::STALE_AFTER;
    }

    private function isCurrentJob(int $postId, string $hash): bool
    {
        return (string) get_post_meta($postId, '_laca_audio_hash', true) === $hash;
    }

    /**
     * Job WP-Cron: gọi API cho từng đoạn văn bản.
     */
    public function handleBackgroundGeneration(int $postId, string $hash): void
    {
        // Job đã bị thay bởi job mới hơn, hoặc đã có job khác nhận xử lý.
        if (!$this->isCurrentJob($postId, $hash) || get_post_meta($postId, '_laca_audio_status', true) !== 'pending') {
            return;
        }

        // Kiểm tra lại: trong 30 giây trễ có thể bài đã bị tắt audio, đặt mật khẩu hoặc gỡ xuất bản.
        if ($this->generationBlocker($postId) !== '') {
            delete_post_meta($postId, '_laca_audio_status');
            delete_post_meta($postId, '_laca_audio_hash');
            return;
        }

        $job = $this->buildJob($postId);
        if (is_wp_error($job)) {
            $this->markFailed($postId, $job->get_error_message());
            return;
        }
        if ($job['hash'] !== $hash) {
            // Nội dung đổi sau khi xếp job — job mới tương ứng sẽ xử lý.
            return;
        }

        update_post_meta($postId, '_laca_audio_status', 'processing');
        update_post_meta($postId, '_laca_audio_started', time());

        $provider = $job['provider'];
        $isFpt    = $provider->getId() === 'fpt';
        $chunks   = (new TextExtractor())->splitChunks(
            $job['text'],
            $isFpt ? FptAiProvider::MAX_CHUNK_CHARS : GoogleTtsProvider::MAX_CHUNK_BYTES,
            !$isFpt
        );

        $options    = ['voice' => $job['voice'], 'speed' => $job['speed']];
        $audioParts = [];
        $asyncUrls  = [];

        foreach ($chunks as $chunk) {
            $res = $provider->synthesize($chunk, $options);
            if (!$res['success']) {
                $this->markFailed($postId, (string) ($res['error'] ?? __('Lỗi không xác định khi gọi TTS API.', 'laca')));
                return;
            }

            if (!empty($res['async'])) {
                $asyncUrls[] = (string) $res['async_url'];
            } else {
                $audioParts[] = (string) $res['audio_data'];
            }
        }

        if (!empty($asyncUrls)) {
            // FPT: gửi hết các đoạn trước, rồi poll đến khi toàn bộ file sẵn sàng.
            update_post_meta($postId, '_laca_audio_fpt_urls', $asyncUrls);
            wp_schedule_single_event(time() + 15, 'laca_post_audio_poll_event', [$postId, $hash, 1]);
            return;
        }

        $this->storeIfCurrent($postId, $hash, $audioParts);
    }

    /**
     * Job WP-Cron: tải các file async của FPT.AI.
     */
    public function handleBackgroundPoll(int $postId, string $hash, int $attempt): void
    {
        if (!$this->isCurrentJob($postId, $hash)) {
            return;
        }

        $urls = get_post_meta($postId, '_laca_audio_fpt_urls', true);
        if (!is_array($urls) || empty($urls)) {
            return;
        }

        $storage = new AudioStorage();
        $parts   = [];
        $lastErr = null;

        foreach ($urls as $url) {
            $data = $storage->downloadAsyncAudio((string) $url);
            if (is_wp_error($data)) {
                $lastErr = $data;
                break;
            }
            $parts[] = $data;
        }

        if ($lastErr === null) {
            $this->storeIfCurrent($postId, $hash, $parts);
            return;
        }

        if ($lastErr->get_error_code() === 'download_pending' && $attempt < self::MAX_POLL_ATTEMPTS) {
            wp_schedule_single_event(time() + min(60, 15 * $attempt), 'laca_post_audio_poll_event', [$postId, $hash, $attempt + 1]);
            return;
        }

        $this->markFailed($postId, sprintf(
            /* translators: %s: chi tiết lỗi lần tải cuối */
            __('Không tải được audio từ FPT.AI: %s', 'laca'),
            $lastErr->get_error_message()
        ));
    }

    /**
     * Lưu MP3 — chỉ khi job vẫn là job mới nhất, để job cũ chạy chậm không ghi đè audio mới.
     */
    private function storeIfCurrent(int $postId, string $hash, array $parts): void
    {
        if (!$this->isCurrentJob($postId, $hash)) {
            return;
        }

        $result = (new AudioStorage())->saveMp3Chunks($postId, $parts, $hash);
        if (is_wp_error($result)) {
            $this->markFailed($postId, $result->get_error_message());
            return;
        }

        // Trong lúc lưu file có thể job mới đã được xếp — khi đó để job mới quyết định trạng thái.
        if ($this->isCurrentJob($postId, $hash)) {
            update_post_meta($postId, '_laca_audio_status', 'done');
            delete_post_meta($postId, '_laca_audio_error');
            delete_post_meta($postId, '_laca_audio_fpt_urls');
            delete_post_meta($postId, '_laca_audio_started');
        }
    }

    private function markFailed(int $postId, string $message): void
    {
        update_post_meta($postId, '_laca_audio_status', 'failed');
        update_post_meta($postId, '_laca_audio_error', $message);
        delete_post_meta($postId, '_laca_audio_started');
        error_log(sprintf('[laca-post-audio] Post %d: %s', $postId, $message));
    }

    // =========================================================================
    // FRONTEND
    // =========================================================================

    /**
     * Có hiển thị trình phát cho bài này không.
     */
    private function canShowPlayer(int $postId): bool
    {
        return $postId > 0
            && $this->isEnabled()
            && in_array(get_post_type($postId), $this->allowedPostTypes(), true)
            && get_post_meta($postId, '_laca_audio_disabled', true) !== '1'
            && !post_password_required($postId);
    }

    public function prependAudioPlayer(string $content): string
    {
        static $rendered = false;

        // Layout WPEmerge gọi the_content() ngoài The Loop (và trước cả wp_head) nên không dùng
        // in_the_loop()/did_action('wp_head'). Chỉ chèn 1 lần, bỏ qua lần plugin SEO chạy the_content trong <head>.
        if ($rendered || !is_singular() || !is_main_query() || doing_action('wp_head')) {
            return $content;
        }

        $postId = (int) get_the_ID();
        if ($postId !== (int) get_queried_object_id() || !$this->canShowPlayer($postId)) {
            return $content;
        }

        if (!apply_filters('laca_post_audio_auto_prepend', true, $postId)) {
            return $content;
        }

        $rendered = true;

        return $this->renderPlayer($postId) . $content;
    }

    public function renderShortcode(): string
    {
        $postId = (int) get_the_ID();
        return $this->canShowPlayer($postId) ? $this->renderPlayer($postId) : '';
    }

    public function renderPlayer(int $postId): string
    {
        $audioId  = (int) get_post_meta($postId, '_laca_audio_id', true);
        // Đang tạo lại thì vẫn phát file cũ. Bài có mật khẩu (kể cả đã mở khoá) không lộ link MP3 — chỉ dùng giọng trình duyệt.
        $audioUrl = ($audioId && get_post_field('post_password', $postId) === '') ? (string) wp_get_attachment_url($audioId) : '';
        $fallback = get_option('laca_audio_fallback_speech', '1') === '1';

        if ($audioUrl === '' && !$fallback) {
            return '';
        }

        $duration = AudioStorage::formatDuration((int) get_post_meta($postId, '_laca_audio_duration', true));

        ob_start();
        ?>
        <aside class="laca-post-audio" aria-label="<?php esc_attr_e('Nghe bài viết', 'laca'); ?>"
            data-mode="<?php echo $audioUrl !== '' ? 'mp3' : 'speech'; ?>"
            data-lang="<?php echo esc_attr($this->postLanguage($postId)); ?>"
            data-i18n-play="<?php esc_attr_e('Phát', 'laca'); ?>"
            data-i18n-pause="<?php esc_attr_e('Tạm dừng', 'laca'); ?>"
            data-i18n-ready="<?php esc_attr_e('Bấm để nghe bằng giọng đọc của trình duyệt', 'laca'); ?>"
            data-i18n-reading="<?php esc_attr_e('Đang đọc…', 'laca'); ?>"
            data-i18n-paused="<?php esc_attr_e('Đã tạm dừng', 'laca'); ?>"
            data-i18n-done="<?php esc_attr_e('Đã đọc xong bài viết.', 'laca'); ?>"
            data-i18n-speed="<?php /* translators: %s: tốc độ hiện tại, vd 1.25x */ esc_attr_e('Tốc độ đọc: %s', 'laca'); ?>"
            data-i18n-progress="<?php /* translators: 1: thời gian đã nghe, 2: tổng thời lượng */ esc_attr_e('Đã nghe %1$s trên %2$s', 'laca'); ?>"
            <?php echo $audioUrl === '' ? 'hidden' : ''; ?>
        >
            <div class="laca-post-audio__header">
                <svg class="laca-post-audio__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                    <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                </svg>
                <span class="laca-post-audio__title"><?php esc_html_e('Nghe bài viết', 'laca'); ?></span>
            </div>

            <div class="laca-post-audio__controls">
                <button type="button" class="laca-post-audio__play" aria-label="<?php esc_attr_e('Phát', 'laca'); ?>">
                    <svg class="laca-post-audio__icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"></path></svg>
                    <svg class="laca-post-audio__icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>
                </button>

                <?php if ($audioUrl !== '') : ?>
                    <div class="laca-post-audio__progress" role="slider" tabindex="0"
                        aria-label="<?php esc_attr_e('Vị trí đang nghe', 'laca'); ?>"
                        aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span class="laca-post-audio__progress-bar"></span>
                    </div>
                    <span class="laca-post-audio__time">
                        <span class="laca-post-audio__time-current">0:00</span> / <span class="laca-post-audio__time-total"><?php echo esc_html($duration); ?></span>
                    </span>
                <?php else : ?>
                    <span class="laca-post-audio__status" aria-live="polite"><?php esc_html_e('Bấm để nghe bằng giọng đọc của trình duyệt', 'laca'); ?></span>
                <?php endif; ?>

                <button type="button" class="laca-post-audio__speed" data-speed="1"
                    aria-label="<?php echo esc_attr(sprintf(/* translators: %s: tốc độ hiện tại, vd 1.25x */ __('Tốc độ đọc: %s', 'laca'), '1x')); ?>">1x</button>
            </div>

            <?php if ($audioUrl !== '') : ?>
                <audio class="laca-post-audio__native" preload="none" src="<?php echo esc_url($audioUrl); ?>"></audio>
            <?php endif; ?>
        </aside>
        <?php
        return (string) ob_get_clean();
    }
}
