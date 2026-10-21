<?php

namespace App\Features\LacaSeo\Modules\Redirections;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;

defined('ABSPATH') || exit;

/**
 * Bootstraps Redirections module.
 */
class Redirections
{
    public function register(): void
    {
        Categories::register();

        if (is_admin()) {
            (new Watcher())->register();
        }

        // Run frontend redirects if module is active and should run frontend
        if (Modules::isActive('redirections')) {
            if (Helper::shouldRunFrontend()) {
                add_action('template_redirect', [$this, 'handleRedirect'], 10);
            }
        }
    }

    public function handleRedirect(): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        new Redirector();
    }
}
