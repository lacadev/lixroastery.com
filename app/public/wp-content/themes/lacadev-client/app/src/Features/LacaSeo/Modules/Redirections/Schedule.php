<?php

namespace App\Features\LacaSeo\Modules\Redirections;

defined('ABSPATH') || exit;

/**
 * Pro feature: Redirection scheduling (start/end date activation).
 */
class Schedule
{
    public const START_META_KEY = '_laca_seo_redirection_start';
    public const END_META_KEY   = '_laca_seo_redirection_end';

    /**
     * Check if a redirection ID is outside scheduled dates.
     */
    public static function isScheduledOut(int $redirectionId): bool
    {
        $start = get_option('laca_seo_sched_start_' . $redirectionId, '');
        $end   = get_option('laca_seo_sched_end_' . $redirectionId, '');

        if ($start === '' && $end === '') {
            return false;
        }

        $today = current_time('Y-m-d');

        if ($start !== '' && $today < $start) {
            return true;
        }

        if ($end !== '' && $today > $end) {
            return true;
        }

        return false;
    }

    /**
     * Set scheduled dates.
     */
    public static function setSchedule(int $redirectionId, string $start, string $end): void
    {
        if ($start !== '') {
            update_option('laca_seo_sched_start_' . $redirectionId, $start);
        } else {
            delete_option('laca_seo_sched_start_' . $redirectionId);
        }

        if ($end !== '') {
            update_option('laca_seo_sched_end_' . $redirectionId, $end);
        } else {
            delete_option('laca_seo_sched_end_' . $redirectionId);
        }
    }

    /**
     * Get start date.
     */
    public static function getStartDate(int $redirectionId): string
    {
        return (string) get_option('laca_seo_sched_start_' . $redirectionId, '');
    }

    /**
     * Get end date.
     */
    public static function getEndDate(int $redirectionId): string
    {
        return (string) get_option('laca_seo_sched_end_' . $redirectionId, '');
    }
}
