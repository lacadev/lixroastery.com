<?php

namespace App\Settings\LacaTools;

class Optimize
{
    protected $currentUser;
    protected $errorMessage = '';

    public function __construct()
    {
        $this->currentUser = wp_get_current_user();
        $this->applyOptions();
    }

    private function isEnabled(string $name): bool
    {
        $val = get_option('_' . $name);
        if ($val === 'yes' || $val === '1' || $val === 1 || $val === true) {
            return true;
        }
        // Class được khởi tạo lúc nạp theme — trước khi Carbon Fields đăng ký field thì gọi carbon_get_* sẽ fatal
        if (did_action('carbon_fields_fields_registered') && function_exists('carbon_get_theme_option')) {
            return (bool) carbon_get_theme_option($name);
        }
        return false;
    }

    public function applyOptions(): void
    {
        if ($this->isEnabled('disable_use_jquery_migrate')) {
            $this->disableUseJqueryMigrate();
        }

        if ($this->isEnabled('disable_gutenberg_css')) {
            $this->disableGutenbergCss();
        }

        if ($this->isEnabled('disable_classic_css')) {
            $this->disableClassicCss();
        }

        if ($this->isEnabled('disable_emoji')) {
            $this->disableEmoji();
        }

        if ($this->isEnabled('enable_instant_page')) {
            $this->enableInstantPage();
        }

        if ($this->isEnabled('enable_smooth_scroll')) {
            $this->enableSmoothScroll();
        }

        if ($this->isEnabled('remove_comments')) {
            $this->removeHtmlComments();
        }

        if ($this->isEnabled('enable_advanced_resource_hints')) {
            $this->enableAdvancedResourceHints();
        }

        if ($this->isEnabled('enable_optimize_images')) {
            $this->optimizeImageAttributes();
        }

        if ($this->isEnabled('enable_optimize_content_images')) {
            $this->optimizeContentImages();
        }

        // Giới hạn Revisions
        $this->applyRevisionLimit();

        // Heartbeat control
        $this->applyHeartbeatControl();
    }

    public function disableUseJqueryMigrate()
    {
        add_action('wp_default_scripts', function ($scripts) {
            if (!is_admin() && isset($scripts->registered['jquery'])) {
                $script = $scripts->registered['jquery'];
                if ($script->deps) {
                    $script->deps = array_diff($script->deps, ['jquery-migrate']);
                }
            }
        });
    }

    public function disableGutenbergCss()
    {
        add_action('wp_enqueue_scripts', function () {
            if (is_front_page()) {
                wp_dequeue_style('wp-block-library');
                wp_dequeue_style('wp-block-library-theme');
                wp_dequeue_style('wc-blocks-style');
            }
        });
    }

    public function disableClassicCss()
    {
        add_action('wp_enqueue_scripts', function () {
            if (is_front_page()) {
                wp_dequeue_style('classic-theme-styles');
            }
        });
    }

    public function disableEmoji()
    {
        add_action('init', function () {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('admin_print_scripts', 'print_emoji_detection_script');
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_action('admin_print_styles', 'print_emoji_styles');
            remove_filter('the_content_feed', 'wp_staticize_emoji');
            remove_filter('comment_text_rss', 'wp_staticize_emoji');
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        });
    }

    public function enableInstantPage()
    {
        add_action('wp_enqueue_scripts', function () {
            $dir = function_exists('lacaThemeRootDir') ? \lacaThemeRootDir() . '/dist/' : dirname(get_stylesheet_directory()) . '/dist/';
            $uri = function_exists('lacaDistUrl') ? \lacaDistUrl('instantpage.js') : dirname(get_stylesheet_directory_uri()) . '/dist/instantpage.js';
            if (file_exists($dir . 'instantpage.js')) {
                wp_enqueue_script('instantpage', $uri, [], '5.7.0', true);
            }
        });
    }

