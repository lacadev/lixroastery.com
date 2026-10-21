<?php

namespace App\Features\LacaSeo\Modules\NotFoundMonitor;

defined('ABSPATH') || exit;

/**
 * Bootstraps 404 Monitor module.
 */
class NotFoundMonitor
{
    public function register(): void
    {
        (new Monitor())->register();
    }
}
