<?php

namespace App\Features\LacaSeo\Modules\Redirections;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Handles incoming request redirection logic (1:1 with Rank Math Redirector).
 */
class Redirector
{
    private array|false $matched = false;
    private string $redirectTo = '';
    private string $uri = '';
    private string $fullUri = '';
    private string $queryString = '';
    private bool $fromCache = false;

    public function __construct()
    {
        $this->start();
        $this->flow();
        $this->redirect();
    }

    private function start(): void
    {
        $this->fullUri = Redirection::getFullUri();

        $parts = explode('?', $this->fullUri, 2);
        if (isset($parts[1])) {
            $this->queryString = $parts[1];
        }
        $this->uri = trim($parts[0], '/');
    }

    private function flow(): void
    {
        $flow = ['preFilter', 'fromCacheMethod', 'everything', 'fallback'];
        foreach ($flow as $func) {
            if (false !== $this->matched) {
                break;
            }
            $this->$func();
        }
    }

    private function preFilter(): void
    {
        $pre = apply_filters('laca_seo/redirection/pre_search', null, $this->uri, $this->fullUri);
        if (null === $pre || !is_array($pre)) {
            return;
        }

        $this->matched = $pre;
        $this->redirectTo = $pre['url_to'] ?? '';
    }

    private function fromCacheMethod(): void
    {
        $objectId = (int) get_queried_object_id();
        $objectType = $this->getCurrentObjectType();

        $redirections = Cache::getByObjectIdOrUrl($objectId, $objectType, $this->uri);
        foreach ($redirections as $redirection) {
            if (empty($redirection->object_id)) {
                $this->fromCache = true;
                $this->setRedirection((int) $redirection->redirection_id);
                return;
            }

            if (trim($redirection->from_url, '/') === $this->uri) {
                $this->fromCache = true;
                $this->setRedirection((int) $redirection->redirection_id);
                return;
            }
        }
    }

    private function everything(): void
    {
        $redirection = DB::matchRedirections($this->uri);
        if (!$redirection && $this->uri !== $this->fullUri) {
            $redirection = DB::matchRedirections($this->fullUri);
        }

        if ($redirection) {
            Cache::add([
                'from_url'       => $this->uri,
                'redirection_id' => $redirection['id'],
                'object_id'      => 0,
                'object_type'    => 'any',
                'is_redirected'  => 1,
            ]);
            $this->setRedirection($redirection);
        }
    }

    private function fallback(): void
    {
        if (!is_404()) {
            return;
        }

        $adminLocations = apply_filters('laca_seo/redirection/fallback_exclude_locations', ['login', 'admin', 'dashboard']);
        if (in_array($this->uri, $adminLocations, true)) {
            return;
        }

        $behavior = (string) Settings::get('general.redirections_fallback', 'default');
        if ('default' === $behavior) {
            return;
        }

        if ('homepage' === $behavior) {
            $this->matched = [
                'header_code' => 301,
            ];
            $this->redirectTo = home_url('/');
            return;
        }

        $customUrl = (string) Settings::get('general.redirections_custom_url', '');
        if (!empty($customUrl)) {
            $this->matched = [
                'header_code' => 301,
            ];
            $this->redirectTo = $customUrl;
        }
    }

    private function setRedirection(array|int $redirection): void
    {
        if (!is_array($redirection)) {
            $redirection = DB::getRedirectionById($redirection, 'active');
        }

        if (false === $redirection) {
            return;
        }

        // Pro: Check schedule activation/deactivation if applicable
        if (Schedule::isScheduledOut($redirection['id'])) {
            return;
        }

        $customMatch = apply_filters('laca_seo/redirection/redirection_match', false, $redirection);
        $sources = $redirection['sources'] ?? [];

        // Support matching against full URI with query string (Pro feature)
        $matched = DB::compareSources($sources, $this->uri);
        if (!$matched && $this->fullUri !== '' && $this->fullUri !== $this->uri) {
            $matched = DB::compareSources($sources, $this->fullUri);
        }

        if (!$matched && !$customMatch) {
            return;
        }

        if (isset($redirection['url_to'])) {
            $this->matched = $redirection;
            $this->setRedirectTo();
        }
    }

    private function setRedirectTo(): void
    {
        $this->redirectTo = $this->matched['url_to'] ?? '';
        foreach ($this->matched['sources'] ?? [] as $source) {
            $this->setRedirectToRegex($source);
        }
    }

