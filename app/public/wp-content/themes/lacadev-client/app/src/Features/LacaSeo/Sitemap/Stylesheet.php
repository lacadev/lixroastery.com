<?php

namespace App\Features\LacaSeo\Sitemap;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Serves XML sitemap stylesheet with Laca SEO branding.
 */
class Stylesheet extends AbstractXml
{
    public function output(string $type): void
    {
        $expires = gmdate('D, d M Y H:i:s', (time() + YEAR_IN_SECONDS));
        $this->sendHeaders([
            'Cache-Control' => 'maxage=' . YEAR_IN_SECONDS,
            'Expires'       => $expires . ' GMT',
            'Etag'          => md5($expires . $type),
        ], true);

        $title = sprintf(__('XML Sitemap %1$s %2$s', 'lacadev'), '-', get_bloginfo('name', 'display'));
        if ($type === 'news') {
            $title = sprintf(__('News Sitemap %1$s %2$s', 'lacadev'), '-', get_bloginfo('name', 'display'));
        } elseif ($type === 'video') {
            $title = sprintf(__('Video Sitemap %1$s %2$s', 'lacadev'), '-', get_bloginfo('name', 'display'));
        }
        $kml_title = sprintf(__('Locations Sitemap %1$s %2$s', 'lacadev'), '-', get_bloginfo('name', 'display'));

        require_once __DIR__ . '/sitemap-xsl.php';
        exit;
    }
}
