<?php

namespace App\Features\LacaSeo\Modules\Links;

defined('ABSPATH') || exit;

/**
 * Storage handler for Laca SEO internal links and link counts.
 */
class Storage
{
    public static function getLinksTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_seo_internal_links';
    }

    public static function getMetaTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'laca_seo_internal_meta';
    }

    /**
     * Clean up stored links for a post.
     */
    public function cleanup(int $postId): void
    {
        global $wpdb;
        $table = self::getLinksTable();
        $wpdb->delete($table, ['post_id' => $postId], ['%d']);
    }

    /**
     * Get stored internal links for a post.
     *
     * @return Link[]
     */
    public function getLinks(int $postId): array
    {
        global $wpdb;
        $table = self::getLinksTable();
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d", $postId),
            ARRAY_A
        );

        $links = [];
        if (!empty($results)) {
            foreach ($results as $row) {
                $links[] = new Link(
                    $row['url'],
                    (int) $row['target_post_id'],
                    $row['type'],
                    $row['anchor_text'] ?? '',
                    $row['anchor_type'] ?? 'HPLNK',
                    (int) ($row['is_nofollow'] ?? 0),
                    (int) ($row['target_blank'] ?? 0)
                );
            }
        }

        return $links;
    }

    /**
     * Save links for a post in batch.
     *
     * @param Link[] $links
     */
    public function saveLinks(int $postId, array $links): void
    {
        if (empty($links)) {
            return;
        }

        global $wpdb;
        $table = self::getLinksTable();

        $values = [];
        foreach ($links as $link) {
            $values[] = $wpdb->prepare(
                '(%s, %d, %d, %s, %s, %s, %d, %d, %s, %s)',
                $link->getUrl(),
                $postId,
                $link->getTargetPostId(),
                $link->getType(),
                $link->getAnchorText(),
                $link->getAnchorType(),
                $link->getIsNofollow(),
                $link->getTargetBlank(),
                $link->getUrlHash(),
                current_time('mysql')
            );
        }

        $wpdb->query(
            "INSERT INTO {$table} (url, post_id, target_post_id, type, anchor_text, anchor_type, is_nofollow, target_blank, url_hash, created_at) VALUES " . implode(', ', $values)
        );
    }

    /**
     * Update link counts for a post and referenced target posts.
     *
     * @param array $counts ['internal_link_count' => N, 'external_link_count' => N]
     * @param Link[] $links
     */
    public function updateLinkCounts(int $postId, array $counts, array $links): void
    {
        $counts = wp_parse_args($counts, [
            'internal_link_count' => 0,
            'external_link_count' => 0,
        ]);

        $this->saveMetaData($postId, $counts);
        $this->updateIncomingLinks($postId, $links);
    }

    /**
     * Save link counts metadata for an object.
     */
    public function saveMetaData(int $postId, array $metaData): void
    {
        global $wpdb;
        $table = self::getMetaTable();

        $allowedKeys = ['incoming_link_count', 'internal_link_count', 'external_link_count'];
        $updateParts = [];
        foreach ($metaData as $key => $val) {
            if (in_array($key, $allowedKeys, true)) {
                $updateParts[] = $wpdb->prepare("{$key} = %d", (int) $val);
            }
        }

        if (empty($updateParts)) {
            return;
        }

        $incoming = (int) ($metaData['incoming_link_count'] ?? 0);
        $internal = (int) ($metaData['internal_link_count'] ?? 0);
        $external = (int) ($metaData['external_link_count'] ?? 0);

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (object_id, incoming_link_count, internal_link_count, external_link_count)
                VALUES (%d, %d, %d, %d)
                ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts),
                $postId,
                $incoming,
                $internal,
                $external
            )
        );
    }

    /**
     * Update incoming link counts for post and related targets.
     *
     * @param Link[] $links
     */
    public function updateIncomingLinks(int $postId, array $links): void
    {
        $postIds = [];
        foreach ($links as $link) {
            $tid = $link->getTargetPostId();
            if ($tid > 0) {
                $postIds[] = $tid;
            }
        }
        $postIds = array_unique(array_merge([$postId], $postIds));

        $this->updateIncomingLinkCount($postIds);
    }

    /**
     * Re-calculate incoming links for an array of post IDs.
     */
    public function updateIncomingLinkCount(array $postIds): void
    {
        if (empty($postIds)) {
            return;
        }

        global $wpdb;
        $table = self::getLinksTable();
        $placeholders = implode(', ', array_fill(0, count($postIds), '%d'));

        $query = $wpdb->prepare(
            "SELECT target_post_id AS post_id, COUNT(id) AS incoming
            FROM {$table}
            WHERE target_post_id IN ({$placeholders}) AND type = 'internal'
            GROUP BY target_post_id",
            ...$postIds
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        $foundPostIds = [];

        if (!empty($results)) {
            foreach ($results as $row) {
                $pid = (int) $row['post_id'];
                $inc = (int) $row['incoming'];
                $this->saveMetaData($pid, ['incoming_link_count' => $inc]);
                $foundPostIds[] = $pid;
            }
        }

        $zeroPosts = array_diff($postIds, $foundPostIds);
        foreach ($zeroPosts as $zeroId) {
            $this->saveMetaData($zeroId, ['incoming_link_count' => 0]);
        }
    }
}
