<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Server-side SVG Chart Renderer for Google Services Reports.
 * Monochromatic, lightweight, valid XML, zero external JS/CSS dependencies.
 */
class Chart
{
    /**
     * Render an SVG line chart.
     *
     * @param array<int|string, mixed> $series Single series of points or list of series.
     *        Point format: ['date' => '2026-10-01', 'label' => '01/10', 'value' => 123]
     *        or Series format: [['name' => 'Kỳ này', 'points' => [...], 'style' => 'solid'], ...]
     * @param array<string, mixed> $opts
     *        - width: int (default 600)
     *        - height: int (default 160)
     *        - title: string
     *        - ariaLabel: string
     *        - ySuffix: string (e.g. '', '%')
     *        - showPoints: bool (default true)
     * @return string Valid SVG XML string or empty string if no valid data.
     */
    public static function line(array $series, array $opts = []): string
    {
        if (empty($series)) {
            return '';
        }

        // Normalize to list of series: [['name' => ..., 'points' => [...], 'style' => ...]]
        $normalizedSeries = [];
        $isListOfPoints = false;
        foreach ($series as $k => $v) {
            if (is_array($v) && (isset($v['value']) || isset($v['date']))) {
                $isListOfPoints = true;
                break;
            }
        }

        if ($isListOfPoints) {
            $normalizedSeries[] = [
                'name'   => (string) ($opts['title'] ?? __('Kỳ hiện tại', 'lacadev')),
                'points' => array_values($series),
                'style'  => 'solid',
            ];
        } else {
            foreach ($series as $sKey => $sVal) {
                if (!is_array($sVal)) {
                    continue;
                }
                $points = $sVal['points'] ?? $sVal['data'] ?? (is_array($sVal) && !empty($sVal[0]) && is_array($sVal[0]) ? $sVal : []);
                if (empty($points)) {
                    continue;
                }
                if (isset($sVal['dashed'])) {
                    $defaultStyle = !empty($sVal['dashed']) ? 'dashed' : 'solid';
                } else {
                    $defaultStyle = ($sKey === 'prev' || $sKey === 1 || (!empty($sVal['name']) && stripos($sVal['name'], 'trước') !== false)) ? 'dashed' : 'solid';
                }
                $normalizedSeries[] = [
                    'name'   => (string) ($sVal['name'] ?? ($defaultStyle === 'dashed' ? __('Kỳ trước', 'lacadev') : __('Kỳ này', 'lacadev'))),
                    'points' => array_values($points),
                    'style'  => (string) ($sVal['style'] ?? $defaultStyle),
                ];
            }
        }

        if (empty($normalizedSeries)) {
            return '';
        }

        // Extract and validate all numeric values
        $allValues = [];
        $pointCount = 0;
        foreach ($normalizedSeries as $s) {
            foreach ($s['points'] as $p) {
                if (isset($p['value']) && is_numeric($p['value'])) {
                    $allValues[] = (float) $p['value'];
                }
            }
            $pointCount = max($pointCount, count($s['points']));
        }

        if (empty($allValues) || $pointCount === 0) {
            return '';
        }

        $minVal = 0.0; // Charts anchor at 0
        $maxVal = max($allValues);
        if ($maxVal <= 0) {
            $maxVal = 1.0;
        }

        // Add 10% headroom
        $maxVal = ceil($maxVal * 1.08);

        // Chart dimensions & padding
        $width = (int) ($opts['width'] ?? 600);
        $height = (int) ($opts['height'] ?? 160);
        $padLeft = 44;
        $padRight = 16;
        $padTop = 14;
        $padBottom = 26;

        $plotW = $width - $padLeft - $padRight;
        $plotH = $height - $padTop - $padBottom;
        if ($plotW <= 0 || $plotH <= 0) {
            return '';
        }

        $ySuffix = (string) ($opts['ySuffix'] ?? '');
        $ariaLabel = (string) ($opts['ariaLabel'] ?? ($opts['title'] ?? __('Biểu đồ xu hướng', 'lacadev')));

        // Y-axis ticks: 3 ticks (0, mid, max)
        $yTicks = [
            ['val' => 0, 'y' => $padTop + $plotH],
            ['val' => round($maxVal / 2), 'y' => $padTop + ($plotH / 2)],
            ['val' => $maxVal, 'y' => $padTop],
        ];

        // Format tick labels
        $formatY = static function ($v) use ($ySuffix) {
            if ($v >= 1000000) {
                return round($v / 1000000, 1) . 'M' . $ySuffix;
            }
            if ($v >= 1000) {
                return round($v / 1000, 1) . 'k' . $ySuffix;
            }
            return (string) $v . $ySuffix;
        };

        // Date labels (<= 6 labels evenly distributed)
        // Reference primary series for date labels
        $refPoints = $normalizedSeries[0]['points'];
        $nRef = count($refPoints);
        $xLabels = [];
        if ($nRef > 0) {
            $maxLabels = min(6, $nRef);
            $step = ($nRef - 1) / max(1, $maxLabels - 1);
            for ($i = 0; $i < $maxLabels; $i++) {
                $idx = (int) round($i * $step);
                $idx = min($idx, $nRef - 1);
                $pt = $refPoints[$idx] ?? null;
                if (!$pt) {
                    continue;
                }
                $xPos = $padLeft + ($nRef > 1 ? ($idx / ($nRef - 1)) * $plotW : ($plotW / 2));
                $dLabel = (string) ($pt['label'] ?? '');
                if ($dLabel === '' && !empty($pt['date'])) {
                    $ts = strtotime((string) $pt['date']);
                    $dLabel = $ts ? date('d/m', $ts) : (string) $pt['date'];
                }
                $xLabels[] = [
                    'x'     => round($xPos, 1),
                    'label' => $dLabel,
                ];
            }
        }

        // Build SVG elements
        $xml = [];
        $xml[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="100%%" height="%d" role="img" aria-label="%s" class="laca-google-chart-svg">',
            $width,
            $height,
            $height,
            esc_attr($ariaLabel)
        );

        // Grid lines (horizontal)
        $xml[] = '<g class="laca-chart-grid" stroke="var(--laca-border)" stroke-width="1" stroke-dasharray="2,2">';
        foreach ($yTicks as $yt) {
            $xml[] = sprintf(
                '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" />',
                $padLeft,
                $yt['y'],
                $width - $padRight,
                $yt['y']
            );
        }
        $xml[] = '</g>';

        // Y-axis tick labels
        $xml[] = '<g class="laca-chart-axis-y" font-size="10" fill="var(--laca-fg-subtle)" text-anchor="end" font-family="sans-serif">';
        foreach ($yTicks as $yt) {
            $xml[] = sprintf(
                '<text x="%d" y="%.1f" dy="3">%s</text>',
                $padLeft - 6,
                $yt['y'],
                esc_html($formatY($yt['val']))
            );
        }
        $xml[] = '</g>';

        // X-axis tick labels
        $xml[] = '<g class="laca-chart-axis-x" font-size="10" fill="var(--laca-fg-subtle)" text-anchor="middle" font-family="sans-serif">';
        foreach ($xLabels as $xl) {
            $xml[] = sprintf(
                '<text x="%.1f" y="%d">%s</text>',
                $xl['x'],
                $height - 6,
                esc_html($xl['label'])
            );
        }
        $xml[] = '</g>';

        // Draw each series
        foreach ($normalizedSeries as $sIndex => $s) {
            $pts = $s['points'];
            $nPts = count($pts);
            if ($nPts === 0) {
                continue;
            }

            $isDashed = ($s['style'] === 'dashed');
            $strokeColor = $isDashed ? 'var(--laca-fg-subtle)' : 'var(--laca-fg)';
            $strokeDash = $isDashed ? ' stroke-dasharray="4,4"' : '';
            $strokeWidth = $isDashed ? '1.5' : '2';

            $coords = [];
            foreach ($pts as $pIdx => $p) {
                $val = (float) ($p['value'] ?? 0);
                $x = $padLeft + ($nPts > 1 ? ($pIdx / ($nPts - 1)) * $plotW : ($plotW / 2));
                $ratio = ($maxVal > $minVal) ? ($val - $minVal) / ($maxVal - $minVal) : 0;
                $y = ($padTop + $plotH) - ($ratio * $plotH);

                $dLabel = (string) ($p['label'] ?? '');
                if ($dLabel === '' && !empty($p['date'])) {
                    $ts = strtotime((string) $p['date']);
                    $dLabel = $ts ? date('d/m', $ts) : (string) $p['date'];
                }
                $formattedVal = number_format_i18n($val);
                $coords[] = [
                    'x'     => round($x, 1),
                    'y'     => round($y, 1),
                    'title' => ($dLabel !== '' ? $dLabel . ': ' : '') . $formattedVal . $ySuffix,
                ];
            }

            // Path d string
            $d = '';
            foreach ($coords as $cIdx => $c) {
                $d .= ($cIdx === 0 ? 'M' : ' L') . $c['x'] . ' ' . $c['y'];
            }

            $xml[] = sprintf(
                '<path d="%s" fill="none" stroke="%s" stroke-width="%s"%s stroke-linecap="round" stroke-linejoin="round" class="laca-chart-line%s" />',
                $d,
                $strokeColor,
                $strokeWidth,
                $strokeDash,
                $isDashed ? ' laca-chart-line--dashed' : ' laca-chart-line--solid'
            );

            // Points / circles with <title>
            $showCircles = $opts['showPoints'] ?? true;
            if ($showCircles) {
                $xml[] = sprintf(
                    '<g class="laca-chart-points" fill="%s">',
                    $isDashed ? 'var(--laca-fg-subtle)' : 'var(--laca-fg)'
                );
                foreach ($coords as $c) {
                    $xml[] = sprintf(
                        '<circle cx="%.1f" cy="%.1f" r="3"><title>%s</title></circle>',
                        $c['x'],
                        $c['y'],
                        esc_html($c['title'])
                    );
                }
                $xml[] = '</g>';
            }
        }

        $xml[] = '</svg>';

        return implode("\n", $xml);
    }
}
