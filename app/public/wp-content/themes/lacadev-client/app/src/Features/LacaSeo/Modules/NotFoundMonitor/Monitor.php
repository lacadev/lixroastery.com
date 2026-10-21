<?php

namespace App\Features\LacaSeo\Modules\NotFoundMonitor;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Modules\Redirections\DB as RedirectionsDB;

defined('ABSPATH') || exit;

/**
 * 404 Monitor: captures and logs 404 hits.
 */
class Monitor
{
    private bool $is404 = false;

    public function register(): void
    {
        if (is_admin()) {
            add_action('wp_ajax_laca_seo_delete_404_log', [$this, 'ajaxDeleteLog']);
            add_action('wp_ajax_laca_seo_clear_404_logs', [$this, 'ajaxClearLogs']);
        }

        if (Modules::isActive('404-monitor')) {
            if (Helper::shouldRunFrontend()) {
                add_action('wp', [$this, 'save404Flag']);
                add_action('template_redirect', [$this, 'capture404'], 999);
            }
        }
    }

    public function save404Flag(): void
    {
        $this->is404 = is_404();
    }

    public function capture404(): void
    {
        if ((!$this->is404 && !is_404()) || in_array(http_response_code(), [410, 451], true)) {
            return;
        }

        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $ignoreQuery = Settings::get('general.404_monitor_ignore_query_parameters', 'off') === 'on';

        $fullUri = untrailingslashit(self::getCurrentPageUrl($ignoreQuery));
        $fullUri = preg_replace('/(?<=\/)(https?:\/[^\s]*)/i', '', $fullUri);
        $uri = str_replace(home_url('/'), '', $fullUri);
        $uri = trim($uri, '/');

        if (!$uri) {
            return;
        }

        if ($this->isUrlExcluded($uri)) {
            return;
        }

        $mode = (string) Settings::get('general.404_monitor_mode', 'simple');

        if ('simple' === $mode) {
            DB::update(['uri' => $uri]);
            return;
        }

        DB::add([
            'uri'        => $uri,
            'referer'    => wp_unslash($_SERVER['HTTP_REFERER'] ?? ''),
            'user_agent' => wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
    }

    private function isUrlExcluded(string $uri): bool
    {
        $excludes = Settings::get('general.404_monitor_exclude', []);
        if (is_string($excludes)) {
            $lines = array_filter(array_map('trim', explode("\n", $excludes)));
            $excludes = [];
            foreach ($lines as $line) {
                $excludes[] = ['exclude' => $line, 'comparison' => 'contains'];
            }
        }

        if (!is_array($excludes)) {
            return false;
        }

        foreach ($excludes as $rule) {
            $comparison = empty($rule['comparison']) ? 'exact' : $rule['comparison'];
            $pattern    = empty($rule['exclude']) ? '' : $rule['exclude'];

            if (!empty($pattern) && RedirectionsDB::stringComparison($pattern, $uri, $comparison)) {
                return true;
            }
        }

        return false;
    }

    public static function getCurrentPageUrl(bool $ignoreQuery = false): string
    {
        $serverUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($ignoreQuery) {
            $parts = explode('?', $serverUri, 2);
            $serverUri = $parts[0];
        }

        return home_url($serverUri);
    }

    public function ajaxDeleteLog(): void
    {
        check_ajax_referer('laca_seo_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = (int) ($_POST['log'] ?? 0);
        if ($id > 0) {
            DB::deleteLog($id);
            wp_send_json_success();
        }

        wp_send_json_error(['message' => 'Invalid ID']);
    }

    public function ajaxClearLogs(): void
    {
        check_ajax_referer('laca_seo_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        DB::clearLogs();
        wp_send_json_success();
    }
}
