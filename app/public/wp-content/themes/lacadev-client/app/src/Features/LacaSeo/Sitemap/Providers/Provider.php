<?php

namespace App\Features\LacaSeo\Sitemap\Providers;

defined('ABSPATH') || exit;

/**
 * Interface for XML sitemap providers.
 */
interface Provider
{
    /**
     * Check if provider supports given item type.
     */
    public function handlesType(string $type): bool;

    /**
     * Get set of sitemaps index link data.
     *
     * @param int $maxEntries Entries per sitemap.
     * @return array<int, array{loc: string, lastmod?: string}>
     */
    public function getIndexLinks(int $maxEntries): array;

    /**
     * Get set of sitemap link data.
     *
     * @param string $type Sitemap type.
     * @param int $maxEntries Entries per sitemap.
     * @param int $currentPage Current page of the sitemap.
     * @return array<int, array{loc: string, mod?: string, images?: array<int, array{src: string}>}>
     */
    public function getSitemapLinks(string $type, int $maxEntries, int $currentPage): array;
}
