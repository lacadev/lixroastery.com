<?php

namespace App\Features\LacaSeo\Frontend\Paper;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

/**
 * Paper orchestrator matching Rank Math Paper factory.
 */
class Paper
{
    private static ?Paper $instance = null;
    private IPaper $paper;

    private ?string $title = null;
    private ?string $description = null;
    private ?array $robots = null;
    private ?array $canonical = null;
    private ?string $keywords = null;

    public static function get(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new self();
        self::$instance->setup();
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function setup(): void
    {
        $hash = [
            'Search'    => is_search(),
            'Shop'      => self::isShopPage(),
            'Singular'  => self::isHomeStaticPage() || self::isSimplePage(),
            'Blog'      => self::isHomePostsPage(),
            'Author'    => is_author(),
            'Date'      => is_date(),
            'Taxonomy'  => is_category() || is_tag() || is_tax(),
            'Archive'   => is_archive(),
            'Error_404' => is_404(),
            'Misc'      => true,
        ];

        foreach ($hash as $className => $isValid) {
            if ($isValid) {
                $fullClass = __NAMESPACE__ . '\\' . $className;
                if (class_exists($fullClass)) {
                    $this->paper = new $fullClass();
                    break;
                }
            }
        }

        if (!isset($this->paper)) {
            $this->paper = new Misc();
        }

        if (method_exists($this->paper, 'set_object')) {
            if (self::isHomeStaticPage()) {
                $this->paper->set_object(get_queried_object());
            } elseif (self::isSimplePage()) {
                $this->paper->set_object(get_post());
            }
        }
    }

    public static function isShopPage(): bool
    {
        return function_exists('is_shop') && is_shop();
    }

    public static function isHomeStaticPage(): bool
    {
        return is_front_page() && 'page' === get_option('show_on_front') && (int) get_option('page_on_front') > 0;
    }

    public static function isSimplePage(): bool
    {
        return is_singular();
    }

    public static function isHomePostsPage(): bool
    {
        return is_home();
    }

    public function get_title(): string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $raw = $this->paper->title();
        if ($raw === '') {
            $this->title = '';
            return '';
        }

        $title = preg_replace('/\s+/', ' ', $raw);
        if (Settings::get('titles.capitalize_titles') === 'on') {
            $title = mb_convert_case($title, MB_CASE_TITLE, 'UTF-8');
        }

        $title = wp_strip_all_tags(stripslashes($title), true);
        $title = esc_html($title);
        $title = convert_smilies($title);

        $this->title = trim($title);
        return $this->title;
    }

