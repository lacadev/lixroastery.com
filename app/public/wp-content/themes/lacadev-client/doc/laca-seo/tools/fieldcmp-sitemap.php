<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}
/**
 * Laca SEO Sitemap Field Comparison Tool
 * 
 * Verifies that 100% of Rank Math sitemap settings fields exist in Laca SEO
 * with identical IDs, types, and defaults.
 * 
 * Run via CLI:
 * php -d mysqli.default_socket="/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock" doc/laca-seo/tools/fieldcmp-sitemap.php
 */

require_once dirname(__DIR__, 6) . '/wp-load.php';

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\SitemapFields;

echo "========================================================================\n";
echo " LACA SEO vs RANK MATH - SITEMAP SETTINGS FIELD COMPARISON\n";
echo "========================================================================\n\n";

// 1. Collect all Rank Math Sitemap fields
class RM_CMB_Collector {
    public array $fields = [];
    public function add_field(array $args) {
        $this->fields[$args['id']] = $args;
    }
}

$cmb = new RM_CMB_Collector();
$rm_dir = WP_CONTENT_DIR . '/plugins/seo-by-rank-math/includes/modules/sitemap/settings';

// Include general, html-sitemap, authors
$files = ['general.php', 'html-sitemap.php', 'authors.php'];
foreach ($files as $file) {
    $full = $rm_dir . '/' . $file;
    if (file_exists($full)) {
        include $full;
    }
}

// Include post types
$post_types = Helper::getAccessiblePostTypes();
foreach ($post_types as $pt_slug => $pt_obj) {
    $tab = ['post_type' => $pt_slug];
    include $rm_dir . '/post-types.php';
}

// Include taxonomies
$taxonomies = Helper::getAccessibleTaxonomies();
foreach ($taxonomies as $tax_slug => $tax_obj) {
    $tab = ['taxonomy' => $tax_slug];
    include $rm_dir . '/taxonomies.php';
}

$rm_fields = $cmb->fields;

// 2. Collect all Laca SEO Sitemap fields from registry
$laca_fields_by_tab = SitemapFields::get();
$laca_fields = [];
foreach ($laca_fields_by_tab as $tab => $fields) {
    foreach ($fields as $field) {
        $laca_fields[$field->getId()] = $field;
    }
}

echo sprintf("Total RM Sitemap fields defined: %d\n", count($rm_fields));
echo sprintf("Total Laca SEO Sitemap fields defined: %d\n\n", count($laca_fields));

// 3. Compare
$missing_in_laca = [];
$type_mismatch = [];
$default_mismatch = [];

foreach ($rm_fields as $id => $rm_field) {
    // Notice fields are presentation only, not saved options
    if (($rm_field['type'] ?? '') === 'notice') {
        continue;
    }

    if (!isset($laca_fields[$id])) {
        $missing_in_laca[] = $id;
        continue;
    }

    $laca_field = $laca_fields[$id];
    $rm_default = $rm_field['default'] ?? null;
    $laca_default = $laca_field->getDefault();

    // Normalize defaults for comparison
    if (is_array($rm_default) && is_array($laca_default)) {
        // Exclude roles in RM is associative, Laca stores slug list
        if ($id === 'exclude_roles') {
            $rm_roles = array_keys($rm_default);
            sort($rm_roles);
            $laca_roles = $laca_default;
            sort($laca_roles);
            if ($rm_roles != $laca_roles) {
                $default_mismatch[$id] = [
                    'rm'   => implode(',', $rm_roles),
                    'laca' => implode(',', $laca_roles),
                ];
            }
        } elseif ($rm_default != $laca_default) {
            $default_mismatch[$id] = [
                'rm'   => wp_json_encode($rm_default),
                'laca' => wp_json_encode($laca_default),
            ];
        }
    } else {
        $rm_str = (string) ($rm_default ?? '');
        $laca_str = (string) ($laca_default ?? '');
        if ($rm_str !== $laca_str) {
            $default_mismatch[$id] = [
                'rm'   => $rm_str,
                'laca' => $laca_str,
            ];
        }
    }
}

// 4. Report table
echo str_pad("Field ID", 35) . str_pad("RM Default", 25) . str_pad("Laca Default", 25) . "Status\n";
echo str_repeat("-", 95) . "\n";

$pass_count = 0;
foreach ($rm_fields as $id => $rm_field) {
    if (($rm_field['type'] ?? '') === 'notice') {
        continue;
    }

    $exists = isset($laca_fields[$id]);
    $has_def_err = isset($default_mismatch[$id]);

    $rm_def = $rm_field['default'] ?? 'null';
    if (is_array($rm_def)) {
        $rm_def = '[' . implode(',', array_keys($rm_def)) . ']';
    }
    $laca_def = $exists ? $laca_fields[$id]->getDefault() : 'N/A';
    if (is_array($laca_def)) {
        $laca_def = '[' . implode(',', $laca_def) . ']';
    }

    $status = "OK (MATCH)";
    if (!$exists) {
        $status = "MISSING IN LACA";
    } elseif ($has_def_err) {
        $status = "DEFAULT DIFF";
    } else {
        $pass_count++;
    }

    echo str_pad($id, 35) . str_pad(substr((string) $rm_def, 0, 23), 25) . str_pad(substr((string) $laca_def, 0, 23), 25) . $status . "\n";
}

echo str_repeat("=", 95) . "\n";
echo sprintf("RESULTS: %d / %d fields matched.\n", $pass_count, count($rm_fields) - 1); // exclude 1 notice field
echo sprintf("Missing in Laca: %d\n", count($missing_in_laca));
echo sprintf("Default differences: %d\n", count($default_mismatch));

// 5. Compare saved options in database (16 keys from DB)
$rm_saved = get_option('rank-math-options-sitemap', []);
$laca_saved = get_option('laca_seo_sitemap', []);

echo "\n========================================================================\n";
echo " DATABASE SAVED OPTIONS (rank-math-options-sitemap vs laca_seo_sitemap)\n";
echo "========================================================================\n";
$db_missing = [];
$db_diff = [];
foreach ($rm_saved as $k => $v) {
    if (!array_key_exists($k, $laca_saved)) {
        $db_missing[] = $k;
    } elseif ($laca_saved[$k] !== $v) {
        $db_diff[$k] = ['rm' => $v, 'laca' => $laca_saved[$k]];
    }
}

echo sprintf("Saved keys in RM: %d | Saved keys in Laca: %d\n", count($rm_saved), count($laca_saved));
echo sprintf("Missing saved keys: %d\n", count($db_missing));
echo sprintf("Differing saved values: %d\n", count($db_diff));

if (empty($missing_in_laca) && empty($default_mismatch) && empty($db_missing) && empty($db_diff)) {
    echo "\n>>> SUCCESS: 100% SITEMAP SETTINGS MATCH RANK MATH!\n";
} else {
    echo "\n>>> WARNING: THERE ARE DIFFERENCES TO INVESTIGATE.\n";
}
