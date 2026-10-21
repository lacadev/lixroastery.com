<?php
// Công cụ dev chạy bằng PHP-CLI — chặn truy cập qua web.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

/**
 * Laca SEO Phase 2: Compare Meta Tool (CLI)
 * 
 * Tests 3 real posts (journal, product, page) + 1 term (product_cat).
 * 1. Backups original meta values.
 * 2. Writes identical meta sets to rank_math_* and _laca_seo_*.
 * 3. Compares <head> output between Rank Math and Laca SEO (?laca_seo_force_frontend=1).
 * 4. Completely restores original meta values.
 */

require_once dirname(__DIR__, 6) . '/wp-load.php';

echo "========================================================================\n";
echo " LACA SEO PHASE 2 - COMPARE META VERIFICATION TOOL\n";
echo "========================================================================\n\n";

// Target Objects
$targets = [
    'journal' => [
        'type' => 'post',
        'url'  => home_url('/journal/extremely-light-cua-lix-roastery/'),
        'id'   => 0,
    ],
    'product' => [
        'type' => 'post',
        'url'  => home_url('/product/aconchego-1-masterpieces-auction-2025/'),
        'id'   => 0,
    ],
    'page' => [
        'type' => 'post',
        'url'  => home_url('/manifesto/'),
        'id'   => 0,
    ],
    'product_cat' => [
        'type'     => 'term',
        'taxonomy' => 'product_cat',
        'url'      => home_url('/product-category/ca-phe-specialty/'),
        'id'       => 0,
    ],
];

// Resolve IDs
$targets['journal']['id'] = url_to_postid($targets['journal']['url']);
$targets['product']['id'] = url_to_postid($targets['product']['url']);
$targets['page']['id']    = url_to_postid($targets['page']['url']);

$catTerm = get_term_by('slug', 'ca-phe-specialty', 'product_cat');
if ($catTerm) {
    $targets['product_cat']['id'] = $catTerm->term_id;
}

// Fallback search if url_to_postid is 0
if ($targets['journal']['id'] === 0) {
    $p = get_page_by_path('extremely-light-cua-lix-roastery', OBJECT, 'journal');
    if ($p) $targets['journal']['id'] = $p->ID;
}
if ($targets['product']['id'] === 0) {
    $p = get_page_by_path('aconchego-1-masterpieces-auction-2025', OBJECT, 'product');
    if ($p) $targets['product']['id'] = $p->ID;
}
if ($targets['page']['id'] === 0) {
    $p = get_page_by_path('manifesto', OBJECT, 'page');
    if ($p) $targets['page']['id'] = $p->ID;
}

foreach ($targets as $key => $t) {
    if (empty($t['id'])) {
        echo "[ERROR] Could not resolve ID for target: {$key}\n";
        exit(1);
    }
}

// Test Metadata Set to Inject
$testAttachmentId = 886;
$testAttachmentUrl = wp_get_attachment_url($testAttachmentId) ?: home_url('/wp-content/uploads/2026/09/202610070904-cafe-show-3c74.webp');

$testMeta = [
    'title'                 => 'Cà Phê Thượng Hạng Lix - Thử Nghiệm Meta Laca',
    'description'           => 'Khám phá hương vị cà phê specialty hảo hạng được rang mộc tỉ mỉ tại Lix Roastery.',
    'robots'                => ['noindex', 'nofollow', 'noarchive'],
    'advanced_robots'       => [
        'max-snippet'       => 150,
        'max-video-preview' => 30,
        'max-image-preview' => 'large',
    ],
    'canonical_url'         => home_url('/nguon-goc-chuan-test/'),
    'breadcrumb_title'      => 'Tiêu Đề Breadcrumb Chuẩn',
    'facebook_title'        => 'Tiêu Đề Chia Sẻ Facebook Mẫu',
    'facebook_description'  => 'Mô tả hấp dẫn khi chia sẻ lên mạng xã hội Facebook.',
    'facebook_image'        => $testAttachmentUrl,
    'facebook_image_id'     => $testAttachmentId,
    'twitter_use_facebook'  => 'off',
    'twitter_title'         => 'Tiêu Đề Twitter Chuẩn',
    'twitter_description'   => 'Mô tả ngắn gọn cho thẻ Twitter Card.',
    'twitter_image'         => $testAttachmentUrl,
    'twitter_image_id'      => $testAttachmentId,
];

// Backup Storage
$backups = [];

