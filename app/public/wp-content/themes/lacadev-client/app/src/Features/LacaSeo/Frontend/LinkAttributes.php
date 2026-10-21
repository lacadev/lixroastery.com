<?php

namespace App\Features\LacaSeo\Frontend;

use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Handles link attributes modification: nofollow, new window (target="_blank"), UGC, attachment redirects, RSS.
 */
class LinkAttributes
{
    public function register(): void
    {
        add_filter('the_content', [$this, 'filterContentLinks'], 99);
        add_action('template_redirect', [$this, 'handleAttachmentRedirect']);
        add_filter('the_content_feed', [$this, 'filterRssContent']);
        add_filter('the_excerpt_rss', [$this, 'filterRssContent']);
    }

    /**
     * Filter post content to dynamically add rel="nofollow" and target="_blank" to external links.
     */
    public function filterContentLinks(string $content): string
    {
        if (empty($content) || !str_contains($content, '<a ')) {
            return $content;
        }

        $nofollow = Settings::get('general.nofollow_external_links', 'off') === 'on';
        $newWindow = Settings::get('general.new_window_external_links', 'on') === 'on';

        if (!$nofollow && !$newWindow) {
            return $content;
        }

        $siteHost = wp_parse_url(get_home_url(), PHP_URL_HOST);
        $excludeDomains = array_filter(array_map('trim', explode("\n", (string) Settings::get('general.nofollow_exclude_domains', ''))));
        $onlyDomains = array_filter(array_map('trim', explode("\n", (string) Settings::get('general.nofollow_domains', ''))));

        return preg_replace_callback('/<a\s+([^>]+)>/i', static function ($matches) use ($nofollow, $newWindow, $siteHost, $excludeDomains, $onlyDomains) {
            $tag = $matches[0];
            $attrs = $matches[1];

            // Extract href
            if (!preg_match('/href=[\'"]([^\'"]+)[\'"]/i', $attrs, $hrefMatch)) {
                return $tag;
            }

            $url = $hrefMatch[1];
            $host = wp_parse_url($url, PHP_URL_HOST);

            // Ignore internal links or anchor links
            if (empty($host) || strcasecmp($host, (string) $siteHost) === 0) {
                return $tag;
            }

            // Target _blank
            if ($newWindow && !preg_match('/target=[\'"][^\'"]*[\'"]/i', $attrs)) {
                $tag = rtrim(substr($tag, 0, -1)) . ' target="_blank" rel="noopener">';
            }

            // Nofollow check
            if ($nofollow) {
                // Check exclusions
                if (!empty($excludeDomains)) {
                    foreach ($excludeDomains as $ex) {
                        if (str_contains($host, $ex)) {
                            return $tag;
                        }
                    }
                }

                // Check only-domains if set
                if (!empty($onlyDomains)) {
                    $matched = false;
                    foreach ($onlyDomains as $od) {
                        if (str_contains($host, $od)) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        return $tag;
                    }
                }

                // Inject nofollow into rel
                if (preg_match('/rel=[\'"]([^\'"]*)[\'"]/i', $tag, $relMatch)) {
                    $relVal = $relMatch[1];
                    if (!str_contains($relVal, 'nofollow')) {
                        $newRel = trim($relVal . ' nofollow');
                        $tag = str_replace($relMatch[0], 'rel="' . esc_attr($newRel) . '"', $tag);
                    }
                } else {
                    $tag = rtrim(substr($tag, 0, -1)) . ' rel="nofollow">';
                }
            }

            return $tag;
        }, $content);
    }

    /**
     * Redirect attachment pages to their parent post (or default URL).
     */
    public function handleAttachmentRedirect(): void
    {
        if (!is_attachment()) {
            return;
        }

        if (Settings::get('general.attachment_redirect_urls', 'on') !== 'on') {
            return;
        }

        global $post;
        $parent_id = !empty($post->post_parent) ? (int) $post->post_parent : 0;

        if ($parent_id > 0) {
            $dest = get_permalink($parent_id);
        } else {
            $dest = (string) Settings::get('general.attachment_redirect_default', home_url('/'));
        }

        if (!empty($dest)) {
            wp_safe_redirect($dest, 301);
            exit;
        }
    }

    /**
     * Append/prepend custom RSS before & after content.
     */
    public function filterRssContent(string $content): string
    {
        $before = (string) Settings::get('general.rss_before_content', '');
        $after = (string) Settings::get('general.rss_after_content', '');

        if (!empty($before)) {
            $before = \App\Features\LacaSeo\Core\Variables::replace($before) . '<br><br>';
        }
        if (!empty($after)) {
            $after = '<br><br>' . \App\Features\LacaSeo\Core\Variables::replace($after);
        }

        return $before . $content . $after;
    }
}
