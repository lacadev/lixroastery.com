<?php

namespace App\Features\LacaSeo\Sitemap;

use DateTime;
use DateTimeZone;
use Exception;

defined('ABSPATH') || exit;

/**
 * Timezone formatter for XML sitemaps.
 */
class Timezone
{
    /**
     * Format arbitrary UTC datetime string to desired form (W3C standard).
     */
    public function formatDate(string $datetime_string, string $format = DATE_W3C): string
    {
        if (empty($datetime_string) || false === $this->isValidDatetime($datetime_string)) {
            return '';
        }

        try {
            $date_time = new DateTime($datetime_string, new DateTimeZone('UTC'));
            return $date_time->format($format);
        } catch (Exception) {
            return '';
        }
    }

    private function isValidDatetime(string $datetime): bool
    {
        if (str_starts_with($datetime, '-')) {
            return false;
        }

        try {
            return new DateTime($datetime) !== false;
        } catch (Exception) {
            return false;
        }
    }
}
