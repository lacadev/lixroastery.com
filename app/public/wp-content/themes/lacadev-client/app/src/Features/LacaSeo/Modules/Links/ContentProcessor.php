<?php

namespace App\Features\LacaSeo\Modules\Links;

defined('ABSPATH') || exit;

/**
 * Extracts links from post content, resolves target IDs, classifies internal/external,
 * and extracts anchor text, nofollow, target blank matching Rank Math & Rank Math Pro.
 */
class ContentProcessor
{
    private Storage $storage;
    private string $homeUrl;

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage ?? new Storage();
        $this->homeUrl = home_url();
    }

    /**
     * Process post content and update links in database.
     */
    public function process(int $postId, string $content): void
    {
        $extracted = $this->extract($content, $postId);
        $newLinks  = $extracted['links'];
        $counts    = $extracted['counts'];

        $oldLinks = $this->getStoredInternalLinks($postId);
        $this->storage->cleanup($postId);
        $this->storage->saveLinks($postId, $newLinks);
        $this->storage->updateLinkCounts($postId, $counts, array_merge($newLinks, $oldLinks));
    }

    /**
     * Extract links from HTML content.
     *
     * @return array{links: Link[], counts: array{internal_link_count: int, external_link_count: int}}
     */
    public function extract(string $content, int $postId = 0): array
    {
        $links = [];
        $counts = [
            'internal_link_count' => 0,
            'external_link_count' => 0,
        ];

        if (stripos($content, 'href') === false) {
            return ['links' => $links, 'counts' => $counts];
        }

        $postPermalink = $postId > 0 ? $this->normalizeLink(get_permalink($postId)) : '';

        // Extract <a> tags with attributes & anchor text
        preg_match_all(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>(.*?)<\/a>/is',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $beforeAttrs = $match[1];
            $url         = trim($match[2]);
            $afterAttrs  = $match[3];
            $anchorHtml  = $match[4];

            $allAttrs = $beforeAttrs . ' ' . $afterAttrs;

            // Skip anchor jumps or empty
            if (empty($url) || $url[0] === '#') {
                continue;
            }

            // Exclude self links
            $normalized = $this->normalizeLink($url);
            if (!empty($postPermalink) && $normalized === $postPermalink) {
                continue;
            }

            $linkType = $this->classifyLink($url);
            if (!$linkType) {
                continue;
            }

            $targetPostId = 0;
            if ($linkType === 'internal') {
                $targetPostId = url_to_postid($url);
            }

            // Check anchor type (image vs text)
            $anchorType = stripos($anchorHtml, '<img') !== false ? 'IMAGE' : 'HPLNK';
            $anchorText = wp_strip_all_tags($anchorHtml);

            // Attributes: nofollow, target_blank
            $isNofollow = (bool) preg_match('/rel=["\'][^"\']*nofollow[^"\']*["\']/i', $allAttrs) ? 1 : 0;
            $targetBlank = (bool) preg_match('/target=["\']_blank["\']/i', $allAttrs) ? 1 : 0;

            $counts["{$linkType}_link_count"]++;

            $links[] = new Link(
                $url,
                $targetPostId,
                $linkType,
                $anchorText,
                $anchorType,
                $isNofollow,
                $targetBlank
            );
        }

        return [
            'links'  => $links,
            'counts' => $counts,
        ];
    }

    /**
     * Normalize URL for matching.
     */
    public function normalizeLink(string $link): string
    {
        $parts = explode('#', $link);
        $url   = str_replace($this->homeUrl, '', $parts[0]);
        return untrailingslashit($url);
    }

    /**
     * Classify link as 'internal', 'external', or false if invalid/media.
     *
     * @return string|false
     */
    public function classifyLink(string $link)
    {
        if (empty($link) || $link[0] === '#') {
            return false;
        }

        // Exclude direct media / binary files from internal link tracking
        if (preg_match('/\.(jpg|jpeg|png|gif|bmp|pdf|mp3|zip)$/i', $link)) {
            return false;
        }

        // Relative path starting with / or relative
        if (str_starts_with($link, '/') && !str_starts_with($link, '//')) {
            return 'internal';
        }

        $homeHost = wp_parse_url($this->homeUrl, PHP_URL_HOST);
        $linkHost = wp_parse_url($link, PHP_URL_HOST);

        if (empty($linkHost)) {
            return 'internal';
        }

        if (strtolower($linkHost) === strtolower($homeHost)) {
            return 'internal';
        }

        return 'external';
    }

    /**
     * Retrieves stored internal links for a post.
     *
     * @return Link[]
     */
    public function getStoredInternalLinks(int $postId): array
    {
        $links = $this->storage->getLinks($postId);
        return array_filter($links, fn(Link $link) => $link->getType() === 'internal');
    }
}
