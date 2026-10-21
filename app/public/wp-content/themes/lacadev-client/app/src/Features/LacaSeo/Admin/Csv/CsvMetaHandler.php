<?php

namespace App\Features\LacaSeo\Admin\Csv;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * CSV Import/Export handler for post/term SEO metadata.
 * Exports UTF-8 with BOM.
 * Imports via batch AJAX (100 rows per batch) with error reporting.
 */
class CsvMetaHandler
{
    public const COLUMNS = [
        'id',
        'object_type',
        'slug',
        'seo_title',
        'seo_description',
        'is_pillar_content',
        'focus_keyword',
        'seo_score',
        'robots',
        'advanced_robots',
        'canonical_url',
        'primary_term',
        'social_facebook_title',
        'social_facebook_description',
        'social_facebook_thumbnail',
        'social_twitter_title',
        'social_twitter_description',
        'social_twitter_thumbnail',
    ];

    public function register(): void
    {
        add_action('admin_post_laca_seo_csv_export', [$this, 'handleExport']);
        add_action('wp_ajax_laca_seo_csv_import_batch', [$this, 'ajaxImportBatch']);
    }

    /**
     * Export CSV file with UTF-8 BOM.
     */
    public function handleExport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'lacadev'));
        }

        check_admin_referer('laca_seo_csv_export_action', 'laca_seo_csv_export_nonce');

        $objectType = sanitize_key($_POST['csv_object_type'] ?? 'post');

        if (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'laca-seo-meta-' . $objectType . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM
        fwrite($output, "\xEF\xBB\xBF");

        // Header
        fputcsv($output, self::COLUMNS);

        if (taxonomy_exists($objectType)) {
            // Taxonomy terms export
            $terms = get_terms([
                'taxonomy'   => $objectType,
                'hide_empty' => false,
            ]);

            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $robots = (array) get_term_meta($t->term_id, '_laca_seo_robots', true);
                    $adv = (array) get_term_meta($t->term_id, '_laca_seo_advanced_robots', true);
                    $advStr = !empty($adv) ? wp_json_encode($adv) : '';

                    $row = [
                        $t->term_id,
                        $objectType,
                        $t->slug,
                        (string) get_term_meta($t->term_id, '_laca_seo_title', true),
                        (string) get_term_meta($t->term_id, '_laca_seo_description', true),
                        '',
                        (string) get_term_meta($t->term_id, '_laca_seo_focus_keyword', true),
                        (int) get_term_meta($t->term_id, '_laca_seo_seo_score', true),
                        implode(', ', $robots),
                        $advStr,
                        (string) get_term_meta($t->term_id, '_laca_seo_canonical_url', true),
                        '',
                        (string) get_term_meta($t->term_id, '_laca_seo_facebook_title', true),
                        (string) get_term_meta($t->term_id, '_laca_seo_facebook_description', true),
                        (string) get_term_meta($t->term_id, '_laca_seo_facebook_image', true),
                        (string) get_term_meta($t->term_id, '_laca_seo_twitter_title', true),
                        (string) get_term_meta($t->term_id, '_laca_seo_twitter_description', true),
                        (string) get_term_meta($t->term_id, '_laca_seo_twitter_image', true),
                    ];
                    fputcsv($output, $row);
                }
            }
        } else {
            // Post type export
            $posts = get_posts([
                'post_type'      => $objectType,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
            ]);

            foreach ($posts as $p) {
                $robots = (array) get_post_meta($p->ID, '_laca_seo_robots', true);
                $adv = (array) get_post_meta($p->ID, '_laca_seo_advanced_robots', true);
                $advStr = !empty($adv) ? wp_json_encode($adv) : '';

                $primaryTax = (string) \App\Features\LacaSeo\Core\Settings::get("titles.pt_{$objectType}_primary_taxonomy", 'off');
                $primaryTermVal = ($primaryTax !== 'off' && $primaryTax !== '') ? (string) get_post_meta($p->ID, "_laca_seo_primary_{$primaryTax}", true) : '';

                $row = [
                    $p->ID,
                    $objectType,
                    $p->post_name,
                    (string) get_post_meta($p->ID, '_laca_seo_title', true),
                    (string) get_post_meta($p->ID, '_laca_seo_description', true),
                    (string) get_post_meta($p->ID, '_laca_seo_is_pillar_content', true),
                    (string) get_post_meta($p->ID, '_laca_seo_focus_keyword', true),
                    (int) get_post_meta($p->ID, '_laca_seo_seo_score', true),
                    implode(', ', $robots),
                    $advStr,
                    (string) get_post_meta($p->ID, '_laca_seo_canonical_url', true),
                    $primaryTermVal,
                    (string) get_post_meta($p->ID, '_laca_seo_facebook_title', true),
                    (string) get_post_meta($p->ID, '_laca_seo_facebook_description', true),
                    (string) get_post_meta($p->ID, '_laca_seo_facebook_image', true),
                    (string) get_post_meta($p->ID, '_laca_seo_twitter_title', true),
                    (string) get_post_meta($p->ID, '_laca_seo_twitter_description', true),
                    (string) get_post_meta($p->ID, '_laca_seo_twitter_image', true),
                ];
                fputcsv($output, $row);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX batch import (receives 100 rows per call).
     */
    public function ajaxImportBatch(): void
    {
        check_ajax_referer('laca_seo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $rows = $_POST['rows'] ?? [];
        if (!is_array($rows) || empty($rows)) {
            wp_send_json_success(['updated' => 0, 'errors' => []]);
            return;
        }

        $updated = 0;
        $errors = [];

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) continue;

            $id = (int) ($row['id'] ?? 0);
            $objectType = sanitize_key($row['object_type'] ?? 'post');
            $slug = sanitize_title($row['slug'] ?? '');

            $targetId = 0;
            $isTerm = taxonomy_exists($objectType);

            if ($isTerm) {
                if ($id > 0 && get_term($id, $objectType)) {
                    $targetId = $id;
                } elseif ($slug !== '') {
                    $term = get_term_by('slug', $slug, $objectType);
                    if ($term) $targetId = $term->term_id;
                }

                if ($targetId <= 0) {
                    $errors[] = "Dòng " . ($rowIndex + 1) . ": Không tìm thấy term ID {$id} hoặc slug {$slug}";
                    continue;
                }

                // Update term meta
                $this->updateTermMetaFromRow($targetId, $row);
                $updated++;
            } else {
                if ($id > 0 && get_post($id)) {
                    $targetId = $id;
                } elseif ($slug !== '') {
                    $posts = get_posts([
                        'name'        => $slug,
                        'post_type'   => $objectType,
                        'post_status' => 'any',
                        'numberposts' => 1,
                    ]);
                    if (!empty($posts)) $targetId = $posts[0]->ID;
                }

                if ($targetId <= 0) {
                    $errors[] = "Dòng " . ($rowIndex + 1) . ": Không tìm thấy bài viết ID {$id} hoặc slug {$slug}";
                    continue;
                }

                // Update post meta
                $this->updatePostMetaFromRow($targetId, $row);
                $updated++;
            }
        }

        wp_send_json_success([
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }

    private function updatePostMetaFromRow(int $postId, array $row): void
    {
        $map = [
            'seo_title'                   => '_laca_seo_title',
            'seo_description'             => '_laca_seo_description',
            'focus_keyword'               => '_laca_seo_focus_keyword',
            'social_facebook_title'       => '_laca_seo_facebook_title',
            'social_facebook_description' => '_laca_seo_facebook_description',
            'social_twitter_title'        => '_laca_seo_twitter_title',
            'social_twitter_description'  => '_laca_seo_twitter_description',
        ];

        foreach ($map as $csvKey => $metaKey) {
            if (isset($row[$csvKey])) {
                $val = sanitize_text_field(trim((string) $row[$csvKey]));
                if ($val !== '') {
                    update_post_meta($postId, $metaKey, $val);
                } else {
                    delete_post_meta($postId, $metaKey);
                }
            }
        }

        // URLs with esc_url_raw
        $urlMap = [
            'canonical_url'             => '_laca_seo_canonical_url',
            'social_facebook_thumbnail' => '_laca_seo_facebook_image',
            'social_twitter_thumbnail'  => '_laca_seo_twitter_image',
        ];
        foreach ($urlMap as $csvKey => $metaKey) {
            if (isset($row[$csvKey])) {
                $val = esc_url_raw(trim((string) $row[$csvKey]));
                if ($val !== '') {
                    update_post_meta($postId, $metaKey, $val);
                } else {
                    delete_post_meta($postId, $metaKey);
                }
            }
        }

        // Pillar content (only accept 'on' or '1')
        if (isset($row['is_pillar_content'])) {
            $val = trim((string) $row['is_pillar_content']);
            if ($val === 'on' || $val === '1') {
                update_post_meta($postId, '_laca_seo_is_pillar_content', 'on');
            } else {
                delete_post_meta($postId, '_laca_seo_is_pillar_content');
            }
        }

        // Primary Term
        if (isset($row['primary_term'])) {
            $termId = absint($row['primary_term']);
            $post = get_post($postId);
            if ($post) {
                $pt = $post->post_type;
                $primaryTax = (string) \App\Features\LacaSeo\Core\Settings::get("titles.pt_{$pt}_primary_taxonomy", 'off');
                if ($primaryTax !== 'off' && $primaryTax !== '') {
                    $primaryKey = "_laca_seo_primary_{$primaryTax}";
                    if ($termId > 0) {
                        update_post_meta($postId, $primaryKey, $termId);
                    } else {
                        delete_post_meta($postId, $primaryKey);
                    }
                }
            }
        }

        // SEO Score
        if (isset($row['seo_score']) && is_numeric($row['seo_score'])) {
            $score = absint($row['seo_score']);
            if ($score > 0) {
                update_post_meta($postId, '_laca_seo_seo_score', $score);
            }
        }

        // Robots
        if (isset($row['robots'])) {
            $rawRobots = explode(',', (string) $row['robots']);
            $cleanRobots = array_filter(array_map('trim', $rawRobots));
            if (!empty($cleanRobots)) {
                update_post_meta($postId, '_laca_seo_robots', array_values($cleanRobots));
            } else {
                delete_post_meta($postId, '_laca_seo_robots');
            }
        }

        // Advanced Robots JSON
        if (isset($row['advanced_robots']) && $row['advanced_robots'] !== '') {
            $adv = json_decode((string) $row['advanced_robots'], true);
            if (is_array($adv)) {
                $cleanAdv = [
                    'max-snippet'       => isset($adv['max-snippet']) ? (int) $adv['max-snippet'] : -1,
                    'max-video-preview' => isset($adv['max-video-preview']) ? (int) $adv['max-video-preview'] : -1,
                    'max-image-preview' => in_array($adv['max-image-preview'] ?? '', ['large', 'standard', 'none'], true) ? $adv['max-image-preview'] : 'large',
                ];
                update_post_meta($postId, '_laca_seo_advanced_robots', $cleanAdv);
            }
        }
    }

    private function updateTermMetaFromRow(int $termId, array $row): void
    {
        $map = [
            'seo_title'                   => '_laca_seo_title',
            'seo_description'             => '_laca_seo_description',
            'focus_keyword'               => '_laca_seo_focus_keyword',
            'social_facebook_title'       => '_laca_seo_facebook_title',
            'social_facebook_description' => '_laca_seo_facebook_description',
            'social_twitter_title'        => '_laca_seo_twitter_title',
            'social_twitter_description'  => '_laca_seo_twitter_description',
        ];

        foreach ($map as $csvKey => $metaKey) {
            if (isset($row[$csvKey])) {
                $val = sanitize_text_field(trim((string) $row[$csvKey]));
                if ($val !== '') {
                    update_term_meta($termId, $metaKey, $val);
                } else {
                    delete_term_meta($termId, $metaKey);
                }
            }
        }

        // URLs with esc_url_raw
        $urlMap = [
            'canonical_url'             => '_laca_seo_canonical_url',
            'social_facebook_thumbnail' => '_laca_seo_facebook_image',
            'social_twitter_thumbnail'  => '_laca_seo_twitter_image',
        ];
        foreach ($urlMap as $csvKey => $metaKey) {
            if (isset($row[$csvKey])) {
                $val = esc_url_raw(trim((string) $row[$csvKey]));
                if ($val !== '') {
                    update_term_meta($termId, $metaKey, $val);
                } else {
                    delete_term_meta($termId, $metaKey);
                }
            }
        }

        if (isset($row['seo_score']) && is_numeric($row['seo_score'])) {
            $score = absint($row['seo_score']);
            if ($score > 0) {
                update_term_meta($termId, '_laca_seo_seo_score', $score);
            }
        }

        if (isset($row['robots'])) {
            $rawRobots = explode(',', (string) $row['robots']);
            $cleanRobots = array_filter(array_map('trim', $rawRobots));
            if (!empty($cleanRobots)) {
                update_term_meta($termId, '_laca_seo_robots', array_values($cleanRobots));
            } else {
                delete_term_meta($termId, '_laca_seo_robots');
            }
        }

        // Advanced Robots JSON
        if (isset($row['advanced_robots']) && $row['advanced_robots'] !== '') {
            $adv = json_decode((string) $row['advanced_robots'], true);
            if (is_array($adv)) {
                $cleanAdv = [
                    'max-snippet'       => isset($adv['max-snippet']) ? (int) $adv['max-snippet'] : -1,
                    'max-video-preview' => isset($adv['max-video-preview']) ? (int) $adv['max-video-preview'] : -1,
                    'max-image-preview' => in_array($adv['max-image-preview'] ?? '', ['large', 'standard', 'none'], true) ? $adv['max-image-preview'] : 'large',
                ];
                update_term_meta($termId, '_laca_seo_advanced_robots', $cleanAdv);
            }
        }
    }
}
