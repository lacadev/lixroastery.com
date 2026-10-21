<?php

namespace App\Features\LacaSeo\Sitemap;

use App\Features\LacaSeo\Core\Helper;
use DOMDocument;
use DOMNode;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * Parses images from posts, pages, galleries, and custom fields for XML sitemaps.
 */
class ImageParser
{
    protected string $homeUrl = '';
    protected string $host = '';
    protected string $scheme = 'http';
    protected string $charset = 'UTF-8';
    private ?object $post = null;
    /** @var array<int, array{src: string}> */
    private array $images = [];

    public function __construct()
    {
        $this->homeUrl = home_url();
        $parsed_home   = wp_parse_url($this->homeUrl);

        if (!empty($parsed_home['host'])) {
            $this->host = str_replace('www.', '', $parsed_home['host']);
        }

        if (!empty($parsed_home['scheme'])) {
            $this->scheme = $parsed_home['scheme'];
        }

        $this->charset = esc_attr(get_bloginfo('charset'));
    }

    /**
     * Get set of image items for the given post.
     *
     * @param object $post
     * @return array<int, array{src: string}>|false
     */
    public function getImages(object $post): array|false
    {
        if (\App\Features\LacaSeo\Core\Settings::get('sitemap.include_images', 'on') !== 'on') {
            return false;
        }

        $this->post = $post;
        if (!is_object($this->post) || !isset($this->post->ID)) {
            return $this->images;
        }

        $this->getPostThumbnail();
        $this->getPostImages();
        $this->getPostGalleries();
        $this->getIsAttachment();
        $this->getCustomFieldImages();

        $images = $this->images;
        $this->images = [];
        $this->post = null;

        return apply_filters('laca_seo/sitemap/urlimages', $images, $post->ID);
    }

    /**
     * Get term images.
     *
     * @param object $term
     * @return array<int, array{src: string}>|false
     */
    public function getTermImages(object $term): array|false
    {
        if (\App\Features\LacaSeo\Core\Settings::get('sitemap.include_images', 'on') !== 'on') {
            return false;
        }

        $description = $term->description ?? '';
        $images = $this->parseHtmlImages($description);
        foreach ($this->parseGalleries($description) as $attachment) {
            $src = $this->imageUrl($attachment->ID);
            if (!empty($src)) {
                $images[] = ['src' => $this->getAbsoluteUrl($src)];
            }
        }

        return $images;
    }

    private function getPostThumbnail(): void
    {
        if (\App\Features\LacaSeo\Core\Settings::get('sitemap.include_featured_image', 'off') !== 'on') {
            return;
        }

        $thumbnail_id = (int) get_post_thumbnail_id($this->post->ID);
        if ($thumbnail_id <= 0) {
            return;
        }

        $url = $this->imageUrl($thumbnail_id);
        if (!empty($url)) {
            $this->getImageItem($this->getAbsoluteUrl($url));
        }
    }

    private function getPostImages(): void
    {
        $content = apply_filters('laca_seo/sitemap/content_before_parse_html_images', $this->post->post_content ?? '', $this->post->ID);
        if (function_exists('do_blocks')) {
            $content = do_blocks($content);
        }

        foreach ($this->parseHtmlImages($content) as $image) {
            $this->getImageItem($image['src']);
        }
    }

    private function getPostGalleries(): void
    {
        foreach ($this->parseGalleries($this->post->post_content ?? '', $this->post->ID) as $attachment) {
            $url = $this->imageUrl($attachment->ID);
            if (!empty($url)) {
                $this->getImageItem($this->getAbsoluteUrl($url));
            }
        }
    }

    private function getIsAttachment(): void
    {
        if (isset($this->post->post_type) && $this->post->post_type === 'attachment' && wp_attachment_is_image($this->post)) {
            $url = $this->imageUrl($this->post->ID);
            if (!empty($url)) {
                $this->getImageItem($this->getAbsoluteUrl($url));
            }
        }
    }

    private function getCustomFieldImages(): void
    {
        $pt = $this->post->post_type ?? '';
        $customs = (string) \App\Features\LacaSeo\Core\Settings::get("sitemap.pt_{$pt}_image_customfields", '');
        if (empty($customs)) {
            return;
        }

        $fields = array_filter(array_map('trim', explode("\n", $customs)));
        foreach ($fields as $key) {
            $src = get_post_meta($this->post->ID, $key, true);
            if (is_string($src) && !empty($src) && preg_match('/\.(jpe?g|png|gif|webp|svg)/i', $src)) {
                $this->getImageItem($src);
            }
        }
    }

