<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Standardized Date Range calculation according to Google Site Kit principles.
 * Supports 7d, 14d, 28d, 90d (default 28d).
 * All periods end yesterday (-1 day) to match freshest finalized data.
 * Compare period is the exact same number of consecutive days immediately preceding the period.
 */
class DateRange
{
    public const SUPPORTED_RANGES = ['7d', '14d', '28d', '90d'];
    public const DEFAULT_RANGE     = '28d';

    /**
     * Get computed dates and labels for a given range.
     *
     * @param string $range
     * @return array{
     *     days: int,
     *     range: string,
     *     startDate: string,
     *     endDate: string,
     *     prevStartDate: string,
     *     prevEndDate: string,
     *     label: string,
     *     dataDate: string,
     * }
     */
    public static function get(string $range = '28d'): array
    {
        $days = match ($range) {
            '7d', '7'   => 7,
            '14d', '14' => 14,
            '90d', '90' => 90,
            default     => 28,
        };

        $canonicalRange = $days . 'd';

        // Current period ends yesterday
        $endDate   = date('Y-m-d', strtotime('-1 day'));
        $startDate = date('Y-m-d', strtotime('-' . $days . ' days'));

        // Compare period: immediately preceding $days
        $prevEndDate   = date('Y-m-d', strtotime('-' . ($days + 1) . ' days'));
        $prevStartDate = date('Y-m-d', strtotime('-' . ($days * 2) . ' days'));

        $labels = self::getRangeLabels();

        return [
            'days'          => $days,
            'range'         => $canonicalRange,
            'startDate'     => $startDate,
            'endDate'       => $endDate,
            'prevStartDate' => $prevStartDate,
            'prevEndDate'   => $prevEndDate,
            'label'         => $labels[$canonicalRange] ?? sprintf(__('%d ngày qua', 'lacadev'), $days),
            'dataDate'      => date('d/m', strtotime($endDate)),
        ];
    }

    /**
     * Alias for get() to maintain backward compatibility with date calculation calls.
     *
     * @param string $range
     * @return array<string, mixed>
     */
    public static function calculateDates(string $range = '28d'): array
    {
        return self::get($range);
    }

    /**
     * Get human-readable labels for all supported date ranges.
     *
     * @return array<string, string>
     */
    public static function getRangeLabels(): array
    {
        return [
            '7d'  => __('7 ngày qua', 'lacadev'),
            '14d' => __('14 ngày qua', 'lacadev'),
            '28d' => __('28 ngày qua', 'lacadev'),
            '90d' => __('90 ngày qua', 'lacadev'),
        ];
    }

    /**
     * Get user-preferred date range (stored in user meta).
     */
    public static function getUserRange(?int $userId = null): string
    {
        $uid = $userId ?: get_current_user_id();
        if (!$uid) {
            return self::DEFAULT_RANGE;
        }

        $saved = get_user_meta($uid, 'laca_google_range', true);
        if (is_string($saved) && in_array($saved, self::SUPPORTED_RANGES, true)) {
            return $saved;
        }

        return self::DEFAULT_RANGE;
    }

    /**
     * Save user-preferred date range.
     */
    public static function setUserRange(string $range, ?int $userId = null): bool
    {
        $uid = $userId ?: get_current_user_id();
        if (!$uid) {
            return false;
        }

        if (!in_array($range, self::SUPPORTED_RANGES, true)) {
            $range = self::DEFAULT_RANGE;
        }

        return (bool) update_user_meta($uid, 'laca_google_range', $range);
    }
}