    public function get_description(): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        $raw = $this->paper->description();
        $this->description = trim(esc_attr(wp_strip_all_tags(stripslashes($raw), true)));
        return $this->description;
    }

    public function get_robots(): array
    {
        if ($this->robots !== null) {
            return $this->robots;
        }

        $this->robots = $this->paper->robots();
        if (empty($this->robots)) {
            $this->robots = self::robots_combine((array) Settings::get('titles.robots_global', ['index']));
        }

        $this->validate_robots();
        $this->respect_settings_for_robots();
        $this->advanced_robots();

        return $this->robots;
    }

    private function validate_robots(): void
    {
        if (empty($this->robots) || !is_array($this->robots)) {
            $this->robots = [
                'index'  => 'index',
                'follow' => 'follow',
            ];
            return;
        }

        $this->robots = array_intersect_key(
            $this->robots,
            [
                'index'        => '',
                'follow'       => '',
                'noarchive'    => '',
                'noimageindex' => '',
                'nosnippet'    => '',
            ]
        );

        if (!isset($this->robots['index'])) {
            $this->robots = ['index' => 'index'] + $this->robots;
        }
        if (!isset($this->robots['follow'])) {
            $this->robots = ['follow' => 'follow'] + $this->robots;
        }
    }

    private function respect_settings_for_robots(): void
    {
        if (0 === absint(get_option('blog_public', 1)) || isset($_GET['replytocom'])) {
            $this->robots['index']  = 'noindex';
            $this->robots['follow'] = 'nofollow';
        }

        if (is_paged() && Settings::get('titles.noindex_archive_subpages', 'off') === 'on') {
            $this->robots['index'] = 'noindex';
        }
    }

    private function advanced_robots(): void
    {
        if ((isset($this->robots['index']) && 'noindex' === $this->robots['index']) || (isset($this->robots['nosnippet']) && 'nosnippet' === $this->robots['nosnippet'])) {
            return;
        }

        $adv = $this->paper->advanced_robots();
        if (!is_array($adv) || empty($adv)) {
            $advGlobal = (array) Settings::get('titles.advanced_robots_global', [
                'max-snippet'       => -1,
                'max-video-preview' => -1,
                'max-image-preview' => 'large',
            ]);
            $adv = self::advanced_robots_combine($advGlobal);
        }

        $adv = array_intersect_key(
            $adv,
            [
                'max-snippet'       => '',
                'max-video-preview' => '',
                'max-image-preview' => '',
            ]
        );

        if (!empty($adv)) {
            $this->robots = $this->robots + $adv;
        }
    }

    public function get_canonical(bool $unPaged = false, bool $noOverride = false): string
    {
        if ($this->canonical === null) {
            $this->generate_canonical();
        }

        if ($unPaged) {
            return (string) ($this->canonical['canonical_unpaged'] ?? '');
        }
        if ($noOverride) {
            return (string) ($this->canonical['canonical_no_override'] ?? '');
        }

        return (string) ($this->canonical['canonical'] ?? '');
    }

    private function generate_canonical(): void
    {
        $raw = $this->paper->canonical();
        $this->canonical = wp_parse_args($raw, [
            'canonical'          => false,
            'canonical_unpaged'  => false,
            'canonical_override' => false,
        ]);

        $canonical = $this->canonical['canonical'] ?: home_url();
        $canonical_unpaged = $canonical;
        $canonical_override = $this->canonical['canonical_override'];

        if (is_front_page()) {
            $canonical = user_trailingslashit(home_url());
        }

        if (!is_singular()) {
            $canonical_unpaged = $canonical;
            $canonical = $this->get_canonical_paged($canonical);
        }

        $this->canonical['canonical_unpaged']     = $canonical_unpaged;
        $this->canonical['canonical_no_override'] = $canonical;

        if (!empty($canonical_override)) {
            $canonical = $canonical_override;
        }

        $this->canonical['canonical'] = $canonical;
    }

    private function get_canonical_paged(string $canonical): string
    {
        global $wp_rewrite;

        if (!$canonical || (int) get_query_var('paged') < 2) {
            return $canonical;
        }

        $paged = (int) get_query_var('paged');

        if (!$wp_rewrite || !$wp_rewrite->using_permalinks()) {
            return add_query_arg('paged', $paged, is_front_page() ? trailingslashit($canonical) : $canonical);
        }

        return user_trailingslashit(
            trailingslashit(is_front_page() ? home_url() : $canonical) .
            trailingslashit($wp_rewrite->pagination_base) .
            $paged
        );
    }

    public function get_keywords(): string
    {
        if ($this->keywords !== null) {
            return $this->keywords;
        }

        $this->keywords = trim($this->paper->keywords());
        return $this->keywords;
    }

    public static function get_from_options(string $id, mixed $args = [], string $defaultValue = ''): string
    {
        $value = (string) Settings::get("titles.{$id}", '');

        // Break loop if using default snippet macros
        if (!str_ends_with($id, 'default_snippet_name') && !str_ends_with($id, 'default_snippet_desc')) {
            $value = str_replace(
                ['%seo_title%', '%seo_description%'],
                ['%title%', '%excerpt%'],
                $value
            );
        }

        $template = ($value !== '') ? $value : $defaultValue;
        return ($template !== '') ? Variables::replace($template, $args) : '';
    }

    public static function robots_combine(mixed $robots, bool $defaultValue = false): array
    {
        if (empty($robots) || !is_array($robots)) {
            return !$defaultValue ? [] : [
                'index'  => 'index',
                'follow' => 'follow',
            ];
        }

        $res = array_combine($robots, $robots);

        if (isset($res['noindex'])) {
            $res = ['index' => 'noindex'] + $res;
            unset($res['noindex']);
        }

        if (isset($res['nofollow'])) {
            $res = ['follow' => 'nofollow'] + $res;
            unset($res['nofollow']);
        }

        return $res;
    }

    public static function advanced_robots_combine(mixed $advancedRobots): array
    {
        if (empty($advancedRobots) || !is_array($advancedRobots)) {
            return [];
        }

        $robots = [];
        foreach ($advancedRobots as $key => $data) {
            if ($data !== '' && $data !== null && $data !== false) {
                $robots[$key] = $key . ':' . $data;
            }
        }
        return $robots;
    }
}
