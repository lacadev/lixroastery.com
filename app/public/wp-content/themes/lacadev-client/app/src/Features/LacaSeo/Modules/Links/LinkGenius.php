<?php

namespace App\Features\LacaSeo\Modules\Links;

defined('ABSPATH') || exit;

/**
 * Link Genius: Audit broken links, bulk update URLs/anchors with snapshot & rollback,
 * and manage keyword maps without external API calls.
 */
class LinkGenius
{
    /**
     * Audit single URL via HTTP HEAD request.
     * Returns structured status category matching Rank Math Pro.
     */
    public static function auditUrl(string $url): array
    {
        $response = wp_safe_remote_head($url, [
            'timeout'     => 5,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'Mozilla/5.0 (compatible; LacaSeoBot/1.0)',
        ]);

        if (is_wp_error($response)) {
            $errMsg = $response->get_error_message();
            $category = (stripos($errMsg, 'timed out') !== false || stripos($errMsg, 'timeout') !== false) ? 'timeout' : 'error';
            return [
                'http_status_code'   => 0,
                'status_category'    => $category,
                'robots_blocked'     => 0,
                'last_error_message' => $errMsg,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $category = '2xx';
        if ($code >= 200 && $code < 300) {
            $category = '2xx';
        } elseif ($code >= 300 && $code < 400) {
            $category = '3xx';
        } elseif ($code >= 400 && $code < 500) {
            $category = '4xx';
        } elseif ($code >= 500) {
            $category = '5xx';
        } else {
            $category = 'error';
        }

        return [
            'http_status_code'   => $code,
            'status_category'    => $category,
            'robots_blocked'     => 0,
            'last_error_message' => null,
        ];
    }

    /**
     * Perform bulk URL / Anchor text replacement across posts with full snapshot for rollback.
     *
     * @param int[] $postIds
     * @return array{batch_id: string, affected_posts: int, affected_links: int}
     */
    public static function bulkUpdateLinks(
        array $postIds,
        string $fromUrl,
        string $toUrl,
        string $fromAnchor = '',
        string $toAnchor = ''
    ): array {
        global $wpdb;
        $batchId = md5(uniqid('laca_lg_', true));
        $snapshotsTable = $wpdb->prefix . 'laca_seo_link_genius_snapshots';
        $historyTable   = $wpdb->prefix . 'laca_seo_link_genius_history';

        $affectedPosts = 0;
        $affectedLinks = 0;

        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if (!$post) {
                continue;
            }

            $originalContent = $post->post_content;
            $newContent = $originalContent;
            $linkChanges = [];

            // Match all <a> tags
            $updatedContent = preg_replace_callback(
                '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>(.*?)<\/a>/is',
                function ($matches) use ($fromUrl, $toUrl, $fromAnchor, $toAnchor, &$linkChanges, &$affectedLinks) {
                    $before = $matches[1];
                    $href   = $matches[2];
                    $after  = $matches[3];
                    $anchor = $matches[4];

                    $matchUrl = (rtrim($href, '/') === rtrim($fromUrl, '/'));
                    $matchAnchor = empty($fromAnchor) || (trim(wp_strip_all_tags($anchor)) === trim($fromAnchor));

                    if ($matchUrl && $matchAnchor) {
                        $newHref = !empty($toUrl) ? $toUrl : $href;
                        $newAnchor = !empty($toAnchor) ? $toAnchor : $anchor;
                        $affectedLinks++;

                        $linkChanges[] = [
                            'original' => $matches[0],
                            'from_url' => $href,
                            'to_url'   => $newHref,
                            'from_txt' => $anchor,
                            'to_txt'   => $newAnchor,
                        ];

                        return "<a {$before}href=\"{$newHref}\"{$after}>{$newAnchor}</a>";
                    }

                    return $matches[0];
                },
                $originalContent
            );

            if ($updatedContent !== $originalContent) {
                $affectedPosts++;

                // Save snapshot
                $wpdb->insert($snapshotsTable, [
                    'batch_id'         => $batchId,
                    'post_id'          => $postId,
                    'original_content' => $originalContent,
                    'link_changes'     => wp_json_encode($linkChanges),
                    'created_at'       => current_time('mysql'),
                ]);

                // Update post content in database
                $wpdb->update(
                    $wpdb->posts,
                    ['post_content' => $updatedContent],
                    ['ID' => $postId]
                );
                clean_post_cache($postId);
            }
        }

        // Record history
        $wpdb->insert($historyTable, [
            'batch_id'             => $batchId,
            'source_type'          => 'bulk_update',
            'user_id'              => get_current_user_id() ?: 1,
            'operation_type'       => !empty($toAnchor) && !empty($toUrl) ? 'both' : (!empty($toAnchor) ? 'anchor' : 'url'),
            'filters'              => wp_json_encode(['from_url' => $fromUrl, 'from_anchor' => $fromAnchor]),
            'changes_summary'      => wp_json_encode(['from_url' => $fromUrl, 'to_url' => $toUrl, 'from_anchor' => $fromAnchor, 'to_anchor' => $toAnchor]),
            'affected_links_count' => $affectedLinks,
            'affected_posts_count' => $affectedPosts,
            'status'               => 'completed',
            'created_at'           => current_time('mysql'),
            'completed_at'         => current_time('mysql'),
        ]);

        return [
            'batch_id'       => $batchId,
            'affected_posts' => $affectedPosts,
            'affected_links' => $affectedLinks,
        ];
    }

    /**
     * Rollback a bulk update batch by restoring original_content.
     */
    public static function rollbackBatch(string $batchId): bool
    {
        global $wpdb;
        $snapshotsTable = $wpdb->prefix . 'laca_seo_link_genius_snapshots';
        $historyTable   = $wpdb->prefix . 'laca_seo_link_genius_history';

        $snapshots = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$snapshotsTable} WHERE batch_id = %s", $batchId),
            ARRAY_A
        );

        if (empty($snapshots)) {
            return false;
        }

        foreach ($snapshots as $snap) {
            $postId = (int) $snap['post_id'];
            $wpdb->update(
                $wpdb->posts,
                ['post_content' => $snap['original_content']],
                ['ID' => $postId]
            );
            clean_post_cache($postId);
        }

        $wpdb->update(
            $historyTable,
            ['status' => 'rolled_back'],
            ['batch_id' => $batchId]
        );

        return true;
    }
}
