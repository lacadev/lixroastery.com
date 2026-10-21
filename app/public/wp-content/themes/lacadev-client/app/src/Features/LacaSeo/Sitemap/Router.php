<?php

namespace App\Features\LacaSeo\Sitemap;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Handles sitemap routing, rewrite rules, and force queries.
 */
class Router
{
    public function register(): void
    {
        // 1. Handle force frontend request for dev/testing when RM is active or forced
        add_action('wp_loaded', [$this, 'handleForceSitemapRequest'], 1);

        // 2. Only register live rewrite rules when Rank Math is inactive OR not handling sitemaps
        if (!Helper::isRankMathActive() || Helper::isForceFrontend()) {
            add_action('init', [$this, 'registerRewriteRules'], 2);
            add_action('parse_query', [$this, 'parseSitemapQuery'], 1);
            add_action('template_redirect', [$this, 'templateRedirect'], 0);
        }
    }

    /**
     * Handle direct force query: /?laca_seo_force_frontend=1&laca_sitemap=index (or &laca_sitemap=page&laca_sitemap_n=1)
     */
    public function handleForceSitemapRequest(): void
    {
        if (!isset($_GET['laca_sitemap'])) {
            return;
        }

        if (!Helper::shouldRunFrontend() && !Helper::isForceFrontend()) {
            return;
        }

        @ini_set('memory_limit', '512M');
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $type = sanitize_key($_GET['laca_sitemap']);
        $n = (int) ($_GET['laca_sitemap_n'] ?? 1);
        if ($n < 1) {
            $n = 1;
        }

        if ($type === 'xsl') {
            (new Stylesheet())->output('main');
            exit;
        }

        $generator = new Generator();
        $output = $generator->getOutput($type, $n);

        if (empty($output)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        (new class extends AbstractXml {
            public function send(string $body): void {
                $this->sendHeaders();
                echo $body;
                exit;
            }
        })->send($output);
    }

    public function registerRewriteRules(): void
    {
        global $wp;

        $base = self::getSitemapBase();
        $wp->add_query_var('laca_sitemap');
        $wp->add_query_var('laca_sitemap_n');
        $wp->add_query_var('laca_xsl');

        if (!Helper::isRankMathActive()) {
            add_rewrite_rule($base . 'sitemap_index\\.xml$', 'index.php?laca_sitemap=1', 'top');
            add_rewrite_rule($base . '([^/]+?)-sitemap([0-9]+)?\\.xml$', 'index.php?laca_sitemap=$matches[1]&laca_sitemap_n=$matches[2]', 'top');
            add_rewrite_rule($base . '([a-z]+)?-?sitemap\\.xsl$', 'index.php?laca_xsl=$matches[1]', 'top');
            add_rewrite_rule($base . 'main-sitemap\\.xsl$', 'index.php?laca_xsl=main', 'top');
        }
    }

    /**
     * @param \WP_Query $query
     */
    public function parseSitemapQuery(\WP_Query $query): void
    {
        if (!$query->is_main_query()) {
            return;
        }

        $xsl = get_query_var('laca_xsl');
        if (!empty($xsl)) {
            (new Stylesheet())->output($xsl);
            exit;
        }

        $type = get_query_var('laca_sitemap');
        if (empty($type)) {
            return;
        }

        @ini_set('memory_limit', '512M');
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $n = (int) get_query_var('laca_sitemap_n', 1);
        if ($n < 1) {
            $n = 1;
        }

        $cache = new Cache();
        $cached = $cache->getSitemap($type, $n);
        if ($cached !== false) {
            (new class extends AbstractXml {
                public function send(string $body): void {
                    $this->sendHeaders();
                    echo $body;
                    exit;
                }
            })->send($cached);
        }

        $generator = new Generator();
        $output = $generator->getOutput($type, $n);
        if (empty($output)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        $cache->storeSitemap($type, $n, $output);

        (new class extends AbstractXml {
            public function send(string $body): void {
                $this->sendHeaders();
                echo $body;
                exit;
            }
        })->send($output);
    }

    public function templateRedirect(): void
    {
        $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);

        // 1. Redirect /sitemap.xml -> /sitemap_index.xml
        if ($path === '/sitemap.xml') {
            wp_safe_redirect(home_url('/sitemap_index.xml'), 301);
            exit;
        }

        // 2. Redirect core wp-sitemap*.xml
        if (str_starts_with($path, '/wp-sitemap')) {
            if ($path === '/wp-sitemap.xml') {
                wp_safe_redirect(home_url('/sitemap_index.xml'), 301);
                exit;
            }
            if (preg_match('/^\/wp-sitemap-(posts|taxonomies)-(\w+)-(\d+)\.xml$/', $path, $matches)) {
                $index = ((int) $matches[3] - 1);
                $index = (0 !== $index) ? (string) $index : '';
                wp_safe_redirect(home_url('/' . $matches[2] . '-sitemap' . $index . '.xml'), 301);
                exit;
            }
            if (preg_match('/^\/wp-sitemap-users-(\d+)\.xml$/', $path, $matches)) {
                $index = ((int) $matches[1] - 1);
                $index = (0 !== $index) ? (string) $index : '';
                wp_safe_redirect(home_url('/author-sitemap' . $index . '.xml'), 301);
                exit;
            }
        }
    }

    public static function getSitemapBase(): string
    {
        global $wp_rewrite;
        $base = ($wp_rewrite && $wp_rewrite->using_index_permalinks()) ? $wp_rewrite->index . '/' : '';
        return apply_filters('laca_seo/sitemap/base_url', $base);
    }

    public static function getBaseUrl(string $page): string
    {
        return home_url(self::getSitemapBase() . $page);
    }
}