    private function setRedirectToRegex(array $source): void
    {
        if ('regex' !== ($source['comparison'] ?? '')) {
            return;
        }

        $pattern = DB::getCleanPattern($source['pattern'], 'regex');
        if (DB::stringComparison($pattern, $this->uri, 'regex')) {
            $this->redirectTo = preg_replace($pattern, $this->redirectTo, $this->uri);
        } elseif ($this->fullUri !== $this->uri && DB::stringComparison($pattern, $this->fullUri, 'regex')) {
            $this->redirectTo = preg_replace($pattern, $this->redirectTo, $this->fullUri);
        }
    }

    public function redirect(): void
    {
        if (false === $this->matched) {
            return;
        }

        if (isset($this->matched['id'], $this->matched['url_to'])) {
            DB::updateAccess($this->matched);
        }

        $headerCode = (int) ($this->matched['header_code'] ?? Settings::get('general.redirections_header_code', 301));

        if (in_array($headerCode, [410, 451], true)) {
            $this->redirectWithoutTarget($headerCode);
            return;
        }

        // Debug mode
        if ($this->isDebug()) {
            $this->renderDebugScreen($headerCode);
            exit;
        }

        if (apply_filters('laca_seo/redirection/add_query_string', true, $this->matched) && $this->queryString !== '') {
            $sep = strpos($this->redirectTo, '?') !== false ? '&' : '?';
            $this->redirectTo .= $sep . $this->queryString;
        }

        // Clean any CRLF before header
        $target = str_replace(["\r", "\n"], '', $this->redirectTo);

        if (wp_redirect(esc_url_raw($target), $headerCode, 'Laca SEO')) {
            exit;
        }
    }

    private function redirectWithoutTarget(int $headerCode): void
    {
        global $wp_query;
        if (!is_object($wp_query)) {
            $wp_query = new \WP_Query();
        }
        $wp_query->is_404 = true;

        if (410 === $headerCode) {
            status_header(410);
        } elseif (451 === $headerCode) {
            status_header(451, 'Unavailable For Legal Reasons');
        }

        $template = get_query_template((string) $headerCode);
        if (!empty($template) && file_exists($template)) {
            include $template;
            exit;
        }
    }

    private function isDebug(): bool
    {
        return Settings::get('general.redirections_debug', 'off') === 'on' && current_user_can('manage_options');
    }

    private function renderDebugScreen(int $headerCode): void
    {
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="utf-8">
            <title>Laca SEO - Redirection Debug Console</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f0f0f1; color: #3c434a; padding: 40px 20px; }
                .debug-card { max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
                h1 { margin-top: 0; font-size: 20px; color: #1d2327; border-bottom: 1px solid #dcdcde; padding-bottom: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 16px; }
                th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #f0f0f1; }
                th { color: #646970; width: 160px; }
                .btn { display: inline-block; background: #2271b1; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 3px; margin-top: 20px; }
                .btn:hover { background: #135e96; }
            </style>
        </head>
        <body>
            <div class="debug-card">
                <h1>Chế độ gỡ lỗi chuyển hướng (Debug Console)</h1>
                <p>Bạn nhìn thấy màn hình này vì đang bật chế độ <strong>Debug Redirections</strong> và có quyền Quản trị viên.</p>
                <table>
                    <tr><th>URL yêu cầu:</th><td><code>/<?php echo esc_html($this->uri); ?></code></td></tr>
                    <tr><th>Mã trạng thái:</th><td><strong><?php echo esc_html((string) $headerCode); ?></strong></td></tr>
                    <tr><th>Đích đến:</th><td><code><?php echo esc_html($this->redirectTo); ?></code></td></tr>
                    <tr><th>Nguồn quy tắc ID:</th><td><?php echo esc_html((string) ($this->matched['id'] ?? 'N/A')); ?></td></tr>
                </table>
                <a href="<?php echo esc_url($this->redirectTo); ?>" class="btn">Tiếp tục chuyển hướng &rarr;</a>
            </div>
        </body>
        </html>
        <?php
    }

    private function getCurrentObjectType(): string
    {
        $object = get_queried_object();
        if (!$object) {
            return 'none';
        }

        if ($object instanceof \WP_Post) {
            return 'post';
        }
        if ($object instanceof \WP_Term) {
            return 'term';
        }
        if ($object instanceof \WP_User) {
            return 'user';
        }

        return 'none';
    }
}
