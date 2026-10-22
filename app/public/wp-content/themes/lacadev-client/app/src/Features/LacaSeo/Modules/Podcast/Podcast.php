<?php

namespace App\Features\LacaSeo\Modules\Podcast;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Podcast RSS Feed (/feed/podcast) handler.
 * Adds iTunes namespace, channel metadata, and episode enclosures.
 */
class Podcast
{
    public function register(): void
    {
        add_action('init', [$this, 'registerFeed']);

        if (!Modules::isActive('podcast')) {
            return;
        }

        add_action('rss2_podcast_ns', [$this, 'addNamespace']);
        add_action('rss2_podcast_head', [$this, 'addChannelData']);
        add_action('rss2_podcast_item', [$this, 'addEpisodeData']);
    }

    public function registerFeed(): void
    {
        add_feed('podcast', [$this, 'renderFeed']);
    }

    public function renderFeed(): void
    {
        // Load standard WordPress RSS2 feed template with podcast hooks
        require_once ABSPATH . 'wp-includes/feed-rss2.php';
    }

    public function addNamespace(): void
    {
        echo 'xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" ';
    }

    public function addChannelData(): void
    {
        $title = Settings::get('general.podcast_title', get_bloginfo('name') . ' Podcast');
        $desc  = Settings::get('general.podcast_description', get_bloginfo('description'));
        $owner = Settings::get('general.podcast_owner', get_bloginfo('name'));
        $email = Settings::get('general.podcast_owner_email', get_bloginfo('admin_email'));
        $cat   = Settings::get('general.podcast_category', 'Technology');

        if (!empty($cat)) {
            echo "\t<itunes:category text=\"" . esc_attr($cat) . "\" />\n";
        }

        if (!empty($owner)) {
            echo "\t<itunes:author>" . esc_html($owner) . "</itunes:author>\n";
            echo "\t<itunes:owner>\n";
            echo "\t\t<itunes:name>" . esc_html($owner) . "</itunes:name>\n";
            if (!empty($email)) {
                echo "\t\t<itunes:email>" . esc_html($email) . "</itunes:email>\n";
            }
            echo "\t</itunes:owner>\n";
        }

        if (!empty($desc)) {
            echo "\t<itunes:summary>" . esc_html($desc) . "</itunes:summary>\n";
        }
    }

    public function addEpisodeData(): void
    {
        $postId = get_the_ID();
        $duration = get_post_meta($postId, 'laca_seo_podcast_duration', true) ?: get_post_meta($postId, 'rank_math_podcast_duration', true);
        if (!empty($duration)) {
            echo "\t<itunes:duration>" . esc_html($duration) . "</itunes:duration>\n";
        }
    }
}
