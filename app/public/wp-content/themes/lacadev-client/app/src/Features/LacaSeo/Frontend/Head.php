<?php

namespace App\Features\LacaSeo\Frontend;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Frontend\OpenGraph\Facebook;
use App\Features\LacaSeo\Frontend\OpenGraph\Slack;
use App\Features\LacaSeo\Frontend\OpenGraph\Twitter;
use App\Features\LacaSeo\Frontend\Paper\Paper;

defined('ABSPATH') || exit;

/**
 * Main frontend <head> builder for Laca SEO.
 * Fully follows Rank Math Paper architecture and credit comments.
 */
class Head
{
    private Facebook $facebook;
    private Twitter $twitter;
    private Slack $slack;

    public function __construct()
    {
        $this->facebook = new Facebook();
        $this->twitter  = new Twitter();
        $this->slack    = new Slack();
    }

    public function register(): void
    {
        // 1. Hook early into wp_head at priority 1
        add_action('wp_head', [$this, 'outputHead'], 1);

        // 2. Remove default WP core actions handled by Laca SEO
        remove_action('wp_head', 'rel_canonical');
        remove_action('wp_head', 'noindex', 1);
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);

        // 3. Document title filters
        add_filter('pre_get_document_title', [$this, 'filterTitle'], 15);
        add_filter('wp_title', [$this, 'filterTitle'], 15);