    public function enableSmoothScroll()
    {
        add_action('wp_enqueue_scripts', function () {
            $dir = function_exists('lacaThemeRootDir') ? \lacaThemeRootDir() . '/dist/' : dirname(get_stylesheet_directory()) . '/dist/';
            $uri = function_exists('lacaDistUrl') ? \lacaDistUrl('smooth-scroll.min.js') : dirname(get_stylesheet_directory_uri()) . '/dist/smooth-scroll.min.js';
            if (file_exists($dir . 'smooth-scroll.min.js')) {
                wp_enqueue_script('smooth-scroll', $uri, [], '1.4.16', true);
            }
        });
    }

    /**
     * Xoá HTML comments khỏi output (trừ IE conditionals).
     */
    public function removeHtmlComments()
    {
        add_action('template_redirect', function () {
            if (is_admin() || is_feed()) return;
            ob_start(function (string $html): string {
                return preg_replace('/<!--(?!\[if)(?!-->).*?-->/s', '', $html) ?: $html;
            });
        });
    }

    /**
     * Thêm preconnect / dns-prefetch cho Google Fonts và CDN thông dụng.
     */
    public function enableAdvancedResourceHints()
    {
        add_action('wp_head', function () {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
            echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        }, 1);
    }

    /**
     * Tự động thêm loading="lazy", alt rỗng (nếu chưa có), width & height vào <img> trong the_post_thumbnail.
     */
    public function optimizeImageAttributes()
    {
        add_filter('wp_get_attachment_image_attributes', function (array $attr, \WP_Post $attachment): array {
            if (!isset($attr['loading'])) {
                $attr['loading'] = 'lazy';
            }
            if (!isset($attr['alt']) || $attr['alt'] === '') {
                $attr['alt'] = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true) ?: '';
            }
            return $attr;
        }, 10, 2);
    }

    /**
     * Tự động thêm loading="lazy" vào <img> bên trong nội dung bài viết.
     */
    public function optimizeContentImages()
    {
        add_filter('the_content', function (string $content): string {
            if (is_admin() || empty($content)) return $content;
            return preg_replace_callback(
                '/<img(?![^>]*loading=)[^>]*>/i',
                fn($m) => str_replace('<img', '<img loading="lazy"', $m[0]),
                $content
            ) ?: $content;
        });
    }

    // ═══════════════════════════════════════════════════════════
    //  REVISION LIMIT
    // ═══════════════════════════════════════════════════════════

    /**
     * Giới hạn số revision giữ lại.
     * Option `_revision_limit`: -1 = không giới hạn (mặc định WP), 0 = tắt revision, N = giữ N bản.
     */
    private function applyRevisionLimit(): void
    {
        $limit = get_option('_revision_limit', -1);
        if ($limit === '' || $limit === false) {
            return; // Không cấu hình → giữ hành vi WP mặc định
        }
        $limit = (int) $limit;
        if ($limit < 0) {
            return; // -1 = không giới hạn
        }

        add_filter('wp_revisions_to_keep', function () use ($limit) {
            return $limit;
        });
    }

    // ═══════════════════════════════════════════════════════════
    //  HEARTBEAT CONTROL
    // ═══════════════════════════════════════════════════════════

    /**
     * Điều khiển Heartbeat API.
     * Option `_heartbeat_mode`: '' = mặc định WP, 'slow' = 60s, 'editor_only' = tắt ngoài editor.
     */
    private function applyHeartbeatControl(): void
    {
        $mode = get_option('_heartbeat_mode', '');
        if (empty($mode)) {
            return; // Giữ mặc định WP
        }

        switch ($mode) {
            case 'slow':
                // Giảm tần suất xuống 60 giây
                add_filter('heartbeat_settings', function (array $settings): array {
                    $settings['interval'] = 60;
                    return $settings;
                });
                break;

            case 'editor_only':
                // Tắt heartbeat ngoài trang soạn bài
                add_action('admin_enqueue_scripts', function (string $hook) {
                    $editorPages = ['post.php', 'post-new.php'];
                    if (!in_array($hook, $editorPages, true)) {
                        wp_deregister_script('heartbeat');
                    }
                }, 1);
                break;
        }
    }
}