function backupAndInjectPost(int $postId, array $testMeta, array &$backups): void {
    $metaKeys = [
        'title'                 => ['rank_math_title', '_laca_seo_title'],
        'description'           => ['rank_math_description', '_laca_seo_description'],
        'robots'                => ['rank_math_robots', '_laca_seo_robots'],
        'advanced_robots'       => ['rank_math_advanced_robots', '_laca_seo_advanced_robots'],
        'canonical_url'         => ['rank_math_canonical_url', '_laca_seo_canonical_url'],
        'breadcrumb_title'      => ['rank_math_breadcrumb_title', '_laca_seo_breadcrumb_title'],
        'facebook_title'        => ['rank_math_facebook_title', '_laca_seo_facebook_title'],
        'facebook_description'  => ['rank_math_facebook_description', '_laca_seo_facebook_description'],
        'facebook_image'        => ['rank_math_facebook_image', '_laca_seo_facebook_image'],
        'facebook_image_id'     => ['rank_math_facebook_image_id', '_laca_seo_facebook_image_id'],
        'twitter_use_facebook'  => ['rank_math_twitter_use_facebook', '_laca_seo_twitter_use_facebook'],
        'twitter_title'         => ['rank_math_twitter_title', '_laca_seo_twitter_title'],
        'twitter_description'   => ['rank_math_twitter_description', '_laca_seo_twitter_description'],
        'twitter_image'         => ['rank_math_twitter_image', '_laca_seo_twitter_image'],
        'twitter_image_id'      => ['rank_math_twitter_image_id', '_laca_seo_twitter_image_id'],
    ];

    $backups["post_{$postId}"] = [];

    foreach ($metaKeys as $field => $keys) {
        [$rmKey, $lacaKey] = $keys;
        $backups["post_{$postId}"][$rmKey] = get_post_meta($postId, $rmKey, true);
        $backups["post_{$postId}"][$lacaKey] = get_post_meta($postId, $lacaKey, true);

        update_post_meta($postId, $rmKey, $testMeta[$field]);
        update_post_meta($postId, $lacaKey, $testMeta[$field]);
    }
}

function restorePost(int $postId, array $backups): void {
    if (!isset($backups["post_{$postId}"])) return;
    foreach ($backups["post_{$postId}"] as $key => $val) {
        if ($val !== '' && $val !== false && $val !== null) {
            update_post_meta($postId, $key, $val);
        } else {
            delete_post_meta($postId, $key);
        }
    }
}

function backupAndInjectTerm(int $termId, array $testMeta, array &$backups): void {
    $metaKeys = [
        'title'                 => ['rank_math_title', '_laca_seo_title'],
        'description'           => ['rank_math_description', '_laca_seo_description'],
        'robots'                => ['rank_math_robots', '_laca_seo_robots'],
        'advanced_robots'       => ['rank_math_advanced_robots', '_laca_seo_advanced_robots'],
        'canonical_url'         => ['rank_math_canonical_url', '_laca_seo_canonical_url'],
        'breadcrumb_title'      => ['rank_math_breadcrumb_title', '_laca_seo_breadcrumb_title'],
        'facebook_title'        => ['rank_math_facebook_title', '_laca_seo_facebook_title'],
        'facebook_description'  => ['rank_math_facebook_description', '_laca_seo_facebook_description'],
        'facebook_image'        => ['rank_math_facebook_image', '_laca_seo_facebook_image'],
        'facebook_image_id'     => ['rank_math_facebook_image_id', '_laca_seo_facebook_image_id'],
        'twitter_use_facebook'  => ['rank_math_twitter_use_facebook', '_laca_seo_twitter_use_facebook'],
        'twitter_title'         => ['rank_math_twitter_title', '_laca_seo_twitter_title'],
        'twitter_description'   => ['rank_math_twitter_description', '_laca_seo_twitter_description'],
        'twitter_image'         => ['rank_math_twitter_image', '_laca_seo_twitter_image'],
        'twitter_image_id'      => ['rank_math_twitter_image_id', '_laca_seo_twitter_image_id'],
    ];

    $backups["term_{$termId}"] = [];

    foreach ($metaKeys as $field => $keys) {
        [$rmKey, $lacaKey] = $keys;
        $backups["term_{$termId}"][$rmKey] = get_term_meta($termId, $rmKey, true);
        $backups["term_{$termId}"][$lacaKey] = get_term_meta($termId, $lacaKey, true);

        update_term_meta($termId, $rmKey, $testMeta[$field]);
        update_term_meta($termId, $lacaKey, $testMeta[$field]);
    }
}

