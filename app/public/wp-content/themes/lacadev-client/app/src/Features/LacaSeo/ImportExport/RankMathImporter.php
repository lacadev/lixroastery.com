<?php

namespace App\Features\LacaSeo\ImportExport;

use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Migrates data from Rank Math SEO (Free + Pro) to Laca SEO 1:1.
 */
class RankMathImporter
{
    /**
     * Check if Rank Math data exists in database.
     */
    public static function hasRankMathData(): bool
    {
        global $wpdb;

        $hasOption = (bool) $wpdb->get_var("SELECT 1 FROM {$wpdb->options} WHERE option_name LIKE 'rank-math-options-%' OR option_name = 'rank_math_modules' LIMIT 1");
        if ($hasOption) {
            return true;
        }

        $hasPostMeta = (bool) $wpdb->get_var("SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key LIKE 'rank_math_%' LIMIT 1");
        return $hasPostMeta;
    }

    /**
     * Safely read Rank Math option from database, repairing PHP serialized string length if needed.
     */
    public static function getRankMathOption(string $optionName): array
    {
        global $wpdb;

        $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $optionName));
        if (empty($raw)) {
            return [];
        }

        $res = @unserialize($raw);
        if (!is_array($res)) {
            // Repair string lengths corrupted by domain search-replace
            $fixed = preg_replace_callback('!s:(\d+):"(.*?)";!s', static function ($m) {
                return 's:' . strlen($m[2]) . ':"' . $m[2] . '";';
            }, $raw);

            $res = @unserialize($fixed);
        }

        if (!is_array($res)) {
            error_log(sprintf('[laca-seo] Rank Math option "%s" is corrupted and could not be unserialized.', $optionName));
            return [];
        }

        // Validate: if array has only numerical keys or looks corrupted like [0 => ...], verify
        if (count($res) === 1 && isset($res[0]) && !is_array($res[0])) {
            error_log(sprintf('[laca-seo] Rank Math option "%s" contains invalid single non-assoc index.', $optionName));
            return [];
        }

        return $res;
    }

    /**
     * Import Rank Math Settings options.
     */
    public static function importSettings(): array
    {
        Importer::createBackup('Trước khi nhập cài đặt từ Rank Math');

        $imported = [];

        // 1. General settings
        $rmGeneral = self::getRankMathOption('rank-math-options-general');
        if (!empty($rmGeneral)) {
            $lacaGeneral = Settings::getGroup('general');
            foreach ($rmGeneral as $k => $v) {
                $lacaGeneral[$k] = $v;
            }

            // Keys in Laca registry that are missing in RM General should be set to empty/default (e.g. robots_txt_content)
            $generalRegistry = Settings::getRegistryMap()['general'] ?? [];
            foreach ($generalRegistry as $key => $field) {
                if (!array_key_exists($key, $rmGeneral)) {
                    $lacaGeneral[$key] = $field->getDefault();
                }
            }

            Settings::updateGroup('general', $lacaGeneral);
            $imported['general'] = count($rmGeneral);
        }

        // 2. Titles settings
        $rmTitles = self::getRankMathOption('rank-math-options-titles');
        if (!empty($rmTitles)) {
            $lacaTitles = Settings::getGroup('titles');
            foreach ($rmTitles as $k => $v) {
                $lacaTitles[$k] = $v;
            }

            // Mô phỏng đúng hành vi RM khi option KHÔNG có key (taxonomy/post type tạo sau lần
            // lưu settings cuối của RM) để chuyển đổi không đổi output live (đối chiếu compare-head):
            // - taxonomy: mọi key thiếu ≡ "rỗng" (toggle off, chuỗi '') → title core WP, không Slack…
            // - post type: title/description single vẫn dùng mẫu mặc định; archive_* rỗng để
            //   Paper Archive tự fallback "%pt_plural% Archive…" như RM.
            // Ghi đè luôn giá trị Laca cũ vì import gộp lên option hiện có.
            $registry = Settings::getRegistryMap()['titles'] ?? [];
            $emptyOf = static function ($field) {
                $default = $field->getDefault();
                if ($field->getType() === 'toggle') {
                    return 'off';
                }
                return is_array($default) ? [] : '';
            };
            foreach (array_keys(\App\Features\LacaSeo\Core\Helper::getAccessibleTaxonomies()) as $tax) {
                foreach ($registry as $key => $field) {
                    $isTaxKey = str_starts_with($key, "tax_{$tax}_") || $key === "remove_{$tax}_snippet_data";
                    if ($isTaxKey && !array_key_exists($key, $rmTitles)) {
                        $lacaTitles[$key] = $emptyOf($field);
                    }
                }
            }
            foreach (array_keys(\App\Features\LacaSeo\Core\Helper::getAccessiblePostTypes()) as $pt) {
                foreach (['title' => true, 'description' => true, 'archive_title' => false, 'archive_description' => false] as $suffix => $useDefault) {
                    $key = "pt_{$pt}_{$suffix}";
                    if (!array_key_exists($key, $rmTitles) && isset($registry[$key])) {
                        $lacaTitles[$key] = $useDefault ? $registry[$key]->getDefault() : '';
                    }
                }
            }

            Settings::updateGroup('titles', $lacaTitles);
            $imported['titles'] = count($rmTitles);
        }

        // 3. Sitemap settings
        $rmSitemap = self::getRankMathOption('rank-math-options-sitemap');
        if (!empty($rmSitemap)) {
            // For any public post type or taxonomy not present in RM sitemap settings,
            // set to 'off' so they do not fall back to registry default 'on'.
            foreach (array_keys(\App\Features\LacaSeo\Core\Helper::getAccessiblePostTypes()) as $pt) {
                $key = "pt_{$pt}_sitemap";
                if (!array_key_exists($key, $rmSitemap)) {
                    $rmSitemap[$key] = 'off';
                }
            }
            foreach (array_keys(\App\Features\LacaSeo\Core\Helper::getAccessibleTaxonomies()) as $tax) {
                $key = "tax_{$tax}_sitemap";
                if (!array_key_exists($key, $rmSitemap)) {
                    $rmSitemap[$key] = 'off';
                }
            }

            update_option('laca_seo_sitemap', $rmSitemap, false);
            $imported['sitemap'] = count($rmSitemap);
        }

        // 4. Instant indexing settings
        $rmIndexing = self::getRankMathOption('rank-math-options-instant-indexing');
        if (!empty($rmIndexing)) {
            update_option('laca_seo_instant_indexing', $rmIndexing, false);
            $imported['instant_indexing'] = count($rmIndexing);
        }

        // 5. Active modules
        $rmModules = get_option('rank_math_modules', []);
        if (is_array($rmModules) && !empty($rmModules)) {
            Modules::setActive($rmModules);
            $imported['modules'] = count($rmModules);
        }

        Settings::clearCache();

        return $imported;
    }

    /**
     * Import Post Meta in batches of 200 posts.
     * Maps rank_math_{x} => _laca_seo_{x}
     *
     * @param int $offset
     * @param int $limit
     * @return array{processed:int,remaining:int}
     */
    public static function importPostMetaBatch(int $offset = 0, int $limit = 200): array
    {
        global $wpdb;

        $excludeKeys = [
            'rank_math_internal_links_processed',
            'rank_math_analytic_object_id',
            'rank_math_contentai_score',
            'rank_math_og_content_image',
        ];
        $excludeSql = "'" . implode("','", $excludeKeys) . "'";

        // Find rows to migrate
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE 'rank_math_%'
              AND meta_key NOT IN ($excludeSql)
            ORDER BY post_id ASC
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        if (empty($rows)) {
            return ['processed' => 0, 'remaining' => 0];
        }

        foreach ($rows as $row) {
            $sub = substr($row->meta_key, strlen('rank_math_'));
            $lacaKey = '_laca_seo_' . $sub;

            // Check if post already has this laca_seo meta
            $existing = get_post_meta($row->post_id, $lacaKey, true);
            if ($existing === '' || $existing === false) {
                $val = maybe_unserialize($row->meta_value);
                update_post_meta($row->post_id, $lacaKey, $val);
            }
        }

        // Check if Woo GTIN code exists
        $gtinRows = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value FROM {$wpdb->postmeta}
            WHERE meta_key = '_rank_math_gtin_code'
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        foreach ($gtinRows as $grow) {
            update_post_meta($grow->post_id, '_laca_seo_gtin_code', $grow->meta_value);
        }

        $total = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key LIKE 'rank_math_%'
              AND meta_key NOT IN ($excludeSql)
        ");

        $remaining = max(0, $total - ($offset + count($rows)));

        return [
            'processed' => count($rows),
            'remaining' => $remaining,
        ];
    }

    /**
     * Import Term Meta.
     */
    public static function importTermMeta(): int
    {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT term_id, meta_key, meta_value
            FROM {$wpdb->termmeta}
            WHERE meta_key LIKE 'rank_math_%'
        ");

        $count = 0;
        foreach ($rows as $row) {
            $sub = substr($row->meta_key, strlen('rank_math_'));
            $lacaKey = '_laca_seo_' . $sub;

            $existing = get_term_meta($row->term_id, $lacaKey, true);
            if ($existing === '' || $existing === false) {
                $val = maybe_unserialize($row->meta_value);
                update_term_meta($row->term_id, $lacaKey, $val);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Import User Meta.
     */
    public static function importUserMeta(): int
    {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT user_id, meta_key, meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key LIKE 'rank_math_%'
        ");

        $count = 0;
        foreach ($rows as $row) {
            $sub = substr($row->meta_key, strlen('rank_math_'));
            $lacaKey = '_laca_seo_' . $sub;

            $existing = get_user_meta($row->user_id, $lacaKey, true);
            if ($existing === '' || $existing === false) {
                $val = maybe_unserialize($row->meta_value);
                update_user_meta($row->user_id, $lacaKey, $val);
                $count++;
            }
        }

        return $count;
    }
}
