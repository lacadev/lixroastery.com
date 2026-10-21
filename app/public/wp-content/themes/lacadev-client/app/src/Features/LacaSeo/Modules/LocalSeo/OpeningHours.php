<?php

namespace App\Features\LacaSeo\Modules\LocalSeo;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

class OpeningHours
{
    public function getData(array $atts, array $schema): string
    {
        if (!isset($schema['openingHoursSpecification'])) {
            return '<p>' . esc_html__('Open 24/7', 'lacadev') . '</p>';
        }

        if (empty($schema['openingHoursSpecification'])) {
            return '';
        }

        $days = $this->normalizeDays($schema, $atts);
        ob_start();
        ?>
        <h5><?php esc_html_e('Opening Hours:', 'lacadev'); ?></h5>
        <div class="laca-seo-business-opening-hours rank-math-business-opening-hours">
            <?php
            foreach ($days as $day => $hours) {
                $time = !empty($hours['time']) ? implode(__(' and ', 'lacadev'), $hours['time']) : __('Closed', 'lacadev');
                $time = str_replace('-', ' &ndash; ', $time);

                printf(
                    '<div class="laca-seo-opening-hours rank-math-opening-hours"><span class="laca-seo-opening-days rank-math-opening-days">%1$s</span> : <span class="laca-seo-opening-time rank-math-opening-time">%2$s</span> <span class="laca-seo-business-open rank-math-business-open">%3$s</span></div>',
                    esc_html($day),
                    esc_html($time),
                    esc_html($hours['isOpen'] ?? '')
                );
            }
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function normalizeDays(array $schema, array $atts): array
    {
        $rawHours = (array) $schema['openingHoursSpecification'];
        $daysList = explode(',', $atts['show_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday');
        $data = [];

        foreach ($daysList as $day) {
            $day = ucfirst(trim($day));
            $data[$day] = [
                'isOpen' => '',
                'time'   => [],
            ];
        }

        foreach ($rawHours as $item) {
            if (!is_array($item)) {
                continue;
            }
            $dayOfWeek = $item['dayOfWeek'] ?? '';
            if (is_array($dayOfWeek)) {
                $dayOfWeek = reset($dayOfWeek);
            }
            $dayOfWeek = ucfirst((string) $dayOfWeek);
            if (isset($data[$dayOfWeek])) {
                $opens = $item['opens'] ?? '';
                $closes = $item['closes'] ?? '';
                if ($opens && $closes) {
                    $data[$dayOfWeek]['time'][] = $opens . '-' . $closes;
                }
            }
        }

        if (!empty($atts['hide_closed_days'])) {
            foreach ($data as $day => $val) {
                if (empty($val['time'])) {
                    unset($data[$day]);
                }
            }
        }

        return $data;
    }
}
