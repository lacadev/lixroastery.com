<?php

namespace App\Features\LacaSeo\Admin\ListScreens;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Custom columns for Taxonomy Term lists.
 */
class TermList
{
    public function register(): void
    {
        $taxonomies = Helper::getAccessibleTaxonomies();

        foreach ($taxonomies as $slug => $obj) {
            $enabled = (string) Settings::get("titles.tax_{$slug}_add_meta_box", 'on');
            if ($enabled === 'on') {
                add_filter("manage_edit-{$slug}_columns", [$this, 'addColumns']);
                add_filter("manage_{$slug}_custom_column", [$this, 'renderColumn'], 10, 3);
            }
        }
    }

    /**
     * Add SEO columns.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addColumns(array $columns): array
    {
        $columns['laca_seo']       = __('SEO', 'lacadev');
        $columns['laca_seo_title'] = __('Tiêu đề SEO', 'lacadev');
        $columns['laca_seo_desc']  = __('Mô tả SEO', 'lacadev');
        return $columns;
    }

    /**
     * Render column content for term.
     *
     * @param string $content
     * @param string $columnName
     * @param int $termId
     * @return string
     */
    public function renderColumn(string $content, string $columnName, int $termId): string
    {
        switch ($columnName) {
            case 'laca_seo':
                $score = (int) get_term_meta($termId, '_laca_seo_seo_score', true);
                $kw = (string) get_term_meta($termId, '_laca_seo_focus_keyword', true);
                $robots = (array) get_term_meta($termId, '_laca_seo_robots', true);
                $isNoindex = in_array('noindex', $robots, true);

                $ratingClass = match (true) {
                    $score >= 80 => 'good',
                    $score >= 50 => 'ok',
                    default      => 'bad',
                };

                $out = '<div class="laca-list-seo-cell">';
                if ($score > 0) {
                    $out .= '<span class="laca-list-score laca-list-score--' . esc_attr($ratingClass) . '">' . esc_html($score) . '/100</span>';
                } else {
                    $out .= '<span class="laca-list-score laca-list-score--bad">N/A</span>';
                }
                if ($isNoindex) {
                    $out .= ' <span class="laca-list-badge laca-list-badge--noindex">Noindex</span>';
                }
                if ($kw !== '') {
                    $firstKw = explode(',', $kw)[0];
                    $out .= '<div class="laca-list-kw">' . esc_html($firstKw) . '</div>';
                }
                $out .= '</div>';
                return $out;

            case 'laca_seo_title':
                $customTitle = (string) get_term_meta($termId, '_laca_seo_title', true);
                if ($customTitle !== '') {
                    return esc_html($customTitle);
                }
                return '<span style="color:#94a3b8;font-style:italic;">' . esc_html__('— Theo mẫu chung —', 'lacadev') . '</span>';

            case 'laca_seo_desc':
                $customDesc = (string) get_term_meta($termId, '_laca_seo_description', true);
                if ($customDesc !== '') {
                    return esc_html(wp_trim_words($customDesc, 15, '...'));
                }
                return '<span style="color:#94a3b8;font-style:italic;">' . esc_html__('— Theo mẫu chung —', 'lacadev') . '</span>';
        }

        return $content;
    }
}
