<?php

namespace App\Features\LacaSeo\Schema;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;
use App\Features\LacaSeo\Frontend\Paper\Paper;

defined('ABSPATH') || exit;

class JsonLd
{
    public ?\WP_Post $post = null;
    public int $postId = 0;
    public array $parts = [];

    public function register(): void
    {
        add_action('laca_seo/head', [$this, 'outputJsonLd'], 90);
        add_filter('laca_seo/json_ld', [$this, 'addContextData']);

        (new Frontend())->register();
    }

    public function outputJsonLd(): void
    {
        global $post;

        if (is_singular() && $post instanceof \WP_Post) {
            $this->post = $post;
            $this->postId = $post->ID;
            setup_postdata($post);
            $this->setupParts();
        }

        $data = apply_filters('laca_seo/json_ld', [], $this);

        if (empty($data)) {
            return;
        }

        $data = apply_filters('laca_seo/schema/validated_data', $this->validateSchema($data));

        if (is_array($data) && !empty($data)) {
            $json = [
                '@context' => 'https://schema.org',
                '@graph'   => array_values($data),
            ];

            $options = JSON_UNESCAPED_SLASHES;
            echo '<script type="application/ld+json" class="laca-seo-schema">' . wp_json_encode(wp_kses_post_deep($json), $options) . '</script>' . "\n";
        }
    }

    public function validateSchema(mixed $data): mixed
    {
        if (!is_array($data) || empty($data)) {
            return $data;
        }

        foreach ($data as $id => $val) {
            if (is_array($val)) {
                if (isset($val['@type']) && count($val) === 1) {
                    unset($data[$id]);
                    continue;
                }
                if ('review' === $id && isset($val['@type'])) {
                    if (!isset($val['reviewRating']['ratingValue'])) {
                        unset($data[$id]);
                        continue;
                    }
                }
                $data[$id] = $this->validateSchema($val);
            }

            if ('' === $val || (is_array($val) && empty($val))) {
                unset($data[$id]);
            }
        }

        return $data;
    }

    public function addContextData(array $data): array
    {
        $isProductArchive = $this->isProductArchivePage();
        $canAddGlobal = $this->canAddGlobalEntities($data, $isProductArchive);

        $snippets = [
            Snippets\Publisher::class     => !isset($data['publisher']) && $canAddGlobal,
            Snippets\Website::class       => $canAddGlobal,
            Snippets\PrimaryImage::class  => is_singular() && !post_password_required() && $canAddGlobal,
            Snippets\Breadcrumbs::class   => $this->canAddBreadcrumb(),
            Snippets\Webpage::class       => $canAddGlobal,
            Snippets\Author::class        => is_author() || (is_singular() && $canAddGlobal),
            Snippets\ProductsPage::class  => $isProductArchive,
            Snippets\Singular::class      => !post_password_required() && is_singular(),
            Pro\BlockParser::class        => is_singular(),
        ];

        foreach ($snippets as $class => $canRun) {
            if ($canRun && class_exists($class)) {
                $obj = new $class();
                $data = $obj->process($data, $this);
            }
        }

        return $data;
    }

    public function canAddGlobalEntities(array $data = [], bool $isProductArchive = false): bool
    {
        if (!$isProductArchive && (is_category() || is_tag() || is_tax())) {
            $object = get_queried_object();
            $remove = $object ? (Settings::get('titles.remove_' . $object->taxonomy . '_snippet_data', 'off') === 'on') : false;
            return !$remove;
        }

        if (is_front_page() || !is_singular() || !empty($data)) {
            return true;
        }

        $schemas = Db::getSchemas($this->postId);
        if (!empty($schemas)) {
            return true;
        }

        $defaultType = Db::getDefaultSchemaType($this->postId);
        return (bool) $defaultType;
    }

    private function canAddBreadcrumb(): bool
    {
        $breadcrumbsEnabled = (Settings::get('general.breadcrumbs', 'off') === 'on');
        return !is_front_page() && $breadcrumbsEnabled;
    }

    public function isProductArchivePage(): bool
    {
        return class_exists('WooCommerce') && ((is_tax() && in_array(get_query_var('taxonomy'), get_object_taxonomies('product'), true)) || (function_exists('is_shop') && is_shop()));
    }

    public function addProp(string $prop, array &$entity, string $key = '', array $data = []): void
    {
        if (empty($prop)) {
            return;
        }

        if ($prop === 'language') {
            $entity['inLanguage'] = get_bloginfo('language');
            return;
        }

        if ($prop === 'image') {
            $logo = Settings::get('titles.knowledgegraph_logo', '');
            if (!$logo) {
                $logoId = get_option('site_logo');
                $logo = $logoId ? wp_get_attachment_image_url($logoId) : '';
            }
            if ($logo) {
                $entity['logo'] = [
                    '@type'      => 'ImageObject',
                    '@id'        => home_url('/#logo'),
                    'url'        => $logo,
                    'contentUrl' => $logo,
                    'caption'    => $this->getWebsiteName(),
                    'inLanguage' => get_bloginfo('language'),
                ];
                $logoId = (int) Settings::get('titles.knowledgegraph_logo_id', 0);
                if ($logoId > 0) {
                    $meta = wp_get_attachment_metadata($logoId, true);
                    if ($meta) {
                        $entity['logo']['width']  = $meta['width'] ?? 0;
                        $entity['logo']['height'] = $meta['height'] ?? 0;
                    }
                }
            }
            return;
        }

        if ($prop === 'publisher') {
            if (!empty($data['publisher'])) {
                $entity[$key ?: 'publisher'] = ['@id' => $data['publisher']['@id']];
            }
            return;
        }

        if ($prop === 'is_part_of') {
            $hash = [
                'website' => home_url('/#website'),
                'webpage' => Paper::get()->get_canonical() . '#webpage',
            ];
            if (!empty($hash[$key])) {
                $entity['isPartOf'] = ['@id' => $hash[$key]];
            }
            return;
        }

        if ($prop === 'thumbnail') {
            if (!empty($data['primaryImage'])) {
                $entity[$key ?: 'image'] = ['@id' => $data['primaryImage']['@id']];
            }
            return;
        }
    }

