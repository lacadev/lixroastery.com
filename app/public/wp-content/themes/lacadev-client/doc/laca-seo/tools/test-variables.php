<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web (file nằm trong thư mục theme công khai).
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

/**
 * CLI Test tool for comparing Laca SEO Variables vs RankMath\Helper::replace_vars()
 * Tests across 1 real Product (ID 29), 1 real Journal post, and 1 real term product_cat.
 * Usage: php -d "mysqli.default_socket=/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock" doc/laca-seo/tools/test-variables.php
 */

require_once dirname(__DIR__, 6) . '/wp-load.php';

// Suppress duplicate registration warnings from Rank Math mock loops
error_reporting(E_ALL & ~E_USER_WARNING);

use App\Features\LacaSeo\Core\Variables as LacaVars;
use RankMath\Helper as RMHelper;

if (isset(rank_math()->variables)) {
    rank_math()->variables->setup();
}

echo "========================================================================================================\n";
echo " LACA SEO vs RANK MATH - VARIABLES COMPARISON TEST (Phase 1)\n";
echo "========================================================================================================\n\n";

// 1. Product (ID 29)
$productPost = get_post(29);
if (!$productPost) {
    echo "ERROR: Product ID 29 not found.\n";
    exit(1);
}

// 2. Journal post
$journalPosts = get_posts([
    'post_type'      => 'journal',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
]);
if (empty($journalPosts)) {
    echo "ERROR: No journal post found.\n";
    exit(1);
}
$journalPost = $journalPosts[0];

// 3. Term product_cat
$productTerms = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'number'     => 1,
]);
if (empty($productTerms) || is_wp_error($productTerms)) {
    $productTerms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 1]);
}
$productTerm = reset($productTerms);

echo "Context 1 (Product): ID {$productPost->ID} - \"{$productPost->post_title}\" (type: {$productPost->post_type})\n";
echo "Context 2 (Journal): ID {$journalPost->ID} - \"{$journalPost->post_title}\" (type: {$journalPost->post_type})\n";
echo "Context 3 (Term):    ID {$productTerm->term_id} - \"{$productTerm->name}\" (tax: {$productTerm->taxonomy})\n\n";

$testItems = [
    // Standard basic
    '%sep%'                               => ['context' => 'product'],
    '%sitename%'                          => ['context' => 'product'],
    '%sitedesc%'                          => ['context' => 'product'],
    '%currentdate%'                       => ['context' => 'product'],
    '%currentday%'                        => ['context' => 'product'],
    '%currentmonth%'                      => ['context' => 'product'],
    '%currentyear%'                       => ['context' => 'product'],
    '%currenttime%'                       => ['context' => 'product'],
    '%currenttime(H:i)%'                  => ['context' => 'product'],
    '%org_name%'                          => ['context' => 'product'],
    '%org_url%'                           => ['context' => 'product'],
    '%org_logo%'                          => ['context' => 'product'],

    // Product ID 29 specific & WooCommerce
    '%title%'                             => ['context' => 'product'],
    '%id%'                                => ['context' => 'product'],
    '%date%'                              => ['context' => 'product'],
    '%date(F jS, Y)%'                     => ['context' => 'product'],
    '%modified%'                          => ['context' => 'product'],
    '%modified(d/m/Y)%'                   => ['context' => 'product'],
    '%category%'                          => ['context' => 'product'],
    '%categories%'                        => ['context' => 'product'],
    '%categories(limit=2&separator= | )%' => ['context' => 'product'],
    '%tag%'                               => ['context' => 'product'],
    '%tags%'                              => ['context' => 'product'],
    '%tags(limit=1)%'                     => ['context' => 'product'],
    '%customterm(product_cat)%'           => ['context' => 'product'],
    '%customterm_desc(product_cat)%'      => ['context' => 'product'],
    '%term_product_cat%'                  => ['context' => 'product'],
    '%customfield(_price)%'               => ['context' => 'product'],
    '%wc_price%'                          => ['context' => 'product'],
    '%wc_sku%'                            => ['context' => 'product'],
    '%focuskw%'                           => ['context' => 'product'],
    '%pt_single%'                         => ['context' => 'product'],
    '%pt_plural%'                         => ['context' => 'product'],
    '%parent_title%'                      => ['context' => 'product'],
    '%primary_taxonomy_terms%'            => ['context' => 'product'],
    '%filename%'                          => ['context' => 'product'],
    '%count(title)%'                      => ['context' => 'product'],
    '%url%'                               => ['context' => 'product'],
    '%excerpt%'                           => ['context' => 'product'],

    // Journal post specific
    'journal:%title%'                     => ['context' => 'journal', 'tag' => '%title%'],
    'journal:%id%'                        => ['context' => 'journal', 'tag' => '%id%'],
    'journal:%date(F jS, Y)%'             => ['context' => 'journal', 'tag' => '%date(F jS, Y)%'],
    'journal:%modified(d/m/Y)%'           => ['context' => 'journal', 'tag' => '%modified(d/m/Y)%'],
    'journal:%categories(limit=2)%'       => ['context' => 'journal', 'tag' => '%categories(limit=2)%'],
    'journal:%tags(limit=1)%'             => ['context' => 'journal', 'tag' => '%tags(limit=1)%'],
    'journal:%pt_single%'                 => ['context' => 'journal', 'tag' => '%pt_single%'],
    'journal:%pt_plural%'                 => ['context' => 'journal', 'tag' => '%pt_plural%'],
    'journal:%name%'                      => ['context' => 'journal', 'tag' => '%name%'],
    'journal:%post_author%'               => ['context' => 'journal', 'tag' => '%post_author%'],
    'journal:%userid%'                    => ['context' => 'journal', 'tag' => '%userid%'],
    'journal:%excerpt%'                   => ['context' => 'journal', 'tag' => '%excerpt%'],

    // Term product_cat specific
    'term:%term%'                         => ['context' => 'term', 'tag' => '%term%'],
    'term:%term_description%'             => ['context' => 'term', 'tag' => '%term_description%'],
    'term:%category%'                     => ['context' => 'term', 'tag' => '%category%'],
    'term:%term% %sep% %sitename%'        => ['context' => 'term', 'tag' => '%term% %sep% %sitename%'],
];

