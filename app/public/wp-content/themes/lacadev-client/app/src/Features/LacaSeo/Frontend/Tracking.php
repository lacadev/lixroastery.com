<?php

namespace App\Features\LacaSeo\Frontend;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Injects Google Analytics 4 and Google Tag Manager measurement scripts.
 * Supports IP anonymization and exclusion of logged-in administrators.
 */
class Tracking
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'outputHeadTracking'], 20);
        add_action('wp_body_open', [$this, 'outputBodyTracking'], 1);
    }

    /**
     * Injects GA4 / GTM script in <head>.
     */
    public function outputHeadTracking(): void
    {
        if ($this->shouldExcludeUser()) {
            return;
        }

        $ga4_id = trim((string) Settings::get('general.ga4_measurement_id', ''));
        $gtm_id = trim((string) Settings::get('general.gtm_container_id', ''));
        $anonymize = Settings::get('general.ga_anonymize_ip', 'on') === 'on';

        // 1. Google Analytics 4
        if (!empty($ga4_id)) {
            echo "<!-- Google tag (gtag.js) - Laca SEO -->\n";
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

        // 2. Google Tag Manager
        if (!empty($gtm_id)) {
            echo "<!-- Google Tag Manager - Laca SEO -->\n";
            echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
            echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
            echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
            echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
            echo "})(window,document,'script','dataLayer','" . esc_js($gtm_id) . "');</script>\n";
            echo "<!-- End Google Tag Manager -->\n";
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

        $gtm_id = trim((string) Settings::get('general.gtm_container_id', ''));
        if (!empty($gtm_id)) {
            echo '<!-- Google Tag Manager (noscript) - Laca SEO -->' . "\n";
            echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm_id) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
            echo '<!-- End Google Tag Manager (noscript) -->' . "\n";
        }
    }

    /**
     * Check if tracking should be excluded for currently logged in administrator.
     */
    private function shouldExcludeUser(): bool
    {
        if (Settings::get('general.ga_exclude_loggedin', 'on') === 'on' && current_user_can('manage_options')) {
            return true;
        }
        return false;
    }
}