    public function replaceVariables(array $schemas, mixed $object = null, array $data = []): array
    {
        $newSchemas = [];
        $object = empty($object) ? get_queried_object() : $object;

        foreach ($schemas as $key => $schema) {
            if ('metadata' === $key) {
                $newSchemas['isPrimary'] = !empty($schema['isPrimary']);
                if (!empty($schema['type']) && 'custom' === $schema['type']) {
                    $newSchemas['isCustom'] = true;
                }
                continue;
            }

            if (is_array($schema)) {
                $newSchemas[$key] = $this->replaceVariables($schema, $object, $data);
                continue;
            }

            $newSchemas[$key] = is_string($schema) && str_contains($schema, '%') && !filter_var($schema, FILTER_VALIDATE_URL)
                ? Variables::replace($schema, $object) : $schema;

            if ('' === $newSchemas[$key]) {
                unset($newSchemas[$key]);
            }
        }

        return $newSchemas;
    }

    public function filter(array $schemas, JsonLd $jsonld, array $data): array
    {
        $newSchemas = [];
        foreach ($schemas as $key => $schema) {
            $type = is_array($schema['@type'] ?? '') ? $schema['@type'][0] : ($schema['@type'] ?? '');
            $type = strtolower($type);
            if (in_array($type, ['blogposting', 'newsarticle'], true)) {
                $type = 'article';
            }
            $hook = 'snippet/rich_snippet_' . $type;

            $pre = apply_filters("rank_math/{$hook}", false, $jsonld->parts, $data);
            $pre = apply_filters("laca_seo/{$hook}", $pre, $jsonld->parts, $data);
            if (false !== $pre) {
                $newSchemas[$key] = apply_filters("laca_seo/{$hook}_custom_entity", []);
                $newSchemas[$key] = apply_filters("laca_seo/snippet/rich_snippet_entity", $newSchemas[$key]);
                continue;
            }

            $newSchemas[$key] = apply_filters("rank_math/{$hook}_entity", $schema);
            $newSchemas[$key] = apply_filters("laca_seo/{$hook}_entity", $newSchemas[$key]);
            $newSchemas[$key] = apply_filters("laca_seo/snippet/rich_snippet_entity", $newSchemas[$key]);
        }
        return $newSchemas;
    }

    public function getWebsiteName(): string
    {
        return (string) Settings::get('titles.website_name', $this->getOrganizationName());
    }

    public function getOrganizationName(): string
    {
        $name = (string) Settings::get('titles.knowledgegraph_name', '');
        return $name ?: get_bloginfo('name');
    }

    public function getSocialProfiles(): array
    {
        $profiles = [(string) Settings::get('titles.social_url_facebook', '')];
        $tw = (string) Settings::get('titles.twitter_author_names', '');
        if ($tw) {
            $profiles[] = 'https://twitter.com/' . ltrim($tw, '@');
        }
        $extra = (string) Settings::get('titles.social_additional_profiles', '');
        if ($extra) {
            $profiles = array_merge($profiles, explode("\n", str_replace("\r", "", $extra)));
        }
        return array_values(array_filter(array_map('trim', $profiles)));
    }

    public function getProductTitle(mixed $product): string
    {
        if (!is_object($product)) {
            return '';
        }
        $meta = (string) get_post_meta($product->get_id(), '_laca_seo_title', true);
        if ($meta !== '') {
            return Variables::replace($meta, get_post($product->get_id()));
        }
        $post = get_post($product->get_id());
        $title = Paper::get_from_options('pt_product_title', $post, '%title% %sep% %sitename%');
        return $title ?: $product->get_name();
    }

    public function getProductDesc(mixed $product): string
    {
        if (!is_object($product)) {
            return '';
        }
        $meta = (string) get_post_meta($product->get_id(), '_laca_seo_description', true);
        if ($meta !== '') {
            return Variables::replace($meta, get_post($product->get_id()));
        }
        $post = get_post($product->get_id());
        $desc = Paper::get_from_options('pt_product_description', $post, '%excerpt%');
        if (!$desc) {
            $desc = $product->get_short_description() ?: $product->get_description();
        }
        return wp_strip_all_tags(do_shortcode((string) $desc), true);
    }

    public function getPostUrl(int $postId = 0): string
    {
        return $postId === 0 ? Paper::get()->get_canonical() : (string) get_permalink($postId);
    }

    private function setupParts(): void
    {
        if (!$this->post) {
            return;
        }

        $this->parts = [
            'title'     => Paper::get()->get_title(),
            'url'       => get_permalink($this->post->ID),
            'canonical' => Paper::get()->get_canonical(),
            'modified'  => mysql2date(DATE_W3C, $this->post->post_modified, false),
            'published' => mysql2date(DATE_W3C, $this->post->post_date, false),
            'excerpt'   => Variables::replace('%excerpt%', $this->post),
            'desc'      => Paper::get()->get_description(),
            'author'    => get_the_author_meta('display_name', $this->post->post_author),
        ];

        if (strtotime($this->post->post_modified) < strtotime($this->post->post_date)) {
            $this->parts['modified'] = $this->parts['published'];
        }
    }
}
