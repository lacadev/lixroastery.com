<?php

namespace App\Features\LacaSeo;

use App\Features\LacaSeo\Admin\AdminPage;
use App\Features\LacaSeo\Admin\SaveHandler;
use App\Features\LacaSeo\Compat\Polylang;
use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Frontend\Head;
use App\Features\LacaSeo\Frontend\LinkAttributes;
use App\Features\LacaSeo\Frontend\Rewrite;
use App\Features\LacaSeo\Frontend\RobotsTxt;
use App\Features\LacaSeo\Frontend\Tracking;

defined('ABSPATH') || exit;

/**
 * Main orchestrator for Laca SEO.
 * Bootstraps admin interface, frontend output, and compatibility modules safely.
 */
class LacaSeo
{
    private static bool $booted = false;

    /**
     * Boot the feature.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        try {
            // 1. Always register admin interface & save handler when in admin
            if (is_admin()) {
                (new AdminPage())->register();
                (new SaveHandler())->register();
                (new \App\Features\LacaSeo\Admin\ImportExportHandler())->register();
                (new \App\Features\LacaSeo\Admin\Metabox\PostMetabox())->register();
                (new \App\Features\LacaSeo\Admin\Metabox\TermMetabox())->register();
                (new \App\Features\LacaSeo\Admin\Metabox\UserMetabox())->register();
                (new \App\Features\LacaSeo\Admin\ListScreens\PostList())->register();
                (new \App\Features\LacaSeo\Admin\ListScreens\BulkActions())->register();
                (new \App\Features\LacaSeo\Admin\ListScreens\TermList())->register();
                (new \App\Features\LacaSeo\Admin\Csv\CsvMetaHandler())->register();
                (new Polylang())->register();
            }

            // 2. Frontend modules: run only if Rank Math is inactive OR force flag is set
            if (Helper::shouldRunFrontend()) {
                if (Helper::isForceFrontend() && Helper::isRankMathActive()) {
                    // Suppress Rank Math frontend head output so Laca SEO can be tested cleanly
                    add_filter('rank_math/frontend/disable_integration', '__return_true', 999);
                    add_action('wp', function() {
                        remove_all_actions('rank_math/head');
                        remove_all_filters('rank_math/frontend/title');
                        remove_action('wp_head', 'rank_math_head', 1);
                    }, 0);
                }

                (new Head())->register();
                (new LinkAttributes())->register();
                (new Rewrite())->register();
                (new RobotsTxt())->register();
                (new Tracking())->register();
                (new \App\Features\LacaSeo\Frontend\FrontendSeoScore())->register();
            }
        } catch (\Throwable $e) {
            error_log('[laca-seo] Boot failure: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
