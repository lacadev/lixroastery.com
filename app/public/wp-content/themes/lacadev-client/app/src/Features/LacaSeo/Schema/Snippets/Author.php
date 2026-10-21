<?php

namespace App\Features\LacaSeo\Schema\Snippets;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Frontend\Paper\Paper;
use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class Author implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        $isArchiveDisabled = (Settings::get('titles.disable_author_archives', 'off') === 'on');
        $authorId = is_singular() ? (int) ($jsonld->post->post_author ?? 0) : (int) get_the_author_meta('ID');
        $authorName = get_the_author();
        $authorUrl = get_author_posts_url($authorId);

        $id = !$isArchiveDisabled ? $authorUrl : (($jsonld->parts['url'] ?? home_url()) . '#author');
        $desc = get_the_author_meta('description', $authorId);

        $data['ProfilePage'] = [
            '@type'       => 'Person',
            '@id'         => $id,
            'name'        => $authorName,
            'description' => wp_strip_all_tags(stripslashes((string) $desc), true),
            'url'         => $isArchiveDisabled ? '' : $authorUrl,
        ];

        // Avatar Image
        $avatarUrl = get_avatar_url($authorId);
        if ($avatarUrl) {
            $data['ProfilePage']['image'] = [
                '@type'   => 'ImageObject',
                '@id'     => $avatarUrl,
                'url'     => $avatarUrl,
                'caption' => $authorName,
            ];
            $jsonld->addProp('language', $data['ProfilePage']['image']);
        }

        // SameAs social links
        $sameAs = [
            get_the_author_meta('user_url', $authorId),
            get_user_meta($authorId, 'facebook', true),
        ];
        $tw = get_user_meta($authorId, 'twitter', true);
        if ($tw) {
            $sameAs[] = 'https://twitter.com/' . $tw;
        }
        $extraUrls = get_user_meta($authorId, 'additional_profile_urls', true);
        if ($extraUrls) {
            $sameAs = array_merge($sameAs, explode(' ', (string) $extraUrls));
        }
        $sameAs = array_values(array_filter($sameAs));
        if (!empty($sameAs)) {
            $data['ProfilePage']['sameAs'] = $sameAs;
        }

        // WorksFor
        if (!empty($data['publisher']) && !in_array('Person', (array) $data['publisher']['@type'], true)) {
            $data['ProfilePage']['worksFor'] = ['@id' => $data['publisher']['@id']];
        }

        if (is_author() && !empty($data['WebPage'])) {
            $data['ProfilePage']['mainEntityOfPage'] = [
                '@id' => $data['WebPage']['@id'],
            ];
        }

        return $data;
    }
}