        // 4. Remove core robots data to avoid duplicates, as we output <meta name="robots"> directly in outputHead() like Rank Math
        if (!is_embed()) {
            remove_all_filters('wp_robots');
        }
    }

    /**
     * Filter document title using Paper architecture.
     */
    public function filterTitle(string $title): string
    {
        if (is_feed()) {
            return $title;
        }

        $generated = Paper::get()->get_title();
        return ($generated !== '') ? $generated : $title;
    }

    /**
     * Main <head> tags emitter.
     */
    public function outputHead(): void
    {
        global $wp_query;

        $old_wp_query = null;
        if (!$wp_query->is_main_query()) {
            $old_wp_query = $wp_query;
            wp_reset_query();
        }

        // Credit comments matching Rank Math
        echo "<!-- / SEO by Laca SEO - Professional Framework -->\n";

        // Description
        $this->outputDescription();

        // Robots (outputs directly like Rank Math, remove_all_filters('wp_robots') ensures no duplicate)
        $this->outputRobots();

        // Canonical
        $this->outputCanonical();

        // Adjacent rel links (prev / next)
        $this->outputAdjacentRelLinks();

        // OpenGraph (Facebook)
        $this->facebook->output();

        // Twitter Cards
        $this->twitter->output();

        // Slack Enhanced Data
        $this->slack->output();

        // Webmaster Tools (on front page)
        if (is_front_page()) {
            $this->outputWebmasterVerification();
        }

        // Schema JSON-LD Graph
        do_action('laca_seo/head');

        echo "<!-- / Laca SEO -->\n";

        if (!empty($old_wp_query)) {
            $GLOBALS['wp_query'] = $old_wp_query;
            unset($old_wp_query);
        }
    }

    /**
     * Output meta description tag.
     */
    private function outputDescription(): void
    {
        $desc = Paper::get()->get_description();
        if ($desc !== '') {
            echo '<meta name="description" content="' . esc_attr($desc) . '"/>' . "\n";
        }
    }

    /**
     * Output meta robots tag matching Rank Math.
     */
    private function outputRobots(): void
    {
        $robots = Paper::get()->get_robots();
        $robotsStr = join(', ', $robots);
        if ($robotsStr !== '') {
            echo '<meta name="robots" content="' . esc_attr($robotsStr) . '"/>' . "\n";
        }
    }

    /**
     * Output rel="canonical" link tag.
     */
    private function outputCanonical(): void
    {
        $robots = Paper::get()->get_robots();
        // If current page is noindex, omit canonical (Google recommendation & RM rule)
        if (isset($robots['index']) && 'noindex' === $robots['index']) {
            return;
        }

        $canonical = Paper::get()->get_canonical();
        if ($canonical !== '') {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }
    }

    /**
     * Output rel="prev" and rel="next" links.
     */
    private function outputAdjacentRelLinks(): void
    {
        $robots = Paper::get()->get_robots();
        if (isset($robots['index']) && 'noindex' === $robots['index']) {
            return;
        }

        if (is_singular()) {
            $this->adjacentRelLinksSingle();
            return;
        }

        $this->adjacentRelLinksArchive();
    }

    private function adjacentRelLinksSingle(): void
    {
        $queried = get_queried_object();
        if (!$queried || !($queried instanceof \WP_Post) || empty($queried->post_content)) {
            return;
        }

        $numPages = substr_count($queried->post_content, '<!--nextpage-->') + 1;
        if ($numPages <= 1) {
            return;
        }

        $page = max(1, (int) get_query_var('page'));
        $url = get_permalink($queried->ID);

        if ($page > 1) {
            $this->printAdjacentRelLink('prev', $url, $page - 1, 'page');
        }

        if ($page < $numPages) {
            $this->printAdjacentRelLink('next', $url, $page + 1, 'page');
        }
    }

    private function adjacentRelLinksArchive(): void
    {
        $url = Paper::get()->get_canonical(true, true);
        if ($url === '') {
            return;
        }

        $paged = max(1, (int) get_query_var('paged'));
        global $wp_query;
        $maxPages = (int) ($wp_query->max_num_pages ?? 1);

        if ($paged === 2) {
            $this->printAdjacentRelLink('prev', $url, $paged - 1);
        } elseif ($paged > 2) {
            $this->printAdjacentRelLink('prev', $url, $paged - 1);
        }

        if ($paged < $maxPages) {
            $this->printAdjacentRelLink('next', $url, $paged + 1);
        }
    }

    private function printAdjacentRelLink(string $rel, string $url, int $page, string $queryArg = 'paged'): void
    {
        global $wp_rewrite;

        if (!$wp_rewrite || !$wp_rewrite->using_permalinks()) {
            $link = ($page === 1) ? $url : add_query_arg($queryArg, $page, $url);
        } else {
            if ($queryArg === 'page') {
                $link = ($page === 1) ? $url : user_trailingslashit(trailingslashit($url) . $page);
            } else {
                $link = ($page === 1) ? $url : user_trailingslashit(trailingslashit($url) . trailingslashit($wp_rewrite->pagination_base) . $page);
            }
        }

        echo '<link rel="' . esc_attr($rel) . '" href="' . esc_url($link) . '" />' . "\n";
    }

    /**
     * Filter core wp_robots array matching Paper get_robots().
     *
     * @param array<string, bool|string> $robots
     * @return array<string, bool|string>
     */
    public function filterRobots(array $robots): array
    {
        $custom = Paper::get()->get_robots();

        $merged = [];
        if (isset($custom['index']) && 'noindex' === $custom['index']) {
            $merged['noindex'] = true;
        }

        if (isset($custom['follow']) && 'nofollow' === $custom['follow']) {
            $merged['nofollow'] = true;
        } elseif (!isset($merged['noindex'])) {
            $merged['follow'] = true;
        }

        if (isset($custom['noarchive'])) {
            $merged['noarchive'] = true;
        }
        if (isset($custom['noimageindex'])) {
            $merged['noimageindex'] = true;
        }
        if (isset($custom['nosnippet'])) {
            $merged['nosnippet'] = true;
        }

        if (!isset($merged['noindex']) && !isset($merged['nosnippet'])) {
            $merged['max-snippet'] = -1;
            $merged['max-image-preview'] = 'large';
            $merged['max-video-preview'] = -1;
        }

        return $merged;
    }

    /**
     * Webmaster verification meta tags on front page.
     */
    private function outputWebmasterVerification(): void
    {
        $tags = [
            'google_verify'    => 'google-site-verification',
            'bing_verify'      => 'msvalidate.01',
            'baidu_verify'     => 'baidu-site-verification',
            'yandex_verify'    => 'yandex-verification',
            'pinterest_verify' => 'p:domain_verify',
            'norton_verify'    => 'norton-safeweb-site-verification',
        ];

        foreach ($tags as $optKey => $metaName) {
            $val = trim((string) Settings::get("general.{$optKey}", ''));
            if ($val !== '') {
                echo '<meta name="' . esc_attr($metaName) . '" content="' . esc_attr($val) . '" />' . "\n";
            }
        }

        $custom = (string) Settings::get('general.custom_webmaster_tags', '');
        if (trim($custom) !== '') {
            echo wp_kses($custom, [
                'meta' => [
                    'name'     => [],
                    'content'  => [],
                    'property' => [],
                ],
            ]) . "\n";
        }
    }
}