    /**
     * @return array<int, array{src: string}>
     */
    private function parseHtmlImages(string $content): array
    {
        $images = [];
        $doc = $this->getDocument($content);
        if ($doc === false) {
            return $images;
        }

        $tags = $doc->getElementsByTagName('img');
        foreach ($tags as $img) {
            $src = $this->getImageSrc($img);
            if ($src !== false) {
                $images[] = ['src' => $src];
            }
        }

        return $images;
    }

    private function getDocument(string $content): DOMDocument|false
    {
        if (!class_exists('DOMDocument') || empty(trim($content))) {
            return false;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="' . $this->charset . '">' . $content);
        libxml_clear_errors();

        return $dom;
    }

    private function getImageSrc(DOMNode $node): string|false
    {
        /** @var \DOMElement $node */
        $src = $node->getAttribute('src');
        if ($node->hasAttribute('data-sitemapexclude') || empty($src)) {
            return false;
        }

        $class = $node->getAttribute('class');
        if (!empty($class) && !str_contains($class, 'size-full') && preg_match('/wp-image-(?P<id>\d+)/', $class, $matches)) {
            $upsize = $this->imageUrl((int) $matches['id']);
            if (!empty($upsize)) {
                $src = $upsize;
            }
        }

        $src = $this->getAbsoluteUrl($src);
        $no_host = esc_url($src) !== $src;
        if (!apply_filters('laca_seo/sitemap/include_external_image', false)) {
            $no_host = !str_contains($src, $this->host) || esc_url($src) !== $src;
        }

        return $no_host ? false : $src;
    }

    /**
     * @return array<int, \WP_Post>
     */
    private function parseGalleries(string $content, int $postId = 0): array
    {
        $attachments = [];
        $pattern = '/' . get_shortcode_regex(['gallery']) . '/s';
        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $shortcode) {
            if ($shortcode[2] !== 'gallery') {
                continue;
            }

            $attrs = shortcode_parse_atts($shortcode[3]);
            if (!is_array($attrs)) {
                continue;
            }

            $id = !empty($attrs['id']) ? (int) $attrs['id'] : $postId;
            if (!empty($attrs['ids'])) {
                $attrs['include'] = $attrs['ids'];
            }

            if (!empty($attrs['include'])) {
                $ids = wp_parse_id_list($attrs['include']);
                $query = new WP_Query([
                    'post_type'           => 'attachment',
                    'post_status'         => 'inherit',
                    'post_mime_type'      => 'image',
                    'posts_per_page'      => count($ids),
                    'post__in'            => $ids,
                    'suppress_filters'    => true,
                    'no_found_rows'       => true,
                ]);
                $attachments = array_merge($attachments, $query->posts);
            } elseif ($id > 0) {
                $exclude = !empty($attrs['exclude']) ? wp_parse_id_list($attrs['exclude']) : [];
                $args = [
                    'post_type'           => 'attachment',
                    'post_status'         => 'inherit',
                    'post_mime_type'      => 'image',
                    'post_parent'         => $id,
                    'posts_per_page'      => -1,
                    'suppress_filters'    => true,
                    'no_found_rows'       => true,
                ];
                if (!empty($exclude)) {
                    $args['post__not_in'] = $exclude;
                }
                $query = new WP_Query($args);
                $attachments = array_merge($attachments, $query->posts);
            }
        }

        return array_unique($attachments, SORT_REGULAR);
    }

    private function getImageItem(string $src): void
    {
        $src = apply_filters('laca_seo/sitemap/xml_img_src', $src, $this->post);
        if (empty($src)) {
            return;
        }

        $image = ['src' => $src];
        $this->images[] = apply_filters('laca_seo/sitemap/xml_img', $image, $this->post);
    }

    private function imageUrl(int $postId): string
    {
        $uploads = wp_upload_dir();
        $attached = (string) get_post_meta($postId, '_wp_attached_file', true);

        if (!empty($uploads['error']) || empty($attached)) {
            return '';
        }

        if (str_starts_with($attached, $uploads['basedir'])) {
            $src = str_replace($uploads['basedir'], $uploads['baseurl'], $attached);
        } elseif (str_contains($attached, 'wp-content/uploads')) {
            $src = $uploads['baseurl'] . substr($attached, strpos($attached, 'wp-content/uploads') + 18);
        } else {
            $src = $uploads['baseurl'] . '/' . $attached;
        }

        return (string) apply_filters('wp_get_attachment_url', $src, $postId);
    }

    private function getAbsoluteUrl(string $src): string
    {
        if (empty($src)) {
            return $src;
        }

        if (str_starts_with($src, '//')) {
            return $this->scheme . ':' . $src;
        }

        if (str_starts_with($src, '/')) {
            return $this->homeUrl . $src;
        }

        if (!str_starts_with($src, 'http://') && !str_starts_with($src, 'https://')) {
            return $this->scheme . ':' . $src;
        }

        return $src;
    }
}
