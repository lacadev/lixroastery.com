<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Injects Google Analytics 4 and Google Tag Manager measurement scripts.
 * Supports IP anonymization and exclusion of logged-in administrators.
 * Runs completely independent of Laca SEO and Rank Math.
 */
class Tracking
{
    public static function register(): void
    {
        $instance = new self();
        add_action('wp_head', [$instance, 'outputHeadTracking'], 20);
        add_action('wp_body_open', [$instance, 'outputBodyTracking'], 1);
    }

    /**
     * Injects GA4 / GTM script in <head>.
     */
    public function outputHeadTracking(): void
    {
        if ($this->shouldExcludeUser()) {
            return;
        }

        $googleOpts = get_option('laca_google_services', []);
        $ga4_id = is_array($googleOpts) ? trim((string) ($googleOpts['ga4_measurement_id'] ?? '')) : '';
        if ($ga4_id === '' && class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $ga4_id = trim((string) \App\Features\LacaSeo\Core\Settings::get('general.ga4_measurement_id', ''));
        }

        $gtm_id = is_array($googleOpts) ? trim((string) ($googleOpts['gtm_container_id'] ?? '')) : '';
        if ($gtm_id === '' && class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $gtm_id = trim((string) \App\Features\LacaSeo\Core\Settings::get('general.gtm_container_id', ''));
        }

        $anonymize = is_array($googleOpts) && isset($googleOpts['ga_anonymize_ip'])
            ? ($googleOpts['ga_anonymize_ip'] === 'on')
            : (class_exists(\App\Features\LacaSeo\Core\Settings::class) ? \App\Features\LacaSeo\Core\Settings::get('general.ga_anonymize_ip', 'on') === 'on' : true);

        $manualScripts = self::getManualScripts();

        // 1. Google Analytics 4 (Chống đếm trùng nếu đã chèn thủ công ở Laca Theme -> Scripts)
        if (!empty($ga4_id)) {
            if (stripos($manualScripts, $ga4_id) !== false) {
                echo "<!-- Google tag (gtag.js) [{$ga4_id}] omitted: already embedded in Laca Theme Scripts -->\n";
            } else {
                echo "<!-- Google tag (gtag.js) -->\n";
                echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($ga4_id) . '"></script>' . "\n";
                echo "<script>\n";
                echo "  window.dataLayer = window.dataLayer || [];\n";
                echo "  function gtag(){dataLayer.push(arguments);}\n";
                echo "  gtag('js', new Date());\n";
                $config = [];
                if ($anonymize) {
                    $config['anonymize_ip'] = true;
                }
                echo "  gtag('config', '" . esc_js($ga4_id) . "', " . (!empty($config) ? wp_json_encode($config) : '{}') . ");\n";
                echo "</script>\n";
            }
        }

        // 2. Google Tag Manager (Chống đếm trùng nếu đã chèn thủ công)
        if (!empty($gtm_id)) {
            if (stripos($manualScripts, $gtm_id) !== false) {
                echo "<!-- Google Tag Manager [{$gtm_id}] omitted: already embedded in Laca Theme Scripts -->\n";
            } else {
                echo "<!-- Google Tag Manager - Laca SEO -->\n";
                echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
                echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
                echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
                echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
                echo "})(window,document,'script','dataLayer','" . esc_js($gtm_id) . "');</script>\n";
                echo "<!-- End Google Tag Manager -->\n";
            }
        }
    }

    /**
     * Injects GTM noscript iframe in <body>.
     */
    public function outputBodyTracking(): void
    {
        if ($this->shouldExcludeUser()) {
            return;
        }

        $googleOpts = get_option('laca_google_services', []);
        $gtm_id = is_array($googleOpts) ? trim((string) ($googleOpts['gtm_container_id'] ?? '')) : '';
        if ($gtm_id === '' && class_exists(\App\Features\LacaSeo\Core\Settings::class)) {
            $gtm_id = trim((string) \App\Features\LacaSeo\Core\Settings::get('general.gtm_container_id', ''));
        }

        if (!empty($gtm_id)) {
            $manualScripts = self::getManualScripts();
            if (stripos($manualScripts, $gtm_id) !== false) {
                return;
            }
            echo '<!-- Google Tag Manager (noscript) -->' . "\n";
            echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm_id) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
            echo '<!-- End Google Tag Manager (noscript) -->' . "\n";
        }
    }

    /**
     * Get manual scripts content from Carbon theme options.
     */
    public static function getManualScripts(): string
    {
        $scripts = '';
        if (function_exists('carbon_get_theme_option')) {
            $scripts = (string) carbon_get_theme_option('crb_header_script') . (string) carbon_get_theme_option('crb_footer_script');
        }
        if (empty($scripts)) {
            $scripts = (string) get_option('_crb_header_script', '') . (string) get_option('crb_header_script', '')
                     . (string) get_option('_crb_footer_script', '') . (string) get_option('crb_footer_script', '');
        }
        return $scripts;
    }

    /**
     * Extracts manual Google Measurement or Tag Manager IDs from theme scripts.
     *
     * @return array<string>
     */
    public static function findManualGoogleIds(): array
    {
        $scripts = self::getManualScripts();
        if (empty($scripts)) {
            return [];
        }

        $ids = [];
        if (preg_match_all('/\b(G-[A-Z0-9]{4,}|GTM-[A-Z0-9]{4,}|UA-\d+-\d+)\b/', $scripts, $matches)) {
            $ids = array_unique($matches[1]);
        }
        return array_values($ids);
    }

    /**
     * Check if tracking should be excluded for currently logged in administrator.
     */
    private function shouldExcludeUser(): bool
    {
        $googleOpts = get_option('laca_google_services', []);
        $exclude = is_array($googleOpts) && isset($googleOpts['ga_exclude_loggedin'])
            ? ($googleOpts['ga_exclude_loggedin'] === 'on')
            : (class_exists(\App\Features\LacaSeo\Core\Settings::class) ? \App\Features\LacaSeo\Core\Settings::get('general.ga_exclude_loggedin', 'on') === 'on' : true);

        if ($exclude && current_user_can('manage_options')) {
            return true;
        }
        return false;
    }
}