function restoreTerm(int $termId, array $backups): void {
    if (!isset($backups["term_{$termId}"])) return;
    foreach ($backups["term_{$termId}"] as $key => $val) {
        if ($val !== '' && $val !== false && $val !== null) {
            update_term_meta($termId, $key, $val);
        } else {
            delete_term_meta($termId, $key);
        }
    }
}

function fetchHeadHtml(string $url): string {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) LacaSeoCompareMeta/1.0');
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return '';
    if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $m)) {
        return $m[1];
    }
    return '';
}

function parseTags(string $head): array {
    $tags = [
        'title'          => '',
        'description'    => '',
        'robots'         => '',
        'canonical'      => '',
        'og:title'       => '',
        'og:description' => '',
        'og:image'       => '',
        'twitter:title'  => '',
        'twitter:description' => '',
        'twitter:image'  => '',
    ];

    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $m)) {
        $tags['title'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['description'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['robots'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['canonical'] = trim($m[1]);
    }
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['og:title'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['og:description'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['og:image'] = trim($m[1]);
    }
    if (preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['twitter:title'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<meta[^>]+name=["\']twitter:description["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['twitter:description'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']*)["\']/is', $head, $m)) {
        $tags['twitter:image'] = trim($m[1]);
    }

    return $tags;
}

try {
    // 1. Inject test meta
    echo "1. Injecting test meta into 3 posts + 1 term...\n";
    backupAndInjectPost($targets['journal']['id'], $testMeta, $backups);
    backupAndInjectPost($targets['product']['id'], $testMeta, $backups);
    backupAndInjectPost($targets['page']['id'], $testMeta, $backups);
    backupAndInjectTerm($targets['product_cat']['id'], $testMeta, $backups);

    echo "   ✓ Meta injected successfully with backups created.\n\n";

    // 2. Fetch and compare heads
    $allMatch = true;

    foreach ($targets as $label => $t) {
        $url = $t['url'];
        $forceUrl = add_query_arg('laca_seo_force_frontend', '1', $url);

        $rmHead = fetchHeadHtml($url);
        $lacaHead = fetchHeadHtml($forceUrl);

        $rmTags = parseTags($rmHead);
        $lacaTags = parseTags($lacaHead);

        echo "------------------------------------------------------------------------\n";
        echo " Target: [{$label}] {$url}\n";
        echo "------------------------------------------------------------------------\n";

        $targetMismatch = 0;
        foreach ($rmTags as $tagKey => $rmVal) {
            $lacaVal = $lacaTags[$tagKey] ?? '';

            // Normalize whitespace
            $normRm = preg_replace('/\s+/', ' ', trim($rmVal));
            $normLaca = preg_replace('/\s+/', ' ', trim($lacaVal));

            if ($normRm !== $normLaca) {
                // Robots order insensitive compare
                if ($tagKey === 'robots') {
                    $p1 = array_map('trim', explode(',', strtolower($normRm)));
                    $p2 = array_map('trim', explode(',', strtolower($normLaca)));
                    sort($p1);
                    sort($p2);
                    if ($p1 === $p2) {
                        continue;
                    }
                }

                $allMatch = false;
                $targetMismatch++;
                echo "  [MISMATCH] {$tagKey}:\n";
                echo "     RM  : {$normRm}\n";
                echo "     LACA: {$normLaca}\n";
            }
        }

        if ($targetMismatch === 0) {
            echo "  ✓ ALL TAGS MATCH PERFECTLY (10/10 tags identical)\n";
        }
        echo "\n";
    }

    if ($allMatch) {
        echo "========================================================================\n";
        echo " RESULT: SUCCESS (4/4 targets MATCH Rank Math output 100%)\n";
        echo "========================================================================\n";
    } else {
        echo "========================================================================\n";
        echo " RESULT: SOME MISMATCHES DETECTED\n";
        echo "========================================================================\n";
    }

} finally {
    // 3. Always restore original metadata
    echo "\n3. Restoring original metadata for all targets...\n";
    restorePost($targets['journal']['id'], $backups);
    restorePost($targets['product']['id'], $backups);
    restorePost($targets['page']['id'], $backups);
    restoreTerm($targets['product_cat']['id'], $backups);
    echo "   ✓ All original metadata successfully restored!\n";
}