$rows = [];
$total = 0;
$passed = 0;

foreach ($testItems as $label => $spec) {
    $total++;
    $pattern = $spec['tag'] ?? $label;
    $ctxType = $spec['context'];

    $ctxObj = match ($ctxType) {
        'journal' => $journalPost,
        'term'    => $productTerm,
        default   => $productPost,
    };

    // Set WordPress global queried object for RM context
    global $wp_query, $post;
    if ($ctxObj instanceof \WP_Post) {
        $post = $ctxObj;
        $wp_query->queried_object = $ctxObj;
        $wp_query->queried_object_id = $ctxObj->ID;
        $wp_query->is_singular = true;
        $wp_query->is_single = true;
    } elseif ($ctxObj instanceof \WP_Term) {
        $wp_query->queried_object = $ctxObj;
        $wp_query->queried_object_id = $ctxObj->term_id;
        $wp_query->is_tax = true;
        $wp_query->is_singular = false;
    }

    \RankMath\Replace_Variables\Replacer::$replacements_cache = [];
    rank_math()->variables = new \RankMath\Replace_Variables\Manager();
    rank_math()->variables->setup();

    // Register custom taxonomies if post
    if ($ctxObj instanceof \WP_Post) {
        $taxonomies = get_post_taxonomies($ctxObj->ID);
        foreach ($taxonomies as $taxonomy) {
            if (!in_array($taxonomy, ['category', 'post_tag'], true)) {
                rank_math_register_var_replacement(
                    "term_{$taxonomy}",
                    [
                        'name'        => "{$taxonomy} Title",
                        'description' => 'Custom Term title.',
                        'variable'    => "customterm({$taxonomy})",
                    ],
                    function() use ($taxonomy) {
                        return rank_math()->variables->get_custom_term($taxonomy);
                    }
                );
            }
        }
    }

    // Register woocommerce extra vars if product
    if ($ctxObj instanceof \WP_Post && $ctxObj->post_type === 'product' && class_exists('\RankMath\WooCommerce\WC_Vars')) {
        (new \RankMath\WooCommerce\WC_Vars())->register();
    }

    $rmVal = trim((string) RMHelper::replace_vars($pattern, $ctxObj));
    $lacaVal = trim((string) LacaVars::replace($pattern, $ctxObj));

    $ok = ($rmVal === $lacaVal);
    if ($ok) {
        $passed++;
    }

    $rows[] = [
        'var'    => $label,
        'rm'     => $rmVal,
        'laca'   => $lacaVal,
        'status' => $ok ? 'OK' : 'DIFF',
    ];
}

printf("%-36s | %-28s | %-28s | %-6s\n", "Biến / Biểu thức", "Rank Math", "Laca SEO", "Status");
echo str_repeat('-', 106) . "\n";

foreach ($rows as $row) {
    $rmShort = mb_strimwidth($row['rm'], 0, 28, '...');
    $lacaShort = mb_strimwidth($row['laca'], 0, 28, '...');
    printf("%-36s | %-28s | %-28s | %-6s\n", $row['var'], $rmShort, $lacaShort, $row['status']);
}

echo "\n" . str_repeat('-', 106) . "\n";
echo "SUMMARY: {$passed} / {$total} tests PASSED (" . ($total - $passed) . " DIFF)\n";
echo str_repeat('-', 106) . "\n\n";

if ($passed === $total) {
    echo "🎉 100% MATCH: 0 difference between Laca SEO and Rank Math replace_vars!\n";
    exit(0);
} else {
    echo "⚠️ Discrepancies detected:\n";
    foreach ($rows as $row) {
        if ($row['status'] === 'DIFF') {
            echo " - Variable: {$row['var']}\n";
            echo "   RM:   \"{$row['rm']}\"\n";
            echo "   LACA: \"{$row['laca']}\"\n";
        }
    }
    exit(1);
}
